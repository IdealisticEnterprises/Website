<?php

class IdealisticOfficePortalStatistics implements PhpAsyncSerializable
{

    private int $totalMessages, $totalSessions, $messagesPerDay;

    public function __construct(
        int $totalMessages,
        int $totalSessions,
        int $messagesPerDay
    )
    {
        $this->totalMessages = $totalMessages;
        $this->totalSessions = $totalSessions;
        $this->messagesPerDay = $messagesPerDay;
    }

    public function getTotalMessages(): int
    {
        return $this->totalMessages;
    }

    public function getTotalSessions(): int
    {
        return $this->totalSessions;
    }

    public function getMessagesPerDay(): int
    {
        return $this->messagesPerDay;
    }

    public function getMessagesPerSession(): int
    {
        return $this->totalSessions > 0
            ? $this->totalMessages / $this->totalSessions
            : 0;
    }

}