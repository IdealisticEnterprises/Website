<?php
require '/var/www/.structure/library/base/form.php';
require '/var/www/.structure/library/base/utilities.php';
require '/var/www/.structure/library/idealistic_office/init.php';
IdealisticOfficeBrand::start();

$token = get_form_get("token");
$portal = get_form_get("portal");
$hasPortal = !empty($portal);
$appMode = get_form_get("app_mode");
$forceBrowser = get_form_get("force_browser");
$to = get_form_get("to") === "structure" ? "structure" : "";

/**
 * Dispatches the user to the native mobile app via Intent / Custom Scheme
 * with a fallback to the web browser.
 */
function dispatch_to_app(string $appTargetUrl, string $browserFallbackUrl): void
{
    $encodedAppUrl = urlencode($appTargetUrl);
    $encodedFallbackUrl = urlencode($browserFallbackUrl);

    $customScheme = "idealistic://open?url=" . $encodedAppUrl;
    $androidPackage = "ai.idealistic";
    $androidIntent = "intent://open?url=" . $encodedAppUrl . "#Intent;scheme=idealistic;package=" . $androidPackage . ";S.browser_fallback_url=" . $encodedFallbackUrl . ";end";
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Opening <?= IdealisticOfficeVariable::APPLICATION_NAME ?>...</title>
        <style>
            body {
                background: #0d0d0f;
                color: #f4f4f5;
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                height: 100vh;
                margin: 0;
                text-align: center;
                padding: 20px;
                box-sizing: border-box;
            }

            .btn {
                display: inline-block;
                margin-top: 18px;
                padding: 12px 24px;
                background: #3b82f6;
                color: #fff;
                text-decoration: none;
                font-weight: 600;
                border-radius: 12px;
                transition: opacity 0.2s;
            }

            .btn-secondary {
                background: transparent;
                border: 1px solid rgba(255, 255, 255, 0.2);
                color: #d4d4d8;
                margin-top: 10px;
            }
        </style>
    </head>
    <body>
    <h3>Launching App...</h3>
    <p style="color: #a1a1aa; font-size: 0.95rem;">If the app does not open automatically, tap below:</p>

    <a id="app-link" href="<?= htmlspecialchars($customScheme, ENT_QUOTES) ?>" class="btn">Open App</a>
    <br>
    <a href="<?= htmlspecialchars($browserFallbackUrl, ENT_QUOTES) ?>" class="btn btn-secondary">Continue in Browser</a>

    <script>
        (function () {
            const browserFallbackUrl = <?= json_encode($browserFallbackUrl) ?>;
            const customScheme = <?= json_encode($customScheme) ?>;
            const androidIntent = <?= json_encode($androidIntent) ?>;
            const isAndroid = /Android/i.test(navigator.userAgent);
            const isIOS = /iPhone|iPad|iPod/i.test(navigator.userAgent);

            if (isAndroid) {
                document.getElementById('app-link').href = androidIntent;
                window.location.href = androidIntent;
            } else if (isIOS) {
                window.location.href = customScheme;
                setTimeout(function () {
                    window.location.href = browserFallbackUrl;
                }, 2500);
            } else {
                window.location.href = browserFallbackUrl;
            }
        })();
    </script>
    </body>
    </html>
    <?php
    exit;
}

/**
 * @param bool $hasPortal
 * @param mixed $portal
 * @return void
 */
function sign_in_redirect_attempt(bool $hasPortal, mixed $portal, bool $outcome): void
{
    if ($hasPortal) {
        $portalObject = IdealisticOfficePortalIndependent::getPortal($portal, !is_numeric($portal), false);

        if ($portalObject !== null
                && $portalObject->hasDefaultURL()) {
            redirect_to_url($portalObject->getDefaultUrl());
        } else {
            redirect_to_url(IdealisticOfficeVariable::PORTAL_VIEW_INPUT_OUTPUT_URL . "?id=" . urlencode($portal) . "&signed_in=" . ($outcome ? "1" : "0"));
        }
    } else {
        global $to;
        redirect_to_url(IdealisticOfficeVariable::COMPANY_WEBSITE_URL . ($to === "structure" ? "/structure" : "/chat") . "?signed_in=" . ($outcome ? "1" : "0"));
    }
}

