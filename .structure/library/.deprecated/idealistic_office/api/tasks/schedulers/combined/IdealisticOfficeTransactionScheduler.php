<?php

namespace idealistic_office\api\tasks\schedulers\combined;

use idealistic_office\api\handlers\IdealisticOfficeError;
use idealistic_office\api\handlers\IdealisticOfficeReader;
use idealistic_office\api\handlers\IdealisticOfficeTransaction;
use idealistic_office\api\objects\helpers\IdealisticOfficeCredit;
use IdealisticOfficeTable;
use PhpAsyncSerializable;

class IdealisticOfficeTransactionScheduler implements PhpAsyncSerializable // Deprecated for now
{

    private const NAME = "Idealistic for Office";

    public static function run(): bool
    {
        try {
            if (!IdealisticOfficeCredit::ALLOW_PAYPAL) {
                return false;
            }
            $equivalence = IdealisticOfficeCredit::getEquivalence();

            if (empty($equivalence)) {
                return true;
            }
            $since = get_past_date("7 days");
            $transactions = get_all_paypal_transactions(
                0,
                $since
            );

            if (!empty($transactions)) {
                $failedTransactions = get_failed_paypal_transactions(
                    0,
                    $since
                );

                foreach ($transactions as $transactionID => $transaction) {
                    if (!in_array($transactionID, $failedTransactions)
                        && isset($transaction->EMAIL)
                        && isset($transaction->AMT)
                        && isset($transaction->L_NAME0)
                        && is_email($transaction->EMAIL)
                        && is_numeric($transaction->AMT)
                        && str_starts_with($transaction->L_NAME0, self::NAME)) {
                        $credits = $equivalence[(int)round($transaction->AMT)] ?? null;

                        if ($credits !== null) {
                            if (!empty(get_sql_query(
                                IdealisticOfficeTable::PAYPAL_PROCESSED_TRANSACTIONS_TABLE,
                                array("id"),
                                array(
                                    array("transaction_id", $transactionID),
                                ),
                                null,
                                1
                            ))) {
                                continue;
                            }
                            $team = IdealisticOfficeTransaction::getTeamFromEmail($transaction->EMAIL, false);

                            if ($team !== null) {
                                $timezone = $team->getTimezone(false);

                                if (IdealisticOfficeError::sqlInsert(
                                    $team,
                                    null,
                                    IdealisticOfficeTable::PAYPAL_PROCESSED_TRANSACTIONS_TABLE,
                                    array(
                                        "transaction_id" => $transactionID,
                                        "team_id" => $team->getID(),
                                        "creation_date" => IdealisticOfficeReader::getCurrentDate($timezone),
                                        "creation_time_zone" => $timezone->getTimeZone()
                                    )
                                )) {
                                    $outcome = $team->getCredit()->add(
                                        null,
                                        $credits
                                    );

                                    if ($outcome->isPositiveOutcome()) {
                                        $team->addNotification(
                                            null,
                                            $outcome->getRawMessage()
                                        );
                                    } else {
                                        IdealisticOfficeError::storeString(
                                            $team,
                                            null,
                                            $outcome->getRawMessage()
                                        );
                                    }
                                }
                            }
                        }
                    }
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
