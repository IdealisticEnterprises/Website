<?php

use function React\Promise\reject;
use function React\Promise\resolve;

class IdealisticOfficePromptTeamInfo implements PhpAsyncSerializable
{

    private const
        USER_REFERENCE = "user",
        RELATED_HISTORY_RATIO = 0.75,
        KEY_UPLOADED_ATTACHMENTS = "uploaded_by_contact_attachments",
        KEY_GENERATED_ATTACHMENTS = "generated_by_software_attachments",
        KEY_USER_PROMPT = "user_prompt",
        KEY_SOFTWARE_RESPONSES = "software_responses",
        KEY_ANALYZED_LINKS = "analyzed_links",
        KEY_WEB = "web",
        KEY_TEAM = "team";

    private static ?object $object = null;

    /**
     * @param IdealisticOfficeHistory[] $histories
     * @param IdealisticOfficeHistory[]|null $redundant
     */
    private static function getHistory(
        array  $histories,
        bool   $attachments = true,
        bool   $links = true,
        ?array $redundant = null
    ): ?object
    {
        $historyCount = sizeof($histories);

        if ($historyCount == 0) {
            return null;
        }
        if (empty($redundant)) {
            $ids = IdealisticOfficeHistoryIndependent::getIDs($histories, false);
        } else {
            $ids = IdealisticOfficeHistoryIndependent::getIDs($histories, true);

            foreach ($redundant as $history) {
                unset($ids[$history->getId()]);
            }
            if (empty($ids)) {
                return null;
            }
            $ids = array_keys($ids);
        }
        $historyObject = new stdClass();
        $promptsData = $historyCount > 1
            ? IdealisticOfficeHistoryIndependent::getHistoryFromSubTable(
                IdealisticOfficeTable::HISTORY_PROMPTS_TABLE,
                IdealisticOfficeQuery::HISTORY_PROMPT,
                $ids,
                false,
                min(
                    $historyCount * IdealisticOfficeLimit::MAX_EXTENSIONS_PER_OBJECT,
                    IdealisticOfficeLimit::MAX_PROCESSED_ROWS
                )
            )
            : null;
        $websData = $historyCount > 1
            ? IdealisticOfficeHistoryIndependent::getHistoryFromSubTable(
                IdealisticOfficeTable::HISTORY_WEB_DATA_TABLE,
                IdealisticOfficeQuery::WEB_DATA,
                $ids,
                false,
                min(
                    $historyCount * IdealisticOfficeLimit::MAX_EXTENSIONS_PER_OBJECT,
                    IdealisticOfficeLimit::MAX_PROCESSED_ROWS
                )
            )
            : null;

        if ($attachments) {
            $attachmentsData = $historyCount > 1
                ? IdealisticOfficeHistoryIndependent::getHistoryFromSubTable(
                    IdealisticOfficeTable::HISTORY_USER_ATTACHMENTS_TABLE,
                    IdealisticOfficeQuery::PARTIAL_ATTACHMENT,
                    $ids,
                    false,
                    min(
                        $historyCount * IdealisticOfficeLimit::MAX_EXTENSIONS_PER_OBJECT,
                        IdealisticOfficeLimit::MAX_PROCESSED_ROWS
                    )
                )
                : null;
            $createdAttachmentsData = $historyCount > 1
                ? IdealisticOfficeHistoryIndependent::getHistoryFromSubTable(
                    IdealisticOfficeTable::HISTORY_CREATED_ATTACHMENTS_TABLE,
                    IdealisticOfficeQuery::CREATED_PARTIAL_ATTACHMENT,
                    $ids,
                    false,
                    min(
                        $historyCount * IdealisticOfficeLimit::MAX_EXTENSIONS_PER_OBJECT,
                        IdealisticOfficeLimit::MAX_PROCESSED_ROWS
                    )
                )
                : null;
        } else {
            $attachmentsData = array();
            $createdAttachmentsData = array();
        }
        if ($links) {
            $linksData = $historyCount > 1
                ? IdealisticOfficeHistoryIndependent::getHistoryFromSubTable(
                    IdealisticOfficeTable::HISTORY_LINKS_TABLE,
                    IdealisticOfficeQuery::HISTORY_LINK_PARTIAL,
                    $ids,
                    false,
                    min(
                        $historyCount * IdealisticOfficeLimit::MAX_EXTENSIONS_PER_OBJECT,
                        IdealisticOfficeLimit::MAX_PROCESSED_ROWS
                    )
                )
                : null;
        } else {
            $linksData = array();
        }
        $repliesData = $historyCount > 1
            ? IdealisticOfficeHistoryIndependent::getHistoryFromSubTable(
                IdealisticOfficeTable::HISTORY_REPLIES_TABLE,
                IdealisticOfficeQuery::HISTORY_REPLY,
                $ids,
                false,
                min($historyCount * 3, IdealisticOfficeLimit::MAX_PROCESSED_ROWS)
            ) : null;

        foreach ($histories as $history) {
            $replies = $history->getReplies(
                null,
                $repliesData
            );

            if (empty($replies)) {
                $repliesArray = null;
            } else {
                $repliesArray = array();
                $outdatedURL = "[OUTDATED URL]";

                foreach ($replies as $reply) {
                    if (empty($reply->getAnswer())) {
                        continue;
                    }
                    $confirmed = $reply->getTaskId() === IdealisticOfficePromptTask::TEAM_INFO
                        ? " (UNCONFIRMED)"
                        : " (CONFIRMED)";
                    $repliesArray[$reply->getTaskId() . $confirmed] = replace_url_by_prefix(
                        $reply->getAnswer(),
                        IdealisticOfficeVariable::INPUT_OUTPUT_URL,
                        $outdatedURL
                    );
                }
            }
            $individualObject = new stdClass();
            $individualObject->{self::KEY_USER_PROMPT} = implode(
                IdealisticOfficeVariable::DEFAULT_TEXT_SEPARATOR,
                $history->getPrompts(
                    $promptsData
                )
            );

            if (empty($individualObject->{self::KEY_USER_PROMPT})) {
                $individualObject->{self::KEY_USER_PROMPT} = null;
            }
            $webData = $history->getWebData(
                $websData
            );

            if ($webData !== null) {
                $newWebData = new stdClass();
                $newWebData->query = $webData->getQuery();
                $newWebData->results = $webData->getResults();
                $individualObject->{self::KEY_WEB} = $newWebData;
            }
            if (!empty($repliesArray)) {
                $individualObject->{self::KEY_SOFTWARE_RESPONSES} = $repliesArray;
            }
            if ($attachments) {
                $individualObject->{self::KEY_UPLOADED_ATTACHMENTS} = $history->getPartialAttachments(
                    null,
                    $attachmentsData
                );

                if (empty($individualObject->{self::KEY_UPLOADED_ATTACHMENTS})) {
                    unset($individualObject->{self::KEY_UPLOADED_ATTACHMENTS});
                }
                $individualObject->{self::KEY_GENERATED_ATTACHMENTS} = $history->getCreatedPartialAttachments(
                    null,
                    $createdAttachmentsData
                );

                if (empty($individualObject->{self::KEY_GENERATED_ATTACHMENTS})) {
                    unset($individualObject->{self::KEY_GENERATED_ATTACHMENTS});
                }
            }
            if ($links) {
                $analyzedLinksList = $history->getPartialLinks(
                    $linksData,
                    null,
                    false
                );

                if (!empty($analyzedLinksList)) {
                    $individualObject->{self::KEY_ANALYZED_LINKS} = array();

                    foreach ($analyzedLinksList as $link) {
                        $individualObject->{self::KEY_ANALYZED_LINKS}[$link->getUrl()] = $link->getAnalyzedDescription();
                    }
                }
            }

            if (empty(get_object_vars($individualObject))) {
                continue;
            }
            $historyObject->{$history->getCreationDate() . " " . $history->getCreationDateTimeZone()} = $individualObject;
        }
        return $historyObject;
    }

