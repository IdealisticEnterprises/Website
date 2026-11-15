<?php

class IdealisticOfficeWeeklyAccessManager implements PhpAsyncSerializable
{

    private IdealisticOfficeUser $user;

    public function __construct(IdealisticOfficeUser $user)
    {
        $this->user = $user;
    }

    public function setUserWeekAccess(
        IdealisticOfficeUser $user,
        ?bool                $mondayAccess = null,
        ?bool                $tuesdayAccess = null,
        ?bool                $wednesdayAccess = null,
        ?bool                $thursdayAccess = null,
        ?bool                $fridayAccess = null,
        ?bool                $saturdayAccess = null,
        ?bool                $sundayAccess = null,
        ?string              $justification = null
    ): IdealisticOfficeOutcome
    {
        if (!$this->user->hasEstablishedAccess()) {
            return new IdealisticOfficeOutcome(false, "No team/member access established.");
        }
        if (!$this->user->hasPermission(IdealisticOfficePermission::ADJUST_USER_WEEK_ACCESS)->isPositiveOutcome()) {
            return new IdealisticOfficeOutcome(false, "You do not have permission to set member weekly access.");
        }
        if ($justification !== null
            && mb_strlen($justification) > IdealisticOfficeLimit::K16_LENGTH) {
            return new IdealisticOfficeOutcome(false, "Member weekly access justification exceeds character limit, must be " . IdealisticOfficeLimit::K16_LENGTH . " characters or less.");
        }
        if ($mondayAccess === null
            && $tuesdayAccess === null
            && $wednesdayAccess === null
            && $thursdayAccess === null
            && $fridayAccess === null
            && $saturdayAccess === null
            && $sundayAccess === null) {
            return new IdealisticOfficeOutcome(false, "No day's access has been provided.");
        }
        $currentWeekAccess = $user->getWeekAccess($this->user->getTeam(), false);

        if ($mondayAccess === null) {
            $mondayAccess = $currentWeekAccess->getMondayAccess();
        }
        if ($tuesdayAccess === null) {
            $tuesdayAccess = $currentWeekAccess->getTuesdayAccess();
        }
        if ($wednesdayAccess === null) {
            $wednesdayAccess = $currentWeekAccess->getWednesdayAccess();
        }
        if ($thursdayAccess === null) {
            $thursdayAccess = $currentWeekAccess->getThursdayAccess();
        }
        if ($fridayAccess === null) {
            $fridayAccess = $currentWeekAccess->getFridayAccess();
        }
        if ($saturdayAccess === null) {
            $saturdayAccess = $currentWeekAccess->getSaturdayAccess();
        }
        if ($sundayAccess === null) {
            $sundayAccess = $currentWeekAccess->getSundayAccess();
        }
        if ($currentWeekAccess->getMondayAccess() === $mondayAccess
            && $currentWeekAccess->getTuesdayAccess() === $tuesdayAccess
            && $currentWeekAccess->getWednesdayAccess() === $wednesdayAccess
            && $currentWeekAccess->getThursdayAccess() === $thursdayAccess
            && $currentWeekAccess->getFridayAccess() === $fridayAccess
            && $currentWeekAccess->getSaturdayAccess() === $saturdayAccess
            && $currentWeekAccess->getSundayAccess() === $sundayAccess) {
            return new IdealisticOfficeOutcome(false, "Member weekly access is already set to this time frame.");
        }
        $timezone = $this->user->getTimezone(false);

        if (IdealisticOfficeError::sqlInsert(
            $this->user->getTeam(),
            $this->user,
            IdealisticOfficeTable::USER_WEEK_ACCESS_TABLE,
            array(
                "team_id" => $this->user->getTeam()->getId(),
                "account_id" => $user->getId(),
                "creation_date" => IdealisticOfficeReader::getCurrentDate($timezone),
                "creation_reason" => $justification,
                "created_by" => $this->user->getId(),
                "creation_time_zone" => $timezone->getTimeZone(),
                "monday_access" => $mondayAccess,
                "tuesday_access" => $tuesdayAccess,
                "wednesday_access" => $wednesdayAccess,
                "thursday_access" => $thursdayAccess,
                "friday_access" => $fridayAccess,
                "saturday_access" => $saturdayAccess,
                "sunday_access" => $sundayAccess
            )
        )) {
            return new IdealisticOfficeOutcome(
                true,
                "Member weekly access set to '"
                . trim(
                    ($mondayAccess ? "Monday, " : "")
                    . ($tuesdayAccess ? "Tuesday, " : "")
                    . ($wednesdayAccess ? "Wednesday, " : "")
                    . ($thursdayAccess ? "Thursday, " : "")
                    . ($fridayAccess ? "Friday, " : "")
                    . ($saturdayAccess ? "Saturday, " : "")
                    . ($sundayAccess ? "Sunday, " : ""),
                    ", "
                )
                . "'"
            );
        } else {
            return new IdealisticOfficeOutcome(
                false,
                "Member weekly access could not be set to '"
                . trim(
                    ($mondayAccess ? "Monday, " : "")
                    . ($tuesdayAccess ? "Tuesday, " : "")
                    . ($wednesdayAccess ? "Wednesday, " : "")
                    . ($thursdayAccess ? "Thursday, " : "")
                    . ($fridayAccess ? "Friday, " : "")
                    . ($saturdayAccess ? "Saturday, " : "")
                    . ($sundayAccess ? "Sunday, " : ""),
                    ", "
                )
                . "'"
            );
        }
    }

