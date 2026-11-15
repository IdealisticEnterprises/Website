<?php

abstract class AbstractIdealisticOfficeTeam extends IdealisticOfficeJSON implements IdealisticOfficeParticipant
{

    private static array
        $users = array(),
        $roles = array(),
        $removedRoles = array();


    protected ?int $id;
    private IdealisticOfficeCredit $credit;

    public function __construct(?object $object, bool $json = true)
    {
        parent::__construct();
        $this->setConstruct($object, $json);
        $this->credit = new IdealisticOfficeCredit($this);
    }

    protected final function setConstruct(?object $object, bool $json = true): void
    {
        if ($object === null) {
            $this->id = null;

            if ($json) {
                self::setJSON(
                    array(
                        "id" => $this->id,
                        "name" => null,
                        "creation_date" => null,
                        "creation_time_zone" => null
                    )
                );
            }
        } else {
            $this->id = $object?->id;

            if ($json) {
                self::setJSON(
                    array(
                        "id" => $this->id,
                        "creation_date" => $this->getCreationDate(),
                        "creation_time_zone" => $this->getCreationDateTimeZone()
                    )
                );
            }
        }
    }

    // Separator

    public final function getTimezone(bool $json = true): IdealisticOfficeTimezone
    {
        return new IdealisticOfficeTimezone(
            $this,
            IdealisticOfficeVariable::DEFAULT_TIME_ZONE,
            $json
        );
    }

    // Separator

    public final function getPortal(
        int|string $reference,
        string     $column = "id",
        bool       $json = true,
        bool       $deleted = false
    ): ?IdealisticOfficePortal
    {
        if (!$this->hasEstablishedAccess()
            || $reference <= 0) {
            return null;
        }
        $isName = $column === "name";
        $query = get_sql_query(
            IdealisticOfficeTable::PORTALS_TABLE,
            IdealisticOfficeQuery::PORTAL,
            array(
                array("team_id", $this->id),
                $deleted
                    ? array("deletion_date", "IS NOT", null)
                    : array("deletion_date", null),
                array($isName ? "LOWER(" . $column . ")" : $column, $isName ? strtolower($reference) : $reference)
            ),
            null,
            1
        );

        if (empty($query)) {
            return null;
        } else {
            return new IdealisticOfficePortal(
                $this,
                $query[0]->id,
                $query[0]->created_by,
                $query[0]->name,
                $query[0]->password,
                $query[0]->random_id,
                $query[0]->default_url,
                $query[0]->downloads_allowed !== null,
                $query[0]->downloads_allowed !== null,
                $query[0]->skip_robot_verification !== null,
                $query[0]->is_18_plus !== null,
                $query[0]->require_login !== null,
                $query[0]->creation_date,
                $query[0]->creation_time_zone,
                $query[0]->default_message,
                $query[0]->transfer_to,
                $json
            );
        }
    }

    /** @return IdealisticOfficePortal[] */
    public final function getPortals(int $limit = 0, bool $json = true, bool $deleted = false): array
    {
        if (!$this->hasEstablishedAccess()) {
            return array();
        }
        $query = get_sql_query(
            IdealisticOfficeTable::PORTALS_TABLE,
            IdealisticOfficeQuery::PORTAL,
            array(
                array("team_id", $this->id),
                $deleted
                    ? array("deletion_date", "IS NOT", null)
                    : array("deletion_date", null)
            ),
            array(
                "DESC",
                "id"
            ),
            $limit <= 0
                ? IdealisticOfficeLimit::MAX_EXTENSIONS_PER_OBJECT_LARGE
                : min($limit, IdealisticOfficeLimit::MAX_EXTENSIONS_PER_OBJECT_LARGE)
        );

        if (!empty($query)) {
            foreach ($query as $key => $row) {
                $query[$key] = new IdealisticOfficePortal(
                    $this,
                    $row->id,
                    $row->created_by,
                    $row->name,
                    $row->password,
                    $row->random_id,
                    $row->default_url,
                    $row->downloads_allowed !== null,
                    $row->voice_replies !== null,
                    $row->skip_robot_verification !== null,
                    $row->is_18_plus !== null,
                    $row->require_login !== null,
                    $row->creation_date,
                    $row->creation_time_zone,
                    $row->default_message,
                    $row->transfer_to,
                    $json
                );
            }
        }
        return $query;
    }

