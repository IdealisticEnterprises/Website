<?php

namespace idealistic_office\api\objects\helpers;

use idealistic_office\api\handlers\IdealisticOfficeError;
use idealistic_office\api\handlers\IdealisticOfficeHistoryIndependent;
use idealistic_office\api\handlers\IdealisticOfficeReader;
use idealistic_office\api\IdealisticOfficeVariable;
use idealistic_office\api\objects\helpers\attachment\AbstractIdealisticOfficeAttachment;
use idealistic_office\api\objects\IdealisticOfficeFolder;
use idealistic_office\api\objects\IdealisticOfficeHistory;
use idealistic_office\api\objects\IdealisticOfficePortal;
use idealistic_office\api\objects\IdealisticOfficePrompt;
use idealistic_office\api\objects\IdealisticOfficeRole;
use idealistic_office\api\objects\IdealisticOfficeTeam;
use idealistic_office\api\objects\IdealisticOfficeUser;
use IdealisticOfficeGeneralMessage;
use IdealisticOfficeLimit;
use IdealisticOfficePromptTask;
use IdealisticOfficeQuery;
use IdealisticOfficeTable;
use PhpAsyncSerializable;
use React\EventLoop\LoopInterface;

class IdealisticOfficePromptCache implements PhpAsyncSerializable
{

    private IdealisticOfficePrompt $prompt;
    private ?array $folders, $users, $roles,
        $foundURLs, $hierarchyLevels,
        $directlyMentionedAttachments;
    private array
        $finishedProcessingTasks,
        $callables,
        $dependsOn, $runAfter,
        $enabledTasks,
        $separatedWords;
    private ?bool $admin;
    private int $includedMessages;
    private float $remainingIncludedMessages;
    private ?LoopInterface $loop;
    private IdealisticOfficeHistory $history;
    private bool $hasUnlimitedCredits, $delay;
    private ?string $aiReply, $webQuery, $webResults;
    private string $defaultLanguage;

    public function __construct(
        IdealisticOfficePrompt  $prompt,
        IdealisticOfficeHistory $history,
        ?LoopInterface          $loop,
        string                  $defaultLanguage
    )
    {
        $this->prompt = $prompt;
        $this->history = $history;
        $this->finishedProcessingTasks = array();
        $this->callables = array();
        $this->runAfter = array();
        $this->dependsOn = array();
        $this->loop = $loop;
        $this->delay = false;
        $this->webQuery = null;
        $this->webResults = null;
        $this->defaultLanguage = $defaultLanguage;
        $this->setTeam(null, false);
    }

    // Separator

    public function setWebResults(string $query, string $results): void
    {
        $this->webQuery = $query;
        $this->webResults = $results;
        IdealisticOfficeError::sqlInsert(
            $this->getTeam(),
            $this->prompt->getUser(),
            IdealisticOfficeTable::HISTORY_WEB_DATA_TABLE,
            array(
                "history_id" => $this->getHistory()->getId(),
                "query" => $query,
                "results" => $results
            )
        );
    }

    public function getWebQuery(): ?string
    {
        return $this->webQuery;
    }

    public function getWebResults(): ?string
    {
        return $this->webResults;
    }

    // Separator

    public function setTaskCallable(string $taskID, callable $callable): void
    {
        $this->callables[$taskID] = $callable;
    }

    public function setToRunAfter(string $taskID, $dependentTaskID): void
    {
        if (!isset($this->runAfter[$taskID])) {
            $this->runAfter[$taskID] = array($dependentTaskID);
        } else {
            $this->runAfter[$taskID][] = $dependentTaskID;
        }
    }

    public function setToDependOn(string $taskID, array $dependencies): void
    {
        $this->dependsOn[$taskID] = $dependencies;
    }

    public function isDependentTask(string $taskID): bool
    {
        return array_key_exists($taskID, $this->dependsOn);
    }

    private function notifyAboutDelay(): void
    {
        if (!$this->delay
            && in_array($this->prompt->getPlatformId(), IdealisticOfficeLimit::IS_USING_TYPING_ANIMATION)
            && $this->prompt->getGeneralTimePassed() > IdealisticOfficeVariable::HISTORY_PROMPT_SECONDS_DELAY_NOTIFICATION
            && !$this->getHistory()->replied()) {
            $this->prompt->notifyWithoutTrace(
                IdealisticOfficeGeneralMessage::PROMPT_WAIT_STILL_RESPONSE
            );
        }
    }

    public function justifyDelay(): void
    {
        $this->delay = true;
    }

