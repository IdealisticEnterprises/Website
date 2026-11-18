<?php

namespace idealistic_office\api\tasks\prompts\link;

use idealistic_office\api\handlers\IdealisticOfficeError;
use idealistic_office\api\handlers\IdealisticOfficeProcessor;
use idealistic_office\api\handlers\IdealisticOfficeReader;
use idealistic_office\api\objects\IdealisticOfficePrompt;
use IdealisticOfficeLimit;
use IdealisticOfficePromptTask;
use IdealisticOfficeTable;
use PhpAsyncSerializable;
use function React\Promise\all;
use function React\Promise\reject;
use function React\Promise\resolve;

class IdealisticOfficePromptLinkAnalysis implements PhpAsyncSerializable
{

    public static function description(IdealisticOfficePrompt $prompt): ?string
    {
        return "[SECONDARY FEATURE] Any written or web-search-retrieved URL/link will be analyzed by building a description of the content of the URL/link."
            . " (Avoid URLs/links that require login, are mostly images, video, audio, or are otherwise inaccessible)";
    }

    public static function run(
        IdealisticOfficePrompt $prompt
    ): void
    {
        $prompt->waitForTasks(array(
            IdealisticOfficePromptTask::WEB_DATA
        ));
        $prompt->runTask(function () use ($prompt) {
            try {
                $timezone = $prompt->getUser()->getTimezone(false);
                $URLs = $prompt->getCache()->getFoundURLs();
                $urlsWithIDs = array();

                if (!empty($URLs)) {
                    foreach ($URLs as $url) {
                        if (IdealisticOfficeError::sqlInsert(
                            $prompt->getTeam(),
                            $prompt->getUser(),
                            IdealisticOfficeTable::HISTORY_LINKS_TABLE,
                            array(
                                "history_id" => $prompt->getHistory()->getId(),
                                "link_url" => $url,
                                "creation_date" => IdealisticOfficeReader::getCurrentDate($timezone),
                                "creation_time_zone" => $timezone->getTimeZone(),
                                "created_by" => $prompt->getUser()->getId()
                            )
                        )) {
                            $insertID = IdealisticOfficeReader::getSqlLastInsertId();

                            if ($insertID === null) {
                                continue;
                            }
                            $urlsWithIDs[$insertID] = $url;
                        }
                    }
                }

                if (empty($urlsWithIDs)) {
                    return resolve(null);
                } else {
                    $promises = array();

                    foreach ($urlsWithIDs as $id => $word) {
                        $contents = IdealisticOfficeProcessor::getLinkContents($word);

                        if (!empty($contents)) {
                            $promises[] = IdealisticOfficeProcessor::analyzeLink(
                                $prompt,
                                $prompt->getTeam(),
                                $contents,
                                IdealisticOfficeLimit::K11_LENGTH
                            )->then(
                                function ($description) use ($id, $word, $contents, $prompt, $timezone) {
                                    if (empty($description)) {
                                        $prompt->notify(
                                            "Failed to find the contents of the URL: " . $word
                                        );
                                        IdealisticOfficeError::deleteSqlQuery(
                                            $prompt->getTeam(),
                                            $prompt->getUser(),
                                            IdealisticOfficeTable::HISTORY_LINKS_TABLE,
                                            array(
                                                array("id", $id)
                                            ),
                                            null,
                                            1
                                        );
                                    } else {
                                        IdealisticOfficeError::setSqlQuery(
                                            $prompt->getTeam(),
                                            $prompt->getUser(),
                                            IdealisticOfficeTable::HISTORY_LINKS_TABLE,
                                            array(
                                                "link_content" => $contents,
                                                "analyzed_description" => $description,
                                                "creation_date" => IdealisticOfficeReader::getCurrentDate($timezone),
                                                "creation_time_zone" => $timezone->getTimeZone(),
                                                "created_by" => $prompt->getUser()->getId()
                                            ),
                                            array(
                                                array("id", $id)
                                            ),
                                            null,
                                            1
                                        );
                                    }
                                },
                                function (Throwable $e) use ($id, $contents, $word, $prompt, $timezone) {
                                    $prompt->notify(
                                        "Failed to retrieve the contents of the URL: " . $word
                                    );
                                    IdealisticOfficeError::deleteSqlQuery(
                                        $prompt->getTeam(),
                                        $prompt->getUser(),
                                        IdealisticOfficeTable::HISTORY_LINKS_TABLE,
                                        array(
                                            array("id", $id)
                                        ),
                                        null,
                                        1
                                    );
                                    throw $e;
                                }
                            );

                            if (!$prompt->getCache()->isSubscriber()) {
                                break;
                            }
                        } else {
                            $prompt->notify(
                                "Failed to access the contents of the URL: " . $word
                            );
                            IdealisticOfficeError::deleteSqlQuery(
                                $prompt->getTeam(),
                                $prompt->getUser(),
                                IdealisticOfficeTable::HISTORY_LINKS_TABLE,
                                array(
                                    array("id", $id)
                                ),
                                null,
                                1
                            );
                        }
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