if (empty($token)) {
    redirect_to_url(IdealisticOfficeVariable::COMPANY_WEBSITE_URL);
} else {
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $isMobile = !$hasPortal && preg_match('/Mobile|Android|BlackBerry|iPhone|iPad|iPod|Opera Mini|IEMobile/i', $userAgent);

    if ($isMobile && empty($appMode) && empty($forceBrowser)) {
        $trustedHosts = [
                parse_url(IdealisticOfficeVariable::COMPANY_WEBSITE_URL, PHP_URL_HOST),
        ];
        $requestHost = $_SERVER['HTTP_HOST'] ?? '';
        $requestHostName = parse_url('http://' . $requestHost, PHP_URL_HOST) ?: '';

        if (!in_array(strtolower($requestHostName), array_map('strtolower', array_filter($trustedHosts)), true)) {
            $isMobile = false;
        } else {
            $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
            $path = parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH);
            $baseUrl = $protocol . "://" . $requestHostName . $path;

            $appTargetUrl = $baseUrl . "?token=" . urlencode($token) . "&app_mode=1" . ($to !== "" ? "&to=" . $to : "");
            $browserFallbackUrl = $baseUrl . "?token=" . urlencode($token) . ($hasPortal ? "&portal=" . urlencode($portal) : "") . ($to !== "" ? "&to=" . $to : "") . "&force_browser=1";

            dispatch_to_app($appTargetUrl, $browserFallbackUrl);
        }
    }

    try {
        $account = new Account(
                Account::IDEALISTIC_OFFICE_APPLICATION_ID
        );
        $reply = $account->getTwoFactorAuthentication()->verify(
                $token,
                null,
                false,
                true
        );

        if ($reply->isPositiveOutcome()) {
            if ($account->exists()) {
                $sessionAccount = new Account(
                        Account::IDEALISTIC_OFFICE_APPLICATION_ID
                );
                $sessionAccount->getSession()->find(IdealisticOfficeVariable::DEFAULT_SESSION_CHECK_IP_ADDRESS);

                if ($sessionAccount->getDetail("id") === $account->getDetail("id")) {
                    redirect_to_url(IdealisticOfficeVariable::COMPANY_WEBSITE_URL);
                } else {
                    $user = AbstractIdealisticOfficeTeam::findUserDynamic(
                            $sessionAccount,
                            false,
                            false
                    );

                    if ($user instanceof IdealisticOfficeOutcome) {
                        $outcome = IdealisticOfficeTeamInitiator::initiateSession(
                                $account,
                                $sessionAccount->getSession()->createKey(),
                                IdealisticOfficeAccessPlatform::NATIVE
                        );
                    } else {
                        $outcome = IdealisticOfficeTeamInitiator::changeSession(
                                $user,
                                $account,
                                $sessionAccount->getSession()->createKey(),
                                IdealisticOfficeAccessPlatform::NATIVE
                        );
                    }

                    if ($outcome->isPositiveOutcome()) {
                        if ($user instanceof IdealisticOfficeUser) {
                            $user->updateNameFromSetting();
                        }
                        sign_in_redirect_attempt($hasPortal, $portal, true);
                    } else {
                        echo $outcome->getRawMessage();
                    }
                }
            } else {
                sign_in_redirect_attempt($hasPortal, $portal, false);
            }
        } else {
            sign_in_redirect_attempt($hasPortal, $portal, false);
        }
    } catch (Throwable $e) {
        echo "An error occurred while processing this sign in.";
        IdealisticOfficeError::simpleStore($e);
    }
}