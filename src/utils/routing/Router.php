<?php

namespace utils\routing;

class Router
{
    public function __construct(private array $routes = []) {}

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
        //get method
        $method = $_SERVER['REQUEST_METHOD'];
        //get path without query string
        $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        //get query string
        $queryParams = $_GET;
        //get headers
        $headerParams = getallheaders();
        //get cookies
        $cookieParams = $_COOKIE;
        //get body
        $bodyParams = $this->getRequestBody();

        foreach ($this->routes as $route)
            try {
                return $route($method, $path, $queryParams, $headerParams, $cookieParams, $bodyParams);
            } catch (\Exception) {
                continue;
            }

        http_response_code(404);
        echo json_encode(['error' => 'Not found']);
    }

    private function getRequestBody(): array
    {
        //if application/json get the body from the input stream, else get it from $_POST
        if (array_key_exists('CONTENT_TYPE', $_SERVER) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false)
            return json_decode(file_get_contents('php://input'), true);

        return $_POST;
    }
}
