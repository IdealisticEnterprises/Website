<?php

namespace idealistic_office\api\objects\helpers\user;

use idealistic_office\api\objects\helpers\IdealisticOfficeJSON;

class IdealisticOfficeUserShortcut extends IdealisticOfficeJSON
{

    private int|string $id;
    private string
        $word,
        $replacement,
        $creationDate,
        $creationTimeZone;

    public function __construct(
        int|string $id,
        string     $word,
        string     $replacement,
        string     $creationDate,
        string     $creationTimeZone,
        bool       $json = true
    )
    {
        parent::__construct();
        $this->id = $id;
        $this->word = $word;
        $this->replacement = $replacement;
        $this->creationDate = $creationDate;
        $this->creationTimeZone = $creationTimeZone;

        if ($json) {
            self::setJSON(
                array(
                    "id" => $this->id,
                    "word" => $this->word,
                    "replacement" => $this->replacement,
                    "creation_date" => $this->creationDate,
                    "creation_time_zone" => $this->creationTimeZone
                )
            );
        }
    }

    public function getId(): int|string
    {
        return $this->id;
    }

    public function getWord(): string
    {
        return $this->word;
    }

    public function getReplacement(): string
    {
        return $this->replacement;
    }

    public function getCreationDate(): string
    {
        return $this->creationDate;
    }

    public function getCreationDateTimeZone(): string
    {
        return $this->creationTimeZone;
    }

}
