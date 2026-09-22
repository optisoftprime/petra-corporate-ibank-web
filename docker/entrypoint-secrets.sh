#!/bin/sh
# Bridges Docker/Swarm secrets (files under /run/secrets/<name>, the standard
# `docker service create --secret` / stack-file `secrets:` mechanism) to the
# plain environment variables class.Env.php already reads via getenv().
#
# No PHP code change needed: a Swarm secret named `db_password` lands at
# /run/secrets/db_password; this exports it as DB_PASSWORD (uppercased)
# before handing off to the image's normal command. The local docker-compose
# verification stack doesn't use Swarm secrets at all (env_file: .env
# instead) so this is a harmless no-op there -- /run/secrets simply won't
# exist, the loop below just does nothing.
#
# Swarm-secret names in docker-stack.yml are lowercase by convention
# (db_password, bankone_api_key, ...) and map 1:1 to the uppercase env vars
# documented in .env.example.
set -e

if [ -d /run/secrets ]; then
    for secret_file in /run/secrets/*; do
        [ -f "$secret_file" ] || continue
        secret_name=$(basename "$secret_file")
        var_name=$(echo "$secret_name" | tr '[:lower:]' '[:upper:]')
        export "$var_name"="$(cat "$secret_file")"
    done
fi

exec "$@"