    // Separator

    public final function getInstruction(int $id, bool $json = true, bool $deleted = false): ?IdealisticOfficeInstruction
    {
        if (!$this->hasEstablishedAccess()
            || $id <= 0) {
            return null;
        }
        $query = get_sql_query(
            IdealisticOfficeTable::TEAM_INSTRUCTIONS_TABLE,
            IdealisticOfficeQuery::INSTRUCTION,
            array(
                array("team_id", $this->id),
                $deleted
                    ? array("deletion_date", "IS NOT", null)
                    : array("deletion_date", null),
                array("id", $id)
            ),
            null,
            1
        );

        if (empty($query)) {
            return null;
        } else {
            return new IdealisticOfficeInstruction(
                $query[0]->id,
                $query[0]->created_by,
                $this,
                $query[0]->instruction,
                $query[0]->creation_date,
                $query[0]->creation_time_zone,
                $json
            );
        }
    }

    /** @return IdealisticOfficeInstruction[] */
    public final function getInstructions(int $limit = 0, bool $json = true, bool $deleted = false): array
    {
        if (!$this->hasEstablishedAccess()) {
            return array();
        }
        $query = get_sql_query(
            IdealisticOfficeTable::TEAM_INSTRUCTIONS_TABLE,
            IdealisticOfficeQuery::INSTRUCTION,
            array(
                array("team_id", $this->id),
                $deleted
                    ? array("deletion_date", "IS NOT", null)
                    : array("deletion_date", null)
            ),
            array(
                "DESC",
                "id"
            ),
            $limit <= 0
                ? IdealisticOfficeLimit::MAX_EXTENSIONS_PER_OBJECT_LARGE
                : min($limit, IdealisticOfficeLimit::MAX_EXTENSIONS_PER_OBJECT_LARGE)
        );

        if (!empty($query)) {
            $array = array();

            foreach ($query as $row) {
                $array[$row->id] = new IdealisticOfficeInstruction(
                    $row->id,
                    $row->created_by,
                    $this,
                    $row->instruction,
                    $row->creation_date,
                    $row->creation_time_zone,
                    $json
                );
            }
            return $array;
        }
        return array();
    }

    // Separator

    public final function getTimeAccess(bool $json = true): IdealisticOfficeTimeAccess
    {
        if (!$this->hasEstablishedAccess()) {
            $timezone = $this->getTimezone($json);
            return new IdealisticOfficeTimeAccess(
                0,
                new Account(
                    Account::IDEALISTIC_OFFICE_APPLICATION_ID,
                ),
                $this,
                null,
                IdealisticOfficeReader::getCurrentDate($timezone),
                -1,
                -1,
                -1,
                -1,
                $timezone->getTimeZone(),
                $json
            );
        }
        $query = get_sql_query(
            IdealisticOfficeTable::TEAM_TIME_ACCESS_TABLE,
            IdealisticOfficeQuery::TIME_ACCESS,
            array(
                array("team_id", $this->id),
            ),
            array(
                "DESC",
                "id"
            ),
            1
        );
        if (empty($query)) {
            $timezone = $this->getTimezone($json);
            return new IdealisticOfficeTimeAccess(
                0,
                0,
                $this,
                null,
                IdealisticOfficeReader::getCurrentDate($timezone),
                0,
                0,
                23,
                59,
                $timezone->getTimeZone(),
                $json
            );
        } else {
            return new IdealisticOfficeTimeAccess(
                $query[0]->id,
                $query[0]->created_by,
                $this,
                $query[0]->creation_reason,
                $query[0]->creation_date,
                (int)$query[0]->start_hour,
                (int)$query[0]->start_minutes,
                (int)$query[0]->end_hour,
                (int)$query[0]->end_minutes,
                $query[0]->creation_time_zone,
                $json
            );
        }
    }

