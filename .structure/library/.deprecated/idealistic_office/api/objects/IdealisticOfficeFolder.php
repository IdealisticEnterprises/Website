<?php

namespace idealistic_office\api\objects;

use account\api\objects\account\Account;
use idealistic_office\api\handlers\IdealisticOfficeError;
use idealistic_office\api\handlers\IdealisticOfficeHistoryIndependent;
use idealistic_office\api\handlers\IdealisticOfficeReader;
use idealistic_office\api\IdealisticOfficeVariable;
use idealistic_office\api\objects\helpers\AbstractIdealisticOfficeTeam;
use idealistic_office\api\objects\helpers\change\folder\IdealisticOfficeFolderAttachedPortal;
use idealistic_office\api\objects\helpers\change\folder\IdealisticOfficeFolderComment;
use idealistic_office\api\objects\helpers\change\folder\IdealisticOfficeFolderModifier;
use idealistic_office\api\objects\helpers\change\folder\IdealisticOfficeFolderParticipant;
use idealistic_office\api\objects\helpers\IdealisticOfficeJSON;
use idealistic_office\api\objects\helpers\IdealisticOfficeOutcome;
use IdealisticOfficeLimit;
use IdealisticOfficePermission;
use IdealisticOfficeQuery;
use IdealisticOfficeTable;

class IdealisticOfficeFolder extends IdealisticOfficeJSON
{

    private AbstractIdealisticOfficeTeam $team;
    private string $name,
        $creationDate, $creationTimeZone,
        $lastModifiedDate, $lastModifiedTimeZone;
    private ?string $creationReason, $expirationDate, $expirationTimeZone;
    private int|string|null $id, $customId;
    private int|Account $firstOpenerID, $lastModifiedBy;
    private ?int $chronologicalOrder;

    public function __construct(
        AbstractIdealisticOfficeTeam $team,
        int|string                   $id,
        int|string|null              $customId,
        string                       $name,
        int|Account                  $firstOpenerID,
        ?int                         $chronologicalOrder,
        string                       $creationDate,
        string                       $creationCreationTimeZone,
        ?string                      $creationReason,
        ?string                      $expirationDate,
        ?string                      $expirationTimeZone,
        int|Account                  $lastModifiedBy,
        string                       $lastModifiedDate,
        string                       $lastModifiedTimeZone,
        bool                         $json = true
    )
    {
        parent::__construct();
        $this->team = $team;
        $this->id = $id;
        $this->customId = $customId;
        $this->name = $name;
        $this->firstOpenerID = $firstOpenerID;
        $this->creationDate = $creationDate;
        $this->creationTimeZone = $creationCreationTimeZone;
        $this->creationReason = $creationReason;
        $this->lastModifiedBy = $lastModifiedBy;
        $this->lastModifiedDate = $lastModifiedDate;
        $this->lastModifiedTimeZone = $lastModifiedTimeZone;
        $this->expirationDate = $expirationDate;
        $this->expirationTimeZone = $expirationTimeZone;
        $this->chronologicalOrder = $chronologicalOrder;

        if ($this->isExpired()) {
            $this->remove(null, true, "having expired");
        }
        if ($json) {
            $open = $this->getOpenInformation(false);
            $close = $this->getClosedInformation(false);
            self::setJSON(
                array(
                    "id" => $this->id,
                    "custom_id" => $this->customId,
                    "name" => $this->getName(),
                    "participants" => sizeof($this->getParticipants()),
                    "first_opener" => $this->getFirstOpenerUser(),
                    "first_opener_account" => $this->getFirstOpenerAccount(),
                    "chronological_order" => $this->chronologicalOrder,
                    "creation_date" => $this->creationDate,
                    "creation_time_zone" => $this->creationTimeZone,
                    "creation_reason" => $this->creationReason,
                    "expiration_date" => $this->expirationDate,
                    "expiration_time_zone" => $this->expirationTimeZone,
                    "last_modified_date" => $this->lastModifiedDate,
                    "last_modified_time_zone" => $this->lastModifiedTimeZone,
                    "last_modified_by" => $this->getLastModifiedUser(),
                    "last_modified_by_account" => $this->getLastModifiedAccount(),
                    "last_open_date" => $open?->getCreationDate(),
                    "last_open_time_zone" => $open?->getCreationDateTimeZone(),
                    "last_open_reason" => $open?->getCreationReason(),
                    "last_opener" => $open?->getCreator(),
                    "last_opener_account" => $open?->getCreatorAccount(),
                    "closed_date" => $close?->getCreationDate(),
                    "closed_time_zone" => $close?->getCreationDateTimeZone(),
                    "closed_reason" => $close?->getCreationReason(),
                    "closer" => $close?->getCreator(),
                    "closer_account" => $close?->getCreatorAccount(),
                )
            );
        }
    }

    // Separator

    public function getTeam(): AbstractIdealisticOfficeTeam
    {
        return $this->team;
    }

    public function setTeam(AbstractIdealisticOfficeTeam $team): void
    {
        $this->team = $team;
    }

    public function getId(): int|string
    {
        return $this->id;
    }

    public function getCustomId(): int|string|null
    {
        return $this->customId;
    }

    public function hasCustomId(): bool
    {
        return $this->customId !== null;
    }

    public function getName(): string
    {
        if (is_numeric($this->name)) {
            $participants = $this->getParticipants(false);

            if (!empty($participants)) {
                $users = array();
                $names = array();

                foreach ($participants as $participant) {
                    $user = $participant->getAddedAccountUser();

                    if ($user === null) {
                        return $this->name;
                    }
                    $users[$user->getId()] = $user;
                    $names[] = $user->getDisplayName();
                }
                $hash = IdealisticOfficeReader::hashUsers($users);

                if ($hash == $this->name) {
                    return implode(IdealisticOfficeVariable::DEFAULT_TEXT_SEPARATOR, $names);
                }
            }
        }
        return $this->name;
    }

    public function getChronologicalOrder(): ?int
    {
        return $this->chronologicalOrder;
    }

    public function shouldRemove(
        array $ignorePortals = [],
    ): bool
    {
        if (empty($this->getComments(1, false))) {
            $portals = $this->getAttachedPortals(false, 1, true);

            if (!empty($ignorePortals)) {
                foreach ($portals as $key => $portal) {
                    if (in_array($portal->getId(), $ignorePortals)) {
                        unset($portals[$key]);
                    }
                }
            }
            return empty($folders)
                && empty($portals);
        } else {
            return false;
        }
    }

    public function remove(
        ?IdealisticOfficeUser $user = null,
        bool                  $notify = false,
        ?string               $justification = null
    ): IdealisticOfficeOutcome
    {
        if (!$this->exists()) {
            return new IdealisticOfficeOutcome(false, "Folder '" . $this->getName() . "' does not exist.");
        }
        if ($user !== null) {
            if (!$user->hasEstablishedAccess()) {
                return new IdealisticOfficeOutcome(false, "No team access established.");
            }
            if (!$user->hasPermission(IdealisticOfficePermission::ADJUST_FOLDERS)->isPositiveOutcome()) {
                return new IdealisticOfficeOutcome(
                    false,
                    "You do not have permission to manage folders."
                );
            }
            if ($this->getAddedParticipant($user) === null) {
                return new IdealisticOfficeOutcome(
                    false,
                    "You must be in this folder to remove it."
                );
            }
        }
        if (IdealisticOfficeError::setSqlQuery(
            $this->team,
            $user,
            IdealisticOfficeTable::FOLDERS_TABLE,
            array(
                "deletion_date" => IdealisticOfficeReader::getCurrentDate(),
                "deletion_time_zone" => IdealisticOfficeVariable::DEFAULT_TIME_ZONE,
                "deleted_by" => $user?->getId()
            ),
            array(
                array("id", $this->id)
            ),
            null,
            1
        )) {
            if ($notify) {
                $this->notify(
                    "Folder '" . $this->getName() . "' has been removed"
                    . ($justification === null
                        ? "."
                        : " due to: " . $justification),
                    $user
                );
            }
            return new IdealisticOfficeOutcome(
                true,
                "Folder '" . $this->getName() . "' has been successfully removed"
                . ($justification === null
                    ? "."
                    : " due to: " . $justification)
            );
        } else {
            return new IdealisticOfficeOutcome(
                false,
                "Failed to delete folder '" . $this->getName() . "'."
            );
        }
    }

