#!/bin/bash
# Production only (docker-stack.yml, via a Swarm config) -- the local
# docker-compose.yml verification stack deliberately keeps using root for
# simplicity, see docs/DEPLOYMENT.md.
#
# The app tier authenticating as MariaDB root would be more access than it
# needs. This runs once, only against an empty data directory (the same
# docker-entrypoint-initdb.d convention every other init script here uses),
# and creates a scoped user instead. Shell (not plain .sql) specifically so
# it can read the app password from its own secret file at container start
# -- a static .sql file has no way to reference a secret's actual value.
set -e

if [ ! -f /run/secrets/db_password ]; then
    echo "init-app-user.sh: /run/secrets/db_password not present, skipping" >&2
    exit 0
fi

# db_root_password (MYSQL_ROOT_PASSWORD_FILE) is root's own credential, used
# only here to provision the app user, never by the app containers. db_password
# is the app-facing credential this script sets for petra_app -- same name the
# corefront/admin/outflow-cron services' entrypoint-secrets.sh bridges to
# DB_PASSWORD, so no PHP-side change was needed to point the app at a scoped
# user instead of root.
APP_PASSWORD="$(cat /run/secrets/db_password)"

mysql -uroot -p"${MYSQL_ROOT_PASSWORD}" <<-EOSQL
    CREATE USER IF NOT EXISTS 'petra_app'@'%' IDENTIFIED BY '${APP_PASSWORD}';
    GRANT ALL PRIVILEGES ON \`dbPetra\`.* TO 'petra_app'@'%';
    GRANT ALL PRIVILEGES ON \`Admin\`.* TO 'petra_app'@'%';
    FLUSH PRIVILEGES;
EOSQL

echo "init-app-user.sh: petra_app user ready"
