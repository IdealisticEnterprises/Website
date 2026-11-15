<?php

class IdealisticOfficeMeta implements PhpAsyncSerializable
{

    public static function utf8Encode(string $string): ?string
    {
        $cleaned = @iconv('UTF-8', 'UTF-8//IGNORE', $string);

        if ($cleaned !== false) {
            return $cleaned;
        }
        $fallback = @mb_convert_encoding($string, 'UTF-8', 'UTF-8');

        if ($fallback !== false) {
            return $fallback;
        }
        return null;
    }

    private const
        FACEBOOK_ID = 204259806112236,
        INSTAGRAM_ID = 17841457289683976;
    public const API_VERSION = "v23.0";

    private string $accessToken;
    private array $userCache;
    private bool $instagram;
    private ?string $appSecret;

    public function __construct(?string $accessToken, bool $instagram, ?string $appSecret = null)
    {
        if ($accessToken === null) {
            $accessToken = get_keys_from_file(
                $instagram
                    ? "instagram_credentials"
                    : "messenger_credentials",
                2
            );

            if ($accessToken === null) {
                $this->accessToken = "";
            } else {
                $this->accessToken = $accessToken[0];
            }
        } else {
            $this->accessToken = $accessToken;
        }
        if ($appSecret === null) {
            $appSecretKeys = get_keys_from_file(
                $instagram
                    ? "instagram_app_secret"
                    : "messenger_app_secret",
                1
            );
            $this->appSecret = $appSecretKeys[0] ?? null;
        } else {
            $this->appSecret = $appSecret;
        }
        $this->userCache = array();
        $this->instagram = $instagram;
    }

    public function isValid(): bool
    {
        return !empty($this->accessToken);
    }

    public function sendMessage(
        string $recipientId,
        string $messageText,
        bool   $sync = false
    ): void
    {
        if (!$this->isValid()) {
            IdealisticOfficeError::simpleStore(
                __METHOD__ . ": Access token is not valid."
            );
            return;
        }
        if ($sync) {
            self::sendMessageWithToken(
                $recipientId,
                $messageText,
                $this->accessToken ?? ""
            );
        } else {
            IdealisticOfficeAsync::run(
                array(
                    "IdealisticOfficeMeta",
                    "sendMessageWithToken"
                ),
                array(
                    $recipientId,
                    $messageText,
                    $this->accessToken ?? ""
                )
            );
        }
    }

    public static function sendMessageWithToken(
        string $recipientId,
        string $messageText,
        string $accessToken
    ): mixed
    {
        $messageText = self::utf8Encode($messageText);

        if ($messageText === null) {
            IdealisticOfficeError::simpleStore("Message text is not valid UTF-8.");
            return null;
        }
        $url = "https://graph.facebook.com/" . self::API_VERSION . "/me/messages?access_token=" . $accessToken;
        $payload = [
            "recipient" => ["id" => $recipientId],
            "message" => ["text" => $messageText]
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            IdealisticOfficeError::simpleStore(curl_error($ch));
            IdealisticOfficeError::simpleStore($payload);
            IdealisticOfficeError::simpleStore(array(
                "recipientId" => $recipientId,
                "messageText" => $messageText
            ));
        }
        curl_close($ch);

        $data = json_decode((string)$response, true);

        if (is_array($data) && array_key_exists("error", $data)) {
            IdealisticOfficeError::simpleStore($data);
            IdealisticOfficeError::simpleStore($payload);
            IdealisticOfficeError::simpleStore(array(
                "recipientId" => $recipientId,
                "messageText" => $messageText
            ));
        }
        return $data;
    }

    public function sendAttachmentMessage(
        string  $recipientId,
        ?string $attachmentName,
        ?string $attachmentContent,
        ?string $attachmentURL,
        ?string $attachmentFormat,
        bool    $isBase64,
        bool    $isVideo,
        bool    $isImage,
        bool    $isAudio
    ): void
    {
        if (!$this->isValid()) {
            IdealisticOfficeError::simpleStore(
                __METHOD__ . ": Access token is not valid."
            );
            return;
        }
        IdealisticOfficeAsync::run(
            array(
                "IdealisticOfficeMeta",
                "sendAttachmentMessageWithToken"
            ),
            array(
                $recipientId,
                $attachmentName,
                $attachmentContent,
                $attachmentURL,
                $attachmentFormat,
                $isBase64,
                $isVideo,
                $isImage,
                $isAudio,
                $this->accessToken ?? ""
            )
        );
    }