    public function restore(
        ?IdealisticOfficeUser $user = null
    ): IdealisticOfficeOutcome
    {
        if (!$this->exists()) {
            return new IdealisticOfficeOutcome(false, "Folder '" . $this->getName() . "' does not exist or is not deleted.");
        }
        if ($user !== null) {
            if (!$user->hasEstablishedAccess()) {
                return new IdealisticOfficeOutcome(false, "No team access established.");
            }
            if (!$user->hasPermission(IdealisticOfficePermission::ADJUST_FOLDERS)->isPositiveOutcome()) {
                return new IdealisticOfficeOutcome(
                    false,
                    "You do not have permission to manage folders."
                );
            }
            if ($this->getAddedParticipant($user) === null) {
                return new IdealisticOfficeOutcome(
                    false,
                    "You be in this folder to restore it."
                );
            }
        }
        if (IdealisticOfficeError::setSqlQuery(
            $this->team,
            $user,
            IdealisticOfficeTable::FOLDERS_TABLE,
            array(
                "deletion_date" => null,
                "deletion_time_zone" => null,
                "deleted_by" => null
            ),
            array(
                array("id", $this->id)
            ),
            null,
            1
        )) {
            $this->notify(
                "Folder '" . $this->getName() . "' has been restored" . ($user !== null ? " by '" . $user->getDisplayName() . "'" : "") . ".",
                $user
            );
            return new IdealisticOfficeOutcome(
                true,
                "Folder '" . $this->getName() . "' has been successfully restored."
            );
        } else {
            return new IdealisticOfficeOutcome(
                false,
                "Failed to restore folder '" . $this->getName() . "'."
            );
        }
    }

    /** @return IdealisticOfficeFolderParticipant[] */
    public function getParticipants(bool $json = true): array
    {
        $query = get_sql_query(
            IdealisticOfficeTable::FOLDER_PARTICIPANTS_TABLE,
            IdealisticOfficeQuery::FOLDER_PARTICIPANT,
            array(
                array("folder_id", $this->id),
                array("deletion_date", null)
            ),
            null,
            IdealisticOfficeLimit::MAX_EXTENSIONS_PER_OBJECT_LARGE
        );

        if (!empty($query)) {
            foreach ($query as $key => $row) {
                $query[$key] = new IdealisticOfficeFolderParticipant(
                    $row->id,
                    $row->created_by,
                    $this->team,
                    $row->creation_reason,
                    $row->creation_date,
                    $row->creation_time_zone,
                    $row->account_id,
                    $json
                );
            }
            return $query;
        } else {
            return array();
        }
    }

    public function getCreationDate(): string
    {
        return $this->creationDate;
    }

    public function getCreationDateTimeZone(): string
    {
        return $this->creationTimeZone;
    }

    public function getCreationReason(): ?string
    {
        return $this->creationReason;
    }

    public function getFirstOpenerID(): int|string
    {
        return is_numeric($this->firstOpenerID)
            ? $this->firstOpenerID
            : $this->firstOpenerID->getDetail("id", 0);
    }

    public function getFirstOpenerAccount(): Account
    {
        if (is_numeric($this->firstOpenerID)) {
            $this->firstOpenerID = new Account(
                Account::IDEALISTIC_OFFICE_APPLICATION_ID,
                $this->firstOpenerID
            );
        }
        return $this->firstOpenerID;
    }

    public function getFirstOpenerUser(): ?IdealisticOfficeUser
    {
        $user = $this->team->findUser($this->getFirstOpenerAccount());

        if ($user instanceof IdealisticOfficeUser) {
            return $user;
        } else {
            return null;
        }
    }

    public function getLastModifiedID(): int|string
    {
        return is_numeric($this->lastModifiedBy)
            ? $this->lastModifiedBy
            : $this->lastModifiedBy->getDetail("id", 0);
    }

    public function getLastModifiedAccount(): Account
    {
        if (is_numeric($this->lastModifiedBy)) {
            $this->lastModifiedBy = new Account(
                Account::IDEALISTIC_OFFICE_APPLICATION_ID,
                $this->lastModifiedBy
            );
        }
        return $this->lastModifiedBy;
    }

    public function getLastModifiedUser(): ?IdealisticOfficeUser
    {
        $user = $this->team->findUser($this->getLastModifiedAccount());

        if ($user instanceof IdealisticOfficeUser) {
            return $user;
        } else {
            return null;
        }
    }

    public function getLastModifiedDate(): string
    {
        return $this->lastModifiedDate;
    }

    public function getLastModifiedDateTimeZone(): string
    {
        return $this->lastModifiedTimeZone;
    }

    // Separator

    public function rename(IdealisticOfficeUser $user, string $name): IdealisticOfficeOutcome
    {
        if (!$user->hasPermission(IdealisticOfficePermission::ADJUST_FOLDERS)->isPositiveOutcome()) {
            return new IdealisticOfficeOutcome(false, "You do not have permission to rename folders.");
        }
        if ($this->getAddedParticipant($user) === null) {
            return new IdealisticOfficeOutcome(
                false,
                "You must be in this folder to rename it."
            );
        }
        if ($this->hasCustomId()) {
            return new IdealisticOfficeOutcome(
                false,
                "You cannot rename folder '" . $this->getName() . "' because it is reserved for other purposes."
            );
        }
        if (strlen($name) === 0) {
            return new IdealisticOfficeOutcome(false, "Folder name cannot be empty.");
        }
        if (strlen($name) > IdealisticOfficeLimit::H128_LENGTH) {
            return new IdealisticOfficeOutcome(
                false,
                "Folder name is too long. Maximum length is " . IdealisticOfficeLimit::H128_LENGTH . " characters."
            );
        }
        if (str_contains($name, "\r")) {
            return new IdealisticOfficeOutcome(false, "Folder name cannot contain carriage return characters.");
        }
        if (str_contains($name, "\n")) {
            return new IdealisticOfficeOutcome(false, "Folder name cannot contain newline characters.");
        }
        if (str_contains($name, "\t")) {
            return new IdealisticOfficeOutcome(false, "Folder name cannot contain tab characters.");
        }
        if (str_contains($name, " ")) {
            return new IdealisticOfficeOutcome(false, "Folder name cannot contain space characters.");
        }
        if ($name === $this->getName()) {
            return new IdealisticOfficeOutcome(false, "New folder name is the same as the current name.");
        }
        if (strtolower($name) !== strtolower($this->getName())
            && !empty(get_sql_query(
                IdealisticOfficeTable::FOLDERS_TABLE,
                array("id"),
                array(
                    array("team_id", $this->team->getId()),
                    array("LOWER(folder_name)", strtolower($name)),
                    array("deletion_date", null)
                ),
                null,
                1
            ))) {
            return new IdealisticOfficeOutcome(
                false,
                "A folder with name '" . $this->getName() . "' already exists."
            );
        }
        $timezone = $user->getTimezone(false);
        $date = IdealisticOfficeReader::getCurrentDate($timezone);

        if (IdealisticOfficeError::setSqlQuery(
            $this->team,
            $user,
            IdealisticOfficeTable::FOLDERS_TABLE,
            array(
                "last_modified_date" => $date,
                "last_modified_time_zone" => $timezone->getTimeZone(),
                "last_modified_by" => $user->getId(),
                "folder_name" => $name
            ),
            array(
                array("id", $this->id)
            ),
            null,
            1
        )) {
            $oldName = $this->getName();
            $this->name = $name;
            return new IdealisticOfficeOutcome(true, "Folder '" . $oldName . "' renamed successfully to '" . $name . "'.");
        } else {
            return new IdealisticOfficeOutcome(false, "Failed to rename folder with ID '" . $this->id . "'.");
        }
    }

