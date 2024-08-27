<?php

declare(strict_types=1);
header('Content-Type: application/json');

function absolutePath(string $path): string
{
    return __DIR__ . '/' . $path;
}

require_once absolutePath('autoload.php');

$CONTAINER = new utils\Container();
$CONTAINER->init([
    utils\EnvLoader::class => fn() => new utils\EnvLoader(absolutePath('.env')),
    utils\Logger::class => fn() => new utils\Logger($CONTAINER->get(utils\EnvLoader::class)),
    utils\ErrorHandler::class => fn() => new utils\ErrorHandler($CONTAINER->get(utils\EnvLoader::class), $CONTAINER->get(utils\Logger::class)),
    utils\Router::class => fn() => new utils\Router($CONTAINER),
]);

$CONTAINER->loadAll();
$router = $CONTAINER->get(utils\Router::class);
$router->addAll([
    new utils\Route("GET", "/", function (utils\Container $container, array $params) {
        echo json_encode(['container' => $container, 'params' => $params]);
    }),
    new utils\Route(
        "GET",
        "/{id}",
        function (utils\Container $container, array $params) {
            throw new Exception("An error occurred");
            echo json_encode(['params' => $params]);
        },
        ['id' => ['regex' => '\d+', 'type' => 'int']],
        [function (utils\Container $container, array $params) {
            return [...$params, "middlewarePassed" => true];
        }]
    ),
]);

$router->resolve(); // matches the current request to the proper route, executes the callback and returns the result if any
