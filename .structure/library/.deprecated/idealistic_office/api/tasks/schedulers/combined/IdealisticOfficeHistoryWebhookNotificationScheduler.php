<?php

namespace idealistic_office\api\tasks\schedulers\combined;

use idealistic_office\api\handlers\IdealisticOfficeError;
use idealistic_office\api\handlers\IdealisticOfficeGmail;
use idealistic_office\api\handlers\IdealisticOfficeMeta;
use idealistic_office\api\handlers\IdealisticOfficeNotifications;
use idealistic_office\api\handlers\IdealisticOfficeWhatsApp;
use idealistic_office\api\IdealisticOfficeVariable;
use idealistic_office\api\objects\helpers\attachment\IdealisticOfficeAttachment;
use IdealisticOfficeAccessPlatform;
use PhpAsyncSerializable;

class IdealisticOfficeHistoryWebhookNotificationScheduler implements PhpAsyncSerializable
{

    public static function run(): bool
    {
        try {
            $notifications = IdealisticOfficeNotifications::retrieve(
                array(
                    IdealisticOfficeAccessPlatform::INSTAGRAM,
                    IdealisticOfficeAccessPlatform::MESSENGER,
                    IdealisticOfficeAccessPlatform::WHATSAPP,
                    IdealisticOfficeAccessPlatform::EMAIL,
                    IdealisticOfficeAccessPlatform::NATIVE
                )
            );
            $notificationCount = sizeof($notifications);

            if ($notificationCount > 0) {
                foreach ($notifications as $notification) {
                    $identity = $notification->getIdentity();

                    if ($identity === null) {
                        continue;
                    }
                    $platformID = $identity->getPlatformID();

                    if ($platformID !== IdealisticOfficeAccessPlatform::INSTAGRAM
                        && $platformID !== IdealisticOfficeAccessPlatform::MESSENGER
                        && $platformID !== IdealisticOfficeAccessPlatform::WHATSAPP
                        && $platformID !== IdealisticOfficeAccessPlatform::EMAIL
                        && $platformID !== IdealisticOfficeAccessPlatform::NATIVE) {
                        continue;
                    }
                    if ($notification->process()) {
                        $platformUserId = $identity->getPlatformUserID();

                        if ($notification->getAttachmentName() !== null
                            && $notification->getAttachmentContent() !== null
                            || $notification->hasMessage()) {
                            switch ($platformID) {
                                case IdealisticOfficeAccessPlatform::INSTAGRAM:
                                case IdealisticOfficeAccessPlatform::MESSENGER:
                                case IdealisticOfficeAccessPlatform::WHATSAPP:
                                    if ($notification->getAttachmentContent() === null) {
                                        switch ($platformID) {
                                            case IdealisticOfficeAccessPlatform::INSTAGRAM:
                                            case IdealisticOfficeAccessPlatform::MESSENGER:
                                                $runner = new IdealisticOfficeMeta(
                                                    null,
                                                    $platformID == IdealisticOfficeAccessPlatform::INSTAGRAM
                                                );
                                                break;
                                            default:
                                                $runner = new IdealisticOfficeWhatsApp(null);
                                                break;
                                        }

                                        if (!$runner->isValid()) {
                                            break;
                                        }
                                        $message = $notification->getMessage();
                                        $pieces = str_split(
                                            $message,
                                            IdealisticOfficeLimit::MESSAGE_CHARACTER_LIMIT[$platformID]
                                        );
                                        $sync = $notificationCount === 1
                                            || sizeof($pieces) > 1;

                                        foreach ($pieces as $piece) {
                                            if ($runner instanceof IdealisticOfficeWhatsApp) {
                                                $runner->sendMessage(
                                                    $platformUserId,
                                                    $piece,
                                                    null,
                                                    $sync
                                                );
                                            } else {
                                                $runner->sendMessage(
                                                    $platformUserId,
                                                    $piece,
                                                    $sync
                                                );
                                            }
                                        }
                                    } else {
                                        switch ($platformID) {
                                            case IdealisticOfficeAccessPlatform::INSTAGRAM:
                                            case IdealisticOfficeAccessPlatform::MESSENGER:
                                            case IdealisticOfficeAccessPlatform::WHATSAPP:
                                                switch ($platformID) {
                                                    case IdealisticOfficeAccessPlatform::INSTAGRAM:
                                                    case IdealisticOfficeAccessPlatform::MESSENGER:
                                                        $runner = new IdealisticOfficeMeta(
                                                            null,
                                                            $platformID == IdealisticOfficeAccessPlatform::INSTAGRAM
                                                        );
                                                        break;
                                                    default:
                                                        $runner = new IdealisticOfficeWhatsApp(null);
                                                        break;
                                                }

                                                if (!$runner->isValid()) {
                                                    break;
                                                }
                                                $url = $notification->getAttachmentURL();
                                                $content = $notification->getAttachmentContent();

                                                if ($url === null
                                                    && $content === null) {
                                                    break;
                                                }
                                                $message = $notification->getMessage();

                                                if ($message !== null) {
                                                    $pieces = str_split(
                                                        $message,
                                                        IdealisticOfficeLimit::MESSAGE_CHARACTER_LIMIT[$platformID]
                                                    );
                                                    $sync = $notificationCount === 1
                                                        || sizeof($pieces) > 1;
                                                    $firstPiece = array_shift($pieces);

                                                    if ($notification->isImage()
                                                        || $notification->isVideo()
                                                        || $notification->isAudio()) {
                                                        $type = $notification->isImage()
                                                            ? "image"
                                                            : ($notification->isVideo()
                                                                ? "video"
                                                                : "audio");

                                                        if ($runner instanceof IdealisticOfficeWhatsApp) {
                                                            $runner->sendTextAndAttachmentMessage(
                                                                $platformUserId,
                                                                $type,
                                                                $firstPiece,
                                                                $url,
                                                                $identity->getPlatformMessageID(),
                                                                $sync
                                                            );
                                                        } else {
                                                            $runner->sendTextAndAttachmentMessage(
                                                                $platformUserId,
                                                                $firstPiece,
                                                                $notification->getAttachmentName(),
                                                                $content,
                                                                $url,
                                                                $notification->getAttachmentFormat(),
                                                                $notification->isBase64(),
                                                                $notification->isVideo(),
                                                                $notification->isImage(),
                                                                $notification->isAudio(),
                                                                $sync
                                                            );
                                                        }
                                                    } else {
                                                        if ($runner instanceof IdealisticOfficeWhatsApp) {
                                                            $runner->sendTextAndAttachmentMessage(
                                                                $platformUserId,
                                                                "document",
                                                                $firstPiece,
                                                                $url,
                                                                $identity->getPlatformMessageID(),
                                                                $sync
                                                            );
                                                        } else if ($platformID === IdealisticOfficeAccessPlatform::MESSENGER) {
                                                            $runner->sendTextAndAttachmentMessage(
                                                                $platformUserId,
                                                                $firstPiece,
                                                                $notification->getAttachmentName(),
                                                                $content,
                                                                $url,
                                                                $notification->getAttachmentFormat(),
                                                                $notification->isBase64(),
                                                                $notification->isVideo(),
                                                                $notification->isImage(),
                                                                $notification->isAudio(),
                                                                $sync
                                                            );
                                                        } else { // Instagram
                                                            $runner->sendMessage(
                                                                $platformUserId,
                                                                $firstPiece
                                                                . IdealisticOfficeVariable::DEFAULT_TEXT_SEPARATOR
                                                                . $url,
                                                                $sync
                                                            );
                                                        }
                                                    }

                                                    if ($sync) {
                                                        foreach ($pieces as $piece) {
                                                            if ($runner instanceof IdealisticOfficeWhatsApp) {
                                                                $runner->sendMessage(
                                                                    $platformUserId,
                                                                    $piece,
                                                                    $identity->getPlatformMessageID(),
                                                                    true
                                                                );
                                                            } else {
                                                                $runner->sendMessage(
                                                                    $platformUserId,
                                                                    $piece,
                                                                    true
                                                                );
                                                            }
                                                        }
                                                    }
                                                } else {
                                                    $sync = $notificationCount === 1;

                                                    if ($notification->isImage()
                                                        || $notification->isVideo()
                                                        || $notification->isAudio()) {
                                                        $type = $notification->isImage()
                                                            ? "image"
                                                            : ($notification->isVideo()
                                                                ? "video"
                                                                : "audio");

                                                        if ($runner instanceof IdealisticOfficeWhatsApp) {
                                                            $runner->sendAttachmentMessage(
                                                                $platformUserId,
                                                                $type,
                                                                $url,
                                                                $identity->getPlatformMessageID()
                                                            );
                                                        } else {
                                                            $runner->sendAttachmentMessage(
                                                                $platformUserId,
                                                                $notification->getAttachmentName(),
                                                                $content,
                                                                $url,
                                                                $notification->getAttachmentFormat(),
                                                                $notification->isBase64(),
                                                                $notification->isVideo(),
                                                                $notification->isImage(),
                                                                $notification->isAudio()
                                                            );
                                                        }
                                                    } else {
                                                        if ($runner instanceof IdealisticOfficeWhatsApp) {
                                                            $runner->sendAttachmentMessage(
                                                                $platformUserId,
                                                                "document",
                                                                $url,
                                                                $identity->getPlatformMessageID()
                                                            );
                                                        } else if ($platformID === IdealisticOfficeAccessPlatform::MESSENGER) {
                                                            $runner->sendAttachmentMessage(
                                                                $platformUserId,
                                                                $notification->getAttachmentName(),
                                                                $content,
                                                                $url,
                                                                $notification->getAttachmentFormat(),
                                                                $notification->isBase64(),
                                                                $notification->isVideo(),
                                                                $notification->isImage(),
                                                                $notification->isAudio()
                                                            );
                                                        } else { // Instagram
                                                            $runner->sendMessage(
                                                                $platformUserId,
                                                                $url,
                                                                $sync
                                                            );
                                                        }
                                                    }
                                                }
                                                break;
                                            default:
                                                break;
                                        }
                                    }
                                    break;
                                case IdealisticOfficeAccessPlatform::EMAIL:
                                    $gmail = new IdealisticOfficeGmail();

                                    if ($notification->getAttachmentContent() !== null) {
                                        $attachment = new IdealisticOfficeAttachment(
                                            null,
                                            $notification->getAttachmentName(),
                                            $notification->getAttachmentFormat(),
                                            $notification->getAttachmentURL(),
                                            -1,
                                            null,
                                            null,
                                            null,
                                            $notification->getAttachmentContent(),
                                            null,
                                            true,
                                            $notification->getCreationDate(),
                                            $notification->getTimeZone(),
                                            $notification->getUser() ?? 0
                                        );
                                    } else {
                                        $attachment = null;
                                    }
                                    $message = $notification->getMessage();
                                    $gmail->send(
                                        $identity->getPlatformChatID(),
                                        $identity->getPlatformUserID(),
                                        $identity->getPlatformChatName(),
                                        $identity->getPlatformMessageID(),
                                        empty($message)
                                            ? ""
                                            : $message,
                                        $attachment === null
                                            ? array()
                                            : array($attachment),
                                    );
                                    break;
                                case IdealisticOfficeAccessPlatform::NATIVE:
                                    if ($notification->getUser()?->hasEmail()) {
                                        $message = $notification->getMessage();

                                        if ($message === null) {
                                            $message = $notification->getAttachmentURL();
                                        } else if ($notification->getAttachmentURL() !== null) {
                                            $message .= IdealisticOfficeVariable::DEFAULT_TEXT_SEPARATOR
                                                . $notification->getAttachmentURL();
                                        }

                                        if (!empty($message)) {
                                            IdealisticOfficeNotifications::sendEmail(
                                                $notification->getUser()->getEmail(),
                                                $message
                                            );
                                        }
                                    }
                                    break;
                                default:
                                    break;
                            }
                        }
                    }
                }
            }
            return true;
        } catch (Throwable $e) {
            IdealisticOfficeError::storeThrowable(
                null,
                null,
                $e
            );
        }
        return false;
    }

}
