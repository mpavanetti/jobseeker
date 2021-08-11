-- --------------------------------------------------------
-- Servidor:                     localhost
-- Versão do servidor:           10.4.11-MariaDB - mariadb.org binary distribution
-- OS do Servidor:               Win64
-- HeidiSQL Versão:              10.3.0.5771
-- Scripted by:              	 matheuspavanetti@gmail.com - 2021
-- --------------------------------------------------------

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET NAMES utf8 */;
/*!50503 SET NAMES utf8mb4 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;


-- Copiando estrutura do banco de dados para jobseeker
CREATE DATABASE IF NOT EXISTS `jobseeker` /*!40100 DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci */;
USE `jobseeker`;

-- Copiando estrutura para tabela jobseeker.ci_sessions
CREATE TABLE IF NOT EXISTS `ci_sessions` (
  `session_id` varchar(40) NOT NULL DEFAULT '0',
  `ip_address` varchar(45) NOT NULL DEFAULT '0',
  `user_agent` varchar(120) NOT NULL,
  `last_activity` int(10) unsigned NOT NULL DEFAULT 0,
  `user_data` text NOT NULL,
  PRIMARY KEY (`session_id`),
  KEY `last_activity_idx` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Copiando dados para a tabela jobseeker.ci_sessions: ~0 rows (aproximadamente)
/*!40000 ALTER TABLE `ci_sessions` DISABLE KEYS */;
/*!40000 ALTER TABLE `ci_sessions` ENABLE KEYS */;

-- Copiando estrutura para tabela jobseeker.contextdetails
CREATE TABLE IF NOT EXISTS `contextdetails` (
  `Id` int(11) NOT NULL AUTO_INCREMENT,
  `ProjectDetailsFK` int(11) NOT NULL,
  `ContextKey` varchar(1000) COLLATE utf8_unicode_ci NOT NULL,
  `ContextValue` varchar(1000) COLLATE utf8_unicode_ci DEFAULT NULL,
  `isEncrypted` tinyint(1) DEFAULT NULL,
  `EnvironmentFK` int(11) NOT NULL,
  `Description` varchar(1000) COLLATE utf8_unicode_ci DEFAULT NULL,
  `IsActive` tinyint(1) NOT NULL,
  `CreatedOn` datetime NOT NULL,
  `CreatedBy` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `ModifiedOn` datetime DEFAULT NULL,
  `ModifiedBy` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`Id`),
  KEY `ProjectDetailsFK` (`ProjectDetailsFK`),
  KEY `EnvironmentFK` (`EnvironmentFK`),
  CONSTRAINT `contextdetails_ibfk_1` FOREIGN KEY (`ProjectDetailsFK`) REFERENCES `projectdetails` (`Id`),
  CONSTRAINT `contextdetails_ibfk_2` FOREIGN KEY (`EnvironmentFK`) REFERENCES `environment` (`Id`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- Copiando dados para a tabela jobseeker.contextdetails: ~0 rows (aproximadamente)
/*!40000 ALTER TABLE `contextdetails` DISABLE KEYS */;
/*!40000 ALTER TABLE `contextdetails` ENABLE KEYS */;

-- Copiando estrutura para tabela jobseeker.database_settings
CREATE TABLE IF NOT EXISTS `database_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `job_name` varchar(200) COLLATE utf8_unicode_ci NOT NULL,
  `db_type` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
  `login` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
  `password` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
  `address` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
  `port` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
  `schema` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
  `creation_date` datetime(6) NOT NULL DEFAULT '0000-00-00 00:00:00.000000' ON UPDATE current_timestamp(6),
  `description` varchar(200) COLLATE utf8_unicode_ci NOT NULL,
  `owner` varchar(200) COLLATE utf8_unicode_ci NOT NULL,
  `additional_parameters` varchar(200) COLLATE utf8_unicode_ci NOT NULL,
  `oracle_ServiceName` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
  `oracle_sid` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- Copiando dados para a tabela jobseeker.database_settings: ~0 rows (aproximadamente)
/*!40000 ALTER TABLE `database_settings` DISABLE KEYS */;
/*!40000 ALTER TABLE `database_settings` ENABLE KEYS */;

-- Copiando estrutura para tabela jobseeker.email_settings
CREATE TABLE IF NOT EXISTS `email_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(200) COLLATE utf8_unicode_ci NOT NULL,
  `creation_date` datetime(6) NOT NULL,
  `to` varchar(200) COLLATE utf8_unicode_ci NOT NULL,
  `from` varchar(200) COLLATE utf8_unicode_ci NOT NULL,
  `cc` varchar(200) COLLATE utf8_unicode_ci NOT NULL,
  `bcc` varchar(200) COLLATE utf8_unicode_ci NOT NULL,
  `subject` varchar(200) COLLATE utf8_unicode_ci NOT NULL,
  `msg` varchar(2000) COLLATE utf8_unicode_ci NOT NULL,
  `attachment` varchar(2000) COLLATE utf8_unicode_ci NOT NULL,
  `smtp` varchar(200) COLLATE utf8_unicode_ci NOT NULL,
  `enabled` int(1) NOT NULL,
  `owner` varchar(200) COLLATE utf8_unicode_ci NOT NULL,
  `description` varchar(200) COLLATE utf8_unicode_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=24 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- Copiando dados para a tabela jobseeker.email_settings: ~0 rows (aproximadamente)
/*!40000 ALTER TABLE `email_settings` DISABLE KEYS */;
/*!40000 ALTER TABLE `email_settings` ENABLE KEYS */;

-- Copiando estrutura para tabela jobseeker.environment
CREATE TABLE IF NOT EXISTS `environment` (
  `Id` int(11) NOT NULL AUTO_INCREMENT,
  `Environment` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
  `Description` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
  `IsActive` tinyint(1) NOT NULL,
  `CreatedOn` datetime NOT NULL,
  `ModifiedOn` datetime DEFAULT NULL,
  PRIMARY KEY (`Id`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- Copiando dados para a tabela jobseeker.environment: ~0 rows (aproximadamente)
/*!40000 ALTER TABLE `environment` DISABLE KEYS */;
/*!40000 ALTER TABLE `environment` ENABLE KEYS */;

-- Copiando estrutura para tabela jobseeker.generic_settings
CREATE TABLE IF NOT EXISTS `generic_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `job_name` varchar(30) COLLATE utf8_unicode_ci NOT NULL,
  `setting` varchar(200) COLLATE utf8_unicode_ci NOT NULL,
  `value1` varchar(200) COLLATE utf8_unicode_ci NOT NULL,
  `value2` varchar(200) COLLATE utf8_unicode_ci NOT NULL,
  `value3` varchar(200) COLLATE utf8_unicode_ci NOT NULL,
  `value4` varchar(200) COLLATE utf8_unicode_ci NOT NULL,
  `value5` varchar(200) COLLATE utf8_unicode_ci NOT NULL,
  `creation_date` datetime(6) DEFAULT NULL ON UPDATE current_timestamp(6),
  `owner` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
  `description` varchar(800) COLLATE utf8_unicode_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- Copiando dados para a tabela jobseeker.generic_settings: ~0 rows (aproximadamente)
/*!40000 ALTER TABLE `generic_settings` DISABLE KEYS */;
/*!40000 ALTER TABLE `generic_settings` ENABLE KEYS */;

-- Copiando estrutura para tabela jobseeker.job_info
CREATE TABLE IF NOT EXISTS `job_info` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `job_name` varchar(100) DEFAULT NULL,
  `job_component` varchar(100) DEFAULT NULL,
  `component_type` varchar(100) DEFAULT NULL,
  `creation_date` datetime(6) DEFAULT NULL ON UPDATE current_timestamp(6),
  `file_path` varchar(500) DEFAULT NULL,
  `file_name` varchar(200) DEFAULT NULL,
  `file` int(1) DEFAULT NULL,
  `path` varchar(200) DEFAULT NULL,
  `file_uploaded` int(20) DEFAULT NULL,
  `owner` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=111 DEFAULT CHARSET=latin1;

-- Copiando dados para a tabela jobseeker.job_info: ~0 rows (aproximadamente)
/*!40000 ALTER TABLE `job_info` DISABLE KEYS */;
/*!40000 ALTER TABLE `job_info` ENABLE KEYS */;

-- Copiando estrutura para tabela jobseeker.job_output
CREATE TABLE IF NOT EXISTS `job_output` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `job_name` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
  `job_component` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
  `component_type` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
  `creation_date` datetime(6) NOT NULL,
  `file_name` varchar(200) COLLATE utf8_unicode_ci NOT NULL,
  `file_path` varchar(30) COLLATE utf8_unicode_ci NOT NULL,
  `path` varchar(200) COLLATE utf8_unicode_ci NOT NULL,
  `file_downloaded` int(20) NOT NULL,
  `owner` varchar(50) COLLATE utf8_unicode_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- Copiando dados para a tabela jobseeker.job_output: ~0 rows (aproximadamente)
/*!40000 ALTER TABLE `job_output` DISABLE KEYS */;
/*!40000 ALTER TABLE `job_output` ENABLE KEYS */;

-- Copiando estrutura para tabela jobseeker.projectdetails
CREATE TABLE IF NOT EXISTS `projectdetails` (
  `Id` int(11) NOT NULL AUTO_INCREMENT,
  `ProjectName` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `GitPath` varchar(510) COLLATE utf8_unicode_ci DEFAULT NULL,
  `IsActive` tinyint(1) NOT NULL,
  `CreatedOn` datetime NOT NULL,
  `ModifiedOn` datetime DEFAULT NULL,
  PRIMARY KEY (`Id`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- Copiando dados para a tabela jobseeker.projectdetails: ~0 rows (aproximadamente)
/*!40000 ALTER TABLE `projectdetails` DISABLE KEYS */;
/*!40000 ALTER TABLE `projectdetails` ENABLE KEYS */;

-- Copiando estrutura para tabela jobseeker.reports
CREATE TABLE IF NOT EXISTS `reports` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `creation_date` datetime NOT NULL,
  `name` varchar(200) COLLATE utf8_unicode_ci NOT NULL,
  `type` varchar(200) COLLATE utf8_unicode_ci NOT NULL,
  `users` varchar(5000) COLLATE utf8_unicode_ci NOT NULL,
  `groups` varchar(5000) COLLATE utf8_unicode_ci NOT NULL,
  `code` varchar(5000) COLLATE utf8_unicode_ci NOT NULL,
  `owner` varchar(200) COLLATE utf8_unicode_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=45 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- Copiando dados para a tabela jobseeker.reports: ~0 rows (aproximadamente)
/*!40000 ALTER TABLE `reports` DISABLE KEYS */;
/*!40000 ALTER TABLE `reports` ENABLE KEYS */;

-- Copiando estrutura para tabela jobseeker.smtp_settings
CREATE TABLE IF NOT EXISTS `smtp_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(200) COLLATE utf8_unicode_ci NOT NULL,
  `smtp_host` varchar(200) COLLATE utf8_unicode_ci NOT NULL,
  `smtp_port` int(25) NOT NULL,
  `username` varchar(200) COLLATE utf8_unicode_ci NOT NULL,
  `password` varchar(200) COLLATE utf8_unicode_ci NOT NULL,
  `ssl` int(1) NOT NULL,
  `creation_date` datetime(6) NOT NULL,
  `owner` varchar(200) COLLATE utf8_unicode_ci NOT NULL,
  `description` varchar(200) COLLATE utf8_unicode_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- Copiando dados para a tabela jobseeker.smtp_settings: ~0 rows (aproximadamente)
/*!40000 ALTER TABLE `smtp_settings` DISABLE KEYS */;
/*!40000 ALTER TABLE `smtp_settings` ENABLE KEYS */;

-- Copiando estrutura para tabela jobseeker.tbl_groups
CREATE TABLE IF NOT EXISTS `tbl_groups` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `creation_date` datetime NOT NULL,
  `owner` varchar(200) COLLATE utf8_unicode_ci NOT NULL,
  `name` varchar(200) COLLATE utf8_unicode_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- Copiando dados para a tabela jobseeker.tbl_groups: ~3 rows (aproximadamente)
/*!40000 ALTER TABLE `tbl_groups` DISABLE KEYS */;
INSERT INTO `tbl_groups` (`id`, `creation_date`, `owner`, `name`) VALUES
	(2, '2020-05-29 15:25:45', 'Matheus', 'Developers'),
	(18, '2020-06-03 04:43:26', 'System Administrator', 'Public'),
	(21, '2021-08-11 19:32:37', 'System Administrator', 'Admins');
/*!40000 ALTER TABLE `tbl_groups` ENABLE KEYS */;

-- Copiando estrutura para tabela jobseeker.tbl_last_login
CREATE TABLE IF NOT EXISTS `tbl_last_login` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `userId` bigint(20) NOT NULL,
  `sessionData` varchar(2048) NOT NULL,
  `machineIp` varchar(1024) NOT NULL,
  `userAgent` varchar(128) NOT NULL,
  `agentString` varchar(1024) NOT NULL,
  `platform` varchar(128) NOT NULL,
  `createdDtm` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=361 DEFAULT CHARSET=utf8;

-- Copiando dados para a tabela jobseeker.tbl_last_login: ~0 rows (aproximadamente)
/*!40000 ALTER TABLE `tbl_last_login` DISABLE KEYS */;
INSERT INTO `tbl_last_login` (`id`, `userId`, `sessionData`, `machineIp`, `userAgent`, `agentString`, `platform`, `createdDtm`) VALUES
	(360, 1, '{"role":"1","roleText":"System Administrator","name":"System Administrator"}', '::1', 'Chrome 92.0.4515.131', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/92.0.4515.131 Safari/537.36', 'Windows 10', '2021-08-11 14:37:07');
/*!40000 ALTER TABLE `tbl_last_login` ENABLE KEYS */;

-- Copiando estrutura para tabela jobseeker.tbl_reset_password
CREATE TABLE IF NOT EXISTS `tbl_reset_password` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `email` varchar(128) NOT NULL,
  `activation_id` varchar(32) NOT NULL,
  `agent` varchar(512) NOT NULL,
  `client_ip` varchar(32) NOT NULL,
  `isDeleted` tinyint(4) NOT NULL DEFAULT 0,
  `createdBy` bigint(20) NOT NULL DEFAULT 1,
  `createdDtm` datetime NOT NULL,
  `updatedBy` bigint(20) DEFAULT NULL,
  `updatedDtm` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=latin1;

-- Copiando dados para a tabela jobseeker.tbl_reset_password: ~0 rows (aproximadamente)
/*!40000 ALTER TABLE `tbl_reset_password` DISABLE KEYS */;
/*!40000 ALTER TABLE `tbl_reset_password` ENABLE KEYS */;

-- Copiando estrutura para tabela jobseeker.tbl_roles
CREATE TABLE IF NOT EXISTS `tbl_roles` (
  `roleId` tinyint(4) NOT NULL AUTO_INCREMENT COMMENT 'role id',
  `role` varchar(50) NOT NULL COMMENT 'role text',
  PRIMARY KEY (`roleId`)
) ENGINE=MyISAM AUTO_INCREMENT=4 DEFAULT CHARSET=utf8;

-- Copiando dados para a tabela jobseeker.tbl_roles: 3 rows
/*!40000 ALTER TABLE `tbl_roles` DISABLE KEYS */;
INSERT INTO `tbl_roles` (`roleId`, `role`) VALUES
	(1, 'System Administrator'),
	(2, 'Developer'),
	(3, 'Key User');
/*!40000 ALTER TABLE `tbl_roles` ENABLE KEYS */;

-- Copiando estrutura para tabela jobseeker.tbl_users
CREATE TABLE IF NOT EXISTS `tbl_users` (
  `userId` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(128) NOT NULL COMMENT 'login email',
  `password` varchar(128) NOT NULL COMMENT 'hashed login password',
  `name` varchar(128) DEFAULT NULL COMMENT 'full name of user',
  `mobile` varchar(20) DEFAULT NULL,
  `groupId` int(11) DEFAULT NULL,
  `roleId` tinyint(4) NOT NULL,
  `isDeleted` tinyint(4) NOT NULL DEFAULT 0,
  `createdBy` int(11) NOT NULL,
  `createdDtm` datetime NOT NULL,
  `updatedBy` int(11) DEFAULT NULL,
  `updatedDtm` datetime DEFAULT NULL,
  PRIMARY KEY (`userId`)
) ENGINE=MyISAM AUTO_INCREMENT=19 DEFAULT CHARSET=utf8;

-- Copiando dados para a tabela jobseeker.tbl_users: 3 rows
/*!40000 ALTER TABLE `tbl_users` DISABLE KEYS */;
INSERT INTO `tbl_users` (`userId`, `email`, `password`, `name`, `mobile`, `groupId`, `roleId`, `isDeleted`, `createdBy`, `createdDtm`, `updatedBy`, `updatedDtm`) VALUES
	(1, 'admin@example.com', '$2y$10$rqyS7G8jngEyE5TAaFruduuyO7m1BtWGaq401l/ptJPjiXeYTLeCG', 'System Administrator', '9890098900', NULL, 1, 0, 0, '2015-07-01 18:56:49', 1, '2019-11-27 20:20:34'),
	(17, 'keyuser@example.com', '$2y$10$wSC09UX5gpLKPZUxxGIpfOx3mRTGNXGqYu7BSO42BpVuht.ilnGUm', 'Key User', '012981992589', 18, 3, 0, 1, '2021-08-11 19:26:20', 1, '2021-08-11 19:26:56'),
	(18, 'developer@example.com', '$2y$10$EuKfsYSVZM3z693lBFuTpuDnER4W3UhqbDSn.yRT4y1OSlVCm9Zw.', 'Developer', '012981992589', 2, 2, 0, 1, '2021-08-11 19:26:44', NULL, NULL);
/*!40000 ALTER TABLE `tbl_users` ENABLE KEYS */;

-- Copiando estrutura para tabela jobseeker.tmf
CREATE TABLE IF NOT EXISTS `tmf` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `interface_id` varchar(200) COLLATE utf8_unicode_ci NOT NULL,
  `status` varchar(200) COLLATE utf8_unicode_ci NOT NULL,
  `job_name` varchar(200) COLLATE utf8_unicode_ci NOT NULL,
  `reprocess` tinyint(1) DEFAULT NULL,
  `event_text` varchar(200) COLLATE utf8_unicode_ci DEFAULT NULL,
  `dimension` varchar(200) COLLATE utf8_unicode_ci DEFAULT NULL,
  `environment` varchar(15) COLLATE utf8_unicode_ci DEFAULT NULL,
  `records_total` varchar(200) COLLATE utf8_unicode_ci DEFAULT NULL,
  `records_processed` varchar(200) COLLATE utf8_unicode_ci DEFAULT NULL,
  `last_activity` datetime(6) DEFAULT NULL,
  `running_time` time(6) DEFAULT NULL,
  `distict_errors` tinyint(1) DEFAULT NULL,
  `warnings` varchar(200) COLLATE utf8_unicode_ci DEFAULT NULL,
  `hostname` varchar(200) COLLATE utf8_unicode_ci DEFAULT NULL,
  `username` varchar(200) COLLATE utf8_unicode_ci DEFAULT NULL,
  `instance_id` varchar(50) COLLATE utf8_unicode_ci DEFAULT NULL,
  `start_time` datetime DEFAULT NULL,
  `msg` text COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1234 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- Copiando dados para a tabela jobseeker.tmf: ~0 rows (aproximadamente)
/*!40000 ALTER TABLE `tmf` DISABLE KEYS */;
/*!40000 ALTER TABLE `tmf` ENABLE KEYS */;

-- Copiando estrutura para tabela jobseeker.tmf_error
CREATE TABLE IF NOT EXISTS `tmf_error` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tmf_id` varchar(50) COLLATE utf8_unicode_ci DEFAULT NULL,
  `job_name` varchar(200) COLLATE utf8_unicode_ci DEFAULT NULL,
  `moment` datetime(6) DEFAULT NULL,
  `type` varchar(200) COLLATE utf8_unicode_ci DEFAULT NULL,
  `origin` varchar(200) COLLATE utf8_unicode_ci DEFAULT NULL,
  `message` varchar(5000) COLLATE utf8_unicode_ci DEFAULT NULL,
  `code` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=878 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- Copiando dados para a tabela jobseeker.tmf_error: ~0 rows (aproximadamente)
/*!40000 ALTER TABLE `tmf_error` DISABLE KEYS */;
/*!40000 ALTER TABLE `tmf_error` ENABLE KEYS */;

-- Copiando estrutura para view jobseeker.vw_contextdetails
-- Criando tabela temporária para evitar erros de dependência de VIEW
CREATE TABLE `vw_contextdetails` (
	`Id` INT(11) NOT NULL,
	`ContextKey` VARCHAR(1000) NOT NULL COLLATE 'utf8_unicode_ci',
	`ContextValue` VARCHAR(1000) NULL COLLATE 'utf8_unicode_ci',
	`isEncrypted` TINYINT(1) NULL,
	`ProjectName` VARCHAR(255) NOT NULL COLLATE 'utf8_unicode_ci',
	`Environment` VARCHAR(100) NOT NULL COLLATE 'utf8_unicode_ci',
	`Description` VARCHAR(1000) NULL COLLATE 'utf8_unicode_ci',
	`IsActive` TINYINT(1) NOT NULL
) ENGINE=MyISAM;

-- Copiando estrutura para view jobseeker.vw_contextdetails
-- Removendo tabela temporária e criando a estrutura VIEW final
DROP TABLE IF EXISTS `vw_contextdetails`;
CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `vw_contextdetails` AS SELECT 
cd.id AS Id, 
cd.ContextKey AS ContextKey,
cd.ContextValue AS ContextValue,
cd.isEncrypted AS isEncrypted,
pd.ProjectName AS ProjectName,
env.Environment AS Environment,
cd.Description AS Description,
cd.IsActive AS IsActive
FROM contextdetails cd, projectdetails pd, environment env
WHERE cd.ProjectDetailsFK = pd.Id 
AND cd.EnvironmentFK = env.Id ;

/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IF(@OLD_FOREIGN_KEY_CHECKS IS NULL, 1, @OLD_FOREIGN_KEY_CHECKS) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
