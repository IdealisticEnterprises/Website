<?php

class IdealisticOfficeUserIdentity extends IdealisticOfficeJSON
{

    private string $creationDate, $timeZone;
    private ?string $userName, $displayName;
    private int $platformID;
    private int|string $id, $platformUserID;
    private int|string|null $platformChatID, $platformMessageId;
    private ?string $platformChatName, $lastUsedDate, $lastUsedTimeZone;

    public function __construct(
        int|string      $id,
        ?string         $userName,
        ?string         $displayName,
        int             $platformID,
        int|string      $platformUserID,
        int|string|null $platformChatID,
        ?string         $platformChatName,
        int|string|null $platformMessageId,
        string          $creationDate,
        string          $timeZone,
        ?string         $lastUsedDate,
        ?string         $lastUsedTimeZone,
        bool            $json = true
    )
    {
        parent::__construct();
        $this->id = $id;
        $this->userName = $userName;
        $this->displayName = $displayName;
        $this->platformID = $platformID;
        $this->platformUserID = $platformUserID;
        $this->platformChatID = $platformChatID;
        $this->platformChatName = $platformChatName;
        $this->platformMessageId = $platformMessageId;
        $this->creationDate = $creationDate;
        $this->timeZone = $timeZone;
        $this->lastUsedDate = $lastUsedDate;
        $this->lastUsedTimeZone = $lastUsedTimeZone;

        if ($json) {
            self::setJSON(
                array(
                    "id" => $this->id,
                    "user_name" => $this->userName,
                    "display_name" => $this->displayName,
                    "platform_id" => $this->platformID,
                    "platform_user_id" => $this->platformUserID,
                    "platform_chat_id" => $this->platformChatID,
                    "platform_chat_name" => $this->platformChatName,
                    "platform_message_id" => $this->platformMessageId,
                    "creation_date" => $this->creationDate,
                    "creation_time_zone" => $this->timeZone,
                    "last_used_date" => $this->lastUsedDate,
                    "last_used_time_zone" => $this->lastUsedTimeZone
                )
            );
        }
    }

    public function getId(): int|string
    {
        return $this->id;
    }

    public function getUserName(): ?string
    {
        return $this->userName;
    }

    public function getDisplayName(bool $backup = true): ?string
    {
        if ($backup && empty($this->displayName)) {
            return $this->userName;
        } else {
            return $this->displayName;
        }
    }

    public function getPlatformID(): int
    {
        return $this->platformID;
    }

    public function getPlatformUserID(): int|string
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
        return $this->platformMessageId;
    }

    public function getCreationDate(): string
    {
        return $this->creationDate;
    }

    public function getCreationDateTimeZone(): string
    {
        return $this->timeZone;
    }

    public function getLastUsedDate(): ?string
    {
        return $this->lastUsedDate;
    }

    public function getLastUsedDateTimeZone(): ?string
    {
        return $this->lastUsedTimeZone;
    }

}
