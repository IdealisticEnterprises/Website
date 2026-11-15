<?php

use function React\Promise\reject;
use function React\Promise\resolve;

class IdealisticOfficePromptNewUsers implements PhpAsyncSerializable
{

    public static function description(IdealisticOfficePrompt $prompt): ?string
    {
        return "[SECONDARY FEATURE] Simply mention the emails of the contacts you wish to include your team and the system will add them if registered."
            . (!$prompt->getUser()->hasEmail() ? " (Warning: You need to have an email registered to your account add other users.)" : "");
    }

    public static function add(IdealisticOfficeUser $user, string $email): IdealisticOfficeOutcome
    {
        return $user->addNewUser(strtolower(trim($email)));
    }

    public static function run(
        IdealisticOfficePrompt $prompt
    ): void
    {
        $prompt->waitForTasks(array(
            IdealisticOfficePromptTask::INFORMATION_ANALYSIS_POSITIONS
        ));
        $prompt->runTask(function () use ($prompt) {
            try {
                if (!$prompt->getUser()->hasEmail()) {
                    return resolve(null);
                }
                $users = $prompt->getCache()->getUsers();
                $prompts = $prompt->getCache()->getSeparatedWords(IdealisticOfficeLimit::MAX_PROCESSED_TASKS);
                $added = false;

                foreach ($prompts as $individual) {
                    if (is_email($individual)) {
                        $individual = strtolower($individual);

                        foreach ($users as $user) {
                            if ($user->hasEmail()
                                && strtolower($user->getEmail()) === $individual) {
                                continue 2;
                            }
                        }
                        $outcome = self::add($prompt->getUser(), $individual);

                        if ($outcome->isPositiveOutcome()) {
                            $added = true;
                        } else {
                            IdealisticOfficeError::simpleStore(
                                $outcome->getRawOutcome()
                            );
                        }
                    }
                }

                if ($added) {
                    $prompt->getCache()->clearUsers();
                }
                return resolve(null);
            } catch (Throwable $e) {
                return reject($e);
            }
        },
            null
        );
    }

}