    private static function getCapabilities(IdealisticOfficePrompt $prompt): object
    {
        $object = new stdClass();
        $capabilities = $prompt->getCache()->getEnabledTasks();

        foreach ($capabilities as $key => $capability) {
            $class = IdealisticOfficePrompt::CLASS_NAME . $capability;

            if (method_exists($class, "fields")) {
                $fields = $class::fields($prompt);

                if (!empty($fields)) {
                    if (method_exists($class, "blacklistedFields")) {
                        $blacklistedFields = $class::blacklistedFields($prompt);
                    } else {
                        $blacklistedFields = array();
                    }
                    $fields = IdealisticOfficeReader::readFields($fields, $blacklistedFields);

                    if ($fields !== null) {
                        $capabilities[$key] = $fields;

                        if (method_exists($class, "description")) {
                            $description = $class::description($prompt);

                            if (!empty($description)) {
                                $capabilities[$key] = array(
                                    "description" => $description,
                                    "fields" => $fields
                                );
                            }
                        }
                    } else if (method_exists($class, "description")) {
                        $description = $class::description($prompt);

                        if (!empty($description)) {
                            $capabilities[$key] = array(
                                "description" => $description
                            );
                        } else {
                            unset($capabilities[$key]);
                        }
                    } else {
                        unset($capabilities[$key]);
                    }
                } else if (method_exists($class, "description")) {
                    $description = $class::description($prompt);

                    if (!empty($description)) {
                        $capabilities[$key] = array(
                            "description" => $description
                        );
                    } else {
                        unset($capabilities[$key]);
                    }
                } else {
                    unset($capabilities[$key]);
                }
            } else if (method_exists($class, "description")) {
                $description = $class::description($prompt);

                if (!empty($description)) {
                    $capabilities[$key] = array(
                        "description" => $description
                    );
                } else {
                    unset($capabilities[$key]);
                }
            } else {
                unset($capabilities[$key]);
            }
        }
        $object->instructions = array(
            "Capabilities requested that are not listed should be accompanied with a warning of no guarantee of completion",
            "Explain simplistically in text without JSON",
            "Avoid numbers when text description exists",
            "Expect the user to use names where an ID is mentioned",
            "Avoid over-explaining",
            "Provide lexical examples"
        );
        $object->list = array_values($capabilities);
        $object->count = sizeof($capabilities);
        return $object;
    }

