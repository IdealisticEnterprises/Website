<?php

class IdealisticOfficeUserPersonal extends IdealisticOfficeJSON
{

    private int|string $id;
    private string $personal, $creationDate, $creationTimeZone;

    public function __construct(
        int|string $id,
        string     $personal,
        string     $creationDate,
        string     $creationTimeZone,
        bool       $json = true
    )
    {
        parent::__construct();
        $this->id = $id;
        $this->personal = $personal;
        $this->creationDate = $creationDate;
        $this->creationTimeZone = $creationTimeZone;

        if ($json) {
            self::setJSON(
                array(
                    "id" => $this->id,
                    "personal" => $this->personal,
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

    public function getPersonal(): string
    {
        return $this->personal;
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
