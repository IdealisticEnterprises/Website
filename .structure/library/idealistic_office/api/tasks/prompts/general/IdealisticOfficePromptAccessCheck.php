<?php

use function React\Promise\reject;
use function React\Promise\resolve;

class IdealisticOfficePromptAccessCheck implements PhpAsyncSerializable
{

    public static function description(IdealisticOfficePrompt $prompt): ?string
    {
        $validPlatforms = IdealisticOfficeAccessPlatform::getValidPlatforms();
        return ("[MAIN FEATURE] Introducing " . IdealisticOfficeVariable::APPLICATION_NAME . ". Stop using platforms you don’t like. Stop forcing others to come to platforms they don’t like.")
            . "Accessible through: " . implode(IdealisticOfficeVariable::DEFAULT_TEXT_SEPARATOR, $validPlatforms)
            . ". To register/log-in, please write down only your email address, it must be the same with one you used to purchase your subscription if you are a subscriber.";
    }

    public static function run(
        IdealisticOfficePrompt $prompt
    ): void
    {
        $prompt->runTask(function () use ($prompt) {
            try {
                // Validate platform
                $platformID = $prompt->getPlatformId();

                if (!array_key_exists($platformID, IdealisticOfficeAccessPlatform::getValidPlatforms())) {
                    return resolve(
                        new IdealisticOfficeOutcome(
                            null,
                            "The platform of your request is generally or temporarily not supported."
                        )
                    );
                }

                // Validate prompt length
                if (mb_strlen($prompt->getPrompt()) > IdealisticOfficeLimit::K13_LENGTH) {
                    return resolve(
                        new IdealisticOfficeOutcome(
                            null,
                            "The information of your request is too long in length."
                        )
                    );
                }

                // Processing prompts
                if ($prompt->getUser()->getProcessingPrompts(
                        1,
                        $prompt->getHistory()->getId()
                    ) > 0) {
                    return resolve(
                        new IdealisticOfficeOutcome(
                            null,
                            "Please wait for your current prompt to finish processing before creating a new one."
                        )
                    );
                }

                if ($prompt->getUser()->hasEmail()) {
                    // Legal record check
                    if (!$prompt->getUser()->hasLegalRecord()) {
                        return resolve(
                            new IdealisticOfficeOutcome(
                                null,
                                "Before using " . IdealisticOfficeVariable::APPLICATION_NAME
                                . ", please review and accept our terms and policies: "
                                . IdealisticOfficeVariable::LEGAL_SIGN_INPUT_OUTPUT_URL
                                . "/?history=" . $prompt->getHistory()->getId()
                                . "&email=" . urlencode($prompt->getUser()->getEmail())
                                . "&account=" . $prompt->getUser()->getId()
                                . "&team=" . $prompt->getTeam()->getId()
                                . "&time=" . strtotime($prompt->getHistory()->getCreationDate())
                            )
                        );
                    }
                } else {
                    // Set email
                    $hasEmail = $prompt->getEmail() !== null;
                    $promptPrompt = $prompt->getEmail() ?? $prompt->getPrompt();
                    $setEmailFunction = function (string $email) use ($prompt): IdealisticOfficeOutcome {
                        $account = $prompt->getUser()->getAccount();
                        $reply = $account->setDetail("email_address", $email);
                        $account->getEmail()->setTemporaryEmailAddress(null);
                        return new IdealisticOfficeOutcome(
                            null,
                            $reply->getMessage()
                        );
                    };

                    if (is_email($promptPrompt)) {
                        $user = AbstractIdealisticOfficeTeam::findUserDynamic(
                            new Account(
                                Account::IDEALISTIC_OFFICE_APPLICATION_ID,
                                null,
                                $promptPrompt
                            ),
                            false
                        );
                        $found = $user instanceof IdealisticOfficeUser
                            && $user->exists();
                        $skipAuthentication = $hasEmail
                            || !$found
                            && IdealisticOfficeCredit::getIncludedMessagesFromEmail(
                                $promptPrompt,
                                IdealisticOfficeVariable::RECENT_PADDLE_ACCOUNT_SECONDS
                            ) > 0;

                        if ($skipAuthentication) {
                            return resolve($setEmailFunction($promptPrompt));
                        } else {
                            $typeMessage = "Please type the two-factor authentication code sent to the email you provided.";

                            if ($found) {
                                $reply = $user->getAccount()->getTwoFactorAuthentication()->initiate(
                                    null,
                                    true
                                );
                            } else {
                                $account = $prompt->getUser()->getAccount();
                                $account->getEmail()->setTemporaryEmailAddress($promptPrompt);
                                $reply = $account->getTwoFactorAuthentication()->initiate(
                                    null,
                                    true
                                );
                            }
                            return resolve(
                                new IdealisticOfficeOutcome(
                                    null,
                                    $reply->isPositiveOutcome()
                                        ? $typeMessage
                                        : $reply->getMessage()
                                )
                            );
                        }
                    } else if (strlen($promptPrompt) === TwoFactorAuthentication::CODE_LENGTH
                        && !str_contains($promptPrompt, " ")
                        && !str_contains($promptPrompt, "\n")
                        && !str_contains($promptPrompt, "\r")
                        && !str_contains($promptPrompt, "\t")) {
                        $account = new Account(Account::IDEALISTIC_OFFICE_APPLICATION_ID);
                        $reply = $account->getTwoFactorAuthentication()->verify(
                            null,
                            $promptPrompt,
                            false,
                            true
                        );

                        if ($reply->isPositiveOutcome()) {
                            $sameAccount = $account->getDetail("id", 0) === $prompt->getUser()->getId();

                            if ($sameAccount) {
                                $account = $prompt->getUser()->getAccount();
                                $email = $account->getEmail()->getTemporaryEmailAddress();
                            } else {
                                $email = $account->getDetail("email_address", null);
                            }

                            if ($email === null) {
                                return resolve(
                                    new IdealisticOfficeOutcome(
                                        null,
                                        "The two-factor authentication code you entered is valid, but we could not retrieve your email address. Please try again."
                                    )
                                );
                            }
                            if ($sameAccount) {
                                $account->getEmail()->setTemporaryEmailAddress(null);
                                $user = AbstractIdealisticOfficeTeam::findUserDynamic(
                                    new Account(
                                        Account::IDEALISTIC_OFFICE_APPLICATION_ID,
                                        null,
                                        $email
                                    ),
                                    false
                                );

                                if ($user instanceof IdealisticOfficeUser
                                    && $user->exists()) {
                                    return resolve(
                                        new IdealisticOfficeOutcome(
                                            null,
                                            "The two-factor authentication code you entered is valid, but the email address is already associated with an existing account. Please use a different email address."
                                        )
                                    );
                                }
                                return resolve($setEmailFunction($email));
                            } else {
                                return resolve(
                                    IdealisticOfficeTeamInitiator::changeSession(
                                        $prompt->getUser(),
                                        $account,
                                        $prompt->getPlatformUserID(),
                                        $prompt->getPlatformId()
                                    )
                                );
                            }
                        } else {
                            return resolve(
                                new IdealisticOfficeOutcome(
                                    null,
                                    "The two-factor authentication code you entered is invalid. Please check your email and try again."
                                )
                            );
                        }
                    }
                }

                // Admin check
                $admin = $prompt->getCache()->isAdminUser();

                if (!$admin) {
                    // Blocked access
                    $blockedAccess = $prompt->getUser()->getAdminBlockedAccess();

                    if ($blockedAccess !== null) {
                        $reason = $blockedAccess->getCreationReason();
                        return resolve(
                            new IdealisticOfficeOutcome(
                                null,
                                $reason === null
                                    ? "The team or contact access has been disabled by a " . IdealisticOfficeVariable::APPLICATION_NAME . " admin."
                                    : "The team or contact access has been disabled by a " . IdealisticOfficeVariable::APPLICATION_NAME . " admin with reason: " . $reason
                            )
                        );
                    }

                    // Credit check
                    if ($prompt->getCache()->needsCharging()) {
                        if ($prompt->getCache()->isSubscriber()) {
                            return resolve(
                                new IdealisticOfficeOutcome(
                                    null,
                                    "Not enough credits to create a prompt."
                                )
                            );
                        } else if (IdealisticOfficeCredit::ALLOW_PAYPAL
                            && is_email($prompt->getPrompt())) {
                            $outcome = $prompt->getUser()->getPaypalEmailManager()->addTeamEmail($prompt->getPrompt());

                            if ($outcome->isPositiveOutcome()) {
                                $outcome->setOutcome(null);
                                $packages = IdealisticOfficeCredit::getPackages();

                                if (!empty($packages)) {
                                    $message = $outcome->getRawMessage();
                                    $message .= "\n\nYou can purchase credits using the following links:";

                                    foreach ($packages as $exchange => $url) {
                                        $message .= "\n- " . $exchange . ": " . $url;
                                    }
                                    $outcome->setRawMessage($message);
                                }
                            }
                            return resolve($outcome);
                        } else if (IdealisticOfficeCredit::ALLOW_PAYPAL
                            && !empty($prompt->getTeam()->getPaypalEmails(1, false))) {
                            $packages = IdealisticOfficeCredit::getPackages();
                            $message = "Not enough credits to create a prompt."
                                . " You have already added one or more PayPal emails to your team."
                                . " To add more emails, please type your PayPal email so we can store and use it to track your future payments.";

                            if (!empty($packages)) {
                                $message .= "\n\nYou can purchase credits using the following links:";

                                foreach ($packages as $exchange => $url) {
                                    $message .= "\n- " . $exchange . ": " . $url;
                                }
                            }
                            return resolve(
                                new IdealisticOfficeOutcome(
                                    null,
                                    $message
                                )
                            );
                        } else {
                            $packages = IdealisticOfficeCredit::getPackages();

                            if (!empty($packages)
                                && IdealisticOfficeCredit::ALLOW_PAYPAL) {
                                return resolve(
                                    new IdealisticOfficeOutcome(
                                        null,
                                        "Your free trial has expired. Please subscribe via "
                                        . IdealisticOfficeVariable::COMPANY_WEBSITE_URL
                                        . " with your email to continue using " . IdealisticOfficeVariable::APPLICATION_NAME . "."
                                        . " You can alternatively purchase credits using the following links:\n\n"
                                        . implode("\n", array_map(
                                            function ($exchange, $url) {
                                                return "- " . $exchange . ": " . $url;
                                            },
                                            array_keys(IdealisticOfficeCredit::getPackages()),
                                            IdealisticOfficeCredit::getPackages()
                                        ))
                                    )
                                );
                            } else {
                                return resolve(
                                    new IdealisticOfficeOutcome(
                                        null,
                                        "Your free trial has expired. Please subscribe via "
                                        . IdealisticOfficeVariable::COMPANY_WEBSITE_URL
                                        . " with your email to continue using " . IdealisticOfficeVariable::APPLICATION_NAME . "."
                                    )
                                );
                            }
                        }
                    }

                    // Limited free access
                    if (!$prompt->getCache()->isSubscriber()
                        && IdealisticOfficeTeamInitiator::limitNewPrompts()) {
                        return resolve(
                            new IdealisticOfficeOutcome(
                                null,
                                "New prompt creation is currently limited due to high demand. Please sign in to get "
                                . IdealisticOfficeVariable::NEW_TEAM_DEFAULT_CREDITS . " more messages and all features in " . IdealisticOfficeVariable::APPLICATION_NAME . "."
                            )
                        );
                    }
                }

                // Use included message
                $outcome = $prompt->getTeam()->getCredit()->useIncludedMessage($prompt, IdealisticOfficeLimit::DEFAULT_MESSAGE_USAGE);

                if (!$outcome->isPositiveOutcome()) {
                    $outcome->setOutcome(null);
                    return resolve($outcome);
                }

                if (empty($prompt->getTeam()->getPortals(1, false))
                    && empty($prompt->getTeam()->getPortals(1, false, true))) {
                    IdealisticOfficePromptManagePortals::createPortal(
                        $prompt,
                        IdealisticOfficeVariable::CHAT_PORTAL_DEFAULT_NAME,
                        null,
                        null,
                        false,
                        false,
                        false,
                        false
                    );
                }
                $prompt->getUser()->updateNameFromSetting();
                return resolve(null);
            } catch (Throwable $e) {
                return reject($e);
            }
        },
            null
        );
    }

}
