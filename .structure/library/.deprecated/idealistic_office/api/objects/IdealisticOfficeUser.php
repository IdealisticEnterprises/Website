<?php


namespace idealistic_office\api\objects;

use account\api\objects\account\Account;
use account\api\objects\correlation\AccountTeam;
use account\api\objects\management\AccountActions;
use idealistic_office\api\handlers\IdealisticOfficeError;
use idealistic_office\api\handlers\IdealisticOfficeNotifications;
use idealistic_office\api\handlers\IdealisticOfficeReader;
use idealistic_office\api\IdealisticOfficeVariable;
use idealistic_office\api\interfaces\IdealisticOfficeParticipant;
use idealistic_office\api\objects\helpers\AbstractIdealisticOfficeTeam;
use idealistic_office\api\objects\helpers\attachment\IdealisticOfficeAttachment;
use idealistic_office\api\objects\helpers\change\IdealisticOfficeLegalRecord;
use idealistic_office\api\objects\helpers\change\IdealisticOfficeTimeAccess;
use idealistic_office\api\objects\helpers\change\IdealisticOfficeWeeklyAccess;
use idealistic_office\api\objects\helpers\IdealisticOfficeAdminBlockedAccess;
use idealistic_office\api\objects\helpers\IdealisticOfficeJSON;
use idealistic_office\api\objects\helpers\IdealisticOfficeLanguageSelection;
use idealistic_office\api\objects\helpers\IdealisticOfficeNotification;
use idealistic_office\api\objects\helpers\IdealisticOfficeOutcome;
use idealistic_office\api\objects\helpers\IdealisticOfficePromptCache;
use idealistic_office\api\objects\helpers\IdealisticOfficeTimezone;
use idealistic_office\api\objects\helpers\manager\IdealisticOfficeInstructionsManager;
use idealistic_office\api\objects\helpers\manager\IdealisticOfficePaypalEmailManager;
use idealistic_office\api\objects\helpers\manager\IdealisticOfficeRoleManager;
use idealistic_office\api\objects\helpers\manager\IdealisticOfficeTimeAccessManager;
use idealistic_office\api\objects\helpers\manager\IdealisticOfficeWeeklyAccessManager;
use idealistic_office\api\objects\helpers\user\IdealisticOfficeUserIdentity;
use idealistic_office\api\objects\helpers\user\IdealisticOfficeUserShortcut;
use IdealisticOfficeAccessPlatform;
use IdealisticOfficeLimit;
use IdealisticOfficePermission;
use IdealisticOfficePromptTask;
use IdealisticOfficeSettings;
use IdealisticOfficeTable;
use React\EventLoop\Factory;
use React\EventLoop\Loop;

class IdealisticOfficeUser extends IdealisticOfficeJSON implements IdealisticOfficeParticipant
{

    private AbstractIdealisticOfficeTeam $team;
    private Account $account;
    private ?IdealisticOfficeUserIdentity $lastIdentity;

    public function __construct(
        ?AbstractIdealisticOfficeTeam $team,
        ?Account                      $account,
        bool                          $json = true
    )
    {
        parent::__construct();

        if ($account === null) {
            $account = new Account(Account::IDEALISTIC_OFFICE_APPLICATION_ID);
        }
        $account->getTeam()->setTeamAdditionalID(AccountTeam::IDEALISTIC_OFFICE_ADDITIONAL_ID);
        $account->getTeam()->togglePermissions(false);
        $object = new stdClass();

        if ($team === null) {
            $team = new IdealisticOfficeTeam($account);
        }
        $object->id = $team->getId();
        $account->getTeam()->setForcedTeam($object);
        $this->account = $account;
        $this->team = $team;
        $this->lastIdentity = null;

        if ($json) {
            $lastIdentity = $this->getLastIdentity();
            self::setJSON(
                array(
                    "id" => $this->getId(),
                    "username" => $lastIdentity?->getUserName(),
                    "display_name" => $lastIdentity?->getDisplayName(),
                    "creation_date" => $this->getCreationDate(),
                    "creation_time_zone" => $this->getCreationDateTimeZone()
                )
            );
        }
    }

    public function isAdministrator(): bool
    {
        return $this->account->getPermissions()->isAdministrator();
    }

    public function getGeneralizedAccess(
        AbstractIdealisticOfficeTeam $team,
        IdealisticOfficePrompt       $prompt = null,
    ): IdealisticOfficeOutcome
    {
        $admin = $this->team->getId() === $team->getId()
            || $this->isAdministrator();

        // Member and Team time access
        if (!$admin) {
            $isEnabled = function (string $task) use ($prompt, $team): bool {
                if ($prompt === null) {
                    return IdealisticOfficePromptTask::isEnabled(
                        $team,
                        $task
                    );
                } else {
                    return $prompt->getCache()->isTaskEnabled($task);
                }
            };
            $timezone = $this->getTimezone(false);

            if ($isEnabled(IdealisticOfficePromptTask::MANAGE_TIME_ACCESSES)) {
                $access = $this->getTimeAccess($team, false);

                if (!$access->hasAccess($timezone)) {
                    return new IdealisticOfficeOutcome(
                        false,
                        "Your member accessible times are between "
                        . $access->getStartHour() . ":" . $access->getStartMinutes()
                        . " and " . $access->getEndHour() . ":" . $access->getEndMinutes()
                        . " " . $timezone->getTimeZone() . ".",
                    );
                }
            }
            if ($isEnabled(IdealisticOfficePromptTask::MANAGE_WEEK_ACCESSES)) {
                $access = $this->getWeekAccess($team, false);

                if (!$access->hasAccess($timezone)) {
                    return new IdealisticOfficeOutcome(
                        false,
                        "Your member accessible week days are: " . $access->getDaysString() . "."
                    );
                }
            }

            // Role access
            if ($isEnabled(IdealisticOfficePromptTask::MANAGE_ROLES)
                && ($isEnabled(IdealisticOfficePromptTask::MANAGE_TIME_ACCESSES)
                    || $isEnabled(IdealisticOfficePromptTask::MANAGE_WEEK_ACCESSES))) {
                $roles = $this->getRoles(false);

                if (!empty($roles)) {
                    $lastFailure = array();

                    foreach ($roles as $role) {
                        if ($isEnabled(IdealisticOfficePromptTask::MANAGE_TIME_ACCESSES)
                            && $isEnabled(IdealisticOfficePromptTask::MANAGE_WEEK_ACCESSES)) {
                            $timeAccess = $role->getTimeAccess(false);
                            $weekAccess = $role->getWeekAccess(false);

                            if ($timeAccess->hasAccess($timezone)
                                && $weekAccess->hasAccess($timezone)) {
                                return new IdealisticOfficeOutcome(
                                    true,
                                    "Access granted through your role '" . $role->getName() . "'."
                                );
                            }
                            $lastFailure[] = "Role '" . $role->getName() . "' restricted to " .
                                $timeAccess->getStartHour() . ":" . $timeAccess->getStartMinutes() . "-" .
                                $timeAccess->getEndHour() . ":" . $timeAccess->getEndMinutes() .
                                " on " . $weekAccess->getDaysString() . ".";
                        } else if ($isEnabled(IdealisticOfficePromptTask::MANAGE_TIME_ACCESSES)) {
                            $timeAccess = $role->getTimeAccess(false);

                            if ($timeAccess->hasAccess($timezone)) {
                                return new IdealisticOfficeOutcome(
                                    true,
                                    "Access granted through your role '" . $role->getName() . "'."
                                );
                            } else {
                                $lastFailure[] = "Role '" . $role->getName() . "' restricted to " .
                                    $timeAccess->getStartHour() . ":" . $timeAccess->getStartMinutes() . "-" .
                                    $timeAccess->getEndHour() . ":" . $timeAccess->getEndMinutes() .
                                    " " . $timezone->getTimeZone() . ".";
                            }
                        } else if ($isEnabled(IdealisticOfficePromptTask::MANAGE_WEEK_ACCESSES)) {
                            $weekAccess = $role->getWeekAccess(false);

                            if ($weekAccess->hasAccess($timezone)) {
                                return new IdealisticOfficeOutcome(
                                    true,
                                    "Access granted through your role '" . $role->getName() . "'."
                                );
                            } else {
                                $lastFailure[] = "Role '" . $role->getName() . "' restricted to " .
                                    $weekAccess->getDaysString() . ".";
                            }
                        }
                    }

                    if (!empty($lastFailure)) {
                        return new IdealisticOfficeOutcome(
                            false,
                            implode(IdealisticOfficeVariable::DEFAULT_TEXT_SEPARATOR, $lastFailure)
                        );
                    }
                }
            }
        }
        return new IdealisticOfficeOutcome(
            true,
            "Access granted."
        );
    }

    public function getId(): int|string
    {
        return $this->account->getDetail("id", 0);
    }

    public function getEmail(): int|string|null
    {
        return $this->account->getDetail("email_address", null);
    }

    public function hasEmail(): bool
    {
        return $this->getEmail() !== null;
    }

    public function getNickname(): ?string
    {
        if ($this->hasEstablishedAccess()
            && $this->hasEmail()) {
            return strtolower(explode("@", $this->getEmail(), 2)[0]);
        }
        return null;
    }

