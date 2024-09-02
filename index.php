<?php

declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

function absolutePath(string $path): string
{
    return __DIR__ . '/' . $path;
}

require_once absolutePath('autoload.php');

use utils\Container;
use utils\env\EnvLoader;
use utils\logging\Logger;
use utils\errors\ErrorHandler;
use utils\cache\ICache;
use utils\cache\Redis;
use utils\database\IDatabase;
use utils\database\Mysql;
use utils\routing\Router;
use utils\routing\Route;

$CONTAINER = new Container();
$CONTAINER->init([
    EnvLoader::class => fn() => new EnvLoader(absolutePath('.env')),
    Logger::class => fn() => new Logger($CONTAINER->get(EnvLoader::class)),
    ErrorHandler::class => fn() => new ErrorHandler($CONTAINER->get(EnvLoader::class), $CONTAINER->get(Logger::class)),
    ICache::class => function () {
        global $CONTAINER;
        try {
            return new Redis($CONTAINER->get(EnvLoader::class));
        } catch (Exception) {
            return null;
        }
    },
    IDatabase::class => fn() => new Mysql($CONTAINER->get(EnvLoader::class), $CONTAINER->get(ICache::class)),
    Router::class => fn() => new Router(
        [
            new Route(
                "GET",
                "/api/watercrafts/{id}",
                function (array $params) {
                    global $CONTAINER;
                    echo json_encode($CONTAINER->get(IDatabase::class)->query("SELECT * FROM watercrafts WHERE id = ?", $params["path"]));
                },
                [],
                [
                    "id" => "int"
                ]
            ),
        ]
    ),
]);

$CONTAINER->loadAll();
$CONTAINER->get(Router::class)->resolve(); // matches the current request to the proper route, executes the callback and returns the result if any
