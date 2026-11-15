<?php

use function React\Promise\reject;
use function React\Promise\resolve;

class IdealisticOfficePromptGeneralVoiceReply implements PhpAsyncSerializable
{

    public static function description(IdealisticOfficePrompt $prompt): ?string
    {
        return "[SECONDARY FEATURE] Receive optional voice replies from the AI if you prefer hearing over reading text.";
    }

    public static function run(
        IdealisticOfficePrompt $prompt
    ): void
    {
        $prompt->waitForTasks($prompt->getCache()->getEnabledTasks());
        $prompt->runTask(function () use ($prompt) {
            try {
                if (!in_array($prompt->getPlatformId(), IdealisticOfficeLimit::SUPPORTS_VOICE_MESSAGES)
                    || !$prompt->getUser()->isSettingEnabled(IdealisticOfficeSettings::SEND_VOICE_REPLY)) {
                    return resolve(null);
                }
                $replies = $prompt->getHistory()->getReplies();

                if (empty($replies)) {
                    return resolve(null);
                }
                foreach ($replies as $key => $reply) {
                    if (empty($reply->getAnswer())) {
                        unset($replies[$key]);
                        continue;
                    }
                    $replies[$key] = replace_url_by_prefix(
                        $reply->getAnswer(),
                        IdealisticOfficeVariable::INPUT_OUTPUT_URL,
                        "[url]"
                    );
                }

                if (empty($replies)) {
                    return resolve(null);
                }
                $replies = implode(IdealisticOfficeVariable::DEFAULT_TEXT_SEPARATOR, $replies);
                return IdealisticOfficeProcessor::transcribeText(
                    $prompt,
                    $prompt->getTeam(),
                    $replies
                )->then(function ($audio) use ($prompt, $replies) {
                    if (is_string($audio)) {
                        $timezone = $prompt->getUser()->getTimezone(false);
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
                            $prompt->getUser()
                        );
                        $attachment->setUserPrompt("");

                        if ($attachment->isValid()) {
                            return $prompt->sendAttachment(
                                $attachment
                            );
                        } else {
                            return new IdealisticOfficeOutcome(
                                false,
                                "Failed to create attachment for voice reply."
                            );
                        }
                    } else {
                        return resolve(new IdealisticOfficeOutcome(
                            false,
                            "The AI model failed to process the contact prompt's audio reply."
                        ));
                    }
                });
            } catch (Throwable $e) {
                return reject($e);
            }
        },
            null
        );
    }

}
