<?php

namespace idealistic_office\api\tasks\prompts\attachment;

use idealistic_office\api\handlers\IdealisticOfficeError;
use idealistic_office\api\objects\IdealisticOfficePrompt;
use IdealisticOfficePromptTask;
use IdealisticOfficeTable;
use PhpAsyncSerializable;
use function React\Promise\all;
use function React\Promise\reject;
use function React\Promise\resolve;

class IdealisticOfficePromptAttachmentNames implements PhpAsyncSerializable
{

    public static function description(IdealisticOfficePrompt $prompt): ?string
    {
        return "[SECONDARY FEATURE] Any uploaded attachment will be properly renamed in the system's files to be easily accessible for future reference.";
    }

    public static function run(
        IdealisticOfficePrompt $prompt
    ): void
    {
        $prompt->waitForTasks(array(
            IdealisticOfficePromptTask::ATTACHMENT_ANALYSIS
        ));
        $prompt->runTask(function () use ($prompt) {
            try {
                $attachments = $prompt->getCache()->getAttachments();

                if (empty($attachments)) {
                    return resolve(null);
                } else {
                    $promises = array();

                    foreach ($attachments as $attachment) {
                        $description = $attachment->getAnalyzedDescription() ?? $attachment->getUserPrompt();

                        if (empty($description)) {
                            continue;
                        }
                        $oldName = $attachment->getName();
                        $promises[] = $attachment->findName(
                            $prompt,
                            $prompt->getTeam()
                        )->then(
                            function (mixed $result)
                            use ($prompt, $attachment, $oldName) {
                                if ($result === null) {
                                    $prompt->notify(
                                        "Failed to find name for contact uploaded attachment with name '" . $attachment->getName() . "'."
                                    );
                                } else {
                                    $newName = $attachment->getName();

                                    if ($oldName !== $newName) {
                                        IdealisticOfficeError::setSqlQuery(
                                            $prompt->getTeam(),
                                            $prompt->getUser(),
                                            IdealisticOfficeTable::HISTORY_USER_ATTACHMENTS_TABLE,
                                            array(
                                                "attachment_name" => $newName
                                            ),
                                            array(
                                                array("id", $attachment->getId())
                                            ),
                                            null,
                                            1
                                        );
                                    }
                                }
                                return resolve(null);
                            }
                        );
                    }
                    return all($promises)->then(
                        function () use ($prompt, &$information) {
                            return resolve(null);
                        },
                        function (Throwable $e) {
                            return reject($e);
                        }
                    );
                }
            } catch (Throwable $e) {
                return reject($e);
            }
        },
            null);
    }

}
