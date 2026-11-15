<?php

class IdealisticOfficeBrand
{

    public const
        RAW_START = "<!--nobrand-->",
        RAW_END = "<!--/nobrand-->";

    private const
        TOKENS = '/(<!--.*?-->|<script\b.*?<\/script>|<style\b.*?<\/style>|<\/?[a-zA-Z][^\s>\/]*(?:"[^"]*"|\'[^\']*\'|[^>"\'])*>)/is',
        ATTRIBUTES = '/\b(content|alt|title|aria-label|placeholder|data-bs-title)(\s*=\s*)(["\'])(.*?)\3/is',
        SKIPPED_TAGS = array("textarea", "option", "code", "pre");

    private static bool $started = false;
    private static ?string $mention = null;

    private static function mention(): string
    {
        if (self::$mention === null) {
            $base = trim(str_replace(array("™", "®"), "", IdealisticOfficeVariable::APPLICATION_NAME));
            $legal = trim((string)substr(IdealisticOfficeVariable::COMPANY_NAME, strlen($base)));
            self::$mention = '/(?<![\w@.\/\-=])' . preg_quote($base, '/') . '(?!\w|@|:|\.[a-z]|\/|-[a-z])'
                . '(?:™|®|&trade;|&reg;|&#8482;|&#174;|&#x2122;|&#xae;)?(?:\s+AI\b)?'
                . ($legal === '' ? '' : '(?!\s+' . preg_quote($legal, '/') . ')')
                . '/iu';
        }
        return self::$mention;
    }

    public static function start(): void
    {
        if (self::$started) {
            return;
        }
        self::$started = true;
        ob_start(array(self::class, "filter"));
    }

    public static function plain(string $text): string
    {
        $result = @preg_replace(self::mention(), IdealisticOfficeVariable::APPLICATION_NAME, $text);
        return $result ?? $text;
    }

    public static function bold(string $text): string
    {
        $result = @preg_replace(self::mention(), "<strong>" . IdealisticOfficeVariable::APPLICATION_NAME . "</strong>", $text);
        return $result ?? $text;
    }

    public static function filter(string $buffer, int $phase = 0): string
    {
        if ($buffer === "") {
            return $buffer;
        }

        try {
            $type = "text/html";

            foreach (headers_list() as $header) {
                if (stripos($header, "content-type:") === 0) {
                    $type = strtolower(trim(substr($header, 13)));
                }
            }

            if (str_starts_with($type, "text/plain")) {
                return self::plain($buffer);
            }

            if (!str_starts_with($type, "text/html")) {
                return $buffer;
            }
            return self::html($buffer);
        } catch (Throwable $e) {
            return $buffer;
        }
    }

    public static function html(string $html): string
    {
        $parts = @preg_split(self::TOKENS, $html, -1, PREG_SPLIT_DELIM_CAPTURE);

        if ($parts === false) {
            return $html;
        }
        $output = "";
        $raw = false;
        $bold = 0;
        $skipped = 0;
        $inTitle = false;

        foreach ($parts as $index => $part) {
            if ($part === "") {
                continue;
            }

            if ($index % 2 === 0) {
                if ($raw || $skipped > 0) {
                    $output .= $part;
                } elseif ($inTitle || $bold > 0) {
                    $output .= self::plain($part);
                } else {
                    $output .= self::bold($part);
                }
                continue;
            }

            if (str_starts_with($part, "<!--")) {
                if ($part === self::RAW_START) {
                    $raw = true;
                } elseif ($part === self::RAW_END) {
                    $raw = false;
                }
                $output .= $part;
                continue;
            }

            if (stripos($part, "<script") === 0) {
                $output .= stripos($part, "ld+json") !== false ? self::plain($part) : $part;
                continue;
            }

            if (stripos($part, "<style") === 0) {
                $output .= $part;
                continue;
            }

            if (preg_match('/^<\s*(\/)?\s*([a-z0-9]+)/i', $part, $match) === 1) {
                $closing = $match[1] === "/";
                $tag = strtolower($match[2]);

                if ($tag === "strong" || $tag === "b") {
                    $bold = max(0, $bold + ($closing ? -1 : 1));
                } elseif ($tag === "title") {
                    $inTitle = !$closing;
                } elseif (in_array($tag, self::SKIPPED_TAGS, true)) {
                    $skipped = max(0, $skipped + ($closing ? -1 : 1));
                }

                if (!$closing && strpbrk($part, "=") !== false) {
                    $part = preg_replace_callback(
                        self::ATTRIBUTES,
                        fn(array $m) => $m[1] . $m[2] . $m[3] . self::plain($m[4]) . $m[3],
                        $part
                    ) ?? $part;
                }
            }
            $output .= $part;
        }
        return $output;
    }

}
