<?php

class IdealisticOfficeRoleManager implements PhpAsyncSerializable
{

    private IdealisticOfficeUser $user;

    public function __construct(IdealisticOfficeUser $user)
    {
        $this->user = $user;
    }

    public function addRolePermission(IdealisticOfficeRole $role, int|string $permission, ?string $justification = null): IdealisticOfficeOutcome
    {
        $raw = $role->getRawObject();

        if ($raw === null) {
            return new IdealisticOfficeOutcome(false, "Role not found with ID '" . $role->getId() . "'.");
        }
        if (!isset($justification[0])) {
            $justification = null;
        }
        $outcome = $this->user->getTeamManager()->addRolePermission(
            $raw,
            $permission,
            $justification
        );
        return new IdealisticOfficeOutcome($outcome->isPositiveOutcome(), $outcome->getMessage());
    }

    public function removeRolePermission(IdealisticOfficeRole $role, int|string $permission, ?string $justification = null): IdealisticOfficeOutcome
    {
        $raw = $role->getRawObject();

        if ($raw === null) {
            return new IdealisticOfficeOutcome(false, "Role not found with ID '" . $role->getId() . "'.");
        }
        if (!isset($justification[0])) {
            $justification = null;
        }
        $outcome = $this->user->getTeamManager()->removeRolePermission(
            $raw,
            $permission,
            $justification
        );
        return new IdealisticOfficeOutcome($outcome->isPositiveOutcome(), $outcome->getMessage());
    }

    // Separator

    public function setUserRole(IdealisticOfficeUser $user, IdealisticOfficeRole $role, bool $trueFalse, ?string $justification = null): IdealisticOfficeOutcome
    {
        $raw = $role->getRawObject();

        if ($raw === null) {
            return new IdealisticOfficeOutcome(false, "Role not found with ID '" . $role->getId() . "'.");
        }
        if (!isset($justification[0])) {
            $justification = null;
        }
        $outcome = $this->user->getTeamManager()->setRole(
            $user->getAccount(),
            $raw,
            $trueFalse,
            $justification
        );
        return new IdealisticOfficeOutcome($outcome->isPositiveOutcome(), $outcome->getMessage());
    }

    // Separator

    public function adjustRoleHierarchy(IdealisticOfficeRole $role, int $level, ?string $justification = null): IdealisticOfficeOutcome
    {
        $raw = $role->getRawObject();

        if ($raw === null) {
            return new IdealisticOfficeOutcome(false, "Role not found with ID '" . $role->getId() . "'.");
        }
        $outcome = $this->user->getTeamManager()->adjustRolePosition(
            $raw,
            $level,
            $justification
        );
        return new IdealisticOfficeOutcome($outcome->isPositiveOutcome(), $outcome->getMessage());
    }

    // Separator

    public function addRole(string $name, ?string $justification = null): IdealisticOfficeOutcome
    {
        if (!isset($justification[0])) {
            $justification = null;
        }
        if (!isset($justification[0])) {
            $justification = null;
        }
        $outcome = $this->user->getTeamManager()->createRole(
            $name,
            $justification
        );
        return new IdealisticOfficeOutcome($outcome->isPositiveOutcome(), $outcome->getMessage());
    }

    public function removeRole(IdealisticOfficeRole $role, ?string $justification = null): IdealisticOfficeOutcome
    {
        $raw = $role->getRawObject();

        if ($raw === null) {
            return new IdealisticOfficeOutcome(false, "Role not found with ID '" . $role->getId() . "'.");
        }
        if (!isset($justification[0])) {
            $justification = null;
        }
        $outcome = $this->user->getTeamManager()->deleteRole(
            $raw,
            $justification
        );
        return new IdealisticOfficeOutcome($outcome->isPositiveOutcome(), $outcome->getMessage());
    }

    public function restoreRole(IdealisticOfficeRole $role): IdealisticOfficeOutcome
    {
        $raw = $role->getRawObject();

        if ($raw === null) {
            return new IdealisticOfficeOutcome(false, "Role not found or is not removed.");
        }
        $outcome = $this->user->getTeamManager()->restoreRole(
            $raw
        );
        return new IdealisticOfficeOutcome($outcome->isPositiveOutcome(), $outcome->getMessage());
    }

    public function renameRole(
        IdealisticOfficeRole $role,
        string               $name,
        ?string              $justification = null
    ): IdealisticOfficeOutcome
    {
        $raw = $role->getRawObject();

        if ($raw === null) {
            return new IdealisticOfficeOutcome(false, "Role not found with ID '" . $role->getId() . "'.");
        }
        if (!isset($justification[0])) {
            $justification = null;
        }
        $outcome = $this->user->getTeamManager()->updateRoleTitle(
            $raw,
            $name,
            $justification
        );
        return new IdealisticOfficeOutcome($outcome->isPositiveOutcome(), $outcome->getMessage());
    }

}
