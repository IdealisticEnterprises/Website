<?php

namespace idealistic_office\api\handlers;

use account\api\objects\account\Account;
use idealistic_office\api\objects\IdealisticOfficeHistory;
use idealistic_office\api\objects\IdealisticOfficeUser;
use IdealisticOfficeLimit;
use IdealisticOfficeQuery;
use IdealisticOfficeTable;
use PhpAsyncSerializable;

class IdealisticOfficeHistoryIndependent implements PhpAsyncSerializable
{

    public static function getPrompts(int $id, bool $positions, ?array $list = null): array
    {
        if ($list === null) {
            $query = get_sql_query(
                IdealisticOfficeTable::HISTORY_PROMPTS_TABLE,
                $positions
                    ? IdealisticOfficeQuery::HISTORY_PROMPT_POSITIONS
                    : IdealisticOfficeQuery::HISTORY_PROMPT,
                array(
                    array("history_id", $id)
                ),
                null,
                IdealisticOfficeLimit::MAX_EXTENSIONS_PER_OBJECT
            );
        } else {
            $query = array_slice(
                $list[$id] ?? array(),
                0,
                IdealisticOfficeLimit::MAX_EXTENSIONS_PER_OBJECT
            );
        }

        if (!empty($query)) {
            $array = array();

            foreach ($query as $row) {
                $array[$row->id] = $row->{$positions ? "history_value_positions" : "history_value"};
            }
            return $array;
        }
        return array();
    }

    /** @param IdealisticOfficeHistory[] $histories */
    public static function getIDs(array $histories, bool $keys): array
    {
        if ($keys) {
            $array = array();

            if (!empty($histories)) {
                foreach ($histories as $history) {
                    $array[$history->getId()] = true;
                }
            }
            return $array;
        } else {
            if (!empty($histories)) {
                foreach ($histories as $key => $history) {
                    $histories[$key] = $history->getId();
                }
            }
            return $histories;
        }
    }

    public static function getHistoryFromSubTable(
        string  $table,
        array   $select,
        ?array  $historyIDs,
        bool    $descending = false,
        int     $limit = IdealisticOfficeLimit::MAX_PROCESSED_ROWS,
        ?string $organizeBy = "history_id",
        bool    $deletion = false
    ): array
    {
        if ($organizeBy !== null
            && !in_array($organizeBy, $select)) {
            $select[] = $organizeBy;
        }
        if (!empty($historyIDs)) {
            foreach ($historyIDs as $key => $history) {
                if ($history instanceof IdealisticOfficeHistory) {
                    $historyIDs[$key] = $history->getId();
                } else if (is_numeric($history)) {
                    break;
                } else {
                    unset($historyIDs[$key]);
                }
            }
        }
        $query = get_sql_query(
            $table,
            $select,
            empty($historyIDs)
                ? ($deletion ? array("deletion_date", false) : "")
                : array(
                "history_id IN ('" . implode("', '", $historyIDs) . "')"
                . ($deletion ? " AND deletion_date IS NULL" : "")
            ),
            $descending
                ? array(
                "DESC",
                "id"
            )
                : null,
            $limit
        );

        if (empty($query)) {
            return array();
        } else if ($organizeBy !== null) {
            $array = array();

            foreach ($query as $row) {
                if (isset($array[$row->{$organizeBy}])) {
                    $array[$row->{$organizeBy}][] = $row;
                } else {
                    $array[$row->{$organizeBy}] = array($row);
                }
            }
            return $array;
        } else {
            return $query;
        }
    }

    // Separator

