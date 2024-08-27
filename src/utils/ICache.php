<?php

namespace utils;

interface ICache
{
    public function exists(string $key): bool;
    public function get(string $key): ?string;
    public function set(string $key, string $value, ?int $seconds = null, ?int $milliseconds = null, ?bool $exist = null): ?bool;
    public function del(string $key): int;
    public function encode(...$args): string;
    public function decode(string $key): array;
}
