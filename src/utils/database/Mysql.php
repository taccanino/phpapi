<?php

namespace utils\database;

use utils\cache\ICache;
use utils\env\EnvLoader;
use utils\errors\ErrorEnum;

class Mysql implements IDatabase
{
    private string $host;
    private string $user;
    private string $pass;
    private string $name;

    private \mysqli $conn;

    public function __construct(private EnvLoader $envLoader, private ?ICache $cache = null)
    {
        $this->connect();
    }

    private function connect(): void
    {
        $this->host = $this->envLoader->get('DB_HOST');
        if ($this->host === null)
            throw new \Exception('The DB_HOST environment variable is not set', ErrorEnum::DATABASE_HOST_NOT_SET->value);

        $this->user = $this->envLoader->get('DB_USER');
        if ($this->user === null)
            throw new \Exception('The DB_USER environment variable is not set', ErrorEnum::DATABASE_USER_NOT_SET->value);

        $this->pass = $this->envLoader->get('DB_PASS');
        if ($this->pass === null)
            throw new \Exception('The DB_PASS environment variable is not set', ErrorEnum::DATABASE_PASS_NOT_SET->value);

        $this->name = $this->envLoader->get('DB_NAME');
        if ($this->name === null)
            throw new \Exception('The DB_NAME environment variable is not set', ErrorEnum::DATABASE_NAME_NOT_SET->value);

        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        $this->conn = new \mysqli($this->host, $this->user, $this->pass, $this->name);
    }

    // Function that incorporates prepared statements
    function query(string $query, array $params = [], array $tables = []): array|int|string
    {
        if (!$this->conn)
            throw new \Exception('No database connection', ErrorEnum::DATABASE_CONNECTION_ERROR->value);

        // Check if the query is a SELECT query
        $isSelect = stripos($query, 'SELECT') === 0;

        if ($this->cache && $isSelect) {
            /**
             * The cache is made like this (to be improved in the future with the use of more advanced cache functionalities (json, list, set, etc.)):
             * [
             *   '['SELECT * FROM example, temp WHERE id = ?, [1]]' => [...],
             *   '['SELECT * FROM example, temp WHERE id = ?, [2]]' => [...],
             *   '['SELECT name FROM temp, example WHERE id = ? AND city = ?, [2, 'ROME']]' => [...],
             *   'example' => [
             *     '['SELECT * FROM example, temp WHERE id = ?, [1]]',
             *     '['SELECT * FROM example, temp WHERE id = ?, [2]]',
             *     '['SELECT name FROM temp, example WHERE id = ? AND city = ?, [2, 'ROME']]'
             *   ],
             *   'temp' => [
             *     '['SELECT * FROM example, temp WHERE id = ?, [1]]',
             *     '['SELECT * FROM example, temp WHERE id = ?, [2]]',
             *     '['SELECT name FROM temp, example WHERE id = ? AND city = ?, [2, 'ROME']]'
             *   ],
             * ]
             */

            $cacheKey = $this->cache->encode([$query, $params]);
            $cachedResult = $this->cache->get($cacheKey);
            if ($cachedResult)
                return $this->cache->decode($cachedResult);
        }

        // Prepare the statement
        $stmt = $this->conn->prepare($query);

        // Infer types from the params and bind them if necessary
        if (count($params) > 0) {
            $types = '';
            $boundParams = [];

            foreach ($params as $param) {
                if (is_int($param))
                    $types .= 'i';
                elseif (is_float($param))
                    $types .= 'd';
                elseif (is_string($param))
                    $types .= 's';
                elseif (is_bool($param))
                    $types .= 'i'; // boolean treated as integer (1 or 0)
                elseif (is_null($param))
                    $types .= 's'; // NULL treated as string (though it's null in DB)
                else
                    $types .= 'b'; // Other types like arrays, objects, or resources as blobs

                $boundParams[] = $param;
            }

            $stmt->bind_param($types, ...$boundParams);
        }

        $stmt->execute();

        if ($isSelect) {
            // For SELECT queries, return the result set
            $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            if ($this->cache) {
                $this->cache->set($cacheKey, $this->cache->encode($result));
                foreach ($tables as $table) {
                    $tableCache = $this->cache->get($table);
                    if ($tableCache === null)
                        $tableCache = [];
                    else
                        $tableCache = $this->cache->decode($tableCache);
                    $tableCache[] = $cacheKey;
                    $this->cache->set($table, $this->cache->encode($tableCache));
                }
            }
            return $result;
        }

        // For non-SELECT queries, invalidate the cache of the affected tables
        if ($this->cache) {
            foreach ($tables as $table) {
                $tableCache = $this->cache->get($table);
                if ($tableCache !== null) {
                    $tableCache = $this->cache->decode($tableCache);
                    foreach ($tableCache as $queryCacheKey)
                        $this->cache->del($queryCacheKey);
                }
            }
        }
        return $stmt->affected_rows;
    }

    public function __destruct()
    {
        if ($this->conn)
            $this->conn->close();
    }
}
