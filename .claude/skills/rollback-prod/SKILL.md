---
name: rollback-prod
description: Roll production back to the build before a deploy, and restore that deploy's pre-deploy database backup when the deploy applied migrations. Use for "rollback", "revert the deploy", "undo the last deploy", "go back to the previous build", or "restore the backup from before the deploy".
argument-hint: "[<deploy-tag>] [--restore-db]"
---

Rolls back production (Cloud Run + MariaDB on `iconvenue-db`) using the deploy record that `deploy/cloudrun-deploy.sh` writes to `gs://icon-events-513902-db-backups/deploys/<tag>.json`. Records and backups are kept for 30 days. Decision: `docs/decisions/2026-11-17-predeploy-backup-and-rollback.md`.

## Steps

1. **Dry run first.** It changes nothing:
   ```bash
   .claude/skills/rollback-prod/scripts/rollback-prod.sh $ARGUMENTS
   ```
   With no tag, it undoes the newest deploy.
2. **Show the user the plan exactly as printed:**
   - the code change (current revision → previous revision);
   - the **Database** line.

   If it says **RESTORE**, state plainly that every booking, payment and change made since the deploy time will be lost, and that a safety backup is taken first.
3. **Get the user's explicit "yes"** for that plan in this conversation. An earlier approval does not count.
4. **Execute.** Use a Bash timeout of 600000 ms:
   ```bash
   .claude/skills/rollback-prod/scripts/rollback-prod.sh $ARGUMENTS --yes
   ```
5. **Report the result:**
   - the revision now serving;
   - whether the DB was restored;
   - the `/up` status;
   - the safety-backup object and how to undo.

## Rules

- **The DB restore is decided by the deploy record.** It happens when that deploy applied migrations. Add `--restore-db` only if the user explicitly asks to restore data from a code-only deploy.
- **Never run with `--yes` without step 3.**
- **If the script refuses, report its reason and stop.** Examples: no record, previous revision gone, or the backup older than 30 days. Don't work around it by hand.
- **To check a backup restores cleanly without touching production,** run `--test-restore restore_check`. It restores into a scratch DB on the VM, verifies it and drops it.
- **IAP SSH and gcloud must work from this machine.** This Mac doesn't need the tailnet; the script checks `/up` from the VM if needed.
