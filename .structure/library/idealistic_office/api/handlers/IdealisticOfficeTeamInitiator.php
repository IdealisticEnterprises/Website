<?php

class IdealisticOfficeTeamInitiator implements PhpAsyncSerializable
{

    /** @return array<int, IdealisticOfficeTeam> */
    public static function getAllTeams(
        ?string $lastAccessTime = null,
        bool    $json = true,
        int     $limit = -1,
    ): array
    {
        $team = new Account(
            Account::IDEALISTIC_OFFICE_APPLICATION_ID
        );
        $team = $team->getTeam();
        $team->setTeamAdditionalID(
            AccountTeam::IDEALISTIC_OFFICE_ADDITIONAL_ID
        );
        $team = $team->getAllTeams($lastAccessTime, $limit);

        if (!empty($team)) {
            $array = array();

            foreach ($team as $value) {
                $array[(int)$value->id] = new IdealisticOfficeTeam($value->id, $json);
            }
            return $array;
        } else {
            return array();
        }
    }

    public static function deleteOldTeams(): bool
    {
        $teams = self::getAllTeams(
            IdealisticOfficeReader::getPastDate(
                AccountSession::session_account_refresh_expiration
            ),
            false,
            IdealisticOfficeLimit::MAX_PROCESSED_TASKS
        );
        $bool = false;

        if (!empty($teams)) {
            foreach ($teams as $team) {
                $bool |= self::deleteOldTeam($team->getOwner(false))->isPositiveOutcome();
            }
        }
        return $bool;
    }

    public static function getRetentionChoices(): array
    {
        $query = get_sql_query(
            AccountVariables::ACCOUNT_SETTINGS_TABLE,
            array("account_id", "option_value"),
            array(
                array("option_key", IdealisticOfficeSettings::DATA_RETENTION_DAYS)
            ),
            null,
            IdealisticOfficeLimit::MAX_PROCESSED_ROWS
        );
        $choices = array();

        foreach ($query as $row) {
            $days = (int)$row->option_value;

            if ($days < IdealisticOfficeVariable::DATA_RETENTION_OPTION_MINIMUM_DAYS
                || $days > IdealisticOfficeVariable::DATA_RETENTION_MAXIMUM_DAYS) {
                continue;
            }
            $user = AbstractIdealisticOfficeTeam::findUserDynamic(
                new Account(Account::IDEALISTIC_OFFICE_APPLICATION_ID, (int)$row->account_id),
                false
            );

            if ($user instanceof IdealisticOfficeUser
                && $user->isOwner()) {
                $choices[(int)$user->getTeam()->getId()] = $days;
            }
        }
        return $choices;
    }

    private static function getRetentionCursor(string $name): int
    {
        return (int)@file_get_contents(sys_get_temp_dir() . "/idealistic_retention_" . $name . ".cursor");
    }

    private static function setRetentionCursor(string $name, int $id): void
    {
        @file_put_contents(sys_get_temp_dir() . "/idealistic_retention_" . $name . ".cursor", strval($id), LOCK_EX);
    }

    private static function deleteHistoryRecord(int $historyId): bool
    {
        foreach (array(
                     IdealisticOfficeTable::HISTORY_USER_ATTACHMENTS_TABLE => true,
                     IdealisticOfficeTable::HISTORY_CREATED_ATTACHMENTS_TABLE => false
                 ) as $table => $userUploaded) {
            $attachments = get_sql_query(
                $table,
                array("id"),
                array(
                    array("history_id", $historyId)
                )
            );

            foreach ($attachments as $attachment) {
                IdealisticOfficeError::deleteSqlQuery(
                    null,
                    null,
                    IdealisticOfficeTable::HISTORY_ATTACHMENT_ACCESS_TABLE,
                    array(
                        array("attachment_id", (int)$attachment->id),
                        array("user_uploaded", $userUploaded)
                    )
                );
            }
            IdealisticOfficeError::deleteSqlQuery(
                null,
                null,
                $table,
                array(
                    array("history_id", $historyId)
                )
            );
        }

        foreach (array(
                     IdealisticOfficeTable::HISTORY_PROMPTS_TABLE,
                     IdealisticOfficeTable::HISTORY_REPLIES_TABLE,
                     IdealisticOfficeTable::HISTORY_LINKS_TABLE,
                     IdealisticOfficeTable::HISTORY_WEB_DATA_TABLE,
                     IdealisticOfficeTable::HISTORY_FAILED_OBJECTS_TABLE,
                     IdealisticOfficeTable::HISTORY_PROCESSING_TIMES_TABLE
                 ) as $table) {
            IdealisticOfficeError::deleteSqlQuery(
                null,
                null,
                $table,
                array(
                    array("history_id", $historyId)
                )
            );
        }
        return (bool)IdealisticOfficeError::deleteSqlQuery(
            null,
            null,
            IdealisticOfficeTable::HISTORY_TOTAL_TABLE,
            array(
                array("id", $historyId)
            ),
            null,
            1
        );
    }