    public static function sendAttachmentMessageWithToken(
        string  $recipientId,
        ?string $attachmentName,
        ?string $attachmentContent,
        ?string $attachmentURL,
        ?string $attachmentFormat,
        bool    $isBase64,
        bool    $isVideo,
        bool    $isImage,
        bool    $isAudio,
        string  $accessToken
    ): mixed
    {
        $url = "https://graph.facebook.com/" . self::API_VERSION . "/me/messages?access_token=" . $accessToken;

        $type = 'file';
        if ($isImage) {
            $type = 'image';
        } elseif ($isVideo) {
            $type = 'video';
        } elseif ($isAudio) {
            $type = 'audio';
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        if (!empty($attachmentContent)) {
            $fileData = $isBase64 ? base64_decode($attachmentContent) : $attachmentContent;

            if ($fileData === false) {
                IdealisticOfficeError::simpleStore("Failed to decode base64 attachment data.");
                return ["error" => ["message" => "Base64 decode failed."]];
            }

            $fileName = $attachmentName ?? 'attachment_' . uniqid();
            $fileFormat = $attachmentFormat ?? 'application/octet-stream';

            $payload = [
                "recipient" => json_encode(["id" => $recipientId]),
                "message" => json_encode([
                    "attachment" => [
                        "type" => $type,
                        "payload" => ["is_reusable" => true]
                    ]
                ])
            ];

            if (class_exists('CURLStringFile')) {
                $payload["filedata"] = new \CURLStringFile($fileData, $fileName, $fileFormat);
            } else {
                $tempFile = tempnam(sys_get_temp_dir(), 'meta_fb_');
                file_put_contents($tempFile, $fileData);
                $payload["filedata"] = new \CURLFile($tempFile, $fileFormat, $fileName);
            }

            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        } elseif (!empty($attachmentURL)) {
            $payload = [
                "recipient" => ["id" => $recipientId],
                "message" => [
                    "attachment" => [
                        "type" => $type,
                        "payload" => [
                            "url" => $attachmentURL,
                            "is_reusable" => true
                        ]
                    ]
                ]
            ];

            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
        } else {
            IdealisticOfficeError::simpleStore("No valid attachment source provided.");
            return ["error" => ["message" => "Missing attachment content and URL."]];
        }

        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            IdealisticOfficeError::simpleStore(curl_error($ch));
            IdealisticOfficeError::simpleStore($payload);
            IdealisticOfficeError::simpleStore(array(
                "recipientId" => $recipientId,
                "type" => $type,
                "attachmentName" => $attachmentName,
                "attachmentUrl" => $attachmentURL
            ));
        }
        curl_close($ch);

        if (isset($tempFile) && file_exists($tempFile)) {
            unlink($tempFile);
        }

        $data = json_decode($response, true);

        if (is_array($data) && array_key_exists("error", $data)) {
            IdealisticOfficeError::simpleStore($data);
            IdealisticOfficeError::simpleStore($payload);
            IdealisticOfficeError::simpleStore(array(
                "recipientId" => $recipientId,
                "type" => $type,
                "attachmentName" => $attachmentName,
                "attachmentUrl" => $attachmentURL
            ));
        }

        return $data;
    }

    public function sendTextAndAttachmentMessage(
        string  $recipientPhoneNumber,
        ?string $messageText,
        ?string $attachmentName,
        ?string $attachmentContent,
        ?string $attachmentURL,
        ?string $attachmentFormat,
        bool    $isBase64,
        bool    $isVideo,
        bool    $isImage,
        bool    $isAudio,
        bool    $sync = false
    ): void
    {
        if (!$this->isValid()) {
            IdealisticOfficeError::simpleStore(
                __METHOD__ . ": Access token is not valid."
            );
            return;
        }
        if ($sync) {
            self::sendTextAndAttachmentMessageWithToken(
                $recipientPhoneNumber,
                $messageText,
                $attachmentName,
                $attachmentContent,
                $attachmentURL,
                $attachmentFormat,
                $isBase64,
                $isVideo,
                $isImage,
                $isAudio,
                $this->accessToken ?? ""
            );
        } else {
            IdealisticOfficeAsync::run(
                array(
                    "IdealisticOfficeMeta",
                    "sendTextAndAttachmentMessageWithToken"
                ),
                array(
                    $recipientPhoneNumber,
                    $messageText,
                    $attachmentName,
                    $attachmentContent,
                    $attachmentURL,
                    $attachmentFormat,
                    $isBase64,
                    $isVideo,
                    $isImage,
                    $isAudio,
                    $this->accessToken ?? ""
                )
            );
        }
    }

