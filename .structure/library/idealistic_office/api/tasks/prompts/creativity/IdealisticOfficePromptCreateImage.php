<?php

use function React\Async\async;
use function React\Promise\reject;
use function React\Promise\resolve;

class IdealisticOfficePromptCreateImage implements PhpAsyncSerializable
{

    private const model = IdealisticOfficeVariable::TASK_AI_IMAGE_CREATING_MODEL;

    public static function fields(IdealisticOfficePrompt $prompt): array
    {
        $linearModel = AIHelper::isLinearImageModel(self::model);

        $array = array(
            "file_name" => new AIFieldObject(
                AIField::STRING,
                null,
                true,
                "The file's name of the image to be created"
            ),
            "quality" => new AIFieldObject(
                AIField::STRING,
                null,
                true,
                $linearModel
                    ? "low or medium or high"
                    : "standard or hd",
                $linearModel
                    ? array("low", "medium", "high")
                    : array("standard", "hd"),
                $linearModel
                    ? "low"
                    : "standard"
            ),
            "size" => new AIFieldObject(
                AIField::STRING,
                null,
                true,
                "horizontal or vertical or square",
                array(
                    "horizontal",
                    "vertical",
                    "square"
                ),
                "square"
            ),
            "description" => new AIFieldObject(
                AIField::STRING,
                null,
                true,
                "The description of the image to be created"
            ),
            "reference" => new AIFieldObject(
                AIField::BOOLEAN,
                null,
                false,
                "Is the contact is making a reference to an image",
                null,
                false
            )
        );

        if ($linearModel) {
            $array["transparent"] = new AIFieldObject(
                AIField::BOOLEAN,
                null,
                false,
                "Is the contact wants a transparent background",
                null,
                false
            );
        }
        return $array;
    }

