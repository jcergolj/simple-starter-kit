#!/usr/bin/env bash

step_database() {
    local php_package_prefix="${PHP_FPM_SERVICE%-fpm}"
    [[ "$php_package_prefix" =~ ^php[0-9]+([.][0-9]+)?$ ]] ||
        die "Unsupported PHP-FPM service name: $PHP_FPM_SERVICE"

    if [[ "$DATABASE_DRIVER" == sqlite ]]; then
        sudo apt-get install -y sqlite3 "${php_package_prefix}-sqlite3"
        sudo install -d -m 2775 -o "$DEPLOY_USER" -g www-data "$APP_FOLDER/shared/database"
        if [[ ! -f "$APP_FOLDER/shared/database/database.sqlite" ]]; then
            sudo install -m 664 -o "$DEPLOY_USER" -g www-data /dev/null \
                "$APP_FOLDER/shared/database/database.sqlite"
        fi
        ok 'SQLite database is ready'
        return
    fi

    sudo apt-get install -y "${php_package_prefix}-mysql"
    ok 'PHP MySQL driver is ready; the configured MySQL database will be used'
}
