<?php

interface IdealisticOfficeParticipant
{

    public function getId(): int|string;

    public function getRawObject(): ?object;

    public function hasPermission(int|string $permission): IdealisticOfficeOutcome;

    public function getTimezone(bool $json = true): IdealisticOfficeTimezone;

    public function getLanguageSelection(bool $json = true): IdealisticOfficeLanguageSelection;

}
