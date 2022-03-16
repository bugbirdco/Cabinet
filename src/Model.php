<?php

namespace BugbirdCo\Cabinet;

use BugbirdCo\Cabinet\Data\Data;
use JsonSerializable;
use phpDocumentor\Reflection\DocBlock\Tags\Property;
use phpDocumentor\Reflection\DocBlockFactory;
use phpDocumentor\Reflection\Types\Context;
use phpDocumentor\Reflection\Types\ContextFactory;
use ReflectionClass;
use ReflectionException;

/**
 * Class Model
 * @package BugbirdCo\Cabinet
 */
abstract class Model implements JsonSerializable
{
    // Construction of model
    /** @var Data $attributes */
    protected $attributes;

    public $include = [];
    public $exclude = [];

    /** @var ReflectionClass[] */
    protected static $reflection = [];
    /** @var DocBlockFactory */
    protected static $docBlock;
    /** @var Context[] */
    protected static $context = [];

    /**
     * @return ReflectionClass
     */
    private static function reflection()
    {
        return static::$reflection[static::class] = static::$reflection[static::class]
            ?? new ReflectionClass(static::class);
    }

    /**
     * @return DocBlockFactory
     */
    private static function docBlock()
    {
        return self::$docBlock = self::$docBlock ?? DocBlockFactory::createInstance();
    }

    /**
     * @return Context
     */
    private static function context()
    {
        return static::$context[static::class] = static::$context[static::class]
            ?? (new ContextFactory())->createFromReflector(static::reflection());

    }

    /**
     * Here we are going to hydrate our models with the data we have just been given
     * The Data object that we receive is responsible for holding the data we
     * want to 'modelise' and handle the casting to the types we specify
     * @param Data $data
     * @throws ReflectionException
     */
    public function __construct(Data $data)
    {
        $schema = static::schema();
        $this->attributes = $data->constrain($schema, $this);
    }

    /**
     * @return array
     * @throws ReflectionException
     */
    private static function schema()
    {
        /** @var Model $parent */
        $parent = get_parent_class(static::class);
        $properties = [];
        if ($parent !== false) {
            $properties = array_merge($properties, $parent::schema());
        }

        $docString = static::reflection()->getDocComment();

        if ($docString == false) {
            return $properties;
        }

        $parsedDocString = static::docBlock()->create($docString, static::context());

        /** @var Property $property */
        foreach ($parsedDocString->getTagsByName('property') as $property) {
            $properties[$property->getVariableName()] = (string)$property->getType();
        }

        return $properties;
    }

    public function __get($name)
    {
        return $this->attributes->__get($name);
    }

    public function __isset($name)
    {
        return $this->attributes->__isset($name);
    }

    public function isDeferred($name)
    {
        return $this->attributes->isDeferred($name);
    }

    /**
     * @param array $elements Things to overwrite on extension
     * @param null|string $into Class name of a different model
     * @return $this|static|self
     * @throws ReflectionException
     */
    public function extend($elements = [], $into = null, $include = null, $exclude = [])
    {
        $data = new Data($elements + $this->attributes->raw($include, $exclude)); // TODO: Create merge recursive handler
        if (empty($into)) {
            return new static($data);
        } else {
            return new $into ($data);
        }
    }

    public function attributes()
    {
        return $this->attributes;
    }

    public function jsonSerialize()
    {
        return $this->attributes;
    }

    /**
     * @return static
     * @throws ReflectionException
     */
    public static function make()
    {
        return new static(new Data(func_get_args()[0] ?? []));
    }
}