    private static function deleteExpiredRows(
        string $name,
        string $table,
        array  $retention,
        bool   $zoneColumn,
        callable $delete
    ): bool
    {
        $minimum = IdealisticOfficeVariable::DATA_RETENTION_MINIMUM_DAYS;
        $cursor = self::getRetentionCursor($name);
        $rows = get_sql_query(
            $table,
            $zoneColumn
                ? array("id", "team_id", "creation_date", "creation_time_zone")
                : array("id", "team_id", "creation_date"),
            array(
                array("id", ">", $cursor),
                array("creation_date", "<", IdealisticOfficeReader::getPastDate(($minimum - 1) . " days"))
            ),
            array("ASC", "id"),
            IdealisticOfficeLimit::DATA_RETENTION_BATCH_ROWS
        );
        $deleted = false;
        $last = 0;

        foreach ($rows as $row) {
            $last = (int)$row->id;
            $days = max(
                $minimum,
                min(
                    IdealisticOfficeVariable::DATA_RETENTION_MAXIMUM_DAYS,
                    $retention[(int)$row->team_id] ?? $minimum
                )
            );

            if ($row->creation_date >= IdealisticOfficeReader::getPastDate(
                    $days . " days",
                    $zoneColumn ? ($row->creation_time_zone ?? null) : null
                )) {
                continue;
            }
            $deleted |= $delete($row);
        }
        self::setRetentionCursor(
            $name,
            sizeof($rows) < IdealisticOfficeLimit::DATA_RETENTION_BATCH_ROWS ? 0 : $last
        );
        return (bool)$deleted;
    }

    public static function deleteExpiredData(): bool
    {
        $choices = self::getRetentionChoices();
        $deleted = self::deleteExpiredRows(
            "history",
            IdealisticOfficeTable::HISTORY_TOTAL_TABLE,
            $choices,
            true,
            fn($row): bool => self::deleteHistoryRecord((int)$row->id)
        );
        $deleted |= self::deleteExpiredRows(
            "portal",
            IdealisticOfficeTable::PORTAL_HISTORY_TABLE,
            $choices,
            false,
            fn($row): bool => (bool)IdealisticOfficeError::deleteSqlQuery(
                null,
                null,
                IdealisticOfficeTable::PORTAL_HISTORY_TABLE,
                array(
                    array("id", (int)$row->id)
                ),
                null,
                1
            )
        );
        return (bool)$deleted;
    }

    public static function deleteOldTeam(?IdealisticOfficeUser $user): IdealisticOfficeOutcome
    {
        if ($user === null) {
            return new IdealisticOfficeOutcome(
                false,
                "User not found."
            );
        }
        if (!$user->hasEstablishedAccess()) {
            return new IdealisticOfficeOutcome(false, "No team access established.");
        }
        $team = $user->getTeam();
        $owner = $team->getOwner(false);

        if ($owner === null) {
            return new IdealisticOfficeOutcome(
                false,
                "Team owner not found."
            );
        }
        if ($owner->hasEmail()) {
            return new IdealisticOfficeOutcome(
                false,
                "Cannot delete a team with an email credential."
            );
        }
        $outcome = $user->deleteTeam();

        if (!$outcome->isPositiveOutcome()) {
            return $outcome;
        }
        $outcome = $user->getAccount()->getActions()->deleteAccount(
            true,
            array(
                explode(AccountVariables::ACCOUNTS_TABLE, ".", 2)[0],
                explode(IdealisticOfficeTable::AI_REQUESTS_TABLE, ".", 2)[0],
            )
        );
        return new IdealisticOfficeOutcome(
            $outcome->getRawOutcome(),
            $outcome->getMessage()
        );
    }

