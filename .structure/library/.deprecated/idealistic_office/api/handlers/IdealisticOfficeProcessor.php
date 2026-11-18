<?php

namespace idealistic_office\api\handlers;

use GoogleSearchResult;
use idealistic_office\api\IdealisticOfficeVariable;
use idealistic_office\api\objects\helpers\AbstractIdealisticOfficeTeam;
use idealistic_office\api\objects\helpers\attachment\AbstractIdealisticOfficeAttachment;
use idealistic_office\api\objects\helpers\attachment\IdealisticOfficePartialAttachment;
use idealistic_office\api\objects\helpers\IdealisticOfficeAI;
use idealistic_office\api\objects\helpers\IdealisticOfficeOutcome;
use idealistic_office\api\objects\IdealisticOfficePrompt;
use idealistic_office\api\objects\IdealisticOfficeUser;
use IdealisticOfficeLimit;
use PhpAsyncSerializable;
use React\Promise\PromiseInterface;
use SerperDevGoogleSearch;
use function React\Promise\reject;
use function React\Promise\resolve;

class IdealisticOfficeProcessor implements PhpAsyncSerializable
{

    private static mixed $httpCode = null;

    public static function findAttachmentName(
        ?IdealisticOfficePrompt            $prompt,
        AbstractIdealisticOfficeTeam       $team,
        AbstractIdealisticOfficeAttachment $attachment
    ): PromiseInterface|string|null
    {
        try {
            $format = $attachment->getFormat();

            if (empty($format)) {
                return $prompt?->getLoop() === null
                    ? null
                    : resolve($attachment->getName());
            }
            $description = $attachment->getAnalyzedDescription() ?? $attachment->getUserPrompt();

            if (empty($description)) {
                return $prompt?->getLoop() === null
                    ? null
                    : resolve(null);
            }
            $object = new stdClass();
            $object->description = $description;
            $object->format = $format;
            $arguments = array(
                "messages" => array(
                    array(
                        "role" => "system",
                        "content" => "Find and return the most appropriate file name for the given attachment based on its description and format. "
                            . "Only return the file name with the correct file extension, without any additional text or explanation."
                    ),
                    array(
                        "role" => "user",
                        "content" => IdealisticOfficeReader::jsonObject($object)
                    )
                )
            );
            $AI = new IdealisticOfficeAI(
                $prompt ?? $team,
                IdealisticOfficeVariable::TASK_AI_IMAGE_READING_MODEL,
                __METHOD__
            );
            $AI->adjustCertainty(
                $arguments,
                IdealisticOfficeVariable::TASK_AI_LOW_VERBOSITY,
                IdealisticOfficeVariable::TASK_AI_NO_REASONING_EFFORT
            );
            $function = function ($result) {
                if (is_string($result)
                    && AbstractIdealisticOfficeAttachment::getFormatFromName($result) !== null) {
                    return $result;
                } else {
                    return null;
                }
            };

            if ($prompt?->getLoop() === null) {
                return $function(
                    $AI->getResult(
                        $arguments
                    )
                );
            } else {
                return $AI->getReactResult(
                    $arguments
                )->then($function);
            }
        } catch (Throwable $e) {
            IdealisticOfficeError::storeThrowable(
                $team,
                $prompt?->getUser(),
                $e
            );
            return $prompt?->getLoop() === null
                ? null
                : resolve(null);
        }
    }

