<?php

class IdealisticOfficePortalScheduler implements PhpAsyncSerializable
{

    public static function run(): bool
    {
        try {
            return IdealisticOfficePortalIndependent::processHistory()
                | IdealisticOfficeWhatsApp::deliverInstalledPortalMessages();
        } catch (Throwable $e) {
            IdealisticOfficeError::storeThrowable(
                null,
                null,
                $e
            );
        }
        return false;
    }

}
