<?php

declare(strict_types=1);

namespace Challenge\Tests\Integration;

use Challenge\AppFactory;
use Challenge\Database\ConnectionFactory;
use PDO;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Slim\Psr7\Factory\ServerRequestFactory;

abstract class IntegrationTestCase extends TestCase
{
    protected PDO $pdo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pdo = ConnectionFactory::createFromEnvironment();
        $this->loadSql(__DIR__ . '/../../database/schema.sql');
        $this->loadSql(__DIR__ . '/../../database/seed.sql');
    }

    /**
     * @param array<string, string> $query
     * @param array<string, mixed>|null $json
     */
    protected function request(string $method, string $path, array $query = [], ?array $json = null): ResponseInterface
    {
        $app = AppFactory::create();
        $uri = $path;

        if ($query !== []) {
            $uri .= '?' . http_build_query($query);
        }

        $request = (new ServerRequestFactory())->createServerRequest($method, $uri);

        if ($json !== null) {
            $request->getBody()->write((string) json_encode($json, JSON_THROW_ON_ERROR));
            $request = $request
                ->withHeader('Content-Type', 'application/json')
                ->withParsedBody($json);
        }

        return $app->handle($request);
    }

    /**
     * @return array<string, mixed>
     */
    protected function json(ResponseInterface $response): array
    {
        return json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);
    }

    private function loadSql(string $path): void
    {
        $sql = file_get_contents($path);
        self::assertIsString($sql);

        $this->pdo->exec($sql);
    }
}

