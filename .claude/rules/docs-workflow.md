# Plan → approve → document

`docs/` is the record of everything we discussed and agreed. An approved plan becomes a decision entry **before the first line of code**.

1. **Plan.** Every plan includes a **BDD scenarios** section listing the scenario names (`<Actor> <verb>s <object> to <objective>`) the work will ship with, or an explicit exemption (refactor / infra / config + reason).
2. **On approval** (ExitPlanMode accepted), first actions:
   - Create `docs/decisions/YYYY-MM-DD-<slug>.md` from the template in `docs/README.md`. Capture the discussion, not just the outcome: every option weighed, what the user chose, what was rejected and why.
   - Add it to the index in `docs/README.md`, newest first.
   - Update each living doc the change touches: `docs/architecture.md`, `docs/testing/bdd.md`, `docs/modules/<module>.md` (create on first change to a module).
   Done when the entry exists, is indexed, and a reader who missed the conversation could reconstruct the decision from it.
3. **Implement.**
4. **Close out.** Set the entry's **Status** to `Implemented` and fill **Deviations from plan** with anything that changed during implementation (or "None").

Later changes of mind get a new entry that links to the old one; mark the old one `Superseded by <link>`.
