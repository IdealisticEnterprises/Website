<?php

class IdealisticOfficeWeeklyAccess extends AbstractIdealisticOfficeInformationChange
{

    private bool
        $mondayAccess,
        $tuesdayAccess,
        $wednesdayAccess,
        $thursdayAccess,
        $fridayAccess,
        $saturdayAccess,
        $sundayAccess;

    public function __construct(
        int|string                   $id,
        Account|int                  $creatorAccount,
        AbstractIdealisticOfficeTeam $team,
        ?string                      $creationReason,
        string                       $creationDate,
        string                       $timeZone,
        bool                         $mondayAccess = false,
        bool                         $tuesdayAccess = false,
        bool                         $wednesdayAccess = false,
        bool                         $thursdayAccess = false,
        bool                         $fridayAccess = false,
        bool                         $saturdayAccess = false,
        bool                         $sundayAccess = false,
        bool                         $json = true
    )
    {
        parent::__construct($id, $creatorAccount, $team, $creationReason, $creationDate, $timeZone, $json);
        $this->mondayAccess = $mondayAccess;
        $this->tuesdayAccess = $tuesdayAccess;
        $this->wednesdayAccess = $wednesdayAccess;
        $this->thursdayAccess = $thursdayAccess;
        $this->fridayAccess = $fridayAccess;
        $this->saturdayAccess = $saturdayAccess;
        $this->sundayAccess = $sundayAccess;

        if ($json) {
            self::addToJSON("monday_access", $mondayAccess);
            self::addToJSON("tuesday_access", $tuesdayAccess);
            self::addToJSON("wednesday_access", $wednesdayAccess);
            self::addToJSON("thursday_access", $thursdayAccess);
            self::addToJSON("friday_access", $fridayAccess);
            self::addToJSON("saturday_access", $saturdayAccess);
            self::addToJSON("sunday_access", $sundayAccess);
        }
    }

    public function getMondayAccess(): bool
    {
        return $this->mondayAccess;
    }

    public function getTuesdayAccess(): bool
    {
        return $this->tuesdayAccess;
    }

    public function getWednesdayAccess(): bool
    {
        return $this->wednesdayAccess;
    }

    public function getThursdayAccess(): bool
    {
        return $this->thursdayAccess;
    }

    public function getFridayAccess(): bool
    {
        return $this->fridayAccess;
    }

    public function getSaturdayAccess(): bool
    {
        return $this->saturdayAccess;
    }

    public function getSundayAccess(): bool
    {
        return $this->sundayAccess;
    }

    public function hasAccess(IdealisticOfficeTimezone $timezone): bool
    {
        try {
            $timezone = new DateTimeZone($timezone->getTimeZone());
            $today = new DateTime("now", $timezone);

            switch ($today->format("N")) {
                case 1:
                    return $this->mondayAccess;
                case 2:
                    return $this->tuesdayAccess;
                case 3:
                    return $this->wednesdayAccess;
                case 4:
                    return $this->thursdayAccess;
                case 5:
                    return $this->fridayAccess;
                case 6:
                    return $this->saturdayAccess;
                case 7:
                    return $this->sundayAccess;
            }
        } catch (Throwable $ignored) {
        }
        return true;
    }

    public function getDaysString(): string
    {
        $days = array();

        if ($this->mondayAccess) {
            $days[] = "Monday";
        }
        if ($this->tuesdayAccess) {
            $days[] = "Tuesday";
        }
        if ($this->wednesdayAccess) {
            $days[] = "Wednesday";
        }
        if ($this->thursdayAccess) {
            $days[] = "Thursday";
        }
        if ($this->fridayAccess) {
            $days[] = "Friday";
        }
        if ($this->saturdayAccess) {
            $days[] = "Saturday";
        }
        if ($this->sundayAccess) {
            $days[] = "Sunday";
        }
        return implode(IdealisticOfficeVariable::DEFAULT_TEXT_SEPARATOR, $days);
    }

}
