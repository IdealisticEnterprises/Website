<?php

class IdealisticOfficeRole extends IdealisticOfficeJSON implements IdealisticOfficeParticipant
{

    private AbstractIdealisticOfficeTeam $team;
    private int|string $id;
    private bool $deleted;

    public function __construct(
        AbstractIdealisticOfficeTeam $team,
        int                          $id,
        bool                         $json = true,
        bool                         $deleted = false
    )
    {
        parent::__construct();
        $this->team = $team;
        $this->id = $id;
        $this->deleted = $deleted;

        if ($json) {
            self::setJSON(
                array(
                    "id" => $this->id,
                    "name" => $this->getName(),
                    "creation_reason" => $this->getCreationReason(),
                    "creation_date" => $this->getCreationDate(),
                    "creation_time_zone" => $this->getCreationDateTimeZone()
                )
            );
        }
    }

    public function getTeam(): AbstractIdealisticOfficeTeam
    {
        return $this->team;
    }

    public function getEvolvedTeam(): IdealisticOfficeTeam
    {
        return $this->team;
    }

    public function getRawObject(): ?object
    {
        return $this->team->findRawRole($this->id, $this->deleted);
    }

    public function getId(): int|string
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->getRawObject()?->title;
    }

    public function getLevel(): int
    {
        $raw = $this->getRawObject();

        if ($raw === null) {
            return 0;
        } else {
            return $this->team->getPartialManager()?->getRolePosition($raw) ?? 0;
        }
    }

    public function getCreationReason(): ?string
    {
        return $this->getRawObject()?->creation_reason;
    }

    public function getCreationDate(): ?string
    {
        return $this->getRawObject()?->creation_date;
    }

    public function getCreationDateTimeZone(): string
    {
        return IdealisticOfficeVariable::DEFAULT_TIME_ZONE;
    }

    public function getPermissions(): array
    {
        $raw = $this->getRawObject();

        if ($raw === null) {
            return array();
        }
        $permissions = $this->team->getPartialManager()?->getRolePermissions($raw);

        if (!empty($permissions)) {
            foreach ($permissions as $key => $permission) {
                $permissions[$key] = $permission->definition->name;
            }
        }
        return $permissions;
    }

    // Separator

    public function getTimezone(bool $json = true): IdealisticOfficeTimezone
    {
        return $this->team->getTimezone($json);
    }

    // Separator

    public function getTimeAccess(bool $json = true): IdealisticOfficeTimeAccess
    {
        if (!$this->team->hasEstablishedAccess()) {
            return $this->team->getTimeAccess($json);
        }
        $query = get_sql_query(
            IdealisticOfficeTable::ROLE_TIME_ACCESS_TABLE,
            IdealisticOfficeQuery::TIME_ACCESS,
            array(
                array("team_id", $this->team->getId()),
                array("role_id", $this->id)
            ),
            array(
                "DESC",
                "id"
            ),
            1
        );
        if (empty($query)) {
            return $this->team->getTimeAccess($json);
        } else {
            return new IdealisticOfficeTimeAccess(
                $query[0]->id,
                $query[0]->created_by,
                $this->team,
                $query[0]->creation_reason,
                $query[0]->creation_date,
                (int)$query[0]->start_hour,
                (int)$query[0]->start_minutes,
                (int)$query[0]->end_hour,
                (int)$query[0]->end_minutes,
                $query[0]->creation_time_zone,
                $json
            );
        }
    }

    // Separator

    public function getWeekAccess(bool $json = true): IdealisticOfficeWeeklyAccess
    {
        if (!$this->team->hasEstablishedAccess()) {
            return $this->team->getWeekAccess($json);
        }
        $query = get_sql_query(
            IdealisticOfficeTable::ROLE_WEEK_ACCESS_TABLE,
            IdealisticOfficeQuery::WEEK_ACCESS,
            array(
                array("team_id", $this->team->getId()),
                array("role_id", $this->id)
            ),
            array(
                "DESC",
                "id"
            ),
            1
        );
        if (empty($query)) {
            return $this->team->getWeekAccess($json);
        } else {
            return new IdealisticOfficeWeeklyAccess(
                $query[0]->id,
                $query[0]->created_by,
                $this->team,
                $query[0]->creation_reason,
                $query[0]->creation_date,
                $query[0]->creation_time_zone,
                $query[0]->monday_access !== null,
                $query[0]->tuesday_access !== null,
                $query[0]->wednesday_access !== null,
                $query[0]->thursday_access !== null,
                $query[0]->friday_access !== null,
                $query[0]->saturday_access !== null,
                $query[0]->sunday_access !== null,
                $json
            );
        }
    }

    // Separator

    /** @return IdealisticOfficeUser[] */
    public function getUsers(
        bool $json = true
    ): array
    {
        $users = $this->team->getPartialManager()?->getRoleMembers();

        if (!empty($users)) {
            $array = array();

            foreach ($users as $user) {
                $accountID = (int)$user;
                $user = $this->team->findUser(
                    new Account(
                        Account::IDEALISTIC_OFFICE_APPLICATION_ID,
                        $user
                    ),
                    $json
                );

                if ($user instanceof IdealisticOfficeUser) {
                    $array[$accountID] = $user;
                }
            }
            return $array;
        }
        return array();
    }

    // Separator

    public function getLanguageSelection(bool $json = true): IdealisticOfficeLanguageSelection
    {
        return $this->team->getLanguageSelection($json);
    }


    public final function hasLanguageSelection(): bool
    {
        return $this->team->hasLanguageSelection();
    }

    // Separator

    public function addNotification(
        ?IdealisticOfficeUser $executor,
        string|array|null     $message,
        ?string               $attachmentName = null,
        ?string               $attachmentContent = null,
        ?string               $attachmentURL = null,
        ?string               $attachmentFormat = null,
        bool                  $base64 = false,
        bool                  $isImage = false,
        bool                  $isVideo = false,
        bool                  $isAudio = false,
        bool                  $translate = true,
        bool                  $deleteOnDelivery = false
    ): bool
    {
        $result = true;
        $users = $this->getUsers(false);

        if (!empty($users)) {
            foreach ($users as $user) {
                if ($user->getId() == $executor?->getId()) {
                    continue;
                }
                if (!$user->addNotification(
                    $executor,
                    $message,
                    $attachmentName,
                    $attachmentContent,
                    $attachmentURL,
                    $attachmentFormat,
                    $base64,
                    $isImage,
                    $isVideo,
                    $isAudio,
                    $translate,
                    $deleteOnDelivery
                )) {
                    $result = false;
                }
            }
        }
        return $result;
    }

    // Separator

    public function hasPermission(int|string $permission): IdealisticOfficeOutcome
    {
        $raw = $this->getRawObject();

        if ($raw === null) {
            return new IdealisticOfficeOutcome(false, "Role not found");
        }
        $outcome = $this->team->getPartialManager()?->getRolePermission(
            $raw,
            $permission
        );
        return new IdealisticOfficeOutcome($outcome->isPositiveOutcome(), $outcome->getMessage());
    }

}
