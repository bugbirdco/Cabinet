<?php

namespace BugbirdCo\Cabinet;

function cab_escape_array($array)
{
    $associative = sizeof(array_filter($array, function ($key) {
            return !is_numeric($key);
        }, ARRAY_FILTER_USE_KEY)) > 0;

    return $associative ? [$array] : $array;
}