<?php

use function React\Promise\reject;
use function React\Promise\resolve;

class IdealisticOfficePromptUserLanguageSelection implements PhpAsyncSerializable
{

    public static function description(IdealisticOfficePrompt $prompt): ?string
    {
        return "[SECONDARY FEATURE] The software adapts automatically to your language preference by simply analyzing what language you use the most in your prompt.";
    }

    public static function set(IdealisticOfficeUser $user, string $language): IdealisticOfficeOutcome
    {
        return $user->setUserLanguageSelection(trim($language));
    }

    public static function run(
        IdealisticOfficePrompt $prompt
    ): void
    {
        $prompt->waitForTasks(array(IdealisticOfficePromptTask::ACCESS_CHECK));
        $prompt->runTask(function () use ($prompt) {
            try {
                $object = new AIObjectConstructor(
                    $prompt->expandFields(array(
                        "language" => new AIFieldObject(
                            AIField::STRING,
                            null,
                            true,
                            "The language used or selected, written in Latin characters",
                        )
                    )),
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
                $AI->adjustCertainty($arguments);
                return $AI->getReactResult(
                    $arguments
                )->then(function ($result) use ($prompt, $object) {
                    $verify = $prompt->readAIObject($object, $result);

                    if (!empty($verify?->language)) {
                        $outcome = self::set($prompt->getUser(), $verify->language);
                        return resolve($outcome->isPositiveOutcome() ? $outcome : null);
                    } else {
                        return resolve(null);
                    }
                });
            } catch (Throwable $e) {
                return reject($e);
            }
        });
    }

}
