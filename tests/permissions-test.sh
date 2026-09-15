#!/usr/bin/env bash
set -Eeuo pipefail

PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
temporary_directory="$(mktemp -d)"
trap 'rm -rf "$temporary_directory"' EXIT

mkdir -p "$temporary_directory/shared/storage"
touch "$temporary_directory/shared/.env" \
    "$temporary_directory/shared/database.sqlite" \
    "$temporary_directory/shared/storage/log.txt"

source "$PROJECT_ROOT/scripts/lib/common.sh"
source "$PROJECT_ROOT/scripts/steps/06-permissions.sh"
APP_FOLDER="$temporary_directory"
DEPLOY_USER="$(id -un)"
current_group="$(id -gn)"

sudo() {
    if [[ "$1" == chown ]]; then
        return 0
    fi
    "$@"
}

step_permissions >/dev/null

assert_mode() {
    local expected="$1" path="$2" actual
    actual="$(stat -c '%a' "$path")"
    [[ "$actual" == "$expected" ]] || {
        printf 'FAIL %s: expected mode %s, got %s\n' "$path" "$expected" "$actual" >&2
        exit 1
    }
}

assert_mode 2770 "$temporary_directory/shared"
assert_mode 2770 "$temporary_directory/shared/storage"
assert_mode 660 "$temporary_directory/shared/database.sqlite"
assert_mode 660 "$temporary_directory/shared/storage/log.txt"
assert_mode 640 "$temporary_directory/shared/.env"

if command -v runuser >/dev/null 2>&1 && id nobody >/dev/null 2>&1; then
    if runuser -u nobody -- cat "$temporary_directory/shared/database.sqlite" >/dev/null 2>&1; then
        printf 'FAIL unrelated user can read the SQLite database\n' >&2
        exit 1
    fi
fi

printf 'PASS shared permission hardening (%s group)\n' "$current_group"
