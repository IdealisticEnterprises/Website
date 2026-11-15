<?php


class IdealisticOfficeReader implements PhpAsyncSerializable
{

    private static array $tzCache = [];

    public static function getSystemReport(IdealisticOfficePrompt $prompt): array
    {
        $array = array();

        if ($prompt->getCache()->isTaskEnabled(IdealisticOfficePromptTask::MANAGE_FOLDERS)) {
            $folders = $prompt->getCache()->getFolders();
            $folderInstructions = array();

            if (!empty($folders)) {
                foreach ($folders as $folder) {
                    $folderInstructions[] = array(
                        "id" => $folder->getId(),
                        "name" => substr($folder->getName(), 0, IdealisticOfficeLimit::T64_LENGTH),
                        "comment_ids" => $folder->getCommentIDs()
                    );
                }
            }
            $array["folders"] = $folderInstructions;
        }
        if ($prompt->getCache()->isTaskEnabled(IdealisticOfficePromptTask::MANAGE_PORTALS)) {
            $portals = $prompt->getTeam()->getPortals(0, false);
            $portalInstructions = array();

            if (!empty($portals)) {
                foreach ($portals as $portal) {
                    $portalInstructions[] = array(
                        "id" => $portal->getId(),
                        "name" => substr($portal->getName(), 0, IdealisticOfficeLimit::T64_LENGTH)
                    );
                }
            }
            $removedPortals = $prompt->getTeam()->getPortals(0, false, true);
            $removedPortalInstructions = array();

            if (!empty($removedPortals)) {
                foreach ($removedPortals as $removedPortal) {
                    $removedPortalInstructions[] = array(
                        "id" => $removedPortal->getId(),
                        "name" => substr($removedPortal->getName(), 0, IdealisticOfficeLimit::T64_LENGTH)
                    );
                }
            }
            $array["portals"] = $portalInstructions;
            $array["removed_portals"] = $removedPortalInstructions;
        }
        $users = $prompt->getCache()->getUsers();
        $userInstructions = array();

        if (!empty($users)) {
            foreach ($users as $user) {
                $userInstructions[] = array(
                    "id" => $user->getId(),
                    "name" => $user->getDisplayName(),
                );
            }
        }
        $array["contacts"] = $userInstructions;

        if ($prompt->getCache()->isTaskEnabled(IdealisticOfficePromptTask::MANAGE_ROLES)) {
            $roles = $prompt->getCache()->getRoles();
            $roleInstructions = array();

            if (!empty($roles)) {
                foreach ($roles as $role) {
                    $roleInstructions[] = array(
                        "id" => $role->getId(),
                        "name" => $role->getName(),
                    );
                }
            }
            $removedRoles = $prompt->getTeam()->getRoles(false, true);
            $removedRoleInstructions = array();

            if (!empty($removedRoles)) {
                foreach ($removedRoles as $removedRole) {
                    $removedRoleInstructions[] = array(
                        "id" => $removedRole->getId(),
                        "name" => $removedRole->getName(),
                    );
                }
            }
            $array["roles"] = $roleInstructions;
            $array["removed_roles"] = $removedRoleInstructions;
        }
        if ($prompt->getCache()->isTaskEnabled(IdealisticOfficePromptTask::MANAGE_SCHEDULER)) {
            $scheduledTasks = $prompt->getUser()->getScheduledTasks(0, false);
            $scheduledTaskInstructions = array();

            if (!empty($scheduledTasks)) {
                foreach ($scheduledTasks as $item) {
                    $scheduledTaskInstructions[] = array(
                        "id" => $item->getId(),
                        "query" => substr($item->getCreationReason(), 0, IdealisticOfficeLimit::T64_LENGTH)
                    );
                }
            }
            $deletedScheduledTasks = $prompt->getUser()->getScheduledTasks(0, false, true);
            $deletedScheduledTaskInstructions = array();

            if (!empty($deletedScheduledTasks)) {
                foreach ($deletedScheduledTasks as $item) {
                    $deletedScheduledTaskInstructions[] = array(
                        "id" => $item->getId(),
                        "query" => substr($item->getCreationReason(), 0, IdealisticOfficeLimit::T64_LENGTH)
                    );
                }
            }
            $array["scheduled_tasks"] = $scheduledTaskInstructions;
            $array["removed_scheduled_tasks"] = $deletedScheduledTaskInstructions;
        }
        if ($prompt->getCache()->isTaskEnabled(IdealisticOfficePromptTask::MANAGE_PAYPAL_EMAILS)) {
            $paypalEmails = $prompt->getTeam()->getPayPalEmails(0, false);
            $paypalEmailInstructions = array();

            if (!empty($paypalEmails)) {
                foreach ($paypalEmails as $paypalEmail) {
                    $paypalEmailInstructions[] = array(
                        "id" => $paypalEmail->getId(),
                        "email_address" => $paypalEmail->getEmailAddress(),
                    );
                }
            }
            $array["paypal_emails"] = $paypalEmailInstructions;
        }
        if ($prompt->getCache()->isTaskEnabled(IdealisticOfficePromptTask::MANAGE_SHORTCUTS)) {
            $shortcuts = $prompt->getUser()->getShortcuts(0, false);
            $shortcutInstructions = array();

            if (!empty($shortcuts)) {
                foreach ($shortcuts as $shortcut) {
                    $shortcutInstructions[] = array(
                        "id" => $shortcut->getId(),
                        "word" => $shortcut->getWord(),
                        "replacement" => substr($shortcut->getReplacement(), 0, IdealisticOfficeLimit::T64_LENGTH)
                    );
                }
            }
            $array["shortcuts"] = $shortcutInstructions;
        }
        return $array;
    }

