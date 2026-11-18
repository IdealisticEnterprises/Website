<?php

namespace idealistic_office\api\objects\helpers\change;

use account\api\objects\account\Account;
use idealistic_office\api\objects\helpers\AbstractIdealisticOfficeTeam;

abstract class AbstractIdealisticOfficeInformationChange extends AbstractIdealisticOfficeChange
{

    protected ?string $creationReason;

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
        parent::__construct($id, $creatorAccount, $team, $creationDate, $creationTimeZone, $json);
        $this->creationReason = $creationReason;

        if ($json) {
            self::addToJSON("creation_reason", $creationReason);
        }
    }

    public final function getCreationReason(): ?string
    {
        return $this->creationReason;
    }

}
