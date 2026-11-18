<?php

namespace idealistic_office\api\objects\helpers\history;
use PhpAsyncSerializable;

class IdealisticOfficeHistoryWebData implements PhpAsyncSerializable
{

    private string $query, $results;

    public function __construct(
        string $query,
        string $results
    )
    {
        $this->query = $query;
        $this->results = $results;
    }

    public function getQuery(): string
    {
        return $this->query;
    }

    public function getResults(): string
    {
        return $this->results;
    }

}
