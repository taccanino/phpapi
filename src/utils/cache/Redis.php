<?php

namespace utils\cache;

use utils\env\EnvLoader;
use utils\errors\ErrorEnum;

class Redis implements ICache
{
    private \RedisClient\RedisClient $redisClient;
    private string $redisServer;
    private string $redisVersion;
    private ?int $redisInvalidateSeconds;

    public function __construct(private EnvLoader $envLoader)
    {
        $this->connect();
    }

    //this method can be rewritten for different cache providers
    private function connect(): void
    {
        require_once __DIR__ . '/redis/autoloader.php';

        $this->redisServer = $this->envLoader->get('REDIS_SERVER');
        if ($this->redisServer === null)
            throw new \Exception('The REDIS_SERVER environment variable is not set', ErrorEnum::CACHE_SERVER_NOT_SET->value);

        $this->redisVersion = $this->envLoader->get('REDIS_VERSION');
        if ($this->redisVersion === null)
            throw new \Exception('The REDIS_VERSION environment variable is not set', ErrorEnum::CACHE_VERSION_NOT_SET->value);

        $temp = $this->envLoader->get('REDIS_INVALIDATE_SECONDS');
        $this->redisInvalidateSeconds = $temp === null || $temp === '' || !ctype_digit($temp) ? null : (int)$temp;

        $this->redisClient = \RedisClient\ClientFactory::create([
            'server' => $this->envLoader->get('REDIS_SERVER'),
            'timeout' => 2,
            'version' => $this->envLoader->get('REDIS_VERSION'),
        ]);

        $pong = $this->redisClient->ping();
        if ($pong !== 'PONG')
            throw new \Exception('Redis server is not responding', ErrorEnum::CACHE_CONNECTION_ERROR->value);
    }

    public function exists(string $key): bool
    {
        return $this->redisClient->exists($key) !== 0;
    }

    public function get(string $key): ?string
    {
        return $this->redisClient->get($key);
    }

    public function set(string $key, string $value, ?int $seconds = null, ?int $milliseconds = null, ?bool $exist = null): ?bool
    {
        return $this->redisClient->set($key, $value, $seconds === null ? $this->redisInvalidateSeconds : $seconds, $milliseconds, $exist ? 'XX' : ($exist == false ? 'NX' : null));
    }

    public function del(string $key): int
    {
        return $this->redisClient->del($key);
    }

    public function encode(...$args): string
    {
        return json_encode($args);
    }

    public function decode(string $key): array
    {
        return json_decode($key, true);
    }

    public function execute(string $command, string ...$args): mixed
    {
        return $this->redisClient->executeRaw([$command, ...$args]);
    }
}
