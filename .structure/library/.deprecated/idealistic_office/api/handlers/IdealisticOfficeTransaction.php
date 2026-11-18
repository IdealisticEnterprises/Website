<?php

namespace idealistic_office\api\handlers;

use idealistic_office\api\objects\IdealisticOfficeTeam;
use IdealisticOfficeLimit;
use PhpAsyncSerializable;

class IdealisticOfficeTransaction implements PhpAsyncSerializable
{

    public static function getEmailsPerTeam(int $limit = 1000): array
    {
        $query = get_sql_query(
            IdealisticOfficeTable::PAYPAL_EMAILS_TABLE,
            array(
                "team_id",
                "email_address"
            ),
            array(
                array("deletion_date", null)
            ),
            array(
                "DESC",
                "id"
            ),
            $limit
        );

        if (empty($query)) {
            return array();
        } else {
            $emails = array();
            $globalEmails = array();

            foreach ($query as $row) {
                $team = new IdealisticOfficeTeam($row->team_id, false);

                if ($team->hasEstablishedAccess()
                    && !in_array($row->email_address, $globalEmails)) {
                    if (array_key_exists($team->getID(), $emails)) {
                        if (!in_array($row->email_address, $emails[$team->getID()])
                            && sizeof($emails[$team->getID()]) < IdealisticOfficeLimit::MAX_PAYPAL_EMAILS) {
                            $emails[$team->getID()][] = $row->email_address;
                            $globalEmails[] = $row->email_address;
                        }
                    } else {
                        $emails[$team->getID()] = array($row->email_address);
                        $globalEmails[] = $row->email_address;
                    }
                }
            }
            return $emails;
        }
    }

    public static function getTeamFromEmail(string $emailAddress, bool $json = true): ?IdealisticOfficeTeam
    {
        $query = get_sql_query(
            IdealisticOfficeTable::PAYPAL_EMAILS_TABLE,
            array(
                "team_id",
            ),
            array(
                array("deletion_date", null),
                array("email_address", $emailAddress)
            ),
            null,
            1
        );

        if (empty($query)) {
            return null;
        } else {
            $team = new IdealisticOfficeTeam($query[0]->team_id, $json);
            return $team->hasEstablishedAccess() ? $team : null;
        }
    }

}