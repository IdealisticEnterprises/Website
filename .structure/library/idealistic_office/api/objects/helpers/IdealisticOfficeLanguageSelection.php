<?php

class IdealisticOfficeLanguageSelection extends IdealisticOfficeJSON
{

    private string $language;
    private AbstractIdealisticOfficeTeam $team;

    public function __construct(
        AbstractIdealisticOfficeTeam $team,
        string                       $language,
        bool                         $json = true
    )
    {
        parent::__construct();
        $this->team = $team;
        $this->language = $language;

        if ($json) {
            self::setJSON(
                array(
                    "team_id" => $this->team->getId(),
                    "language" => $this->language
                )
            );
        }
    }

    public function getLanguage(): string
    {
        return $this->language;
    }

    public function getTeam(): AbstractIdealisticOfficeTeam
    {
        return $this->team;
    }

}
