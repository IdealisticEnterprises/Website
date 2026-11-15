<?php

class HetznerServer
{

    public string $name, $ipv4, $status;
    public int $identifier, $created;
    public ?float $cpuPercentage;
    public HetznerAbstractServer $type;
    public HetznerServerLocation $location;
    public HetznerNetwork $network;
    public int $customStorageGB;
    public bool $blockingAction, $imageUpdated, $inDns = false;
    public array $labels, $firewalls;

    public function __construct(string                $name,
                                string                $ipv4,
                                int                   $identifier,
                                ?float                $cpuPercentage,
                                HetznerAbstractServer $type,
                                HetznerServerLocation $location,
                                HetznerNetwork        $network,
                                int                   $customStorageGB,
                                bool                  $blockingAction,
                                bool                  $imageUpdated,
                                string                $status = "running",
                                int                   $created = 0,
                                array                 $labels = array(),
                                array                 $firewalls = array())
    {
        $this->name = $name;
        $this->ipv4 = $ipv4;
        $this->identifier = $identifier;
        $this->cpuPercentage = $cpuPercentage;
        $this->network = $network;
        $this->type = $type;
        $this->location = $location;
        $this->customStorageGB = $customStorageGB;
        $this->blockingAction = $blockingAction;
        $this->imageUpdated = $imageUpdated;
        $this->status = $status;
        $this->created = $created;
        $this->labels = $labels;
        $this->firewalls = $firewalls;
    }

    // Separator

    public function isBlockingAction(): bool
    {
        return $this->blockingAction
            || in_array($this->status, array("initializing", "starting", "stopping", "migrating", "rebuilding", "deleting", "unknown"), true);
    }

    public function isRunning(): bool
    {
        return $this->status === "running" && !$this->isBlockingAction();
    }

    public function hasOtherServing(array $servers): bool
    {
        foreach ($servers as $server) {
            if ($server instanceof HetznerServer
                && $server->identifier !== $this->identifier
                && $server->inDns
                && $server->isRunning()) {
                return true;
            }
        }
        return false;
    }

    public function getAgeSeconds(): int
    {
        return $this->created > 0 ? max(0, time() - $this->created) : PHP_INT_MAX;
    }

    public function isReachable(): bool
    {
        return hetzner_can_reach($this->ipv4);
    }

    // Separator

    public function completeDnsRecords(): bool
    {
        return $this->isRunning()
            && $this->isReachable()
            && HetznerAction::getDefaultDomain()?->add_A_DNS(
                HetznerVariables::HETZNER_DNS_RECORD_NAME,
                $this->ipv4,
                true
            );
    }

    public function removeDnsRecords(): bool
    {
        return HetznerAction::getDefaultDomain()?->remove_A_DNS(
            HetznerVariables::HETZNER_DNS_RECORD_NAME,
            $this->ipv4
        ) ?? false;
    }

    // Separator

    private function changeType(array $servers, int $level): bool
    {
        $types = HetznerComparison::getServerTypes($this->type);

        if (!$this->hasOtherServing($servers)
            || !isset($types[$level])
            || isset($this->labels[HetznerVariables::HETZNER_LABEL_TARGET_TYPE])
            || $this->isBlockingAction()) {
            return false;
        }
        $target = $types[$level]->getName();

        if (!HetznerAction::updateLabels($this, array(HetznerVariables::HETZNER_LABEL_TARGET_TYPE => $target))) {
            return false;
        }
        $this->removeDnsRecords();

        if ($this->powerOff()) {
            $this->blockingAction = true;
            return true;
        }
        HetznerAction::updateLabels($this, array(), array(HetznerVariables::HETZNER_LABEL_TARGET_TYPE));
        return false;
    }

    public function upgrade(array $servers): bool
    {
        $level = HetznerComparison::getServerLevel($this);
        return $level !== -1 && $this->changeType($servers, $level + 1);
    }

    public function downgrade(array $servers): bool
    {
        $level = HetznerComparison::getServerLevel($this);
        return $level > 0 && $this->changeType($servers, $level - 1);
    }

