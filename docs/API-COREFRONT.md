# CoreFront API reference

Base routing is via `.htaccess`: `/<file-stem>/<action>` rewrites to `<file>.php?action=<action>`,
e.g. `POST /user/login` → `user.php?action=login`. `/receipt/<reference>` is the one exception,
rewriting to `receipt.php?PaymentReference=<reference>`.

**Every request**, authenticated or not: `Content-Type` doesn't matter, the body is read raw via
`php://input`. **Authenticated requests** carry `X-Clientid` (the customer's `UserID`, i.e. login
email) and `X-Authid-Token` (`session_token` from `validateOTP`) headers, and the body is
AES-128-CBC-encrypted (base64-wrapped) with that session's `crypto_key`/`crypto_iv` — see
[ARCHITECTURE.md](ARCHITECTURE.md#the-per-login-encryption-key). **Pre-session requests** (`login`,
`validateOTP`, `forgot_password`) send and receive plain JSON. Response encoding always matches the
request: encrypted session → encrypted response; no session → plain JSON response.

Every action below is authenticated (via `require_session()` or an equivalent direct check) unless
explicitly marked **pre-session**.

## user.php

| Action | Auth | Notes |
|---|---|---|
| `login` | pre-session | Body: `email`, `password`. Verifies bcrypt against `Credentials`, emails + SMS's a 6-digit OTP (`OTP` table, no expiry set on this particular insert — see `validateOTP`'s own 90s window instead), returns `HTTP 200` with **no body** on success. `423`/`422`-style: `HTTP 422` + plain JSON `{"description": "Profile Locked..."}` if the account is locked; `HTTP 422` with no body on a wrong password (also increments `Credentials.Trials`; the lockout is enforced in the database, not in PHP — the `LockAccount` BEFORE UPDATE trigger on `Credentials` forces `Status='Locked'` once `Trials` reaches 3). |
| `validateOTP` | pre-session | Body: `userId` (the login email), `otp_code`. 90-second window on the OTP row. On success: mints and returns the session (see architecture doc) as plain JSON: `{user_id, session_token, status, crypto_key?, crypto_iv?}` (`crypto_key`/`crypto_iv` omitted only if Redis was unreachable). `HTTP 422` with no body on a wrong/expired code. |
| `forgot_password` | pre-session | Body: `email`. Generates a random password, emails it, sets `Credentials.Status='ChangePassword'`. No response body either way; `HTTP 422` if the email doesn't match an unlocked account. |
| `change_password` | session | Body: `current_password`, `new_password`. `new_password` must match `/(?=^.{8,}$)((?=.*\d)|(?=.*\W+))(?![.\n])(?=.*[A-Z])(?=.*[a-z]).*$/` (8+ chars, upper+lower, and a digit or symbol). `HTTP 500` + encrypted `{"description": "Password not strong enough"}` or `{"description": "Incorrect Password"}` on failure — yes, `500` for a validation failure, not `400`; that's the existing behavior, not a doc error. |
| `get_all_users` | session | Returns every `Credentials` row for the caller's own `CustomerID` (i.e. every login under the same business — Initiators, Authorizers, Viewers on the same account). |
| `lock_account` | session | Body: `account` (a `UserID`). **Ownership-checked**: must belong to the caller's own `CustomerID` (i.e. the same business) — without this check, any authenticated session could lock any login system-wide. |
| `create_otp` | session | Re-sends a fresh OTP (email + SMS) for the *caller's own* `X-Clientid`, 4-minute expiry — this is the "authorization PIN" OTP type used by `transfer.php`'s `auth_pin`, distinct from the login OTP above. |
| `get_user_details` | session | Returns the caller's own `Credentials` row (minus `Password`) plus a computed `UsedLimit` (today's successful transfer total). |

## account.php

All actions require a session.

| Action | Notes |
|---|---|
| `get_accounts` | Every BankOne account for the caller's `CustomerID`, plus any customer linked via an *approved* `LinkedCustomerID` row. `500` if the BankOne call itself fails. |
| `freeze_account` / `unfreeze_account` | Body: `cod_acct_no`, `reason`. **Ownership-checked**: the account must appear in the caller's own `get_accounts` result, or `401`. No role restriction beyond "logged in" — any role, including `Viewer`, can freeze an account they own. |
| `get_account_statement` / `get_account_statement_pdf` | Body: `cod_acct_no`, `start_date`, `end_date` (`Y-m-d`). Ownership-checked the same way. The PDF variant doesn't return a PDF directly — it inserts a row into `dbInternetBank.StatementRequest` (created by `docker/db/02-missing-schema.sql`, not by the dump); something outside this repo drains that queue and generates/delivers the file. |
| `validate_account` | Body: `cod_acct_no` (10 digits), `account_type` (`Intra` or `Inter`), and for `Inter` also `sortcode`. This is a name-enquiry lookup on a *recipient* account, not the caller's own — no ownership check applies or would make sense here. |

