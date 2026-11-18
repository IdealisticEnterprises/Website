<?php

namespace idealistic_office\api\objects\helpers;

use idealistic_office\api\handlers\IdealisticOfficeError;
use idealistic_office\api\handlers\IdealisticOfficeNotifications;
use idealistic_office\api\handlers\IdealisticOfficeReader;
use idealistic_office\api\handlers\IdealisticPaddleApi;
use idealistic_office\api\IdealisticOfficeVariable;
use idealistic_office\api\objects\IdealisticOfficePrompt;
use idealistic_office\api\objects\IdealisticOfficeUser;
use IdealisticOfficePlans;
use IdealisticOfficeTable;
use PhpAsyncSerializable;

class IdealisticOfficeCredit implements PhpAsyncSerializable
{

    public const ALLOW_PAYPAL = false;

    public static function getPackages(): array
    {
        return self::ALLOW_PAYPAL
            ? array()
            : array(
                "19 credits / 20 EUR" => "https://www.paypal.com/ncp/payment/QWADF6EE8XFDJ",
                "48 credits / 50 EUR" => "https://www.paypal.com/ncp/payment/M53YB77DGHHJ8",
                "96 credits / 100 EUR" => "https://www.paypal.com/ncp/payment/LN33ES36CR44U",
                "193 credits / 200 EUR" => "https://www.paypal.com/ncp/payment/CSDX8CWC9ECY6"
            );
    }

    public static function getEquivalence(): array
    {
        return self::ALLOW_PAYPAL
            ? array()
            : array(
                20 => 19,
                50 => 48,
                100 => 96,
                200 => 193
            );
    }

    public static function getIncludedMessagesFromEmail(
        string $email,
        int    $secondsPassed = 0,
        string $key = IdealisticPaddleApi::INCLUDED_CREDITS_KEY
    ): int
    {
        $api = new IdealisticPaddleApi();
        $customer = $api->getCustomerByEmail($email, $secondsPassed);

        if ($customer !== null) {
            $items = $api->getCustomerActiveItems($customer->id);

            if (!empty($items)) {
                $included = 0;

                foreach ($items as $item) {
                    $included += $item?->price?->customData[$key] ?? 0;
                }
                return $included;
            }
        }
        return 0;
    }

    private static function getActiveItemsFromEmail(string $email): array
    {
        $api = new IdealisticPaddleApi();
        $customer = $api->getCustomerByEmail($email);
        return $customer === null ? array() : $api->getCustomerActiveItems($customer->id);
    }

    public static function getContextLengthFromItems(array $items): int
    {
        $context = 0;

        foreach ($items as $item) {
            $context = max(
                $context,
                IdealisticPaddleApi::parseAmount($item?->price?->customData[IdealisticPaddleApi::CONTEXT_LENGTH_KEY] ?? 0)
            );
        }
        return $context;
    }

    public static function getHasLogoFromItems(array $items): ?bool
    {
        $hasLogo = null;

        foreach ($items as $item) {
            $value = IdealisticPaddleApi::parseBoolean($item?->price?->customData[IdealisticPaddleApi::HAS_LOGO_KEY] ?? null);

            if ($value === false) {
                return false;
            }

            if ($value === true) {
                $hasLogo = true;
            }
        }
        return $hasLogo;
    }

    public static function getContextLengthFromEmail(string $email): int
    {
        return self::getContextLengthFromItems(self::getActiveItemsFromEmail($email));
    }

    public static function getHasLogoFromEmail(string $email): ?bool
    {
        return self::getHasLogoFromItems(self::getActiveItemsFromEmail($email));
    }

    // Separator

    private AbstractIdealisticOfficeTeam $team;

    public function __construct(
        AbstractIdealisticOfficeTeam $team
    )
    {
        $this->team = $team;
    }

    public function add(
        ?int                    $historyID,
        float                   $amount,
        ?IdealisticOfficePrompt $prompt = null,
        bool                    $restoreNegative = false
    ): IdealisticOfficeOutcome
    {
        if ($amount === 0.0) {
            return new IdealisticOfficeOutcome(
                false,
                "Cannot add zero credit to team."
            );
        }
        if ($amount > 0.0) {
            if ($restoreNegative) {
                $remaining = $this->getRemainingCredits();

                if ($remaining < 0.0) {
                    $amount += abs($remaining);
                }
            }
        } else if ($prompt !== null
            ? $prompt->getCache()->hasUnlimitedCredits()
            : $this->hasUnlimited()) {
            return new IdealisticOfficeOutcome(
                false,
                "Cannot subtract credit from a team with unlimited credits."
            );
        }
        $timezone = $this->team->getTimezone(false);

        if (IdealisticOfficeError::sqlInsert(
            $this->team,
            null,
            IdealisticOfficeTable::TEAM_CREDITS_TABLE,
            array(
                "team_id" => $this->team->getId(),
                "history_id" => $historyID,
                "credit_amount" => $amount,
                "creation_date" => IdealisticOfficeReader::getCurrentDate($timezone),
                "creation_time_zone" => $timezone->getTimeZone(),
            )
        )) {
            return new IdealisticOfficeOutcome(
                true,
                $amount . " credits added successfully to team.",
            );
        } else {
            return new IdealisticOfficeOutcome(
                false,
                "Failed to add credit to team."
            );
        }
    }

