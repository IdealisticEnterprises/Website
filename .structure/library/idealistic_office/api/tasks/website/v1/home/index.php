<?php

require_once '/var/www/.structure/library/idealistic_office/api/tasks/website/v1/footer/index.php';

IdealisticOfficeBrand::start();

$siteUrl = IdealisticOfficeVariable::COMPANY_WEBSITE_URL . '/';
$assetBase = IdealisticOfficeVariable::COMPANY_WEBSITE_URL;
$supportEmail = IdealisticOfficeVariable::SUPPORT_EMAIL;
$businessEmail = IdealisticOfficeVariable::BUSINESS_EMAIL;
$tryUrl = IdealisticOfficeVariable::PORTAL_CHAT_URL;
$title = IdealisticOfficeWebsite::TITLE;
$description = IdealisticOfficeWebsite::DESCRIPTION;
$groups = IdealisticOfficeWebsite::PLATFORM_GROUPS;
$isApp = IdealisticOfficeWebsite::isNativeApp($_SERVER['HTTP_USER_AGENT'] ?? null);
$faq = IdealisticOfficeWebsite::getFaq(!$isApp);
$comingSoon = IdealisticOfficeWebsite::COMING_SOON;

header('Cache-Control: private, no-cache');
header('Vary: User-Agent');
$currencySymbols = ['EUR' => '€', 'USD' => '$', 'GBP' => '£'];
$currency = $currencySymbols[IdealisticOfficePlans::CURRENCY] ?? (IdealisticOfficePlans::CURRENCY . ' ');

function io_price(float $amount): string
{
    return $amount == floor($amount) ? number_format($amount, 0, '.', ',') : number_format($amount, 2, '.', ',');
}

$plans = [];

foreach (IdealisticOfficePlans::PLANS as $position => $plan) {
    $saving = IdealisticOfficePlans::getSaving($position);
    $plans[] = [
        'name' => IdealisticOfficePlans::NAMES[$position] ?? ('Plan ' . ($position + 1)),
        'slug' => IdealisticOfficePlans::getSlug(IdealisticOfficePlans::NAMES[$position] ?? ('plan' . ($position + 1))),
        'price' => (float)$plan['price'],
        'credits' => $plan['credits'],
        'club' => $position >= IdealisticOfficePlans::CLUB_FROM_PLAN,
        'logo_removal' => !$plan['has_logo'],
        'trial' => $plan['trial'],
        'history' => $plan['context'],
        'price_id' => $plan['paddle_price_id'],
        'rate' => sprintf(IdealisticOfficePlans::RATE_TEXT, $currency . number_format(IdealisticOfficePlans::getPricePerThousandCredits($position), 2, '.', ',')),
        'saving' => $saving === null
            ? null
            : sprintf(IdealisticOfficePlans::SAVING_TEXT, $currency . io_price($saving['amount']), IdealisticOfficePlans::NAMES[0])
    ];
}

