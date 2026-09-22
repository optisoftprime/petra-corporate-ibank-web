-- ============================================================================
-- LOCAL-DEV-ONLY SEED DATA. Never load this against a real environment.
--
-- Adds exactly one synthetic admin login for local testing, and nothing else.
-- Database/all.sql already supplies real Category, Forms, Roles, Services and
-- Users rows — enough to render the sidebar — so this file must stay purely
-- additive: reference the existing RoleID 1 ('Administrator') rather than
-- inserting roles or categories of its own, or the load will collide with
-- that real data and fail.
-- ============================================================================

-- UserID: admin@local.test  Password: LocalDevOnly!2026
-- TOTP secret below is a real, freshly generated Base32 secret (not a
-- placeholder) so class.TOTPManager can validate a live code against it.
INSERT INTO `Admin`.`Users`
  (`UserID`, `RoleID`, `Password`, `Username`, `LPasswordCDate`, `ManagerID`,
   `Mobile`, `Email`, `Auth_Password`, `LAuth_PasswordCDate`, `LastLoginDate`, `Secret`)
VALUES
  ('admin@local.test', '1',
   '$2y$12$qjDCwsArCiqNxPjHSjR0GO1ZRmRXoeRlmk2BEd5d6shwlBEpMteO2',
   'Local Test Admin', NOW(), '',
   '08000000000', 'admin@local.test',
   '$2y$12$qjDCwsArCiqNxPjHSjR0GO1ZRmRXoeRlmk2BEd5d6shwlBEpMteO2',
   NOW(), NOW(), 'XNM6FH6T6VXH7X7OWDJYDTE4GIO4JDKENKTR5KRNNUGYV7G4SCQQ');
