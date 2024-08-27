<?php

declare(strict_types=1);
header('Content-Type: application/json');

function absolutePath(string $path): string
{
    return __DIR__ . '/' . $path;
}

require_once absolutePath('autoload.php');

use utils\Container;
use utils\EnvLoader;
use utils\Logger;
use utils\ErrorHandler;
use utils\ICache;
use utils\Redis;
use utils\Database;
use utils\Router;
use utils\Route;

$CONTAINER = new Container();
$CONTAINER->init([
    EnvLoader::class => fn() => new EnvLoader(absolutePath('.env')),
    Logger::class => fn() => new Logger($CONTAINER->get(EnvLoader::class)),
    ErrorHandler::class => fn() => new ErrorHandler($CONTAINER->get(EnvLoader::class), $CONTAINER->get(Logger::class)),
    ICache::class => fn() => new Redis($CONTAINER->get(EnvLoader::class)),
    Database::class => fn() => new Database($CONTAINER->get(EnvLoader::class), $CONTAINER->get(ICache::class)),
    Router::class => fn() => new Router(
        [
            new Route("GET", "/", function (array $params) {
                global $CONTAINER;
                echo json_encode(['container' => $CONTAINER, 'params' => $params]);
            }),
            new Route(
                "GET",
                "/{id}",
                function (array $params) {
                    echo json_encode(['params' => $params]);
                },
                ['id' => ['regex' => '\d+', 'type' => 'int']],
                [function (array $params) {
                    return [...$params, "middlewarePassed" => true];
                }]
            ),
        ]
    ),
]);

$CONTAINER->loadAll();
$CONTAINER->get(Router::class)->resolve(); // matches the current request to the proper route, executes the callback and returns the result if any
