<?php

namespace idealistic_office\api\tasks\prompts\portal;

use AIObjectConstructor;
use idealistic_office\api\handlers\IdealisticOfficeReader;
use idealistic_office\api\IdealisticOfficeVariable;
use idealistic_office\api\objects\helpers\IdealisticOfficeAI;
use idealistic_office\api\objects\helpers\IdealisticOfficeOutcome;
use idealistic_office\api\objects\helpers\IdealisticOfficeTimezone;
use idealistic_office\api\objects\IdealisticOfficePortal;
use idealistic_office\api\objects\IdealisticOfficePrompt;
use idealistic_office\api\objects\IdealisticOfficeUser;
use idealistic_office\api\tasks\prompts\folder\IdealisticOfficePromptManageFolders;
use idealistic_office\api\tasks\prompts\folder\IdealisticOfficePromptShareInformation;
use IdealisticOfficeLimit;
use IdealisticOfficePromptFields;
use IdealisticOfficePromptTask;
use PhpAsyncSerializable;
use function React\Promise\reject;
use function React\Promise\resolve;

class IdealisticOfficePromptManagePortals implements PhpAsyncSerializable
{

    public static function description(IdealisticOfficePrompt $prompt): ?string
    {
        return "[MAIN FEATURE] External communication for specified purposes (Ex: assisting customers). "
            . "Deploy secure chat-rooms via unique links and use folders to feed them context. "
            . "Portal context is taken directly from connected/attached folders which are created and connected automatically under the same name. "
            . "For your own website, you can embed a chat widget by using the script: " . IdealisticOfficeVariable::PORTAL_EXTERNAL_SCRIPT . ". "
            . "Portals that allow crawling will trust the first domain that accesses them, but you can also set a default domain for each portal to avoid any potential issues with crawling. "
            . "You can transfer portals between teams by initiating a transfer and having the target contact accept it. "
            . "Contact us optionally at " . IdealisticOfficeVariable::SUPPORT_EMAIL . " to help you with the integration and maintenance of your portals.";
    }

