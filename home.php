<?php
// Landing page of idealistic.ai. The chat portal itself (demo, widget, sign-in, shared portals) is still rendered by
// index.php for every request that carries portal parameters, e.g. /?try=1 or /?id=...

$siteUrl = 'https://www.idealistic.ai/';
$assetBase = 'https://www.idealistic.ai';
$email = 'office@idealistic.ai';
$tryUrl = $siteUrl . '?try=1';
$title = 'Idealistic AI | Where humans and AI talk in harmony';
$description = 'Add Idealistic to Discord, Telegram, WhatsApp or your website. Humans talk, AI keeps track and only speaks when asked. Settle disputes, resolve disagreements and bring chats back to life.';

function io_e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function io_asset(string $path): string
{
    global $assetBase;
    $file = __DIR__ . '/' . ltrim($path, '/');
    $version = is_file($file) ? filemtime($file) : false;
    return $assetBase . '/' . ltrim($path, '/') . ($version ? '?v=' . $version : '');
}

// Where Idealistic can be installed, grouped like the sections of the page
$groups = [
    [
        'id' => 'install',
        'label' => 'Install',
        'title' => 'Add it where people already talk.',
        'text' => 'No new place to go. Idealistic joins the conversations you already have.',
        'items' => [
            ['key' => 'discord', 'name' => 'Discord', 'mark' => 'D', 'color' => '#5865F2', 'action' => 'Add to Discord', 'url' => $siteUrl . 'discord', 'text' => 'Bring it into your server and let everyone keep talking as usual.'],
            ['key' => 'telegram', 'name' => 'Telegram', 'mark' => 'T', 'color' => '#26A5E4', 'action' => 'Add to Telegram', 'url' => $siteUrl . 'telegram', 'text' => 'Add it to a group or chat and keep the whole thread on track.'],
            ['key' => 'whatsapp', 'name' => 'WhatsApp', 'mark' => 'W', 'color' => '#25D366', 'action' => 'Add to WhatsApp', 'url' => $siteUrl . 'whatsapp', 'text' => 'The group chat everybody lives in, finally with a memory.'],
            ['key' => 'widget', 'name' => 'Web chat widget', 'mark' => '</>', 'color' => '#3B82F6', 'action' => 'Install the widget', 'url' => '#setup', 'text' => 'One line of code puts Idealistic on your own website.']
        ]
    ],
    [
        'id' => 'connect',
        'label' => 'Connect',
        'title' => 'And everywhere else they reach you.',
        'text' => 'Configure more channels and keep every conversation in one place.',
        'items' => [
            ['key' => 'messenger', 'name' => 'Messenger', 'mark' => 'M', 'color' => '#0A7CFF', 'action' => 'Connect Messenger', 'url' => $siteUrl . 'messenger', 'text' => 'Meet people in the app they already open every day.'],
            ['key' => 'instagram', 'name' => 'Instagram', 'mark' => 'I', 'color' => '#E1306C', 'action' => 'Connect Instagram', 'url' => $siteUrl . 'instagram', 'text' => 'Direct messages become conversations that never get lost.'],
            ['key' => 'email', 'name' => 'Email', 'mark' => '@', 'color' => '#9AA4B5', 'action' => $email, 'url' => 'mailto:' . $email, 'text' => 'Plain email works too. Write to us and talk it through.']
        ]
    ],
    [
        'id' => 'apps',
        'label' => 'Apps',
        'title' => 'Or take it with you.',
        'text' => 'Use Idealistic as an app, on any device.',
        'items' => [
            ['key' => 'ios', 'name' => 'iOS', 'mark' => 'iOS', 'color' => '#F5F7FB', 'action' => 'Get it for iOS', 'url' => $siteUrl . 'iOS', 'text' => 'For iPhone and iPad.'],
            ['key' => 'android', 'name' => 'Android', 'mark' => 'A', 'color' => '#3DDC84', 'action' => 'Get it for Android', 'url' => $siteUrl . 'android', 'text' => 'For phones and tablets.'],
            ['key' => 'web', 'name' => 'Web app', 'mark' => 'W', 'color' => '#60A5FA', 'action' => 'Open the web app', 'url' => $tryUrl, 'text' => 'Nothing to install. Just open it and start talking.']
        ]
    ]
];

$steps = [
    ['number' => '01', 'title' => 'Humans talk.', 'text' => 'Conversations stay between people, exactly like before. Nobody is interrupted and nothing changes about how you talk.'],
    ['number' => '02', 'title' => 'AI tracks.', 'text' => 'Quietly, in the background, Idealistic keeps track of what was said, what was decided and what was promised.'],
    ['number' => '03', 'title' => 'AI speaks when asked.', 'text' => 'The AI never starts a conversation. Its input is only ever added through human interaction, when someone asks.']
];