    // Separator

    public function open(IdealisticOfficeUser $user, ?string $justification = null): IdealisticOfficeOutcome
    {
        if (!$user->hasEstablishedAccess()) {
            return new IdealisticOfficeOutcome(false, "No team access established.");
        }
        if (!$user->hasPermission(IdealisticOfficePermission::ADJUST_FOLDERS)->isPositiveOutcome()) {
            return new IdealisticOfficeOutcome(
                false,
                "You do not have permission to manage folders."
            );
        }
        if ($this->exists()
            && $this->getAddedParticipant($user) === null) {
            return new IdealisticOfficeOutcome(
                false,
                "You must be in this folder to open it."
            );
        }
        if ($justification !== null
            && mb_strlen($justification) > IdealisticOfficeLimit::K15_LENGTH) {
            return new IdealisticOfficeOutcome(
                false,
                "Justification for opening the folder '" . $this->getName() . "' is too long. Maximum length is "
                . IdealisticOfficeLimit::K15_LENGTH . " characters."
            );
        }
        $open = $this->getOpenInformation(false);

        if ($open !== null) {
            return new IdealisticOfficeOutcome(
                false,
                "Folder '" . $this->getName() . "' is already open since '"
                . $open->getCreationDate() . " " . $open->getCreationDateTimeZone()
                . "' by '" . $open->getCreator()->getDisplayName() . "'."
            );
        }
        if ($this->getClosedInformation(false) === null) {
            if (strlen($this->getName()) === 0) {
                return new IdealisticOfficeOutcome(
                    false,
                    "The folder name cannot be empty."
                );
            }
            if (strlen($this->getName()) > IdealisticOfficeLimit::H128_LENGTH) {
                if ($this->hasCustomId()) {
                    $this->name = mb_substr($this->getName(), 0, IdealisticOfficeLimit::H128_LENGTH);
                } else {
                    return new IdealisticOfficeOutcome(
                        false,
                        "The folder name '" . $this->getName() . "' is too long. Maximum length is "
                        . IdealisticOfficeLimit::H128_LENGTH . " characters."
                    );
                }
            }
            if (str_contains($this->getName(), "\r")) {
                return new IdealisticOfficeOutcome(false, "Folder name cannot contain carriage return characters.");
            }
            if (str_contains($this->getName(), "\n")) {
                return new IdealisticOfficeOutcome(false, "Folder name cannot contain newline characters.");
            }
            if (str_contains($this->getName(), "\t")) {
                return new IdealisticOfficeOutcome(false, "Folder name cannot contain tab characters.");
            }
            if (str_contains($this->getName(), " ")) {
                return new IdealisticOfficeOutcome(false, "Folder name cannot contain space characters.");
            }
            if (!empty(get_sql_query(
                IdealisticOfficeTable::FOLDERS_TABLE,
                array("id"),
                array(
                    array("team_id", $this->team->getId()),
                    array("LOWER(folder_name)", strtolower($this->getName())),
                    array("deletion_date", null)
                ),
                null,
                1
            ))) {
                return new IdealisticOfficeOutcome(
                    false,
                    "A folder with name '" . $this->getName() . "' already exists."
                );
            }
            if ($justification === null) {
                $justification = $this->creationReason;
            }
            if (IdealisticOfficeError::sqlInsert(
                $this->team,
                $user,
                IdealisticOfficeTable::FOLDERS_TABLE,
                array(
                    "custom_id" => $this->customId,
                    "team_id" => $this->team->getId(),
                    "created_by" => $user->getId(),
                    "creation_date" => $this->creationDate,
                    "creation_time_zone" => $this->creationTimeZone,
                    "creation_reason" => $justification,
                    "last_modified_date" => $this->creationDate,
                    "last_modified_time_zone" => $this->creationTimeZone,
                    "last_modified_by" => $user->getId(),
                    "folder_name" => $this->getName(),
                )
            )) {
                $insertID = IdealisticOfficeReader::getSqlLastInsertId();

                if ($insertID === null) {
                    return new IdealisticOfficeOutcome(
                        false,
                        "Failed to find opened folder '" . $this->getName() . "' for the first time."
                    );
                } else {
                    $this->id = $insertID;
                    IdealisticOfficeError::sqlInsert(
                        $this->team,
                        $user,
                        IdealisticOfficeTable::FOLDER_PARTICIPANTS_TABLE,
                        array(
                            "account_id" => $user->getId(),
                            "creation_date" => $this->creationDate,
                            "creation_time_zone" => $this->creationTimeZone,
                            "folder_id" => $this->id,
                            "created_by" => $user->getId()
                        )
                    );
                    return new IdealisticOfficeOutcome(
                        true,
                        "Folder '" . $this->getName() . "' opened for the first time"
                        . ($justification === null ? "." : " with justification: " . $justification)
                    );
                }
            } else {
                return new IdealisticOfficeOutcome(
                    false,
                    "Failed to open folder '" . $this->getName() . "' for the first time."
                );
            }
        } else {
            $timezone = $user->getTimezone(false);
            $date = IdealisticOfficeReader::getCurrentDate($timezone);

            if (IdealisticOfficeError::sqlInsert(
                $this->team,
                $user,
                IdealisticOfficeTable::FOLDER_STATUS_TABLE,
                array(
                    "account_id" => $user->getId(),
                    "creation_date" => $date,
                    "creation_reason" => $justification,
                    "creation_time_zone" => $timezone->getTimeZone(),
                    "folder_id" => $this->id,
                    "open" => true,
                )
            )) {
                $this->setLastModified($user, $date, $timezone->getTimeZone());
                $this->notify(
                    "Folder '" . $this->getName() . "' has been re-opened by '" . $user->getDisplayName() . "'.",
                    $user
                );
                return new IdealisticOfficeOutcome(
                    true,
                    "Folder '" . $this->getName() . "' is now re-opened."
                );
            } else {
                return new IdealisticOfficeOutcome(
                    false,
                    "Failed to re-open folder '" . $this->getName() . "'."
                );
            }
        }
    }

