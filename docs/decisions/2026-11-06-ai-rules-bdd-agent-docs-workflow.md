# 2026-11-06 — AI rules, BDD QA agent, docs workflow, `/run-dev`

**Status:** Implemented

## Context
The project had no AI-assistant guidance (no `CLAUDE.md`, no rules, no `docs/`). The codebase has drifted from MVC:
`Admin/BookingController.php` is 738 lines; `store()` alone holds ~220 lines of inline validation, a DB transaction and
`Mail::` calls. There is no `app/Services` or `app/Http/Requests`, and tests are placeholders only.

## What we discussed
- **Which AI tools to target** — Cursor + Claude Code, Cursor only, or Claude Code only. → **Claude Code only.** No `.cursor/` files.
- **BDD stack** — Gherkin + Dusk, Gherkin + Playwright (Node), Gherkin + HTTP feature tests (no JS coverage), or scenarios only.
  → **Gherkin `.feature` files + Laravel Dusk**: stays in the PHP toolchain and exercises real JS (calendar, pricing, modals).
- **Legacy fat controllers** — refactor-on-touch, new-code-only, or plan a full refactor. → **New code only.** Existing
  controllers stay as-is unless a refactor is explicitly requested.
- **Docs after every approved plan** — agreed, enforced by a `PostToolUse` hook on plan approval.
- **Every feature/flow needs a BDD test** (added during review) — agreed as the Definition of Done, enforced by a `Stop` hook.
- **`/run-dev` skill** (added during planning) — starts Docker if it is off, then brings up the compose stack.

## Agreed decision
| Area | Artifact |
|---|---|
| Project guide | `CLAUDE.md` |
| Rules | `.claude/rules/` — `senior-laravel.md`, `mvc-architecture.md`, `service-modules.md`, `docs-workflow.md`, `definition-of-done.md`, `testing-bdd.md` |
| QA agent | `.claude/agents/bdd-qa-tester.md` |
| Run skill | `.claude/skills/run-dev/` (`SKILL.md` + `scripts/run-dev.sh`) |
| Hooks | `.claude/settings.json` → `PostToolUse(ExitPlanMode)` docs reminder; `Stop` → `.claude/hooks/require-bdd-test.sh` |
| Dusk infra | `laravel/dusk`, `selenium` + `dusk-app` compose services (profile `testing`), `iconvenue_testing` DB |
| Docs | `docs/README.md`, `docs/architecture.md`, `docs/testing/bdd.md`, this file |

### Naming rule for test cases
Every case is a **verb + objective** from the user's behaviour: `<Actor> <verb>s <object> to <objective>`,
e.g. *Staff records a downpayment to secure a booking*. Details in [testing/bdd.md](../testing/bdd.md).

## BDD scenarios
- Admin signs in to access the dashboard
- Guest submits wrong credentials to see an error

## Scope / Out of scope
- **In:** everything in the table above, plus one smoke feature (sign-in) proving the Dusk pipeline.
- **Out:** refactoring existing controllers, Cursor files, CI pipeline, git commits.

## Files affected
`CLAUDE.md`, `.claude/**` (new), `docs/**` (new), `docker-compose.yml` (added services), `docker/mysql/init/`,
`composer.json`/`composer.lock` (dusk), `tests/DuskTestCase.php`, `tests/Browser/**`, `resources/views/auth/login.blade.php` (`dusk` selectors),
`phpunit.xml` and `tests/TestCase.php` (DB safety), `app/Providers/ImageServiceProvider.php` (redeclare fix), `.claude/hooks/*.sh`.

## Open questions
- CI for Dusk is not set up; tests run locally via Docker only.

## Deviations from plan
- **Incident: dev DB wiped.** During verification, `php artisan test` was run inside the `app` container. Container env vars
  (`DB_CONNECTION=mysql`, `DB_DATABASE=iconvenue`) overrode `phpunit.xml`, so `ImageUploadTest` (`RefreshDatabase`) ran
  `migrate:fresh` on the dev DB. Binary logging was off, so nothing could be replayed. The user chose to re-seed the defaults
  (`db:seed`). `icon (3).sql` (a 2026-06-17 export) is still available if the old records are wanted.
  **Safeguards added:** `phpunit.xml` forces both `<env>` and `<server>` values (Laravel reads `$_SERVER` first, so `<env force>`
  alone isn't enough; checked with a probe test), `tests/TestCase.php` refuses `RefreshDatabase` on anything but in-memory
  SQLite, and `tests/DuskTestCase.php` refuses truncation on anything but `iconvenue_testing`.
- **Legacy fix (blocked all tests):** `app/Providers/ImageServiceProvider.php` guarded namespaced helper functions with a
  global `function_exists()`, so a second app boot in one process fatally failed with "Cannot redeclare". The guards now
  check `__NAMESPACE__.'\\…'`. No behaviour change, since nothing calls the helpers.
- **Legacy tests still failing (out of scope, not caused by this work):** `ImageUploadTest` creates users with `role_id => 1`
  without seeding roles (foreign key failure), and `Feature\ExampleTest` hits `/` without migrations (500). Before today
  they never ran in Docker.
- **`/run-dev` hardening:** it now fails if any service has exited, not only when `app` is unhealthy. The first run showed
  Vite crashing on a broken `node_modules` volume (missing `@rollup/rollup-linux-arm64-musl`). Fixed by recreating the volume.
- **Dusk details:** `DuskTestCase` clears cookies between tests so each scenario starts as a signed-out Guest. Page-object
  aliases use `[dusk="…"]` CSS selectors (nested `@` aliases don't resolve).
- **CLAUDE.md** includes the README content the user asked for, checked against the code. Corrections: Tailwind, jQuery
  and Font Awesome load from CDNs; the `allow_same_day_booking` migration is empty (same-day works by blocking slots that
  have passed); several README links point to files that don't exist.
- **Stop-hook limitation:** it compares the working tree with HEAD, so once any `tests/Browser/` change exists, the gate
  stays open until the next commit.
