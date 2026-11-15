<?php

class IdealisticOfficeFolderParticipant extends IdealisticOfficeAbstractParticipant
{

    public function __construct(
        int|string                   $id,
        Account|int                  $creatorAccount,
        AbstractIdealisticOfficeTeam $team,
        ?string                      $creationReason,
        string                       $creationDate,
        string                       $creationTimeZone,
        Account|int                  $addedAccount,
        bool                         $json = true
    )
    {
        parent::__construct(
            $id,
            $creatorAccount,
            $team,
            $creationReason,
            $creationDate,
            $creationTimeZone,
            $addedAccount,
            $json
        );
    }

}