    // Separator

    private static function verifyAccount(Account $account): IdealisticOfficeOutcome
    {
        if ($account->exists()) {
            if ($account->getDetail("email_address", null) !== null) {
                $account->getEmail()->setToRun(false);
                $reply = $account->getEmail()->completeVerification();

                if (!$reply->isPositiveOutcome()
                    && !$account->getEmail()->isVerified()) {
                    return new IdealisticOfficeOutcome(
                        false,
                        $reply->getMessage()
                    );
                }
                $account->getEmail()->setToRun(true);
            }
            return self::createTeam($account);
        } else {
            $creationAttempt = $account->getCreationAttempt();

            if (!$creationAttempt->isPositiveOutcome()) {
                return new IdealisticOfficeOutcome(
                    false,
                    $creationAttempt->getMessage()
                );
            }
            return new IdealisticOfficeOutcome(
                false,
                "Account does not exist."
            );
        }
    }

    public static function createSignInLink(
        string  $email,
        ?string $fullName = null,
        ?string $portal = null,
        ?string $to = null
    ): string|IdealisticOfficeOutcome
    {
        $account = self::createAccount($email, $fullName);

        if ($account instanceof Account) {
            $account->getEmail()->setToRun(false);
            $outcome = $account->getTwoFactorAuthentication()->initiate(
                null,
                false
            );
            $account->getEmail()->setToRun(true);

            if ($outcome->isPositiveOutcome()) {
                return IdealisticOfficeVariable::ACCOUNT_SIGN_IN_INPUT_OUTPUT_URL
                    . "/?token=" . $account->getTwoFactorAuthentication()->getLastToken()
                    . (!empty($portal)
                        ? "&portal=" . urlencode($portal)
                        : "")
                    . ($to === "structure" ? "&to=structure" : "");
            } else {
                return new IdealisticOfficeOutcome(
                    $outcome->getRawOutcome(),
                    $outcome->getMessage()
                );
            }
        } else {
            return $account;
        }
    }

    public static function createAccount(
        string  $emailOrUsername,
        ?string $fullName = null
    ): Account|IdealisticOfficeOutcome
    {
        $email = is_email($emailOrUsername)
            ? $emailOrUsername
            : null;
        $username = $email === null
            ? $emailOrUsername
            : null;
        $account = new Account(Account::IDEALISTIC_OFFICE_APPLICATION_ID);
        $account->getRegistry()->setUsernameLength(AccountRegistry::MAX_USERNAME_LENGTH);
        $account->transform(
            null,
            $email,
            $username,
            null,
            true,
            Account::EMPTY_PASSWORD
        );
        $creationAttempt = $account->getCreationAttempt()->isPositiveOutcome();

        if (!empty($fullName)
            && $creationAttempt) {
            $nameSplit = explode(" ", $fullName, 3);

            if (sizeof($nameSplit) === 3) {
                $account->setDetail(AccountActions::FIRST_NAME, $nameSplit[0]);
                $account->setDetail(AccountActions::MIDDLE_NAME, $nameSplit[1]);
                $account->setDetail(AccountActions::LAST_NAME, $nameSplit[2]);
            } else if (sizeof($nameSplit) === 2) {
                $account->setDetail(AccountActions::FIRST_NAME, $nameSplit[0]);
                $account->setDetail(AccountActions::MIDDLE_NAME, null);
                $account->setDetail(AccountActions::LAST_NAME, $nameSplit[1]);
            } else {
                $account->setDetail(AccountActions::FIRST_NAME, $nameSplit[0]);
                $account->setDetail(AccountActions::MIDDLE_NAME, null);
                $account->setDetail(AccountActions::LAST_NAME, null);
            }
            $user = AbstractIdealisticOfficeTeam::findUserDynamic($account, false);

            if ($user instanceof IdealisticOfficeUser
                && !$user->hasLegalRecord()) {
                $legalRecord = new IdealisticOfficeLegalRecord(
                    0,
                    $account,
                    $user->getTeam(),
                    IdealisticOfficeLegalRecord::CURRENT_VERSION,
                    $fullName,
                    get_client_ip_address(),
                    null,
                    $user->getLastIdentity(null, true),
                    IdealisticOfficeReader::getCurrentDate(),
                    IdealisticOfficeVariable::DEFAULT_TIME_ZONE,
                    false
                );
                $legalRecord->create();
            }
        }
        $verify = self::verifyAccount($account);

        if (!$verify->isPositiveOutcome()) {
            return $verify;
        }

        if (!empty($fullName)
            && !$creationAttempt) {
            $user = AbstractIdealisticOfficeTeam::findUserDynamic($account, false);

            if ($user instanceof IdealisticOfficeUser) {
                $user->setSetting(
                    IdealisticOfficeSettings::NEW_FULL_NAME,
                    $fullName
                );
            }
        }
        return $account;
    }

