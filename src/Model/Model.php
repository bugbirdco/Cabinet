<?php

namespace BugbirdCo\Cabinet\Model;

use BugbirdCo\Cabinet\Data\Data;
use Closure;
use Exception;
use JsonSerializable;
use phpDocumentor\Reflection\DocBlock;
use phpDocumentor\Reflection\DocBlock\Tags\Property;
use phpDocumentor\Reflection\DocBlockFactory;
use phpDocumentor\Reflection\Types\Context;
use phpDocumentor\Reflection\Types\ContextFactory;
use ReflectionClass;
use ReflectionException;
use RuntimeException;
use Spatie\Regex\Regex;
use Spatie\Regex\RegexFailed;

/**
 * Class Model
 * @package BugbirdCo\Candy
 * @method $this|$this[] resolve($singular = false)
 * @method $this singular()
 * @method $this[] plural()
 * @method self __invoke($data = [])
 */
abstract class Model implements JsonSerializable
{
    // Construction of model
    /** @var Data $attributes */
    private $attributes;

    /**
     * Here we are going to hydrate our models with the data we have just been given
     * The Data object that we receive is responsible for holding the data we
     * want to 'modelise' and handle the casting to the types we specify
     * @param Data $data
     * @throws ReflectionException
     * @throws RegexFailed
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
        if($parent !== false) {
            $properties = $parent::schema();
        }

        $docString = self::reflection()->getDocComment();

        if ($docString == false) {
            return $properties;
        }

        $parsedDocString = self::docBlock()->create($docString, self::context());

        /** @var Property $property */
        foreach ($parsedDocString->getTagsByName('property') as $property) {
            $properties[$property->getVariableName()] = (string)$property->getType();
        }

