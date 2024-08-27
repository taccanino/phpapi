<?php

namespace utils;

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
            throw new \Exception('The DB_HOST environment variable is not set');

        $this->user = $this->envLoader->get('DB_USER');
        if ($this->user === null)
            throw new \Exception('The DB_USER environment variable is not set');

        $this->pass = $this->envLoader->get('DB_PASS');
        if ($this->pass === null)
            throw new \Exception('The DB_PASS environment variable is not set');

        $this->name = $this->envLoader->get('DB_NAME');
        if ($this->name === null)
            throw new \Exception('The DB_NAME environment variable is not set');

        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        $this->conn = new \mysqli($this->host, $this->user, $this->pass, $this->name);
    }

    // Function that incorporates prepared statements
    function query(string $query, array $params = []): array|int|string
    {
        if ($this->cache && stripos($query, 'SELECT') === 0) {
            // Check if the query is cached
            $cacheKey = $this->cache->encode([$query, $params]);
            $cachedResult = $this->cache->get($cacheKey);
            if ($cachedResult !== null)
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

        if (stripos($query, 'SELECT') === 0) {
            // For SELECT queries, return the result set
            $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            if ($this->cache)
                $this->cache->set($cacheKey, $this->cache->encode($result));
            return $result;
        }

        // For non-SELECT queries, return the number of affected rows
        return $stmt->affected_rows;
    }

    public function __destruct()
    {
        if ($this->conn)
            $this->conn->close();
    }
}
