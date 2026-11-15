<?php
require '/var/www/.structure/library/base/utilities.php';
require '/var/www/.structure/library/idealistic_office/init.php';
IdealisticOfficeBrand::start();

$text = "USER DATA DELETION\n"
    . "Effective Date: 6th of September 2025\n\n"
    . "Please email us and attach any relevant details such as email address, usernames, full names, e.t.c you have used with our services.\n"
    . "We may ask for additional information or additional processes to be completed to truly verify it is you creating this request.\n"
    . "We will find any correlated information and process your request as soon as possible, confirming to you once the deletion has been completed.\n\n"
    . "CONTACT US\n"
    . "If you have questions about this process, you can contact us at:\n"
    . IdealisticOfficeVariable::APPLICATION_NAME . "\n"
    . IdealisticOfficeVariable::SUPPORT_EMAIL . "\n"
    . IdealisticOfficeVariable::COMPANY_ADDRESS;

IdealisticOfficeLegal::render($text, IdealisticOfficeVariable::USER_DATA_DELETION_INSTRUCTIONS_URL);
