#!/usr/bin/env bash
# Start the Icon Venue & Suites dev stack in Docker.
# Starts the Docker daemon first if it is not running. Idempotent: safe to re-run.
#
# Usage: run-dev.sh [--build] [--tools] [--testing]
#   --build    rebuild images before starting
#   --tools    also start phpMyAdmin (profile: tools)
#   --testing  also start selenium + dusk-app for Dusk tests (profile: testing)

set -euo pipefail

DAEMON_TIMEOUT=120
HEALTH_TIMEOUT=180
APP_CONTAINER=iconvenue-app

ROOT="$(git -C "$(dirname "$0")" rev-parse --show-toplevel)"
cd "$ROOT"

build=() profiles=() testing=false tools=false
for arg in "$@"; do
  case "$arg" in
    --build)   build=(--build) ;;
    --tools)   profiles+=(--profile tools); tools=true ;;
    --testing) profiles+=(--profile testing); testing=true ;;
    *) echo "Unknown option: $arg (expected --build, --tools, --testing)" >&2; exit 2 ;;
  esac
done

log() { printf '\033[1;34m[run-dev]\033[0m %s\n' "$*"; }
fail() { printf '\033[1;31m[run-dev]\033[0m %s\n' "$*" >&2; exit 1; }

# 1. Docker daemon
if docker info >/dev/null 2>&1; then
  log "Docker daemon is running."
else
  log "Docker daemon is not running — starting it."
  case "$(uname -s)" in
    Darwin)
      if [ -d /Applications/Docker.app ] || [ -d "$HOME/Applications/Docker.app" ]; then
        open -a Docker
      elif [ -d /Applications/OrbStack.app ]; then
        open -a OrbStack
      else
        fail "No Docker Desktop or OrbStack found in /Applications. Install one and re-run."
      fi
      ;;
    Linux)
      sudo systemctl start docker || fail "Could not start docker via systemctl."
      ;;
    *)
      fail "Unsupported OS $(uname -s): start Docker manually and re-run."
      ;;
  esac

  log "Waiting up to ${DAEMON_TIMEOUT}s for the Docker daemon..."
  waited=0
  until docker info >/dev/null 2>&1; do
    if [ "$waited" -ge "$DAEMON_TIMEOUT" ]; then
      fail "Docker daemon did not become ready within ${DAEMON_TIMEOUT}s. Open Docker Desktop and check for errors or pending updates."
    fi
    sleep 2; waited=$((waited + 2))
  done
  log "Docker daemon is ready (${waited}s)."
fi

# 2. Compose stack
log "docker compose ${profiles[*]:-} up -d ${build[*]:-}"
docker compose "${profiles[@]+"${profiles[@]}"}" up -d "${build[@]+"${build[@]}"}"

# 3. Wait for the app healthcheck (first boot runs composer install + migrations + seed)
log "Waiting up to ${HEALTH_TIMEOUT}s for ${APP_CONTAINER} to be healthy..."
waited=0
while :; do
  status="$(docker inspect -f '{{if .State.Health}}{{.State.Health.Status}}{{else}}{{.State.Status}}{{end}}' "$APP_CONTAINER" 2>/dev/null || echo missing)"
  case "$status" in
    healthy) break ;;
    exited|dead|missing) fail "${APP_CONTAINER} is ${status}. Inspect: docker compose logs --tail=80 app" ;;
  esac
  if [ "$waited" -ge "$HEALTH_TIMEOUT" ]; then
    fail "${APP_CONTAINER} still '${status}' after ${HEALTH_TIMEOUT}s. Inspect: docker compose logs --tail=80 app"
  fi
  sleep 3; waited=$((waited + 3))
done
log "App is healthy."

# 4. Every other long-running service must still be up (e.g. vite can crash on a broken node_modules volume)
sleep 5
exited="$(docker compose "${profiles[@]+"${profiles[@]}"}" ps -a --status exited --status dead --format '{{.Service}}')"
if [ -n "$exited" ]; then
  for svc in $exited; do
    printf '\033[1;31m[run-dev]\033[0m service %s exited. Last log lines:\n' "$svc" >&2
    docker compose logs --tail=15 "$svc" >&2 || true
  done
  fail "Service(s) exited: $(echo $exited | tr '\n' ' '). Inspect: docker compose logs --tail=80 <service>"
fi

# 5. Summary
docker compose "${profiles[@]+"${profiles[@]}"}" ps --format 'table {{.Service}}\t{{.Status}}\t{{.Ports}}'
echo
echo "  App         http://localhost:8000"
echo "  Admin login http://localhost:8000/login"
echo "  Vite (HMR)  http://localhost:5173"
echo "  Mailpit     http://localhost:8025"
$tools   && echo "  phpMyAdmin  http://localhost:8080"
$testing && echo "  Dusk        docker compose exec dusk-app php artisan dusk"
exit 0
