-- The table the login needs, on top of your current `dwight` schema.
-- Paste into phpMyAdmin → SQL tab, with the `dwight` database selected.

-- Failed logins, for the brute-force limit.
-- Only failures are stored; rows older than a day are deleted automatically.
CREATE TABLE `dwight-login-attempts` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `Username` varchar(64) NOT NULL,
  `IP` varchar(45) NOT NULL,
  `AttemptedAt` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`ID`),
  KEY `Username` (`Username`, `AttemptedAt`),
  KEY `IP` (`IP`, `AttemptedAt`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
