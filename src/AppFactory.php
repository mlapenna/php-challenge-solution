<?php

declare(strict_types=1);

namespace Challenge;

use Challenge\Database\ConnectionFactory;
use Challenge\Http\JsonResponder;
use Challenge\Http\SegmentPreviewRequest;
use Challenge\Repositories\VisitorAnalyticsRepository;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;
use Slim\Factory\AppFactory as SlimAppFactory;

final class AppFactory
{
    public static function create(): App
    {
        $app = SlimAppFactory::create();
        $app->addBodyParsingMiddleware();

        $pdo = ConnectionFactory::createFromEnvironment();
        $repository = new VisitorAnalyticsRepository($pdo);

        $app->get('/health', function (Request $request, Response $response) use ($pdo): Response {
            $pdo->query('SELECT 1')->fetchColumn();

            return JsonResponder::json($response, [
                'status' => 'ok',
                'database' => 'connected',
            ]);
        });

        $app->get('/api/accounts/{accountId}/visitors/active', function (Request $request, Response $response, array $args) use ($repository): Response {
            $accountId = (int) $args['accountId'];
            $query = $request->getQueryParams();

            $from = is_string($query['from'] ?? null) ? trim($query['from']) : '';
            $to = is_string($query['to'] ?? null) ? trim($query['to']) : '';

            $visitors = $repository->activeVisitors($accountId, $from, $to);

            return JsonResponder::json($response, [
                'data' => $visitors,
            ]);
        });

        $app->post('/api/accounts/{accountId}/segments/preview', function (Request $request, Response $response, array $args) use ($repository): Response {
            $previewRequest = new SegmentPreviewRequest((array) $request->getParsedBody());

            if (!$previewRequest->isValid()) {
                return JsonResponder::json($response, [
                    'error' => 'validation_failed',
                    'fields' => $previewRequest->errors,
                ], 422);
            }

            $result = $repository->segmentPreview(
                (int) $args['accountId'],
                $previewRequest->toRules(),
                $previewRequest->limit,
            );

            return JsonResponder::json($response, [
                'count' => $result['count'],
                'visitors' => $result['visitors'],
            ]);
        });

        $app->addErrorMiddleware(true, true, true);

        return $app;
    }
}