    private static function createTeam(
        Account $account
    ): IdealisticOfficeOutcome
    {
        $team = new IdealisticOfficeTeam($account, false);

        if ($team->hasEstablishedAccess()) {
            return new IdealisticOfficeOutcome(
                true,
                "Welcome back to your " . IdealisticOfficeVariable::APPLICATION_NAME . " team!"
            );
        }
        $team = $account->getTeam();
        $team->togglePermissions(false);
        $team->setTeamAdditionalID(AccountTeam::IDEALISTIC_OFFICE_ADDITIONAL_ID);
        $result = $team->createTeam(
            IdealisticOfficeTeam::PREFIX . $account->getDetail("id")
        );

        if ($result->isPositiveOutcome()) {
            $team = new IdealisticOfficeTeam($account, false);

            if (!$team->hasEstablishedAccess()) {
                return new IdealisticOfficeOutcome(
                    false,
                    "Failed to find the created team."
                );
            }
            $outcome = $team->getCredit()->add(
                null,
                IdealisticOfficeVariable::NEW_TEAM_DEFAULT_CREDITS
            );

            if (!$outcome->isPositiveOutcome()) {
                return $outcome;
            }
            return new IdealisticOfficeOutcome(
                $result->getRawOutcome(),
                "Welcome to " . IdealisticOfficeVariable::APPLICATION_NAME . "!"
            );
        }
        return new IdealisticOfficeOutcome($result->getRawOutcome(), $result->getMessage());
    }

    // Separator

    public static function limitNewPrompts(): bool
    {
        $query = get_sql_query(
            IdealisticOfficeTable::HISTORY_TOTAL_TABLE,
            array("id", "creation_date", "creation_time_zone"),
            array(
                array("processing", true)
            ),
            array(
                "DESC",
                "id"
            ),
            IdealisticOfficeLimit::MAX_PROCESSED_TASKS * 2
        );

        if (!empty($query)) {
            $count = 0;
            $pastTime = IdealisticOfficeLimit::HISTORY_PROMPT_SECONDS_TIMEOUT . " seconds";

            foreach ($query as $row) {
                if (IdealisticOfficeReader::getPastDate(
                        $pastTime,
                        $row->creation_time_zone
                    ) <= $row->creation_date) {
                    $count++;
                } else {
                    IdealisticOfficeError::setSqlQuery(
                        null,
                        null,
                        IdealisticOfficeTable::HISTORY_TOTAL_TABLE,
                        array(
                            "processing" => false
                        ),
                        array(
                            array("id", $row->id)
                        ),
                        null,
                        1
                    );
                }
            }

            if ($count >= IdealisticOfficeLimit::MAX_PROCESSED_TASKS) {
                return true;
            }
        }
        return false;
    }

    // Separator

