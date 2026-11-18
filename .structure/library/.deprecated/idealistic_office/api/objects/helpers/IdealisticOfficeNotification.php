<?php

namespace idealistic_office\api\objects\helpers;

use account\api\objects\account\Account;
use idealistic_office\api\handlers\IdealisticOfficeError;
use idealistic_office\api\handlers\IdealisticOfficeReader;
use idealistic_office\api\handlers\IdealisticOfficeStrings;
use idealistic_office\api\IdealisticOfficeVariable;
use idealistic_office\api\objects\helpers\user\IdealisticOfficeUserIdentity;
use idealistic_office\api\objects\IdealisticOfficePrompt;
use idealistic_office\api\objects\IdealisticOfficeUser;
use IdealisticOfficeTable;
use PhpAsyncSerializable;

class IdealisticOfficeNotification implements PhpAsyncSerializable
{

    private ?IdealisticOfficeUser $user;
    private int|string $id, $platformUserId;
    private int|string|null $platformChatId, $platformMessageId, $executor;
    private string $creationDate, $timeZone;
    private ?string $message, $attachmentName, $attachmentContent, $attachmentURL, $attachmentFormat, $platformChatName;
    private bool $base64, $isVideo, $isImage, $isAudio, $deleteOnDelivery;
    private ?bool $translateMessage;
    private int $platformID;

    public function __construct(
        ?IdealisticOfficeUser $user,
        int|string|null       $executor,
        int|string            $id,
        ?string               $message,
        string                $creationDate,
        string                $timeZone,
        int                   $platformID,
        int|string            $platformUserID,
        int|string|null       $platformChatID,
        ?string               $platformChatName,
        int|string|null       $platformMessageId,
        ?string               $attachmentName,
        ?string               $attachmentContent,
        ?string               $attachmentURL,
        ?string               $attachmentFormat,
        bool                  $base64,
        bool                  $isVideo,
        bool                  $isImage,
        bool                  $isAudio,
        bool                  $translateMessage,
        bool                  $deleteOnDelivery
    )
    {
        $this->user = $user;
        $this->id = $id;
        $this->executor = $executor;
        $this->message = $message;
        $this->creationDate = $creationDate;
        $this->timeZone = $timeZone;
        $this->attachmentName = $attachmentName;
        $this->attachmentContent = $attachmentContent;
        $this->attachmentURL = $attachmentURL;
        $this->attachmentFormat = $attachmentFormat;
        $this->base64 = $base64;
        $this->isVideo = $isVideo;
        $this->isImage = $isImage;
        $this->isAudio = $isAudio;
        $this->translateMessage = $translateMessage;
        $this->deleteOnDelivery = $deleteOnDelivery;
        $this->platformID = $platformID;
        $this->platformUserId = $platformUserID;
        $this->platformChatId = $platformChatID;
        $this->platformChatName = $platformChatName;
        $this->platformMessageId = $platformMessageId;
    }

    public function getId(): int|string
    {
        return $this->id;
    }

    public function getUser(): ?IdealisticOfficeUser
    {
        return $this->user;
    }

    public function getIdentity(bool $json = false): ?IdealisticOfficeUserIdentity
    {
        $lastIdentity = $this->user?->getLastIdentity(null, $json);

        if ($lastIdentity !== null) {
            return $lastIdentity;
        } else {
            return new IdealisticOfficeUserIdentity(
                0,
                null,
                null,
                $this->platformID,
                $this->platformUserId,
                $this->platformChatId,
                $this->platformChatName,
                $this->platformMessageId,
                $this->creationDate,
                $this->timeZone,
                null,
                null,
                $json
            );
        }
    }

    public function getExecutor(bool $json = true): ?IdealisticOfficeUser
    {
        $account = $this->getExecutorAccount();

        if ($account === null) {
            return null;
        }
        $user = AbstractIdealisticOfficeTeam::findUserDynamic(
            $account,
            $json
        );

        if ($user instanceof IdealisticOfficeUser) {
            return $user;
        } else {
            return null;
        }
    }