    private static function getSoftware(IdealisticOfficePrompt $prompt): object
    {
        $team = $prompt->getTeam();
        $owner = $prompt->getCache()->getUser($team->getOwnerId());
        $object = new stdClass();
        $groupWorld = "team";

        $object->full_name = IdealisticOfficeVariable::APPLICATION_NAME;
        $object->short_name = IdealisticOfficeVariable::APPLICATION_NAME;
        $object->company_details = IdealisticOfficeVariable::COMPANY_DETAILS;
        $object->created_by = IdealisticOfficeVariable::COMPANY_NAME;
        $object->description = "For more information, visit" . IdealisticOfficeVariable::COMPANY_WEBSITE_URL;
        $object->functionality = "Works by identifying information in user chat prompts with the use of AI. No use of menu, buttons, or other UI elements";
        $object->ownership =
            "The " . IdealisticOfficeVariable::APPLICATION_NAME
            . " software does not belong to the contacts of the " . $groupWorld
            . ($owner === null ? "" : " owned by '" . $owner->getDisplayName() . " (" . ($owner->getEmail() ?? "unknown email") . ")'")
            . ". It is a third-party software that is used by the contacts of the "
            . $groupWorld . " to help manage their tasks";

        //

        $object->capabilities = self::getCapabilities($prompt);

        //

        $platforms = IdealisticOfficeAccessPlatform::getValidPlatforms();

        if (!empty($platforms)) {
            foreach ($platforms as $key => $platform) {
                if ($key < 0) {
                    unset($platforms[$key]);
                }
            }
            $object->available_on_platforms = array_values($platforms);
        }

        //

        $object->links = new stdClass();
        $object->links->company_website = IdealisticOfficeVariable::COMPANY_WEBSITE_URL;
        $object->links->official_website = IdealisticOfficeVariable::COMPANY_WEBSITE_URL;
        $object->links->terms_of_service = IdealisticOfficeVariable::TERMS_OF_USE_URL;
        $object->links->terms_of_use = IdealisticOfficeVariable::TERMS_OF_USE_URL;
        $object->links->refund_policy = IdealisticOfficeVariable::TERMS_OF_USE_URL;
        $object->links->privacy_policy = IdealisticOfficeVariable::PRIVACY_POLICY_URL;
        $object->links->download_your_data = IdealisticOfficeVariable::ACCOUNT_DOWNLOAD_INPUT_OUTPUT_URL;
        $object->links->portal_external_script = IdealisticOfficeVariable::PORTAL_EXTERNAL_SCRIPT;
        $object->links->user_data_deletion_instructions = IdealisticOfficeVariable::USER_DATA_DELETION_INSTRUCTIONS_URL;
        $object->links->pricing_website = IdealisticOfficeVariable::COMPANY_WEBSITE_URL;
        $object->links->software_usage = new stdClass();
        $object->links->software_usage->instagram = IdealisticOfficeVariable::INSTAGRAM_APP_URL;
        $object->links->software_usage->messenger = IdealisticOfficeVariable::MESSENGER_APP_URL;
        $object->links->software_usage->discord = IdealisticOfficeVariable::DISCORD_APP_URL;
        $object->links->software_usage->telegram = IdealisticOfficeVariable::TELEGRAM_APP_URL;
        $object->links->software_usage->whatsapp = IdealisticOfficeVariable::WHATSAPP_APP_URL;
        $object->links->software_usage->android = IdealisticOfficeVariable::ANDROID_APP_URL;
        $object->links->software_usage->iOS = IdealisticOfficeVariable::IOS_APP_URL;
        $object->links->software_usage->iPadOS = IdealisticOfficeVariable::IPADOS_APP_URL;
        $object->links->software_usage->email = IdealisticOfficeVariable::REPLY_EMAIL;

        //

        $object->pricing = new stdClass();
        $object->pricing->more_information = "Visit " . IdealisticOfficeVariable::COMPANY_WEBSITE_URL;
        $object->pricing->type = IdealisticOfficePlans::TYPE;
        $object->pricing->vat = IdealisticOfficePlans::VAT;
        $object->pricing->free_trial = IdealisticOfficePlans::FREE_TRIAL . " on " . IdealisticOfficePlans::NAMES[0] . " only";
        $object->pricing->disclaimer = IdealisticOfficePlans::DISCLAIMER;
        $object->pricing->plans = array();

        foreach (IdealisticOfficePlans::PLANS as $plan) {
            $object->pricing->plans[] = number_format($plan["price"], 2, '.', '') . " "
                . IdealisticOfficePlans::CURRENCY
                . " / " . $plan["credits"]
                . " credits";
        }
        if (IdealisticOfficeCredit::ALLOW_PAYPAL) {
            if (!$prompt->getCache()->hasUnlimitedCredits()
                && !$prompt->getCache()->isSubscriber()) {
                if ($prompt->getCache()->isTaskEnabled(IdealisticOfficePromptTask::MANAGE_PAYPAL_EMAILS)) {
                    $object->pricing->alternative = "Alternatively, you can purchase credits instead of a subscription plan";
                    $object->pricing->credits = new stdClass();
                    $object->pricing->credits->packages = IdealisticOfficeCredit::getPackages();

                    if (empty($team->getPaypalEmails(1, false))) {
                        $object->pricing->credits->warning = "Must add at least one PayPal email address to process future payments";
                    }
                } else {
                    $object->pricing->free_trial = true;
                }
            }
        } else if (!$prompt->getCache()->hasUnlimitedCredits()
            && !$prompt->getCache()->isSubscriber()) {
            $object->pricing->free_trial = true;
        }
        return $object;
    }

