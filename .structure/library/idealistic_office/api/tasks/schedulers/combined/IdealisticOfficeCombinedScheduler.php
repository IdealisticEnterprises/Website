<?php

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