    // Separator

    public function getWeekAccess(bool $json = true): IdealisticOfficeWeeklyAccess
    {
        if (!$this->hasEstablishedAccess()) {
            $timezone = $this->getTimezone($json);
            return new IdealisticOfficeWeeklyAccess(
                0,
                0,
                $this,
                null,
                IdealisticOfficeReader::getCurrentDate($timezone),
                $timezone->getTimeZone(),
                true,
                true,
                true,
                true,
                true,
                true,
                true,
                $json
            );
        }
        $query = get_sql_query(
            IdealisticOfficeTable::TEAM_WEEKLY_ACCESS_TABLE,
            IdealisticOfficeQuery::WEEK_ACCESS,
            array(
                array("team_id", $this->id)
            ),
            array(
                "DESC",
                "id"
            ),
            1
        );
        if (empty($query)) {
            $timezone = $this->getTimezone($json);
            return new IdealisticOfficeWeeklyAccess(
                0,
                0,
                $this,
                null,
                IdealisticOfficeReader::getCurrentDate($timezone),
                $timezone->getTimeZone(),
                true,
                true,
                true,
                true,
                true,
                true,
                true,
                $json
            );
        } else {
            return new IdealisticOfficeWeeklyAccess(
                $query[0]->id,
                $query[0]->created_by,
                $this,
                $query[0]->creation_reason,
                $query[0]->creation_date,
                $query[0]->creation_time_zone,
                $query[0]->monday_access !== null,
                $query[0]->tuesday_access !== null,
                $query[0]->wednesday_access !== null,
                $query[0]->thursday_access !== null,
                $query[0]->friday_access !== null,
                $query[0]->saturday_access !== null,
                $query[0]->sunday_access !== null,
                $json
            );
        }
    }

    // Separator

    public final function getLanguageSelection(bool $json = true): IdealisticOfficeLanguageSelection
    {
        return new IdealisticOfficeLanguageSelection(
            $this,
            IdealisticOfficeVariable::DEFAULT_LANGUAGE,
            $json
        );
    }

    public final function hasLanguageSelection(): bool
    {
        return false;
    }

    // Separator

    public final function getCredit(): IdealisticOfficeCredit
    {
        return $this->credit;
    }

    // Separator

    public final function hasEstablishedAccess(): bool
    {
        return $this->id !== null
            && $this->id > 0;
    }

    public final function getPartialManager(): ?AccountTeam
    {
        if ($this->hasEstablishedAccess()) {
            $account = new Account(Account::IDEALISTIC_OFFICE_APPLICATION_ID);
            $account->getTeam()->setTeamAdditionalID(AccountTeam::IDEALISTIC_OFFICE_ADDITIONAL_ID);
            $account->getTeam()->togglePermissions(false);
            $result = $account->getTeam()->findTeam($this->id);

            if ($result->isPositiveOutcome()) {
                $account->getTeam()->setForcedTeam($result->getObject());
                return $account->getTeam();
            }
        }
        return null;
    }

    public final function getRawObject(): ?object
    {
        if (!$this->hasEstablishedAccess()) {
            return null;
        }
        $account = new Account(Account::IDEALISTIC_OFFICE_APPLICATION_ID);
        $account->getTeam()->setTeamAdditionalID(AccountTeam::IDEALISTIC_OFFICE_ADDITIONAL_ID);
        $account->getTeam()->togglePermissions(false);
        $result = $account->getTeam()->findTeam($this->id);
        return $result->isPositiveOutcome() ? $result->getObject() : null;
    }

    public final function getId(): int|string
    {
        return $this->id ?? 0;
    }

    public final function getCreationDate(): ?string
    {
        return $this->getRawObject()?->creation_date;
    }

    public final function getCreationDateTimeZone(): string
    {
        return IdealisticOfficeVariable::DEFAULT_TIME_ZONE;
    }

    // Separator

