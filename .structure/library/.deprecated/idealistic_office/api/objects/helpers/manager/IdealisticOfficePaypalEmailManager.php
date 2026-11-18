<?php

namespace idealistic_office\api\objects\helpers\manager;

use idealistic_office\api\handlers\IdealisticOfficeError;
use idealistic_office\api\handlers\IdealisticOfficeReader;
use idealistic_office\api\objects\helpers\change\IdealisticOfficePaypalEmail;
use idealistic_office\api\objects\helpers\IdealisticOfficeOutcome;
use idealistic_office\api\objects\IdealisticOfficeUser;
use IdealisticOfficeLimit;
use IdealisticOfficePermission;
use IdealisticOfficeTable;
use PhpAsyncSerializable;

class IdealisticOfficePaypalEmailManager implements PhpAsyncSerializable
{

    private IdealisticOfficeUser $user;

    public function __construct(IdealisticOfficeUser $user)
    {
        $this->user = $user;
    }

    public function addTeamEmail(string $information): IdealisticOfficeOutcome
    {
        if (!$this->user->hasEstablishedAccess()) {
            return new IdealisticOfficeOutcome(false, "No team/member access established.");
        }
        if (!$this->user->hasPermission(IdealisticOfficePermission::ADJUST_TEAM_PAYPAL_EMAILS)->isPositiveOutcome()) {
            return new IdealisticOfficeOutcome(false, "You do not have permission to set team PayPal email addresses.");
        }
        if (!is_email($information)) {
            return new IdealisticOfficeOutcome(false, "Invalid email address provided.");
        }
        if (strlen($information) > IdealisticOfficeLimit::EMAIL_LENGTH) {
            return new IdealisticOfficeOutcome(false, "Team PayPal email address exceeds character limit, must be " . IdealisticOfficeLimit::EMAIL_LENGTH . " characters or less.");
        }
        $information = strtolower($information);

        if (!empty(get_sql_query(
            IdealisticOfficeTable::PAYPAL_EMAILS_TABLE,
            array("id"),
            array(
                array("team_id", $this->user->getTeam()->getId()),
                array("email_address", $information),
                array("deletion_date", null),
            ),
            null,
            1
        ))) {
            return new IdealisticOfficeOutcome(false, "Team PayPal email address already exists.");
        }
        $timezone = $this->user->getTimezone(false);

        if (IdealisticOfficeError::sqlInsert(
            $this->user->getTeam(),
            $this->user,
            IdealisticOfficeTable::PAYPAL_EMAILS_TABLE,
            array(
                "team_id" => $this->user->getTeam()->getId(),
                "creation_date" => IdealisticOfficeReader::getCurrentDate($timezone),
                "created_by" => $this->user->getId(),
                "creation_time_zone" => $timezone->getTimeZone(),
                "email_address" => $information
            )
        )) {
            return new IdealisticOfficeOutcome(true, "Team PayPal email address added as '" . $information . "'.");
        } else {
            return new IdealisticOfficeOutcome(false, "Team PayPal email address could not be added, tried adding '" . $information . "'.");
        }
    }

    public function removeTeamEmail(int|IdealisticOfficePaypalEmail $id, ?string $justification = null): IdealisticOfficeOutcome
    {
        if (!$this->user->hasPermission(IdealisticOfficePermission::ADJUST_TEAM_PAYPAL_EMAILS)->isPositiveOutcome()) {
            return new IdealisticOfficeOutcome(false, "You do not have permission to remove team PayPal email addresses.");
        }
        if ($id instanceof IdealisticOfficePaypalEmail) {
            $data = $id;
        } else {
            $data = $this->user->getTeam()->getPaypalEmail($id, false);

            if ($data === null) {
                return new IdealisticOfficeOutcome(false, "No team PayPal email address found with ID '" . $id . "'.");
            }
        }
        if ($justification !== null
            && mb_strlen($justification) > IdealisticOfficeLimit::K15_LENGTH) {
            return new IdealisticOfficeOutcome(false, "Team PayPal email address justification exceeds character limit, must be " . IdealisticOfficeLimit::K15_LENGTH . " characters or less.");
        }
        if (!isset($justification[0])) {
            $justification = null;
        }
        $timezone = $this->user->getTimezone(false);

        if (IdealisticOfficeError::setSqlQuery(
            $this->user->getTeam(),
            $this->user,
            IdealisticOfficeTable::PAYPAL_EMAILS_TABLE,
            array(
                "deletion_date" => IdealisticOfficeReader::getCurrentDate($timezone),
                "deletion_reason" => $justification,
                "deleted_by" => $this->user->getId(),
                "deletion_time_zone" => $timezone->getTimeZone()
            ),
            array(
                array("id", $data->getId())
            ),
            null,
            1
        )) {
            return new IdealisticOfficeOutcome(true, "Team PayPal email addresses no longer contain '" . $data->getEmailAddress() . "'.");
        } else {
            return new IdealisticOfficeOutcome(false, "Team PayPal email address could not be removed, tried removing '" . $data->getEmailAddress() . "'.");
        }
    }

}