    public static function getAttachment(
        bool                               $showData,
        AbstractIdealisticOfficeAttachment $attachment,
        bool                               $url = false
    ): array
    {
        $data = [];

        if ($attachment->getId() !== null) {
            $data['id'] = $attachment->getId();
        }
        $data['bytes'] = $attachment->getBytes();
        $data['time'] = [];
        $data['time']['date'] = $attachment->getCreationDate();
        $data['time']['timezone'] = $attachment->getCreationTimeZone();
        $data['creator'] = $attachment->getCreator()?->getDisplayName() ?? null;

        if ($attachment->isUserUploaded()) {
            $data["user_uploaded"] = true;
        } else {
            $data['software_generated'] = true;
        }

        $name = $attachment->getName();
        if ($name !== null) {
            $data['name'] = $name;
        }

        $userPrompt = $attachment->getUserPrompt();
        $dataDescription = !empty($userPrompt)
            ? $userPrompt
            : $attachment->getAnalyzedDescription();

        if ($dataDescription !== null) {
            $data['description'] = $dataDescription;
        }

        if ($attachment->isImage()) {
            $data['image'] = [
                'format' => $attachment->getSimpleFormat(),
                'x_size' => $attachment->getXSize(),
                'y_size' => $attachment->getYSize(),
                'xy_ratio' => $attachment->getImageRatio(2)
            ];
        } else {
            $data['format'] = $attachment->getFormat();
        }

        $data['data'] = [];

        if ($attachment->getId() === null) {
            $data['data']['hash'] = $attachment->getDataHash();
        }

        if ($showData && $attachment instanceof IdealisticOfficeAttachment) {
            $decodedData = $attachment->getDecodedData();

            if (!empty($decodedData)) {
                $data['data']['content'] = $decodedData;
            } else {
                $data['url'] = $attachment->getUrl(false, $url);
            }
        } else {
            $data['url'] = $attachment->getUrl(false, $url);
        }

        if (empty($data['data'])) {
            unset($data['data']);
        }
        return $data;
    }

    /**
     * @param IdealisticOfficePaypalEmail[] $emails
     */
    public static function getPayPalEmails(array $emails, IdealisticOfficePrompt $prompt): array
    {
        $timezone = $prompt->getUser()->getTimezone(false)->getTimezone();

        foreach ($emails as $key => $value) {
            $emails[$key] = [
                'id' => $value->getId(),
                'email_address' => $value->getEmailAddress(),
                'since' => [
                    'date' => self::translateDate(
                        $value->getCreationDate(),
                        $timezone,
                        $value->getCreationDateTimeZone()
                    ),
                    'created_by' => self::getCreatorDetails($value)
                ],
            ];
        }
        return array_values($emails);
    }

    /**
     * @param IdealisticOfficeUserShortcut[] $shortcuts
     */
    public static function getShortcut(array $shortcuts, IdealisticOfficePrompt $prompt): array
    {
        $timezone = $prompt->getUser()->getTimezone(false)->getTimezone();

        foreach ($shortcuts as $key => $shortcut) {
            $shortcuts[$key] = [
                'id' => $shortcut->getId(),
                'word' => $shortcut->getWord(),
                'replacement' => $shortcut->getReplacement(),
                'since' => [
                    'date' => self::translateDate(
                        $shortcut->getCreationDate(),
                        $timezone,
                        $shortcut->getCreationDateTimeZone()
                    )
                ]
            ];
        }
        return array_values($shortcuts);
    }

    private static function getTimeAccess(IdealisticOfficeTimeAccess $access, IdealisticOfficePrompt $prompt): array
    {
        return [
            'start_hour' => $access->getStartHour(),
            'start_minutes' => $access->getStartMinutes(),
            'end_hour' => $access->getEndHour(),
            'end_minutes' => $access->getEndMinutes(),
            'since' => [
                'date' => self::translateDate(
                    $access->getCreationDate(),
                    $prompt->getUser()->getTimezone(false)->getTimezone(),
                    $access->getCreationDateTimeZone()
                ),
                'created_by' => self::getCreatorDetails($access)
            ],
        ];
    }

