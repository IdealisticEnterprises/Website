<?php

namespace idealistic_office\api\handlers;

use Google\Service\Gmail\Message;
use Google\Service\Gmail\MessagePart;
use Google_Client;
use Google_Service_Gmail;
use Google_Service_Gmail_Message;
use Google_Service_Gmail_WatchRequest;
use idealistic_office\api\IdealisticOfficeVariable;
use idealistic_office\api\objects\helpers\attachment\IdealisticOfficeAttachment;
use IdealisticOfficeAccessPlatform;
use IdealisticOfficeLimit;
use IdealisticOfficeTable;
use PhpAsyncSerializable;

class IdealisticOfficeGmail implements PhpAsyncSerializable
{

    private Google_Service_Gmail $service;

    public function __construct()
    {
        $client = new Google_Client();
        $client->setAuthConfig("/var/www/.structure/private/google_idealistic_service_account.json");
        $client->setSubject(IdealisticOfficeVariable::SUPPORT_EMAIL);
        $client->setScopes(array(
            Google_Service_Gmail::GMAIL_MODIFY,
            Google_Service_Gmail::GMAIL_SEND,
            Google_Service_Gmail::GMAIL_READONLY
        ));
        $this->service = new Google_Service_Gmail($client);
    }

    public function getService(): Google_Service_Gmail
    {
        return $this->service;
    }

    public function getLastProcessedHistoryID(): ?string
    {
        $query = get_sql_query(
            IdealisticOfficeTable::GMAIL_TRACKING_TABLE,
            array(
                "history_id"
            ),
            array(
                array("deletion_date", null)
            ),
            array(
                "DESC",
                "id"
            ),
            1
        );
        return empty($query)
            ? null
            : $query[0]->history_id;
    }

    public function queue(string $emailId): void
    {
        IdealisticOfficeError::sqlInsert(
            null,
            null,
            IdealisticOfficeTable::GMAIL_QUEUE_TABLE,
            array(
                "email_id" => $emailId,
                "creation_date" => IdealisticOfficeReader::getCurrentDate(),
                "creation_time_zone" => IdealisticOfficeVariable::DEFAULT_TIME_ZONE
            )
        );
    }

    public function process(string $historyId, ?string $messageId, bool $isWatch = false): void
    {
        IdealisticOfficeError::sqlInsert(
            null,
            null,
            IdealisticOfficeTable::GMAIL_TRACKING_TABLE,
            array(
                "history_id" => $historyId,
                "message_id" => $messageId,
                "is_watch" => $isWatch,
                "creation_date" => IdealisticOfficeReader::getCurrentDate(),
                "creation_time_zone" => IdealisticOfficeVariable::DEFAULT_TIME_ZONE
            )
        );
    }

    public function isQueued(string $emailId): bool
    {
        $query = get_sql_query(
            IdealisticOfficeTable::GMAIL_QUEUE_TABLE,
            array(
                "id"
            ),
            array(
                array("email_id", $emailId),
                array("deletion_date", null)
            ),
            null,
            1
        );
        return !empty($query);
    }

    public function isProcessed(string $historyId, string $messageId): bool
    {
        $query = get_sql_query(
            IdealisticOfficeTable::GMAIL_TRACKING_TABLE,
            array(
                "id"
            ),
            array(
                array("deletion_date", null),
                null,
                array("history_id", "=", $historyId, 0),
                array("message_id", $messageId),
                null
            ),
            null,
            1
        );
        return !empty($query);
    }

    public function newWatch(): ?string
    {
        $request = new Google_Service_Gmail_WatchRequest([
            'topicName' => 'projects/crucial-oarlock-468117-g3/topics/gmail-notify',
        ]);
        try {
            $response = $this->service->users->watch("me", $request);
        } catch (Throwable $e) {
            IdealisticOfficeError::simpleStore('Gmail API Watch Error: ' . $e->getMessage());
            return null;
        }
        $historyID = $response?->getHistoryId();

        if (is_numeric($historyID)) {
            $this->process($historyID, null, true);
            return $historyID;
        }
        return null;
    }

