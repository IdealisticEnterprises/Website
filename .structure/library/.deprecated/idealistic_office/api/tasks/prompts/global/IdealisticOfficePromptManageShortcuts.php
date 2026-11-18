<?php

namespace idealistic_office\api\tasks\prompts\global;

use AIObjectConstructor;
use idealistic_office\api\IdealisticOfficeVariable;
use idealistic_office\api\objects\helpers\IdealisticOfficeAI;
use idealistic_office\api\objects\helpers\IdealisticOfficeOutcome;
use idealistic_office\api\objects\IdealisticOfficePrompt;
use idealistic_office\api\objects\IdealisticOfficeUser;
use IdealisticOfficePromptFields;
use IdealisticOfficePromptTask;
use PhpAsyncSerializable;
use function React\Promise\reject;
use function React\Promise\resolve;

class IdealisticOfficePromptManageShortcuts implements PhpAsyncSerializable
{

    public static function description(IdealisticOfficePrompt $prompt): ?string
    {
        return "[SECONDARY FEATURE] Manage shortcuts that will be replaced with larger words or phrases to save you valuable time.";
    }

    public static function fields(IdealisticOfficePrompt $prompt): array
    {
        return array(
            "0_shortcut_word" => IdealisticOfficePromptFields::nullableString(
                "The word or phrase that should trigger the shortcut"
            ),
            "0_shortcut_replacement" => IdealisticOfficePromptFields::nullableString(
                "The text that should replace the shortcut word or phrase when the shortcut is triggered"
            ),

            "1_shortcuts_to_remove" => IdealisticOfficePromptFields::nullableIntegerArray(
                "The IDs of the shortcuts to remove"
            )
        );
    }

    public static function add(IdealisticOfficeUser $user, string $word, string $replacement): IdealisticOfficeOutcome
    {
        return $user->addShortcut($word, $replacement);
    }

    public static function remove(IdealisticOfficeUser $user, int $id): IdealisticOfficeOutcome
    {
        return $user->removeShortcut($id);
    }

    public static function run(
        IdealisticOfficePrompt $prompt
    ): void
    {
        $prompt->waitForTasks(array(IdealisticOfficePromptTask::TEAM_INFO));
        $prompt->runTask(function () use ($prompt) {
            try {
                $constructor = new AIObjectConstructor(
                    $prompt->expandFields(self::fields($prompt)),
                    $prompt->expandInstructions()
                );
                $arguments = array(
                    "messages" => array(
                        array(
                            "role" => "system",
                            "content" => $constructor->build()
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
                )->then(function ($result) use ($prompt, $constructor) {
                    $verify = $prompt->readAIObject($constructor, $result);

                    if ($verify === null) {
                        return resolve(null);
                    } else {
                        $outcomes = array();

                        if (!empty($verify->{"0_shortcut_word"})
                            && !empty($verify->{"0_shortcut_replacement"})) {
                            $outcomes[] = self::add(
                                $prompt->getUser(),
                                $verify->{"0_shortcut_word"},
                                $verify->{"0_shortcut_replacement"}
                            );
                        }
                        if (!empty($verify->{"1_shortcuts_to_remove"})) {
                            foreach ($verify->{"1_shortcuts_to_remove"} as $id) {
                                $outcomes[] = self::remove($prompt->getUser(), $id);
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
