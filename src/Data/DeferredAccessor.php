<?php


namespace BugbirdCo\Cabinet\Data;


use BugbirdCo\Cabinet\Model\Model;

class DeferredAccessor
{
    /** @var callable */
    private $accessor;

    /** @var Model[]|Model */
    private $value = null;

    /**
     * DeferredAccessor constructor.
     * @param Model $type
     * @param array $items
     * @param Model $parent
     */
    public function __construct($type, $items, bool $singular = false, $parent = null)
    {
        $this->accessor = function() use ($type, $items, $parent, $singular){
            return $type::consume($items, $parent)->resolve($singular);
        };
    }

    public function resolve()
    {
        return $this->value = $this->value ?? ($this->accessor)();
    }
}