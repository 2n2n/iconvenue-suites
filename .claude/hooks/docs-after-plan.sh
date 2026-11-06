#!/usr/bin/env bash
# PostToolUse(ExitPlanMode) — docs workflow (.claude/rules/docs-workflow.md).
# Fires after a plan is approved and reminds Claude to write the decision entry before code.
cat >/dev/null
today="$(date +%Y-%m-%d)"
cat <<JSON
{"hookSpecificOutput":{"hookEventName":"PostToolUse","additionalContext":"Plan approved. Before writing any code, follow .claude/rules/docs-workflow.md: create docs/decisions/${today}-<slug>.md from the template in docs/README.md (discussion, options weighed, agreed decision, BDD scenarios, scope, files), add it to the docs/README.md index, and update affected living docs."}}
JSON