    public static function isPromptProcessing(IdealisticOfficeHistory $history): bool
    {
        if (IdealisticOfficeReader::getPastDate(
                IdealisticOfficeLimit::HISTORY_PROMPT_SECONDS_TIMEOUT . " seconds",
                $history->getCreationDateTimeZone()
            ) <= $history->getCreationDate()
            && !$history->failed()
            && !self::areRequiredTasksProcessed($history->getId())) {
            return true;
        } else {
            return false;
        }
    }

    private static function areRequiredTasksProcessed(int $historyID): bool
    {
        return empty(get_sql_query(
            IdealisticOfficeTable::HISTORY_PROCESSING_TIMES_TABLE,
            array(
                "id"
            ),
            array(
                array("history_id", $historyID),
                array("time_passed", null),
                array("task_terminated", null),
                array("utc_creation_date", ">=", IdealisticOfficeReader::getPastDate(IdealisticOfficeLimit::HISTORY_PROMPT_SECONDS_TIMEOUT . " seconds"))
            ),
            null,
            1
        ));
    }

    // Separator

    public static function createPrompt(
        ?IdealisticOfficeUser $user,
        int                   $platformID,
        int|string            $platformUserID,
        int|string|null       $platformChatID,
        ?string               $platformChatName,
        int|string|null       $platformMessageID,
        ?string               $userName,
        ?string               $displayName,
        ?string               $prompt,
        array                 $attachments = [],
        bool                  $automated = false,
        string                $defaultLanguage = IdealisticOfficeVariable::DEFAULT_LANGUAGE
    ): IdealisticOfficeOutcome
    {
        if ($prompt !== null
            && empty($prompt)) {
            $prompt = null;
        }
        if ($user === null) {
            $user = self::findUser(
                $platformID,
                $platformUserID
            );

            if ($user instanceof IdealisticOfficeOutcome) {
                return new IdealisticOfficeOutcome(
                    $user->getRawOutcome(),
                    $user->getRawMessage(),
                    true
                );
            }
        }
        if (!$user->hasEstablishedAccess()) {
            return new IdealisticOfficeOutcome(
                false,
                IdealisticOfficeGeneralMessage::EXCEPTION_THROWN . " (#937304191)"
            );
        }
        $team = $user->getTeam();
        $userTimezone = $user->getTimezone(false);
        $creationDate = IdealisticOfficeReader::getCurrentDate($userTimezone);
        $lastHistoryID = $team->getLastHistoryID();

        if ($lastHistoryID !== null) {
            $cost = $team->getCredit()->getCost($lastHistoryID);

            if ($cost !== null) {
                $cost -= IdealisticOfficeVariable::MINIMUM_CREDITS_TO_USE;

                if ($cost < 0.0) {
                    $team->getCredit()->subtract(
                        $lastHistoryID,
                        abs($cost)
                    );
                }
            }
        }
        if (IdealisticOfficeError::sqlInsert(
            $team,
            $user,
            IdealisticOfficeTable::HISTORY_TOTAL_TABLE,
            array(
                "team_id" => $team->getId(),
                "account_id" => $user->getId(),
                "platform_id" => $platformID,
                "platform_user_id" => $platformUserID,
                "platform_chat_id" => $platformChatID,
                "platform_chat_name" => $platformChatName,
                "platform_message_id" => $platformMessageID,
                "creation_date" => $creationDate,
                "creation_time_zone" => $userTimezone->getTimeZone(),
                "processing" => true
            )
        )) {
            $historyID = IdealisticOfficeReader::getSqlLastInsertId();

            if ($historyID === null) {
                return new IdealisticOfficeOutcome(
                    false,
                    IdealisticOfficeGeneralMessage::EXCEPTION_THROWN . " (#582617309)"
                );
            }
            return self::startPrompt(
                $historyID,
                $user,
                $platformID,
                $platformUserID,
                $platformChatID,
                $platformChatName,
                $platformMessageID,
                $userName,
                $displayName,
                $prompt,
                $attachments,
                $creationDate,
                $userTimezone,
                $automated,
                $defaultLanguage
            );
        }
        return new IdealisticOfficeOutcome(
            false,
            IdealisticOfficeGeneralMessage::EXCEPTION_THROWN . " (#839201467)",
            true,
            $user
        );
    }

