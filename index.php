<?php
$portalParameters = ['id', 'default_message', 'default_url', 'deleted', 'signed_out', 'signed_in', 'embedded'];
$isPortalRequest = !in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['GET', 'HEAD'], true)
    || (isset($_GET['in']) && $_GET['in'] === 'web');

foreach ($portalParameters as $parameter) {
    if (isset($_GET[$parameter])) {
        $isPortalRequest = true;
        break;
    }
}

if ($isPortalRequest) {
    include '/var/www/.structure/library/idealistic_office/api/tasks/website/v1/index.php';
} else {
    include __DIR__ . '/home.php';
}