    public final function getAvailablePermissions(): array
    {
        $array = $this->getPartialManager()?->getPermissionDefinitions();

        if (!empty($array)) {
            foreach ($array as $key => $value) {
                $array[$key] = strtolower($value->name);
            }
        }
        return $array;
    }

    public final function getDefaultPermissions(): array
    {
        $permissions = $this->getPartialManager()?->getTeamPermissions();

        if (!empty($permissions)) {
            foreach ($permissions as $key => $permission) {
                $permissions[$key] = strtolower($permission->definition->name);
            }
        }
        return $permissions;
    }

    public final function hasPermission(int|string $permission): IdealisticOfficeOutcome
    {
        $manager = $this->getPartialManager();

        if ($manager === null) {
            return new IdealisticOfficeOutcome(false, "Team manager not found.");
        }
        $outcome = $manager->getTeamPermission(
            $permission
        );
        return new IdealisticOfficeOutcome($outcome->isPositiveOutcome(), $outcome->getMessage());
    }

    public final function getPermissionName(int|string $permission): ?string
    {
        $manager = $this->getPartialManager();

        if ($manager === null) {
            return null;
        }
        $outcome = $manager->getPermissionDefinition(
            $permission
        );
        $name = $outcome?->name;

        if ($name === null) {
            return null;
        }
        return $name;
    }

    // Separator

    public final function getOwnerAccount(): ?Account
    {
        $manager = $this->getPartialManager();

        if ($manager !== null) {
            $owner = $manager->getOwner(true);

            if ($owner !== null) {
                return new Account(
                    Account::IDEALISTIC_OFFICE_APPLICATION_ID,
                    $owner
                );
            }
        }
        return null;
    }

    public final function getOwner(bool $json = true): ?IdealisticOfficeUser
    {
        $owner = $this->getOwnerAccount();

        if ($owner !== null) {
            $owner = $this->findUser($owner, $json);

            if ($owner instanceof IdealisticOfficeUser) {
                return $owner;
            }
        }
        return null;
    }

    public final function getOwnerId(): int|string|null
    {
        return $this->getOwnerAccount()?->getDetail("id");
    }

    // Separator

    public final function getHistoryIDs(
        array $extraColumns = [],
        int   $limit = IdealisticOfficeLimit::MAX_PROCESSED_ROWS,
        int   $previousLastQueryId = 0
    ): array
    {
        if (!$this->hasEstablishedAccess()) {
            return array();
        }
        $where = array(
            array("team_id", $this->id)
        );

        if ($previousLastQueryId > 0) {
            $where[] = array("id", "<", $previousLastQueryId);
        }
        $query = get_sql_query(
            IdealisticOfficeTable::HISTORY_TOTAL_TABLE,
            array_merge(array("id"), $extraColumns),
            $where,
            array(
                "DESC",
                "id"
            ),
            $limit
        );

        if (!empty($query)) {
            foreach ($query as $id => $row) {
                $query[$id] = (int)$row->id;
            }
        }
        return $query;
    }

    /** @return IdealisticOfficeHistory[] */
    public final function getHistory(
        int $limit = IdealisticOfficeLimit::MAX_PROCESSED_ROWS,
        int $previousLastQueryId = 0
    ): array
    {
        if (!$this->hasEstablishedAccess()) {
            return array();
        }
        $where = array(
            array("team_id", $this->id)
        );

        if ($previousLastQueryId > 0) {
            $where[] = array("id", "<", $previousLastQueryId);
        }
        $query = get_sql_query(
            IdealisticOfficeTable::HISTORY_TOTAL_TABLE,
            IdealisticOfficeQuery::HISTORY,
            $where,
            array(
                "DESC",
                "id"
            ),
            $limit
        );

        if (!empty($query)) {
            foreach ($query as $id => $row) {
                $query[$id] = new IdealisticOfficeHistory(
                    $this,
                    $row->id,
                    $row->platform_id,
                    $row->platform_user_id,
                    $row->platform_message_id,
                    $row->platform_chat_id,
                    $row->platform_chat_name,
                    $row->account_id,
                    $row->creation_date,
                    $row->creation_time_zone,
                    null
                );
            }
        }
        return $query;
    }

