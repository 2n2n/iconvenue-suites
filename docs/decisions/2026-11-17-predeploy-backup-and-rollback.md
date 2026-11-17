# 2026-11-17 — Pre-deploy database backup and `/rollback-prod`

**Status:** Implemented

## Context
Production is live: Cloud Run, plus MariaDB on `iconvenue-db`, reachable from the tailnet only. Until now
`deploy/cloudrun-deploy.sh` built the image, ran `migrate --force` and deployed the service, with three gaps:
- **No backup before deploying.** The only dump was the nightly 02:00 one, so a bad migration could cost up to a day of
  bookings.
- **No record of what a deploy replaced.** Nothing saved the previous revision, the previous image or the backup to go
  back to.
- **Rollback was manual and partial.** It was one `update-traffic` command. The scheduled jobs kept the new image, and
  undoing the database was left to the operator.

The user wants production mode:
1. every deploy backs up the database first, then deploys and applies any migrations;
2. a skill rolls back to the previous build and restores that backup;
3. backups are kept for 30 days.

## What we discussed
- **Where the pre-deploy backup runs.** We reuse `deploy/backup-db.sh` and pipe the repo copy over IAP SSH with a label
  (`predeploy-<tag>`). The committed version always runs, so the copy installed on the VM can't drift. If the backup
  fails, the deploy aborts.
- **Retention.** The backup bucket's lifecycle already deletes objects after 30 days (`deploy/gcp-setup.sh`), so labelled
  backups and deploy records inherit it. Copies on the VM stay at 7 days.
- **Deploy record.** Each deploy writes `deploys/<tag>.json` to the backup bucket. It records the previous
  revision/image, the backup object and the migration counts before and after. The rollback reads it, so nobody has to
  guess.
- **The database on rollback.** Restoring erases bookings and payments entered after the deploy. Options:
  - always restore;
  - **restore only if that deploy applied migrations** (chosen);
  - ask each time.

  The user chose "only if migrated". A code-only deploy rolls back without touching data. `--restore-db` forces a
  restore.
- **How to restore.** Dumps don't drop tables that a newer migration created. The restore therefore recreates the
  `iconvenue` database (utf8mb4_unicode_ci), then loads the dump. The DB-level grant for `iconvenue@10.128.%` survives
  the drop.
- **Safety.**
  - The rollback first takes a `prerollback-<tag>` backup, so the rollback itself can be undone.
  - Without `--yes` it only prints the plan.
  - Claude must show that plan and get explicit confirmation before running it.

## Agreed decision
- **`deploy/backup-db.sh [label]`.** It takes an optional label (`iconvenue-<label>-<ts>.sql.gz`) and prints the
  `gs://` object as its last line.
- **`deploy/cloudrun-deploy.sh`** runs these steps in order:
  1. capture the serving revision and image;
  2. pre-deploy backup (abort on failure), plus the migration count;
  3. build;
  4. migrate job;
  5. service;
  6. scheduled jobs;
  7. migration count again;
  8. deploy record (also written when the migrate job fails, with `status: migrate-failed`);
  9. health check.
- **`/rollback-prod` skill** (`.claude/skills/rollback-prod/`):
  1. dry run by default;
  2. `--yes` executes: safety backup → traffic to the previous revision → jobs re-pointed to the previous image →
     DB restore when the record says `migrated` (or `--restore-db`) → health check.
- **New rule `.claude/rules/production.md`.** Production-mode rules for Claude.

## BDD scenarios
Exempt (infra/ops). These are deploy scripts, a Claude skill, rules and docs only, with no app routes, controllers,
views or behaviour changes.

## Scope / Out of scope
- **In scope:** DB backup before each deploy, deploy records, code + DB rollback of the last deploy (or a named one).
- **Out of scope:**
  - backing up the uploads bucket (no versioning yet);
  - rolling back further than the images Artifact Registry keeps (the newest 3);
  - maintenance mode during a restore (the app shows errors for the few seconds of the restore).

## Files affected
- `deploy/backup-db.sh`, `deploy/cloudrun-deploy.sh`
- `.claude/skills/rollback-prod/SKILL.md`, `.claude/skills/rollback-prod/scripts/rollback-prod.sh`
- `.claude/rules/production.md`, `.claude/settings.json`
- `CLAUDE.md`, `docs/deployment.md`, `docs/README.md`

## Open questions
- Should the uploads bucket get object versioning so images can be rolled back too?

## Deviations from plan
- **The restore streams the dump from the operator's machine instead of downloading it on the VM.** The VM's service
  account only has `storage.objectCreator` on the backup bucket and can't read backups. We kept that write-only
  property. The dump is checked locally, then on the VM in a temp file, before the database is dropped.
- **`cloudrun-deploy.sh` now runs `update-traffic --to-latest` after the service deploy.** After a rollback, traffic is
  pinned to a revision, and a plain `gcloud run deploy` would not move it to the new build.
- **Testing flags added to the rollback script:**
  - `--record <file>` (dry run only);
  - `--test-restore <db>`, which restores into a scratch DB and drops it.
- **The copy of `backup-db.sh` installed on the VM was not updated.** Only the nightly cron uses it, and the unlabelled
  behaviour is unchanged. Deploys and rollbacks pipe the repo copy.
- **Verified 2026-11-17:**
  - a labelled backup uploaded and its object path was parsed;
  - a test restore into `restore_check` (18 tables, 31 migrations, matching production), then dropped;
  - dry-run plans for migrated and non-migrated records.

  No live deploy or rollback was run as part of this change.