    public function runDependencies(string $taskID): void
    {
        $dependencies = $this->runAfter[$taskID] ?? null;

        if (!empty($dependencies)) {
            foreach ($dependencies as $dependentTaskID) {
                $dependsOn = $this->dependsOn[$dependentTaskID] ?? null;

                if ($dependsOn === null) {
                    $callable = $this->callables[$dependentTaskID] ?? null;

                    if ($callable !== null) {
                        unset($this->callables[$dependentTaskID]);
                        $this->getLoop()->futureTick($callable);
                    }
                } else {
                    $key = array_search($taskID, $dependsOn);

                    if ($key !== false) {
                        unset($this->dependsOn[$dependentTaskID][$key]);
                    }
                    if (empty($this->dependsOn[$dependentTaskID])) {
                        $callable = $this->callables[$dependentTaskID] ?? null;

                        if ($callable !== null) {
                            unset($this->callables[$dependentTaskID]);
                            $this->getLoop()->futureTick($callable);
                        }
                    }
                }
            }
        }
        $this->notifyAboutDelay();
    }

    // Separator

    public function getEmbedding(string $string, bool $save = false): mixed
    {
        return IdealisticOfficeAI::getEmbedding(
            $this->prompt,
            $string,
            $save
        );
    }

    // Separator

    private function calculateEnabledTasks(): void
    {
        $tasks = IdealisticOfficePromptTask::TASKS_LIST;
        $team = $this->getTeam();

        foreach ($tasks as $key => $taskID) {
            if (!IdealisticOfficePromptTask::isEnabled(
                $team,
                $taskID
            )) {
                unset($tasks[$key]);
            }
        }
        $this->enabledTasks = $tasks;
    }

    public function isTaskEnabled(
        ?string $taskID
    ): bool
    {
        return $taskID !== null
            && in_array($taskID, $this->enabledTasks);
    }

    // Separator

    public function isTaskProcessing(string $taskID): bool
    {
        return !$this->isTaskEnabled($taskID)
            || !in_array($taskID, $this->finishedProcessingTasks);
    }

    public function terminateTask(string $taskID, ?callable $debug = null): void
    {
        if ($this->isTaskProcessing($taskID)) {
            $this->finishedProcessingTasks[] = $taskID;

            if ($debug !== null) {
                $debug($taskID);
            }
        }
    }

    public function getEnabledTasks(): array
    {
        return $this->enabledTasks;
    }

    // Separator

    public function getLoop(): ?LoopInterface
    {
        return $this->loop;
    }

    public function getHistory(): IdealisticOfficeHistory
    {
        return $this->history;
    }

    public function getAiReply(): string
    {
        return $this->aiReply ?? $this->getImplodedPrompts();
    }

    public function setAiReply(string $reply): void
    {
        $this->aiReply = $reply;
    }

    // Separator

    public function getLanguageSelection(): IdealisticOfficeLanguageSelection
    {
        if ($this->prompt->getUser()->hasLanguageSelection()) {
            return $this->prompt->getUser()->getLanguageSelection(false);
        } else {
            return new IdealisticOfficeLanguageSelection(
                $this->getTeam(),
                $this->defaultLanguage,
                false
            );
        }
    }

    // Separator

    public function getTeam(): AbstractIdealisticOfficeTeam
    {
        return $this->history->getTeam();
    }

    public function getEvolvedTeam(): IdealisticOfficeTeam
    {
        $team = $this->getTeam();
        return $team instanceof IdealisticOfficeTeam
            ? $team
            : new IdealisticOfficeTeam($team, false);
    }

    public function setTeam(?AbstractIdealisticOfficeTeam $team, bool $track = true): void
    {
        if ($team !== null
            && $this->getTeam()->getId() !== $team->getId()) {
            IdealisticOfficeError::setSqlQuery(
                $team,
                $this->prompt->getUser(),
                IdealisticOfficeTable::HISTORY_TOTAL_TABLE,
                array(
                    "team_id" => $team->getId()
                ),
                array(
                    array("id", $this->getHistory()->getId())
                ),
                null,
                1
            );
            $this->history->setTeam($team);
        }
        $this->folders = null;
        $this->users = null;
        $this->roles = null;
        $this->admin = null;
        $this->separatedWords = array();
        $this->foundURLs = null;
        $this->hierarchyLevels = null;
        $this->hasUnlimitedCredits = $this->prompt->getTeam()->getCredit()->hasUnlimited();
        $this->includedMessages = $this->prompt->getTeam()->getCredit()->getIncludedMessages();
        $this->remainingIncludedMessages = $this->hasUnlimitedCredits
            ? 0
            : $this->prompt->getTeam()->getCredit()->getRemainingIncludedMessages($this->includedMessages);
        $this->aiReply = null;
        $this->directlyMentionedAttachments = null;
        $this->calculateEnabledTasks();

        // Always last

        if ($track) {
            $this->prompt->trackUserIdentity();
        }
    }