    public function close(IdealisticOfficeUser $user, ?string $justification = null): IdealisticOfficeOutcome
    {
        if (!$user->hasPermission(IdealisticOfficePermission::ADJUST_FOLDERS)->isPositiveOutcome()) {
            return new IdealisticOfficeOutcome(
                false,
                "You do not have permission to manage folders."
            );
        }
        if ($this->getAddedParticipant($user) === null) {
            return new IdealisticOfficeOutcome(
                false,
                "You must be in this folder to close it."
            );
        }
        if (!$this->exists()) {
            return new IdealisticOfficeOutcome(false, "Folder '" . $this->getName() . "' does not exist.");
        }
        if ($this->hasCustomId()) {
            return new IdealisticOfficeOutcome(
                false,
                "You cannot close folder '" . $this->getName() . "' because it is reserved for other purposes."
            );
        }
        if ($justification !== null
            && mb_strlen($justification) > IdealisticOfficeLimit::K15_LENGTH) {
            return new IdealisticOfficeOutcome(
                false,
                "Justification for closing the folder '" . $this->getName() . "' is too long. Maximum length is "
                . IdealisticOfficeLimit::K15_LENGTH . " characters."
            );
        }
        $closed = $this->getClosedInformation();

        if ($closed !== null) {
            return new IdealisticOfficeOutcome(
                false,
                "Folder '" . $this->getName() . "' is already closed since '"
                . $closed->getCreationDate() . " " . $closed->getCreationDateTimeZone()
                . "' by '" . $closed->getCreator()->getDisplayName() . "'."
            );
        }
        $timezone = $user->getTimezone(false);
        $openInformation = $this->getOpenInformation(false);
        $date = IdealisticOfficeReader::getCurrentDate($timezone);

        if (IdealisticOfficeError::sqlInsert(
            $this->team,
            $user,
            IdealisticOfficeTable::FOLDER_STATUS_TABLE,
            array(
                "account_id" => $user->getId(),
                "creation_date" => $date,
                "creation_reason" => $justification,
                "creation_time_zone" => $timezone->getTimeZone(),
                "folder_id" => $this->id,
                "open" => false,
            )
        )) {
            $this->setLastModified($user, $date, $timezone->getTimeZone());

            if ($openInformation === null) {
                $this->notify(
                    "Folder '" . $this->getName() . "' has been closed for the first time by '" . $user->getDisplayName() . "'"
                    . ($justification === null ? "." : " with justification: " . $justification),
                    $user
                );
                return new IdealisticOfficeOutcome(
                    true,
                    "Folder '" . $this->getName() . "' is now closed for the first time"
                    . ($justification === null ? "." : " with justification: " . $justification)
                );
            } else {
                $this->notify(
                    "Folder '" . $this->getName() . "' has been closed again by '" . $user->getDisplayName() . "'"
                    . ($justification === null ? "." : " with justification: " . $justification),
                    $user
                );
                return new IdealisticOfficeOutcome(
                    true,
                    "Folder '" . $this->getName() . "' is now closed again"
                    . ($justification === null ? "." : " with justification: " . $justification)
                );
            }
        } else {
            return new IdealisticOfficeOutcome(
                false,
                "Failed to close folder '" . $this->getName() . "'."
            );
        }
    }

    // Separator

    public function addUser(
        IdealisticOfficeUser $executorUser,
        IdealisticOfficeUser $user,
        bool                 $bypassCustomId = false,
        ?string              $justification = null,
        bool                 $notify = true
    ): IdealisticOfficeOutcome
    {
        if (!$executorUser->hasPermission(IdealisticOfficePermission::ADJUST_FOLDERS)->isPositiveOutcome()) {
            return new IdealisticOfficeOutcome(
                false,
                "You do not have permission to manage folders."
            );
        }
        if ($executorUser->getTeam()->getId() !== $this->team->getId()) {
            return new IdealisticOfficeOutcome(
                false,
                "You must own this folder to add members to it"
            );
        }
        if ($this->getCustomId() !== null) {
            $outcome = $executorUser->getGeneralizedAccess($this->team);

            if (!$outcome->isPositiveOutcome()) {
                return $outcome;
            }
        }
        if (!$bypassCustomId && $this->hasCustomId()) {
            return new IdealisticOfficeOutcome(
                false,
                "You cannot add members to folder '" . $this->getName() . "' because it is reserved for other purposes."
            );
        }
        if ($user->getId() == $executorUser->getId()) {
            return new IdealisticOfficeOutcome(
                false,
                "You cannot add yourself to the folder '" . $this->getName() . "'."
            );
        }
        $closed = $this->getClosedInformation();

        if ($closed !== null) {
            return new IdealisticOfficeOutcome(
                false,
                "Folder '" . $this->getName() . "' must be open to add members. In this folder, it is closed since '"
                . $closed->getCreationDate() . " " . $closed->getCreationDateTimeZone()
                . "' by '" . $closed->getCreator()->getDisplayName() . "'."
            );
        }
        if ($this->getAddedParticipant($user, false) !== null) {
            return new IdealisticOfficeOutcome(
                false,
                "Member '" . $user->getDisplayName() . "' is already added to folder '" . $this->getName() . "'."
            );
        }
        if ($justification !== null
            && mb_strlen($justification) > IdealisticOfficeLimit::K8_LENGTH) {
            return new IdealisticOfficeOutcome(
                false,
                "Justification for adding a member to the folder '" . $this->getName() . "' is too long. Maximum length is "
                . IdealisticOfficeLimit::K8_LENGTH . " characters."
            );
        }
        $timezone = $executorUser->getTimezone(false);
        $date = IdealisticOfficeReader::getCurrentDate($timezone);

        if (IdealisticOfficeError::sqlInsert(
            $this->team,
            $executorUser,
            IdealisticOfficeTable::FOLDER_PARTICIPANTS_TABLE,
            array(
                "account_id" => $user->getId(),
                "creation_date" => $date,
                "creation_reason" => $justification,
                "creation_time_zone" => $timezone->getTimeZone(),
                "folder_id" => $this->id,
                "created_by" => $executorUser->getId()
            )
        )) {
            $this->setLastModified($executorUser, $date, $timezone->getTimeZone());

            if ($notify) {
                $this->notify(
                    "Member '" . $user->getDisplayName() . "' has been added to folder '" . $this->getName() . "' by '"
                    . $executorUser->getDisplayName() . "'.",
                    $executorUser
                );
            }
            return new IdealisticOfficeOutcome(
                true,
                "Member '" . $user->getDisplayName() . "' has been added to folder '" . $this->getName() . "'."
            );
        } else {
            return new IdealisticOfficeOutcome(
                false,
                "Failed to add member '" . $user->getDisplayName() . "' to folder '" . $this->getName() . "'."
            );
        }
    }

