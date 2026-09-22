# Admin action reference

Admin is server-rendered and session-based (see
[ARCHITECTURE.md](ARCHITECTURE.md#admin-a-different-unrelated-auth-model)), not a JSON API in the
CoreFront sense. Actions in `util.php` require an existing `$_SESSION['uname']`, checked by
`Admin/src/include.all.php`, which that dispatcher includes. The login/2FA dispatcher
(`validate.Authenticate.php`) does **not** include `include.all.php` — it sets up its own include
path and autoloader — which is correct, since it is the path by which a session is established in
the first place.

## Login and 2FA — `Admin/src/validate.Authenticate.php`

`POST` with `action` = one of:

| Action | Body | Notes |
|---|---|---|
| `login` | `loginame`, `password` | bcrypt-verified against `Admin.Users`. Routes to one of three next steps by session status: `change_password_ui` (password unset or >90 days old), `reg_2fa_ui` (no TOTP secret enrolled yet — returns a QR code), or `enter_2fa_ui` (normal case). |
| `register2fa` | `otpcode` | Validates the code against the secret generated during `login`'s `reg_2fa_ui` step, then enrolls it (`Authenticate::updateSecret()`) and opens the session. |
| `validateOTP` | `otpcode` | Normal-case 2FA: validates against the already-enrolled secret, opens the session. |
| `changepassword` | `currentpassword`, `newpassword` | Re-authenticates with the current password first. |
| `forgot_password` | `userID` | Emails a new random password (`Authenticate::resetPassword()` — the one that correctly uses two separate query-result variables; don't confuse with `UserAdmin::resetPassword()` below, a same-named method on a different class with a different call site). |

On success, redirects to `views/main.php`; on failure, back to `views/index.php` with a message.

## `Admin/src/util.php` — everything else

One `switch ($_POST['action'])`. Grouped by area below; each row is `action` → what it does.

### Customers

| Action | Does |
|---|---|
| `createNewCustomerSection` | Returns the create-customer form HTML. |
| `createNewCustomer` | Body: `formContent` (query-string encoded: `customerID`, `userID`, `email`, `mobile`, etc.). Creates a `Credentials` row with `Status='New'`, pending authorization. |
| `validateCustomerByID` | Body: `customerID`. BankOne name lookup, for the create/manage-customer UI's "confirm this is who you think it is" step. |
| `validateUserID` | Body: `userID`. Checks for a `Credentials` collision before create. |
| `createManageCustomerSection` / `createAuthorizeAccountSetup` / `createAuthorizePasswordReset` | Return the respective filter-and-lookup form HTML (customer ID in, results loaded separately via `getAllUsersByCustomerID`). |
| `getAllUsersByCustomerID` | Body: `customerID`. All logins under that customer, plus their approved linked accounts. The same function also powers `getPendingSetup` (filtered to `Status='New'`) and `getPendingAuthorization` (filtered to `Status='PendingReset'`) below — three actions, one implementation. |
| `getPendingSetup` | Body: `customerID`. `getAllUsersByCustomerID(..., 'New')`. |
| `getPendingAuthorization` | Body: `customerID`. `getAllUsersByCustomerID(..., 'PendingReset')`. |
| `updateUserStatus` | Body: `userID` (a `Credentials.ID`, not a login email), `status` ∈ `Authorize`\|`Delete`\|`Decline`\|`Reactivate`\|`Lock`\|`Reset`. Note: two `case "Delete"` blocks exist in the underlying `switch` (lines ~698 and ~718 of `util.php`) — the second is unreachable (PHP takes the first match), so `status=Delete` always calls `Credentials` hard-delete, never the soft `updateStatus('Deleted', ...)` the second block intended. |
| `getChangeTxnLimitUI` / `updateTxnLimit` | Body: `userID` (+ `singleTxnLimit`, `dailyTxnLimit` for the update). Files an `Admin.UpdateLimits` row (`Status='Pending'`) — doesn't apply the change itself, see `authorizeLimitChange`. |

### Limits and account linking (maker-checker queues)

| Action | Does |
|---|---|
| `createLimitApprovalSection` / `getPendingLimitApproval` | Spool `Admin.UpdateLimits` rows with `Status='Pending'` in a date range. |
| `authorizeLimitChange` | Body: `status` (`Approve`/`Decline`), `rowId`. The `UpdateCredentials` trigger on `Admin.UpdateLimits` applies an `Approve` to the real `Credentials.TxnLimit`/`SingleTxnLimit` — this action just flips the status, the trigger does the actual work. |
| `createAccountLinkSection` / `getPendingLinkApproval` | Same pattern for `LinkedCustomerID` requests. |
| `linkAccountRequest` | Body: `customerID`, `linkedCustomerID`. Files the request. |
| `linkAccount` | Body: `status`, `rowId`. Approves/declines it. |
| `unLinkAccount` | Body: `rowId`. Unlinks an already-approved link. |
| `getLinkedAccountUI` | Body: `cod_cust`. Returns the "link a new account" modal form. |

### Admin users, roles, and access rights

| Action | Does |
|---|---|
| `createNewUserSection` / `createNewUser` | Create a new **Admin** back-office login (distinct from `createNewCustomer` above, which creates a **customer** login). Body for the latter: `formContent`. |
| `createDeleteUsersSection` / `removeUser` | List/delete Admin users. `removeUser` won't touch a `RoleID=1` (super-admin) account — see `UserAdmin::deleteUser()`. |
| `createAccessRightSection` / `getRoleRights` / `grantAccessRights` / `revokeAccessRights` | Manage which `Admin.Services` a `Admin.Roles` row can see, via the `Access` colon-list column (`:1:19:` etc). |
| `createChangePasswordForm` / `changePassword` | The logged-in admin's own password change (distinct from `Admin/src/validate.Authenticate.php`'s `changepassword` pre-session action — same underlying `Authenticate::changePassword()`, different entry point). |

### Reports and audit

| Action | Does |
|---|---|
| `createTransactionReportSection` / `getTransactionReport` | Spools every `Transfers` row (across all customers — this is a bank-staff report, not scoped to one customer) in a date range. |
| `createAuditLogViewerSection` / `getAuditLogsData` / `createAuditLogTable` | The `Admin.Logs` viewer — filter by date/category/user, up to 5000 rows per query. |

## What `createChangeAuthPasswordForm()` is

Defined in `util.php` but **not wired to any case in this switch** — no `action` value reaches it.
It builds a form for changing the separate "authorization password"
(`Authenticate::changeAuthPassword()` exists and is presumably reachable some other way not
present in this repo, or was left unfinished). Flagging here so it isn't mistaken for a routing bug
in `util.php` itself.
