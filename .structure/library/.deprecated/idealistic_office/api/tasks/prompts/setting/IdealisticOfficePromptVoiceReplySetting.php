<?php

namespace idealistic_office\api\tasks\prompts\setting;

use AIField;
use AIFieldObject;
use AIObjectConstructor;
use idealistic_office\api\IdealisticOfficeVariable;
use idealistic_office\api\objects\helpers\IdealisticOfficeAI;
use idealistic_office\api\objects\helpers\IdealisticOfficeOutcome;
use idealistic_office\api\objects\IdealisticOfficePrompt;
use idealistic_office\api\objects\IdealisticOfficeUser;
use idealistic_office\api\tasks\prompts\folder\IdealisticOfficePromptShareInformation;
use IdealisticOfficePromptTask;
use IdealisticOfficeSettings;
use PhpAsyncSerializable;
use function React\Promise\reject;
use function React\Promise\resolve;

class IdealisticOfficePromptVoiceReplySetting implements PhpAsyncSerializable
{

    public static function fields(IdealisticOfficePrompt $prompt): array
    {
        return array(
            "enabled_status" => new AIFieldObject(
                AIField::BOOLEAN,
                null,
                false,
                "Is the contact is attempting to enable the setting",
                null,
                IdealisticOfficeSettings::DEFAULTS[IdealisticOfficeSettings::SEND_VOICE_REPLY] ?? false
            )
        );
    }

    public static function set(IdealisticOfficeUser $user, bool $enabled): IdealisticOfficeOutcome
    {
        return $user->setSetting(IdealisticOfficeSettings::SEND_VOICE_REPLY, $enabled);
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
                    $prompt->expandInstructions(array(
                        "current_setting_value" => $prompt->getUser()->getSetting(IdealisticOfficeSettings::SEND_VOICE_REPLY)
                    ))
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
                        if ($verify->{"enabled_status"} == $prompt->getUser()->getSetting(IdealisticOfficeSettings::SEND_VOICE_REPLY)) {
                            return resolve(null);
                        }
                        $outcome = self::set($prompt->getUser(), $verify->{"enabled_status"});
                        return resolve($outcome->isPositiveOutcome() ? $outcome : null);
                    }
                });
            } catch (Throwable $e) {
                return reject($e);
            }
        });
    }

}
