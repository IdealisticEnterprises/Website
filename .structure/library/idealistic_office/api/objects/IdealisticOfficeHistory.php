<?php

class IdealisticOfficeHistory implements PhpAsyncSerializable
{

    private AbstractIdealisticOfficeTeam $team;
    private int $platformId;
    private int|string $id, $platformUserId;
    private int|string|null $platformChatID, $platformMessageId;
    private Account|int $account;
    private string $creationDate, $timeZone;
    private ?bool $processing;
    private ?string $platformChatName;

    public function __construct(
        AbstractIdealisticOfficeTeam $team,
        int|string                   $id,
        int                          $platformId,
        int|string                   $platformUserId,
        int|string|null              $platformMessageId,
        int|string|null              $platformChatID,
        ?string                      $platformChatName,
        Account|int                  $account,
        string                       $creationDate,
        string                       $timeZone,
        ?bool                        $processing = null
    )
    {
        $this->team = $team;
        $this->id = $id;
        $this->platformId = $platformId;
        $this->platformUserId = $platformUserId;
        $this->platformMessageId = $platformMessageId;
        $this->platformChatID = $platformChatID;
        $this->platformChatName = $platformChatName;
        $this->account = $account;
        $this->creationDate = $creationDate;
        $this->timeZone = $timeZone;
        $this->processing = $processing;
    }

    public function getTeam(): AbstractIdealisticOfficeTeam
    {
        return $this->team;
    }

    public function setTeam(AbstractIdealisticOfficeTeam $team): void
    {
        $this->team = $team;
    }

    public function getId(): int|string
    {
        return $this->id;
    }

    public function setId(int|string $id): void
    {
        $this->id = $id;
    }

    public function getPlatformId(): int
    {
        return $this->platformId;
    }

    public function getPlatformUserId(): int|string
    {
        return $this->platformUserId;
    }

    public function getPlatformMessageId(): int|string|null
    {
        return $this->platformMessageId;
    }

    public function getPlatformChatID(): int|string|null
    {
        return $this->platformChatID;
    }

    public function getPlatformChatName(): ?string
    {
        return $this->platformChatName;
    }

    public function isProcessing(): bool
    {
        if ($this->processing !== null) {
            $bool = $this->processing;

            if ($this->processing) {
                $this->processing = null;
            }
        } else {
            $bool = IdealisticOfficeTeamInitiator::isPromptProcessing($this);

            if (!$bool) {
                $this->processing = false;
            }
        }
        return $bool;
    }

    public function setProcessing(bool $processing): void
    {
        $this->processing = $processing;
    }

    public function getAccount(): Account
    {
        if (is_numeric($this->account)) {
            $this->account = new Account(
                Account::IDEALISTIC_OFFICE_APPLICATION_ID,
                $this->account
            );
        }
        return $this->account;
    }

    public function getAccountID(): int|string
    {
        if (is_numeric($this->account)) {
            return $this->account;
        } else {
            return $this->account->getDetail("id", 0);
        }
    }

    public function setAccount(Account|int $account): void
    {
        $this->account = $account;
    }

    public function getUser(bool $json = true): IdealisticOfficeUser|IdealisticOfficeOutcome
    {
        return $this->team->findUser($this->getAccount(), $json);
    }

    public function getCreationDate(): string
    {
        return $this->creationDate;
    }

    public function getCreationDateTimeZone(): string
    {
        return $this->timeZone;
    }

    // Separator

    public function getWebData(?array $list = null): ?IdealisticOfficeHistoryWebData
    {
        if ($list === null) {
            $query = get_sql_query(
                IdealisticOfficeTable::HISTORY_WEB_DATA_TABLE,
                IdealisticOfficeQuery::WEB_DATA,
                array(
                    array("history_id", $this->id)
                ),
                null,
                1
            );
        } else {
            $query = $list[$this->id] ?? array();
        }

        if (!empty($query)) {
            return new IdealisticOfficeHistoryWebData(
                $query[0]->query,
                $query[0]->results
            );
        } else {
            return null;
        }
    }

    // Separator

    public function getPrompts(
        ?array $list = null,
        bool   $positions = false
    ): array
    {
        return IdealisticOfficeHistoryIndependent::getPrompts($this->id, $positions, $list);
    }

    public function getPromptEmbedding(
        ?array $list = null
    ): ?string
    {
        $prompts = $this->getPrompts($list, true);

        if (!empty($prompts)) {
            return array_shift($prompts);
        }
        return null;
    }

    public function getPromptEmbeddingArray(
        ?array $list = null
    ): ?array
    {
        $prompts = $this->getPrompts($list, true);

        if (!empty($prompts)) {
            $prompt = array_shift($prompts);
            $result = unpack("f*", $prompt);

            if (is_array($result)) {
                return array_values($result);
            }
        }
        return null;
    }

    public function hasLinks(?bool $web = null): bool
    {
        return !empty(get_sql_query(
            IdealisticOfficeTable::HISTORY_LINKS_TABLE,
            array("id"),
            array(
                array("history_id", $this->id),
                $web === null ? "" : array("web_search", $web)
            ),
            null,
            1
        ));
    }

