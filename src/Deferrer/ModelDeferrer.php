<?php

namespace BugbirdCo\Cabinet\Deferrer;

use App\Models\Cabinet\Action\RemainingChanges;
use BugbirdCo\Cabinet\Data\Data;

/**
 * Model consumer methods names are static and are named in the format
 * "consume[{Namespace}]{Name}". They accept one argument, the data
 * object, and are expected to return a newly created model.
 *
 * @package BugbirdCo\Cabinet\Accessor
 */
class ModelDeferrer extends AutoDeferrer
{
    protected function make($name, $type, $items)
    {
        $name = "consume{$name}";
        return method_exists($type, $name) ? $type::$name($items, $this->model) : null;
    }

    protected function fallback($type, $items)
    {
        if (is_a($items, $type))
            return $items;
        return new $type (new Data($items));
    }
}