    public function send(
        ?string $threadId,
        string  $to,
        string  $subject,
        ?string $messageId,
        string  $body,
        array   $attachments = []
    ): Message
    {
        $replyTo = preg_replace('/.*<(.+)>/', '$1', $to);
        $replySubject = preg_match('/^Re:/i', $subject) ? $subject : 'Re: ' . $subject;
        $boundary = 'b_' . md5(uniqid((string)microtime(true), true));
        $eol = "\r\n";
        $rawMessage = 'From: me' . $eol;
        $rawMessage .= 'To: ' . $replyTo . $eol;
        $rawMessage .= 'Subject: ' . $replySubject . $eol;
        $rawMessage .= 'Reply-To: ' . IdealisticOfficeVariable::REPLY_EMAIL . $eol;

        if ($messageId !== null) {
            $rawMessage .= 'In-Reply-To: ' . $messageId . $eol;
            $rawMessage .= 'References: ' . $messageId . $eol;
        }
        $rawMessage .= 'MIME-Version: 1.0' . $eol;
        $rawMessage .= 'Content-Type: multipart/mixed; boundary="' . $boundary . '"' . $eol . $eol;
        $rawMessage .= '--' . $boundary . $eol;
        $rawMessage .= 'Content-Type: text/plain; charset=UTF-8' . $eol;
        $rawMessage .= 'Content-Transfer-Encoding: 7bit' . $eol . $eol;
        $rawMessage .= $body . $eol . $eol;

        if (!empty($attachments)) {
            $count = 0;
            $countLimit = IdealisticOfficeLimit::ATTACHMENT_LIMIT[IdealisticOfficeAccessPlatform::EMAIL] ?? null;
            $bytesLimit = IdealisticOfficeLimit::ATTACHMENT_BYTES_LIMIT[IdealisticOfficeAccessPlatform::EMAIL] ?? null;

            if ($bytesLimit !== null) {
                $bytesLimit = min($bytesLimit, IdealisticOfficeLimit::MAX_ATTACHMENT_BYTES_SIZE);
            }
            foreach ($attachments as $att) {
                if (!($att instanceof IdealisticOfficeAttachment)

                    || $bytesLimit !== null
                    && $att->getBytes() > $bytesLimit) {
                    continue;
                }
                $filename = $att->getName();

                if ($att->isBase64()) {
                    $binary = $att->getRawData();
                } else {
                    $binary = base64_encode($att->getRawData());
                }
                $b64 = chunk_split($binary, 76, $eol);
                $rawMessage .= '--' . $boundary . $eol;
                $rawMessage .= 'Content-Type: ' . $att->getFormat(true) . '; name="' . $filename . '"' . $eol;
                $rawMessage .= 'Content-Transfer-Encoding: base64' . $eol;
                $rawMessage .= 'Content-Disposition: attachment; filename="' . $filename . '"' . $eol . $eol;
                $rawMessage .= $b64 . $eol . $eol;

                if ($countLimit !== null) {
                    $count++;

                    if ($count === $countLimit) {
                        break;
                    }
                }
            }
        }
        $rawMessage .= '--' . $boundary . '--' . $eol;
        $b64Raw = base64_encode($rawMessage);
        $b64Url = rtrim(strtr($b64Raw, '+/', '-_'), '=');

        $gmailMessage = new Google_Service_Gmail_Message();
        $gmailMessage->setRaw($b64Url);

        if ($threadId !== null) {
            $gmailMessage->setThreadId($threadId);
        }
        return $this->service->users_messages->send('me', $gmailMessage);
    }

