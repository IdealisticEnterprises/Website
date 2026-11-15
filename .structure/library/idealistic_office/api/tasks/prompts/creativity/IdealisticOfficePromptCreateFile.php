<?php

use function React\Promise\reject;
use function React\Promise\resolve;

class IdealisticOfficePromptCreateFile implements PhpAsyncSerializable
{

    public static function blacklistedFields(IdealisticOfficePrompt $prompt): array
    {
        return array("user_instructions");
    }

    public static function fields(IdealisticOfficePrompt $prompt): array
    {
        return array(
            "is_image" => new AIFieldObject(
                AIField::BOOLEAN,
                null,
                false,
                "Is the file being created is an image",
                null,
                false
            ),
            "file_modification" => new AIFieldObject(
                AIField::BOOLEAN,
                null,
                false,
                "Does the contact want to modify an existing file",
                null,
                false
            ),
            "file_format" => new AIFieldObject(
                AIField::STRING,
                null,
                false,
                "The format of the file",
                null,
                "application/octet-stream"
            ),
            "file_name" => new AIFieldObject(
                AIField::STRING,
                null,
                false,
                "The name of the file",
                null,
                "unnamed"
            ),
            "file_content" => new AIFieldObject(
                AIField::STRING,
                null,
                false,
                "The contents of the file to be calculated (no name, format, or other metadata)"
            ),
            "user_instructions" => new AIFieldObject(
                AIField::STRING,
                null,
                true,
                "The contact's instruction for the file creation"
            )
        );
    }

    public static function run(
        IdealisticOfficePrompt $prompt
    ): void
    {
        $prompt->waitForTasks(array(IdealisticOfficePromptTask::TEAM_INFO));
        $prompt->runTask(function () use ($prompt) {
            try {
                $object = new AIObjectConstructor(
                    $prompt->expandFields(self::fields($prompt)),
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
                    $arguments,
                    IdealisticOfficeVariable::TASK_AI_LOW_VERBOSITY,
                    IdealisticOfficeVariable::TASK_AI_INCREASED_REASONING_EFFORT
                );
                return $AI->getReactResult(
                    $arguments
                )->then(function ($result) use ($prompt, $object) {
                    $verify = $prompt->readAIObject($object, $result);

                    if ($verify !== null
                        && !$verify->is_image
                        && !$verify->file_modification) {
                        $format = trim(strtolower($verify->file_format));
                        $name = $verify->file_name ?? random_string(
                            IdealisticOfficeLimit::RANDOM_STRING_LENGTH
                        );
                        $timezone = $prompt->getUser()->getTimezone(false);
                        $attachment = new IdealisticOfficeAttachment(
                            null,
                            $name
                            . (str_contains($name, ".")
                            || str_contains($format, "/")
                                ? ""
                                : "." . $format),
                            $format,
                            null,
                            -1,
                            null,
                            null,
                            null,
                            base64_encode($verify->file_content),
                            null,
                            true,
                            IdealisticOfficeReader::getCurrentDate($timezone),
                            $timezone->getTimeZone(),
                            $prompt->getUser()
                        );
                        $attachment->setUserPrompt(
                            $verify->user_instructions ?? ""
                        );

                        if ($attachment->isValid()) {
                            return $prompt->sendAttachment(
                                $attachment,
                                null,
                                true
                            );
                        } else {
                            return resolve(new IdealisticOfficeOutcome(
                                false,
                                "Failed to complete the following task: file creation"
                            ));
                        }
                    }
                    return resolve(null);
                });
            } catch (Throwable $e) {
                return reject($e);
            }
        });
    }

}
