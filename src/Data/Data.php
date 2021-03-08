<?php

namespace BugbirdCo\Cabinet\Data;

use BugbirdCo\Cabinet\Deferrer\DeferresAccess;
use BugbirdCo\Cabinet\Model;
use BugbirdCo\Cabinet\Operations;
use JsonSerializable;

/**
 * Class Data
 *
 * This is an abstraction of the idea of data.
 * This class handles holding data, applying casting constraints, and serialising.
 *
 * @package BugbirdCo\Cabinet
 */
class Data implements JsonSerializable
{
    use Operations;

    /** @var array */
    private $original;
    /** @var array */
    private $schema;
    /** @var array */
    private $data;
    /** @var Model */
    private $model;

    public function __construct(array $data = [])
    {
        $this->original = $data;
    }

    public function __get($name)
    {
        return $this->data[$name] instanceof DeferresAccess
            ? $this->data[$name]->resolve()
            : $this->data[$name];
    }

    public function __isset($name)
    {
        return isset($this->data[$name]);
    }

    /**
     * This function applies the constraints and casting information
     * provided in the schema attribute in the format of:
     * [$dataKey => $type]
     *
     * @param array $schema
     * @param Model $model
     * @return $this
     */
    public function constrain(array $schema, Model $model)
    {
        $this->model = $model;
        $this->schema = $schema;


        $modelName = get_class($model);
        /**
         * @var string $key
         * @var string $type
         */
        foreach ($schema as $key => $type) {
            if (static::isPlural($type))
                $this->data[$key] = static::pluralCast($this->original[$key] ?? [], $type, $modelName, $this->model);
            else
                $this->data[$key] = static::singularCast($this->original[$key] ?? null, $type, $modelName, $this->model);
        }

        return $this;
    }

    public function raw(array $including = null, array $excluding = [])
    {
        return array_diff_key(
            $including ? array_intersect_key($this->data, array_flip($including)) : $this->data,
            array_flip($excluding));
    }

    /**
     * Specify data which should be serialized to JSON
     * @link https://php.net/manual/en/jsonserializable.jsonserialize.php
     * @return mixed data which can be serialized by <b>json_encode</b>,
     * which is a value of any type other than a resource.
     * @since 5.4.0
     */
    public function jsonSerialize()
    {
        return $this->raw(
            empty($this->model->include) ? null : $this->model->include,
            $this->model->exclude
        );
    }

    public function isDeferred($name)
    {
        return $this->data[$name] instanceof DeferresAccess;
    }

    protected function changed($comp)
    {
        return array_udiff(ksort($this->data), ksort($comp), function ($a, $b) {
            if ($a instanceof DeferresAccess && !($b instanceof DeferresAccess)) {
                return 1;
            } else if ($b instanceof DeferresAccess && !($a instanceof DeferresAccess)) {
                return 0;
            } else if ($a == $b) {
                return 0;
            } else {
                return $a > $b ? 1 : -1;
            }
        });
    }
}