    public function subtract(?int $historyID, float $amount, ?IdealisticOfficePrompt $prompt = null): IdealisticOfficeOutcome
    {
        if ($amount > 0.0) {
            $amount = -$amount;
        }
        return $this->add($historyID, $amount, $prompt);
    }

    public function hasUnlimited(?IdealisticOfficeUser $user = null): bool
    {
        if ($user === null
            ? !$this->team->hasEstablishedAccess()
            : !$user->hasEstablishedAccess()) {
            return false;
        }
        if ($user === null) {
            $user = $this->team->getOwner(false);

            if (!($user instanceof IdealisticOfficeUser)) {
                $user = null;
            }
        }
        if ($user === null) {
            $query = get_sql_query(
                IdealisticOfficeTable::TEAM_UNLIMITED_CREDITS_TABLE,
                array("id"),
                array(
                    array("team_id", $this->team->getId()),
                    array("account_id", null)
                ),
                array(
                    "DESC",
                    "id"
                ),
                1
            );
        } else {
            $query = get_sql_query(
                IdealisticOfficeTable::TEAM_UNLIMITED_CREDITS_TABLE,
                array("id"),
                array(
                    array("team_id", $this->team->getId()),
                    null,
                    array("account_id", "IS", null, 0),
                    array("account_id", $user->getId()),
                    null
                ),
                array(
                    "DESC",
                    "id"
                ),
                1
            );
        }
        return !empty($query);
    }

    public function needsCharging(
        ?IdealisticOfficePrompt $prompt = null,
        ?IdealisticOfficeUser   $user = null,
        string                  $key = IdealisticPaddleApi::INCLUDED_CREDITS_KEY
    ): bool
    {
        if ($prompt !== null
            ? $prompt->getCache()->hasUnlimitedCredits()
            : $this->hasUnlimited($user)) {
            return false;
        }
        $included = $prompt !== null
            ? $prompt->getCache()->getIncludedMessages()
            : $this->getIncludedMessages($key);
        return $included > 0
            ? $this->getRemainingIncludedMessages($included, $key) <= 0
            : $this->getRemainingCredits() < IdealisticOfficeVariable::MINIMUM_CREDITS_TO_USE;
    }

    public function getCost(int $historyID = null): ?float
    {
        $query = get_sql_query(
            IdealisticOfficeTable::TEAM_CREDITS_TABLE,
            array("credit_amount"),
            array(
                array("history_id", $historyID)
            ),
            null,
            IdealisticOfficeLimit::MAX_PROCESSED_ROWS
        );

        if (empty($query)) {
            return null;
        } else {
            $cost = 0.0;

            foreach ($query as $row) {
                $cost += abs($row->credit_amount);
            }
            return $cost;
        }
    }

