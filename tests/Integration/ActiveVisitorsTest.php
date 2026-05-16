<?php

declare(strict_types=1);

namespace Challenge\Tests\Integration;

final class ActiveVisitorsTest extends IntegrationTestCase
{
    public function testActiveVisitorsAreScopedDeduplicatedAndCorrectlyCounted(): void
    {
        $response = $this->request('GET', '/api/accounts/1/visitors/active', [
            'from' => '2026-05-01',
            'to' => '2026-05-15',
        ]);

        self::assertSame(200, $response->getStatusCode());

        $payload = $this->json($response);
        $visitors = $payload['data'];

        self::assertSame(['v_1001', 'v_1002', 'v_1003'], array_column($visitors, 'visitor_id'));

        $byId = [];
        foreach ($visitors as $visitor) {
            $byId[$visitor['visitor_id']] = $visitor;
        }

        self::assertSame(4, (int) $byId['v_1001']['page_view_count']);
        self::assertSame('2026-05-14 12:30:00', $byId['v_1001']['last_seen_at']);
        self::assertSame('ana.silva@example.com', $byId['v_1001']['email']);

        self::assertArrayNotHasKey('v_2001', $byId, 'Visitors from other accounts must never leak into account 1.');
        self::assertArrayNotHasKey('v_1004', $byId, 'Visitors outside the requested date range should not be returned.');
    }
}

