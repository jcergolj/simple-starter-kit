#!/usr/bin/env bash
set -Eeuo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

database_script="$(<"$ROOT_DIR/scripts/steps/05-database.sh")"
common_script="$(<"$ROOT_DIR/scripts/lib/common.sh")"
permissions_script="$(<"$ROOT_DIR/scripts/steps/06-permissions.sh")"
workers_script="$(<"$ROOT_DIR/scripts/steps/09-workers.sh")"
deploy_config="$(<"$ROOT_DIR/deploy.php")"
backup_config="$(<"$ROOT_DIR/config/backup.php")"

[[ "$database_script" == *'php_package_prefix="${PHP_FPM_SERVICE%-fpm}"'* ]]
[[ "$database_script" == *'"${php_package_prefix}-sqlite3"'* ]]
[[ "$database_script" == *'"${php_package_prefix}-mysql"'* ]]
[[ "$common_script" == *'while IFS= read -r line || [[ -n "$line" ]]'* ]]
[[ "$common_script" != *'sed -i "s|^${key}=.*'* ]]
[[ "$permissions_script" == *'chmod 2770'* ]]
[[ "$permissions_script" == *'chmod 660'* ]]
[[ "$workers_script" == *'--timeout=60'* ]]
[[ "$deploy_config" == *"task('deploy:activate-workers'"* ]]
[[ "$deploy_config" == *"after('deploy:symlink', 'deploy:activate-workers')"* ]]
[[ "$deploy_config" == *"after('deploy:activate-workers', 'deploy:restart-workers')"* ]]
[[ "$backup_config" == *"env('DB_CONNECTION', 'sqlite')"* ]]
[[ "$backup_config" != *"'databases' => ["$'\n'"                'sqlite'"* ]]

printf '%s\n' 'Installation script checks passed.'