    public static function getTime(IdealisticOfficeTimezone $timezone): object
    {
        $object = new stdClass();
        $todayTime = IdealisticOfficeReader::getCurrentDateTime($timezone);
        $object->time_zone = $timezone->getTimeZone();
        $object->year = $todayTime->format("Y");
        $object->month = $todayTime->format("m");
        $object->day = $todayTime->format("d");
        $object->hour = $todayTime->format("H");
        $object->minute = $todayTime->format("i");
        $object->second = $todayTime->format("s");
        return $object;
    }

    private static function getRelatedInformation(IdealisticOfficePrompt $prompt, object $object): object
    {
        $related = round(IdealisticOfficeLimit::MAX_EXTENSIONS_PER_OBJECT * self::RELATED_HISTORY_RATIO);
        $recentHistory = $prompt->getUserHistory(0, IdealisticOfficeLimit::MAX_EXTENSIONS_PER_OBJECT);
        $relatedHistory = array();
        $count = 0;
        $limit = 3;

        foreach ($recentHistory as $history) {
            $relatedHistory = array_merge(
                $relatedHistory,
                IdealisticOfficeHistoryIndependent::getRelated($history, true, round($related / $limit))
            );
            $count++;

            if ($count === $limit) {
                break;
            }
        }
        $object->{self::USER_REFERENCE}->recent_history = IdealisticOfficePromptTeamInfo::getHistory(
            $recentHistory
        );
        $object->{self::USER_REFERENCE}->related_history = IdealisticOfficePromptTeamInfo::getHistory(
            $relatedHistory,
            false, // Brought in alternative way
            false, // Brought in alternative way
            $recentHistory
        );
        return $object;
    }

