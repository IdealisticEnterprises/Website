<?php

class IdealisticOfficeLegal
{

    private const
        ACRONYMS = array("AI", "GDPR", "EEA", "NLP", "API", "IP", "UI", "URL", "PII", "EU", "UK", "VAT"),
        SMALL_WORDS = array("of", "and", "the", "to", "in", "a", "for", "or", "on", "an", "at", "by"),
        HEADING = '/^(?:(\d+)\.\s+)?([A-Z][A-Z0-9 &,\'\/()\-]{2,})$/u',
        SUBHEADING = '/^([a-z])\.\s+(.{3,})$/u';

    public static function render(string $text, string $canonicalUrl): void
    {
        header('Content-Type: text/html; charset=utf-8');
        header('Cache-Control: public, max-age=300');
        echo self::page($text, $canonicalUrl);
    }

    private static function words(string $text): string
    {
        $words = explode(" ", strtolower($text));

        foreach ($words as $index => $word) {
            $bare = strtoupper(trim($word, ",.&()"));

            if (in_array($bare, self::ACRONYMS, true)) {
                $words[$index] = str_ireplace(trim($word, ",.&()"), $bare, $word);
            } elseif ($index > 0 && in_array($word, self::SMALL_WORDS, true)) {
                continue;
            } else {
                $words[$index] = ucfirst($word);
            }
        }
        return implode(" ", $words);
    }

    private static function inline(string $text): string
    {
        $safe = io_e($text);
        $safe = preg_replace('/([A-Za-z0-9._%+\-]+@[A-Za-z0-9\-]+(?:\.[A-Za-z0-9\-]+)*\.[A-Za-z]{2,})/', '<a href="mailto:$1">$1</a>', $safe) ?? $safe;
        return preg_replace('/(?<![">])(https?:\/\/[^\s<]+[^\s<.,;:)])/', '<a href="$1" rel="noopener">$1</a>', $safe) ?? $safe;
    }

    private static function lead(string $text, int $maxLength, bool $allowCommas): string
    {
        if (preg_match('/^([^:]{2,' . $maxLength . '}):\s+(.+)$/su', $text, $match) === 1
            && !str_contains($match[1], ". ")
            && !str_contains($match[1], "http")
            && ($allowCommas || !str_contains($match[1], ","))) {
            return '<strong>' . io_e($match[1]) . ':</strong> ' . self::inline($match[2]);
        }
        return self::inline($text);
    }

    private static function slug(string $text, array $used): string
    {
        $slug = trim((string)preg_replace('/[^a-z0-9]+/', '-', strtolower($text)), '-');
        $slug = $slug === "" ? "section" : $slug;
        $candidate = $slug;
        $counter = 2;

        while (isset($used[$candidate])) {
            $candidate = $slug . "-" . $counter++;
        }
        return $candidate;
    }

