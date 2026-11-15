<?php

class IdealisticOfficeWhatsApp implements PhpAsyncSerializable
{

    public const
        API_META_TYPE = "meta",
        API_WASENDERAPI_TYPE = "wasenderapi",
        API_CURRENT_TYPE = self::API_TYPES[1];

    private const
        PHONE_NUMBER_ID = 801252903072236,
        META_API_VERSION = IdealisticOfficeMeta::API_VERSION,
        API_TYPES = [
        self::API_META_TYPE,
        self::API_WASENDERAPI_TYPE
    ],
        WASENDERAPI_MAX_RETRIES = 12, // 12 * 5 = 1 minute
        WASENDERAPI_GROUP_JID_SUFFIX = "@g.us",
        PORTAL_INSTALL_COMMAND = "/idealistic-setup",
        PORTAL_UNINSTALL_COMMAND = "/idealistic-remove";

    private string $accessToken;
    private ?string $webhookSecret;

    public function __construct(?string $accessToken, ?string $webhookSecret = null)
    {
        switch (self::API_CURRENT_TYPE) {
            case self::API_META_TYPE:
                if ($accessToken === null) {
                    $file = "whatsapp_credentials";
                    $accessToken = get_keys_from_file(
                        $file,
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
                if ($webhookSecret === null) {
                    $webhookSecretKeys = get_keys_from_file("whatsapp_app_secret", 1);
                    $this->webhookSecret = $webhookSecretKeys[0] ?? null;
                } else {
                    $this->webhookSecret = $webhookSecret;
                }
                break;
            case self::API_WASENDERAPI_TYPE:
                $file = "wasenderapi_credentials";
                $accessToken = get_keys_from_file($file);

                if ($accessToken === null) {
                    $this->accessToken = "";
                } else {
                    $this->accessToken = $accessToken[0];
                }
                if ($webhookSecret === null) {
                    $webhookSecretKeys = get_keys_from_file("wasenderapi_webhook_secret", 1);
                    $this->webhookSecret = $webhookSecretKeys[0] ?? null;
                } else {
                    $this->webhookSecret = $webhookSecret;
                }
                break;
            default:
                $this->accessToken = "";
                $this->webhookSecret = null;
                break;
        }
    }

    public function isValid(): bool
    {
        return !empty($this->accessToken);
    }

    private function getMediaUrl(string $mediaId): ?string
    {
        $url = "https://graph.facebook.com/" . self::META_API_VERSION . "/" . $mediaId;
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPGET, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer " . $this->accessToken
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            IdealisticOfficeError::simpleStore(curl_error($ch));
            IdealisticOfficeError::simpleStore(array(
                "mediaId" => $mediaId
            ));
            curl_close($ch);
            return null;
        }
        curl_close($ch);
        $data = json_decode($response, true);

        if (array_key_exists("error", $data)) {
            IdealisticOfficeError::simpleStore($data);
            IdealisticOfficeError::simpleStore(array(
                "mediaId" => $mediaId
            ));
            return null;
        }
        return $data["url"] ?? null;
    }

    public function sendMessage(
        string  $recipientPhoneNumber,
        string  $messageText,
        ?string $quotedMessageId = null,
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
            self::sendMessageWithToken(
                $recipientPhoneNumber,
                $messageText,
                $quotedMessageId,
                $this->accessToken ?? ""
            );
        } else {
            IdealisticOfficeAsync::run(
                array(
                    "IdealisticOfficeWhatsApp",
                    "sendMessageWithToken"
                ),
                array(
                    $recipientPhoneNumber,
                    $messageText,
                    $quotedMessageId,
                    $this->accessToken ?? ""
                )
            );
        }
    }

    public static function sendMessageWithToken(
        string  $recipientPhoneNumber, // Ex: +1234567890
        string  $messageText,
        ?string $quotedMessageId = null,
        string  $accessToken = "",
        int     $retryCount = self::WASENDERAPI_MAX_RETRIES
    ): mixed
    {
        $messageText = IdealisticOfficeMeta::utf8Encode($messageText);

        if ($messageText === null) {
            IdealisticOfficeError::simpleStore("Message text is not valid UTF-8.");
            return null;
        }
        switch (self::API_CURRENT_TYPE) {
            case self::API_META_TYPE:
                $url = "https://graph.facebook.com/" . self::META_API_VERSION . "/" . self::PHONE_NUMBER_ID . "/messages";
                $payload = [
                    "messaging_product" => "whatsapp",
                    "to" => $recipientPhoneNumber,
                    "type" => "text",
                    "text" => [
                        "body" => $messageText
                    ]
                ];

                if ($quotedMessageId !== null) {
                    $payload["context"] = ["message_id" => $quotedMessageId];
                }
                break;
            case self::API_WASENDERAPI_TYPE:
                if (!str_contains($recipientPhoneNumber, "@") && !str_starts_with($recipientPhoneNumber, "+")) {
                    $recipientPhoneNumber = "+" . $recipientPhoneNumber;
                }
                $url = "https://www.wasenderapi.com/api/send-message";
                $payload = [
                    "to" => $recipientPhoneNumber,
                    "text" => $messageText
                ];
                break;
            default:
                return null;
        }
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, @json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer " . $accessToken,
            "Content-Type: application/json"
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            IdealisticOfficeError::simpleStore(curl_error($ch));
            IdealisticOfficeError::simpleStore($payload);
            IdealisticOfficeError::simpleStore(array(
                "recipientPhoneNumber" => $recipientPhoneNumber,
                "messageText" => $messageText,
                "quotedMessageId" => $quotedMessageId,
            ));
        }
        curl_close($ch);

        $data = json_decode($response, true);

