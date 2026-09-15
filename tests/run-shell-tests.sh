#!/usr/bin/env bash
set -Eeuo pipefail

PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

shell_tests=(
    cloudflare-dns-test.sh
    database-bootstrap-test.sh
    deploy-workers-test.sh
    env-serialization-test.sh
    installation-scripts-test.sh
    permissions-test.sh
    queue-timeout-test.sh
)

for test_file in "${shell_tests[@]}"; do
    printf 'Running %s\n' "$test_file"
    bash "$PROJECT_ROOT/tests/$test_file"
done

printf '%s\n' 'All shell boundary tests passed.'
