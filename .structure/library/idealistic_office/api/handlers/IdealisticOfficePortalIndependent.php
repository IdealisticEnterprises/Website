<?php

class IdealisticOfficePortalIndependent implements PhpAsyncSerializable
{

    public static function getPortal(
        int|string $reference,
        bool       $isRandomId = true,
        bool       $json = true,
        ?string    $defaultURLToSet = null
    ): ?IdealisticOfficePortal
    {
        if ($isRandomId
            && empty($reference)) {
            $team = new IdealisticOfficeTeam(0);
            return new IdealisticOfficePortal(
                $team,
                0,
                0,
                IdealisticOfficeVariable::APPLICATION_NAME,
                null,
                "",
                null,
                true,
                true,
                true,
                false,
                false,
                IdealisticOfficeReader::getCurrentDate(),
                IdealisticOfficeVariable::DEFAULT_TIME_ZONE,
                null,
                null,
                $json
            );
        }
        $query = get_sql_query(
            IdealisticOfficeTable::PORTALS_TABLE,
            IdealisticOfficeQuery::PORTAL_WITH_TEAM,
            array(
                array("deletion_date", null),
                array($isRandomId ? "random_id" : "id", $reference)
            ),
            null,
            1
        );

        if (empty($query)) {
            return null;
        } else {
            $team = new IdealisticOfficeTeam($query[0]->team_id, $json);

            if (!$team->hasEstablishedAccess()) {
                return null;
            }
            return new IdealisticOfficePortal(
                $team,
                $query[0]->id,
                $query[0]->created_by,
                $query[0]->name,
                $query[0]->password,
                $query[0]->random_id,
                $query[0]->default_url,
                $query[0]->downloads_allowed !== null,
                $query[0]->voice_replies !== null,
                $query[0]->skip_robot_verification !== null,
                $query[0]->is_18_plus !== null,
                $query[0]->require_login !== null,
                $query[0]->creation_date,
                $query[0]->creation_time_zone,
                $query[0]->default_message,
                $query[0]->transfer_to,
                $json,
                $defaultURLToSet
            );
        }
    }

    public static function getProcessingHistory(): int
    {
        return sizeof(get_sql_query(
            IdealisticOfficeTable::PORTAL_HISTORY_TABLE,
            array("id"),
            array(
                array("is_completed", false),
                array("is_processing", true)
            ),
            null,
            IdealisticOfficeLimit::MAX_PROCESSED_TASKS
        ));
    }

    public static function processHistory(): bool
    {
        $query = get_sql_query(
            IdealisticOfficeTable::PORTAL_HISTORY_TABLE,
            IdealisticOfficeQuery::PORTAL_HISTORY_PENDING,
            array(
                array("is_completed", false),
                array("is_processing", false)
            ),
            null,
            IdealisticOfficeLimit::MAX_PROCESSED_TASKS
        );
        $portals = array();

        if (!empty($query)) {
            foreach ($query as $row) {
                $portal = $portals[$row->portal_id] ?? null;

                if ($portal !== null) {
                    if ($portal === false) {
                        IdealisticOfficeError::setSqlQuery(
                            null,
                            null,
                            IdealisticOfficeTable::PORTAL_HISTORY_TABLE,
                            array(
                                "is_completed" => true
                            ),
                            array(
                                array("id", $row->id)
                            ),
                            null,
                            1
                        );
                        continue;
                    }
                } else {
                    $portal = self::getPortal($row->portal_id, false, false);

                    if ($portal === null) {
                        $portals[$row->portal_id] = false;
                        IdealisticOfficeError::setSqlQuery(
                            null,
                            null,
                            IdealisticOfficeTable::PORTAL_HISTORY_TABLE,
                            array(
                                "is_completed" => true
                            ),
                            array(
                                array("id", $row->id)
                            ),
                            null,
                            1
                        );
                        continue;
                    } else {
                        $portals[$row->portal_id] = $portal;
                    }
                }
                IdealisticOfficeError::setSqlQuery(
                    $portal->getTeam(),
                    null,
                    IdealisticOfficeTable::PORTAL_HISTORY_TABLE,
                    array(
                        "is_processing" => true
                    ),
                    array(
                        array("id", $row->id)
                    ),
                    null,
                    1
                );
                // The cached portal is shared between rows, so each one gets a copy that knows where its message came from,
                // otherwise the history of installed portals cannot be found when replying
                $place = clone $portal;

                if ($row->platform_id !== null) {
                    self::preparePortalForExternalUse(
                        $place,
                        (int)$row->platform_id,
                        $row->server_id,
                        $row->channel_id,
                        $row->channel_thread_id,
                        $row->message_id,
                        $row->user_id,
                        null,
                        null
                    );
                }
                IdealisticOfficeAsync::run(
                    array(
                        "IdealisticOfficePortalIndependent",
                        "processIndividualHistory"
                    ),
                    array(
                        $place,
                        $row->id,
                        $row->session_id,
                        $row->default_language
                    )
                );
            }
            return true;
        }
        return false;
    }

