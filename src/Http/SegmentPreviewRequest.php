<?php

declare(strict_types=1);

namespace Challenge\Http;

final class SegmentPreviewRequest
{
    /** @var array<string, string> */
    public readonly array $errors;

    public readonly string $visitedPath;
    public readonly int $minPageViews;
    public readonly bool $identifiedOnly;
    public readonly string $from;
    public readonly string $to;
    public readonly int $limit;

    /** @param array<string, mixed> $body */
    public function __construct(array $body)
    {
        $rules = $body['rules'] ?? [];
        $errors = [];

        $visitedPath = is_string($rules['visited_path'] ?? null) ? trim($rules['visited_path']) : '';
        if ($visitedPath === '') {
            $errors['rules.visited_path'] = 'Visited path is required and must be a non-empty string.';
        }

        $minPageViews = $rules['min_page_views'] ?? null;
        if (filter_var($minPageViews, FILTER_VALIDATE_INT) === false || (int) $minPageViews < 1) {
            $errors['rules.min_page_views'] = 'Minimum page views must be an integer greater than or equal to 1.';
            $minPageViews = 0;
        } else {
            $minPageViews = (int) $minPageViews;
        }

        if (!isset($rules['identified_only']) || !is_bool($rules['identified_only'])) {
            $errors['rules.identified_only'] = 'Identified only must be a boolean.';
            $identifiedOnly = false;
        } else {
            $identifiedOnly = $rules['identified_only'];
        }

        $from = is_string($rules['from'] ?? null) ? trim($rules['from']) : '';
        $to = is_string($rules['to'] ?? null) ? trim($rules['to']) : '';
        if (!self::isValidDate($from) || !self::isValidDate($to) || $from > $to) {
            $errors['rules.from'] = 'Invalid date range. Both from and to must be valid YYYY-MM-DD dates, and from must be earlier than or equal to to.';
        }

        $limitRaw = $body['limit'] ?? 25;
        if (filter_var($limitRaw, FILTER_VALIDATE_INT) === false) {
            $errors['limit'] = 'Limit must be between 1 and 100.';
            $limit = 25;
        } else {
            $limit = (int) $limitRaw;
            if ($limit < 1 || $limit > 100) {
                $errors['limit'] = 'Limit must be between 1 and 100.';
            }
        }

        $this->errors = $errors;
        $this->visitedPath = $visitedPath;
        $this->minPageViews = $minPageViews;
        $this->identifiedOnly = $identifiedOnly;
        $this->from = $from;
        $this->to = $to;
        $this->limit = $limit;
    }

    public function isValid(): bool
    {
        return $this->errors === [];
    }

    public function toRules(): array
    {
        return [
            'visited_path'    => $this->visitedPath,
            'min_page_views'  => $this->minPageViews,
            'identified_only' => $this->identifiedOnly,
            'from'            => $this->from,
            'to'              => $this->to,
        ];
    }

    private static function isValidDate(string $value): bool
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return false;
        }
        [$year, $month, $day] = explode('-', $value);
        return checkdate((int) $month, (int) $day, (int) $year);
    }
}
