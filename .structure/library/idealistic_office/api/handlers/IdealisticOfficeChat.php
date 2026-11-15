<?php

class IdealisticOfficeChat
{

    public const
        POLL_FIELD = "chat_poll",
        POLL_HEADER = "idealistic-chat",
        FRAGMENT_ID = "chat-fragment";

    private const ESCAPES = array(
        "&" => "&amp;",
        "<" => "&lt;",
        ">" => "&gt;",
        "\"" => "&quot;",
        "'" => "&#39;"
    );

    public static function escape(?string $value): string
    {
        return strtr($value ?? "", self::ESCAPES);
    }

    public static function parseText(string $text): string
    {
        $safe = self::escape($text);
        $steps = array(
            array('/(*ANYCRLF)^### +(.*)$/mu', '<strong class="msg-h3">$1</strong>'),
            array('/(*ANYCRLF)^## +(.*)$/mu', '<strong class="msg-h2">$1</strong>'),
            array('/(*ANYCRLF)^# +(.*)$/mu', '<strong class="msg-h1">$1</strong>'),
            array('/\*\*(.*?)\*\*/u', '<strong>$1</strong>'),
            array('/\*(.*?)\*/u', '<em>$1</em>'),
            array('/__(.*?)__/u', '<u>$1</u>'),
            array(
                '/(*UCP)(https?:\/\/[^\s<>"\'*]+[^<>"\'*.,:;)\]\s])/u',
                '<a href="$1" target="_blank" rel="noopener noreferrer" class="text-info text-decoration-underline" style="word-break: break-all;">$1</a>'
            )
        );

        foreach ($steps as $step) {
            $result = @preg_replace($step[0], $step[1], $safe);

            if ($result !== null) {
                $safe = $result;
            }
        }
        return $safe;
    }

    public static function renderMessage(string $sender, mixed $id, ?string $text, ?string $attachmentName, bool $hasAttachment, string $fileLabel, ?array $segments = null): string
    {
        $empty = ($text === null || $text === "") && !$hasAttachment;
        $html = '<div class="' . ($empty ? 'd-none' : 'd-flex w-100 anim-slide-up mb-3') . '"><div class="msg-bubble msg-' . self::escape($sender) . ' d-flex flex-column"';

        if ($id !== null && $id !== "" && $id !== 0 && $id !== "0") {
            $html .= ' id="msg-' . self::escape($sender) . '-' . self::escape((string)$id) . '"';
        }
        $html .= '>';

        if (!empty($segments)) {
            $html .= '<div>';

            foreach ($segments as $segment) {
                $html .= '<div class="msg-segment' . (!empty($segment["highlight"]) ? ' msg-confirmed' : '') . '">'
                    . self::parseText((string)($segment["text"] ?? "")) . '</div>';
            }
            $html .= '</div>';
        } elseif ($text !== null && $text !== "") {
            $html .= '<div>' . self::parseText($text) . '</div>';
        }

        if ($hasAttachment) {
            $name = $attachmentName !== null && $attachmentName !== "" ? $attachmentName : $fileLabel;
            $html .= '<div class="attachment-box"><strong><i class="bi bi-paperclip"></i> ' . self::escape($name) . '</strong></div>';
        }
        return $html . '</div></div>';
    }

    public static function build(
        IdealisticOfficePortal $portal,
        string                 $sessionId,
        ?string                $ipAddress,
        ?string                $defaultMessage,
        int                    $afterId,
        string                 $fileLabel,
        ?string                $language
    ): array|IdealisticOfficeOutcome
    {
        $data = $portal->getData(
            IdealisticOfficeLimit::MAX_EXTENSIONS_PER_OBJECT,
            $sessionId,
            $ipAddress,
            false,
            $defaultMessage,
            false,
            $afterId,
            true
        );

        if ($data instanceof IdealisticOfficeOutcome) {
            return $data;
        }
        $html = "";
        $fingerprint = array($language ?? "", (string)($data[IdealisticOfficePortal::LAST_ID_KEY] ?? 0));
        $lastSender = null;
        $lastDate = null;

        foreach ($data[IdealisticOfficePortal::HISTORY_KEY] ?? array() as $time => $interaction) {
            foreach (array("user", "assistant") as $sender) {
                foreach ($interaction[$sender] ?? array() as $message) {
                    $attachment = $message["attachment"] ?? null;
                    $hasAttachment = $attachment instanceof AbstractIdealisticOfficeAttachment;
                    $name = $hasAttachment ? $attachment->getName() : null;
                    $text = isset($message["text"]) ? (string)$message["text"] : null;

                    if ($text === IdealisticOfficePortal::NO_MESSAGE) {
                        $text = null;
                    }
                    $segments = isset($message["segments"]) && is_array($message["segments"]) ? $message["segments"] : null;
                    $html .= self::renderMessage(
                        $sender,
                        $message["id"] ?? null,
                        $text,
                        $name,
                        $hasAttachment,
                        $fileLabel,
                        $segments
                    );
                    $fingerprint[] = $sender . ":" . ($message["id"] ?? "") . ":" . ($text ?? "") . ":" . ($name ?? "")
                        . ($segments === null ? "" : ":" . implode("", array_map(fn($segment) => !empty($segment["highlight"]) ? "1" : "0", $segments)));
                    $lastSender = $sender;
                    $lastDate = $sender === "user" ? ($message["date"] ?? $time) : null;
                }
            }
        }
        return array(
            "html" => $html,
            "lastId" => (int)($data[IdealisticOfficePortal::LAST_ID_KEY] ?? 0),
            "pendingDate" => $lastSender === "user" ? $lastDate : null,
            "state" => hash("sha256", implode("\n", $fingerprint))
        );
    }

