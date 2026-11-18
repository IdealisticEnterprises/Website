<?php

namespace idealistic_office\api\tasks\schedulers\combined;

use PhpAsyncSerializable;

class IdealisticOfficeCombinedScheduler implements PhpAsyncSerializable
{

    public static function run(): bool
    {
        // Most to least important
        return IdealisticOfficeHistoryWebhookNotificationScheduler::run()
            | IdealisticOfficeHistoryGmailScheduler::run()
            | IdealisticOfficePortalScheduler::run()
            | IdealisticOfficeHistorySchedulersScheduler::run()
            | IdealisticOfficeTransactionScheduler::run()
            | IdealisticOfficeOldTeamsScheduler::run();
    }

}
