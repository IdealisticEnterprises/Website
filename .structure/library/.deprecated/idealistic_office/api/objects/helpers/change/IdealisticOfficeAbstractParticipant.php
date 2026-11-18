<?php

namespace idealistic_office\api\objects\helpers\change;

use account\api\objects\account\Account;
use idealistic_office\api\objects\helpers\AbstractIdealisticOfficeTeam;
use idealistic_office\api\objects\IdealisticOfficeUser;

abstract class IdealisticOfficeAbstractParticipant extends AbstractIdealisticOfficeInformationChange
{

    private Account|int $addedAccount;

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
        parent::__construct($id, $creatorAccount, $team, $creationReason, $creationDate, $creationTimeZone, $json);
        $this->addedAccount = $addedAccount;

        if ($json) {
            self::addToJSON("added_account", $this->getAddedAccount());
            self::addToJSON("added_account_user", $this->getAddedAccountUser());
        }
    }

    public final function getAddedAccountUser(): ?IdealisticOfficeUser
    {
        $user = $this->team->findUser($this->getAddedAccount());
        return $user instanceof IdealisticOfficeUser ? $user : null;
    }

    public final function getAddedAccount(): Account
    {
        if (is_numeric($this->addedAccount)) {
            $this->addedAccount = new Account(
                Account::IDEALISTIC_OFFICE_APPLICATION_ID,
                $this->addedAccount
            );
        }
        return $this->addedAccount;
    }

    public final function getAddedID(): int|string|null
    {
        if (is_numeric($this->addedAccount)) {
            return $this->addedAccount;
        }
        return $this->addedAccount->getDetail("id", 0);
    }

}
