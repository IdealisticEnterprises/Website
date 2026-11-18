<?php

namespace idealistic_office\api\objects;

use account\api\objects\account\Account;
use idealistic_office\api\handlers\IdealisticOfficeError;
use idealistic_office\api\handlers\IdealisticOfficePortalIndependent;
use idealistic_office\api\handlers\IdealisticOfficeProcessor;
use idealistic_office\api\handlers\IdealisticOfficeReader;
use idealistic_office\api\handlers\IdealisticOfficeTeamInitiator;
use idealistic_office\api\IdealisticOfficeVariable;
use idealistic_office\api\objects\helpers\AbstractIdealisticOfficeTeam;
use idealistic_office\api\objects\helpers\attachment\IdealisticOfficeAttachment;
use idealistic_office\api\objects\helpers\attachment\IdealisticOfficePartialAttachment;
use idealistic_office\api\objects\helpers\change\AbstractIdealisticOfficeChange;
use idealistic_office\api\objects\helpers\IdealisticOfficeLink;
use idealistic_office\api\objects\helpers\IdealisticOfficeOutcome;
use idealistic_office\api\objects\helpers\IdealisticOfficePortalStatistics;
use idealistic_office\api\objects\helpers\IdealisticOfficeTimezone;
use idealistic_office\api\objects\helpers\user\IdealisticOfficeUserIdentity;
use IdealisticOfficeAccessPlatform;
use IdealisticOfficeLimit;
use IdealisticOfficePermission;
use IdealisticOfficePromptTask;
use IdealisticOfficeTable;

class IdealisticOfficePortal extends AbstractIdealisticOfficeChange
{

    public const
        VOICE_REPLIES = false;

    public const
        LAST_ID_KEY = "last_id",
        NO_MESSAGE = "(no message)",
        HISTORY_KEY = "history",
        KNOWLEDGE_KEY = "knowledge",
        LINKS_KEY = "links",
        ATTACHMENTS_KEY = "attachments";

    private bool $isDemo;
    private string $name, $randomId;
    private ?string $password, $defaultMessage, $accessedURL;
    private string|false|null $defaultUrl;
    private bool $downloadsAllowed, $voiceReplies, $skipRobotVerification, $is18plus, $requireLogin,
        $access, $uniqueSessionChecks;
    private int|string|null $transferTo, $threadId, $platformId,
        $serverId, $channelId, $channelThreadId, $messageId, $userId,
        $userNickName, $userDisplayName;

    public function __construct(
        AbstractIdealisticOfficeTeam $team,
        int|string                   $id,
        int|Account                  $createdBy,
        string                       $name,
        ?string                      $password,
        string                       $randomId,
        string|false|null            $defaultUrl,
        bool                         $downloadsAllowed,
        bool                         $voiceReplies,
        bool                         $skipRobotVerification,
        bool                         $is18plus,
        bool                         $requireLogin,
        string                       $creationDate,
        string                       $creationCreationTimeZone,
        ?string                      $defaultMessage,
        int|string|null              $transferTo,
        bool                         $json = true,
        string|false|null            $defaultURLToSet = null
    )
    {
        parent::__construct($id, $createdBy, $team, $creationDate, $creationCreationTimeZone, $json);
        $this->randomId = $randomId;
        $this->password = $password;
        $this->name = $name;
        $this->downloadsAllowed = $downloadsAllowed;
        $this->voiceReplies = $voiceReplies;
        $this->skipRobotVerification = $skipRobotVerification;
        $this->is18plus = $is18plus;
        $this->defaultMessage = $defaultMessage;
        $this->isDemo = false;
        $this->requireLogin = $requireLogin;
        $this->transferTo = $transferTo;
        $this->defaultUrl = $defaultUrl === null || $defaultUrl === false
            ? $defaultUrl
            : strtolower(trim($defaultUrl));
        $this->accessedURL = is_url($defaultURLToSet)
            ? $defaultURLToSet
            : null;
        $defaultUrl = $this->getDefaultUrl();
        $this->threadId = null;
        $this->access = false;
        $this->uniqueSessionChecks = true;
        $this->platformId = null;
        $this->serverId = null;
        $this->channelId = null;
        $this->channelThreadId = null;
        $this->messageId = null;
        $this->userId = null;
        $this->userNickName = null;
        $this->userDisplayName = null;

        if ($this->id > 0
            && !empty($defaultURLToSet)
            && $defaultUrl === null
            && $this->defaultUrl !== false
            && $this->defaultUrl != "false"
            && (is_domain($defaultURLToSet)
                || is_url($defaultURLToSet))) {
            $this->setDefaultUrl(null, $defaultURLToSet);
        }
        if ($json) {
            self::addMultipleToJSON(
                array(
                    "random_id" => $this->randomId,
                    "password" => $this->password,
                    "name" => $this->name,
                    "downloads_allowed" => $this->downloadsAllowed,
                    "voice_replies" => $this->voiceReplies,
                    "skip_robot_verification" => $this->skipRobotVerification,
                    "is_18_plus" => $this->is18plus,
                    "default_message" => $this->defaultMessage,
                    "default_url" => $defaultUrl
                )
            );
        }
    }

    // Portals installed in a chat see everything said in that place, web portals only see the visitor's own session
    public function isPlaceHistory(): bool
    {
        return $this->platformId !== null
            && $this->threadId !== null;
    }

    public function setThreadId(int|string|null $threadId): void
    {
        $this->threadId = $threadId;
    }

    public function setPlatformId(int|string|null $platformId): void
    {
        $this->platformId = $platformId;
    }

    public function setServerId(int|string|null $serverId): void
    {
        $this->serverId = $serverId;
    }

    public function setChannelId(int|string|null $channelId): void
    {
        $this->channelId = $channelId;
    }

    public function setChannelThreadId(int|string|null $channelThreadId): void
    {
        $this->channelThreadId = $channelThreadId;
    }

    public function setMessageId(int|string|null $messageId): void
    {
        $this->messageId = $messageId;
    }

    public function setUserId(int|string|null $userId): void
    {
        $this->userId = $userId;
    }

    public function setUniqueSessionChecks(bool $uniqueSessionChecks): void
    {
        $this->uniqueSessionChecks = $uniqueSessionChecks;
    }

    public function setUserNickName(int|string|null $nickName): void
    {
        $this->userNickName = $nickName;
    }

    public function setUserDisplayName(int|string|null $displayName): void
    {
        $this->userDisplayName = $displayName;
    }

    public function setAccess(bool $access): void
    {
        $this->access = $access;
    }

    private function addThreadId(array $array): array
    {
        if ($this->threadId === null) {
            $array[] = array("thread_id", null);
        } else {
            $array[] = array("thread_id", $this->threadId);
        }
        return $array;
    }

    // Separator

    public function isDemo(): bool
    {
        if ($this->isDemo) {
            $this->refreshDemoSession();
            return true;
        }
        if ($this->id === 0
            && $this->team->getId() === 0
            && $this->getCreatorID() === 0
            && !$this->team->hasEstablishedAccess()
            && empty($this->randomId)) {
            $this->refreshDemoSession();
            return $this->isDemo = true;
        } else {
            return false;
        }
    }

    public function showsBranding(): bool
    {
        if ($this->isDemo() || !$this->team->hasEstablishedAccess()) {
            return true;
        }
        return $this->team->getCredit()->hasLogo();
    }

    private function refreshDemoSession(): void
    {
        if ($this->team->hasEstablishedAccess()) {
            return;
        }
        $account = new Account(
            Account::IDEALISTIC_OFFICE_APPLICATION_ID
        );
        $session = $account->getSession()->find(IdealisticOfficeVariable::DEFAULT_SESSION_CHECK_IP_ADDRESS);

        if ($session->isPositiveOutcome()) {
            $this->team = new IdealisticOfficeTeam($account);
            $this->creatorAccount = $account;
        }
    }

    // Separator

    public function getPortalTimeZone(bool $json = false): IdealisticOfficeTimezone
    {
        return $this->team->getTimezone($json);
    }

    public function canView(IdealisticOfficeUser $user): bool
    {
        $folder = $this->getParentFolder();

        if ($folder !== null && $folder->canView($user)) {
            return true;
        } else {
            $this->remove();
            return false;
        }
    }

    public function getURL(): string
    {
        return IdealisticOfficeVariable::PORTAL_VIEW_INPUT_OUTPUT_URL . "?id=" . $this->randomId;
    }

    public function getDefaultMessage(): ?string
    {
        return $this->defaultMessage;
    }

    public function getDefaultUrl(): ?string
    {
        return $this->hasDefaultURL()
            ? (is_domain($this->defaultUrl) ? "https://" . $this->defaultUrl : $this->defaultUrl)
            : null;
    }

    public function getAccessedURL(): ?string
    {
        return $this->accessedURL;
    }

    public function hasDefaultURL(): bool
    {
        return is_url($this->defaultUrl)
            || is_domain($this->defaultUrl);
    }

    public function canCrawl(): bool
    {
        return !$this->hasDefaultURL()
            && $this->defaultUrl !== false
            && $this->defaultUrl !== "false";
    }

    public function getTransferTo(): int|string|null
    {
        return $this->transferTo;
    }

