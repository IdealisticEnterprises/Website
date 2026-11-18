<?php

namespace idealistic_office\api\handlers;

use account\api\objects\account\Account;
use idealistic_office\api\objects\helpers\IdealisticOfficeNotification;
use idealistic_office\api\objects\helpers\IdealisticOfficeOutcome;
use idealistic_office\api\objects\IdealisticOfficeTeam;
use idealistic_office\api\objects\IdealisticOfficeUser;
use PhpAsyncSerializable;

class IdealisticOfficeNotifications implements PhpAsyncSerializable
{

    /** @return IdealisticOfficeNotification[] */
    public static function retrieve(int|array $platformID): array
    {
        $query = get_sql_query(
            IdealisticOfficeTable::NOTIFICATIONS_TABLE,
            IdealisticOfficeQuery::NOTIFICATION_FULL,
            array(
                array("delivered_date", null)
            ),
            null,
            IdealisticOfficeLimit::MAX_PROCESSED_TASKS
        );

        if (!empty($query)) {
            $array = array();

            foreach ($query as $row) {
                $team = new IdealisticOfficeTeam($row->team_id ?? 0, false);

                if (!$team->hasEstablishedAccess()) {
                    $array[] = new IdealisticOfficeNotification(
                        null,
                        $row->created_by,
                        (int)$row->id,
                        $row->notification_message,
                        $row->creation_date,
                        $row->creation_time_zone,
                        $row->platform_id,
                        $row->platform_user_id,
                        $row->platform_chat_id,
                        $row->platform_chat_name,
                        $row->platform_message_id,
                        $row->notification_attachment_name,
                        $row->notification_attachment_content,
                        $row->notification_attachment_url,
                        $row->notification_attachment_format,
                        $row->is_base64 !== null,
                        $row->is_video !== null,
                        $row->is_image !== null,
                        $row->is_audio !== null,
                        $row->translate_message !== null,
                        $row->delete_on_delivery !== null
                    );
                } else {
                    $user = $team->findUser(
                        new Account(
                            Account::IDEALISTIC_OFFICE_APPLICATION_ID,
                            $row->account_id
                        )
                    );

                    if ($user instanceof IdealisticOfficeUser) {
                        $identity = $user->getLastIdentity();

                        if ($identity !== null
                            && (is_int($platformID)
                                ? $identity->getPlatformID() === $platformID
                                : in_array($identity->getPlatformID(), $platformID))) {
                            $array[] = new IdealisticOfficeNotification(
                                $user,
                                $row->created_by,
                                (int)$row->id,
                                $row->notification_message,
                                $row->creation_date,
                                $row->creation_time_zone,
                                $row->platform_id,
                                $row->platform_user_id,
                                $row->platform_chat_id,
                                $row->platform_chat_name,
                                $row->platform_message_id,
                                $row->notification_attachment_name,
                                $row->notification_attachment_content,
                                $row->notification_attachment_url,
                                $row->notification_attachment_format,
                                $row->is_base64 !== null,
                                $row->is_video !== null,
                                $row->is_image !== null,
                                $row->is_audio !== null,
                                $row->translate_message !== null,
                                $row->delete_on_delivery !== null
                            );
                        }
                    }
                }
            }
            return $array;
        } else {
            return array();
        }
    }

    public static function sendEmail(
        string $credential,
        string $content
    ): IdealisticOfficeOutcome
    {
        try {
            $execution = IdealisticOfficeEmailer::send(
                "0-localPlatformNotification",
                $credential,
                array(
                    "notification" => $content
                )
            );

            if ($execution === 1) {
                return new IdealisticOfficeOutcome(
                    true,
                    "Email sent successfully."
                );
            } else {
                return new IdealisticOfficeOutcome(
                    false,
                    "Email sending failed (Error Information: " . $execution . ")"
                );
            }
        } catch (Throwable $e) {
            IdealisticOfficeError::storeThrowable(
                null,
                null,
                $e
            );
            return new IdealisticOfficeOutcome(
                false,
                "Email sending failed (Exception: " . $e->getMessage() . ")"
            );
        }
    }

}