    public static function run(
        IdealisticOfficePrompt $prompt
    ): void
    {
        $prompt->waitForTasks(array(IdealisticOfficePromptTask::TEAM_INFO));
        $prompt->runTask(function () use ($prompt) {
            try {
                $messageAmount = IdealisticOfficeLimit::DEFAULT_MESSAGE_USAGE;
                $outcome = $prompt->getTeam()->getCredit()->useIncludedMessage($prompt, $messageAmount, false);

                if (!$outcome->isPositiveOutcome()) {
                    return resolve($outcome);
                }
                $linearModel = AIHelper::isLinearImageModel(self::model);
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
                )->then(function ($result) use ($prompt, $object, $linearModel, $messageAmount) {
                    $verify = $prompt->readAIObject($object, $result);

                    if ($verify === null
                        || $verify?->size === null
                        || $verify?->quality === null
                        || $verify?->description === null) {
                        return resolve(null);
                    } else {
                        $prompt->notifyWithoutTrace(
                            "Your request to create a "
                            . ($verify->size ?? "square")
                            . " image with "
                            . ($verify->quality ?? ($linearModel ? "low" : "standard"))
                            . " quality is being processed. This may take a few moments."
                        );
                        $size = !isset($verify->size)
                            ? null
                            : trim(strtolower($verify->size));

                        if ($size === "vertical") {
                            $xResolution = 1024;
                            $zResolution = $linearModel ? 1536 : 1792;
                        } else if ($size === "horizontal") {
                            $xResolution = $linearModel ? 1536 : 1792;
                            $zResolution = 1024;
                        } else {
                            $xResolution = 1024;
                            $zResolution = 1024;
                        }
                        $description = $verify->description ?? null;
                        $description = empty($description)
                            ? array()
                            : array($description);

                        if ($verify->reference) {
                            $attachments = $prompt->getHistory()->getPartialAttachments(
                                null,
                                null,
                                false
                            );

                            if (!empty($attachments)) {
                                foreach ($attachments as $attachment) {
                                    $desc = $attachment->getAnalyzedDescription();

                                    if ($desc !== null) {
                                        $description[] = $desc;
                                    }
                                }
                            }
                        }

                        if (empty($description)) {
                            return resolve(new IdealisticOfficeOutcome(
                                false,
                                "Failed to complete the task 'image creation' due to missing description, please try again."
                            ));
                        }
                        $AI = new IdealisticOfficeAI(
                            $prompt->getUser(),
                            self::model
                        );
                        $context = $AI->getModel()?->getContext();
                        $arguments = array(
                            "n" => 1,
                            "prompt" => $context === null
                                ? implode(IdealisticOfficeVariable::DEFAULT_TEXT_SEPARATOR, $description)
                                : substr(
                                    implode(IdealisticOfficeVariable::DEFAULT_TEXT_SEPARATOR, $description),
                                    0,
                                    $context
                                ),
                            "size" => $xResolution . "x" . $zResolution
                        );
                        if ($linearModel
                            && isset($verify->transparent)
                            && $verify->transparent) {
                            $arguments["background"] = "transparent";
                        }
                        $quality = !isset($verify->quality)
                            ? null
                            : trim(strtolower($verify->quality));

                        if ($linearModel) {
                            $arguments["quality"] = $quality === "high"
                                ? "high"
                                : ($quality === "medium" ? "medium" : "low");
                        } else {
                            $arguments["quality"] = $quality === "hd"
                                ? "hd"
                                : "standard";
                        }
                        return $AI->getReactResult(
                            $arguments
                        )->then(async(function ($result)
                        use ($AI, $prompt, $verify, $xResolution, $zResolution, $description, $messageAmount) {
                            if (is_url($result)) {
                                $outcome = $prompt->getTeam()->getCredit()->useIncludedMessage($prompt, $messageAmount);

                                if (!$outcome->isPositiveOutcome()) {
                                    return resolve($outcome);
                                }
                                $contents = IdealisticOfficeProcessor::getLinkContents(
                                    $result
                                );

                                if ($contents !== false) {
                                    $timezone = $prompt->getUser()->getTimezone(false);
                                    $name = $verify->file_name ?? random_string(
                                        IdealisticOfficeLimit::RANDOM_STRING_LENGTH
                                    );
                                    $attachment = new IdealisticOfficeAttachment(
                                        null,
                                        $name . ".png",
                                        "image/png",
                                        $result,
                                        -1,
                                        $xResolution,
                                        $zResolution,
                                        null,
                                        base64_encode($contents),
                                        null,
                                        true,
                                        IdealisticOfficeReader::getCurrentDate($timezone),
                                        $timezone->getTimeZone(),
                                        $prompt->getUser()
                                    );
                                    $attachment->setUserPrompt(
                                        implode(
                                            IdealisticOfficeVariable::DEFAULT_TEXT_SEPARATOR,
                                            $description
                                        )
                                    );

                                    if ($attachment->isValid()) {
                                        $description = $AI->getModel()?->getRevisedPrompt($AI->getReply());

                                        if ($description === null) {
                                            $attachment->setAnalyzedDescription($attachment->getUserPrompt());
                                        } else {
                                            $attachment->setAnalyzedDescription($description);
                                        }
                                        $prompt->sendAttachment(
                                            $attachment,
                                            null,
                                            true
                                        );
                                    } else {
                                        return resolve(new IdealisticOfficeOutcome(
                                            false,
                                            "Failed to complete the task 'image creation' due to an error with the created attachment, please try again."
                                        ));
                                    }
                                } else {
                                    return resolve(new IdealisticOfficeOutcome(
                                        false,
                                        "Failed to complete the task 'image creation' due to an error while downloading the created image, please try again."
                                    ));
                                }
                            } else if (is_string($result)) {
                                $outcome = $prompt->getTeam()->getCredit()->useIncludedMessage($prompt, $messageAmount);

                                if (!$outcome->isPositiveOutcome()) {
                                    return resolve($outcome);
                                }
                                $timezone = $prompt->getUser()->getTimezone(false);
                                $name = $verify->file_name ?? random_string(
                                    IdealisticOfficeLimit::RANDOM_STRING_LENGTH
                                );
                                $attachment = new IdealisticOfficeAttachment(
                                    null,
                                    $name . ".png",
                                    "image/png",
                                    null,
                                    -1,
                                    $xResolution,
                                    $zResolution,
                                    null,
                                    $result,
                                    null,
                                    true,
                                    IdealisticOfficeReader::getCurrentDate($timezone),
                                    $timezone->getTimeZone(),
                                    $prompt->getUser()
                                );
                                $attachment->setUserPrompt(
                                    implode(
                                        IdealisticOfficeVariable::DEFAULT_TEXT_SEPARATOR,
                                        $description
                                    )
                                );

                                if ($attachment->isValid()) {
                                    $description = $AI->getModel()?->getRevisedPrompt($AI->getReply());

                                    if ($description === null) {
                                        $attachment->setAnalyzedDescription($attachment->getUserPrompt());
                                    } else {
                                        $attachment->setAnalyzedDescription($description);
                                    }
                                    $prompt->sendAttachment(
                                        $attachment,
                                        null,
                                        true
                                    );
                                } else {
                                    return resolve(new IdealisticOfficeOutcome(
                                        false,
                                        "Failed to complete the task 'image creation' due to an error with the created attachment, please try again."
                                    ));
                                }
                            }
                            return resolve(null);
                        }));
                    }
                });
            } catch (Throwable $e) {
                return reject($e);
            }
        });
    }

}
