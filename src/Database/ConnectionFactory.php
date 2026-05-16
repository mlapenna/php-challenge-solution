<?php

declare(strict_types=1);

namespace Challenge\Database;

use PDO;

final class ConnectionFactory
{
    public static function createFromEnvironment(): PDO
    {
        $host = getenv('DB_HOST') ?: '127.0.0.1';
        $port = getenv('DB_PORT') ?: '3306';
        $database = getenv('DB_DATABASE') ?: 'visitor_analytics';
        $username = getenv('DB_USERNAME') ?: 'analytics_user';
        $password = getenv('DB_PASSWORD') ?: 'analytics_password';

        return self::create($host, $port, $database, $username, $password);
    }

    public static function create(string $host, string $port, string $database, string $username, string $password): PDO
    {
        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $database);

        return new PDO($dsn, $username, $password, [
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }
}

