#!/usr/bin/env bash
# Stop hook — Definition of Done gate (.claude/rules/definition-of-done.md).
# Blocks ending a turn ONCE when app/, routes/ or resources/views/ have uncommitted
# changes but tests/Browser/ has none. stop_hook_active=true (Claude already answered
# the block, e.g. by stating an exemption) lets the turn end — so it never loops.
#
# Limitation: compares against the working tree vs HEAD, so after a tests/Browser/
# change exists, the gate stays open until the next commit.

input="$(cat)"

if printf '%s' "$input" | grep -Eq '"stop_hook_active"[[:space:]]*:[[:space:]]*true'; then
  exit 0
fi

cd "${CLAUDE_PROJECT_DIR:-$(git rev-parse --show-toplevel 2>/dev/null)}" 2>/dev/null || exit 0
git rev-parse --is-inside-work-tree >/dev/null 2>&1 || exit 0

changed="$(git status --porcelain --untracked-files=all | sed -E 's/^.{3}//; s/^.* -> //')"

feature_changes="$(printf '%s\n' "$changed" | grep -E '^(app/|routes/|resources/views/)' || true)"
bdd_changes="$(printf '%s\n' "$changed" | grep -E '^tests/Browser/' || true)"

if [ -n "$feature_changes" ] && [ -z "$bdd_changes" ]; then
  files="$(printf '%s' "$feature_changes" | head -10 | tr '\n' ' ')"
  cat <<JSON
{"decision":"block","reason":"Definition of Done: feature/flow files changed without a BDD test (${files}). Delegate to the bdd-qa-tester agent to add the .feature + Dusk test and run it, or state why this change is exempt (refactor with no behaviour change / infra / config) and record the exemption in the docs decision entry."}
JSON
fi

exit 0