    public static function processIndividualHistory(
        IdealisticOfficePortal $portal,
        string                 $id,
        string                 $sessionId,
        string                 $defaultLanguage
    ): void
    {
        try {
            $placeHistory = $portal->isPlaceHistory();
            $data = $portal->getData(
                IdealisticOfficeLimit::MAX_EXTENSIONS_PER_OBJECT_LARGE,
                $sessionId,
                null,
                true
            );
            $knowledgeTexts = array();

            if (!empty($data[IdealisticOfficePortal::KNOWLEDGE_KEY])) {
                foreach ($data[IdealisticOfficePortal::KNOWLEDGE_KEY] as $entries) {
                    foreach ($entries as $entry) {
                        if (!empty($entry['text'])) {
                            $knowledgeTexts[] = "- " . $entry['text'];
                        }
                    }
                }
            }
            $entries = array();

            if (!empty($data[IdealisticOfficePortal::HISTORY_KEY])) {
                foreach ($data[IdealisticOfficePortal::HISTORY_KEY] as $interaction) {
                    foreach (['user', 'assistant'] as $role) {
                        foreach ($interaction[$role] ?? array() as $msgData) {
                            $entryId = (int)($msgData['id'] ?? 0);

                            if ($entryId > (int)$id) {
                                continue; // Said after the message that is being answered
                            }
                            $content = trim($msgData['text'] ?? '');

                            if ($content === IdealisticOfficePortal::NO_MESSAGE) {
                                $content = '';
                            }
                            if (isset($msgData['attachment'])) {
                                $content .= "\n[Attachment: " . @json_encode($msgData['attachment']) . "]";
                            }
                            if (isset($msgData['link'])) {
                                $content .= "\n[Link: " . $msgData['link'] . "]";
                            }
                            $content = trim($content);

                            if ($content === '') {
                                continue;
                            }
                            $name = null;

                            if ($placeHistory && $role === 'user') {
                                $name = trim(mb_substr(preg_replace('/\s+/', ' ', (string)($msgData['display_name'] ?? '')), 0, IdealisticOfficeLimit::CHAT_PORTAL_HISTORY_NAME_LENGTH));
                            }
                            $entries[] = array(
                                "id" => $entryId,
                                "role" => $role,
                                "content" => $content,
                                "name" => $name === null ? null : ($name === '' ? "User" : $name)
                            );
                        }
                    }
                }
                // Messages are grouped by their date, so the order is rebuilt from the IDs
                usort($entries, fn(array $a, array $b) => $a['id'] <=> $b['id']);
            }
            // The message that triggered this reply is the sender's latest own message before the pending reply
            $trigger = get_sql_query(
                IdealisticOfficeTable::PORTAL_HISTORY_TABLE,
                IdealisticOfficeQuery::PORTAL_HISTORY_TRIGGER,
                array(
                    array("portal_id", $portal->getId()),
                    array("session_id", $sessionId),
                    array("is_completed", true),
                    array("ip_address", "IS NOT", null),
                    array("id", "<", $id),
                    array("deletion_date", null)
                ),
                array("DESC", "id"),
                1
            );
            $triggerId = empty($trigger) ? null : (int)$trigger[0]->id;
            $finalUserPrompt = null;

            for ($i = sizeof($entries) - 1; $i >= 0; $i--) {
                if ($entries[$i]['role'] === 'user'
                    && ($triggerId === null || $entries[$i]['id'] === $triggerId)) {
                    $finalUserPrompt = $entries[$i]['name'] === null
                        ? $entries[$i]['content']
                        : $entries[$i]['name'] . ": " . $entries[$i]['content'];
                    array_splice($entries, $i, 1);
                    break;
                }
            }
            $finalUserPrompt ??= empty($trigger) || empty($trigger[0]->message)
                ? "(Empty prompt)"
                : $trigger[0]->message;
            $turns = array();
            $budget = $portal->getTeam()->getCredit()->getHistoryBudget();

            // Newest first so the oldest messages are the ones to fall out of the budget
            for ($i = sizeof($entries) - 1; $i >= 0; $i--) {
                $characters = $entries[$i]['role'] === 'assistant'
                    ? IdealisticOfficeLimit::CHAT_PORTAL_HISTORY_REPLY_CHARACTERS
                    : IdealisticOfficeLimit::CHAT_PORTAL_HISTORY_MESSAGE_CHARACTERS;
                $text = mb_strlen($entries[$i]['content']) > $characters
                    ? mb_substr($entries[$i]['content'], 0, $characters) . "..."
                    : $entries[$i]['content'];
                $text = $entries[$i]['name'] === null ? $text : $entries[$i]['name'] . ": " . $text;
                $budget -= mb_strlen($text);

                if ($budget < 0) {
                    break;
                }
                array_unshift($turns, array("role" => $entries[$i]['role'], "content" => $text));
            }
            $turns[] = array("role" => "user", "content" => $finalUserPrompt);
            $chatTurns = array();

            // Consecutive messages of the same role are merged, since not every model accepts them
            foreach ($turns as $turn) {
                $last = sizeof($chatTurns) - 1;

                if ($last >= 0 && $chatTurns[$last]['role'] === $turn['role']) {
                    $chatTurns[$last]['content'] .= "\n" . $turn['content'];
                } else {
                    $chatTurns[] = $turn;
                }
            }
            $relatedAttachments = $data[IdealisticOfficePortal::ATTACHMENTS_KEY];
            $size = sizeof($relatedAttachments);

            if ($size > IdealisticOfficeLimit::MAX_EXTENSIONS_PER_OBJECT_LARGE) {
                $cut = array_slice($relatedAttachments, 0, min(IdealisticOfficeLimit::MAX_EXTENSIONS_PER_OBJECT_LARGE, $size));

                if (!empty($cut)) {
                    for ($i = 0; $i < sizeof($cut); $i++) {
                        array_shift($relatedAttachments);
                    }
                }
                $relatedAttachments = array_merge(
                    $cut,
                    IdealisticOfficeAttachmentSearch::find(
                        $relatedAttachments,
                        $portal->getTeam(),
                        $finalUserPrompt
                    )
                );
            }

            if (is_array($relatedAttachments)
                && !empty($relatedAttachments)) {
                foreach ($relatedAttachments as $attachment) {
                    $knowledgeTexts[] = "- [Attachment: " . @json_encode($attachment) . "]";
                }
            }
            $relatedLinks = $data[IdealisticOfficePortal::LINKS_KEY];
            $size = sizeof($relatedLinks);

            if ($size > IdealisticOfficeLimit::MAX_EXTENSIONS_PER_OBJECT_LARGE) {
                $cut = array_slice($relatedLinks, 0, min(IdealisticOfficeLimit::MAX_EXTENSIONS_PER_OBJECT_LARGE, $size));

                if (!empty($cut)) {
                    for ($i = 0; $i < sizeof($cut); $i++) {
                        array_shift($relatedLinks);
                    }
                }
                $relatedLinks = array_merge(
                    $cut,
                    IdealisticOfficeLinkSearch::find(
                        $relatedLinks,
                        $portal->getTeam(),
                        $finalUserPrompt
                    )
                );
            }

            if (is_array($relatedLinks)
                && !empty($relatedLinks)) {
                foreach ($relatedLinks as $link) {
                    $knowledgeTexts[] = "- [Link: " . $link->getUrl() . ":\n\n" . $link->getAnalyzedDescription() . "]";
                }
            }
            $knowledgeContext = implode("\n", $knowledgeTexts);
            $systemPrompt = array(
                "The timezone is: " . $portal->getPortalTimeZone()->getTimeZone(),
                "ROLE: You are a warm, professional, and highly helpful customer success representative for this business. You speak directly to the business's potential clients and customers.",
                "NOISE FILTER (CRITICAL): When reading the provided knowledge base or website content, completely IGNORE technical metadata, SEO elements, HTML structure, cookie consent notices, legal disclaimers about privacy, and script references (e.g., lazy loading, GTM). Your ONLY focus is the actual business, its services, and the value it provides to the customer.",
                "GOAL & MARKETING: Your primary job is to assist visitors, answer their questions about the company's services, and gently highlight the value of the business. Be conversational and NEVER confront the user with technical website analysis or metadata summaries.",
                "STAY ON TOPIC: If a user asks about entirely unrelated or inappropriate topics, politely steer the conversation back to how this specific business can help them.",
                "KNOWLEDGE BOUNDARY: Base your answers ONLY on the relevant business information in the knowledge base. If you do not know the answer, politely say so or offer to connect them with the human team.",
                "ACTION LIMIT: You are an informational guide. While you cannot take direct actions on behalf of the user, you should eagerly explain the steps they can take to do it themselves.",
                "ORIGIN: If a user asks who made you or how you work, proudly and naturally state that you are an AI assistant powered by " . IdealisticOfficeVariable::APPLICATION_NAME . " (" . IdealisticOfficeVariable::COMPANY_WEBSITE_URL . ").",
                "PROMOTION: If the conversation presents a natural opportunity, politely suggest to business owners that they can get a smart chat widget like you at " . IdealisticOfficeVariable::APPLICATION_NAME . " (" . IdealisticOfficeVariable::COMPANY_WEBSITE_URL . ").",
                "Unless instructed otherwise by the user, the default language is: " . $defaultLanguage,
                "CONVERSATION: The earlier messages of this conversation follow as chat turns. Answer ONLY the latest user message, using the earlier turns for context, such as follow-up questions, words like 'it' or 'that', and what was already said or answered. Never repeat an earlier answer word for word, build on it instead. Answer the actual question first, then add anything useful"
            );

            if ($placeHistory) {
                $systemPrompt[] = "GROUP CHAT: You are in a chat with several people. Each user message starts with its sender's name. Only the latest message was addressed to you, the earlier ones are context from people talking, so use them to understand the situation but do not answer them again. Address the sender of the latest message by name when it feels natural";
            }
            $systemPrompt = implode(". ", $systemPrompt)
                . (empty($knowledgeContext) ? "." : ". You have been provided with a Reference Knowledge Base. This data is your primary anchor. "
                    . "If a prompt targets topics completely outside this reference data, you must not refuse to answer. However, you are strictly required to keep your explanation of off-topic subjects brief and concise. "
                    . "Immediately after briefly addressing the off-topic request, you must smoothly and logically pivot the conversation back to the concepts and rules found within the Knowledge Base. "
                    . "Synthesize creatively, but maintain strict conversational discipline by prioritizing the provided reference text.\n\n"
                    . "=== KNOWLEDGE BASE ===\n"
                    . $knowledgeContext)
                . "\n\n=== CURRENT TIME ===\n"
                . IdealisticOfficeReader::getCurrentDate() . " " . IdealisticOfficeVariable::DEFAULT_TIME_ZONE;
            $arguments = array(
                "messages" => array_merge(
                    array(array("role" => "system", "content" => $systemPrompt)),
                    $chatTurns
                )
            );
            $AI = new IdealisticOfficeAI(
                $portal->getTeam(),
                IdealisticOfficeVariable::TASK_AI_CHEAP_MODEL,
                "portal_id_" . $portal->getId(),
                IdealisticOfficeVariable::MINIMUM_PORTAL_CREDITS_TO_USE
            );
            $AI->adjustCertainty($arguments);
            $result = $AI->getResult(
                $arguments
            );
            $updateData = array(
                "is_completed" => true,
                "is_processing" => false
            );

            if (!empty($result)) {
                if (mb_strlen($result) > IdealisticOfficeLimit::CHAT_PORTAL_MAX_REPLY_LENGTH) {
                    $result = mb_substr($result, 0, IdealisticOfficeLimit::CHAT_PORTAL_MAX_REPLY_LENGTH - 3) . "...";
                }
                $updateData["message"] = $result;
                $updateData["creation_date"] = IdealisticOfficeReader::getCurrentDate();

                if ($portal->areVoiceRepliesEnabled()) {
                    $audio = IdealisticOfficeProcessor::transcribeText(
                        null,
                        $portal->getTeam(),
                        $result
                    );

                    if (is_string($audio)) {
                        $timezone = $portal->getPortalTimeZone();
                        $attachment = new IdealisticOfficeAttachment(
                            null,
                            random_string(IdealisticOfficeLimit::RANDOM_STRING_LENGTH) . ".wav",
                            "audio/wav",
                            null,
                            -1,
                            null,
                            null,
                            null,
                            base64_encode($audio),
                            null,
                            true,
                            IdealisticOfficeReader::getCurrentDate($timezone),
                            $timezone->getTimeZone(),
                            0
                        );
                        $attachment->setUserPrompt($result);

                        if ($attachment->isValid()) {
                            $updateData["attachment_name"] = $attachment->getName();
                            $updateData["attachment_format"] = $attachment->getFormat();
                            $updateData["attachment_bytes"] = $attachment->getBytes();
                            $updateData["attachment_analyzed_description"] = $attachment->getAnalyzedDescription();
                            $updateData["attachment_data"] = $attachment->getRawData();
                        }
                    }
                }
            }
            IdealisticOfficeError::setSqlQuery(
                $portal->getTeam(),
                null,
                IdealisticOfficeTable::PORTAL_HISTORY_TABLE,
                $updateData,
                array(
                    array("id", $id)
                ),
                null,
                1
            );
        } catch (Throwable $e) {
            IdealisticOfficeError::simpleStore($e);
            IdealisticOfficeError::setSqlQuery(
                $portal->getTeam(),
                null,
                IdealisticOfficeTable::PORTAL_HISTORY_TABLE,
                array(
                    "is_completed" => true,
                    "is_processing" => false
                ),
                array(
                    array("id", $id)
                ),
                null,
                1
            );
        }
    }

