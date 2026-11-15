<?php

use function React\Promise\reject;
use function React\Promise\resolve;

class IdealisticOfficePromptManageRoles implements PhpAsyncSerializable
{

    public static function description(IdealisticOfficePrompt $prompt): ?string
    {
        return "[SECONDARY FEATURE] Set up roles to speed up the organization of your contacts instead of working on an individual level with each one.";
    }

    public static function fields(IdealisticOfficePrompt $prompt): array
    {
        $array = array(
            "0_new_roles" => IdealisticOfficePromptFields::nullableStringArray(
                "The names of the new roles to add to the team"
            ),


            "2_roles_to_restore" => IdealisticOfficePromptFields::nullableStringArray(
                "The IDs/names of the roles to restore to the team"
            ),
        );

        if (!empty($prompt->getCache()->getRoles())) {
            $array["1_roles_to_remove"] = IdealisticOfficePromptFields::nullableStringArray(
                "The IDs/names of the roles to remove from the team"
            );

            $array["5_role_to_rename"] = IdealisticOfficePromptFields::nullableString(
                "The ID/name of the role to rename"
            );
            $array["5_new_role_name"] = IdealisticOfficePromptFields::nullableString(
                "The new name for the role"
            );
            $array["5_rename_role_optional_justification"] = IdealisticOfficePromptFields::nullableString(
                "An optional justification for renaming the role"
            );
        }

        if (sizeof($prompt->getCache()->getUsers()) > 1
            && !empty($prompt->getCache()->getRoles())) {
            $array["3_contacts_to_add_role_to"] = IdealisticOfficePromptFields::nullableIntegerArray(
                "The IDs of the contacts to add a role to"
            );
            $array["3_role_to_add"] = IdealisticOfficePromptFields::nullableString(
                "The ID/name of the role to add to the contacts (use name if a role ID could not be found)"
            );


            $array["4_contacts_to_remove_role_from"] = IdealisticOfficePromptFields::nullableIntegerArray(
                "The IDs of the contacts to remove a role from"
            );
            $array["4_role_to_remove"] = IdealisticOfficePromptFields::nullableString(
                "The ID/name of the role to remove from the contacts"
            );
        }
        return $array;
    }

    public static function add(IdealisticOfficeUser $user, string $name): IdealisticOfficeOutcome
    {
        return $user->getRoleManager()->addRole(trim($name));
    }

    public static function remove(IdealisticOfficeUser $user, IdealisticOfficeRole $role, ?string $justification = null): IdealisticOfficeOutcome
    {
        return $user->getRoleManager()->removeRole($role, empty($justification) ? null : trim($justification));
    }

    public static function restore(IdealisticOfficeUser $user, IdealisticOfficeRole $role): IdealisticOfficeOutcome
    {
        return $user->getRoleManager()->restoreRole($role);
    }

    public static function rename(IdealisticOfficeUser $user, IdealisticOfficeRole $role, string $name, ?string $justification = null): IdealisticOfficeOutcome
    {
        return $user->getRoleManager()->renameRole($role, trim($name), empty($justification) ? null : trim($justification));
    }