    public static function fields(IdealisticOfficePrompt $prompt): array
    {
        $array = array(
            "0_new_portal_name" => IdealisticOfficePromptFields::nullableString(
                "The name of the portal to be created"
            ),
            "0_new_portal_optional_password" => IdealisticOfficePromptFields::nullableString(
                "The optional password for the portal to be created"
            ),
            "0_new_portal_downloads_allowed" => IdealisticOfficePromptFields::nullableBoolean(
                "Whether the new portal should allow downloads or not"
            ),
            "0_new_portal_voice_replies_enabled" => IdealisticOfficePromptFields::nullableBoolean(
                "Whether the new portal should have voice replies enabled or not"
            ),
            "0_new_portal_18_plus" => IdealisticOfficePromptFields::nullableBoolean(
                "Whether the new portal should be marked as 18+ or not (this will add an age restriction gate before accessing the portal)"
            ),
            "0_new_portal_default_message" => IdealisticOfficePromptFields::nullableString(
                "The default placeholder message sent by the user to initiate the portal (if not provided, the portal will use a generic default message)"
            ),
            "0_new_portal_require_login" => IdealisticOfficePromptFields::nullableBoolean(
                "Whether the new portal should require the user to login or not"
            ),


            "1_portal_to_rename" => IdealisticOfficePromptFields::nullableString(
                "The ID/name of the portal to rename"
            ),
            "1_portal_new_name" => IdealisticOfficePromptFields::nullableString(
                "The new name for the portal"
            ),


            "2_portals_to_regenerate_url" => IdealisticOfficePromptFields::nullableStringArray(
                "The IDs/names of the portals to regenerate their URL"
            ),


            "3_portal_to_change_password" => IdealisticOfficePromptFields::nullableString(
                "The ID/name of the portal to change the password for"
            ),
            "3_portal_new_optional_password" => IdealisticOfficePromptFields::nullableString(
                "The new optional password for the portal, null for now password"
            ),


            "4_portal_to_remove" => IdealisticOfficePromptFields::nullableString(
                "The ID/name of the portal to remove"
            ),
            "4_portal_removal_optional_justification" => IdealisticOfficePromptFields::nullableString(
                "An optional justification for the portal removal"
            ),


            "5_portals_to_restore" => IdealisticOfficePromptFields::nullableStringArray(
                "The IDs/names of the portals to restore"
            ),


            "6_portal_id_to_generate_qr_code_for" => IdealisticOfficePromptFields::nullableString(
                "The ID/name of the portal to generate a QR code for its URL"
            ),


            "7_portal_id_to_update_download_allowance" => IdealisticOfficePromptFields::nullableString(
                "The ID/name of the portal to update the download allowance for"
            ),
            "7_portal_new_download_allowance" => IdealisticOfficePromptFields::nullableBoolean(
                "Whether the portal should allow downloads or not"
            ),


            "9_portal_id_to_update_default_message" => IdealisticOfficePromptFields::nullableString(
                "The ID/name of the portal to update the default message for"
            ),
            "9_portal_new_default_message" => IdealisticOfficePromptFields::nullableString(
                "The new default placeholder message for the portal (leave empty to reset to generic default message)"
            ),

            "10_portal_id_to_update_age_restriction" => IdealisticOfficePromptFields::nullableString(
                "The ID/name of the portal to update the age restriction setting for"
            ),
            "10_portal_18_plus" => IdealisticOfficePromptFields::nullableBoolean(
                "Whether the portal should be marked as 18+ or not (this will add an age restriction gate before accessing the portal)"
            ),

            "11_portal_id_to_set_default_url" => IdealisticOfficePromptFields::nullableString(
                "The ID/name of the portal to set the default domain/URL for"
            ),
            "11_portal_default_url" => IdealisticOfficePromptFields::nullableString(
                "The default domain/URL to set for the portal (false = no crawling allowed, null = will trust first domain, string = will trust the specified domain)"
            ),

            "13_portal_id_to_cancel_transfer" => IdealisticOfficePromptFields::nullableString(
                "The ID/name of the portal to cancel a pending transfer for"
            ),

            "15_portal_id_to_set_login_requirement" => IdealisticOfficePromptFields::nullableString(
                "The ID/name of the portal to update the login requirement for"
            ),
            "15_portal_require_login" => IdealisticOfficePromptFields::nullableBoolean(
                "Whether the portal should require the user to login or not"
            )
        );

        if (IdealisticOfficePortal::VOICE_REPLIES) {
            $array["8_portal_id_to_update_voice_replies"] = IdealisticOfficePromptFields::nullableString(
                "The ID/name of the portal to update the voice replies setting for"
            );
            $array["8_portal_voice_replies_enabled"] = IdealisticOfficePromptFields::nullableBoolean(
                "Whether the portal should have voice replies enabled or not"
            );
        }

        if (sizeof($prompt->getCache()->getUsers()) > 1) {
            $array["12_portal_id_to_transfer"] = IdealisticOfficePromptFields::nullableString(
                "The ID/name of the portal to initiate a transfer for"
            );
            $array["12_portal_transfer_target_contact"] = IdealisticOfficePromptFields::nullableString(
                "The contact reference (ID or name) to transfer the portal to"
            );

            $array["14_accept_transfers_from_contact"] = IdealisticOfficePromptFields::nullableString(
                "The contact reference (ID or name) to accept pending portal transfers from"
            );
        }

        return $array;
    }

