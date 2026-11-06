# Project docs

The written record of what we planned, discussed and agreed. Every approved plan gets a decision entry **before** code is written (see `.claude/rules/docs-workflow.md`).

## Index
- [Business rules](BUSINESS_RULES.md): business process, booking rules, approved initiatives, business decision log. Maintained with `/product-review`.
- [Architecture](architecture.md): MVC plus modular services, and the legacy policy
- [BDD testing](testing/bdd.md) — Gherkin + Dusk conventions, how to run
- [Deployment](deployment.md): production on GCP (Cloud Run + MariaDB on a free-tier VM, tailnet-only via a Tailscale gateway), the deploy, backup and restore runbook
- [Tailscale guide](tailscale-guide.md): registering the server, inviting staff and approving devices (Personal plan)
- [Research briefs](research/README.md): approved outside context from the `researcher` agent
- `modules/<module>.md` — living docs per module (create when a module first changes)

### Decisions (newest first)
- [2026-11-06 — Tailnet-only access: Tailscale gateway on the DB VM → Cloud Run](decisions/2026-11-06-tailnet-only-access.md)
- [2026-11-06 — Production on Cloud Run + MariaDB on a free-tier VM (public, no Tailscale)](decisions/2026-11-06-production-cloud-run.md)
- [2026-11-06 — Production deployment on GCP (private, Tailscale-only)](decisions/2026-11-06-production-deployment-gcp.md) — superseded
- [2026-11-06: Reconcile with the remote CLAUDE.md (Dusk over Playwright, services for new code)](decisions/2026-11-06-reconcile-remote-claude-md.md)
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