    public static function analyzeAttachment(
        ?IdealisticOfficePrompt            $prompt,
        AbstractIdealisticOfficeTeam       $team,
        AbstractIdealisticOfficeAttachment $attachment,
        int                                $lengthLimit
    ): PromiseInterface|string|null
    {
        try {
            if ($attachment instanceof IdealisticOfficePartialAttachment) {
                $attachment = $attachment->upgrade();

                if ($attachment === null) {
                    return $prompt?->getLoop() === null
                        ? null
                        : resolve(null);
                }
            }
            $file = null;
            $arguments = array();
            $userInstructions = array();

            if ($attachment->isImage()) {
                $modelFamily = IdealisticOfficeVariable::TASK_AI_IMAGE_READING_MODEL;

                if ($attachment->isBase64()
                    && $attachment->getRawData() !== null) {
                    $object = new stdClass();
                    $object->type = "text";
                    $object->text = "Transcribe the image to words with a maximum character length of "
                        . $lengthLimit . ".";

                    $object1 = new stdClass();
                    $object1->type = "image_url";
                    $object1->image_url = new stdClass();
                    $object1->image_url->url = "data:" . $attachment->getFormat()
                        . ";base64," . $attachment->getRawData();
                    $userInstructions[] = array($object1, $object);
                } else {
                    return $prompt?->getLoop() === null
                        ? null
                        : resolve(null);
                }
            } else if ($attachment->isAudio()) {
                $modelFamily = IdealisticOfficeVariable::TASK_AI_VOICE_READING_MODEL;
                $data = $attachment->transferAudioData("mp3", array("wav"));

                if ($data !== null) {
                    while (true) {
                        $file = sys_get_temp_dir() . "/" . random_string(32) . "." . $data[0];

                        if (file_exists($file)) {
                            continue;
                        }
                        $put = @file_put_contents($file, base64_decode($data[1]));

                        if ($put === false) {
                            return $prompt?->getLoop() === null
                                ? null
                                : resolve(null);
                        }
                        break;
                    }
                    $arguments["file"] = new CURLFile($file);
                } else {
                    return $prompt?->getLoop() === null
                        ? null
                        : resolve(null);
                }
            } else if ($attachment->isVideo()) {
                $modelFamily = IdealisticOfficeVariable::TASK_AI_VOICE_READING_MODEL;
                $data = $attachment->transferVideoData("mp3");

                if ($data !== null) {
                    while (true) {
                        $file = sys_get_temp_dir() . "/" . random_string(32) . "." . $data[0];

                        if (file_exists($file)) {
                            continue;
                        }
                        $put = @file_put_contents($file, base64_decode($data[1]));

                        if ($put === false) {
                            return $prompt?->getLoop() === null
                                ? null
                                : resolve(null);
                        }
                        break;
                    }
                    $arguments["file"] = new CURLFile($file);
                } else {
                    return $prompt?->getLoop() === null
                        ? null
                        : resolve(null);
                }
            } else {
                $modelFamily = IdealisticOfficeVariable::TASK_AI_DOCUMENT_READING_MODEL;
                $data = $attachment->getDecodedData();

                if (is_string($data)
                    && !empty($data)) {
                    $extension = $attachment->getSimpleFormat();

                    while (true) {
                        $file = sys_get_temp_dir() . "/" . random_string(32) . "." . $extension;

                        if (file_exists($file)) {
                            continue;
                        }
                        $put = @file_put_contents($file, $data);

                        if ($put === false) {
                            return $prompt?->getLoop() === null
                                ? null
                                : resolve(null);
                        }
                        break;
                    }
                    $content = AbstractIdealisticOfficeAttachment::extractTextFromFile($file, $extension);

                    if ($content === null) {
                        @unlink($file);
                        return $prompt?->getLoop() === null
                            ? null
                            : resolve($data);
                    }
                    if (str_contains($data, "\0")) { // binary file
                        @unlink($file);
                        return $prompt?->getLoop() === null
                            ? null
                            : resolve(null);
                    }
                    if (strlen($content) <= $lengthLimit) {
                        @unlink($file);
                        return $prompt?->getLoop() === null
                            ? null
                            : resolve($content);
                    }
                    $object = new stdClass();
                    $object->type = "text";
                    $object->text = "Transcribe the contents of this document with a maximum character length of " . $lengthLimit . ".";

                    $object1 = new stdClass();
                    $object1->type = "text";
                    $object1->text = $content;
                    $userInstructions[] = array($object, $object1);
                } else {
                    return $prompt?->getLoop() === null
                        ? null
                        : resolve(null);
                }
            }
            $messages = array();

            if (!empty($userInstructions)) {
                foreach ($userInstructions as $instruction) {
                    $messages[] = array(
                        "role" => "user",
                        "content" => $instruction
                    );
                }
            }
            if (!empty($messages)) {
                $arguments["messages"] = $messages;
            }
            $AI = new IdealisticOfficeAI(
                $prompt ?? $team,
                $modelFamily,
                __METHOD__
            );

            if ($attachment->isImage()
                || !$attachment->canBeAudio()) {
                $AI->adjustCertainty(
                    $arguments,
                    IdealisticOfficeVariable::TASK_AI_LOW_VERBOSITY,
                    IdealisticOfficeVariable::TASK_AI_NO_REASONING_EFFORT
                );
            } else {
                $AI->adjustCertainty($arguments);
            }
            $function = function ($result) use ($file, $lengthLimit) {
                if ($file !== null) {
                    @unlink($file);
                }
                if (is_string($result)) {
                    if (mb_strlen($result) > $lengthLimit) {
                        $result = mb_substr($result, 0, $lengthLimit - 3) . "...";
                    }
                    return $result;
                } else {
                    return null;
                }
            };

            if ($prompt?->getLoop() === null) {
                return $function(
                    $AI->getResult(
                        $arguments
                    )
                );
            } else {
                return $AI->getReactResult(
                    $arguments
                )->then($function);
            }
        } catch (Throwable $e) {
            IdealisticOfficeError::storeThrowable(
                $team,
                $prompt?->getUser(),
                $e
            );
            return $prompt?->getLoop() === null
                ? null
                : resolve(null);
        }
    }

