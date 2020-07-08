<?php

namespace BugbirdCo\Cabinet\Data;

use BugbirdCo\Cabinet\Deferrer\Deferrer;
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

    /** @var callable[] */
    public static $constructors = [];

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
        return $this->data[$name] instanceof Deferrer
            ? $this->data[$name]->resolve()
            : $this->data[$name];
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
                $this->data[$key] = static::pluralCast($this->original[$key] ?? [], $type, $modelName);
            else
                $this->data[$key] = static::singularCast($this->original[$key] ?? null, $type, $modelName);
        }

        return $this;
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
        return $this->data;
    }
}