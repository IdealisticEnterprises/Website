<?php

namespace idealistic_office\api\tasks\prompts\team;

use AIObjectConstructor;
use idealistic_office\api\IdealisticOfficeVariable;
use idealistic_office\api\objects\helpers\IdealisticOfficeAI;
use idealistic_office\api\objects\helpers\IdealisticOfficeOutcome;
use idealistic_office\api\objects\IdealisticOfficePrompt;
use idealistic_office\api\objects\IdealisticOfficeUser;
use idealistic_office\api\tasks\prompts\folder\IdealisticOfficePromptShareInformation;
use IdealisticOfficePromptFields;
use IdealisticOfficePromptTask;
use PhpAsyncSerializable;
use function React\Promise\reject;
use function React\Promise\resolve;

class IdealisticOfficePromptDataRetention implements PhpAsyncSerializable
{

    public static function description(IdealisticOfficePrompt $prompt): ?string
    {
        return "[SECONDARY FEATURE] Choose how long your data is kept before it is deleted, anywhere from "
            . IdealisticOfficeVariable::DATA_RETENTION_OPTION_MINIMUM_DAYS . " to "
            . IdealisticOfficeVariable::DATA_RETENTION_MAXIMUM_DAYS . " days. Without a choice, data is kept for "
            . IdealisticOfficeVariable::DATA_RETENTION_MINIMUM_DAYS . " days.";
    }

    public static function fields(IdealisticOfficePrompt $prompt): array
    {
        return array(
            "retention_days" => IdealisticOfficePromptFields::nullableInteger(
                "The number of days the contact explicitly asks for their data to be kept before it is deleted, between "
                . IdealisticOfficeVariable::DATA_RETENTION_MINIMUM_DAYS . " and "
                . IdealisticOfficeVariable::DATA_RETENTION_MAXIMUM_DAYS
                . ", only when they clearly request to change how long their data is kept"
            )
        );
    }

    public static function set(IdealisticOfficeUser $user, int $days): IdealisticOfficeOutcome
    {
        return $user->setRetentionDays($days);
    }

    public static function run(
        IdealisticOfficePrompt $prompt
    ): void
    {
        $prompt->waitForTasks(array(IdealisticOfficePromptTask::TEAM_INFO));
        $prompt->runTask(function () use ($prompt) {
            try {
                if (IdealisticOfficePromptShareInformation::isSharing($prompt)) {
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

                    if (empty($verify?->retention_days)) {
                        return resolve(null);
                    }
                    return resolve(self::set($prompt->getUser(), (int)$verify->retention_days));
                });
            } catch (Throwable $e) {
                return reject($e);
            }
        });
    }

}