    private static function startPrompt(
        int                       $historyID,
        ?IdealisticOfficeUser     $user,
        int                       $platformID,
        int|string                $platformUserID,
        int|string|null           $platformChatID,
        ?string                   $platformChatName,
        int|string|null           $platformMessageID,
        ?string                   $userName,
        ?string                   $displayName,
        ?string                   $prompt,
        array                     $attachments,
        ?string                   $creationDate,
        ?IdealisticOfficeTimezone $userTimezone,
        bool                      $automated,
        string                    $defaultLanguage
    ): IdealisticOfficeOutcome
    {
        try {
            $team = $user->getTeam();

            if ($userTimezone === null) {
                $userTimezone = $user->getTimezone(false);
            }
            if ($creationDate === null) {
                $creationDate = IdealisticOfficeReader::getCurrentDate($userTimezone);
            }
            $prompt = new IdealisticOfficePrompt(
                $platformID,
                $platformUserID,
                $platformChatID,
                $platformChatName,
                $platformMessageID,
                $userName,
                $displayName,
                is_email($platformUserID) ? $platformUserID : null,
                $user,
                $prompt === null
                    ? null
                    : base64_encode($prompt),
                $automated
            );
        } catch (Throwable $e) {
            IdealisticOfficeError::setSqlQuery(
                $team,
                $user,
                IdealisticOfficeTable::HISTORY_TOTAL_TABLE,
                array(
                    "processing" => false
                ),
                array(
                    array("id", $historyID)
                ),
                null,
                1
            );
            IdealisticOfficeError::storeThrowable(
                $team,
                $user,
                $e
            );
            return new IdealisticOfficeOutcome(
                false,
                $e->getMessage() . " (#852930413)",
                true,
                $user
            );
        }

        if (!empty($attachments)) {
            $bytesSize = 0;

            foreach ($attachments as $attachment) {
                if (!($attachment instanceof IdealisticOfficeAttachment)) {
                    continue;
                }
                if ($attachment->isValid()) {
                    $bytesSize = $attachment->getBytes();
                    IdealisticOfficeError::sqlInsert(
                        $team,
                        $user,
                        IdealisticOfficeTable::HISTORY_USER_ATTACHMENTS_TABLE,
                        array(
                            "history_id" => $historyID,
                            "attachment_format" => $attachment->getFormat(),
                            "attachment_name" => $attachment->getName(),
                            "attachment_bytes" => $attachment->getBytes(),
                            "attachment_x" => $attachment->getXSize(),
                            "attachment_y" => $attachment->getYSize(),
                            "attachment_data" => $attachment->getRawData(),
                            "attachment_data_hash" => $attachment->getDataHash(),
                            "analyzed_description" => $attachment->getAnalyzedDescription(),
                            "is_base64" => $attachment->isBase64(),
                            "creation_date" => $creationDate,
                            "creation_time_zone" => $userTimezone->getTimeZone(),
                            "created_by" => $user->getId()
                        )
                    );
                } else {
                    $prompt->notify(
                        "Attachment '" . $attachment->getName() . "' is not valid and was skipped."
                    );
                }
            }

            if ($bytesSize > 0) {
                $query = get_sql_query(
                    IdealisticOfficeTable::HISTORY_USER_ATTACHMENTS_TABLE,
                    array("attachment_bytes"),
                    array(
                        array("creation_date", ">=", IdealisticOfficeReader::getPastDate("1 day")),
                    ),
                    null,
                    IdealisticOfficeLimit::MAX_PROCESSED_ROWS
                );

                if (!empty($query)) {
                    $bytesSize += array_sum(array_map(
                        function ($item) {
                            return $item->attachment_bytes;
                        },
                        $query
                    ));
                }

                if ($bytesSize > IdealisticOfficeLimit::MAX_ATTACHMENT_BYTES_PER_DAY) {
                    return new IdealisticOfficeOutcome(
                        false,
                        "The total size of attachments received in the last 24 hours has exceeded the allowed limit, please upload more later.",
                        true,
                        $user
                    );
                }
            }
        }
        $tasks = IdealisticOfficePromptTask::TASKS_LIST;
        $rows = array();
        $history = new IdealisticOfficeHistory(
            $team,
            $historyID,
            $platformID,
            $platformUserID,
            $platformMessageID,
            $platformChatID,
            $platformChatName,
            $user->getAccount(),
            $creationDate,
            $userTimezone->getTimeZone(),
            null
        );

        foreach ($tasks as $key => $task) {
            $promptClone = clone $prompt;

            try {
                $row = $promptClone->setTaskIDAndGetRow($task, $history);
                $rows[] = $row;
            } catch (Throwable $e) {
                IdealisticOfficeError::setSqlQuery(
                    $team,
                    $user,
                    IdealisticOfficeTable::HISTORY_TOTAL_TABLE,
                    array(
                        "processing" => false
                    ),
                    array(
                        array("id", $historyID)
                    ),
                    null,
                    1
                );
                IdealisticOfficeError::storeThrowable(
                    $team,
                    $user,
                    $e
                );
                return new IdealisticOfficeOutcome(
                    false,
                    $e->getMessage() . " (#638794942)",
                    true,
                    $user
                );
            }
            $tasks[$key] = $promptClone;
        }
        if (IdealisticOfficeError::multipleSqlInsert(
            $team,
            $user,
            IdealisticOfficeTable::HISTORY_PROCESSING_TIMES_TABLE,
            IdealisticOfficePrompt::DATABASE_COLUMNS,
            $rows
        )) {
            IdealisticOfficeAsync::run(
                array(
                    "IdealisticOfficeUser",
                    "runMultiplePrompts"
                ),
                array(
                    $tasks,
                    $prompt,
                    $history,
                    $defaultLanguage
                )
            );
        } else {
            return new IdealisticOfficeOutcome(
                false,
                IdealisticOfficeGeneralMessage::EXCEPTION_THROWN . " (#927364581)",
                true,
                $user
            );
        }
        return new IdealisticOfficeOutcome(
            true,
            $historyID,
            false,
            $user
        );
    }