    private static function getInstalledPortalRaw(
        int             $platformId,
        int|string|null $serverId,
        int|string|null $channelId,
        int|string|null $threadId,
        bool            $objectOnly = false,
        bool            $json = false
    ): IdealisticOfficePortal|int|string|null
    {
        $query = array(
            array("platform_id", $platformId),
            array("server_id", $serverId),
            array("channel_id", $channelId),
            array("deletion_date", null)
        );

        if ($threadId === null) {
            $query[] = array("thread_id", null);
        } else {
            $query[] = null;
            $query[] = array("thread_id", "=", $threadId, 0);
            $query[] = array("thread_id", null);
            $query[] = null;
        }
        $query = get_sql_query(
            IdealisticOfficeTable::PORTAL_INSTALLATIONS_TABLE,
            $objectOnly ? array("id") : array("id", "portal_id"),
            $query,
            null,
            1
        );

        if (empty($query)) {
            return null;
        } else {
            $id = $query[0]->id;
            $portal = $objectOnly ? $id : self::getPortal(
                $query[0]->portal_id,
                false,
                $json
            );

            if ($portal === null) {
                IdealisticOfficeError::setSqlQuery(
                    null,
                    null,
                    IdealisticOfficeTable::PORTAL_INSTALLATIONS_TABLE,
                    array(
                        "deletion_date" => IdealisticOfficeReader::getCurrentDate(),
                        "deletion_time_zone" => IdealisticOfficeVariable::DEFAULT_TIME_ZONE
                    ),
                    array(
                        array("id", $id),
                    ),
                    null,
                    1
                );
            }
            return $portal;
        }
    }

