<?php

class IdealisticOfficeTimeAccessManager implements PhpAsyncSerializable
{

    private IdealisticOfficeUser $user;

    public function __construct(IdealisticOfficeUser $user)
    {
        $this->user = $user;
    }

    public function setUserTimeAccess(
        IdealisticOfficeUser $user,
        int                  $startHour,
        int                  $startMinutes,
        int                  $endHour,
        int                  $endMinutes,
        ?string              $justification = null
    ): IdealisticOfficeOutcome
    {
        if (!$this->user->hasEstablishedAccess()) {
            return new IdealisticOfficeOutcome(false, "No team/member access established.");
        }
        if (!$this->user->hasPermission(IdealisticOfficePermission::ADJUST_USER_TIME_ACCESS)->isPositiveOutcome()) {
            return new IdealisticOfficeOutcome(false, "You do not have permission to set member time access.");
        }
        if ($justification !== null
            && mb_strlen($justification) > IdealisticOfficeLimit::K16_LENGTH) {
            return new IdealisticOfficeOutcome(false, "Member time access justification exceeds character limit, must be " . IdealisticOfficeLimit::K16_LENGTH . " characters or less.");
        }
        if ($startHour < 0
            || $startHour > 23
            || $endHour < 0
            || $endHour > 23
            || $startHour > $endHour) {
            return new IdealisticOfficeOutcome(false, "The hour values are invalid");
        }
        if ($startMinutes < 0
            || $startMinutes > 59
            || $endMinutes < 0
            || $endMinutes > 59
            || $startHour === $endHour && $startMinutes >= $endMinutes) {
            return new IdealisticOfficeOutcome(false, "The minute values are invalid");
        }
        $currentTimeAccess = $user->getTimeAccess($this->user->getTeam(), false);

        if ($currentTimeAccess->getStartHour() == $startHour
            && $currentTimeAccess->getStartMinutes() == $startMinutes
            && $currentTimeAccess->getEndHour() == $endHour
            && $currentTimeAccess->getEndMinutes() == $endMinutes) {
            return new IdealisticOfficeOutcome(false, "Member time access is already set to this time frame.");
        }
        $timezone = $this->user->getTimezone(false);

        if (IdealisticOfficeError::sqlInsert(
            $this->user->getTeam(),
            $this->user,
            IdealisticOfficeTable::USER_TIME_ACCESS_TABLE,
            array(
                "team_id" => $this->user->getTeam()->getId(),
                "account_id" => $user->getId(),
                "creation_date" => IdealisticOfficeReader::getCurrentDate($timezone),
                "creation_reason" => $justification,
                "created_by" => $this->user->getId(),
                "creation_time_zone" => $timezone->getTimeZone(),
                "start_hour" => $startHour,
                "start_minutes" => $startMinutes,
                "end_hour" => $endHour,
                "end_minutes" => $endMinutes
            )
        )) {
            return new IdealisticOfficeOutcome(
                true,
                "Member time access set to '"
                . $startHour . ":" . $startMinutes . " - " . $endHour . ":" . $endMinutes . "'."
            );
        } else {
            return new IdealisticOfficeOutcome(
                false,
                "Member time access could not be set to '"
                . $startHour . ":" . $startMinutes . " - " . $endHour . ":" . $endMinutes . "'."
            );
        }
    }

    // Separator

