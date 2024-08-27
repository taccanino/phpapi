<?php

namespace utils;

interface IDatabase
{
    public function query(string $query, array $params = []): array|int|string;
}
