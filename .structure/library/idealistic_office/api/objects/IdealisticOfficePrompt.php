<?php

use React\EventLoop\LoopInterface;
use React\Promise\PromiseInterface;
use function React\Promise\all;
use function React\Promise\reject;
use function React\Promise\resolve;

class IdealisticOfficePrompt implements PhpAsyncSerializable
{

    public const
        CLASS_NAME = self::class,
        DATABASE_COLUMNS = array(
        "history_id",
        "task_id",
        "utc_creation_date"
    );

    private const
        GENERAL_TIME = "general_time",
        HISTORY_FAILED = "history_failed",
        TASK_NOT_TRIGGERED = "not_triggered",
        STOP_OUTCOME = "stop_outcome";

    // Separator

    private IdealisticOfficeUser $user;
    private int $platformId;
    private string $taskID;
    private ?string $prompt, $userName, $displayName, $email, $platformChatName;
    private string|bool|null $restoredPrompt;
    private int|string $platformUserID;
    private int|string|null $platformChatID, $platformMessageID;
    private float $creationTime, $lastCreationTime;
    private ?IdealisticOfficePromptCache $cache;
    private bool $automated;

    /**
     * @throws Exception
     */
    public function __construct(
        int                  $platformID,
        int|string           $platformUserID,
        int|string|null      $platformChatID,
        ?string              $platformChatName,
        int|string|null      $platformMessageID,
        ?string              $userName,
        ?string              $displayName,
        ?string              $email,
        IdealisticOfficeUser $user,
        ?string              $prompt,
        bool                 $automated
    )
    {
        $this->platformId = $platformID;
        $this->platformUserID = $platformUserID;
        $this->platformChatID = $platformChatID;
        $this->platformChatName = $platformChatName;
        $this->platformMessageID = $platformMessageID;
        $this->userName = $userName;
        $this->displayName = $displayName;
        $this->email = $email;
        $this->user = $user;
        $this->prompt = $prompt;
        $this->taskID = "";
        $this->creationTime = microtime(true);
        $this->lastCreationTime = microtime(true);
        $this->cache = null;
        $this->restoredPrompt = false;
        $this->automated = $automated;
    }

    public function trackUserIdentity(): void
    {
        if (!$this->user->exists()) { // Transition from free prompt
            $user = IdealisticOfficeTeamInitiator::findUser(
                $this->platformId,
                $this->platformUserID
            );

            if ($user instanceof IdealisticOfficeUser
                && $user->hasEstablishedAccess()) {
                $this->user = $user;
                $this->cache->setTeam($user->getTeam());
                IdealisticOfficeError::setSqlQuery(
                    null,
                    null,
                    IdealisticOfficeTable::HISTORY_TOTAL_TABLE,
                    array(
                        "account_id" => $user->getId(),
                        "team_id" => $user->getTeam()->getId()
                    ),
                    array(
                        array("id", $this->getHistory()->getId())
                    ),
                    null,
                    1
                );
            } else {
                $this->user->setLastIdentity(
                    new IdealisticOfficeUserIdentity(
                        0,
                        $this->userName,
                        $this->displayName,
                        $this->platformId,
                        $this->platformUserID,
                        $this->platformChatID,
                        $this->platformChatName,
                        $this->platformMessageID,
                        IdealisticOfficeReader::getCurrentDate(),
                        IdealisticOfficeVariable::DEFAULT_TIME_ZONE,
                        null,
                        null,
                        false
                    )
                );
                return;
            }
        }
        $this->user->trackIdentity(
            $this->platformId,
            $this->platformUserID,
            $this->platformChatID,
            $this->platformChatName,
            $this->platformMessageID,
            $this->userName,
            $this->displayName
        );
    }

    // Separator

    public function getLoop(): ?LoopInterface
    {
        return $this->cache?->getLoop();
    }

    // Separator

    public function getCache(): ?IdealisticOfficePromptCache
    {
        return $this->cache;
    }