    public static function parse(string $text): array
    {
        $lines = preg_split('/\R/u', trim(str_replace("\r", "", $text))) ?: array();
        $title = trim((string)array_shift($lines));
        $meta = array();

        while (!empty($lines) && trim($lines[0]) === "") {
            array_shift($lines);
        }

        while (!empty($lines) && preg_match('/^([A-Za-z][A-Za-z ]{1,30}):\s+(.+)$/u', $lines[0], $match) === 1) {
            $meta[] = array(trim($match[1]), trim($match[2]));
            array_shift($lines);
        }
        $sections = array(array("id" => "", "number" => null, "title" => "", "nodes" => array()));
        $used = array();
        $block = array();
        $flush = function () use (&$block, &$sections): void {
            if (empty($block)) {
                return;
            }
            $current = sizeof($sections) - 1;
            $bullets = array();
            $paragraph = array();
            $endParagraph = function () use (&$paragraph, &$sections, $current): void {
                if (empty($paragraph)) {
                    return;
                }
                $punctuated = true;

                foreach ($paragraph as $line) {
                    if (!preg_match('/[.!?:)]$/u', $line)) {
                        $punctuated = false;
                        break;
                    }
                }

                if ($punctuated || sizeof($paragraph) === 1) {
                    foreach ($paragraph as $line) {
                        $sections[$current]["nodes"][] = array("p", $line);
                    }
                } else {
                    $sections[$current]["nodes"][] = array("lines", $paragraph);
                }
                $paragraph = array();
            };
            $endBullets = function () use (&$bullets, &$sections, $current): void {
                if (!empty($bullets)) {
                    $sections[$current]["nodes"][] = array("ul", $bullets);
                    $bullets = array();
                }
            };

            foreach ($block as $line) {
                if (str_starts_with($line, "- ")) {
                    $endParagraph();
                    $bullets[] = trim(substr($line, 2));
                } elseif (preg_match(self::SUBHEADING, $line, $match) === 1) {
                    $endParagraph();
                    $endBullets();
                    $sections[$current]["nodes"][] = array("h3", $match[1] . ". " . $match[2]);
                } else {
                    $endBullets();
                    $paragraph[] = $line;
                }
            }
            $endParagraph();
            $endBullets();
            $block = array();
        };

        foreach ($lines as $line) {
            $line = rtrim($line);

            if (trim($line) === "") {
                $flush();
                continue;
            }

            if (preg_match(self::HEADING, $line, $match) === 1
                && preg_match('/[A-Z]{3,}/', $line) === 1) {
                $flush();
                $heading = self::words($match[2]);
                $id = self::slug($heading, $used);
                $used[$id] = true;
                $sections[] = array(
                    "id" => $id,
                    "number" => $match[1] === "" ? null : (int)$match[1],
                    "title" => $heading,
                    "nodes" => array()
                );
                continue;
            }
            $block[] = trim($line);
        }
        $flush();
        return array(
            "title" => self::words($title),
            "meta" => $meta,
            "intro" => array_shift($sections)["nodes"],
            "sections" => $sections
        );
    }

    private static function nodes(array $nodes): string
    {
        $html = "";

        foreach ($nodes as $node) {
            if ($node[0] === "p") {
                $html .= '<p>' . self::lead($node[1], 45, false) . '</p>';
            } elseif ($node[0] === "lines") {
                $html .= '<p>' . implode('<br>', array_map(fn($line) => self::inline($line), $node[1])) . '</p>';
            } elseif ($node[0] === "h3") {
                $html .= '<h3>' . io_e($node[1]) . '</h3>';
            } elseif ($node[0] === "ul") {
                $html .= '<ul>';

                foreach ($node[1] as $item) {
                    $html .= '<li>' . self::lead($item, 90, true) . '</li>';
                }
                $html .= '</ul>';
            }
        }
        return $html;
    }

    private static function plainText(array $nodes): string
    {
        foreach ($nodes as $node) {
            if ($node[0] === "p") {
                return $node[1];
            }
        }
        return "";
    }

