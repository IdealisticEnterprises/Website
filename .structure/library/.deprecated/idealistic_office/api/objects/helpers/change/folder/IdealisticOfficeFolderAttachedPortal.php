<?php

namespace idealistic_office\api\objects\helpers\change\folder;

use account\api\objects\account\Account;
use idealistic_office\api\objects\helpers\AbstractIdealisticOfficeTeam;
use idealistic_office\api\objects\helpers\change\AbstractIdealisticOfficeInformationChange;
use idealistic_office\api\objects\IdealisticOfficePortal;

class IdealisticOfficeFolderAttachedPortal extends AbstractIdealisticOfficeInformationChange
{

    private int|IdealisticOfficePortal $portal;

    public function __construct(
        int|string                   $id,
        Account|int                  $creatorAccount,
        AbstractIdealisticOfficeTeam $team,
        string                       $creationDate,
        ?string                      $creationReason,
        string                       $creationTimeZone,
        int|IdealisticOfficePortal   $portal,
        bool                         $json = true
    )
    {
        parent::__construct($id, $creatorAccount, $team, $creationReason, $creationDate, $creationTimeZone, $json);
        $this->portal = $portal;

        if ($json) {
            self::addToJSON("portal_id", $this->getPortalId());
            self::addToJSON("portal", $this->getPortal(true));
        }
    }

    public function getPortalId(): int
    {
        return is_int($this->portal)
            ? $this->portal
            : $this->portal->getId();
    }

    public function getPortal(bool $json = true): ?IdealisticOfficePortal
    {
        if (is_int($this->portal)) {
            $portal = $this->team->getPortal($this->portal, "id", $json);

            if ($portal instanceof IdealisticOfficePortal) {
                $this->portal = $portal;
            } else {
                return null;
            }
        }
        return $this->portal;
    }

}