<?php

// Builds the "download all my data" JSON export for account/download/index.php.
// Modeled on AccountActions::deleteAccount()'s cross-schema table sweep (the
// codebase's existing precedent for "every table this account touches"), but
// read-only and split into three deliberately-scoped buckets instead of one
// flat dump:
//
//   - account / team: the account's own identity row and their team's identity row.
//   - personal_data: every row across every schema these two constant classes
//     touch (session.*, team.*, account.*, management.*, moderation.*,
//     cooldown.*, correlation.*, verification.*, idealistic.*, etc. - see
//     discoverSchemas()) that is specifically attributable to this account
//     (via account_id, member_id, or created_by), discovered generically via
//     schema introspection rather than a hand-maintained table list, so new
//     tables are picked up automatically.
//   - team_configuration: team-wide settings (team_id present) - but ONLY from
//     tables that carry no per-member column at all. A table with both team_id
//     and account_id/member_id holds individual teammates' own data, not
//     shared config, and must never be swept team-wide here: this export is
//     scoped to the requesting account, not their colleagues.
//   - conversation_history: the idealistic.historyTotal row(s) belonging to
//     this account, plus their child content tables (prompts/replies/
//     attachments/links/web data), which only carry a history_id and so can't
//     be reached by the generic column sweep above - joined explicitly instead.
//
// A fixed set of internal/operational tables (error logs, queues, derived
// caches, rate-limit bookkeeping, catalog/definition tables) is skipped
// entirely - see EXCLUDED_TABLES. Columns that hold secrets (passwords,
// session/2FA tokens, verification codes) are never exported even on the
// account's own rows - see REDACTED_COLUMN_FRAGMENTS.

class IdealisticOfficeAccountExporter implements PhpAsyncSerializable
{
    private const EXCLUDED_TABLES = [
        // idealistic: internal/operational, not meaningful personal content
        IdealisticOfficeTable::ERRORS_TABLE,
        IdealisticOfficeTable::ENABLED_TASKS_TABLE,
        IdealisticOfficeTable::GMAIL_QUEUE_TABLE,
        IdealisticOfficeTable::GMAIL_TRACKING_TABLE,
        IdealisticOfficeTable::LATE_NOTIFICATIONS_TABLE,
        IdealisticOfficeTable::TEAM_ADMIN_BLOCKED_ACCESS,
        IdealisticOfficeTable::TEAM_CREDIT_CACHE_TABLE,
        IdealisticOfficeTable::HISTORY_PROCESSING_TIMES_TABLE,
        IdealisticOfficeTable::HISTORY_FAILED_OBJECTS_TABLE,
        IdealisticOfficeTable::HISTORY_ATTACHMENT_ACCESS_TABLE,
        IdealisticOfficeTable::PORTAL_ACCESS_TABLE,
        // account: catalogs/definitions and pure rate-limit/cache bookkeeping
        AccountVariables::FUNCTIONALITIES_TABLE,
        AccountVariables::ACCEPTED_ACCOUNTS_TABLE,
        AccountVariables::MODERATIONS_TABLE,
        AccountVariables::ROLES_TABLE,
        AccountVariables::ROLE_PERMISSIONS_TABLE,
        AccountVariables::TEAM_PERMISSION_DEFINITIONS_TABLE,
        AccountVariables::ACCOUNT_INSTANT_COOLDOWNS_TABLE,
        AccountVariables::ACCOUNT_BUFFER_COOLDOWNS_TABLE,
        AccountVariables::TRANSLATIONS_PROCESSED_TABLE,
        AccountVariables::EMBEDDINGS_PROCESSED_TABLE,
        AccountVariables::INSTRUCTIONS_LOCAL_TABLE,
        AccountVariables::INSTRUCTIONS_PUBLIC_TABLE,
        AccountVariables::INSTRUCTIONS_REPLACEMENTS_TABLE,
    ];

