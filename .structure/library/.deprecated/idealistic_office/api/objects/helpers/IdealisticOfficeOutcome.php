<?php

namespace idealistic_office\api\objects\helpers;

use AbstractMethodReply;
use idealistic_office\api\handlers\IdealisticOfficeStrings;
use idealistic_office\api\objects\IdealisticOfficePrompt;
use idealistic_office\api\objects\IdealisticOfficeRole;
use idealistic_office\api\objects\IdealisticOfficeUser;
use IdealisticOfficeGeneralMessage;
use React\Promise\PromiseInterface;

class IdealisticOfficeOutcome extends AbstractMethodReply implements JsonSerializable
{

    private string|array $message;
    private bool $save;
    private AbstractIdealisticOfficeTeam|IdealisticOfficeUser|IdealisticOfficeRole|null|false $referenceToTranslateTo;

    public function __construct(
        ?bool                                                                             $success,
        string|array|null                                                                 $message,
        bool                                                                              $save = true,
        AbstractIdealisticOfficeTeam|IdealisticOfficeUser|IdealisticOfficeRole|null|false $referenceToTranslateTo = null
    )
    {
        parent::__construct($success);

        if ($message === null) {
            $this->setOutcome(false);
            $this->message = IdealisticOfficeGeneralMessage::OUTCOME_FAILED_PROCESSING;
        } else {
            $this->message = $message;
        }
        $this->save = $save;
        $this->referenceToTranslateTo = $referenceToTranslateTo;
    }

    public function getRawMessage(): string|array
    {
        return $this->message;
    }

    public function setRawMessage(string|array $message): void
    {
        $this->message = $message;
    }

    public function setReferenceToTranslateTo(
        AbstractIdealisticOfficeTeam|IdealisticOfficeUser|IdealisticOfficeRole|null|false $referenceToTranslateTo
    ): void
    {
        $this->referenceToTranslateTo = $referenceToTranslateTo;
    }

    public function getTranslatedMessage(
        AbstractIdealisticOfficeTeam|IdealisticOfficeUser|IdealisticOfficeRole|null $referenceToTranslateTo = null,
        ?IdealisticOfficePrompt                                                     $prompt = null
    ): PromiseInterface|string
    {
        if ($this->referenceToTranslateTo === false) {
            $referenceToTranslateTo = null;
        } else {
            $referenceToTranslateTo = $referenceToTranslateTo ?? $this->referenceToTranslateTo;
        }
        return IdealisticOfficeStrings::translateMessage(
            $this->message,
            $referenceToTranslateTo,
            $this->save,
            $prompt
        );
    }

    public final function jsonSerialize(): array|object
    {
        return array(
            "success" => $this->isPositiveOutcome(),
            "message" => $this->getRawMessage()
        );
    }

}