    // Separator

    public function hasUnlimitedCredits(): bool
    {
        return $this->hasUnlimitedCredits;
    }

    public function isSubscriber(): bool
    {
        return $this->getTeam()->getCredit()->isSubscriber($this->prompt);
    }

    public function needsCharging(): bool
    {
        return $this->getTeam()->getCredit()->needsCharging($this->prompt);
    }

    public function getIncludedMessages(): int
    {
        return $this->includedMessages;
    }

    public function subtractIncludedMessage(float $amount, bool $action): bool
    {
        if ($this->remainingIncludedMessages >= $amount) {
            if ($action) {
                $this->remainingIncludedMessages -= $amount;
            }
            return true;
        } else {
            return false;
        }
    }

    public function canChargeCredits(): bool
    {
        return $this->hasUnlimitedCredits()
            || $this->getTeam()->getCredit()->getRemainingCredits() >= IdealisticOfficeVariable::MINIMUM_CREDITS_TO_USE;
    }

    // Separator

    public function getPortal(int|string $reference, bool $removed = false): ?IdealisticOfficePortal
    {
        return $this->getTeam()->getPortal($reference, "id", false, $removed)
            ?? ($this->getTeam()->getPortal($reference, "name", false, $removed)
                ?? $this->getTeam()->getPortal($reference, "random_id", false, $removed));
    }

    // Separator

    /** @return IdealisticOfficeFolder[] */
    public function getFolders(): array
    {
        if ($this->folders === null) {
            $folders = array();
            $data = $this->prompt->getUser()->getFolders(false);

            foreach ($data as $folder) {
                if (!$folder->canView($this->prompt->getUser())) {
                    continue;
                }
                $folders[$folder->getId()] = $folder;
            }
            $this->folders = $folders;
        }
        return $this->folders;
    }

    public function getSelectedFolder(): ?IdealisticOfficeFolder
    {
        $selectedID = $this->prompt->getUser()->getSelectedFolderId();

        if ($selectedID === null) {
            $folders = $this->getFolders();
            return sizeof($folders) === 1
                ? array_shift($folders)
                : null;
        }
        return $this->getTeam()->getFolder($selectedID, false, false);
    }

    public function folderExistsInMemory(int|string $reference): bool
    {
        return isset($this->getFolders()[$reference]);
    }

    public function clearFolders(): void
    {
        $this->folders = null;
    }

    // Separator

    /** @return IdealisticOfficeUser[] */
    public function getUsers(): array
    {
        if ($this->users === null) {
            $this->users = $this->getTeam()->getUsers(false); // Already sorted by ID
        }
        return $this->users;
    }

    public function getUser(int|string|null $userId): ?IdealisticOfficeUser
    {
        if ($userId === null) {
            return null;
        }
        if (is_numeric($userId)) {
            return $this->getUsers()[$userId] ?? null;
        } else {
            $userId = strtolower(trim($userId));

            foreach ($this->getUsers() as $user) {
                if (strtolower($user->getDisplayName()) === $userId) {
                    return $user;
                }
            }
            return null;
        }
    }

    public function clearUsers(): void
    {
        $this->users = null;
        $this->hierarchyLevels = null;
    }

    // Separator

    public function getHierarchyLevels(): array
    {
        if ($this->hierarchyLevels === null) {
            $this->hierarchyLevels = $this->getTeam()->getPartialManager()->getSortedLevels();
        }
        return $this->hierarchyLevels;
    }

    public function getHierarchyLevel(int|string $levelId): int
    {
        $levels = $this->getHierarchyLevels();
        $level = $levels[$levelId];

        if ($level === null) {
            $level = array_pop($levels) ?? 1;
        }
        return $level;
    }

    // Separator

    /** @return IdealisticOfficeRole[] */
    public function getRoles(): array
    {
        if ($this->roles === null) {
            $this->roles = $this->getTeam()->getRoles(false);
        }
        return $this->roles;
    }

    public function clearRoles(): void
    {
        $this->roles = null;
        $this->hierarchyLevels = null;
    }

    // Separator

    public function getSeparatedWords(int $limit = -1): array
    {
        if (sizeof($this->separatedWords[$limit] ?? array()) <= 1) {
            $this->separatedWords[$limit] = preg_split('/\s+/', trim($this->getImplodedPrompts()), $limit);
        }
        return $this->separatedWords[$limit];
    }

