<?php
require '/var/www/.structure/library/base/form.php';
$id = get_form_get("id");

if (!empty($id)) {
    require '/var/www/.structure/library/idealistic_office/init.php';

    try {
        if (strlen($id) === IdealisticOfficeLimit::URL_RANDOM_ID_LENGTH) {
            $query = get_sql_query(
                IdealisticOfficeTable::HISTORY_ATTACHMENT_ACCESS_TABLE,
                array(
                    "attachment_id",
                    "user_uploaded",
                    "expiration_date",
                    "expiration_time_zone"
                ),
                array(
                    array("random_id", $id)
                ),
                null,
                1
            );

            if (!empty($query)) {
                $row = $query[0];

                if ($row->expiration_date === null
                    || IdealisticOfficeReader::getCurrentDate($row->expiration_time_zone) <= $row->expiration_date) {
                    $userUploaded = $row->user_uploaded !== null;
                    $query = get_sql_query(
                        $userUploaded
                            ? IdealisticOfficeTable::HISTORY_USER_ATTACHMENTS_TABLE
                            : IdealisticOfficeTable::HISTORY_CREATED_ATTACHMENTS_TABLE,
                        $userUploaded
                            ? IdealisticOfficeQuery::ATTACHMENT
                            : IdealisticOfficeQuery::CREATED_ATTACHMENT,
                        array(
                            array("id", $row->attachment_id)
                        ),
                        null,
                        1
                    );

                    if (!empty($query)) {
                        $row = $query[0];
                        $attachment = new IdealisticOfficeAttachment(
                            (int)$row->id,
                            $row->attachment_name,
                            $row->attachment_format,
                            null,
                            $row->attachment_bytes ?? 0,
                            $row->attachment_x,
                            $row->attachment_y,
                            $row->analyzed_description,
                            $row->attachment_data,
                            $row->attachment_data_hash,
                            $row->is_base64 !== null,
                            $row->creation_date,
                            $row->creation_time_zone,
                            $row->created_by
                        );
                        $attachment->setAnalyzedDescriptionPositions($row->analyzed_description_positions);

                        if (!$userUploaded) {
                            $attachment->setUserPrompt($row->user_prompt);
                        }
                        $decoded = $attachment->getDecodedData();

                        if (!empty($decoded)) {
                            AbstractIdealisticOfficeAttachment::sendFileHeaders(
                                $attachment->getName(),
                                $attachment->getFormat(true),
                                strlen($decoded),
                                false
                            );
                            echo $decoded;
                            exit;
                        }
                    } else {
                        echo "This attachment may have expired or does not exist.";
                    }
                } else {
                    echo "This attachment may have expired or does not exist.";
                }
            } else {
                echo "This attachment may have expired or does not exist.";
            }
        }
    } catch (Throwable $e) {
        echo "An error occurred while processing this attachment.";
        IdealisticOfficeError::simpleStore($e);
    }
}
http_response_code(404);