    private static function getNewPortal(
        IdealisticOfficeUser     $user,
        string                   $name,
        ?string                  $optionalPassword,
        string                   $date,
        IdealisticOfficeTimezone $timezone,
        ?string                  $defaultMessage,
        bool                     $downloadsAllowed,
        bool                     $voiceReplies,
        bool                     $is18Plus,
        bool                     $requireLogin
    ): IdealisticOfficePortal
    {
        return new IdealisticOfficePortal(
            $user->getTeam(),
            0,
            $user->getAccount(),
            $name,
            $optionalPassword !== null
                ? trim($optionalPassword)
                : null,
            "",
            false,
            $downloadsAllowed,
            $voiceReplies,
            IdealisticOfficeLimit::CHAT_PORTAL_SKIP_BOT_VERIFICATION,
            $is18Plus,
            $requireLogin,
            $date,
            $timezone->getTimeZone(),
            $defaultMessage,
            null,
            false,
        );
    }

    public static function create(
        IdealisticOfficeUser $user,
        string               $name,
        ?string              $optionalPassword,
        ?string              $defaultMessage,
        bool                 $downloadsAllowed,
        bool                 $voiceReplies,
        bool                 $is18Plus,
        bool                 $requireLogin
    ): array
    {
        $outcomes = array();
        $timezone = $user->getTimezone(false);
        $date = IdealisticOfficeReader::getCurrentDate($timezone);
        $name = trim($name);
        $portal = self::getNewPortal(
            $user,
            $name,
            empty($optionalPassword)
                ? null
                : trim($optionalPassword),
            $date,
            $timezone,
            empty($defaultMessage)
                ? null
                : trim($defaultMessage),
            $downloadsAllowed,
            $voiceReplies,
            $is18Plus,
            $requireLogin
        );
        $outcome = $portal->create();

        if ($outcome->isPositiveOutcome()) {
            $folder = IdealisticOfficePromptManageFolders::newFolder(
                $user->getTeam(),
                $user,
                $name,
                $portal->getId(),
                $date,
                $timezone
            );
            $secondOutcome = $folder->open($user);

            if ($secondOutcome->isPositiveOutcome()) {
                $thirdOutcome = $folder->attachPortal($user, $portal);

                if ($thirdOutcome->isPositiveOutcome()) {
                    $outcomes[] = $outcome;
                    $outcomes[] = $secondOutcome;
                    $outcomes[] = $thirdOutcome;
                } else {
                    $outcomes[] = $portal->remove($user, "Removing portal since the associated folder failed to be attached with reason: " . $thirdOutcome->getRawMessage());
                    $outcomes[] = $folder->remove($user, "Removing folder since the associated portal failed to be attached");
                }
            } else {
                $outcomes[] = $portal->remove($user, "Removing portal since the associated folder failed to be created with reason: " . $secondOutcome->getRawMessage());
            }
        } else {
            $outcomes[] = $outcome;
        }
        return $outcomes;
    }

    public static function createPortal(
        IdealisticOfficePrompt $prompt,
        string                 $name,
        ?string                $optionalPassword,
        ?string                $defaultMessage,
        bool                   $downloadsAllowed,
        bool                   $voiceReplies,
        bool                   $is18Plus,
        bool                   $requireLogin,
        array                  &$outcomes = [],
    ): void
    {
        $created = self::create(
            $prompt->getUser(),
            $name,
            $optionalPassword,
            $defaultMessage,
            $downloadsAllowed,
            $voiceReplies,
            $is18Plus,
            $requireLogin
        );

        if (sizeof($created) === 3) {
            $prompt->getCache()->clearFolders();
        }
        foreach ($created as $outcome) {
            $outcomes[] = $outcome;
        }
    }

    public static function rename(IdealisticOfficeUser $user, IdealisticOfficePortal $portal, string $name): array
    {
        $name = trim($name);
        $outcome = $portal->rename($user, $name);
        $outcomes = array($outcome);

        if ($outcome->isPositiveOutcome()) {
            $folder = $user->getTeam()->getFolder(
                $portal->getId(),
                true,
                false
            );

            if ($folder !== null) {
                $outcomes[] = $folder->rename($user, $name);
            }
        }
        return $outcomes;
    }

    public static function regenerateUrl(IdealisticOfficeUser $user, IdealisticOfficePortal $portal): IdealisticOfficeOutcome
    {
        return $portal->regenerateURL($user);
    }

