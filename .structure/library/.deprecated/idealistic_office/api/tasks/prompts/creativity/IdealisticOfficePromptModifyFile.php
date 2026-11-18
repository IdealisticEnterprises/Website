<?php

namespace idealistic_office\api\tasks\prompts\creativity;

use AIField;
use AIFieldObject;
use AIObjectConstructor;
use idealistic_office\api\handlers\IdealisticOfficeReader;
use idealistic_office\api\IdealisticOfficeVariable;
use idealistic_office\api\objects\helpers\attachment\IdealisticOfficeAttachment;
use idealistic_office\api\objects\helpers\IdealisticOfficeAI;
use idealistic_office\api\objects\helpers\IdealisticOfficeOutcome;
use idealistic_office\api\objects\IdealisticOfficePrompt;
use idealistic_office\api\tasks\prompts\team\IdealisticOfficePromptTeamInfo;
use IdealisticOfficePromptTask;
use PhpAsyncSerializable;
use function React\Promise\reject;
use function React\Promise\resolve;

class IdealisticOfficePromptModifyFile implements PhpAsyncSerializable
{

    public static function run(
        IdealisticOfficePrompt $prompt
    ): void
    {
        $prompt->waitForTasks(array(IdealisticOfficePromptTask::TEAM_INFO));
        $prompt->runTask(function () use ($prompt) {
            try {
                $found = null;
                $decoded = null;
                $attachments = $prompt->getCache()->getAttachments();

                if (!empty($attachments)) {
                    foreach ($attachments as $attachment) {
                        if ($attachment->canBeAudio()
                            || $attachment->isImage()) {
                            continue;
                        }
                        $found = $attachment;
                        $decoded = $attachment->getDecodedData();

                        if (empty($decoded)) {
                            $found = null;
                            $decoded = null;
                            continue;
                        }
                        break;
                    }
                }

                if ($found === null) {
                    return resolve(null);
                }
                $object = new AIObjectConstructor(
                    $prompt->expandFields(array(
                        "user_instructions" => new AIFieldObject(
                            AIField::STRING,
                            null,
                            false,
                            "The modification instructions for the file's contents"
                        )
                    )),
                    array(
                        "useful_information" => IdealisticOfficePromptTeamInfo::getObject($prompt)
                    )
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
                )->then(function ($result) use ($prompt, $object, $found, $decoded) {
                    $verify = $prompt->readAIObject($object, $result);

                    if ($verify === null) {
                        return resolve(null);
                    } else {
                        $prompt->notifyWithoutTrace(
                            "Your request to modify a file is being processed. This may take a few moments."
                        );
                        $object = new AIObjectConstructor(
                            array(
                                "file_content" => new AIFieldObject(
                                    AIField::STRING,
                                    null,
                                    false,
                                    "The modified content of the file"
                                )
                            ),
                            array(
                                "Modify the file's content: " . $verify->user_instructions,
                                "Return the modified file's content in JSON format without markdown"
                            )
                        );
                        $arguments = array(
                            "messages" => array(
                                array(
                                    "role" => "system",
                                    "content" => $object->build()
                                ),
                                array(
                                    "role" => "user",
                                    "content" => $decoded
                                )
                            )
                        );
                        $AI = new IdealisticOfficeAI(
                            $prompt->getUser(),
                            IdealisticOfficeVariable::TASK_AI_CHEAP_MODEL
                        );
                        $AI->adjustCertainty(
                            $arguments,
                            IdealisticOfficeVariable::TASK_AI_LOW_VERBOSITY,
                            IdealisticOfficeVariable::TASK_AI_INCREASED_REASONING_EFFORT
                        );
                        return $AI->getReactResult(
                            $arguments
                        )->then(function ($result) use ($object, $found, $prompt) {
                            $verify = $prompt->readAIObject($object, $result);

                            if ($verify === null) {
                                return resolve(new IdealisticOfficeOutcome(
                                    false,
                                    "The file modification process failed to complete successfully, please try again."
                                ));
                            } else {
                                $timezone = $prompt->getUser()->getTimezone(false);
                                $attachment = new IdealisticOfficeAttachment(
                                    null,
                                    $found->getName(),
                                    $found->getFormat(),
                                    null,
                                    -1,
                                    $found->getXSize(),
                                    $found->getYSize(),
                                    null,
                                    base64_encode($verify->file_content),
                                    null,
                                    true,
                                    IdealisticOfficeReader::getCurrentDate($timezone),
                                    $timezone->getTimeZone(),
                                    $prompt->getUser()
                                );
                                $attachment->setUserPrompt($verify->user_instructions ?? "");

                                if ($attachment->isValid()) {
                                    return $prompt->sendAttachment(
                                        $attachment
                                    );
                                } else {
                                    return resolve(new IdealisticOfficeOutcome(
                                        false,
                                        "The file modification process failed to complete successfully due to an issue with the modified file, please try again."
                                    ));
                                }
                            }
                        });
                    }
                });
            } catch (Throwable $e) {
                return reject($e);
            }
        });
    }

}
