<?php

class IdealisticOfficeAccess extends AbstractIdealisticOfficeInformationChange
{

    private ?int $accessLevel;

    public function __construct(
        int|string                   $id,
        Account|int                  $creatorAccount,
        AbstractIdealisticOfficeTeam $team,
        ?string                      $creationReason,
        string                       $creationDate,
        string                       $creationTimeZone,
        ?int                         $accessLevel,
        bool                         $json = true
    )
    {
        parent::__construct($id, $creatorAccount, $team, $creationReason, $creationDate, $creationTimeZone, $json);
        $this->accessLevel = $accessLevel;

        if ($json) {
            self::addToJSON("access_level", $accessLevel);
        }
    }

    public function getAccessLevel(): ?int
    {
        return $this->accessLevel;
    }

}