    public static function transcribeText(
        ?IdealisticOfficePrompt      $prompt,
        AbstractIdealisticOfficeTeam $team,
        string                       $input,
        string                       $format = "wav",
    ): PromiseInterface|string|null
    {
        $AI = new IdealisticOfficeAI(
            $prompt ?? $team,
            IdealisticOfficeVariable::TASK_AI_VOICE_CREATING_MODEL,
            __METHOD__
        );
        $function = function ($result) {
            if (is_string($result)) {
                return $result;
            } else {
                return null;
            }
        };
        $parameters = array(
            "voice" => "onyx", // best voice out of all tested
            "input" => $input,
            "response_format" => $format,
            "speed" => 1.2
        );

        if ($prompt?->getLoop() === null) {
            return $function(
                $AI->getResult(
                    $parameters,
                    null,
                    0
                )
            );
        } else {
            return $AI->getReactResult(
                $parameters,
                null,
                0
            )->then($function);
        }
    }

    public static function getLinkContents(string $url): bool|string
    {
        $maxRedirects = 5;
        $maxBytes = 10 * 1024 * 1024; // 10 MB response cap; adjust to taste

        for ($hop = 0; $hop <= $maxRedirects; $hop++) {
            $parsed = parse_url($url);
            $scheme = strtolower($parsed['scheme'] ?? '');
            $host = $parsed['host'] ?? null;

            if ($host === null || !in_array($scheme, ['http', 'https'], true)) {
                self::$httpCode = 0;
                return false;
            }

            // Re-validate on EVERY hop, not just the original URL. Resolve
            // every A/AAAA record (not just the first) and reject if any of
            // them is private/reserved (127.0.0.1, 10.x, 169.254.169.254,
            // etc.) — closes both the multi-A-record bypass and, combined
            // with CURLOPT_RESOLVE below, the DNS-rebinding TOCTOU where a
            // short-TTL record could point elsewhere by the time curl
            // actually connects.
            $records = array_merge(
                dns_get_record($host, DNS_A) ?: [],
                dns_get_record($host, DNS_AAAA) ?: []
            );

            if (empty($records)) {
                self::$httpCode = 0;
                return false;
            }
            $resolvedIp = null;

            foreach ($records as $record) {
                $ip = $record['ip'] ?? $record['ipv6'] ?? null;

                if ($ip === null
                    || !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    self::$httpCode = 0;
                    return false;
                }
                $resolvedIp ??= $ip;
            }
            $port = $parsed['port'] ?? ($scheme === 'https' ? 443 : 80);

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RESOLVE, ["{$host}:{$port}:{$resolvedIp}"]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
            curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
            curl_setopt($ch, CURLOPT_REDIR_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
            curl_setopt($ch, CURLOPT_TIMEOUT, IdealisticOfficeLimit::LINK_DOWNLOAD_SECONDS_TIMEOUT);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
            curl_setopt($ch, CURLOPT_MAXFILESIZE, $maxBytes);
            curl_setopt($ch, CURLOPT_TIMEOUT_MS, 0);

            $html = curl_exec($ch);
            $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $redirectUrl = curl_getinfo($ch, CURLINFO_REDIRECT_URL);
            curl_close($ch);

            self::$httpCode = $httpCode;

            if (in_array($httpCode, [301, 302, 303, 307, 308], true) && !empty($redirectUrl)) {
                $url = $redirectUrl;
                continue; // loop back and re-validate the new target from scratch
            }
            return $html;
        }
        // Too many redirects.
        self::$httpCode = 0;
        return false;
    }

    public static function getLastHttpCode(): mixed
    {
        return self::$httpCode;
    }

    public static function analyzeLink(
        ?IdealisticOfficePrompt      $prompt,
        AbstractIdealisticOfficeTeam $team,
        string                       $linkContent,
        int                          $lengthLimit
    ): PromiseInterface|string|null
    {
        try {
            $cleanText = strip_tags($linkContent);
            $wordLimit = intval($lengthLimit / 5);

            $arguments = array(
                "messages" => array(
                    array(
                        "role" => "system",
                        "content" => "You are a helpful text extraction assistant. Extract and summarize the main content from the provided text. Keep your response concise, non-technical and user-friendly to a maximum of approximately " . $wordLimit . " words. Do not include any HTML tags."
                    ),
                    array(
                        "role" => "user",
                        "content" => $cleanText
                    )
                )
            );
            $AI = new IdealisticOfficeAI(
                $prompt ?? $team,
                IdealisticOfficeVariable::TASK_AI_LINK_ANALYSIS_MODEL,
                __METHOD__
            );
            $AI->adjustCertainty(
                $arguments,
                IdealisticOfficeVariable::TASK_AI_LOW_VERBOSITY,
                IdealisticOfficeVariable::TASK_AI_NO_REASONING_EFFORT
            );
            $function = function ($result) use ($team, $prompt, $lengthLimit) {
                if (is_string($result)) {
                    if (mb_strlen($result) > $lengthLimit) {
                        $result = mb_substr($result, 0, $lengthLimit - 3) . "...";
                    }
                    return $result;
                } else {
                    return null;
                }
            };

            if ($prompt?->getLoop() === null) {
                return $function(
                    $AI->getResult(
                        $arguments
                    )
                );
            } else {
                return $AI->getReactResult(
                    $arguments
                )->then($function);
            }
        } catch (Throwable $e) {
            IdealisticOfficeError::storeThrowable(
                $team,
                $prompt?->getUser(),
                $e
            );
            return $prompt?->getLoop() === null
                ? null
                : reject($e);
        }
    }

    /** @return GoogleSearchResult[]|IdealisticOfficeOutcome */
    public static function search(
        AbstractIdealisticOfficeTeam|IdealisticOfficeUser|IdealisticOfficePrompt $reference,
        string                                                                   $query
    ): array|IdealisticOfficeOutcome
    {
        $search = new SerperDevGoogleSearch();

        if (!$search->isValid()) {
            return new IdealisticOfficeOutcome(
                false,
                "Search service is currently unavailable."
            );
        }
        if (!SerperDevGoogleSearch::isQueryValid($query)) {
            return new IdealisticOfficeOutcome(
                false,
                "Invalid news tracker '" . $query
                . "' Maximum length is " . SerperDevGoogleSearch::MAX_QUERY_LENGTH
                . " characters and maximum words is " . SerperDevGoogleSearch::MAX_QUERY_WORDS . "."
            );
        }
        $result = $search->fetch(
            $query,
            IdealisticOfficeLimit::WEB_SEARCH_MAX_RESULTS
        );

        if (is_string($result)) {
            return new IdealisticOfficeOutcome(
                false,
                $result
            );
        } else {
            IdealisticOfficeAI::storeCost(
                null,
                $reference,
                SerperDevGoogleSearch::US_DOLLAR_COST_PER_QUERY,
                SerperDevGoogleSearch::US_DOLLAR_COST_PER_QUERY,
                null,
                __METHOD__
            );
            return $result;
        }
    }

    public static function floatToInteger(float $value): int
    {
        return (int)round($value * 100_000_000);
    }

}
