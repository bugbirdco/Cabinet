<?php

namespace BugbirdCo\Cabinet\Model;


use BugbirdCo\Cabinet\Data\Data;
use BugbirdCo\Cabinet\Model\Model;

/**
 * Class Proxy
 * @package BugbirdCo\Candy\Model
 */
class Proxy
{
    /** @var Model */
    private $model;
    /** @var Data[] */
    private $data = null;

    private $previousMutations = [];

    /**
     * Proxy constructor.
     * @param string $model
     * @param Data[] $data
     */
    public function __construct(string $model)
    {
        $this->model = $model;
    }

    public function __call($name, $arguments)
    {
        return $this->apply($name, $arguments);
    }

    /**
     * Applies a mutator (or scope) to the proxied data, updates the proxy
     * data and logs the mutation (or scope)
     *
     * @param $name
     * @param $arguments
     * @return $this
     * @throws \ReflectionException
     */
    public function apply($name, $arguments)
    {
        $this->data = $this->model::apply($name, $arguments, $this);
        $this->previousMutations[] = $name;
        return $this;
    }

    public function __invoke(array $data = null)
    {
        return $this->hydrate($data);
    }

    /**
     * Loads data into the proxy. It is always in the format of a
     * list of arrays of data. E.g. [['name' => 'foo'], ['name' => 'bar']]
     *
     * @param array|null $data
     * @return $this
     */
    public function hydrate(array $data = null)
    {
        if (is_null($this->data)) {
            if (is_null($data)) {
                $this->model::fromSource($this);
            } else {
                $this->data = $data;
            }
        } else {
            throw new \RuntimeException('Tried to re-hydrate a data object');
        }
        return $this;
    }

    public function resolve($singular = false)
    {
        if (is_null($this->data)) {
            throw new \RuntimeException('Tried to resolve a non-hydrated data object');
        }

        $models = array_map(function ($data) {
            if (!is_array($data)) {
                throw new \RuntimeException("Source resolved without valid data");
            }
            return new $this->model (new Data($data));
        }, $this->data);

        if ($singular) {
            return sizeof($models) > 0 ? $models[0] : null;
        } else {
            return $models;
        }
    }

    public function singular()
    {
        return $this->resolve(true);
    }

    public function plural()
    {
        return $this->resolve(false);
    }

    public function getData()
    {
        return $this->data;
    }

    /**
     * @return Model
     */
    public function getModel()
    {
        return $this->model;
    }

    public function getPreviousMutations()
    {
        return $this->previousMutations;
    }

    public function hydrated()
    {
        return !is_null($this->data);
    }
}