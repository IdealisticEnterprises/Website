<?php
require '/var/www/.structure/library/base/form.php';
require '/var/www/.structure/library/idealistic_office/init.php';

try {
    $account = new Account(Account::IDEALISTIC_OFFICE_APPLICATION_ID);
    $session = $account->getSession();
    $outcome = $session->find(IdealisticOfficeVariable::DEFAULT_SESSION_CHECK_IP_ADDRESS);

    if (!$outcome->isPositiveOutcome()) {
        http_response_code(401);
        echo "You must be signed in to download your data.";
        return;
    }
    $user = AbstractIdealisticOfficeTeam::findUserDynamic($account, false);

    if (!($user instanceof IdealisticOfficeUser)) {
        http_response_code(403);
        echo "No account access established.";
        return;
    }
    $accountId = $user->getId();
    $cooldownKey = strtolower(IdealisticOfficeVariable::APPLICATION_NAME) . "_account_download_" . $accountId;

    if (has_memory_cooldown($cooldownKey, "1 day")
        && !$user->isAdministrator()) {
        http_response_code(429);
        echo "You may download again in the future, please try again later.";
        return;
    }
    $export = IdealisticOfficeAccountExporter::export($accountId, $user->getTeam()->getId());
    $json = IdealisticOfficeReader::jsonObject($export, true);

    if ($json === null) {
        http_response_code(500);
        echo "Failed to prepare your data.";
        return;
    }
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="'
        . strtolower(IdealisticOfficeVariable::APPLICATION_NAME)
        . '-data-' . $accountId . '-' . date('Y-m-d') . '.json"');
    header('Content-Length: ' . strlen($json));
    echo $json;
} catch (Throwable $e) {
    http_response_code(500);
    echo "An error occurred while preparing your data.";
    IdealisticOfficeError::simpleStore($e);
}
