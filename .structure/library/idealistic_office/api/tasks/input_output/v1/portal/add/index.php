<?php
header('Content-Type: application/json; charset=utf-8');
header("Cache-Control: no-store");

require '/var/www/.structure/library/base/form.php';
require '/var/www/.structure/library/idealistic_office/init.php';

if (!IdealisticOfficeChat::isRequest()
    || !IdealisticOfficeChat::isSameOrigin()) {
    IdealisticOfficeChat::reply(false, "Invalid request.", 403);
    return;
}
$submittedSession = trim((string)get_form_post("session_id"));
$message = get_form_post("message", "");
$language = get_form_post("language", IdealisticOfficeVariable::DEFAULT_LANGUAGE);
$defaultUrl = get_form_post("default_url", null);

if ($message === "") {
    $message = null;
}
$voiceFile = $_FILES['voice'] ?? null;
$generalFile = $_FILES['file'] ?? null;

if (($message === null && empty($voiceFile['tmp_name']) && empty($generalFile['tmp_name']))
    || ($message !== null && mb_strlen($message) > IdealisticOfficeLimit::K2_LENGTH)

    || !IdealisticOfficeReader::isLatinOnly($language)
    || !in_array($language, IdealisticOfficeLimit::ALL_LANGUAGES)

    || !empty($defaultUrl) && !is_url($defaultUrl) && !is_domain($defaultUrl)) {
    $object = new stdClass();
    $object->success = false;
    $object->message = "Invalid input.";
    echo IdealisticOfficeReader::jsonObject($object, false);
    return;
}
try {
    $randomId = get_form_post("random_id");

    if (!empty($randomId)
        && strlen($randomId) !== IdealisticOfficeLimit::URL_RANDOM_ID_LENGTH
        && !is_numeric($randomId)) {
        echo IdealisticOfficeReader::jsonObject(new IdealisticOfficeOutcome(false, "Invalid random ID."), false);
        return;
    }

    $account = new Account(Account::IDEALISTIC_OFFICE_APPLICATION_ID);
    $session = $account->getSession();
    $sessionId = IdealisticOfficeChat::sessionKey($session, $submittedSession);

    if ($sessionId === null) {
        echo IdealisticOfficeReader::jsonObject(new IdealisticOfficeOutcome(false, "Invalid session."), false);
        return;
    }

    if (has_memory_cooldown(
        strtolower(IdealisticOfficeVariable::APPLICATION_NAME) . "_portal_add_" . $sessionId,
        IdealisticOfficeLimit::CHAT_PORTAL_MESSAGE_COOLDOWN
    )) {
        echo IdealisticOfficeReader::jsonObject(
            new IdealisticOfficeOutcome(
                false,
                "Please wait before trying to send another message."
            ),
            false
        );
        return;
    }

    $portal = IdealisticOfficePortalIndependent::getPortal(
        $randomId,
        empty($randomId) || !is_numeric($randomId),
        false,
        $defaultUrl
    );

    if ($portal === null) {
        echo IdealisticOfficeReader::jsonObject(new IdealisticOfficeOutcome(false, "Portal not found."), false);
        return;
    }
    $portal->setPlatformId(IdealisticOfficeAccessPlatform::NATIVE);

    if ($portal->isLoginRequired()) {
        if ($submittedSession !== "") {
            $session->setCustomKey($sessionId);
        }
        $sessionOutcome = $session->find(IdealisticOfficeVariable::DEFAULT_SESSION_CHECK_IP_ADDRESS);

        if (!$sessionOutcome->isPositiveOutcome()) {
            echo IdealisticOfficeReader::jsonObject(
                new IdealisticOfficeOutcome(
                    false,
                    "You must be logged in to send messages."
                ),
                false
            );
            return;
        }
    }
    $dummyAccount = new Account();
    $currentDate = IdealisticOfficeReader::getCurrentDate();
    $voiceAttachment = null;
    $fileAttachment = null;

    if (!empty($voiceFile['tmp_name'])
        && $voiceFile['error'] === UPLOAD_ERR_OK) {

        if ($voiceFile['size'] > IdealisticOfficeLimit::MAX_ATTACHMENT_BYTES_SIZE) {
            echo IdealisticOfficeReader::jsonObject(new IdealisticOfficeOutcome(false, "Voice file is too large."), false);
            return;
        }

        $voiceAttachment = new IdealisticOfficeAttachment(
            null,
            $voiceFile['name'],
            'audio/ogg',
            null,
            $voiceFile['size'],
            null,
            null,
            null,
            base64_encode(file_get_contents($voiceFile['tmp_name'])),
            null,
            true,
            IdealisticOfficeReader::getCurrentDate(),
            IdealisticOfficeVariable::DEFAULT_TIME_ZONE,
            $dummyAccount,
            false
        );

        if (!$voiceAttachment->isValid()) {
            echo IdealisticOfficeReader::jsonObject(new IdealisticOfficeOutcome(false, "Invalid voice attachment."), false);
            return;
        }
    }

    if (!empty($generalFile['tmp_name'])
        && $generalFile['error'] === UPLOAD_ERR_OK) {

        if ($generalFile['size'] > IdealisticOfficeLimit::MAX_ATTACHMENT_BYTES_SIZE) {
            echo IdealisticOfficeReader::jsonObject(new IdealisticOfficeOutcome(false, "File is too large."), false);
            return;
        }

        $fileAttachment = new IdealisticOfficeAttachment(
            null,
            $generalFile['name'],
            AbstractIdealisticOfficeAttachment::getFormatFromName($generalFile['name']),
            null,
            $generalFile['size'],
            null,
            null,
            null,
            base64_encode(file_get_contents($generalFile['tmp_name'])),
            null,
            true,
            $currentDate,
            IdealisticOfficeVariable::DEFAULT_TIME_ZONE,
            $dummyAccount,
            false
        );

        if (!$fileAttachment->isValid()) {
            echo IdealisticOfficeReader::jsonObject(new IdealisticOfficeOutcome(false, "Invalid file attachment."), false);
            return;
        }
    }

    echo IdealisticOfficeReader::jsonObject(
        $portal->addHistory(
            $sessionId,
            get_client_ip_address(),
            $message,
            $voiceAttachment,
            $fileAttachment,
            $language
        ),
        false
    );
} catch (Throwable $e) {
    echo IdealisticOfficeReader::jsonObject(
        new IdealisticOfficeOutcome(false, "An error occurred while processing the request."),
        false
    );
    IdealisticOfficeError::simpleStore($e);
}