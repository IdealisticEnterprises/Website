<?php

class HetznerPricing
{

    private static ?array $pricing = null;

    public static function load(): ?array
    {
        if (self::$pricing !== null) {
            return self::$pricing;
        }
        $query = get_hetzner_object(HetznerConnectionType::GET, "pricing");
        $pricing = $query->pricing ?? null;

        if (!is_object($pricing)
            || ($pricing->currency ?? null) !== HetznerVariables::HETZNER_BUDGET_CURRENCY) {
            return null;
        }
        $servers = array();
        $addresses = array();

        foreach ($pricing->server_types ?? [] as $type) {
            foreach ($type->prices ?? [] as $price) {
                $servers[strtolower($type->name)][$price->location] = (float)$price->price_monthly->gross;
            }
        }
        foreach ($pricing->primary_ips ?? [] as $ip) {
            if (strtolower($ip->type ?? "") !== "ipv4") {
                continue;
            }
            foreach ($ip->prices ?? [] as $price) {
                $addresses[$price->location] = (float)$price->price_monthly->gross;
            }
        }
        return self::$pricing = array("servers" => $servers, "addresses" => $addresses);
    }

    public static function getServerMonthly(string $type, string $location): ?float
    {
        return self::load()["servers"][strtolower($type)][$location] ?? null;
    }

    public static function getAddressMonthly(string $location): ?float
    {
        return self::load()["addresses"][$location] ?? null;
    }

    public static function getNewServerMonthly(string $type, string $location): ?float
    {
        $server = self::getServerMonthly($type, $location);
        $address = self::getAddressMonthly($location);
        return $server === null || $address === null ? null : $server + $address;
    }

    public static function getFleetMonthly(array $servers): ?float
    {
        $total = 0.0;

        foreach ($servers as $server) {
            if (!($server instanceof HetznerServer)) {
                continue;
            }
            $price = self::getNewServerMonthly($server->type->getName(), $server->location->getName());

            if ($price === null) {
                return null;
            }
            $total += $price;
        }
        return $total;
    }

    public static function getUpgradeDelta(HetznerServer $server, string $newType): ?float
    {
        $current = self::getServerMonthly($server->type->getName(), $server->location->getName());
        $target = self::getServerMonthly($newType, $server->location->getName());
        return $current === null || $target === null ? null : $target - $current;
    }

}
