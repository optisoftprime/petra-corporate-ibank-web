# Petra core banking backend

Backend for Petra Microfinance Bank: two PHP applications sharing one database and one core-banking
integration (BankOne).

- **[CoreFront](CoreFront/)** — the customer-facing REST API, consumed by a separate mobile/web
  client application. Its request/response contract is therefore external: a breaking change here
  breaks that client, and nothing in this repository will catch it.
- **[Admin](Admin/)** — the staff back-office web application (session-based, server-rendered).

## Documentation

| Doc | Covers |
|---|---|
| [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) | How the two apps relate, request lifecycle, encryption, session storage, the core-banking integration |
| [docs/API-COREFRONT.md](docs/API-COREFRONT.md) | Every CoreFront endpoint and action: auth, request/response shape, business rules |
| [docs/API-ADMIN.md](docs/API-ADMIN.md) | Every Admin action: what it does, who can reach it |
| [docs/DATABASE.md](docs/DATABASE.md) | Schema reference: the `dbPetra` and `Admin` tables, and the triggers that carry real behaviour |
| [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md) | Docker setup, environment variables, local verification stack, troubleshooting |

## Quickstart (local verification stack)

```bash
cp .env.example .env   # fill in DB_PASSWORD, GITHUB_TOKEN, etc. — see docs/DEPLOYMENT.md
CACHEBUST=$(git rev-parse main) docker compose build
docker compose up -d
```

Admin: `http://localhost:18082` · CoreFront API: `http://localhost:18081` · mailpit (test SMTP):
`http://localhost:18025`. Full detail, including why `CACHEBUST` is required, in
[docs/DEPLOYMENT.md](docs/DEPLOYMENT.md).

## Security model, in brief

Every secret (database password, BankOne API key, SMTP password, session encryption) is supplied
via environment variables — see [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md). Request/response
encryption uses a key minted fresh per login session, stored in Redis, never a static shared key —
see [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md#the-per-login-encryption-key). Every action that
operates on a specific account, transfer, or loan verifies it belongs to the caller before acting
on it — see [docs/API-COREFRONT.md](docs/API-COREFRONT.md) for which actions are ownership-checked.

See [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) for known limitations and open items.
