<?php

class IdealisticOfficeFolderModifier extends AbstractIdealisticOfficeInformationChange
{

    private bool $open;

    public function __construct(
        int|string                   $id,
        Account|int                  $creatorAccount,
        AbstractIdealisticOfficeTeam $team,
        ?string                      $creationReason,
        string                       $creationDate,
        string                       $creationTimeZone,
        bool                         $open,
        bool                         $json = true
    )
    {
        parent::__construct($id, $creatorAccount, $team, $creationReason, $creationDate, $creationTimeZone, $json);
        $this->open = $open;

        if ($json) {
            self::addToJSON("open", $this->open);
        }
    }

    public function isOpen(): bool
    {
        return $this->open;
    }

}