    public static function assign(IdealisticOfficeUser $user, IdealisticOfficeUser $member, IdealisticOfficeRole $role, bool $assigned, ?string $justification = null): IdealisticOfficeOutcome
    {
        return $assigned
            ? $user->getRoleManager()->setUserRole($member, $role, true, empty($justification) ? null : trim($justification))
            : $user->getRoleManager()->setUserRole($member, $role, false);
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

                        if (!empty($verify->{"0_new_roles"})) {
                            foreach ($verify->{"0_new_roles"} as $name) {
                                $outcome = self::add($prompt->getUser(), $name);

                                if ($outcome->isPositiveOutcome()) {
                                    $prompt->getCache()->clearRoles();
                                }
                                $outcomes[] = $outcome;
                            }
                        }
                        if (!empty($verify->{"1_roles_to_remove"})) {
                            foreach ($verify->{"1_roles_to_remove"} as $name) {
                                $role = $prompt->getTeam()->findRole(
                                    $name,
                                    false,
                                    false
                                );

                                if ($role instanceof IdealisticOfficeRole) {
                                    $outcome = self::remove(
                                        $prompt->getUser(),
                                        $role,
                                        $verify->{"1_remove_role_optional_justification"} ?? null
                                    );
                                } else {
                                    $outcome = new IdealisticOfficeOutcome(
                                        false,
                                        "No role found to remove with name '" . $name . "'.",
                                    );
                                }

                                if ($outcome->isPositiveOutcome()) {
                                    $prompt->getCache()->clearRoles();
                                }
                                $outcomes[] = $outcome;
                            }
                        }
                        if (!empty($verify->{"2_roles_to_restore"})) {
                            foreach ($verify->{"2_roles_to_restore"} as $name) {
                                $role = $prompt->getTeam()->findRemovedRole(
                                    $name,
                                    false
                                );

                                if ($role instanceof IdealisticOfficeRole) {
                                    $outcome = self::restore($prompt->getUser(), $role);
                                } else {
                                    $outcome = new IdealisticOfficeOutcome(
                                        false,
                                        "No removed role found to restore with name '" . $name . "'.",
                                    );
                                }

                                if ($outcome->isPositiveOutcome()) {
                                    $prompt->getCache()->clearRoles();
                                }
                                $outcomes[] = $outcome;
                            }
                        }
                        if (!empty($verify->{"3_contacts_to_add_role_to"})
                            && !empty($verify->{"3_role_to_add"})) {
                            foreach ($verify->{"3_contacts_to_add_role_to"} as $contactId) {
                                $user = $prompt->getCache()->getUser(trim($contactId));

                                if ($user !== null) {
                                    $reference = trim($verify->{"3_role_to_add"});
                                    $role = $prompt->getTeam()->findRole(
                                        $reference,
                                        false,
                                        false
                                    );

                                    if ($role instanceof IdealisticOfficeRole) {
                                        $outcome = self::assign($prompt->getUser(), $user, $role, true);

                                        if ($outcome->isPositiveOutcome()) {
                                            $prompt->getCache()->clearRoles();
                                        }
                                        $outcomes[] = $outcome;
                                    } else if (!is_numeric($reference)) {
                                        $outcome = self::add($prompt->getUser(), $reference);
                                        $outcomes[] = $outcome;

                                        if ($outcome->isPositiveOutcome()) {
                                            $prompt->getCache()->clearRoles();
                                            $role = $prompt->getTeam()->findRole(
                                                $reference,
                                                false,
                                                false
                                            );

                                            if ($role instanceof IdealisticOfficeRole) {
                                                $outcome = self::assign(
                                                    $prompt->getUser(),
                                                    $user,
                                                    $role,
                                                    true,
                                                    $verify->{"3_add_role_optional_justification"} ?? null
                                                );

                                                if ($outcome->isPositiveOutcome()) {
                                                    $prompt->getCache()->clearRoles();
                                                }
                                            } else {
                                                $outcome = new IdealisticOfficeOutcome(
                                                    false,
                                                    "No role found to add to a contact.",
                                                );
                                            }
                                            $outcomes[] = $outcome;
                                        }
                                    } else {
                                        $outcome = new IdealisticOfficeOutcome(
                                            false,
                                            "No role found to add to a contact.",
                                        );
                                        $outcomes[] = $outcome;
                                    }
                                } else {
                                    $outcome = new IdealisticOfficeOutcome(
                                        false,
                                        "No contact found to add role to.",
                                    );
                                    $outcomes[] = $outcome;
                                }
                            }
                        }
                        if (!empty($verify->{"4_contacts_to_remove_role_from"})
                            && !empty($verify->{"4_role_to_remove"})) {
                            foreach ($verify->{"4_contacts_to_remove_role_from"} as $contactId) {
                                $user = $prompt->getCache()->getUser(trim($contactId));

                                if ($user !== null) {
                                    $role = $prompt->getTeam()->findRole(
                                        $verify->{"4_role_to_remove"},
                                        false,
                                        false
                                    );

                                    if ($role instanceof IdealisticOfficeRole) {
                                        $outcome = self::assign($prompt->getUser(), $user, $role, false);

                                        if ($outcome->isPositiveOutcome()) {
                                            $prompt->getCache()->clearRoles();
                                        }
                                    } else {
                                        $outcome = new IdealisticOfficeOutcome(
                                            false,
                                            "No role found to remove from a contact.",
                                        );
                                    }
                                } else {
                                    $outcome = new IdealisticOfficeOutcome(
                                        false,
                                        "No contact found to remove role from.",
                                    );
                                }
                                $outcomes[] = $outcome;
                            }
                        }
                        if (!empty($verify->{"5_role_to_rename"})
                            && !empty($verify->{"5_new_role_name"})) {
                            $role = $prompt->getTeam()->findRole(
                                $verify->{"5_role_to_rename"},
                                false,
                                false
                            );

                            if ($role instanceof IdealisticOfficeRole) {
                                $outcome = self::rename(
                                    $prompt->getUser(),
                                    $role,
                                    $verify->{"5_new_role_name"},
                                    $verify->{"5_rename_role_optional_justification"} ?? null
                                );
                            } else {
                                $outcome = new IdealisticOfficeOutcome(
                                    false,
                                    "No role found to rename.",
                                );
                            }

                            if ($outcome->isPositiveOutcome()) {
                                $prompt->getCache()->clearRoles();
                            }
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
