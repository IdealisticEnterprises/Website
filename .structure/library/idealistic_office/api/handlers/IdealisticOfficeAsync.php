<?php

class IdealisticOfficeAsync implements PhpAsyncSerializable
{

    public static function run(
        array|string|callable $method,
        array                 $parameters = [],
        ?bool                 $debug = null
    ): void
    {
        $async = new PhpAsync();
        $async->storeAndRun(
            $method,
            $parameters,
            array(
                "/var/www/.structure/library/idealistic_office/init.php"
            ),
            $debug,
            null,
            true
        );
    }

}
