<?php

namespace App\Repository;

use PDOStatement;

class DatabaseConnection
{
    protected \PDO $pdo;

    public function __construct(?string $databaseName = null)
    {
        $databaseName = $databaseName ?? $_ENV['DATABASE_NAME'];
        $dsn = sprintf(
            "mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4", 
            $_ENV['DATABASE_HOST'], 
            $_ENV['DATABASE_PORT'],
            $databaseName
        );

        $this->pdo = new \PDO($dsn, $_ENV['DATABASE_USER'], $_ENV['DATABASE_PASS'], [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        ]);
    }

    protected function prepare(string $query, ?array $params = []): PDOStatement|false
    {
        $stmt = $this->pdo->prepare($query);

        if ($params) {
            foreach ($params as $key => $value) {
                $name = ltrim($key, ':');

                $type = match (gettype($value)) {
                    'integer' => \PDO::PARAM_INT,
                    'boolean' => \PDO::PARAM_BOOL,
                    'NULL'    => \PDO::PARAM_NULL,
                    default   => \PDO::PARAM_STR,
                };

                $stmt->bindValue($name, $value, $type);
            }
        }

        $stmt->execute();
        return $stmt;
    }
}