$schemas = [
    [
        '@context' => 'https://schema.org',
        '@graph' => [
            [
                '@type' => 'Organization',
                '@id' => $siteUrl . '#organization',
                'name' => IdealisticOfficeVariable::APPLICATION_NAME,
                'legalName' => IdealisticOfficeVariable::COMPANY_NAME,
                'url' => $siteUrl,
                'logo' => $assetBase . '/.images/logoSquare.png',
                'email' => $supportEmail,
                'contactPoint' => [
                    ['@type' => 'ContactPoint', 'email' => $supportEmail, 'contactType' => 'customer support'],
                    ['@type' => 'ContactPoint', 'email' => $businessEmail, 'contactType' => 'sales']
                ],
                'sameAs' => [IdealisticOfficeVariable::GITHUB_URL, IdealisticOfficeVariable::YOUTUBE_URL]
            ],
            [
                '@type' => 'WebSite',
                '@id' => $siteUrl . '#website',
                'url' => $siteUrl,
                'name' => IdealisticOfficeVariable::APPLICATION_NAME,
                'inLanguage' => 'en',
                'publisher' => ['@id' => $siteUrl . '#organization']
            ],
            [
                '@type' => 'SoftwareApplication',
                'name' => IdealisticOfficeVariable::APPLICATION_NAME,
                'description' => $description,
                'url' => $siteUrl,
                'image' => $assetBase . '/.images/social.png',
                'applicationCategory' => 'CommunicationApplication',
                'operatingSystem' => 'iOS, Android, Web, Discord, Telegram, WhatsApp',
                'publisher' => ['@id' => $siteUrl . '#organization'],
                'offers' => $isApp ? [] : array_map(function ($plan) use ($comingSoon) {
                    return [
                        '@type' => 'Offer',
                        'name' => $plan['name'] . ', ' . IdealisticOfficePlans::TYPE . ', ' . number_format($plan['credits']) . ' credits',
                        'price' => number_format($plan['price'], 2, '.', ''),
                        'priceCurrency' => IdealisticOfficePlans::CURRENCY,
                        'availability' => $comingSoon ? 'https://schema.org/PreOrder' : 'https://schema.org/InStock'
                    ];
                }, $plans)
            ]
        ]
    ],
    [
        '@context' => 'https://schema.org',
        '@type' => 'FAQPage',
        'mainEntity' => array_map(function ($item) {
            return ['@type' => 'Question', 'name' => $item['q'], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => strip_tags($item['a'])]];
        }, $faq)
    ]
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script>document.documentElement.className += ' js';</script>
    <title><?= io_e($title) ?></title>
    <meta name="description" content="<?= io_e($description) ?>">
    <meta name="robots" content="index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1">
    <meta name="theme-color" content="#06070a">
    <meta name="author" content="<?= IdealisticOfficeVariable::APPLICATION_NAME ?>">
    <link rel="canonical" href="<?= io_e($siteUrl) ?>">
    <link rel="icon" type="image/svg+xml" href="<?= io_e($assetBase) ?>/.images/favicon.svg">
    <link rel="icon" type="image/png" href="<?= io_e($assetBase) ?>/.images/logoSquare.png">
    <link rel="apple-touch-icon" href="<?= io_e($assetBase) ?>/.images/logoSquare.png">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="<?= IdealisticOfficeVariable::APPLICATION_NAME ?>">
    <meta property="og:locale" content="en_US">
    <meta property="og:url" content="<?= io_e($siteUrl) ?>">
    <meta property="og:title" content="<?= io_e($title) ?>">
    <meta property="og:description" content="<?= io_e($description) ?>">
    <meta property="og:image" content="<?= io_e($assetBase) ?>/.images/social.png">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:image:alt" content="<?= IdealisticOfficeVariable::APPLICATION_NAME ?>: humans talk, AI keeps track">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= io_e($title) ?>">
    <meta name="twitter:description" content="<?= io_e($description) ?>">
    <meta name="twitter:image" content="<?= io_e($assetBase) ?>/.images/social.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap">
    <link rel="stylesheet" href="<?= io_e(io_asset('.assets/css/landing.css')) ?>">
    <link rel="stylesheet" href="<?= io_e(io_asset('.assets/css/footer.css')) ?>">
    <?php foreach ($schemas as $schema) : ?>
        <script type="application/ld+json"><?= json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?></script>
    <?php endforeach; ?>
</head>
<body>
<a class="skip-link" href="#main">Skip to content</a>

<header class="island nav" id="nav">
    <a class="brand" href="<?= io_e($siteUrl) ?>" aria-label="<?= IdealisticOfficeVariable::APPLICATION_NAME ?>">
        <img src="<?= io_e($assetBase) ?>/.images/logoWhite.svg" alt="" width="30" height="30">
        <span><?= IdealisticOfficeVariable::APPLICATION_NAME ?></span>
    </a>
    <nav aria-label="Main">
        <a href="#how">How it works</a>
        <a href="#install">Deploy</a>
        <a href="#setup">Commands &amp; setup</a>
        <?php if (!$isApp) : ?>
        <a href="#pricing">Pricing</a>
        <?php endif; ?>
        <a href="#data">Data</a>
        <a href="#faq">FAQ</a>
        <a href="<?= io_e(IdealisticOfficeVariable::PORTAL_STRUCTURE_URL) ?>">Structure</a>
    </nav>
    <a class="button solid small" id="nav-try" href="<?= io_e($tryUrl) ?>">Try it now</a>
</header>

