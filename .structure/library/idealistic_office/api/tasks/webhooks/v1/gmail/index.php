<?php
require '/var/www/.structure/library/idealistic_office/init.php';
$webhookSecret = get_keys_from_file("gmail_webhook_secret", 1);

if ($webhookSecret === null
    || !verify_shared_secret($webhookSecret[0], $_GET["token"] ?? null)) {
    http_response_code(401);
    return;
}
$data = json_decode(timed_file_get_contents("php://input", IdealisticOfficeLimit::LINK_DOWNLOAD_SECONDS_TIMEOUT), true);

if (!is_array($data)) {
    IdealisticOfficeError::simpleStore(
        "Gmail data is not an array"
    );
    http_response_code(500);
    return;
}
$base64 = $data["message"]["data"] ?? null;

if ($base64 === null) {
    http_response_code(500);
    return;
}
$decoded = base64_decode($base64);

if ($decoded === false) {
    http_response_code(500);
    return;
}
$decoded = json_decode($decoded, true);

if (!is_array($decoded)) {
    http_response_code(500);
    return;
}
$historyId = $decoded["historyId"] ?? null;

if ($historyId === null) {
    http_response_code(500);
    return;
}
IdealisticOfficeGmailRunner::run($historyId);
http_response_code(200);