$fixes = [
    ['word' => 'Disputes', 'text' => 'Who said what, and who agreed to what? The conversation remembers, so nobody has to scroll for hours or argue about it.'],
    ['word' => 'Disagreements', 'text' => 'When people see things differently, an impartial record of the discussion helps everyone find common ground faster.'],
    ['word' => 'Silent chats', 'text' => 'Chats go quiet when nothing moves forward. With something that keeps track, there is always a next thing to pick up.']
];

$tabs = [
    ['id' => 'discord', 'name' => 'Discord', 'steps' => [
        'Press <strong>Add to Discord</strong> and pick the server you want to use it in.',
        'Keep chatting as usual. Idealistic stays quiet and keeps track.',
        'Whenever someone asks it something, it answers. That is the only time it speaks.'
    ], 'link' => [$siteUrl . 'discord', 'Add to Discord']],
    ['id' => 'telegram', 'name' => 'Telegram', 'steps' => [
        'Press <strong>Add to Telegram</strong> and choose the group or chat.',
        'Keep chatting as usual. Idealistic stays quiet and keeps track.',
        'Whenever someone asks it something, it answers.'
    ], 'link' => [$siteUrl . 'telegram', 'Add to Telegram']],
    ['id' => 'whatsapp', 'name' => 'WhatsApp', 'steps' => [
        'Press <strong>Add to WhatsApp</strong> and open the chat.',
        'Keep chatting as usual. Idealistic stays quiet and keeps track.',
        'Whenever someone asks it something, it answers.'
    ], 'link' => [$siteUrl . 'whatsapp', 'Add to WhatsApp']],
    ['id' => 'widget', 'name' => 'Web chat widget', 'steps' => [
        'Paste this line just before the closing <code>&lt;/body&gt;</code> tag of your website.',
        'Replace <code>YOUR_PORTAL_ID</code> with the identifier of your portal.',
        'A chat button appears on your website. That is all.'
    ], 'code' => '<script src="https://www.idealistic.ai/.scripts/v1/portal.js" data-portal="YOUR_PORTAL_ID" id="idealistic-script" defer></script>', 'link' => [$tryUrl, 'Try it first']],
    ['id' => 'more', 'name' => 'Messenger, Instagram & email', 'steps' => [
        'Open <a href="' . $siteUrl . 'messenger">Messenger</a> or <a href="' . $siteUrl . 'instagram">Instagram</a> to start a conversation.',
        'Prefer email? Write to <a href="mailto:' . $email . '">' . $email . '</a>.',
        'Everything you talk about stays connected to the same place.'
    ], 'link' => ['mailto:' . $email, 'Email us']],
    ['id' => 'apps', 'name' => 'iOS, Android & web app', 'steps' => [
        'Get the app for <a href="' . $siteUrl . 'iOS">iOS</a> or <a href="' . $siteUrl . 'android">Android</a>.',
        'Or open the <a href="' . $tryUrl . '">web app</a> in any browser, nothing to install.',
        'Start talking. Idealistic keeps track.'
    ], 'link' => [$tryUrl, 'Open the web app']]
];

$faq = [
    ['q' => 'What is Idealistic?', 'a' => 'A way for humans to communicate harmoniously with AI. You talk with other people as usual, the AI keeps track of the conversation, and it only joins in when a human asks it to.'],
    ['q' => 'Does the AI ever talk on its own?', 'a' => 'No. The AI never starts a conversation and never interrupts. Its input is only added through human interaction.'],
    ['q' => 'Where can I use it?', 'a' => 'On Discord, Telegram, WhatsApp and as a chat widget on your own website. You can also connect Messenger, Instagram and email, or use the iOS, Android and web apps.'],
    ['q' => 'How can it settle a dispute?', 'a' => 'Because it keeps track of what was said and decided, anyone can ask what was actually agreed instead of relying on memory. The record is the same for everybody.'],
    ['q' => 'What happens to my data?', 'a' => 'Read the <a href="' . $siteUrl . 'policies/privacy_policy/">Privacy Policy</a> and the <a href="' . $siteUrl . 'terms/terms_of_use/">Terms of Use</a>. You can also ask for your data to be deleted, see the <a href="' . $siteUrl . 'instructions/user_data_deletion/">data deletion instructions</a>.'],
    ['q' => 'How do I get in touch?', 'a' => 'Write to <a href="mailto:' . $email . '">' . $email . '</a>.']
];