    public final function getHistoryById(
        int $id
    ): ?IdealisticOfficeHistory
    {
        $query = get_sql_query(
            IdealisticOfficeTable::HISTORY_TOTAL_TABLE,
            IdealisticOfficeQuery::HISTORY,
            array(
                array("team_id", $this->id),
                array("id", $id)
            ),
            null,
            1
        );

        if (!empty($query)) {
            $row = $query[0];
            return new IdealisticOfficeHistory(
                $this,
                $row->id,
                $row->platform_id,
                $row->platform_user_id,
                $row->platform_message_id,
                $row->platform_chat_id,
                $row->platform_chat_name,
                $row->account_id,
                $row->creation_date,
                $row->creation_time_zone,
                null
            );
        }
        return null;
    }

    public final function getLastHistoryID(): ?int
    {
        if (!$this->hasEstablishedAccess()) {
            return null;
        }
        $query = get_sql_query(
            IdealisticOfficeTable::HISTORY_TOTAL_TABLE,
            array("id"),
            array(
                array("team_id", $this->id)
            ),
            array(
                "DESC",
                "id"
            ),
            1
        );

        if (empty($query)) {
            return null;
        } else {
            $row = $query[0];
            return (int)$row->id;
        }
    }

    public final function getLastHistory(): ?IdealisticOfficeHistory
    {
        if (!$this->hasEstablishedAccess()) {
            return null;
        }
        $query = get_sql_query(
            IdealisticOfficeTable::HISTORY_TOTAL_TABLE,
            IdealisticOfficeQuery::HISTORY,
            array(
                array("team_id", $this->id)
            ),
            array(
                "DESC",
                "id"
            ),
            1
        );

        if (empty($query)) {
            return null;
        } else {
            $row = $query[0];
            return new IdealisticOfficeHistory(
                $this,
                $row->id,
                $row->platform_id,
                $row->platform_user_id,
                $row->platform_message_id,
                $row->platform_chat_id,
                $row->platform_chat_name,
                $row->account_id,
                $row->creation_date,
                $row->creation_time_zone,
                null
            );
        }
    }

    // Separator

    /** @return IdealisticOfficeUser[] */
    public final function getUsers(
        bool $json = true,
        int  $limit = 0
    ): array
    {
        $users = $this->getPartialManager()?->getMembers();

        if (empty($users)) {
            return array();
        } else {
            $array = array();
            $count = 0;

            foreach ($users as $user) {
                $accountID = $user->account_id;
                $user = $this->findUser(
                    $user->account,
                    $json
                );

                if ($user instanceof IdealisticOfficeUser) {
                    $array[(int)$accountID] = $user;
                    $count++;

                    if ($limit > 0
                        && $count === $limit) {
                        break;
                    }
                }
            }
            return $array;
        }
    }

    /** @return IdealisticOfficeRole[] */
    public final function getRoles(bool $json = true, bool $deleted = false): array
    {
        $roles = $this->getPartialManager()?->getTeamRoles($deleted);

        if (empty($roles)) {
            return array();
        } else {
            $array = array();

            foreach ($roles as $role) {
                $array[(int)$role->id] = new IdealisticOfficeRole(
                    $this,
                    $role->id,
                    $json
                );
            }
            return $array;
        }
    }

    public final function getLowestRole(bool $json = true): ?IdealisticOfficeRole
    {
        $role = $this->getPartialManager()?->getRookieRole();

        if ($role !== null) {
            return new IdealisticOfficeRole(
                $this,
                $role->id,
                $json
            );
        }
        return null;
    }

    // Separator

