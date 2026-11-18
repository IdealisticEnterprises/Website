<?php

namespace idealistic_office\api\objects\helpers\change;

use account\api\objects\account\Account;
use idealistic_office\api\handlers\IdealisticOfficeEmailer;
use idealistic_office\api\handlers\IdealisticOfficeError;
use idealistic_office\api\handlers\IdealisticOfficeProcessor;
use idealistic_office\api\handlers\IdealisticOfficeReader;
use idealistic_office\api\IdealisticOfficeVariable;
use idealistic_office\api\objects\helpers\AbstractIdealisticOfficeTeam;
use idealistic_office\api\objects\helpers\IdealisticOfficeOutcome;
use idealistic_office\api\objects\IdealisticOfficeHistory;
use IdealisticOfficeLimit;
use IdealisticOfficeTable;

class IdealisticOfficeLegalRecord extends AbstractIdealisticOfficeChange
{

    public const CURRENT_VERSION = 3;

    private string $fullName;
    private ?string $ipAddress;
    private int|string|null
        $historyId;
    private int $version;
    private ?object $identity;

    public function __construct(
        int|string                   $id,
        Account|int                  $creatorAccount,
        AbstractIdealisticOfficeTeam $team,
        int                          $version,
        string                       $fullName,
        ?string                      $ipAddress,
        int|string|null              $historyId,
        ?object                      $identity,
        string                       $creationDate,
        string                       $creationTimeZone,
        bool                         $json = true
    )
    {
        parent::__construct($id, $creatorAccount, $team, $creationDate, $creationTimeZone, $json);
        $this->fullName = $fullName;
        $this->ipAddress = $ipAddress;
        $this->historyId = $historyId;
        $this->identity = $identity;
        $this->version = $version;

        if ($json) {
            self::addToJSON("full_name", $fullName);
            self::addToJSON("ip_address", $this->ipAddress);
            self::addToJSON("history_id", $this->historyId);
            self::addToJSON("identity_object", $identity);
            self::addToJSON("version", $this->version);
        }
    }

    public function getVersion(): int
    {
        return $this->version;
    }

    public function getFullName(): string
    {
        return $this->fullName;
    }

    public function getIpAddress(): ?string
    {
        return $this->ipAddress;
    }

    // Separator

    public function getHistoryId(): int|string|null
    {
        return $this->historyId;
    }

    public function getHistory(): ?IdealisticOfficeHistory
    {
        if (!$this->team->hasEstablishedAccess()
            || $this->historyId === null) {
            return null;
        }
        $query = get_sql_query(
            IdealisticOfficeTable::HISTORY_TOTAL_TABLE,
            IdealisticOfficeQuery::HISTORY,
            array(
                array("team_id", $this->team->getId()),
                array("id", $this->historyId)
            ),
            null,
            1
        );

        if (!empty($query)) {
            $row = $query[0];
            return new IdealisticOfficeHistory(
                $this->team,
                $row->id,
                $row->platform_id,
                $row->platform_user_id,
                $row->platform_message_id,
                $row->platform_chat_id,
                $row->platform_chat_name,
                $this->getCreatorID(),
                $row->creation_date,
                $row->creation_time_zone,
                null
            );
        }
        return null;
    }

    // Separator

    public function getIdentity(): ?object
    {
        return $this->identity;
    }

    // Separator

    public function create(bool $sendEmail = true): IdealisticOfficeOutcome
    {
        if ($this->id > 0) {
            return new IdealisticOfficeOutcome(false, "Legal record already exists.");
        }
        if (!$this->team->hasEstablishedAccess()) {
            return new IdealisticOfficeOutcome(false, "No team access established.");
        }
        $creator = $this->getCreator();

        if ($creator === null) {
            return new IdealisticOfficeOutcome(false, "Creator account not found.");
        }
        if (strlen($this->fullName) > IdealisticOfficeLimit::H128_LENGTH) {
            return new IdealisticOfficeOutcome(
                false,
                "Full name exceeds maximum length of "
                . IdealisticOfficeLimit::H128_LENGTH . " characters."
            );
        }
        $terms = IdealisticOfficeProcessor::getLinkContents(
            IdealisticOfficeVariable::TERMS_OF_USE_URL
        );

        if (!is_string($terms)) {
            return new IdealisticOfficeOutcome(false, "Failed to retrieve terms of use.");
        }
        $policy = IdealisticOfficeProcessor::getLinkContents(
            IdealisticOfficeVariable::PRIVACY_POLICY_URL
        );

        if (!is_string($policy)) {
            return new IdealisticOfficeOutcome(false, "Failed to retrieve privacy policy.");
        }
        if (IdealisticOfficeError::sqlInsert(
            $this->team,
            $creator,
            IdealisticOfficeTable::LEGAL_RECORDS_TABLE,
            array(
                "team_id" => $this->team->getId(),
                "account_id" => $creator->getId(),
                "full_name" => $this->fullName,
                "ip_address" => $this->ipAddress,
                "history_id" => $this->historyId,
                "identity_object" => $this->identity === null
                    ? null
                    : IdealisticOfficeReader::jsonObject($this->identity, true),
                "creation_date" => $this->creationDate,
                "creation_time_zone" => $this->creationTimeZone,
                "terms_of_use" => $terms,
                "terms_of_service" => $terms,
                "privacy_policy" => $policy,
                "current_version" => $this->version
            )
        )) {
            $id = IdealisticOfficeReader::getSqlLastInsertId();

            if ($id === null) {
                return new IdealisticOfficeOutcome(false, "Failed to retrieve last insert ID.");
            }
            $this->id = $id;

            if ($sendEmail
                && $creator->hasEmail()) {
                IdealisticOfficeEmailer::send(
                    "0-legalAccepted",
                    $creator->getEmail(),
                    array(
                        "name" => $this->getFullName()
                    ),
                    false
                );
            }
            $fullName = explode(" ", $this->getFullName(), 3);

            if (sizeof($fullName) === 3) {
                $creator->setName(
                    $fullName[0],
                    $fullName[1],
                    $fullName[2]
                );
            } else if (sizeof($fullName) === 2) {
                $creator->setName(
                    $fullName[0],
                    null,
                    $fullName[1]
                );
            } else {
                $creator->setName(
                    $fullName[0],
                    null,
                    null
                );
            }
            return new IdealisticOfficeOutcome(
                true,
                "Legal record created successfully, you may now use " . IdealisticOfficeVariable::APPLICATION_NAME
            );
        } else {
            return new IdealisticOfficeOutcome(false, "Failed to create legal record.");
        }
    }

}
