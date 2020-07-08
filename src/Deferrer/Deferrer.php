<?php

namespace BugbirdCo\Cabinet\Deferrer;

use BugbirdCo\Cabinet\Data\Data;
use BugbirdCo\Cabinet\Model;
use BugbirdCo\Cabinet\Operations;

/**
 * This class is responsible for deferring access to a model or class, and creating
 * it on access. This is done in order to reduce the amount of operations required
 * to create a resource, and is the primary means of extensibility. This class is
 * also responsible for setting up a model to consume parent data.
 *
 * @package BugbirdCo\Cabinet
 */
abstract class Deferrer
{
    use Operations;

    /** @var callable */
    private $accessor;

    /** @var Model[]|Model */
    private $value = null;

    /**
     * Accessor constructor.
     * @param array $items
     * @param string $type
     * @param string $parent
     */
    public function __construct($items, string $type, string $parent)
    {
        $this->accessor = function () use ($type, $items, $parent) {
            return $this->create($items, $type, $parent);
        };
    }

    protected function create($items, string $type, string $parent)
    {
        if (static::isPlural($type)) {
            $singular = self::singularise($type);
            return array_map(function ($item) use ($parent, $singular) {
                return $this->create($item, $singular, $parent);
            }, $items);
        } else {
            $namedElements = explode('/', $parent);
            $created = array_reduce($namedElements, function ($model) use (&$namedElements, $type, $items) {
                if(!is_null($model)) {
                    return $model;
                }

                $object = $this->make(implode($namedElements), $type, $items);
                array_shift($namedElements);
                return $object;
            });

            return is_null($created) ? $this->fallback($type, $items) : $created;
        }
    }

    abstract protected function make($name, $type, $items);
    abstract protected function fallback($type, $items);

    /**
     * Provides the resolution of the accessor, called from inside the magic method of
     * the data object.
     *
     * @return Model|Model[]|mixed
     */
    public function resolve()
    {
        return $this->value = $this->value ?? ($this->accessor)();
    }
}