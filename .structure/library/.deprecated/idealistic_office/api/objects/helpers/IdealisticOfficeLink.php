<?php

namespace idealistic_office\api\objects\helpers;

use account\api\objects\account\Account;
use idealistic_office\api\handlers\IdealisticOfficeError;
use idealistic_office\api\handlers\IdealisticOfficeProcessor;
use idealistic_office\api\handlers\IdealisticOfficeReader;
use idealistic_office\api\objects\IdealisticOfficeUser;
use IdealisticOfficeLimit;
use IdealisticOfficeTable;

class IdealisticOfficeLink extends IdealisticOfficeJSON
{

    private int|string $id;
    private int $hash;
    private string $url, $creationDate, $creationTimeZone;
    private ?string $analyzedDescriptionPositions,
        $content, $analyzedDescription,
        $refreshDate, $refreshTimeZone;
    private int|Account $createdBy;
    private int|Account|null $refreshedBy;
    private AbstractIdealisticOfficeTeam $team;

    public function __construct(
        AbstractIdealisticOfficeTeam $team,
        int|string                   $id,
        string                       $url,
        ?string                      $content,
        ?string                      $analyzedDescription,
        ?string                      $analyzedDescriptionPositions,
        string                       $creationDate,
        string                       $creationTimeZone,
        int|Account                  $createdBy,
        ?string                      $refreshDate,
        ?string                      $refreshTimeZone,
        int|Account|null             $refreshedBy,
        bool                         $json = true
    )
    {
        parent::__construct();
        $this->team = $team;
        $this->id = $id;
        $this->url = $url;
        $this->content = $content;
        $this->analyzedDescription = $analyzedDescription;
        $this->analyzedDescriptionPositions = $analyzedDescriptionPositions;
        $this->creationDate = $creationDate;
        $this->creationTimeZone = $creationTimeZone;
        $this->createdBy = $createdBy;
        $this->refreshDate = $refreshDate;
        $this->refreshTimeZone = $refreshTimeZone;
        $this->refreshedBy = $refreshedBy;
        $this->hash = overflow_integer((string_to_integer($url) * 31) + string_to_integer($content));

        if ($json) {
            self::setJSON(
                array(
                    "id" => $this->id,
                    "team_id" => $this->team->getId(),
                    "url" => $this->url,
                    "content" => $this->content,
                    "analyzed_description" => $this->analyzedDescription,
                    "creation_date" => $this->creationDate,
                    "creation_time_zone" => $this->creationTimeZone,
                    "creator_account" => $this->getCreatorAccount(),
                    "refresh_date" => $this->refreshDate,
                    "refresh_time_zone" => $this->refreshTimeZone,
                    "refreshed_by" => $this->refreshedBy
                )
            );
        }
    }

    public function getTeam(): AbstractIdealisticOfficeTeam
    {
        return $this->team;
    }

    public function getHash(): int
    {
        return $this->hash;
    }

    public function getId(): int|string
    {
        return $this->id;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getDomain(): string
    {
        return get_domain_from_url($this->url, true);
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function getAnalyzedDescription(): ?string
    {
        return $this->analyzedDescription;
    }

    public function getAnalyzedDescriptionPositions(): ?string
    {
        return $this->analyzedDescriptionPositions;
    }

    public final function getCreationDate(): string
    {
        return $this->creationDate;
    }

    public final function getRefreshDate(): ?string
    {
        return $this->refreshDate;
    }

    public final function getCreationTimeZone(): string
    {
        return $this->creationTimeZone;
    }

    public final function getRefreshTimeZone(): ?string
    {
        return $this->refreshTimeZone;
    }

    public final function getCreator(): ?IdealisticOfficeUser
    {
        $user = $this->team->findUser($this->getCreatorAccount());
        return $user instanceof IdealisticOfficeUser ? $user : null;
    }

    public final function getRefresher(): ?IdealisticOfficeUser
    {
        if ($this->refreshedBy === null) {
            return null;
        }
        $user = $this->team->findUser($this->refreshedBy);
        return $user instanceof IdealisticOfficeUser ? $user : null;
    }

    public final function getCreatorAccount(): Account
    {
        if (is_int($this->createdBy)) {
            $this->createdBy = new Account(
                Account::IDEALISTIC_OFFICE_APPLICATION_ID,
                $this->createdBy
            );
        }
        return $this->createdBy;
    }

    public final function getRefresherAccount(): ?Account
    {
        if ($this->refreshedBy === null) {
            return null;
        }
        if (is_int($this->refreshedBy)) {
            $this->refreshedBy = new Account(
                Account::IDEALISTIC_OFFICE_APPLICATION_ID,
                $this->refreshedBy
            );
        }
        return $this->refreshedBy;
    }

    public final function getCreatorID(): int
    {
        if (is_int($this->createdBy)) {
            return $this->createdBy;
        } else {
            return $this->createdBy->getDetail("id", 0);
        }
    }

    public final function getRefresherID(): ?int
    {
        if ($this->refreshedBy === null) {
            return null;
        }
        if (is_int($this->refreshedBy)) {
            return $this->refreshedBy;
        } else {
            return $this->refreshedBy->getDetail("id", 0);
        }
    }

    public final function refresh(
        IdealisticOfficeUser $user,
        string               $table = IdealisticOfficeTable::HISTORY_LINKS_TABLE
    ): IdealisticOfficeOutcome
    {
        $contents = IdealisticOfficeProcessor::getLinkContents($this->url);

        if (!empty($contents)) {
            $description = IdealisticOfficeProcessor::analyzeLink(
                null,
                $this->team,
                $contents,
                IdealisticOfficeLimit::K11_LENGTH
            );

            if (!empty($description)) {
                $timezone = $user->getTimezone(false);
                $this->content = $contents;
                $this->analyzedDescription = $description;
                IdealisticOfficeError::setSqlQuery(
                    $this->team,
                    $user,
                    $table,
                    array(
                        "link_content" => $contents,
                        "analyzed_description" => $description,
                        "refresh_date" => IdealisticOfficeReader::getCurrentDate($timezone),
                        "refresh_time_zone" => $timezone->getTimeZone(),
                        "refreshed_by" => $user->getId()
                    ),
                    array(
                        array("id", $this->id)
                    ),
                    1
                );
                return new IdealisticOfficeOutcome(true, "Link refreshed successfully.");
            } else {
                return new IdealisticOfficeOutcome(false, "Failed to analyze the link.");
            }
        } else {
            return new IdealisticOfficeOutcome(false, "Failed to retrieve the link contents.");
        }
    }

}
