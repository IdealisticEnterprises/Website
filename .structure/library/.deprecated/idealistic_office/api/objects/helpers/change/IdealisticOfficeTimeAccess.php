<?php

namespace idealistic_office\api\objects\helpers\change;

use account\api\objects\account\Account;
use idealistic_office\api\objects\helpers\AbstractIdealisticOfficeTeam;
use idealistic_office\api\objects\helpers\IdealisticOfficeTimezone;

class IdealisticOfficeTimeAccess extends AbstractIdealisticOfficeInformationChange
{

    private int $startHour, $startMinutes, $endHour, $endMinutes;

    public function __construct(
        int|string                   $id,
        Account|int                  $creatorAccount,
        AbstractIdealisticOfficeTeam $team,
        ?string                      $creationReason,
        string                       $creationDate,
        int                          $startHour,
        int                          $startMinutes,
        int                          $endHour,
        int                          $endMinutes,
        string                       $timeZone,
        bool                         $json = true
    )
    {
        parent::__construct($id, $creatorAccount, $team, $creationReason, $creationDate, $timeZone, $json);
        $this->startHour = $startHour;
        $this->startMinutes = $startMinutes;
        $this->endHour = $endHour;
        $this->endMinutes = $endMinutes;

        if ($json) {
            self::addToJSON("start_hour", $startHour);
            self::addToJSON("start_minutes", $startMinutes);
            self::addToJSON("end_hour", $endHour);
            self::addToJSON("end_minutes", $endMinutes);
        }
    }

    public function getStartHour(): int
    {
        return str_pad((string)$this->startHour, 2, "0", STR_PAD_LEFT);
    }

    public function getStartMinutes(): int
    {
        return str_pad((string)$this->startMinutes, 2, "0", STR_PAD_LEFT);
    }

    public function getEndHour(): int
    {
        return str_pad((string)$this->endHour, 2, "0", STR_PAD_LEFT);
    }

    public function getEndMinutes(): int
    {
        return str_pad((string)$this->endMinutes, 2, "0", STR_PAD_LEFT);
    }

    public function hasAccess(IdealisticOfficeTimezone $timezone): bool
    {
        try {
            $timezone = new DateTimeZone($timezone->getTimeZone());
            $today = new DateTime("now", $timezone);
            $hour = $today->format("H");

            if ($hour < $this->startHour || $hour > $this->endHour) {
                return false;
            }
            $minutes = $today->format("i");

            if ($minutes < $this->startMinutes || $minutes > $this->endMinutes) {
                return false;
            }
        } catch (Throwable $ignored) {
        }
        return true;
    }

}
