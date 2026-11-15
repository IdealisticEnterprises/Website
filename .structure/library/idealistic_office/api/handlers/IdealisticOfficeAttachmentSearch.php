<?php

class IdealisticOfficeAttachmentSearch implements PhpAsyncSerializable
{

    /** @return AbstractIdealisticOfficeAttachment[]|IdealisticOfficeOutcome */
    public static function find(
        ?array                                            $customPool,
        IdealisticOfficeUser|AbstractIdealisticOfficeTeam $caller,
        string|array|null                                 $term,
        int                                               $limit = 0,
        ?string                                           $afterDate = null,
        ?string                                           $beforeDate = null,
        ?string                                           $fileType = null,
        ?bool                                             $userUploaded = null,
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
            if ($userUploaded === null
                || $userUploaded === false) {
                $createdAttachments = IdealisticOfficeHistoryIndependent::getHistoryFromSubTable(
                    IdealisticOfficeTable::HISTORY_CREATED_ATTACHMENTS_TABLE,
                    $partial
                        ? IdealisticOfficeQuery::CREATED_PARTIAL_ATTACHMENT
                        : IdealisticOfficeQuery::CREATED_ATTACHMENT,
                    $historyIDs,
                    false,
                    IdealisticOfficeLimit::MAX_PROCESSED_ROWS / ($userUploaded === null ? 2 : 1),
                    null
                );
                if ($partial) {
                    $createdAttachments = IdealisticOfficeReader::getCreatedPartialAttachments($createdAttachments, false);
                } else {
                    $createdAttachments = IdealisticOfficeReader::getCreatedAttachments($createdAttachments, false);
                }
            } else {
                $createdAttachments = array();
            }
            if ($userUploaded === null
                || $userUploaded === true) {
                $userAttachments = IdealisticOfficeHistoryIndependent::getHistoryFromSubTable(
                    IdealisticOfficeTable::HISTORY_USER_ATTACHMENTS_TABLE,
                    $partial
                        ? IdealisticOfficeQuery::PARTIAL_ATTACHMENT
                        : IdealisticOfficeQuery::ATTACHMENT,
                    $historyIDs,
                    false,
                    IdealisticOfficeLimit::MAX_PROCESSED_ROWS - sizeof($createdAttachments),
                    null
                );
                if ($partial) {
                    $userAttachments = IdealisticOfficeReader::getPartialAttachments($userAttachments, false);
                } else {
                    $userAttachments = IdealisticOfficeReader::getAttachments($userAttachments, false);
                }
            } else {
                $userAttachments = array();
            }
            $attachments = array_merge($userAttachments, $createdAttachments);

            if (empty($attachments)) {
                return new IdealisticOfficeOutcome(false, "No attachments found for team.");
            }
        } else {
            if (empty($customPool)) {
                return new IdealisticOfficeOutcome(false, "No attachments found.");
            }
            $attachments = $customPool;
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
        $results = array();
        $duplicate = array();
        $embeder = new Account();
        $embeder = $embeder->getEmbeddings();

        foreach ($attachments as $attachment) {
            if (!($attachment instanceof AbstractIdealisticOfficeAttachment)

                || $attachment->isAudio()
                && !$attachment->isUserUploaded()

                || $afterDate !== null
                && $attachment->getCreationDate() < $afterDate

                || $beforeDate !== null
                && $attachment->getCreationDate() > $beforeDate

                || array_key_exists($attachment->getDataHash(), $duplicate)) {
                continue;
            }
            switch ($fileType) {
                case "image":
                    if (!$attachment->isImage()) {
                        continue 2;
                    }
                    break;
                case "document":
                    if ($attachment->isImage()
                        || $attachment->canBeAudio()) {
                        continue 2;
                    }
                    break;
                case "audio":
                    if (!$attachment->isAudio()) {
                        continue 2;
                    }
                    break;
                case "video":
                    if (!$attachment->isVideo()) {
                        continue 2;
                    }
                    break;
                default:
                    break;
            }
            $analyzed = $attachment->getAnalyzedDescriptionPositions();

            if ($analyzed !== null) {
                $threshold = $embeder->cosineSimilarityString(
                    $embedding,
                    $analyzed
                );

                if ($threshold < IdealisticOfficeVariable::EMBEDDING_PROBABILITY_MINIMUM_THRESHOLD) {
                    continue;
                }
            } else {
                continue;
            }
            $results[IdealisticOfficeProcessor::floatToInteger($threshold)] = $attachment;
            $duplicate[$attachment->getDataHash()] = true;
        }
        if (empty($results)) {
            return new IdealisticOfficeOutcome(false, "No relevant attachments found for search term.");
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
            foreach ($results as $key => $attachment) {
                $results[$key] = IdealisticOfficeReader::getAttachment(
                    true,
                    $attachment,
                    true
                );
            }
        } else if ($json) {
            foreach ($results as $attachment) {
                $attachment->enableJSON();
            }
        }
        return $results;
    }

}