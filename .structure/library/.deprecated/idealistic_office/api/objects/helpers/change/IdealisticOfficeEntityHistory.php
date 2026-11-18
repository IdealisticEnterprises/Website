<?php

namespace idealistic_office\api\objects\helpers\change;
use account\api\objects\account\Account;
use idealistic_office\api\objects\helpers\AbstractIdealisticOfficeTeam;

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
