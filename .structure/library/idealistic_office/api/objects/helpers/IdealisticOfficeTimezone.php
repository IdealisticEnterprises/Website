<?php

class IdealisticOfficeTimezone extends IdealisticOfficeJSON
{

    private string $timeZone;
    private AbstractIdealisticOfficeTeam $team;

    public function __construct(
        AbstractIdealisticOfficeTeam $team,
        string                       $timeZone,
        bool                         $json = true
    )
    {
        parent::__construct();
        $this->team = $team;
        $this->timeZone = $timeZone;

        if ($json) {
            self::setJSON(
                array(
                    "team_id" => $this->team->getId(),
                    "time_zone" => $this->timeZone
                )
            );
        }
    }

    public function getTimeZone(): string
    {
        return $this->timeZone;
    }

    public function getTeam(): AbstractIdealisticOfficeTeam
    {
        return $this->team;
    }

}