    // Separator

    public static function getCredential(
        int        $platformID,
        int|string $platformUserID
    ): string|int|null
    {
        switch ($platformID) {
            case IdealisticOfficeAccessPlatform::TELEGRAM:
            case IdealisticOfficeAccessPlatform::DISCORD:
            case IdealisticOfficeAccessPlatform::INSTAGRAM:
            case IdealisticOfficeAccessPlatform::MESSENGER:
            case IdealisticOfficeAccessPlatform::WHATSAPP:
            case IdealisticOfficeAccessPlatform::NATIVE:
                return $platformUserID;
            default:
                return null;
        }
    }

    public static function findUser(
        int        $platformID,
        int|string $platformUserID,
    ): IdealisticOfficeUser|IdealisticOfficeOutcome
    {
        $isEmail = is_email($platformUserID);

        if ($isEmail) {
            $account = new Account(
                Account::IDEALISTIC_OFFICE_APPLICATION_ID,
                null,
                $platformUserID
            );
            $credential = $platformUserID;
        } else {
            $credential = self::getCredential(
                $platformID,
                $platformUserID
            );

            if ($credential === null) {
                return new IdealisticOfficeOutcome(
                    false,
                    IdealisticOfficeGeneralMessage::EXCEPTION_THROWN . " (#746395810)"
                );
            }
            $account = new Account(Account::IDEALISTIC_OFFICE_APPLICATION_ID);
            $account = $account->getAccounts()->getAccountFromType(
                $platformID,
                $credential
            );
        }

        if ($account === null
            || !$account->exists()) {
            $account = self::createAccount($credential);

            if ($account instanceof IdealisticOfficeOutcome) {
                return $account;
            }
            if (!$isEmail) {
                $reply = $account->getAccounts()->add(
                    $platformID,
                    $credential
                );

                if (!$reply->isPositiveOutcome()) {
                    return new IdealisticOfficeOutcome(
                        false,
                        $reply->getMessage()
                    );
                }
            }
        } else {
            $outcome = self::verifyAccount($account);

            if (!$outcome->isPositiveOutcome()) {
                return $outcome;
            }
        }
        $team = new IdealisticOfficeTeam($account, false);

        if ($team->hasEstablishedAccess()) {
            $user = $team->findUser($account);

            if ($user instanceof IdealisticOfficeOutcome) {
                return $user;
            }
            $session = self::initiateSession($account, $credential, $platformID);

            if (!$session->isPositiveOutcome()) {
                return $session;
            }
            return $user;
        } else {
            self::deleteSession(
                $account,
                $credential,
                $platformID
            );
            return new IdealisticOfficeOutcome(
                false,
                IdealisticOfficeGeneralMessage::EXCEPTION_THROWN . " (#884632574)"
            );
        }
    }

