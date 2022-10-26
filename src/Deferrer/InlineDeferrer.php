<?php

namespace BugbirdCo\Cabinet\Deferrer;

use BugbirdCo\Cabinet\Operations;
use BugbirdCo\Cabinet\Model;

class InlineDeferrer implements DeferresAccess, \JsonSerializable
{
    use Operations;

    private $accessor;
    private $type;
    private $parent;
    private $model;
    private $value = null;

    public static function wrap(callable|self $deferrer)
    {
        if ($deferrer instanceof static) return $deferrer;
        return new static($deferrer);
    }

    public function __construct(callable $deferrer)
    {
        $this->accessor = $deferrer;
    }

    public function resolve()
    {
        return $this->value = $this->value ?? $this->resolveAndConstrain();
    }

    public function constraints($type, $parent, Model $model = null)
    {
        $this->type = $type;
        $this->parent = $parent;
        $this->model = $model;
        return $this;
    }

    private function resolveAndConstrain()
    {
        $data = ($this->accessor)($this->model);
        if (static::isPlural($this->type))
            return static::pluralCast($data, $this->type, $this->parent, $this->model);
        else
            return static::singularCast($data, $this->type, $this->parent, $this->model);
    }

    public function jsonSerialize()
    {
        return $this->resolve();
    }
}