    public function getRemainingCredits(): float
    {
        if (!$this->team->hasEstablishedAccess()) {
            return 0.0;
        }
        $this->checkRefill();
        $query = get_sql_query(
            IdealisticOfficeTable::TEAM_CREDITS_TABLE,
            array("id"),
            array(
                array("team_id", $this->team->getId())
            ),
            array(
                "DESC",
                "id"
            ),
            1
        );

        if (empty($query)) {
            return 0.0;
        } else {
            $lastColumn = $query[0]->id;
            $query = get_sql_query(
                IdealisticOfficeTable::TEAM_CREDIT_CACHE_TABLE,
                array("up_to_column", "credit_amount"),
                array(
                    array("team_id", $this->team->getId())
                ),
                array(
                    "DESC",
                    "id"
                ),
                1
            );

            if (empty($query)) {
                $creditAmount = 0.0;
                $query = get_sql_query(
                    IdealisticOfficeTable::TEAM_CREDITS_TABLE,
                    array("credit_amount"),
                    array(
                        array("team_id", $this->team->getId())
                    ),
                    array(
                        "DESC",
                        "id"
                    )
                );

                if (!empty($query)) {
                    foreach ($query as $row) {
                        $creditAmount += $row->credit_amount;
                    }
                    IdealisticOfficeError::sqlInsert(
                        $this->team,
                        null,
                        IdealisticOfficeTable::TEAM_CREDIT_CACHE_TABLE,
                        array(
                            "team_id" => $this->team->getId(),
                            "up_to_column" => $lastColumn,
                            "credit_amount" => $creditAmount
                        )
                    );
                }
            } else {
                $upToColumn = $query[0]->up_to_column;
                $creditAmount = $query[0]->credit_amount;

                if ($lastColumn > $upToColumn) {
                    $query = get_sql_query(
                        IdealisticOfficeTable::TEAM_CREDITS_TABLE,
                        array("credit_amount"),
                        array(
                            array("team_id", $this->team->getId()),
                            array("id", ">", $upToColumn)
                        ),
                        array(
                            "DESC",
                            "id"
                        )
                    );

                    if (!empty($query)) {
                        foreach ($query as $row) {
                            $creditAmount += $row->credit_amount;
                        }
                        IdealisticOfficeError::sqlInsert(
                            $this->team,
                            null,
                            IdealisticOfficeTable::TEAM_CREDIT_CACHE_TABLE,
                            array(
                                "team_id" => $this->team->getId(),
                                "up_to_column" => $lastColumn,
                                "credit_amount" => $creditAmount
                            )
                        );
                    }
                }
            }
            return max($creditAmount, 0.0);
        }
    }

    private function checkRefill(): void
    {
        $query = get_sql_query(
            IdealisticOfficeTable::TEAM_CREDIT_REFILL_TABLE,
            array(
                "credit_amount",
                "refill_frequency",
                "last_refill_date",
                "last_refill_time_zone"
            ),
            array(
                array("team_id", $this->team->getId())
            ),
            array(
                "DESC",
                "id"
            ),
            1
        );

        if (empty($query)) {
            return;
        }
        $query = $query[0];

        if ($query->refill_frequency === null
            || $query->refill_frequency <= 0
            || $query->credit_amount === null
            || $query->credit_amount <= 0.0) {
            return;
        }
        $timezone = $query->last_refill_time_zone ?? IdealisticOfficeVariable::DEFAULT_TIME_ZONE;

        if ($query->last_refill_date !== null
            && IdealisticOfficeReader::getPastDate(
                $query->refill_frequency,
                $timezone
            ) <= $query->last_refill_date) {
            return;
        }
        $this->add(null, $query->credit_amount);
    }

    public function isSubscriber(?IdealisticOfficePrompt $prompt): bool
    {
        return $prompt !== null
            ? $prompt->getCache()->getIncludedMessages() > 0
            : $this->getIncludedMessages() > 0;
    }

    public function canChargeCredits(?IdealisticOfficePrompt $prompt = null): bool
    {
        return $prompt !== null
            ? $prompt->getCache()->canChargeCredits()
            : $this->hasUnlimited()
            || $this->getRemainingCredits() >= IdealisticOfficeVariable::MINIMUM_CREDITS_TO_USE;
    }

    public function getIncludedMessages(string $key = IdealisticPaddleApi::INCLUDED_CREDITS_KEY): int
    {
        if (!$this->team->hasEstablishedAccess()) {
            return 0;
        }
        $query = get_sql_query(
            IdealisticOfficeTable::TEAM_INCLUDED_MESSAGES_TABLE,
            array("message_amount"),
            array(
                array("deletion_date", null),
                null,
                array("team_id", "IS", null, 0),
                array("team_id", $this->team->getId()),
                null
            ),
            array(
                "DESC",
                "id"
            ),
            1
        );
        $owner = $this->team->getOwner(false);

        if (empty($query)) {
            if ($owner !== null
                && $owner->hasEmail()) {
                $owner = $owner->getEmail();

                if (!empty($owner)) {
                    return self::getIncludedMessagesFromEmail($owner, 0, $key);
                }
            }
            return 0;
        } else {
            $messages = $query[0]->message_amount ?? 0;

            if ($owner !== null
                && $owner->hasEmail()) {
                $owner = $owner->getEmail();

                if (!empty($owner)) {
                    $messages += self::getIncludedMessagesFromEmail($owner, 0, $key);
                }
            }
            return $messages;
        }
    }

    private function getOwnerEmail(): ?string
    {
        if (!$this->team->hasEstablishedAccess()) {
            return null;
        }
        $owner = $this->team->getOwner(false);

        if ($owner === null
            || !$owner->hasEmail()) {
            return null;
        }
        $email = $owner->getEmail();
        return empty($email) ? null : $email;
    }

