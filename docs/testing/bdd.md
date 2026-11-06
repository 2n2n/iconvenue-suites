# BDD testing — Gherkin + Laravel Dusk

## Definition of Done
A feature or user flow (new route, controller action, service use case, or user-visible Blade change) ships with:
1. a Gherkin scenario in `tests/Browser/features/{module}/{verb-objective}.feature`, and
2. a passing Dusk test in `tests/Browser/{Module}/` linked to it by a `@scenario` docblock.

Exempt: refactors with no behaviour change, infra/config work — state the exemption in the decision entry.
The `bdd-qa-tester` agent writes these; a `Stop` hook blocks finishing when `app/`, `routes/` or `resources/views/` changed without a `tests/Browser/` change.

## Naming — verbs with objectives
| Item | Pattern | Example |
|---|---|---|
| Feature | Verb phrase + `In order to / As a / I want to` | `Feature: Book a venue` |
| Scenario | `<Actor> <verb>s <object> to <objective>` | `Staff records a downpayment to secure a booking` |
| Dusk method | scenario in snake_case, `test_` prefix | `test_staff_records_a_downpayment_to_secure_a_booking` |
| Feature file | kebab-case verb + objective | `features/payment/record-downpayment-to-secure-booking.feature` |

Actors: **Guest** (public site), **Staff**, **Admin**. Steps describe what the user sees and does — no URLs, tables or HTTP verbs.

```gherkin
Feature: Sign in to manage bookings
  In order to manage bookings and payments
  As a Staff or Admin user
  I want to sign in to the admin panel

  Scenario: Admin signs in to access the dashboard
    Given I am an active Admin
    When I sign in with my email and password
    Then I see the dashboard
```

```php
/** @scenario features/auth/sign-in-to-manage-bookings.feature:6 */
public function test_admin_signs_in_to_access_the_dashboard(): void
```

## Layout
```
tests/Browser/
  features/{module}/*.feature   ← source of truth (Gherkin)
  {Module}/*Test.php            ← Dusk tests mirroring the scenarios
  Pages/*.php                   ← Page objects
```
Select elements with `dusk="..."` attributes in Blade (`@dusk` selectors), never CSS classes.

## Database safety
Tests must never touch the dev DB `iconvenue`. It was wiped once (see the [2026-11-06 decision](../decisions/2026-11-06-ai-rules-bdd-agent-docs-workflow.md)).
- **PHPUnit:** `phpunit.xml` forces in-memory SQLite through both `<env force="true">` and `<server force="true">`. Docker env vars sit in `$_SERVER`, which Laravel reads first. `tests/TestCase.php::beforeRefreshingDatabase()` throws on any other connection.
- **Dusk:** `tests/DuskTestCase.php::beforeTruncatingDatabase()` throws unless the database is `iconvenue_testing`.

## Running (Docker)
Dusk runs in a dedicated `dusk-app` service against the `iconvenue_testing` database, so the dev DB is never truncated. (The `app` container's env vars would override `.env.dusk.local`, which is why Dusk must not run there.)

```bash
# one-time, existing MariaDB volume only (fresh volumes run docker/mysql/init/ automatically)
docker compose exec mysql mariadb -uroot -prootsecret -e "CREATE DATABASE IF NOT EXISTS iconvenue_testing; GRANT ALL ON iconvenue_testing.* TO 'iconvenue'@'%';"

docker compose --profile testing up -d          # or: /run-dev --testing
docker compose exec dusk-app php artisan dusk   # all scenarios
docker compose exec dusk-app php artisan dusk --filter=signs_in
```
Screenshots of failures: `tests/Browser/screenshots/`. Emails: Mailpit at http://localhost:8025 (API `http://mailpit:8025/api/v1/messages` from inside the network).
