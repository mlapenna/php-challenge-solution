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

    public function testSegmentPreviewEnforcesAccountIsolation(): void
    {
        // v_2001 (account 2) visited /pricing in May — must never appear in account 1 results.
        $response = $this->request('POST', '/api/accounts/1/segments/preview', json: [
            'rules' => [
                'visited_path' => '/pricing',
                'min_page_views' => 1,
                'identified_only' => false,
                'from' => '2026-05-01',
                'to' => '2026-05-15',
            ],
            'limit' => 100,
        ]);

        self::assertSame(200, $response->getStatusCode());

        $visitorIds = array_column($this->json($response)['visitors'], 'visitor_id');

        self::assertNotContains('v_2001', $visitorIds, 'Visitors from other accounts must not appear.');
    }

    public function testSegmentPreviewExcludesVisitorsOutsideDateRange(): void
    {
        // v_1004 visited /pricing on 2026-04-25 — outside the May window.
        $response = $this->request('POST', '/api/accounts/1/segments/preview', json: [
            'rules' => [
                'visited_path' => '/pricing',
                'min_page_views' => 1,
                'identified_only' => false,
                'from' => '2026-05-01',
                'to' => '2026-05-15',
            ],
            'limit' => 100,
        ]);

        self::assertSame(200, $response->getStatusCode());

        $visitorIds = array_column($this->json($response)['visitors'], 'visitor_id');

        self::assertNotContains('v_1004', $visitorIds, 'Visitors active only outside the date range must be excluded.');
    }

    public function testSegmentPreviewIncludesAnonymousVisitorsWhenIdentifiedOnlyIsFalse(): void
    {
        // v_1003 has no identity_events row — must appear when identified_only is false.
        $response = $this->request('POST', '/api/accounts/1/segments/preview', json: [
            'rules' => [
                'visited_path' => '/pricing',
                'min_page_views' => 1,
                'identified_only' => false,
                'from' => '2026-05-01',
                'to' => '2026-05-15',
            ],
            'limit' => 100,
        ]);

        self::assertSame(200, $response->getStatusCode());

        $visitorIds = array_column($this->json($response)['visitors'], 'visitor_id');

        self::assertContains('v_1003', $visitorIds, 'Anonymous visitors must be included when identified_only is false.');
    }

    public function testSegmentPreviewExcludesAnonymousVisitorsWhenIdentifiedOnlyIsTrue(): void
    {
        // v_1003 has no identity_events row — must be excluded when identified_only is true.
        $response = $this->request('POST', '/api/accounts/1/segments/preview', json: [
            'rules' => [
                'visited_path' => '/pricing',
                'min_page_views' => 1,
                'identified_only' => true,
                'from' => '2026-05-01',
                'to' => '2026-05-15',
            ],
            'limit' => 100,
        ]);

        self::assertSame(200, $response->getStatusCode());

        $visitorIds = array_column($this->json($response)['visitors'], 'visitor_id');

        self::assertNotContains('v_1003', $visitorIds, 'Anonymous visitors must be excluded when identified_only is true.');
    }

    public function testSegmentPreviewRespectsMinPageViewsThreshold(): void
    {
        // v_1003 has exactly 2 page views in May (/ and /pricing) — must be excluded when min_page_views is 3.
        $response = $this->request('POST', '/api/accounts/1/segments/preview', json: [
            'rules' => [
                'visited_path' => '/pricing',
                'min_page_views' => 3,
                'identified_only' => false,
                'from' => '2026-05-01',
                'to' => '2026-05-15',
            ],
            'limit' => 100,
        ]);

        self::assertSame(200, $response->getStatusCode());

        $visitorIds = array_column($this->json($response)['visitors'], 'visitor_id');

        self::assertNotContains('v_1003', $visitorIds, 'Visitors below min_page_views threshold must be excluded.');
    }

    public function testSegmentPreviewAcceptsSameDayRange(): void
    {
        // from === to is explicitly allowed by the spec.
        $response = $this->request('POST', '/api/accounts/1/segments/preview', json: [
            'rules' => [
                'visited_path' => '/pricing',
                'min_page_views' => 1,
                'identified_only' => false,
                'from' => '2026-05-03',
                'to' => '2026-05-03',
            ],
            'limit' => 25,
        ]);

        self::assertSame(200, $response->getStatusCode());

        $payload = $this->json($response);

        // v_1001 and v_1002 both visited /pricing on 2026-05-03.
        self::assertSame(2, $payload['count']);
        self::assertSame(['v_1001', 'v_1002'], array_column($payload['visitors'], 'visitor_id'));
    }

    public function testSegmentPreviewDefaultsLimitTo25(): void
    {
        // Omitting limit should default to 25 and return HTTP 200.
        $response = $this->request('POST', '/api/accounts/1/segments/preview', json: [
            'rules' => [
                'visited_path' => '/pricing',
                'min_page_views' => 1,
                'identified_only' => false,
                'from' => '2026-05-01',
                'to' => '2026-05-15',
            ],
        ]);

        self::assertSame(200, $response->getStatusCode());

        $payload = $this->json($response);

        self::assertArrayHasKey('count', $payload);
        self::assertArrayHasKey('visitors', $payload);
        self::assertLessThanOrEqual(25, count($payload['visitors']));
    }

    public function testSegmentPreviewRejectsLimitBelowOne(): void
    {
        $response = $this->request('POST', '/api/accounts/1/segments/preview', json: [
            'rules' => [
                'visited_path' => '/pricing',
                'min_page_views' => 1,
                'identified_only' => false,
                'from' => '2026-04-01',
                'to' => '2026-05-20',
            ],
            'limit' => 0,
        ]);

        self::assertSame(422, $response->getStatusCode());

        $payload = $this->json($response);

        self::assertSame('validation_failed', $payload['error']);
        self::assertArrayHasKey('limit', $payload['fields']);
    }

    public function testSegmentPreviewRejectsInvalidDateFormat(): void
    {
        $response = $this->request('POST', '/api/accounts/1/segments/preview', json: [
            'rules' => [
                'visited_path' => '/pricing',
                'min_page_views' => 1,
                'identified_only' => false,
                'from' => '01-05-2026',
                'to' => '2026-05-15',
            ],
            'limit' => 25,
        ]);

        self::assertSame(422, $response->getStatusCode());

        $payload = $this->json($response);

        self::assertSame('validation_failed', $payload['error']);
        self::assertArrayHasKey('rules.from', $payload['fields']);
    }

    public function testSegmentPreviewRejectsFromAfterTo(): void
    {
        $response = $this->request('POST', '/api/accounts/1/segments/preview', json: [
            'rules' => [
                'visited_path' => '/pricing',
                'min_page_views' => 1,
                'identified_only' => false,
                'from' => '2026-05-15',
                'to' => '2026-05-01',
            ],
            'limit' => 25,
        ]);

        self::assertSame(422, $response->getStatusCode());

        $payload = $this->json($response);

        self::assertSame('validation_failed', $payload['error']);
        self::assertArrayHasKey('rules.from', $payload['fields']);
    }

    public function testSegmentPreviewReturnsEmptyForNoMatches(): void
    {
        $response = $this->request('POST', '/api/accounts/1/segments/preview', json: [
            'rules' => [
                'visited_path' => '/nonexistent-path',
                'min_page_views' => 1,
                'identified_only' => false,
                'from' => '2026-05-01',
                'to' => '2026-05-15',
            ],
            'limit' => 25,
        ]);

        self::assertSame(200, $response->getStatusCode());

        $payload = $this->json($response);

        self::assertSame(0, $payload['count']);
        self::assertSame([], $payload['visitors']);
    }
}
