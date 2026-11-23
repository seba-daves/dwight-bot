-- Only if you created `dwight-login-attempts` before 2026-11-23
-- (new installs already get this index from auth-tables.sql).
-- Lets the periodic cleanup of old attempts use an index instead of reading the whole table.
ALTER TABLE `dwight-login-attempts`
  ADD KEY `AttemptedAt` (`AttemptedAt`);
