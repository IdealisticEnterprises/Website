<?php

class IdealisticOfficeEmailer implements PhpAsyncSerializable
{

    public static function send(
        int|string|float $planID,
        string           $emailPointer,
        ?array           $details = null,
        bool             $unsubscribe = true,
        int|string|null  $cooldown = null
    ): bool
    {
        $int = send_email_by_plan(
            $planID,
            $emailPointer,
            $details,
            $unsubscribe,
            $cooldown
        );

        if ($int !== 1) {
            IdealisticOfficeError::simpleStore(
                "Failed to send email with plan ID: "
                . $planID
                . " and email pointer: "
                . $emailPointer
                . ". Error code: "
                . $int
            );
        }
        return true;
    }

}