    public static function createSessionId(
        int             $platformId,
        int|string|null $serverId,
        int|string|null $channelId,
        int|string|null $threadId
    ): int|string|null
    {
        $key = overflow_long($platformId);

        foreach (array($serverId, $channelId, $threadId) as $part) {
            if (is_numeric($part)) {
                $key = overflow_long($key * 31 + $part);
            } else {
                $key = overflow_long($key * 31 + string_to_integer($part, true));
            }
        }
        return $key;
    }

    /** @return IdealisticOfficePortalMessage[] */
    public static function getInstalledPortalMessages(int|array $platformID): array
    {
        $where = array(
            array("ip_address", null), // Assistant replies only
            array("is_completed", true),
            array("delivered_date", null),
            array("deletion_date", null),
            array("creation_date", ">=", IdealisticOfficeReader::getPastDate(IdealisticOfficeLimit::CHAT_PORTAL_MAX_PROCESSING_TIME))
        );

        if (is_int($platformID)) {
            $where[] = array("platform_id", $platformID);
        }
        $query = get_sql_query(
            IdealisticOfficeTable::PORTAL_HISTORY_TABLE,
            IdealisticOfficeQuery::INSTALLED_PORTAL_MESSAGE,
            $where,
            array(
                "ASC",
                "id"
            ),
            IdealisticOfficeLimit::MAX_PROCESSED_TASKS
        );

        if (empty($query)) {
            return array();
        }
        $array = array();
        $timezone = IdealisticOfficeVariable::DEFAULT_TIME_ZONE;
        $emptyAccount = new Account();
        $branding = array();

        foreach ($query as $row) {
            if (!is_int($platformID)
                && !in_array($row->platform_id, $platformID)) {
                continue;
            }

            if (!isset($branding[$row->portal_id])) {
                $portal = self::getPortal($row->portal_id, false, false);
                $branding[$row->portal_id] = $portal === null || $portal->showsBranding();
            }
            $array[] = new IdealisticOfficePortalMessage(
                $row->id,
                $row->portal_id,
                (int)$row->platform_id,
                $row->server_id,
                $row->channel_id,
                $row->channel_thread_id,
                $row->message_id,
                $row->user_id,
                $row->message,
                $row->attachment_bytes === null
                    ? null
                    : new IdealisticOfficeAttachment(
                    0,
                    $row->attachment_name,
                    $row->attachment_format,
                    null,
                    $row->attachment_bytes,
                    $row->attachment_x,
                    $row->attachment_y,
                    $row->attachment_analyzed_description,
                    $row->attachment_data,
                    null,
                    true,
                    $row->creation_date,
                    $timezone,
                    $emptyAccount
                ),
                $row->creation_date,
                $timezone,
                $branding[$row->portal_id]
            );
        }
        return $array;
    }

