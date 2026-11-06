---
name: run-dev
description: Run the app locally in Docker — starts the Docker daemon if it is off, then brings up the compose stack and waits until the app is healthy. Use for "run the app", "start dev", "spin up docker", or before Dusk tests (--testing).
argument-hint: "[--build] [--tools] [--testing]"
---

Start the dev stack with the bundled script — it handles the Docker daemon, compose and the health wait in one bounded, idempotent run:

```bash
.claude/skills/run-dev/scripts/run-dev.sh $ARGUMENTS
```

| Flag | Effect |
|---|---|
| `--build` | Rebuild images first (after Dockerfile or `composer.json` changes) |
| `--tools` | Also start phpMyAdmin on :8080 |
| `--testing` | Also start `selenium` + `dusk-app` for Dusk tests |

Run it with a Bash timeout of 600000 ms — a cold Docker start plus first boot (composer install, migrate, seed) can take several minutes.

**On success**, relay the URL list the script prints, plus the seeded logins: Admin `admin@iconvenue.com` / `admin123`, Staff `staff@iconvenue.com` / `staff123`.

**On failure**, the script exits non-zero with the reason. Diagnose once before reporting:
- App unhealthy/exited → `docker compose logs --tail=80 app` and report the root error line.
- Port already allocated → `lsof -nP -iTCP:<port> -sTCP:LISTEN` and name the process holding it.
- Daemon timeout → tell the user to open Docker Desktop and check for an error or update prompt.
Report the cause and the fix you recommend; re-run only after the cause is addressed.
