---
description: Product-owner review of the booking system — analyze the codebase and update docs/BUSINESS_RULES.md without changing code
---

Review the Icon Venue & Suites booking system from a product-owner view.

Topic or question for this review: $ARGUMENTS

## Working agreement

- Role: solutions architect / senior developer reviewing the system from a product-owner view.
- Analyze facts from the codebase only. Do not assume.
- Do not modify code. Only `docs/BUSINESS_RULES.md` is edited.
- If a process or area looks inconsistent, do not prescribe a fix — point to where to look so the team can decide.
- Keep content high level: business process, rules, unique constraints. No technical limitations.
- Goal: evolve the product for different hotel/inn processes while staying backward compatible with existing features.

## Steps

1. Read `docs/BUSINESS_RULES.md` first; it is the current source of truth.
2. Investigate the topic in the codebase and verify each statement you add against the code.
3. Update the relevant sections of `docs/BUSINESS_RULES.md` (rules, observations, open questions), bump "Last updated", and add a dated entry to the "Decision log" when a decision or new initiative is recorded.
4. Summarize what changed in the document and list any open questions that need a business decision.