        switch (self::API_CURRENT_TYPE) {
            case self::API_META_TYPE:
                if (array_key_exists("error", $data)) {
                    IdealisticOfficeError::simpleStore($data);
                    IdealisticOfficeError::simpleStore($payload);
                    IdealisticOfficeError::simpleStore(array(
                        "recipientPhoneNumber" => $recipientPhoneNumber,
                        "messageText" => $messageText,
                        "quotedMessageId" => $quotedMessageId,
                    ));
                }
                break;
            case self::API_WASENDERAPI_TYPE:
                if (!array_key_exists("success", $data)
                    || $data["success"] !== true) {
                    $retryAfter = $data["retry_after"] ?? null;

                    if ($retryAfter !== null) {
                        $retryCount--;

                        if ($retryCount > 0) {
                            usleep(((int)$retryAfter * 1_000_000) + 100_000);
                            return self::sendMessageWithToken(
                                $recipientPhoneNumber,
                                $messageText,
                                $quotedMessageId,
                                $accessToken
                            );
                        }
                    }
                    IdealisticOfficeError::simpleStore($data);
                    IdealisticOfficeError::simpleStore($payload);
                    IdealisticOfficeError::simpleStore(array(
                        "recipientPhoneNumber" => $recipientPhoneNumber,
                        "messageText" => $messageText,
                        "quotedMessageId" => $quotedMessageId,
                    ));
                }
                break;
            default:
                break;
        }
        return $data;
    }

    // Shows "typing..." until the reply is sent or WhatsApp clears it on its own
    public function sendTyping(string $recipient): void
    {
        if (!$this->isValid()
            || self::API_CURRENT_TYPE !== self::API_WASENDERAPI_TYPE) {
            return;
        }
        IdealisticOfficeAsync::run(
            array(
                "IdealisticOfficeWhatsApp",
                "sendTypingWithToken"
            ),
            array(
                $recipient,
                $this->accessToken ?? ""
            )
        );
    }

    public static function sendTypingWithToken(
        string $recipient, // Ex: 1234567890 or a JID such as a group's
        string $accessToken = ""
    ): mixed
    {
        if (!str_contains($recipient, "@")) {
            $recipient = ltrim($recipient, "+") . "@s.whatsapp.net";
        }
        $payload = [
            "jid" => $recipient,
            "type" => "composing"
        ];
        $ch = curl_init("https://www.wasenderapi.com/api/send-presence-update");
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, @json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer " . $accessToken,
            "Content-Type: application/json"
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            IdealisticOfficeError::simpleStore(curl_error($ch));
            IdealisticOfficeError::simpleStore($payload);
            curl_close($ch);
            return null;
        }
        curl_close($ch);
        $data = json_decode($response, true);

        if (($data["success"] ?? false) !== true) {
            IdealisticOfficeError::simpleStore($data);
            IdealisticOfficeError::simpleStore($payload);
        }
        return $data;
    }

    public function sendTextAndAttachmentMessage(
        string  $recipientPhoneNumber,
        string  $type,
        ?string $messageText,
        string  $attachmentUrl,
        ?string $quotedMessageId = null,
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
                $type,
                $messageText,
                $attachmentUrl,
                $quotedMessageId,
                $this->accessToken ?? ""
            );
        } else {
            IdealisticOfficeAsync::run(
                array(
                    "IdealisticOfficeWhatsApp",
                    "sendTextAndAttachmentMessageWithToken"
                ),
                array(
                    $recipientPhoneNumber,
                    $type,
                    $messageText,
                    $attachmentUrl,
                    $quotedMessageId,
                    $this->accessToken ?? ""
                )
            );
        }
    }

    public static function sendTextAndAttachmentMessageWithToken(
        string  $recipientPhoneNumber,
        string  $type,
        ?string $messageText,
        string  $attachmentUrl,
        ?string $quotedMessageId = null,
        string  $accessToken = "",
        int     $retryCount = self::WASENDERAPI_MAX_RETRIES
    ): mixed
    {
        switch (self::API_CURRENT_TYPE) {
            case self::API_META_TYPE:
                if (!empty($messageText)) {
                    self::sendMessageWithToken(
                        $recipientPhoneNumber,
                        $messageText,
                        $quotedMessageId,
                        $accessToken,
                        $retryCount
                    );
                }
                return self::sendAttachmentMessageWithToken(
                    $recipientPhoneNumber,
                    $type,
                    $attachmentUrl,
                    $quotedMessageId,
                    $accessToken,
                    $retryCount
                );
            case self::API_WASENDERAPI_TYPE:
                $url = "https://www.wasenderapi.com/api/send-message";
                $attachmentPayload = match ($type) {
                    'image' => 'imageUrl',
                    'video' => 'videoUrl',
                    'audio' => 'audioUrl',
                    'file' => 'documentUrl',
                    'document' => 'documentUrl',
                    default => null
                };

                if ($attachmentPayload === null) {
                    IdealisticOfficeError::simpleStore("Invalid attachment type: " . $type);
                    return null;
                }
                if (!str_contains($recipientPhoneNumber, "@") && !str_starts_with($recipientPhoneNumber, "+")) {
                    $recipientPhoneNumber = "+" . $recipientPhoneNumber;
                }
                $payload = [
                    "to" => $recipientPhoneNumber,
                    $attachmentPayload => $attachmentUrl
                ];

                if ($messageText !== null) {
                    $messageText = IdealisticOfficeMeta::utf8Encode($messageText);

                    if ($messageText === null) {
                        IdealisticOfficeError::simpleStore("Message text is not valid UTF-8.");
                        return null;
                    }
                    $payload["text"] = $messageText;
                }
                break;
            default:
                return null;
        }
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, @json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer " . $accessToken,
            "Content-Type: application/json"
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            IdealisticOfficeError::simpleStore(curl_error($ch));
            IdealisticOfficeError::simpleStore($payload);
            IdealisticOfficeError::simpleStore(array(
                "recipientPhoneNumber" => $recipientPhoneNumber,
                "type" => $type,
                "attachmentUrl" => $attachmentUrl,
                "quotedMessageId" => $quotedMessageId,
                "messageText" => $messageText
            ));
        }
        curl_close($ch);

        $data = json_decode($response, true);

        switch (self::API_CURRENT_TYPE) {
            case self::API_WASENDERAPI_TYPE:
                if (!array_key_exists("success", $data)
                    || $data["success"] !== true) {
                    $retryAfter = $data["retry_after"] ?? null;

                    if ($retryAfter !== null) {
                        $retryCount--;

                        if ($retryCount > 0) {
                            usleep(((int)$retryAfter * 1_000_000) + 100_000);
                            return self::sendTextAndAttachmentMessageWithToken(
                                $recipientPhoneNumber,
                                $type,
                                $attachmentUrl,
                                $quotedMessageId,
                                $accessToken
                            );
                        }
                    }
                    IdealisticOfficeError::simpleStore($data);
                    IdealisticOfficeError::simpleStore($payload);
                    IdealisticOfficeError::simpleStore(array(
                        "recipientPhoneNumber" => $recipientPhoneNumber,
                        "type" => $type,
                        "attachmentUrl" => $attachmentUrl,
                        "quotedMessageId" => $quotedMessageId,
                        "messageText" => $messageText
                    ));
                }
                break;
            default:
                break;
        }
        return $data;
    }

    public function sendAttachmentMessage(
        string  $recipientPhoneNumber,
        string  $type,
        string  $attachmentUrl,
        ?string $quotedMessageId = null
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
                "IdealisticOfficeWhatsApp",
                "sendAttachmentMessageWithToken"
            ),
            array(
                $recipientPhoneNumber,
                $type,
                $attachmentUrl,
                $quotedMessageId,
                $this->accessToken ?? ""
            )
        );
    }

    public static function sendAttachmentMessageWithToken(
        string  $recipientPhoneNumber,
        string  $type,
        string  $attachmentUrl,
        ?string $quotedMessageId = null,
        string  $accessToken = "",
        int     $retryCount = 10
    ): mixed
    {
        switch (self::API_CURRENT_TYPE) {
            case self::API_META_TYPE:
                $url = "https://graph.facebook.com/" . self::META_API_VERSION . "/" . self::PHONE_NUMBER_ID . "/messages";
                $attachmentPayload = match ($type) {
                    'image' => ['image' => ['link' => $attachmentUrl]],
                    'video' => ['video' => ['link' => $attachmentUrl]],
                    'audio' => ['audio' => ['link' => $attachmentUrl]],
                    'file' => ['document' => ['link' => $attachmentUrl]],
                    'document' => ['document' => ['link' => $attachmentUrl]],
                    default => null
                };

                if ($attachmentPayload === null) {
                    IdealisticOfficeError::simpleStore("Invalid attachment type: " . $type);
                    return null;
                }

                $payload = [
                    "messaging_product" => "whatsapp",
                    "to" => $recipientPhoneNumber,
                    "type" => $type,
                    $type => $attachmentPayload[$type]
                ];

                if ($quotedMessageId !== null) {
                    $payload["context"] = ["message_id" => $quotedMessageId];
                }
                break;
            case self::API_WASENDERAPI_TYPE:
                $url = "https://www.wasenderapi.com/api/send-message";
                $attachmentPayload = match ($type) {
                    'image' => 'imageUrl',
                    'video' => 'videoUrl',
                    'audio' => 'audioUrl',
                    'file' => 'documentUrl',
                    'document' => 'documentUrl',
                    default => null
                };

                if ($attachmentPayload === null) {
                    IdealisticOfficeError::simpleStore("Invalid attachment type: " . $type);
                    return null;
                }
                if (!str_contains($recipientPhoneNumber, "@") && !str_starts_with($recipientPhoneNumber, "+")) {
                    $recipientPhoneNumber = "+" . $recipientPhoneNumber;
                }
                $payload = [
                    "to" => $recipientPhoneNumber,
                    $attachmentPayload => $attachmentUrl
                ];
                break;
            default:
                return null;
        }
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, @json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer " . $accessToken,
            "Content-Type: application/json"
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            IdealisticOfficeError::simpleStore(curl_error($ch));
            IdealisticOfficeError::simpleStore($payload);
            IdealisticOfficeError::simpleStore(array(
                "recipientPhoneNumber" => $recipientPhoneNumber,
                "type" => $type,
                "attachmentUrl" => $attachmentUrl,
                "quotedMessageId" => $quotedMessageId,
            ));
        }
        curl_close($ch);

        $data = json_decode($response, true);

        switch (self::API_CURRENT_TYPE) {
            case self::API_META_TYPE:
                if (array_key_exists("error", $data)) {
                    IdealisticOfficeError::simpleStore($data);
                    IdealisticOfficeError::simpleStore($payload);
                    IdealisticOfficeError::simpleStore(array(
                        "recipientPhoneNumber" => $recipientPhoneNumber,
                        "type" => $type,
                        "attachmentUrl" => $attachmentUrl,
                        "quotedMessageId" => $quotedMessageId,
                    ));
                }
                break;
            case self::API_WASENDERAPI_TYPE:
                if (!array_key_exists("success", $data)
                    || $data["success"] !== true) {
                    $retryAfter = $data["retry_after"] ?? null;

                    if ($retryAfter !== null) {
                        $retryCount--;

                        if ($retryCount > 0) {
                            usleep(((int)$retryAfter * 1_000_000) + 100_000);
                            return self::sendAttachmentMessageWithToken(
                                $recipientPhoneNumber,
                                $type,
                                $attachmentUrl,
                                $quotedMessageId,
                                $accessToken
                            );
                        }
                    }
                    IdealisticOfficeError::simpleStore($data);
                    IdealisticOfficeError::simpleStore($payload);
                    IdealisticOfficeError::simpleStore(array(
                        "recipientPhoneNumber" => $recipientPhoneNumber,
                        "type" => $type,
                        "attachmentUrl" => $attachmentUrl,
                        "quotedMessageId" => $quotedMessageId,
                    ));
                }
                break;
            default:
                break;
        }
        return $data;
    }

    // Separator

    // Returns false when the request's signature does not check out (caller should respond
    // with an error status and must not treat the payload as genuine); true otherwise.
    public function handleMessage(): bool
    {
        switch (self::API_CURRENT_TYPE) {
            case self::API_META_TYPE:
                $payload = timed_file_get_contents("php://input", IdealisticOfficeLimit::LINK_DOWNLOAD_SECONDS_TIMEOUT);

                if (!verify_hmac_sha256_signature(
                    $payload,
                    $this->webhookSecret,
                    $_SERVER["HTTP_X_HUB_SIGNATURE_256"] ?? null
                )) {
                    IdealisticOfficeError::simpleStore("WhatsApp (Meta) webhook signature verification failed");
                    return false;
                }
                $this->handleMessageWhatsApp($payload);
                return true;
            case self::API_WASENDERAPI_TYPE:
                if (!verify_shared_secret($this->webhookSecret, $_SERVER["HTTP_X_WEBHOOK_SIGNATURE"] ?? null)) {
                    IdealisticOfficeError::simpleStore("WhatsApp (WaSenderAPI) webhook signature verification failed");
                    return false;
                }
                $this->handleMessageWasenderApi();
                return true;
            default:
                return false;
        }
    }

    // Separator

    /**
     * @throws Exception
     */
    private function getDecryptionKeys(string $mediaKey, string $type, int $length = 112): false|string
    {
        $info = match ($type) {
            'image' => 'WhatsApp Image Keys',
            'video' => 'WhatsApp Video Keys',
            'audio' => 'WhatsApp Audio Keys',
            'document' => 'WhatsApp Document Keys',
            default => throw new Exception("Invalid media type"),
        };
        return hash_hkdf('sha256', base64_decode($mediaKey), $length, $info, '');
    }

    /**
     * @throws Exception
     */
    private function decryptWhatsAppMedia(string $mediaKey, string $url, string $mediaType): string
    {
        $encFile = IdealisticOfficeProcessor::getLinkContents(
            $url
        );

        if (!$encFile) {
            throw new Exception("Failed to download file");
        }
        // Get decryption keys based on media type
        $keys = $this->getDecryptionKeys($mediaKey, $mediaType);
        $iv = substr($keys, 0, 16);
        $cipherKey = substr($keys, 16, 32);

        // Remove the last 10 bytes (MAC) from the encrypted file
        $ciphertext = substr($encFile, 0, strlen($encFile) - 10);

        // Decrypt the file using AES-256-CBC
        $plaintext = openssl_decrypt($ciphertext, 'aes-256-cbc', $cipherKey, OPENSSL_RAW_DATA, $iv);

        if (!$plaintext) {
            throw new Exception("Failed to decrypt media");
        }
        return $plaintext;
    }

    /**
     * @throws Exception
     */
    private function getMediaContents(array $message): ?array
    {
        if (isset($message['imageMessage'])) {
            $mediaInfo = $message['imageMessage'];
            $mediaType = 'image';
            $extension = '.jpg';
        } elseif (isset($message['videoMessage'])) {
            $mediaInfo = $message['videoMessage'];
            $mediaType = 'video';
            $extension = '.mp4';
        } elseif (isset($message['audioMessage'])) {
            $mediaInfo = $message['audioMessage'];
            $mediaType = 'audio';
            $extension = '.ogg';
        } elseif (isset($message['documentMessage'])) {
            $mediaInfo = $message['documentMessage'];
            $mediaType = 'document';
            $extension = ''; // Use the original extension if available
        } else {
            return null;
        }
        $mediaKey = $mediaInfo['mediaKey'] ?? null;
        $url = $mediaInfo['url'] ?? null;

        if (!$mediaKey
            || !$url) {
            return null;
        }
        return array(
            $this->decryptWhatsAppMedia($mediaKey, $url, $mediaType),
            $extension
        );
    }

    private function getTextAndAttachmentsAndQuote(
        IdealisticOfficeUser $user,
        array                $message,
        bool                 $secondary = false
    ): array
    {
        $text = $message["conversation"] ?? null;
        $quotedText = null;
        $attachments = array();
        $timezone = $user->getTimezone(false);

        if ($text === null) {
            $extendedMessage = $message["extendedTextMessage"] ?? null;

            if ($extendedMessage !== null) {
                $text = $extendedMessage["text"] ?? null;
                $quotedText = $extendedMessage["contextInfo"]["quotedMessage"] ?? null;

                if ($quotedText !== null) {
                    $quotedText = $this->getTextAndAttachmentsAndQuote($user, $quotedText, true)[0];
                }
            } else if (!$secondary) {
                $imageMessage = $message["imageMessage"] ?? null;

                if ($imageMessage !== null) {
                    $text = $imageMessage["caption"] ?? null;

                    try {
                        $attachment = $this->getMediaContents($message);

                        if ($attachment !== null) {
                            $attachments[] = new IdealisticOfficeAttachment(
                                null,
                                random_string(IdealisticOfficeLimit::RANDOM_STRING_LENGTH)
                                . $attachment[1],
                                $imageMessage["mimetype"],
                                null,
                                -1,
                                null,
                                null,
                                null,
                                base64_encode($attachment[0]),
                                null,
                                true,
                                IdealisticOfficeReader::getCurrentDate($timezone),
                                $timezone->getTimeZone(),
                                $user
                            );
                        }
                    } catch (Exception $e) {
                        IdealisticOfficeError::storeThrowable($user->getTeam(), $user, $e);
                        $text = null;
                    }
                } else {
                    $videoMessage = $message["videoMessage"] ?? null;

                    if ($videoMessage !== null) {
                        $text = $videoMessage["caption"] ?? null;

                        try {
                            $attachment = $this->getMediaContents($message);

                            if ($attachment !== null) {
                                $attachments[] = new IdealisticOfficeAttachment(
                                    null,
                                    random_string(IdealisticOfficeLimit::RANDOM_STRING_LENGTH)
                                    . $attachment[1],
                                    $imageMessage["mimetype"],
                                    null,
                                    -1,
                                    null,
                                    null,
                                    null,
                                    base64_encode($attachment[0]),
                                    null,
                                    true,
                                    IdealisticOfficeReader::getCurrentDate($timezone),
                                    $timezone->getTimeZone(),
                                    $user
                                );
                            }
                        } catch (Exception $e) {
                            IdealisticOfficeError::storeThrowable($user->getTeam(), $user, $e);
                            $text = null;
                        }
                    } else {
                        $audioMessage = $message["audioMessage"] ?? null;

                        if ($audioMessage !== null) {
                            $text = $documentMessage["caption"] ?? null;

                            try {
                                $attachment = $this->getMediaContents($message);

                                if ($attachment !== null) {
                                    $attachments[] = new IdealisticOfficeAttachment(
                                        null,
                                        random_string(IdealisticOfficeLimit::RANDOM_STRING_LENGTH)
                                        . $attachment[1],
                                        $imageMessage["mimetype"],
                                        null,
                                        -1,
                                        null,
                                        null,
                                        null,
                                        base64_encode($attachment[0]),
                                        null,
                                        true,
                                        IdealisticOfficeReader::getCurrentDate($timezone),
                                        $timezone->getTimeZone(),
                                        $user
                                    );
                                }
                            } catch (Exception $e) {
                                IdealisticOfficeError::storeThrowable($user->getTeam(), $user, $e);
                                $text = null;
                            }
                        } else {
                            $documentMessage = $message["documentMessage"] ?? null;

                            if ($documentMessage !== null) {
                                $text = $documentMessage["caption"] ?? null;

                                try {
                                    $attachment = $this->getMediaContents($message);

                                    if ($attachment !== null) {
                                        $attachments[] = new IdealisticOfficeAttachment(
                                            null,
                                            random_string(IdealisticOfficeLimit::RANDOM_STRING_LENGTH)
                                            . $attachment[1],
                                            $imageMessage["mimetype"],
                                            null,
                                            -1,
                                            null,
                                            null,
                                            null,
                                            base64_encode($attachment[0]),
                                            null,
                                            true,
                                            IdealisticOfficeReader::getCurrentDate($timezone),
                                            $timezone->getTimeZone(),
                                            $user
                                        );
                                    }
                                } catch (Exception $e) {
                                    IdealisticOfficeError::storeThrowable($user->getTeam(), $user, $e);
                                    $text = null;
                                }
                            } else {
                                $videoMessage = $message["videoMessage"] ?? null;

                                if ($videoMessage !== null) {
                                    $text = $videoMessage["caption"] ?? null;

                                    try {
                                        $attachment = $this->getMediaContents($message);

                                        if ($attachment !== null) {
                                            $attachments[] = new IdealisticOfficeAttachment(
                                                null,
                                                random_string(IdealisticOfficeLimit::RANDOM_STRING_LENGTH)
                                                . $attachment[1],
                                                $imageMessage["mimetype"],
                                                null,
                                                -1,
                                                null,
                                                null,
                                                null,
                                                base64_encode($attachment[0]),
                                                null,
                                                true,
                                                IdealisticOfficeReader::getCurrentDate($timezone),
                                                $timezone->getTimeZone(),
                                                $user
                                            );
                                        }
                                    } catch (Exception $e) {
                                        IdealisticOfficeError::storeThrowable($user->getTeam(), $user, $e);
                                        $text = null;
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
        return array($text, $attachments, $quotedText);
    }

    // Returns true only for the first caller per message ID, the exclusive file creation is atomic
    private static function claimMessage(string $messageId): bool
    {
        $directory = sys_get_temp_dir() . "/whatsapp_claimed_messages";

        if (!is_dir($directory)) {
            @mkdir($directory, 0700, true);
        }
        $path = $directory . "/" . md5($messageId);
        $handle = @fopen($path, "x");

        if ($handle === false) {
            return file_exists($path) ? false : true; // Only skip when it was really claimed already
        }
        fclose($handle);

        if (mt_rand(1, 50) === 1) {
            foreach (glob($directory . "/*") ?: array() as $file) {
                if (filemtime($file) < time() - 3600) {
                    @unlink($file);
                }
            }
        }
        return true;
    }

    private function handleMessageWasenderApi(): void
    {
        $input = json_decode(timed_file_get_contents("php://input", IdealisticOfficeLimit::LINK_DOWNLOAD_SECONDS_TIMEOUT), true);

        if (!is_array($input)) {
            IdealisticOfficeError::simpleStore(
                "Input is not an array: " . @json_encode($input)
            );
            return;
        }
        $event = ($input["event"] ?? null);
        $isGroupEvent = $event === "messages-group.received";

        if ($event !== "messages.received"
            && !$isGroupEvent) {
            if ($event === "session.status") {
                $status = $input["data"]["status"] ?? "null";

                if ($status !== "connected") {
                    if ($status === "logged_out" || $status === "need_scan") {
                        // These statuses explicitly mean authentication was fully lost. A code cannot rescan the QR for you.
                        services_self_email(
                            IdealisticOfficeVariable::SUPPORT_EMAIL,
                            "WhatsApp Action Required",
                            "WaSender session status is: " . $status . ". You must manually log into your dashboard and scan the QR code to re-authenticate."
                        );
                    } else {
                        // For transient drops like 'disconnected' or 'expired', attempt the automated hook
                        $reconnectAttempt = $this->reconnectWasenderSession();
                        services_self_email(
                            IdealisticOfficeVariable::SUPPORT_EMAIL,
                            "WhatsApp Session Update",
                            "Status: " . $status . " | Auto-reconnect triggered: " . ($reconnectAttempt ? 'Success/Pending' : 'Failed')
                        );
                    }
                }
            }
            return;
        }
        $messages = $input["data"]["messages"] ?? null;

        if ($messages === null
            || ($messages["key"]["fromMe"] ?? false) === true) {
            return;
        }
        $remoteJid = $messages["key"]["remoteJid"] ?? null;
        $isGroup = $isGroupEvent
            || (is_string($remoteJid) && str_ends_with($remoteJid, self::WASENDERAPI_GROUP_JID_SUFFIX));
        $phoneNumber = $isGroup
            ? ($messages["key"]["cleanedParticipantPn"] ?? null)
            : ($messages["key"]["cleanedSenderPn"] ?? null);
        $replyTo = $isGroup ? $remoteJid : $phoneNumber;

        if ($replyTo === null) {
            IdealisticOfficeError::simpleStore($messages);
            return;
        }
        if ($phoneNumber === null) {
            $this->sendMessage(
                $replyTo,
                IdealisticOfficeGeneralMessage::EXCEPTION_THROWN . " (#985230441)",
                null,
                true
            );
            return;
        }
        $messageId = $messages["key"]["id"] ?? null;

        if ($messageId === null) {
            $this->sendMessage(
                $replyTo,
                IdealisticOfficeGeneralMessage::EXCEPTION_THROWN . " (#438792095)",
                null,
                true
            );
            return;
        }

        // WaSender can deliver the same message more than once (e.g. both group and regular events)
        if (!self::claimMessage($messageId)) {
            return;
        }
        $messageBody = $messages["messageBody"]
            ?? $messages["message"]["conversation"]
            ?? $messages["message"]["extendedTextMessage"]["text"]
            ?? null;
        $commandOutcome = $this->runPortalCommand(
            $messageBody,
            $phoneNumber,
            $replyTo,
            $messageId,
            $isGroup
        );

        if ($commandOutcome !== null) {
            $this->sendMessage(
                $replyTo,
                $commandOutcome->getTranslatedMessage(),
                null,
                true
            );
            return;
        }
        if ($isGroup) {
            // Groups are portal territory only, regular chatter must never reach the user's assistant
            $this->runInstalledPortal($messages, $messageBody, $phoneNumber, $replyTo, $messageId);
            return;
        }
        $platformID = IdealisticOfficeAccessPlatform::WHATSAPP;
        $user = IdealisticOfficeTeamInitiator::findUser(
            $platformID,
            $phoneNumber
        );

        if ($user instanceof IdealisticOfficeOutcome) {
            $this->sendMessage(
                $phoneNumber,
                $user->getTranslatedMessage(),
                null,
                true
            );
            return;
        }
        $text = $this->getTextAndAttachmentsAndQuote($user, $messages["message"]);
        $attachments = $text[1];
        $text = $text[0] . ($text[2] !== null ? IdealisticOfficeVariable::DEFAULT_TEXT_SEPARATOR . $text[2] : "");
        $prompt = IdealisticOfficeTeamInitiator::createPrompt(
            $user,
            $platformID,
            $phoneNumber,
            null,
            null,
            $messageId,
            null,
            $messages["pushName"] ?? null,
            $text,
            $attachments
        );

        if ($prompt->isPositiveOutcome()) {
            if (false) {
                $this->sendMessage(
                    $phoneNumber,
                    IdealisticOfficeStrings::translateMessage(
                        IdealisticOfficeGeneralMessage::PROMPT_WAIT_RESPONSE,
                        $user
                    ),
                    null,
                    true
                );
            }
        } else {
            $this->sendMessage(
                $phoneNumber,
                $prompt->getTranslatedMessage(),
                null,
                true
            );
        }
    }

    // Returns null when the text is not a portal command, so the caller can continue with regular handling
    private function runPortalCommand(
        ?string $text,
        string  $userId,
        string  $channelId,
        string  $messageId,
        bool    $isGroup
    ): ?IdealisticOfficeOutcome
    {
        if ($text === null) {
            return null;
        }
        $parts = preg_split("/\s+/", trim($text));
        $command = strtolower($parts[0] ?? "");

        if ($command !== self::PORTAL_INSTALL_COMMAND
            && $command !== self::PORTAL_UNINSTALL_COMMAND) {
            return null;
        }
        if (!$isGroup) {
            // Private chats are the user's own assistant, portals can only be installed in groups
            return new IdealisticOfficeOutcome(false, "This command can only be used in groups.");
        }
        $platformId = IdealisticOfficeAccessPlatform::WHATSAPP;

        switch ($command) {
            case self::PORTAL_INSTALL_COMMAND:
                if (sizeof($parts) !== 2) {
                    return new IdealisticOfficeOutcome(
                        false,
                        "Usage: " . self::PORTAL_INSTALL_COMMAND . " <portal>"
                    );
                }
                $portalId = $parts[1];
                return IdealisticOfficePortalIndependent::installPortal(
                    $platformId,
                    $portalId,
                    $userId,
                    null,
                    $channelId,
                    null,
                    $messageId
                );
            case self::PORTAL_UNINSTALL_COMMAND:
                if (sizeof($parts) !== 1) {
                    return new IdealisticOfficeOutcome(
                        false,
                        "Usage: " . self::PORTAL_UNINSTALL_COMMAND
                    );
                }
                return IdealisticOfficePortalIndependent::uninstallPortal(
                    $platformId,
                    $userId,
                    null,
                    $channelId,
                    null
                );
            default:
                return null;
        }
    }

    private function runInstalledPortal(
        array   $messages,
        ?string $messageBody,
        string  $phoneNumber,
        string  $groupJid,
        string  $messageId
    ): void
    {
        // Installed portals only reply when the bot is tagged, other messages are kept as history
        $taggedBody = $this->getTextIfMentioned($messages["message"] ?? array(), $messageBody);
        $isTagged = $taggedBody !== null;
        $messageBody = $isTagged ? $taggedBody : $messageBody;

        if (empty($messageBody)) {
            return;
        }
        try {
            $outcome = IdealisticOfficePortalIndependent::runInstalledPortal(
                IdealisticOfficeAccessPlatform::WHATSAPP,
                $phoneNumber,
                null,
                $messages["pushName"] ?? null,
                null,
                $groupJid,
                null,
                $messageId,
                $messageBody,
                null,
                false,
                $isTagged
            );
        } catch (Throwable $e) {
            IdealisticOfficeError::storeThrowable(null, null, $e);
            return;
        }

        if (!$isTagged) {
            return;
        }

        if ($outcome->isPositiveOutcome()) {
            $this->sendTyping($groupJid);
        } else if ($outcome->getRawOutcome() === false) {
            $this->sendMessage(
                $groupJid,
                $outcome->getTranslatedMessage(),
                null,
                true
            );
        }
    }

    // Returns the text without the bot's mentions, or null when the bot was not mentioned
    private function getTextIfMentioned(array $message, ?string $text): ?string
    {
        $mentioned = array();

        foreach ($message as $content) {
            if (is_array($content)) {
                foreach ($content["contextInfo"]["mentionedJid"] ?? array() as $jid) {
                    if (is_string($jid)) {
                        $mentioned[] = self::getJidUser($jid);
                    }
                }
            }
        }
        if (empty($mentioned)) {
            return null;
        }
        $matches = array_intersect($this->getSessionUsers(), $mentioned);

        if (empty($matches)) {
            return null;
        }
        foreach ($matches as $user) {
            $text = str_replace("@" . $user, "", $text ?? "");
        }
        return trim($text);
    }

    // Ex: "1234567890:12@s.whatsapp.net" -> "1234567890"
    private static function getJidUser(string $jid): string
    {
        return explode(":", explode("@", $jid, 2)[0], 2)[0];
    }

    // The bot's own number and LID, as they appear in mentions
    private function getSessionUsers(): array
    {
        if (!$this->isValid()) {
            return array();
        }
        $ch = curl_init("https://www.wasenderapi.com/api/user");
        curl_setopt($ch, CURLOPT_HTTPGET, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer " . $this->accessToken
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            IdealisticOfficeError::simpleStore(curl_error($ch));
            curl_close($ch);
            return array();
        }
        curl_close($ch);
        $data = json_decode($response, true);

        if (($data["success"] ?? false) !== true) {
            IdealisticOfficeError::simpleStore($data);
            return array();
        }
        $users = array();

        foreach (array("id", "lid") as $key) {
            $jid = $data["data"][$key] ?? null;

            if (is_string($jid)
                && $jid !== "") {
                $users[] = self::getJidUser($jid);
            }
        }
        return $users;
    }

    /**
     * Attempts to automatically reconnect the WaSenderAPI session for transient drops.
     */
    private function reconnectWasenderSession(): bool
    {
        if (!$this->isValid()) {
            IdealisticOfficeError::simpleStore(__METHOD__ . ": Access token missing during auto-reconnect.");
            return false;
        }

        // Generic reconnect trigger. Adjust path if using explicit Session IDs in your architecture.
        $url = "https://www.wasenderapi.com/api/reconnect";

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer " . $this->accessToken,
            "Content-Type: application/json"
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            IdealisticOfficeError::simpleStore("Auto-reconnect CURL Error: " . curl_error($ch));
            curl_close($ch);
            return false;
        }

        curl_close($ch);

        $data = json_decode($response, true);

        if (!isset($data["success"]) || $data["success"] !== true) {
            IdealisticOfficeError::simpleStore("Auto-reconnect failed at API level.");
            IdealisticOfficeError::simpleStore($data);
            return false;
        }

        return true;
    }

    // Separator

    private function handleMessageWhatsApp(string $payload): void
    {
        try {
            $input = json_decode($payload, true);

            if (!is_array($input)) {
                IdealisticOfficeError::simpleStore(
                    "Input is not an array: " . @json_encode($input)
                );
                return;
            }
            $entries = $input["entry"] ?? null;

            if ($entries !== null) {
                $platform = $input["object"] ?? null;

                if ($platform !== "whatsapp_business_account") {
                    return;
                }
                $toSend = [];

                foreach ($entries as $entry) {
                    foreach ($entry["changes"] as $change) {
                        try {
                            if (($change["field"] ?? "") !== "messages") {
                                continue;
                            }
                            $value = $change["value"] ?? [];
                            $botId = $value["metadata"]["phone_number_id"] ?? null;

                            if ($botId === null
                                || $botId != self::PHONE_NUMBER_ID) {
                                continue;
                            }
                            $contactMap = [];

                            foreach ($value["contacts"] ?? [] as $contact) {
                                $waId = $contact["wa_id"] ?? null;
                                $profileName = $contact["profile"]["name"] ?? null;
                                $contactMap[$waId] = [
                                    "display_name" => $profileName,
                                    "phone" => $waId
                                ];
                            }
                            foreach ($value["messages"] as $message) {
                                $userId = $message["from"] ?? null;
                                $messageId = $message["id"] ?? null;

                                if ($userId === null || $messageId === null) {
                                    IdealisticOfficeError::simpleStore($message);

                                    if ($userId !== null) {
                                        $toSend[$userId] = IdealisticOfficeGeneralMessage::EXCEPTION_THROWN . " (#218304956)";
                                    }
                                    continue;
                                }
                                $platformID = IdealisticOfficeAccessPlatform::WHATSAPP;
                                $user = IdealisticOfficeTeamInitiator::findUser(
                                    $platformID,
                                    $userId
                                );

                                if ($user instanceof IdealisticOfficeOutcome) {
                                    $toSend[$userId] = $user->getTranslatedMessage();
                                    continue;
                                }
                                $bodyText = $message["text"]["body"] ?? null;
                                $displayName = $contactMap[$userId]["display_name"] ?? null;
                                $msgType = $message["type"] ?? null;
                                $attachments = array();

                                if ($msgType !== null
                                    && $msgType !== "text") {
                                    $mediaId = $message[$msgType]["id"] ?? null;

                                    if ($mediaId !== null) {
                                        $mediaUrl = $this->getMediaUrl($mediaId);
                                        $name = AbstractIdealisticOfficeAttachment::getNameFromURL($mediaUrl);

                                        if ($name === null) {
                                            $toSend[$userId] = IdealisticOfficeGeneralMessage::ATTACHMENT_FAILED_PROCESSING . " (#890512413)";
                                            continue;
                                        }
                                        $type = AbstractIdealisticOfficeAttachment::getFormatFromName($name);

                                        if ($type === null) {
                                            $toSend[$userId] = IdealisticOfficeGeneralMessage::ATTACHMENT_FAILED_PROCESSING . " (#290134003)";
                                            continue;
                                        }
                                        $contents = IdealisticOfficeProcessor::getLinkContents(
                                            $mediaUrl
                                        );

                                        if ($contents === false) {
                                            $toSend[$userId] = IdealisticOfficeGeneralMessage::ATTACHMENT_FAILED_PROCESSING . " (#189024142)";
                                            continue;
                                        }
                                        $timezone = $user->getTimezone(false);
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
                                }
                                $prompt = IdealisticOfficeTeamInitiator::createPrompt(
                                    $user,
                                    $platformID,
                                    $userId,
                                    null,
                                    null,
                                    $messageId,
                                    null,
                                    $displayName,
                                    $bodyText ?? "",
                                    $attachments
                                );

                                if ($prompt->isPositiveOutcome()) {
                                    $toSend[$userId] = array(
                                        $messageId,
                                        IdealisticOfficeStrings::translateMessage(
                                            IdealisticOfficeGeneralMessage::PROMPT_WAIT_RESPONSE,
                                            $user
                                        )
                                    );
                                } else {
                                    $toSend[$userId] = array(
                                        $messageId,
                                        $prompt->getTranslatedMessage()
                                    );
                                }
                            }
                        } catch (Throwable $e) {
                            IdealisticOfficeError::storeThrowable(null, null, $e, false);
                            $toSend[$userId] = IdealisticOfficeGeneralMessage::EXCEPTION_THROWN . " (#903712468)";
                        }
                    }
                }

                if (!empty($toSend)) {
                    $sync = sizeof($toSend) === 1;

                    foreach ($toSend as $recipientId => $messageContents) {
                        if (is_array($messageContents)) {
                            $this->sendMessage(
                                $recipientId,
                                $messageContents[1],
                                $messageContents[0],
                                $sync
                            );
                        } else {
                            $this->sendMessage(
                                $recipientId,
                                $messageContents,
                                $sync
                            );
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

    // Sends the replies of portals installed in WhatsApp chats, there is no long-running bot so a scheduler runs this
    public static function deliverInstalledPortalMessages(): bool
    {
        $portalMessages = IdealisticOfficePortalIndependent::getInstalledPortalMessages(
            IdealisticOfficeAccessPlatform::WHATSAPP
        );

        if (empty($portalMessages)) {
            return false;
        }
        $runner = new IdealisticOfficeWhatsApp(null);

        if (!$runner->isValid()) {
            return false;
        }
        $messageCount = sizeof($portalMessages);

        foreach ($portalMessages as $portalMessage) {
            if (!$portalMessage->hasMessage()
                || !$portalMessage->process()) {
                continue;
            }
            $pieces = mb_str_split(
                $portalMessage->getBrandedMessage(),
                IdealisticOfficeLimit::MESSAGE_CHARACTER_LIMIT[IdealisticOfficeAccessPlatform::WHATSAPP]
            );
            $sync = $messageCount === 1
                || sizeof($pieces) > 1;

            foreach ($pieces as $piece) {
                $runner->sendMessage(
                    (string)$portalMessage->getChannelId(),
                    $piece,
                    $portalMessage->getMessageId(),
                    $sync
                );
            }
        }
        return true;
    }

    public function getToken(): string
    {
        return $this->accessToken;
    }

}