    public static function sendTextAndAttachmentMessageWithToken(
        string  $recipientPhoneNumber,
        ?string $messageText,
        ?string $attachmentName,
        ?string $attachmentContent,
        ?string $attachmentURL,
        ?string $attachmentFormat,
        bool    $isBase64,
        bool    $isVideo,
        bool    $isImage,
        bool    $isAudio,
        string  $accessToken = ""
    ): mixed
    {
        if (!empty($messageText)) {
            self::sendMessageWithToken(
                $recipientPhoneNumber,
                $messageText,
                $accessToken
            );
        }
        return self::sendAttachmentMessageWithToken(
            $recipientPhoneNumber,
            $attachmentName,
            $attachmentContent,
            $attachmentURL,
            $attachmentFormat,
            $isBase64,
            $isVideo,
            $isImage,
            $isAudio,
            $accessToken
        );
    }

    private function getUserInfo(string|int $senderID): array
    {
        if ($this->instagram) {
            return array();
        }
        if (array_key_exists($senderID, $this->userCache)) {
            return $this->userCache[$senderID];
        }
        $url = "https://graph.facebook.com/" . self::API_VERSION . "/" . $senderID . "?fields=first_name,last_name&access_token=" . $this->accessToken;

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            IdealisticOfficeError::simpleStore(curl_error($ch));
            IdealisticOfficeError::simpleStore(array(
                "senderID" => $senderID
            ));
            curl_close($ch);
            return $this->userCache[$senderID] = array();
        }
        curl_close($ch);

        $data = json_decode((string)$response, true);

