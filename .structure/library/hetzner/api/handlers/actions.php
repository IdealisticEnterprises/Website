<?php

class HetznerAction
{
    private static array $defaultImages = array();
    private static ?CloudflareDomain $defaultDomain = null;

    private static function date(string $time = "now"): string
    {
        return urlencode((new DateTimeImmutable($time))->format(DateTimeInterface::ATOM));
    }

    public static function executedAction(array|object|bool|null $query): bool
    {
        if (!is_object($query) || isset($query->error)) {
            return false;
        }
        $action = $query->action ?? null;
        return is_object($action)
            && !isset($action->error->code)
            && !isset($action->error->message)
            && ($action->status ?? "") !== "error";
    }

    public static function updateLabels(HetznerServer $server, array $set, array $remove = array()): bool
    {
        $labels = $server->labels;

        foreach ($remove as $key) {
            unset($labels[$key]);
        }
        foreach ($set as $key => $value) {
            $labels[$key] = strval($value);
        }
        $query = get_hetzner_object(
            HetznerConnectionType::PUT,
            "servers/" . $server->identifier,
            json_encode(array("labels" => (object)$labels))
        );

        if (is_object($query) && !isset($query->error) && isset($query->server)) {
            $server->labels = $labels;
            return true;
        }
        return false;
    }

    // Separator

    public static function getDefaultImage(string $architecture = "arm"): ?int
    {
        if (array_key_exists($architecture, self::$defaultImages)) {
            return self::$defaultImages[$architecture];
        }
        $query = get_hetzner_object_pages(
            HetznerConnectionType::GET,
            "images?type=snapshot&status=available&sort=created:desc&per_page=50&architecture=" . urlencode($architecture)
        ) ?: [];

        foreach ($query as $page) {
            foreach ($page->images ?? [] as $image) {
                if ($image->description === HetznerVariables::HETZNER_DEFAULT_IMAGE_NAME) {
                    return self::$defaultImages[$architecture] = $image->id;
                }
            }
        }
        return null;
    }

    public static function getDnsAddresses(): array
    {
        $addresses = array();

        foreach (self::getDefaultDomain()?->getDNS(HetznerVariables::HETZNER_DNS_RECORD_NAME) ?? [] as $page) {
            foreach ($page->result ?? [] as $record) {
                if ($record->type === "A") {
                    $addresses[] = $record->content;
                }
            }
        }
        return $addresses;
    }

    public static function getDefaultDomain(): ?CloudflareDomain
    {
        if (self::$defaultDomain !== null) {
            return self::$defaultDomain;
        }

        global $backup_domain;
        $parts = explode(".", $backup_domain ?? "");

        if (count($parts) >= 2) {
            return self::$defaultDomain = new CloudflareDomain(implode(".", array_slice($parts, -2)));
        }
        return null;
    }

    // Separator

    private static function getCpuPercentage(int $serverID): ?float
    {
        $metricsUrl = sprintf(
            "servers/%s/metrics?type=cpu&start=%s&end=%s&step=%d",
            $serverID,
            self::date("-" . HetznerVariables::CPU_METRICS_PAST_SECONDS . " seconds"),
            self::date(),
            HetznerVariables::CPU_METRICS_STEP_SECONDS
        );
        $metricsData = get_hetzner_object(HetznerConnectionType::GET, $metricsUrl);
        $values = $metricsData->metrics->time_series->cpu->values ?? null;

        if (!is_array($values) || empty($values)) {
            return null;
        }
        $sum = 0.0;
        $count = 0;

        foreach ($values as $value) {
            if (isset($value[1]) && is_numeric($value[1])) {
                $sum += (float)$value[1];
                $count++;
            }
        }
        return $count > 0 ? $sum / $count : null;
    }

    public static function getServers(?array $networks): array
    {
        if (empty($networks)) {
            return [];
        }

        $query = get_hetzner_object_pages(HetznerConnectionType::GET, "servers") ?: [];
        $array = [];

        foreach ($query as $page) {
            foreach ($page->servers ?? [] as $server) {
                $serverID = $server->id;

                if (!isset($server->name)
                    || !HetznerComparison::shouldConsiderServer($server->name)
                    || !isset($server->datacenter->location->name)
                    || !isset($server->server_type)
                    || !isset($server->public_net->ipv4->ip)) {
                    continue;
                }

                foreach ($networks as $network) {
                    if (!$network->isServerIncluded($serverID)) {
                        continue;
                    }

                    $type = $server->server_type;
                    $isArm = strtolower($type->architecture) === "arm";
                    $serverClass = $isArm ? HetznerArmServer::class : HetznerX86Server::class;
                    $serverInstance = new $serverClass(
                        strtolower($type->name),
                        $type->cores,
                        $type->memory,
                        $type->disk
                    );
                    $labels = isset($server->labels) ? (array)$server->labels : array();
                    $defaultImage = self::getDefaultImage($isArm ? "arm" : "x86");
                    $firewalls = array();

                    foreach ($server->public_net->firewalls ?? [] as $firewall) {
                        $firewalls[] = $firewall->id;
                    }

                    $array[$serverID] = new HetznerServer(
                        $server->name,
                        $server->public_net->ipv4->ip,
                        $serverID,
                        self::getCpuPercentage($serverID),
                        $serverInstance,
                        new HetznerServerLocation($server->datacenter->location->name),
                        $network,
                        $server->primary_disk_size ?? $type->disk,
                        $server->locked ?? false,
                        $defaultImage === null
                        || ($server->image->id ?? null) === $defaultImage
                        || ($labels[HetznerVariables::HETZNER_LABEL_IMAGE] ?? null) === strval($defaultImage),
                        $server->status ?? "unknown",
                        isset($server->created) ? (int)strtotime($server->created) : 0,
                        $labels,
                        $firewalls
                    );
                    break;
                }
            }
        }
        return $array;
    }

