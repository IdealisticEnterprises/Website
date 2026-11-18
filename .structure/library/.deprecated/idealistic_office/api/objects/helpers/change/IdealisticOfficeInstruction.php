<?php

namespace idealistic_office\api\objects\helpers\change;

use account\api\objects\account\Account;
use idealistic_office\api\objects\helpers\AbstractIdealisticOfficeTeam;

class IdealisticOfficeInstruction extends AbstractIdealisticOfficeChange
{

    private string $instruction;

    public function __construct(
        int|string                   $id,
        Account|int                  $creatorAccount,
        AbstractIdealisticOfficeTeam $team,
        string                       $instruction,
        string                       $creationDate,
        string                       $creationTimeZone,
        bool                         $json = true
    )
    {
        parent::__construct($id, $creatorAccount, $team, $creationDate, $creationTimeZone, $json);
        $this->instruction = $instruction;

        if ($json) {
            self::addToJSON("purpose", $instruction);
        }
    }

    public function getInstruction(): string
    {
        return $this->instruction;
    }

}