    public function getContextLength(): ?int
    {
        $email = $this->getOwnerEmail();

        if ($email === null) {
            return null;
        }
        $context = self::getContextLengthFromEmail($email);
        return $context > 0 ? $context : null;
    }

    public function hasLogo(): bool
    {
        $credits = $this->getIncludedMessages();
        $fallback = $credits < IdealisticOfficePlans::LOGO_REMOVAL_MINIMUM_CREDITS;

        if ($credits <= 0
            || $credits > IdealisticOfficePlans::getMaximumPlanCredits()) {
            return $fallback;
        }
        $email = $this->getOwnerEmail();
        return $email === null
            ? $fallback
            : (self::getHasLogoFromEmail($email) ?? $fallback);
    }

    public function getHistoryBudget(): int
    {
        $credits = $this->getIncludedMessages();
        $fallback = IdealisticOfficePlans::getHistoryBudgetCharacters($credits);

        if ($credits <= 0
            || $credits > IdealisticOfficePlans::getMaximumPlanCredits()) {
            return $fallback;
        }
        return $this->getContextLength() ?? $fallback;
    }

    private function resetUsedIncludedMessages(): void
    {
        $oldest = get_sql_query(
            IdealisticOfficeTable::TEAM_INCLUDED_MESSAGES_TRACKING_TABLE,
            array(
                "creation_date",
                "creation_time_zone"
            ),
            array(
                array("team_id", $this->team->getId()),
                array("deletion_date", null)
            ),
            null,
            1
        );

        if (!empty($oldest)) {
            $secondsInAMonth = 30.4375 * 24 * 60 * 60;
            $date = IdealisticOfficeReader::getCurrentDate(
                $oldest[0]->creation_time_zone
            );

            if (strtotime($date) - strtotime($oldest[0]->creation_date) > $secondsInAMonth) {
                IdealisticOfficeError::setSqlQuery(
                    $this->team,
                    null,
                    IdealisticOfficeTable::TEAM_INCLUDED_MESSAGES_TRACKING_TABLE,
                    array(
                        "deletion_date" => $date,
                        "deletion_time_zone" => $oldest[0]->creation_time_zone
                    ),
                    array(
                        array("team_id", $this->team->getId()),
                        array("deletion_date", null)
                    )
                );
            }
        }
    }

    private function getUsedIncludedMessagesCount(int $limit = 0, ?float $specificAmount = null): float
    {
        if (!$this->team->hasEstablishedAccess()) {
            return 0;
        }
        $this->resetUsedIncludedMessages();
        $query = get_sql_query(
            IdealisticOfficeTable::TEAM_INCLUDED_MESSAGES_TRACKING_TABLE,
            array("message_amount"),
            array(
                array("team_id", $this->team->getId()),
                array("deletion_date", null),
                $specificAmount !== null
                    ? array("message_amount", $specificAmount)
                    : null
            ),
            null,
            $limit
        );

        if (empty($query)) {
            return 0;
        } else {
            $used = 0;

            foreach ($query as $row) {
                $used += $row->message_amount;
            }
            return $used;
        }
    }

    public function getRemainingIncludedMessages(
        ?int   $included = null,
        string $key = IdealisticPaddleApi::INCLUDED_CREDITS_KEY
    ): float
    {
        if ($included === null) {
            $included = $this->getIncludedMessages($key);
        }
        if ($included === 0) {
            return 0;
        } else {
            return $included - $this->getUsedIncludedMessagesCount($included);
        }
    }

    private function useIncludedMessageFromOtherTeams(
        IdealisticOfficeUser $user,
        float                $amount,
        bool                 $action
    ): IdealisticOfficeOutcome
    {
        $teams = $user->getIncludedCreditTeams(false);

        if (!empty($teams)) {
            foreach ($teams as $team) {
                if ($team->getId() === $this->team->getId()) {
                    continue;
                }
                $outcome = $team->getCredit()->useIncludedMessageIndependently(
                    $user,
                    $amount,
                    $action,
                    false
                );

                if ($outcome->isPositiveOutcome()) {
                    return $outcome;
                }
            }
            return new IdealisticOfficeOutcome(
                false,
                "No included messages available from other teams."
            );
        }
        return new IdealisticOfficeOutcome(
            false,
            "No other teams with included messages available."
        );
    }

