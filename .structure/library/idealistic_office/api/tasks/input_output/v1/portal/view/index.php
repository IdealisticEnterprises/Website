<?php
require '/var/www/.structure/library/base/form.php';
require '/var/www/.structure/library/idealistic_office/init.php';
require_once '/var/www/.structure/library/idealistic_office/api/tasks/website/v1/navigation/index.php';

if (IdealisticOfficeChat::isPoll()) {
    IdealisticOfficeChat::respondToPoll('File');
    return;
}
IdealisticOfficeBrand::start();

try {
    $appName = IdealisticOfficeVariable::APPLICATION_NAME;
    $ASSET_BASE_URL = 'https://www.idealistic.ai/.assets';
    $translationData = require '/var/www/.structure/library/idealistic_office/api/tasks/input_output/v1/portal/view/translations.php';
    $languages = $translationData['languages'];
    $translations = $translationData['translations'];

    $geoData = require '/var/www/.structure/library/idealistic_office/api/tasks/input_output/v1/portal/view/geo_mapping.php';
    $countryLanguageMap = $geoData['countryLanguageMap'];
    $countryTimezoneMap = $geoData['countryTimezoneMap'];

    if (isset($_COOKIE['io_lang'])) {
        $lang = $_COOKIE['io_lang'];
    } else {
        $lang = 'english';
        if (isset($_SERVER['HTTP_CF_IPCOUNTRY'])) {
            $country = strtoupper($_SERVER['HTTP_CF_IPCOUNTRY']);
            if (isset($countryLanguageMap[$country])) $lang = $countryLanguageMap[$country];
        }
    }
    if (!isset($languages[$lang])) $lang = 'english';
    $t = $translations['english'];

    $cfTimezone = $_SERVER['HTTP_CF_TIMEZONE'] ?? null;
    if (!$cfTimezone) {
        $tzCountry = $_SERVER['HTTP_CF_IPCOUNTRY'] ?? '';
        $cfTimezone = $countryTimezoneMap[$tzCountry] ?? 'UTC';
    }

    $randomId = get_form_get("id");
    $defaultUrl = get_form_get("default_url", null);

    if (!is_url($defaultUrl) && !is_domain($defaultUrl)) $defaultUrl = null;
    if (!empty($randomId) && strlen($randomId) !== IdealisticOfficeLimit::URL_RANDOM_ID_LENGTH && !is_numeric($randomId)) {
        header("Location: " . IdealisticOfficeVariable::COMPANY_WEBSITE_URL);
        exit;
    }

    $portal = IdealisticOfficePortalIndependent::getPortal($randomId, empty($randomId) || !is_numeric($randomId), false, $defaultUrl);
    if ($portal === null) {
        header("Location: " . IdealisticOfficeVariable::COMPANY_WEBSITE_URL);
        exit;
    }
    $portal->setPlatformId(IdealisticOfficeAccessPlatform::NATIVE);

    $isDemo = $portal->isDemo();
    $isEmbedded = ($_GET['embedded'] ?? '') === 'true';
    $showNavigation = $isDemo && !$isEmbedded;
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');

    if (!$isEmbedded) {
        header('X-Frame-Options: SAMEORIGIN');
        header("Content-Security-Policy: frame-ancestors 'self'");
    }
    $showBranding = $portal->showsBranding();
    $loginRequired = $portal->isLoginRequired();
    $account = new Account(Account::IDEALISTIC_OFFICE_APPLICATION_ID);
    $session = $account->getSession();
    $hasCookieSession = $session->hasCookieKey();
    $outcome = $session->find(IdealisticOfficeVariable::DEFAULT_SESSION_CHECK_IP_ADDRESS);
    $hasSession = $outcome->isPositiveOutcome();
    $sessionLanguage = null;

    if ($hasSession) {
        $user = AbstractIdealisticOfficeTeam::findUserDynamic($account, false);

        if (!($user instanceof IdealisticOfficeUser)) {
            $account = null;
            $user = null;
        }
    } else {
        $account = null;
        $user = null;
    }

    // Keep the language cookie and the account's stored preference in sync: the
    // account's value wins so a returning visit or a new device picks up
    // whatever was last saved for it. Gated on established
    // access rather than hasEmail() so this also covers demo accounts that
    // haven't signed in with an email yet - setUserLanguageSelection()/
    // getLanguageSelection() key off the same check internally.
    if ($user?->hasEstablishedAccess()) {
        $dbLanguage = $user->getLanguageSelection(false)->getLanguage();
        $sessionLanguage = $dbLanguage;

        if ($lang !== $dbLanguage) {
            $lang = $dbLanguage;
            setcookie('io_lang', $lang, array('expires' => time() + 31536000, 'path' => '/', 'secure' => true, 'httponly' => true, 'samesite' => 'Lax'));
        }
        if (!isset($languages[$lang])) {
            $lang = 'english';
        }
    }

    $defaultMessage = get_form_get("default_message", $t['def_msg_main'] ?? '');
    $sessionId = $session->createKey();
    $ipAddress = get_client_ip_address();
    $csrfToken = $session->getCsrfToken();
    $csrfValid = static function () use ($session): bool {
        return $session->validateCsrfToken($_POST['csrf_token'] ?? null);
    };

    $username = $user?->hasEmail() ? ($user?->getDisplayName() ?? "user") : null;
    $hasEmail = $user?->hasEmail() ?? false;
    $forceLogin = !$isDemo && $loginRequired && !$hasEmail;
    $portalNow = IdealisticOfficeReader::getCurrentDate($portal->getPortalTimeZone());

    if ($username) {
        $t['f_portal_step_1'] = ($t['welcome_back'] ?? 'Welcome back, ') . htmlspecialchars($username, ENT_QUOTES, 'UTF-8');
    }

    // Every POST branch below redirects back to this same view with some
    // combination of a status flag, the portal id, and the default message
    // - previously each call site rebuilt that query string by hand.
    $redirectTo = function (string $status = '') use ($randomId, $isDemo, $defaultMessage): void {
        $parts = array();
        if ($status !== '') $parts[] = $status;
        if (!empty($randomId)) $parts[] = 'id=' . urlencode($randomId);
        if (!$isDemo && !empty($defaultMessage)) $parts[] = 'default_message=' . urlencode($defaultMessage);
        header("Location: ?" . implode('&', $parts));
        exit;
    };

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $isStateChangingAction = isset($_POST['delete_history']) || isset($_POST['sign_out']) || isset($_POST['sign_in']);

        if ($isStateChangingAction && !$csrfValid()) {
            $redirectTo();
        }

        if (isset($_POST['delete_history']) && !$isDemo) {
            $outcome = $portal->deleteHistory($sessionId, $ipAddress);
            $redirectTo('deleted=1');
        }

        if (isset($_POST['sign_out']) && $user?->hasEmail()) {
            $account->getSession()->delete();
            $redirectTo('signed_out=1');
        }

        if (isset($_POST['sign_in']) && !$user?->hasEmail()) {
            $emailForLimit = strtolower(trim((string)($_POST['email'] ?? '')));

            if (has_memory_cooldown("io_signin_email_" . $emailForLimit, "5 minutes")
                    || has_memory_cooldown("io_signin_ip_" . $ipAddress, "10 seconds")) {
                $redirectTo('signed_in=0');
            }

            $name = mb_substr(trim((string)($_POST['fullname'] ?? '')), 0, IdealisticOfficeLimit::H128_LENGTH);
            if (empty($name)) {
                $redirectTo('signed_in=0');
            }
            $email = filter_var(trim((string)($_POST['email'] ?? '')), FILTER_SANITIZE_EMAIL);
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $redirectTo('signed_in=0');
            }
            $signInPortalId = $isDemo ? null : $randomId;
            $signInLink = IdealisticOfficeTeamInitiator::createSignInLink($email, $name, $signInPortalId);

            if ($signInLink instanceof IdealisticOfficeOutcome) {
                IdealisticOfficeError::simpleStore($signInLink->getRawMessage() . "(#582376491)");
                $redirectTo('signed_in=0');
            } else {
                $emailStatus = IdealisticOfficeEmailer::send("0-signIn", $email, array("link" => IdealisticOfficeTeamInitiator::createSignInLink($email, null, $signInPortalId) ?? IdealisticOfficeVariable::COMPANY_WEBSITE_URL, "name" => $name), false);
                if (!$emailStatus) {
                    $redirectTo('signed_in=0');
                }
            }
            $redirectTo('signed_in=1');
        }
    }

    $authError = null;
    $actionMessage = null;
    $actionSuccess = false;

    if (isset($_GET['deleted']) && $_GET['deleted'] === '1') {
        if (!$isDemo) {
            $actionMessage = $t['action_session_reset'] ?? 'Session reset';
            $actionSuccess = true;
        }
    } elseif (isset($_GET['signed_out']) && $_GET['signed_out'] === '1') {
        if (!$user?->hasEmail()) {
            $actionMessage = $t['action_signed_out'] ?? 'Signed out';
            $actionSuccess = true;
        }
    } elseif (isset($_GET['signed_in'])) {
        if ($_GET['signed_in'] === '1' && !$user?->hasEmail()) {
            $actionMessage = $t['action_signed_in'] ?? 'Email sent';
            $actionSuccess = true;
        } elseif ($_GET['signed_in'] === '0' && !$user?->hasEmail()) {
            $actionMessage = $t['action_failed_sign_in'] ?? 'Failed';
            $actionSuccess = false;
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['g-recaptcha-response'])) {
        $verifyResponse = is_google_captcha_valid();

        if (!$verifyResponse) {
            $authError = $t['auth_captcha_failed'] ?? "Captcha verification failed. Please prove you are human.";
        } else {
            $access = $portal->attemptAccess($sessionId, $ipAddress, $_POST['password'] ?? null);
            if (!$access->isPositiveOutcome()) $authError = $access->getRawMessage();
        }
    }

    if (!$portal->hasAccess($sessionId)) {
        require '/var/www/.structure/library/idealistic_office/api/tasks/input_output/v1/portal/view/auth_gate.php';
        return;
    }

    $addUrl = rtrim(parse_url(IdealisticOfficeVariable::PORTAL_ADD_INPUT_OUTPUT_URL, PHP_URL_PATH), '/') . '/';
    $pollQuery = array();

    if (isset($_GET['id']) && is_string($_GET['id']) && $_GET['id'] !== '') {
        $pollQuery['id'] = $_GET['id'];
    }
    if (!$isDemo) {
        if (!empty($defaultUrl)) $pollQuery['default_url'] = $defaultUrl;
        if (!empty($defaultMessage)) $pollQuery['default_message'] = $defaultMessage;
    }
    $pollUrl = (strtok((string)($_SERVER['REQUEST_URI'] ?? '/'), '?') ?: '/') . (empty($pollQuery) ? '' : '?' . http_build_query($pollQuery));
    $chatBuilt = null;

    if (!$portal->isLoginRequired() || $hasSession) {
        $chatBuilt = IdealisticOfficeChat::build(
                $portal,
                $sessionId,
                $ipAddress,
                $isDemo ? null : $defaultMessage,
                -1,
                $t['chat_file'] ?? 'File',
                $sessionLanguage
        );

        if ($chatBuilt instanceof IdealisticOfficeOutcome) {
            $chatBuilt = null;
        }
    }

    // Small helper for the repeated "confirm(...) then submit hidden form"
    // onclick/onsubmit strings below - each call site only differs by which
    // translation key it uses and the English fallback text.
    $confirmText = function (string $key, string $default) use ($t): string {
        return addslashes($t[$key] ?? $default);
    };

    $platformIcons = [
            ['url' => 'https://www.instagram.com/idealistic.ai', 'icon' => 'instagram', 'title' => 'Instagram', 'color' => '#E1306C'],
            ['url' => 'https://www.facebook.com/idealisticai', 'icon' => 'messenger', 'title' => 'Messenger', 'color' => '#00B2FF'],
            ['url' => 'https://wa.me/message/YA5Z4B5YULQZA1', 'icon' => 'whatsapp', 'title' => 'WhatsApp', 'color' => '#25D366'],
            ['url' => 'https://discord.com/invite/NtPwR4dVwK', 'icon' => 'discord', 'title' => 'Discord', 'color' => '#5865F2'],
            ['url' => 'https://t.me/idealisticBot', 'icon' => 'telegram', 'title' => 'Telegram', 'color' => '#229ED9'],
            ['url' => 'mailto:' . IdealisticOfficeVariable::REPLY_EMAIL, 'icon' => 'envelope', 'title' => 'Email', 'color' => '#EA4335'],
            ['url' => IdealisticOfficeVariable::ANDROID_STORE_URL, 'icon' => 'android2', 'title' => 'Android', 'color' => '#3DDC84'],
            ['url' => IdealisticOfficeVariable::IOS_STORE_URL, 'icon' => 'apple', 'title' => 'iOS', 'color' => '#A2AAAD'],
    ];

    $pageTitle = htmlspecialchars($portal->getName());
    if (strtoupper($pageTitle) !== IdealisticOfficeVariable::APPLICATION_NAME) $pageTitle .= (' | ' . IdealisticOfficeVariable::APPLICATION_NAME);

    $IO_CONFIG = [
            'randomId' => $randomId,
            'sessionId' => $hasCookieSession ? '' : $sessionId,
            'language' => $lang,
            'defaultUrl' => $defaultUrl ?? '',
            'addUrl' => $addUrl,
            'pollUrl' => $pollUrl,
            'pollField' => IdealisticOfficeChat::POLL_FIELD,
            'pollHeader' => IdealisticOfficeChat::POLL_HEADER,
            'fragmentId' => IdealisticOfficeChat::FRAGMENT_ID,
            'lastId' => $chatBuilt['lastId'] ?? 0,
            'pendingDate' => $chatBuilt['pendingDate'] ?? '',
            'state' => $chatBuilt['state'] ?? '',
            'isDemo' => $isDemo,
            'hasEmail' => $hasEmail,
            'codeLength' => TwoFactorAuthentication::CODE_LENGTH,
            'promptTimeoutSeconds' => IdealisticOfficeLimit::HISTORY_PROMPT_SECONDS_TIMEOUT,
            'forceLogin' => $forceLogin,
            'serverTime' => $portalNow
    ];
    $IO_I18N = [
            'chat_send' => $t['chat_send'] ?? 'SEND',
            'chat_recording' => $t['chat_recording'] ?? 'Recording... tap to stop',
            'chat_microphone_error' => $t['chat_microphone_error'] ?? 'Microphone access was denied or the microphone is unavailable.',
            'chat_wait_reply' => $t['chat_wait_reply'] ?? 'Wait for the reply...',
            'chat_voice_ready' => $t['chat_voice_ready'] ?? 'Voice message ready to send...',
            'chat_file' => $t['chat_file'] ?? 'File',
            'chat_voice_message' => $t['chat_voice_message'] ?? 'Voice Message',
            'chat_audio' => $t['chat_audio'] ?? 'Audio',
            'chat_network_failed' => $t['chat_network_failed'] ?? 'The network request failed.',
            'chat_message_ph' => $t['chat_message_ph'] ?? 'Enter your message...',
            'anim_widget' => $t['anim_widget'] ?? '',
            'anim_chatbot' => $t['anim_chatbot'] ?? '',
            'anim_chatbox' => $t['anim_chatbox'] ?? '',
            'anim_support' => $t['anim_support'] ?? '',
            'anim_clients' => $t['anim_clients'] ?? '',
            'anim_business' => $t['anim_business'] ?? '',
            'anim_modern' => $t['anim_modern'] ?? '',
    ];

    ?>
    <!DOCTYPE html>
    <html lang="en" translate="yes" data-theme="dark">
    <head>
        <title><?= $pageTitle ?></title>
        <meta name="description" content="<?= htmlspecialchars($t['meta_description'] ?? '') ?>">
        <meta name="keywords" content="<?= htmlspecialchars($t['meta_keywords'] ?? '') ?>">
        <meta name="author" content="Evangelos Dedes">
        <meta property="og:title" content="<?= $pageTitle ?>">
        <meta property="og:description" content="<?= htmlspecialchars($t['og_description'] ?? '') ?>">
        <meta property="og:type" content="website">
        <meta name="viewport"
              content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
        <link rel="icon" type="image/svg+xml" href="https://www.idealistic.ai/.images/favicon.svg">
        <link rel="icon" type="image/png" href="https://www.idealistic.ai/.images/logoSquare.png">
        <link href="<?= htmlspecialchars($ASSET_BASE_URL, ENT_QUOTES) ?>/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.css" rel="stylesheet">
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap"
              rel="stylesheet">
        <link href="<?= htmlspecialchars($ASSET_BASE_URL, ENT_QUOTES) ?>/css/idealistic.css?v=15" rel="stylesheet">
        <?php if ($showNavigation): ?>
            <link href="<?= htmlspecialchars(io_asset('.assets/css/navigation.css'), ENT_QUOTES) ?>" rel="stylesheet">
        <?php endif; ?>
        <script src="https://cdn.paddle.com/paddle/v2/paddle.js"></script>
        <script type="text/javascript">
            Paddle.Environment.set("production");
            Paddle.Initialize({
                token: <?= json_encode(IdealisticOfficeVariable::PADDLE_CLIENT_TOKEN) ?>,
                checkout: {
                    settings: {
                        theme: "dark",
                        displayMode: "overlay",
                        themeOverrides: {
                            primaryColor: "#ffffff",
                            backgroundColor: "#171717",
                            textColor: "#f4f4f5",
                            borderColor: "#27272a",
                            fontFamily: "Inter"
                        }
                    }
                }
            });
        </script>
    </head>
    <body class="d-flex flex-column overflow-hidden position-relative<?= $showNavigation ? ' has-topnav' : '' ?>">
    <?php if ($showNavigation) io_navigation('chat'); ?>
    <div id="loading-screen"
         class="position-fixed top-0 start-0 w-100 h-100 bg-card d-flex flex-column align-items-center justify-content-center">
        <div class="anim-idealistic mb-3"><img src="https://www.idealistic.ai/.images/logoTransparent.png"
                                               style="height: 60px; width: auto;" alt="<?= IdealisticOfficeVariable::APPLICATION_NAME ?> Logo"></div>
    </div>
    <main class="chat-container">
        <div id="chat-box" class="chat-history chat-container-inner<?php echo $isDemo ? ' is-demo' : ''; ?>">
            <?php if ($isDemo): ?>
                <section class="demo-marketing-content px-2 pb-4 pt-4 position-relative z-2">
                    <div class="text-center mt-4 pt-2 reveal-on-scroll" id="portal-hero-section">
                        <div class="row align-items-center justify-content-center text-start mb-5 gx-5 portal-hero-wrapper">
                            <div class="col-lg-5 mb-5 mb-lg-0">
                                <div class="mx-auto" style="max-width: 400px;">
                                    <div class="step-item mb-4">
                                        <div class="step-circle hero-step-circle me-4">1</div>
                                        <div class="step-text hero-step-text">
                                            <?php if ($username === null): ?>
                                                <a href="#" data-bs-toggle="modal" data-bs-target="#signInModal"
                                                   class="custom-link-color text-decoration-none fw-bold"><?php echo $t['f_portal_step_1'] ?? ''; ?></a>
                                            <?php else: ?>
                                                <span class="text-white fw-bold"><?php echo $t['f_portal_step_1'] ?? ''; ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="step-item mb-4">
                                        <div class="step-circle hero-step-circle me-4">2</div>
                                        <div class="step-text hero-step-text"><?php echo $t['f_portal_step_2'] ?? ''; ?></div>
                                    </div>
                                    <?php
                                    $scriptPortals = $portal->getTeam()->getPortals(1, false);
                                    if (!empty($scriptPortals)):
                                        $scriptPortalHtml = str_replace(
                                                IdealisticOfficeVariable::PORTAL_EXTERNAL_SCRIPT_ID_PLACEHOLDER,
                                                $scriptPortals[0]->getRandomId(),
                                                IdealisticOfficeVariable::PORTAL_EXTERNAL_SCRIPT
                                        );
                                        ?>
                                        <div class="mb-4 anim-slide-up embed-snippet-wrap d-flex align-items-center gap-2">
                                            <code id="embed-snippet-code" tabindex="0"
                                                  class="embed-snippet bg-dark text-light p-3 rounded-3 border border-secondary flex-grow-1"><?= htmlspecialchars($scriptPortalHtml, ENT_QUOTES, 'UTF-8') ?></code>
                                            <button type="button"
                                                    class="btn btn-sm btn-outline-light embed-snippet-copy-btn"
                                                    data-copy-target="embed-snippet-code">
                                                <i class="bi bi-copy"></i>
                                            </button>
                                        </div>
                                    <?php endif; ?>

                                    <div class="step-item mb-4">
                                        <div class="step-circle hero-step-circle me-4">3</div>
                                        <div class="step-text hero-step-text"><?php echo $t['f_portal_step_3'] ?? ''; ?></div>
                                    </div>
                                    <div class="mx-auto text-center mt-5">
                                        <h4 class="fw-bold text-white mb-0"><i
                                                    class="bi bi-check2-square text-success me-2"></i><?php echo $t['f_portal_step_done'] ?? ''; ?>
                                        </h4>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-6">
                                <div class="portal-animation-wrapper my-0 mx-auto">
                                    <div class="mock-browser d-none d-md-flex">
                                        <div class="mock-browser-top">
                                            <div class="mock-dot" style="background:#ff5f56;"></div>
                                            <div class="mock-dot" style="background:#ffbd2e;"></div>
                                            <div class="mock-dot" style="background:#27c93f;"></div>
                                        </div>
                                        <div class="mock-wireframe">
                                            <img src="https://www.idealistic.ai/.images/logoTransparent.png" alt="Logo">
                                            <div class="mock-text-large"><?= IdealisticOfficeVariable::APPLICATION_NAME ?></div>
                                            <div class="mock-dynamic-word"></div>
                                        </div>
                                        <div class="mock-chat-widget">
                                            <div class="mock-chat-header">
                                                <div class="mock-chat-logo"></div>
                                            </div>
                                            <div class="mock-chat-body">
                                                <div class="mock-bubble mock-bubble-user">
                                                    <div class="mock-text-line" style="width: 40px;"></div>
                                                </div>
                                                <div class="mock-bot-wrapper">
                                                    <div class="mock-bubble mock-bubble-typing">
                                                        <div class="mock-typing-dot"></div>
                                                        <div class="mock-typing-dot"></div>
                                                        <div class="mock-typing-dot"></div>
                                                    </div>
                                                    <div class="mock-bubble mock-bubble-bot">
                                                        <div class="mock-text-line" style="width: 60px;"></div>
                                                        <div class="mock-text-line" style="width: 45px;"></div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="mock-phone-frame d-flex d-md-none">
                                        <div class="mock-phone-notch"></div>
                                        <div class="mock-phone-screen">
                                            <div class="mock-phone-page">
                                                <img src="https://www.idealistic.ai/.images/logoTransparent.png"
                                                     alt="Logo">
                                                <div class="mock-text-large"><?= IdealisticOfficeVariable::APPLICATION_NAME ?></div>
                                                <div class="mock-dynamic-word"></div>
                                            </div>
                                            <div class="mock-phone-launcher"><i class="bi bi-chat-dots-fill"></i></div>
                                            <div class="mock-phone-chat-sheet">
                                                <div class="mock-chat-header">
                                                    <div class="mock-chat-logo"></div>
                                                </div>
                                                <div class="mock-chat-body">
                                                    <div class="mock-bubble mock-bubble-user">
                                                        <div class="mock-text-line" style="width: 70px;"></div>
                                                    </div>
                                                    <div class="mock-bot-wrapper">
                                                        <div class="mock-bubble mock-bubble-typing">
                                                            <div class="mock-typing-dot"></div>
                                                            <div class="mock-typing-dot"></div>
                                                            <div class="mock-typing-dot"></div>
                                                        </div>
                                                        <div class="mock-bubble mock-bubble-bot">
                                                            <div class="mock-text-line" style="width: 100px;"></div>
                                                            <div class="mock-text-line" style="width: 75px;"></div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <?php if (!$hasEmail): ?>
                            <div class="input-group shadow-sm rounded-4 input-pill p-2 align-items-center mx-auto mt-4"
                                 style="max-width: 600px;">
                                <input type="text" id="demoUrlInput" class="form-control chat-input px-3 m-0 fs-5"
                                       placeholder="<?php echo $t['f_portal_try_ph'] ?? 'Enter URL...'; ?>"
                                       style="height: 50px; box-shadow: none;">
                                <button type="button"
                                        class="btn btn-primary text-white rounded-3 px-5 fw-bold transition fs-5"
                                        onclick="launchDemo()"
                                        style="height: 46px; margin-right: 2px;"><?php echo $t['f_portal_try_btn'] ?? 'Try'; ?></button>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="text-center mt-5 pt-4 reveal-on-scroll">
                        <?php if ($hasEmail): ?>
                            <?php
                            $platformMeta = [
                                    IdealisticOfficeAccessPlatform::DISCORD => ['icon' => 'discord', 'color' => '#5865F2', 'url' => 'https://discord.com/invite/NtPwR4dVwK'],
                                    IdealisticOfficeAccessPlatform::TELEGRAM => ['icon' => 'telegram', 'color' => '#229ED9', 'url' => 'https://t.me/idealisticBot'],
                                    IdealisticOfficeAccessPlatform::MESSENGER => ['icon' => 'messenger', 'color' => '#00B2FF', 'url' => 'https://www.facebook.com/idealisticai'],
                                    IdealisticOfficeAccessPlatform::WHATSAPP => ['icon' => 'whatsapp', 'color' => '#25D366', 'url' => 'https://wa.me/message/YA5Z4B5YULQZA1'],
                                    IdealisticOfficeAccessPlatform::INSTAGRAM => ['icon' => 'instagram', 'color' => '#E1306C', 'url' => 'https://www.instagram.com/idealistic.app'],
                                    IdealisticOfficeAccessPlatform::EMAIL => ['icon' => 'envelope', 'color' => '#EA4335', 'url' => 'mailto:' . IdealisticOfficeVariable::REPLY_EMAIL],
                            ];
                            $platformNames = IdealisticOfficeAccessPlatform::getValidPlatforms();
                            $connectedIdentities = $user?->getLastIdentities() ?? [];
                            $identityByPlatform = [];

                            foreach ($connectedIdentities as $identity) {
                                $pid = $identity->getPlatformID();

                                if (!isset($identityByPlatform[$pid]) && isset($platformMeta[$pid])) {
                                    $identityByPlatform[$pid] = $identity;
                                }
                            }

                            $connectedAccounts = [];
                            foreach ($platformMeta as $pid => $meta) {
                                $isConnected = isset($identityByPlatform[$pid]);
                                $connectedAccounts[] = [
                                        'meta' => $meta,
                                        'name' => $platformNames[$pid] ?? 'Unknown',
                                        'connected' => $isConnected
                                ];
                            }
                            ?>
                            <?php if (!empty($connectedAccounts)): ?>
                                <h4 class="fw-bold mb-4"><?php echo $t['platforms_title'] ?? ''; ?></h4>
                                <div class="d-flex flex-wrap justify-content-center gap-2 connected-accounts-wrapper"
                                     style="max-width: 640px; margin: 0 auto;">
                                    <?php foreach ($connectedAccounts as $account): ?>
                                        <a href="<?= htmlspecialchars($account['meta']['url'], ENT_QUOTES) ?>"
                                           target="_blank" rel="noopener noreferrer"
                                           class="connected-account-card text-decoration-none"
                                           title="<?= htmlspecialchars($account['name']) ?>">
                                            <span class="connected-account-status <?= $account['connected'] ? 'is-connected' : 'is-disconnected' ?>">
                                                <i class="bi bi-<?= $account['connected'] ? 'check-circle-fill' : 'x-circle-fill' ?>"></i>
                                            </span>
                                            <div class="connected-account-icon"
                                                 style="background: <?= $account['meta']['color'] ?>22; border-color: <?= $account['meta']['color'] ?>55;">
                                                <i class="bi bi-<?= $account['meta']['icon'] ?>"
                                                   style="color: <?= $account['meta']['color'] ?>;"></i>
                                            </div>
                                            <div class="connected-account-name"><?= htmlspecialchars($account['name']) ?></div>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <h4 class="fw-bold mb-4"><?php echo $t['platforms_title'] ?? ''; ?></h4>
                            <div class="reviews-carousel-wrapper mt-4" style="max-width: 600px;">
                                <div id="icons-scroll-area" class="reviews-scroll-area"
                                     data-original-count="<?= count($platformIcons) ?>" style="cursor: grab;">
                                    <?php
                                    $marqueeIcons = array_merge($platformIcons, $platformIcons);
                                    foreach ($marqueeIcons as $icon):
                                        ?>
                                        <a href="<?= $icon['url'] ?>" <?php echo isset($icon['onclick']) ? 'onclick="' . $icon['onclick'] . '"' : 'target="_blank"'; ?>
                                           class="social-icon-link text-decoration-none icon-card"
                                           title="<?= $icon['title'] ?>">
                                            <i class="bi bi-<?= $icon['icon'] ?>"
                                               style="color: <?= $icon['color'] ?? 'var(--primary-blue)' ?>;"></i>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                                <div id="icons-dots" class="reviews-dots"></div>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php if (!$hasEmail): ?>
                        <div class="text-center mb-4 mt-5 pt-5 reveal-on-scroll">
                            <h3 class="fw-bold mb-3"><?php echo $t['features_title'] ?? ''; ?></h3>
                        </div>
                        <div class="row justify-content-center g-3 features-grid-wrapper reveal-on-scroll mobile-vertical-carousel">
                            <?php
                            $feats = ['2', '3', '5', '6', '8'];
                            $icons = ['at', 'file-earmark-richtext', 'list-check', 'calendar-check', 'lock'];
                            foreach ($feats as $k => $f):
                                $colClass = in_array($f, ['6', '8']) ? 'col-md-6 col-lg-6' : 'col-md-6 col-lg-4';
                                ?>
                                <div class="<?php echo $colClass; ?>">
                                    <div class="p-3 border border-color rounded-4 bg-card h-100 text-center feature-box-inner d-flex flex-column justify-content-center"
                                         style="cursor:pointer;">
                                        <div class="d-flex flex-row align-items-center justify-content-center gap-2 mb-2">
                                            <i class="bi bi-<?php echo $icons[$k]; ?> fs-4"
                                               style="color: var(--primary-blue);"></i>
                                            <h5 class="fw-bold mb-0"
                                                style="color: #e4e4e7;"><?php echo mb_strtoupper($t['f_' . $f] ?? ''); ?></h5>
                                        </div>
                                        <p class="small mb-0"
                                           style="color: #d4d4d8;"><?php echo $t['f_' . $f . '_desc'] ?? ''; ?></p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <div class="mt-4 pb-5 reveal-on-scroll">
                        <div class="bottom-links-wrapper reveal-on-scroll">
                            <div class="bottom-links-container">
                                <?php if (!$isDemo): ?>
                                    <form method="POST" class="m-0"
                                          onsubmit="return confirm('<?php echo $confirmText('confirm_clear_history', 'Are you sure you want to clear your chat history? This cannot be undone.'); ?>');">
                                        <input type="hidden" name="csrf_token"
                                               value="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>">
                                        <button type="submit" name="delete_history" value="1"
                                                class="btn btn-link custom-link-color p-0 m-0 text-decoration-none shadow-none fw-medium"
                                                style="font-size: 0.85rem;"><?php echo $t['btn_del_hist'] ?? ''; ?></button>
                                    </form>
                                    <span class="text-secondary-theme opacity-50 small">&bull;</span>
                                <?php endif; ?>
                                <?php if (!$isEmbedded): ?>
                                    <a href="<?= htmlspecialchars(IdealisticOfficeVariable::PORTAL_STRUCTURE_URL, ENT_QUOTES) ?>"
                                       class="custom-link-color text-decoration-none fw-medium"
                                       style="font-size: 0.85rem;"><?php echo $t['ft_structure'] ?? ''; ?></a>
                                    <span class="text-secondary-theme opacity-50 small">&bull;</span>
                                <?php endif; ?>
                                <a href="/terms/terms_of_use/" target="_blank"
                                   class="custom-link-color text-decoration-none fw-medium"
                                   style="font-size: 0.85rem;"><?php echo $t['ft_terms'] ?? ''; ?></a>
                                <span class="text-secondary-theme opacity-50 small">&bull;</span>
                                <a href="/policies/privacy_policy/" target="_blank"
                                   class="custom-link-color text-decoration-none fw-medium"
                                   style="font-size: 0.85rem;"><?php echo $t['ft_privacy'] ?? ''; ?></a>
                                <span class="text-secondary-theme opacity-50 small">&bull;</span>
                                <a href="https://ariregister.rik.ee/eng/company/17320016/Idealistic-OÜ" target="_blank"
                                   class="custom-link-color text-decoration-none fw-medium"
                                   style="font-size: 0.85rem;"><?php echo $t['ft_registry'] ?? ''; ?></a>
                            </div>
                        </div>
                        <?php if ($isDemo && !$user?->hasEmail()): ?>
                            <p class="demo-disclaimer text-center text-secondary-theme mt-3 mb-0"><i class="bi bi-info-square me-1"></i><?php echo $t['demo_account_hint'] ?? ''; ?></p>
                        <?php endif; ?>
                    </div>
                </section>
            <?php endif; ?>
            <?= IdealisticOfficeBrand::RAW_START . ($chatBuilt['html'] ?? '') . IdealisticOfficeBrand::RAW_END ?>
        </div>
    </main>

    <div class="glass-bottom w-100">
        <div class="chat-container-inner pt-2 pb-3">
            <div id="upload-preview" class="d-flex flex-wrap gap-2 mb-2"></div>
            <div class="d-flex align-items-end gap-2 position-relative z-3">
                <form id="chat-form" class="m-0 flex-grow-1">
                    <input type="file" id="file-input" class="d-none">
                    <div class="input-group shadow-sm rounded-4 input-pill p-1 align-items-end">
                        <div class="d-flex input-actions ms-1 gap-1 mb-1">
                            <button type="button" id="file-btn" class="btn btn-dark text-secondary">
                                <i class="bi bi-paperclip"></i>
                            </button>
                            <?php if (!$isDemo): ?>
                                <button type="button" id="delete-btn"
                                        class="btn btn-dark text-secondary util-btn d-flex align-items-center justify-content-center"
                                        title="<?php echo $t['btn_del_hist'] ?? ''; ?>"
                                        onclick="if(confirm('<?php echo $confirmText('confirm_clear_history', 'Are you sure you want to clear your chat history? This cannot be undone.'); ?>')) document.getElementById('hidden-delete-form').submit();">
                                    <i class="bi bi-trash-fill"></i>
                                </button>
                            <?php endif; ?>
                            <?php if ($user?->hasEmail()): ?>
                                <button type="button" id="signout-btn"
                                        class="btn btn-dark text-secondary util-btn d-flex align-items-center justify-content-center"
                                        title="<?php echo $t['btn_sign_out'] ?? ''; ?>"
                                        onclick="if(confirm('<?php echo $confirmText('confirm_sign_out', 'Are you sure you want to sign out?'); ?>')) document.getElementById('hidden-signout-form').submit();">
                                    <i class="bi bi-box-arrow-right"></i>
                                </button>
                            <?php else: ?>
                                <button type="button" id="signin-btn"
                                        class="btn btn-dark text-secondary util-btn d-flex align-items-center justify-content-center"
                                        title="<?php echo $t['btn_sign_in'] ?? ''; ?>" data-bs-toggle="modal"
                                        data-bs-target="#signInModal">
                                    <i class="bi bi-box-arrow-in-right"></i>
                                </button>
                            <?php endif; ?>
                        </div>
                        <textarea id="message" name="chat_message" class="form-control chat-input px-3"
                                  autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false"
                                  data-1p-ignore="true" data-lpignore="true" data-bwignore="true" inputmode="text"
                                  maxlength="<?= IdealisticOfficeLimit::K2_LENGTH ?>" rows="1"></textarea>
                        <button type="button" id="mic-btn"
                                class="btn btn-dark text-secondary mb-1 me-1 d-flex align-items-center justify-content-center"
                                title="<?php echo $t['chat_recording'] ?? 'Record a voice message'; ?>">
                            <i class="bi bi-mic-fill"></i>
                        </button>
                        <button type="submit" id="send-btn"
                                class="btn btn-light text-dark mb-1 me-1 d-flex align-items-center justify-content-center is-hidden">
                            <i class="bi bi-send-fill"></i>
                        </button>
                    </div>
                </form>
                <?php if (!$isDemo): ?>
                    <form method="POST" id="hidden-delete-form" class="d-none">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>">
                        <input type="hidden" name="delete_history" value="1">
                    </form>
                <?php endif; ?>
                <?php if ($user?->hasEmail()): ?>
                    <form method="POST" id="hidden-signout-form" class="d-none">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>">
                        <input type="hidden" name="sign_out" value="1">
                    </form>
                <?php endif; ?>
            </div>
            <?php if ($isEmbedded && $showBranding): ?>
                <a class="io-brand" href="<?= htmlspecialchars(IdealisticOfficeVariable::COMPANY_WEBSITE_URL, ENT_QUOTES) ?>"
                   target="_blank" rel="noopener">
                    <img src="https://www.idealistic.ai/.images/favicon.svg" width="14" height="14" alt="">
                    <span><?= htmlspecialchars($appName) ?></span>
                </a>
            <?php endif; ?>
        </div>
    </div>

    <div class="toast-container position-fixed top-0 start-50 translate-middle-x p-3 z-3 mt-5">
        <div id="errorToast" class="toast align-items-center text-bg-danger border-0" role="alert" aria-live="assertive"
             aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body fw-medium" id="toastMessage"></div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"
                        aria-label="<?php echo $t['close'] ?? 'Close'; ?>"></button>
            </div>
        </div>
    </div>

    <div class="modal fade" id="signInModal" tabindex="-1" aria-labelledby="signInModalLabel" aria-hidden="true"
         <?php if ($forceLogin): ?>data-bs-backdrop="static" data-bs-keyboard="false"<?php endif; ?>>
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content bg-card border-color rounded-4 shadow">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>">
                    <div class="modal-header border-bottom border-color">
                        <h5 class="modal-title fw-bold d-flex align-items-center" id="signInModalLabel">
                            <img src="https://www.idealistic.ai/.images/logoCircular.png"
                                 style="height: 24px; width: auto; margin-right: 10px;" alt="<?= IdealisticOfficeVariable::APPLICATION_NAME ?> Logo">
                            <?php echo $t['btn_sign_in'] ?? ''; ?>
                        </h5>
                        <?php if (!$forceLogin): ?>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                                    aria-label="<?php echo $t['close'] ?? 'Close'; ?>"></button>
                        <?php endif; ?>
                    </div>
                    <div class="modal-body px-4 px-md-5">
                        <p class="text-secondary-theme small mb-4 text-center"><?php echo $t['signin_desc'] ?? ''; ?></p>
                        <div class="mb-3 mx-auto" style="max-width: 280px;">
                            <input type="text" name="fullname"
                                   class="form-control border-secondary shadow-none text-center"
                                   style="background-color: var(--input-bg); color: var(--text);"
                                   placeholder="<?php echo $t['f_fullname'] ?? ''; ?>" required>
                        </div>
                        <div class="mb-4 mx-auto" style="max-width: 280px;">
                            <input type="email" name="email"
                                   class="form-control border-secondary shadow-none text-center"
                                   style="background-color: var(--input-bg); color: var(--text);"
                                   placeholder="<?php echo $t['f_email'] ?? ''; ?>" required>
                        </div>
                        <p class="small text-secondary-theme mb-0 text-center" style="font-size: 0.8rem;">
                            <?php echo $t['signin_terms'] ?? ''; ?>
                        </p>
                    </div>
                    <div class="modal-footer border-top border-color">
                        <?php if (!$forceLogin): ?>
                            <button type="button" class="btn btn-dark text-secondary rounded-3"
                                    data-bs-dismiss="modal"><?php echo $t['f_cancel'] ?? ''; ?></button>
                        <?php endif; ?>
                        <button type="submit" name="sign_in" value="1"
                                class="btn btn-primary text-white fw-bold rounded-3"><?php echo $t['f_submit'] ?? ''; ?></button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://www.idealistic.ai/.scripts/bootstrap.bundle.min.js"></script>
    <script>
        window.IO_CONFIG = <?= json_encode($IO_CONFIG, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
        window.IO_I18N = <?= json_encode($IO_I18N, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
        <?php if ($actionMessage !== null): ?>
        window.IO_ACTION_TOAST = {
            message: <?= json_encode($actionMessage, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
            success: <?= $actionSuccess ? 'true' : 'false' ?>
        };
        <?php endif; ?>
    </script>
    <script src="<?= htmlspecialchars($ASSET_BASE_URL, ENT_QUOTES) ?>/js/idealistic.js?v=26"></script>
    <?php if ($isEmbedded): ?>
        <script>
            if (window.parent !== window) {
                window.parent.postMessage({type: 'idealistic-widget', branding: <?= $showBranding ? 'true' : 'false' ?>}, '*');
            }
        </script>
    <?php endif; ?>
    </body>
    </html>
    <?php
} catch (Throwable $e) {
    echo "Fatal failure.";
    IdealisticOfficeError::simpleStore($e);
}