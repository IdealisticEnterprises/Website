<?php
require_once '/var/www/.structure/library/base/utilities.php';
require_once '/var/www/.structure/library/idealistic_office/init.php';

$userAgent = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
$platform = strtolower(trim((string)($_SERVER['HTTP_SEC_CH_UA_PLATFORM'] ?? ''), " \""));

if ($platform === 'android' || stripos($userAgent, 'android') !== false) {
    $target = IdealisticOfficeVariable::ANDROID_STORE_URL;
} elseif ($platform === 'ios' || preg_match('/iPhone|iPad|iPod/i', $userAgent) === 1) {
    $target = IdealisticOfficeVariable::IOS_STORE_URL;
} else {
    $target = IdealisticOfficeVariable::COMPANY_WEBSITE_URL . '/';
}
header('Vary: User-Agent, Sec-CH-UA-Platform');
header('Accept-CH: Sec-CH-UA-Platform');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex');
header('Location: ' . $target, true, 302);
exit;