    private function sendIncludedMessageEmail(): IdealisticOfficeOutcome
    {
        $owner = $this->team->getOwner(false);

        if ($owner === null) {
            return new IdealisticOfficeOutcome(
                false,
                "No team owner found to send included message email."
            );
        }
        if (!$owner->hasEmail()) {
            return new IdealisticOfficeOutcome(
                false,
                "Team owner does not have an email to send included message email."
            );
        }
        return IdealisticOfficeNotifications::sendEmail(
            $owner->getEmail(),
            ("You portals, your " . IdealisticOfficeVariable::APPLICATION_NAME . " app or included credit members have no remaining included messages left to use.")
            . " Please consider upgrading your plan to receive additional credits."
        );
    }

    public function useIncludedMessage(
        IdealisticOfficePrompt $prompt,
        float                  $amount,
        bool                   $action = true,
        bool                   $repetitive = true
    ): IdealisticOfficeOutcome
    {
        if (!$prompt->getUser()->hasEstablishedAccess()) {
            return new IdealisticOfficeOutcome(false, "No team/member access established.");
        }
        if ($this->canChargeCredits($prompt)) {
            return new IdealisticOfficeOutcome(
                true, // True because we don't want to block the prompt's operations
                "Cannot use included messages."
            );
        }
        if ($prompt->getCache()->subtractIncludedMessage($amount, $action)) {
            if (!$action) {
                return new IdealisticOfficeOutcome(
                    true,
                    "Included message/s can be used successfully."
                );
            }
            if (IdealisticOfficeError::sqlInsert(
                $this->team,
                $prompt->getUser(),
                IdealisticOfficeTable::TEAM_INCLUDED_MESSAGES_TRACKING_TABLE,
                array(
                    "team_id" => $this->team->getId(),
                    "history_id" => $prompt->getHistory()->getId(),
                    "account_id" => $prompt->getUser()->getId(),
                    "message_amount" => $amount,
                    "creation_date" => IdealisticOfficeReader::getCurrentDate(),
                    "creation_time_zone" => IdealisticOfficeVariable::DEFAULT_TIME_ZONE
                )
            )) {
                return new IdealisticOfficeOutcome(
                    true,
                    "Included message/s used successfully."
                );
            } else {
                return new IdealisticOfficeOutcome(
                    false,
                    "Failed to use included message."
                );
            }
        } else {
            if ($repetitive) {
                $other = $this->useIncludedMessageFromOtherTeams($prompt->getUser(), $amount, $action);

                if ($other->isPositiveOutcome()) {
                    return $other;
                }
            }
            $this->sendIncludedMessageEmail();
            return new IdealisticOfficeOutcome(
                false,
                "No remaining included messages left to use."
            );
        }
    }

    public function useIncludedMessageIndependently(
        IdealisticOfficeUser $user,
        float                $amount,
        bool                 $action = true,
        bool                 $repetitive = true,
        string               $key = IdealisticPaddleApi::INCLUDED_CREDITS_KEY
    ): IdealisticOfficeOutcome
    {
        if (!$this->team->hasEstablishedAccess()) {
            return new IdealisticOfficeOutcome(false, "No team access established.");
        }
        if ($this->canChargeCredits()) {
            return new IdealisticOfficeOutcome(
                true, // True because we don't want to block the future operations
                "Cannot use included messages."
            );
        }
        $included = $this->getIncludedMessages($key);

        if ($this->getRemainingIncludedMessages($included, $key) >= $amount) {
            if (!$action) {
                return new IdealisticOfficeOutcome(
                    true,
                    "Included message/s can be used successfully."
                );
            }
            if (IdealisticOfficeError::sqlInsert(
                $this->team,
                null,
                IdealisticOfficeTable::TEAM_INCLUDED_MESSAGES_TRACKING_TABLE,
                array(
                    "team_id" => $this->team->getId(),
                    "account_id" => $user->getId(),
                    "message_amount" => $amount,
                    "creation_date" => IdealisticOfficeReader::getCurrentDate(),
                    "creation_time_zone" => IdealisticOfficeVariable::DEFAULT_TIME_ZONE
                )
            )) {
                return new IdealisticOfficeOutcome(
                    true,
                    "Included message/s used successfully."
                );
            } else {
                return new IdealisticOfficeOutcome(
                    false,
                    "Failed to use included message."
                );
            }
        } else {
            if ($repetitive) {
                $other = $this->useIncludedMessageFromOtherTeams($user, $amount, $action);

                if ($other->isPositiveOutcome()) {
                    return $other;
                }
            }
            $this->sendIncludedMessageEmail();
            return new IdealisticOfficeOutcome(
                false,
                "No remaining included messages to use."
            );
        }
    }

}
