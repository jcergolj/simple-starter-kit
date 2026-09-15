#!/usr/bin/env bash
set -Eeuo pipefail

PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
worker_file="$PROJECT_ROOT/scripts/steps/09-workers.sh"
horizon_file="$PROJECT_ROOT/config/horizon.php"
queue_file="$PROJECT_ROOT/config/queue.php"

grep -Fq -- '--timeout=60' "$worker_file"
if grep -Fq -- '--timeout=90' "$worker_file"; then
    printf '%s\n' 'FAIL queue worker timeout equals retry_after' >&2
    exit 1
fi

queue_retry_after="$(grep -m1 "DB_QUEUE_RETRY_AFTER" "$queue_file" | sed -E 's/.*,[[:space:]]*([0-9]+)\).*/\1/')"
horizon_timeout="$(grep -m1 "'timeout' =>" "$horizon_file" | sed -E 's/.*=>[[:space:]]*([0-9]+),.*/\1/')"
[[ "$queue_retry_after" -gt 60 ]]
[[ "$horizon_timeout" -lt "$queue_retry_after" ]]

printf '%s\n' "Queue timeout checks passed (worker=60, retry_after=$queue_retry_after, horizon=$horizon_timeout)."
