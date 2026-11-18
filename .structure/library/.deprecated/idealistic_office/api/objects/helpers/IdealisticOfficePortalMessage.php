<?php

namespace idealistic_office\api\objects\helpers;

use idealistic_office\api\handlers\IdealisticOfficeError;
use idealistic_office\api\handlers\IdealisticOfficeReader;
use idealistic_office\api\objects\helpers\attachment\IdealisticOfficeAttachment;
use IdealisticOfficePlans;
use IdealisticOfficeTable;
use PhpAsyncSerializable;

class IdealisticOfficePortalMessage implements PhpAsyncSerializable
{

    private int|string $id, $portalId;
    private int|string|null $serverId, $channelId, $channelThreadId, $messageId, $userId;
    private string $creationDate, $timeZone;
    private ?string $message;
    private ?IdealisticOfficeAttachment $attachment;
    private int $platformID;
    private bool $branding;

    public function __construct(
        int|string                  $id,
        int|string                  $portalId,
        int                         $platformID,
        int|string|null             $serverId,
        int|string|null             $channelId,
        int|string|null             $channelThreadId,
        int|string|null             $messageId,
        int|string|null             $userId,
        ?string                     $message,
        ?IdealisticOfficeAttachment $attachment,
        string                      $creationDate,
        string                      $timeZone,
        bool                        $branding = true
    )
    {
        $this->id = $id;
        $this->portalId = $portalId;
        $this->platformID = $platformID;
        $this->serverId = $serverId;
        $this->channelId = $channelId;
        $this->channelThreadId = $channelThreadId;
        $this->messageId = $messageId;
        $this->userId = $userId;
        $this->message = $message;
        $this->attachment = $attachment;
        $this->creationDate = $creationDate;
        $this->timeZone = $timeZone;
        $this->branding = $branding;
    }

    public function getId(): int|string
    {
        return $this->id;
    }

    public function getPortalId(): int|string
    {
        return $this->portalId;
    }

    public function getPlatformID(): int
    {
        return $this->platformID;
    }

    public function getServerId(): int|string|null
    {
        return $this->serverId;
    }

    public function getChannelId(): int|string|null
    {
        return $this->channelId;
    }

    public function getChannelThreadId(): int|string|null
    {
        return $this->channelThreadId;
    }

    // The message the reply answers, so platforms can quote or reply to it
    public function getMessageId(): int|string|null
    {
        return $this->messageId;
    }

    public function getUserId(): int|string|null
    {
        return $this->userId;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function hasMessage(): bool
    {
        return !empty($this->message);
    }

    public function showsBranding(): bool
    {
        return $this->branding;
    }

    public function getBrandedMessage(): ?string
    {
        return $this->branding && $this->hasMessage()
            ? $this->message . "\n\n" . IdealisticOfficePlans::POWERED_BY_TEXT
            : $this->message;
    }

    public function getCreationDate(): string
    {
        return $this->creationDate;
    }

    public function getTimeZone(): string
    {
        return $this->timeZone;
    }

    // Separator

    public function getAttachment(): ?IdealisticOfficeAttachment
    {
        return $this->attachment;
    }

    public function hasAttachment(): bool
    {
        return $this->attachment !== null;
    }

    // Separator

    // Marks the message as delivered, returns false if it could not be, in which case it must not be sent
    public function process(): bool
    {
        return IdealisticOfficeError::setSqlQuery(
            null,
            null,
            IdealisticOfficeTable::PORTAL_HISTORY_TABLE,
            array(
                "delivered_date" => IdealisticOfficeReader::getCurrentDate($this->timeZone)
            ),
            array(
                array("id", $this->id)
            ),
            null,
            1
        );
    }

}
