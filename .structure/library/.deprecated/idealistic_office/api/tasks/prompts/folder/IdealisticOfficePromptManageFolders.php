<?php

namespace idealistic_office\api\tasks\prompts\folder;

use AIObjectConstructor;
use idealistic_office\api\IdealisticOfficeVariable;
use idealistic_office\api\objects\helpers\AbstractIdealisticOfficeTeam;
use idealistic_office\api\objects\helpers\IdealisticOfficeAI;
use idealistic_office\api\objects\helpers\IdealisticOfficeOutcome;
use idealistic_office\api\objects\helpers\IdealisticOfficeTimezone;
use idealistic_office\api\objects\IdealisticOfficeFolder;
use idealistic_office\api\objects\IdealisticOfficePrompt;
use idealistic_office\api\objects\IdealisticOfficeUser;
use IdealisticOfficePromptFields;
use IdealisticOfficePromptTask;
use PhpAsyncSerializable;
use function React\Promise\reject;
use function React\Promise\resolve;

class IdealisticOfficePromptManageFolders implements PhpAsyncSerializable
{

    public static function fields(IdealisticOfficePrompt $prompt): array
    {
        $array = array(
            "0_folder_to_remove_comment_from" => IdealisticOfficePromptFields::nullableString(
                "An optional ID/name of the folder to remove a comment from, use the selected folder's ID if not defined"
            ),
            "0_comments_to_remove" => IdealisticOfficePromptFields::nullableIntegerArray(
                "The IDs of the comments to remove from the selected folder"
            ),


            "3_folder_to_refresh_links" => IdealisticOfficePromptFields::nullableString(
                "An optional ID/name of the folder to refresh links for, use the selected folder's ID if not defined"
            )
        );

        if (sizeof($prompt->getCache()->getUsers()) > 1) {
            $array["1_folder_to_add_participant_to"] = IdealisticOfficePromptFields::nullableString(
                "An optional ID/name of the folder to add a participant to, use the selected folder's ID if not defined"
            );
            $array["1_participants_to_add"] = IdealisticOfficePromptFields::nullableIntegerArray(
                "The IDs of the contacts to add as participants to the selected folder"
            );


            $array["2_folder_to_remove_participant_from"] = IdealisticOfficePromptFields::nullableString(
                "An optional ID/name of the folder to remove a participant from, use the selected folder's ID if not defined"
            );
            $array["2_participants_to_remove"] = IdealisticOfficePromptFields::nullableIntegerArray(
                "The IDs of the contacts to remove as participants from the selected folder"
            );
        }

        return $array;
    }

    public static function newFolder(
        AbstractIdealisticOfficeTeam $team,
        IdealisticOfficeUser         $user,
        string                       $name,
        int|string|null              $customId,
        string                       $date,
        IdealisticOfficeTimezone     $timezone
    ): IdealisticOfficeFolder
    {
        return new IdealisticOfficeFolder(
            $team,
            0,
            $customId,
            $name,
            $user->getAccount(),
            null,
            $date,
            $timezone->getTimeZone(),
            null,
            null,
            null,
            $user->getAccount(),
            $date,
            $timezone->getTimeZone(),
            false
        );
    }

    public static function getNewFolder(
        IdealisticOfficePrompt   $prompt,
        string                   $name,
        int|string|null          $customId,
        string                   $date,
        IdealisticOfficeTimezone $timezone
    ): IdealisticOfficeFolder
    {
        return self::newFolder($prompt->getTeam(), $prompt->getUser(), $name, $customId, $date, $timezone);
    }

    public static function addParticipant(IdealisticOfficeUser $user, IdealisticOfficeFolder $folder, IdealisticOfficeUser $member): IdealisticOfficeOutcome
    {
        return $folder->addUser($user, $member);
    }

    public static function removeParticipant(IdealisticOfficeUser $user, IdealisticOfficeFolder $folder, IdealisticOfficeUser $member): IdealisticOfficeOutcome
    {
        return $folder->removeUser($user, $member);
    }

    public static function removeComment(IdealisticOfficeUser $user, IdealisticOfficeFolder $folder, int $id): IdealisticOfficeOutcome
    {
        return $folder->removeComment($user, $id);
    }

