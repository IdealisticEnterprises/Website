<?php

class CloudflareDomain
{

    private string $domain;

    public function __construct(string $domain)
    {
        $this->domain = $domain;
    }

    public function getZone(): ?string
    {
        return CloudflareVariables::CLOUDFLARE_ZONES[$this->domain] ?? null;
    }

    public function getDNS(?string $name = null): array
    {
        $zone = self::getZone();

        if ($zone === null) {
            return array();
        }
        return CloudflareConnection::query_pages(
            CloudflareConnectionType::GET,
            "zones/" . $zone . "/dns_records?per_page=100"
            . ($name !== null ? "&name=" . urlencode($name . "." . $this->domain) : "")
        );
    }

    public function add_A_DNS(string $name, string $target, bool $proxied): bool
    {
        $zone = self::getZone();

        if ($zone === null) {
            return false;
        }
        foreach (self::getDNS($name) as $page) {
            foreach ($page->result ?? [] as $record) {
                if ($record->type === 'A' && $record->content === $target) {
                    return true;
                }
            }
        }
        $data = [
            'type' => 'A',
            'name' => $name,
            'content' => $target,
            'ttl' => 1, // 1 is Auto
            'proxied' => $proxied
        ];
        $request = CloudflareConnection::query(
            CloudflareConnectionType::POST,
            "zones/" . $zone . "/dns_records",
            json_encode($data)
        );
        return $request?->success ?? false;
    }

    public function remove_A_DNS(string $name, ?string $target = null): bool
    {
        $zone = self::getZone();

        if ($zone === null) {
            return false;
        }
        $dns = self::getDNS($name);

        if (!empty($dns)) {
            $success = false;
            $hasTarget = $target !== null;

            foreach ($dns as $page) {
                foreach ($page->result as $record) {
                    if ($record->name == ($name . "." . $this->domain)
                        && (!$hasTarget
                            || $record->content == $target)) {
                        $request = CloudflareConnection::query(
                            CloudflareConnectionType::DELETE,
                            "zones/" . $zone . "/dns_records/" . $record->id
                        );
                        $success |= $request?->success ?? false;

                        if ($hasTarget) {
                            break 2;
                        }
                    }
                }
            }
            return $success;
        }
        return false;
    }

}