    public static function getNetworks(): array
    {
        $query = get_hetzner_object_pages(HetznerConnectionType::GET, "networks") ?: [];
        $array = [];

        foreach ($query as $page) {
            foreach ($page->networks ?? [] as $network) {
                $array[$network->id] = new HetznerNetwork($network->id, $network->servers);
            }
        }
        return $array;
    }

    // Separator

    public static function addNewServerBasedOn(
        array                 $servers,
        HetznerServerLocation $location,
        HetznerNetwork        $network,
        HetznerAbstractServer $serverType,
        int                   $level,
        ?HetznerServer        $template = null
    ): bool
    {
        $types = HetznerComparison::getServerTypes($serverType);
        $image = self::getDefaultImage($serverType instanceof HetznerArmServer ? "arm" : "x86");

        if ($image === null || !isset($types[$level])) {
            return false;
        }

        $existingNames = array_column($servers, 'name');

        do {
            $name = HetznerVariables::HETZNER_SERVER_NAME_PATTERN . random_int(100000, 999999);
        } while (in_array($name, $existingNames, true));

        $payload = [
            'name' => $name,
            'location' => $location->getName(),
            'image' => $image,
            'start_after_create' => true,
            'networks' => [$network->getIdentifier()],
            'server_type' => $types[$level]->getName(),
            'labels' => [HetznerVariables::HETZNER_LABEL_IMAGE => strval($image)]
        ];

        if ($template !== null && !empty($template->firewalls)) {
            $payload['firewalls'] = array_map(fn($id) => ['firewall' => $id], $template->firewalls);
        }

        try {
            $json = json_encode($payload, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return false;
        }

        return self::executedAction(
            get_hetzner_object(HetznerConnectionType::POST, "servers", $json)
        );
    }

    // Separator

    private static function getReferenceServer(array $servers): ?HetznerServer
    {
        $reference = null;

        foreach ($servers as $server) {
            if (!($server instanceof HetznerServer)) {
                continue;
            }
            if ($server->name === HetznerVariables::HETZNER_DEFAULT_SERVER_NAME) {
                return $server;
            }
            if ($reference === null || $server->identifier < $reference->identifier) {
                $reference = $server;
            }
        }
        return $reference;
    }

    public static function alert(array $servers, string $key, string $subject, string $message): bool
    {
        $reference = self::getReferenceServer($servers);
        $label = HetznerVariables::HETZNER_LABEL_ALERT_PREFIX . $key;

        if ($reference !== null) {
            if (time() - (int)($reference->labels[$label] ?? 0) < HetznerVariables::HETZNER_ALERT_COOLDOWN_SECONDS) {
                return false;
            }
            self::updateLabels($reference, array($label => time()));
        }
        return services_self_email("scaler@system.local", $subject, $message) === true;
    }

    private static function isCoolingDown(array $servers): bool
    {
        $reference = self::getReferenceServer($servers);
        return $reference !== null
            && time() - (int)($reference->labels[HetznerVariables::HETZNER_LABEL_LAST_ACTION] ?? 0) < HetznerVariables::HETZNER_SCALE_COOLDOWN_SECONDS;
    }

    private static function markScaled(array $servers): void
    {
        $reference = self::getReferenceServer($servers);

        if ($reference !== null) {
            self::updateLabels($reference, array(HetznerVariables::HETZNER_LABEL_LAST_ACTION => time()));
        }
    }

    // Separator

    public static function maintain(array $servers): bool
    {
        $busy = false;

        foreach ($servers as $server) {
            if ($server instanceof HetznerServer && $server->isBlockingAction()) {
                $server->removeDnsRecords();
                $busy = true;
            }
        }

        if ($busy) {
            return true;
        }

        foreach ($servers as $server) {
            if ($server instanceof HetznerServer && $server->resume()) {
                return true;
            }
        }

        $addresses = self::getDnsAddresses();

        foreach ($servers as $server) {
            if ($server instanceof HetznerServer) {
                $server->inDns = in_array($server->ipv4, $addresses, true) || $server->completeDnsRecords();
            }
        }

        foreach ($servers as $server) {
            if (!($server instanceof HetznerServer) || $server->isUpdated() || !$server->canUpdate()) {
                continue;
            }
            $image = self::getDefaultImage($server->type instanceof HetznerArmServer ? "arm" : "x86");

            if ($image !== null && $server->update($image, $servers)) {
                return true;
            }
        }

        if (self::isCoolingDown($servers)) {
            return false;
        }
        $overloaded = array_filter($servers, fn($server) => $server instanceof HetznerServer && $server->shouldUpgrade());

        $changed = !empty($overloaded)
            ? self::scaleUp($servers, $overloaded)
            : self::scaleDown($servers);

        if ($changed) {
            self::markScaled($servers);
        }
        return $changed;
    }

    private static function scaleUp(array $servers, array $overloaded): bool
    {
        $budget = HetznerVariables::HETZNER_MONTHLY_BUDGET_EUR;
        $fleetCost = HetznerPricing::getFleetMonthly($servers);

        if ($fleetCost === null) {
            self::alert($servers, "pricing", "Hetzner Action: Pricing Unavailable", "Load thresholds are exceeded, but the Hetzner prices could not be read in " . HetznerVariables::HETZNER_BUDGET_CURRENCY . ", so nothing was changed.");
            return false;
        }

        if (count($servers) > 1) {
            $upgradable = array();

            foreach ($overloaded as $server) {
                $types = HetznerComparison::getServerTypes($server->type);
                $next = $types[HetznerComparison::getServerLevel($server) + 1] ?? null;
                $delta = $next === null ? null : HetznerPricing::getUpgradeDelta($server, $next->getName());

                if ($server->canUpgrade() && $delta !== null && $fleetCost + $delta <= $budget) {
                    $upgradable[] = $server;
                }
            }

            if (!empty($upgradable)
                && HetznerComparison::findLeastLevelServer($upgradable)->upgrade($servers)) {
                return true;
            }
        }

        if (count($servers) < HetznerVariables::HETZNER_MAX_SERVERS) {
            $source = HetznerComparison::findBusiestServer($overloaded);

            for ($level = max(0, HetznerComparison::getServerLevel($source)); $level >= 0; $level--) {
                $types = HetznerComparison::getServerTypes($source->type);
                $price = HetznerPricing::getNewServerMonthly($types[$level]->getName(), $source->location->getName());

                if ($price !== null && $fleetCost + $price <= $budget) {
                    return self::addNewServerBasedOn(
                        $servers,
                        $source->location,
                        $source->network,
                        $source->type,
                        $level,
                        $source
                    );
                }
            }
        }

        self::alert(
            $servers,
            "capacity",
            "Hetzner Action: Capacity Needed",
            sprintf(
                "Load thresholds are exceeded, but the scaler cannot grow within its limits (%d of %d servers, %.2f of %.2f %s per month). Raise HetznerVariables limits or add capacity manually.",
                count($servers), HetznerVariables::HETZNER_MAX_SERVERS, $fleetCost, $budget, HetznerVariables::HETZNER_BUDGET_CURRENCY
            )
        );
        return false;
    }

    private static function scaleDown(array $servers): bool
    {
        if (count($servers) < 2) {
            return false;
        }
        $fleetCost = HetznerPricing::getFleetMonthly($servers);
        $overBudget = $fleetCost !== null && $fleetCost > HetznerVariables::HETZNER_MONTHLY_BUDGET_EUR;
        $limit = $overBudget
            ? HetznerVariables::HETZNER_UPGRADE_USAGE_RATIO
            : HetznerVariables::HETZNER_REMOVE_USAGE_RATIO;

        if (HetznerVariables::HETZNER_ALLOW_AUTOMATIC_REMOVAL
            && count($servers) > HetznerVariables::HETZNER_MIN_SERVERS) {
            $usedCores = 0.0;
            $known = true;

            foreach ($servers as $server) {
                if ($server->cpuPercentage === null) {
                    $known = false;
                    break;
                }
                $usedCores += $server->cpuPercentage / 100.0;
            }
            $removable = $known
                ? array_filter($servers, fn($server) => $server->canDelete()
                    && $usedCores / (array_sum(array_map(fn($other) => $other->type->getCpuCores(), $servers)) - $server->type->getCpuCores()) <= $limit)
                : array();

            if (!empty($removable)) {
                usort($removable, fn($a, $b) => [HetznerComparison::getServerLevel($a), $a->getUsageRatio()]
                    <=> [HetznerComparison::getServerLevel($b), $b->getUsageRatio()]);

                if ($removable[0]->remove($servers)) {
                    return true;
                }
            }
        }

        $downgradable = array();

        foreach ($servers as $server) {
            $types = HetznerComparison::getServerTypes($server->type);
            $level = HetznerComparison::getServerLevel($server);

            if ($level > 0
                && $server->shouldDowngrade($servers)
                && $server->canDowngrade()
                && $server->getUsageRatio() * $server->type->getCpuCores() / $types[$level - 1]->getCpuCores() < HetznerVariables::HETZNER_UPGRADE_USAGE_RATIO * 0.75) {
                $downgradable[] = $server;
            }
        }

        return !empty($downgradable)
            && HetznerComparison::findGreatestLevelServer($downgradable)->downgrade($servers);
    }
}
