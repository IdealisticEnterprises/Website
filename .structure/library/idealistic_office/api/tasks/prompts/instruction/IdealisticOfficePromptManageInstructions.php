<?php

use function React\Promise\reject;
use function React\Promise\resolve;

class IdealisticOfficePromptManageInstructions implements PhpAsyncSerializable
{

    public static function description(IdealisticOfficePrompt $prompt): ?string
    {
        return "[SECONDARY FEATURE] Instructions change the behavior of the AI towards you (not portals) based on your rules.";
    }

    public static function fields(IdealisticOfficePrompt $prompt): array
    {
        return array(
            "0_new_instruction" => IdealisticOfficePromptFields::nullableString(
                "The new instruction to add. ONLY populate this field if the user explicitly asks to create or add a new instruction. Otherwise, you MUST return null."
            ),

            "1_instructions_to_remove" => IdealisticOfficePromptFields::nullableIntegerArray(
                "The IDs of the instructions to remove. ONLY populate this field if the user explicitly asks to remove instructions. Otherwise, return null."
            ),
            "1_instruction_removal_optional_justification" => IdealisticOfficePromptFields::nullableString(
                "An optional justification for removing the instructions. Leave null if no instructions are being removed."
            )
        );
    }

    public static function add(IdealisticOfficeUser $user, string $instruction): IdealisticOfficeOutcome
    {
        return $user->getInstructionsManager()->addTeamInstruction(trim($instruction));
    }

    public static function remove(IdealisticOfficeUser $user, int $id, ?string $justification = null): IdealisticOfficeOutcome
    {
        return $user->getInstructionsManager()->removeTeamInstruction(
            $id,
            empty($justification) ? null : trim($justification)
        );
    }

    public static function run(
        IdealisticOfficePrompt $prompt
    ): void
    {
        $prompt->waitForTasks(array(
            IdealisticOfficePromptTask::TEAM_INFO
        ));
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
                        $outcomes = array();

                        if (!empty($verify->{"0_new_instruction"})) {
                            $outcomes[] = self::add($prompt->getUser(), $verify->{"0_new_instruction"});
                        }
                        if (!empty($verify->{"1_instructions_to_remove"})) {
                            foreach ($verify->{"1_instructions_to_remove"} as $id) {
                                $outcomes[] = self::remove(
                                    $prompt->getUser(),
                                    $id,
                                    $verify->{"1_instruction_removal_optional_justification"} ?? null
                                );
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