    public static function fragment(array $built, ?string $language): string
    {
        return '<div id="' . self::FRAGMENT_ID . '"'
            . ' data-last-id="' . (int)$built["lastId"] . '"'
            . ' data-pending-date="' . self::escape($built["pendingDate"]) . '"'
            . ' data-language="' . self::escape($language) . '"'
            . ' data-state="' . self::escape($built["state"]) . '">'
            . $built["html"]
            . '</div>';
    }

    public static function isRequest(): bool
    {
        return ($_SERVER["REQUEST_METHOD"] ?? "") === "POST"
            && ($_SERVER["HTTP_X_REQUESTED_WITH"] ?? "") === self::POLL_HEADER;
    }

    public static function isSameOrigin(): bool
    {
        $site = $_SERVER["HTTP_SEC_FETCH_SITE"] ?? null;
        return $site === null || $site === "same-origin";
    }

    public static function isPoll(): bool
    {
        return self::isRequest() && isset($_POST[self::POLL_FIELD]);
    }

    public static function sessionKey(AccountSession $session, string $submitted): ?string
    {
        if ($submitted !== "") {
            return strlen($submitted) === IdealisticOfficeLimit::SESSION_ID_LENGTH ? $submitted : null;
        }
        return $session->hasCookieKey() ? $session->createKey() : null;
    }

    public static function reply(bool $success, ?string $message, int $status = 200): void
    {
        http_response_code($status);
        header("Content-Type: application/json; charset=utf-8");
        $object = new stdClass();
        $object->success = $success;
        $object->message = $message;
        echo IdealisticOfficeReader::jsonObject($object, false);
    }

    public static function respondToPoll(string $fileLabel): void
    {
        header("Cache-Control: no-store");
        header("X-Content-Type-Options: nosniff");

        if (!self::isSameOrigin()) {
            self::reply(false, "Invalid request.", 403);
            return;
        }
        $submittedSession = trim((string)($_POST["session_id"] ?? ""));
        $afterId = $_POST["after_id"] ?? null;
        $clientState = trim((string)($_POST["state"] ?? ""));
        $randomId = trim((string)($_GET["id"] ?? ""));
        $defaultMessage = trim((string)($_GET["default_message"] ?? ""));
        $defaultUrl = trim((string)($_GET["default_url"] ?? ""));

        if ($defaultUrl === "") {
            $defaultUrl = null;
        }

        if (!empty($defaultMessage)
            && mb_strlen($defaultMessage) > IdealisticOfficeLimit::CHAT_PORTAL_DEFAULT_MESSAGE_LENGTH

            || !empty($defaultUrl) && !is_url($defaultUrl) && !is_domain($defaultUrl)

            || !empty($randomId)
            && strlen($randomId) !== IdealisticOfficeLimit::URL_RANDOM_ID_LENGTH
            && !is_numeric($randomId)) {
            self::reply(false, "Invalid input.");
            return;
        }
        $account = new Account(Account::IDEALISTIC_OFFICE_APPLICATION_ID);
        $session = $account->getSession();
        $sessionId = self::sessionKey($session, $submittedSession);

        if ($sessionId === null) {
            self::reply(false, "Invalid session.");
            return;
        }

        try {
            if (has_memory_cooldown(
                strtolower(IdealisticOfficeVariable::APPLICATION_NAME) . "_portal_poll_" . $sessionId,
                (int)round(IdealisticOfficeLimit::APPLICATION_DATA_ACTIVE_QUERY_MILLISECONDS / 2) . " milliseconds"
            )) {
                self::reply(false, "Too many " . IdealisticOfficeVariable::APPLICATION_NAME . " pages open or requests in a short time.");
                return;
            }
            $language = null;
            $hasSession = false;
            if ($submittedSession !== "") {
                $session->setCustomKey($sessionId);
            }

            if ($session->find(IdealisticOfficeVariable::DEFAULT_SESSION_CHECK_IP_ADDRESS)->isPositiveOutcome()) {
                $hasSession = true;
                $sessionUser = AbstractIdealisticOfficeTeam::findUserDynamic($account, false);

                if ($sessionUser instanceof IdealisticOfficeUser
                    && $sessionUser->hasEstablishedAccess()) {
                    $language = $sessionUser->getLanguageSelection(false)->getLanguage();
                }
            }
            $portal = IdealisticOfficePortalIndependent::getPortal(
                $randomId,
                empty($randomId) || !is_numeric($randomId),
                false,
                $defaultUrl
            );

            if ($portal === null) {
                self::reply(false, "Portal not found.");
                return;
            }
            $portal->setPlatformId(IdealisticOfficeAccessPlatform::NATIVE);

            if ($portal->isLoginRequired()
                && !$hasSession) {
                self::reply(false, "You must be logged in to access your message history.");
                return;
            }
            $built = self::build(
                $portal,
                $sessionId,
                get_client_ip_address(),
                $defaultMessage,
                is_numeric($afterId) ? max(intval($afterId), -1) : -1,
                $fileLabel,
                $language
            );

            if ($built instanceof IdealisticOfficeOutcome) {
                self::reply(false, $built->getRawMessage());
                return;
            }

            if ($clientState !== "" && hash_equals($built["state"], $clientState)) {
                http_response_code(204);
                return;
            }
            header("Content-Type: text/html; charset=utf-8");
            echo self::fragment($built, $language);
        } catch (Throwable $e) {
            self::reply(false, "An error occurred while processing the request.");
            IdealisticOfficeError::simpleStore($e);
        }
    }

}
