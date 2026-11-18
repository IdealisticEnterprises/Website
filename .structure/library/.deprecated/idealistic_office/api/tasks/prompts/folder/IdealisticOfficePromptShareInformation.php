<?php

namespace idealistic_office\api\tasks\prompts\folder;

use AIObjectConstructor;
use idealistic_office\api\handlers\IdealisticOfficeReader;
use idealistic_office\api\IdealisticOfficeVariable;
use idealistic_office\api\objects\helpers\IdealisticOfficeAI;
use idealistic_office\api\objects\helpers\IdealisticOfficeOutcome;
use idealistic_office\api\objects\IdealisticOfficePrompt;
use IdealisticOfficePromptFields;
use IdealisticOfficePromptTask;
use PhpAsyncSerializable;
use function React\Promise\reject;
use function React\Promise\resolve;

class IdealisticOfficePromptShareInformation implements PhpAsyncSerializable
{

    private const ENABLE_AI = false;

    public static function isSharing(IdealisticOfficePrompt $prompt): bool
    {
        $words = $prompt->getCache()->getSeparatedWords();

        if (!empty($words)) {
            foreach ($words as $word) {
                if (str_starts_with($word, "@")
                    || str_starts_with($word, "#")) {
                    return true;
                }
            }
        }
        return false;
    }

    public static function description(IdealisticOfficePrompt $prompt): ?string
    {
        return "[SECONDARY FEATURE] Share/comment text (including uploaded attachments and mentioned links/URLs) via folders using the following strict pattern: "
            . "'@nickname' (or multiple nicknames) to share with users, '#foldername' or '#folderid' (or multiple folders) to share with folders and their automatically connected portals, and any and only following text will be shared as information. "
            . "Communication Example: '@john @george #projectX Please review the attached document.' will share the document with user 'john' and folder 'projectX'."
            . "Portal Example: '#portalName https://www.example.com' will add the contents of the mentioned website to the folder 'portalName' and as a result its identically named and connected portal.";
    }

    public static function fields(IdealisticOfficePrompt $prompt): array
    {
        return self::ENABLE_AI
            ? array(
                "0_contacts_to_share_with" => IdealisticOfficePromptFields::nullableIntegerArray(
                    "The IDs of the contacts to share the information with"
                ),
                "0_information_to_share" => IdealisticOfficePromptFields::nullableString(
                    "The information to share with the contacts"
                )
            )
            : array();
    }

