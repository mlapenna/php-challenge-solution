# PHP + MySQL Visitor Analytics Challenge

## Task Overview

You are joining a martech SaaS team that helps customers identify and understand website visitors. The current API is a small PHP service backed by MySQL. It already has seeded visitor analytics data and one endpoint for active visitors, but the analytics result is wrong. A second endpoint for segment previews is planned but not implemented.

Your task is to fix the existing active visitor analytics bug and implement the missing segment preview endpoint.

This assessment is designed for a 45-minute working session. Use any tools you normally use, including AI agents. We care about how you debug, make tradeoffs, validate behavior, and keep the code maintainable.

## Setup

Requirements:

- Docker
- Docker Compose

## Submission Workflow

Do not push changes to this starter repository.

Create a repository under your own GitHub account, push your completed solution there, and share the repository link with the reviewer.

Recommended commands:

```bash
git clone git@github.com:joao-devsignal/php-challenge.git php-challenge-solution
cd php-challenge-solution
git remote rename origin starter
```

Create an empty repository on GitHub under your account. Do not initialize it with a README, `.gitignore`, or license. Then connect your local clone to that repository:

```bash
git remote add origin git@github.com:YOUR_GITHUB_USERNAME/php-challenge-solution.git
git push -u origin main
```

If you use HTTPS instead of SSH:

```bash
git remote add origin https://github.com/YOUR_GITHUB_USERNAME/php-challenge-solution.git
git push -u origin main
```

After finishing the challenge:

```bash
git add .
git commit -m "Complete PHP visitor analytics challenge"
git push
```

Send the reviewer the URL of your repository, for example:

```text
https://github.com/YOUR_GITHUB_USERNAME/php-challenge-solution
```

Start the app:

```bash
docker compose up --build
```

In another terminal, run the tests:

```bash
docker compose exec app composer test
```

The tests are expected to fail in the starter state. They describe the behavior you need to fix or implement.

If you need a clean database container while debugging, run:

```bash
docker compose down
docker compose up --build
```

The API runs at `http://localhost:8080`.

```bash
curl http://localhost:8080/health
```

## Current Behavior

The app has seeded data for two accounts, visitors, page views, and identity events.

Existing endpoint:

```http
GET /api/accounts/{accountId}/visitors/active?from=YYYY-MM-DD&to=YYYY-MM-DD
```

Expected response shape:

```json
{
  "data": [
    {
      "visitor_id": "v_1001",
      "email": "ana.silva@example.com",
      "company": "Acme",
      "page_view_count": 4,
      "last_seen_at": "2026-05-14 12:30:00",
      "engagement_score": 14
    }
  ]
}
```

Known issue:

- The active visitor query currently returns incorrect analytics because joined identity events inflate page view counts.
- The date/account filtering is also too loose for a production analytics query.
- The endpoint must return one row per visitor, scoped to the requested account and date range.

## Required Implementation

Implement:

```http
POST /api/accounts/{accountId}/segments/preview
```

Request:

```json
{
  "rules": {
    "visited_path": "/pricing",
    "min_page_views": 2,
    "identified_only": true,
    "from": "2026-05-01",
    "to": "2026-05-15"
  },
  "limit": 25
}
```

Response:

```json
{
  "count": 2,
  "visitors": [
    {
      "visitor_id": "v_1001",
      "email": "ana.silva@example.com",
      "company": "Acme",
      "page_view_count": 4,
      "last_seen_at": "2026-05-14 12:30:00"
    }
  ]
}
```

Rules:

- `visited_path` is required and must be a non-empty string.
- `min_page_views` is required and must be an integer greater than or equal to `1`.
- `identified_only` is required and must be a boolean.
- `from` and `to` are required `YYYY-MM-DD` dates, and `from` must be earlier than or equal to `to`.
- `limit` is optional, defaults to `25`, and must be between `1` and `100`.
- Validation failures should return HTTP `422` with an `error` of `validation_failed` and field-specific messages.
- Use PDO placeholders for dynamic values.
- Enforce account isolation.
- Avoid duplicate visitor rows.
- Keep ordering deterministic: most recent activity first, then highest page view count, then visitor id.

## Objectives

- Fix the active visitor analytics bug without changing the response contract.
- Implement segment preview using clear validation and safe SQL.
- Keep SQL readable enough for another engineer to review.
- Keep the app runnable through Docker.
- Add or adjust tests if you need more confidence.

## How to Verify

Run:

```bash
docker compose exec app composer test
```

Manual examples:

```bash
curl "http://localhost:8080/api/accounts/1/visitors/active?from=2026-05-01&to=2026-05-15"
```

```bash
curl -X POST "http://localhost:8080/api/accounts/1/segments/preview" \
  -H "Content-Type: application/json" \
  -d '{
    "rules": {
      "visited_path": "/pricing",
      "min_page_views": 2,
      "identified_only": true,
      "from": "2026-05-01",
      "to": "2026-05-15"
    },
    "limit": 25
  }'
```

Public tests are intentionally incomplete. Hidden review checks may verify edge cases, account isolation, SQL safety, and validation consistency.
