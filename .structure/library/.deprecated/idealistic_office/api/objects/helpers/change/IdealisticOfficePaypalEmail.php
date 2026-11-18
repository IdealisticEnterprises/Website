<?php

namespace idealistic_office\api\objects\helpers\change;

use account\api\objects\account\Account;
use idealistic_office\api\objects\helpers\AbstractIdealisticOfficeTeam;

class IdealisticOfficePaypalEmail extends AbstractIdealisticOfficeChange
{

    private string $emailAddress;

    public function __construct(
        int|string                   $id,
        Account|int                  $creatorAccount,
        AbstractIdealisticOfficeTeam $team,
        string                       $emailAddress,
        string                       $creationDate,
        string                       $creationTimeZone,
        bool                         $json = true
    )
    {
        parent::__construct($id, $creatorAccount, $team, $creationDate, $creationTimeZone, $json);
        $this->emailAddress = $emailAddress;

        if ($json) {
            self::addToJSON("email_address", $emailAddress);
        }
    }

    public function getEmailAddress(): string
    {
        return $this->emailAddress;
    }

}