    public static function getObject(
        IdealisticOfficePrompt $prompt,
        bool                   $software = true
    ): object
    {
        if (self::$object !== null) {
            return self::$object;
        }
        $object = new stdClass();

        if ($software) {
            $object->software = IdealisticOfficePromptTeamInfo::getSoftware($prompt);
        }
        $object->{self::USER_REFERENCE} = new stdClass();
        $object->{self::USER_REFERENCE}->time = IdealisticOfficePromptTeamInfo::getTime($prompt->getUser()->getTimeZone(false));
        $object->{self::USER_REFERENCE}->platform = IdealisticOfficeStrings::getPlatformFromID($prompt->getPlatformId());
        $object->{self::USER_REFERENCE}->email = $prompt->getUser()->getEmail() ?? "unknown";

        if ($prompt->getCache()->isTaskEnabled(IdealisticOfficePromptTask::MANAGE_SHORTCUTS)) {
            $object->{self::USER_REFERENCE}->shortcuts = IdealisticOfficeReader::getShortcut($prompt->getUser()->getShortcuts(0, false), $prompt);
        }
        if ($prompt->getCache()->isTaskEnabled(IdealisticOfficePromptTask::MANAGE_SCHEDULER)) {
            $object->{self::USER_REFERENCE}->scheduled_tasks = IdealisticOfficeReader::getScheduledTasks($prompt->getUser()->getScheduledTasks(0, false), $prompt);
        }
        if ($prompt->getCache()->isTaskEnabled(IdealisticOfficePromptTask::MANAGE_FOLDERS)) {
            $selected = $prompt->getCache()->getSelectedFolder();

            if ($selected !== null) {
                $object->{self::USER_REFERENCE}->selected_folder = [
                    'id' => $selected->getId(),
                    'name' => $selected->getName()
                ];
            } else {
                $object->{self::USER_REFERENCE}->selected_folder = null;
            }
        }
        if (!empty($prompt->getCache()->getWebQuery())
            && !empty($prompt->getCache()->getWebResults())) {
            $object->{self::USER_REFERENCE}->{self::KEY_WEB} = new stdClass();
            $object->{self::USER_REFERENCE}->{self::KEY_WEB}->query = $prompt->getCache()->getWebQuery();
            $object->{self::USER_REFERENCE}->{self::KEY_WEB}->results = $prompt->getCache()->getWebResults();
        }
        $object->{self::USER_REFERENCE}->settings = array();

        foreach (IdealisticOfficeSettings::DEFAULTS as $key => $value) {
            $object->{self::USER_REFERENCE}->settings[$key] = $prompt->getUser()->isSettingEnabled($key);
        }
        $object = self::getRelatedInformation($prompt, $object);
        $object->{self::USER_REFERENCE}->last_received_notifications = array();
        $notifications = true ? array() : $prompt->getUser()->getNotifications(true, IdealisticOfficeLimit::MAX_EXTENSIONS_PER_OBJECT);

        if (!empty($notifications)) {
            foreach ($notifications as $notification) {
                if (!$notification->hasMessage()
                    || IdealisticOfficeReader::getPastDate(
                        "7 days",
                        $notification->getTimeZone()
                    ) > $notification->getCreationDate()) {
                    continue;
                }
                $object->{self::USER_REFERENCE}->last_received_notifications[] = $notification->getRawMessage();
            }
        }
        if (!$prompt->getUser()->hasEmail()) {
            $object->{self::USER_REFERENCE}->system_state = "Type only your email to register yourself in " . IdealisticOfficeVariable::APPLICATION_NAME . " and maintain your account across this temporary session.";
        } else {
            $object->{self::USER_REFERENCE}->system_state = "User is registered and has access to the software via the email: " . $prompt->getUser()->getEmail();
        }
        $object->{self::KEY_TEAM} = IdealisticOfficeReader::getTeam($prompt);
        return self::$object = $object;
    }

