<?php

if (!class_exists('IdealisticOfficeVariable', false)) {
    require_once '/var/www/.structure/library/idealistic_office/init.php';
}

if (!function_exists('io_e')) {
    function io_e($value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('io_asset')) {
    function io_asset(string $path): string
    {
        $file = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/') . '/' . ltrim($path, '/');
        $version = is_file($file) ? filemtime($file) : false;
        return IdealisticOfficeVariable::COMPANY_WEBSITE_URL . '/' . ltrim($path, '/') . ($version ? '?v=' . $version : '');
    }
}

if (!function_exists('io_footer')) {
    function io_footer(): void
    {
        $site = IdealisticOfficeVariable::COMPANY_WEBSITE_URL;
        $supportEmail = IdealisticOfficeVariable::SUPPORT_EMAIL;
        $businessEmail = IdealisticOfficeVariable::BUSINESS_EMAIL;
        ?>
<footer class="footer">
    <div class="wrap">
        <div class="footer-grid">
            <div>
                <a class="brand" href="<?= io_e($site) ?>/">
                    <img src="<?= io_e($site) ?>/.images/logoWhite.svg" alt="" width="30" height="30" loading="lazy">
                    <span><?= IdealisticOfficeVariable::APPLICATION_NAME ?></span>
                </a>
                <p><?= io_e(IdealisticOfficeWebsite::TAGLINE) ?>.</p>
                <p>Support: <a href="mailto:<?= io_e($supportEmail) ?>"><?= io_e($supportEmail) ?></a><br>
                    <small><?= io_e(IdealisticOfficeVariable::SUPPORT_RESPONSE_NOTE) ?></small></p>
                <p>Business &amp; Enterprise: <a href="mailto:<?= io_e($businessEmail) ?>"><?= io_e($businessEmail) ?></a></p>
            </div>
            <div>
                <h4>Deploy</h4>
                <a href="<?= io_e(IdealisticOfficeVariable::DISCORD_APP_URL) ?>">Discord</a>
                <a href="<?= io_e(IdealisticOfficeVariable::TELEGRAM_APP_URL) ?>">Telegram</a>
                <a href="<?= io_e(IdealisticOfficeVariable::WHATSAPP_APP_URL) ?>">WhatsApp</a>
                <a href="<?= io_e($site) ?>/#setup">Chat widget</a>
            </div>
            <div>
                <h4>Configure</h4>
                <a href="<?= io_e(IdealisticOfficeVariable::MESSENGER_APP_URL) ?>">Messenger</a>
                <a href="<?= io_e(IdealisticOfficeVariable::INSTAGRAM_APP_URL) ?>">Instagram</a>
                <a href="<?= io_e(IdealisticOfficeVariable::IOS_APP_URL) ?>">iOS</a>
                <a href="<?= io_e(IdealisticOfficeVariable::ANDROID_APP_URL) ?>">Android</a>
                <a href="<?= io_e(IdealisticOfficeVariable::PORTAL_CHAT_URL) ?>">Chat</a>
                <a href="<?= io_e(IdealisticOfficeVariable::PORTAL_STRUCTURE_URL) ?>">Structure</a>
            </div>
            <div>
                <h4>Company</h4>
                <a href="<?= io_e(IdealisticOfficeVariable::GITHUB_URL) ?>">GitHub</a>
                <a href="<?= io_e(IdealisticOfficeVariable::YOUTUBE_URL) ?>">YouTube</a>
                <a href="<?= io_e(IdealisticOfficeVariable::WORK_URL) ?>"><?= io_e(IdealisticOfficeWebsite::WORK_NAV) ?></a>
                <a href="<?= io_e(IdealisticOfficeVariable::TERMS_OF_USE_URL) ?>">Terms of Use</a>
                <a href="<?= io_e(IdealisticOfficeVariable::PRIVACY_POLICY_URL) ?>">Privacy Policy</a>
                <a href="<?= io_e($site) ?>/#data">Data retention</a>
                <a href="<?= io_e(IdealisticOfficeVariable::USER_DATA_DELETION_INSTRUCTIONS_URL) ?>">Data deletion</a>
                <a href="<?= io_e(IdealisticOfficeVariable::SPARTAN_URL) ?>">Spartan AntiCheat</a>
            </div>
        </div>
        <p class="copyright">&copy; <?= date('Y') ?> <a href="<?= io_e(IdealisticOfficeVariable::COMPANY_DETAILS) ?>"><?= io_e(IdealisticOfficeVariable::COMPANY_NAME) ?></a>, <?= io_e(IdealisticOfficeVariable::COMPANY_ADDRESS) ?>. All rights reserved. <a href="<?= io_e(IdealisticOfficeVariable::TRADEMARK_URL) ?>">EU trademark no. <?= io_e(IdealisticOfficeVariable::TRADEMARK_NUMBER) ?></a>.</p>
        <p class="copyright"><?= io_e(IdealisticOfficeWebsite::TRANSLATE_NOTE) ?></p>
    </div>
</footer>
        <?php
    }
}
