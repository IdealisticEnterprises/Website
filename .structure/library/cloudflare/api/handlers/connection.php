<?php

class CloudflareConnection
{

    public static function query(string $type, string $service, mixed $arguments = null): object|bool|null
    {
        $credentials = get_keys_from_file(CloudflareVariables::CLOUDFLARE_CREDENTIALS_DIRECTORY, 1);

        if ($credentials === null) {
            return null;
        }
        return json_decode(get_curl(
            "https://api.cloudflare.com/client/" . CloudflareVariables::CLOUDFLARE_API_VERSION . "/" . $service,
            $type,
            array(
                "Content-Type: application/json",
                "Authorization: Bearer " . $credentials[0]
            ),
            $arguments,
            5
        ), false);
    }

    public static function query_pages(string $type, string $service, mixed $arguments = null): array
    {
        $results = array();
        $separator = str_contains($service, "?") ? "&" : "?";
        $page = 1;
        $totalPages = 1;

        do {
            $object = self::query($type, $service . $separator . "page=" . $page, $arguments);

            if (!is_object($object)) {
                break;
            }
            $results[] = $object;
            $totalPages = (int)($object->result_info->total_pages ?? 1);
            $page++;
        } while ($page <= $totalPages && $page <= 100);

        return $results;
    }

}
