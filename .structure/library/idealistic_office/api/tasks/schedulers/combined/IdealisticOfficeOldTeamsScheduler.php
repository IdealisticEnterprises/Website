<?php

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
