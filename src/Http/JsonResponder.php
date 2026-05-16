<?php

declare(strict_types=1);

namespace Challenge\Http;

use Psr\Http\Message\ResponseInterface as Response;

final class JsonResponder
{
    /**
     * @param array<string, mixed> $payload
     */
    public static function json(Response $response, array $payload, int $status = 200): Response
    {
        $response->getBody()->write((string) json_encode($payload, JSON_THROW_ON_ERROR));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($status);
    }
}