## transfer.php

All actions require a session.

| Action | Notes |
|---|---|
| `create_transfer` | Requires `Role='Initiator'` and a valid `auth_pin` (the OTP from `create_otp`, consumed on use). Body: `beneficiary_details` (array of `{amount, account_no, account_name, dest_bank_code, source_account, narration}`). Every `source_account` must be one of the caller's own NUBAN accounts (`401` otherwise). Checks per-transaction against `SingleTxnLimit` and the running daily total against `TxnLimit`. Emails every `Authorizer` on the account. Returns `{"BatchID": "..."}`. |
| `authorize_transfer` | Requires `Role='Authorizer'` and a valid `auth_pin`. Body: `trxn_id` (array of `{ID}`). Re-checks each transaction's `CustomerID` against the caller's own before touching it. |
| `decline_transfer` | Requires `Role='Authorizer'`. Body: `trxn_id` (array of `{ID}`). **Ownership-checked** at the query level (`CustomerID` must match). No `auth_pin` requirement, unlike `authorize_transfer` (an existing asymmetry between the two actions). |
| `add_beneficiary` / `delete_beneficiary` | Body: `dest_bank_code`, `account_no`, `account_name` / `beneficiary_id`. Scoped to the caller's own `CustomerID` + `UserID` pair. |
| `get_beneficiary` | Returns the caller's own saved beneficiaries. |
| `get_pending_transfer` | Requires `Role='Authorizer'`. Pending transfers up to the caller's `TxnLimit`. |
| `get_all_transactions` | Body: `start_date`, `end_date`. |
| `generate_trxn_receipt` | Defined, does nothing (empty case body) — not implemented. Receipts are fetched via `/receipt/<reference>` instead, see below. |

## investments.php

| Action | Notes |
|---|---|
| `portfolio` | Session required. BankOne fixed-deposit accounts for the caller's `CustomerID`, filtered to `accountType == 'SavingsOrCurrent'`. |

## loans.php

| Action | Notes |
|---|---|
| `get_repayment_schedule` | Session required. Body: `loanID` (BankOne's `LoanAccountNo`). **Ownership-checked**: must be one of the caller's own active loans (`BankOne::getLoans()`). |
| `get_loan_details` | Session required. Body: `loanID` (a `dbCredits.Loans` primary key — a different ID namespace than `get_repayment_schedule`'s `loanID` above, despite the shared parameter name). **Ownership-checked** via `and CustomerID=?` on both queries. **Note:** this is the only action that reads the `dbCredits` schema, which nothing in this repository creates — see `docker/db/02-missing-schema.sql`. It cannot succeed against a database built solely from this repo. |
| `portfolio` | Session required. Active BankOne loans for the caller's own `CustomerID`. |

## utils.php

Reference/lookup data, six actions. Two of them (`validate_bvn`, `validate_reg_no`) work both
with and without a session — they appear in both branches of the dispatcher; the other four
require one.

| Action | Auth | Notes |
|---|---|---|
| `get_banks` | session | Commercial bank list from BankOne, sorted by name. |
| `get_states` | session | `State` table. |
| `get_currency` | session | `Currency` table. |
| `confirm_bank_account` | session | Body: `sortcode` (6 digits), `account_no` (10 digits). Calls `BankOne::getValidateExternalAccount()` — the same method `account.php`'s `validate_account` (Inter case) uses. Response: `{cod_acct_title, bvn}`. |
| `validate_bvn` | either | Body: `bvn` (>10 chars). **Currently non-functional in both branches**: `$prembly` is never instantiated anywhere in this file, so this always fatals with "call to a member function on null" before it can do anything. |
| `validate_reg_no` | either | Body: `rc_number`. Same `$prembly`-not-instantiated issue as `validate_bvn`. |

## receipt.php

`GET /receipt/<TransferReference>`. Doesn't go through `includes/bootstrap.php` (it serves a PDF,
not JSON) but does its own equivalent session check: `X-Clientid`/`X-Authid-Token` headers,
`User::getSession()`, then confirms the transfer's `SourceAccount` belongs to the caller (via
`Account::getAccounts()`, the same ownership pattern as `account.php`). `401` if either check
fails. On success, streams a TCPDF-rendered receipt inline (`Content-Disposition: inline`,
unencrypted — TLS is the only transport protection here, matching how every other binary/file
response in this codebase works).