    public function removeUser(
        IdealisticOfficeUser $executorUser,
        IdealisticOfficeUser $user,
        bool                 $bypassCustomId = false,
        ?string              $justification = null
    ): IdealisticOfficeOutcome
    {
        if (!$user->hasPermission(IdealisticOfficePermission::ADJUST_FOLDERS)->isPositiveOutcome()) {
            return new IdealisticOfficeOutcome(
                false,
                "You do not have permission to manage folders."
            );
        }
        if ($executorUser->getTeam()->getId() !== $this->team->getId()) {
            return new IdealisticOfficeOutcome(
                false,
                "You must own this folder to remove its participants."
            );
        }
        if ($this->getCustomId() !== null) {
            $outcome = $executorUser->getGeneralizedAccess($this->team);

            if (!$outcome->isPositiveOutcome()) {
                return $outcome;
            }
        }
        if (!$bypassCustomId
            && $this->hasCustomId()) {
            return new IdealisticOfficeOutcome(
                false,
                "You cannot remove members from folder '" . $this->getName() . "' because it is reserved for other purposes."
            );
        }
        if ($user->getId() == $executorUser->getId()) {
            return new IdealisticOfficeOutcome(
                false,
                "You cannot remove yourself from the folder '" . $this->getName() . "'."
            );
        }
        $closed = $this->getClosedInformation();

        if ($closed !== null) {
            return new IdealisticOfficeOutcome(
                false,
                "Folder '" . $this->getName() . "' must be open to remove members. In this folder, it is closed since '"
                . $closed->getCreationDate() . " " . $closed->getCreationDateTimeZone()
                . "' by '" . $closed->getCreator()->getDisplayName() . "'."
            );
        }
        $participant = $this->getAddedParticipant($user, false);

        if ($participant === null) {
            return new IdealisticOfficeOutcome(
                false,
                "Member '" . $user->getDisplayName() . "' is not added to folder '" . $this->getName() . "'."
            );
        }
        if ($justification !== null
            && mb_strlen($justification) > IdealisticOfficeLimit::K8_LENGTH) {
            return new IdealisticOfficeOutcome(
                false,
                "Justification for removing a member from the folder '" . $this->getName() . "' is too long. Maximum length is "
                . IdealisticOfficeLimit::K8_LENGTH . " characters."
            );
        }
        $timezone = $executorUser->getTimezone(false);
        $date = IdealisticOfficeReader::getCurrentDate($timezone);

        if (IdealisticOfficeError::setSqlQuery(
            $this->team,
            $executorUser,
            IdealisticOfficeTable::FOLDER_PARTICIPANTS_TABLE,
            array(
                "deletion_date" => $date,
                "deletion_reason" => $justification,
                "deletion_time_zone" => $timezone->getTimeZone(),
                "deleted_by" => $executorUser->getId(),
            ),
            array(
                array("id", $participant->getId())
            ),
            null,
            1
        )) {
            $this->setLastModified($executorUser, $date, $timezone->getTimeZone());
            $this->notify(
                "Member '" . $user->getDisplayName() . "' has been removed from folder '" . $this->getName() . "' by '"
                . $executorUser->getDisplayName() . "'.",
                $executorUser
            );
            return new IdealisticOfficeOutcome(
                true,
                "Member '" . $user->getDisplayName() . "' has been removed from folder '" . $this->getName() . "'."
            );
        } else {
            return new IdealisticOfficeOutcome(
                false,
                "Failed to remove member '" . $user->getDisplayName() . "' from folder '" . $this->getName() . "'."
            );
        }
    }

    // Separator

    public function comment(
        IdealisticOfficePrompt $prompt,
        ?string                $comment,
        bool                   $notify = true
    ): IdealisticOfficeOutcome
    {
        if (!$prompt->getUser()->hasPermission(IdealisticOfficePermission::PARTICIPATE_IN_FOLDERS)->isPositiveOutcome()) {
            return new IdealisticOfficeOutcome(
                false,
                "You do not have permission to participate in folders."
            );
        }
        if (!$this->exists()) {
            return new IdealisticOfficeOutcome(false, "Folder '" . $this->getName() . "' does not exist.");
        }
        if ($this->getAddedParticipant($prompt->getUser(), false) === null) {
            return new IdealisticOfficeOutcome(
                false,
                "You cannot view or interact with the folder with ID '" . $this->id . "'."
            );
        }
        $closed = $this->getClosedInformation();

        if ($closed !== null) {
            return new IdealisticOfficeOutcome(
                false,
                "Folder '" . $this->getName() . "' must be open to add comments. In this folder, it is closed since '"
                . $closed->getCreationDate() . " " . $closed->getCreationDateTimeZone()
                . "' by '" . $closed->getCreator()->getDisplayName() . "'."
            );
        }
        $history = $prompt->getHistory();

        if ($comment !== null
            && strlen($comment) > IdealisticOfficeLimit::K15_LENGTH) {
            return new IdealisticOfficeOutcome(
                false,
                "Comment for folder '" . $this->getName() . "' is too long. Maximum length is "
                . IdealisticOfficeLimit::K15_LENGTH . " characters."
            );
        }
        $timezone = $prompt->getUser()->getTimezone(false);
        $date = IdealisticOfficeReader::getCurrentDate($timezone);

        if (!IdealisticOfficeError::sqlInsert(
            $this->team,
            $prompt->getUser(),
            IdealisticOfficeTable::FOLDER_COMMENTS_TABLE,
            array(
                "folder_id" => $this->id,
                "history_id" => $history->getId(),
                "account_id" => $prompt->getUser()->getId(),
                "comment" => empty($comment) ? null : $comment,
                "creation_date" => $date,
                "creation_time_zone" => $timezone->getTimeZone()
            )
        )) {
            return new IdealisticOfficeOutcome(
                false,
                "Failed to add comment to folder '" . $this->getName() . "'."
            );
        }
        $attachmentCount = $history->getAttachmentCount();
        $linkCount = $history->getLinkCount(false);
        $hasExtra = $attachmentCount > 0
            || $linkCount > 0;
        $this->setLastModified($prompt->getUser(), $date, $timezone->getTimeZone());

        if (empty($comment)) {
            $comment = " ";
        } else {
            $comment = " '" . $comment . "' ";
        }

        if ($notify
            && !empty($comment)) {
            $this->notify(
                $prompt->getUser()->getDisplayName() . "' commented" . $comment
                . "to folder '" . $this->getName() . "'"
                . ($hasExtra
                    ? " with "
                    . ($attachmentCount > 0 ? $attachmentCount . ($attachmentCount === 1 ? " attachment" : " attachments") : "")
                    . ($linkCount > 0 ? ($attachmentCount > 0 ? ", " : "") . $linkCount . ($linkCount === 1 ? " link" : " links") : "")
                    . "."
                    : "."),
                $prompt->getUser()
            );
        }
        return new IdealisticOfficeOutcome(
            true,
            "Your comment" . $comment . "to the folder '" . $this->getName() . "' has been successfully added"
            . ($hasExtra
                ? " with "
                . ($attachmentCount > 0 ? $attachmentCount . ($attachmentCount === 1 ? " attachment" : " attachments") : "")
                . ($linkCount > 0 ? ($attachmentCount > 0 ? ", " : "") . $linkCount . ($linkCount === 1 ? " link" : " links") : "")
                . "."
                : ".")
        );
    }