    private static function getWeekAccess(IdealisticOfficeWeeklyAccess $access, IdealisticOfficePrompt $prompt): array
    {
        return [
            'days_of_access' => $access->getDaysString(),
            'since' => [
                'date' => self::translateDate(
                    $access->getCreationDate(),
                    $prompt->getUser()->getTimezone(false)->getTimezone(),
                    $access->getCreationDateTimeZone()
                ),
                'created_by' => self::getCreatorDetails($access)
            ]
        ];
    }

    private static function getCreatorDetails(AbstractIdealisticOfficeChange $change): array
    {
        if ($change instanceof AbstractIdealisticOfficeInformationChange) {
            return self::getCreatorDetailsRaw($change->getCreatorAccount(), $change->getCreationReason());
        }
        return self::getCreatorDetailsRaw($change->getCreatorAccount(), null);
    }

    private static function getCreatorDetailsRaw(Account $creator, ?string $justification): array
    {
        $data = [];

        if ($creator->exists()) {
            $data['executor'] = $creator->getDetail("email_address", "");
        }
        if ($justification !== null) {
            $data['justification'] = $justification;
        }
        return $data;
    }

    public static function getUser(
        IdealisticOfficeUser   $user,
        IdealisticOfficePrompt $prompt
    ): array
    {
        $timezone = $prompt->getUser()->getTimezone(false)->getTimezone();
        $account = $user->getAccount();
        $cache = $prompt->getCache();

        $data = [
            'id' => $user->getId(),
            'name' => $user->getDisplayName(),
            'email' => $account->getDetail("email_address", ""),
            'since' => [
                'date' => self::translateDate(
                    $user->getCreationDate(),
                    $timezone,
                    $user->getCreationDateTimeZone()
                )
            ],
            'settings' => [],
            'preferred_language' => $prompt->getCache()->getLanguageSelection()->getLanguage(),
            'myself' => $user->getId() === $prompt->getUser()->getId()
        ];

        $nickname = $user->getNickname();
        if (!empty($nickname)) {
            $data['nickname'] = "@" . $nickname;
        }

        if (!$account->exists()) {
            $data['does_not_exist'] = true;
        }
        if ($cache->isTaskEnabled(IdealisticOfficePromptTask::DATA_RETENTION)) {
            $data['data_retention_days'] = $user->getRetentionDays();
        }
        if ($cache->isTaskEnabled(IdealisticOfficePromptTask::MANAGE_TIME_ACCESSES)) {
            $data['time_access'] = self::getTimeAccess($user->getTimeAccess($prompt->getTeam(), false), $prompt);
        }
        if ($cache->isTaskEnabled(IdealisticOfficePromptTask::MANAGE_WEEK_ACCESSES)) {
            $data['week_access'] = self::getWeekAccess($user->getWeekAccess($prompt->getTeam(), false), $prompt);
        }

        $identities = $user->getLastIdentities();
        if (!empty($identities)) {
            $data['identities'] = [];
            foreach ($identities as $identity) {
                $data['identities'][] = [
                    'platform' => IdealisticOfficeStrings::getPlatformFromID($identity->getPlatformID()),
                    'username' => $identity->getUserName(),
                    'display_name' => $identity->getDisplayName(),
                    'since' => [
                        'date' => self::translateDate(
                            $identity->getCreationDate(),
                            $timezone,
                            $identity->getCreationDateTimeZone()
                        )
                    ]
                ];
            }
        }
        return $data;
    }

    public static function getRole(
        IdealisticOfficeRole   $role,
        IdealisticOfficePrompt $prompt,
        bool                   $intuitive = true
    ): array
    {
        $cache = $prompt->getCache();
        $data = [
            'id' => $role->getId(),
            'name' => $role->getName(),
            'since' => [
                'date' => self::translateDate(
                    $role->getCreationDate(),
                    $prompt->getUser()->getTimezone(false)->getTimezone(),
                    $role->getCreationDateTimeZone()
                )
            ]
        ];

        if ($cache->isTaskEnabled(IdealisticOfficePromptTask::MANAGE_TIME_ACCESSES)) {
            $data['time_access'] = self::getTimeAccess($role->getTimeAccess(false), $prompt);
        }
        if ($cache->isTaskEnabled(IdealisticOfficePromptTask::MANAGE_WEEK_ACCESSES)) {
            $data['week_access'] = self::getWeekAccess($role->getWeekAccess(false), $prompt);
        }
        return $data;
    }