    // Handled explicitly by exportConversationHistory() instead of the generic
    // sweep, so they must be skipped there to avoid exporting them twice.
    private const CONVERSATION_TABLES = [
        IdealisticOfficeTable::HISTORY_TOTAL_TABLE,
        IdealisticOfficeTable::HISTORY_PROMPTS_TABLE,
        IdealisticOfficeTable::HISTORY_REPLIES_TABLE,
        IdealisticOfficeTable::HISTORY_CREATED_ATTACHMENTS_TABLE,
        IdealisticOfficeTable::HISTORY_USER_ATTACHMENTS_TABLE,
        IdealisticOfficeTable::HISTORY_LINKS_TABLE,
        IdealisticOfficeTable::HISTORY_WEB_DATA_TABLE,
    ];

    private const REDACTED_COLUMN_FRAGMENTS = [
        "password", "token", "code", "secret", "otp", "hash", "csrf",
    ];

    // Dropped entirely rather than redacted: internal AI-analysis bookkeeping
    // (embedding vectors/hashes, and the "_positions" character-offset arrays
    // that pair with analyzed_description/history_value text) that isn't
    // meaningful on its own without the analysis pipeline behind it.
    private const EXCLUDED_COLUMN_FRAGMENTS = [
        "_positions", "embedding",
    ];

    private const PERSONAL_IDENTITY_COLUMNS = ["account_id", "member_id", "created_by"];

    public static function export(int|string $accountId, int|string $teamId): stdClass
    {
        $result = new stdClass();
        $result->generated_date = get_current_date();
        $result->account = self::fetchOwnRow(AccountVariables::ACCOUNTS_TABLE, "id", $accountId);
        $result->team = self::fetchOwnRow(AccountVariables::TEAM_TABLE, "id", $teamId);
        $result->personal_data = array();
        $result->team_configuration = array();

        foreach (self::discoverSchemas() as $schema) {
            $result->personal_data += self::sweepPersonal($schema, $accountId);
            $result->team_configuration += self::sweepTeamConfiguration($schema, $teamId);
        }
        $result->conversation_history = self::exportConversationHistory($accountId);
        return $result;
    }

    // AccountVariables::* constants alone span roughly a dozen distinct schemas
    // (session.*, team.*, account.*, management.*, moderation.*, cooldown.*,
    // correlation.*, verification.*, instructions.*, translations.*,
    // embeddings.*) rather than one single "account" schema, plus
    // IdealisticOfficeTable::*'s own "idealistic" schema. Deriving the list
    // from the constants themselves - instead of hardcoding it - means a
    // schema is never silently missed here the way it is in
    // AccountActions::deleteAccount()'s caller, which only ever passes
    // ["account", "idealistic"].
    private static function discoverSchemas(): array
    {
        $schemas = array();

        foreach ([AccountVariables::class, IdealisticOfficeTable::class] as $class) {
            foreach ((new ReflectionClass($class))->getConstants() as $value) {
                if (is_string($value) && str_contains($value, ".")) {
                    $schemas[explode(".", $value, 2)[0]] = true;
                }
            }
        }
        return array_keys($schemas);
    }

    private static function fetchOwnRow(string $table, string $idColumn, int|string $id): ?stdClass
    {
        $rows = get_sql_query($table, null, array(array($idColumn, $id)), null, 1);

        if (empty($rows)) {
            return null;
        }
        return self::redactRow($rows[0], get_sql_database_columns($table));
    }

    private static function sweepPersonal(string $schema, int|string $accountId): array
    {
        $data = array();

        foreach (get_sql_database_tables($schema) as $bareTable) {
            $table = $schema . "." . $bareTable;

            if ($table === AccountVariables::ACCOUNTS_TABLE
                || $table === AccountVariables::TEAM_TABLE
                || in_array($table, self::EXCLUDED_TABLES, true)
                || in_array($table, self::CONVERSATION_TABLES, true)) {
                continue;
            }
            $columns = get_sql_database_columns($table);
            $matching = array_values(array_intersect(self::PERSONAL_IDENTITY_COLUMNS, $columns));

            if (empty($matching)) {
                continue;
            }
            $rows = get_sql_query($table, null, self::orWhereEquals($matching, $accountId));

            if (!empty($rows)) {
                $data[$table] = self::redactRows($rows, $columns);
            }
        }
        return $data;
    }