    public function setRoleTimeAccess(
        IdealisticOfficeRole $role,
        int                  $startHour,
        int                  $startMinutes,
        int                  $endHour,
        int                  $endMinutes,
        ?string              $justification = null
    ): IdealisticOfficeOutcome
    {
        if (!$this->user->hasEstablishedAccess()) {
            return new IdealisticOfficeOutcome(false, "No team/member access established.");
        }
        if (!$this->user->hasPermission(IdealisticOfficePermission::ADJUST_ROLE_TIME_ACCESS)->isPositiveOutcome()) {
            return new IdealisticOfficeOutcome(false, "You do not have permission to set role time access.");
        }
        if ($justification !== null
            && mb_strlen($justification) > IdealisticOfficeLimit::K16_LENGTH) {
            return new IdealisticOfficeOutcome(false, "Role time access justification exceeds character limit, must be " . IdealisticOfficeLimit::K16_LENGTH . " characters or less.");
        }
        if ($startHour < 0
            || $startHour > 23
            || $endHour < 0
            || $endHour > 23
            || $startHour > $endHour) {
            return new IdealisticOfficeOutcome(false, "The hour values are invalid");
        }
        if ($startMinutes < 0
            || $startMinutes > 59
            || $endMinutes < 0
            || $endMinutes > 59
            || $startHour === $endHour && $startMinutes >= $endMinutes) {
            return new IdealisticOfficeOutcome(false, "The minute values are invalid");
        }
        $currentTimeAccess = $role->getTimeAccess(false);

        if ($currentTimeAccess->getStartHour() === $startHour
            && $currentTimeAccess->getStartMinutes() === $startMinutes
            && $currentTimeAccess->getEndHour() === $endHour
            && $currentTimeAccess->getEndMinutes() === $endMinutes) {
            return new IdealisticOfficeOutcome(false, "Role time access is already set to this time frame.");
        }
        $timezone = $this->user->getTimezone(false);

        if (IdealisticOfficeError::sqlInsert(
            $this->user->getTeam(),
            $this->user,
            IdealisticOfficeTable::ROLE_TIME_ACCESS_TABLE,
            array(
                "team_id" => $this->user->getTeam()->getId(),
                "role_id" => $role->getId(),
                "creation_date" => IdealisticOfficeReader::getCurrentDate($timezone),
                "creation_reason" => $justification,
                "creation_time_zone" => $timezone->getTimeZone(),
                "created_by" => $this->user->getId(),
                "start_hour" => $startHour,
                "start_minutes" => $startMinutes,
                "end_hour" => $endHour,
                "end_minutes" => $endMinutes
            )
        )) {
            return new IdealisticOfficeOutcome(
                true,
                "Role time access set to '"
                . $startHour . ":" . $startMinutes . " - " . $endHour . ":" . $endMinutes . "'."
            );
        } else {
            return new IdealisticOfficeOutcome(
                false,
                "Role time access could not be set to '"
                . $startHour . ":" . $startMinutes . " - " . $endHour . ":" . $endMinutes . "'."
            );
        }
    }

    // Separator

    public function setTeamTimeAccess(
        int     $startHour,
        int     $startMinutes,
        int     $endHour,
        int     $endMinutes,
        ?string $justification = null
    ): IdealisticOfficeOutcome
    {
        if (!$this->user->hasEstablishedAccess()) {
            return new IdealisticOfficeOutcome(false, "No team/member access established.");
        }
        if (!$this->user->hasPermission(IdealisticOfficePermission::ADJUST_TEAM_TIME_ACCESS)->isPositiveOutcome()) {
            return new IdealisticOfficeOutcome(false, "You do not have permission to set team time access.");
        }
        if ($justification !== null
            && mb_strlen($justification) > IdealisticOfficeLimit::K16_LENGTH) {
            return new IdealisticOfficeOutcome(false, "Team time access justification exceeds character limit, must be " . IdealisticOfficeLimit::K16_LENGTH . " characters or less.");
        }
        if ($startHour < 0
            || $startHour > 23
            || $endHour < 0
            || $endHour > 23
            || $startHour > $endHour) {
            return new IdealisticOfficeOutcome(false, "The hour values are invalid");
        }
        if ($startMinutes < 0
            || $startMinutes > 59
            || $endMinutes < 0
            || $endMinutes > 59
            || $startHour === $endHour && $startMinutes >= $endMinutes) {
            return new IdealisticOfficeOutcome(false, "The minute values are invalid");
        }
        $currentTimeAccess = $this->user->getTeam()->getTimeAccess(false);

        if ($currentTimeAccess->getStartHour() === $startHour
            && $currentTimeAccess->getStartMinutes() === $startMinutes
            && $currentTimeAccess->getEndHour() === $endHour
            && $currentTimeAccess->getEndMinutes() === $endMinutes) {
            return new IdealisticOfficeOutcome(false, "Team time access is already set to this time frame.");
        }
        $timezone = $this->user->getTimezone(false);

        if (IdealisticOfficeError::sqlInsert(
            $this->user->getTeam(),
            $this->user,
            IdealisticOfficeTable::TEAM_TIME_ACCESS_TABLE,
            array(
                "team_id" => $this->user->getTeam()->getId(),
                "creation_date" => IdealisticOfficeReader::getCurrentDate($timezone),
                "creation_reason" => $justification,
                "created_by" => $this->user->getId(),
                "creation_time_zone" => $timezone->getTimeZone(),
                "start_hour" => $startHour,
                "start_minutes" => $startMinutes,
                "end_hour" => $endHour,
                "end_minutes" => $endMinutes
            )
        )) {
            return new IdealisticOfficeOutcome(
                true,
                "Team time access set to '"
                . $startHour . ":" . $startMinutes . " - " . $endHour . ":" . $endMinutes . "'."
            );
        } else {
            return new IdealisticOfficeOutcome(
                false,
                "Team time access could not be set to '"
                . $startHour . ":" . $startMinutes . " - " . $endHour . ":" . $endMinutes . "'."
            );
        }
    }

}
