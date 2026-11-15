<?php

use function React\Promise\all;
use function React\Promise\reject;
use function React\Promise\resolve;

class IdealisticOfficePromptAttachmentAnalysis implements PhpAsyncSerializable
{

    public static function description(IdealisticOfficePrompt $prompt): ?string
    {
        return "[SECONDARY FEATURE] Any uploaded attachment will be analyzed by building a description of the content of the attachment.";
    }

    public static function run(
        IdealisticOfficePrompt $prompt
    ): void
    {
        $prompt->waitForTasks(array(
            IdealisticOfficePromptTask::ACCESS_CHECK
        ));
        $prompt->runTask(function () use ($prompt) {
            try {
                $attachments = $prompt->getCache()->getAttachments();

                if (empty($attachments)) {
                    return resolve(null);
                } else {
                    $promises = array();
                    $information = array();
                    $first = true;

                    foreach ($attachments as $attachment) {
                        if ($attachment->canBeAudio()) {
                            if ($first) {
                                $first = false;
                                continue;
                            }
                        }
                        $promises[] = $attachment->analyzeDescription(
                            $prompt,
                            $prompt->getTeam()
                        )->then(
                            function (mixed $result)
                            use ($prompt, $attachment, &$information) {
                                if ($result === null) {
                                    $prompt->notify(
                                        "Failed to analyze the contact uploaded attachment with name '" . $attachment->getName() . "'."
                                    );
                                    IdealisticOfficeError::deleteSqlQuery(
                                        $prompt->getTeam(),
                                        $prompt->getUser(),
                                        IdealisticOfficeTable::HISTORY_USER_ATTACHMENTS_TABLE,
                                        array(
                                            array("id", $attachment->getId())
                                        ),
                                        null,
                                        1
                                    );
                                } else {
                                    $analyzedDescription = $attachment->getAnalyzedDescription();

                                    if ($analyzedDescription !== null) {
                                        $information[] = $analyzedDescription;
                                        IdealisticOfficeError::setSqlQuery(
                                            $prompt->getTeam(),
                                            $prompt->getUser(),
                                            IdealisticOfficeTable::HISTORY_USER_ATTACHMENTS_TABLE,
                                            array(
                                                "analyzed_description" => $analyzedDescription
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

                        if (!$prompt->getCache()->isSubscriber()) {
                            break;
                        }
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