    public static function runInstalledPortal(
        int                         $platformId,
        int|string|null             $userId,
        int|string|null             $userNickName,
        int|string|null             $userDisplayName,
        int|string|null             $serverId,
        int|string|null             $channelId,
        int|string|null             $threadId,
        int|string|null             $messageId,
        int|string|null             $messageContent,
        ?IdealisticOfficeAttachment $attachment,
        bool                        $isAttachmentVoice = false,
        bool                        $process = true // False only records the message as context, without a reply
    ): IdealisticOfficeOutcome
    {
        $portal = self::getInstalledPortalRaw(
            $platformId,
            $serverId,
            $channelId,
            $threadId
        );

        if ($portal === null) {
            return new IdealisticOfficeOutcome(
                null,
                "No installed portal in this place.",
            );
        }
        if ($userId === null) {
            return new IdealisticOfficeOutcome(false, "User's ID could not be found.");
        }
        $user = IdealisticOfficeTeamInitiator::findUser(
            $platformId,
            $userId
        );

        if (!($user instanceof IdealisticOfficeUser)) {
            $user = null;
        }
        if (!array_key_exists($platformId, IdealisticOfficeAccessPlatform::getValidPlatforms())) {
            return new IdealisticOfficeOutcome(
                false,
                "The platform of your request is generally or temporarily not supported.",
                true,
                $user
            );
        }
        $sessionId = self::createSessionId(
            $platformId,
            $process ? $userId : "context:" . $userId, // Context-only messages must not count towards the sender's limits
            $serverId,
            $channelId,
            $threadId
        );

        if ($sessionId === null) {
            return new IdealisticOfficeOutcome(
                false,
                "Session ID could not be created.",
                true,
                $user
            );
        }
        self::preparePortalForExternalUse(
            $portal,
            $platformId,
            $serverId,
            $channelId,
            $threadId,
            $messageId,
            $userId,
            $userNickName,
            $userDisplayName
        );
        if (!$process) {
            return $portal->addHistoryWithoutProcessing(
                $sessionId,
                $messageContent === null ? null : (string)$messageContent
            );
        }
        return $portal->addHistory(
            $sessionId,
            null,
            $messageContent,
            $isAttachmentVoice ? $attachment : null,
            $isAttachmentVoice ? null : $attachment,
            $user?->getLanguageSelection(false)?->getLanguage() ?? IdealisticOfficeVariable::DEFAULT_LANGUAGE
        );
    }

