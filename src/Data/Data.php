<?php

namespace BugbirdCo\Cabinet\Data;

use BugbirdCo\Cabinet\Model\Model;
use Spatie\Regex\RegexFailed;

/**
 * Class Data
 *
 * This is an abstraction of the idea of data.
 * This class handles holding data, applying casting constraints, and serialising.
 *
 * @package BugbirdCo\Candy
 */
class Data implements \JsonSerializable
{
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
        return $this->data[$name];
    }

    /**
     * This function applies the constraints and casting information
     * provided in the schema attribute in the format of:
     * [$dataKey => $type]
     *
     * @param Model $model
     * @return $this
     * @throws \ReflectionException
     * @throws RegexFailed
     */
    public function constrain(array $schema, Model $model)
    {
        $this->model = $model;
        $this->schema = $schema;
        /**
         * @var string $key
         * @var string $type
         */
        foreach ($schema as $key => $type) {
            if ($this->isPlural($type))
                $this->data[$key] = $this->pluralCast($this->original[$key] ?? [], $type);
            else
                $this->data[$key] = $this->singularCast($this->original[$key] ?? null, $type);
        }

        return $this;
    }

    /**
     * Test if the specified type is actually plural (an array of a
     * specified type).
     *
     * @param string $type
     * @return bool
     */
    private function isPlural(string $type)
    {
        return substr($type, -2, 2) == '[]';
    }

    /**
     * Inverse of isPlural().
     *
     * @param string $type
     * @return bool
     */
    private function isSingular(string $type)
    {
        return !$this->isPlural($type);
    }

    /**
     * Turns a plural type into a singular type.
     *
     * @param string $type
     * @return Model|string
     */
    private function singularise(string $type)
    {
        return $this->isSingular($type) ? $type : substr($type, 0, -2);
    }

    /**
     * Inverse of singularise().
     *
     * @param string $type
     * @return string
     */
    private function pluralise(string $type)
    {
        return $this->isPlural($type) ? $type : ($type . '[]');
    }

    /**
     * Iterates over a plural attribute and casts it's children into
     * the plural type.
     *
     * @param iterable $items
     * @param string $arrayType
     * @return array
     * @throws \ReflectionException
     * @throws RegexFailed
     */
    private function pluralCast(iterable $items, string $arrayType)
    {
        // If there is a main consumer or a specific consumer for the
        // target model, for the parent model, then we want to pass
        // it the raw data, rather than breaking down the model
        // and consuming it
        if ($this->isModelable($arrayType)) {
            $model = $this->singularise($arrayType);

            if(method_exists(static::class, 'source') || !is_null($model::specificScope($this->model))) {
                return $model::consume($items, $this->model)->plural();
            }
        }

        $type = $this->singularise($arrayType);
        return array_map(function ($item) use ($type) {
            return $this->singularCast($item, $type);
        }, $this->cast($items, 'array', []));
    }

    /**
     * Applies the casting constraints to the applied item.
     *
     * @param mixed $item
     * @param string $type
     * @return mixed|null
     * @throws \ReflectionException
     * @throws RegexFailed
     */
    private function singularCast($item, string $type)
    {
        if (is_null($item)) {
            return $this->fake($item, $type);
        } elseif ($this->isModelable($type)) {
            return $this->modelise($item, $type);
        } else {
            return $this->cast($item, $type);
        }
    }

    /**
     * Mocks out an entry if the schema defined the attribute as
     * existing, but one was not supplied.
     *
     * @param mixed $item
     * @param string $type
     * @return mixed
     */
    private function fake($item, string $type)
    {
        return $this->isModelable($type) ? new $type(new static()) : $item;
    }

    /**
     * Checks if the type should be a class.
     *
     * @param string $type
     * @return bool
     */
    private function isObjectable(string $type)
    {
        return $type != '' && $type[0] == '\\';
    }

    /**
     * Checks if the type is not an inbuilt, exists, an is a model.
     *
     * @param string $type
     * @return bool
     */
    private function isModelable(string $type)
    {
        $type = $this->singularise($type);
        return $this->isObjectable($type) && is_a($type, Model::class, true);
    }

    /**
     * Creates a model out of the supplied array, the attribute is not
     * found, or can not be cast into an array (if it is not already
     * one) then it will use an empty array to produce a blank model.
     *
     * @param mixed $item
     * @param Model $type
     * @return mixed
     * @throws \ReflectionException
     * @throws RegexFailed
     */
    private function modelise($item, string $type)
    {
        return $type::consume($this->cast($item, 'array', []), $this->model)->singular();
    }

    /**
     * Casts the specified data into the specified type.
     * If casting fails, it will return a default value, which
     * defaults to null.
     *
     * @param mixed $item
     * @param string $type
     * @param mixed|array|null $default
     * @return mixed|array|null
     */
    private function cast($item, string $type, $default = null)
    {
        if ($this->isObjectable($type)) {
            $type = 'object';
        }
        return settype($item, $type) ? $item : $default;
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