$schemas = [
    [
        '@context' => 'https://schema.org',
        '@graph' => [
            [
                '@type' => 'Organization',
                '@id' => $siteUrl . '#organization',
                'name' => 'Idealistic AI',
                'legalName' => 'Idealistic OÜ',
                'url' => $siteUrl,
                'logo' => $assetBase . '/.images/logoSquare.png',
                'email' => $email,
                'contactPoint' => [['@type' => 'ContactPoint', 'email' => $email, 'contactType' => 'customer support']],
                'sameAs' => ['https://www.youtube.com/@IdealisticAI']
            ],
            [
                '@type' => 'WebSite',
                '@id' => $siteUrl . '#website',
                'url' => $siteUrl,
                'name' => 'Idealistic AI',
                'inLanguage' => 'en',
                'publisher' => ['@id' => $siteUrl . '#organization']
            ],
            [
                '@type' => 'SoftwareApplication',
                'name' => 'Idealistic',
                'description' => $description,
                'url' => $siteUrl,
                'image' => $assetBase . '/.images/social.png',
                'applicationCategory' => 'CommunicationApplication',
                'operatingSystem' => 'iOS, Android, Web, Discord, Telegram, WhatsApp',
                'publisher' => ['@id' => $siteUrl . '#organization']
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
    <meta name="author" content="Idealistic AI">
    <link rel="canonical" href="<?= io_e($siteUrl) ?>">
    <link rel="icon" type="image/svg+xml" href="<?= io_e($assetBase) ?>/.images/favicon.svg">
    <link rel="icon" type="image/png" href="<?= io_e($assetBase) ?>/.images/logoSquare.png">
    <link rel="apple-touch-icon" href="<?= io_e($assetBase) ?>/.images/logoSquare.png">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Idealistic AI">
    <meta property="og:locale" content="en_US">
    <meta property="og:url" content="<?= io_e($siteUrl) ?>">
    <meta property="og:title" content="<?= io_e($title) ?>">
    <meta property="og:description" content="<?= io_e($description) ?>">
    <meta property="og:image" content="<?= io_e($assetBase) ?>/.images/social.png">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:image:alt" content="Idealistic AI: humans talk, AI keeps track">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= io_e($title) ?>">
    <meta name="twitter:description" content="<?= io_e($description) ?>">
    <meta name="twitter:image" content="<?= io_e($assetBase) ?>/.images/social.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap">
    <link rel="stylesheet" href="<?= io_e(io_asset('.assets/css/landing.css')) ?>">
    <?php foreach ($schemas as $schema) : ?>
        <script type="application/ld+json"><?= json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?></script>
    <?php endforeach; ?>
</head>
<body>
<a class="skip-link" href="#main">Skip to content</a>

<header class="island nav" id="nav">
    <a class="brand" href="<?= io_e($siteUrl) ?>" aria-label="Idealistic AI">
        <img src="<?= io_e($assetBase) ?>/.images/logoWhite.svg" alt="" width="30" height="30">
        <span>Idealistic</span>
    </a>
    <nav aria-label="Main">
        <a href="#how">How it works</a>
        <a href="#install">Install</a>
        <a href="#setup">Setup</a>
        <a href="#faq">FAQ</a>
    </nav>
    <a class="button solid small" href="<?= io_e($tryUrl) ?>">Try it now</a>
</header>

<main id="main">
    <section class="hero">
        <div class="hero-mark" aria-hidden="true"><img src="<?= io_e($assetBase) ?>/.images/logoWhite.svg" alt="" width="640" height="640"></div>
        <div class="wrap">
            <p class="eyebrow">Discord &middot; Telegram &middot; WhatsApp &middot; Web</p>
            <h1>Humans talk.<br><span>AI keeps track.</span></h1>
            <p class="lead">Idealistic is the way for people and AI to communicate in harmony. Add it to the chats you already have. Everyone keeps talking, the AI quietly keeps track, and it only speaks when a human asks.</p>
            <div class="actions">
                <a class="button solid large" href="<?= io_e($siteUrl) ?>discord">Add to Discord</a>
                <a class="button large" href="<?= io_e($tryUrl) ?>">Try it now</a>
            </div>
            <div class="island strip" aria-label="Works with">
                <span>Works with</span>
                <?php foreach ($groups as $group) : ?>
                    <?php foreach ($group['items'] as $item) : ?>
                        <a href="<?= io_e($item['url']) ?>"><?= io_e($item['name']) ?></a>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <section id="how" class="reveal">
        <div class="wrap">
            <p class="label">How it works</p>
            <h2>A conversation with a memory.</h2>
            <div class="grid three">
                <?php foreach ($steps as $index => $step) : ?>
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
                    <div class="msg ai"><b>Idealistic</b><span>Sam proposed Friday on one condition: the design must be approved first. Alex agreed to review it by Wednesday. The design is still waiting for approval.</span></div>
                </div>
                <div class="demo-text">
                    <h3>It never joins uninvited.</h3>
                    <p>Idealistic listens and keeps track, but it only answers after a human asks. Everything it says is triggered by a person, so it never talks over anyone and never makes up a conversation of its own.</p>
                </div>
            </div>
        </div>
    </section>

    <section class="reveal">
        <div class="wrap">
            <p class="label">What it fixes</p>
            <h2>Settle it. Calm it down. Bring it back to life.</h2>
            <div class="grid three">
                <?php foreach ($fixes as $index => $fix) : ?>
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
            <p class="label">Setup</p>
            <h2>Up and running in a minute.</h2>
            <div class="island setup" data-tabs>
                <div class="tablist" role="tablist" aria-label="Choose where to set it up">
                    <?php foreach ($tabs as $index => $tab) : ?>
                        <button type="button" role="tab" id="tab-<?= io_e($tab['id']) ?>" aria-controls="panel-<?= io_e($tab['id']) ?>"
                                aria-selected="<?= $index === 0 ? 'true' : 'false' ?>"><?= io_e($tab['name']) ?></button>
                    <?php endforeach; ?>
                </div>
                <?php foreach ($tabs as $index => $tab) : ?>
                    <div class="tabpanel" role="tabpanel" id="panel-<?= io_e($tab['id']) ?>" aria-labelledby="tab-<?= io_e($tab['id']) ?>"<?= $index === 0 ? '' : ' data-inactive' ?>>
                        <h3 class="print-title"><?= io_e($tab['name']) ?></h3>
                        <ol>
                            <?php foreach ($tab['steps'] as $step) : ?>
                                <li><?= $step ?></li>
                            <?php endforeach; ?>
                        </ol>
                        <?php if (!empty($tab['code'])) : ?>
                            <pre><code><?= io_e($tab['code']) ?></code></pre>
                        <?php endif; ?>
                        <a class="button solid" href="<?= io_e($tab['link'][0]) ?>"><?= io_e($tab['link'][1]) ?></a>
                    </div>
                <?php endforeach; ?>
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
                <p>Add Idealistic in a minute. Everyone keeps talking. The AI keeps track.</p>
                <div class="actions center">
                    <a class="button solid large" href="<?= io_e($siteUrl) ?>discord">Add to Discord</a>
                    <a class="button large" href="<?= io_e($tryUrl) ?>">Try it now</a>
                </div>
            </div>
        </div>
    </section>
</main>

<footer class="footer">
    <div class="wrap">
        <div class="footer-grid">
            <div>
                <a class="brand" href="<?= io_e($siteUrl) ?>">
                    <img src="<?= io_e($assetBase) ?>/.images/logoWhite.svg" alt="" width="30" height="30" loading="lazy">
                    <span>Idealistic</span>
                </a>
                <p>Where humans and AI talk in harmony.</p>
                <p><a href="mailto:<?= io_e($email) ?>"><?= io_e($email) ?></a></p>
            </div>
            <div>
                <h4>Install</h4>
                <a href="<?= io_e($siteUrl) ?>discord">Discord</a>
                <a href="<?= io_e($siteUrl) ?>telegram">Telegram</a>
                <a href="<?= io_e($siteUrl) ?>whatsapp">WhatsApp</a>
                <a href="#setup">Web chat widget</a>
            </div>
            <div>
                <h4>Connect</h4>
                <a href="<?= io_e($siteUrl) ?>messenger">Messenger</a>
                <a href="<?= io_e($siteUrl) ?>instagram">Instagram</a>
                <a href="<?= io_e($siteUrl) ?>iOS">iOS</a>
                <a href="<?= io_e($siteUrl) ?>android">Android</a>
            </div>
            <div>
                <h4>Company</h4>
                <a href="<?= io_e($siteUrl) ?>terms/terms_of_use/">Terms of Use</a>
                <a href="<?= io_e($siteUrl) ?>policies/privacy_policy/">Privacy Policy</a>
                <a href="<?= io_e($siteUrl) ?>instructions/user_data_deletion/">Data deletion</a>
                <a href="https://spartan.top">Spartan AntiCheat</a>
            </div>
        </div>
        <p class="copyright">&copy; <?= date('Y') ?> Idealistic OÜ. All rights reserved.</p>
    </div>
</footer>

<script src="<?= io_e(io_asset('.assets/js/landing.js')) ?>" defer></script>
</body>
</html>
