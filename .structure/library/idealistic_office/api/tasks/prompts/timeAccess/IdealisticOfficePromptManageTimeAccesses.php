<?php

use function React\Promise\reject;
use function React\Promise\resolve;

class IdealisticOfficePromptManageTimeAccesses implements PhpAsyncSerializable
{

    public static function description(IdealisticOfficePrompt $prompt): ?string
    {
        return "[SECONDARY FEATURE] Prevent any contact from accessing any participated folders the time of the day you don't desire.";
    }

    public static function fields(IdealisticOfficePrompt $prompt): array
    {
        $array = array(
            "2_team_start_hour" => IdealisticOfficePromptFields::nullableInteger(
                "The 24-clock hour at which the time access modification starts"
            ),
            "2_team_start_minutes" => IdealisticOfficePromptFields::nullableInteger(
                "The start hour's minutes at which the time access modification starts"
            ),
            "2_team_end_hour" => IdealisticOfficePromptFields::nullableInteger(
                "The 24-clock hour at which the time access modification ends"
            ),
            "2_team_end_minutes" => IdealisticOfficePromptFields::nullableInteger(
                "The end hour's minutes at which the time access modification ends"
            ),
            "2_team_optional_justification" => IdealisticOfficePromptFields::nullableString(
                "An optional justification for the time access modification"
            )
        );

        if ($prompt->getCache()->isTaskEnabled(IdealisticOfficePromptTask::MANAGE_ROLES)
            && !empty($prompt->getCache()->getRoles())) {
            $array["0_role_to_set_time_access"] = IdealisticOfficePromptFields::nullableString(
                "The ID/name of the role to set time access for"
            );
            $array["0_role_start_hour"] = IdealisticOfficePromptFields::nullableInteger(
                "The 24-clock hour at which the time access modification starts"
            );
            $array["0_role_start_minutes"] = IdealisticOfficePromptFields::nullableInteger(
                "The start hour's minutes at which the time access modification starts"
            );
            $array["0_role_end_hour"] = IdealisticOfficePromptFields::nullableInteger(
                "The 24-clock hour at which the time access modification ends"
            );
            $array["0_role_end_minutes"] = IdealisticOfficePromptFields::nullableInteger(
                "The end hour's minutes at which the time access modification ends"
            );
            $array["0_role_optional_justification"] = IdealisticOfficePromptFields::nullableString(
                "An optional justification for the time access modification"
            );
        }

        if (sizeof($prompt->getCache()->getUsers()) > 1) {
            $array["1_contact_to_set_time_access"] = IdealisticOfficePromptFields::nullableInteger(
                "The ID of the contact to set time access for"
            );
            $array["1_contact_start_hour"] = IdealisticOfficePromptFields::nullableInteger(
                "The 24-clock hour at which the time access modification starts"
            );
            $array["1_contact_start_minutes"] = IdealisticOfficePromptFields::nullableInteger(
                "The start hour's minutes at which the time access modification starts"
            );
            $array["1_contact_end_hour"] = IdealisticOfficePromptFields::nullableInteger(
                "The 24-clock hour at which the time access modification ends"
            );
            $array["1_contact_end_minutes"] = IdealisticOfficePromptFields::nullableInteger(
                "The end hour's minutes at which the time access modification ends"
            );
            $array["1_contact_optional_justification"] = IdealisticOfficePromptFields::nullableString(
                "An optional justification for the time access modification"
            );
        }

        return $array;
    }

    public static function setTeam(IdealisticOfficeUser $user, int $startHour, int $startMinutes, int $endHour, int $endMinutes, ?string $justification = null): IdealisticOfficeOutcome
    {
        return $user->getTimeAccessManager()->setTeamTimeAccess($startHour, $startMinutes, $endHour, $endMinutes, empty($justification) ? null : trim($justification));
    }

    public static function setRole(IdealisticOfficeUser $user, IdealisticOfficeRole $role, int $startHour, int $startMinutes, int $endHour, int $endMinutes, ?string $justification = null): IdealisticOfficeOutcome
    {
        return $user->getTimeAccessManager()->setRoleTimeAccess($role, $startHour, $startMinutes, $endHour, $endMinutes, empty($justification) ? null : trim($justification));
    }

    public static function setUser(IdealisticOfficeUser $user, IdealisticOfficeUser $member, int $startHour, int $startMinutes, int $endHour, int $endMinutes, ?string $justification = null): IdealisticOfficeOutcome
    {
        return $user->getTimeAccessManager()->setUserTimeAccess($member, $startHour, $startMinutes, $endHour, $endMinutes, empty($justification) ? null : trim($justification));
    }

    public static function run(
        IdealisticOfficePrompt $prompt
    ): void
    {
        $prompt->waitForTasks(array(
            IdealisticOfficePromptTask::MANAGE_ROLES
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

                        if (!empty($verify->{"0_role_to_set_time_access"})
                            && !empty($verify->{"0_role_start_hour"})
                            && !empty($verify->{"0_role_end_hour"})) {
                            $role = $prompt->getTeam()->findRole($verify->{"0_role_to_set_time_access"}, false, false);

                            if ($role instanceof IdealisticOfficeRole) {
                                $outcome = self::setRole(
                                    $prompt->getUser(),
                                    $role,
                                    $verify->{"0_role_start_hour"},
                                    $verify->{"0_role_start_minutes"} ?? 0,
                                    $verify->{"0_role_end_hour"},
                                    $verify->{"0_role_end_minutes"} ?? 0,
                                    $verify->{"0_role_optional_justification"} ?? null
                                );
                            } else {
                                $outcome = new IdealisticOfficeOutcome(
                                    false,
                                    "No role found to change time access for."
                                );
                            }
                            $outcomes[] = $outcome;
                        }
                        if (!empty($verify->{"1_contact_to_set_time_access"})
                            && !empty($verify->{"1_contact_start_hour"})
                            && !empty($verify->{"1_contact_end_hour"})) {
                            $user = $prompt->getCache()->getUser($verify->{"1_contact_to_set_time_access"});

                            if ($user !== null) {
                                $outcome = self::setUser(
                                    $prompt->getUser(),
                                    $user,
                                    $verify->{"1_contact_start_hour"},
                                    $verify->{"1_contact_start_minutes"} ?? 0,
                                    $verify->{"1_contact_end_hour"},
                                    $verify->{"1_contact_end_minutes"} ?? 0,
                                    $verify->{"1_contact_optional_justification"} ?? null
                                );
                            } else {
                                $outcome = new IdealisticOfficeOutcome(
                                    false,
                                    "No contact found to change time access for."
                                );
                            }
                            $outcomes[] = $outcome;
                        }
                        if (!empty($verify->{"2_team_start_hour"})
                            && !empty($verify->{"2_team_end_hour"})) {
                            $outcome = self::setTeam(
                                $prompt->getUser(),
                                $verify->{"2_team_start_hour"},
                                $verify->{"2_team_start_minutes"} ?? 0,
                                $verify->{"2_team_end_hour"},
                                $verify->{"2_team_end_minutes"} ?? 0,
                                $verify->{"2_team_optional_justification"} ?? null
                            );
                            $outcomes[] = $outcome;
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
