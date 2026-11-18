<?php

namespace idealistic_office\api\objects\helpers\attachment;

use account\api\objects\account\Account;
use idealistic_office\api\objects\IdealisticOfficeUser;
use IdealisticOfficeTable;

class IdealisticOfficePartialAttachment extends AbstractIdealisticOfficeAttachment
{

    public function __construct(
        int|string|null                  $id,
        ?string                          $name,
        ?string                          $format,
        ?string                          $url,
        int                              $bytes,
        ?int                             $xSize,
        ?int                             $ySize,
        ?string                          $analyzedDescription,
        int|float|null                   $dataHash,
        string                           $creationDate,
        string                           $creationTimeZone,
        int|Account|IdealisticOfficeUser $createdBy,
        bool                             $json = true
    )
    {
        parent::__construct($id, $name, $format, $url, $bytes, $xSize, $ySize, $analyzedDescription, $dataHash, $creationDate, $creationTimeZone, $createdBy, $json);
    }

    public function upgrade(): ?IdealisticOfficeAttachment
    {
        if ($this->getId() === null) {
            return null;
        }
        $table = $this->isUserUploaded()
            ? IdealisticOfficeTable::HISTORY_USER_ATTACHMENTS_TABLE
            : IdealisticOfficeTable::HISTORY_CREATED_ATTACHMENTS_TABLE;
        $query = get_sql_query(
            $table,
            IdealisticOfficeQuery::ATTACHMENT,
            array(
                array("id", $this->getId())
            ),
            null,
            1
        );
        if (empty($query)) {
            return null;
        }
        $attachment = new IdealisticOfficeAttachment(
            (int)$query[0]->id,
            $query[0]->attachment_name,
            $query[0]->attachment_format,
            null,
            $query[0]->attachment_bytes ?? 0,
            $query[0]->attachment_x,
            $query[0]->attachment_y,
            $query[0]->analyzed_description,
            $query[0]->attachment_data,
            $query[0]->attachment_data_hash,
            $query[0]->is_base64 !== null,
            $query[0]->creation_date,
            $query[0]->creation_timezone,
            $query[0]->created_by
        );
        $attachment->setAnalyzedDescriptionPositions($query[0]->analyzed_description_positions);

        if (!$this->isUserUploaded()) {
            $attachment->setUserPrompt($query[0]->user_prompt ?? "");
        }
        return $attachment;
    }

}