    public static function getTeam(
        IdealisticOfficePrompt $prompt
    ): array
    {
        $team = $prompt->getTeam();
        $cache = $prompt->getCache();

        $data = [
            'id' => $team->getId(),
            'since' => [
                'date' => self::translateDate(
                    $team->getCreationDate(),
                    $prompt->getUser()->getTimezone(false)->getTimezone(),
                    $team->getCreationDateTimeZone()
                )
            ]
        ];

        if ($cache->isTaskEnabled(IdealisticOfficePromptTask::MANAGE_TIME_ACCESSES)) {
            $data['time_access'] = self::getTimeAccess($team->getTimeAccess(false), $prompt);
        }
        if ($cache->isTaskEnabled(IdealisticOfficePromptTask::MANAGE_WEEK_ACCESSES)) {
            $data['week_access'] = self::getWeekAccess($team->getWeekAccess(false), $prompt);
        }

        if (IdealisticOfficeCredit::ALLOW_PAYPAL
            && $cache->isTaskEnabled(IdealisticOfficePromptTask::MANAGE_PAYPAL_EMAILS)) {
            $data['paypal_emails'] = self::getPayPalEmails($team->getPayPalEmails(0, false), $prompt);
        }

        $unlimited = $prompt->getCache()->hasUnlimitedCredits();
        $includedMessages = $prompt->getCache()->getIncludedMessages();
        $data['balance'] = [];

        if ($prompt->getCache()->isSubscriber()) {
            $data['balance']['credits'] = [
                'available' => $unlimited ? "unlimited" : $includedMessages,
                'remaining' => $unlimited ? "unlimited" : $team->getCredit()->getRemainingIncludedMessages($includedMessages),
                'remaining_app_messages' => $unlimited ? "unlimited" : (int)floor($team->getCredit()->getRemainingIncludedMessages($includedMessages) / IdealisticOfficeLimit::DEFAULT_MESSAGE_USAGE),
                'remaining_portal_messages' => $unlimited ? "unlimited" : (int)floor($team->getCredit()->getRemainingIncludedMessages($includedMessages) / IdealisticOfficeLimit::CHAT_PORTAL_MESSAGE_USAGE),
            ];
            $data['balance']['analogy'] = "1 credit = 1 portal/external message OR 10 app/internal messages";
        } else {
            $data['balance']['credits'] = [
                'available' => $unlimited ? "unlimited" : cut_decimal($team->getCredit()->getRemainingCredits(), 2),
            ];

            if (IdealisticOfficeVariable::MINIMUM_CREDITS_TO_USE > 0.0) {
                $equivalence = (int)floor(1.0 / IdealisticOfficeVariable::MINIMUM_CREDITS_TO_USE);
                $data['balance']['analogy'] = "1 credit = " . $equivalence . " prompts/messages";
                $data['balance']['credits']['remaining_messages'] = $unlimited ? "unlimited" : (int)floor($team->getCredit()->getRemainingCredits() / IdealisticOfficeVariable::MINIMUM_CREDITS_TO_USE);
            }
        }
        if ($cache->isTaskEnabled(IdealisticOfficePromptTask::MANAGE_ROLES)) {
            $rolesDefinition = "roles";
            $data[$rolesDefinition] = [];
            $roles = $cache->getRoles();
            if (!empty($roles)) {
                foreach ($roles as $role) {
                    $data[$rolesDefinition][] = self::getRole($role, $prompt);
                }
            }
        }

        $users = $cache->getUsers();
        if (!empty($users)) {
            $usersNameDefinition = "contacts";

            foreach ($users as $user) {
                $data[$usersNameDefinition][] = self::getUser($user, $prompt);
            }
        }

        if ($cache->isTaskEnabled(IdealisticOfficePromptTask::MANAGE_FOLDERS)) {
            $data['folders'] = [];
            $folders = $cache->getFolders();
            $folderCount = sizeof($folders);

            if ($folderCount > 0) {
                $messages = max(IdealisticOfficeLimit::FOLDER_MAX_COMMENTS / $folderCount, 1);

                foreach ($folders as $folder) {
                    $data['folders'][] = self::getFolder($folder, $prompt, $messages);
                }
            }
        }

        if ($cache->isTaskEnabled(IdealisticOfficePromptTask::MANAGE_PORTALS)) {
            $data['portals'] = self::getPortals($prompt->getTeam()->getPortals(0, false), $prompt);
        }
        return $data;
    }

