# Architecture

## The two applications aren't independent

`Admin/` and `CoreFront/` look like separate applications but share one PHP class library at
runtime. Both bootstrap with:

```php
set_include_path('/var/www/html/classes/');                          // CoreFront's own files
set_include_path('/var/www/html/classes/:/var/www/admin/classes/');   // Admin: both, colon-joined
```

Admin has no `class.dbConnect.php`, `class.BankOne.php`, `class.Secure.php`, `class.User.php`, or
`class.Mailer.php` of its own — it resolves all of them from `CoreFront/classes/` via that absolute
filesystem path. This isn't incidental; it's load-bearing. The two Docker images
(`docker/corefront/Dockerfile`, `docker/admin/Dockerfile`) both reproduce it by cloning the same
repo and placing files at those same absolute paths — see
[DEPLOYMENT.md](DEPLOYMENT.md#why-both-images-clone-the-whole-repo).

Both sit in front of the same MariaDB instance (schemas `dbPetra` and `Admin`, plus
`dbInternetBank` created by `docker/db/02-missing-schema.sql`) and the same core-banking
integration (BankOne, over HTTPS).
Redis is CoreFront-only (per-login session/encryption keys — Admin's session handling is
unrelated, see below).

## CoreFront: request lifecycle

Every CoreFront endpoint file (`user.php`, `account.php`, `transfer.php`, `investments.php`,
`loans.php`, `utils.php`) starts with `include 'includes/bootstrap.php';`, which:

1. Handles CORS/OPTIONS.
2. Reads `X-Clientid` and `X-Authid-Token` headers. If both are present, looks up the session in
   Redis (`User::getSession($token)` — a single GET, keyed by token alone).
3. **If a session was found**: sets `Secure`'s AES-128-CBC key/IV to that session's own key
   (`Secure::setKeys()`), then decrypts the request body with it.
4. **If no session was found** (pre-login traffic — `login`, `validateOTP`, `forgot_password`):
   treats the body as plain JSON. There is no shared secret to encrypt with before a session
   exists — this is deliberate, not a gap.
5. Redacts password/PIN/OTP/token/crypto-key fields before anything is written to `error_log` or
   the `Log` table (`redact_sensitive()`).
6. Registers a shutdown function that logs the request/response pair to the `Log` table —
   encrypted responses are decrypted first (with the same session key) so the logged copy is
   readable, matching however the request was logged.

Two functions endpoint files use directly:

- **`require_session()`** — for endpoints where every action needs a session. 401s and exits if
  the token is missing, not found in Redis, or (because the Redis lookup is by token alone) if the
  session's own `UserID` doesn't match the caller's claimed `X-Clientid`. Used by `account.php`,
  `transfer.php`, `investments.php`, `loans.php`.
- **Per-action gating** — for the two files that mix authenticated and unauthenticated actions in
  one dispatcher. `user.php` handles `login`/`validateOTP`/`forgot_password` as top-level cases
  and calls `require_session()` inside its `default:` arm, so everything else is gated.
  `utils.php` instead branches on `$current_session` directly, because `validate_bvn` and
  `validate_reg_no` work both with and without a session.

`receipt.php` is the one endpoint that doesn't go through `bootstrap.php` — it serves a PDF, not
JSON, so the encrypted-body machinery doesn't apply. It does its own lightweight version of the
same session lookup (see [API-COREFRONT.md](API-COREFRONT.md#receiptphp)).

## The per-login encryption key

Minted once, at `validateOTP()`:

- `session_token`: `bin2hex(random_bytes(32))`.
- `crypto_key` / `crypto_iv`: `bin2hex(random_bytes(8))` each — 16 hex characters, used directly as
  the 16 raw bytes AES-128-CBC needs. They are NOT hex-decoded first: the 16 ASCII characters
  themselves are the key material, which is what the client must also do to interoperate.
- All three are returned to the client **once**, in the plaintext `validateOTP` response — the one
  moment a client can learn keys it doesn't have yet.

Storage is Redis, not the `AccessStore` table:

- `session:<token>` → `{"UserID": "...", "CryptoKey": "...", "CryptoIV": "..."}`, with a TTL
  (`SESSION_TTL` env var, default 3600s), refreshed on every use.
- `user:active:<UserID>` → the current token, same TTL. `startSession()` checks this pointer and
  evicts the previous session before writing a new one — logging in on a new device ends the old
  session. Single session per user, by design.
- `AccessStore` (the SQL table) still gets one row per login (`Signature`, `Age`), but it is no
  longer consulted to validate a session. It is not strictly append-only: `User::lockUser()`
  deletes a locked user's rows from it.
- If Redis is unreachable, session lookups fail closed (treated as no session — the same as an
  invalid or expired one), and `validateOTP` simply omits `crypto_key`/`crypto_iv` from its
  response rather than erroring.

## Admin: a different, unrelated auth model

Admin uses native PHP sessions (`$_SESSION`), not the CoreFront/Redis machinery at all. Login is
bcrypt password + mandatory TOTP 2FA (`class.TOTPManager.php`) — a real secret enrolled per admin
user (`Admin.Users.Secret`), no bypass. `Admin/src/include.all.php` gates every page: no
`$_SESSION['uname']` means an immediate redirect to the login screen. There's a second,
lower-level `LastAuthenticatedToken` mechanism (`Authenticate::createValidSession()` /
`validateTokenID()`) generated at 2FA completion; as far as this codebase's own call sites go, it
isn't re-checked on every subsequent request the way the CoreFront session is — the PHP session
itself is what actually gates access on each page load.

Every write in Admin goes through one of two dispatchers, both requiring a valid session already
(`include.all.php` runs before either):

- **`Admin/src/util.php`** — one big `switch ($_POST['action'])`, ~37 actions, both read (spool a
  report, load a form) and write (approve a limit change, create a user) operations. See
  [API-ADMIN.md](API-ADMIN.md).
- **`Admin/src/validate.Authenticate.php`** — login, 2FA, password change/reset only.

### A vestigial reference worth knowing about, not a live bug

Three forms (`UserAdmin::createFormsection()`'s create-user form, and two in `util.php` — change
password, change authorization password) have `action="../src/submitVals.php"`. That file does not
exist anywhere in this repository — presumably a leftover from whatever template this codebase was
started from. It's harmless: every one of these forms' submit control is
`<input type="button">`, not `type="submit"`, so the browser never attempts a native form
submission at all — `views/js/actions.js` intercepts the button click and posts to `util.php`
instead. If you ever see `action="../src/submitVals.php"` while reading this code, it's dead
markup, not a routing bug.

### A dead route

`CoreFront/.htaccess` maps `^customer/(.*)$` to `customer.php`, which does not exist in this repo.
Hitting `/customer/*` on a live deployment will error. Whether this was a removed feature or one
never finished isn't knowable from the code alone.

## The outflow cron

`Admin/crons/crons.outflow.php` is the only place a `Transfers` row with `Status='Authorized'`
actually gets sent to BankOne (`BankOne::transferFunds(..., 'Inter')` — interbank/NIP transfers
only; the intrabank branch of `transferFunds()` is an empty stub, so intrabank transfers are not
currently wired to actually move money through this path). It's not a real scheduler: `Scripts/
jobs.sh` is a `while true; sleep 30` shell loop invoking the PHP script directly. Batches of 10,
capped at 3 retries (`Trials`). In production (`docker-stack.yml`) it runs as the `outflow-cron`
service, triggered on a schedule by `swarm-cronjob` — see
[DEPLOYMENT.md](DEPLOYMENT.md#production-docker-swarm). **No Docker profile runs `Scripts/jobs.sh`**:
nothing in `docker-compose.yml` invokes it, so in the local stack the script has to be run by hand
— see [DEPLOYMENT.md](DEPLOYMENT.md#the-outflow-cron-locally).
