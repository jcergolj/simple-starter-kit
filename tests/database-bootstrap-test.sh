#!/usr/bin/env bash
set -Eeuo pipefail

PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

run_step() {
    local php_service="$1" driver="$2"
    PHP_FPM_SERVICE="$php_service" DATABASE_DRIVER="$driver" APP_FOLDER=/var/www/test \
        DEPLOY_USER=deployer bash -c '
            source "$1/scripts/lib/common.sh"
            source "$1/scripts/steps/05-database.sh"
            sudo() {
                printf "sudo %s\n" "$*"
            }
            step_database
        ' -- "$PROJECT_ROOT"
}

assert_package() {
    local expected="$1" php_service="$2" driver="$3" output
    output="$(run_step "$php_service" "$driver")"
    [[ "$output" == *"$expected"* ]] || {
        printf 'FAIL %s/%s: expected %s\n%s\n' "$php_service" "$driver" "$expected" "$output" >&2
        exit 1
    }
    [[ "$output" != *fpm-* ]] || {
        printf 'FAIL %s/%s: package retained fpm suffix\n%s\n' "$php_service" "$driver" "$output" >&2
        exit 1
    }
}

assert_package 'sudo apt-get install -y sqlite3 php8.4-sqlite3' php8.4-fpm sqlite
assert_package 'sudo apt-get install -y php8.4-mysql' php8.4-fpm mysql
assert_package 'sudo apt-get install -y sqlite3 php8.2-sqlite3' php8.2-fpm sqlite
assert_package 'sudo apt-get install -y php8.1-mysql' php8.1-fpm mysql

if run_step php-fpm sqlite >/dev/null 2>&1; then
    printf 'FAIL malformed PHP-FPM service was accepted\n' >&2
    exit 1
fi

printf 'PASS database package selection\n'
