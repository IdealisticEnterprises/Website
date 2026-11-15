<?php

class IdealisticOfficeInstructionsManager implements PhpAsyncSerializable
{

    private IdealisticOfficeUser $user;

    public function __construct(IdealisticOfficeUser $user)
    {
        $this->user = $user;
    }

    public function addTeamInstruction(string $information): IdealisticOfficeOutcome
    {
        if (!$this->user->hasEstablishedAccess()) {
            return new IdealisticOfficeOutcome(false, "No team/member access established.");
        }
        if (!$this->user->hasPermission(IdealisticOfficePermission::ADJUST_TEAM_INSTRUCTIONS)->isPositiveOutcome()) {
            return new IdealisticOfficeOutcome(false, "You do not have permission to set team instructions.");
        }
        if (strlen($information) === 0) {
            return new IdealisticOfficeOutcome(false, "Team instruction cannot be empty.");
        }
        if (strlen($information) > IdealisticOfficeLimit::K8_LENGTH) {
            return new IdealisticOfficeOutcome(false, "Team instruction exceeds character limit, must be " . IdealisticOfficeLimit::K8_LENGTH . " characters or less.");
        }
        $timezone = $this->user->getTeam()->getTimezone(false);

        if (IdealisticOfficeError::sqlInsert(
            $this->user->getTeam(),
            $this->user,
            IdealisticOfficeTable::TEAM_INSTRUCTIONS_TABLE,
            array(
                "team_id" => $this->user->getTeam()->getId(),
                "creation_date" => IdealisticOfficeReader::getCurrentDate($timezone),
                "created_by" => $this->user->getId(),
                "creation_time_zone" => $timezone->getTimeZone(),
                "instruction" => $information
            )
        )) {
            return new IdealisticOfficeOutcome(true, "Team instruction added as '" . $information . "'.");
        } else {
            return new IdealisticOfficeOutcome(false, "Team instruction could not be added, tried adding '" . $information . "'.");
        }
    }

    public function removeTeamInstruction(int|IdealisticOfficeInstruction $id, ?string $justification = null): IdealisticOfficeOutcome
    {
        if (!$this->user->hasPermission(IdealisticOfficePermission::ADJUST_TEAM_INSTRUCTIONS)->isPositiveOutcome()) {
            return new IdealisticOfficeOutcome(false, "You do not have permission to remove team instructions.");
        }
        if ($id instanceof IdealisticOfficeInstruction) {
            $instruction = $id;
        } else {
            $instruction = $this->user->getTeam()->getInstruction($id, false);

            if ($instruction === null) {
                return new IdealisticOfficeOutcome(false, "No team instruction found with ID '" . $id . "'.");
            }
        }
        if ($justification !== null
            && mb_strlen($justification) > IdealisticOfficeLimit::K8_LENGTH) {
            return new IdealisticOfficeOutcome(false, "Team instruction justification exceeds character limit, must be " . IdealisticOfficeLimit::K8_LENGTH . " characters or less.");
        }
        if (!isset($justification[0])) {
            $justification = null;
        }
        $timezone = $this->user->getTimezone(false);

        if (IdealisticOfficeError::setSqlQuery(
            $this->user->getTeam(),
            $this->user,
            IdealisticOfficeTable::TEAM_INSTRUCTIONS_TABLE,
            array(
                "deletion_date" => IdealisticOfficeReader::getCurrentDate($timezone),
                "deletion_reason" => $justification,
                "deleted_by" => $this->user->getId(),
                "deletion_time_zone" => $timezone->getTimeZone()
            ),
            array(
                array("id", $instruction->getId())
            ),
            null,
            1
        )) {
            return new IdealisticOfficeOutcome(true, "Team instructions no longer contain '" . $instruction->getInstruction() . "'.");
        } else {
            return new IdealisticOfficeOutcome(false, "Team instruction could not be removed, tried removing '" . $instruction->getInstruction() . "'.");
        }
    }

}
