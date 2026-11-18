<?php

namespace idealistic_office\api\tasks\prompts\scheduler;

use AIObjectConstructor;
use idealistic_office\api\handlers\IdealisticOfficeReader;
use idealistic_office\api\IdealisticOfficeVariable;
use idealistic_office\api\objects\helpers\IdealisticOfficeAI;
use idealistic_office\api\objects\helpers\IdealisticOfficeOutcome;
use idealistic_office\api\objects\IdealisticOfficePrompt;
use idealistic_office\api\objects\IdealisticOfficeScheduler;
use idealistic_office\api\objects\IdealisticOfficeUser;
use idealistic_office\api\tasks\prompts\folder\IdealisticOfficePromptShareInformation;
use IdealisticOfficePromptFields;
use IdealisticOfficePromptTask;
use PhpAsyncSerializable;
use function React\Promise\reject;
use function React\Promise\resolve;

class IdealisticOfficePromptManageScheduler implements PhpAsyncSerializable
{

    public static function description(IdealisticOfficePrompt $prompt): ?string
    {
        return "[SECONDARY FEATURE] Schedules are prompts (like the one right now) that run for you automatically when you set them to.";
    }

    public static function fields(IdealisticOfficePrompt $prompt): array
    {
        return array(
            "0_scheduled_task_instructions" => IdealisticOfficePromptFields::nullableString(
                "The instructions for the scheduled task to run as a prompt"
            ),
            "0_scheduled_task_optional_seconds_frequency" => IdealisticOfficePromptFields::nullableInteger(
                "The optional frequency in seconds to run the instructions as a prompt",
                -1
            ),
            "0_scheduled_task_optional_month" => IdealisticOfficePromptFields::nullableInteger(
                "The optional announcement month to run the instructions as a prompt"
            ),
            "0_scheduled_task_optional_day" => IdealisticOfficePromptFields::nullableInteger(
                "The optional announcement day to run the instructions as a prompt"
            ),
            "0_scheduled_task_optional_hour" => IdealisticOfficePromptFields::nullableInteger(
                "The optional announcement hour to run the instructions as a prompt"
            ),
            "0_scheduled_task_optional_minute" => IdealisticOfficePromptFields::nullableInteger(
                "The optional announcement minutes to run the instructions as a prompt"
            ),


            "1_scheduled_tasks_to_delete" => IdealisticOfficePromptFields::nullableIntegerArray(
                "The IDs of the scheduled tasks to delete"
            ),


            "2_scheduled_tasks_to_restore" => IdealisticOfficePromptFields::nullableIntegerArray(
                "The IDs of the scheduled tasks to restore"
            )
        );
    }

    public static function create(
        IdealisticOfficeUser $user,
        string               $instructions,
        ?int                 $month,
        ?int                 $day,
        ?int                 $hour,
        ?int                 $minute,
        ?int                 $secondsFrequency,
        int                  $historyId
    ): IdealisticOfficeOutcome
    {
        $timezone = $user->getTimezone(false);
        return (new IdealisticOfficeScheduler(
            $user->getTeam(),
            0,
            $user->getAccount(),
            IdealisticOfficeReader::getCurrentDate($timezone),
            $timezone->getTimeZone(),
            $instructions,
            $month,
            $day,
            $hour,
            $hour !== null ? ($minute ?? 0) : null,
            IdealisticOfficeReader::getCurrentDate(),
            $hour === null ? $secondsFrequency : null,
            $historyId,
            false
        ))->create();
    }

    public static function delete(IdealisticOfficeUser $user, int $id): IdealisticOfficeOutcome
    {
        $object = $user->getSpecificScheduledTask($id, false);
        return $object !== null
            ? $object->delete()
            : new IdealisticOfficeOutcome(false, "No scheduled task found to delete with ID '" . $id . "'");
    }

    public static function restore(IdealisticOfficeUser $user, int $id): IdealisticOfficeOutcome
    {
        $object = $user->getSpecificScheduledTask($id, false, true);
        return $object !== null
            ? $object->restore()
            : new IdealisticOfficeOutcome(false, "No scheduled task found to restore with ID '" . $id . "'");
    }

    public static function run(
        IdealisticOfficePrompt $prompt
    ): void
    {
        $prompt->waitForTasks(array(
            IdealisticOfficePromptTask::TEAM_INFO
        ));
        $prompt->runTask(function () use ($prompt) {
            try {
                if (IdealisticOfficePromptShareInformation::isSharing($prompt)) {
                    return resolve(null);
                }
                $object = new AIObjectConstructor(
                    $prompt->expandFields(self::fields($prompt))
                );

                $arguments = array(
                    "messages" => array(
                        array(
                            "role" => "system",
                            "content" => $object->build()
                        ),
                        array(
                            "role" => "user",
                            "content" => $prompt->getCache()->getAiReply()
                        )
                    ),
                    "response_format" => array("type" => "json_object")
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
                )->then(function ($result) use ($prompt, $object) {
                    $verify = $prompt->readAIObject($object, $result);

                    if ($verify === null) {
                        return resolve(null);
                    } else {
                        $outcomes = array();

                        if (!empty($verify->{"0_scheduled_task_instructions"})
                            && (!empty($verify->{"0_scheduled_task_optional_hour"})
                                || !empty($verify->{"0_scheduled_task_optional_seconds_frequency"}))) {
                            $outcomes[] = self::create(
                                $prompt->getUser(),
                                $verify->{"0_scheduled_task_instructions"},
                                $verify->{"0_scheduled_task_optional_month"},
                                $verify->{"0_scheduled_task_optional_day"},
                                $verify->{"0_scheduled_task_optional_hour"},
                                $verify->{"0_scheduled_task_optional_minute"},
                                $verify->{"0_scheduled_task_optional_seconds_frequency"},
                                $prompt->getHistory()->getId()
                            );
                        }
                        if (!empty($verify->{"1_scheduled_tasks_to_delete"})) {
                            foreach ($verify->{"1_scheduled_tasks_to_delete"} as $id) {
                                $outcomes[] = self::delete($prompt->getUser(), $id);
                            }
                        }
                        if (!empty($verify->{"2_scheduled_tasks_to_restore"})) {
                            foreach ($verify->{"2_scheduled_tasks_to_restore"} as $id) {
                                $outcomes[] = self::restore($prompt->getUser(), $id);
                            }
                        }
                        return resolve($outcomes);
                    }
                });
            } catch (Throwable $e) {
                return reject($e);
            }
        });
    }

}