    public static function page(string $text, string $canonicalUrl): string
    {
        require_once '/var/www/.structure/library/idealistic_office/api/tasks/website/v1/footer/index.php';

        $parsed = self::parse($text);
        $site = IdealisticOfficeVariable::COMPANY_WEBSITE_URL;
        $title = $parsed["title"] . " | " . IdealisticOfficeVariable::APPLICATION_NAME;
        $summary = self::plainText($parsed["intro"]);

        if ($summary === "" && !empty($parsed["sections"])) {
            $summary = self::plainText($parsed["sections"][0]["nodes"]);
        }
        $description = mb_strlen($summary) > 155 ? rtrim(mb_substr($summary, 0, 152)) . "..." : $summary;
        $documents = array(
            array("Privacy Policy", IdealisticOfficeVariable::PRIVACY_POLICY_URL),
            array("Terms of Use", IdealisticOfficeVariable::TERMS_OF_USE_URL),
            array("Data deletion", IdealisticOfficeVariable::USER_DATA_DELETION_INSTRUCTIONS_URL)
        );
        ob_start();
        ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= io_e($title) ?></title>
    <meta name="description" content="<?= io_e($description) ?>">
    <meta name="robots" content="index, follow">
    <meta name="theme-color" content="#06070a">
    <link rel="canonical" href="<?= io_e($canonicalUrl) ?>">
    <link rel="icon" type="image/svg+xml" href="<?= io_e($site) ?>/.images/favicon.svg">
    <link rel="icon" type="image/png" href="<?= io_e($site) ?>/.images/logoSquare.png">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="<?= IdealisticOfficeVariable::APPLICATION_NAME ?>">
    <meta property="og:url" content="<?= io_e($canonicalUrl) ?>">
    <meta property="og:title" content="<?= io_e($title) ?>">
    <meta property="og:description" content="<?= io_e($description) ?>">
    <meta property="og:image" content="<?= io_e($site) ?>/.images/social.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap">
    <link rel="stylesheet" href="<?= io_e(io_asset('.assets/css/landing.css')) ?>">
    <link rel="stylesheet" href="<?= io_e(io_asset('.assets/css/footer.css')) ?>">
    <link rel="stylesheet" href="<?= io_e(io_asset('.assets/css/legal.css')) ?>">
</head>
<body>
<a class="skip-link" href="#main">Skip to content</a>

<header class="island nav" id="nav">
    <a class="brand" href="<?= io_e($site) ?>/" aria-label="<?= IdealisticOfficeVariable::APPLICATION_NAME ?>">
        <img src="<?= io_e($site) ?>/.images/logoWhite.svg" alt="" width="30" height="30">
        <span><?= IdealisticOfficeVariable::APPLICATION_NAME ?></span>
    </a>
    <nav aria-label="Legal">
        <?php foreach ($documents as $document) : ?>
            <a href="<?= io_e($document[1]) ?>"<?= rtrim($document[1], '/') === rtrim($canonicalUrl, '/') ? ' aria-current="page"' : '' ?>><?= io_e($document[0]) ?></a>
        <?php endforeach; ?>
    </nav>
    <a class="button solid small" href="<?= io_e($site) ?>/">Home</a>
</header>

<main id="main">
    <div class="legal-head">
        <div class="wrap">
            <p class="eyebrow">Legal</p>
            <h1><?= io_e($parsed["title"]) ?></h1>
            <?php if (!empty($parsed["meta"])) : ?>
                <ul class="legal-meta">
                    <?php foreach ($parsed["meta"] as $item) : ?>
                        <li><span><?= io_e($item[0]) ?></span><?= self::inline($item[1]) ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <?php if (!empty($parsed["intro"])) : ?>
                <div class="legal-intro"><?= self::nodes($parsed["intro"]) ?></div>
            <?php endif; ?>
        </div>
    </div>

    <div class="wrap legal-layout<?= sizeof($parsed["sections"]) > 2 ? '' : ' single' ?>">
        <?php if (sizeof($parsed["sections"]) > 2) : ?>
            <nav class="legal-toc" aria-label="Contents">
                <p class="label">Contents</p>
                <?php foreach ($parsed["sections"] as $section) : ?>
                    <a href="#<?= io_e($section["id"]) ?>"><?= $section["number"] !== null ? '<span>' . io_e($section["number"]) . '</span>' : '' ?><?= io_e($section["title"]) ?></a>
                <?php endforeach; ?>
            </nav>
        <?php endif; ?>
        <div class="legal-body">
            <?php foreach ($parsed["sections"] as $section) : ?>
                <article class="island legal-section" id="<?= io_e($section["id"]) ?>">
                    <h2><?= $section["number"] !== null ? '<span>' . io_e(str_pad((string)$section["number"], 2, "0", STR_PAD_LEFT)) . '</span>' : '' ?><?= io_e($section["title"]) ?></h2>
                    <?= self::nodes($section["nodes"]) ?>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</main>

<?php io_footer(); ?>
</body>
</html>
        <?php
        return (string)ob_get_clean();
    }

}