    /** @return IdealisticOfficeLink[] */
    public function getLinks(?array $list = null, ?bool $web = null, bool $json = true): array
    {
        if ($list === null) {
            $query = get_sql_query(
                IdealisticOfficeTable::HISTORY_LINKS_TABLE,
                IdealisticOfficeQuery::HISTORY_LINK,
                array(
                    array("history_id", $this->id),
                    $web === null ? "" : array("web_search", $web)
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

    public function getLinkCount(?bool $web = null, ?array $list = null): int
    {
        return IdealisticOfficeReader::getLinkCount(
            $this->id,
            $web,
            IdealisticOfficeTable::HISTORY_LINKS_TABLE,
            $list
        );
    }

    /** @return IdealisticOfficeLink[] */
    public function getPartialLinks(?array $list = null, ?bool $web = null, bool $json = true): array
    {
        if ($list === null) {
            $query = get_sql_query(
                IdealisticOfficeTable::HISTORY_LINKS_TABLE,
                IdealisticOfficeQuery::HISTORY_LINK_PARTIAL,
                array(
                    array("history_id", $this->id),
                    $web === null ? "" : array("web_search", $web)
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

    public function getReplyEmbedding(
        string $taskID,
        ?array $list = null
    ): ?string
    {
        $reply = $this->getReplies($taskID, $list);

        if (empty($reply)) {
            return null;
        } else {
            return array_shift($reply);
        }
    }

    public function getReplyEmbeddingArray(
        string $taskID,
        ?array $list = null
    ): ?array
    {
        $reply = $this->getReplies($taskID, $list);

        if (empty($reply)) {
            return null;
        } else {
            $reply = array_shift($reply);
            return $reply->getPositionsArray();
        }
    }

    /** @return IdealisticOfficeHistoryReply[] */
    public function getReplies(
        ?string $taskID = null,
        ?array  $list = null
    ): array
    {
        if ($list === null) {
            $query = get_sql_query(
                IdealisticOfficeTable::HISTORY_REPLIES_TABLE,
                IdealisticOfficeQuery::HISTORY_REPLY,
                array(
                    array("history_id", $this->id),
                    $taskID !== null ? array("task_id", $taskID) : ""
                ),
                null,
                sizeof(IdealisticOfficePromptTask::TASKS_LIST)
            );
        } else {
            $query = array_slice(
                $list[$this->id] ?? array(),
                0,
                sizeof(IdealisticOfficePromptTask::TASKS_LIST)
            );
        }

        if (!empty($query)) {
            foreach ($query as $key => $row) {
                $query[$key] = new IdealisticOfficeHistoryReply(
                    $this->id,
                    $row->history_value,
                    $row->task_id,
                    $row->history_value_positions
                );
            }
            return $query;
        } else {
            return array();
        }
    }

    public function replied(
        ?string $taskID = null,
        ?array  $list = null,
        bool    $checkForStop = false
    ): bool
    {
        if ($list === null) {
            $query = get_sql_query(
                IdealisticOfficeTable::HISTORY_REPLIES_TABLE,
                array(
                    "history_id",
                ),
                array(
                    array("history_id", $this->id),
                    $checkForStop ? array("is_stop", true) : "",
                    $taskID === null ? "" : array("task_id", $taskID)
                ),
                null,
                1
            );
        } else {
            $query = $list[$this->id] ?? array();
        }
        return !empty($query);
    }

    public function failed(
        ?string $taskID = null,
        ?array  $list = null
    ): bool
    {
        return $this->replied(
            $taskID,
            $list,
            true
        );
    }

    public function addFinalFailedReply(?IdealisticOfficeUser $user = null): ?string
    {
        if ($user === null) {
            $user = $this->getUser(false);
        }
        $message = IdealisticOfficeStrings::translateMessage(
            IdealisticOfficeGeneralMessage::PROMPT_FAILED_PROCESSING,
            $user instanceof IdealisticOfficeOutcome
                ? $this->getTeam()
                : $user
        );

        if ($this->failed()) {
            return null;
        } else {
            IdealisticOfficeError::sqlInsert(
                $this->getTeam(),
                $user instanceof IdealisticOfficeUser ? $user : null,
                IdealisticOfficeTable::HISTORY_REPLIES_TABLE,
                array(
                    "task_id" => IdealisticOfficePromptTask::ACCESS_CHECK,
                    "history_id" => $this->id,
                    "history_value" => $message
                )
            );
            /*$this->deleteProcessingTimes(
                $user instanceof IdealisticOfficeUser ? $user : null
            );*/
            IdealisticOfficeError::storeString(
                $this->getTeam(),
                $user instanceof IdealisticOfficeUser ? $user : null,
                $message . " (ID: " . $this->id . ")"
            );
            return $message;
        }
    }

    public function silentlyFail(): void
    {
        $user = $this->getUser(false);
        IdealisticOfficeError::sqlInsert(
            $this->getTeam(),
            $user instanceof IdealisticOfficeUser ? $user : null,
            IdealisticOfficeTable::HISTORY_REPLIES_TABLE,
            array(
                "history_id" => $this->id,
                "task_id" => IdealisticOfficePromptTask::ACCESS_CHECK,
                "is_stop" => true
            )
        );
    }

    // Separator

    public function hasAttachments(?string $taskID = null, ?array $list = null): bool
    {
        return IdealisticOfficeReader::hasAttachments(
            $this->id,
            $taskID,
            IdealisticOfficeTable::HISTORY_USER_ATTACHMENTS_TABLE,
            $list
        );
    }

    public function getAttachmentCount(?string $taskID = null, ?array $list = null): int
    {
        return IdealisticOfficeReader::getAttachmentCount(
            $this->id,
            $taskID,
            IdealisticOfficeTable::HISTORY_USER_ATTACHMENTS_TABLE,
            $list
        );
    }

    /** @return IdealisticOfficePartialAttachment[] */
    public function getPartialAttachments(?string $taskID = null, ?array $list = null, bool $json = true): array
    {
        if ($list === null) {
            $query = get_sql_query(
                IdealisticOfficeTable::HISTORY_USER_ATTACHMENTS_TABLE,
                IdealisticOfficeQuery::PARTIAL_ATTACHMENT,
                array(
                    array("history_id", $this->id),
                    $taskID === null ? "" : array("task_id", $taskID)
                ),
                null,
                IdealisticOfficeLimit::MAX_EXTENSIONS_PER_OBJECT
            );
        } else {
            $query = array_slice($list[$this->id] ?? array(), 0, IdealisticOfficeLimit::MAX_EXTENSIONS_PER_OBJECT);
        }
        return IdealisticOfficeReader::getPartialAttachments($query, $json);
    }

    /** @return IdealisticOfficeAttachment[] */
    public function getAttachments(?string $taskID = null, ?array $list = null, bool $json = true): array
    {
        if ($list === null) {
            $query = get_sql_query(
                IdealisticOfficeTable::HISTORY_USER_ATTACHMENTS_TABLE,
                IdealisticOfficeQuery::ATTACHMENT,
                array(
                    array("history_id", $this->id),
                    $taskID === null ? "" : array("task_id", $taskID)
                ),
                null,
                IdealisticOfficeLimit::MAX_EXTENSIONS_PER_OBJECT
            );
        } else {
            $query = array_slice($list[$this->id] ?? array(), 0, IdealisticOfficeLimit::MAX_EXTENSIONS_PER_OBJECT);
        }
        return IdealisticOfficeReader::getAttachments($query, $json);
    }

    // Separator

    public function hasCreatedAttachments(?string $taskID = null, ?array $list = null): int
    {
        return IdealisticOfficeReader::hasAttachments(
            $this->id,
            $taskID,
            IdealisticOfficeTable::HISTORY_CREATED_ATTACHMENTS_TABLE,
            $list
        );
    }

    public function getCreatedAttachmentCount(?string $taskID = null, ?array $list = null): int
    {
        return IdealisticOfficeReader::getAttachmentCount(
            $this->id,
            $taskID,
            IdealisticOfficeTable::HISTORY_CREATED_ATTACHMENTS_TABLE,
            $list
        );
    }

    /** @return IdealisticOfficePartialAttachment[] */
    public function getCreatedPartialAttachments(
        ?string $taskID = null,
        ?array  $list = null,
        bool    $json = true
    ): array
    {
        if ($list === null) {
            $query = get_sql_query(
                IdealisticOfficeTable::HISTORY_CREATED_ATTACHMENTS_TABLE,
                IdealisticOfficeQuery::CREATED_PARTIAL_ATTACHMENT,
                array(
                    array("history_id", $this->id),
                    $taskID === null ? "" : array("task_id", $taskID)
                ),
                null,
                IdealisticOfficeLimit::MAX_EXTENSIONS_PER_OBJECT
            );
        } else {
            $query = array_slice($list[$this->id] ?? array(), 0, IdealisticOfficeLimit::MAX_EXTENSIONS_PER_OBJECT);
        }
        return IdealisticOfficeReader::getCreatedPartialAttachments($query, $json);
    }

    /** @return IdealisticOfficeAttachment[] */
    public function getCreatedAttachments(
        ?string $taskID = null,
        ?array  $list = null,
        bool    $json = true
    ): array
    {
        if ($list === null) {
            $query = get_sql_query(
                IdealisticOfficeTable::HISTORY_CREATED_ATTACHMENTS_TABLE,
                IdealisticOfficeQuery::CREATED_ATTACHMENT,
                array(
                    array("history_id", $this->id),
                    $taskID === null ? "" : array("task_id", $taskID)
                ),
                null,
                IdealisticOfficeLimit::MAX_EXTENSIONS_PER_OBJECT
            );
        } else {
            $query = array_slice($list[$this->id] ?? array(), 0, IdealisticOfficeLimit::MAX_EXTENSIONS_PER_OBJECT);
        }
        return IdealisticOfficeReader::getCreatedAttachments($query, $json);
    }

}
