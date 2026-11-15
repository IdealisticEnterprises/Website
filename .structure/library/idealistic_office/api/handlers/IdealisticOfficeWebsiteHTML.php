<?php

class IdealisticOfficeWebsiteHTML implements PhpAsyncSerializable
{

    private static array $LANG = [
        'legal_title' => 'Legal Agreement',
        'legal_desc' => 'By signing below, you agree to our Terms of Use/Service and our Privacy Policy.',
        'legal_lbl_name' => 'Full Legal Name',
        'legal_btn' => 'Digitally Sign & Enter',
        'legal_terms' => 'Terms of Use/Service',
        'legal_privacy' => 'Privacy Policy',
        'btn_back_home' => ('Back to ' . IdealisticOfficeVariable::APPLICATION_NAME)
    ];

    private const FORM_CSS = <<<'CSS'
    :root {
        --bg: #f8f9fa;
        --surface: #ffffff;
        --text: #212529;
        --text-muted: #6c757d;
        --border-color: rgba(0, 0, 0, 0.08);
        --input-bg: #f8f9fa;
        --primary: #0d6efd;
    }

    html[data-theme='dark'] {
        --bg: #0f172a;
        --surface: #1e293b;
        --text: #f1f5f9;
        --text-muted: #94a3b8;
        --border-color: rgba(255, 255, 255, 0.08);
        --input-bg: #0f172a;
        --primary: #3b82f6;
    }

    body {
        background: var(--bg);
        color: var(--text);
        font-family: 'Inter', -apple-system, sans-serif;
        margin: 0;
        min-height: 100vh;
        display: flex;
        flex-direction: column;
        transition: background .3s, color .3s;
    }

    /* Core Utility Classes */
    .border-color { border-color: var(--border-color) !important; }
    .text-body { color: var(--text) !important; }
    .text-secondary-theme { color: var(--text-muted) !important; }
    
    /* Login & Legal Form Styles */
    .full-height-center { min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 1rem; }
    .auth-card { max-width: 420px; width: 100%; background: var(--surface); border: 1px solid var(--border-color); border-radius: 1.5rem; padding: 2.5rem; box-shadow: 0 0.5rem 1rem rgba(0,0,0,0.05); text-align: center; }
    html[data-theme='dark'] .auth-card { box-shadow: 0 0.5rem 1rem rgba(0,0,0,0.3); }
    
    .form-control { background: var(--input-bg); border: 1px solid var(--border-color); color: var(--text); border-radius: 0.5rem; }
    .form-control:focus { border-color: var(--primary); box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25); background: var(--input-bg); color: var(--text); }
    
    .legal-footer { margin-top: 1.5rem; display: flex; justify-content: center; gap: 1.5rem; }
    .legal-link { font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); text-decoration: none; letter-spacing: 0.5px; font-weight: 600; }
    .legal-link:hover { color: var(--primary); }

    .spinner { display: none; width: 1.5rem; height: 1.5rem; border: 0.2em solid currentColor; border-right-color: transparent; border-radius: 50%; animation: spinner-border .75s linear infinite; margin: 0 auto; }
    @keyframes spinner-border { to { transform: rotate(360deg); } }
CSS;

    private const LEGAL_TEMPLATE = <<<'HTML'
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="robots" content="noindex, nofollow">
    <title>Terms & Conditions</title>
    <link href="https://www.idealistic.ai/.design/bootstrap.min.css" rel="stylesheet">
    <style>{{CSS}}</style>
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>
    <script>
        function showLoading() {
            document.querySelectorAll('.auth-btn').forEach(btn => btn.style.display = 'none');
            document.querySelector('.spinner').style.display = 'block';
        }
        
        if (localStorage.getItem('theme') === 'dark') { 
            document.documentElement.setAttribute('data-theme', 'dark'); 
        }
    </script>
</head>
<body>
    <div class="full-height-center">
        <div class="auth-card">
            <div class="mb-4">
                <img src="https://www.idealistic.ai/.images/logoCircular.png" width="60" height="60" alt="Logo" class="mb-3">
                <h4 class="fw-bold mb-1 text-body">{{TXT_BRAND}}</h4>
            </div>
            <form method="post" id="legalForm" autocomplete="off" onsubmit="showLoading()">
                <h5 class="fw-bold mb-2 text-body">{{TXT_TITLE}}</h5>
                <p class="text-secondary-theme small mb-4">{{TXT_DESC}}</p>
                
                {{ERROR}}
                
                <input type="text" style="display:none" name="fake_user">
                <div class="mb-4 text-start">
                    <label class="form-label small fw-bold text-secondary-theme text-uppercase">{{TXT_PLACEHOLDER}}</label>
                    <input type="text" name="full_name" required autofocus autocomplete="name" class="form-control form-control-lg text-body">
                </div>
                
                <div class="d-flex justify-content-center mb-4">
                    <div class="g-recaptcha" data-sitekey="{{RECAPTCHA_KEY}}"></div>
                </div>
                
                <button type="submit" class="btn btn-primary w-100 py-3 mb-2 rounded-pill fw-bold auth-btn">{{TXT_BTN}}</button>
                <a href="{{TXT_WEBSITE_URL}}" class="btn btn-outline-secondary w-100 py-3 rounded-pill fw-bold auth-btn" style="text-decoration: none;">{{TXT_BACK_HOME}}</a>
                <div class="spinner text-primary mt-3"></div>
                
                <div class="legal-footer">
                    <a href="{{TXT_TERMS_URL}}" class="legal-link" target="_blank">{{TXT_TERMS}}</a>
                    <a href="{{TXT_PRIVACY_URL}}" class="legal-link" target="_blank">{{TXT_PRIVACY}}</a>
                </div>
            </form>
            <div class="mt-4 pt-3 border-top border-color text-secondary-theme" style="font-size: 0.7rem; font-family: monospace;">
                {{IP}}
            </div>
        </div>
    </div>
</body>
</html>
HTML;

    public static function renderLegalForm(string $ipAddress, ?string $error = null): string
    {
        $errorHtml = ($error) ? '<div class="alert alert-danger border-0 small py-2 mb-3">' . htmlspecialchars($error) . '</div>' : '';
        $map = [
            '{{CSS}}' => self::FORM_CSS,
            '{{IP}}' => htmlspecialchars($ipAddress),
            '{{ERROR}}' => $errorHtml,
            '{{RECAPTCHA_KEY}}' => IdealisticOfficeVariable::CAPTCHA_SITE_KEY,
            '{{TXT_BRAND}}' => IdealisticOfficeVariable::APPLICATION_NAME,
            '{{TXT_TITLE}}' => self::$LANG['legal_title'],
            '{{TXT_DESC}}' => self::$LANG['legal_desc'],
            '{{TXT_PLACEHOLDER}}' => self::$LANG['legal_lbl_name'],
            '{{TXT_BTN}}' => self::$LANG['legal_btn'],
            '{{TXT_BACK_HOME}}' => self::$LANG['btn_back_home'],
            '{{TXT_TERMS}}' => self::$LANG['legal_terms'],
            '{{TXT_PRIVACY}}' => self::$LANG['legal_privacy'],
            '{{TXT_TERMS_URL}}' => IdealisticOfficeVariable::TERMS_OF_USE_URL,
            '{{TXT_PRIVACY_URL}}' => IdealisticOfficeVariable::PRIVACY_POLICY_URL,
            '{{TXT_WEBSITE_URL}}' => IdealisticOfficeVariable::COMPANY_WEBSITE_URL
        ];
        return str_replace(array_keys($map), array_values($map), self::LEGAL_TEMPLATE);
    }

}