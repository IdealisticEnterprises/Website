<?php
// The homepage is the landing page. Everything that belongs to the chat portal is still rendered by the portal view,
// exactly as before: the demo behind /?try=1, shared portals (/?id=...), sign-in results and every form submission.
$portalParameters = ['id', 'try', 'default_message', 'default_url', 'lang_changed', 'deleted', 'signed_out', 'signed_in', 'embedded'];
$isPortalRequest = !in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['GET', 'HEAD'], true);

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
