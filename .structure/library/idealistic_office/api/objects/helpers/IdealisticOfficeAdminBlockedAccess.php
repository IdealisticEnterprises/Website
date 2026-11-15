<?php

class IdealisticOfficeAdminBlockedAccess implements PhpAsyncSerializable
{

    private ?string $creationReason;
    private string $creationDate, $creationTimeZone;

    public function __construct(?string $creationReason, string $creationDate, string $creationTimeZone)
    {
        $this->creationReason = $creationReason;
        $this->creationDate = $creationDate;
        $this->creationTimeZone = $creationTimeZone;
    }

    public function getCreationReason(): ?string
    {
        return $this->creationReason;
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

