<?php

namespace idealistic_office\api\objects;

use account\api\objects\account\Account;
use idealistic_office\api\handlers\IdealisticOfficeError;
use idealistic_office\api\handlers\IdealisticOfficeReader;
use idealistic_office\api\handlers\IdealisticOfficeTeamInitiator;
use idealistic_office\api\objects\helpers\AbstractIdealisticOfficeTeam;
use idealistic_office\api\objects\helpers\change\AbstractIdealisticOfficeInformationChange;
use idealistic_office\api\objects\helpers\IdealisticOfficeOutcome;
use IdealisticOfficeAccessPlatform;
use IdealisticOfficeLimit;
use IdealisticOfficePermission;
use IdealisticOfficeTable;

class IdealisticOfficeScheduler extends AbstractIdealisticOfficeInformationChange
{

    public const MINIMUM_SECONDS_FREQUENCY = 60 * 60;

    private ?int $hour, $minute, $secondsFrequency, $month, $day;
    private ?string $nextDate;
    private int $historyId;

    public function __construct(
        AbstractIdealisticOfficeTeam $team,
        int|string                   $id,
        int|Account                  $createdBy,
        string                       $creationDate,
        string                       $creationCreationTimeZone,
        ?string                      $creationReason,
        ?int                         $month,
        ?int                         $day,
        ?int                         $hour,
        ?int                         $minute,
        ?string                      $nextDate,
        ?int                         $secondsFrequency,
        int                          $historyId,
        bool                         $json = true
    )
    {
        parent::__construct($id, $createdBy, $team, $creationReason, $creationDate, $creationCreationTimeZone, $json);
        $this->hour = $hour;
        $this->minute = $minute;
        $this->nextDate = $nextDate;
        $this->secondsFrequency = $secondsFrequency;
        $this->historyId = $historyId;
        $this->month = $month;
        $this->day = $day;

        if ($json) {
            self::addToJSON("month", $month);
            self::addToJSON("day", $day);
            self::addToJSON("hour", $hour);
            self::addToJSON("minute", $minute);
            self::addToJSON("next_date", $nextDate);
            self::addToJSON("seconds_frequency", $secondsFrequency);
            self::addToJSON("history_id", $historyId);
        }
    }

    public function getMonth(): ?int
    {
        return $this->month;
    }

    public function getDay(): ?int
    {
        return $this->day;
    }

    public function getHour(): ?string
    {
        return $this->hour === null
        || $this->hour < 0
            ? null
            : str_pad((string)$this->hour, 2, "0", STR_PAD_LEFT);
    }

    public function getMinute(): ?string
    {
        return $this->minute === null
        || $this->minute < 0
            ? null
            : str_pad((string)$this->minute, 2, "0", STR_PAD_LEFT);
    }

    public function getNextDate(): ?string
    {
        return $this->nextDate;
    }

    public function setNextDate(?string $date = null): IdealisticOfficeOutcome
    {
        $frequency = $this->getSecondsFrequency(false) ?? self::MINIMUM_SECONDS_FREQUENCY;

        if ($date === null) {
            $date = IdealisticOfficeReader::getFutureDate(
                $frequency . " seconds"
            );
        }
        $creator = $this->getCreator();

        if ($creator === null) {
            return new IdealisticOfficeOutcome(
                false,
                "Creator account not found."
            );
        }
        if (IdealisticOfficeError::setSqlQuery(
            $this->team,
            $creator,
            IdealisticOfficeTable::SCHEDULER_TABLE,
            array(
                "next_date" => $date
            ),
            array(
                array("id", $this->id)
            ),
            null,
            1
        )) {
            $this->nextDate = $date;
            self::setToJSON("next_date", $date);
            return new IdealisticOfficeOutcome(
                true,
                "Next date updated successfully for scheduled task '" . $this->getCreationReason() . "'."
            );
        } else {
            return new IdealisticOfficeOutcome(
                false,
                "Failed to update next date for scheduled task '" . $this->getCreationReason() . "'."
            );
        }
    }

    public function hasTimePreference(): bool
    {
        return $this->getHour() !== null
            && $this->getMinute() !== null;
    }

    public function getSecondsFrequency(bool $protection = true): ?int
    {
        return $this->secondsFrequency === null
        || $this->secondsFrequency <= 0
            ? null
            : ($protection
                ? max(self::MINIMUM_SECONDS_FREQUENCY, $this->secondsFrequency)
                : $this->secondsFrequency);
    }