    /**
     * @param IdealisticOfficePortal[] $portals
     */
    public static function getPortals(
        array                  $portals,
        IdealisticOfficePrompt $prompt
    ): array
    {
        $timezone = $prompt->getUser()->getTimezone(false)->getTimezone();
        $data = [];

        foreach ($portals as $portal) {
            $stats = $portal->getStatistics();
            $data[] = [
                'id' => $portal->getId(),
                'external_script_id' => $portal->getRandomId(),
                'name' => $portal->getName(),
                'url' => $portal->getURL(),
                'external_script' => str_replace(
                    IdealisticOfficeVariable::PORTAL_EXTERNAL_SCRIPT_ID_PLACEHOLDER,
                    $portal->getRandomId(),
                    IdealisticOfficeVariable::PORTAL_EXTERNAL_SCRIPT
                ),
                'downloads_allowed' => $portal->areDownloadsAllowed(),
                'voice_replies_enabled' => $portal->areVoiceRepliesEnabled(),
                'skip_bot_verification' => $portal->skipRobotVerification(),
                'is_18_plus' => $portal->is18Plus(),
                'default_url' => $portal->getDefaultUrl(),
                'can_trust_and_crawl_first_used_domain' => $portal->canCrawl(),
                'default_message' => $portal->getDefaultMessage(),
                'password_protected' => $portal->getPassword() !== null,
                'set_to_be_transferred' => $portal->getTransferTo() !== null,
                'statistics' => [
                    'total_messages' => $stats->getTotalMessages(),
                    'total_sessions' => $stats->getTotalSessions(),
                    'messages_per_session' => $stats->getMessagesPerSession(),
                    'messages_per_day' => $stats->getMessagesPerDay()
                ],
                'since' => [
                    'date' => self::translateDate(
                        $portal->getCreationDate(),
                        $timezone,
                        $portal->getCreationDateTimeZone()
                    ),
                    'created_by' => $portal->getCreatorAccount()->getDetail("email_address", "")
                ]
            ];
        }
        return $data;
    }

    /**
     * @param IdealisticOfficeScheduler[] $news
     * @return array
     */
    public static function getScheduledTasks(array $news, IdealisticOfficePrompt $prompt): array
    {
        $timezone = $prompt->getUser()->getTimezone(false)->getTimezone();
        $data = [];

        foreach ($news as $item) {
            $data[] = [
                'id' => $item->getId(),
                'instructions' => $item->getCreationReason(),
                '24_hour_time_preference' => $item->hasTimePreference()
                    ? $item->getHour() . ":" . $item->getMinute()
                    : null,
                'seconds_frequency' => $item->getSecondsFrequency(),
                'since' => [
                    'date' => self::translateDate(
                        $item->getCreationDate(),
                        $timezone,
                        $item->getCreationDateTimeZone()
                    ),
                    'created_by' => self::getCreatorDetails($item)
                ]
            ];
        }
        return $data;
    }

    public static function getFolder(
        IdealisticOfficeFolder $folder,
        IdealisticOfficePrompt $prompt,
        int                    $limit,
        bool                   $optimized = false
    ): array
    {
        $timezone = $prompt->getUser()->getTimezone(false)->getTimezone();
        $isOpen = $folder->isOpen();

        $data = [
            'id' => $folder->getId(),
            'currently_open' => $isOpen,
            'since' => [
                'date' => self::translateDate(
                    $folder->getCreationDate(),
                    $timezone,
                    $folder->getCreationDateTimeZone()
                ),
                'created_by' => $folder->getFirstOpenerAccount()->getDetail("email_address", ""),
                'creation_reason' => $folder->getCreationReason()
            ],
            'attached_portals' => [],
            'participants' => []
        ];

        if ($folder->getCustomId() !== null) {
            $data['name'] = $folder->getName();
        }
        if (!$optimized) {
            $data['last_modified'] = [
                'date' => self::translateDate(
                    $folder->getLastModifiedDate(),
                    $timezone,
                    $folder->getLastModifiedDateTimeZone()
                ),
                'modified_by' => $folder->getLastModifiedAccount()->getDetail("email_address", "")
            ];

            $statusHistory = $folder->getStatusHistory();
            if (!empty($statusHistory)) {
                $data['status_history'] = [];

                foreach ($statusHistory as $history) {
                    $subData = [
                        'status' => $history->isOpen() ? "open" : "closed",
                        'since' => [
                            'date' => self::translateDate(
                                $history->getCreationDate(),
                                $timezone,
                                $history->getCreationDateTimeZone()
                            )
                        ]
                    ];
                    $subData['since'][$history->isOpen() ? "opened_by" : "closed_by"]
                        = $history->getCreatorAccount()->getDetail("email_address", "");
                    $subData['since'][$history->isOpen() ? "open_reason" : "close_reason"]
                        = $history->getCreationReason();
                    $data['status_history'][] = $subData;
                }
            }

            $attachedPortals = $folder->getAttachedPortals(false);
            if (!empty($attachedPortals)) {
                foreach ($attachedPortals as $val) {
                    $obj = $val->getPortal(false);

                    if ($obj !== null
                        && $obj->canView($prompt->getUser())) {
                        $data['attached_portals'][] = ['id' => $obj->getId(), 'name' => $obj->getName()];
                    }
                }
            }
        }

        $participants = $folder->getParticipants(false);
        if (!empty($participants)) {
            foreach ($participants as $participant) {
                $data['participants'][] = [
                    'email' => $participant->getAddedAccount()->getDetail("email_address", ""),
                    'since' => [
                        'date' => self::translateDate(
                            $participant->getCreationDate(),
                            $timezone,
                            $participant->getCreationDateTimeZone()
                        ),
                        'added_by' => $participant->getCreatorAccount()->getDetail("email_address", "")
                    ]
                ];
            }
        }

        if ($isOpen) {
            $comments = $folder->getComments($limit, false);

            if (!empty($comments)) {
                $data['recent_comments'] = [];
                $dateCount = array();

                foreach ($comments as $comment) {
                    $commentData = [
                        'id' => $comment->getId(),
                        'text' => $comment->getComment() ?? "(no text)",
                        'sent_by' => $comment->getCreatorAccount()->getDetail("email_address", "")
                    ];

                    if (!empty($comment->getPartialAttachments())) {
                        $commentData['attachments'] = $comment->getPartialAttachments();
                    }
                    if (!empty($comment->getPartialLinks())) {
                        $commentData['links'] = [];

                        foreach ($comment->getPartialLinks() as $link) {
                            $commentData['links'][$link->getUrl()] = $link->getAnalyzedDescription();
                        }
                    }

                    $key = self::translateDate(
                        $comment->getCreationDate(),
                        $timezone,
                        $comment->getCreationDateTimeZone()
                    );
                    if (isset($dateCount[$key])) {
                        $dateCount[$key]++;
                        $data['recent_comments'][$key . " (" . $dateCount[$key] . ")"] = $commentData;
                    } else {
                        $dateCount[$key] = 1;
                        $data['recent_comments'][$key . " (1)"] = $commentData;
                    }
                }
            }
        }
        return $data;
    }