    public static function run(
        IdealisticOfficePrompt $prompt
    ): void
    {
        $prompt->waitForTasks(array(
            IdealisticOfficePromptTask::MANAGE_FOLDERS
        ));
        $prompt->runTask(function () use ($prompt) {
            try {
                if (!$prompt->getCache()->isTaskEnabled(IdealisticOfficePromptTask::MANAGE_FOLDERS)) {
                    return resolve(null);
                }
                $object = new AIObjectConstructor(
                    $prompt->expandFields(self::fields($prompt)),
                    $prompt->expandInstructions()
                );
                $outcomes = array();
                $words = $prompt->getCache()->getSeparatedWords();

                if (!empty($words)) {
                    $usersToShare = array();
                    $foldersToShare = array();
                    $wordsToShare = array();
                    $collectWordsFunction = function ($word) use (&$wordsToShare, &$usersToShare, &$foldersToShare): void {
                        if (empty($usersToShare)
                            && empty($foldersToShare)) {
                            return;
                        }
                        $wordsToShare[] = $word;
                    };
                    $userFolderShareFunction = function (array $usersFound, string $info, ?array $ignoreFolders = null) use ($prompt, &$outcomes): void {
                        $usersFound[$prompt->getUser()->getId()] = $prompt->getUser();

                        if (sizeof($usersFound) < 2) {
                            $outcomes[] = new IdealisticOfficeOutcome(
                                false,
                                "You must share the information '" . $info . "' with at least one other user."
                            );
                            return;
                        }
                        $hash = IdealisticOfficeReader::hashUsers($usersFound);
                        $folder = $prompt->getTeam()->getFolder($hash, true, false);

                        if ($folder === null) {
                            $timezone = $prompt->getUser()->getTimezone(false);
                            $folder = IdealisticOfficePromptManageFolders::getNewFolder(
                                $prompt,
                                $hash,
                                $hash,
                                IdealisticOfficeReader::getCurrentDate($timezone),
                                $timezone
                            );
                            $outcome = $folder->open($prompt->getUser());
                            $outcomes[] = $outcome;

                            if ($outcome->isPositiveOutcome()) {
                                foreach ($usersFound as $user) {
                                    if ($user->getId() === $prompt->getUser()->getId()) {
                                        continue;
                                    }
                                    $outcome = $folder->addUser(
                                        $prompt->getUser(),
                                        $user,
                                        true,
                                        null,
                                        false
                                    );

                                    if (!$outcome->isPositiveOutcome()) {
                                        $outcomes[] = $outcome;
                                        $folder->remove();
                                        $folder = null;
                                        break;
                                    }
                                }
                            } else {
                                $folder = null;
                            }
                        } else if (!empty($ignoreFolders)
                            && isset($ignoreFolders[$folder->getId()])) {
                            $folder = null;
                        }

                        if ($folder !== null) {
                            $outcomes[] = $folder->comment(
                                $prompt,
                                $info,
                                true
                            );
                        }
                    };
                    $finishSharingFunction = function () use ($prompt, &$usersToShare, &$foldersToShare, &$wordsToShare, &$outcomes, $userFolderShareFunction): void {
                        if (!empty($wordsToShare)) {
                            if (!empty($foldersToShare)) {
                                foreach ($foldersToShare as $folder) {
                                    $outcomes[] = $folder->comment(
                                        $prompt,
                                        implode(" ", $wordsToShare),
                                        true
                                    );
                                }
                                if (!empty($usersToShare)) {
                                    $userFolderShareFunction($usersToShare, implode(" ", $wordsToShare), $foldersToShare);
                                    $usersToShare = array();
                                }
                                $foldersToShare = array();
                            } else if (!empty($usersToShare)) {
                                $userFolderShareFunction($usersToShare, implode(" ", $wordsToShare));
                                $usersToShare = array();
                            }
                            $wordsToShare = array();
                        }
                    };

                    foreach ($words as $word) {
                        if (str_starts_with($word, "@")) {
                            $nickname = str_replace(".", "",
                                str_replace(",", "",
                                    substr($word, 1)));
                            $user = $prompt->getUser()->getUserByNickname($nickname);

                            if ($user !== null) {
                                $finishSharingFunction();
                                $usersToShare[$user->getId()] = $user;
                            } else {
                                $outcomes[] = new IdealisticOfficeOutcome(
                                    false,
                                    "User with nickname '" . $nickname . "' was not found in order to share information with."
                                );
                            }
                        } else if (str_starts_with($word, "#")) {
                            $folderName = str_replace(".", "",
                                str_replace(",", "",
                                    substr($word, 1)));
                            $folder = $prompt->getTeam()->getFolder($folderName, false, false);

                            if ($folder !== null) {
                                $finishSharingFunction();
                                $foldersToShare[$folder->getId()] = $folder;
                            } else {
                                $collectWordsFunction($word);
                            }
                        } else {
                            $collectWordsFunction($word);
                        }
                    }
                    $finishSharingFunction();
                }

                if (!empty($outcomes)) {
                    return resolve($outcomes);
                }

                if (!self::ENABLE_AI) {
                    return resolve(null);
                }
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
                )->then(function ($result) use ($prompt, $object, $outcomes, $userFolderShareFunction) {
                    $verify = $prompt->readAIObject($object, $result);

                    if ($verify === null) {
                        return resolve(null);
                    } else {
                        if (!empty($verify->{"0_contacts_to_share_with"})
                            && !empty($verify->{"0_information_to_share"})) {
                            $info = trim($verify->{"0_information_to_share"});

                            if (!empty($info)
                                || $prompt->getHistory()->hasAttachments()
                                || $prompt->getHistory()->hasLinks()) {
                                $usersFound = array();

                                foreach ($verify->{"0_contacts_to_share_with"} as $userId) {
                                    $user = $prompt->getCache()->getUser($userId);

                                    if ($user !== null) {
                                        if ($user->getId() !== $prompt->getUser()->getId()) {
                                            $usersFound[$user->getId()] = $user;
                                        }
                                    } else {
                                        $outcomes[] = new IdealisticOfficeOutcome(
                                            false,
                                            "User with reference ID '" . $userId . "' was not found in order to share information with."
                                        );
                                        $usersFound = array();
                                        break;
                                    }
                                }

                                if (!empty($usersFound)) {
                                    $userFolderShareFunction($usersFound, $info);
                                }
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