    public function exists(): bool
    {
        return $this->account->exists();
    }

    public function hasEstablishedAccess(): bool
    {
        return $this->team->hasEstablishedAccess()
            && $this->exists();
    }

    public function getAccount(): Account
    {
        return $this->account;
    }

    public function setAccount(Account $account): void
    {
        $this->account = $account;
    }

    public function getHistoryIDs(int $limit = 10_000): array
    {
        if (!$this->hasEstablishedAccess()) {
            return array();
        }
        $query = get_sql_query(
            IdealisticOfficeTable::HISTORY_TOTAL_TABLE,
            array("id"),
            array(
                array("team_id", $this->team->getId()),
                array("account_id", $this->getId())
            ),
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
    public function getHistory(
        int $afterId = 0,
        int $limit = IdealisticOfficeLimit::MAX_PROCESSED_ROWS
    ): array
    {
        if (!$this->hasEstablishedAccess()) {
            return array();
        }
        $query = get_sql_query(
            IdealisticOfficeTable::HISTORY_TOTAL_TABLE,
            IdealisticOfficeQuery::HISTORY,
            array(
                array("team_id", $this->team->getId()),
                array("account_id", $this->getId()),
                $afterId > 0 ? array("id", ">", $afterId) : ""
            ),
            array(
                "DESC",
                "id"
            ),
            $limit
        );

        if (!empty($query)) {
            $account = $this->getAccount();

            foreach ($query as $id => $row) {
                $query[$id] = new IdealisticOfficeHistory(
                    $this->team,
                    $row->id,
                    $row->platform_id,
                    $row->platform_user_id,
                    $row->platform_message_id,
                    $row->platform_chat_id,
                    $row->platform_chat_name,
                    $account,
                    $row->creation_date,
                    $row->creation_time_zone,
                    null
                );
            }
        }
        return $query;
    }

    public function getLastPlatformHistory(
        IdealisticOfficePrompt|IdealisticOfficeUserIdentity|int $reference,
        ?int                                                    $ignoreId = null
    ): ?IdealisticOfficeHistory
    {
        if ($this->hasEstablishedAccess()) {
            if (is_int($reference)) {
                $query = get_sql_query(
                    IdealisticOfficeTable::HISTORY_TOTAL_TABLE,
                    IdealisticOfficeQuery::HISTORY,
                    array(
                        array("team_id", $this->team->getId()),
                        array("account_id", $this->getId()),
                        array("platform_id", $reference),
                        $ignoreId !== null ? array("id", "!=", $ignoreId) : ""
                    ),
                    array(
                        "DESC",
                        "id"
                    ),
                    1
                );
            } else {
                $query = null;
            }
        } else if (!is_int($reference)) {
            $query = get_sql_query(
                IdealisticOfficeTable::HISTORY_TOTAL_TABLE,
                IdealisticOfficeQuery::HISTORY,
                array(
                    array("team_id", null),
                    array("account_id", null),
                    $ignoreId !== null ? array("id", "!=", $ignoreId) : "",
                    array("platform_id", $reference),
                    array("platform_user_id", $reference->getPlatformUserID()),
                    null,
                    array("platform_chat_id", "=", $reference->getPlatformChatID(), 0),
                    array("platform_chat_name", $reference->getPlatformChatName()),
                    null
                ),
                array(
                    "DESC",
                    "id"
                ),
                1
            );
        } else {
            $query = null;
        }

        if (empty($query)) {
            return null;
        } else {
            $row = $query[0];
            return new IdealisticOfficeHistory(
                $this->team,
                $row->id,
                $row->platform_id,
                $row->platform_user_id,
                $row->platform_message_id,
                $row->platform_chat_id,
                $row->platform_chat_name,
                $this->getAccount(),
                $row->creation_date,
                $row->creation_time_zone,
                null
            );
        }
    }

    public function getRawObject(): ?object
    {
        return $this->getTeamManager()->getMember();
    }

    public function getLevel(): int
    {
        return $this->getTeamManager()->getPosition($this->account);
    }

    public function getDisplayName(bool $backup = true): ?string
    {
        $lastIdentities = $this->getLastIdentities();

        if (!empty($lastIdentities)) {
            foreach ($lastIdentities as $lastIdentity) {
                $lastIdentity = $lastIdentity->getDisplayName();

                if (!empty($lastIdentity)) {
                    return $lastIdentity;
                }
            }
        }
        if ($backup) {
            $middleName = $this->getAccount()->getDetail(AccountActions::MIDDLE_NAME, null);
            $lastName = $this->getAccount()->getDetail(AccountActions::LAST_NAME, null);
            $lastIdentity = trim(
                $this->getAccount()->getDetail(AccountActions::FIRST_NAME, "")
                . ($middleName !== null ? " " . $middleName : "")
                . ($lastName !== null ? " " . $lastName : "")
            );

            if (empty($lastIdentity)) {
                $lastIdentity = $this->getNickname() ?? $this->getId();
            }
            return $lastIdentity;
        }
        return null;
    }

    // Separator

    public function updateNameFromSetting(): IdealisticOfficeOutcome
    {
        $fullName = $this->getSetting(IdealisticOfficeSettings::NEW_FULL_NAME);

        if ($fullName !== null) {
            $fullName = explode(" ", $fullName, 3);

            if (sizeof($fullName) === 3) {
                return $this->setName(
                    $fullName[0],
                    $fullName[1],
                    $fullName[2]
                );
            } else if (sizeof($fullName) === 2) {
                return $this->setName(
                    $fullName[0],
                    null,
                    $fullName[1]
                );
            } else {
                return $this->setName(
                    $fullName[0],
                    null,
                    null
                );
            }
        }
        return new IdealisticOfficeOutcome(false, "No new name set.");
    }

    public function setName(
        ?string $firstName,
        ?string $middleName,
        ?string $lastName
    ): IdealisticOfficeOutcome
    {
        if (!$this->hasEstablishedAccess()) {
            return new IdealisticOfficeOutcome(false, "No team access established.");
        }
        if ($firstName === null
            && $middleName === null
            && $lastName === null) {
            return new IdealisticOfficeOutcome(false, "At least one name must be set.");
        }
        if ($firstName !== null
            && mb_strlen($firstName) > IdealisticOfficeLimit::T64_LENGTH) {
            return new IdealisticOfficeOutcome(false, "First name exceeds character limit, must be " . IdealisticOfficeLimit::T64_LENGTH . " characters or less.");
        }
        if ($middleName !== null
            && mb_strlen($middleName) > IdealisticOfficeLimit::T64_LENGTH) {
            return new IdealisticOfficeOutcome(false, "Middle name exceeds character limit, must be " . IdealisticOfficeLimit::T64_LENGTH . " characters or less.");
        }
        if ($lastName !== null
            && mb_strlen($lastName) > IdealisticOfficeLimit::T64_LENGTH) {
            return new IdealisticOfficeOutcome(false, "Last name exceeds character limit, must be " . IdealisticOfficeLimit::T64_LENGTH . " characters or less.");
        }
        if ($this->account->setDetail(AccountActions::FIRST_NAME, $firstName)
            && $this->account->setDetail(AccountActions::MIDDLE_NAME, $middleName)
            && $this->account->setDetail(AccountActions::LAST_NAME, $lastName)) {
            return new IdealisticOfficeOutcome(true, "Member name successfully set.");
        } else {
            return new IdealisticOfficeOutcome(false, "Member name failed to be set.");
        }
    }

    // Separator

    public function getAICostPerCurrency(string $date1, string $date2): array
    {
        if (!$this->hasEstablishedAccess()) {
            return array();
        }
        $query = get_sql_query(
            IdealisticOfficeTable::AI_REQUESTS_TABLE,
            array(
                "cost",
                "currency",
                "creation_date"
            ),
            array(
                array("team_id", $this->team->getId()),
                array("account_id", $this->getId()),
                array("creation_date", ">=", $date1),
                array("creation_date", "<=", $date2),
            ),
            array(
                "DESC",
                "id"
            )
        );
        if (empty($query)) {
            return array();
        } else {
            $array = array();

            foreach ($query as $row) {
                if (!array_key_exists($row->currency, $array)) {
                    $array[$row->currency] = array();
                    $array[$row->currency]["total"] = 0.0;
                    $array[$row->currency]["count"] = 0;
                }
                $date = substr($row->creation_date, 0, 10);

                if (array_key_exists($date, $array[$row->currency])) {
                    $array[$row->currency][$date] += $row->cost;
                } else {
                    $array[$row->currency][$date] = $row->cost;
                }
                $array[$row->currency]["total"] += $row->cost;
                $array[$row->currency]["count"]++;
            }
            return $array;
        }
    }

    // Separator

    public function getProcessingPrompts(
        int             $limit,
        int|string|null $ignoreID = null
    ): int
    {
        if ($ignoreID !== null) {
            $limit++;
        }
        $query = get_sql_query(
            IdealisticOfficeTable::HISTORY_TOTAL_TABLE,
            array(
                "creation_date",
                "creation_time_zone",
                "id"
            ),
            array(
                array("processing", true),
                array("account_id", $this->getId())
            ),
            array(
                "DESC",
                "id"
            ),
            $limit
        );

        if (empty($query)) {
            return 0;
        } else {
            $count = 0;
            $pastTime = IdealisticOfficeLimit::HISTORY_PROMPT_SECONDS_TIMEOUT . " seconds";

            foreach ($query as $row) {
                if (IdealisticOfficeReader::getPastDate(
                        $pastTime,
                        $row->creation_time_zone
                    ) <= $row->creation_date) {
                    if ($ignoreID === null
                        || $row->id != $ignoreID) {
                        $count++;
                    }
                } else {
                    IdealisticOfficeError::setSqlQuery(
                        $this->team,
                        $this,
                        IdealisticOfficeTable::HISTORY_TOTAL_TABLE,
                        array(
                            "processing" => false,
                        ),
                        array(
                            array("id", $row->id)
                        ),
                        null,
                        1
                    );
                }
            }
            return $count;
        }
    }

    // Separator

    public function getAdminBlockedAccess(): ?IdealisticOfficeAdminBlockedAccess
    {
        $query = get_sql_query(
            IdealisticOfficeTable::TEAM_ADMIN_BLOCKED_ACCESS,
            array(
                "creation_date",
                "creation_reason",
                "creation_time_zone",
                "expiration_date",
                "expiration_time_zone"
            ),
            array(
                array("deletion_date", null),
                null,
                array("team_id", "=", $this->team->getId(), 0),
                array("account_id", $this->getId()),
                null
            ),
            array(
                "DESC",
                "id"
            ),
            1
        );
        if (empty($query)
            || $query[0]->expiration_date !== null
            && IdealisticOfficeReader::getCurrentDate($query[0]->expiration_time_zone) > $query[0]->expiration_date) {
            return null;
        } else {
            return new IdealisticOfficeAdminBlockedAccess(
                $query[0]->creation_reason,
                $query[0]->creation_date,
                $query[0]->creation_time_zone
            );
        }
    }

    // Separator

    public function updateTeamName(string $name): IdealisticOfficeOutcome
    {
        $methodReply = $this->getTeamManager()->updateTeamTitle($name);
        return new IdealisticOfficeOutcome(
            $methodReply->isPositiveOutcome(),
            $methodReply->getMessage()
        );
    }

    // Separator

    public function addNewUser(
        string $email
    ): IdealisticOfficeOutcome
    {
        if (!is_email($email)) {
            return new IdealisticOfficeOutcome(
                false,
                "Invalid email address."
            );
        }
        if (sizeof($this->team->getUsers(
                false,
                IdealisticOfficeLimit::MAX_EXTENSIONS_PER_OBJECT_LARGE
            )) === IdealisticOfficeLimit::MAX_EXTENSIONS_PER_OBJECT_LARGE) {
            return new IdealisticOfficeOutcome(
                false,
                "A team can have up to " . IdealisticOfficeLimit::MAX_EXTENSIONS_PER_OBJECT_LARGE . " members."
            );
        }
        $account = new Account(
            Account::IDEALISTIC_OFFICE_APPLICATION_ID,
            null,
            $email
        );

        if (!$account->exists()) {
            return new IdealisticOfficeOutcome(
                false,
                "Member does not exist."
            );
        }
        $methodReply = $this->getTeamManager()->addMember($account);
        return new IdealisticOfficeOutcome(
            $methodReply->isPositiveOutcome(),
            $methodReply->getMessage()
        );
    }

    // Separator

    public function hasLegalRecord(
        int $version = IdealisticOfficeLegalRecord::CURRENT_VERSION
    ): bool
    {
        if (!$this->hasEstablishedAccess()) {
            return false;
        }
        return !empty(get_sql_query(
            IdealisticOfficeTable::LEGAL_RECORDS_TABLE,
            array(
                "id",
            ),
            array(
                array("account_id", $this->getId()),
                array("current_version", $version),
            ),
            null,
            1
        ));
    }

    public function getLegalRecord(
        int  $version = IdealisticOfficeLegalRecord::CURRENT_VERSION,
        bool $json = true
    ): ?IdealisticOfficeLegalRecord
    {
        if (!$this->hasEstablishedAccess()) {
            return null;
        }
        $query = get_sql_query(
            IdealisticOfficeTable::LEGAL_RECORDS_TABLE,
            array(
                "history_id",
                "identity_object",
                "creation_date",
                "creation_time_zone",
                "full_name",
                "ip_address",
                "team_id",
                "id"
            ),
            array(
                array("account_id", $this->getId()),
                array("current_version", $version),
            ),
            null,
            1
        );

        if (empty($query)) {
            return null;
        }
        $identity = @json_decode(
            $query[0]->identity_object
        );

        if (!is_object($identity)) {
            return null;
        }
        return new IdealisticOfficeLegalRecord(
            $query[0]->id,
            $this->account,
            new IdealisticOfficeTeam($query[0]->team_id, false),
            $version,
            $query[0]->full_name,
            $query[0]->ip_address,
            $query[0]->history_id,
            $identity,
            $query[0]->creation_date,
            $query[0]->creation_time_zone,
            $json
        );
    }

    // Separator

    public function setLastIdentity(?IdealisticOfficeUserIdentity $identity): void
    {
        if (!$this->hasEstablishedAccess()) {
            $this->lastIdentity = $identity;
        }
    }

    public function getLastIdentity(?int $platformID = null, bool $json = false): ?IdealisticOfficeUserIdentity
    {
        if (!$this->hasEstablishedAccess()) {
            return $platformID === null
            || $this->lastIdentity?->getPlatformID() === $platformID
                ? $this->lastIdentity
                : null;
        }
        $query = get_sql_query(
            IdealisticOfficeTable::USER_IDENTITIES_TABLE,
            IdealisticOfficeQuery::USER_IDENTITY,
            array(
                array("account_id", $this->getId()),
                $platformID !== null ? array("platform_id", $platformID) : "",
                null,
                array("team_id", "=", $this->team->getId(), 0),
                array("team_id", 0),
                null
            ),
            "last_used_date DESC, id DESC",
            1
        );
        return empty($query)
            ? null
            : new IdealisticOfficeUserIdentity(
                $query[0]->id,
                $query[0]->user_name,
                $query[0]->display_name,
                $query[0]->platform_id,
                $query[0]->platform_user_id,
                $query[0]->platform_chat_id,
                $query[0]->platform_chat_name,
                $query[0]->platform_message_id,
                $query[0]->creation_date,
                $query[0]->creation_time_zone,
                $query[0]->last_used_date,
                $query[0]->last_used_time_zone,
                $json
            );
    }

    /** @return IdealisticOfficeUserIdentity[] */
    public function getLastIdentities(
        int  $amount = 0,
        ?int $platformID = null,
        bool $json = false
    ): array
    {
        if (!$this->hasEstablishedAccess()) {
            return array();
        }
        $query = get_sql_query(
            IdealisticOfficeTable::USER_IDENTITIES_TABLE,
            IdealisticOfficeQuery::USER_IDENTITY,
            array(
                array("account_id", $this->getId()),
                null,
                array("team_id", "=", $this->team->getId(), 0),
                array("team_id", 0),
                null,
                $platformID !== null ? array("platform_id", $platformID) : "",
            ),
            "last_used_date DESC, id DESC",
            $amount > 0
                ? $amount
                : sizeof(IdealisticOfficeAccessPlatform::getValidPlatforms())
        );

        if (empty($query)) {
            return array();
        } else {
            $array = array();

            foreach ($query as $row) {
                $array[] = new IdealisticOfficeUserIdentity(
                    $row->id,
                    $row->user_name,
                    $row->display_name,
                    $row->platform_id,
                    $row->platform_user_id,
                    $row->platform_chat_id,
                    $row->platform_chat_name,
                    $row->platform_message_id,
                    $row->creation_date,
                    $row->creation_time_zone,
                    $row->last_used_date,
                    $row->last_used_time_zone,
                    $json
                );
            }
            return $array;
        }
    }

    public function trackIdentity(
        int             $platformID,
        int|string      $platformUserID,
        int|string|null $platformChatID,
        ?string         $platformChatName,
        int|string|null $platformMessageID,
        ?string         $userName,
        ?string         $displayName
    ): void
    {
        $validPlatforms = IdealisticOfficeAccessPlatform::getValidPlatforms();

        if (!array_key_exists($platformID, $validPlatforms)
            || !$this->hasEstablishedAccess()) {
            return;
        }
        if ($userName !== null) {
            $userName = mb_substr($userName, 0, IdealisticOfficeLimit::T64_LENGTH);
        } else {
            $userName = "";
        }
        if ($displayName !== null) {
            $displayName = mb_substr($displayName, 0, IdealisticOfficeLimit::T64_LENGTH);
        } else {
            $displayName = "";
        }
        $last = $this->getLastIdentity($platformID);

        if ($last === null) {
            $timezone = $this->getTimezone(false);
            IdealisticOfficeError::sqlInsert(
                $this->team,
                $this,
                IdealisticOfficeTable::USER_IDENTITIES_TABLE,
                array(
                    "team_id" => $this->team->getId(),
                    "account_id" => $this->getId(),
                    "creation_date" => IdealisticOfficeReader::getCurrentDate($timezone),
                    "creation_time_zone" => $timezone->getTimeZone(),
                    "user_name" => $userName,
                    "display_name" => $displayName,
                    "platform_id" => $platformID,
                    "platform_user_id" => $platformUserID,
                    "platform_chat_id" => $platformChatID,
                    "platform_chat_name" => $platformChatName,
                    "platform_message_id" => $platformMessageID,
                    "last_used_date" => IdealisticOfficeReader::getCurrentDate($timezone),
                    "last_used_time_zone" => $timezone->getTimeZone()
                )
            );
            if (!empty($userName)) {
                $this->account->setDetail(AccountActions::NAME, $userName);
            }
            if (!empty($displayName)) {
                $appearanceNameSplit = explode(" ", $displayName, 3);

                if (sizeof($appearanceNameSplit) === 3) {
                    $this->account->setDetail(AccountActions::FIRST_NAME, $appearanceNameSplit[0]);
                    $this->account->setDetail(AccountActions::MIDDLE_NAME, $appearanceNameSplit[1]);
                    $this->account->setDetail(AccountActions::LAST_NAME, $appearanceNameSplit[2]);
                } else if (sizeof($appearanceNameSplit) === 2) {
                    $this->account->setDetail(AccountActions::FIRST_NAME, $appearanceNameSplit[0]);
                    $this->account->setDetail(AccountActions::MIDDLE_NAME, null);
                    $this->account->setDetail(AccountActions::LAST_NAME, $appearanceNameSplit[1]);
                } else {
                    $this->account->setDetail(AccountActions::FIRST_NAME, $appearanceNameSplit[0]);
                    $this->account->setDetail(AccountActions::MIDDLE_NAME, null);
                    $this->account->setDetail(AccountActions::LAST_NAME, null);
                }
            }
        } else {
            if (!empty($userName)
                && strtolower($last->getUserName() ?? "") !== strtolower($userName)) {
                $this->account->setDetail(AccountActions::NAME, $userName);
            }
            if (!empty($displayName)
                && strtolower($last->getDisplayName() ?? "") !== strtolower($displayName)) {
                $appearanceNameSplit = explode(" ", $displayName, 3);

                if (sizeof($appearanceNameSplit) === 3) {
                    $this->account->setDetail(AccountActions::FIRST_NAME, $appearanceNameSplit[0]);
                    $this->account->setDetail(AccountActions::MIDDLE_NAME, $appearanceNameSplit[1]);
                    $this->account->setDetail(AccountActions::LAST_NAME, $appearanceNameSplit[2]);
                } else if (sizeof($appearanceNameSplit) === 2) {
                    $this->account->setDetail(AccountActions::FIRST_NAME, $appearanceNameSplit[0]);
                    $this->account->setDetail(AccountActions::MIDDLE_NAME, null);
                    $this->account->setDetail(AccountActions::LAST_NAME, $appearanceNameSplit[1]);
                } else {
                    $this->account->setDetail(AccountActions::FIRST_NAME, $appearanceNameSplit[0]);
                    $this->account->setDetail(AccountActions::MIDDLE_NAME, null);
                    $this->account->setDetail(AccountActions::LAST_NAME, null);
                }
            }
            if ($last->getLastUsedDate() === null
                || $last->getLastUsedDateTimeZone() === null
                || $last->getPlatformUserID() != $platformUserID
                || $last->getPlatformChatID() != $platformChatID
                || $last->getPlatformChatName() != $platformChatName
                || $last->getPlatformMessageID() != $platformMessageID) {
                $timezone = $this->getTimezone(false);
                IdealisticOfficeError::setSqlQuery(
                    $this->team,
                    $this,
                    IdealisticOfficeTable::USER_IDENTITIES_TABLE,
                    array(
                        "platform_user_id" => $platformUserID,
                        "platform_chat_id" => $platformChatID,
                        "platform_chat_name" => $platformChatName,
                        "platform_message_id" => $platformMessageID,
                        "last_used_date" => IdealisticOfficeReader::getCurrentDate($timezone),
                        "last_used_time_zone" => $timezone->getTimeZone()
                    ),
                    array(
                        array("id", $last->getId())
                    ),
                    null,
                    1
                );
                IdealisticOfficeError::setSqlQuery(
                    $this->team,
                    $this,
                    IdealisticOfficeTable::USER_IDENTITIES_TABLE,
                    array(
                        "last_used_date" => null,
                        "last_used_time_zone" => null
                    ),
                    array(
                        array("id", "!=", $last->getId())
                    ),
                    null,
                    sizeof($validPlatforms) - 1
                );
            }
        }
    }

    // Separator

    /**
     * @param IdealisticOfficePrompt[] $prompts
     */
    public static function runMultiplePrompts(
        array                   $prompts,
        IdealisticOfficePrompt  $prompt,
        IdealisticOfficeHistory $history,
        string                  $defaultLanguage
    ): void
    {
        IdealisticOfficeTable::enableSqlLocalMemory($prompt->getPlatformId());
        $loop = Factory::create();
        Loop::set($loop);
        $cache = new IdealisticOfficePromptCache($prompt, $history, $loop, $defaultLanguage);
        $prompt->setCache($cache);
        $prompt->trackUserIdentity();

        $processingFunction = function () use ($prompt) {
            IdealisticOfficeError::setSqlQuery(
                $prompt->getTeam(),
                $prompt->getUser(),
                IdealisticOfficeTable::HISTORY_TOTAL_TABLE,
                array(
                    "processing" => false,
                ),
                array(
                    array("id", $prompt->getHistory()->getId())
                ),
                null,
                1
            );
        };

        $terminateFunction = function (mixed $error) use ($cache, $prompt, $history, $processingFunction) {
            $cache->terminateTask($prompt->getTaskID());
            IdealisticOfficeError::storeString(
                $prompt->getTeam(),
                $prompt->getUser(),
                $error
            );
            IdealisticOfficeError::sqlInsert(
                $prompt->getTeam(),
                $prompt->getUser(),
                IdealisticOfficeTable::HISTORY_REPLIES_TABLE,
                array(
                    "history_id" => $history->getId(),
                    "task_id" => IdealisticOfficePromptTask::ACCESS_CHECK,
                    "history_value" => $error
                )
            );
            $prompt->notifyWithoutTrace($error);
            $processingFunction();
        };

        foreach ($prompts as $prompt) {
            $prompt->setCache($cache);
            $class = IdealisticOfficePrompt::CLASS_NAME . $prompt->getTaskID();

            if (class_exists($class)) {
                try {
                    $class::run($prompt);
                } catch (Throwable $e) {
                    $terminateFunction("Error running prompt task: " . $e->getMessage());
                    break;
                }
            } else {
                $terminateFunction("Prompt class not found: " . $class);
                break;
            }
        }
        $loop->run();

        if (!$prompt->getHistory()->replied()) {
            $finalReply = $prompt->getHistory()->addFinalFailedReply($prompt->getUser());

            if ($finalReply !== null
                && in_array($prompt->getPlatformId(), IdealisticOfficeLimit::DISALLOWS_MESSAGE_EDITS)) {
                $prompt->notifyWithoutTrace($finalReply);
            }
        }
        $processingFunction();
    }

    public function getPrompt(
        ?int $historyID,
        bool $processingFinished = false
    ): ?IdealisticOfficeHistory
    {
        $query = get_sql_query(
            IdealisticOfficeTable::HISTORY_TOTAL_TABLE,
            IdealisticOfficeQuery::HISTORY,
            array(
                array("id", $historyID)
            ),
            null,
            1
        );

        if (empty($query)) {
            return null;
        }
        $row = $query[0];
        $account = $this->getAccount();
        $history = new IdealisticOfficeHistory(
            $this->team,
            $row->id,
            $row->platform_id,
            $row->platform_user_id,
            $row->platform_message_id,
            $row->platform_chat_id,
            $row->platform_chat_name,
            $account,
            $row->creation_date,
            $row->creation_time_zone
        );
        $processing = $history->isProcessing();

        if ($processing) {
            if ($processingFinished) {
                return null;
            }
        } else {
            IdealisticOfficeError::setSqlQuery(
                $this->team,
                $this,
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
            $history->setProcessing(false);
        }
        return $history;
    }

    // Separator

    /** @return IdealisticOfficeNotification[] */
    public function getNotifications(bool $delivered = false, int $limit = 0): array
    {
        if (!$this->hasEstablishedAccess()) {
            return array();
        }
        $query = get_sql_query(
            IdealisticOfficeTable::NOTIFICATIONS_TABLE,
            IdealisticOfficeQuery::NOTIFICATION,
            array(
                array("team_id", $this->team->getId()),
                array("account_id", $this->getId()),
                $delivered
                    ? array("delivered_date", "IS NOT", null)
                    : array("delivered_date", null)
            ),
            null,
            $limit <= 0
                ? IdealisticOfficeLimit::MAX_EXTENSIONS_PER_OBJECT
                : min($limit, IdealisticOfficeLimit::MAX_EXTENSIONS_PER_OBJECT)
        );

        if (!empty($query)) {
            foreach ($query as $key => $row) {
                $query[$key] = new IdealisticOfficeNotification(
                    $this,
                    $row->created_by,
                    $row->id,
                    $row->notification_message,
                    $row->creation_date,
                    $row->creation_time_zone,
                    $row->platform_id,
                    $row->platform_user_id,
                    $row->platform_chat_id,
                    $row->platform_chat_name,
                    $row->platform_message_id,
                    $row->notification_attachment_name,
                    $row->notification_attachment_content,
                    $row->notification_attachment_url,
                    $row->notification_attachment_format,
                    $row->is_base64 !== null,
                    $row->is_video !== null,
                    $row->is_image !== null,
                    $row->is_audio !== null,
                    $row->translate_message !== null,
                    $row->delete_on_delivery !== null
                );
            }
        }
        return $query;
    }

    public function addNotification(
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
        if ($attachmentURL !== null
            && mb_strlen($attachmentURL) > IdealisticOfficeLimit::K3_LENGTH) {
            $attachmentURL = null;
        }
        $identity = $this->getLastIdentity();

        if ($identity !== null) {
            $time = IdealisticOfficeLimit::LATE_NOTIFICATION_TIME[$identity->getPlatformID()] ?? null;
            $limit = IdealisticOfficeLimit::MONTHLY_LATE_NOTIFICATION_LIMIT[$identity->getPlatformID()] ?? null;

            if ($time !== null
                && $limit !== null) {
                $history = $this->getLastPlatformHistory($identity->getPlatformID());

                if ($history !== null) {
                    $creationDate = $history->getCreationDate();
                    $creationTimeZone = $history->getCreationDateTimeZone();
                } else {
                    $creationDate = $identity->getCreationDate();
                    $creationTimeZone = $identity->getCreationDateTimeZone();
                }
                $currentDate = IdealisticOfficeReader::getCurrentDate($creationTimeZone);

                if (strtotime($currentDate) - strtotime($creationDate) >= $time) {
                    $year = IdealisticOfficeReader::getCurrentDate($creationTimeZone, "Y");
                    $month = IdealisticOfficeReader::getCurrentDate($creationTimeZone, "m");
                    $count = sizeof(get_sql_query(
                        IdealisticOfficeTable::LATE_NOTIFICATIONS_TABLE,
                        array("id"),
                        array(
                            array("platform_id", $identity->getPlatformID()),
                            array("year", $year),
                            array("month", $month),
                        ),
                        null,
                        $limit
                    ));

                    if ($count === $limit) {
                        if ($message !== null) {
                            if (is_array($message)) {
                                $message = implode(IdealisticOfficeVariable::DEFAULT_TEXT_SEPARATOR, $message);
                            }
                            if ($this->hasEmail()) {
                                IdealisticOfficeNotifications::sendEmail(
                                    $this->getEmail(),
                                    $message
                                );
                            }
                        }
                        return false;
                    } else {
                        IdealisticOfficeError::sqlInsert(
                            $this->team,
                            $this,
                            IdealisticOfficeTable::LATE_NOTIFICATIONS_TABLE,
                            array(
                                "team_id" => $this->team->getId(),
                                "account_id" => $this->getId(),
                                "platform_id" => $identity->getPlatformID(),
                                "year" => $year,
                                "month" => $month,
                                "day" => IdealisticOfficeReader::getCurrentDate($creationTimeZone, "d")
                            )
                        );
                    }
                }
            }
        }
        if ($message !== null) {
            if (is_array($message)) {
                $message = implode(IdealisticOfficeVariable::DEFAULT_TEXT_SEPARATOR, $message);
            }
            if (mb_strlen($message) > IdealisticOfficeLimit::K10_LENGTH) {
                $message = mb_substr($message, 0, IdealisticOfficeLimit::K10_LENGTH - 3) . "...";
            }
        }
        $timezone = $this->getTimezone(false);

        if (IdealisticOfficeError::sqlInsert(
            $this->team,
            $this,
            IdealisticOfficeTable::NOTIFICATIONS_TABLE,
            array(
                "team_id" => $this->team->hasEstablishedAccess() ? $this->team->getId() : null,
                "account_id" => $this->hasEstablishedAccess() ? $this->getId() : null,
                "translate_message" => $translate,
                "notification_message" => $message,
                "platform_id" => $identity?->getPlatformID(),
                "platform_user_id" => $identity?->getPlatformUserID(),
                "platform_chat_id" => $identity?->getPlatformChatID(),
                "platform_chat_name" => $identity?->getPlatformChatName(),
                "platform_message_id" => $identity?->getPlatformMessageID(),
                "notification_attachment_name" => $attachmentName,
                "notification_attachment_content" => $attachmentContent,
                "notification_attachment_url" => $attachmentURL,
                "notification_attachment_format" => $attachmentFormat,
                "is_base64" => $base64,
                "is_image" => $isImage,
                "is_video" => $isVideo,
                "is_audio" => $isAudio,
                "delete_on_delivery" => $deleteOnDelivery,
                "creation_date" => IdealisticOfficeReader::getCurrentDate($timezone),
                "creation_time_zone" => $timezone->getTimeZone(),
                "created_by" => $executor?->getId()
            )
        )) {
            return true;
        } else {
            return false;
        }
    }

    public function addAttachmentNotification(
        ?IdealisticOfficeUser      $executor,
        string|array|null          $message,
        IdealisticOfficeAttachment $attachment,
        bool                       $createURL = false,
        bool                       $translate = true,
        bool                       $deleteOnDelivery = false
    ): bool
    {
        return $this->addNotification(
            $executor,
            $message,
            $attachment->getName(),
            $attachment->getRawData(),
            $attachment->getUrl(false, $createURL),
            $attachment->getFormat(true),
            $attachment->isBase64(),
            $attachment->isImage(),
            $attachment->isVideo(),
            $attachment->isAudio(),
            $translate,
            $deleteOnDelivery
        );
    }

    // Separator

    public function getLanguageSelection(bool $json = true): IdealisticOfficeLanguageSelection
    {
        if (!$this->hasEstablishedAccess()) {
            return $this->team->getLanguageSelection($json);
        }
        $setting = $this->getSetting(IdealisticOfficeSettings::SELECTED_LANGUAGE);

        if (empty($setting)
            || !is_string($setting)) {
            return $this->team->getLanguageSelection($json);
        }
        return new IdealisticOfficeLanguageSelection(
            $this->team,
            $setting,
            $json
        );
    }

    public function hasLanguageSelection(): bool
    {
        if (!$this->hasEstablishedAccess()) {
            return $this->team->hasLanguageSelection();
        }
        $setting = $this->getSetting(IdealisticOfficeSettings::SELECTED_LANGUAGE);
        return empty($setting)
            || !is_string($setting);
    }

    // Separator

    public function setUserLanguageSelection(string $language): IdealisticOfficeOutcome
    {
        if (!$this->hasEstablishedAccess()) {
            return new IdealisticOfficeOutcome(false, "No team access established.");
        }
        if (!$this->hasPermission(IdealisticOfficePermission::ADJUST_USER_LANGUAGE_SELECTION)->isPositiveOutcome()) {
            return new IdealisticOfficeOutcome(false, "You do not have permission to set member language selections.");
        }
        if (mb_strlen($language) > IdealisticOfficeLimit::T64_LENGTH) {
            return new IdealisticOfficeOutcome(false, "Language exceeds character limit, must be " . IdealisticOfficeLimit::T64_LENGTH . " characters or less.");
        }
        $language = strtolower($language);

        if (!IdealisticOfficeReader::isLatinOnly($language)) {
            return new IdealisticOfficeOutcome(false, "Language must be in Latin characters.");
        }
        if (!in_array($language, IdealisticOfficeLimit::ALL_LANGUAGES)) {
            return new IdealisticOfficeOutcome(false, "Language '" . $language . "' is not supported.");
        }
        $previousLanguage = $this->getLanguageSelection(false);

        if ($previousLanguage->getLanguage() === $language) {
            return new IdealisticOfficeOutcome(false, "Member language selection is already set to this language.");
        }
        return $this->setSetting(
            IdealisticOfficeSettings::SELECTED_LANGUAGE,
            $language
        );
    }

    // Separator

    public function getTimezone(bool $json = true): IdealisticOfficeTimezone
    {
        if (!$this->hasEstablishedAccess()) {
            return $this->team->getTimezone($json);
        }
        $setting = $this->getSetting(IdealisticOfficeSettings::SELECTED_TIMEZONE);

        if (empty($setting)
            || !is_string($setting)) {
            return $this->team->getTimezone($json);
        }
        return new IdealisticOfficeTimezone(
            $this->team,
            $setting,
            $json
        );
    }

    // Separator

    public function getRetentionDays(): int
    {
        return max(
            IdealisticOfficeVariable::DATA_RETENTION_MINIMUM_DAYS,
            min(
                IdealisticOfficeVariable::DATA_RETENTION_MAXIMUM_DAYS,
                (int)$this->getSetting(IdealisticOfficeSettings::DATA_RETENTION_DAYS)
            )
        );
    }

    public function setRetentionDays(int $days): IdealisticOfficeOutcome
    {
        if (!$this->hasEstablishedAccess()) {
            return new IdealisticOfficeOutcome(false, "No team access established.");
        }
        if ($days < IdealisticOfficeVariable::DATA_RETENTION_MINIMUM_DAYS
            || $days > IdealisticOfficeVariable::DATA_RETENTION_MAXIMUM_DAYS) {
            return new IdealisticOfficeOutcome(
                false,
                "Data can be kept for " . IdealisticOfficeVariable::DATA_RETENTION_MINIMUM_DAYS
                . " to " . IdealisticOfficeVariable::DATA_RETENTION_MAXIMUM_DAYS . " days."
            );
        }
        if ($this->getRetentionDays() === $days) {
            return new IdealisticOfficeOutcome(false, "Data retention is already set to " . $days . " days.");
        }
        return $this->setSetting(IdealisticOfficeSettings::DATA_RETENTION_DAYS, $days);
    }

    public function setUserTimezone(string $newTimezone): IdealisticOfficeOutcome
    {
        if (!$this->hasEstablishedAccess()) {
            return new IdealisticOfficeOutcome(false, "No team access established.");
        }
        if (!$this->hasPermission(IdealisticOfficePermission::ADJUST_USER_TIMEZONE)->isPositiveOutcome()) {
            return new IdealisticOfficeOutcome(false, "You do not have permission to set member timezones.");
        }
        if (mb_strlen($newTimezone) > IdealisticOfficeLimit::T64_LENGTH) {
            return new IdealisticOfficeOutcome(false, "Time zone exceeds character limit, must be " . IdealisticOfficeLimit::T64_LENGTH . " characters or less.");
        }
        try {
            new DateTimeZone($newTimezone);
        } catch (Throwable $ignored) {
            return new IdealisticOfficeOutcome(false, "Invalid timezone.");
        }
        $timezone = $this->getTimezone(false);

        if ($timezone->getTimeZone() == $newTimezone) {
            return new IdealisticOfficeOutcome(false, "Member time zone is already set to this time zone.");
        }
        return $this->setSetting(
            IdealisticOfficeSettings::SELECTED_TIMEZONE,
            $newTimezone
        );
    }

    // Separator

    public final function getSpecificScheduledTask(int $id, bool $json = true, bool $deleted = false): ?IdealisticOfficeScheduler
    {
        if (!$this->hasEstablishedAccess()
            || $id <= 0) {
            return null;
        }
        $query = get_sql_query(
            IdealisticOfficeTable::SCHEDULER_TABLE,
            IdealisticOfficeQuery::SCHEDULER,
            array(
                array("team_id", $this->team->getId()),
                array("account_id", $this->getId()),
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
            return new IdealisticOfficeScheduler(
                $this->team,
                $query[0]->id,
                $query[0]->account_id,
                $query[0]->creation_date,
                $query[0]->creation_time_zone,
                $query[0]->creation_reason,
                $query[0]->month,
                $query[0]->day,
                $query[0]->hour,
                $query[0]->minute,
                $query[0]->next_date,
                $query[0]->seconds_frequency,
                $query[0]->history_id,
                $json
            );
        }
    }

    /** @return IdealisticOfficeScheduler[] */
    public final function getScheduledTasks(int $limit = 0, bool $json = true, bool $deleted = false): array
    {
        if (!$this->hasEstablishedAccess()) {
            return array();
        }
        $query = get_sql_query(
            IdealisticOfficeTable::SCHEDULER_TABLE,
            IdealisticOfficeQuery::SCHEDULER,
            array(
                array("team_id", $this->team->getId()),
                array("account_id", $this->getId()),
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
                $query[$key] = new IdealisticOfficeScheduler(
                    $this->team,
                    $row->id,
                    $row->account_id,
                    $row->creation_date,
                    $row->creation_time_zone,
                    $row->creation_reason,
                    $row->month,
                    $row->day,
                    $row->hour,
                    $row->minute,
                    $row->next_date,
                    $row->seconds_frequency,
                    $row->history_id,
                    $json
                );
            }
        }
        return $query;
    }

    // Separator

    public function getInstructionsManager(): IdealisticOfficeInstructionsManager
    {
        return new IdealisticOfficeInstructionsManager($this);
    }

    // Separator

    public function getTimeAccess(AbstractIdealisticOfficeTeam $team, bool $json = true): IdealisticOfficeTimeAccess
    {
        if (!$this->hasEstablishedAccess()) {
            return $team->getTimeAccess($json);
        }
        if ($this->team->getId() === $team->getId()
            && $this->isOwner()) {
            $timezone = $this->getTimezone(false);
            return new IdealisticOfficeTimeAccess(
                0,
                new Account(
                    Account::IDEALISTIC_OFFICE_APPLICATION_ID,
                ),
                $team,
                "Specific member is not subject to restrictions",
                IdealisticOfficeReader::getCurrentDate($timezone),
                0,
                0,
                23,
                59,
                $timezone->getTimeZone(),
                $json
            );
        }
        $query = get_sql_query(
            IdealisticOfficeTable::USER_TIME_ACCESS_TABLE,
            IdealisticOfficeQuery::TIME_ACCESS,
            array(
                array("team_id", $team->getId()),
                array("account_id", $this->getId()),
            ),
            array(
                "DESC",
                "id"
            ),
            1
        );
        if (empty($query)) {
            return $team->getTimeAccess($json);
        } else {
            return new IdealisticOfficeTimeAccess(
                $query[0]->id,
                $query[0]->created_by,
                $team,
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

    public function getTimeAccessManager(): IdealisticOfficeTimeAccessManager
    {
        return new IdealisticOfficeTimeAccessManager($this);
    }

    // Separator

    public function getWeekAccess(AbstractIdealisticOfficeTeam $team, bool $json = true): IdealisticOfficeWeeklyAccess
    {
        if (!$this->hasEstablishedAccess()) {
            return $team->getWeekAccess($json);
        }
        if ($this->team->getId() === $team->getId()
            && $this->isOwner()) {
            $timezone = $this->getTimezone($json);
            return new IdealisticOfficeWeeklyAccess(
                0,
                0,
                $team,
                "Specific member is not subject to restrictions",
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
            IdealisticOfficeTable::USER_WEEK_ACCESS_TABLE,
            IdealisticOfficeQuery::WEEK_ACCESS,
            array(
                array("team_id", $team->getId()),
                array("role_id", $this->getId())
            ),
            array(
                "DESC",
                "id"
            ),
            1
        );
        if (empty($query)) {
            return $team->getWeekAccess($json);
        } else {
            return new IdealisticOfficeWeeklyAccess(
                $query[0]->id,
                $query[0]->created_by,
                $team,
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

    public function getWeekAccessManager(): IdealisticOfficeWeeklyAccessManager
    {
        return new IdealisticOfficeWeeklyAccessManager($this);
    }

    // Separator

    public final function getFolders(bool $json = true): array
    {
        if (!$this->hasEstablishedAccess()) {
            return array();
        }
        $query = get_sql_query(
            IdealisticOfficeTable::FOLDER_PARTICIPANTS_TABLE,
            array("folder_id"),
            array(
                array("account_id", $this->getId()),
                array("deletion_date", null)
            ),
            array(
                "DESC",
                "id"
            ),
            IdealisticOfficeLimit::MAX_EXTENSIONS_PER_OBJECT_LARGE
        );
        $folders = array();

        foreach ($query as $value) {
            $folder = $this->team->getFolder($value->folder_id, $json);

            if ($folder !== null) {
                $folders[] = $folder;
            }
        }
        return $folders;
    }

    // Separator

    public final function isOwner(): bool
    {
        return $this->team->getOwnerId() == $this->getId();
    }

    // Separator

    public function getPaypalEmailManager(): IdealisticOfficePaypalEmailManager
    {
        return new IdealisticOfficePaypalEmailManager($this);
    }

    // Separator

    public function getTeam(): AbstractIdealisticOfficeTeam
    {
        return $this->team;
    }

    public function getEvolvedTeam(): IdealisticOfficeTeam
    {
        return $this->team;
    }

    public function getTeamManager(): AccountTeam
    {
        return $this->account->getTeam();
    }

    /** @return IdealisticOfficeRole[] */
    public function getRoles(bool $json = true): array
    {
        $roles = $this->account->getTeam()->getMemberRoles();

        if (empty($roles)) {
            return array();
        } else {
            $array = array();

            foreach ($roles as $role) {
                $array[(int)$role->id] = new IdealisticOfficeRole(
                    $this->team,
                    $role->id,
                    $json
                );
            }
            return $array;
        }
    }

    public function hasRole(int|IdealisticOfficeRole $roleToCompare): bool
    {
        $roles = $this->account->getTeam()->getMemberRoles();

        if (!empty($roles)) {
            foreach ($roles as $role) {
                if ($roleToCompare instanceof IdealisticOfficeRole
                    ? $role->id == $roleToCompare->getId()
                    : $role->id == $roleToCompare) {
                    return true;
                }
            }
        }
        return false;
    }

    public function getCreationDate(): ?string
    {
        return $this->account->getDetail("creation_date");
    }

    public function getCreationDateTimeZone(): string
    {
        return IdealisticOfficeVariable::DEFAULT_TIME_ZONE;
    }

    // Separator

    /** @return IdealisticOfficeUserShortcut[] */
    public function getShortcuts(int $limit = 0, bool $json = true): array
    {
        $query = get_sql_query(
            IdealisticOfficeTable::USER_SHORTCUTS_TABLE,
            array(
                "id",
                "creation_date",
                "creation_time_zone",
                "shortcut_word",
                "shortcut_replacement"
            ),
            array(
                array("account_id", $this->getId()),
                array("deletion_date", null)
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
                $query[$key] = new IdealisticOfficeUserShortcut(
                    $row->id,
                    $row->shortcut_word,
                    $row->shortcut_replacement,
                    $row->creation_date,
                    $row->creation_time_zone,
                    $json
                );
            }
        }
        return $query;
    }

    public function addShortcut(string $word, string $replacement): IdealisticOfficeOutcome
    {
        if (mb_strlen($word) < IdealisticOfficeLimit::SHORTCUT_WORD_MIN_LENGTH) {
            return new IdealisticOfficeOutcome(false, "Shortcut words must be at least " . IdealisticOfficeLimit::SHORTCUT_WORD_MIN_LENGTH . " characters.");
        }
        if (mb_strlen($word) > IdealisticOfficeLimit::SHORTCUT_WORD_MAX_LENGTH) {
            return new IdealisticOfficeOutcome(false, "Shortcut words exceeds character limit, must be " . IdealisticOfficeLimit::SHORTCUT_WORD_MAX_LENGTH . " characters or less.");
        }
        if (str_contains(
            $word,
            " "
        )) {
            return new IdealisticOfficeOutcome(false, "Shortcut words cannot contain spaces.");
        }
        if (str_starts_with(
            "/",
            $word
        )) {
            return new IdealisticOfficeOutcome(false, "Shortcut words cannot start with a '/'.");
        }
        if (mb_strlen($replacement) > IdealisticOfficeLimit::SHORTCUT_REPLACEMENT_MAX_LENGTH) {
            return new IdealisticOfficeOutcome(false, "Shortcut replacement exceeds character limit, must be " . IdealisticOfficeLimit::SHORTCUT_REPLACEMENT_MAX_LENGTH . " characters or less.");
        }
        $timezone = $this->getTimezone(false);

        if (IdealisticOfficeError::sqlInsert(
            $this->team,
            $this,
            IdealisticOfficeTable::USER_SHORTCUTS_TABLE,
            array(
                "account_id" => $this->getId(),
                "creation_date" => IdealisticOfficeReader::getCurrentDate($timezone),
                "creation_time_zone" => $timezone->getTimeZone(),
                "shortcut_word" => $word,
                "shortcut_replacement" => $replacement
            )
        )) {
            return new IdealisticOfficeOutcome(
                true,
                "Successfully added '" . $word . "' -> '" . $replacement . "' to shortcuts."
            );
        } else {
            return new IdealisticOfficeOutcome(
                false,
                "Could not add '" . $word . "' -> '" . $replacement . "' to shortcuts."
            );
        }
    }

    public function removeShortcut(int $id, ?string $justification = null): IdealisticOfficeOutcome
    {
        $query = get_sql_query(
            IdealisticOfficeTable::USER_SHORTCUTS_TABLE,
            array(
                "id",
                "shortcut_word",
                "shortcut_replacement"
            ),
            array(
                array("account_id", $this->getId()),
                array("deletion_date", null),
                array("id", $id)
            ),
            null,
            1
        );

        if (empty($query)) {
            return new IdealisticOfficeOutcome(false, "No shortcut found to remove with ID '" . $id . "'.");
        }
        if ($justification !== null
            && mb_strlen($justification) > IdealisticOfficeLimit::K16_LENGTH) {
            return new IdealisticOfficeOutcome(false, "Shortcut deletion justification exceeds character limit, must be " . IdealisticOfficeLimit::K16_LENGTH . " characters or less.");
        }
        if (!isset($justification[0])) {
            $justification = null;
        }
        $query = $query[0];
        $timezone = $this->getTimezone(false);

        if (IdealisticOfficeError::setSqlQuery(
            $this->getTeam(),
            $this,
            IdealisticOfficeTable::USER_SHORTCUTS_TABLE,
            array(
                "deletion_date" => IdealisticOfficeReader::getCurrentDate($timezone),
                "deletion_reason" => $justification,
                "deletion_time_zone" => $timezone->getTimeZone()
            ),
            array(
                array("id", $id)
            ),
            null,
            1
        )) {
            return new IdealisticOfficeOutcome(true, "Shortcuts no longer contain '" . $query->shortcut_word . "' -> '" . $query->shortcut_replacement . "'.");
        } else {
            return new IdealisticOfficeOutcome(false, "Shortcut could not be removed, tried removing '" . $query->shortcut_word . "' -> '" . $query->shortcut_replacement . "'.");
        }
    }

    // Separator

    public function getRoleManager(): IdealisticOfficeRoleManager
    {
        return new IdealisticOfficeRoleManager($this);
    }

    // Separator

    public function getPermissions(): array
    {
        $permissions = $this->getTeamManager()->getMemberPermissions();

        if (!empty($permissions)) {
            foreach ($permissions as $key => $permission) {
                $permissions[$key] = $permission->definition->name;
            }
        }
        return $permissions;
    }

    public function hasPermission(int|string $permission): IdealisticOfficeOutcome
    {
        $outcome = $this->getTeamManager()->getMemberPermission(
            $this->account,
            $permission
        );
        return new IdealisticOfficeOutcome($outcome->isPositiveOutcome(), $outcome->getMessage());
    }

    public function addUserPermission(IdealisticOfficeUser $user, int|string $permission, ?string $justification = null): IdealisticOfficeOutcome
    {
        if (!isset($justification[0])) {
            $justification = null;
        }
        $outcome = $this->getTeamManager()->addMemberPermission(
            $user->getAccount(),
            $permission,
            $justification
        );
        return new IdealisticOfficeOutcome($outcome->isPositiveOutcome(), $outcome->getMessage());
    }

    public function removeUserPermission(IdealisticOfficeUser $user, int|string $permission, ?string $reason = null): IdealisticOfficeOutcome
    {
        $outcome = $this->getTeamManager()->removeMemberPermission(
            $user->getAccount(),
            $permission,
            $reason
        );
        return new IdealisticOfficeOutcome($outcome->isPositiveOutcome(), $outcome->getMessage());
    }

    // Separator

    public function addTeamPermission(int|string $permission, ?string $justification = null): IdealisticOfficeOutcome
    {
        if (!isset($justification[0])) {
            $justification = null;
        }
        $outcome = $this->getTeamManager()->addTeamPermission(
            $permission,
            $justification
        );
        return new IdealisticOfficeOutcome($outcome->isPositiveOutcome(), $outcome->getMessage());
    }

    public function removeTeamPermission(int|string $permission, ?string $reason = null): IdealisticOfficeOutcome
    {
        $outcome = $this->getTeamManager()->removeTeamPermission(
            $permission,
            $reason
        );
        return new IdealisticOfficeOutcome($outcome->isPositiveOutcome(), $outcome->getMessage());
    }

    // Separator

    public function setIncludedToTeamCredits(
        IdealisticOfficeUser $user,
        bool                 $trueFalse,
    ): IdealisticOfficeOutcome
    {
        $query = get_sql_query(
            IdealisticOfficeTable::TEAM_CREDIT_INCLUDED_MEMBERS_TABLE,
            array("id"),
            array(
                array("account_id", $this->getId()),
                array("deletion_date", null),
                array("team_id", $this->team->getId())
            ),
            null,
            1
        );

        if (empty($query)) {
            if ($trueFalse) {
                $timezone = $this->getTimezone(false);

                if (IdealisticOfficeError::sqlInsert(
                    $this->team,
                    $this,
                    IdealisticOfficeTable::TEAM_CREDIT_INCLUDED_MEMBERS_TABLE,
                    array(
                        "account_id" => $user->getId(),
                        "team_id" => $this->team->getId(),
                        "creation_date" => IdealisticOfficeReader::getCurrentDate($timezone),
                        "creation_time_zone" => $timezone->getTimeZone(),
                        "created_by" => $this->getId()
                    )
                )) {
                    return new IdealisticOfficeOutcome(true, "Member '" . $user->getDisplayName() . "' is now included in team credits.");
                } else {
                    return new IdealisticOfficeOutcome(false, "Member '" . $user->getDisplayName() . "' could not be added to team credits.");
                }
            } else {
                return new IdealisticOfficeOutcome(true, "Member '" . $user->getDisplayName() . "' is not included in team credits.");
            }
        } else {
            if (!$trueFalse) {
                $query = $query[0];
                $timezone = $this->getTimezone(false);

                if (IdealisticOfficeError::setSqlQuery(
                    $this->getTeam(),
                    $this,
                    IdealisticOfficeTable::TEAM_CREDIT_INCLUDED_MEMBERS_TABLE,
                    array(
                        "deletion_date" => IdealisticOfficeReader::getCurrentDate($timezone),
                        "deletion_time_zone" => $timezone->getTimeZone(),
                        "deleted_by" => $this->getId()
                    ),
                    array(
                        array("id", $query->id)
                    ),
                    null,
                    1
                )) {
                    return new IdealisticOfficeOutcome(true, "Member '" . $user->getDisplayName() . "' is no longer included in team credits.");
                } else {
                    return new IdealisticOfficeOutcome(false, "Member '" . $user->getDisplayName() . "' could not be removed from team credits.");
                }
            } else {
                return new IdealisticOfficeOutcome(true, "Member '" . $user->getDisplayName() . "' is already included in team credits.");
            }
        }
    }

    /** @return IdealisticOfficeTeam[] */
    public function getIncludedCreditTeams(bool $json = true): array
    {
        $query = get_sql_query(
            IdealisticOfficeTable::TEAM_CREDIT_INCLUDED_MEMBERS_TABLE,
            array("team_id"),
            array(
                array("account_id", $this->getId()),
                array("deletion_date", null)
            ),
            null,
            IdealisticOfficeLimit::MAX_EXTENSIONS_PER_OBJECT
        );

        if (!empty($query)) {
            $teams = array();

            foreach ($query as $key => $value) {
                $team = new IdealisticOfficeTeam($value->team_id, $json);

                if ($team->hasEstablishedAccess()) {
                    $query[$key] = $team;
                } else {
                    unset($query[$key]);
                }
            }
            return array_values($teams);
        }
        return array();
    }

    // Separator

    public function adjustUserHierarchy(IdealisticOfficeUser $user, int $level, ?string $justification = null): IdealisticOfficeOutcome
    {
        if (!isset($justification[0])) {
            $justification = null;
        }
        $owner = $this->team->getOwner(false);

        if ($owner === null) {
            return new IdealisticOfficeOutcome(false, "Team owner account not found, cannot determine if member can be set as team manager.");
        }
        $outcome = $this->getTeamManager()->adjustMemberPosition(
            $user->getAccount(),
            $level,
            $justification
        );
        return new IdealisticOfficeOutcome($outcome->isPositiveOutcome(), $outcome->getMessage());
    }

    // Separator

    public function getUserByNickname(string $nickname): ?IdealisticOfficeUser
    {
        $nickname = strtolower($nickname);
        $users = $this->team->getUsers(false);

        foreach ($users as $user) {
            if ($user->getNickname() === $nickname) {
                return $user;
            }
        }
        return null;
    }

    // Separator

    public function adjustPositionByComparison(
        IdealisticOfficeUser|IdealisticOfficeRole $reference,
        IdealisticOfficeUser|IdealisticOfficeRole $against,
        bool                                      $above = true,
        ?string                                   $justification = null
    ): IdealisticOfficeOutcome
    {
        if ($reference instanceof IdealisticOfficeRole) {
            $raw = $reference->getRawObject();

            if ($raw === null) {
                return new IdealisticOfficeOutcome(false, "Role not found.");
            }
            $outcome = $this->getTeamManager()->adjustPositionByComparison(
                $raw,
                $against->getRawObject(),
                $above,
                $justification
            );
        } else {
            $outcome = $this->getTeamManager()->adjustPositionByComparison(
                $reference->getAccount(),
                $against->getAccount(),
                $above,
                $justification
            );
        }
        return new IdealisticOfficeOutcome($outcome->isPositiveOutcome(), $outcome->getMessage());
    }

    // Separator

    public function deleteTeam(?string $justification = null): IdealisticOfficeOutcome
    {
        if (!isset($justification[0])) {
            $justification = null;
        }
        $outcome = $this->getTeamManager()->deleteTeam($justification);
        return new IdealisticOfficeOutcome($outcome->isPositiveOutcome(), $outcome->getMessage());
    }

    public function transferTeamOwnership(
        IdealisticOfficeUser $user,
        ?string              $justification = null,
        ?string              $verificationCode = null
    ): IdealisticOfficeOutcome
    {
        if ($verificationCode !== null) {
            $this->account->getEmail()->setSuffix("transferOwnership");
            $reply = $this->account->getTwoFactorAuthentication()->verify(
                null,
                $verificationCode,
                false
            );

            if (!$reply->isPositiveOutcome()) {
                $outcome = $this->account->getTwoFactorAuthentication()->initiate(
                    null,
                    true
                );
                return new IdealisticOfficeOutcome(false, $outcome->getMessage());
            }
        }
        if (!isset($justification[0])) {
            $justification = null;
        }
        $outcome = $this->getTeamManager()->transferTeamOwnership($user->getAccount(), $justification);
        return new IdealisticOfficeOutcome($outcome->isPositiveOutcome(), $outcome->getMessage());
    }

    // Separator

    public function getSetting(string $key, mixed $notFoundDefault = null): string|float|int|bool|null
    {
        $disabled = IdealisticOfficeSettings::DISABLED[$key] ?? null;

        if ($disabled !== null) {
            return $disabled;
        }
        $reply = $this->account->getSettings()->get(
            $key,
            $notFoundDefault !== null
                ? $notFoundDefault
                : IdealisticOfficeSettings::DEFAULTS[$key] ?? false
        );
        return $reply->getObject();
    }

    public function isSettingEnabled(string $key, mixed $notFoundDefault = null): bool
    {
        $disabled = IdealisticOfficeSettings::DISABLED[$key] ?? null;

        if ($disabled !== null) {
            return $disabled;
        }
        return $this->account->getSettings()->isEnabled(
            $key,
            $notFoundDefault !== null
                ? $notFoundDefault
                : IdealisticOfficeSettings::DEFAULTS[$key] ?? false
        );
    }

    public function setSetting(string $key, mixed $value): IdealisticOfficeOutcome
    {
        $outcome = $this->account->getSettings()->modify(
            $key,
            $value,
            null
        );
        $key = str_replace("_", " ", $key);
        return new IdealisticOfficeOutcome(
            $outcome->isPositiveOutcome(),
            $outcome->isPositiveOutcome()
                ? "Setting '$key' updated successfully to '" . (is_bool($value) ? ($value ? "enabled" : "disabled") : $value) . "'."
                : ($outcome->getMessage() ?? "Setting '$key' could not be updated.")
        );
    }

    public function toggleSetting(string $key): IdealisticOfficeOutcome
    {
        $outcome = $this->account->getSettings()->toggle(
            $key,
            IdealisticOfficeSettings::DEFAULTS[$key] ?? false,
            null
        );
        $key = str_replace("_", " ", $key);
        return new IdealisticOfficeOutcome(
            $outcome->isPositiveOutcome(),
            $outcome->getMessage() ??
            ($outcome->isPositiveOutcome()
                ? "Setting '$key' toggled successfully."
                : "Setting '$key' could not be toggled.")
        );
    }

    // Separator

    public function getSelectedFolderId(bool $rawId = false): ?int
    {
        if (!$this->hasEstablishedAccess()) {
            return null;
        }
        $select = array(
            "deletion_date"
        );

        if ($rawId) {
            $select[] = "id";
        } else {
            $select[] = "folder_id";
        }
        $query = get_sql_query(
            IdealisticOfficeTable::USER_SELECTED_FOLDER_TABLE,
            $select,
            array(
                array("team_id", $this->team->getId()),
                array("account_id", $this->getId())
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

            if ($row->deletion_date !== null) {
                return null;
            } else {
                return (int)($rawId ? $row->id : $row->folder_id);
            }
        }
    }

    public function removeSelectedFolder(): IdealisticOfficeOutcome
    {
        $obj = $this->getSelectedFolderId(true);

        if ($obj === null) {
            return new IdealisticOfficeOutcome(false, "No folder selection to remove.");
        } else {
            $timezone = $this->getTimezone(false);

            if (IdealisticOfficeError::setSqlQuery(
                $this->team,
                $this,
                IdealisticOfficeTable::USER_SELECTED_FOLDER_TABLE,
                array(
                    "deletion_date" => IdealisticOfficeReader::getCurrentDate($timezone),
                    "deletion_time_zone" => $timezone->getTimeZone()
                ),
                array(
                    array("id", $obj)
                ),
                null,
                1
            )) {
                return new IdealisticOfficeOutcome(true, "Selected folder removed successfully.");
            } else {
                return new IdealisticOfficeOutcome(false, "Selected folder could not be removed.");
            }
        }
    }

    public function setSelectedFolder(?IdealisticOfficeFolder $folder): IdealisticOfficeOutcome
    {
        if (!$this->hasEstablishedAccess()) {
            return new IdealisticOfficeOutcome(false, "No team access established.");
        }
        if ($folder === null) {
            return new IdealisticOfficeOutcome(false, "No team folder found to be selected.");
        }
        $selectedId = $this->getSelectedFolderId();

        if ($selectedId === $folder->getId()) {
            return new IdealisticOfficeOutcome(false, "Selected folder is already set to '" . $folder->getName() . "'.");
        }
        $timezone = $this->getTimezone(false);

        if (IdealisticOfficeError::sqlInsert(
            $this->team,
            $this,
            IdealisticOfficeTable::USER_SELECTED_FOLDER_TABLE,
            array(
                "team_id" => $this->team->getId(),
                "account_id" => $this->getId(),
                "folder_id" => $folder->getId(),
                "creation_date" => IdealisticOfficeReader::getCurrentDate($timezone),
                "creation_time_zone" => $timezone->getTimeZone()
            )
        )) {
            return new IdealisticOfficeOutcome(true, "Selected folder set to '" . $folder->getName() . "'.");
        } else {
            return new IdealisticOfficeOutcome(false, "Selected folder could not be set to '" . $folder->getName() . "'.");
        }
    }

    // Separator

    private function setCooldown(string $key, int|string $duration): bool
    {
        return $this->account->getCooldowns()->addInstant(
            $key,
            $duration
        );
    }

    private function getCooldown(string $key): bool
    {
        return $this->account->getCooldowns()->has($key);
    }

}
