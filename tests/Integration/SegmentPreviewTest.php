<?php

declare(strict_types=1);

namespace Challenge\Tests\Integration;

final class SegmentPreviewTest extends IntegrationTestCase
{
    public function testSegmentPreviewFiltersVisitorsByRules(): void
    {
        $response = $this->request('POST', '/api/accounts/1/segments/preview', json: [
            'rules' => [
                'visited_path' => '/pricing',
                'min_page_views' => 2,
                'identified_only' => true,
                'from' => '2026-05-01',
                'to' => '2026-05-15',
            ],
            'limit' => 25,
        ]);

        self::assertSame(200, $response->getStatusCode());

        $payload = $this->json($response);

        self::assertSame(2, $payload['count']);
        self::assertSame(['v_1001', 'v_1002'], array_column($payload['visitors'], 'visitor_id'));
        self::assertSame(4, (int) $payload['visitors'][0]['page_view_count']);
        self::assertSame('ana.silva@example.com', $payload['visitors'][0]['email']);
    }

    public function testSegmentPreviewRejectsInvalidPayload(): void
    {
        $response = $this->request('POST', '/api/accounts/1/segments/preview', json: [
            'rules' => [
                'visited_path' => '',
                'min_page_views' => 0,
                'identified_only' => 'yes',
                'from' => '2026-05-15',
                'to' => '2026-05-01',
            ],
            'limit' => 500,
        ]);

        self::assertSame(422, $response->getStatusCode());

        $payload = $this->json($response);

        self::assertSame('validation_failed', $payload['error']);
        self::assertArrayHasKey('rules.visited_path', $payload['fields']);
        self::assertArrayHasKey('rules.min_page_views', $payload['fields']);
        self::assertArrayHasKey('rules.identified_only', $payload['fields']);
        self::assertArrayHasKey('rules.from', $payload['fields']);
        self::assertArrayHasKey('limit', $payload['fields']);
    }

    public function testSegmentPreviewUsesDeterministicOrderingAndLimit(): void
    {
        $response = $this->request('POST', '/api/accounts/1/segments/preview', json: [
            'rules' => [
                'visited_path' => '/pricing',
                'min_page_views' => 1,
                'identified_only' => false,
                'from' => '2026-05-01',
                'to' => '2026-05-15',
            ],
            'limit' => 2,
        ]);

        self::assertSame(200, $response->getStatusCode());

        $payload = $this->json($response);

        self::assertSame(3, $payload['count']);
        self::assertSame(['v_1001', 'v_1002'], array_column($payload['visitors'], 'visitor_id'));
    }
}