    public final function getFolder(int|string $idOrName, bool $customId = false, bool $json = true, bool $deleted = false): ?IdealisticOfficeFolder
    {
        if (!$this->hasEstablishedAccess()
            || is_numeric($idOrName) && $idOrName <= 0) {
            return null;
        }
        $query = get_sql_query(
            IdealisticOfficeTable::FOLDERS_TABLE,
            IdealisticOfficeQuery::FOLDER,
            array(
                is_numeric($idOrName)
                    ? array($customId ? "custom_id" : "id", $idOrName)
                    : array("LOWER(folder_name)", strtolower($idOrName)),
                $deleted
                    ? array("deletion_date", "IS NOT", null)
                    : array("deletion_date", null)
            ),
            null,
            1
        );

        if (empty($query)) {
            return null;
        } else {
            $row = $query[0];
            return new IdealisticOfficeFolder(
                $this,
                $row->id,
                $row->custom_id,
                $row->folder_name,
                $row->created_by,
                $row->chronological_order,
                $row->creation_date,
                $row->creation_time_zone,
                $row->creation_reason,
                $row->expiration_date,
                $row->expiration_time_zone,
                $row->last_modified_by,
                $row->last_modified_date,
                $row->last_modified_time_zone,
                $json
            );
        }
    }

    // Separator

    public final function findUserConditional(int|string $account, bool $json = true): IdealisticOfficeUser|IdealisticOfficeOutcome
    {
        if (is_email($account)) {
            $user = self::$users[$account] ?? null;

            if ($user !== null) {
                return $user;
            }
            return self::$users[$account] = $this->findUser(
                new Account(
                    Account::IDEALISTIC_OFFICE_APPLICATION_ID,
                    null,
                    $account
                ),
                $json
            );
        } else if (is_numeric($account)) {
            return $this->findUser(
                new Account(
                    Account::IDEALISTIC_OFFICE_APPLICATION_ID,
                    $account
                ),
                $json
            );
        } else {
            return new IdealisticOfficeOutcome(false, "Invalid account identifier.");
        }
    }

    public final function findUser(Account $account, bool $json = true, bool $cache = true): IdealisticOfficeUser|IdealisticOfficeOutcome
    {
        $hash = overflow_integer((string_to_integer($account->getDetail("id")) * 31) + boolean_to_integer($json));

        if ($cache) {
            $user = self::$users[$hash] ?? null;

            if ($user !== null) {
                return $user;
            }
        }
        if ($this->hasEstablishedAccess()
            && $account->exists()) {
            $user = new IdealisticOfficeUser($this, $account, $json);

            if ($user->hasEstablishedAccess()) {
                return self::$users[$hash] = $user;
            }
        }
        return self::$users[$hash] = new IdealisticOfficeOutcome(false, "Member not found in this team or in general.");
    }

    public static final function findUserDynamic(Account $account, bool $json = true, bool $cache = true): IdealisticOfficeUser|IdealisticOfficeOutcome
    {
        $hash = overflow_integer((string_to_integer($account->getDetail("id")) * 31) + boolean_to_integer($json));

        if ($cache) {
            $user = self::$users[$hash] ?? null;

            if ($user !== null) {
                return $user;
            }
        }
        if ($account->exists()) {
            $user = new IdealisticOfficeUser(null, $account, $json);

            if ($user->hasEstablishedAccess()) {
                return self::$users[$hash] = $user;
            }
        }
        return self::$users[$hash] = new IdealisticOfficeOutcome(false, "Member not found in this team or in general.");
    }

    public final function findRawRole(string|int $reference, bool $deleted = false): ?object
    {
        if ($this->hasEstablishedAccess()) {
            $partialManager = $this->getPartialManager();

            if ($partialManager !== null) {
                return $partialManager->getRole($reference, $deleted);
            }
        }
        return null;
    }

    public final function findRole(string|int $reference, bool $json = true, bool $cache = true): IdealisticOfficeRole|IdealisticOfficeOutcome
    {
        $hash = overflow_integer((string_to_integer($reference) * 31) + boolean_to_integer($json));

        if ($cache) {
            $role = self::$roles[$hash] ?? null;

            if ($role !== null) {
                return $role;
            }
        }
        $rawRole = $this->findRawRole($reference);

        if ($rawRole !== null) {
            return self::$roles[$hash] = new IdealisticOfficeRole($this, $rawRole->id, $json);
        } else {
            return self::$roles[$hash] = new IdealisticOfficeOutcome(false, "Role not found in this team.");
        }
    }

