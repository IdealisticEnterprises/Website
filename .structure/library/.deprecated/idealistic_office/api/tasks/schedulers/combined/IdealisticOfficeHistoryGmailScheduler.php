<?php

namespace idealistic_office\api\tasks\schedulers\combined;

use account\api\objects\account\Account;
use idealistic_office\api\handlers\IdealisticOfficeError;
use idealistic_office\api\handlers\IdealisticOfficeGmail;
use idealistic_office\api\handlers\IdealisticOfficeGmailRunner;
use PhpAsyncSerializable;

class IdealisticOfficeHistoryGmailScheduler implements PhpAsyncSerializable
{

    private const
        TRACKER_ENABLED = false,
        TRACKER_COOLDOWN_NAME = "idealistic_gmail_tracker",
        TRACKER_COOLDOWN_DURATION = "1 second",

        WATCH_COOLDOWN_NAME = "idealistic_gmail_watch",
        WATCH_LONG_COOLDOWN_DURATION = "1 day",
        WATCH_SHORT_COOLDOWN_DURATION = "1 hour";

    public static function run(): bool
    {
        try {
            if (self::TRACKER_ENABLED
                && !has_memory_cooldown(
                    self::TRACKER_COOLDOWN_NAME,
                    self::TRACKER_COOLDOWN_DURATION
                )) {
                $account = new Account(
                    null,
                    Account::SYSTEM_ACCOUNT_ID,
                    null,
                    null,
                    null,
                    false
                );

                if (!$account->getCooldowns()->has(self::TRACKER_COOLDOWN_NAME, true, true, false)
                    && $account->getCooldowns()->addInstant(self::TRACKER_COOLDOWN_NAME, self::TRACKER_COOLDOWN_DURATION)) {
                    IdealisticOfficeGmailRunner::run();
                }
            } else {
                $account = null;
            }
            if (!has_memory_cooldown(
                self::WATCH_COOLDOWN_NAME,
                self::WATCH_SHORT_COOLDOWN_DURATION
            )) {
                if ($account === null) {
                    $account = new Account(
                        null,
                        Account::SYSTEM_ACCOUNT_ID,
                        null,
                        null,
                        null,
                        false
                    );
                }

                if (!$account->getCooldowns()->has(self::WATCH_COOLDOWN_NAME, true, true, false)
                    && $account->getCooldowns()->addInstant(self::WATCH_COOLDOWN_NAME, self::WATCH_LONG_COOLDOWN_DURATION)) {
                    $gmail = new IdealisticOfficeGmail();
                    $gmail->newWatch();
                }
            }
            return true;
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
