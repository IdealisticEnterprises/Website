<?php

class IdealisticOfficeTeam extends AbstractIdealisticOfficeTeam
{

    public const PREFIX = Account::IDEALISTIC_OFFICE_APPLICATION_ID . "_";

    private ?Account $initiator;

    public function __construct(Account|string|int|AbstractIdealisticOfficeTeam $reference, bool $json = true)
    {
        parent::__construct(null);
        $account = new Account(Account::IDEALISTIC_OFFICE_APPLICATION_ID);
        $account->getTeam()->setTeamAdditionalID(AccountTeam::IDEALISTIC_OFFICE_ADDITIONAL_ID);
        $account->getTeam()->togglePermissions(false);

        if ($reference instanceof Account) {
            if ($reference->getDetail("application_id") == Account::IDEALISTIC_OFFICE_APPLICATION_ID) {
                $this->initiator = $reference;
                $team = $account->getTeam()->findTeam(self::PREFIX . $reference->getDetail("id"));
            } else {
                $this->initiator = null;
                $team = null;
            }
        } else if ($reference instanceof AbstractIdealisticOfficeTeam) {
            $this->initiator = null;
            $team = $account->getTeam()->findTeam($reference->getId());
        } else {
            $this->initiator = null;
            $team = $account->getTeam()->findTeam($reference);
        }

        if ($team?->isPositiveOutcome()) {
            $this->setConstruct($team->getObject(), $json);
        }
    }

    public function getInitiator(): ?Account
    {
        return $this->initiator ?? $this->getOwnerAccount();
    }

    public function getInitiatorUser(): ?IdealisticOfficeUser
    {
        $initiator = $this->getInitiator();

        if ($initiator !== null) {
            $user = $this->findUser($initiator);

            if ($user instanceof IdealisticOfficeUser) {
                return $user;
            }
        }
        return null;
    }

}