    // Separator

    public function setRoleWeekAccess(
        IdealisticOfficeRole $role,
        ?bool                $mondayAccess = null,
        ?bool                $tuesdayAccess = null,
        ?bool                $wednesdayAccess = null,
        ?bool                $thursdayAccess = null,
        ?bool                $fridayAccess = null,
        ?bool                $saturdayAccess = null,
        ?bool                $sundayAccess = null,
        ?string              $justification = null
    ): IdealisticOfficeOutcome
    {
        if (!$this->user->hasEstablishedAccess()) {
            return new IdealisticOfficeOutcome(false, "No team/member access established.");
        }
        if (!$this->user->hasPermission(IdealisticOfficePermission::ADJUST_ROLE_WEEK_ACCESS)->isPositiveOutcome()) {
            return new IdealisticOfficeOutcome(false, "You do not have permission to set role weekly access.");
        }
        if ($justification !== null
            && mb_strlen($justification) > IdealisticOfficeLimit::K16_LENGTH) {
            return new IdealisticOfficeOutcome(false, "Role weekly access justification exceeds character limit, must be " . IdealisticOfficeLimit::K16_LENGTH . " characters or less.");
        }
        if ($mondayAccess === null
            && $tuesdayAccess === null
            && $wednesdayAccess === null
            && $thursdayAccess === null
            && $fridayAccess === null
            && $saturdayAccess === null
            && $sundayAccess === null) {
            return new IdealisticOfficeOutcome(false, "No day's access has been provided.");
        }
        $currentWeekAccess = $role->getWeekAccess(false);

        if ($mondayAccess === null) {
            $mondayAccess = $currentWeekAccess->getMondayAccess();
        }
        if ($tuesdayAccess === null) {
            $tuesdayAccess = $currentWeekAccess->getTuesdayAccess();
        }
        if ($wednesdayAccess === null) {
            $wednesdayAccess = $currentWeekAccess->getWednesdayAccess();
        }
        if ($thursdayAccess === null) {
            $thursdayAccess = $currentWeekAccess->getThursdayAccess();
        }
        if ($fridayAccess === null) {
            $fridayAccess = $currentWeekAccess->getFridayAccess();
        }
        if ($saturdayAccess === null) {
            $saturdayAccess = $currentWeekAccess->getSaturdayAccess();
        }
        if ($sundayAccess === null) {
            $sundayAccess = $currentWeekAccess->getSundayAccess();
        }
        if ($currentWeekAccess->getMondayAccess() === $mondayAccess
            && $currentWeekAccess->getTuesdayAccess() === $tuesdayAccess
            && $currentWeekAccess->getWednesdayAccess() === $wednesdayAccess
            && $currentWeekAccess->getThursdayAccess() === $thursdayAccess
            && $currentWeekAccess->getFridayAccess() === $fridayAccess
            && $currentWeekAccess->getSaturdayAccess() === $saturdayAccess
            && $currentWeekAccess->getSundayAccess() === $sundayAccess) {
            return new IdealisticOfficeOutcome(false, "Member weekly access is already set to this time frame.");
        }
        $timezone = $this->user->getTimezone(false);

        if (IdealisticOfficeError::sqlInsert(
            $this->user->getTeam(),
            $this->user,
            IdealisticOfficeTable::ROLE_WEEK_ACCESS_TABLE,
            array(
                "team_id" => $this->user->getTeam()->getId(),
                "role_id" => $role->getId(),
                "creation_date" => IdealisticOfficeReader::getCurrentDate($timezone),
                "creation_reason" => $justification,
                "creation_time_zone" => $timezone->getTimeZone(),
                "created_by" => $this->user->getId(),
                "monday_access" => $mondayAccess,
                "tuesday_access" => $tuesdayAccess,
                "wednesday_access" => $wednesdayAccess,
                "thursday_access" => $thursdayAccess,
                "friday_access" => $fridayAccess,
                "saturday_access" => $saturdayAccess,
                "sunday_access" => $sundayAccess
            )
        )) {
            return new IdealisticOfficeOutcome(
                true,
                "Role weekly access set to '"
                . trim(
                    ($mondayAccess ? "Monday, " : "")
                    . ($tuesdayAccess ? "Tuesday, " : "")
                    . ($wednesdayAccess ? "Wednesday, " : "")
                    . ($thursdayAccess ? "Thursday, " : "")
                    . ($fridayAccess ? "Friday, " : "")
                    . ($saturdayAccess ? "Saturday, " : "")
                    . ($sundayAccess ? "Sunday, " : ""),
                    ", "
                )
                . "'"
            );
        } else {
            return new IdealisticOfficeOutcome(
                false,
                "Role weekly access could not be set to '"
                . trim(
                    ($mondayAccess ? "Monday, " : "")
                    . ($tuesdayAccess ? "Tuesday, " : "")
                    . ($wednesdayAccess ? "Wednesday, " : "")
                    . ($thursdayAccess ? "Thursday, " : "")
                    . ($fridayAccess ? "Friday, " : "")
                    . ($saturdayAccess ? "Saturday, " : "")
                    . ($sundayAccess ? "Sunday, " : ""),
                    ", "
                )
                . "'"
            );
        }
    }