    /** @return IdealisticOfficeHistory[] */
    public static function getRelated(IdealisticOfficeHistory $history, bool $selfOnly, int $limit): array
    {
        $userPositions = $history->getPromptEmbedding();

        if (empty($userPositions)) {
            return array();
        }
        $user = $history->getUser();

        if (!($user instanceof IdealisticOfficeUser)) {
            return array();
        }
        $histories = get_sql_query(
            IdealisticOfficeTable::HISTORY_TOTAL_TABLE,
            IdealisticOfficeQuery::HISTORY,
            array(
                array("team_id", $history->getTeam()->getId()),
                $selfOnly ? array("account_id", $user->getId()) : ""
            ),
            array(
                "DESC",
                "id"
            ),
            IdealisticOfficeLimit::MAX_PROCESSED_ROWS
        );

        if (empty($histories)) {
            return array();
        }
        $ids = array();
        $historyIndex = array();

        foreach ($histories as $key => $row) {
            $ids[] = (int)$row->id;
            $historyIndex[$row->id] = $key;
        }
        $repliesData = IdealisticOfficeHistoryIndependent::getHistoryFromSubTable(
            IdealisticOfficeTable::HISTORY_REPLIES_TABLE,
            IdealisticOfficeQuery::HISTORY_REPLY,
            $ids
        );

        if (empty($repliesData)) {
            return array();
        }
        $attachmentPositions = array();

        foreach (array(
                     IdealisticOfficeTable::HISTORY_USER_ATTACHMENTS_TABLE,
                     IdealisticOfficeTable::HISTORY_CREATED_ATTACHMENTS_TABLE
                 ) as $table) {
            $attachments = IdealisticOfficeHistoryIndependent::getHistoryFromSubTable(
                $table,
                array("history_id", "analyzed_description_positions"),
                $ids,
                false,
                IdealisticOfficeLimit::MAX_PROCESSED_ROWS,
                null
            );

            if (!empty($attachments)) {
                foreach ($attachments as $attachment) {
                    $attachmentPositions[$attachment->history_id][] = $attachment->analyzed_description_positions;
                }
            }
        }
        $linkPositions = array();
        $links = IdealisticOfficeHistoryIndependent::getHistoryFromSubTable(
            IdealisticOfficeTable::HISTORY_LINKS_TABLE,
            array("history_id", "analyzed_description_positions"),
            $ids,
            false,
            IdealisticOfficeLimit::MAX_PROCESSED_ROWS,
            null
        );

        if (!empty($links)) {
            foreach ($links as $link) {
                $linkPositions[$link->history_id][] = $link->analyzed_description_positions;
            }
        }
        $hasReplies = array();
        $rankedReplies = array();

        $embedder = new Account();
        $embedder = $embedder->getEmbeddings();

        foreach ($repliesData as $historyId => $replies) {
            $hasReplies[$historyId] = true;

            foreach ($replies as $reply) {
                if ($reply->history_value_positions !== null) {
                    $rankedReplies[$historyId] = max(
                        $rankedReplies[$historyId] ?? 0,
                        $embedder->cosineSimilarityString(
                            $userPositions,
                            $reply->history_value_positions
                        )
                    );
                }
            }
            $foundAttachments = $attachmentPositions[$historyId] ?? array();

            if (!empty($foundAttachments)) {
                foreach ($foundAttachments as $position) {
                    if ($position !== null) {
                        $rankedReplies[$historyId] = max(
                            $rankedReplies[$historyId] ?? 0,
                            $embedder->cosineSimilarityString(
                                $userPositions,
                                $position
                            )
                        );
                    }
                }
            }
            $foundLinks = $linkPositions[$historyId] ?? array();

            if (!empty($foundLinks)) {
                foreach ($foundLinks as $position) {
                    if ($position !== null) {
                        $rankedReplies[$historyId] = max(
                            $rankedReplies[$historyId] ?? 0,
                            $embedder->cosineSimilarityString(
                                $userPositions,
                                $position
                            )
                        );
                    }
                }
            }
        }

        if (!empty($rankedReplies)) {
            arsort($rankedReplies);
            $rankedReplies = array_slice($rankedReplies, 0, $limit);
        }
        foreach ($ids as $key => $id) {
            if (isset($hasReplies[$id])
                && !isset($rankedReplies[$id])) {
                unset($ids[$key]);
                $index = $historyIndex[$id] ?? null;

                if ($index !== null) {
                    unset($histories[$index]);
                }
            }
        }
        foreach ($histories as $id => $row) {
            $histories[$id] = new IdealisticOfficeHistory(
                $history->getTeam(),
                $row->id,
                $row->platform_id,
                $row->platform_user_id,
                $row->platform_message_id,
                $row->platform_chat_id,
                $row->platform_chat_name,
                $user->getAccount(),
                $row->creation_date,
                $row->creation_time_zone
            );
        }
        return $histories;
    }

}