    public function getExecutorAccount(): ?Account
    {
        return $this->executor === null
            ? null
            : new Account(
                Account::IDEALISTIC_OFFICE_APPLICATION_ID,
                $this->executor
            );
    }

    public function deleteOnDelivery(): bool
    {
        return $this->deleteOnDelivery;
    }

    public function isMessageTranslated(): bool
    {
        return $this->translateMessage === true
            || $this->translateMessage === null;
    }

    public function getMessage(?IdealisticOfficePrompt $prompt = null): ?string
    {
        if ($this->message === null) {
            return null;
        }
        if ($this->translateMessage === true) {
            $this->message = IdealisticOfficeStrings::translateMessage(
                $this->message,
                $this->user,
                false,
                $prompt
            );
            $this->translateMessage = null;
        }
        return $this->message;
    }

    public function getRawMessage(): ?string
    {
        return $this->message;
    }

    public function hasMessage(): bool
    {
        return $this->message !== null;
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

    public function getAttachmentName(): ?string
    {
        return $this->attachmentName;
    }

    public function getAttachmentContent(): ?string
    {
        return $this->attachmentContent;
    }

    public function getAttachmentURL(): ?string
    {
        return $this->attachmentURL;
    }

    public function getAttachmentFormat(): ?string
    {
        return $this->attachmentFormat;
    }

    public function isBase64(): bool
    {
        return $this->base64;
    }

    public function isVideo(): bool
    {
        return $this->isVideo;
    }

    public function isImage(): bool
    {
        return $this->isImage;
    }

    public function isAudio(): bool
    {
        return $this->isAudio;
    }

    // Separator

    public function process(): bool
    {
        $id = $this->id;
        $delete = function () use ($id): mixed {
            return IdealisticOfficeError::deleteSqlQuery(
                $this->user?->getTeam(),
                $this->user,
                IdealisticOfficeTable::NOTIFICATIONS_TABLE,
                array(
                    array("id", $id)
                ),
                null,
                1
            );
        };

        if ($this->user !== null) {
            $executor = $this->getExecutor(false);

            if ($executor !== null) {
                if ($executor->getId() === $this->user->getId()) {
                    $delete();
                    return false;
                }
                $generalAccess = $this->user->getGeneralizedAccess($executor->getTeam());

                if (!$generalAccess->isPositiveOutcome()) {
                    $delete();
                    return false;
                }
                $generalAccess = $executor->getGeneralizedAccess($this->user->getTeam());

                if (!$generalAccess->isPositiveOutcome()) {
                    $delete();
                    return false;
                }
            }
            $notifications = $this->user->getNotifications(true);

            if (!empty($notifications)) {
                $pastTime = "1 hour";

                foreach ($notifications as $notification) {
                    if ($notification->getCreationDate() >= IdealisticOfficeReader::getPastDate($pastTime, $notification->getTimeZone())
                        && $notification->getRawMessage() === $this->getRawMessage()
                        && $notification->getAttachmentURL() === $this->getAttachmentURL()
                        && $notification->getAttachmentName() === $this->getAttachmentName()
                        && $notification->getAttachmentFormat() === $this->getAttachmentFormat()
                        && $notification->getAttachmentContent() === $this->getAttachmentContent()) {
                        return $delete();
                    }
                }
            }
        }
        if ($this->deleteOnDelivery) {
            return $delete();
        } else {
            $timezone = $this->user === null
                ? IdealisticOfficeVariable::DEFAULT_TIME_ZONE
                : $this->user->getTimeZone(false)->getTimeZone();
            return IdealisticOfficeError::setSqlQuery(
                $this->user?->getTeam(),
                $this->user,
                IdealisticOfficeTable::NOTIFICATIONS_TABLE,
                array(
                    "delivered_date" => IdealisticOfficeReader::getCurrentDate($timezone),
                    "delivered_time_zone" => $timezone
                ),
                array(
                    array("id", $this->id)
                ),
                null,
                1
            );
        }
    }

}