    public static function changePassword(IdealisticOfficeUser $user, IdealisticOfficePortal $portal, ?string $password): IdealisticOfficeOutcome
    {
        return $portal->changePassword($user, !empty($password) ? trim($password) : null);
    }

    public static function remove(IdealisticOfficeUser $user, IdealisticOfficePortal $portal, ?string $justification = null): array
    {
        $outcome = $portal->remove($user, !empty($justification) ? trim($justification) : null);
        $outcomes = array($outcome);

        if ($outcome->isPositiveOutcome()) {
            $folder = $portal->getParentFolder();

            if ($folder !== null) {
                $outcomes[] = $folder->remove(
                    $user,
                    "Removing folder since the associated portal was also removed"
                );
            }
        }
        return $outcomes;
    }

    public static function restore(IdealisticOfficeUser $user, IdealisticOfficePortal $portal): array
    {
        $outcome = $portal->restore($user);
        $outcomes = array($outcome);

        if ($outcome->isPositiveOutcome()) {
            $folder = $user->getTeam()->getFolder(
                $portal->getId(),
                true,
                false,
                true
            );

            if ($folder !== null) {
                $outcomes[] = $folder->restore($user);
            }
        }
        return $outcomes;
    }

    public static function setDownloadsAllowed(IdealisticOfficeUser $user, IdealisticOfficePortal $portal, bool $allowed): IdealisticOfficeOutcome
    {
        return $portal->setDownloadsAllowed($user, $allowed);
    }

    public static function setVoiceReplies(IdealisticOfficeUser $user, IdealisticOfficePortal $portal, bool $enabled): IdealisticOfficeOutcome
    {
        return $portal->setVoiceRepliesAllowed($user, $enabled);
    }

    public static function setDefaultMessage(IdealisticOfficeUser $user, IdealisticOfficePortal $portal, ?string $message): IdealisticOfficeOutcome
    {
        return $portal->setDefaultMessage($user, !empty($message) ? trim($message) : null);
    }

    public static function set18Plus(IdealisticOfficeUser $user, IdealisticOfficePortal $portal, bool $is18Plus): IdealisticOfficeOutcome
    {
        return $portal->set18Plus($user, $is18Plus);
    }

    public static function setDefaultUrl(IdealisticOfficeUser $user, IdealisticOfficePortal $portal, string|false|null $url): IdealisticOfficeOutcome
    {
        return $portal->setDefaultUrl($user, is_string($url) && $url !== "" ? trim($url) : ($url === false ? false : null));
    }

    public static function setLoginRequired(IdealisticOfficeUser $user, IdealisticOfficePortal $portal, bool $required): IdealisticOfficeOutcome
    {
        return $portal->setRequireLogin($user, $required);
    }

