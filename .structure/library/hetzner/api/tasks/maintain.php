<?php
require_once '/var/www/.structure/library/hetzner/init.php';

function hetzner_maintain_network(): bool
{
    $lock = @fopen(sys_get_temp_dir() . "/" . HetznerVariables::HETZNER_LOCK_FILE, "c");

    if ($lock === false) {
        return false;
    }
    if (!flock($lock, LOCK_EX | LOCK_NB)) {
        fclose($lock);
        return false;
    }

    try {
        $networks = HetznerAction::getNetworks();
        $servers = HetznerAction::getServers($networks);
        return !empty($servers) && HetznerAction::maintain($servers);
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}
