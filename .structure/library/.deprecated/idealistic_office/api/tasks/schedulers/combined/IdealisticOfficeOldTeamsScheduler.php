<?php

namespace idealistic_office\api\tasks\schedulers\combined;

use idealistic_office\api\handlers\IdealisticOfficeError;
use idealistic_office\api\handlers\IdealisticOfficeTeamInitiator;
use PhpAsyncSerializable;

class IdealisticOfficeOldTeamsScheduler implements PhpAsyncSerializable
{

    public static function run(): bool
    {
        try {
            return IdealisticOfficeTeamInitiator::deleteOldTeams()
                | IdealisticOfficeTeamInitiator::deleteExpiredData();
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
