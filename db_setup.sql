-- --------------------------------------------------------
-- Servidor:                     localhost
-- Versão do servidor:           10.4.11-MariaDB - mariadb.org binary distribution
-- OS do Servidor:               Win64
-- HeidiSQL Versão:              10.3.0.5771
-- Scripted by:              	 maintainer@example.com - 2021
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
`id` varchar(40) NOT NULL,
`ip_address` varchar(45) NOT NULL,
`timestamp` int(10) unsigned DEFAULT 0 NOT NULL,
`data` blob NOT NULL,
PRIMARY KEY (id),
KEY `ci_sessions_timestamp` (`timestamp`));

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
  `connector_key` varchar(128) COLLATE utf8_unicode_ci NOT NULL,
  `job_name` varchar(200) COLLATE utf8_unicode_ci NOT NULL DEFAULT '*',
  `environment` varchar(100) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'ALL',
  `db_type` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
  `auth_type` varchar(50) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'username_password',
  `login` varchar(100) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `password` varchar(100) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `address` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `port` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
  `schema` varchar(200) COLLATE utf8_unicode_ci NOT NULL,
  `creation_date` datetime(6) NOT NULL DEFAULT '0000-00-00 00:00:00.000000' ON UPDATE current_timestamp(6),
  `updated_at` datetime DEFAULT NULL,
  `description` varchar(2000) COLLATE utf8_unicode_ci NOT NULL,
  `secret_backend` varchar(30) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'local',
  `secret_reference` longtext COLLATE utf8_unicode_ci DEFAULT NULL,
  `secret_encrypted` longtext COLLATE utf8_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `owner` varchar(200) COLLATE utf8_unicode_ci NOT NULL,
  `additional_parameters` varchar(1000) COLLATE utf8_unicode_ci NOT NULL,
  `oracle_ServiceName` varchar(200) COLLATE utf8_unicode_ci NOT NULL,
  `oracle_sid` varchar(200) COLLATE utf8_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `database_settings_scope` (`connector_key`,`environment`,`job_name`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- Copiando dados para a tabela jobseeker.database_settings: ~0 rows (aproximadamente)
/*!40000 ALTER TABLE `database_settings` DISABLE KEYS */;
/*!40000 ALTER TABLE `database_settings` ENABLE KEYS */;

CREATE TABLE IF NOT EXISTS `connector_access_log` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `connector_id` int(11) DEFAULT NULL,
  `connector_key` varchar(128) COLLATE utf8_unicode_ci NOT NULL,
  `environment` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
  `job_name` varchar(200) COLLATE utf8_unicode_ci NOT NULL,
  `secret_backend` varchar(30) COLLATE utf8_unicode_ci NOT NULL,
  `status` varchar(20) COLLATE utf8_unicode_ci NOT NULL,
  `accessed_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `connector_accessed_at` (`accessed_at`),
  KEY `connector_access_scope` (`connector_key`,`environment`,`job_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

CREATE TABLE IF NOT EXISTS `job_pipelines` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `pipeline_key` varchar(128) COLLATE utf8_unicode_ci NOT NULL,
  `name` varchar(200) COLLATE utf8_unicode_ci NOT NULL,
  `group_name` varchar(128) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'General',
  `description` varchar(2000) COLLATE utf8_unicode_ci DEFAULT NULL,
  `environment` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
  `graph_json` longtext COLLATE utf8_unicode_ci NOT NULL,
  `jenkins_job_name` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `sync_status` varchar(20) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'pending',
  `sync_error` varchar(1000) COLLATE utf8_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `version` int(11) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `owner` varchar(200) COLLATE utf8_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `job_pipeline_scope` (`pipeline_key`,`environment`),
  KEY `job_pipeline_environment` (`environment`),
  KEY `job_pipeline_group` (`group_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

CREATE TABLE IF NOT EXISTS `job_pipeline_runs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `pipeline_id` int(11) NOT NULL,
  `jenkins_queue_id` bigint(20) unsigned DEFAULT NULL,
  `jenkins_build_number` int(11) unsigned DEFAULT NULL,
  `status` varchar(30) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'QUEUED',
  `environment` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
  `triggered_by` varchar(200) COLLATE utf8_unicode_ci NOT NULL,
  `started_at` datetime NOT NULL,
  `completed_at` datetime DEFAULT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `job_pipeline_run_pipeline` (`pipeline_id`,`id`),
  KEY `job_pipeline_run_status` (`status`,`updated_at`),
  CONSTRAINT `job_pipeline_runs_pipeline_fk` FOREIGN KEY (`pipeline_id`) REFERENCES `job_pipelines` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

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
  `msg` mediumtext COLLATE utf8_unicode_ci NOT NULL,
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

-- Unified runtime-neutral input/output datasets and file contracts.
CREATE TABLE IF NOT EXISTS `data_assets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `asset_key` varchar(128) COLLATE utf8_unicode_ci NOT NULL,
  `name` varchar(200) COLLATE utf8_unicode_ci NOT NULL,
  `direction` varchar(20) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'input',
  `format` varchar(30) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'csv',
  `environment` varchar(100) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'ALL',
  `job_name` varchar(200) COLLATE utf8_unicode_ci NOT NULL DEFAULT '*',
  `storage_path` varchar(1000) COLLATE utf8_unicode_ci NOT NULL,
  `file_name` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `options_json` longtext COLLATE utf8_unicode_ci DEFAULT NULL,
  `description` varchar(2000) COLLATE utf8_unicode_ci DEFAULT NULL,
  `is_required` tinyint(1) NOT NULL DEFAULT 1,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `version` int(11) NOT NULL DEFAULT 0,
  `file_size` bigint(20) unsigned DEFAULT NULL,
  `checksum` char(64) COLLATE utf8_unicode_ci DEFAULT NULL,
  `uploaded_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `owner` varchar(200) COLLATE utf8_unicode_ci NOT NULL,
  `legacy_source` varchar(30) COLLATE utf8_unicode_ci DEFAULT NULL,
  `legacy_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `data_assets_scope` (`asset_key`,`environment`,`job_name`),
  UNIQUE KEY `data_assets_legacy` (`legacy_source`,`legacy_id`),
  KEY `data_assets_environment` (`environment`),
  KEY `data_assets_direction` (`direction`),
  KEY `data_assets_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

CREATE TABLE IF NOT EXISTS `data_asset_migrations` (
  `migration_key` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
  `applied_at` datetime NOT NULL,
  PRIMARY KEY (`migration_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

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

-- Saved, governed dashboards created in Insight Studio
CREATE TABLE IF NOT EXISTS `visualization_dashboards` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(120) COLLATE utf8_unicode_ci NOT NULL,
  `description` varchar(500) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `owner_id` int(11) NOT NULL,
  `owner` varchar(128) COLLATE utf8_unicode_ci NOT NULL,
  `definition_json` mediumtext COLLATE utf8_unicode_ci NOT NULL,
  `is_shared` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `visualization_dashboards_owner` (`owner_id`),
  KEY `visualization_dashboards_shared` (`is_shared`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- Encrypted, governed data sources exposed through Insight Studio. Credentials
-- never leave the server and dataset definitions contain approved fields only.
CREATE TABLE IF NOT EXISTS `visualization_connections` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(120) COLLATE utf8_unicode_ci NOT NULL,
  `driver` varchar(20) COLLATE utf8_unicode_ci NOT NULL,
  `host` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `port` int(11) NOT NULL,
  `database_name` varchar(128) COLLATE utf8_unicode_ci NOT NULL,
  `username` varchar(128) COLLATE utf8_unicode_ci NOT NULL,
  `password_encrypted` text COLLATE utf8_unicode_ci NOT NULL,
  `ssl_mode` varchar(20) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'required',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `owner_id` int(11) NOT NULL,
  `owner` varchar(128) COLLATE utf8_unicode_ci NOT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `visualization_connections_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

CREATE TABLE IF NOT EXISTS `visualization_datasets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `connection_id` int(11) NOT NULL,
  `name` varchar(120) COLLATE utf8_unicode_ci NOT NULL,
  `dataset_key` varchar(40) COLLATE utf8_unicode_ci NOT NULL,
  `description` varchar(500) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `table_schema` varchar(128) COLLATE utf8_unicode_ci NOT NULL,
  `table_name` varchar(128) COLLATE utf8_unicode_ci NOT NULL,
  `dimensions_json` mediumtext COLLATE utf8_unicode_ci NOT NULL,
  `measures_json` mediumtext COLLATE utf8_unicode_ci NOT NULL,
  `time_column` varchar(128) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `environment_column` varchar(128) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `owner_id` int(11) NOT NULL,
  `owner` varchar(128) COLLATE utf8_unicode_ci NOT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `visualization_datasets_key` (`dataset_key`),
  KEY `visualization_datasets_connection` (`connection_id`),
  KEY `visualization_datasets_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- Copiando estrutura para tabela jobseeker.smtp_settings
CREATE TABLE IF NOT EXISTS `smtp_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(200) COLLATE utf8_unicode_ci NOT NULL,
  `smtp_host` varchar(200) COLLATE utf8_unicode_ci NOT NULL,
  `smtp_port` int(25) NOT NULL,
  `username` varchar(200) COLLATE utf8_unicode_ci NOT NULL,
  `password` varchar(200) COLLATE utf8_unicode_ci NOT NULL,
  `ssl` int(1) NOT NULL,
  `is_enabled` int(1) NOT NULL DEFAULT 1,
  `is_default` int(1) NOT NULL DEFAULT 0,
  `reply_to` varchar(200) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'jobseeker@local.test',
  `creation_date` datetime(6) NOT NULL,
  `owner` varchar(200) COLLATE utf8_unicode_ci NOT NULL,
  `description` varchar(200) COLLATE utf8_unicode_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- Copiando dados para a tabela jobseeker.smtp_settings: ~0 rows (aproximadamente)
/*!40000 ALTER TABLE `smtp_settings` DISABLE KEYS */;
INSERT INTO `smtp_settings` (`name`, `smtp_host`, `smtp_port`, `username`, `password`, `ssl`, `is_enabled`, `is_default`, `reply_to`, `creation_date`, `owner`, `description`) VALUES
  ('Local Mailpit', 'mailpit', 1025, '', '', 0, 1, 1, 'jobseeker@local.test', CURRENT_TIMESTAMP(6), 'System', 'Local test inbox; captures Jenkins emails in Mailpit and does not deliver to external mailboxes.');
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
  PRIMARY KEY (`id`),
  KEY `tmf_dashboard_activity` (`last_activity`,`status`,`environment`),
  KEY `tmf_dashboard_environment` (`environment`,`last_activity`,`status`)
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

-- ---------------------------------------------------------------------------
-- Machine Learning platform.
-- The Ml*_model classes run CREATE TABLE IF NOT EXISTS + seed at load, so a
-- running stack self-heals; these statements only keep a fresh install
-- schema-complete. Runtime images are built by scripts/build-ml-runtimes.sh and
-- artifacts flow over HTTP (machine-learning/runtime/*), so no extra volumes are
-- required here.
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `ml_runtime` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `runtime_key` varchar(64) COLLATE utf8_unicode_ci NOT NULL,
  `display_name` varchar(160) COLLATE utf8_unicode_ci NOT NULL,
  `kind` varchar(24) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'cpu',
  `image_repository` varchar(200) COLLATE utf8_unicode_ci NOT NULL,
  `image_tag` varchar(120) COLLATE utf8_unicode_ci NOT NULL,
  `base_image` varchar(200) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'continuumio/miniconda3',
  `library_summary` varchar(1000) COLLATE utf8_unicode_ci DEFAULT NULL,
  `description` varchar(1000) COLLATE utf8_unicode_ci DEFAULT NULL,
  `default_cpu_limit` decimal(5,2) NOT NULL DEFAULT 1.00,
  `default_memory_mb` int(11) NOT NULL DEFAULT 2048,
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(11) NOT NULL DEFAULT 100,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ml_runtime_key` (`runtime_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

CREATE TABLE IF NOT EXISTS `ml_sample` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sample_key` varchar(96) COLLATE utf8_unicode_ci NOT NULL,
  `name` varchar(200) COLLATE utf8_unicode_ci NOT NULL,
  `category` varchar(48) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'tabular',
  `run_type` varchar(24) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'train',
  `runtime_key` varchar(64) COLLATE utf8_unicode_ci NOT NULL,
  `description` varchar(2000) COLLATE utf8_unicode_ci DEFAULT NULL,
  `entry_point` varchar(400) COLLATE utf8_unicode_ci DEFAULT NULL,
  `code` longtext COLLATE utf8_unicode_ci NOT NULL,
  `params_schema_json` longtext COLLATE utf8_unicode_ci DEFAULT NULL,
  `dataset_roles_json` longtext COLLATE utf8_unicode_ci DEFAULT NULL,
  `tags` varchar(400) COLLATE utf8_unicode_ci DEFAULT NULL,
  `is_builtin` tinyint(1) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(11) NOT NULL DEFAULT 100,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `owner` varchar(200) COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ml_sample_key` (`sample_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

CREATE TABLE IF NOT EXISTS `ml_experiment` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `experiment_key` varchar(128) COLLATE utf8_unicode_ci NOT NULL,
  `name` varchar(200) COLLATE utf8_unicode_ci NOT NULL,
  `environment` varchar(100) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'ALL',
  `description` varchar(2000) COLLATE utf8_unicode_ci DEFAULT NULL,
  `primary_metric` varchar(120) COLLATE utf8_unicode_ci DEFAULT NULL,
  `metric_goal` varchar(8) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'max',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `owner` varchar(200) COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ml_experiment_scope` (`experiment_key`,`environment`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

CREATE TABLE IF NOT EXISTS `ml_job` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `job_key` varchar(128) COLLATE utf8_unicode_ci NOT NULL,
  `name` varchar(200) COLLATE utf8_unicode_ci NOT NULL,
  `group_name` varchar(128) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'General',
  `environment` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
  `experiment_id` int(11) DEFAULT NULL,
  `runtime_key` varchar(64) COLLATE utf8_unicode_ci NOT NULL,
  `sample_key` varchar(96) COLLATE utf8_unicode_ci DEFAULT NULL,
  `description` varchar(2000) COLLATE utf8_unicode_ci DEFAULT NULL,
  `source_type` varchar(20) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'inline',
  `entry_point` varchar(400) COLLATE utf8_unicode_ci DEFAULT NULL,
  `entrypoint` varchar(200) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'main.py',
  `inline_code` longtext COLLATE utf8_unicode_ci DEFAULT NULL,
  `dependency_mode` varchar(16) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'requirements',
  `requirements_txt` text COLLATE utf8_unicode_ci DEFAULT NULL,
  `pyproject_text` text COLLATE utf8_unicode_ci DEFAULT NULL,
  `dockerfile` text COLLATE utf8_unicode_ci DEFAULT NULL,
  `image_tag` varchar(200) COLLATE utf8_unicode_ci DEFAULT NULL,
  `image_state` varchar(16) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'none',
  `image_digest` varchar(120) COLLATE utf8_unicode_ci DEFAULT NULL,
  `image_built_at` datetime DEFAULT NULL,
  `image_build_log` mediumtext COLLATE utf8_unicode_ci DEFAULT NULL,
  `workspace_hash` varchar(32) COLLATE utf8_unicode_ci DEFAULT NULL,
  `application_args` varchar(2000) COLLATE utf8_unicode_ci DEFAULT NULL,
  `params_json` longtext COLLATE utf8_unicode_ci DEFAULT NULL,
  `dataset_bindings_json` longtext COLLATE utf8_unicode_ci DEFAULT NULL,
  `env_json` longtext COLLATE utf8_unicode_ci DEFAULT NULL,
  `run_type` varchar(24) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'unknown',
  `run_type_source` varchar(12) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'auto',
  `run_type_confidence` decimal(4,3) NOT NULL DEFAULT 0.000,
  `introspection_json` longtext COLLATE utf8_unicode_ci DEFAULT NULL,
  `cpu_limit` decimal(5,2) NOT NULL DEFAULT 1.00,
  `memory_limit_mb` int(11) NOT NULL DEFAULT 2048,
  `timeout_seconds` int(11) NOT NULL DEFAULT 3600,
  `schedule_cron` varchar(120) COLLATE utf8_unicode_ci DEFAULT NULL,
  `jenkins_job_name` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `version` int(11) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `owner` varchar(200) COLLATE utf8_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ml_job_scope` (`job_key`,`environment`),
  KEY `ml_job_environment` (`environment`),
  KEY `ml_job_runtime` (`runtime_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

CREATE TABLE IF NOT EXISTS `ml_run` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `run_key` varchar(40) COLLATE utf8_unicode_ci NOT NULL,
  `job_id` int(11) DEFAULT NULL,
  `experiment_id` int(11) DEFAULT NULL,
  `name` varchar(200) COLLATE utf8_unicode_ci DEFAULT NULL,
  `environment` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
  `run_type` varchar(24) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'unknown',
  `status` varchar(30) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'QUEUED',
  `trigger_source` varchar(24) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'manual',
  `triggered_by` varchar(200) COLLATE utf8_unicode_ci NOT NULL,
  `runtime_key` varchar(64) COLLATE utf8_unicode_ci DEFAULT NULL,
  `image_ref` varchar(300) COLLATE utf8_unicode_ci DEFAULT NULL,
  `driver` varchar(24) COLLATE utf8_unicode_ci DEFAULT NULL,
  `container_id` varchar(96) COLLATE utf8_unicode_ci DEFAULT NULL,
  `params_json` longtext COLLATE utf8_unicode_ci DEFAULT NULL,
  `tags_json` longtext COLLATE utf8_unicode_ci DEFAULT NULL,
  `metrics_summary_json` longtext COLLATE utf8_unicode_ci DEFAULT NULL,
  `cpu_limit` decimal(5,2) NOT NULL DEFAULT 1.00,
  `memory_limit_mb` int(11) NOT NULL DEFAULT 2048,
  `jenkins_build_number` int(10) unsigned DEFAULT NULL,
  `exit_code` int(11) DEFAULT NULL,
  `log_tail` mediumtext COLLATE utf8_unicode_ci DEFAULT NULL,
  `error_message` varchar(2000) COLLATE utf8_unicode_ci DEFAULT NULL,
  `queued_at` datetime NOT NULL,
  `started_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `heartbeat_at` datetime DEFAULT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ml_run_key` (`run_key`),
  KEY `ml_run_job` (`job_id`,`id`),
  KEY `ml_run_experiment` (`experiment_id`,`id`),
  KEY `ml_run_status` (`status`,`updated_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

CREATE TABLE IF NOT EXISTS `ml_run_metric` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `run_id` bigint(20) unsigned NOT NULL,
  `metric_key` varchar(120) COLLATE utf8_unicode_ci NOT NULL,
  `step` int(11) NOT NULL DEFAULT 0,
  `value` double NOT NULL,
  `recorded_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ml_run_metric_point` (`run_id`,`metric_key`,`step`),
  KEY `ml_run_metric_run` (`run_id`,`metric_key`,`step`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

CREATE TABLE IF NOT EXISTS `ml_artifact` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `sha256` char(64) COLLATE utf8_unicode_ci NOT NULL,
  `media_type` varchar(120) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'application/octet-stream',
  `size_bytes` bigint(20) unsigned NOT NULL DEFAULT 0,
  `storage_backend` varchar(16) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'local',
  `storage_uri` varchar(1000) COLLATE utf8_unicode_ci NOT NULL,
  `original_name` varchar(400) COLLATE utf8_unicode_ci DEFAULT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ml_artifact_sha` (`sha256`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

CREATE TABLE IF NOT EXISTS `ml_run_artifact` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `run_id` bigint(20) unsigned NOT NULL,
  `artifact_id` bigint(20) unsigned NOT NULL,
  `role` varchar(32) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'artifact',
  `path` varchar(700) COLLATE utf8_unicode_ci NOT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ml_run_artifact_path` (`run_id`,`path`),
  KEY `ml_run_artifact_run` (`run_id`),
  KEY `ml_run_artifact_artifact` (`artifact_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

CREATE TABLE IF NOT EXISTS `ml_lineage_edge` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `src_kind` varchar(24) COLLATE utf8_unicode_ci NOT NULL,
  `src_id` bigint(20) unsigned NOT NULL,
  `dst_kind` varchar(24) COLLATE utf8_unicode_ci NOT NULL,
  `dst_id` bigint(20) unsigned NOT NULL,
  `role` varchar(32) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'uses',
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ml_lineage_edge_unique` (`src_kind`,`src_id`,`dst_kind`,`dst_id`,`role`),
  KEY `ml_lineage_src` (`src_kind`,`src_id`),
  KEY `ml_lineage_dst` (`dst_kind`,`dst_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- v2: the ML dataset registry is unified into the Data Assets store. Datasets
-- are `data_assets` rows; immutable versions with schema/profile/fingerprint
-- live in `data_asset_versions`. DataAssets_model also ALTERs `data_assets` to
-- add `kind`, `source`, `tags`, `latest_version`, `schema_json`,
-- `profile_status` at load; a fresh install gets them here.
ALTER TABLE `data_assets`
  ADD COLUMN IF NOT EXISTS `kind` varchar(24) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'file' AFTER `format`,
  ADD COLUMN IF NOT EXISTS `source` varchar(24) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'manual' AFTER `kind`,
  ADD COLUMN IF NOT EXISTS `tags` varchar(400) COLLATE utf8_unicode_ci DEFAULT NULL AFTER `description`,
  ADD COLUMN IF NOT EXISTS `latest_version` int(11) NOT NULL DEFAULT 0 AFTER `version`,
  ADD COLUMN IF NOT EXISTS `schema_json` longtext COLLATE utf8_unicode_ci DEFAULT NULL AFTER `latest_version`,
  ADD COLUMN IF NOT EXISTS `profile_status` varchar(16) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'none' AFTER `schema_json`;

CREATE TABLE IF NOT EXISTS `data_asset_versions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `asset_id` int(11) NOT NULL,
  `version` int(11) NOT NULL,
  `source_type` varchar(24) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'upload',
  `source_ref_json` longtext COLLATE utf8_unicode_ci DEFAULT NULL,
  `storage_path` varchar(1000) COLLATE utf8_unicode_ci DEFAULT NULL,
  `artifact_id` bigint(20) unsigned DEFAULT NULL,
  `checksum` char(64) COLLATE utf8_unicode_ci DEFAULT NULL,
  `size_bytes` bigint(20) unsigned DEFAULT NULL,
  `format` varchar(24) COLLATE utf8_unicode_ci DEFAULT NULL,
  `row_count` bigint(20) DEFAULT NULL,
  `column_count` int(11) DEFAULT NULL,
  `schema_json` longtext COLLATE utf8_unicode_ci DEFAULT NULL,
  `profile_json` longtext COLLATE utf8_unicode_ci DEFAULT NULL,
  `fingerprint_json` longtext COLLATE utf8_unicode_ci DEFAULT NULL,
  `profile_status` varchar(16) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'pending',
  `profile_error` varchar(2000) COLLATE utf8_unicode_ci DEFAULT NULL,
  `produced_by_run_id` bigint(20) unsigned DEFAULT NULL,
  `notes` varchar(2000) COLLATE utf8_unicode_ci DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `created_by` varchar(200) COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `data_asset_versions_scope` (`asset_id`,`version`),
  KEY `data_asset_versions_run` (`produced_by_run_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

CREATE TABLE IF NOT EXISTS `ml_model` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `model_key` varchar(128) COLLATE utf8_unicode_ci NOT NULL,
  `name` varchar(200) COLLATE utf8_unicode_ci NOT NULL,
  `environment` varchar(100) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'ALL',
  `task` varchar(48) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'classification',
  `description` varchar(2000) COLLATE utf8_unicode_ci DEFAULT NULL,
  `primary_metric` varchar(120) COLLATE utf8_unicode_ci DEFAULT NULL,
  `metric_goal` varchar(8) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'max',
  `tags` varchar(400) COLLATE utf8_unicode_ci DEFAULT NULL,
  `latest_version` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `owner` varchar(200) COLLATE utf8_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ml_model_scope` (`model_key`,`environment`),
  KEY `ml_model_environment` (`environment`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

CREATE TABLE IF NOT EXISTS `ml_model_version` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `model_id` int(11) NOT NULL,
  `version` int(11) NOT NULL,
  `run_id` bigint(20) unsigned DEFAULT NULL,
  `stage` varchar(16) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'none',
  `artifact_id` bigint(20) unsigned DEFAULT NULL,
  `framework` varchar(48) COLLATE utf8_unicode_ci DEFAULT NULL,
  `metrics_json` longtext COLLATE utf8_unicode_ci DEFAULT NULL,
  `params_json` longtext COLLATE utf8_unicode_ci DEFAULT NULL,
  `signature_json` longtext COLLATE utf8_unicode_ci DEFAULT NULL,
  `training_dataset_version_id` bigint(20) unsigned DEFAULT NULL,
  `notes` varchar(2000) COLLATE utf8_unicode_ci DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `created_by` varchar(200) COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ml_model_version_scope` (`model_id`,`version`),
  KEY `ml_model_version_stage` (`model_id`,`stage`),
  KEY `ml_model_version_run` (`run_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

CREATE TABLE IF NOT EXISTS `ml_model_stage_event` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `model_version_id` bigint(20) unsigned NOT NULL,
  `from_stage` varchar(16) COLLATE utf8_unicode_ci NOT NULL,
  `to_stage` varchar(16) COLLATE utf8_unicode_ci NOT NULL,
  `reason` varchar(1000) COLLATE utf8_unicode_ci DEFAULT NULL,
  `actor` varchar(200) COLLATE utf8_unicode_ci DEFAULT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `ml_model_stage_event_version` (`model_version_id`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

CREATE TABLE IF NOT EXISTS `ml_monitor` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `monitor_key` varchar(128) COLLATE utf8_unicode_ci NOT NULL,
  `name` varchar(200) COLLATE utf8_unicode_ci NOT NULL,
  `environment` varchar(100) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'ALL',
  `model_id` int(11) NOT NULL,
  `model_version_id` bigint(20) unsigned DEFAULT NULL,
  `track_stage` varchar(16) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'production',
  `baseline_dataset_version_id` bigint(20) unsigned DEFAULT NULL,
  `config_json` longtext COLLATE utf8_unicode_ci DEFAULT NULL,
  `schedule_cron` varchar(120) COLLATE utf8_unicode_ci DEFAULT NULL,
  `jenkins_job_name` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `status` varchar(16) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'ok',
  `last_run_at` datetime DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `owner` varchar(200) COLLATE utf8_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ml_monitor_scope` (`monitor_key`,`environment`),
  KEY `ml_monitor_model` (`model_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

CREATE TABLE IF NOT EXISTS `ml_monitor_run` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `monitor_id` int(11) NOT NULL,
  `status` varchar(16) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'RUNNING',
  `trigger_source` varchar(16) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'manual',
  `current_dataset_version_id` bigint(20) unsigned DEFAULT NULL,
  `summary_json` longtext COLLATE utf8_unicode_ci DEFAULT NULL,
  `drift_score` double DEFAULT NULL,
  `alerts_opened` int(11) NOT NULL DEFAULT 0,
  `error_message` varchar(2000) COLLATE utf8_unicode_ci DEFAULT NULL,
  `started_at` datetime NOT NULL,
  `completed_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ml_monitor_run_monitor` (`monitor_id`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

CREATE TABLE IF NOT EXISTS `ml_monitor_point` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `monitor_id` int(11) NOT NULL,
  `monitor_run_id` bigint(20) unsigned DEFAULT NULL,
  `recorded_at` datetime NOT NULL,
  `metric_key` varchar(64) COLLATE utf8_unicode_ci NOT NULL,
  `feature` varchar(200) COLLATE utf8_unicode_ci NOT NULL DEFAULT '__overall__',
  `value` double NOT NULL,
  `breached` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `ml_monitor_point_series` (`monitor_id`,`metric_key`,`feature`,`recorded_at`),
  KEY `ml_monitor_point_run` (`monitor_run_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

CREATE TABLE IF NOT EXISTS `ml_alert` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `monitor_id` int(11) DEFAULT NULL,
  `monitor_run_id` bigint(20) unsigned DEFAULT NULL,
  `run_id` bigint(20) unsigned DEFAULT NULL,
  `environment` varchar(100) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'ALL',
  `severity` varchar(12) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'warning',
  `category` varchar(32) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'drift',
  `title` varchar(300) COLLATE utf8_unicode_ci NOT NULL,
  `detail` varchar(2000) COLLATE utf8_unicode_ci DEFAULT NULL,
  `metric_key` varchar(64) COLLATE utf8_unicode_ci DEFAULT NULL,
  `feature` varchar(200) COLLATE utf8_unicode_ci DEFAULT NULL,
  `observed_value` double DEFAULT NULL,
  `threshold_value` double DEFAULT NULL,
  `state` varchar(16) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'open',
  `fingerprint` char(40) COLLATE utf8_unicode_ci DEFAULT NULL,
  `notified_at` datetime DEFAULT NULL,
  `acknowledged_by` varchar(200) COLLATE utf8_unicode_ci DEFAULT NULL,
  `acknowledged_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `ml_alert_state` (`state`,`created_at`),
  KEY `ml_alert_monitor` (`monitor_id`,`state`),
  KEY `ml_alert_fingerprint` (`fingerprint`,`state`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IF(@OLD_FOREIGN_KEY_CHECKS IS NULL, 1, @OLD_FOREIGN_KEY_CHECKS) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;

-- Grant Privilleges
GRANT SELECT, INSERT, UPDATE, DELETE ON jobseeker.* TO 'mysql'@'%';

-- pre defined contexts
INSERT INTO `projectdetails` (`Id`, `ProjectName`, `GitPath`, `IsActive`, `CreatedOn`, `ModifiedOn`) VALUES
(1,	'PoC',	'',	1,	CURRENT_TIMESTAMP(), NULL);

INSERT INTO `jobseeker`.`environment` (`Id`, `Environment`, `Description`, `IsActive`, `CreatedOn`, `ModifiedOn`) VALUES
(1,	'LOCAL',	'',	1,	CURRENT_TIMESTAMP(),	NULL),
(2,	'DEV',	'',	1,	CURRENT_TIMESTAMP(),	NULL),
(3,	'QA',	'',	1,	CURRENT_TIMESTAMP(),	NULL),
(4,	'UAT',	'',	1,	CURRENT_TIMESTAMP(),	NULL),
(5,	'PREPROD',	'',	1,	CURRENT_TIMESTAMP(),	NULL),
(6,	'PROD',	'',	1,	CURRENT_TIMESTAMP(),	NULL);

INSERT INTO `jobseeker`.`contextdetails` (`Id`, `ProjectDetailsFK`, `ContextKey`, `ContextValue`, `isEncrypted`, `EnvironmentFK`, `Description`, `IsActive`, `CreatedOn`, `CreatedBy`, `ModifiedOn`, `ModifiedBy`) VALUES
(1,	1,	'rows',	'100',	0,	1,	'',	1,	CURRENT_TIMESTAMP(),	'Developer',	NULL,	NULL);

INSERT INTO `jobseeker`.`contextdetails` (`Id`, `ProjectDetailsFK`, `ContextKey`, `ContextValue`, `isEncrypted`, `EnvironmentFK`, `Description`, `IsActive`, `CreatedOn`, `CreatedBy`, `ModifiedOn`, `ModifiedBy`) VALUES
(2,	1,	'Custom',	'This is a custom context from jobseeker LOCAL',	0,	1,	'',	1,	CURRENT_TIMESTAMP(),	'Developer',	NULL,	NULL);

INSERT INTO `jobseeker`.`contextdetails` (`Id`, `ProjectDetailsFK`, `ContextKey`, `ContextValue`, `isEncrypted`, `EnvironmentFK`, `Description`, `IsActive`, `CreatedOn`, `CreatedBy`, `ModifiedOn`, `ModifiedBy`) VALUES
(3,	1,	'Custom',	'This is a custom context from jobseeker DEV',	0,	2,	'',	1,	CURRENT_TIMESTAMP(),	'Developer',	NULL,	NULL);
