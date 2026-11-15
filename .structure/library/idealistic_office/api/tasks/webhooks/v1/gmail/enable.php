<?php
try {
    require '/var/www/.structure/library/idealistic_office/init.php';
    $enableSecret = get_keys_from_file("gmail_enable_secret", 1);

    if ($enableSecret === null
        || !verify_shared_secret($enableSecret[0], $_GET["token"] ?? null)) {
        http_response_code(401);
        exit(1);
    }
    $gmail = new IdealisticOfficeGmail();
    echo "Gmail watch started. History ID: " . $gmail->newWatch() . "\n";
} catch (Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}