    public function extractMessageBody(MessagePart $part): array
    {
        $mime = $part->getMimeType();
        $body = $part->getBody();
        $data = $body?->getData();

        if ($data !== null
            && ($mime === 'text/plain'
                || $mime === 'text/html')) {
            $decoded = base64_decode(strtr($data, '-_', '+/'));

            if ($decoded !== false) {
                return ['type' => $mime, 'data' => $decoded];
            }
        }
        $subparts = $part->getParts();

        if (!empty($subparts)) {
            $htmlCandidate = null;

            foreach ($subparts as $sub) {
                $res = $this->extractMessageBody($sub);

                if ($res['data']) {
                    if ($res['type'] === 'text/plain') {
                        return $res;
                    }
                    if ($res['type'] === 'text/html') {
                        $htmlCandidate = $res;
                    }
                }
            }
            if ($htmlCandidate) {
                return $htmlCandidate;
            }
        }
        return ['type' => null, 'data' => null];
    }

    public function extractUserReply(?string $body, bool $isHtml = false): ?string
    {
        if (empty($body)) {
            return null;
        }
        if ($isHtml) {
            libxml_use_internal_errors(true);
            $dom = new DOMDocument();
            $dom->loadHTML('<?xml encoding="utf-8" ?>' . $body);
            $xpath = new DOMXPath($dom);

            foreach ($xpath->query('//blockquote') as $node) {
                $node->parentNode->removeChild($node);
            }
            foreach ($xpath->query('//*[contains(@class,"gmail_quote") or contains(@class,"quoted")]') as $node) {
                $node->parentNode->removeChild($node);
            }
            $body = $dom->textContent;
            $body = html_entity_decode($body, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
        $text = str_replace(["\r\n", "\r"], "\n", $body);
        $text = preg_replace("/\n{3,}/", "\n\n", $text); // collapse excessive blank lines
        $text = trim($text);
        $lines = preg_split("/\n/", $text);
        $filtered = [];

        foreach ($lines as $line) {
            if (preg_match('/^\s*>/', $line)) {
                continue;
            }
            $filtered[] = $line;
        }
        $text = implode("\n", $filtered);
        $separatorPatterns = [
            // Common "On ... wrote:" (English)
            '/\nOn\s.+?wrote:\s*$/im',                         // line ending with "On ... wrote:"
            '/\nOn\s.+?wrote:/im',                            // "On ... wrote:" anywhere
            // Common header blocks
            '/\nFrom:\s.*\nSent:\s.*\nTo:\s.*\nSubject:\s.*\n?/is',
            '/\n-{2,}\s*Original Message\s*-{2,}/i',
            '/\n-{2,}\s*Forwarded message\s*-{2,}/i',
            // Gmail style '-----Original Message-----' variations
            '/\n----+ ?Original Message ?-*\n/i',
            // Lines that begin with "From:" at start of line (catch many clients)
            '/\nFrom:\s.*\n/i',
            // Generic separator often inserted by clients
            '/\n-{4,}\n/',
            // Divider used by many clients
            '/\n_{4,}\n/',
        ];
        $earliestPos = null;

        foreach ($separatorPatterns as $pat) {
            if (preg_match($pat . 'm', $text, $m, PREG_OFFSET_CAPTURE)) {
                $pos = $m[0][1];
                if ($earliestPos === null || $pos < $earliestPos) {
                    $earliestPos = $pos;
                }
            }
        }
        if ($earliestPos !== null) {
            $text = substr($text, 0, $earliestPos);
        }
        if (preg_match('/\n--\s*\n/s', $text, $m, PREG_OFFSET_CAPTURE)) {
            $text = substr($text, 0, $m[0][1]);
        } else {
            $text = preg_replace('/\n(?:Thanks|Regards|Best|Sincerely|Cheers)[\s\S]{0,200}$/i', '', $text);
        }
        $text = preg_replace('/^On .+ wrote:.*$/im', '', $text);
        $text = preg_replace("/\n{3,}/", "\n\n", trim($text));
        return trim($text);
    }

}