        return $properties;
    }

    public function __get($name)
    {
        return $this->attributes->{$name};
    }

    public function __set($name, $value)
    {
        $this->attributes->{$name} = $value;
    }

    // Consuming framework

    /** @var ReflectionClass[] */
    private static $reflection = [];
    /** @var DocBlockFactory */
    private static $docBlock;
    /** @var Context[] */
    private static $context = [];

    /**
     * Entry point for constructing models. Returns a proxy which is used to
     * chain mutations. Will except nothing, and array, a model class, or a
     * constructing model.
     *
     * The construction of a model will take in an object.
     * If we retrieve a constructing model (a proxy returned by a constructing
     * model), or we received a model class string and we know it inherits
     * the Model class, then we resolve it and set the data as the source.
     *
     * When we resolve a model, we fist check for a consumer method.
     * A consumer method is bound to a parent model. This allows us to consume
     * data being resolved by a parent, dependent on the parent type.
     * E.g. 2 models: Booking, Flights... If the Booking model has an attribute
     * called flights, of type Flight[], when Booking is resolved, it will pass
     * the flight data it has into the bookingConsumer consumer method, in
     * order to mutate the data into usable information, in the format of the
     * Flight model.
     *
     * A new proxy is created before calling the consumer, and passed into the
     * consumer. The consumer is then responsible for hydrating the proxy,
     * with either the source data passed into this consume function,
     * data derived from it's own source, or both.
     *
     * A model can have a source method defined which is responsible for
     * optionally providing data or manipulating the provided source array to
     * the proxy on construction. If defined, this method will automatically
     * be called if a consumer was not called, or if the proxy was hydrated
     * without any value.
     *
     * In the event of there not being a relevant consumer or a source
     * method, this consume method will hydrate the proxy with what ever was
     * passed into the source parameter. This should be in the format of an
     * array of entries, however, we will try to detect when a data array
     * is passed in and format it appropriately.
     *
     * @param array $source
     * @param Model $parent
     * @return static|callable
     * @throws RuntimeException
     */
    public static function consume($source = [], Model $parent = null)
    {
        try {
            if ($source instanceof Proxy) {
                $parent = $source->getModel();
                $source = $source->plural();
            } elseif (is_string($source) && is_a($source, Model::class, true)) {
                /** @var Model $source */
                $parent = $source;
                $source = $source::consume()->plural();
            }

            $proxy = new Proxy(static::class);

            if (!is_null($parent) && !is_null($scope = self::specificScope($parent))) {
                call_user_func([static::class, $scope], $proxy, $source);
            } else {
                static::fromSource($proxy, $source);
            }

            if (!$proxy->hydrated()) {
                $associative = array_filter([], function ($key) {
                        return !is_numeric($key);
                    }, ARRAY_FILTER_USE_KEY) > 0;

                $proxy($associative ? [$source] : $source);
            }

            return $proxy;
        } catch (Exception $e) {
            throw $e;
            throw new RuntimeException('Failed to consume data', 0, $e);
        }
    }

    /**
     * @param Proxy $proxy
     * @param array $source
     * @param Model $parent
     */
    public static function fromSource(Proxy $proxy, array $source = [], string $parent = null)
    {
        $class = is_null($parent) ? static::class : $parent;
        if (method_exists($class, 'source')) {
            $class::source($proxy, $source);
        }
    }

    /**
     * This method finds a consumer method in this model, if one exists.
     *
     * @param Model $fQName
     * @return string
     * @throws ReflectionException
     * @throws RegexFailed
     */
    public static function specificScope($fQName)
    {
        if (!is_string($fQName)) {
            $fQName = get_class($fQName);
        }

        $name = Regex::match('/(?:(?:^.*\\\)|(?:^))(\w*)$/', $fQName)->group(1) . 'Consumer';

        if (self::reflection()->hasMethod($name)) {
            $docBlock = self::docBlock()->create(
                self::reflection()->getMethod($name), self::context()
            );

            $matches = sizeof(
                    array_filter($docBlock->getTagsByName('uses'), function (DocBlock\Tags\Uses $uses) use ($fQName) {
                        return Regex::match('/^\\\?(.*)$/', $uses->getReference())->group(1) == $fQName;
                    })
                ) > 0;

            return $matches ? $name : null;
        }
        return null;
    }

    /**
     * @param Model $class
     * @return ReflectionClass
     * @throws ReflectionException
     */
    private static function reflection()
    {
        return self::$reflection[static::class] = self::$reflection[static::class] ?? new ReflectionClass(static::class);
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
     * @throws ReflectionException
     */
    private static function context()
    {
        return self::$context[static::class] = self::$context[static::class]
            ?? (new ContextFactory())->createFromReflector(static::reflection());

    }

    /**
     * Apply a mutation or a scope (grouping of mutations) from the
     * model to the proxy data.
     *
     * @param string $name
     * @param array $arguments
     * @param Proxy $proxy
     * @return array
     * @throws ReflectionException
     */
    public static function apply(string $name, array $arguments, Proxy $proxy)
    {
        if (self::reflection()->hasMethod("{$name}Mutation")) {
            return self::applyMutation($name, $arguments, $proxy);
        } elseif (self::reflection()->hasMethod("{$name}Scope")) {
            return self::applyScope($name, $arguments, $proxy);
        }
        throw new RuntimeException("No mutators or scopes named {$name} found in " . static::class);
    }

    /**
     * Apply a scope to the proxy.
     * In order to define a scope, a method doc tag must exist on the class
     * defining the method. It must not contain the 'Scope' suffix.
     *
     * A scope can require that other mutations/scopes are called prior
     * to this scope being called. This feature is called a dependency
     * and is defined by adding a uses doc tag to the doc block above the
     * function definition.
     *
     * Any additional arguments passed into the method call will be passed
     * into the scope method.
     *
     * @param string $name
     * @param array $arguments
     * @param Proxy $proxy
     * @return mixed
     */
    private static function applyScope(string $name, array $arguments, Proxy $proxy)
    {
        call_user_func([static::class, $name . 'Scope'], $proxy, ...$arguments);
        return $proxy->getData();
    }

    /**
     * Apply the mutation to the proxy
     *
     * In order to define a mutation, a method doc tag must exist on the class
     * defining the method. It must not contain the 'Mutation' suffix.
     *
     * The mutation is passed into an array manipulation function, like
     * array_map. A handler doc tag is used to indicate what kind of mutation
     * this function should be used for.
     *
     * The function also requires the parameters are documented using the
     * param doc tag, this allows us to map in attributes from the represented
     * data, depending on the handler requirements.
     * For example, the 'map' handler will place in the listed parameters from
     * the data in order of the list and then any additional parameters passed
     * into the function call.
     *
     * A mutation can require that other mutations/scopes are called prior
     * to this mutation being called. This feature is called a dependency
     * and is defined by adding a uses doc to the doc block above the
     * function definition.
     *
     * @param string $name
     * @param array $arguments
     * @param Proxy $proxy
     * @return mixed
     * @throws ReflectionException
     */
    private static function applyMutation(string $name, array $arguments, Proxy $proxy)
    {
        $mutator = self::reflection()->getMethod("{$name}Mutation");

        $docBlock = self::docBlock()->create($mutator->getDocComment(), self::context());

        self::checkDependencies($docBlock->getTagsByName('uses'), $proxy->getPreviousMutations());

        $handler = self::makeHandler(
            $docBlock->getTagsByName('handler'),
            $docBlock->getTagsByName('param')
        );

        return $handler($mutator->getClosure(), $proxy->getData(), $arguments);
    }

    private static function checkDependencies(array $dependencyTags, array $previousMutations, $throw = true)
    {
        $dependencyTags = array_map(function (DocBlock\Tags\Uses $dependency) {
            return $dependency->getReference()->getName();
        }, $dependencyTags);

        $appliedDependencies = array_intersect($dependencyTags, $previousMutations);

        if ($dependencyTags != $appliedDependencies) {
            if ($throw) {
                throw new RuntimeException('Mutator called with insufficient mutations for' . static::class);
            } else {
                return false;
            }
        }

        return true;
    }

    /**
     * Creates a closure for the method handler. It is responsible for
     * choosing the array manipulation function and handling loading
     * the parameters.
     *
     * @param array $handlerTags
     * @param array $listedParams
     * @return Closure
     */
    private static function makeHandler(array $handlerTags, array $listedParams)
    {
        if (sizeof($handlerTags) != 1) {
            throw new RuntimeException('Mutator did not have a single assigned handler' . static::class);
        }
        $handlerName = (string)$handlerTags[0];

        return function (callable $mutator, array $data, array $arguments) use ($handlerName, $listedParams) {

            $yanker = function ($entry) use (&$listedParams) {
                return array_reduce($listedParams, function (array $arguments, DocBlock\Tags\Param $key) use ($entry) {
                    if (isset($entry[$key->getVariableName()])) {
                        $arguments[] = $entry[$key->getVariableName()];
                    }
                    return $arguments;
                }, []);
            };


            switch ((string)$handlerName) {
                case 'filter':
                    return array_filter($data, function ($entry) use ($mutator, $arguments, $yanker) {
                        return $mutator(...$yanker($entry), ...$arguments);
                    });
                case 'map':
                    return array_map(function ($entry) use ($mutator, $arguments, $yanker) {
                        return $mutator(...$yanker($entry), ...$arguments);
                    }, $data);
                case 'merge':
                    return array_map(function ($entry) use ($mutator, $arguments, $yanker) {
                        return array_merge($entry, $mutator(...$yanker($entry), ...$arguments));
                    }, $data);
                case 'each':
                    return array_map(function ($entry) use ($mutator, $arguments, $yanker) {
                        $mutator(...$yanker($entry), ...$arguments);
                        return $entry;
                    }, $data);
                case 'reduce':
                    array_shift($listedParams);
                    return array_reduce($data, function ($accumulator, $entry) use ($mutator, $arguments, $yanker) {
                        return $mutator($accumulator, ...$yanker($entry), ...$arguments);
                    }, []);
                case 'tap':
                    return $mutator($data, ...$arguments);
                default:
                    throw new RuntimeException('Mutator did not use a known handler for ' . static::class);
            }
        };
    }

    public function jsonSerialize()
    {
        return $this->attributes;
    }
}