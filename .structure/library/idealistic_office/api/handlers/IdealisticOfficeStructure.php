<?php

class IdealisticOfficeStructure implements PhpAsyncSerializable
{

    private static function yesNo(bool $value): string
    {
        return $value ? "yes" : "no";
    }

    private static function isBlocked($week): bool
    {
        return !($week->getMondayAccess() || $week->getTuesdayAccess() || $week->getWednesdayAccess()
            || $week->getThursdayAccess() || $week->getFridayAccess() || $week->getSaturdayAccess() || $week->getSundayAccess());
    }

    private static function load(?IdealisticOfficeUser $user): array
    {
        $team = $user?->getTeam();
        $loaded = $user !== null && $user->hasEstablishedAccess();
        return array(
            "team" => $team,
            "loaded" => $loaded,
            "portals" => $loaded ? $team->getPortals(0, false) : array(),
            "roles" => $loaded ? $team->getRoles(false) : array(),
            "members" => $loaded ? $team->getUsers(false) : array(),
            "shortcuts" => $loaded ? $user->getShortcuts(0, false) : array(),
            "instructions" => $loaded ? $team->getInstructions(0, false) : array(),
            "tasks" => $loaded ? $user->getScheduledTasks(0, false) : array()
        );
    }

    public static function getBlocks(?IdealisticOfficeUser $user): array
    {
        $data = self::load($user);

        if (!$data["loaded"]) {
            return array();
        }
        $team = $data["team"];
        $on = static fn(string $task): bool => IdealisticOfficePromptTask::isEnabled($team, $task);
        $timeText = static fn($access) => sprintf("%02d:%02d to %02d:%02d", $access->getStartHour(), $access->getStartMinutes(), $access->getEndHour(), $access->getEndMinutes());
        $platforms = IdealisticOfficeAccessPlatform::getValidPlatforms();
        $names = array();

        foreach ($data["members"] as $id => $member) {
            $names[(int)$id] = (string)($member->getDisplayName() ?? ("Member " . $id));
        }
        $credit = $team->getCredit();
        $blocks = array();

        $blocks[] = array(
            "id" => "structure-you",
            "title" => "You",
            "text" => "Your own account and settings.",
            "cards" => array(array(
                "title" => (string)($user->getDisplayName() ?? "Your account"),
                "subtitle" => $user->hasEmail() ? (string)$user->getEmail() : "Demo account, no email yet",
                "badges" => array_filter(array(
                    $user->isOwner() ? "Owner" : "Member",
                    $user->hasEmail() ? "Signed in" : "Demo"
                )),
                "lines" => array(
                    array("Time zone", $user->getTimezone(false)->getTimeZone()),
                    array("Language", $user->hasLanguageSelection() ? ucfirst($user->getLanguageSelection(false)->getLanguage()) : "Automatic"),
                    array("Voice replies", self::yesNo((bool)$user->getSetting(IdealisticOfficeSettings::SEND_VOICE_REPLY))),
                    array("Data kept for", $user->getRetentionDays() . " days"),
                    array("Credits", $credit->hasUnlimited() ? "Unlimited" : number_format((int)floor($credit->getRemainingCredits())) . " left")
                )
            ))
        );

        if ($on(IdealisticOfficePromptTask::MANAGE_PORTALS)) {
            $installations = get_sql_query(
                IdealisticOfficeTable::PORTAL_INSTALLATIONS_TABLE,
                array("portal_id", "platform_id", "server_id", "channel_id", "thread_id"),
                array(
                    array("team_id", $team->getId()),
                    array("deletion_date", null)
                ),
                null,
                IdealisticOfficeLimit::MAX_EXTENSIONS_PER_OBJECT_LARGE * 10
            );
            $cards = array();

            foreach ($data["portals"] as $portal) {
                $where = array();

                foreach ($installations as $installation) {
                    if ((int)$installation->portal_id === (int)$portal->getId()) {
                        $where[] = ($platforms[(int)$installation->platform_id] ?? "Unknown")
                            . " " . implode(" / ", array_filter(array($installation->server_id, $installation->channel_id, $installation->thread_id)));
                    }
                }
                $people = array();
                $folder = $portal->getParentFolder();

                if ($folder !== null && $on(IdealisticOfficePromptTask::MANAGE_FOLDERS)) {
                    foreach ($folder->getParticipants(false) as $participant) {
                        $people[] = $names[(int)$participant->getAddedID()] ?? ("Member " . $participant->getAddedID());
                    }
                }
                $cards[] = array(
                    "title" => $portal->getName(),
                    "subtitle" => $portal->getURL(),
                    "badges" => array_filter(array(
                        $portal->areDownloadsAllowed() ? "Downloads" : null,
                        $portal->is18Plus() ? "18+" : null,
                        $portal->isLoginRequired() ? "Login required" : null,
                        $portal->getPassword() !== null ? "Password" : null
                    )),
                    "lines" => array_values(array_filter(array(
                        array("Installed in", empty($where) ? "Not installed in a chat app" : implode(", ", $where)),
                        array("Website", $portal->hasDefaultURL() ? ($portal->getDefaultUrl() ?: "none") : "The first website that uses it"),
                        array("Default message", (string)($portal->getDefaultMessage() ?? "The generic default message")),
                        empty($people) ? null : array("Participants", implode(", ", $people))
                    )))
                );
            }
            $blocks[] = array("id" => "structure-portals", "title" => "Portals", "text" => "The assistants you created, where each one is installed and who takes part.", "cards" => $cards);
        }

        $cards = array();
        $teamWeek = $team->getWeekAccess(false);

        foreach ($data["members"] as $id => $member) {
            $roleNames = array();

            if ($on(IdealisticOfficePromptTask::MANAGE_ROLES)) {
                foreach ($member->getRoles(false) as $role) {
                    $roleNames[] = (string)$role->getName();
                }
            }
            $week = $member->getWeekAccess($team, false);
            $cards[] = array(
                "title" => $names[(int)$id],
                "subtitle" => $member->hasEmail() ? (string)$member->getEmail() : "No email",
                "badges" => array_filter(array_merge(
                    array($team->getOwnerId() == $id ? "Owner" : null, self::isBlocked($week) ? "Blocked" : "Allowed"),
                    $roleNames
                )),
                "lines" => array(
                    array("Hours", $timeText($member->getTimeAccess($team, false))),
                    array("Days", $week->getDaysString())
                )
            );
        }
        $blocks[] = array("id" => "structure-contacts", "title" => "Contacts", "text" => ("The people on your team, their roles and when they can reach " . IdealisticOfficeVariable::APPLICATION_NAME . "."), "cards" => $cards);

        if ($on(IdealisticOfficePromptTask::MANAGE_ROLES)) {
            $cards = array();

            foreach ($data["roles"] as $role) {
                $holders = array();

                foreach ($data["members"] as $id => $member) {
                    foreach ($member->getRoles(false) as $memberRole) {
                        if ($memberRole->getId() == $role->getId()) {
                            $holders[] = $names[(int)$id];
                        }
                    }
                }
                $count = count($holders);
                $cards[] = array(
                    "title" => (string)$role->getName(),
                    "subtitle" => $count . " " . ($count === 1 ? "contact" : "contacts"),
                    "badges" => array(self::isBlocked($role->getWeekAccess(false)) ? "Blocked" : "Allowed"),
                    "lines" => array_values(array_filter(array(
                        $count === 0 ? null : array("Contacts", implode(", ", $holders)),
                        array("Hours", $timeText($role->getTimeAccess(false))),
                        array("Days", $role->getWeekAccess(false)->getDaysString())
                    )))
                );
            }
            $blocks[] = array("id" => "structure-roles", "title" => "Roles", "text" => "Groups of contacts that share the same access.", "cards" => $cards);
        }
        $teamTime = $team->getTimeAccess(false);
        $blocks[] = array(
            "id" => "structure-team",
            "title" => "Team access",
            "text" => "The default access for everyone on your team.",
            "cards" => array(array(
                "title" => "Everyone on the team",
                "subtitle" => "Applies unless a role or a contact says otherwise",
                "badges" => array(self::isBlocked($teamWeek) ? "Blocked" : "Allowed"),
                "lines" => array(array("Hours", $timeText($teamTime)), array("Days", $teamWeek->getDaysString()))
            ))
        );

        if ($on(IdealisticOfficePromptTask::MANAGE_INSTRUCTIONS)) {
            $cards = array();

            foreach ($data["instructions"] as $instruction) {
                $cards[] = array("title" => mb_substr($instruction->getInstruction(), 0, 140), "subtitle" => "", "badges" => array(), "lines" => array());
            }
            $blocks[] = array("id" => "structure-instructions", "title" => "Instructions", "text" => ("What " . IdealisticOfficeVariable::APPLICATION_NAME . " always keeps in mind."), "cards" => $cards);
        }
        if ($on(IdealisticOfficePromptTask::MANAGE_SHORTCUTS)) {
            $cards = array();

            foreach ($data["shortcuts"] as $shortcut) {
                $cards[] = array("title" => $shortcut->getWord(), "subtitle" => $shortcut->getReplacement(), "badges" => array(), "lines" => array());
            }
            $blocks[] = array("id" => "structure-shortcuts", "title" => "Shortcuts", "text" => ("Words that " . IdealisticOfficeVariable::APPLICATION_NAME . " expands into longer text."), "cards" => $cards);
        }
        if ($on(IdealisticOfficePromptTask::MANAGE_SCHEDULER)) {
            $cards = array();

            foreach ($data["tasks"] as $task) {
                $cards[] = array(
                    "title" => mb_substr((string)$task->getCreationReason(), 0, 140),
                    "subtitle" => $task->getHour() !== null
                        ? sprintf("Every day at %02d:%02d", (int)$task->getHour(), (int)$task->getMinute())
                        : "Every " . max(1, (int)round(($task->getSecondsFrequency() ?? IdealisticOfficeScheduler::MINIMUM_SECONDS_FREQUENCY) / 3600)) . " hours",
                    "badges" => array(),
                    "lines" => array()
                );
            }
            $blocks[] = array("id" => "structure-scheduler", "title" => "Scheduled tasks", "text" => ("What " . IdealisticOfficeVariable::APPLICATION_NAME . " does on its own, and when."), "cards" => $cards);
        }
        return $blocks;
    }

}