<main id="main">
    <section class="hero">
        <div class="hero-mark" aria-hidden="true"><img src="<?= io_e($assetBase) ?>/.images/logoWhite.svg" alt="" width="640" height="640"></div>
        <div class="wrap">
            <?php if ($comingSoon) : ?>
                <p class="soon-badge"><?= io_e(IdealisticOfficeWebsite::COMING_SOON_LABEL) ?></p>
            <?php endif; ?>
            <p class="eyebrow">Discord &middot; Telegram &middot; WhatsApp &middot; Web</p>
            <h1>Humans talk.<br><span>AI keeps track.</span></h1>
            <p class="lead"><?= io_e(IdealisticOfficeWebsite::HERO_LEAD) ?></p>
            <div class="actions">
                <a class="button solid large" href="<?= io_e(IdealisticOfficeVariable::DISCORD_APP_URL) ?>">Add to Discord</a>
                <a class="button large" data-try href="<?= io_e($tryUrl) ?>">Try it now</a>
                <a class="button large" href="<?= io_e(IdealisticOfficeVariable::PORTAL_STRUCTURE_URL) ?>">Structure</a>
            </div>
            <div class="island strip" aria-label="Where it works">
                <?php foreach ($groups as $group) : ?>
                    <span><?= io_e($group['id'] === 'install' ? 'Deploy on' : 'Configure from') ?></span>
                    <?php if ($group['id'] === 'install') : ?>
                        <?php foreach ($group['items'] as $item) : ?>
                            <a href="<?= io_e($item['url']) ?>"><?= io_e($item['name']) ?></a>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <a href="#configure">Every app</a>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
            <div class="island trust" aria-label="About the company">
                <?php foreach (IdealisticOfficeWebsite::TRUST as $item) : ?>
                    <div>
                        <strong><?= io_e($item['title']) ?></strong>
                        <p><?= io_e($item['text']) ?></p>
                        <a href="<?= io_e($item['url']) ?>"><?= io_e($item['label']) ?> <i aria-hidden="true">&rarr;</i></a>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <section id="how" class="reveal">
        <div class="wrap">
            <p class="label">How it works</p>
            <h2>A conversation with a memory.</h2>
            <div class="grid three">
                <?php foreach (IdealisticOfficeWebsite::STEPS as $index => $step) : ?>
                    <article class="island step reveal" style="--i: <?= $index ?>">
                        <span class="number"><?= io_e($step['number']) ?></span>
                        <h3><?= io_e($step['title']) ?></h3>
                        <p><?= io_e($step['text']) ?></p>
                    </article>
                <?php endforeach; ?>
            </div>

            <div class="demo reveal">
                <div class="island chat" aria-label="Example conversation">
                    <p class="chat-tag">Example conversation</p>
                    <div class="msg"><b>Alex</b><span>I thought we agreed to launch on Friday.</span></div>
                    <div class="msg"><b>Sam</b><span>No, I said Friday only if the design is approved.</span></div>
                    <div class="msg"><b>Alex</b><span>@Idealistic what did we actually decide?</span></div>
                    <div class="msg ai"><b><?= IdealisticOfficeVariable::APPLICATION_NAME ?></b><span>Sam proposed Friday on one condition: the design must be approved first. Alex agreed to review it by Wednesday. The design is still waiting for approval.</span></div>
                </div>
                <div class="demo-text">
                    <h3>It never joins uninvited.</h3>
                    <p><?= IdealisticOfficeVariable::APPLICATION_NAME ?> listens and keeps track, but it only answers after a human tags it. Everything it says is triggered by a person, so it never talks over anyone and never makes up a conversation of its own.</p>
                </div>
            </div>
        </div>
    </section>

    <section class="reveal">
        <div class="wrap">
            <p class="label">What it fixes</p>
            <h2>Settle it. Calm it down. Bring it back to life.</h2>
            <div class="grid three">
                <?php foreach (IdealisticOfficeWebsite::FIXES as $index => $fix) : ?>
                    <article class="island fix reveal" style="--i: <?= $index ?>">
                        <h3><?= io_e($fix['word']) ?></h3>
                        <p><?= io_e($fix['text']) ?></p>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <?php foreach ($groups as $group) : ?>
        <section id="<?= io_e($group['id']) ?>" class="reveal">
            <div class="wrap">
                <p class="label"><?= io_e($group['label']) ?></p>
                <h2><?= io_e($group['title']) ?></h2>
                <p class="section-text"><?= io_e($group['text']) ?></p>
                <div class="grid <?= count($group['items']) === 4 ? 'four' : 'three' ?>">
                    <?php foreach ($group['items'] as $index => $item) : ?>
                        <a class="island platform reveal" style="--brand: <?= io_e($item['color']) ?>; --i: <?= $index ?>" href="<?= io_e($item['url']) ?>">
                            <span class="mark" aria-hidden="true"><?= io_e($item['mark']) ?></span>
                            <h3><?= io_e($item['name']) ?></h3>
                            <p><?= io_e($item['text']) ?></p>
                            <span class="action"><?= io_e($item['action']) ?> <i aria-hidden="true">&rarr;</i></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
    <?php endforeach; ?>

    <section id="setup" class="reveal">
        <div class="wrap">
            <p class="label">Commands &amp; setup</p>
            <h2>Up and running in a minute.</h2>
            <p class="section-text"><?= io_e(IdealisticOfficeWebsite::COMMANDS_TEXT) ?></p>
            <div class="island setup" data-tabs>
                <div class="tablist" role="tablist" aria-label="Choose where to set it up">
                    <?php foreach (IdealisticOfficeWebsite::SETUP_TABS as $index => $tab) : ?>
                        <button type="button" role="tab" id="tab-<?= io_e($tab['id']) ?>" aria-controls="panel-<?= io_e($tab['id']) ?>"
                                aria-selected="<?= $index === 0 ? 'true' : 'false' ?>"><?= io_e($tab['name']) ?></button>
                    <?php endforeach; ?>
                </div>
                <?php foreach (IdealisticOfficeWebsite::SETUP_TABS as $index => $tab) : ?>
                    <div class="tabpanel" role="tabpanel" id="panel-<?= io_e($tab['id']) ?>" aria-labelledby="tab-<?= io_e($tab['id']) ?>"<?= $index === 0 ? '' : ' data-inactive' ?>>
                        <h3 class="print-title"><?= io_e($tab['name']) ?></h3>
                        <ol>
                            <?php foreach ($tab['steps'] as $step) : ?>
                                <li><?= $step ?></li>
                            <?php endforeach; ?>
                        </ol>
                        <?php if (!empty($tab['commands'])) : ?>
                            <div class="commands">
                                <?php foreach ($tab['commands'] as $command) : ?>
                                    <div class="command">
                                        <code><?= io_e($command['command']) ?></code>
                                        <p><?= io_e($command['text']) ?></p>
                                        <small><?= io_e($command['requires']) ?></small>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($tab['code'])) : ?>
                            <pre><code><?= io_e($tab['code']) ?></code></pre>
                        <?php endif; ?>
                        <?php if (!empty($tab['note'])) : ?>
                            <p class="tab-note"><?= io_e($tab['note']) ?></p>
                        <?php endif; ?>
                        <a class="button solid"<?= $tab['link'][0] === $tryUrl ? ' data-try' : '' ?> href="<?= io_e($tab['link'][0]) ?>"><?= io_e($tab['link'][1]) ?></a>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <?php if (!$isApp) : ?>
    <section id="pricing" class="reveal">
        <div class="wrap">
            <p class="label">Pricing</p>
            <h2><?php if ($comingSoon) : ?><?= io_e(IdealisticOfficeWebsite::COMING_SOON_PRICING_TITLE) ?><?php else : ?>Start free for <?= io_e(preg_replace('/ free trial$/', '', IdealisticOfficePlans::FREE_TRIAL)) ?> with <?= io_e(IdealisticOfficePlans::NAMES[0]) ?>.<?php endif; ?></h2>
            <?php if ($comingSoon) : ?>
                <p class="soon-note"><?= IdealisticOfficeWebsite::COMING_SOON_NOTE ?></p>
            <?php endif; ?>
            <p class="section-text"><?= io_e(IdealisticOfficePlans::TYPE) ?>. Pick the amount of credits that fits how much your chats talk to <?= IdealisticOfficeVariable::APPLICATION_NAME ?>.</p>
            <div class="grid four plans">
                <?php foreach ($plans as $index => $plan) : ?>
                    <article class="island plan reveal" id="<?= io_e($plan['slug']) ?>" style="--i: <?= $index ?>">
                        <h3 class="plan-name"><?= io_e($plan['name']) ?></h3>
                        <p class="plan-credits"><?= io_e(number_format($plan['credits'])) ?> credits</p>
                        <p class="plan-price"><span><?= io_e($currency) ?></span><?= io_e(io_price($plan['price'])) ?><small>/ month</small></p>
                        <p class="plan-rate"><?= io_e($plan['rate']) ?></p>
                        <?php if ($plan['saving'] !== null) : ?>
                            <p class="plan-saving"><?= io_e($plan['saving']) ?></p>
                        <?php endif; ?>
                        <ul>
                            <?php if ($plan['trial']) : ?>
                                <li><?= io_e(IdealisticOfficePlans::FREE_TRIAL) ?></li>
                            <?php endif; ?>
                            <li>Deploy on Discord, Telegram, WhatsApp and the chat widget</li>
                            <li>Configure from any app</li>
                            <li><?= io_e(IdealisticOfficePlans::HISTORY_TEXT) ?>: <?= io_e(number_format($plan['history'])) ?> characters</li>
                            <?php if ($plan['club']) : ?>
                                <li class="extra"><?= io_e(IdealisticOfficePlans::CLUB_NAME) ?></li>
                            <?php endif; ?>
                            <?php if ($plan['logo_removal']) : ?>
                                <li class="extra"><?= io_e(IdealisticOfficePlans::LOGO_REMOVAL_TEXT) ?></li>
                            <?php else : ?>
                                <li><?= io_e(IdealisticOfficePlans::LOGO_TEXT) ?></li>
                            <?php endif; ?>
                        </ul>
                        <?php if ($comingSoon) : ?>
                            <button type="button" class="button solid" disabled><?= io_e(IdealisticOfficeWebsite::COMING_SOON_LABEL) ?></button>
                        <?php else : ?>
                            <button type="button" class="button solid" data-paddle-price="<?= io_e($plan['price_id']) ?>" data-fallback="mailto:<?= io_e($supportEmail) ?>"><?= io_e($plan['trial'] ? IdealisticOfficePlans::CHECKOUT_TEXT : IdealisticOfficePlans::SUBSCRIBE_TEXT) ?></button>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
                <article class="island plan enterprise reveal" id="<?= io_e(IdealisticOfficePlans::getSlug(IdealisticOfficePlans::ENTERPRISE_NAME)) ?>" style="--i: <?= count($plans) ?>">
                    <h3 class="plan-name"><?= io_e(IdealisticOfficeWebsite::ENTERPRISE_TITLE) ?></h3>
                    <p class="plan-credits">By email only</p>
                    <p class="plan-price plan-custom">Custom</p>
                    <ul>
                        <li><?= io_e(IdealisticOfficeWebsite::ENTERPRISE_TEXT) ?></li>
                        <li><?= io_e(IdealisticOfficePlans::HISTORY_TEXT) ?>: <?= io_e(number_format(IdealisticOfficePlans::ENTERPRISE_HISTORY_BUDGET_CHARACTERS)) ?> characters</li>
                        <li class="extra"><?= io_e(IdealisticOfficePlans::CLUB_NAME) ?></li>
                        <li class="extra"><?= io_e(IdealisticOfficePlans::LOGO_REMOVAL_TEXT) ?></li>
                        <li class="extra"><?= io_e(IdealisticOfficePlans::STATISTICS_PANEL_NAME) ?></li>
                    </ul>
                    <a class="button solid" href="mailto:<?= io_e($businessEmail) ?>?subject=<?= rawurlencode(IdealisticOfficeVariable::APPLICATION_NAME . ' Enterprise') ?>"><?= io_e($businessEmail) ?></a>
                </article>
            </div>
            <p class="fine-print"><?= io_e(IdealisticOfficePlans::CURRENCY) ?>. VAT: <?= io_e(IdealisticOfficePlans::VAT) ?>. <?= io_e(IdealisticOfficePlans::CHECKOUT_NOTE) ?></p>
        </div>
    </section>
    <?php endif; ?>

    <section id="data" class="reveal">
        <div class="wrap">
            <p class="label">Your data</p>
            <h2><?= io_e(IdealisticOfficeWebsite::RETENTION_TITLE) ?></h2>
            <p class="section-text"><?= io_e(IdealisticOfficeWebsite::RETENTION_TEXT) ?></p>
            <div class="grid three">
                <?php foreach (IdealisticOfficeWebsite::RETENTION_FACTS as $index => $fact) : ?>
                    <article class="island step reveal" style="--i: <?= $index ?>">
                        <span class="number"><?= io_e($fact['value']) ?></span>
                        <h3><?= io_e($fact['unit']) ?></h3>
                        <p><?= io_e($fact['text']) ?></p>
                    </article>
                <?php endforeach; ?>
            </div>
            <div class="island promises reveal">
                <?php foreach (IdealisticOfficeWebsite::RETENTION_PROMISES as $promise) : ?>
                    <div>
                        <strong><?= io_e($promise['title']) ?></strong>
                        <p><?= io_e($promise['text']) ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
            <p class="fine-print">Read the full details in the <a href="<?= io_e(IdealisticOfficeVariable::PRIVACY_POLICY_URL) ?>">Privacy Policy</a>.</p>
        </div>
    </section>


    <section id="open-source" class="reveal">
        <div class="wrap">
            <div class="grid two">
                <article class="island human reveal">
                    <p class="label">Who builds it</p>
                    <h2><?= io_e(IdealisticOfficeWebsite::HUMAN_MADE_TITLE) ?></h2>
                    <p><?= io_e(IdealisticOfficeWebsite::HUMAN_MADE_TEXT) ?></p>
                </article>
                <article class="island opensource reveal" style="--i: 1">
                    <p class="label">Open source</p>
                    <h2><?= io_e(IdealisticOfficeWebsite::OPEN_SOURCE_TITLE) ?></h2>
                    <p><?= io_e(IdealisticOfficeWebsite::OPEN_SOURCE_TEXT) ?></p>
                    <ul class="repos">
                        <?php foreach (IdealisticOfficeWebsite::REPOSITORIES as $repository) : ?>
                            <li>
                                <a href="<?= io_e($repository['url']) ?>">
                                    <strong><?= io_e($repository['name']) ?></strong>
                                    <span><?= io_e($repository['text']) ?></span>
                                    <em><?= io_e($repository['language']) ?></em>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <a class="button" href="<?= io_e(IdealisticOfficeVariable::GITHUB_URL) ?>">View on GitHub</a>
                </article>
            </div>
        </div>
    </section>

    <section id="faq" class="reveal">
        <div class="wrap narrow">
            <p class="label">FAQ</p>
            <h2>Good questions.</h2>
            <?php foreach ($faq as $item) : ?>
                <details class="island faq" data-accordion="faq">
                    <summary><?= io_e($item['q']) ?></summary>
                    <div class="faq-body"><p><?= $item['a'] ?></p></div>
                </details>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="reveal">
        <div class="wrap">
            <div class="island final">
                <h2>Make your chat alive again.</h2>
                <p>Add <?= IdealisticOfficeVariable::APPLICATION_NAME ?> in a minute. Everyone keeps talking. The AI keeps track.</p>
                <div class="actions center">
                    <a class="button solid large" href="<?= io_e(IdealisticOfficeVariable::DISCORD_APP_URL) ?>">Add to Discord</a>
                    <a class="button large" data-try href="<?= io_e($tryUrl) ?>">Try it now</a>
                </div>
            </div>
        </div>
    </section>
</main>

<?php io_footer(); ?>

<?php if (!$isApp && !$comingSoon) : ?>
<script src="https://cdn.paddle.com/paddle/v2/paddle.js"></script>
<script>
    if (window.Paddle) {
        Paddle.Environment.set("production");
        Paddle.Initialize({
            token: <?= json_encode(IdealisticOfficeVariable::PADDLE_CLIENT_TOKEN) ?>,
            checkout: {
                settings: {
                    theme: "dark",
                    displayMode: "overlay",
                    locale: "en",
                    themeOverrides: {
                        primaryColor: "#3b82f6",
                        backgroundColor: "#0b0d12",
                        textColor: "#f5f7fb",
                        borderColor: "#27272a",
                        fontFamily: "Inter"
                    }
                }
            }
        });
    }
</script>
<?php endif; ?>
<script src="<?= io_e(io_asset('.assets/js/landing.js')) ?>" defer></script>
</body>
</html>
