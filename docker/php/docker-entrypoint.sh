#!/bin/sh
set -eu

fix_permissions() {
    target="$1"
    if [ -e "$target" ]; then
        find "$target" -exec chown www-data:www-data {} +
        find "$target" -exec chmod ug+rwX {} +
    fi
}

# chown/chmod require root — skip in dev where the container runs as the host UID
if [ "$(id -u)" = "0" ]; then
    fix_permissions /var/www/html/storage
    fix_permissions /var/www/html/bootstrap/cache
fi

exec "$@"