        if (!is_array($data)) {
            return $this->userCache[$senderID] = array();
        }
        if (array_key_exists("error", $data)) {
            IdealisticOfficeError::simpleStore($data);
            IdealisticOfficeError::simpleStore(array(
                "senderID" => $senderID
            ));
            return $this->userCache[$senderID] = array();
        }
        return $this->userCache[$senderID] = $data;
    }

    public function sendTypingAction(string $recipientId, bool $isTyping = true, bool $sync = false): void
    {
        if (!$this->isValid()) {
            IdealisticOfficeError::simpleStore(
                __METHOD__ . ": Access token is not valid."
            );
            return;
        }

        $action = $isTyping ? "typing_on" : "typing_off";

        if ($sync) {
            self::sendSenderActionWithToken(
                $recipientId,
                $action,
                $this->accessToken ?? ""
            );
        } else {
            IdealisticOfficeAsync::run(
                array(
                    "IdealisticOfficeMeta",
                    "sendSenderActionWithToken"
                ),
                array(
                    $recipientId,
                    $action,
                    $this->accessToken ?? ""
                )
            );
        }
    }

    public static function sendSenderActionWithToken(
        string $recipientId,
        string $action,
        string $accessToken
    ): mixed
    {
        $url = "https://graph.facebook.com/" . self::API_VERSION . "/me/messages?access_token=" . $accessToken;

        $payload = [
            "recipient" => ["id" => $recipientId],
            "sender_action" => $action
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            IdealisticOfficeError::simpleStore(curl_error($ch));
            IdealisticOfficeError::simpleStore($payload);
        }
        curl_close($ch);

        $data = json_decode($response, true);

        if (isset($data["error"])) {
            IdealisticOfficeError::simpleStore($data);
            IdealisticOfficeError::simpleStore($payload);
        }

        return $data;
    }

    // Returns false when the request's signature does not check out (caller should respond
    // with an error status and must not treat the payload as genuine); true otherwise.
    public function handleMessage(): bool
    {
        $payload = timed_file_get_contents("php://input", IdealisticOfficeLimit::LINK_DOWNLOAD_SECONDS_TIMEOUT);

        if (empty($this->appSecret)) {
            IdealisticOfficeError::simpleStore(
                "Meta webhook rejected because the app secret is missing or empty (key file "
                . ($this->instagram ? "instagram_app_secret" : "messenger_app_secret") . ")"
            );
            return false;
        }
        if (!verify_hmac_sha256_signature(
            $payload,
            $this->appSecret,
            $_SERVER["HTTP_X_HUB_SIGNATURE_256"] ?? null
        )) {
            IdealisticOfficeError::simpleStore(
                "Meta webhook signature verification failed (" . ($this->instagram ? "Instagram" : "Messenger") . ")"
            );
            return false;
        }
        $this->processMessage($payload);
        return true;
    }

    private function processMessage(string $payload): void
    {
        try {
            $input = json_decode($payload, true);

            if (!is_array($input)) {
                IdealisticOfficeError::simpleStore(
                    "Input is not an array: " . json_encode($input)
                );
                return;
            }
            if (array_key_exists("entry", $input)) {
                $platform = $input["object"] ?? null;

                if ($platform === null) {
                    return;
                }
                $instagram = $platform === "instagram";

                if ($instagram !== $this->instagram) {
                    return;
                }
                $toSend = array();

                foreach ($input["entry"] as $entry) {
                    if (!array_key_exists("messaging", $entry)) {
                        continue;
                    }
                    foreach ($entry["messaging"] as $event) {
                        $userId = $event["sender"]["id"] ?? null;

                        try {
                            $botId = $event["recipient"]["id"] ?? null;

                            $expectedId = $instagram ? self::INSTAGRAM_ID : self::FACEBOOK_ID;

                            if ($userId === null
                                || $botId === null
                                || $userId === $botId
                                || $botId != $expectedId) {
                                if ($userId !== null
                                    && $botId !== null
                                    && $userId != $expectedId
                                    && $botId != $expectedId) {
                                    IdealisticOfficeError::simpleStore(
                                        "Meta event ignored (" . ($instagram ? "Instagram" : "Messenger")
                                        . "): neither sender " . $userId . " nor recipient " . $botId
                                        . " matches the expected account " . $expectedId
                                    );
                                }
                                continue;
                            }
                            $messageId = $event["message"]["mid"] ?? null;

                            if ($messageId === null) {
                                IdealisticOfficeError::simpleStore($event);
                                $toSend[$userId] = IdealisticOfficeGeneralMessage::EXCEPTION_THROWN . " (#384920175)";
                                continue;
                            }
                            $platformID = $instagram
                                ? IdealisticOfficeAccessPlatform::INSTAGRAM
                                : IdealisticOfficeAccessPlatform::MESSENGER;
                            $message = $event["message"]["text"] ?? null;
                            $info = $this->getUserInfo($userId);

                            if ($message !== null) {
                                $user = IdealisticOfficeTeamInitiator::findUser(
                                    $platformID,
                                    $userId
                                );

                                if ($user instanceof IdealisticOfficeOutcome) {
                                    $toSend[$userId] = $user->getTranslatedMessage();
                                    continue;
                                }
                                $firstName = $info["first_name"] ?? null;
                                $lastName = $info["last_name"] ?? null;
                                $prompt = IdealisticOfficeTeamInitiator::createPrompt(
                                    $user,
                                    $platformID,
                                    $userId,
                                    null,
                                    null,
                                    $messageId,
                                    null,
                                    $firstName === null && $lastName === null
                                        ? null
                                        : trim(
                                        ($firstName !== null ? $firstName . " " : "")
                                        . ($lastName !== null ? $lastName : "")
                                    ),
                                    $message
                                );

                                if ($prompt->isPositiveOutcome()) {
                                    $toSend[$userId] = null;
                                } else {
                                    $toSend[$userId] = $prompt->getTranslatedMessage();
                                }
                            } else {
                                $user = null;
                            }

                            if (array_key_exists("message", $event)
                                && array_key_exists("attachments", $event["message"])) {
                                if ($user === null) {
                                    $user = IdealisticOfficeTeamInitiator::findUser(
                                        $platformID,
                                        $userId
                                    );

                                    if ($user instanceof IdealisticOfficeOutcome) {
                                        $toSend[$userId] = $user->getTranslatedMessage();
                                        continue;
                                    }
                                }
                                $timezone = $user->getTimezone(false);
                                $attachments = array();

                                foreach ($event["message"]["attachments"] as $attachment) {
                                    $attachmentType = $attachment["type"] ?? null;
                                    $payload = $attachment["payload"] ?? array();

                                    switch ($attachmentType) {
                                        case "image":
                                        case "audio":
                                        case "file":
                                            $mediaUrl = $payload["url"] ?? null;

                                            if ($mediaUrl !== null) {
                                                $name = AbstractIdealisticOfficeAttachment::getNameFromURL($mediaUrl);
                                                $type = AbstractIdealisticOfficeAttachment::getFormatFromName($name);

                                                if ($type === null) {
                                                    $context = stream_context_create([
                                                        'http' => [
                                                            'method' => 'HEAD',
                                                            'header' => "User-Agent: Mozilla/5.0\r\n",
                                                            'ignore_errors' => true,
                                                        ]
                                                    ]);
                                                    $headers = get_headers($mediaUrl, true, $context);
                                                    $contentType = $headers["Content-Type"] ?? null;

                                                    if (is_array($contentType)) {
                                                        $contentType = $contentType[0] ?? null;
                                                    }
                                                    if ($contentType === null) {
                                                        $toSend[$userId] = IdealisticOfficeGeneralMessage::ATTACHMENT_FAILED_PROCESSING . " (#963405241)";
                                                        continue 2;
                                                    }
                                                    $type = $contentType;
                                                }
                                                $contents = IdealisticOfficeProcessor::getLinkContents(
                                                    $mediaUrl
                                                );

                                                if ($contents === false) {
                                                    $toSend[$userId] = IdealisticOfficeGeneralMessage::ATTACHMENT_FAILED_PROCESSING . " (#190241437)";
                                                    continue 2;
                                                }
                                                $attachments[] = new IdealisticOfficeAttachment(
                                                    null,
                                                    $name,
                                                    $type,
                                                    $mediaUrl,
                                                    -1,
                                                    null,
                                                    null,
                                                    null,
                                                    base64_encode($contents),
                                                    null,
                                                    true,
                                                    IdealisticOfficeReader::getCurrentDate($timezone),
                                                    $timezone->getTimeZone(),
                                                    $user
                                                );
                                            }
                                            break;
                                        default:
                                            break;
                                    }
                                }

                                if (!empty($attachments)) {
                                    $history = $user->getLastPlatformHistory($platformID);
                                    $firstName = $info["first_name"] ?? null;
                                    $lastName = $info["last_name"] ?? null;

                                    if ($history === null
                                        || !$history->isProcessing()) {
                                        $prompt = IdealisticOfficeTeamInitiator::createPrompt(
                                            $user,
                                            $platformID,
                                            $userId,
                                            null,
                                            null,
                                            $messageId,
                                            null,
                                            $firstName === null && $lastName === null
                                                ? null
                                                : trim(
                                                ($firstName !== null ? $firstName . " " : "")
                                                . ($lastName !== null ? $lastName : "")
                                            ),
                                            null,
                                            $attachments
                                        );

                                        if ($prompt->isPositiveOutcome()) {
                                            $toSend[$userId] = null;
                                        } else {
                                            $toSend[$userId] = $prompt->getTranslatedMessage();
                                        }
                                    } else {
                                        $history->silentlyFail();
                                        $prompt = IdealisticOfficeTeamInitiator::createPrompt(
                                            $user,
                                            $platformID,
                                            $userId,
                                            null,
                                            null,
                                            $messageId,
                                            null,
                                            $firstName === null && $lastName === null
                                                ? null
                                                : trim(
                                                ($firstName !== null ? $firstName . " " : "")
                                                . ($lastName !== null ? $lastName : "")
                                            ),
                                            implode(
                                                IdealisticOfficeVariable::DEFAULT_TEXT_SEPARATOR,
                                                $history->getPrompts() ?? array()
                                            ),
                                            $attachments
                                        );

                                        if (!$prompt->isPositiveOutcome()) {
                                            $toSend[$userId] = $prompt->getTranslatedMessage();
                                        }
                                    }
                                }
                            }
                        } catch (Throwable $e) {
                            IdealisticOfficeError::storeThrowable(
                                null,
                                null,
                                $e
                            );
                            $toSend[$userId] = IdealisticOfficeGeneralMessage::EXCEPTION_THROWN . " (#607318942)";
                        }
                    }
                }

                if (!empty($toSend)) {
                    $sync = sizeof($toSend) === 1;

                    foreach ($toSend as $recipientId => $messageText) {
                        if ($messageText === null) {
                            $this->sendTypingAction($recipientId, true, $sync);
                        } else {
                            $this->sendMessage($recipientId, $messageText, $sync);
                        }
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

    public function getToken(): string
    {
        return $this->accessToken;
    }

}