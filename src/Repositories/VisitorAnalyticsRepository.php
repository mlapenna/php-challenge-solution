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
                MAX(ie.email) AS email,
                MAX(ie.company) AS company,
                COUNT(pv.id) AS page_view_count,
                MAX(pv.occurred_at) AS last_seen_at,
                COUNT(pv.id) + (CASE WHEN MAX(ie.email) IS NULL THEN 0 ELSE 10 END) AS engagement_score
            FROM visitors v
            LEFT JOIN page_views pv ON pv.visitor_id = v.id
            LEFT JOIN identity_events ie ON ie.visitor_id = v.id
            WHERE v.account_id = :account_id
              AND pv.occurred_at >= :from_date
              OR pv.occurred_at <= :to_date
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
}

