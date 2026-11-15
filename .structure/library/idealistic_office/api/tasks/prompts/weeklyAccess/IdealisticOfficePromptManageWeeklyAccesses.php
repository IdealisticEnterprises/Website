<?php

use function React\Promise\reject;
use function React\Promise\resolve;

class IdealisticOfficePromptManageWeeklyAccesses implements PhpAsyncSerializable
{

    public static function description(IdealisticOfficePrompt $prompt): ?string
    {
        return "[SECONDARY FEATURE] Prevent any contact from accessing any participated folders the day of the week you don't desire.";
    }

    public static function fields(IdealisticOfficePrompt $prompt): array
    {
        $array = array(
            "2_team_monday_access" => IdealisticOfficePromptFields::nullableBoolean("Monday access"),
            "2_team_tuesday_access" => IdealisticOfficePromptFields::nullableBoolean("Tuesday access"),
            "2_team_wednesday_access" => IdealisticOfficePromptFields::nullableBoolean("Wednesday access"),
            "2_team_thursday_access" => IdealisticOfficePromptFields::nullableBoolean("Thursday access"),
            "2_team_friday_access" => IdealisticOfficePromptFields::nullableBoolean("Friday access"),
            "2_team_saturday_access" => IdealisticOfficePromptFields::nullableBoolean("Saturday access"),
            "2_team_sunday_access" => IdealisticOfficePromptFields::nullableBoolean("Sunday access"),
            "2_team_optional_justification" => IdealisticOfficePromptFields::nullableString(
                "An optional justification for setting the weekly access for the team"
            )
        );

        if ($prompt->getCache()->isTaskEnabled(IdealisticOfficePromptTask::MANAGE_ROLES)
            && !empty($prompt->getCache()->getRoles())) {
            $array["0_role_to_set_access"] = IdealisticOfficePromptFields::nullableString(
                "The ID/name of the role to set weekly access for. Only provide this if you want to set weekly access for a role"
            );
            $array["0_role_monday_access"] = IdealisticOfficePromptFields::nullableBoolean(
                "Monday access"
            );
            $array["0_role_tuesday_access"] = IdealisticOfficePromptFields::nullableBoolean(
                "Tuesday access"
            );
            $array["0_role_wednesday_access"] = IdealisticOfficePromptFields::nullableBoolean(
                "Wednesday access"
            );
            $array["0_role_thursday_access"] = IdealisticOfficePromptFields::nullableBoolean(
                "Thursday access"
            );
            $array["0_role_friday_access"] = IdealisticOfficePromptFields::nullableBoolean(
                "Friday access"
            );
            $array["0_role_saturday_access"] = IdealisticOfficePromptFields::nullableBoolean(
                "Saturday access"
            );
            $array["0_role_sunday_access"] = IdealisticOfficePromptFields::nullableBoolean(
                "Sunday access"
            );
            $array["0_role_optional_justification"] = IdealisticOfficePromptFields::nullableString(
                "An optional justification for setting the weekly access for the role"
            );
        }

        if (sizeof($prompt->getCache()->getUsers()) > 1) {
            $array["1_contact_to_set_access"] = IdealisticOfficePromptFields::nullableInteger(
                "The ID of the contact to set weekly access for"
            );
            $array["1_contact_monday_access"] = IdealisticOfficePromptFields::nullableBoolean("Monday access");
            $array["1_contact_tuesday_access"] = IdealisticOfficePromptFields::nullableBoolean("Tuesday access");
            $array["1_contact_wednesday_access"] = IdealisticOfficePromptFields::nullableBoolean("Wednesday access");
            $array["1_contact_thursday_access"] = IdealisticOfficePromptFields::nullableBoolean("Thursday access");
            $array["1_contact_friday_access"] = IdealisticOfficePromptFields::nullableBoolean("Friday access");
            $array["1_contact_saturday_access"] = IdealisticOfficePromptFields::nullableBoolean("Saturday access");
            $array["1_contact_sunday_access"] = IdealisticOfficePromptFields::nullableBoolean("Sunday access");
            $array["1_contact_optional_justification"] = IdealisticOfficePromptFields::nullableString(
                "An optional justification for setting the weekly access for the contact"
            );
        }

        return $array;
    }

