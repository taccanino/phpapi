<?php

namespace utils;

interface ICache
{
    public function get(string $key): ?string;
    public function set(string $key, string $value, ?int $seconds = null, ?int $milliseconds = null, ?bool $exist = null): ?bool;
}
