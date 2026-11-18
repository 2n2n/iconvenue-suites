# Production mode

Production is live (Cloud Run + MariaDB on `iconvenue-db`, tailnet-only; runbook `docs/deployment.md`). Real bookings
and payments live there.

- **Deploy only with `deploy/cloudrun-deploy.sh`.** It runs, in order:
  1. a **pre-deploy DB backup**; the deploy aborts if the backup fails;
  2. build;
  3. `migrate --force` (applies any pending migrations);
  4. service deploy;
  5. a deploy record in `gs://icon-events-513902-db-backups/deploys/`.

  Never deploy by hand, and never skip or bypass the backup.
- **Confirm with the user before every production deploy, rollback or restore.** Approval for one doesn't cover the
  next.
- **Roll back with the `/rollback-prod` skill.** It always runs a dry run first, then executes with `--yes` only after
  the user confirms that plan. It restores the DB only when the deploy applied migrations.
- **Backups are kept 30 days** in the bucket (lifecycle rule) and 7 days on the VM. A rollback older than that
  can't restore data.
- **Never against production:**
  - `db:seed`, Dusk, `migrate:fresh`, `migrate:refresh` or `migrate:rollback`;
  - `RefreshDatabase`;
  - raw writes to the DB except through a reviewed plan.
- **Migrations must stay additive and backward compatible.** The previous build has to keep working on the new schema
  until a restore. The undo path is the backup restore, not `down()`.