    public function getImplodedPrompts(
        string $separator = IdealisticOfficeVariable::DEFAULT_TEXT_SEPARATOR
    ): string
    {
        return $this->getImplodedStrings(
            $this->getHistory()->getPrompts(),
            $separator,
            null
        );
    }

    public function getImplodedStrings(
        array   $array,
        string  $separator = IdealisticOfficeVariable::DEFAULT_TEXT_SEPARATOR,
        ?string $empty = "[]"
    ): string
    {
        $strings = implode(
            $separator,
            $array
        );
        return $empty !== null && empty($strings)
            ? $empty
            : $strings;
    }

    // Separator

    public function getAttachments(): array
    {
        $separatedWords = $this->getSeparatedWords();

        if ($this->directlyMentionedAttachments === null
            || empty($this->directlyMentionedAttachments) && sizeof($separatedWords) <= 1) {
            $mentioned = array();
            $historyIDs = $this->getTeam()->getHistoryIDs();

            if (!empty($historyIDs)) {
                $findCurrentId = array_search($this->getHistory()->getId(), $historyIDs);

                if ($findCurrentId !== false) {
                    unset($historyIDs[$findCurrentId]);
                }
                if (!empty($historyIDs)) {
                    $createdAttachments = IdealisticOfficeHistoryIndependent::getHistoryFromSubTable(
                        IdealisticOfficeTable::HISTORY_CREATED_ATTACHMENTS_TABLE,
                        IdealisticOfficeQuery::CREATED_PARTIAL_ATTACHMENT,
                        $historyIDs,
                        false,
                        IdealisticOfficeLimit::MAX_PROCESSED_ROWS / 2,
                        null
                    );
                    $createdAttachments = IdealisticOfficeReader::getPartialAttachments($createdAttachments);
                    $userAttachments = IdealisticOfficeHistoryIndependent::getHistoryFromSubTable(
                        IdealisticOfficeTable::HISTORY_USER_ATTACHMENTS_TABLE,
                        IdealisticOfficeQuery::PARTIAL_ATTACHMENT,
                        $historyIDs,
                        false,
                        IdealisticOfficeLimit::MAX_PROCESSED_ROWS - sizeof($createdAttachments),
                        null
                    );
                    $userAttachments = IdealisticOfficeReader::getPartialAttachments($userAttachments);
                    $attachments = array_merge(
                        $createdAttachments,
                        $userAttachments
                    );

                    if (!empty($attachments)) {
                        $prepared = array();

                        foreach ($attachments as $attachment) {
                            if (!($attachment instanceof AbstractIdealisticOfficeAttachment)) {
                                continue;
                            }
                            $prepared[$attachment->getId()] = $attachment;
                            $prepared[$attachment->getName()] = $attachment;
                            $prepared[$attachment->getNoFormatName()] = $attachment;
                        }

                        if (sizeof($separatedWords) > 1) {
                            foreach ($separatedWords as $word) {
                                if (isset($prepared[$word])) {
                                    $mentioned[] = $prepared[$word];
                                }
                            }
                        }
                    }
                }
            }
            $this->directlyMentionedAttachments = array_merge(
                $mentioned,
                $this->getHistory()->getAttachments(null, null, false)
            );
        }
        return $this->directlyMentionedAttachments;
    }

    // Separator

    public function getFoundURLs(): array
    {
        if ($this->foundURLs === null) {
            $prompts = $this->getImplodedPrompts();

            if (empty($prompts)) {
                $prompts = $this->prompt->getPrompt();
            }
            if (empty($prompts)) {
                $this->foundURLs = array();
            } else {
                $this->foundURLs = IdealisticOfficeReader::findURLs($prompts);
            }
        }
        return $this->foundURLs;
    }

    public function addExtraURL(string $url, string $description): mixed
    {
        if (is_url($url)) {
            $timezone = $this->prompt->getUser()->getTimezone(false);
            return IdealisticOfficeError::sqlInsert(
                $this->getTeam(),
                $this->prompt->getUser(),
                IdealisticOfficeTable::HISTORY_LINKS_TABLE,
                array(
                    "history_id" => $this->getHistory()->getId(),
                    "link_url" => $url,
                    "analyzed_description" => $description,
                    "creation_date" => IdealisticOfficeReader::getCurrentDate($timezone),
                    "creation_time_zone" => $timezone->getTimeZone(),
                    "created_by" => $this->prompt->getUser()->getId(),
                    "web_search" => true
                )
            );
        } else {
            return false;
        }
    }

    public function isAdminUser(): bool
    {
        if ($this->admin === null) {
            $this->admin = $this->prompt->getUser()->isAdministrator();
        }
        return $this->admin;
    }

}
