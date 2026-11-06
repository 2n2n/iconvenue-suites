# 2026-11-06: Reconcile with the remote CLAUDE.md and business-rules docs

**Status:** Implemented

## Context
The push to `git@github.com:2n2n/iconvenue-suites.git` was rejected because the histories had split. Remote `main` already had
PR #1 (30 Sep 2026): a `CLAUDE.md`, `docs/BUSINESS_RULES.md` and the `/product-review` command. Its `CLAUDE.md` contradicted
two decisions in [2026-11-06: AI rules, BDD QA agent…](2026-11-06-ai-rules-bdd-agent-docs-workflow.md).

## What we discussed
- **Integration:** rebase, a branch + PR, or a merge commit. → **Rebase our 2 commits onto `origin/main`**, then a normal push
  (no force).
- **E2E stack:** the remote planned *Playwright + playwright-bdd in `e2e/`, `.env.e2e`, `venue_booking_test`*. → **Keep Gherkin +
  Laravel Dusk.** It's already built and passing. The Playwright plan is superseded.
- **Architecture:** the remote said *"Controllers are fat… follow the existing pattern unless asked to refactor"*. → **Today's
  rules win**: new code uses strict MVC and service modules, and legacy controllers stay untouched unless a refactor is approved.

## Agreed decision
`CLAUDE.md` is merged as follows. The remote's richer domain sections are kept as they were (pricing duplication, suite rules,
the auto-complete middleware and command, images/uploads, migration conventions, the `@docs/BUSINESS_RULES.md` import, and
`/product-review`). Added to them: the non-negotiables, Docker commands, the Dusk testing section, troubleshooting and pointers
to `.claude/`. Two remote lines were replaced: the Playwright E2E line (now Dusk) and "follow the existing pattern" (now
"legacy stays, new code uses services").

`docs/BUSINESS_RULES.md` is unchanged. Its business rules don't conflict. Backlog epic **E0, E2E foundation**, is partly
delivered by the Dusk setup: the runner, an isolated test DB and the seeders.

## BDD scenarios
Exempt (docs/config only, no behaviour change).

## Scope / Out of scope
- **In:** the `CLAUDE.md` merge, the docs index, this entry.
- **Out:** changes to `BUSINESS_RULES.md`, and the remote's unmerged branches (`project-claude-md` has 2 more commits;
  `feature/add-and-remove-inclusions-in-admin-side`).

## Files affected
`CLAUDE.md`, `docs/README.md`, this file.

## Open questions
- `origin/project-claude-md` has 2 unmerged commits (upgrade credit rule, client requirement mapping, test environment
  wording). Its test-environment line still says Playwright and should be updated to Dusk when that branch is merged.

## Deviations from plan
None.