    public function removeComment(IdealisticOfficeUser $user, int $id, bool $exact = true): IdealisticOfficeOutcome
    {
        if (!$user->hasPermission(IdealisticOfficePermission::ADJUST_FOLDERS)->isPositiveOutcome()) {
            return new IdealisticOfficeOutcome(
                false,
                "You do not have permission to manage folder comments."
            );
        }
        if (!$this->exists()) {
            return new IdealisticOfficeOutcome(false, "Folder '" . $this->getName() . "' does not exist.");
        }
        if ($this->getAddedParticipant($user, false) === null) {
            return new IdealisticOfficeOutcome(
                false,
                "You cannot view or interact with the folder with ID '" . $this->id . "'."
            );
        }
        $closed = $this->getClosedInformation();

        if ($closed !== null) {
            return new IdealisticOfficeOutcome(
                false,
                "Folder '" . $this->getName() . "' must be open to remove comments. In this folder, it is closed since '"
                . $closed->getCreationDate() . " " . $closed->getCreationDateTimeZone()
                . "' by '" . $closed->getCreator()->getDisplayName() . "'."
            );
        }
        if ($id <= 0) {
            $id = 1;
        }
        if ($exact) {
            $comment = get_sql_query(
                IdealisticOfficeTable::FOLDER_COMMENTS_TABLE,
                array("id", "account_id", "comment"),
                array(
                    array("folder_id", $this->id),
                    array("deletion_date", null),
                    array("id", $id)
                ),
                null,
                1
            );
            $comment = $comment[0] ?? null;
        } else {
            $comment = get_sql_query(
                IdealisticOfficeTable::FOLDER_COMMENTS_TABLE,
                array("id", "account_id", "comment"),
                array(
                    array("folder_id", $this->id),
                    array("deletion_date", null)
                ),
                array(
                    "DESC",
                    "id"
                ),
                $id
            );
            $comment = $comment[$id - 1] ?? null;
        }

        if ($comment === null) {
            return new IdealisticOfficeOutcome(
                false,
                "Comment with ID '" . $id . "' does not exist in folder '" . $this->getName() . "'."
            );
        } else {
            $selfComment = $comment->account_id == $user->getId();

            if (!$selfComment
                && $this->getCustomId() === null) {
                return new IdealisticOfficeOutcome(
                    false,
                    "You cannot remove a comment of another member in this folder."
                );
            }
            $timezone = $user->getTimezone(false);
            $date = IdealisticOfficeReader::getCurrentDate($timezone);

            if (IdealisticOfficeError::setSqlQuery(
                $this->team,
                $user,
                IdealisticOfficeTable::FOLDER_COMMENTS_TABLE,
                array(
                    "deletion_date" => $date,
                    "deletion_time_zone" => $timezone->getTimeZone(),
                    "deleted_by" => $user->getId(),
                ),
                array(
                    array("id", $comment->id)
                ),
                null,
                1
            )) {
                $this->setLastModified($user, $date, $timezone->getTimeZone());
                $otherUser = $this->team->findUser(
                    new Account(
                        Account::IDEALISTIC_OFFICE_APPLICATION_ID,
                        $comment->account_id
                    )
                );

                if ($otherUser instanceof IdealisticOfficeUser) {
                    $otherUser = "'" . $otherUser->getDisplayName() . "'";
                } else {
                    $otherUser = "an unknown member with ID '" . $comment->account_id . "'";
                }
                $this->notify(
                    "Member '" . $user->getDisplayName() . "' removed "
                    . ($selfComment
                        ? "their own comment '" . $comment->comment . "'"
                        : "the comment '" . $comment->comment . "' by " . $otherUser)
                    . " from folder '" . $this->getName() . "'.",
                    $user
                );
                return new IdealisticOfficeOutcome(
                    true,
                    "Comment '" . $comment->comment . "' has been successfully removed from folder '" . $this->getName() . "'."
                );
            } else {
                return new IdealisticOfficeOutcome(
                    false,
                    "Failed to remove comment with ID '" . $id . "' from folder '" . $this->getName() . "'."
                );
            }
        }
    }

    public function getCommentIDs(
        int $limit = IdealisticOfficeLimit::FOLDER_MAX_COMMENTS
    ): array
    {
        $query = get_sql_query(
            IdealisticOfficeTable::FOLDER_COMMENTS_TABLE,
            array("id"),
            array(
                array("folder_id", $this->id),
                array("deletion_date", null)
            ),
            array(
                "DESC",
                "id"
            ),
            $limit
        );

        if (empty($query)) {
            return array();
        } else {
            $array = array();

            foreach ($query as $row) {
                $array[] = $row->id;
            }
            return $array;
        }
    }

    public function refreshLinks(IdealisticOfficeUser $user): IdealisticOfficeOutcome
    {
        if (!$user->hasPermission(IdealisticOfficePermission::ADJUST_FOLDERS)->isPositiveOutcome()) {
            return new IdealisticOfficeOutcome(
                false,
                "You do not have permission to manage folder links."
            );
        }
        if (!$this->exists()) {
            return new IdealisticOfficeOutcome(false, "Folder '" . $this->getName() . "' does not exist.");
        }
        if ($this->getAddedParticipant($user, false) === null) {
            return new IdealisticOfficeOutcome(
                false,
                "You cannot view or interact with the folder with ID '" . $this->id . "'."
            );
        }
        $closed = $this->getClosedInformation();

        if ($closed !== null) {
            return new IdealisticOfficeOutcome(
                false,
                "Folder '" . $this->getName() . "' must be open to remove comments. In this folder, it is closed since '"
                . $closed->getCreationDate() . " " . $closed->getCreationDateTimeZone()
                . "' by '" . $closed->getCreator()->getDisplayName() . "'."
            );
        }
        $comments = $this->getComments(IdealisticOfficeLimit::FOLDER_MAX_COMMENTS, false);

        if (empty($comments)) {
            return new IdealisticOfficeOutcome(true, "No comments to refresh links for folder '" . $this->getName() . "'.");
        }
        $links = array();

        foreach ($comments as $comment) {
            $links = array_merge($links, $comment->getPartialLinks());

            if (sizeof($links) > IdealisticOfficeLimit::MAX_EXTENSIONS_PER_OBJECT) {
                $links = array_slice($links, 0, IdealisticOfficeLimit::MAX_EXTENSIONS_PER_OBJECT);
                break;
            }
        }

        if (empty($links)) {
            return new IdealisticOfficeOutcome(true, "No links to refresh.");
        }
        $refreshed = 0;
        $refreshedRecently = 0;

        foreach ($links as $link) {
            if ($link->getRefreshDate() === null
                || IdealisticOfficeReader::getPastDate(IdealisticOfficeLimit::LINK_REFRESH_COOLDOWN, $link->getRefreshTimeZone()) > $link->getRefreshDate()) {
                if ($link->refresh($user)->isPositiveOutcome()) {
                    $refreshed++;
                }
            } else {
                $refreshedRecently++;
            }
        }
        return new IdealisticOfficeOutcome(
            true,
            "Refreshed " . $refreshed . " out of " . count($links) . " links."
            . ($refreshedRecently > 0 ? " " . $refreshedRecently . " links were refreshed recently and were skipped." : "")
        );
    }

    /** @return IdealisticOfficeFolderComment[] */
    public function getComments(
        int  $limit = IdealisticOfficeLimit::FOLDER_MAX_COMMENTS,
        bool $json = true
    ): array
    {
        $query = get_sql_query(
            IdealisticOfficeTable::FOLDER_COMMENTS_TABLE,
            array(
                "id",
                "history_id",
                "comment",
                "creation_date",
                "creation_time_zone",
                "account_id",
            ),
            array(
                array("folder_id", $this->id),
                array("deletion_date", null)
            ),
            array(
                "DESC",
                "id"
            ),
            $limit
        );

        if (empty($query)) {
            return array();
        } else {
            $historyIDs = array();

            foreach ($query as $row) {
                $historyIDs[] = $row->history_id;
            }
            $attachmentsData = IdealisticOfficeHistoryIndependent::getHistoryFromSubTable(
                IdealisticOfficeTable::HISTORY_USER_ATTACHMENTS_TABLE,
                IdealisticOfficeQuery::PARTIAL_ATTACHMENT,
                $historyIDs
            );
            $links = IdealisticOfficeHistoryIndependent::getHistoryFromSubTable(
                IdealisticOfficeTable::HISTORY_LINKS_TABLE,
                IdealisticOfficeQuery::HISTORY_LINK_PARTIAL,
                $historyIDs
            );
            $array = array();
            $history = new IdealisticOfficeHistory(
                $this->team,
                0,
                0,
                0,
                0,
                null,
                null,
                0,
                "",
                ""
            );

            foreach ($query as $row) {
                $history->setId($row->history_id);
                $history->setAccount($row->account_id);
                $array[] = new IdealisticOfficeFolderComment(
                    $row->id,
                    $row->account_id,
                    $this->team,
                    $row->creation_date,
                    $row->creation_time_zone,
                    $row->comment,
                    $history->getPartialAttachments(null, $attachmentsData),
                    $history->getPartialLinks($links, false),
                    $json
                );
            }
            return $array;
        }
    }

