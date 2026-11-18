<?php

namespace idealistic_office\api\objects\helpers\change\folder;

use account\api\objects\account\Account;
use idealistic_office\api\objects\helpers\AbstractIdealisticOfficeTeam;
use idealistic_office\api\objects\helpers\change\AbstractIdealisticOfficeInformationChange;

class IdealisticOfficeFolderProgress extends AbstractIdealisticOfficeInformationChange
{

    private float $percentage;

    public function __construct(
        int|string                   $id,
        Account|int                  $creatorAccount,
        AbstractIdealisticOfficeTeam $team,
        string                       $creationDate,
        ?string                      $creationReason,
        string                       $creationTimeZone,
        float                        $percentage,
        bool                         $json = true
    )
    {
        parent::__construct($id, $creatorAccount, $team, $creationReason, $creationDate, $creationTimeZone, $json);
        $this->percentage = $percentage;

        if ($json) {
            self::addToJSON("progress_percentage", $this->getPercentage());
        }
    }

    public function getPercentage(int $cutDecimal = 2): int|float
    {
        return $cutDecimal <= 0
            ? (int)$this->percentage
            : cut_decimal($this->percentage, $cutDecimal);
    }

}