    public function create(): IdealisticOfficeOutcome
    {
        if ($this->id > 0) {
            return new IdealisticOfficeOutcome(
                false,
                "This scheduled task already exists."
            );
        }
        $user = $this->getCreator();

        if ($user === null) {
            return new IdealisticOfficeOutcome(
                false,
                "Creator account not found."
            );
        }
        if (!$user->hasEstablishedAccess()) {
            return new IdealisticOfficeOutcome(false, "No team access established.");
        }
        $identity = $user->getLastIdentity();

        if ($identity === null) {
            return new IdealisticOfficeOutcome(
                false,
                "Creator identity not found."
            );
        }
        if ($identity->getId() === IdealisticOfficeAccessPlatform::NATIVE
            && !$user->hasEmail()) {
            return new IdealisticOfficeOutcome(
                false,
                "You must have a valid email address associated with your account to create scheduled tasks."
            );
        }
        if (!$user->hasPermission(IdealisticOfficePermission::ADJUST_SCHEDULER)->isPositiveOutcome()) {
            return new IdealisticOfficeOutcome(
                false,
                "You do not have permission to manage scheduled tasks."
            );
        }
        $tasks = $user->getScheduledTasks();

        if (sizeof($tasks) >= IdealisticOfficeLimit::MAX_EXTENSIONS_PER_OBJECT_LARGE) {
            return new IdealisticOfficeOutcome(
                false,
                "You have reached the maximum number of scheduled tasks allowed, that being " . IdealisticOfficeLimit::MAX_EXTENSIONS_PER_OBJECT_LARGE . "."
            );
        }
        if (!empty($tasks)) {
            foreach ($tasks as $task) {
                if ($task->getCreationReason() === $this->getCreationReason()) {
                    return new IdealisticOfficeOutcome(
                        false,
                        "You already have a scheduled task with the same creation reason. Please choose a different reason to distinguish them."
                    );
                }
                if ($this->hasTimePreference()
                    && $this->getMonth() === $task->getMonth()
                    && $this->getDay() === $task->getDay()
                    && $this->getHour() === $task->getHour()
                    && $this->getMinute() === $task->getMinute()) {
                    return new IdealisticOfficeOutcome(
                        false,
                        "You already have a scheduled task with the same time preference. Please choose a different time to distinguish them."
                    );
                }
                $secondsFrequency = $this->getSecondsFrequency(false);

                if ($secondsFrequency !== null
                    && $secondsFrequency === $task->getSecondsFrequency(false)) {
                    return new IdealisticOfficeOutcome(
                        false,
                        "You already have a scheduled task with the same frequency. Please choose a different frequency to distinguish them."
                    );
                }
            }
        }
        if ($this->getSecondsFrequency(false) === null) {
            return new IdealisticOfficeOutcome(
                false,
                "Seconds frequency must be set."
            );
        }
        if ($this->getMonth() !== null
            && ($this->month < 1 || $this->month > 12)) {
            return new IdealisticOfficeOutcome(false, "Scheduled task month must be between 1 and 12.");
        }
        if ($this->getDay() !== null
            && ($this->day < 1 || $this->day > 31)) {
            return new IdealisticOfficeOutcome(false, "Scheduled task day must be between 1 and 31.");
        }
        if ($this->getHour() !== null
            && ($this->hour < 0 || $this->hour > 23)) {
            return new IdealisticOfficeOutcome(false, "Scheduled task hour must be between 0 and 23.");
        }
        if ($this->getMinute() !== null
            && ($this->minute < 0 || $this->minute > 59)) {
            return new IdealisticOfficeOutcome(false, "Scheduled task minute must be between 0 and 59.");
        }
        if (strlen($this->getCreationReason()) > IdealisticOfficeLimit::K8_LENGTH) {
            return new IdealisticOfficeOutcome(
                false,
                "Creation reason exceeds maximum length of " . IdealisticOfficeLimit::K8_LENGTH . " characters."
            );
        }
        if (IdealisticOfficeError::sqlInsert(
            $this->team,
            $user,
            IdealisticOfficeTable::SCHEDULER_TABLE,
            array(
                "hour" => $this->getHour(),
                "minute" => $this->getMinute(),
                "seconds_frequency" => $this->getSecondsFrequency(),
                "creation_reason" => $this->getCreationReason(),
                "creation_date" => $this->getCreationDate(),
                "creation_time_zone" => $this->getCreationDateTimeZone(),
                "account_id" => $user->getId(),
                "team_id" => $this->team->getId(),
                "history_id" => $this->historyId
            )
        )) {
            $message = "Scheduled task '" . $this->getCreationReason() . "' created successfully.";

            if ($this->getSecondsFrequency(false) < self::MINIMUM_SECONDS_FREQUENCY) {
                $message .= " Also, the specified frequency is below the minimum allowed. It has been set to the minimum of " . self::MINIMUM_SECONDS_FREQUENCY . " seconds.";
            }
            return new IdealisticOfficeOutcome(
                true,
                $message
            );
        } else {
            return new IdealisticOfficeOutcome(
                false,
                "Failed to create scheduled task '" . $this->getCreationReason() . "'."
            );
        }
    }