    public function getRandomId(): string
    {
        return $this->randomId;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function areDownloadsAllowed(): bool
    {
        return $this->downloadsAllowed;
    }

    public function areVoiceRepliesEnabled(): bool
    {
        return self::VOICE_REPLIES && $this->voiceReplies;
    }

    public function isLoginRequired(): bool
    {
        return $this->requireLogin;
    }

    public function skipRobotVerification(): bool
    {
        return $this->skipRobotVerification;
    }

    public function is18Plus(): bool
    {
        return $this->is18plus;
    }

    // Separator

    public function getFailedAccessTimes(
        ?string $ipAddress,
        int     $limit = IdealisticOfficeLimit::CHAT_PORTAL_MAX_DAILY_FAILED_ACCESSES
    ): int
    {
        if ($this->isDemo()
            || $this->id <= 0
            || empty($ipAddress)) {
            return 0;
        }
        return sizeof(get_sql_query(
            IdealisticOfficeTable::PORTAL_ACCESS_TABLE,
            array("id"),
            array(
                array("portal_id", $this->id),
                array("password_match", false),
                array("ip_address", $ipAddress),
                array("creation_date", ">=", IdealisticOfficeReader::getPastDate("1 day", $this->creationTimeZone))
            ),
            null,
            $limit
        ));
    }

    public function attemptAccess(
        string          $sessionId,
        ?string         $ipAddress,
        int|string|null $password
    ): IdealisticOfficeOutcome
    {
        if ($this->id <= 0) {
            return new IdealisticOfficeOutcome(false, "This portal does not exist.");
        }
        if ($this->password !== null) {
            $password = $password !== null
                && is_valid_password($password, $this->password);
        } else {
            $password = true;
        }
        $store = function (bool $match = true) use ($ipAddress, $password, $sessionId) {
            if (!IdealisticOfficeError::sqlInsert(
                $this->team,
                null,
                IdealisticOfficeTable::PORTAL_ACCESS_TABLE,
                array(
                    "portal_id" => $this->id,
                    "thread_id" => $this->threadId,
                    "session_id" => $sessionId,
                    "platform_id" => $this->platformId,
                    "ip_address" => $ipAddress ?? "", // ATTENTION
                    "password_match" => $match && $password,
                    "creation_date" => IdealisticOfficeReader::getCurrentDate($this->creationTimeZone),
                    "creation_time_zone" => $this->creationTimeZone
                )
            )) {
                return new IdealisticOfficeOutcome(false, "Could not attempt access.");
            } else {
                return new IdealisticOfficeOutcome(true, "Access attempt recorded.");
            }
        };
        if (!$password) {
            $outcome = $store();

            if (!$outcome->isPositiveOutcome()) {
                return $outcome;
            }
            return new IdealisticOfficeOutcome(false, "Incorrect password.");
        }
        $limit = $this->getFailedAccessTimes($ipAddress) === IdealisticOfficeLimit::CHAT_PORTAL_MAX_DAILY_FAILED_ACCESSES
            && !$this->isCreatorAdministrator();
        $outcome = $store(!$limit);

        if (!$outcome->isPositiveOutcome()) {
            return $outcome;
        }
        if ($limit) {
            return new IdealisticOfficeOutcome(false, "Maximum incorrect passwords reached, try again later.");
        }
        return new IdealisticOfficeOutcome(true, "Access granted.");
    }

    public function hasAccess(string $sessionId): bool
    {
        return $this->access
            || $this->isDemo()
            || $this->skipRobotVerification() && $this->getPassword() === null
            || !empty(get_sql_query(
                IdealisticOfficeTable::PORTAL_ACCESS_TABLE,
                array("id"),
                array(
                    array("portal_id", $this->id),
                    array("password_match", true),
                    array("session_id", $sessionId),
                    array("creation_date", ">=", IdealisticOfficeReader::getPastDate(IdealisticOfficeLimit::EXTENDED_URL_EXPIRATION_TIME, $this->creationTimeZone))
                ),
                null,
                1
            ));
    }

    // Separator

    private function getUniqueAddresses(
        string $sessionId,
        int    $limit = IdealisticOfficeLimit::MAX_EXTENSIONS_PER_OBJECT_LARGE * 10
    ): int
    {
        if ($this->isDemo()) {
            return 1;
        }
        $query = get_sql_query(
            IdealisticOfficeTable::PORTAL_HISTORY_TABLE,
            array("ip_address"),
            array(
                array("portal_id", $this->id),
                array("session_id", $sessionId),
                array("ip_address", "IS NOT", null),
                array("creation_date", ">=", IdealisticOfficeReader::getPastDate(IdealisticOfficeLimit::CHAT_PORTAL_MAX_CONSIDERATION_TIME, $this->getPortalTimeZone())),
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
            $array = array();

            foreach ($query as $row) {
                $array[$row->ip_address] = true;
            }
            return sizeof($array);
        }
    }

    private function getSessions(
        ?string $ipAddress,
        int     $limit = IdealisticOfficeLimit::MAX_EXTENSIONS_PER_OBJECT_LARGE * 10
    ): int
    {
        if ($this->isDemo()
            || $this->id <= 0
            || empty($ipAddress)) {
            return 1;
        }
        $query = get_sql_query(
            IdealisticOfficeTable::PORTAL_HISTORY_TABLE,
            array("session_id"),
            array(
                array("ip_address", $ipAddress),
                array("creation_date", ">=", IdealisticOfficeReader::getPastDate(IdealisticOfficeLimit::CHAT_PORTAL_MAX_CONSIDERATION_TIME, $this->getPortalTimeZone()))
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
            $array = array();

            foreach ($query as $row) {
                $array[$row->session_id] = true;
            }
            return sizeof($array);
        }
    }

    private function hasProtection(
        string  $sessionId,
        ?string $ipAddress
    ): IdealisticOfficeOutcome
    {
        if (!$this->isDemo()
            && $this->id > 0) {
            if ($this->getUniqueAddresses($sessionId) > IdealisticOfficeLimit::MAX_ACCEPTED_IP_ADDRESSES) {
                return new IdealisticOfficeOutcome(true, "Multiple addresses detected for this session.");
            }
            if ($this->uniqueSessionChecks
                && $this->getSessions($ipAddress) > IdealisticOfficeLimit::MAX_ACCEPTED_UNIQUE_SESSIONS) {
                return new IdealisticOfficeOutcome(true, "Multiple sessions detected for this IP address.");
            }
        }
        return new IdealisticOfficeOutcome(false, "Access granted.");
    }

    // Separator

    public function getStatistics(): IdealisticOfficePortalStatistics
    {
        if ($this->isDemo()
            || $this->id <= 0) {
            return new IdealisticOfficePortalStatistics(0, 0, 0);
        }
        $totalMessages = get_sql_query(
            IdealisticOfficeTable::PORTAL_HISTORY_TABLE,
            array("id", "session_id", "creation_date"),
            array(
                array("portal_id", $this->id)
            ),
            array(
                "DESC",
                "id"
            ),
            IdealisticOfficeLimit::MAX_PROCESSED_ROWS
        );
        $totalSessions = array();
        $messagesPerDay = array();

        if (!empty($totalMessages)) {
            foreach ($totalMessages as $row) {
                $totalSessions[$row->session_id] = true;
                $key = string_to_integer($row->creation_date);

                if (isset($messagesPerDay[$key])) {
                    $messagesPerDay[$key] += 1;
                } else {
                    $messagesPerDay[$key] = 1;
                }
            }

            if (!empty($messagesPerDay)) {
                $messagesPerDay = array_values($messagesPerDay);
                $messagesPerDay = array_sum($messagesPerDay) / sizeof($messagesPerDay);
            } else {
                $messagesPerDay = 0;
            }
        } else {
            $messagesPerDay = 0;
        }
        return new IdealisticOfficePortalStatistics(
            sizeof($totalMessages),
            sizeof($totalSessions),
            $messagesPerDay
        );
    }

    // Separator

    private function processDefaultURL(?IdealisticOfficeUser $user): void
    {
        $url = $this->getAccessedURL();
        $defaultURL = $this->getDefaultUrl();

        if ($defaultURL === null) {
            if ($url === null) {
                return;
            } else {
                $defaultURL = $url;
            }
        }
        $contents = IdealisticOfficeProcessor::getLinkContents(
            $url
        );

        if (!empty($contents)) {
            $mainContentHash = string_to_integer($contents, true);
            $timezone = $user?->getTimezone(false) ?? $this->getPortalTimeZone(false);
            IdealisticOfficeError::sqlInsert(
                $this->team,
                null,
                IdealisticOfficeTable::PORTAL_LINKS_TABLE,
                array(
                    "portal_id" => $this->id,
                    "link_url" => $url,
                    "link_content" => $contents,
                    "analyzed_description" => IdealisticOfficeProcessor::analyzeLink(null, $this->team, $contents, IdealisticOfficeLimit::K11_LENGTH),
                    "creation_date" => IdealisticOfficeReader::getCurrentDate($timezone),
                    "creation_time_zone" => $timezone->getTimeZone(),
                    "created_by" => $user?->getId()
                )
            );
            $urls = IdealisticOfficeReader::findURLs($contents, true);

            if (!empty($urls)) {
                $defaultDomain = strtolower(trim(get_domain_from_url($url, true)));

                if (!is_domain($defaultDomain)) {
                    return;
                }
                foreach ($urls as $url) {
                    if (str_starts_with($url, "https://")
                        && strtolower(trim(get_domain_from_url($url, true))) === $defaultDomain) {
                        $contents = IdealisticOfficeProcessor::getLinkContents(
                            $url
                        );
                        $count = 0;
                        $limit = IdealisticOfficeLimit::MAX_EXTENSIONS_PER_OBJECT - 1;

                        if (!empty($contents)
                            && string_to_integer($contents, true) !== $mainContentHash
                            && IdealisticOfficeError::sqlInsert(
                                $this->team,
                                null,
                                IdealisticOfficeTable::PORTAL_LINKS_TABLE,
                                array(
                                    "portal_id" => $this->id,
                                    "link_url" => $url,
                                    "link_content" => $contents,
                                    "analyzed_description" => IdealisticOfficeProcessor::analyzeLink(null, $this->team, $contents, IdealisticOfficeLimit::K11_LENGTH),
                                    "creation_date" => IdealisticOfficeReader::getCurrentDate($timezone),
                                    "creation_time_zone" => $timezone->getTimeZone(),
                                    "created_by" => $user?->getId()
                                )
                            )) {
                            $count++;

                            if ($count >= $limit) {
                                break;
                            }
                        }
                    }
                }
            }
        }
    }

    public function setDefaultUrl(
        ?IdealisticOfficeUser $user,
        string|false|null     $url
    ): IdealisticOfficeOutcome
    {
        if ($user !== null
            && !$user->hasPermission(IdealisticOfficePermission::ADJUST_PORTALS)->isPositiveOutcome()) {
            return new IdealisticOfficeOutcome(
                false,
                "You do not have permission to manage portal links."
            );
        }
        if (empty($url)
            && $url !== false) {
            if ($this->defaultUrl === null) {
                return new IdealisticOfficeOutcome(true, "Default URL is already cleared for portal: " . $this->getName());
            }
            if (IdealisticOfficeError::setSqlQuery(
                $this->team,
                $user,
                IdealisticOfficeTable::PORTALS_TABLE,
                array(
                    "default_url" => null
                ),
                array(
                    array("id", $this->id)
                ),
                null,
                1
            )) {
                $partialLinks = $this->getSeparatePartialLinks(null, false);

                if (!empty($partialLinks)) {
                    $timezone = $user?->getTimezone(false) ?? $this->getPortalTimeZone(false);

                    foreach ($partialLinks as $link) {
                        IdealisticOfficeError::setSqlQuery(
                            $this->team,
                            $user,
                            IdealisticOfficeTable::PORTAL_LINKS_TABLE,
                            array(
                                "deletion_date" => IdealisticOfficeReader::getCurrentDate($timezone),
                                "deletion_time_zone" => $timezone->getTimeZone(),
                                "deleted_by" => $user?->getId()
                            ),
                            array(
                                array("id", $link->getId())
                            ),
                            null,
                            1
                        );
                    }
                }
                $this->defaultUrl = null;
                return new IdealisticOfficeOutcome(true, "Default URL cleared for portal: " . $this->getName());
            } else {
                return new IdealisticOfficeOutcome(false, "Could not clear default URL for portal: " . $this->getName());
            }
        } else if ($url === false
            || strtolower(trim($url)) == "false") {
            if ($this->defaultUrl === false
                || $this->defaultUrl == "false") {
                return new IdealisticOfficeOutcome(true, "Default URL is already disabled for portal: " . $this->getName());
            }
            if (IdealisticOfficeError::setSqlQuery(
                $this->team,
                $user,
                IdealisticOfficeTable::PORTALS_TABLE,
                array(
                    "default_url" => "false"
                ),
                array(
                    array("id", $this->id)
                ),
                null,
                1
            )) {
                $partialLinks = $this->getSeparatePartialLinks(null, false);

                if (!empty($partialLinks)) {
                    $timezone = $user?->getTimezone(false) ?? $this->getPortalTimeZone(false);

                    foreach ($partialLinks as $link) {
                        IdealisticOfficeError::setSqlQuery(
                            $this->team,
                            $user,
                            IdealisticOfficeTable::PORTAL_LINKS_TABLE,
                            array(
                                "deletion_date" => IdealisticOfficeReader::getCurrentDate($timezone),
                                "deletion_time_zone" => $timezone->getTimeZone(),
                                "deleted_by" => $user?->getId()
                            ),
                            array(
                                array("id", $link->getId())
                            ),
                            null,
                            1
                        );
                    }
                }
                $this->defaultUrl = "false";
                return new IdealisticOfficeOutcome(true, "Default URL disabled for portal: " . $this->getName());
            } else {
                return new IdealisticOfficeOutcome(false, "Could not disable default URL for portal: " . $this->getName());
            }
        } else {
            if ($url === $this->defaultUrl) {
                return new IdealisticOfficeOutcome(true, "Default URL is already set to '" . $user . "' for portal: " . $this->getName());
            }
            if (IdealisticOfficeError::setSqlQuery(
                $this->team,
                null,
                IdealisticOfficeTable::PORTALS_TABLE,
                array(
                    "default_url" => $url
                ),
                array(
                    array("id", $this->id)
                ),
                null,
                1
            )) {
                $this->defaultUrl = $url;
                $this->processDefaultURL($user);
                return new IdealisticOfficeOutcome(true, "Default URL set to '" . $url . "' for portal: " . $this->getName());
            } else {
                return new IdealisticOfficeOutcome(false, "Could not set default URL for portal: " . $this->getName());
            }
        }
    }

    /** @return IdealisticOfficeLink[] */
    public function getSeparateLinks(?array $list = null, bool $json = true): array
    {
        if (!$this->hasDefaultURL()) {
            return array();
        }
        if ($list === null) {
            $query = get_sql_query(
                IdealisticOfficeTable::PORTAL_LINKS_TABLE,
                IdealisticOfficeQuery::PORTAL_LINK,
                array(
                    array("portal_id", $this->id),
                    array("deletion_date", null)
                ),
                null,
                IdealisticOfficeLimit::MAX_EXTENSIONS_PER_OBJECT
            );
        } else {
            $query = array_slice($list[$this->id] ?? array(), 0, IdealisticOfficeLimit::MAX_EXTENSIONS_PER_OBJECT);
        }

        if (!empty($query)) {
            foreach ($query as $id => $row) {
                $query[$id] = new IdealisticOfficeLink(
                    $this->team,
                    (int)$row->id,
                    $row->link_url,
                    $row->link_content,
                    $row->analyzed_description,
                    $row->analyzed_description_positions,
                    $row->creation_date,
                    $row->creation_time_zone,
                    $row->created_by,
                    $row->refresh_date,
                    $row->refresh_time_zone,
                    $row->refreshed_by,
                    $json
                );
            }
        }
        return array_values($query);
    }

    /** @return IdealisticOfficeLink[] */
    public function getSeparatePartialLinks(?array $list = null, bool $json = true): array
    {
        if (!$this->hasDefaultURL()) {
            return array();
        }
        if ($list === null) {
            $query = get_sql_query(
                IdealisticOfficeTable::PORTAL_LINKS_TABLE,
                IdealisticOfficeQuery::PORTAL_LINK_PARTIAL,
                array(
                    array("portal_id", $this->id),
                    array("deletion_date", null)
                ),
                null,
                IdealisticOfficeLimit::MAX_EXTENSIONS_PER_OBJECT
            );
        } else {
            $query = array_slice($list[$this->id] ?? array(), 0, IdealisticOfficeLimit::MAX_EXTENSIONS_PER_OBJECT);
        }

        if (!empty($query)) {
            foreach ($query as $id => $row) {
                $query[$id] = new IdealisticOfficeLink(
                    $this->team,
                    (int)$row->id,
                    $row->link_url,
                    null,
                    $row->analyzed_description,
                    $row->analyzed_description_positions,
                    $row->creation_date,
                    $row->creation_time_zone,
                    $row->created_by,
                    $row->refresh_date,
                    $row->refresh_time_zone,
                    $row->refreshed_by,
                    $json
                );
            }
        }
        return array_values($query);
    }

    public function hasSeparateLinks(): bool
    {
        return $this->hasDefaultURL()
            && !empty(get_sql_query(
                IdealisticOfficeTable::PORTAL_LINKS_TABLE,
                array("id"),
                array(
                    array("portal_id", $this->id),
                    array("deletion_date", null)
                ),
                null,
                1
            ));
    }

    // Separator

    public function getData(
        int     $limit,
        string  $sessionId,
        ?string $ipAddress,
        bool    $all,
        ?string $defaultMessage = null,
        bool    $knowledge = true,
        int     $afterId = -1,
        bool    $rsort = false
    ): array|IdealisticOfficeOutcome
    {
        if (!$this->isDemo()
            && $this->id <= 0) {
            return new IdealisticOfficeOutcome(false, "This portal does not exist.");
        }
        if (!$this->hasAccess($sessionId)) {
            return new IdealisticOfficeOutcome(false, "No access to this portal.");
        }
        $protection = $this->hasProtection($sessionId, $ipAddress);

        if ($protection->isPositiveOutcome()) {
            $protection->setOutcome(false);
            return $protection;
        }
        $knowledge &= !$this->isDemo();
        $defaultMessageFn = function () use ($defaultMessage) {
            $defaultMessageCustom = empty($defaultMessage) || mb_strlen($defaultMessage) > IdealisticOfficeLimit::CHAT_PORTAL_DEFAULT_MESSAGE_LENGTH
                ? null
                : $defaultMessage;
            return empty($this->defaultMessage)
                ? $defaultMessageCustom ?? "Hi! How can you help me? Reply shortly in different languages."
                : $this->defaultMessage;
        };

        if (!$this->isDemo()) {
            $get = function () use ($afterId, $sessionId, $knowledge, $rsort, $limit): array {
                return get_sql_query(
                    IdealisticOfficeTable::PORTAL_HISTORY_TABLE,
                    IdealisticOfficeQuery::PORTAL_HISTORY,
                    $this->addThreadId(array(
                        array("portal_id", $this->id),
                        array("session_id", $sessionId),
                        array("is_completed", true),
                        array("creation_date", ">=", IdealisticOfficeReader::getPastDate(IdealisticOfficeLimit::CHAT_PORTAL_MAX_HISTORY_TIME, $this->getPortalTimeZone())),
                        array("id", ">", $afterId),
                        array("deletion_date", null),
                        array("platform_id", $this->platformId)
                    )),
                    array("DESC", "id"),
                    $limit
                );
            };
            $query = $get();

            if ($afterId === -1
                && empty($query)
                && $this->addHistory(
                    $sessionId,
                    $ipAddress ?? "",
                    $defaultMessageFn(),
                    null,
                    null
                )->isPositiveOutcome()) {
                $query = $get();
            }
        }
        $data = array(
            self::LAST_ID_KEY => 0,
            self::HISTORY_KEY => array(),
            self::KNOWLEDGE_KEY => array()
        );

        if ($knowledge) {
            if ($all) {
                $data[self::LINKS_KEY] = array();
                $data[self::ATTACHMENTS_KEY] = array();
                $links = $this->getSeparatePartialLinks(null, false);

                if (!empty($links)) {
                    foreach ($links as $link) {
                        $data[self::LINKS_KEY][$link->getURL()] = $link;
                    }
                }
            }
            $folders = $this->getConnectedFolders(false);
            $folderCount = sizeof($folders);

            if (!empty($folders)) {
                $folderLimit = IdealisticOfficeLimit::FOLDER_MAX_COMMENTS / $folderCount;

                foreach ($folders as $folder) {
                    $comments = $folder->getComments($folderLimit);

                    if (!empty($comments)) {
                        foreach ($comments as $comment) {
                            $time = IdealisticOfficeReader::translateDate(
                                $comment->getCreationDate(),
                                $comment->getCreationDateTimeZone(),
                                $this->getPortalTimeZone(),
                                false
                            );
                            $value = array("text" => $comment->getComment() ?? "(no text)");
                            $attachments = $comment->getPartialAttachments();

                            if (!empty($attachments)) {
                                $value["attachments"] = $attachments;

                                if ($all) {
                                    foreach ($attachments as $attachment) {
                                        if ($this->downloadsAllowed) {
                                            $attachment->getUrl(false, true);
                                        } else {
                                            $attachment->disableURL();
                                        }
                                        $data[self::ATTACHMENTS_KEY][$attachment->getDataHash()] = $attachment;
                                    }
                                }
                            }

                            $links = $comment->getPartialLinks();
                            if (!empty($links)) {
                                $value["links"] = array();

                                foreach ($links as $link) {
                                    $value["links"][$link->getURL()] = $link->getAnalyzedDescription();

                                    if ($all) {
                                        $data[self::LINKS_KEY][$link->getURL()] = $link;
                                    }
                                }
                            }

                            if (isset($data[self::KNOWLEDGE_KEY][$time])) {
                                $data[self::KNOWLEDGE_KEY][$time][] = $value;
                            } else {
                                $data[self::KNOWLEDGE_KEY][$time] = array($value);
                            }
                        }
                    }
                }
            }
        }

        if ($this->isDemo()) {
            $creator = $this->getCreator()
                ?? new IdealisticOfficeUser(
                    $this->team,
                    $this->getCreatorAccount(),
                    false
                );

            if ($creator->hasEstablishedAccess()) {
                $history = $creator->getHistory(0, $limit);
            } else {
                $creator->setLastIdentity(
                    new IdealisticOfficeUserIdentity(
                        0, null, null, IdealisticOfficeAccessPlatform::NATIVE,
                        $sessionId, null, null, null,
                        IdealisticOfficeReader::getCurrentDate(), IdealisticOfficeVariable::DEFAULT_TIME_ZONE,
                        null, null, false
                    )
                );
                $history = array();
            }

            if (!empty($history)) {
                $itemsToProcess = array();
                $highestCompletedId = $afterId;

                foreach ($history as $item) {
                    $itemId = $item->getId();

                    if ($item->replied() && $itemId > $highestCompletedId) {
                        $highestCompletedId = $itemId;
                    }

                    if ($itemId > $afterId) {
                        $itemsToProcess[] = array(
                            "item" => $item,
                            "include_user" => true,
                            "include_assistant" => $item->replied()
                        );
                    }
                }

                $data[self::LAST_ID_KEY] = $highestCompletedId;

                if (!empty($itemsToProcess)) {
                    if ($rsort) {
                        $itemsToProcess = array_reverse($itemsToProcess);
                    }

                    foreach ($itemsToProcess as $dataItem) {
                        $item = $dataItem['item'];
                        $time = $item->getCreationDate();

                        if ($dataItem['include_user']) {
                            $userText = implode(IdealisticOfficeVariable::DEFAULT_TEXT_SEPARATOR, $item->getPrompts());
                            $userValue = array(
                                "id" => $item->getId(),
                                "text" => empty($userText) ? self::NO_MESSAGE : $userText,
                                "date" => IdealisticOfficeReader::translateDate(
                                    $item->getCreationDate(),
                                    $item->getCreationDateTimeZone(),
                                    $this->getPortalTimeZone(),
                                    false
                                )
                            );

                            $userAttachments = $item->getPartialAttachments();
                            if (!empty($userAttachments)) {
                                $userValue["attachment"] = $userAttachments[0];
                            }

                            $userLinks = $item->getPartialLinks(null, false);
                            if (!empty($userLinks)) {
                                $userValue["link"] = $userLinks[0]->getURL() . ":\n\n" . $userLinks[0]->getAnalyzedDescription();
                            }

                            if (!isset($data[self::HISTORY_KEY][$time])) $data[self::HISTORY_KEY][$time] = array();
                            if (!isset($data[self::HISTORY_KEY][$time]["user"])) $data[self::HISTORY_KEY][$time]["user"] = array();
                            $data[self::HISTORY_KEY][$time]["user"][] = $userValue;
                        }

                        if ($dataItem['include_assistant']) {
                            $replies = $item->getReplies();
                            $segments = array();
                            $highlighted = false;

                            if (!empty($replies)) {
                                foreach ($replies as $key => $reply) {
                                    $answer = $reply->getAnswer();
                                    $replies[$key] = $answer;

                                    if ($answer !== null && $answer !== "") {
                                        $highlight = $reply->getTaskId() !== IdealisticOfficePromptTask::TEAM_INFO;
                                        $highlighted |= $highlight;
                                        $segments[] = array("text" => $answer, "highlight" => $highlight);
                                    }
                                }
                            }
                            $assistantAttachments = $item->getCreatedAttachments();

                            $assistantText = empty($replies) ? "" : implode(IdealisticOfficeVariable::DEFAULT_TEXT_SEPARATOR, $replies);
                            $assistantValue = array(
                                "id" => $item->getId(),
                                "text" => $assistantText
                            );

                            if (!empty($assistantAttachments)) {
                                foreach ($assistantAttachments as $attachment) {
                                    if ($this->downloadsAllowed) {
                                        $url = $attachment->getUrl(false, true);

                                        if ($url === null) {
                                            continue;
                                        }
                                    } else {
                                        continue;
                                    }
                                    $assistantValue["text"] = $assistantValue["text"]
                                        . (empty($assistantText) ? "" : IdealisticOfficeVariable::DEFAULT_TEXT_SEPARATOR)
                                        . $url;
                                    $segments[] = array("text" => $url, "highlight" => false);
                                    break;
                                }
                            }
                            if (empty($assistantValue["text"])) {
                                $assistantValue["text"] = self::NO_MESSAGE;
                            }
                            if ($highlighted
                                && !empty($segments)) {
                                $assistantValue["segments"] = $segments;
                            }

                            if (!isset($data[self::HISTORY_KEY][$time])) $data[self::HISTORY_KEY][$time] = array();
                            if (!isset($data[self::HISTORY_KEY][$time]["assistant"])) $data[self::HISTORY_KEY][$time]["assistant"] = array();
                            $data[self::HISTORY_KEY][$time]["assistant"][] = $assistantValue;
                        }
                    }
                }
            }
        } else {
            if (!empty($query)) {
                $data[self::LAST_ID_KEY] = $query[0]->id;

                if ($rsort) {
                    $query = array_reverse($query);
                }
                $timezone = $this->getPortalTimeZone();
                $emptyAccount = new Account();

                foreach ($query as $row) {
                    $isAssistant = $row->ip_address === null;
                    $time = $row->creation_date;
                    $who = $isAssistant ? "assistant" : "user";
                    if ($isAssistant) {
                        $displayName = null;
                    } else if ($row->account_id !== null) {
                        $displayName = AbstractIdealisticOfficeTeam::findUserDynamic(
                            new Account(
                                Account::IDEALISTIC_OFFICE_APPLICATION_ID,
                                $row->account_id
                            ),
                            false
                        );

                        if ($displayName instanceof IdealisticOfficeUser) {
                            $displayName = $displayName->getDisplayName();
                        } else {
                            $displayName = $row->user_display_name ?? ($row->user_nick_name ?? $row->user_id);
                        }
                    } else {
                        $displayName = $row->user_display_name ?? ($row->user_nick_name ?? $row->user_id);
                    }
                    $value = array(
                        "id" => $row->id,
                        "text" => empty($row->message) ? self::NO_MESSAGE : $row->message,
                        "date" => $time,
                        "display_name" => $displayName
                    );

                    if ($row->attachment_bytes !== null) {
                        if ($isAssistant) {
                            $value["attachment"] = new IdealisticOfficeAttachment(
                                0,
                                $row->attachment_name,
                                $row->attachment_format,
                                null,
                                $row->attachment_bytes,
                                $row->attachment_x,
                                $row->attachment_y,
                                $row->attachment_analyzed_description,
                                $row->attachment_data,
                                null,
                                true,
                                $row->creation_date,
                                $timezone->getTimeZone(),
                                $emptyAccount
                            );
                        } else {
                            $value["attachment"] = new IdealisticOfficePartialAttachment(
                                0,
                                $row->attachment_name,
                                $row->attachment_format,
                                null,
                                $row->attachment_bytes,
                                $row->attachment_x,
                                $row->attachment_y,
                                $row->attachment_analyzed_description,
                                null,
                                $row->creation_date,
                                $timezone->getTimeZone(),
                                $emptyAccount
                            );
                        }
                    }
                    if ($row->link_url !== null
                        && $row->link_analyzed_description !== null) {
                        $value["link"] = $row->link_url . ":\n\n" . $row->link_analyzed_description;
                    }
                    if (!isset($data[self::HISTORY_KEY][$time])) {
                        $data[self::HISTORY_KEY][$time] = array();
                    }
                    if (!isset($data[self::HISTORY_KEY][$time][$who])) {
                        $data[self::HISTORY_KEY][$time][$who] = array();
                    }
                    $data[self::HISTORY_KEY][$time][$who][] = $value;
                }
            }
        }
        return $data;
    }

    public function addHistory(
        string                      $sessionId,
        ?string                     $ipAddress,
        ?string                     $message,
        ?IdealisticOfficeAttachment $voice,
        ?IdealisticOfficeAttachment $anyFile,
        string                      $defaultLanguage = IdealisticOfficeVariable::DEFAULT_LANGUAGE
    ): IdealisticOfficeOutcome
    {
        if (!$this->isDemo()
            && $this->id <= 0) {
            return new IdealisticOfficeOutcome(false, "This portal does not exist.");
        }
        if ($message === null
            && $voice === null
            && $anyFile === null) {
            return new IdealisticOfficeOutcome(false, "Message cannot be empty.");
        }
        if (!IdealisticOfficeReader::isLatinOnly($defaultLanguage)) {
            return new IdealisticOfficeOutcome(false, "Language must be in Latin characters.");
        }
        if (!in_array($defaultLanguage, IdealisticOfficeLimit::ALL_LANGUAGES)) {
            return new IdealisticOfficeOutcome(false, "Language '" . $defaultLanguage . "' is not supported.");
        }
        if (!$this->hasAccess($sessionId)) {
            return new IdealisticOfficeOutcome(false, "No access to this portal.");
        }
        $protection = $this->hasProtection($sessionId, $ipAddress);

        if ($protection->isPositiveOutcome()) {
            $protection->setOutcome(false);
            return $protection;
        }
        if ($this->isDemo()) {
            $attachments = array();

            if ($voice !== null) {
                $attachments[] = $voice;
            }
            if ($anyFile !== null) {
                $attachments[] = $anyFile;
            }
            return IdealisticOfficeTeamInitiator::createPrompt(
                $this->getCreator(),
                IdealisticOfficeAccessPlatform::NATIVE,
                $sessionId,
                null,
                null,
                null,
                null,
                null,
                $message,
                $attachments,
                false,
                $defaultLanguage
            );
        } else {
            $creator = $this->getCreator();

            if ($creator === null) {
                return new IdealisticOfficeOutcome(false, "Creator does not exist.");
            }
            if (IdealisticOfficePortalIndependent::getProcessingHistory() >= IdealisticOfficeLimit::MAX_PROCESSED_TASKS) {
                return new IdealisticOfficeOutcome(false, "The portal is currently processing too many messages, please try again later.");
            }
            $lockQuery = get_sql_query(
                IdealisticOfficeTable::PORTAL_HISTORY_TABLE,
                array("id"),
                $this->addThreadId(array(
                    array("portal_id", $this->id),
                    array("session_id", $sessionId),
                    array("is_completed", false),
                    array("creation_date", ">=", IdealisticOfficeReader::getPastDate(IdealisticOfficeLimit::CHAT_PORTAL_MAX_PROCESSING_TIME, $this->getPortalTimeZone())),
                    array("platform_id", $this->platformId)
                )),
                array("DESC", "id"),
                1
            );

            if (!empty($lockQuery)) {
                return new IdealisticOfficeOutcome(false, "A previous message is still being processed, please wait.");
            }
            $cooldownQuery = get_sql_query(
                IdealisticOfficeTable::PORTAL_HISTORY_TABLE,
                array("creation_date"),
                array(
                    array("portal_id", $this->id),
                    array("session_id", $sessionId),
                    array("is_completed", false),
                    array("ip_address", "IS NOT", null),
                    array("creation_date", ">=", IdealisticOfficeReader::getPastDate(IdealisticOfficeLimit::CHAT_PORTAL_MESSAGE_COOLDOWN, $this->getPortalTimeZone())),
                    array("platform_id", $this->platformId)
                ),
                array("DESC", "id"),
                1
            );

            if (!empty($cooldownQuery)) {
                return new IdealisticOfficeOutcome(false, "You are sending messages too quickly, please try again soon.");
            }
            // Chat platforms share one session per place, so the limit is for everyone in it
            $hourlyLimit = $this->platformId !== null
            && (int)$this->platformId !== IdealisticOfficeAccessPlatform::NATIVE
                ? IdealisticOfficeLimit::CHAT_PORTAL_MAX_HOURLY_MESSAGES_PER_SESSION * IdealisticOfficeLimit::CHAT_PORTAL_MAX_HOURLY_MESSAGES_PER_SESSION
                : IdealisticOfficeLimit::CHAT_PORTAL_MAX_HOURLY_MESSAGES_PER_SESSION;
            $countQuery = get_sql_query(
                IdealisticOfficeTable::PORTAL_HISTORY_TABLE,
                array("id"),
                array(
                    array("portal_id", $this->id),
                    array("session_id", $sessionId),
                    array("is_completed", true),
                    array("ip_address", "IS NOT", null),
                    array("creation_date", ">=", IdealisticOfficeReader::getPastDate("1 hour", $this->getPortalTimeZone())),
                    array("platform_id", $this->platformId)
                ),
                array("DESC", "id"),
                $hourlyLimit
            );

            if (sizeof($countQuery) === $hourlyLimit) {
                return new IdealisticOfficeOutcome(false, "You have reached the maximum number of messages you can send in the past hour, please try again later.");
            }
            if (!empty($ipAddress)) {
                $countQuery = get_sql_query(
                    IdealisticOfficeTable::PORTAL_HISTORY_TABLE,
                    array("id"),
                    array(
                        array("ip_address", $ipAddress),
                        array("creation_date", ">=", IdealisticOfficeReader::getPastDate("1 hour", $this->getPortalTimeZone()))
                    ),
                    array("DESC", "id"),
                    IdealisticOfficeLimit::CHAT_PORTAL_MAX_HOURLY_MESSAGES_PER_IP
                );

                if (sizeof($countQuery) === IdealisticOfficeLimit::CHAT_PORTAL_MAX_HOURLY_MESSAGES_PER_IP) {
                    return new IdealisticOfficeOutcome(false, "You have reached the maximum number of messages you can send from this IP address in the past hour, please try again later.");
                }
            }
            $includedMessage = $this->team->getCredit()->useIncludedMessageIndependently(
                $creator,
                IdealisticOfficeLimit::CHAT_PORTAL_MESSAGE_USAGE
            );

            if (!$includedMessage->isPositiveOutcome()) {
                return $includedMessage;
            }
            $currentDate = IdealisticOfficeReader::getCurrentDate($this->getPortalTimeZone());
            $account = new Account(Account::IDEALISTIC_OFFICE_APPLICATION_ID);
            $session = $account->getSession();
            $session->setCustomKey($sessionId);
            $outcome = $session->find(IdealisticOfficeVariable::DEFAULT_SESSION_CHECK_IP_ADDRESS);

            if ($outcome->isPositiveOutcome()) {
                $user = AbstractIdealisticOfficeTeam::findUserDynamic($account, false);

                if ($user instanceof IdealisticOfficeUser) {
                    $team = $user->getTeam()->getId();
                    $user = $user->getId();
                } else {
                    $user = null;
                    $team = null;
                }
            } else {
                $user = null;
                $team = null;
            }
            $userPayload = array(
                "account_id" => $user,
                "team_id" => $team,
                "portal_id" => $this->id,
                "thread_id" => $this->threadId,
                "server_id" => $this->serverId,
                "channel_id" => $this->channelId,
                "channel_thread_id" => $this->channelThreadId,
                "message_id" => $this->messageId,
                "user_id" => $this->userId,
                "user_nick_name" => $this->userNickName,
                "user_display_name" => $this->userDisplayName,
                "session_id" => $sessionId,
                "platform_id" => $this->platformId,
                "ip_address" => $ipAddress ?? "",
                "message" => $message,
                "is_completed" => true,
                "creation_date" => $currentDate,
                "default_language" => $defaultLanguage
            );
            $attachment = $voice ?? $anyFile;
            if ($attachment !== null) {
                $userPayload["attachment_format"] = $attachment->getFormat();
                $userPayload["attachment_name"] = $attachment->getName();
                $userPayload["attachment_bytes"] = $attachment->getBytes();
                $userPayload["attachment_x"] = $attachment->getXSize();
                $userPayload["attachment_y"] = $attachment->getYSize();
                $userPayload["attachment_data"] = $attachment->getRawData();
                $userPayload["attachment_analyzed_description"] = $attachment->analyzeDescription(null, $this->team, IdealisticOfficeLimit::K3_LENGTH);

                if ($message === null) {
                    $userPayload["message"] = $userPayload["attachment_analyzed_description"];
                }
            }
            $urls = $message === null
                ? null
                : IdealisticOfficeReader::findURLs($message);

            if (!empty($urls)) {
                foreach ($urls as $url) {
                    $contents = IdealisticOfficeProcessor::getLinkContents($url);

                    if (!empty($contents)) {
                        $userPayload["link_url"] = $url;
                        $userPayload["link_analyzed_description"] = IdealisticOfficeProcessor::analyzeLink(null, $this->team, $contents, IdealisticOfficeLimit::K11_LENGTH);
                        break;
                    }
                }
            }
            if (!empty($userPayload["message"])
                && mb_strlen($userPayload["message"]) > IdealisticOfficeLimit::K3_LENGTH) {
                return new IdealisticOfficeOutcome(false, "Message is too long. Maximum length is " . IdealisticOfficeLimit::K3_LENGTH . " characters.");
            }
            if (IdealisticOfficeError::sqlInsert($this->team, null, IdealisticOfficeTable::PORTAL_HISTORY_TABLE, $userPayload)
                && IdealisticOfficeError::sqlInsert($this->team, null, IdealisticOfficeTable::PORTAL_HISTORY_TABLE, array(
                    "portal_id" => $this->id,
                    "thread_id" => $this->threadId,
                    "server_id" => $this->serverId,
                    "channel_id" => $this->channelId,
                    "channel_thread_id" => $this->channelThreadId,
                    "message_id" => $this->messageId,
                    "user_id" => $this->userId,
                    "team_id" => $this->team->getId(),
                    "session_id" => $sessionId,
                    "platform_id" => $this->platformId,
                    "creation_date" => $currentDate,
                    "default_language" => $defaultLanguage
                ))) {
                return new IdealisticOfficeOutcome(true, "Message added to history.");
            }
            return new IdealisticOfficeOutcome(false, "Could not add message to history.");
        }
    }

    // Stores a message as context only: no reply is generated, so no credits, cooldowns, attachments or link analysis apply
    public function addHistoryWithoutProcessing(
        string  $sessionId,
        ?string $message
    ): IdealisticOfficeOutcome
    {
        if ($this->isDemo()
            || $this->id <= 0) {
            return new IdealisticOfficeOutcome(false, "This portal does not support history-only messages.");
        }
        if ($message === null
            || trim($message) === "") {
            return new IdealisticOfficeOutcome(false, "Message cannot be empty.");
        }
        if (!$this->hasAccess($sessionId)) {
            return new IdealisticOfficeOutcome(false, "No access to this portal.");
        }
        if (mb_strlen($message) > IdealisticOfficeLimit::K3_LENGTH) {
            $message = mb_substr($message, 0, IdealisticOfficeLimit::K3_LENGTH);
        }
        if (IdealisticOfficeError::sqlInsert($this->team, null, IdealisticOfficeTable::PORTAL_HISTORY_TABLE, array(
            "team_id" => $this->team->getId(),
            "portal_id" => $this->id,
            "thread_id" => $this->threadId,
            "server_id" => $this->serverId,
            "channel_id" => $this->channelId,
            "channel_thread_id" => $this->channelThreadId,
            "message_id" => $this->messageId,
            "user_id" => $this->userId,
            "user_nick_name" => $this->userNickName,
            "user_display_name" => $this->userDisplayName,
            "session_id" => $sessionId,
            "platform_id" => $this->platformId,
            "ip_address" => "",
            "message" => $message,
            "is_completed" => true,
            "creation_date" => IdealisticOfficeReader::getCurrentDate($this->getPortalTimeZone()),
            "default_language" => IdealisticOfficeVariable::DEFAULT_LANGUAGE
        ))) {
            return new IdealisticOfficeOutcome(true, "Message added to history.");
        }
        return new IdealisticOfficeOutcome(false, "Could not add message to history.");
    }

    public function deleteHistory(
        string  $sessionId,
        ?string $ipAddress,
    ): IdealisticOfficeOutcome
    {
        if ($this->isDemo()) {
            return new IdealisticOfficeOutcome(false, "This portal is a demo and cannot be modified.");
        }
        if ($this->id <= 0) {
            return new IdealisticOfficeOutcome(false, "This portal does not exist.");
        }
        if (!$this->hasAccess($sessionId)) {
            return new IdealisticOfficeOutcome(false, "No access to this portal.");
        }
        if (IdealisticOfficeError::setSqlQuery(
            $this->team,
            null,
            IdealisticOfficeTable::PORTAL_HISTORY_TABLE,
            array(
                "deletion_date" => IdealisticOfficeReader::getCurrentDate($this->getPortalTimeZone()),
                "deleted_by_ip_address" => $ipAddress
            ),
            $this->addThreadId(array(
                array("portal_id", $this->id),
                array("session_id", $sessionId),
                array("thread_id", $this->threadId),
                array("platform_id", $this->platformId),
                array("deletion_date", null)
            )),
            null,
            IdealisticOfficeLimit::MAX_PROCESSED_ROWS
        )) {
            return new IdealisticOfficeOutcome(true, "History deleted.");
        } else {
            return new IdealisticOfficeOutcome(false, "Could not delete history.");
        }
    }

    // Separator

    private function findRandomId(): string
    {
        if ($this->isDemo()) {
            return "";
        }
        while (true) {
            $randomId = random_string(IdealisticOfficeLimit::URL_RANDOM_ID_LENGTH);

            if (!is_numeric($randomId) // so regular IDs can be trusted as numbers for queries
                && empty(get_sql_query(
                    IdealisticOfficeTable::PORTALS_TABLE,
                    array("id"),
                    array(
                        array("random_id", $randomId)
                    ),
                    null,
                    1
                ))) {
                break;
            }
        }
        return $randomId;
    }

    // Separator

    public function create(): IdealisticOfficeOutcome
    {
        if ($this->isDemo()) {
            return new IdealisticOfficeOutcome(false, "This portal is a demo and cannot be created.");
        }
        if ($this->id > 0) {
            return new IdealisticOfficeOutcome(false, "This portal has already been created.");
        }
        if (!$this->team->hasEstablishedAccess()) {
            return new IdealisticOfficeOutcome(false, "No team access established.");
        }
        $creator = $this->getCreator();

        if ($creator === null) {
            return new IdealisticOfficeOutcome(false, "Creator does not exist.");
        }
        if (!$creator->hasPermission(IdealisticOfficePermission::ADJUST_PORTALS)->isPositiveOutcome()) {
            return new IdealisticOfficeOutcome(
                false,
                "You do not have permission to manage portals."
            );
        }
        if (strlen($this->name) === 0) {
            return new IdealisticOfficeOutcome(false, "Portal name cannot be empty.");
        }
        if (strlen($this->name) > IdealisticOfficeLimit::H128_LENGTH) {
            return new IdealisticOfficeOutcome(
                false,
                "Portal name is too long. Maximum length is " . IdealisticOfficeLimit::H128_LENGTH . " characters."
            );
        }
        if (str_contains($this->name, "\r")) {
            return new IdealisticOfficeOutcome(false, "Portal name cannot contain carriage return characters.");
        }
        if (str_contains($this->name, "\n")) {
            return new IdealisticOfficeOutcome(false, "Portal name cannot contain newline characters.");
        }
        if (str_contains($this->name, "\t")) {
            return new IdealisticOfficeOutcome(false, "Portal name cannot contain tab characters.");
        }
        if (str_contains($this->name, " ")) {
            return new IdealisticOfficeOutcome(false, "Portal name cannot contain space characters.");
        }
        if (strlen($this->defaultMessage) > IdealisticOfficeLimit::CHAT_PORTAL_DEFAULT_MESSAGE_LENGTH) {
            return new IdealisticOfficeOutcome(
                false,
                "Default message is too long. Maximum length is " . IdealisticOfficeLimit::CHAT_PORTAL_DEFAULT_MESSAGE_LENGTH . " characters."
            );
        }
        if (!empty(get_sql_query(
            IdealisticOfficeTable::PORTALS_TABLE,
            array("id"),
            array(
                array("team_id", $this->team->getId()),
                array("LOWER(name)", strtolower($this->name)),
                array("deletion_date", null)
            ),
            null,
            1
        ))) {
            return new IdealisticOfficeOutcome(
                false,
                "A portal with name '" . $this->name . "' already exists."
            );
        }
        $date = IdealisticOfficeReader::getCurrentDate($this->creationTimeZone);
        $randomId = $this->findRandomId();

        if (IdealisticOfficeError::sqlInsert(
            $this->team,
            $creator,
            IdealisticOfficeTable::PORTALS_TABLE,
            array(
                "team_id" => $this->team->getId(),
                "created_by" => $this->getCreatorID(),
                "creation_date" => $date,
                "creation_time_zone" => $this->creationTimeZone,
                "default_message" => $this->defaultMessage,
                "name" => $this->name,
                "random_id" => $randomId,
                "password" => $this->password !== null
                    ? encrypt_password($this->password)
                    : null,
                "downloads_allowed" => $this->downloadsAllowed,
                "voice_replies" => $this->voiceReplies,
                "skip_robot_verification" => $this->skipRobotVerification,
                "is_18_plus" => $this->is18plus,
                "default_url" => $this->getDefaultUrl(),
                "require_login" => $this->requireLogin
            )
        )) {
            $lastInsertId = IdealisticOfficeReader::getSqlLastInsertId();

            if ($lastInsertId === null) {
                return new IdealisticOfficeOutcome(false, "Could not retrieve last insert ID.");
            }
            $this->id = $lastInsertId;
            $this->randomId = $randomId;
            $this->creationDate = $date;
            $this->processDefaultURL($creator);
            return new IdealisticOfficeOutcome(
                true,
                "Portal created successfully"
                . "\nPortal URL: " . $this->getURL()
                . "\nAccess Password: " . (empty($this->password) ? "(no password)" : $this->password)
            );
        } else {
            return new IdealisticOfficeOutcome(false, "Could not create portal.");
        }
    }

    public function regenerateURL(IdealisticOfficeUser $user): IdealisticOfficeOutcome
    {
        if ($this->id <= 0) {
            return new IdealisticOfficeOutcome(false, "This portal does not exist.");
        }
        if (!$user->hasPermission(IdealisticOfficePermission::ADJUST_PORTALS)->isPositiveOutcome()) {
            return new IdealisticOfficeOutcome(
                false,
                "You do not have permission to manage portals."
            );
        }
        $randomId = $this->findRandomId();

        if (IdealisticOfficeError::setSqlQuery(
            $this->team,
            $this->getCreator(),
            IdealisticOfficeTable::PORTALS_TABLE,
            array(
                "random_id" => $randomId
            ),
            array(
                array("id", $this->id)
            ),
            null,
            1
        )) {
            $this->randomId = $randomId;
            return new IdealisticOfficeOutcome(
                true,
                "Portal URL regenerated successfully"
                . "\nNew Portal URL: " . $this->getURL()
            );
        } else {
            return new IdealisticOfficeOutcome(false, "Could not regenerate portal URL.");
        }
    }

    public function changePassword(IdealisticOfficeUser $user, ?string $password): IdealisticOfficeOutcome
    {
        if (!$user->hasPermission(IdealisticOfficePermission::ADJUST_PORTALS)->isPositiveOutcome()) {
            return new IdealisticOfficeOutcome(false, "You do not have permission to manage portals.");
        }
        if ($password !== null
            && mb_strlen($password) > IdealisticOfficeLimit::H128_LENGTH) {
            return new IdealisticOfficeOutcome(
                false,
                "Password is too long. Maximum length is " . IdealisticOfficeLimit::H128_LENGTH . " characters."
            );
        }
        $encryptedPassword = $password !== null
            ? encrypt_password($password)
            : null;

        if (IdealisticOfficeError::setSqlQuery(
            $this->team,
            $this->getCreator(),
            IdealisticOfficeTable::PORTALS_TABLE,
            array(
                "password" => $encryptedPassword
            ),
            array(
                array("id", $this->id)
            ),
            null,
            1
        )) {
            $this->password = $encryptedPassword;
            return new IdealisticOfficeOutcome(
                true,
                "Portal password updated successfully"
                . "\nNew Access Password: " . (empty($password) ? "(no password)" : $password)
            );
        } else {
            return new IdealisticOfficeOutcome(false, "Could not update portal password.");
        }
    }

    public function rename(IdealisticOfficeUser $user, string $name): IdealisticOfficeOutcome
    {
        if ($this->isDemo()) {
            return new IdealisticOfficeOutcome(false, "This portal is a demo and cannot be renamed.");
        }
        if ($this->id <= 0) {
            return new IdealisticOfficeOutcome(false, "This portal does not exist.");
        }
        if (!$user->hasPermission(IdealisticOfficePermission::ADJUST_PORTALS)->isPositiveOutcome()) {
            return new IdealisticOfficeOutcome(false, "You do not have permission to rename folders.");
        }
        if (strlen($name) === 0) {
            return new IdealisticOfficeOutcome(false, "Portal name cannot be empty.");
        }
        if (strlen($name) > IdealisticOfficeLimit::H128_LENGTH) {
            return new IdealisticOfficeOutcome(
                false,
                "Portal name is too long. Maximum length is " . IdealisticOfficeLimit::H128_LENGTH . " characters."
            );
        }
        if (str_contains($name, "\r")) {
            return new IdealisticOfficeOutcome(false, "Portal name cannot contain carriage return characters.");
        }
        if (str_contains($name, "\n")) {
            return new IdealisticOfficeOutcome(false, "Portal name cannot contain newline characters.");
        }
        if (str_contains($name, "\t")) {
            return new IdealisticOfficeOutcome(false, "Portal name cannot contain tab characters.");
        }
        if (str_contains($name, " ")) {
            return new IdealisticOfficeOutcome(false, "Portal name cannot contain space characters.");
        }
        if ($name === $this->name) {
            return new IdealisticOfficeOutcome(false, "New portal name is the same as the current name.");
        }
        if (strtolower($name) !== strtolower($this->name)
            && !empty(get_sql_query(
                IdealisticOfficeTable::PORTALS_TABLE,
                array("id"),
                array(
                    array("team_id", $this->team->getId()),
                    array("LOWER(name)", strtolower($name)),
                    array("deletion_date", null)
                ),
                null,
                1
            ))) {
            return new IdealisticOfficeOutcome(
                false,
                "A portal with name '" . $this->name . "' already exists."
            );
        }
        if (IdealisticOfficeError::setSqlQuery(
            $this->team,
            $user,
            IdealisticOfficeTable::PORTALS_TABLE,
            array(
                "name" => $name
            ),
            array(
                array("id", $this->id)
            ),
            null,
            1
        )) {
            $oldName = $this->name;
            $this->name = $name;
            return new IdealisticOfficeOutcome(true, "Portal '" . $oldName . "' renamed successfully to '" . $name . "'.");
        } else {
            return new IdealisticOfficeOutcome(false, "Failed to rename portal with ID " . $this->id . ".");
        }
    }

    public function shouldRemove(array $ignoreFolders = []): bool
    {
        if ($this->isDemo()
            || $this->id <= 0) {
            return false;
        }
        $folders = $this->getConnectedFolders(false, 1);

        if (!empty($ignoreFolders)) {
            foreach ($folders as $key => $folder) {
                if (in_array($folder->getId(), $ignoreFolders)) {
                    unset($folders[$key]);
                }
            }
        }
        return empty($folders);
    }

    public function remove(?IdealisticOfficeUser $user = null, ?string $justification = null): IdealisticOfficeOutcome
    {
        if ($this->isDemo()) {
            return new IdealisticOfficeOutcome(false, "This portal is a demo and cannot be removed.");
        }
        if ($this->id <= 0) {
            return new IdealisticOfficeOutcome(false, "This portal does not exist.");
        }
        if ($user !== null) {
            if (!$user->hasPermission(IdealisticOfficePermission::ADJUST_PORTALS)->isPositiveOutcome()) {
                return new IdealisticOfficeOutcome(false, "You do not have permission to manage portals.");
            }
        }
        if ($justification !== null
            && mb_strlen($justification) > IdealisticOfficeLimit::K12_LENGTH) {
            return new IdealisticOfficeOutcome(
                false,
                "Justification is too long. Maximum length is " . IdealisticOfficeLimit::K12_LENGTH . " characters."
            );
        }
        $timezone = $user === null
            ? $this->team->getTimezone(false)
            : $user->getTimezone(false);

        if (IdealisticOfficeError::setSqlQuery(
            $this->team,
            $user,
            IdealisticOfficeTable::PORTALS_TABLE,
            array(
                "deletion_date" => IdealisticOfficeReader::getCurrentDate($timezone),
                "deletion_time_zone" => $timezone->getTimeZone(),
                "deleted_by" => $user?->getId(),
                "deletion_reason" => $justification
            ),
            array(
                array("id", $this->id)
            ),
            null,
            1
        )) {
            return new IdealisticOfficeOutcome(true, "Portal '" . $this->name . "' removed successfully.");
        } else {
            return new IdealisticOfficeOutcome(false, "Could not remove portal with ID '" . $this->id . "'.");
        }
    }

    public function restore(IdealisticOfficeUser $user): IdealisticOfficeOutcome
    {
        if ($this->isDemo()) {
            return new IdealisticOfficeOutcome(false, "This portal is a demo and cannot be restored.");
        }
        if ($this->id <= 0) {
            return new IdealisticOfficeOutcome(false, "This portal does not exist.");
        }
        if (!$user->hasPermission(IdealisticOfficePermission::ADJUST_PORTALS)->isPositiveOutcome()) {
            return new IdealisticOfficeOutcome(false, "You do not have permission to manage portals.");
        }
        if (IdealisticOfficeError::setSqlQuery(
            $this->team,
            $user,
            IdealisticOfficeTable::PORTALS_TABLE,
            array(
                "deletion_date" => null,
                "deletion_time_zone" => null,
                "deleted_by" => null
            ),
            array(
                array("id", $this->id)
            ),
            null,
            1
        )) {
            return new IdealisticOfficeOutcome(true, "Portal '" . $this->name . "' restored successfully.");
        } else {
            return new IdealisticOfficeOutcome(false, "Could not restore portal with ID '" . $this->id . "'.");
        }
    }

    // Separator

    public function getParentFolder(): ?IdealisticOfficeFolder
    {
        return $this->team->getFolder(
            $this->getId(),
            true,
            false
        );
    }

    /** @return IdealisticOfficeFolder[] */
    public function getConnectedFolders(bool $json = true, int $limit = IdealisticOfficeLimit::MAX_EXTENSIONS_PER_OBJECT): array
    {
        if ($this->isDemo()
            || $this->id <= 0) {
            return array();
        }
        $query = get_sql_query(
            IdealisticOfficeTable::FOLDER_ATTACHED_PORTALS_TABLE,
            array("folder_id", "id"),
            array(
                array("portal_id", $this->id),
                array("deletion_date", null)
            ),
            array(
                "DESC",
                "id"
            ),
            $limit
        );

        if (!empty($query)) {
            foreach ($query as $key => $row) {
                $folder = $this->team->getFolder($row->folder_id, false, $json);

                if ($folder !== null) {
                    $query[$key] = $folder;
                } else {
                    unset($query[$key]);
                    IdealisticOfficeError::setSqlQuery(
                        $this->team,
                        null,
                        IdealisticOfficeTable::FOLDER_ATTACHED_PORTALS_TABLE,
                        array(
                            "deletion_date" => IdealisticOfficeReader::getCurrentDate(),
                            "deletion_time_zone" => IdealisticOfficeVariable::DEFAULT_TIME_ZONE
                        ),
                        array(
                            array("id", $row->id)
                        ),
                        null,
                        1
                    );
                }
            }
        }
        return $query;
    }

    public function setDefaultMessage(IdealisticOfficeUser $user, ?string $defaultMessage): IdealisticOfficeOutcome
    {
        if ($this->isDemo()) {
            return new IdealisticOfficeOutcome(false, "This portal is a demo and cannot be updated.");
        }
        if ($this->id <= 0) {
            return new IdealisticOfficeOutcome(false, "This portal does not exist.");
        }
        if (!$user->hasPermission(IdealisticOfficePermission::ADJUST_PORTALS)->isPositiveOutcome()) {
            return new IdealisticOfficeOutcome(false, "You do not have permission to manage portals.");
        }
        if ($defaultMessage !== null
            && mb_strlen($defaultMessage) > IdealisticOfficeLimit::CHAT_PORTAL_DEFAULT_MESSAGE_LENGTH) {
            return new IdealisticOfficeOutcome(
                false,
                "Default message is too long. Maximum length is " . IdealisticOfficeLimit::CHAT_PORTAL_DEFAULT_MESSAGE_LENGTH . " characters."
            );
        }
        if (IdealisticOfficeError::setSqlQuery(
            $this->team,
            $user,
            IdealisticOfficeTable::PORTALS_TABLE,
            array(
                "default_message" => $defaultMessage
            ),
            array(
                array("id", $this->id)
            ),
            null,
            1
        )) {
            $this->defaultMessage = $defaultMessage;
            return new IdealisticOfficeOutcome(true, "Portal default message successfully updated.");
        } else {
            return new IdealisticOfficeOutcome(false, "Could not update portal default message.");
        }
    }

    // Separator

    /**
     * Instantly generates a base64 Data URI for immediate frontend HTML rendering.
     * Use this in <img src="<?= $portal->getQRCodeDataUri() ?>">
     */
    public function getQRCodeDataUri(): ?string
    {
        if ($this->isDemo()
            || $this->id <= 0) {
            return null;
        }
        return generate_qr_code(
            $this->getURL(),
            \chillerlan\QRCode\QRCode::OUTPUT_IMAGE_PNG,
            true
        );
    }

    public function createQRCodeAttachment(int|Account|IdealisticOfficeUser $creator, bool $json = true): ?IdealisticOfficeAttachment
    {
        if ($this->isDemo()
            || $this->id <= 0) {
            return null;
        }
        $rawBytes = generate_qr_code(
            $this->getURL(),
            \chillerlan\QRCode\QRCode::OUTPUT_IMAGE_PNG,
            false
        );
        $dimensions = @getimagesizefromstring($rawBytes);
        $attachment = new IdealisticOfficeAttachment(
            null,
            "portal_" . $this->getId() . "_qr_code.png",
            "image/png",
            null,
            mb_strlen($rawBytes),
            $dimensions[0] ?? null,
            $dimensions[1] ?? null,
            "QR code linking to the URL '" . $this->getURL() . "' of the portal '" . $this->getName() . "'.",
            base64_encode($rawBytes),
            null,
            true,
            IdealisticOfficeReader::getCurrentDate($this->creationTimeZone),
            $this->creationTimeZone,
            $creator,
            $json
        );
        $attachment->setUserPrompt("");
        return $attachment;
    }

    // Separator

    public function setDownloadsAllowed(IdealisticOfficeUser $user, bool $allowed): IdealisticOfficeOutcome
    {
        if ($this->isDemo()) {
            return new IdealisticOfficeOutcome(false, "This portal is a demo and cannot be updated.");
        }
        if ($this->id <= 0) {
            return new IdealisticOfficeOutcome(false, "This portal does not exist.");
        }
        if (!$user->hasPermission(IdealisticOfficePermission::ADJUST_PORTALS)->isPositiveOutcome()) {
            return new IdealisticOfficeOutcome(false, "You do not have permission to manage portals.");
        }
        if (IdealisticOfficeError::setSqlQuery(
            $this->team,
            $user,
            IdealisticOfficeTable::PORTALS_TABLE,
            array(
                "downloads_allowed" => $allowed
            ),
            array(
                array("id", $this->id)
            ),
            null,
            1
        )) {
            $this->downloadsAllowed = $allowed;
            return new IdealisticOfficeOutcome(true, "Portal download permissions successfully updated to " . ($allowed ? "allowed" : "not allowed") . ".");
        } else {
            return new IdealisticOfficeOutcome(false, "Could not update portal download permissions.");
        }
    }

    // Separator

    public function setVoiceRepliesAllowed(IdealisticOfficeUser $user, bool $allowed): IdealisticOfficeOutcome
    {
        if ($this->isDemo()) {
            return new IdealisticOfficeOutcome(false, "This portal is a demo and cannot be updated.");
        }
        if ($this->id <= 0) {
            return new IdealisticOfficeOutcome(false, "This portal does not exist.");
        }
        if (!$user->hasPermission(IdealisticOfficePermission::ADJUST_PORTALS)->isPositiveOutcome()) {
            return new IdealisticOfficeOutcome(false, "You do not have permission to manage portals.");
        }
        if (IdealisticOfficeError::setSqlQuery(
            $this->team,
            $user,
            IdealisticOfficeTable::PORTALS_TABLE,
            array(
                "voice_replies_allowed" => $allowed
            ),
            array(
                array("id", $this->id)
            ),
            null,
            1
        )) {
            $this->voiceReplies = $allowed;
            $reply = "Portal voice reply permissions successfully updated to " . ($allowed ? "allowed" : "not allowed") . ".";

            if (!self::VOICE_REPLIES) {
                $reply .= " Voice replies are currently disabled for all portals in our system, so this setting will not have any effect until that's changed.";
            }
            return new IdealisticOfficeOutcome(true, $reply);
        } else {
            return new IdealisticOfficeOutcome(false, "Could not update portal voice reply permissions.");
        }
    }

    public function set18Plus(IdealisticOfficeUser $user, bool $is18Plus): IdealisticOfficeOutcome
    {
        if ($this->isDemo()) {
            return new IdealisticOfficeOutcome(false, "This portal is a demo and cannot be updated.");
        }
        if ($this->id <= 0) {
            return new IdealisticOfficeOutcome(false, "This portal does not exist.");
        }
        if (!$user->hasPermission(IdealisticOfficePermission::ADJUST_PORTALS)->isPositiveOutcome()) {
            return new IdealisticOfficeOutcome(false, "You do not have permission to manage portals.");
        }
        if (IdealisticOfficeError::setSqlQuery(
            $this->team,
            $user,
            IdealisticOfficeTable::PORTALS_TABLE,
            array(
                "is_18_plus" => $is18Plus
            ),
            array(
                array("id", $this->id)
            ),
            null,
            1
        )) {
            $this->is18plus = $is18Plus;
            $action = $is18Plus ? "enabled" : "disabled";
            return new IdealisticOfficeOutcome(true, "Portal 18+ age restriction successfully updated to " . $action . ".");
        } else {
            return new IdealisticOfficeOutcome(false, "Could not update portal age restriction settings.");
        }
    }

    // Separator

    public function refreshLinks(IdealisticOfficeUser $user): IdealisticOfficeOutcome
    {
        if ($this->isDemo()) {
            return new IdealisticOfficeOutcome(false, "This portal is a demo and cannot be updated.");
        }
        if ($this->id <= 0) {
            return new IdealisticOfficeOutcome(false, "This portal does not exist.");
        }
        if (!$user->hasPermission(IdealisticOfficePermission::ADJUST_PORTALS)->isPositiveOutcome()) {
            return new IdealisticOfficeOutcome(
                false,
                "You do not have permission to manage portal links."
            );
        }
        $links = $this->getSeparatePartialLinks(null, false);

        if (empty($links)) {
            return new IdealisticOfficeOutcome(true, "No links found to refresh for portal '" . $this->getName() . "'.");
        }
        if (sizeof($links) > IdealisticOfficeLimit::MAX_EXTENSIONS_PER_OBJECT) {
            $links = array_slice($links, 0, IdealisticOfficeLimit::MAX_EXTENSIONS_PER_OBJECT);
        }
        $refreshed = 0;
        $refreshedRecently = 0;

        foreach ($links as $link) {
            if ($link->getRefreshDate() === null
                || IdealisticOfficeReader::getPastDate(IdealisticOfficeLimit::LINK_REFRESH_COOLDOWN, $link->getRefreshTimeZone()) > $link->getRefreshDate()) {
                if ($link->refresh(
                    $user,
                    IdealisticOfficeTable::PORTAL_LINKS_TABLE
                )->isPositiveOutcome()) {
                    $refreshed++;
                }
            } else {
                $refreshedRecently++;
            }
        }
        return new IdealisticOfficeOutcome(
            true,
            "Refreshed " . $refreshed . " out of " . count($links) . " links."
            . ($refreshedRecently > 0 ? " " . $refreshedRecently . " links were refreshed recently and were skipped." : "")
        );
    }

    // Separator

    public function transfer(IdealisticOfficeUser $user): IdealisticOfficeOutcome
    {
        if ($this->isDemo()) {
            return new IdealisticOfficeOutcome(false, "This portal is a demo and cannot be transferred.");
        }
        if ($this->id <= 0) {
            return new IdealisticOfficeOutcome(false, "This portal does not exist.");
        }
        $creator = $this->getCreator();

        if ($creator === null) {
            return new IdealisticOfficeOutcome(false, "Creator does not exist.");
        }
        if (!$creator->hasPermission(IdealisticOfficePermission::ADJUST_PORTALS)->isPositiveOutcome()) {
            return new IdealisticOfficeOutcome(false, "You do not have permission to manage portals.");
        }
        if ($user->getTeam()->getId() === $this->team->getId()) {
            return new IdealisticOfficeOutcome(false, "Cannot transfer a portal to its current team.");
        }
        if (IdealisticOfficeError::setSqlQuery(
            $this->team,
            $creator,
            IdealisticOfficeTable::PORTALS_TABLE,
            array(
                "transfer_to" => $creator->getId()
            ),
            array(
                array("id", $this->id)
            ),
            null,
            1
        )) {
            return new IdealisticOfficeOutcome(true, "Portal transfer initiated successfully.");
        } else {
            return new IdealisticOfficeOutcome(false, "Could not initiate portal transfer.");
        }
    }

    public function cancelTransfer(): IdealisticOfficeOutcome
    {
        if ($this->isDemo()) {
            return new IdealisticOfficeOutcome(false, "This portal is a demo and cannot be updated.");
        }
        if ($this->id <= 0) {
            return new IdealisticOfficeOutcome(false, "This portal does not exist.");
        }
        $creator = $this->getCreator();

        if ($creator === null) {
            return new IdealisticOfficeOutcome(false, "Creator does not exist.");
        }
        if (!$creator->hasPermission(IdealisticOfficePermission::ADJUST_PORTALS)->isPositiveOutcome()) {
            return new IdealisticOfficeOutcome(false, "You do not have permission to manage portals.");
        }

        if (IdealisticOfficeError::setSqlQuery(
            $this->team,
            $creator,
            IdealisticOfficeTable::PORTALS_TABLE,
            array(
                "transfer_to" => null
            ),
            array(
                array("id", $this->id)
            ),
            null,
            1
        )) {
            return new IdealisticOfficeOutcome(true, "Portal transfer cancelled successfully.");
        } else {
            return new IdealisticOfficeOutcome(false, "Could not cancel portal transfer.");
        }
    }

    public static function acceptTransfer(IdealisticOfficeUser $user, IdealisticOfficeUser $requester): IdealisticOfficeOutcome
    {
        if (!$user->hasPermission(IdealisticOfficePermission::ADJUST_PORTALS)->isPositiveOutcome()) {
            return new IdealisticOfficeOutcome(false, "You do not have permission to manage portals.");
        }
        if ($user->getId() === $requester->getId()) {
            return new IdealisticOfficeOutcome(false, "You cannot accept a portal transfer from yourself.");
        }
        $portals = $requester->getTeam()->getPortals(0, false);

        if (empty($portals)) {
            return new IdealisticOfficeOutcome(false, "No portals available for transfer acceptance.");
        }
        $acceptingTeamId = $user->getTeam()->getId();
        $valid = false;

        foreach ($portals as $portal) {
            if ($portal->getTransferTo() === $user->getId()) {
                $valid = true;

                if (IdealisticOfficeError::setSqlQuery(
                    $user->getTeam(),
                    $user,
                    IdealisticOfficeTable::PORTALS_TABLE,
                    array(
                        "team_id" => $acceptingTeamId,
                        "transfer_to" => null
                    ),
                    array(
                        array("id", $portal->getId())
                    ),
                    null,
                    1
                )) {
                    $folders = $portal->getConnectedFolders(false);

                    if (!empty($folders)) {
                        foreach ($folders as $folder) {
                            $folder->addUser($requester, $user, true);

                            if (!IdealisticOfficeError::setSqlQuery(
                                $user->getTeam(),
                                $user,
                                IdealisticOfficeTable::FOLDERS_TABLE,
                                array(
                                    "team_id" => $acceptingTeamId
                                ),
                                array(
                                    array("id", $folder->getId())
                                ),
                                null,
                                1
                            )) {
                                return new IdealisticOfficeOutcome(false, "Could not accept portal transfer due to folder failure.");
                            }
                            $folder->setTeam($user->getTeam());
                            $folder->removeUser($user, $requester, true);
                        }
                    }
                } else {
                    return new IdealisticOfficeOutcome(false, "Could not accept portal transfer.");
                }
            }
        }
        return $valid
            ? new IdealisticOfficeOutcome(true, "Portal transfer accepted successfully.")
            : new IdealisticOfficeOutcome(false, "No valid portal transfers found for acceptance.");
    }

    // Separator

    public function setRequireLogin(IdealisticOfficeUser $user, bool $requireLogin): IdealisticOfficeOutcome
    {
        if ($this->isDemo()) {
            return new IdealisticOfficeOutcome(false, "This portal is a demo and cannot be updated.");
        }
        if ($this->id <= 0) {
            return new IdealisticOfficeOutcome(false, "This portal does not exist.");
        }
        if (!$user->hasPermission(IdealisticOfficePermission::ADJUST_PORTALS)->isPositiveOutcome()) {
            return new IdealisticOfficeOutcome(false, "You do not have permission to manage portals.");
        }
        if (IdealisticOfficeError::setSqlQuery(
            $this->team,
            $user,
            IdealisticOfficeTable::PORTALS_TABLE,
            array(
                "require_login" => $requireLogin
            ),
            array(
                array("id", $this->id)
            ),
            null,
            1
        )) {
            $this->requireLogin = $requireLogin;
            return new IdealisticOfficeOutcome(true, "Portal login requirement successfully updated to " . ($requireLogin ? "required" : "not required") . ".");
        } else {
            return new IdealisticOfficeOutcome(false, "Could not update portal login requirement.");
        }
    }

}