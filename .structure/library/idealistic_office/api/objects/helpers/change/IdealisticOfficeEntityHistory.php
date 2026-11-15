<?php

namespace change;
use AbstractIdealisticOfficeInformationChange;
use AbstractIdealisticOfficeTeam;
use Account;

class IdealisticOfficeEntityHistory extends AbstractIdealisticOfficeInformationChange
{

    public function __construct(
        int|string                   $id,
        Account|int                  $creatorAccount,
        AbstractIdealisticOfficeTeam $team,
        ?string                      $creationReason,
        string                       $creationDate,
        string                       $creationTimeZone,
        bool                         $json = true
    )
    {
        parent::__construct($id, $creatorAccount, $team, $creationReason, $creationDate, $creationTimeZone, $json);
    }

}
