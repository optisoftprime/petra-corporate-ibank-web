# Database reference

Three data stores: MariaDB (two schemas: `dbPetra`, `Admin`), Redis (CoreFront session/encryption
keys only, see [ARCHITECTURE.md](ARCHITECTURE.md#the-per-login-encryption-key)), and BankOne
(external, HTTP — the system of record for accounts, balances, and transaction execution; nothing
account-shaped is stored locally except the `Transfers` audit/workflow table below).

`Database/all.sql` (a real production-shaped dump) is **deliberately gitignored** — it carries real
customer PII (emails, phone numbers) and password/session hashes. It's a local-only file for
standing up the verification stack; never committed, never should be.

## `dbPetra` — customer-facing schema

The schema the dump actually covers in full, tables and data both.

| Table | Purpose |
|---|---|
| `Credentials` | Customer-facing logins. One row per person who can log into CoreFront, not one row per customer — a business can have several (`Role` ∈ `Initiator`\|`Authorizer`\|`Viewer`\|`I-Initiator`, all sharing one `CustomerID`). |
| `AccessStore` | Session audit trail (see architecture doc — no longer the live session store). |
| `OTP` | Login OTPs and transaction-authorization PINs, distinguished by `Type`. |
| `Transfers` | The transfer workflow/audit table — `Status` moves `Pending → Authorized → Processing → Successful/Failed`, or `→ Declined`. This is what the outflow cron reads and what `receipt.php` serves from; BankOne itself is the actual ledger. |
| `Beneficiary` | Saved transfer recipients, per `(CustomerID, AccountNo, DestBankCode)`. |
| `LinkedCustomerID` | Approval-gated links letting one login see multiple `CustomerID`s' accounts. |
| `Banks` | Sort-code → bank name, used to join transfer/receipt data for display. |
| `Contents` | Email template bodies, keyed by `Type` (`OTPCODE`, `RESET`, `CREATE_TRANSFER`, etc.) — `[Placeholder]`-style substitution, not a template engine. |
| `Log` | Every CoreFront request/response, redacted (see architecture doc). Singular — not to be confused with `Admin.Logs`. |
| `Templates` | Present in the dump with one row; not queried anywhere in the current codebase. |

## `Admin` — staff back-office schema

This schema is *not* missing from `Database/all.sql`, though it's easy to assume it is: the dump's
`CREATE DATABASE` statement for it is a second one, further down the file, not the first. Every
table below ships with real DDL and real data — `Roles` (5 rows), `Category` (4), `Services` (11,
all currently under the `Admin` category; the other three categories have no services assigned
yet, which is expected, not a bug), `Forms` (6), `UpdateLimits` (4), `Users` (3) and `Logs` (~530).

| Table | Purpose | Notes |
|---|---|---|
| `Users` | Admin back-office logins. | No `Pass_State` column (only the legacy `Users_` table below has one). |
| `Users_` | Legacy admin-user table. | Not queried anywhere in the current codebase. |
| `Roles`, `Category`, `Services` | Role-based menu/access control. | `Services.Access` is a colon-delimited list of `RoleID`s (`:1:19:26:`) matched with `LIKE`. `RoleID=1` is hardcoded as "sees everything" in `Menu::getChildren()`, bypassing the `Access` filter entirely for the submenu (but *not* for which top-level categories show at all — `Role::getServices()` still filters by `Access` even for RoleID 1). |
| `Forms` | Drives every dynamically-rendered create/edit form (`FormGenerator`). | Real columns: `SN` (primary key), `id` (groups several `Forms` rows into one logical form — *not* the primary key, despite the name), `form_name`, `category`, `field_short_name`, `field_long_name`, `field_range`, `field_type`, `required`. |
| `Logs` | Admin audit trail (`class.Log.php`). | A different table from the customer-side `dbPetra.Log`, and a different shape: `idno`, `logdate`, `usr`, `activity`, `ipaddress`, `category`, `payload`, against `Log`'s `id`, `DateCreated`, `UserID`, `Payload`, `Response`, `Action`. Note the names differ only by a plural — always schema-qualify, as the code does. |
| `UpdateLimits` | Pending transaction-limit change requests. | Has an `UpdateCredentials` trigger: on `Status: Pending → Approve`, writes the new limits straight into `dbPetra.Credentials`. |

## Triggers

- **`LockAccount`** (`dbPetra.Credentials`, `BEFORE UPDATE`): forces `Status='Locked'` at
  `Trials=3`, and forces a fresh `Status='New'` login into `'ChangePassword'` the first time it's
  set `Active`.
- **`UpdateCredentials`** (`Admin.UpdateLimits`, `AFTER UPDATE`): see the `Admin.UpdateLimits` row
  above.

## Migrations

None pending. Session encryption keys are stored in Redis, not the database — see
[ARCHITECTURE.md](ARCHITECTURE.md#the-per-login-encryption-key). If a future change needs a schema
migration, use a numbered file under `Database/migrations/`, committed to git (unlike
`Database/all.sql`, which never should be).
