<?php
require '/var/www/.structure/library/base/form.php';
require '/var/www/.structure/library/base/utilities.php';
$history = get_form_get("history");
$time = get_form_get("time");
$account = get_form_get("account");
$email = get_form_get("email");
$team = get_form_get("team");

if (!empty($history)
    && is_numeric($time)
    && $time > 0
    && is_email($email)
    && is_numeric($account)
    && !empty($team)) {
    require '/var/www/.structure/library/idealistic_office/init.php';
    IdealisticOfficeBrand::start();

    try {
        $account = new Account(
            Account::IDEALISTIC_OFFICE_APPLICATION_ID,
            $account
        );

        if ($account->exists()) {
            if ($account->getDetail("email_address") === $email) {
                $teamObj = new IdealisticOfficeTeam($account);

                if ($teamObj->hasEstablishedAccess()) {
                    if ($team == $teamObj->getId()) {
                        $lastHistory = $teamObj->getLastHistory();

                        if ($lastHistory?->getId() == $history
                            && strtotime($lastHistory?->getCreationDate() ?? IdealisticOfficeReader::getCurrentDate()) == $time) {
                            $initiator = $teamObj->getInitiatorUser();

                            if ($initiator->getAccount()->exists()) {
                                if (!$initiator->hasLegalRecord()) {
                                    $fullName = get_form_post("full_name");
                                    $fake = get_form_post("fake_user");

                                    if (empty($fullName)
                                        || !empty($fake)
                                        || !is_google_captcha_valid()) {
                                        echo IdealisticOfficeWebsiteHTML::renderLegalForm(
                                            get_client_ip_address(),
                                            "Full name is required."
                                        );
                                        http_response_code(403);
                                        exit();
                                    } else {
                                        $lastIdentity = $initiator->getLastIdentity(
                                            null,
                                            true
                                        );

                                        if ($lastIdentity !== null) {
                                            $legalRecord = new IdealisticOfficeLegalRecord(
                                                0,
                                                $initiator->getAccount(),
                                                $teamObj,
                                                IdealisticOfficeLegalRecord::CURRENT_VERSION,
                                                $fullName,
                                                get_client_ip_address(),
                                                $history,
                                                $lastIdentity,
                                                IdealisticOfficeReader::getCurrentDate(),
                                                IdealisticOfficeVariable::DEFAULT_TIME_ZONE,
                                                false
                                            );
                                            $legalRecord = $legalRecord->create();

                                            if ($legalRecord->isPositiveOutcome()) {
                                                echo IdealisticOfficeWebsiteHTML::renderLegalForm(
                                                    get_client_ip_address(),
                                                    $legalRecord->getRawMessage()
                                                );
                                                http_response_code(200);
                                                exit();
                                            } else {
                                                echo IdealisticOfficeWebsiteHTML::renderLegalForm(
                                                    get_client_ip_address(),
                                                    $legalRecord->getRawMessage()
                                                );
                                                http_response_code(500);
                                                exit();
                                            }
                                        } else {
                                            echo IdealisticOfficeWebsiteHTML::renderLegalForm(
                                                get_client_ip_address(),
                                                "User identity could not be verified.",
                                            );
                                        }
                                    }
                                } else {
                                    echo IdealisticOfficeWebsiteHTML::renderLegalForm(
                                        get_client_ip_address(),
                                        "This form has already been completed.",
                                    );
                                }
                            } else {
                                echo IdealisticOfficeWebsiteHTML::renderLegalForm(
                                    get_client_ip_address(),
                                    "Team/company initiator could not be found.",
                                );
                            }
                        } else {
                            echo IdealisticOfficeWebsiteHTML::renderLegalForm(
                                get_client_ip_address(),
                                "History does not match the latest record.",
                            );
                        }
                    } else {
                        echo IdealisticOfficeWebsiteHTML::renderLegalForm(
                            get_client_ip_address(),
                            "Team/company does not match account.",
                        );
                    }
                } else {
                    echo IdealisticOfficeWebsiteHTML::renderLegalForm(
                        get_client_ip_address(),
                        "Correlated team/company could not be found.",
                    );
                }
            } else {
                echo IdealisticOfficeWebsiteHTML::renderLegalForm(
                    get_client_ip_address(),
                    "Email address does not match account.",
                );
            }
        } else {
            echo IdealisticOfficeWebsiteHTML::renderLegalForm(
                get_client_ip_address(),
                "Account does not exist.",
            );
        }
    } catch (Throwable $e) {
        echo "An error occurred while processing this form.";
        IdealisticOfficeError::simpleStore($e);
    }
}
http_response_code(404);
