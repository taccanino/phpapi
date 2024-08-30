<?php

namespace utils;

use utils\errors\ErrorEnum;

class Container
{
    private array $instances = [];
    private ?array $definitions = null;

    public function __construct() {}

    /**
     * @param array $definitions An associative array where the key is the class name and the value is a callable that returns an instance of the class
     */
    public function init(array $definitions): void
    {
        $this->definitions = $definitions;
    }

    /**
     * Same as get but without returning the instance to the caller
     * 
     * @param string $className The name of the class to load an instance of
     * @return void
     */
    public function load(string $className): void
    {
        // If the instance has already been created, return
        if (isset($this->instances[$className]))
            return;

        if ($this->definitions === null)
            throw new \Exception("Cannot load class {$className} without definitions", ErrorEnum::CONTAINER_MISSING_DEFINITIONS->value);

        // If the class has a definition, use it to create an instance
        if (isset($this->definitions[$className])) {
            $this->instances[$className] = $this->definitions[$className]($this);
            return;
        }

        // If the class has no definition, try to create an instance using reflection
        $reflection = new \ReflectionClass($className);
        $constructor = $reflection->getConstructor();
        if ($constructor === null) {
            $this->instances[$className] = new $className();
            return;
        }

        $parameters = $constructor->getParameters();
        $dependencies = [];
        foreach ($parameters as $parameter) {
            $dependency = $parameter->getType();
            if ($dependency === null)
                throw new \Exception("Cannot resolve dependency for parameter \${$parameter->getName()} in {$className} constructor", ErrorEnum::CONTAINER_CANNOT_RESOLVE_CONSTRUCTOR_PARAMETER_DEPENDENCY->value);
            $dependencies[] = $this->get($dependency->getName());
        }

        $this->instances[$className] = $reflection->newInstanceArgs($dependencies);
    }

    /**
     * Loads all the classes in the correct insertion (dependency) order
     * 
     * @return void
     */
    public function loadAll(): void
    {
        if ($this->definitions === null)
            throw new \Exception("Cannot load classes without definitions", ErrorEnum::CONTAINER_MISSING_DEFINITIONS->value);

        foreach (array_keys($this->definitions) as $className)
            $this->load($className);
    }

    /**
     * Get an instance of a class
     * 
     * @param string $className The name of the class to get an instance of
     * @return mixed An instance of the class
     */
    public function get(string $className): mixed
    {
        $this->load($className);
        return $this->instances[$className];
    }
}
