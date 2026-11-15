<?php

function get_hetzner_object(string $type, string $service, mixed $arguments = null): object|bool|null
{
    $credentials = get_keys_from_file(HetznerVariables::HETZNER_CREDENTIALS_DIRECTORY, 1);

    if ($credentials === null) {
        return null;
    }
    return json_decode(get_curl(
        "https://api.hetzner.cloud/" . HetznerVariables::HETZNER_API_VERSION . "/" . $service,
        $type,
        array(
            "Content-Type: application/json",
            "Authorization: Bearer " . $credentials[0]
        ),
        $arguments,
        5
    ), false);
}

function get_hetzner_object_pages(string $type, string $service, mixed $arguments = null): array
{
    $results = array();
    $separator = str_contains($service, "?") ? "&" : "?";
    $page = 1;

    while ($page !== null && $page <= 100) {
        $object = get_hetzner_object(
            $type,
            $service . $separator . "page=" . $page,
            $arguments
        );

        if (!is_object($object)) {
            break;
        }
        $results[] = $object;
        $page = $object->meta->pagination->next_page ?? null;
    }
    return $results;
}

function get_hetzner_error(object|bool|null $object): ?string
{
    return is_object($object) && isset($object->error)
        ? ($object->error->code ?? "error") . ": " . ($object->error->message ?? "")
        : null;
}

if (!function_exists('hetzner_can_reach')) {
    function hetzner_can_reach(string $ip): bool
    {
        foreach (HetznerVariables::HETZNER_CONNECT_PORTS as $port) {
            $socket = @fsockopen($ip, $port, $errorCode, $errorMessage, HetznerVariables::HETZNER_CONNECT_TIMEOUT_SECONDS);

            if ($socket !== false) {
                fclose($socket);
                return true;
            }
        }
        return false;
    }
}
