<?php

use function React\Promise\all;
use function React\Promise\reject;
use function React\Promise\resolve;

class IdealisticOfficePromptAttachmentPositions implements PhpAsyncSerializable
{

    public static function description(IdealisticOfficePrompt $prompt): ?string
    {
        return "[SECONDARY FEATURE] Any uploaded attachment will be properly organized in the system's files to be easily accessible for future reference.";
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
                        $promises[] = $prompt->getCache()->getEmbedding(
                            $description
                        )->then(function ($positions) use ($prompt, $attachment, $description) {
                            if ($positions !== null) {
                                $embedding = pack("f*", ...$positions);

                                if (empty($embedding)) {
                                    return;
                                }
                                IdealisticOfficeError::setSqlQuery(
                                    $prompt->getTeam(),
                                    $prompt->getUser(),
                                    IdealisticOfficeTable::HISTORY_USER_ATTACHMENTS_TABLE,
                                    array(
                                        "analyzed_description_positions" => $embedding
                                    ),
                                    array(
                                        array("id", $attachment->getId())
                                    ),
                                    null,
                                    1
                                );
                            }
                        });
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