    // Separator

    public function getAddedParticipant(IdealisticOfficeUser $user, bool $json = true): ?IdealisticOfficeFolderParticipant
    {
        $query = get_sql_query(
            IdealisticOfficeTable::FOLDER_PARTICIPANTS_TABLE,
            IdealisticOfficeQuery::FOLDER_PARTICIPANT,
            array(
                array("folder_id", $this->id),
                array("account_id", $user->getId()),
                array("deletion_date", null),
            ),
            null,
            1
        );

        if (empty($query)) {
            return null;
        } else {
            $query = $query[0];
            return new IdealisticOfficeFolderParticipant(
                $query->id,
                $query->created_by,
                $this->team,
                $query->creation_reason,
                $query->creation_date,
                $query->creation_time_zone,
                $query->account_id,
                $json
            );
        }
    }

    public function canView(IdealisticOfficeUser $user): bool
    {
        return !$this->isArchived()
            && !$this->isExpired()
            && ($this->team->getId() === $user->getTeam()->getId()
                || $this->getAddedParticipant($user, false) !== null);
    }

    // Separator

    private function getLastOpenInformation(): ?IdealisticOfficeFolderModifier
    {
        $query = get_sql_query(
            IdealisticOfficeTable::FOLDER_STATUS_TABLE,
            IdealisticOfficeQuery::FOLDER_STATUS_SIMPLE,
            array(
                array("folder_id", $this->id),
                array("open", true),
            ),
            array(
                "DESC",
                "id"
            ),
            1
        );

        if (empty($query)) {
            if ($this->exists()) {
                return new IdealisticOfficeFolderModifier(
                    0,
                    $this->getFirstOpenerID(),
                    $this->team,
                    $this->creationReason,
                    $this->creationDate,
                    $this->creationTimeZone,
                    true,
                    false
                );
            } else {
                return null;
            }
        } else {
            $query = $query[0];
            return new IdealisticOfficeFolderModifier(
                $query->id,
                $query->account_id,
                $this->team,
                $query->creation_reason,
                $query->creation_date,
                $query->creation_time_zone,
                true,
                false
            );
        }
    }

    public function getOpenInformation(bool $json = true): ?IdealisticOfficeFolderModifier
    {
        $query = get_sql_query(
            IdealisticOfficeTable::FOLDER_STATUS_TABLE,
            IdealisticOfficeQuery::FOLDER_STATUS,
            array(
                array("folder_id", $this->id),
            ),
            array(
                "DESC",
                "id"
            ),
            1
        );

        if (empty($query)) {
            if ($this->exists()) {
                return new IdealisticOfficeFolderModifier(
                    0,
                    $this->getFirstOpenerID(),
                    $this->team,
                    $this->creationReason,
                    $this->creationDate,
                    $this->creationTimeZone,
                    true,
                    $json
                );
            } else {
                return null;
            }
        } else {
            $query = $query[0];

            if ($query->open === null) {
                return null;
            } else {
                return new IdealisticOfficeFolderModifier(
                    $query->id,
                    $query->account_id,
                    $this->team,
                    $query->creation_reason,
                    $query->creation_date,
                    $query->creation_time_zone,
                    true,
                    $json
                );
            }
        }
    }

    public function isReOpened(bool $json = true): bool
    {
        $open = $this->getOpenInformation($json);
        return $open !== null
            && $open->getCreationDate() !== $this->getCreationDate();
    }

    public function getClosedInformation(bool $json = true): ?IdealisticOfficeFolderModifier
    {
        $query = get_sql_query(
            IdealisticOfficeTable::FOLDER_STATUS_TABLE,
            IdealisticOfficeQuery::FOLDER_STATUS,
            array(
                array("folder_id", $this->id),
            ),
            array(
                "DESC",
                "id"
            ),
            1
        );

        if (empty($query)) {
            return null;
        } else {
            $query = $query[0];

            if ($query->open !== null) {
                return null;
            } else {
                return new IdealisticOfficeFolderModifier(
                    $query->id,
                    $query->account_id,
                    $this->team,
                    $query->creation_reason,
                    $query->creation_date,
                    $query->creation_time_zone,
                    false,
                    $json
                );
            }
        }
    }

    public function isOpen(): bool
    {
        return $this->getClosedInformation(false) === null;
    }

    private function isArchived(): bool
    {
        $close = $this->getClosedInformation(false);
        return $close !== null
            && IdealisticOfficeReader::getPastDate(
                IdealisticOfficeLimit::FOLDER_ARCHIVE_AFTER_DAYS . " days",
                $close->getCreationDateTimeZone()
            ) > $close->getCreationDate();
    }

    private function isExpired(): bool
    {
        return $this->expirationDate !== null
            && $this->expirationTimeZone !== null
            && IdealisticOfficeReader::getCurrentDate($this->expirationTimeZone)
            > $this->expirationDate;
    }

    /** @return IdealisticOfficeFolderModifier[] */
    public function getStatusHistory(bool $json = true): array
    {
        $query = get_sql_query(
            IdealisticOfficeTable::FOLDER_STATUS_TABLE,
            IdealisticOfficeQuery::FOLDER_STATUS,
            array(
                array("folder_id", $this->id),
            ),
            array(
                "DESC",
                "id"
            ),
            IdealisticOfficeLimit::MAX_MODIFICATION_HISTORY_ENTRIES
        );
        if (!empty($query)) {
            foreach ($query as $key => $row) {
                $query[$key] = new IdealisticOfficeFolderModifier(
                    $row->id,
                    $row->account_id,
                    $this->team,
                    $row->creation_reason,
                    $row->creation_date,
                    $row->creation_time_zone,
                    $row->open !== null,
                    $json
                );
            }
        }
        if ($this->exists()) {
            $query[] = new IdealisticOfficeFolderModifier(
                0,
                $this->getFirstOpenerID(),
                $this->team,
                $this->creationReason,
                $this->creationDate,
                $this->creationTimeZone,
                true,
                $json
            );
        }
        return $query;
    }

    public function exists(): bool
    {
        return $this->id !== 0
            && !empty(get_sql_query(
                IdealisticOfficeTable::FOLDERS_TABLE,
                array("id"),
                array(
                    array("id", $this->id)
                ),
                null,
                1
            ));
    }

    // Separator

    /** @return IdealisticOfficeFolderAttachedPortal[] */
    public function getAttachedPortals(bool $json = true, int $limit = IdealisticOfficeLimit::MAX_EXTENSIONS_PER_OBJECT, bool $local = false): array
    {
        $query = get_sql_query(
            IdealisticOfficeTable::FOLDER_ATTACHED_PORTALS_TABLE,
            IdealisticOfficeQuery::FOLDER_ATTACHED_PORTAL,
            array(
                array("folder_id", $this->id),
                array("deletion_date", null)
            ),
            null,
            $limit
        );

        if (!empty($query)) {
            foreach ($query as $key => $row) {
                $attached = new IdealisticOfficeFolderAttachedPortal(
                    $row->id,
                    $row->created_by,
                    $this->team,
                    $row->creation_date,
                    $row->creation_reason,
                    $row->creation_time_zone,
                    $row->portal_id,
                    $json
                );

                if (!$local
                    && $attached->getPortal(false) === null) {
                    unset($query[$key]);
                    IdealisticOfficeError::setSqlQuery(
                        $this->team,
                        null,
                        IdealisticOfficeTable::FOLDER_ATTACHED_PORTALS_TABLE,
                        array(
                            "deletion_date" => IdealisticOfficeReader::getCurrentDate(),
                            "deletion_time_zone" => IdealisticOfficeVariable::DEFAULT_TIME_ZONE
                        ),
                        array(
                            array("id", $attached->getId())
                        ),
                        null,
                        1
                    );
                    continue;
                }
                $query[$key] = $attached;
            }
        }
        return $query;
    }

