-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Creato il: Nov 18, 2026 alle 11:30
-- Versione del server: 10.4.32-MariaDB
-- Versione PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `dwight`
--

-- --------------------------------------------------------

--
-- Struttura della tabella `dwight-admins`
--

CREATE TABLE `dwight-admins` (
  `ID` int(11) NOT NULL,
  `UUID` text NOT NULL,
  `Username` varchar(64) NOT NULL,
  `Hash` text NOT NULL,
  `UsesOTP` tinyint(1) NOT NULL DEFAULT 0,
  `OTPCode` text DEFAULT NULL,
  `Privileges` text NOT NULL DEFAULT 'A',
  `IsActive` tinyint(1) NOT NULL DEFAULT 1,
  `TelegramUID` bigint(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `dwight-bans`
--

CREATE TABLE `dwight-bans` (
  `ID` int(11) NOT NULL,
  `UUID` text NOT NULL,
  `Scope` char(2) NOT NULL DEFAULT 'L',
  `Reason` text NOT NULL,
  `State` char(2) NOT NULL DEFAULT 'A',
  `IssuedByDwightAutomation` tinyint(1) NOT NULL DEFAULT 0,
  `AutomationID` int(11) DEFAULT NULL,
  `IssuedByModerator` tinyint(1) NOT NULL DEFAULT 0,
  `ModeratorID` int(11) NOT NULL,
  `IssuedAt` timestamp NOT NULL DEFAULT current_timestamp(),
  `ExpiresAt` timestamp NULL DEFAULT NULL,
  `RevokedByDwightAutomation` tinyint(1) NOT NULL DEFAULT 0,
  `RevocatingAutomationID` int(11) DEFAULT NULL,
  `RevokedByModerator` tinyint(1) NOT NULL DEFAULT 0,
  `RevocatingModeratorID` int(11) DEFAULT NULL,
  `RevokeReason` text DEFAULT NULL,
  `RevokedAt` timestamp NULL DEFAULT NULL,
  `BannedUserID` int(11) NOT NULL,
  `OffendingMessage` int(11) DEFAULT NULL,
  `GroupID` int(11) DEFAULT NULL,
  `IsDM` tinyint(1) NOT NULL DEFAULT 0,
  `TelegramExecutionXcheck` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `dwight-mutes`
--

CREATE TABLE `dwight-mutes` (
  `ID` int(11) NOT NULL,
  `UUID` text NOT NULL,
  `Scope` char(2) NOT NULL DEFAULT 'L',
  `Reason` text NOT NULL,
  `State` char(2) NOT NULL DEFAULT 'A',
  `IssuedByDwightAutomation` tinyint(1) NOT NULL DEFAULT 0,
  `AutomationID` int(11) DEFAULT NULL,
  `IssuedByModerator` tinyint(1) NOT NULL DEFAULT 0,
  `ModeratorID` int(11) NOT NULL,
  `IssuedAt` timestamp NOT NULL DEFAULT current_timestamp(),
  `ExpiresAt` timestamp NULL DEFAULT NULL,
  `RevokedByDwightAutomation` tinyint(1) NOT NULL DEFAULT 0,
  `RevocatingAutomationID` int(11) DEFAULT NULL,
  `RevokedByModerator` tinyint(1) NOT NULL DEFAULT 0,
  `RevocatingModeratorID` int(11) DEFAULT NULL,
  `RevokeReason` text DEFAULT NULL,
  `RevokedAt` timestamp NULL DEFAULT NULL,
  `MutedUserID` int(11) NOT NULL,
  `OffendingMessage` int(11) DEFAULT NULL,
  `GroupID` int(11) DEFAULT NULL,
  `IsDM` tinyint(1) NOT NULL DEFAULT 0,
  `TelegramExecutionXcheck` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `dwight-offending-messages`
--

CREATE TABLE `dwight-offending-messages` (
  `ID` int(11) NOT NULL,
  `UUID` text NOT NULL,
  `Body` text DEFAULT NULL,
  `SenderUser` int(11) NOT NULL,
  `Redacted` tinyint(1) NOT NULL DEFAULT 0,
  `RedactionReason` text DEFAULT NULL,
  `GroupID` int(11) DEFAULT NULL,
  `TelegramID` int(11) DEFAULT NULL,
  `TelegramChatID` bigint(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `dwight-overseen-groups`
--

CREATE TABLE `dwight-overseen-groups` (
  `ID` int(11) NOT NULL,
  `UUID` text NOT NULL,
  `Name` text NOT NULL,
  `IsActive` tinyint(1) NOT NULL DEFAULT 1,
  `Redacted` tinyint(1) NOT NULL DEFAULT 0,
  `RedactionReason` text DEFAULT NULL,
  `TelegramID` bigint(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `dwight-overseen-users`
--

CREATE TABLE `dwight-overseen-users` (
  `ID` int(11) NOT NULL,
  `UUID` text NOT NULL,
  `FriendlyName` text NOT NULL,
  `Redacted` tinyint(1) NOT NULL DEFAULT 0,
  `RedactionReason` text DEFAULT NULL,
  `TelegramUID` bigint(20) NOT NULL,
  `TelegramAtUsername` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `dwight-warns`
--

CREATE TABLE `dwight-warns` (
  `ID` int(11) NOT NULL,
  `UUID` text NOT NULL,
  `Scope` char(2) NOT NULL DEFAULT 'L',
  `Reason` text NOT NULL,
  `State` char(2) NOT NULL DEFAULT 'A',
  `IssuedByDwightAutomation` tinyint(1) NOT NULL DEFAULT 0,
  `AutomationID` int(11) DEFAULT NULL,
  `IssuedByModerator` tinyint(1) NOT NULL DEFAULT 0,
  `ModeratorID` int(11) NOT NULL,
  `IssuedAt` timestamp NOT NULL DEFAULT current_timestamp(),
  `ExpiresAt` timestamp NULL DEFAULT NULL,
  `RevokedByDwightAutomation` tinyint(1) NOT NULL DEFAULT 0,
  `RevocatingAutomationID` int(11) DEFAULT NULL,
  `RevokedByModerator` tinyint(1) NOT NULL DEFAULT 0,
  `RevocatingModeratorID` int(11) DEFAULT NULL,
  `RevokeReason` text DEFAULT NULL,
  `RevokedAt` timestamp NULL DEFAULT NULL,
  `WarnedUserID` int(11) NOT NULL,
  `OffendingMessage` int(11) DEFAULT NULL,
  `GroupID` int(11) DEFAULT NULL,
  `IsDM` tinyint(1) NOT NULL DEFAULT 0,
  `TelegramExecutionXcheck` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indici per le tabelle scaricate
--

--
-- Indici per le tabelle `dwight-admins`
--
ALTER TABLE `dwight-admins`
  ADD PRIMARY KEY (`ID`),
  ADD UNIQUE KEY `Username` (`Username`),
  ADD UNIQUE KEY `TelegramUID` (`TelegramUID`),
  ADD UNIQUE KEY `dwight-admin-uuid` (`UUID`) USING HASH;

--
-- Indici per le tabelle `dwight-bans`
--
ALTER TABLE `dwight-bans`
  ADD PRIMARY KEY (`ID`),
  ADD UNIQUE KEY `dwight-ban-uuid` (`UUID`) USING HASH,
  ADD KEY `AutomationID` (`AutomationID`),
  ADD KEY `ModeratorID` (`ModeratorID`),
  ADD KEY `RevocatingAutomationID` (`RevocatingAutomationID`),
  ADD KEY `RevocatingModeratorID` (`RevocatingModeratorID`),
  ADD KEY `BannedUserID` (`BannedUserID`),
  ADD KEY `GroupID` (`GroupID`),
  ADD KEY `OffendingMessage` (`OffendingMessage`);

--
-- Indici per le tabelle `dwight-mutes`
--
ALTER TABLE `dwight-mutes`
  ADD PRIMARY KEY (`ID`),
  ADD UNIQUE KEY `dwight-mute-uuid` (`UUID`) USING HASH,
  ADD KEY `AutomationID` (`AutomationID`),
  ADD KEY `ModeratorID` (`ModeratorID`),
  ADD KEY `RevocatingAutomationID` (`RevocatingAutomationID`),
  ADD KEY `RevocatingModeratorID` (`RevocatingModeratorID`),
  ADD KEY `GroupID` (`GroupID`),
  ADD KEY `OffendingMessage` (`OffendingMessage`),
  ADD KEY `MutedUserID` (`MutedUserID`) USING BTREE;

--
-- Indici per le tabelle `dwight-offending-messages`
--
ALTER TABLE `dwight-offending-messages`
  ADD PRIMARY KEY (`ID`),
  ADD UNIQUE KEY `dwight-offending-message-uuid` (`UUID`) USING HASH,
  ADD KEY `SenderUser` (`SenderUser`),
  ADD KEY `GroupID` (`GroupID`);

--
-- Indici per le tabelle `dwight-overseen-groups`
--
ALTER TABLE `dwight-overseen-groups`
  ADD PRIMARY KEY (`ID`),
  ADD UNIQUE KEY `TelegramID` (`TelegramID`),
  ADD UNIQUE KEY `dwight-overseengroup-uuid` (`UUID`) USING HASH;

--
-- Indici per le tabelle `dwight-overseen-users`
--
ALTER TABLE `dwight-overseen-users`
  ADD PRIMARY KEY (`ID`),
  ADD UNIQUE KEY `TelegramUID` (`TelegramUID`),
  ADD UNIQUE KEY `dwight-overseen-user-uuid` (`UUID`) USING HASH;

--
-- Indici per le tabelle `dwight-warns`
--
ALTER TABLE `dwight-warns`
  ADD PRIMARY KEY (`ID`),
  ADD UNIQUE KEY `dwight-warn-uuid` (`UUID`) USING HASH,
  ADD KEY `AutomationID` (`AutomationID`),
  ADD KEY `ModeratorID` (`ModeratorID`),
  ADD KEY `RevocatingAutomationID` (`RevocatingAutomationID`),
  ADD KEY `RevocatingModeratorID` (`RevocatingModeratorID`),
  ADD KEY `GroupID` (`GroupID`),
  ADD KEY `OffendingMessage` (`OffendingMessage`),
  ADD KEY `WarnedUserID` (`WarnedUserID`) USING BTREE;

--
-- AUTO_INCREMENT per le tabelle scaricate
--

--
-- AUTO_INCREMENT per la tabella `dwight-admins`
--
ALTER TABLE `dwight-admins`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `dwight-bans`
--
ALTER TABLE `dwight-bans`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `dwight-mutes`
--
ALTER TABLE `dwight-mutes`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `dwight-offending-messages`
--
ALTER TABLE `dwight-offending-messages`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `dwight-overseen-groups`
--
ALTER TABLE `dwight-overseen-groups`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `dwight-overseen-users`
--
ALTER TABLE `dwight-overseen-users`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `dwight-warns`
--
ALTER TABLE `dwight-warns`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT;

--
-- Limiti per le tabelle scaricate
--

--
-- Limiti per la tabella `dwight-bans`
--
ALTER TABLE `dwight-bans`
  ADD CONSTRAINT `dwight-bans_ibfk_1` FOREIGN KEY (`ModeratorID`) REFERENCES `dwight-admins` (`ID`),
  ADD CONSTRAINT `dwight-bans_ibfk_2` FOREIGN KEY (`RevocatingModeratorID`) REFERENCES `dwight-admins` (`ID`),
  ADD CONSTRAINT `dwight-bans_ibfk_3` FOREIGN KEY (`BannedUserID`) REFERENCES `dwight-overseen-users` (`ID`),
  ADD CONSTRAINT `dwight-bans_ibfk_4` FOREIGN KEY (`GroupID`) REFERENCES `dwight-overseen-groups` (`ID`),
  ADD CONSTRAINT `dwight-bans_ibfk_5` FOREIGN KEY (`OffendingMessage`) REFERENCES `dwight-offending-messages` (`ID`);

--
-- Limiti per la tabella `dwight-mutes`
--
ALTER TABLE `dwight-mutes`
  ADD CONSTRAINT `dwight-mutes_ibfk_1` FOREIGN KEY (`ModeratorID`) REFERENCES `dwight-admins` (`ID`),
  ADD CONSTRAINT `dwight-mutes_ibfk_2` FOREIGN KEY (`RevocatingModeratorID`) REFERENCES `dwight-admins` (`ID`),
  ADD CONSTRAINT `dwight-mutes_ibfk_3` FOREIGN KEY (`MutedUserID`) REFERENCES `dwight-overseen-users` (`ID`),
  ADD CONSTRAINT `dwight-mutes_ibfk_4` FOREIGN KEY (`GroupID`) REFERENCES `dwight-overseen-groups` (`ID`),
  ADD CONSTRAINT `dwight-mutes_ibfk_5` FOREIGN KEY (`OffendingMessage`) REFERENCES `dwight-offending-messages` (`ID`);

--
-- Limiti per la tabella `dwight-offending-messages`
--
ALTER TABLE `dwight-offending-messages`
  ADD CONSTRAINT `dwight-offending-messages_ibfk_1` FOREIGN KEY (`SenderUser`) REFERENCES `dwight-overseen-users` (`ID`),
  ADD CONSTRAINT `dwight-offending-messages_ibfk_2` FOREIGN KEY (`GroupID`) REFERENCES `dwight-overseen-groups` (`ID`);

--
-- Limiti per la tabella `dwight-warns`
--
ALTER TABLE `dwight-warns`
  ADD CONSTRAINT `dwight-warns_ibfk_1` FOREIGN KEY (`WarnedUserID`) REFERENCES `dwight-overseen-users` (`ID`),
  ADD CONSTRAINT `dwight-warns_ibfk_2` FOREIGN KEY (`GroupID`) REFERENCES `dwight-overseen-groups` (`ID`),
  ADD CONSTRAINT `dwight-warns_ibfk_3` FOREIGN KEY (`ModeratorID`) REFERENCES `dwight-admins` (`ID`),
  ADD CONSTRAINT `dwight-warns_ibfk_4` FOREIGN KEY (`RevocatingModeratorID`) REFERENCES `dwight-admins` (`ID`),
  ADD CONSTRAINT `dwight-warns_ibfk_5` FOREIGN KEY (`OffendingMessage`) REFERENCES `dwight-offending-messages` (`ID`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
