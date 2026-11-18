<?php

namespace idealistic_office\api\tasks\prompts\paypalEmail;

use AIObjectConstructor;
use idealistic_office\api\IdealisticOfficeVariable;
use idealistic_office\api\objects\helpers\IdealisticOfficeAI;
use idealistic_office\api\objects\helpers\IdealisticOfficeCredit;
use idealistic_office\api\objects\helpers\IdealisticOfficeOutcome;
use idealistic_office\api\objects\IdealisticOfficePrompt;
use idealistic_office\api\objects\IdealisticOfficeUser;
use idealistic_office\api\tasks\prompts\folder\IdealisticOfficePromptShareInformation;
use IdealisticOfficePromptFields;
use IdealisticOfficePromptTask;
use PhpAsyncSerializable;
use function React\Promise\reject;
use function React\Promise\resolve;

class IdealisticOfficePromptManagePaypalEmails implements PhpAsyncSerializable
{

    public static function description(IdealisticOfficePrompt $prompt): ?string
    {
        return "[SECONDARY FEATURE] PayPal emails are used to track your transactions and load you up with credits once a new transaction is found.";
    }

    public static function fields(IdealisticOfficePrompt $prompt): array
    {
        return array(
            "0_new_paypal_email_addresses" => IdealisticOfficePromptFields::nullableStringArray(
                "The new PayPal email addresses to add to the team"
            ),

            "1_paypal_email_addresses_to_remove" => IdealisticOfficePromptFields::nullableStringArray(
                "The IDs of the PayPal email addresses or plain PayPal email addresses to remove from the team"
            )
        );
    }

    public static function add(IdealisticOfficeUser $user, string $email): IdealisticOfficeOutcome
    {
        return $user->getPaypalEmailManager()->addTeamEmail(trim($email));
    }

    public static function remove(IdealisticOfficeUser $user, string $emailOrId): IdealisticOfficeOutcome
    {
        $address = $user->getTeam()->getPayPalEmail(trim($emailOrId), false);
        return $address !== null
            ? $user->getPaypalEmailManager()->removeTeamEmail($address)
            : new IdealisticOfficeOutcome(false, "No PayPal email address found to remove from team.");
    }

    public static function run(
        IdealisticOfficePrompt $prompt
    ): void
    {
        $prompt->waitForTasks(array(IdealisticOfficePromptTask::TEAM_INFO));
        $prompt->runTask(function () use ($prompt) {
            try {
                if (!IdealisticOfficeCredit::ALLOW_PAYPAL
                    || $prompt->getCache()->isSubscriber()
                    || IdealisticOfficePromptShareInformation::isSharing($prompt)) {
                    return resolve(null);
                }
                $object = new AIObjectConstructor(
                    $prompt->expandFields(self::fields($prompt)),
                    $prompt->expandInstructions()
                );
                $arguments = array(
                    "messages" => array(
                        array(
                            "role" => "system",
                            "content" => $object->build()
                        ),
                        array(
                            "role" => "user",
                            "content" => $prompt->getCache()->getAiReply()
                        )
                    ),
                    "response_format" => array("type" => "json_object")
                );
                $AI = new IdealisticOfficeAI(
                    $prompt,
                    IdealisticOfficeVariable::TASK_AI_CHEAP_MODEL
                );
                $AI->adjustCertainty(
                    $arguments
                );
                return $AI->getReactResult(
                    $arguments
                )->then(function ($result) use ($prompt, $object) {
                    $verify = $prompt->readAIObject($object, $result);

                    if ($verify === null) {
                        return resolve(null);
                    } else {
                        $outcomes = array();

                        if (!empty($verify->{"0_new_paypal_email_addresses"})) {
                            foreach ($verify->{"0_new_paypal_email_addresses"} as $name) {
                                $outcomes[] = self::add($prompt->getUser(), $name);
                            }
                        }
                        if (!empty($verify->{"1_paypal_email_addresses_to_remove"})) {
                            foreach ($verify->{"1_paypal_email_addresses_to_remove"} as $name) {
                                $outcomes[] = self::remove($prompt->getUser(), $name);
                            }
                        }
                        return resolve($outcomes);
                    }
                });
            } catch (Throwable $e) {
                return reject($e);
            }
        });
    }

}
