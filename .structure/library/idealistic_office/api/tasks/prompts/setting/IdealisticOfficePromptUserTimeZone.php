<?php

use function React\Promise\reject;
use function React\Promise\resolve;

class IdealisticOfficePromptUserTimeZone implements PhpAsyncSerializable
{

    public static function description(IdealisticOfficePrompt $prompt): ?string
    {
        return "[SECONDARY FEATURE] The software adapts to the your time zone once you set it.";
    }

    public static function fields(IdealisticOfficePrompt $prompt): array
    {
        return array(
            "time_zone" => new AIFieldObject(
                AIField::STRING,
                null,
                false,
                "The time zone the contact is attempting to set in time zone abbreviation format"
            )
        );
    }

    public static function set(IdealisticOfficeUser $user, string $timeZone): IdealisticOfficeOutcome
    {
        return $user->setUserTimezone(trim($timeZone));
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

                    if ($verify === null) {
                        return resolve(null);
                    } else {
                        $outcome = self::set($prompt->getUser(), $verify->time_zone);
                        return resolve($outcome->isPositiveOutcome() ? $outcome : null);
                    }
                });
            } catch (Throwable $e) {
                return reject($e);
            }
        });
    }

}