    public function delete(?string $justification = null): IdealisticOfficeOutcome
    {
        if ($this->id <= 0) {
            return new IdealisticOfficeOutcome(
                false,
                "This scheduled task does not exist."
            );
        }
        $user = $this->getCreator();

        if ($user === null) {
            return new IdealisticOfficeOutcome(
                false,
                "Creator account not found."
            );
        }
        if (!$user->hasEstablishedAccess()) {
            return new IdealisticOfficeOutcome(false, "No team access established.");
        }
        if (!$user->hasPermission(IdealisticOfficePermission::ADJUST_SCHEDULER)->isPositiveOutcome()) {
            return new IdealisticOfficeOutcome(
                false,
                "You do not have permission to manage scheduled tasks."
            );
        }
        if ($justification !== null
            && strlen($justification) > IdealisticOfficeLimit::K8_LENGTH) {
            return new IdealisticOfficeOutcome(
                false,
                "Justification exceeds maximum length of " . IdealisticOfficeLimit::K8_LENGTH . " characters."
            );
        }
        $timezone = $user->getTimeZone(false);

        if (IdealisticOfficeError::setSqlQuery(
            $this->team,
            $user,
            IdealisticOfficeTable::SCHEDULER_TABLE,
            array(
                "deletion_date" => IdealisticOfficeReader::getCurrentDate($timezone),
                "deletion_time_zone" => $timezone->getTimeZone(),
                "deletion_reason" => $justification
            ),
            array(
                array("id", $this->id)
            ),
            null,
            1
        )) {
            return new IdealisticOfficeOutcome(
                true,
                "Scheduled task '" . $this->getCreationReason() . "' deleted successfully."
            );
        } else {
            return new IdealisticOfficeOutcome(
                false,
                "Failed to delete scheduled task '" . $this->getCreationReason() . "'."
            );
        }
    }

    public function restore(): IdealisticOfficeOutcome
    {
        if ($this->id <= 0) {
            return new IdealisticOfficeOutcome(
                false,
                "This scheduled task does not exist."
            );
        }
        $user = $this->getCreator();

        if ($user === null) {
            return new IdealisticOfficeOutcome(
                false,
                "Creator account not found."
            );
        }
        if (!$user->hasEstablishedAccess()) {
            return new IdealisticOfficeOutcome(false, "No team access established.");
        }
        if (!$user->hasPermission(IdealisticOfficePermission::ADJUST_SCHEDULER)->isPositiveOutcome()) {
            return new IdealisticOfficeOutcome(
                false,
                "You do not have permission to manage scheduled tasks."
            );
        }
        if (IdealisticOfficeError::setSqlQuery(
            $this->team,
            $user,
            IdealisticOfficeTable::SCHEDULER_TABLE,
            array(
                "deletion_date" => null,
                "deletion_time_zone" => null,
                "deletion_reason" => null
            ),
            array(
                array("id", $this->id)
            ),
            null,
            1
        )) {
            return new IdealisticOfficeOutcome(
                true,
                "Scheduled task '" . $this->getCreationReason() . "' restored successfully."
            );
        } else {
            return new IdealisticOfficeOutcome(
                false,
                "Failed to restore scheduled task '" . $this->getCreationReason() . "'."
            );
        }
    }

    public function run(): IdealisticOfficeOutcome
    {
        $user = $this->getCreator();

        if ($user === null) {
            return new IdealisticOfficeOutcome(
                false,
                "Creator account not found."
            );
        }
        $lastIdentity = $user->getLastIdentity();

        if ($lastIdentity === null) {
            return new IdealisticOfficeOutcome(
                false,
                "Creator identity not found."
            );
        }
        $history = $this->team->getHistoryById($this->historyId);
        return IdealisticOfficeTeamInitiator::createPrompt(
            $user,
            $lastIdentity->getPlatformID(),
            $lastIdentity->getPlatformUserID(),
            $lastIdentity->getPlatformChatID(),
            $lastIdentity->getPlatformChatName(),
            $lastIdentity->getPlatformMessageID(),
            $lastIdentity->getUserName(),
            $lastIdentity->getDisplayName(),
            $this->getCreationReason(),
            $history?->getAttachments(null, null, false),
            true
        );
    }

}