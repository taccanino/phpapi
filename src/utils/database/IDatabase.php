<?php

namespace utils\database;

interface IDatabase
{
    public function query(string $query, array $params = []): array|int|string;
}