    public static function run(
        IdealisticOfficePrompt $prompt
    ): void
    {
        $prompt->waitForTasks(array(
            IdealisticOfficePromptTask::SHARE_INFORMATION
        ));
        $prompt->runTask(function () use ($prompt) {
            try {
                if (IdealisticOfficePromptShareInformation::isSharing($prompt)) {
                    return resolve(null);
                }
                $constructor = new AIObjectConstructor(
                    $prompt->expandFields(self::fields($prompt)),
                    $prompt->expandInstructions()
                );
                $arguments = array(
                    "messages" => array(
                        array(
                            "role" => "system",
                            "content" => $constructor->build()
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
                )->then(function ($result) use ($prompt, $constructor) {
                    $verify = $prompt->readAIObject($constructor, $result);

                    if ($verify === null) {
                        return resolve(null);
                    } else {
                        $outcomes = array();
                        $qrCode = null;

                        if (!empty($verify->{"0_new_portal_name"})) {
                            self::createPortal(
                                $prompt,
                                $verify->{"0_new_portal_name"},
                                $verify->{"0_new_portal_optional_password"} ?? null,
                                $verify->{"0_new_portal_default_message"} ?? null,
                                $verify->{"0_new_portal_downloads_allowed"} ?? false,
                                $verify->{"0_new_portal_voice_replies_enabled"} ?? false,
                                $verify->{"0_new_portal_18_plus"} ?? false,
                                $verify->{"0_new_portal_require_login"} ?? false,
                                $outcomes
                            );
                        }
                        if (!empty($verify->{"1_portal_to_rename"})
                            && !empty($verify->{"1_portal_new_name"})) {
                            $portal = $prompt->getCache()->getPortal(
                                trim($verify->{"1_portal_to_rename"})
                            );

                            if ($portal !== null) {
                                foreach (self::rename($prompt->getUser(), $portal, $verify->{"1_portal_new_name"}) as $outcome) {
                                    $outcomes[] = $outcome;
                                }
                            } else {
                                $outcomes[] = new IdealisticOfficeOutcome(
                                    false,
                                    "Portal to rename with reference '" . trim($verify->{"1_portal_to_rename"}) . "' not found"
                                );
                            }
                        }
                        if (!empty($verify->{"2_portals_to_regenerate_url"})) {
                            foreach ($verify->{"2_portals_to_regenerate_url"} as $portalReference) {
                                $portal = $prompt->getCache()->getPortal(
                                    trim($portalReference)
                                );

                                if ($portal !== null) {
                                    $outcomes[] = self::regenerateUrl($prompt->getUser(), $portal);
                                } else {
                                    $outcomes[] = new IdealisticOfficeOutcome(
                                        false,
                                        "Portal to regenerate URL with reference '" . trim($portalReference) . "' not found"
                                    );
                                }
                            }
                        }
                        if (!empty($verify->{"3_portal_to_change_password"})) {
                            $portal = $prompt->getCache()->getPortal(
                                trim($verify->{"3_portal_to_change_password"})
                            );

                            if ($portal !== null) {
                                $outcomes[] = self::changePassword(
                                    $prompt->getUser(),
                                    $portal,
                                    $verify->{"3_portal_new_optional_password"} ?? null
                                );
                            } else {
                                $outcomes[] = new IdealisticOfficeOutcome(
                                    false,
                                    "Portal to change password with reference '" . trim($verify->{"3_portal_to_change_password"}) . "' not found"
                                );
                            }
                        }
                        if (!empty($verify->{"4_portal_to_remove"})) {
                            $portal = $prompt->getCache()->getPortal(
                                trim($verify->{"4_portal_to_remove"})
                            );

                            if ($portal !== null) {
                                foreach (self::remove(
                                    $prompt->getUser(),
                                    $portal,
                                    $verify->{"4_portal_removal_optional_justification"} ?? null
                                ) as $outcome) {
                                    $outcomes[] = $outcome;
                                }
                            } else {
                                $outcomes[] = new IdealisticOfficeOutcome(
                                    false,
                                    "Portal to remove with reference '" . trim($verify->{"4_portal_to_remove"}) . "' not found"
                                );
                            }
                        }
                        if (!empty($verify->{"5_portals_to_restore"})) {
                            foreach ($verify->{"5_portals_to_restore"} as $portalReference) {
                                $portal = $prompt->getCache()->getPortal(
                                    trim($portalReference),
                                    true
                                );

                                if ($portal !== null) {
                                    foreach (self::restore($prompt->getUser(), $portal) as $outcome) {
                                        $outcomes[] = $outcome;
                                    }
                                } else {
                                    $outcomes[] = new IdealisticOfficeOutcome(
                                        false,
                                        "Portal to restore with reference '" . trim($portalReference) . "' not found"
                                    );
                                }
                            }
                        }
                        if (!empty($verify->{"6_portal_id_to_generate_qr_code_for"})) {
                            $portal = $prompt->getCache()->getPortal(
                                trim($verify->{"6_portal_id_to_generate_qr_code_for"})
                            );

                            if ($portal !== null) {
                                $qrCode = $portal->createQRCodeAttachment($prompt->getUser(), false);

                                if (!empty($qrCode)
                                    && !$qrCode->isValid()) {
                                    $outcomes[] = new IdealisticOfficeOutcome(
                                        false,
                                        "Failed to generate QR code for portal '" . trim($verify->{"6_portal_id_to_generate_qr_code_for"}) . "'"
                                    );
                                }
                            } else {
                                $outcomes[] = new IdealisticOfficeOutcome(
                                    false,
                                    "Portal to generate QR code for with reference '" . trim($verify->{"6_portal_id_to_generate_qr_code_for"}) . "' not found"
                                );
                            }
                        }
                        if (!empty($verify->{"7_portal_id_to_update_download_allowance"})
                            && !empty($verify->{"7_portal_new_download_allowance"})) {
                            $portal = $prompt->getCache()->getPortal(
                                trim($verify->{"7_portal_id_to_update_download_allowance"})
                            );

                            if ($portal !== null) {
                                $outcomes[] = self::setDownloadsAllowed(
                                    $prompt->getUser(),
                                    $portal,
                                    $verify->{"7_portal_new_download_allowance"}
                                );
                            } else {
                                $outcomes[] = new IdealisticOfficeOutcome(
                                    false,
                                    "Portal to update download allowance for with reference '" . trim($verify->{"7_portal_id_to_update_download_allowance"}) . "' not found"
                                );
                            }
                        }
                        if (IdealisticOfficePortal::VOICE_REPLIES
                            && !empty($verify->{"8_portal_id_to_update_voice_replies"})
                            && !empty($verify->{"8_portal_voice_replies_enabled"})) {
                            $portal = $prompt->getCache()->getPortal(
                                trim($verify->{"8_portal_id_to_update_voice_replies"})
                            );

                            if ($portal !== null) {
                                $outcomes[] = self::setVoiceReplies(
                                    $prompt->getUser(),
                                    $portal,
                                    $verify->{"8_portal_voice_replies_enabled"}
                                );
                            } else {
                                $outcomes[] = new IdealisticOfficeOutcome(
                                    false,
                                    "Portal to update voice replies setting for with reference '" . trim($verify->{"8_portal_id_to_update_voice_replies"}) . "' not found"
                                );
                            }
                        }
                        if (!empty($verify->{"9_portal_id_to_update_default_message"})) {
                            $portal = $prompt->getCache()->getPortal(
                                trim($verify->{"9_portal_id_to_update_default_message"})
                            );

                            if ($portal !== null) {
                                $outcomes[] = self::setDefaultMessage(
                                    $prompt->getUser(),
                                    $portal,
                                    $verify->{"9_portal_new_default_message"} ?? null
                                );
                            } else {
                                $outcomes[] = new IdealisticOfficeOutcome(
                                    false,
                                    "Portal to update default message for with reference '" . trim($verify->{"9_portal_id_to_update_default_message"}) . "' not found"
                                );
                            }
                        }
                        if (!empty($verify->{"10_portal_id_to_update_age_restriction"})
                            && !empty($verify->{"10_portal_18_plus"})) {
                            $portal = $prompt->getCache()->getPortal(
                                trim($verify->{"10_portal_id_to_update_age_restriction"})
                            );

                            if ($portal !== null) {
                                $outcomes[] = self::set18Plus(
                                    $prompt->getUser(),
                                    $portal,
                                    $verify->{"10_portal_18_plus"}
                                );
                            } else {
                                $outcomes[] = new IdealisticOfficeOutcome(
                                    false,
                                    "Portal to update age restriction setting for with reference '" . trim($verify->{"10_portal_id_to_update_age_restriction"}) . "' not found"
                                );
                            }
                        }
                        if (!empty($verify->{"11_portal_id_to_set_default_url"})) {
                            $portal = $prompt->getCache()->getPortal(
                                trim($verify->{"11_portal_id_to_set_default_url"})
                            );

                            if ($portal !== null) {
                                $outcomes[] = self::setDefaultUrl(
                                    $prompt->getUser(),
                                    $portal,
                                    !empty($verify->{"11_portal_default_url"})
                                        ? $verify->{"11_portal_default_url"}
                                        : null
                                );
                            } else {
                                $outcomes[] = new IdealisticOfficeOutcome(
                                    false,
                                    "Portal to set default domain for with reference '" . trim($verify->{"11_portal_id_to_set_default_url"}) . "' not found"
                                );
                            }
                        }

                        // New Transfer Management Execution Blocks

                        if (!empty($verify->{"12_portal_id_to_transfer"})
                            && !empty($verify->{"12_portal_transfer_target_contact"})) {
                            $portal = $prompt->getCache()->getPortal(
                                trim($verify->{"12_portal_id_to_transfer"})
                            );

                            if ($portal !== null) {
                                $targetUser = $prompt->getCache()->getUser(
                                    trim($verify->{"12_portal_transfer_target_contact"})
                                );

                                if ($targetUser !== null) {
                                    $outcome = $portal->transfer(
                                        $targetUser
                                    );
                                    $outcomes[] = $outcome;
                                } else {
                                    $outcomes[] = new IdealisticOfficeOutcome(
                                        false,
                                        "Target user for portal transfer with reference '" . trim($verify->{"12_portal_transfer_target_contact"}) . "' not found"
                                    );
                                }
                            } else {
                                $outcomes[] = new IdealisticOfficeOutcome(
                                    false,
                                    "Portal to transfer with reference '" . trim($verify->{"12_portal_id_to_transfer"}) . "' not found"
                                );
                            }
                        }

                        if (!empty($verify->{"13_portal_id_to_cancel_transfer"})) {
                            $portal = $prompt->getCache()->getPortal(
                                trim($verify->{"13_portal_id_to_cancel_transfer"})
                            );

                            if ($portal !== null) {
                                $outcome = $portal->cancelTransfer();
                                $outcomes[] = $outcome;
                            } else {
                                $outcomes[] = new IdealisticOfficeOutcome(
                                    false,
                                    "Portal to cancel transfer for with reference '" . trim($verify->{"13_portal_id_to_cancel_transfer"}) . "' not found"
                                );
                            }
                        }

                        if (!empty($verify->{"14_accept_transfers_from_contact"})) {
                            $requester = $prompt->getCache()->getUser(
                                trim($verify->{"14_accept_transfers_from_contact"})
                            );

                            if ($requester !== null) {
                                $outcome = IdealisticOfficePortal::acceptTransfer(
                                    $prompt->getUser(),
                                    $requester
                                );
                                $outcomes[] = $outcome;
                            } else {
                                $outcomes[] = new IdealisticOfficeOutcome(
                                    false,
                                    "User to accept transfers from with reference '" . trim($verify->{"14_accept_transfers_from_contact"}) . "' not found"
                                );
                            }
                        }

                        if (!empty($verify->{"15_portal_id_to_set_login_requirement"})
                            && !empty($verify->{"15_portal_require_login"})) {
                            $portal = $prompt->getCache()->getPortal(
                                trim($verify->{"15_portal_id_to_set_login_requirement"})
                            );

                            if ($portal !== null) {
                                $outcomes[] = self::setLoginRequired(
                                    $prompt->getUser(),
                                    $portal,
                                    $verify->{"15_portal_require_login"}
                                );
                            } else {
                                $outcomes[] = new IdealisticOfficeOutcome(
                                    false,
                                    "Portal to update login requirement for with reference '" . trim($verify->{"15_portal_id_to_set_login_requirement"}) . "' not found"
                                );
                            }
                        }

                        return $qrCode === null
                            ? resolve($outcomes)
                            : $prompt->sendAttachment($qrCode)->then(
                                function () use ($outcomes) {
                                    return resolve($outcomes);
                                },
                                function () use ($outcomes) {
                                    return resolve($outcomes);
                                }
                            );
                    }
                });
            } catch (Throwable $e) {
                return reject($e);
            }
        });
    }

}