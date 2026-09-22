# Deployment

Two profiles: the **local verification stack** (`docker-compose.yml`, covered first below) for
development and testing, and **production** (`docker-stack.yml`, Docker Swarm — see
[Production](#production-docker-swarm) further down) for a real deploy. Both build from the same
two Dockerfiles.

## Prerequisites

- Docker with the **`compose`** and **`buildx`** CLI plugins. Both are genuine plugins, not
  bundled with every Docker install — if `docker compose version` or `docker buildx version` fail,
  install them (`brew install docker-compose docker-buildx` on macOS) and make sure your Docker
  config's `cliPluginsExtraDirs` includes wherever they land
  (`~/.docker/config.json`). `buildx` additionally needs a bootstrapped builder instance:
  `docker buildx create --use`.
- Read access to `secondcaesar-apps/petra-corporate-ibank` for the build-time clone (see below) — a
  `GITHUB_TOKEN` in `.env`.

## Why both images clone the whole repo

`docker/corefront/Dockerfile` and `docker/admin/Dockerfile` don't `COPY` the local working tree —
they `git clone` `secondcaesar-apps/petra-corporate-ibank` at build time and copy out just the
subdirectory each needs (`CoreFront/` for corefront; `Admin/` **and** `CoreFront/classes/` for
admin, from the same clone, since Admin depends on CoreFront's classes — see
[ARCHITECTURE.md](ARCHITECTURE.md#the-two-applications-arent-independent)). This means **a local
edit isn't live until it's pushed** — the build pulls from the remote branch (`GIT_REF`, default
`main`), not your working directory.

The clone token is a BuildKit secret (`--mount=type=secret`), never written to an image layer. The
clone's own `.git/config` does carry the token (git embeds it in the authenticated URL) but that
directory is never among the paths copied out, and the whole clone is deleted within the same `RUN`
step regardless.

### `CACHEBUST` — read this before your first "why isn't my fix showing up"

Docker's layer cache has no way to know a branch got new commits pushed — neither the Dockerfile
text nor `GIT_REF`'s *value* changes on a push, so a plain `docker compose build` after pushing
**silently keeps serving the old clone**. No error, no warning, it just builds "successfully" from
stale source. Always set `CACHEBUST` to the branch's current commit before building:

```bash
CACHEBUST=$(git rev-parse main) docker compose build
```

If you're ever unsure whether a running container reflects the latest commit, don't trust a
successful build — check directly:

```bash
docker exec petra-corefront-1 grep -n 'something you just changed' /var/www/html/classes/class.Whatever.php
```

## Environment variables

`.env.example` is the authoritative, documented list — copy it to `.env` and fill in real values
(gitignored, never commit it). Grouped summary:

| Group | Variables |
|---|---|
| Database | `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASSWORD` |
| BankOne | `BANKONE_API_KEY`, `BANKONE_INST_ID` |
| Outbound mail | `SMTP_HOST`, `SMTP_PORT`, `SMTP_USERNAME`, `SMTP_PASSWORD`, `SMTP_SECURE`, `SMTP_AUTH` (the last two exist because a local test SMTP server needed different values than the real mailbox — see the comments in `.env.example`) |
| Session store | `REDIS_HOST`, `REDIS_PORT`, `REDIS_PASSWORD`, `SESSION_TTL` |
| Source repo | `GITHUB_TOKEN`, `GIT_REF`, `CACHEBUST` |

There is deliberately no request/response encryption key variable — there's no static key to
configure; see [ARCHITECTURE.md](ARCHITECTURE.md#the-per-login-encryption-key).

## Bringing the stack up

```bash
CACHEBUST=$(git rev-parse main) docker compose build
docker compose up -d
```

Services: `db` (MariaDB 10.5), `redis`, `mailpit` (test SMTP + web UI), `corefront`, `admin`.
`docker-entrypoint-initdb.d` runs, in order: the real dump (`Database/all.sql`), the one genuinely
missing table (`docker/db/02-missing-schema.sql`), then two **local-dev-only, purely additive**
seed files (`03-seed-local-dev-admin.sql`, `04-seed-local-dev-customer.sql`) — real data is never
touched or overwritten, only new synthetic rows added. Each init file is its own `mysql`
invocation with no inherited database context from the previous one — schema-qualify every table
name, or add an explicit `USE` at the top, if you add another one.

| Service | URL |
|---|---|
| Admin | `http://localhost:18082` |
| CoreFront API | `http://localhost:18081` |
| mailpit (sent-mail inbox) | `http://localhost:18025` |
| MariaDB | `localhost:13306` |
| Redis | `localhost:16379` |

`docker compose down -v` for a fully clean slate (drops the database volume too — the next `up`
re-seeds from scratch).

### Seeded logins

Both are additive-only and never touch real rows:

- Admin: `admin@local.test` / `LocalDevOnly!2026`, TOTP secret
  `XNM6FH6T6VXH7X7OWDJYDTE4GIO4JDKENKTR5KRNNUGYV7G4SCQQ` (generate a fresh 6-digit code from it
  right before logging in — codes expire in ~30s).
- CoreFront customer: `customer@local.test` / `CustomerDevOnly!2026`, `CustomerID='0000001'` — not
  a real BankOne customer, so BankOne-backed actions (account lists, balances) will reach BankOne
  and get nothing back in this sandbox. That's an expected sandbox limitation, not a bug: it's
  still useful for exercising login, session, and encryption end to end.

Running the `forgot_password` flow against the seeded admin resets its password/TOTP secret to an
unknown value — re-seed (`down -v && up -d`) if you need to log in again afterward.

## The outflow cron, locally

`Admin/crons/crons.outflow.php` (the only path that actually sends an `Authorized` transfer to
BankOne) runs via `Scripts/jobs.sh` in a real deployment (see below) or manually here — this local
stack doesn't run it on a schedule:

```bash
docker exec petra-admin-1 php /var/www/admin/crons/crons.outflow.php
```

## Production (Docker Swarm)

`docker-stack.yml` (repo root) is the production counterpart to `docker-compose.yml` — same two
Dockerfiles, but images are pulled from GHCR rather than built locally, credentials come from
Docker Swarm secrets rather than a `.env` file, and the topology follows a standard shape for this
kind of deployment: Traefik ingress, `swarm-cronjob` for the outflow cron, one MariaDB + one Redis
with persistent volumes. See the comments at the top of `docker-stack.yml` itself for the short
version; this is the long version.

### Images

`.github/workflows/build-and-push.yml` builds and pushes both images to GHCR automatically on
every push to `main`, and on any `v*` tag. `docker-stack.yml`'s `${TAG:-latest}` picks up whichever
tag you want:

```bash
TAG=v1.2.3 docker stack deploy -c docker-stack.yml petra   # a specific release
docker stack deploy -c docker-stack.yml petra              # :latest, i.e. the default branch's most recent build
```

If you need to build and push manually instead (no CI): same command CI runs, using your own
`GITHUB_TOKEN` in place of the workflow's automatic one —

```bash
export CACHEBUST=$(git rev-parse main)
docker buildx build --push -t ghcr.io/secondcaesar-apps/petra-corefront:latest \
  -f docker/corefront/Dockerfile --secret id=github_token,src=<(gh auth token) \
  --build-arg CACHEBUST="$CACHEBUST" .
# repeat for docker/admin/Dockerfile -> petra-admin
```

### Secrets — create these on the target Swarm before the first deploy

Each is read from stdin so the value never touches shell history or a file on disk:

```bash
docker secret create db_root_password -   # MariaDB root's own credential — provisioning only, the app never sees it
docker secret create db_password -        # the credential the app tier actually authenticates with (a scoped `petra_app` user, provisioned automatically — see below)
docker secret create bankone_api_key -
docker secret create smtp_password -
docker secret create redis_password -
```

Rotating a secret needs a new name (Swarm secrets are immutable once created) plus a service
update pointing at it — `docker secret create db_password_v2 -` then update `docker-stack.yml`'s
reference and redeploy.

### Before your first deploy

1. Replace every `CHANGE-ME.example.com` in `docker-stack.yml` with your real hostnames.
2. Confirm a `traefik-public` overlay network already exists with Traefik attached to it
   (`docker network ls | grep traefik-public`). If this is the first app on a fresh Swarm node
   instead of joining existing infrastructure, stand up Traefik first — a minimal Traefik-on-Swarm
   setup (global mode, ACME via `letsencrypt`, attached to a network named `traefik-public`) is out
   of scope for this file; any standard configuration of that shape will do, as long as it attaches
   to a network with this name.
3. Confirm `crazymax/swarm-cronjob` isn't already running for another stack on this Swarm — it's
   one per Swarm, not one per app. If it's already there, delete the `swarm-cronjob` service block
   from `docker-stack.yml` before deploying and just leave the `swarm.cronjob.*` labels on
   `outflow-cron` — the existing instance will pick it up.

### Deploy

```bash
docker stack deploy -c docker-stack.yml petra
docker stack services petra           # everything should reach the requested replica count
docker service logs petra_corefront   # etc., per service
```

### The scoped database user

The app tier authenticates as `petra_app`, not `root` — `docker/db/init-app-user.sh` (mounted into
the `db` service via a Swarm config) creates it automatically the first time the `db` service
starts against an empty volume, using `db_root_password` once for that provisioning step only and
granting it the `db_password` secret's value (the same credential `corefront`/`admin`/
`outflow-cron` already use). Nothing extra to run by hand on a fresh deploy; on an *existing*
database being migrated onto this stack, run the same `CREATE USER`/`GRANT` statements from that
script manually first, since `docker-entrypoint-initdb.d` only fires against an empty data
directory.

### Redeploying after a code change

```bash
git push                                  # triggers the CI build above
docker service update --image ghcr.io/secondcaesar-apps/petra-corefront:latest petra_corefront
docker service update --image ghcr.io/secondcaesar-apps/petra-admin:latest petra_admin
```

`update_config.order: start-first` on both services means the new task starts and passes its
healthcheck before the old one stops — no downtime window for a routine update.
