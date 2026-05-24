<?php

declare(strict_types=1);

namespace Challenge\Repositories;

use PDO;

final class VisitorAnalyticsRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * Returns active visitors for an account.
     *
     * Assessment note: this method is intentionally flawed. The joins below
     * make repeated identity events inflate page_view_count, and the date
     * filter is too loose for a production analytics query.
     *
     * @return list<array<string, mixed>>
     */
    public function activeVisitors(int $accountId, string $from, string $to): array
    {
        $statement = $this->pdo->prepare(
            <<<'SQL'
            SELECT
                v.external_id AS visitor_id,
                (SELECT email FROM identity_events WHERE visitor_id = v.id ORDER BY occurred_at DESC LIMIT 1) AS email,
                (SELECT company FROM identity_events WHERE visitor_id = v.id ORDER BY occurred_at DESC LIMIT 1) AS company,
                COUNT(DISTINCT pv.id) AS page_view_count,
                MAX(pv.occurred_at) AS last_seen_at,
                COUNT(DISTINCT pv.id) + (CASE WHEN MAX(ie.id) IS NULL THEN 0 ELSE 10 END) AS engagement_score
            FROM visitors v
            INNER JOIN page_views pv ON pv.visitor_id = v.id
            LEFT JOIN identity_events ie ON ie.visitor_id = v.id
            WHERE v.account_id = :account_id
              AND pv.occurred_at BETWEEN :from_date AND :to_date
            GROUP BY v.id, v.external_id
            ORDER BY last_seen_at DESC, engagement_score DESC
            SQL
        );

        $statement->execute([
            'account_id' => $accountId,
            'from_date' => $from . ' 00:00:00',
            'to_date' => $to . ' 23:59:59',
        ]);

        return $statement->fetchAll();
    }

    /**
     * Returns a preview of visitors matching the given segment rules.
     *
     * Filters by account, date range, visited path (visitor must have at least
     * one page view on that path within the range), minimum total page views,
     * and optionally restricts to identified visitors only.
     *
     * @param array<string, mixed> $rules
     * @return array{count: int, visitors: list<array<string, mixed>>}
     */
    public function segmentPreview(int $accountId, array $rules, int $limit): array
    {
        $fromDate = $rules['from'] . ' 00:00:00';
        $toDate   = $rules['to'] . ' 23:59:59';

        $params = [
            'account_id'      => $accountId,
            'from_date'       => $fromDate,
            'to_date'         => $toDate,
            'visited_path'    => $rules['visited_path'],
            'from_date_path'  => $fromDate,
            'to_date_path'    => $toDate,
            'min_page_views'  => $rules['min_page_views'],
            'limit'           => $limit,
        ];

        $sql  = $this->buildSegmentPreviewSql((bool) $rules['identified_only']);
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $this->formatSegmentPreviewResult($rows);
    }

    private function buildSegmentPreviewSql(bool $identifiedOnly): string
    {
        $identifiedFilter = $identifiedOnly ? 'AND ie.id IS NOT NULL' : '';

        return <<<SQL
            -- Pre-compute the latest identity event per visitor in one pass
            -- instead of a correlated subquery that fires once per row.
            WITH latest_identity AS (
                SELECT id, visitor_id, email, company
                FROM (
                    SELECT
                        id,
                        visitor_id,
                        email,
                        company,
                        ROW_NUMBER() OVER (
                            PARTITION BY visitor_id
                            ORDER BY occurred_at DESC, id DESC
                        ) AS rn
                    FROM identity_events
                ) ranked
                WHERE rn = 1
            )
            SELECT
                visitor_id,
                email,
                company,
                page_view_count,
                last_seen_at,
                COUNT(*) OVER() AS total_count
            FROM (
                SELECT
                    v.external_id AS visitor_id,
                    ie.email,
                    ie.company,
                    COUNT(DISTINCT pv.id) AS page_view_count,
                    MAX(pv.occurred_at) AS last_seen_at
                FROM visitors v
                INNER JOIN page_views pv
                    ON pv.visitor_id = v.id
                    AND pv.occurred_at BETWEEN :from_date AND :to_date
                LEFT JOIN latest_identity ie ON ie.visitor_id = v.id
                WHERE v.account_id = :account_id
                  $identifiedFilter
                  AND EXISTS (
                      SELECT 1
                      FROM page_views pv_path
                      WHERE pv_path.visitor_id = v.id
                        AND pv_path.occurred_at BETWEEN :from_date_path AND :to_date_path
                        AND pv_path.path = :visited_path
                  )
                GROUP BY v.id, v.external_id, ie.email, ie.company
                HAVING COUNT(DISTINCT pv.id) >= :min_page_views
            ) grouped
            ORDER BY last_seen_at DESC, page_view_count DESC, visitor_id
            LIMIT :limit
        SQL;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return array{count: int, visitors: list<array<string, mixed>>}
     */
    private function formatSegmentPreviewResult(array $rows): array
    {
        $totalCount = (int) ($rows[0]['total_count'] ?? 0);

        foreach ($rows as &$row) {
            unset($row['total_count']);
        }
        unset($row);

        return [
            'count'    => $totalCount,
            'visitors' => $rows,
        ];
    }
}
