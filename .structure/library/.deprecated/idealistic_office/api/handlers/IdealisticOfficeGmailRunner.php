<?php

namespace idealistic_office\api\handlers;

use idealistic_office\api\IdealisticOfficeVariable;
use idealistic_office\api\objects\helpers\attachment\AbstractIdealisticOfficeAttachment;
use idealistic_office\api\objects\helpers\attachment\IdealisticOfficeAttachment;
use idealistic_office\api\objects\helpers\IdealisticOfficeOutcome;
use IdealisticOfficeAccessPlatform;
use IdealisticOfficeGeneralMessage;
use PhpAsyncSerializable;

class IdealisticOfficeGmailRunner implements PhpAsyncSerializable
{

    public static function run(int|string|null $historyId = null): void
    {
        try {
            $gmail = new IdealisticOfficeGmail();
            $lastProcessedHistoryId = $gmail->getLastProcessedHistoryID();
            $historyResponse = $gmail->getService()->users_history->listUsersHistory(
                "me",
                $lastProcessedHistoryId === null
                    ? ($historyId === null
                    ? array()
                    : array("startHistoryId" => $historyId))
                    : array("startHistoryId" => $lastProcessedHistoryId)
            );
            $messages = array();
            $history = $historyResponse->getHistory();

            if (empty($history)) {
                return;
            }
            foreach ($history as $record) {
                if ($record->getMessages()) {
                    foreach ($record->getMessages() as $msg) {
                        if (!$gmail->isQueued($msg->getId())) {
                            $gmail->queue($msg->getId());
                            $messages[] = $msg->getId();
                        }
                    }
                }
            }

            if (empty($messages)) {
                return;
            }

            foreach ($messages as $msgId) {
                try {
                    $message = $gmail->getService()->users_messages->get(
                        "me",
                        $msgId,
                        array("format" => "full")
                    );
                    $payload = $message->getPayload();
                    $headers = $payload->getHeaders();
                    $subject = "";
                    $from = "";
                    $messageId = "";
                    $toHeader = "";

                    foreach ($headers as $h) {
                        $name = strtolower($h->getName());

                        if ($name === 'subject') {
                            $subject = $h->getValue();
                        } else if ($name === 'from') {
                            $from = $h->getValue();
                        } else if ($name === 'message-id') {
                            $messageId = $h->getValue();
                        } else if ($name === 'to'
                            || $name === 'delivered-to') {
                            $toHeader = $h->getValue();
                        }
                    }

                    if (!$gmail->isProcessed($message->getHistoryId(), $messageId)) {
                        $gmail->process($message->getHistoryId(), $messageId);

                        if (!str_contains($toHeader, IdealisticOfficeVariable::REPLY_EMAIL)
                            || str_contains($from, IdealisticOfficeVariable::EMAIL_DOMAIN)) {
                            continue;
                        }
                        if (preg_match('/^(?:"?([^"]*)"?\s)?<?(.+@[^>]+)>?$/', $from, $matches)) {
                            $displayName = trim($matches[1] ?? '');
                            $emailOnly = trim($matches[2] ?? '');
                        } else {
                            $displayName = null;
                            $emailOnly = trim($from);
                        }
                        $platformID = IdealisticOfficeAccessPlatform::EMAIL;
                        $threadId = $message->getThreadId();
                        $user = IdealisticOfficeTeamInitiator::findUser(
                            $platformID,
                            $emailOnly
                        );

                        if ($user instanceof IdealisticOfficeOutcome) {
                            $gmail->send(
                                $message->getThreadId(),
                                $from,
                                $subject,
                                $messageId,
                                $user->getTranslatedMessage()
                            );
                            continue;
                        }
                        $parts = $payload->getParts();
                        $attachments = array();
                        $bodyText = $gmail->extractMessageBody($payload);
                        $bodyText = $gmail->extractUserReply(
                            $bodyText["data"],
                            $bodyText["type"] === "text/html"
                        );

                        if (!empty($parts)) {
                            $timezone = $user->getTimezone(false);
                            foreach ($parts as $part) {
                                $filename = $part->getFilename();

                                if (!empty($filename)) {
                                    $type = AbstractIdealisticOfficeAttachment::getFormatFromName($filename);

                                    if ($type !== null) {
                                        $body = $part->getBody();
                                        $attachmentId = $body->getAttachmentId();

                                        if ($attachmentId !== null) {
                                            try {
                                                $attachment = $gmail->getService()->users_messages_attachments->get(
                                                    "me",
                                                    $msgId,
                                                    $attachmentId
                                                );
                                                $base64data = strtr($attachment->getData(), '-_', '+/');
                                                $attachments[] = new IdealisticOfficeAttachment(
                                                    null,
                                                    $filename,
                                                    $type,
                                                    null,
                                                    -1,
                                                    null,
                                                    null,
                                                    null,
                                                    $base64data,
                                                    null,
                                                    true,
                                                    IdealisticOfficeReader::getCurrentDate($timezone),
                                                    $timezone->getTimeZone(),
                                                    $user
                                                );
                                            } catch (Throwable $e) {
                                                IdealisticOfficeError::storeThrowable(
                                                    $user->getTeam(),
                                                    $user,
                                                    $e,
                                                    false
                                                );
                                                $gmail->send(
                                                    $message->getThreadId(),
                                                    $from,
                                                    $subject,
                                                    $messageId,
                                                    IdealisticOfficeGeneralMessage::ATTACHMENT_FAILED_PROCESSING . " (#628517924)"
                                                );
                                                continue 2;
                                            }
                                        } else {
                                            IdealisticOfficeError::storeString(
                                                $user->getTeam(),
                                                $user,
                                                IdealisticOfficeGeneralMessage::ATTACHMENT_FAILED_PROCESSING
                                                . " (File-Name: " . $filename . ", Reason: Attachment ID is null)",
                                                false
                                            );
                                            $gmail->send(
                                                $message->getThreadId(),
                                                $from,
                                                $subject,
                                                $messageId,
                                                IdealisticOfficeGeneralMessage::ATTACHMENT_FAILED_PROCESSING . " (#981204413)"
                                            );
                                            continue 2;
                                        }
                                    } else {
                                        IdealisticOfficeError::storeString(
                                            $user->getTeam(),
                                            $user,
                                            IdealisticOfficeGeneralMessage::ATTACHMENT_FAILED_PROCESSING
                                            . " (File-Name: " . $filename . ", Reason: Unsupported file type)",
                                            false
                                        );
                                        $gmail->send(
                                            $message->getThreadId(),
                                            $from,
                                            $subject,
                                            $messageId,
                                            IdealisticOfficeGeneralMessage::ATTACHMENT_FAILED_PROCESSING . " (#781429133)"
                                        );
                                        continue 2;
                                    }
                                }
                            }
                        }
                        $prompt = IdealisticOfficeTeamInitiator::createPrompt(
                            $user,
                            $platformID,
                            $emailOnly,
                            $threadId,
                            $subject,
                            $messageId,
                            null,
                            $displayName,
                            $bodyText,
                            $attachments
                        );

                        if ($prompt->isPositiveOutcome()) {
                            if (false) {
                                $gmail->send(
                                    $threadId,
                                    $from,
                                    $subject,
                                    $messageId,
                                    IdealisticOfficeStrings::translateMessage(
                                        IdealisticOfficeGeneralMessage::PROMPT_WAIT_RESPONSE,
                                        $user
                                    )
                                );
                            }
                        } else {
                            $gmail->send(
                                $threadId,
                                $from,
                                $subject,
                                $messageId,
                                $prompt->getTranslatedMessage()
                            );
                        }
                    }
                } catch (\Google\Service\Exception $e) {
                    if ($e->getCode() != 404) {
                        IdealisticOfficeError::storeThrowable(
                            null,
                            null,
                            $e,
                            false
                        );
                    }
                }
            }
        } catch (Throwable $e) {
            IdealisticOfficeError::storeThrowable(
                null,
                null,
                $e,
                false
            );
        }
    }

}