    private static function waitForTasks(
        IdealisticOfficePrompt $prompt
    ): void
    {
        $waitFor = array(
            IdealisticOfficePromptTask::NEW_USERS,
            IdealisticOfficePromptTask::WEB_DATA,
            IdealisticOfficePromptTask::ATTACHMENT_ANALYSIS
        );

        if (!empty($prompt->getCache()->getFoundURLs())) {
            $waitFor[] = IdealisticOfficePromptTask::LINK_ANALYSIS;
        }
        $prompt->waitForTasks($waitFor);
    }

    public static function description(IdealisticOfficePrompt $prompt): ?string
    {
        return "[SECONDARY FEATURE] Access all important information in one place.";
    }

    public static function run(
        IdealisticOfficePrompt $prompt
    ): void
    {
        self::waitForTasks($prompt);
        $prompt->runTask(function () use ($prompt) {
            try {
                $teamObject = IdealisticOfficePromptTeamInfo::getObject(
                    $prompt
                );
                $teamObject->reply_instructions = new stdClass();
                $teamObject->reply_instructions->default = array(
                    "ROLE: You are a conversational interface. ALWAYS start your response with a natural, human-friendly sentence acknowledging the user's request.",
                    "INTENT BOUNDARY: After your opening sentence, if the user asked a read-only question, list the requested items and IMMEDIATELY STOP. DO NOT hallucinate actions like 'created' or 'updated'.",
                    "IN-BAND SIGNALING: EVERY single data entity you mention MUST include its exact type and ID. Format strictly as: '[Entity Type] Entity Name (Ref: ID)'.",
                    "If the user provides invalid parameters, reply with ONE exact example of the text they need to send.",
                    "Silently interpret typos. Never point out spelling mistakes.",
                    "Output natural conversational text only. No raw JSON, code, or system-style prefixes.",
                    "ATTACHMENT TARGETING (CRITICAL): If the JSON payload contains '" . self::KEY_UPLOADED_ATTACHMENTS . "' or '" . self::KEY_GENERATED_ATTACHMENTS . "', YOU MUST treat them as the absolute primary context for the user's prompt. Any vague pronouns (e.g., 'it', 'this', 'that'), requests to 'look', 'analyze', 'explain', or short/vague questions refer directly to these attachments. Base your answer entirely on their pre-analyzed content.",
                    "WEB SEARCH OVERRIDE (CRITICAL): The system automatically generates a '" . self::KEY_WEB . "' (web search results) object. If the user's prompt implies they are talking about a provided attachment/file, or if the user's prompt lacks explicit search intent and an attachment exists in the JSON, YOU MUST COMPLETELY IGNORE THE '" . self::KEY_WEB . "' OBJECT. Do not mention web links, external sites, or unrelated search results.",
                    "BLIND/DEAF RESTRICTION: NEVER state or imply that you cannot see images, open files, or hear audio. Read the pre-analyzed text context and act confidently as if you are directly perceiving the files.",
                    "Prioritize explaining the main features first, deriving your explanation directly from the attachment context if applicable.",
                    "Explain everything like you would to a 5-year-old, but DO NOT omit important facts from the attachments just to simplify. Avoid technical jargon and over-explaining.",
                    "MARKETING MANDATE: Naturally weave in a brief, friendly mention of your advanced capabilities, speed, or how much effort you save the user. Subtly encourage them to rely on you for more tasks, framing your helpfulness as a core feature without sounding like a robotic advertisement."
                );

                if ($prompt->getCache()->isTaskEnabled(IdealisticOfficePromptTask::MANAGE_INSTRUCTIONS)) {
                    $teamObject->reply_instructions->custom = $prompt->getTeam()->getInstructions(0, false);
                }
                $chatHistoryMessages = array();

                // Dynamically build the chronological chat history from the already-fetched object
                if (isset($teamObject->{self::USER_REFERENCE}->recent_history)
                    && is_object($teamObject->{self::USER_REFERENCE}->recent_history)) {
                    $historyItems = get_object_vars($teamObject->{self::USER_REFERENCE}->recent_history);
                    $historyItems = array_reverse($historyItems); // Set oldest-to-newest

                    foreach ($historyItems as $data) {
                        if (isset($data->{self::KEY_USER_PROMPT})
                            && $data->{self::KEY_USER_PROMPT} !== "") {
                            $chatHistoryMessages[] = array(
                                "role" => "user",
                                "content" => $data->{self::KEY_USER_PROMPT}
                            );
                        }
                        if (isset($data->{self::KEY_SOFTWARE_RESPONSES})
                            && !empty($data->{self::KEY_SOFTWARE_RESPONSES})) {
                            $replyContent = implode(IdealisticOfficeVariable::DEFAULT_TEXT_SEPARATOR, (array)$data->{self::KEY_SOFTWARE_RESPONSES});

                            if ($replyContent !== "") {
                                $chatHistoryMessages[] = array(
                                    "role" => "assistant",
                                    "content" => $replyContent
                                );
                            }
                        }
                    }
                    // Unset the history block from the system object so it doesn't duplicate and confuse the AI
                    unset($teamObject->{self::USER_REFERENCE}->recent_history);
                }

                $arguments = array(
                    "messages" => array_merge(
                        array(
                            array(
                                "role" => "system",
                                "content" => IdealisticOfficeReader::jsonObject(
                                    $teamObject,
                                    false
                                )
                            )
                        ),
                        $chatHistoryMessages, // Inject the conversational turns here
                        array(
                            array(
                                "role" => "user",
                                "content" => IdealisticOfficeReader::jsonObject(
                                    IdealisticOfficePromptTeamInfo::getHistory(
                                        array($prompt->getHistory()),
                                        true,
                                        true,
                                        null
                                    ),
                                    false
                                )
                            )
                        )
                    )
                );

                $AI = new IdealisticOfficeAI(
                    $prompt,
                    IdealisticOfficeVariable::TASK_AI_CHEAP_MODEL
                );
                $AI->adjustCertainty(
                    $arguments
                );
                return $AI->getReactResult(
                    $arguments
                )->then(function ($result) use ($prompt) {
                    if (empty($result)) {
                        return resolve(null);
                    }
                    $result = trim($result);

                    if (empty($result)) {
                        return resolve(null);
                    }
                    $prompt->getCache()->setAiReply($result);
                    return resolve(
                        new IdealisticOfficeOutcome(
                            true,
                            $result,
                            false,
                            false
                        )
                    );
                });
            } catch (Throwable $e) {
                return reject($e);
            }
        },
            null
        );
    }

}