<?php
require '/var/www/.structure/library/base/form.php';
require '/var/www/.structure/library/idealistic_office/init.php';
require_once '/var/www/.structure/library/idealistic_office/api/tasks/website/v1/navigation/index.php';

IdealisticOfficeBrand::start();

try {
    $assetBase = IdealisticOfficeVariable::COMPANY_WEBSITE_URL . '/.assets';
    $imageBase = IdealisticOfficeVariable::COMPANY_WEBSITE_URL . '/.images';
    $e = static fn($value): string => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');

    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header("Content-Security-Policy: default-src 'none'; style-src 'self' https://fonts.googleapis.com; font-src https://fonts.gstatic.com; img-src 'self' data:; script-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'none'");

    $account = new Account(Account::IDEALISTIC_OFFICE_APPLICATION_ID);
    $session = $account->getSession();
    $outcome = $session->find(IdealisticOfficeVariable::DEFAULT_SESSION_CHECK_IP_ADDRESS);
    $user = null;

    if ($outcome->isPositiveOutcome()) {
        $found = AbstractIdealisticOfficeTeam::findUserDynamic($account, false);

        if ($found instanceof IdealisticOfficeUser) {
            $user = $found;
        }
    }
    $hasEmail = $user?->hasEmail() ?? false;
    $isDemo = !$hasEmail;
    $hasAccount = $user !== null;
    $csrfToken = $session->getCsrfToken();
    $ipAddress = get_client_ip_address();
    $redirectTo = static function (string $query = '', string $anchor = ''): void {
        header('Location: ' . (strtok((string)($_SERVER['REQUEST_URI'] ?? '/structure'), '?') ?: '/structure') . ($query !== '' ? '?' . $query : '') . ($anchor !== '' ? '#' . $anchor : ''));
        exit;
    };

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        if (!$session->validateCsrfToken($_POST['csrf_token'] ?? null)) {
            $redirectTo();
        }

        if (isset($_POST['sign_out']) && $hasEmail) {
            $session->delete();
            $redirectTo('signed_out=1');
        }

        if (isset($_POST['start_demo']) && !$hasAccount) {
            if (has_memory_cooldown("io_demo_ip_" . $ipAddress, "30 seconds")) {
                $redirectTo('demo=0');
            }
            $started = IdealisticOfficeTeamInitiator::findUser(IdealisticOfficeAccessPlatform::NATIVE, $session->createKey());

            if (!($started instanceof IdealisticOfficeUser)) {
                IdealisticOfficeError::simpleStore($started->getRawMessage() . "(#structure demo)");
            }
            $redirectTo('demo=' . ($started instanceof IdealisticOfficeUser ? '1' : '0'));
        }

        if (isset($_POST['sign_in']) && $isDemo) {
            $emailForLimit = strtolower(trim((string)($_POST['email'] ?? '')));

            if (has_memory_cooldown("io_signin_email_" . $emailForLimit, "5 minutes")
                || has_memory_cooldown("io_signin_ip_" . $ipAddress, "10 seconds")) {
                $redirectTo('signed_in=0');
            }
            $name = mb_substr(trim((string)($_POST['fullname'] ?? '')), 0, IdealisticOfficeLimit::H128_LENGTH);
            $email = filter_var(trim((string)($_POST['email'] ?? '')), FILTER_SANITIZE_EMAIL);

            if (empty($name) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $redirectTo('signed_in=0');
            }
            $link = IdealisticOfficeTeamInitiator::createSignInLink($email, $name, null, 'structure');

            if ($link instanceof IdealisticOfficeOutcome) {
                IdealisticOfficeError::simpleStore($link->getRawMessage() . "(#structure sign in)");
                $redirectTo('signed_in=0');
            }
            $sent = IdealisticOfficeEmailer::send("0-signIn", $email, array("link" => $link, "name" => $name), false);
            $redirectTo('signed_in=' . ($sent ? '1' : '0'));
        }

        $redirectTo();
    }

    $flash = null;

    if (isset($_GET['demo'])) {
        $flash = $_GET['demo'] === '1'
            ? array('ok' => true, 'text' => 'Your demo account is ready. Open the chat to start building.')
            : array('ok' => false, 'text' => 'The demo account could not be started. Please try again in a moment.');
    } elseif (isset($_GET['signed_out'])) {
        $flash = array('ok' => true, 'text' => 'You are signed out.');
    } elseif (isset($_GET['signed_in'])) {
        if ($_GET['signed_in'] === '1') {
            $flash = array('ok' => true, 'text' => $hasEmail ? 'You are signed in.' : 'We sent you an email with a sign-in link.');
        } elseif (!$hasEmail) {
            $flash = array('ok' => false, 'text' => 'The sign-in failed. Please check your details and try again.');
        }
    }
    $blocks = IdealisticOfficeStructure::getBlocks($user);
    $displayName = $hasEmail ? ($user?->getDisplayName() ?? 'you') : null;
    $demoText = 'This is a demo account.';

    header('Cache-Control: private, no-store');
    ?>
    <!DOCTYPE html>
    <html lang="en" translate="yes">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Structure | <?= $e(IdealisticOfficeVariable::APPLICATION_NAME) ?></title>
        <meta name="description" content="Everything you set up with <?= IdealisticOfficeVariable::APPLICATION_NAME ?>, in one place.">
        <meta name="robots" content="noindex, nofollow">
        <link rel="icon" type="image/svg+xml" href="<?= $e($imageBase) ?>/favicon.svg">
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="<?= $e($assetBase) ?>/css/structure.css?v=2">
        <link rel="stylesheet" href="<?= $e(io_asset('.assets/css/footer.css')) ?>">
        <link rel="stylesheet" href="<?= $e(io_asset('.assets/css/navigation.css')) ?>">
    </head>
    <body class="has-topnav">
    <?php
    ob_start();
    if ($hasEmail): ?>
        <form method="post" class="signout">
            <input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>">
            <span><?= $e($displayName) ?></span>
            <button type="submit" name="sign_out" value="1" class="button small">Sign out</button>
        </form>
    <?php endif;
    io_navigation('structure', (string)ob_get_clean());
    ?>

    <main class="wrap">
        <h1>Structure</h1>
        <p class="lead">Everything you have set up with <?= IdealisticOfficeVariable::APPLICATION_NAME ?>, in one place: your portals and where they are installed, your contacts and roles, who has access and when, your instructions, shortcuts and scheduled tasks. To change any of it, just ask <?= IdealisticOfficeVariable::APPLICATION_NAME ?> in the chat or in any app.</p>
        <div class="actions">
            <a class="button solid" href="<?= $e(IdealisticOfficeVariable::PORTAL_CHAT_URL) ?>">Open the chat</a>
        </div>

        <?php if ($flash !== null): ?>
            <div class="flash <?= $flash['ok'] ? 'ok' : 'bad' ?>" role="status"><?= $e($flash['text']) ?></div>
        <?php endif; ?>

        <?php if ($isDemo): ?>
            <section class="island demo" id="demo">
                <h2><?= $e($demoText) ?></h2>
                <?php if ($hasAccount): ?>
                    <p>What you build in the chat shows up here. Sign in to use your own account.</p>
                <?php else: ?>
                    <p>Start a demo account to see how your setup takes shape here, or sign in to use your own account.</p>
                    <form method="post" class="signin">
                        <input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>">
                        <button type="submit" name="start_demo" value="1" class="button solid">Start a demo account</button>
                    </form>
                <?php endif; ?>
                <form method="post" class="signin">
                    <input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>">
                    <input type="text" name="fullname" placeholder="Your name" maxlength="<?= IdealisticOfficeLimit::H128_LENGTH ?>" required>
                    <input type="email" name="email" placeholder="Your email" required>
                    <button type="submit" name="sign_in" value="1" class="button">Sign in</button>
                </form>
                <p class="small">By signing in, you agree to our <a href="<?= $e(IdealisticOfficeVariable::TERMS_OF_USE_URL) ?>">Terms of Use</a> and <a href="<?= $e(IdealisticOfficeVariable::PRIVACY_POLICY_URL) ?>">Privacy Policy</a>.</p>
            </section>
        <?php endif; ?>

        <?php if (empty($blocks)): ?>
            <p class="empty">Start a demo account or sign in to see your structure here.</p>
        <?php else: ?>
            <div class="layout">
                <nav class="side" aria-label="Sections">
                    <?php foreach ($blocks as $block): ?>
                        <a href="#<?= $e($block['id']) ?>"><?= $e($block['title']) ?> <span class="count"><?= count($block['cards']) ?></span></a>
                    <?php endforeach; ?>
                </nav>
                <div class="content">
                    <?php foreach ($blocks as $block): ?>
                        <section class="island block" id="<?= $e($block['id']) ?>">
                            <h2><?= $e($block['title']) ?> <span class="count"><?= count($block['cards']) ?></span></h2>
                            <?php if (!empty($block['text'])): ?>
                                <p><?= $e($block['text']) ?></p>
                            <?php endif; ?>
                            <?php if (empty($block['cards'])): ?>
                                <p class="empty">Nothing here yet. Ask <?= IdealisticOfficeVariable::APPLICATION_NAME ?> in the <a href="<?= $e(IdealisticOfficeVariable::PORTAL_CHAT_URL) ?>">chat</a> to add one.</p>
                            <?php else: ?>
                                <?= IdealisticOfficeBrand::RAW_START ?>
                                <div class="cards">
                                    <?php foreach ($block['cards'] as $card): ?>
                                        <article class="card">
                                            <strong><?= $e($card['title']) ?></strong>
                                            <?php if ($card['subtitle'] !== ''): ?>
                                                <span class="sub"><?= $e($card['subtitle']) ?></span>
                                            <?php endif; ?>
                                            <?php if (!empty($card['badges'])): ?>
                                                <span class="badges">
                                                    <?php foreach ($card['badges'] as $badge): ?>
                                                        <span class="badge"><?= $e($badge) ?></span>
                                                    <?php endforeach; ?>
                                                </span>
                                            <?php endif; ?>
                                            <?php if (!empty($card['lines'])): ?>
                                                <dl>
                                                    <?php foreach ($card['lines'] as $line): ?>
                                                        <dt><?= $e($line[0]) ?></dt>
                                                        <dd><?= $e($line[1]) ?></dd>
                                                    <?php endforeach; ?>
                                                </dl>
                                            <?php endif; ?>
                                        </article>
                                    <?php endforeach; ?>
                                </div>
                                <?= IdealisticOfficeBrand::RAW_END ?>
                            <?php endif; ?>
                        </section>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </main>

    <?php if ($isDemo): ?>
        <p class="demo-line"><?= $e($demoText) ?></p>
    <?php endif; ?>
    <?php io_footer(); ?>
    </body>
    </html>
    <?php
} catch (Throwable $exception) {
    IdealisticOfficeError::simpleStore($exception);
    echo "Fatal failure.";
}
