# Project docs

The written record of what we planned, discussed and agreed. Every approved plan gets a decision entry **before** code is written (see `.claude/rules/docs-workflow.md`).

## Index
- [Architecture](architecture.md) — MVC + modular services, legacy policy
- [BDD testing](testing/bdd.md) — Gherkin + Dusk conventions, how to run
- `modules/<module>.md` — living docs per module (create when a module first changes)

### Decisions (newest first)
- [2026-11-06 — AI rules, BDD QA agent, docs workflow, `/run-dev`](decisions/2026-11-06-ai-rules-bdd-agent-docs-workflow.md)

## Decision entry template
File: `docs/decisions/YYYY-MM-DD-<slug>.md`. Add it to the index above, newest first.

```markdown
# YYYY-MM-DD — <Title>

**Status:** Approved | Implemented | Superseded by <link>

## Context
Why this change: the problem, what prompted it, the intended outcome.

## What we discussed
Each option considered → what was chosen and why; rejected options and the reason.

## Agreed decision
The approach, as approved.

## BDD scenarios
- <Actor> <verb>s <object> to <objective>
(or: "Exempt — <refactor | infra | config>: <reason>")

## Scope / Out of scope

## Files affected

## Open questions

## Deviations from plan
Filled in when implementation finishes.
```