    public static function installPortal(
        int             $platformId,
        int|string      $portalId,
        int|string|null $userId,
        int|string|null $serverId,
        int|string|null $channelId,
        int|string|null $threadId,
        int|string|null $messageId
    ): IdealisticOfficeOutcome
    {
        if ($userId === null) {
            return new IdealisticOfficeOutcome(false, "User's ID could not be found.");
        }
        $user = IdealisticOfficeTeamInitiator::findUser(
            $platformId,
            $userId
        );

        if ($user instanceof IdealisticOfficeOutcome) {
            return $user;
        }
        if (!$user->hasEstablishedAccess()) {
            return new IdealisticOfficeOutcome(false, "No team access established.", true, $user);
        }
        if (!array_key_exists($platformId, IdealisticOfficeAccessPlatform::getValidPlatforms())) {
            return new IdealisticOfficeOutcome(
                false,
                "The platform of your request is generally or temporarily not supported.",
                true,
                $user
            );
        }
        $portal = self::getInstalledPortalRaw(
            $platformId,
            $serverId,
            $channelId,
            $threadId
        );

        if ($portal !== null) {
            return new IdealisticOfficeOutcome(
                false,
                "The portal '" . $portal->getName() . "' is already installed in this place, uninstall it to install a different one.",
                true,
                $user
            );
        }
        $portal = self::getPortal(
            $portalId,
            true,
            false
        ) ?? self::getPortal(
            $portalId,
            false,
            false
        );

        if ($portal === null) {
            return new IdealisticOfficeOutcome(
                false,
                "Portal with this reference does not exist or is inaccessible to you.",
                true,
                $user
            );
        }
        $folder = $portal->getParentFolder();

        if ($folder === null) {
            return new IdealisticOfficeOutcome(false, "Portal's folder not found.", true, $user);
        }
        if (!$folder->canView($user)) {
            return new IdealisticOfficeOutcome(false, "You must be in the portal's folder to install it.", true, $user);
        }
        if ($portal->getPassword() !== null) {
            return new IdealisticOfficeOutcome(false, "You cannot install a portal with a password.", true, $user);
        }
        if ($portal->is18Plus()) {
            return new IdealisticOfficeOutcome(false, "You cannot install a portal that's for 18 plus users.", true, $user);
        }
        $timezone = $user->getTimezone(false);

        if (!IdealisticOfficeError::sqlInsert(
            $user->getTeam(),
            $user,
            IdealisticOfficeTable::PORTAL_INSTALLATIONS_TABLE,
            array(
                "team_id" => $portal->getTeam()->getId(),
                "platform_id" => $platformId,
                "portal_id" => $portal->getId(),
                "server_id" => $serverId,
                "channel_id" => $channelId,
                "thread_id" => $threadId,
                "message_id" => $messageId,
                "created_by" => $user->getId(),
                "creation_date" => IdealisticOfficeReader::getCurrentDate($timezone),
                "creation_time_zone" => $timezone->getTimeZone()
            )
        )) {
            return new IdealisticOfficeOutcome(false, "Portal failed to be installed.", true, $user);
        }
        return new IdealisticOfficeOutcome(true, "Portal successfully installed.", true, $user);
    }