    public static function setTeam(IdealisticOfficeUser $user, array $days, ?string $justification = null): IdealisticOfficeOutcome
    {
        return $user->getWeekAccessManager()->setTeamWeekAccess(
            $days[0] ?? null, $days[1] ?? null, $days[2] ?? null, $days[3] ?? null, $days[4] ?? null, $days[5] ?? null, $days[6] ?? null,
            empty($justification) ? null : trim($justification)
        );
    }

    public static function setRole(IdealisticOfficeUser $user, IdealisticOfficeRole $role, array $days, ?string $justification = null): IdealisticOfficeOutcome
    {
        return $user->getWeekAccessManager()->setRoleWeekAccess(
            $role,
            $days[0] ?? null, $days[1] ?? null, $days[2] ?? null, $days[3] ?? null, $days[4] ?? null, $days[5] ?? null, $days[6] ?? null,
            empty($justification) ? null : trim($justification)
        );
    }

    public static function setUser(IdealisticOfficeUser $user, IdealisticOfficeUser $member, array $days, ?string $justification = null): IdealisticOfficeOutcome
    {
        return $user->getWeekAccessManager()->setUserWeekAccess(
            $member,
            $days[0] ?? null, $days[1] ?? null, $days[2] ?? null, $days[3] ?? null, $days[4] ?? null, $days[5] ?? null, $days[6] ?? null,
            empty($justification) ? null : trim($justification)
        );
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

                        if (!empty($verify->{"0_role_to_set_access"})) {
                            $role = $prompt->getTeam()->findRole($verify->{"0_role_to_set_access"}, false, false);

                            if ($role instanceof IdealisticOfficeRole) {
                                $outcome = self::setRole(
                                    $prompt->getUser(),
                                    $role,
                                    array(
                                    $verify->{"0_role_monday_access"} ?? null,
                                    $verify->{"0_role_tuesday_access"} ?? null,
                                    $verify->{"0_role_wednesday_access"} ?? null,
                                    $verify->{"0_role_thursday_access"} ?? null,
                                    $verify->{"0_role_friday_access"} ?? null,
                                    $verify->{"0_role_saturday_access"} ?? null,
                                    $verify->{"0_role_sunday_access"} ?? null
                                ),
                                    $verify->{"0_role_optional_justification"} ?? null
                                );
                            } else {
                                $outcome = new IdealisticOfficeOutcome(
                                    false,
                                    "No role found to change weekly access for."
                                );
                            }
                            $outcomes[] = $outcome;
                        }
                        if (!empty($verify->{"1_contact_to_set_access"})) {
                            $user = $prompt->getCache()->getUser($verify->{"1_contact_to_set_access"});

                            if ($user !== null) {
                                $outcome = self::setUser(
                                    $prompt->getUser(),
                                    $user,
                                    array(
                                    $verify->{"1_contact_monday_access"} ?? null,
                                    $verify->{"1_contact_tuesday_access"} ?? null,
                                    $verify->{"1_contact_wednesday_access"} ?? null,
                                    $verify->{"1_contact_thursday_access"} ?? null,
                                    $verify->{"1_contact_friday_access"} ?? null,
                                    $verify->{"1_contact_saturday_access"} ?? null,
                                    $verify->{"1_contact_sunday_access"} ?? null
                                ),
                                    $verify->{"1_contact_optional_justification"} ?? null
                                );
                            } else {
                                $outcome = new IdealisticOfficeOutcome(
                                    false,
                                    "No contact found to change weekly access for."
                                );
                            }
                            $outcomes[] = $outcome;
                        }
                        if (!empty($verify->{"2_team_monday_access"})
                            || !empty($verify->{"2_team_tuesday_access"})
                            || !empty($verify->{"2_team_wednesday_access"})
                            || !empty($verify->{"2_team_thursday_access"})
                            || !empty($verify->{"2_team_friday_access"})
                            || !empty($verify->{"2_team_saturday_access"})
                            || !empty($verify->{"2_team_sunday_access"})
                        ) {
                            $outcomes[] = self::setTeam(
                                $prompt->getUser(),
                                array(
                                    $verify->{"2_team_monday_access"} ?? null,
                                    $verify->{"2_team_tuesday_access"} ?? null,
                                    $verify->{"2_team_wednesday_access"} ?? null,
                                    $verify->{"2_team_thursday_access"} ?? null,
                                    $verify->{"2_team_friday_access"} ?? null,
                                    $verify->{"2_team_saturday_access"} ?? null,
                                    $verify->{"2_team_sunday_access"} ?? null
                                ),
                                $verify->{"2_team_optional_justification"} ?? null
                            );
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