    private function getAttachedPortal(IdealisticOfficePortal $portal): ?IdealisticOfficeFolderAttachedPortal
    {
        $query = get_sql_query(
            IdealisticOfficeTable::FOLDER_ATTACHED_PORTALS_TABLE,
            IdealisticOfficeQuery::FOLDER_ATTACHED_SPECIFIC,
            array(
                array("folder_id", $this->id),
                array("portal_id", $portal->getId()),
                array("deletion_date", null)
            ),
            null,
            1
        );

        if (empty($query)) {
            return null;
        } else {
            $row = $query[0];
            return new IdealisticOfficeFolderAttachedPortal(
                $row->id,
                $row->created_by,
                $this->team,
                $row->creation_date,
                $row->creation_reason,
                $row->creation_time_zone,
                $portal,
                false
            );
        }
    }

    public function attachPortal(IdealisticOfficeUser $user, IdealisticOfficePortal $portal, string $justification = null): IdealisticOfficeOutcome
    {
        if (!$user->hasPermission(IdealisticOfficePermission::ADJUST_FOLDERS)->isPositiveOutcome()) {
            return new IdealisticOfficeOutcome(
                false,
                "You do not have permission to manage folders."
            );
        }
        if ($this->getAddedParticipant($user) === null) {
            return new IdealisticOfficeOutcome(
                false,
                "You be in this folder to attach portals to it."
            );
        }
        $closed = $this->getClosedInformation();

        if ($closed !== null) {
            return new IdealisticOfficeOutcome(
                false,
                "Folder '" . $this->getName() . "' is already closed since '"
                . $closed->getCreationDate() . " " . $closed->getCreationDateTimeZone()
                . "' by '" . $closed->getCreator()->getDisplayName() . "'."
            );
        }
        $attached = $this->getAttachedPortal($portal);

        if ($attached !== null) {
            return new IdealisticOfficeOutcome(
                false,
                "Portal '" . $portal->getName() . "' is already attached to folder '" . $this->getName() . "' by '"
                . $attached->getCreator()->getDisplayName() . "'."
            );
        }
        $timezone = $user->getTimezone(false);
        $date = IdealisticOfficeReader::getCurrentDate($timezone);

        if (IdealisticOfficeError::sqlInsert(
            $this->team,
            $user,
            IdealisticOfficeTable::FOLDER_ATTACHED_PORTALS_TABLE,
            array(
                "folder_id" => $this->id,
                "portal_id" => $portal->getId(),
                "created_by" => $user->getId(),
                "creation_date" => $date,
                "creation_time_zone" => $timezone->getTimeZone(),
                "creation_reason" => $justification
            )
        )) {
            $this->setLastModified($user, $date, $timezone->getTimeZone());
            $this->notify(
                "Portal '" . $portal->getName() . "' has been attached to folder '" . $this->getName() . "' by '"
                . $user->getDisplayName() . "'"
                . ($justification === null ? "." : " with justification: " . $justification),
                $user
            );
            return new IdealisticOfficeOutcome(
                true,
                "Portal '" . $portal->getName() . "' has been attached to folder '" . $this->getName() . "'"
                . ($justification === null ? "." : " with justification: " . $justification)
            );
        } else {
            return new IdealisticOfficeOutcome(
                false,
                "Failed to attach portal '" . $portal->getName() . "' to folder '" . $this->getName() . "'."
            );
        }
    }

    public function detachPortal(IdealisticOfficeUser $user, IdealisticOfficePortal $portal, string $justification = null): IdealisticOfficeOutcome
    {
        if (!$user->hasPermission(IdealisticOfficePermission::ADJUST_FOLDERS)->isPositiveOutcome()) {
            return new IdealisticOfficeOutcome(
                false,
                "You do not have permission to manage folders."
            );
        }
        if ($this->getAddedParticipant($user) === null) {
            return new IdealisticOfficeOutcome(
                false,
                "You must be in folder to detach portals from it."
            );
        }
        $closed = $this->getClosedInformation();

        if ($closed !== null) {
            return new IdealisticOfficeOutcome(
                false,
                "Folder '" . $this->getName() . "' is already closed since '"
                . $closed->getCreationDate() . " " . $closed->getCreationDateTimeZone()
                . "' by '" . $closed->getCreator()->getDisplayName() . "'."
            );
        }
        $attached = $this->getAttachedPortal($portal);

        if ($attached === null) {
            return new IdealisticOfficeOutcome(
                false,
                "Portal '" . $portal->getName() . "' is not attached to folder '" . $this->getName() . "'."
            );
        }
        $timezone = $user->getTimezone(false);
        $date = IdealisticOfficeReader::getCurrentDate($timezone);

        if (IdealisticOfficeError::setSqlQuery(
            $this->team,
            $user,
            IdealisticOfficeTable::FOLDER_ATTACHED_PORTALS_TABLE,
            array(
                "deletion_date" => $date,
                "deletion_reason" => $justification,
                "deletion_time_zone" => $timezone->getTimeZone(),
                "deleted_by" => $user->getId()
            ),
            array(
                array("id", $attached->getId())
            ),
            null,
            1
        )) {
            $this->setLastModified($user, $date, $timezone->getTimeZone());
            $this->notify(
                "Portal '" . $portal->getName() . "' has been detached from folder '" . $this->getName() . "' by '"
                . $user->getDisplayName() . "'"
                . ($justification === null ? "." : " with justification: " . $justification),
                $user
            );
            return new IdealisticOfficeOutcome(
                true,
                "Portal '" . $portal->getName() . "' has been detached from folder '" . $this->getName() . "'"
                . ($justification === null ? "." : " with justification: " . $justification)
            );
        } else {
            return new IdealisticOfficeOutcome(
                false,
                "Failed to detach portal '" . $portal->getName() . "' from folder '" . $this->getName() . "'."
            );
        }
    }

    // Separator

    public function notify(string $notification, ?IdealisticOfficeUser $except = null): void
    {
        $participants = $this->getParticipants();

        if (!empty($participants)) {
            foreach ($participants as $participant) {
                if ($except !== null
                    && $participant->getAddedID() == $except->getId()) {
                    continue;
                }
                $participant->getAddedAccountUser()?->addNotification(
                    $except,
                    $notification
                );
            }
        }
    }

    private function setLastModified(
        IdealisticOfficeUser $user,
        string               $date,
        string               $timeZone
    ): void
    {
        if (IdealisticOfficeError::setSqlQuery(
            $this->team,
            $user,
            IdealisticOfficeTable::FOLDERS_TABLE,
            array(
                "last_modified_date" => $date,
                "last_modified_time_zone" => $timeZone,
                "last_modified_by" => $user->getId()
            ),
            array(
                array("id", $this->id)
            ),
            null,
            1
        )) {
            $user->setSelectedFolder($this);
        }
    }

}