    // Separator

    public function setTeamWeekAccess(
        ?bool   $mondayAccess = null,
        ?bool   $tuesdayAccess = null,
        ?bool   $wednesdayAccess = null,
        ?bool   $thursdayAccess = null,
        ?bool   $fridayAccess = null,
        ?bool   $saturdayAccess = null,
        ?bool   $sundayAccess = null,
        ?string $justification = null
    ): IdealisticOfficeOutcome
    {
        if (!$this->user->hasEstablishedAccess()) {
            return new IdealisticOfficeOutcome(false, "No team/member access established.");
        }
        if (!$this->user->hasPermission(IdealisticOfficePermission::ADJUST_TEAM_WEEK_ACCESS)->isPositiveOutcome()) {
            return new IdealisticOfficeOutcome(false, "You do not have permission to set team weekly access.");
        }
        if ($justification !== null
            && mb_strlen($justification) > IdealisticOfficeLimit::K16_LENGTH) {
            return new IdealisticOfficeOutcome(false, "Team weekly access justification exceeds character limit, must be " . IdealisticOfficeLimit::K16_LENGTH . " characters or less.");
        }
        if ($mondayAccess === null
            && $tuesdayAccess === null
            && $wednesdayAccess === null
            && $thursdayAccess === null
            && $fridayAccess === null
            && $saturdayAccess === null
            && $sundayAccess === null) {
            return new IdealisticOfficeOutcome(false, "No day's access has been provided.");
        }
        $currentWeekAccess = $this->user->getTeam()->getWeekAccess(false);

        if ($mondayAccess === null) {
            $mondayAccess = $currentWeekAccess->getMondayAccess();
        }
        if ($tuesdayAccess === null) {
            $tuesdayAccess = $currentWeekAccess->getTuesdayAccess();
        }
        if ($wednesdayAccess === null) {
            $wednesdayAccess = $currentWeekAccess->getWednesdayAccess();
        }
        if ($thursdayAccess === null) {
            $thursdayAccess = $currentWeekAccess->getThursdayAccess();
        }
        if ($fridayAccess === null) {
            $fridayAccess = $currentWeekAccess->getFridayAccess();
        }
        if ($saturdayAccess === null) {
            $saturdayAccess = $currentWeekAccess->getSaturdayAccess();
        }
        if ($sundayAccess === null) {
            $sundayAccess = $currentWeekAccess->getSundayAccess();
        }
        if ($currentWeekAccess->getMondayAccess() === $mondayAccess
            && $currentWeekAccess->getTuesdayAccess() === $tuesdayAccess
            && $currentWeekAccess->getWednesdayAccess() === $wednesdayAccess
            && $currentWeekAccess->getThursdayAccess() === $thursdayAccess
            && $currentWeekAccess->getFridayAccess() === $fridayAccess
            && $currentWeekAccess->getSaturdayAccess() === $saturdayAccess
            && $currentWeekAccess->getSundayAccess() === $sundayAccess) {
            return new IdealisticOfficeOutcome(false, "Member weekly access is already set to this time frame.");
        }
        $timezone = $this->user->getTimezone(false);

        if (IdealisticOfficeError::sqlInsert(
            $this->user->getTeam(),
            $this->user,
            IdealisticOfficeTable::TEAM_WEEKLY_ACCESS_TABLE,
            array(
                "team_id" => $this->user->getTeam()->getId(),
                "creation_date" => IdealisticOfficeReader::getCurrentDate($timezone),
                "creation_reason" => $justification,
                "created_by" => $this->user->getId(),
                "creation_time_zone" => $timezone->getTimeZone(),
                "monday_access" => $mondayAccess,
                "tuesday_access" => $tuesdayAccess,
                "wednesday_access" => $wednesdayAccess,
                "thursday_access" => $thursdayAccess,
                "friday_access" => $fridayAccess,
                "saturday_access" => $saturdayAccess,
                "sunday_access" => $sundayAccess
            )
        )) {
            return new IdealisticOfficeOutcome(
                true,
                "Team weekly access set to '"
                . trim(
                    ($mondayAccess ? "Monday, " : "")
                    . ($tuesdayAccess ? "Tuesday, " : "")
                    . ($wednesdayAccess ? "Wednesday, " : "")
                    . ($thursdayAccess ? "Thursday, " : "")
                    . ($fridayAccess ? "Friday, " : "")
                    . ($saturdayAccess ? "Saturday, " : "")
                    . ($sundayAccess ? "Sunday, " : ""),
                    ", "
                )
                . "'"
            );
        } else {
            return new IdealisticOfficeOutcome(
                false,
                "Team weekly access could not be set to '"
                . trim(
                    ($mondayAccess ? "Monday, " : "")
                    . ($tuesdayAccess ? "Tuesday, " : "")
                    . ($wednesdayAccess ? "Wednesday, " : "")
                    . ($thursdayAccess ? "Thursday, " : "")
                    . ($fridayAccess ? "Friday, " : "")
                    . ($saturdayAccess ? "Saturday, " : "")
                    . ($sundayAccess ? "Sunday, " : ""),
                    ", "
                )
                . "'"
            );
        }
    }

}
