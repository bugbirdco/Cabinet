<?php

namespace BugbirdCo\Cabinet\Deferrer;

/**
 * Model provider methods are static and are named in the format
 * "consume[{Namespace}]{Name}". They accept one argument, the data
 * object, and are expected to return a newly created model.
 *
 * @package BugbirdCo\Cabinet\Accessor
 */
class ClassDeferrer extends AutoDeferrer
{
    protected function make($name, $type, $items)
    {
        $name = "provide{$name}";
        return method_exists($type, $name) ? $type::$name($items, $this->model) : null;
    }

    protected function fallback($type, $items)
    {
        return new $type ($items);
    }
}
