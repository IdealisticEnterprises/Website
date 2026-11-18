<?php

namespace idealistic_office\api\tasks\schedulers\combined;

use idealistic_office\api\handlers\IdealisticOfficeError;
use idealistic_office\api\handlers\IdealisticOfficePortalIndependent;
use idealistic_office\api\handlers\IdealisticOfficeWhatsApp;
use PhpAsyncSerializable;

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