    /** @return IdealisticOfficeAttachment[] */
    public static function getAttachments(array $query, bool $json = true): array
    {
        if (!empty($query)) {
            foreach ($query as $key => $row) {
                $attachment = new IdealisticOfficeAttachment(
                    (int)$row->id,
                    $row->attachment_name,
                    $row->attachment_format,
                    null,
                    $row->attachment_bytes ?? 0,
                    $row->attachment_x,
                    $row->attachment_y,
                    $row->analyzed_description,
                    $row->attachment_data,
                    $row->attachment_data_hash,
                    $row->is_base64 !== null,
                    $row->creation_date,
                    $row->creation_time_zone,
                    $row->created_by,
                    $json
                );
                $attachment->setAnalyzedDescriptionPositions($row->analyzed_description_positions);
                $query[$key] = $attachment;
            }
        }
        return $query;
    }

    /** @return IdealisticOfficePartialAttachment[] */
    public static function getPartialAttachments(array $query, bool $json = true): array
    {
        if (!empty($query)) {
            foreach ($query as $key => $row) {
                $attachment = new IdealisticOfficePartialAttachment(
                    (int)$row->id,
                    $row->attachment_name,
                    $row->attachment_format,
                    null,
                    $row->attachment_bytes ?? 0,
                    $row->attachment_x,
                    $row->attachment_y,
                    $row->analyzed_description,
                    $row->attachment_data_hash,
                    $row->creation_date,
                    $row->creation_time_zone,
                    $row->created_by,
                    $json
                );
                $attachment->setAnalyzedDescriptionPositions($row->analyzed_description_positions);
                $query[$key] = $attachment;
            }
        }
        return $query;
    }

    /** @return IdealisticOfficePartialAttachment[] */
    public static function getCreatedPartialAttachments(array $query, bool $json = true): array
    {
        if (!empty($query)) {
            foreach ($query as $key => $row) {
                $attachment = new IdealisticOfficePartialAttachment(
                    (int)$row->id,
                    $row->attachment_name,
                    $row->attachment_format,
                    null,
                    $row->attachment_bytes ?? 0,
                    $row->attachment_x,
                    $row->attachment_y,
                    $row->analyzed_description,
                    $row->attachment_data_hash,
                    $row->creation_date,
                    $row->creation_time_zone,
                    $row->created_by,
                    $json
                );
                $attachment->setAnalyzedDescriptionPositions($row->analyzed_description_positions);
                $attachment->setUserPrompt($row->user_prompt ?? "");
                $query[$key] = $attachment;
            }
        }
        return $query;
    }