    public static function uninstallPortal(
        int             $platformId,
        int|string|null $userId,
        int|string|null $serverId,
        int|string|null $channelId,
        int|string|null $threadId
    ): IdealisticOfficeOutcome
    {
        if ($userId === null) {
            return new IdealisticOfficeOutcome(false, "User's ID could not be found.");
        }
        $user = IdealisticOfficeTeamInitiator::findUser(
            $platformId,
            $userId
        );

        if ($user instanceof IdealisticOfficeOutcome) {
            return $user;
        }
        if (!$user->hasEstablishedAccess()) {
            return new IdealisticOfficeOutcome(false, "No team access established.", true, $user);
        }
        $portal = self::getInstalledPortalRaw(
            $platformId,
            $serverId,
            $channelId,
            $threadId
        );

        if ($portal === null) {
            return new IdealisticOfficeOutcome(
                false,
                "No installed portal in this place.",
                true,
                $user
            );
        }
        $folder = $portal->getParentFolder();

        if ($folder === null) {
            return new IdealisticOfficeOutcome(false, "Portal's folder not found.", true, $user);
        }
        if (!$folder->canView($user)) {
            return new IdealisticOfficeOutcome(false, "You must be in the portal's folder to uninstall it.", true, $user);
        }
        $portal = self::getInstalledPortalRaw(
            $platformId,
            $serverId,
            $channelId,
            $threadId,
            true
        );

        if ($portal === null) {
            return new IdealisticOfficeOutcome(
                false,
                "No installed portal in this place.",
                true,
                $user
            );
        }
        $timezone = $user->getTimezone(false);

        if (!IdealisticOfficeError::setSqlQuery(
            $user->getTeam(),
            $user,
            IdealisticOfficeTable::PORTAL_INSTALLATIONS_TABLE,
            array(
                "deleted_by" => $user->getId(),
                "deletion_date" => IdealisticOfficeReader::getCurrentDate($timezone),
                "deletion_time_zone" => $timezone->getTimeZone()
            ),
            array(
                array("id", $portal),
            ),
            null,
            1
        )) {
            return new IdealisticOfficeOutcome(
                false,
                "Portal failed to be uninstalled.",
                true,
                $user
            );
        }
        return new IdealisticOfficeOutcome(true, "Portal successfully uninstalled.", true, $user);
    }

    public static function preparePortalForExternalUse(
        IdealisticOfficePortal &$portal,
        int                    $platformId,
        int|string|null        $serverId,
        int|string|null        $channelId,
        int|string|null        $threadId,
        int|string|null        $messageId,
        int|string|null        $userId,
        int|string|null        $userNickName,
        int|string|null        $userDisplayName
    ): void
    {
        $key = overflow_long($platformId);

        foreach (array($serverId, $channelId, $threadId) as $part) {
            if (is_numeric($part)) {
                $key = overflow_long($key * 31 + $part);
            } else {
                $key = overflow_long($key * 31 + string_to_integer($part, true));
            }
        }
        $portal->setThreadId($key);
        $portal->setPlatformId($platformId);
        $portal->setAccess(true);
        $portal->setUniqueSessionChecks(false);
        $portal->setServerId($serverId);
        $portal->setChannelId($channelId);
        $portal->setChannelThreadId($threadId);
        $portal->setMessageId($messageId);
        $portal->setUserId($userId);
        $portal->setUserNickName($userNickName);
        $portal->setUserDisplayName($userDisplayName);
    }

}
