<?php

namespace idealistic_office\api\handlers;

use account\api\objects\account\Account;
use idealistic_office\api\IdealisticOfficeVariable;
use idealistic_office\api\objects\helpers\AbstractIdealisticOfficeTeam;
use idealistic_office\api\objects\helpers\IdealisticOfficeAI;
use idealistic_office\api\objects\helpers\IdealisticOfficeLink;
use idealistic_office\api\objects\helpers\IdealisticOfficeOutcome;
use idealistic_office\api\objects\IdealisticOfficeUser;
use IdealisticOfficeLimit;
use IdealisticOfficeQuery;
use IdealisticOfficeTable;
use PhpAsyncSerializable;

class IdealisticOfficeLinkSearch implements PhpAsyncSerializable
{

    /** @return IdealisticOfficeLink[]|IdealisticOfficeOutcome */
    public static function find(
        ?array                                            $customPool,
        IdealisticOfficeUser|AbstractIdealisticOfficeTeam $caller,
        string|array|null                                 $term,
        int                                               $limit = IdealisticOfficeLimit::MAX_EXTENSIONS_PER_OBJECT_LARGE,
        ?string                                           $afterDate = null,
        ?string                                           $beforeDate = null,
        bool                                              $partial = true,
        bool                                              $simpleObjects = false,
        bool                                              $json = true
    ): array|IdealisticOfficeOutcome
    {
        $team = $caller instanceof IdealisticOfficeUser ? $caller->getTeam() : $caller;

        if (!$caller->hasEstablishedAccess()) {
            return new IdealisticOfficeOutcome(false, "No team access established.");
        }
        if (is_string($term)) {
            if (strlen($term) === 0) {
                return new IdealisticOfficeOutcome(false, "Search term cannot be empty.");
            }
            if (strlen($term) > IdealisticOfficeLimit::MAX_SEARCH_QUERY_LENGTH) {
                return new IdealisticOfficeOutcome(false, "Search term exceeds maximum length of " . IdealisticOfficeLimit::MAX_SEARCH_QUERY_LENGTH . " characters.");
            }
        } else if ($term === null) {
            return new IdealisticOfficeOutcome(false, "Search term cannot be null.");
        }
        $historyIDs = $team->getHistoryIDs();

        if (empty($historyIDs)) {
            return new IdealisticOfficeOutcome(false, "No history found for team.");
        }
        if ($customPool === null) {
            $links = IdealisticOfficeHistoryIndependent::getHistoryFromSubTable(
                IdealisticOfficeTable::HISTORY_LINKS_TABLE,
                $partial
                    ? IdealisticOfficeQuery::HISTORY_LINK_PARTIAL
                    : IdealisticOfficeQuery::HISTORY_LINK,
                $historyIDs,
                false,
                IdealisticOfficeLimit::MAX_PROCESSED_ROWS,
                null
            );

            if (empty($links)) {
                return new IdealisticOfficeOutcome(false, "No links found for team.");
            }
        } else {
            if (!empty($customPool)) {
                return new IdealisticOfficeOutcome(false, "No links found.");
            }
            $links = $customPool;
        }
        $user = $caller instanceof IdealisticOfficeUser ? $caller : null;

        if (is_string($term)) {
            $embedding = IdealisticOfficeAI::getEmbedding(
                $user ?? $team,
                $term
            );

            if ($embedding === null) {
                return new IdealisticOfficeOutcome(false, "Failed to generate embedding for search term.");
            }
        } else {
            $embedding = $term;
        }
        $embedding = pack("f*", ...$embedding);

        if (empty($embedding)) {
            return new IdealisticOfficeOutcome(false, "Failed to pack embedding.");
        }
        if ($customPool === null) {
            foreach ($links as $key => $row) {
                $links[$key] = new IdealisticOfficeLink(
                    $team,
                    (int)$row->id,
                    $row->link_url,
                    $partial ? null : $row->link_content,
                    $row->analyzed_description,
                    $row->analyzed_description_positions,
                    $row->creation_date,
                    $row->creation_time_zone,
                    $row->created_by,
                    $row->refresh_date,
                    $row->refresh_time_zone,
                    $row->refreshed_by,
                    $json
                );
            }
        }
        $results = array();
        $duplicate = array();
        $embeder = new Account();
        $embeder = $embeder->getEmbeddings();

        foreach ($links as $link) {
            if (!($link instanceof IdealisticOfficeLink)

                || $afterDate !== null
                && $link->getCreationDate() < $afterDate

                || $beforeDate !== null
                && $link->getCreationDate() > $beforeDate

                || array_key_exists($link->getHash(), $duplicate)) {
                continue;
            }
            $positions = $link->getAnalyzedDescriptionPositions();

            if ($positions !== null) {
                $threshold = $embeder->cosineSimilarityString(
                    $embedding,
                    $positions
                );
            } else {
                $threshold = 0;
            }

            if ($threshold < IdealisticOfficeVariable::EMBEDDING_PROBABILITY_MINIMUM_THRESHOLD) {
                continue;
            }
            $results[IdealisticOfficeProcessor::floatToInteger($threshold)] = $link;
            $duplicate[$link->getHash()] = true;
        }
        if (empty($results)) {
            return new IdealisticOfficeOutcome(false, "No relevant links found for search term.");
        }
        krsort($results);
        $results = array_slice(
            $results,
            0,
            min(
                max(IdealisticOfficeLimit::MAX_EXTENSIONS_PER_OBJECT_LARGE, $limit),
                IdealisticOfficeLimit::MAX_PROCESSED_ROWS
            ),
            true
        );

        if ($simpleObjects) {
            $final = array();

            foreach ($results as $link) {
                if (!($link instanceof IdealisticOfficeLink)) {
                    continue;
                }
                $results[$link->getUrl()] = $link->getAnalyzedDescription();
            }
            return $final;
        }
        return $results;
    }

}