    /** @return IdealisticOfficeAttachment[] */
    public static function getCreatedAttachments(array $query, bool $json = true): array
    {
        if (!empty($query)) {
            foreach ($query as $key => $row) {
                $attachment = new IdealisticOfficeAttachment(
                    (int)$row->id,
                    $row->attachment_name,
                    $row->attachment_format,
                    null,
                    $row->attachment_bytes ?? 0,
                    $row->attachment_x,
                    $row->attachment_y,
                    $row->analyzed_description,
                    $row->attachment_data,
                    $row->attachment_data_hash,
                    $row->is_base64 !== null,
                    $row->creation_date,
                    $row->creation_time_zone,
                    $row->created_by,
                    $json
                );
                $attachment->setAnalyzedDescriptionPositions($row->analyzed_description_positions);
                $attachment->setUserPrompt($row->user_prompt ?? "");
                $query[$key] = $attachment;
            }
        }
        return $query;
    }

    public static function hasAttachments(int $historyID, ?string $taskID, string $table, ?array $list = null): bool
    {
        if ($list === null) {
            $query = get_sql_query(
                $table,
                array("history_id"),
                array(
                    array("history_id", $historyID),
                    $taskID === null ? "" : array("task_id", $taskID)
                ),
                null,
                1
            );
        } else {
            $query = $list[$historyID];
        }
        return !empty($query);
    }

    public static function getAttachmentCount(int $historyID, ?string $taskID, string $table, ?array $list = null): int
    {
        if ($list === null) {
            $query = get_sql_query(
                $table,
                array("history_id"),
                array(
                    array("history_id", $historyID),
                    $taskID === null ? "" : array("task_id", $taskID)
                ),
                null,
                IdealisticOfficeLimit::MAX_EXTENSIONS_PER_OBJECT
            );
        } else {
            $query = array_slice($list[$historyID] ?? array(), 0, IdealisticOfficeLimit::MAX_EXTENSIONS_PER_OBJECT);
        }
        return sizeof($query);
    }

    public static function getLinkCount(int $historyID, ?bool $web, string $table, ?array $list = null): int
    {
        if ($list === null) {
            $query = get_sql_query(
                $table,
                array("history_id"),
                array(
                    array("history_id", $historyID),
                    $web === null ? "" : array("web_search", $web)
                ),
                null,
                IdealisticOfficeLimit::MAX_EXTENSIONS_PER_OBJECT
            );
        } else {
            $query = array_slice($list[$historyID] ?? array(), 0, IdealisticOfficeLimit::MAX_EXTENSIONS_PER_OBJECT);
        }
        return sizeof($query);
    }

    /**
     * @param IdealisticOfficeUser[] $users
     * @return int
     */
    public static function hashUsers(array $users): int
    {
        foreach ($users as $key => $user) {
            $users[$key] = $user->getId();
        }
        sort($users);
        return array_to_integer($users);
    }