    public function resume(): bool
    {
        $target = $this->labels[HetznerVariables::HETZNER_LABEL_TARGET_TYPE] ?? null;

        if ($target === null || $this->isBlockingAction()) {
            return false;
        }
        $clear = fn(): bool => HetznerAction::updateLabels($this, array(), array(HetznerVariables::HETZNER_LABEL_TARGET_TYPE));

        if ($this->status !== "off") {
            $clear();
            return false;
        }
        if ($this->type->getName() !== $target) {
            $object = new stdClass();
            $object->server_type = $target;
            $object->upgrade_disk = false;

            if (HetznerAction::executedAction(
                get_hetzner_object(
                    HetznerConnectionType::POST,
                    "servers/" . $this->identifier . "/actions/change_type",
                    json_encode($object)
                )
            )) {
                $this->blockingAction = true;
                return true;
            }
            HetznerAction::alert(
                array($this),
                "resize",
                "Hetzner Action: Resize Failed",
                sprintf("Server '%s' (%s) could not be changed to '%s', it is being powered on again with its previous type.", $this->name, $this->identifier, $target)
            );
        }
        $clear();
        return $this->powerOn();
    }

    // Separator

    public function update(int $image, array $servers): bool
    {
        if ($this->canUpdate()
            && $this->hasOtherServing($servers)
            && HetznerAction::updateLabels($this, array(HetznerVariables::HETZNER_LABEL_IMAGE => strval($image)))) {
            $this->removeDnsRecords();
            $object = new stdClass();
            $object->image = $image;

            if (HetznerAction::executedAction(
                get_hetzner_object(
                    HetznerConnectionType::POST,
                    "servers/" . $this->identifier . "/actions/rebuild",
                    json_encode($object)
                )
            )) {
                $this->blockingAction = true;
                return true;
            }
        }
        return false;
    }

    public function remove(array $servers): bool
    {
        if (!$this->canDelete() || !$this->hasOtherServing($servers)) {
            return false;
        }
        $this->removeDnsRecords();

        if (HetznerAction::executedAction(
            get_hetzner_object(
                HetznerConnectionType::DELETE,
                "servers/" . $this->identifier
            )
        )) {
            $this->blockingAction = true;
            return true;
        }
        return false;
    }

    // Separator

    public function getUsageRatio(): ?float
    {
        return $this->cpuPercentage === null
            ? null
            : $this->cpuPercentage / $this->type->maxCpuPercentage();
    }

    public function shouldUpgrade(): bool
    {
        $ratio = $this->getUsageRatio();
        return $ratio !== null && $ratio >= HetznerVariables::HETZNER_UPGRADE_USAGE_RATIO;
    }

    public function shouldDowngrade(array $servers): bool
    {
        $ratio = $this->getUsageRatio();

        if ($ratio === null
            || $ratio > HetznerVariables::HETZNER_DOWNGRADE_USAGE_RATIO
            || $this->getAgeSeconds() < HetznerVariables::HETZNER_MIN_SERVER_AGE_SECONDS) {
            return false;
        }
        foreach ($servers as $server) {
            if ($server instanceof HetznerServer
                && $server->identifier !== $this->identifier
                && $server->shouldUpgrade()) {
                return false;
            }
        }
        return true;
    }

    // Separator

    public function canUpgrade(): bool
    {
        $level = HetznerComparison::getServerLevel($this);
        return $level !== -1 && $level < sizeof(HetznerComparison::getServerTypes($this->type)) - 1;
    }

    public function canDowngrade(): bool
    {
        $level = HetznerComparison::getServerLevel($this);

        if ($level > 0) {
            $types = HetznerComparison::getServerTypes($this->type);
            return $this->customStorageGB <= $types[$level - 1]->getStorageGB();
        }
        return false;
    }

    public function canDelete(): bool
    {
        return $this->name != HetznerVariables::HETZNER_DEFAULT_SERVER_NAME
            && $this->getAgeSeconds() >= HetznerVariables::HETZNER_MIN_SERVER_AGE_SECONDS;
    }

    public function canUpdate(): bool
    {
        return $this->name != HetznerVariables::HETZNER_DEFAULT_SERVER_NAME
            && !$this->isBlockingAction();
    }

    public function isUpdated(): bool
    {
        return $this->imageUpdated;
    }

    // Separator

    private function powerOff(): bool
    {
        return HetznerAction::executedAction(
            get_hetzner_object(
                HetznerConnectionType::POST,
                "servers/" . $this->identifier . "/actions/poweroff"
            )
        );
    }

    private function powerOn(): bool
    {
        $success = HetznerAction::executedAction(
            get_hetzner_object(
                HetznerConnectionType::POST,
                "servers/" . $this->identifier . "/actions/poweron"
            )
        );

        if ($success) {
            $this->blockingAction = true;
        }
        return $success;
    }

}
