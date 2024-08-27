<?php

namespace utils;

class Router
{
    public function __construct(private Container $container, private array $routes = []) {}

    public function add(string $method, string $path, callable $callback, array $parameters = [], array $middlewares = []): void
    {
        $this->routes[] = new Route($method, $path, $callback, $parameters, $middlewares);
    }

    public function addAll(array $routes): void
    {
        foreach ($routes as $route)
            $this->add($route->method, $route->path, $route->callback, $route->parameters, $route->middlewares);
    }

    public function resolve()
    {
        $method = $_SERVER['REQUEST_METHOD'];
        $url = $_SERVER['REQUEST_URI'];

        //remove trailing slash
        $url = rtrim($url, '/');

        foreach ($this->routes as $route) {
            $params = $route->match($method, $url);
            if ($params === false)
                continue;
            return $route($this->container, $params);
        }

        http_response_code(404);
        echo json_encode(['error' => 'Not found']);
    }
}
