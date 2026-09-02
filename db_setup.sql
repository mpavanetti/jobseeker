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

-- Registry for removable synthetic performance-test batches created from the
-- admin Dataset Generator or the command-line generator script.
CREATE TABLE IF NOT EXISTS `generated_datasets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `batch_key` varchar(64) COLLATE utf8_unicode_ci NOT NULL,
  `profile` varchar(30) COLLATE utf8_unicode_ci NOT NULL,
  `status` varchar(30) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'generating',
  `tmf_rows` int(11) unsigned NOT NULL DEFAULT 0,
  `error_rows` int(11) unsigned NOT NULL DEFAULT 0,
  `job_count` int(11) unsigned NOT NULL DEFAULT 0,
  `pipeline_count` int(11) unsigned NOT NULL DEFAULT 0,
  `pipeline_run_rows` int(11) unsigned NOT NULL DEFAULT 0,
  `seed_value` int(11) unsigned NOT NULL DEFAULT 1,
  `include_jenkins` tinyint(1) NOT NULL DEFAULT 0,
  `config_json` longtext COLLATE utf8_unicode_ci DEFAULT NULL,
  `metrics_json` longtext COLLATE utf8_unicode_ci DEFAULT NULL,
  `created_by` varchar(200) COLLATE utf8_unicode_ci NOT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `generated_dataset_batch` (`batch_key`),
  KEY `generated_dataset_created` (`created_at`)
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

-- Estrutura para tabela jobseeker.tbl_login_attempts (brute-force throttle for the sign-in form)
CREATE TABLE IF NOT EXISTS `tbl_login_attempts` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `email` varchar(190) COLLATE utf8_unicode_ci NOT NULL,
  `ip_address` varchar(45) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `successful` tinyint(1) NOT NULL DEFAULT 0,
  `attempted_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `login_attempts_email` (`email`,`attempted_at`),
  KEY `login_attempts_ip` (`ip_address`,`attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

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
