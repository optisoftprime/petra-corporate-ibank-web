-- LOCAL-DEV-ONLY SEED DATA. Never load this against a real environment.
-- A synthetic CoreFront (customer-facing API) login, purely additive, for
-- exercising the per-login crypto key end to end (login -> validateOTP ->
-- an authenticated endpoint) without touching any of the real Credentials
-- rows already in Database/all.sql.
--
-- UserID/login: customer@local.test   Password: CustomerDevOnly!2026
-- CustomerID '0000001' is not a real BankOne customer, so BankOne-backed
-- actions (get_accounts, etc.) will reach BankOne and get an empty/failed
-- response in this sandbox — expected, not a failure of what's being
-- tested here (the encryption layer, not the BankOne integration).
--
-- Each docker-entrypoint-initdb.d/*.sql file runs as its own mysql
-- invocation with no inherited USE context from 00-dbpetra-dump.sql, so
-- this needs its own (unlike 02/03, which fully schema-qualify every name).
USE `dbPetra`;

INSERT INTO `Credentials`
  (`UserID`, `Password`, `Status`, `CustomerID`, `TxnLimit`, `SingleTxnLimit`,
   `Role`, `Firstname`, `Lastname`, `Mobile`, `Email`)
VALUES
  ('customer@local.test',
   '$2y$12$pbjXqB6dmP5l2dRCuA78oeteO.NYbXRr1SJl2GsNfoIyoe0zewf.G',
   'Active', '0000001', 5000000.00, 1000000.00,
   'Initiator', 'Local', 'Test Customer', '08000000001', 'customer@local.test');
