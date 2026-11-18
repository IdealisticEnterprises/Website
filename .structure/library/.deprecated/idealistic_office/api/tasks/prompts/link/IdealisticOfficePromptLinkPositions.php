<?php

namespace idealistic_office\api\tasks\prompts\link;

use idealistic_office\api\handlers\IdealisticOfficeError;
use idealistic_office\api\objects\IdealisticOfficePrompt;
use IdealisticOfficePromptTask;
use IdealisticOfficeTable;
use PhpAsyncSerializable;
use function React\Promise\all;
use function React\Promise\reject;
use function React\Promise\resolve;

class IdealisticOfficePromptLinkPositions implements PhpAsyncSerializable
{

    public static function description(IdealisticOfficePrompt $prompt): ?string
    {
        return "[SECONDARY FEATURE] Any searched URL/link will be properly organized in the system's files to be easily accessible for future reference.";
    }

    public static function run(
        IdealisticOfficePrompt $prompt
    ): void
    {
        $prompt->waitForTasks(array(IdealisticOfficePromptTask::LINK_ANALYSIS));
        $prompt->runTask(function () use ($prompt) {
            try {
                $links = $prompt->getHistory()->getPartialLinks(null, null, false);

                if (empty($links)) {
                    return resolve(null);
                } else {
                    $promises = array();

                    foreach ($links as $link) {
                        $description = $link->getAnalyzedDescription();

                        if ($description === null) {
                            continue;
                        }
                        $promises[] = $prompt->getCache()->getEmbedding(
                            $description
                        )->then(function ($positions) use ($prompt, $link, $description) {
                            if ($positions !== null) {
                                $embedding = pack("f*", ...$positions);

                                if (empty($embedding)) {
                                    return;
                                }
                                IdealisticOfficeError::setSqlQuery(
                                    $prompt->getTeam(),
                                    $prompt->getUser(),
                                    IdealisticOfficeTable::HISTORY_LINKS_TABLE,
                                    array(
                                        "analyzed_description_positions" => $embedding
                                    ),
                                    array(
                                        array("id", $link->getId())
                                    ),
                                    null,
                                    1
                                );
                            }
                        });
                    }
                    return all($promises)->then(
                        function () {
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
            null
        );
    }

}
