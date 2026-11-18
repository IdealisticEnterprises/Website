<?php

namespace idealistic_office\api\handlers;

use idealistic_office\api\IdealisticOfficeVariable;
use idealistic_office\api\objects\helpers\AbstractIdealisticOfficeTeam;
use idealistic_office\api\objects\IdealisticOfficeUser;
use PhpAsyncSerializable;

class IdealisticOfficeError implements PhpAsyncSerializable
{

    public static function debug(
        mixed $debug,
        bool  $broadcast = false,
        bool  $redundant = true
    ): void
    {
        self::simpleStore(
            $debug,
            $broadcast,
            $redundant
        );
    }

    public static function simpleStore(
        mixed $debug,
        bool  $broadcast = false,
        bool  $redundant = true
    ): void
    {
        if ($debug instanceof Throwable) {
            self::storeThrowable(
                null,
                null,
                $debug
            );
        } else {
            self::storeString(
                null,
                null,
                is_object($debug) || is_array($debug)
                    ? IdealisticOfficeReader::jsonObject($debug, true, true) ?? "Failed to convert to JSON (3)"
                    : (string)$debug,
                $broadcast,
                $redundant
            );
        }
    }

    public static function storeString(
        ?AbstractIdealisticOfficeTeam $team,
        ?IdealisticOfficeUser         $user,
        ?string                       $error,
        bool                          $broadcast = false,
        bool                          $redundant = true
    ): void
    {
        if ($broadcast) {
            var_dump($error);
        }
        if ($error !== null) {
            error_log($error);
        }
        try {
            $timezone = $user?->getTimeZone(false) ?? $team?->getTimeZone(false);
            sql_insert(
                IdealisticOfficeTable::ERRORS_TABLE,
                array(
                    "team_id" => $team?->getId(),
                    "account_id" => $user?->getId(),
                    "value_type" => get_debug_type($error),
                    "error" => $error,
                    "backtrace" => @json_encode(debug_backtrace(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?? "Failed to convert to JSON (8)",
                    "creation_date" => IdealisticOfficeReader::getCurrentDate($timezone),
                    "creation_time_zone" => $timezone?->getTimeZone() ?? IdealisticOfficeVariable::DEFAULT_TIME_ZONE
                )
            );

            if ($broadcast
                && $user !== null
                && $user->isAdministrator()) {
                $user->addNotification(
                    null,
                    $error,
                    null,
                    null,
                    null,
                    null,
                    false,
                    false,
                    false,
                    false,
                    false,
                    true
                );
            }
        } catch (Throwable $ignored) {
            if ($redundant) {
                IdealisticOfficeError::storeString(null, null, $ignored->getTraceAsString(), false, false);
                IdealisticOfficeError::storeString(null, null, $ignored->getMessage(), false, false);
            }
        }
    }

    public static function storeArray(
        ?AbstractIdealisticOfficeTeam $team,
        ?IdealisticOfficeUser         $user,
        array                         $error,
        bool                          $broadcast = false
    ): void
    {
        if (empty($error)) {
            return;
        }
        if ($broadcast) {
            var_dump($error);
        }
        foreach ($error as $key => $value) {
            if (is_string($value)) {
                $error[$key] = mb_substr($value, 0, IdealisticOfficeLimit::K1_LENGTH);
            }
        }
        try {
            $error = IdealisticOfficeReader::jsonObject($error, true, true) ?? "Failed to convert to JSON (4)";
            $timezone = $user?->getTimeZone(false) ?? $team?->getTimeZone(false);
            sql_insert(
                IdealisticOfficeTable::ERRORS_TABLE,
                array(
                    "team_id" => $team?->getId(),
                    "account_id" => $user?->getId(),
                    "value_type" => get_debug_type($error),
                    "error" => $error,
                    "backtrace" => @json_encode(debug_backtrace(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?? "Failed to convert to JSON (9)",
                    "creation_date" => IdealisticOfficeReader::getCurrentDate($timezone),
                    "creation_time_zone" => $timezone?->getTimeZone() ?? IdealisticOfficeVariable::DEFAULT_TIME_ZONE
                )
            );

            if ($broadcast
                && $user !== null
                && $user->isAdministrator()) {
                $user->addNotification(
                    null,
                    $error,
                    null,
                    null,
                    null,
                    null,
                    false,
                    false,
                    false,
                    false,
                    false,
                    true
                );
            }
        } catch (Throwable $ignored) {
        }
    }

    public static function sqlInsert(
        ?AbstractIdealisticOfficeTeam $team,
        ?IdealisticOfficeUser         $user,
        string                        $table,
        array                         $pairs
    ): mixed
    {
        $insert = sql_insert($table, $pairs);

        if (!$insert) {
            $object = new stdClass();
            $object->table = $table;
            $object->pairs = $pairs;
            $object->error = $insert;
            self::storeString($team, $user, IdealisticOfficeReader::jsonObject($object, true, true) ?? "Failed to convert to JSON (5)");
        }
        return $insert;
    }

    public static function multipleSqlInsert(
        ?AbstractIdealisticOfficeTeam $team,
        ?IdealisticOfficeUser         $user,
        string                        $table,
        array                         $columns,
        array                         $rows
    ): mixed
    {
        $insert = multiple_sql_insert($table, $columns, $rows);

        if (!$insert) {
            $object = new stdClass();
            $object->table = $table;
            $object->columns = $columns;
            $object->rows = $rows;
            $object->error = $insert;
            self::storeString($team, $user, IdealisticOfficeReader::jsonObject($object, true, true) ?? "Failed to convert to JSON (6)");
        }
        return $insert;
    }

    public static function setSqlQuery(
        ?AbstractIdealisticOfficeTeam $team,
        ?IdealisticOfficeUser         $user,
        string                        $table,
        array                         $what,
        ?array                        $where = null,
        string|array|null             $order = null,
        int                           $limit = 0
    ): mixed
    {
        $set = set_sql_query($table, $what, $where, $order, $limit);

        if (!$set) {
            $object = new stdClass();
            $object->table = $table;
            $object->what = $what;
            $object->where = $where;
            $object->order = $order;
            $object->limit = $limit;
            $object->error = $set;
            self::storeString($team, $user, IdealisticOfficeReader::jsonObject($object, true, true) ?? "Failed to convert to JSON (7)");
        }
        return $set;
    }

    public static function deleteSqlQuery(
        ?AbstractIdealisticOfficeTeam $team,
        ?IdealisticOfficeUser         $user,
        string                        $table,
        array                         $where,
        string|array|null             $order = null,
        int                           $limit = 0
    ): mixed
    {
        $delete = delete_sql_query($table, $where, $order, $limit);

        if (!$delete) {
            $object = new stdClass();
            $object->table = $table;
            $object->where = $where;
            $object->order = $order;
            $object->limit = $limit;
            $object->error = $delete;
            self::storeString($team, $user, IdealisticOfficeReader::jsonObject($object, true, true) ?? "Failed to convert to JSON (2)");
        }
        return $delete;
    }

    public static function storeThrowable(
        ?AbstractIdealisticOfficeTeam $team,
        ?IdealisticOfficeUser         $user,
        Throwable                     $throwable,
        bool                          $broadcast = false
    ): void
    {
        $object = new stdClass();
        $object->file = $throwable->getFile();
        $object->code = $throwable->getCode();
        $object->message = $throwable->getMessage();
        $object->line = $throwable->getLine();
        $object->trace = $throwable->getTrace();
        self::storeString($team, $user, IdealisticOfficeReader::jsonObject($object, true, true) ?? "Failed to convert to JSON (1)", $broadcast);
    }

}