    public function setCache(IdealisticOfficePromptCache $cache): void
    {
        $this->cache = $cache;
    }

    // Separator

    private function setCreationTime(): void
    {
        $this->lastCreationTime = microtime(true);
    }

    public function getGeneralTimePassed(): float
    {
        return microtime(true) - $this->creationTime;
    }

    private function getTimePassed(): float
    {
        return microtime(true) - $this->lastCreationTime;
    }

    // Separator

    public function getPlatformId(): int
    {
        return $this->platformId;
    }

    public function getPlatformUserID(): string|int
    {
        return $this->platformUserID;
    }

    public function getPlatformChatID(): int|string|null
    {
        return $this->platformChatID;
    }

    public function getPlatformChatName(): ?string
    {
        return $this->platformChatName;
    }

    public function getPlatformMessageID(): int|string|null
    {
        return $this->platformMessageID;
    }

    public function getUserName(): ?string
    {
        return $this->userName;
    }

    public function getDisplayName(): ?string
    {
        return $this->displayName;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function getUser(): IdealisticOfficeUser
    {
        return $this->user;
    }

    public function getTeam(): AbstractIdealisticOfficeTeam
    {
        return $this->cache === null
            ? $this->user->getTeam()
            : $this->cache->getTeam();
    }

    public function getEvolvedTeam(): IdealisticOfficeTeam
    {
        if ($this->cache === null) {
            return $this->user->getTeam();
        } else {
            return $this->cache->getEvolvedTeam();
        }
    }

    public function getPrompt(): string
    {
        if ($this->restoredPrompt === false) {
            $this->restoredPrompt = $this->prompt === null
                ? ""
                : trim(base64_decode($this->prompt));

            if (!empty($this->restoredPrompt)
                && $this->cache->isTaskEnabled(IdealisticOfficePromptTask::MANAGE_SHORTCUTS)) {
                $shortcuts = $this->user->getShortcuts(0, false);

                if (!empty($shortcuts)) {
                    $array = array();

                    foreach ($shortcuts as $shortcut) {
                        $array[$shortcut->getWord()] = $shortcut->getReplacement();
                    }
                    $this->restoredPrompt = str_replace(
                        array_keys($array),
                        array_values($array),
                        $this->restoredPrompt
                    );
                }
            }
        }
        return $this->restoredPrompt;
    }

    // Separator

    public function setTaskID(string $taskID): void
    {
        $this->taskID = $taskID;
    }

    public function setTaskIDAndGetRow(string $taskID, IdealisticOfficeHistory $history): array
    {
        $this->setTaskID($taskID);
        return array(
            $history->getId(),
            $this->taskID,
            IdealisticOfficeReader::getCurrentDate()
        );
    }

    public function getTaskID(): string
    {
        return $this->taskID;
    }

    // Separator

    public function notify(string $message): bool
    {
        $this->cache->justifyDelay();
        return $this->user->addNotification(
            null,
            $message
        );
    }

    public function notifyWithoutTrace(string $message): bool
    {
        if ($this->platformId === IdealisticOfficeAccessPlatform::NATIVE) {
            return false;
        }
        $this->cache->justifyDelay();
        return $this->user->addNotification(
            null,
            $message,
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

    private function findFailedTaskFromResult(AIObjectConstructor $constructor): void
    {
        $object = $constructor->getLastObject();

        if ($object !== null) {
            $error = $object->{AIObjectConstructor::ERROR_KEY} ?? null;

            if ($error !== null) {
                if (is_array($error)
                    || is_object($error)) {
                    $error = IdealisticOfficeReader::jsonObject($error, false);
                }
                $this->notify(
                    $error
                );
            }
        }
    }

    public function expandFields(array $fields): array
    {
        return $fields;
    }

    public function expandInstructions(array $instructions = []): array
    {
        return array_merge(
            $instructions,
            IdealisticOfficeReader::getSystemReport($this)
        );
    }

    public function readAIObject(
        AIObjectConstructor $constructor,
        mixed               $result,
        bool                $findFailure = false
    ): ?object
    {
        $object = $constructor->get($result, false);

        if ($object === null) {
            if ($findFailure) {
                $this->findFailedTaskFromResult($constructor);
            }
            if (!empty($result)) {
                IdealisticOfficeError::sqlInsert(
                    $this->getTeam(),
                    $this->user,
                    IdealisticOfficeTable::HISTORY_FAILED_OBJECTS_TABLE,
                    array(
                        "history_id" => $this->getHistory()->getId(),
                        "task_id" => $this->taskID,
                        "raw_response" => (is_array($result) || is_object($result))
                            ? IdealisticOfficeReader::jsonObject($result, true)
                            : $result
                    )
                );
            }
        }
        return $object;
    }

    // Separator

    public function getHistory(): IdealisticOfficeHistory
    {
        return $this->cache->getHistory();
    }

    /** @return IdealisticOfficeHistory[] */
    public function getUserHistory(
        int $afterId = 0,
        int $limit = IdealisticOfficeLimit::MAX_PROCESSED_ROWS
    ): array
    {
        return $this->user->getHistory($afterId, $limit);
    }

    public function getUserHistoryCount(int $limit = IdealisticOfficeLimit::MAX_PROCESSED_ROWS): int
    {
        return sizeof($this->user->getHistoryIDs($limit));
    }

    /** @return IdealisticOfficeHistory[] */
    public function getTeamHistory(int $limit = IdealisticOfficeLimit::MAX_PROCESSED_ROWS): array
    {
        if ($this->user->hasEstablishedAccess()) {
            return $this->getTeam()->getHistory($limit);
        } else {
            return $this->getUserHistory(0, $limit);
        }
    }

    // Separator

    private function storeOutcome(
        IdealisticOfficeOutcome|array|null $outcome,
        ?string                            $suffix
    ): PromiseInterface
    {
        $time = $this->getTimePassed();

        if (!$this->hasTerminated()) {
            $this->cache->terminateTask($this->taskID);

            if ($outcome === null
                || $outcome instanceof IdealisticOfficeOutcome) {
                $finalOutcome = $outcome;
            } else {
                $finalOutcome = array();

                if (empty($outcome)) {
                    $finalOutcome = null;
                } else if (sizeof($outcome) === 1) { // Preserves certain parameters
                    $pop = array_shift($outcome);
                    $finalOutcome = $pop instanceof IdealisticOfficeOutcome
                        ? $pop
                        : null;
                } else {
                    $bool = true;
                    $redundancy = array();

                    foreach ($outcome as $singleOutcome) {
                        if (!($singleOutcome instanceof IdealisticOfficeOutcome)) {
                            continue;
                        }
                        $raw = $singleOutcome->getRawMessage();

                        if (is_array($raw)) {
                            $raw = implode(IdealisticOfficeVariable::DEFAULT_TEXT_SEPARATOR, $raw);
                        }
                        if (array_key_exists(
                            $raw,
                            $redundancy
                        )) {
                            continue;
                        }
                        $redundancy[$raw] = true;
                        $bool &= $singleOutcome->isPositiveOutcome();
                        $finalOutcome[] = $raw;
                    }
                    $finalOutcome = new IdealisticOfficeOutcome(
                        $bool,
                        ($suffix ?? "") . str_replace(
                            "." . IdealisticOfficeVariable::DEFAULT_TEXT_SEPARATOR,
                            IdealisticOfficeVariable::DEFAULT_TEXT_SEPARATOR,
                            implode(IdealisticOfficeVariable::DEFAULT_TEXT_SEPARATOR, $finalOutcome)
                        )
                    );
                }
            }
            $callable = function () use ($finalOutcome, $time) {
                // Close process
                IdealisticOfficeError::setSqlQuery(
                    $this->getTeam(),
                    $this->user,
                    IdealisticOfficeTable::HISTORY_PROCESSING_TIMES_TABLE,
                    array(
                        "time_passed" => $time
                    ),
                    array(
                        array("history_id", $this->getHistory()->getId()),
                        array("task_id", $this->taskID)
                    ),
                    null,
                    1
                );

                // Close all processes
                if ($finalOutcome !== null
                    && $finalOutcome->isStopOutcome()) {
                    foreach ($this->cache->getEnabledTasks() as $task) {
                        $this->terminate($task, self::STOP_OUTCOME);
                    }
                } else if ($this->getHistory()->failed()) {
                    foreach ($this->cache->getEnabledTasks() as $task) {
                        $this->terminate($task, self::HISTORY_FAILED);
                    }
                }
            };

            if ($finalOutcome !== null) {
                return $finalOutcome->getTranslatedMessage(
                    $this->user,
                    $this
                )->then(
                    function (string $translatedMessage) use ($finalOutcome, $callable) {
                        $values = str_split(
                            $translatedMessage,
                            IdealisticOfficeLimit::K13_LENGTH
                        );
                        $insert = array(
                            "history_id" => $this->getHistory()->getId(),
                            "task_id" => $this->taskID
                        );

                        if ($this->mustSendNotification()) {
                            $this->user->addNotification(
                                null,
                                $translatedMessage,
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
                        if ($finalOutcome->isStopOutcome()) {
                            $insert["is_stop"] = true;
                        }
                        $promises = array();

                        foreach ($values as $value) {
                            $promises[] = $this->cache->getEmbedding(
                                $value
                            )->then(function ($positions) use ($insert, $value) {
                                $insert["history_value"] = $value;

                                if ($positions !== null) {
                                    $embedding = pack("f*", ...$positions);

                                    if (empty($embedding)) {
                                        return;
                                    }
                                    $insert["history_value_positions"] = $embedding;
                                }
                                IdealisticOfficeError::sqlInsert(
                                    $this->getTeam(),
                                    $this->user,
                                    IdealisticOfficeTable::HISTORY_REPLIES_TABLE,
                                    $insert
                                );
                            });
                        }
                        return all($promises)->then(
                            function () use ($callable) {
                                $callable();
                                $this->cache->runDependencies($this->taskID);
                                return resolve(null);
                            },
                            function (Throwable $e) use ($callable) {
                                $callable();
                                $this->cache->runDependencies($this->taskID);
                                return reject($e);
                            }
                        );
                    },
                    function (Throwable $e) use ($callable) {
                        $this->cache->runDependencies($this->taskID);
                        throw $e;
                    }
                );
            } else {
                $callable();
            }
        }
        $this->cache->runDependencies($this->taskID);
        return resolve(null);
    }

    public function waitForTasks(array $tasks = []): void
    {
        if (!empty($tasks)) {
            $search = array_search($this->taskID, $tasks);

            if ($search !== false) {
                unset($tasks[$search]);
            }
            $this->cache->setToDependOn($this->taskID, $tasks);

            foreach ($tasks as $taskID) {
                $this->cache->setToRunAfter($taskID, $this->taskID);
            }
        }
    }

    public function runTask(
        callable $callable,
        ?string  $suffix = IdealisticOfficeVariable::DEFAULT_TASK_SUFFIX . "\n"
    ): void
    {
        $function = function () use ($callable, $suffix) {
            try {
                if (!$this->cache->isTaskProcessing($this->taskID)) {
                    $this->cache->runDependencies($this->taskID);
                    return;
                }
                if ($this->hasTerminated(false)) {
                    $this->cache->terminateTask($this->taskID);
                    $this->cache->runDependencies($this->taskID);
                    return;
                }
                $this->setCreationTime();
                $promise = $callable();

                if ($promise === null) {
                    throw new Exception("No promise returned from callable for task: " . $this->taskID);
                }
                $promise->then(
                    function (
                        IdealisticOfficeOutcome|array|null $outcome = null,
                        bool                               $ignore = false
                    ) use ($suffix) {
                        if ($ignore) {
                            return;
                        }
                        $this->storeOutcome(
                            $outcome,
                            $suffix
                        )->then(
                            function ($value) {
                            },
                            function (Throwable $e) {
                                IdealisticOfficeError::storeThrowable(
                                    $this->getTeam(),
                                    $this->user,
                                    $e
                                );
                                $this->storeOutcome(
                                    new IdealisticOfficeOutcome(
                                        false,
                                        IdealisticOfficeGeneralMessage::EXCEPTION_THROWN . " (#782639401)"
                                    ),
                                    null
                                )->then(
                                    function ($value) {
                                    },
                                    function (Throwable $e) {
                                        IdealisticOfficeError::storeThrowable(
                                            $this->getTeam(),
                                            $this->user,
                                            $e
                                        );
                                    }
                                );
                            }
                        );
                    },
                    function (Throwable $e) {
                        IdealisticOfficeError::storeThrowable(
                            $this->getTeam(),
                            $this->user,
                            $e
                        );
                        $this->storeOutcome(
                            new IdealisticOfficeOutcome(
                                false,
                                IdealisticOfficeGeneralMessage::EXCEPTION_THROWN . " (#671842305)"
                            ),
                            null
                        );
                    }
                );
            } catch (Throwable $e) {
                IdealisticOfficeError::storeThrowable(
                    $this->getTeam(),
                    $this->user,
                    $e
                );
                $this->storeOutcome(
                    new IdealisticOfficeOutcome(
                        false,
                        IdealisticOfficeGeneralMessage::EXCEPTION_THROWN . " (#450193827)"
                    ),
                    null
                );
            }
        };

        if ($this->cache->isDependentTask($this->taskID)) {
            $this->cache->setTaskCallable($this->taskID, $function);
        } else {
            $this->cache->getLoop()->futureTick($function);
        }
    }

    // Separator

    private function hasTerminated(bool $cache = true): bool
    {
        if ($cache
            && !$this->cache->isTaskProcessing($this->taskID)) {
            return true;
        }
        if (!$this->cache->isTaskEnabled($this->taskID)) {
            return $this->terminate($this->taskID, self::TASK_NOT_TRIGGERED);
        } else if ($this->getGeneralTimePassed() > IdealisticOfficeLimit::HISTORY_PROMPT_SECONDS_TIMEOUT) {
            foreach ($this->cache->getEnabledTasks() as $task) {
                if ($this->cache->isTaskProcessing($task)) {
                    $this->notify(
                        IdealisticOfficeGeneralMessage::TIME_TERMINATED_TASKS
                    );
                    break;
                }
            }
            return $this->terminate($this->taskID, self::GENERAL_TIME);
        } else {
            return false;
        }
    }

    private function terminate(string $taskID, int|string $termination): bool
    {
        if ($this->cache->isTaskProcessing($taskID)) {
            $this->cache->terminateTask($taskID);
            IdealisticOfficeError::setSqlQuery(
                $this->getTeam(),
                $this->user,
                IdealisticOfficeTable::HISTORY_PROCESSING_TIMES_TABLE,
                array(
                    "task_terminated" => $termination
                ),
                array(
                    array("history_id", $this->getHistory()->getId()),
                    array("task_id", $taskID)
                ),
                null,
                1
            );
            return true;
        } else {
            return false;
        }
    }

    // Separator

    private function mustSendNotification(): bool
    {
        return $this->automated
            || in_array($this->platformId, IdealisticOfficeLimit::DISALLOWS_MESSAGE_EDITS);
    }

    public function sendAttachment(
        IdealisticOfficeAttachment $attachment,
        ?string                    $overrideMessage = null,
        bool                       $notify = false
    ): PromiseInterface
    {
        $timezone = $this->user->getTimeZone(false);
        $analyzedDescription = $attachment->getAnalyzedDescription();
        $userPrompt = $attachment->getUserPrompt();
        $sendNotification = $this->mustSendNotification();

        $performInsert = function (?array $positions) use (
            $attachment, $analyzedDescription, $userPrompt, $timezone,
            $sendNotification, $overrideMessage, $notify
        ) {
            if ($positions !== null) {
                $packedPositions = pack("f*", ...$positions);

                if (empty($packedPositions)) {
                    $packedPositions = null;
                }
            } else {
                $packedPositions = null;
            }
            $insert = array(
                "history_id" => $this->getHistory()->getId(),
                "task_id" => $this->taskID,
                "attachment_format" => $attachment->getFormat(),
                "attachment_name" => $attachment->getName(),
                "attachment_bytes" => $attachment->getBytes(),
                "attachment_x" => $attachment->getXSize(),
                "attachment_y" => $attachment->getYSize(),
                "attachment_data" => $attachment->getRawData(),
                "attachment_data_hash" => $attachment->getDataHash(),
                "analyzed_description" => $analyzedDescription,
                "analyzed_description_positions" => $packedPositions,
                "user_prompt" => $userPrompt,
                "is_base64" => $attachment->isBase64(),
                "creation_date" => IdealisticOfficeReader::getCurrentDate($timezone),
                "creation_time_zone" => $timezone->getTimeZone(),
                "created_by" => $this->user->getId()
            );

            if (IdealisticOfficeError::sqlInsert(
                $this->getTeam(),
                $this->user,
                IdealisticOfficeTable::HISTORY_CREATED_ATTACHMENTS_TABLE,
                $insert
            )) {
                $insertID = IdealisticOfficeReader::getSqlLastInsertId();

                if ($insertID !== null) {
                    $attachment->setId($insertID);

                    if ($sendNotification) {
                        $message = $overrideMessage === null
                            ? $attachment->getAnalyzedDescription()
                            : $overrideMessage;
                        $this->user->addAttachmentNotification(
                            null,
                            $message,
                            $attachment,
                            in_array($this->platformId, IdealisticOfficeLimit::ATTACHMENT_URL_ONLY),
                            !empty($message),
                            true
                        );
                    } else {
                        $this->attachmentNotify($notify, $attachment);
                    }
                }
            }
            return resolve(null);
        };

        if (empty($analyzedDescription)) {
            return $performInsert(null);
        } else {
            return $this->cache->getEmbedding($analyzedDescription)->then(
                function ($positions) use ($performInsert) {
                    return $performInsert($positions);
                },
                function (\Throwable $e) use ($performInsert) {
                    IdealisticOfficeError::storeThrowable(
                        $this->getTeam(),
                        $this->user,
                        $e
                    );
                    return $performInsert(null);
                }
            );
        }
    }

    private function attachmentNotify(bool $notify, IdealisticOfficeAttachment $attachment): void
    {
        if ($notify) {
            $bytesLimit = IdealisticOfficeLimit::ATTACHMENT_BYTES_LIMIT[$this->getPlatformId()] ?? null;

            if (is_numeric($bytesLimit)) {
                $bytesLimit = min($bytesLimit, IdealisticOfficeLimit::MAX_ATTACHMENT_BYTES_SIZE);

                if ($attachment->getBytes() > $bytesLimit) {
                    $this->notify(
                        "A created attachment was not sent because it exceeds the platform's size limit."
                    );
                    return;
                }
            } else if (is_array($bytesLimit)) {
                $bytesLimit = $bytesLimit[IdealisticOfficeFileType::getTypeFromAttachment($attachment)] ?? null;

                if (is_numeric($bytesLimit)
                    && $attachment->getBytes() > $bytesLimit) {
                    $this->notify(
                        "A created attachment was not sent because it exceeds the platform's size limit for its type."
                    );
                    return;
                }
            }
        }
    }

}
