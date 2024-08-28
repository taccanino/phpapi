<?php

namespace utils;

class EnvLoader
{
    public function __construct(string $path)
    {
        if (!file_exists($path)) {
            throw new \Exception('The .env file does not exist');
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos($line, '=') === false)
                continue;

            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);

            if (!array_key_exists($key, $_ENV))
                $_ENV[$key] = $value;
        }
    }

    public function get(string $key): ?string
    {
        //return null if the value is empty or not set or null
        return $_ENV[$key] ?? null;
    }
}
