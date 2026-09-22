-- ============================================================================
-- Schemas the application queries but `Database/all.sql` does not create.
-- That dump creates exactly two databases: `dbPetra` and `Admin`.
--
-- 1. `dbInternetBank`.`StatementRequest` — created below. Written to by
--    Account::getStatementPDF(), reached via CoreFront's
--    `get_account_statement_pdf` action. The definition here is INFERRED from
--    the single INSERT in that method and has never been checked against the
--    production DDL, so treat it as local-development scaffolding only and
--    confirm the real structure before relying on it anywhere else.
--
-- 2. `dbCredits` — NOT created here, and not created anywhere else in this
--    repository. CoreFront/loans.php's `get_loan_details` action queries
--    `dbCredits.Loans`, `dbCredits.Statements` and `dbCredits.LiquidatedLoans`
--    directly, so that one action cannot succeed against a database built
--    solely from this repo; it needs a `dbCredits` supplied from elsewhere.
-- ============================================================================

CREATE DATABASE IF NOT EXISTS `dbInternetBank` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;

CREATE TABLE `dbInternetBank`.`StatementRequest` (
  `ID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `DateCreated` timestamp NOT NULL DEFAULT current_timestamp(),
  `CustomerID` varchar(255) DEFAULT NULL,
  `AccountNo` varchar(20) DEFAULT NULL,
  `StartDate` date DEFAULT NULL,
  `EndDate` date DEFAULT NULL,
  PRIMARY KEY (`ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
