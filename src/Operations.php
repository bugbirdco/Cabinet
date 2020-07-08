<?php

namespace BugbirdCo\Cabinet;

use BugbirdCo\Cabinet\Deferrer\ClassDeferrer;
use BugbirdCo\Cabinet\Deferrer\Deferrer;
use BugbirdCo\Cabinet\Deferrer\ModelDeferrer;

trait Operations
{
    /**
     * Test if the specified type is actually plural (an array of a
     * specified type).
     *
     * @param string $type
     * @return bool
     */
    protected static function isPlural(string $type)
    {
        return substr($type, -2, 2) == '[]';
    }

    /**
     * Inverse of isPlural().
     *
     * @param string $type
     * @return bool
     */
    protected static function isSingular(string $type)
    {
        return !static::isPlural($type);
    }

    /**
     * Turns a plural type into a singular type.
     *
     * @param string $type
     * @return Model|string
     */
    protected static function singularise(string $type)
    {
        return static::isSingular($type) ? $type : substr($type, 0, -2);
    }

    /**
     * Inverse of singularise().
     *
     * @param string $type
     * @return string
     */
    protected static function pluralise(string $type)
    {
        return static::isPlural($type) ? $type : ($type . '[]');
    }

    /**
     * Iterates over a plural attribute and casts it's children into
     * the plural type.
     *
     * @param array $items
     * @param string $arrayType
     * @param string $parent
     * @return array|mixed
     */
    protected static function pluralCast($items, $arrayType, $parent)
    {
        // If there is a main consumer or a specific consumer for the
        // target model, for the parent model, then we want to pass
        // it the raw data, rather than breaking down the model
        // and consuming it
        if (static::isModelable($arrayType)) {
            return static::modelise($items, $arrayType, $parent);
        }

        $type = static::singularise($arrayType);
        return array_map(function ($item) use ($type, $parent) {
            return static::singularCast($item, $type, $parent);
        }, static::cast($items, 'array', []));
    }

    /**
     * Applies the casting constraints to the applied item.
     *
     * @param mixed|Model $item
     * @param string $type
     * @param string $parent
     * @return mixed|null
     */
    protected static function singularCast($item, string $type, $parent)
    {
        if (is_null($item)) {
            return static::fake($type, $parent);
        } elseif (static::isModelable($type)) {
            return static::modelise($item, $type, $parent);
        } elseif (static::isObjectable($type)) {
            return static::objectify($item, $type, $parent);
        } else {
            return static::cast($item, $type);
        }
    }

    /**
     * Mocks out an entry if the schema defined the attribute as
     * existing, but one was not supplied.
     *
     * @param string|Model $type
     * @param string $parent
     * @return mixed
     */
    protected static function fake(string $type, $parent)
    {
        if (self::isModelable($type)) {
            return static::modelise([], $type, $parent);
        } elseif (self::isObjectable($type)) {
            return static::objectify(null, $type, $parent);
        }
        return static::isModelable($type) ?: static::cast(null, $type, []);
    }

    /**
     * Checks if the type should be a class.
     *
     * @param string $type
     * @return bool
     */
    protected static function isObjectable(string $type)
    {
        return $type != '' && $type[0] == '\\';
    }

    /**
     * Checks if the type is not an inbuilt, exists, an is a model.
     *
     * @param string $type
     * @return bool
     */
    protected static function isModelable(string $type)
    {
        $type = static::singularise($type);
        return static::isObjectable($type) && is_a($type, Model::class, true);
    }

    /**
     * Creates a model out of the supplied array, the attribute is not
     * found, or can not be cast into an array (if it is not already
     * one) then it will use an empty array to produce a blank model.
     *
     * @param array $item
     * @param string $type
     * @param string $parent
     * @return Model|Deferrer
     */
    protected static function modelise($item, string $type, string $parent)
    {
        return new ModelDeferrer($item, $type, $parent);
    }

    /**
     * Turns the item into the specified object via the data constructors
     *
     * @param array $item
     * @param string $type
     * @param string $parent
     * @return mixed
     */
    protected static function objectify($item, string $type, string $parent)
    {
        return new ClassDeferrer($item, $type, $parent);
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
    protected static function cast($item, string $type, $default = null)
    {
        if (static::isObjectable($type)) {
            $type = 'object';
        }
        return settype($item, $type) ? $item : $default;
    }
}