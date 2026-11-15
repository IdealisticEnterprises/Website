<?php
$portalParameters = ['id', 'default_message', 'default_url', 'deleted', 'signed_out', 'signed_in', 'embedded'];
$in = isset($_GET['in']) && is_string($_GET['in']) ? $_GET['in'] : null;
$isReadRequest = in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['GET', 'HEAD'], true);
$route = strtolower(trim((string)parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH), '/'));

if ($isReadRequest && in_array($in, ['web', 'webchat', 'chat', 'structure'], true)) {
    $query = $_GET;
    unset($query['in']);
    header('Location: /' . ($in === 'structure' ? 'structure' : 'chat') . (empty($query) ? '' : '?' . http_build_query($query)), true, 301);
    exit;
}
$view = in_array($route, ['chat', 'structure'], true) ? $route : $in;

if ($view === 'structure') {
    include '/var/www/.structure/library/idealistic_office/api/tasks/input_output/v1/structure/view/index.php';
    return;
}
$isPortalRequest = !$isReadRequest || $view === 'chat';

foreach ($portalParameters as $parameter) {
    if (isset($_GET[$parameter])) {
        $isPortalRequest = true;
        break;
    }
}

if ($isPortalRequest) {
    include '/var/www/.structure/library/idealistic_office/api/tasks/input_output/v1/portal/view/index.php';
} else {
    include '/var/www/.structure/library/idealistic_office/api/tasks/website/v1/home/index.php';
}