    public static function refreshLinks(IdealisticOfficeUser $user, IdealisticOfficeFolder $folder): array
    {
        $outcomes = array($folder->refreshLinks($user));
        $portals = $folder->getAttachedPortals(false);

        if (!empty($portals)) {
            foreach ($portals as $attached) {
                $portal = $attached->getPortal(false);

                if ($portal !== null
                    && $portal->hasSeparateLinks()) {
                    $outcomes[] = $portal->refreshLinks($user);
                }
            }
        }
        return $outcomes;
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
                if (IdealisticOfficePromptShareInformation::isSharing($prompt)
                    || empty($prompt->getCache()->getFolders())) {
                    return resolve(null);
                }
                $selectedFolder = $prompt->getCache()->getSelectedFolder();
                $object = new AIObjectConstructor(
                    $prompt->expandFields(self::fields($prompt)),
                    $prompt->expandInstructions(array(
                        "selected_folder_id" => $selectedFolder?->getId()
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
                )->then(function ($result) use ($prompt, $object, $selectedFolder) {
                    $verify = $prompt->readAIObject($object, $result);

                    if ($verify === null) {
                        return resolve(null);
                    } else {
                        $outcomes = array();

                        if (!empty($verify->{"0_comments_to_remove"})
                            && !empty($verify->{"0_folder_to_remove_comment_from"})) {
                            $folder = $prompt->getTeam()->getFolder($verify->{"0_folder_to_remove_comment_from"}, false, false)
                                ?? $selectedFolder;

                            if ($folder !== null) {
                                foreach ($verify->{"0_comments_to_remove"} as $id) {
                                    $outcome = self::removeComment($prompt->getUser(), $folder, $id);

                                    if ($outcome->isPositiveOutcome()) {
                                        $prompt->getCache()->clearFolders();
                                    }
                                    $outcomes[] = $outcome;
                                }
                            } else {
                                $outcomes[] = new IdealisticOfficeOutcome(
                                    false,
                                    "No folder has been selected to remove a comment from."
                                );
                            }
                        }
                        if (!empty($verify->{"1_participants_to_add"})
                            && !empty($verify->{"1_folder_to_add_participant_to"})) {
                            $folder = $prompt->getTeam()->getFolder($verify->{"1_folder_to_add_participant_to"}, false, false)
                                ?? $selectedFolder;

                            if ($folder !== null) {
                                foreach ($verify->{"1_participants_to_add"} as $id) {
                                    $user = $prompt->getCache()->getUser(trim($id));

                                    if ($user !== null) {
                                        $outcome = self::addParticipant($prompt->getUser(), $folder, $user);

                                        if ($outcome->isPositiveOutcome()) {
                                            $prompt->getCache()->clearFolders();
                                        }
                                        $outcomes[] = $outcome;
                                    } else {
                                        $outcomes[] = new IdealisticOfficeOutcome(
                                            false,
                                            "Failed to find contact to add to folder with the reference: " . $id
                                        );
                                    }
                                }
                            } else {
                                $outcomes[] = new IdealisticOfficeOutcome(
                                    false,
                                    "No folder has been selected to add a participant to."
                                );
                            }
                        }
                        if (!empty($verify->{"2_participants_to_remove"})
                            && !empty($verify->{"2_folder_to_remove_participant_from"})) {
                            $folder = $prompt->getTeam()->getFolder($verify->{"2_folder_to_remove_participant_from"}, false, false)
                                ?? $selectedFolder;

                            if ($folder !== null) {
                                foreach ($verify->{"2_participants_to_remove"} as $id) {
                                    $user = $prompt->getCache()->getUser(trim($id));

                                    if ($user !== null) {
                                        $outcome = self::removeParticipant($prompt->getUser(), $folder, $user);

                                        if ($outcome->isPositiveOutcome()) {
                                            $prompt->getCache()->clearFolders();
                                        }
                                        $outcomes[] = $outcome;
                                    } else {
                                        $outcomes[] = new IdealisticOfficeOutcome(
                                            false,
                                            "Failed to find contact to remove from folder with the reference: " . $id
                                        );
                                    }
                                }
                            } else {
                                $outcomes[] = new IdealisticOfficeOutcome(
                                    false,
                                    "No folder has been selected to remove a participant from."
                                );
                            }
                        }
                        if (!empty($verify->{"3_folder_to_refresh_links"})) {
                            $folder = $prompt->getTeam()->getFolder($verify->{"3_folder_to_refresh_links"}, false, false)
                                ?? $selectedFolder;

                            if ($folder !== null) {
                                $refreshed = self::refreshLinks($prompt->getUser(), $folder);

                                if ($refreshed[0]->isPositiveOutcome()) {
                                    $prompt->getCache()->clearFolders();
                                }
                                foreach ($refreshed as $outcome) {
                                    $outcomes[] = $outcome;
                                }
                            } else {
                                $outcomes[] = new IdealisticOfficeOutcome(
                                    false,
                                    "No folder has been selected to refresh links for."
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
