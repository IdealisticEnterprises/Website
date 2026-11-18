<?php

namespace idealistic_office\api\objects\helpers\history;
use IdealisticOfficePromptTask;
use PhpAsyncSerializable;

class IdealisticOfficeHistoryReply implements PhpAsyncSerializable
{

    private ?string $taskId, $reply;
    private int|string $id;
    private ?string $positions;

    public function __construct(
        int|string $id,
        ?string    $reply,
        ?string    $taskId,
        ?string    $positions
    )
    {
        $this->id = $id;
        $this->reply = $reply;
        $this->taskId = $taskId;
        $this->positions = $positions;
    }

    public function getId(): int|string
    {
        return $this->id;
    }

    public function getAnswer(): ?string
    {
        return $this->reply;
    }

    public function getTaskId(): string
    {
        return $this->taskId ?? IdealisticOfficePromptTask::ACCESS_CHECK;
    }

    public function getPositions(): ?string
    {
        return $this->positions;
    }

    public function getPositionsArray(): ?array
    {
        if ($this->positions === null) {
            return null;
        }
        $result = unpack("f*", $this->positions);

        if (is_array($result)) {
            return array_values($result);
        }
        return null;
    }

}
