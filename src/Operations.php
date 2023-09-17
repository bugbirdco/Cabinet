<?php

namespace BugbirdCo\Cabinet;

use AurignyLabs\Klint\Bridges\Avantik\Booking\Seat;
use BugbirdCo\Cabinet\Data\Data;
use BugbirdCo\Cabinet\Deferrer\ClassDeferrer;
use BugbirdCo\Cabinet\Deferrer\DeferresAccess;
use BugbirdCo\Cabinet\Deferrer\InlineDeferrer;
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
     * @param Model $parent
     * @return array|mixed
     */
    protected static function pluralCast($items, $arrayType, $parent, $model = null)
    {
        $arrayType = static::escapeType($arrayType);
        if (static::isInlineDeferrer($items, $arrayType)) {
            /** @var InlineDeferrer $items */
            return InlineDeferrer::wrap($items)->constraints($arrayType, $parent, $model);
        } elseif (static::isDeferrer($items)) {
            return $items;
        }

        // If there is a main consumer or a specific consumer for the
        // target model, for the parent model, then we want to pass
        // it the raw data, rather than breaking down the model
        // and consuming it
        if (static::isModelable($arrayType)) {
            return static::modelise($items, $arrayType, $parent, $model);
        }

        $type = static::singularise($arrayType);
        return array_map(function ($item) use ($type, $parent, $model) {
            return static::singularCast($item, $type, $parent, $model);
        }, static::cast($items, 'array', []));
    }

    /**
     * Applies the casting constraints to the applied item.
     *
     * @param mixed|Model $item
     * @param string $type
     * @param string $parent
     * @param Model $model
     * @return mixed|null
     */
    protected static function singularCast($item, string $type, $parent, $model = null)
    {
        if (is_null($item)) {
            $type = static::canBeNull($type) ? 'null' : $type;
            return static::fake($type, $parent, $model);
        }

        $type = static::escapeType($type);
        if (static::isInlineDeferrer($item, $type)) {
            return InlineDeferrer::wrap($item)->constraints($type, $parent, $model);
        } elseif (static::isDeferrer($item)) {
            return $item;
        } elseif (static::isModelable($type)) {
            return static::modelise($item, $type, $parent, $model);
        } elseif (static::isObjectable($type)) {
            return static::objectify($item, $type, $parent, $model);
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
     * @param Model $model
     * @return mixed
     */
    protected static function fake(string $type, $parent, $model)
    {
        if ($type === 'null') {
            return static::cast(null, 'null', []);
        } elseif (self::isModelable($type)) {
            return static::modelise([], $type, $parent, $model);
        } elseif (self::isObjectable($type)) {
            return static::objectify(null, $type, $parent, $model);
        }
        return static::cast(null, $type, []);
    }

    protected static function isDeferrer($item)
    {
        return $item instanceof DeferresAccess;
    }

    protected static function isInlineDeferrer($item, $type)
    {
        return $item instanceof InlineDeferrer || (is_object($item) && is_callable($item) && !preg_match('/^callable/i', $type));
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
     * @return Model|ModelDeferrer
     */
    protected static function modelise($item, string $type, string $parent, Model $model)
    {
        if ($item instanceof ModelDeferrer)
            return $item;
        return is_a($item, $type) ? $item : new ModelDeferrer($item, $type, $parent, $model);
    }

    /**
     * Turns the item into the specified object via the data constructors
     *
     * @param array $item
     * @param string $type
     * @param string $parent
     * @return mixed|ClassDeferrer
     */
    protected static function objectify($item, string $type, string $parent, Model $model)
    {
        return is_a($item, $type) ? $item : new ClassDeferrer($item, $type, $parent, $model);
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
        // Handle array to string conversions. If the item is
        // an array, is empty, and we are converting to a scalar,
        // treat the empty array as null
        if ($type != 'array' && is_array($item) && sizeof($item) == 0) {
            $item = null;
        }
        if ($type == 'mixed') return $item;
        return settype($item, $type) ? $item : $default;
    }

    /**
     * Can the element be null?
     *
     * @param $type
     * @return false|int
     */
    protected static function canBeNull($type)
    {
        return preg_match('/\\|?null\\|?/', $type);
    }

    protected static function escapeType($type)
    {
        return explode('|', trim(str_replace('null|', '', $type), '|'))[0];
    }
}