    private static function sweepTeamConfiguration(string $schema, int|string $teamId): array
    {
        $data = array();

        foreach (get_sql_database_tables($schema) as $bareTable) {
            $table = $schema . "." . $bareTable;

            if (in_array($table, self::EXCLUDED_TABLES, true)
                || in_array($table, self::CONVERSATION_TABLES, true)) {
                continue;
            }
            $columns = get_sql_database_columns($table);

            if (!in_array("team_id", $columns, true)
                || in_array("account_id", $columns, true)
                || in_array("member_id", $columns, true)) {
                continue;
            }
            $rows = get_sql_query($table, null, array(array("team_id", $teamId)));

            if (!empty($rows)) {
                $data[$table] = self::redactRows($rows, $columns);
            }
        }
        return $data;
    }

    private static function exportConversationHistory(int|string $accountId): array
    {
        $historyRows = get_sql_query(
            IdealisticOfficeTable::HISTORY_TOTAL_TABLE,
            null,
            array(array("account_id", $accountId))
        );

        if (empty($historyRows)) {
            return array();
        }
        $historyIds = array_values(array_unique(array_map(
            static fn($row) => $row->id,
            $historyRows
        )));
        $data = array(
            IdealisticOfficeTable::HISTORY_TOTAL_TABLE => self::redactRows(
                $historyRows,
                get_sql_database_columns(IdealisticOfficeTable::HISTORY_TOTAL_TABLE)
            ),
        );

        foreach (self::CONVERSATION_TABLES as $table) {
            if ($table === IdealisticOfficeTable::HISTORY_TOTAL_TABLE) {
                continue;
            }
            $rows = self::rowsForHistoryIds($table, $historyIds);

            if (!empty($rows)) {
                $data[$table] = self::redactRows($rows, get_sql_database_columns($table));
            }
        }
        return $data;
    }

    private static function rowsForHistoryIds(string $table, array $historyIds): array
    {
        $rows = array();

        // history_id has no IN()-style helper in get_sql_query(), and a single
        // account's set of history ids is small in practice, so this queries
        // per id rather than adding a new query primitive.
        foreach ($historyIds as $historyId) {
            $matches = get_sql_query($table, null, array(array("history_id", $historyId)));

            if (!empty($matches)) {
                array_push($rows, ...$matches);
            }
        }
        return $rows;
    }

    private static function orWhereEquals(array $columns, int|string $value): array
    {
        $where = array();
        $last = sizeof($columns) - 1;

        foreach ($columns as $i => $column) {
            $where[] = array($column, "=", $value, $i === $last ? 1 : 0);
        }
        return $where;
    }

    private static function redactRows(array $rows, array $columns): array
    {
        foreach ($rows as $row) {
            self::redactRow($row, $columns);
        }
        return $rows;
    }

    private static function redactRow(?stdClass $row, array $columns): ?stdClass
    {
        if ($row === null) {
            return null;
        }
        foreach ($columns as $column) {
            if (!property_exists($row, $column)) {
                continue;
            }
            $lower = strtolower($column);

            foreach (self::EXCLUDED_COLUMN_FRAGMENTS as $fragment) {
                if (str_contains($lower, $fragment)) {
                    unset($row->$column);
                    continue 2;
                }
            }
            if ($row->$column === null) {
                continue;
            }
            $redacted = false;

            foreach (self::REDACTED_COLUMN_FRAGMENTS as $fragment) {
                if (str_contains($lower, $fragment)) {
                    $row->$column = "[redacted]";
                    $redacted = true;
                    break;
                }
            }
            // A handful of legacy/scraped text fields (web content, older
            // messages) can carry invalid UTF-8 byte sequences, which makes
            // json_encode() fail for the entire export rather than just that
            // value. Scrub every string through the same sanitizer already
            // used before sending outgoing Meta messages, so one bad row can
            // never break the whole download.
            if (!$redacted && is_string($row->$column)) {
                $row->$column = IdealisticOfficeMeta::utf8Encode($row->$column) ?? "[invalid encoding]";
            }
        }
        return $row;
    }
}
