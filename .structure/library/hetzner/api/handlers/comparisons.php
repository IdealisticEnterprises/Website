<?php

class HetznerComparison
{

    public static function getServerTypes(HetznerAbstractServer $type): array
    {
        global $HETZNER_ARM_SERVERS, $HETZNER_X86_SERVERS;
        return $type instanceof HetznerArmServer ? $HETZNER_ARM_SERVERS : $HETZNER_X86_SERVERS;
    }

    public static function getServerLevel(HetznerServer $server, bool $storage = false): int
    {
        foreach (self::getServerTypes($server->type) as $key => $value) {
            if ($server->type->getCpuCores() === $value->getCpuCores()
                && $server->type->getMemoryGB() === $value->getMemoryGB()
                && $server->type->getName() === $value->getName()
                && (!$storage || $server->type->getStorageGB() === $value->getStorageGB())) {
                return $key;
            }
        }
        return -1;
    }

    public static function shouldConsiderServer(HetznerServer|string $server): bool
    {
        return str_starts_with(
            is_string($server) ? $server : $server->name,
            HetznerVariables::HETZNER_SERVER_NAME_PATTERN
        );
    }

    public static function findLeastLevelServer(array $servers, bool $delete = false): ?HetznerServer
    {
        $min = null;

        foreach ($servers as $server) {
            if (!($server instanceof HetznerServer)) {
                continue;
            }
            if ((!$delete || $server->canDelete())
                && ($min === null || self::getServerLevel($server) < self::getServerLevel($min))) {
                $min = $server;
            }
        }
        return $min;
    }

    public static function findGreatestLevelServer(array $servers): ?HetznerServer
    {
        $max = null;

        foreach ($servers as $server) {
            if ($server instanceof HetznerServer
                && ($max === null || self::getServerLevel($server) > self::getServerLevel($max))) {
                $max = $server;
            }
        }
        return $max;
    }

    public static function findBusiestServer(array $servers): ?HetznerServer
    {
        $busiest = null;

        foreach ($servers as $server) {
            if ($server instanceof HetznerServer
                && $server->getUsageRatio() !== null
                && ($busiest === null || $server->getUsageRatio() > $busiest->getUsageRatio())) {
                $busiest = $server;
            }
        }
        return $busiest;
    }

}
