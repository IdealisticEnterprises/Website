<?php

namespace idealistic_office\api\tasks\prompts\general;

use idealistic_office\api\handlers\IdealisticOfficeError;
use idealistic_office\api\objects\IdealisticOfficePrompt;
use IdealisticOfficePromptTask;
use IdealisticOfficeTable;
use PhpAsyncSerializable;
use function React\Promise\all;
use function React\Promise\reject;
use function React\Promise\resolve;

class IdealisticOfficePromptInformationAnalysisPositions implements PhpAsyncSerializable
{

    public static function description(IdealisticOfficePrompt $prompt): ?string
    {
        return "[SECONDARY FEATURE] Any sent message will be properly organized in the system's history to be easily accessible for future reference.";
    }

    public static function run(
        IdealisticOfficePrompt $prompt
    ): void
    {
        $prompt->waitForTasks(array(
            IdealisticOfficePromptTask::INFORMATION_ANALYSIS
        ));
        $prompt->runTask(
            function () use ($prompt) {
                try {
                    $prompts = $prompt->getHistory()->getPrompts();

                    if (empty($prompts)) {
                        return resolve(null);
                    } else {
                        $promises = array();

                        foreach ($prompts as $id => $info) {
                            $promises[] = $prompt->getCache()->getEmbedding(
                                $info
                            )->then(function ($positions) use ($prompt, $info, $id) {
                                if ($positions !== null) {
                                    $embedding = pack("f*", ...$positions);

                                    if (empty($embedding)) {
                                        return;
                                    }
                                    IdealisticOfficeError::setSqlQuery(
                                        $prompt->getTeam(),
                                        $prompt->getUser(),
                                        IdealisticOfficeTable::HISTORY_PROMPTS_TABLE,
                                        array(
                                            "history_value_positions" => $embedding
                                        ),
                                        array(
                                            array("id", $id)
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
            null
        );
    }

}
