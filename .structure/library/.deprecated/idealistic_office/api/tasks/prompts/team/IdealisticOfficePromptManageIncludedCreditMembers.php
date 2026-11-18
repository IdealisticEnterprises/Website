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

class IdealisticOfficePromptManageIncludedCreditMembers implements PhpAsyncSerializable
{

    public static function description(IdealisticOfficePrompt $prompt): ?string
    {
        return "[SECONDARY FEATURE] Add contacts to your subscription so they use yours if they don't have one or their one runs out. (Must have set email address to your account to be able to use this feature.)";
    }

    public static function fields(IdealisticOfficePrompt $prompt): array
    {
        return array(
            "0_contact_to_include_to_team_credit_usage" => IdealisticOfficePromptFields::nullableInteger(
                "The ID of the contact to include to team credit usage, allowing them to use the team's credits for their tasks"
            ),

            "1_contact_to_remove_from_team_credit_usage" => IdealisticOfficePromptFields::nullableInteger(
                "The ID of the contact to remove from team credit usage, preventing them from using the team's credits for their tasks"
            )
        );
    }

    public static function set(IdealisticOfficeUser $user, IdealisticOfficeUser $member, bool $included): IdealisticOfficeOutcome
    {
        return $user->setIncludedToTeamCredits($member, $included);
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
                if (sizeof($prompt->getCache()->getUsers()) <= 1
                    || IdealisticOfficePromptShareInformation::isSharing($prompt)
                    || !$prompt->getUser()->hasEmail()) {
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

                        if (!empty($verify->{"0_contact_to_include_to_team_credit_usage"})) {
                            $user = $prompt->getCache()->getUser($verify->{"0_contact_to_include_to_team_credit_usage"});

                            if ($user !== null) {
                                $outcomes[] = self::set($prompt->getUser(), $user, true);
                            } else {
                                $outcomes[] = "User with reference '" . $verify->{"0_contact_to_include_to_team_credit_usage"} . "' not found to include to team's credit usage.";
                            }
                        }
                        if (!empty($verify->{"1_contact_to_remove_from_team_credit_usage"})) {
                            $user = $prompt->getCache()->getUser($verify->{"1_contact_to_remove_from_team_credit_usage"});

                            if ($user !== null) {
                                $outcomes[] = self::set($prompt->getUser(), $user, false);
                            } else {
                                $outcomes[] = "User with reference '" . $verify->{"1_contact_to_remove_from_team_credit_usage"} . "' not found to remove from team's credit usage.";
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
