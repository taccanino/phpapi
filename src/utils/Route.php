<?php

namespace utils;

class Route
{
    private string $regex;
    public string $path;
    public $callback;

    public function __construct(
        public string $method,
        string $path,
        callable $callback,
        public array $parameters = [],
        public array $middlewares = []
    ) {
        $this->path = rtrim($path, '/');
        $this->regex = $this->createRegex();
        $this->callback = $callback;
    }

    private function createRegex(): string
    {
        // Handle required parameters in path
        $regex = preg_replace_callback('/{(\w+)}/', function ($matches) {
            $name = $matches[1];
            $parameterConfig = $this->parameters[$name] ?? [];

            // Use the provided regex pattern or default to matching non-slash characters
            $pattern = $parameterConfig['regex'] ?? '[^/]+';

            // Return the capturing group for the parameter
            return "(?P<{$name}>{$pattern})";
        }, $this->path);

        // Ensure the path does not have double slashes
        $regex = "#^" . rtrim($regex, '/') . "$#";

        return $regex;
    }

    public function match(string $method, string $urlPath): array|false
    {
        // If the HTTP method does not match, return false
        if ($this->method !== $method) {
            return false;
        }

        // Match the URL path with the route's regex pattern
        if (!preg_match($this->regex, $urlPath, $matches)) {
            return false;
        }

        // Remove the first element of the matches array (the full match)
        array_shift($matches);

        // Handle type conversion for matched parameters
        foreach ($this->parameters as $name => $config) {
            if (isset($matches[$name]) && isset($config['type'])) {
                settype($matches[$name], $config['type']);
            }
        }

        // Filter out numeric keys
        $matches = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);

        return $matches;
    }

    public function __invoke(array $parameters): mixed
    {
        $modifiedParameters = $parameters;
        foreach ($this->middlewares as $middleware)
            $modifiedParameters = $middleware($modifiedParameters);
        $callback = $this->callback;
        return $callback($modifiedParameters);
    }
}