    public static function initiateSession(
        Account $account,
        string  $credential,
        int     $platformID
    ): IdealisticOfficeOutcome
    {
        if ($platformID === IdealisticOfficeAccessPlatform::NATIVE) {
            $session = $account->getSession()->create(false, $credential);
            return new IdealisticOfficeOutcome($session->getRawOutcome(), $session->getMessage());
        } else {
            return new IdealisticOfficeOutcome(
                true,
                "Session initiation not required for the platform: " . IdealisticOfficeAccessPlatform::getValidPlatforms()[$platformID]
            );
        }
    }

    private static function deleteSession(
        Account $account,
        string  $credential,
        int     $platformID
    ): IdealisticOfficeOutcome
    {
        if ($platformID === IdealisticOfficeAccessPlatform::NATIVE) {
            $session = $account->getSession()->delete();

            if (!$session->isPositiveOutcome()) {
                return new IdealisticOfficeOutcome($session->getRawOutcome(), $session->getMessage());
            }
            return self::initiateSession(
                $account,
                $credential,
                $platformID
            );
        } else {
            return new IdealisticOfficeOutcome(
                true,
                "Session deletion not required for the platform: " . IdealisticOfficeAccessPlatform::getValidPlatforms()[$platformID]
            );
        }
    }

    private static function switchSession(
        Account $oldAccount,
        Account $account,
        int     $platformID
    ): IdealisticOfficeOutcome
    {
        if ($platformID === IdealisticOfficeAccessPlatform::NATIVE) {
            $session = $oldAccount->getSession()->switch($account);
            return new IdealisticOfficeOutcome($session->getRawOutcome(), $session->getMessage());
        } else {
            return new IdealisticOfficeOutcome(
                true,
                "Session deletion not required for the platform: " . IdealisticOfficeAccessPlatform::getValidPlatforms()[$platformID]
            );
        }
    }

    public static function changeSession(
        IdealisticOfficeUser $oldUser,
        Account              $account,
        string               $credential,
        int                  $platformID
    ): IdealisticOfficeOutcome
    {
        if ($oldUser->getId() == $account->getDetail("id", 0)) {
            return new IdealisticOfficeOutcome(
                true,
                "No session change required."
            );
        }
        if ($oldUser->hasEmail()) {
            return new IdealisticOfficeOutcome(
                false,
                "Session change not allowed for users with email credentials, try signing out first."
            );
        }
        if ($platformID === IdealisticOfficeAccessPlatform::NATIVE) {
            $delete = self::deleteOldTeam($oldUser);

            if (!$delete->isPositiveOutcome()) {
                return $delete;
            }
            $delete = self::switchSession($oldUser->getAccount(), $account, $platformID);

            if (!$delete->isPositiveOutcome()) {
                return $delete;
            }
        } else {
            $delete = self::deleteOldTeam($oldUser);

            if (!$delete->isPositiveOutcome()) {
                return $delete;
            }
            $reply = $account->getAccounts()->add(
                $platformID,
                $credential
            );

            if (!$reply->isPositiveOutcome()) {
                return new IdealisticOfficeOutcome(
                    false,
                    $reply->getMessage()
                );
            }
        }
        return self::verifyAccount($account);
    }

}
