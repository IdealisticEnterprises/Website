<?php

namespace idealistic_office\api\objects\helpers\change;

use account\api\objects\account\Account;
use idealistic_office\api\objects\helpers\AbstractIdealisticOfficeTeam;
use idealistic_office\api\objects\helpers\IdealisticOfficeJSON;
use idealistic_office\api\objects\IdealisticOfficeUser;

abstract class AbstractIdealisticOfficeChange extends IdealisticOfficeJSON
{

    protected int $id;
    protected AbstractIdealisticOfficeTeam $team;
    protected Account|int $creatorAccount;
    protected string $creationDate, $creationTimeZone;

    public function __construct(
        int|string                   $id,
        Account|int                  $creatorAccount,
        AbstractIdealisticOfficeTeam $team,
        string                       $creationDate,
        string                       $creationTimeZone,
        bool                         $json = true
    )
    {
        parent::__construct();
        $this->id = $id;
        $this->creatorAccount = $creatorAccount;
        $this->team = $team;
        $this->creationDate = $creationDate;
        $this->creationTimeZone = $creationTimeZone;

        if ($json) {
            self::setJSON(
                array(
                    "id" => $this->id,
                    "team_id" => $this->team->getId(),
                    "creator" => $this->getCreator(),
                    "creator_account" => $this->getCreatorAccount(),
                    "creation_date" => $this->creationDate,
                    "creation_time_zone" => $this->creationTimeZone,
                )
            );
        }
    }

    public final function getId(): int|string
    {
        return $this->id;
    }

    public final function getTeam(): AbstractIdealisticOfficeTeam
    {
        return $this->team;
    }

    public final function getCreator(bool $json = false): ?IdealisticOfficeUser
    {
        $user = $this->team->findUser($this->getCreatorAccount(), $json);
        return $user instanceof IdealisticOfficeUser ? $user : null;
    }

    public function isCreatorAdministrator(): bool
    {
        return $this->getCreator()?->getAccount()->getPermissions()->isAdministrator();
    }

    public final function getCreatorAccount(): Account
    {
        if (is_numeric($this->creatorAccount)) {
            $this->creatorAccount = new Account(
                Account::IDEALISTIC_OFFICE_APPLICATION_ID,
                $this->creatorAccount
            );
        }
        return $this->creatorAccount;
    }

    public final function getCreatorID(): int|string
    {
        if (is_numeric($this->creatorAccount)) {
            return $this->creatorAccount;
        }
        return $this->creatorAccount->getDetail("id", 0);
    }

    public final function getCreationDate(): string
    {
        return $this->creationDate;
    }

    public final function getCreationDateTimeZone(): string
    {
        return $this->creationTimeZone;
    }

}
