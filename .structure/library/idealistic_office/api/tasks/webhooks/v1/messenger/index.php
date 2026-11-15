<?php
require '/var/www/.structure/library/idealistic_office/init.php';
$PAGE_ACCESS_TOKEN = get_keys_from_file("messenger_credentials", 2);

if ($PAGE_ACCESS_TOKEN === null) {
    http_response_code(500);
    return;
}
$VERIFY_TOKEN = $PAGE_ACCESS_TOKEN[1];
$PAGE_ACCESS_TOKEN = $PAGE_ACCESS_TOKEN[0];

if ($_SERVER["REQUEST_METHOD"] === "GET"
    && array_key_exists("hub_verify_token", $_GET)) {
    if (verify_shared_secret($VERIFY_TOKEN, $_GET["hub_verify_token"])) {
        echo $_GET["hub_challenge"] ?? "";
    } else {
        echo "Invalid verify token";
    }
    return;
}

$meta = new IdealisticOfficeMeta($PAGE_ACCESS_TOKEN, false);

if (!$meta->handleMessage()) {
    http_response_code(401);
    return;
}
http_response_code(200);