<?php

use function React\Promise\reject;
use function React\Promise\resolve;

class IdealisticOfficePromptWebData implements PhpAsyncSerializable
{

    public static function description(IdealisticOfficePrompt $prompt): ?string
    {
        return "[SECONDARY FEATURE] The web will be lightly searched for information related to the query in case it ends up being useful later on.";
    }

    public static function run(
        IdealisticOfficePrompt $prompt
    ): void
    {
        $prompt->waitForTasks(array(
            IdealisticOfficePromptTask::INFORMATION_ANALYSIS
        ));
        $prompt->runTask(function () use ($prompt) {
            try {
                if (IdealisticOfficePromptShareInformation::isSharing($prompt)) {
                    return resolve(null);
                }
                $query = $prompt->getCache()->getImplodedPrompts();
                $search = IdealisticOfficeProcessor::search($prompt, $query);

                if (is_array($search)
                    && !empty($search)) {
                    $array = array();

                    foreach ($search as $item) {
                        $string = $item->toString();

                        if (empty($string)) {
                            continue;
                        }
                        $array[] = $string;

                        if (empty($item->getLink())
                            || empty($item->getSnippet())) {
                            continue;
                        }
                        $prompt->getCache()->addExtraURL($item->getLink(), $item->getSnippet());
                    }

                    if (!empty($array)) {
                        $results = implode("\n", $array);
                        $prompt->getCache()->setWebResults($query, $results);
                    }
                }
                return resolve(null);
            } catch (Throwable $e) {
                return reject($e);
            }
        },
            null
        );
    }

}
