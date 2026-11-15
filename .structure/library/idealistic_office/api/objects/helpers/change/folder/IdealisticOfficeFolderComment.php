<?php

class IdealisticOfficeFolderComment extends AbstractIdealisticOfficeChange
{
    private ?string $comment;
    private array $partialAttachments, $partialLinks;

    public function __construct(
        int|string                   $id,
        Account|int                  $creatorAccount,
        AbstractIdealisticOfficeTeam $team,
        string                       $creationDate,
        string                       $timeZone,
        ?string                      $comment,
        array                        $partialAttachments,
        array                        $partialLinks,
        bool                         $json = true
    )
    {
        parent::__construct($id, $creatorAccount, $team, $creationDate, $timeZone, $json);
        $this->comment = $comment;
        $this->partialAttachments = $partialAttachments;
        $this->partialLinks = $partialLinks;

        if ($json) {
            self::addToJSON("comment", $this->comment);
            self::addToJSON("attachments", $this->partialAttachments);
            self::addToJSON("links", $this->partialLinks);
        }
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    /** @return IdealisticOfficePartialAttachment[] */
    public function getPartialAttachments(): array
    {
        return $this->partialAttachments;
    }

    /** @return IdealisticOfficeLink[] */
    public function getPartialLinks(): array
    {
        return $this->partialLinks;
    }

}
