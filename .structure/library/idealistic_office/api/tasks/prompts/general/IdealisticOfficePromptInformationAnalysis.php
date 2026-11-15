<?php

use function React\Promise\reject;
use function React\Promise\resolve;

class IdealisticOfficePromptInformationAnalysis implements PhpAsyncSerializable
{

    public static function description(IdealisticOfficePrompt $prompt): ?string
    {
        return "[SECONDARY FEATURE] Any sent message will be analyzed to build a profile about you and your contacts.";
    }

    public static function run(
        IdealisticOfficePrompt $prompt
    ): void
    {
        $prompt->waitForTasks(array(
            IdealisticOfficePromptTask::ATTACHMENT_ANALYSIS
        ));
        $prompt->runTask(
            function () use ($prompt) {
                try {
                    $promptPrompt = $prompt->getPrompt();
                    $hasTextPrompt = !empty($promptPrompt);

                    if ($hasTextPrompt) {
                        IdealisticOfficeError::sqlInsert(
                            $prompt->getTeam(),
                            $prompt->getUser(),
                            IdealisticOfficeTable::HISTORY_PROMPTS_TABLE,
                            array(
                                "history_id" => $prompt->getHistory()->getId(),
                                "history_value" => $promptPrompt
                            )
                        );
                    }
                    $attachments = $prompt->getCache()->getAttachments();

                    if (!empty($attachments)) {
                        foreach ($attachments as $attachment) {
                            if ($attachment->canBeAudio()) {
                                return $attachment->analyzeDescription(
                                    $prompt,
                                    $prompt->getTeam()
                                )->then(
                                    function (mixed $result)
                                    use ($prompt, $attachment, $hasTextPrompt) {
                                        $audioPrompt = null;

                                        if ($result === null) {
                                            $prompt->notify(
                                                "Failed to analyze the contact uploaded attachment with name '"
                                                . $attachment->getName()
                                                . "'."
                                            );
                                        } else {
                                            $analyzedDescription = $attachment->getAnalyzedDescription();

                                            if ($analyzedDescription !== null) {
                                                $audioPrompt = $analyzedDescription;

                                                if (IdealisticOfficeError::deleteSqlQuery(
                                                    $prompt->getTeam(),
                                                    $prompt->getUser(),
                                                    IdealisticOfficeTable::HISTORY_USER_ATTACHMENTS_TABLE,
                                                    array(
                                                        array("id", $attachment->getId())
                                                    ),
                                                    null,
                                                    1
                                                )) {
                                                    IdealisticOfficeError::sqlInsert(
                                                        $prompt->getTeam(),
                                                        $prompt->getUser(),
                                                        IdealisticOfficeTable::HISTORY_PROMPTS_TABLE,
                                                        array(
                                                            "history_id" => $prompt->getHistory()->getId(),
                                                            "history_value" => $analyzedDescription
                                                        )
                                                    );
                                                }
                                            }
                                        }

                                        if ($audioPrompt === null) {
                                            if (!$hasTextPrompt) {
                                                return resolve(
                                                    new IdealisticOfficeOutcome(
                                                        null,
                                                        "We could not extract any useful information from your request."
                                                    )
                                                );
                                            } else {
                                                return resolve(null);
                                            }
                                        } else {
                                            return resolve(null);
                                        }
                                    }
                                );
                            }
                        }
                        if ($hasTextPrompt) {
                            return resolve(null);
                        } else {
                            $found = false;

                            foreach ($attachments as $attachment) {
                                $analyzedDescription = $attachment->getAnalyzedDescription();

                                if (!empty($analyzedDescription)) {
                                    IdealisticOfficeError::sqlInsert(
                                        $prompt->getTeam(),
                                        $prompt->getUser(),
                                        IdealisticOfficeTable::HISTORY_PROMPTS_TABLE,
                                        array(
                                            "history_id" => $prompt->getHistory()->getId(),
                                            "history_value" => $analyzedDescription
                                        )
                                    );
                                    $found = true;
                                }
                            }

                            if ($found) {
                                return resolve(null);
                            } else {
                                return resolve(
                                    new IdealisticOfficeOutcome(
                                        null,
                                        "We could not extract any useful information from your prompt or attachments."
                                    )
                                );
                            }
                        }
                    } else {
                        if (!$hasTextPrompt) {
                            return resolve(
                                new IdealisticOfficeOutcome(
                                    null,
                                    "We could not extract any useful information from your prompt."
                                )
                            );
                        } else {
                            return resolve(null);
                        }
                    }
                } catch (Throwable $e) {
                    return reject($e);
                }
            },
            null
        );
    }

}
