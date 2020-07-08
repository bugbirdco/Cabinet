<?php

namespace BugbirdCo\Cabinet\Data;

use BugbirdCo\Cabinet\Data;

class JsonData extends Data
{
    public function __construct(string $data)
    {
        parent::__construct(json_decode($data, true));
    }
}