    public final function findRemovedRole(string|int $reference, bool $json = true, bool $cache = true): IdealisticOfficeRole|IdealisticOfficeOutcome
    {
        $hash = overflow_integer((string_to_integer($reference) * 31) + boolean_to_integer($json));

        if ($cache) {
            $role = self::$removedRoles[$hash] ?? null;

            if ($role !== null) {
                return $role;
            }
        }
        $rawRole = $this->findRawRole($reference, true);

        if ($rawRole !== null) {
            return self::$removedRoles[$hash] = new IdealisticOfficeRole($this, $rawRole->id, $json, true);
        } else {
            return self::$removedRoles[$hash] = new IdealisticOfficeOutcome(false, "Removed role not found in this team.");
        }
    }

    // Separator

    public final function getPaypalEmail(int|string $idOrEmail, bool $json = true): ?IdealisticOfficePaypalEmail
    {
        if (!$this->hasEstablishedAccess()
            || is_numeric($idOrEmail) && $idOrEmail <= 0) {
            return null;
        }
        $query = get_sql_query(
            IdealisticOfficeTable::PAYPAL_EMAILS_TABLE,
            IdealisticOfficeQuery::PAYPAL_EMAIL,
            array(
                array("team_id", $this->id),
                array("deletion_date", null),
                is_numeric($idOrEmail)
                    ? array("id", $idOrEmail)
                    : array("email_address", $idOrEmail)
            ),
            null,
            1
        );

        if (empty($query)) {
            return null;
        } else {
            return new IdealisticOfficePaypalEmail(
                $query[0]->id,
                $query[0]->created_by,
                $this,
                $query[0]->email_address,
                $query[0]->creation_date,
                $query[0]->creation_time_zone,
                $json
            );
        }
    }

    /** @return IdealisticOfficePaypalEmail[] */
    public final function getPaypalEmails(int $limit = 0, bool $json = true): array
    {
        if (!$this->hasEstablishedAccess()) {
            return array();
        }
        $query = get_sql_query(
            IdealisticOfficeTable::PAYPAL_EMAILS_TABLE,
            IdealisticOfficeQuery::PAYPAL_EMAIL,
            array(
                array("team_id", $this->id),
                array("deletion_date", null)
            ),
            array(
                "DESC",
                "id"
            ),
            $limit <= 0
                ? IdealisticOfficeLimit::MAX_PAYPAL_EMAILS
                : min($limit, IdealisticOfficeLimit::MAX_PAYPAL_EMAILS)
        );

        if (!empty($query)) {
            foreach ($query as $key => $row) {
                $query[$key] = new IdealisticOfficePaypalEmail(
                    $row->id,
                    $row->created_by,
                    $this,
                    $row->email_address,
                    $row->creation_date,
                    $row->creation_time_zone,
                    $json
                );
            }
        }
        return $query;
    }

    // Separator

    public final function addNotification(
        ?IdealisticOfficeUser $executor,
        string|array|null     $message,
        ?string               $attachmentName = null,
        ?string               $attachmentContent = null,
        ?string               $attachmentURL = null,
        ?string               $attachmentFormat = null,
        bool                  $base64 = false,
        bool                  $isImage = false,
        bool                  $isVideo = false,
        bool                  $isAudio = false,
        bool                  $translate = true,
        bool                  $deleteOnDelivery = false
    ): bool
    {
        $result = true;
        $users = $this->getUsers(false);

        if (!empty($users)) {
            foreach ($users as $user) {
                if ($user->getId() == $executor?->getId()) {
                    continue;
                }
                if (!$user->addNotification(
                    $executor,
                    $message,
                    $attachmentName,
                    $attachmentContent,
                    $attachmentURL,
                    $attachmentFormat,
                    $base64,
                    $isImage,
                    $isVideo,
                    $isAudio,
                    $translate,
                    $deleteOnDelivery
                )) {
                    $result = false;
                }
            }
        }
        return $result;
    }

}