    public static function jsonObject(mixed $object, bool $pretty = true, bool $default = false): ?string
    {
        if ($pretty) {
            if ($default) {
                $json = @json_encode($object, JSON_PRETTY_PRINT);
            } else {
                $json = @json_encode($object, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
        } else {
            if ($default) {
                $json = @json_encode($object);
            } else {
                $json = @json_encode($object, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
        }
        if ($json === false) {
            IdealisticOfficeError::simpleStore(json_last_error_msg());
            return null;
        }
        return $json;
    }

    public static function readFields(array $fields, array $blacklistedFields): ?string
    {
        if (empty($fields)) {
            return null;
        }
        $str = "Information to be provided via chat as AI prompt:";
        $newFields = [];

        foreach ($fields as $key => $field) {
            if ($field instanceof AIFieldObject && !in_array($key, $blacklistedFields)) {
                $fieldData = [
                    'definition' => $field->getDefinition(),
                    'type' => $field->getType(),
                    'required' => !$field->isNullable()
                ];

                if ($field->getEnums() !== null) {
                    $fieldData['must_be_one_of'] = implode(IdealisticOfficeVariable::DEFAULT_TEXT_SEPARATOR, $field->getEnums());
                }
                $maxLength = $field->getMaxLength(false);

                if ($maxLength !== null) {
                    $fieldData['max_length'] = $maxLength;
                }
                $newFields[str_replace("_", " ", $key)] = $fieldData;
            }
        }
        return $str . " " . self::jsonObject($newFields, false);
    }

    private static function getTimezoneObject(string $timezone): DateTimeZone
    {
        if (!isset(self::$tzCache[$timezone])) {
            try {
                self::$tzCache[$timezone] = new DateTimeZone($timezone);
            } catch (Exception $e) {
                $default = IdealisticOfficeVariable::DEFAULT_TIME_ZONE;

                if (!isset(self::$tzCache[$default])) {
                    self::$tzCache[$default] = new DateTimeZone($default);
                }
                self::$tzCache[$timezone] = self::$tzCache[$default];
            }
        }
        return self::$tzCache[$timezone];
    }

    public static function getCurrentDateTime(string|IdealisticOfficeTimezone|null $timezone = null): DateTime
    {
        if ($timezone === null) {
            $tzString = IdealisticOfficeVariable::DEFAULT_TIME_ZONE;
        } elseif ($timezone instanceof IdealisticOfficeTimezone) {
            $tzString = $timezone->getTimeZone();
        } else {
            $tzString = $timezone;
        }
        return new DateTime("now", self::getTimezoneObject($tzString));
    }

    public static function getCurrentDate(
        string|IdealisticOfficeTimezone|null $timezone = null,
        string                               $format = IdealisticOfficeVariable::DEFAULT_DATE_FORMAT
    ): string
    {
        return self::getCurrentDateTime($timezone)->format($format);
    }

    public static function translateDate(
        ?string                              $date,
        null|string|IdealisticOfficeTimezone $nativeTimezone,
        null|string|IdealisticOfficeTimezone $otherTimezone,
        bool                                 $timePassed = true,
        string                               $format = IdealisticOfficeVariable::DEFAULT_DATE_FORMAT
    ): ?string
    {
        if ($date === null || $nativeTimezone === null || $otherTimezone === null) {
            return $date;
        }

        $nativeTzStr = ($nativeTimezone instanceof IdealisticOfficeTimezone)
            ? $nativeTimezone->getTimeZone()
            : $nativeTimezone;

        $otherTzStr = ($otherTimezone instanceof IdealisticOfficeTimezone)
            ? $otherTimezone->getTimeZone()
            : $otherTimezone;

        if ($nativeTzStr !== $otherTzStr) {
            $dateTime = new DateTime($date, self::getTimezoneObject($otherTzStr));
            $dateTime->setTimezone(self::getTimezoneObject($nativeTzStr));
            $date = $dateTime->format($format);

            if ($timePassed) {
                $nowTimestamp = self::getCurrentDateTime($nativeTzStr)->getTimestamp();
                $dateTimestamp = $dateTime->getTimestamp();
                return self::getTimePassed($nowTimestamp - $dateTimestamp);
            }
        } else if ($timePassed) {
            $nowTimestamp = self::getCurrentDateTime($nativeTzStr)->getTimestamp();
            $dateTimestamp = strtotime($date);
            return self::getTimePassed($nowTimestamp - $dateTimestamp);
        }

        return $date;
    }

    public static function getTimePassed(int $seconds): string
    {
        $units = [
            "y" => 365 * 24 * 60 * 60, // approximate
            "m" => 30 * 24 * 60 * 60,  // approximate
            "d" => 24 * 60 * 60,
            "h" => 60 * 60,
            "min" => 60,
        ];
        $parts = [];

        foreach ($units as $name => $divisor) {
            $value = intdiv($seconds, $divisor);

            if ($value > 0) {
                $parts[] = $value . $name;
                $seconds %= $divisor;
            }
        }
        return $parts ? implode(", ", $parts) . " ago" : "now";
    }

    public static function getFutureDate(
        int|string                           $time,
        string|IdealisticOfficeTimezone|null $timezone = null,
        string                               $format = IdealisticOfficeVariable::DEFAULT_DATE_FORMAT
    ): string
    {
        $dateTime = self::getCurrentDateTime($timezone);
        $dateTime->modify("+" . $time);
        return $dateTime->format($format);
    }

    public static function getPastDate(
        int|string                           $time,
        string|IdealisticOfficeTimezone|null $timezone = null,
        string                               $format = IdealisticOfficeVariable::DEFAULT_DATE_FORMAT
    ): string
    {
        $dateTime = self::getCurrentDateTime($timezone);
        $dateTime->modify("-" . $time);
        return $dateTime->format($format);
    }

    public static function invalidAPIParameter(string $parameter): string
    {
        return "Invalid API parameter: " . $parameter;
    }

    public static function getSqlLastInsertId(): ?int
    {
        return get_sql_last_insert_id();
    }

    public static function isLatinOnly(string $string): bool
    {
        return preg_match('/^[A-Za-z]+$/', $string);
    }

    public static function findURLs(string $text, bool $https = false): array
    {
        $pattern = '/\b((?:https?:\/\/|www\.)?+[a-z0-9]+(?:[\-\.]{1}[a-z0-9]+)*\.[a-z]{2,6}(?:\/[^\s<>{}|\\^~\[\]`]*)?)/i';

        if (preg_match_all($pattern, $text, $matches)) {
            $results = array_map(
                function ($url) use ($https) {
                    $url = rtrim($url, '.,?!');

                    if (!preg_match('/^https?:\/\//i', $url)) {
                        return ($https ? 'https://' : 'http://') . $url;
                    }
                    return $url;
                },
                $matches[1]
            );
            return array_slice(
                array_unique($results),
                0,
                IdealisticOfficeLimit::MAX_EXTENSIONS_PER_OBJECT,
                false
            );
        }
        return [];
    }

}