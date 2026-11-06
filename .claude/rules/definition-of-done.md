# Definition of Done — every feature ships with a BDD test

A feature or user flow is **done** when its behaviour is pinned by a passing end-to-end BDD test:

1. Gherkin scenario(s) in `tests/Browser/features/{module}/{verb-objective}.feature`.
2. Matching Dusk test(s) in `tests/Browser/{Module}/`, each linked by a `@scenario` docblock.
3. `docker compose exec dusk-app php artisan dusk` is green for those tests.

**Counts as a feature/flow:** a new route, controller action, service use case, or user-visible Blade change. Changing an existing flow means updating its scenarios too.
**Exempt:** refactors with no behaviour change, infra/config/docs work — record the exemption under *BDD scenarios* in the decision entry.

## How
After implementing, delegate to the **`bdd-qa-tester`** agent with: the feature, the actors involved, the scenario names agreed in the plan, and the files changed. Run the tests it writes and report the result. Bugs it reports in app code are yours to fix — then re-run.

A `Stop` hook (`.claude/hooks/require-bdd-test.sh`) blocks finishing a turn once when `app/`, `routes/` or `resources/views/` changed with no `tests/Browser/` change. Answer it by adding the tests, or by stating the exemption.
