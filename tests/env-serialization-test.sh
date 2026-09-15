#!/usr/bin/env bash
set -Eeuo pipefail

PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
TEST_DIR="$(mktemp -d)"
trap 'rm -rf "$TEST_DIR"' EXIT

mkdir -p "$TEST_DIR/shared"
printf '%s\n' 'SECRET="old value"' 'UNCHANGED="keep me"' > "$TEST_DIR/shared/.env"

source "$PROJECT_ROOT/scripts/lib/common.sh"
APP_FOLDER="$TEST_DIR"

sudo() {
    "$@"
}

secret='quote" slash\ amp&pipe| dollar$HOME interpolation${APP_NAME} spaces'
set_env_value SECRET "$secret"
set_env_value NEW_SECRET "$secret"

php -r '
require $argv[1];
$values = Dotenv\Dotenv::parse(file_get_contents($argv[2]));
foreach (["SECRET", "NEW_SECRET"] as $key) {
    if (($values[$key] ?? null) !== $argv[3]) {
        fwrite(STDERR, "FAIL {$key} did not round-trip\n");
        exit(1);
    }
}
if (($values["UNCHANGED"] ?? null) !== "keep me") {
    fwrite(STDERR, "FAIL unrelated dotenv entry changed\n");
    exit(1);
}
' "$PROJECT_ROOT/vendor/autoload.php" "$TEST_DIR/shared/.env" "$secret"

if grep -qF "$secret" "$TEST_DIR/shared/.env"; then
    printf '%s\n' 'FAIL raw secret appeared in diagnostics check' >&2
    exit 1
fi

printf '%s\n' 'Environment serialization checks passed.'
