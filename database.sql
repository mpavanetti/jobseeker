-- phpMyAdmin SQL Dump
-- version 4.9.2
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Tempo de geração: 19-Jul-2020 às 23:59
-- Versão do servidor: 10.4.11-MariaDB
-- versão do PHP: 7.4.1

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Banco de dados: `talend`
--

-- --------------------------------------------------------

--
-- Estrutura da tabela `ci_sessions`
--

CREATE TABLE `ci_sessions` (
  `session_id` varchar(40) NOT NULL DEFAULT '0',
  `ip_address` varchar(45) NOT NULL DEFAULT '0',
  `user_agent` varchar(120) NOT NULL,
  `last_activity` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `user_data` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Estrutura da tabela `database_settings`
--

CREATE TABLE `database_settings` (
  `id` int(11) NOT NULL,
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
  `oracle_sid` varchar(100) COLLATE utf8_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

--
-- Extraindo dados da tabela `database_settings`
--

INSERT INTO `database_settings` (`id`, `job_name`, `db_type`, `login`, `password`, `address`, `port`, `schema`, `creation_date`, `description`, `owner`, `additional_parameters`, `oracle_ServiceName`, `oracle_sid`) VALUES
(5, 'DW_Vendas', 'oracle_sid', 'biapleite', 'q1w2e3r4A!s@d#', 'oracle-dev.cajp1e3hhhw9.us-east-1.rds.amazonaws.com', '1521', 'biapleite', '2020-07-07 22:27:42.000000', 'Banco de dados Oracle 11G XE Local casa Matheus', 'Matheus', '', '', 'ORCL');

-- --------------------------------------------------------

--
-- Estrutura da tabela `email_settings`
--

CREATE TABLE `email_settings` (
  `id` int(11) NOT NULL,
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
  `description` varchar(200) COLLATE utf8_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura da tabela `generic_settings`
--

CREATE TABLE `generic_settings` (
  `id` int(11) NOT NULL,
  `job_name` varchar(30) COLLATE utf8_unicode_ci NOT NULL,
  `setting` varchar(200) COLLATE utf8_unicode_ci NOT NULL,
  `value1` varchar(200) COLLATE utf8_unicode_ci NOT NULL,
  `value2` varchar(200) COLLATE utf8_unicode_ci NOT NULL,
  `value3` varchar(200) COLLATE utf8_unicode_ci NOT NULL,
  `value4` varchar(200) COLLATE utf8_unicode_ci NOT NULL,
  `value5` varchar(200) COLLATE utf8_unicode_ci NOT NULL,
  `creation_date` datetime(6) DEFAULT NULL ON UPDATE current_timestamp(6),
  `owner` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
  `description` varchar(800) COLLATE utf8_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura da tabela `job_info`
--

CREATE TABLE `job_info` (
  `id` int(11) NOT NULL,
  `job_name` varchar(100) DEFAULT NULL,
  `job_component` varchar(100) DEFAULT NULL,
  `component_type` varchar(100) DEFAULT NULL,
  `creation_date` datetime(6) DEFAULT NULL ON UPDATE current_timestamp(6),
  `file_path` varchar(500) DEFAULT NULL,
  `file_name` varchar(200) DEFAULT NULL,
  `file` int(1) DEFAULT NULL,
  `path` varchar(200) DEFAULT NULL,
  `file_uploaded` int(20) DEFAULT NULL,
  `owner` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Estrutura da tabela `job_output`
--

CREATE TABLE `job_output` (
  `id` int(11) NOT NULL,
  `job_name` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
  `job_component` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
  `component_type` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
  `creation_date` datetime(6) NOT NULL,
  `file_name` varchar(200) COLLATE utf8_unicode_ci NOT NULL,
  `file_path` varchar(30) COLLATE utf8_unicode_ci NOT NULL,
  `path` varchar(200) COLLATE utf8_unicode_ci NOT NULL,
  `file_downloaded` int(20) NOT NULL,
  `owner` varchar(50) COLLATE utf8_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura da tabela `reports`
--

CREATE TABLE `reports` (
  `id` int(11) NOT NULL,
  `creation_date` datetime NOT NULL,
  `name` varchar(200) COLLATE utf8_unicode_ci NOT NULL,
  `type` varchar(200) COLLATE utf8_unicode_ci NOT NULL,
  `users` varchar(5000) COLLATE utf8_unicode_ci NOT NULL,
  `groups` varchar(5000) COLLATE utf8_unicode_ci NOT NULL,
  `code` varchar(5000) COLLATE utf8_unicode_ci NOT NULL,
  `owner` varchar(200) COLLATE utf8_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura da tabela `smtp_settings`
--

CREATE TABLE `smtp_settings` (
  `id` int(11) NOT NULL,
  `name` varchar(200) COLLATE utf8_unicode_ci NOT NULL,
  `smtp_host` varchar(200) COLLATE utf8_unicode_ci NOT NULL,
  `smtp_port` int(25) NOT NULL,
  `username` varchar(200) COLLATE utf8_unicode_ci NOT NULL,
  `password` varchar(200) COLLATE utf8_unicode_ci NOT NULL,
  `ssl` int(1) NOT NULL,
  `creation_date` datetime(6) NOT NULL,
  `owner` varchar(200) COLLATE utf8_unicode_ci NOT NULL,
  `description` varchar(200) COLLATE utf8_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura da tabela `tbl_groups`
--

CREATE TABLE `tbl_groups` (
  `id` int(11) NOT NULL,
  `creation_date` datetime NOT NULL,
  `owner` varchar(200) COLLATE utf8_unicode_ci NOT NULL,
  `name` varchar(200) COLLATE utf8_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

--
-- Extraindo dados da tabela `tbl_groups`
--

INSERT INTO `tbl_groups` (`id`, `creation_date`, `owner`, `name`) VALUES
(2, '2020-05-29 15:25:45', 'Matheus', 'Developers'),
(18, '2020-06-03 04:43:26', 'System Administrator', 'Public'),
(20, '2020-06-03 04:45:24', 'System Administrator', 'Aham');

-- --------------------------------------------------------

--
-- Estrutura da tabela `tbl_last_login`
--

CREATE TABLE `tbl_last_login` (
  `id` bigint(20) NOT NULL,
  `userId` bigint(20) NOT NULL,
  `sessionData` varchar(2048) NOT NULL,
  `machineIp` varchar(1024) NOT NULL,
  `userAgent` varchar(128) NOT NULL,
  `agentString` varchar(1024) NOT NULL,
  `platform` varchar(128) NOT NULL,
  `createdDtm` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Extraindo dados da tabela `tbl_last_login`
--

INSERT INTO `tbl_last_login` (`id`, `userId`, `sessionData`, `machineIp`, `userAgent`, `agentString`, `platform`, `createdDtm`) VALUES
(1, 1, '{\"role\":\"1\",\"roleText\":\"System Administrator\",\"name\":\"System Administrator\"}', '::1', 'Chrome 78.0.3904.108', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/78.0.3904.108 Safari/537.36', 'Windows 10', '2019-11-27 15:39:30'),
(2, 1, '{\"role\":\"1\",\"roleText\":\"System Administrator\",\"name\":\"System Administrator\"}', '::1', 'Chrome 78.0.3904.108', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/78.0.3904.108 Safari/537.36', 'Windows 10', '2019-11-27 15:39:36'),
(3, 1, '{\"role\":\"1\",\"roleText\":\"System Administrator\",\"name\":\"System Administrator\"}', '::1', 'Chrome 78.0.3904.108', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/78.0.3904.108 Safari/537.36', 'Windows 10', '2019-11-27 16:20:21'),
(4, 1, '{\"role\":\"1\",\"roleText\":\"System Administrator\",\"name\":\"System Administrator\"}', '::1', 'Chrome 78.0.3904.108', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/78.0.3904.108 Safari/537.36', 'Windows 10', '2019-11-27 16:22:26'),
(5, 1, '{\"role\":\"1\",\"roleText\":\"System Administrator\",\"name\":\"System Administrator\"}', '::1', 'Chrome 78.0.3904.108', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/78.0.3904.108 Safari/537.36', 'Windows 10', '2019-11-27 16:26:17'),
(6, 1, '{\"role\":\"1\",\"roleText\":\"System Administrator\",\"name\":\"System Administrator\"}', '::1', 'Chrome 78.0.3904.108', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/78.0.3904.108 Safari/537.36', 'Windows 10', '2019-11-27 17:11:41'),
(7, 1, '{\"role\":\"1\",\"roleText\":\"System Administrator\",\"name\":\"System Administrator\"}', '::1', 'Chrome 78.0.3904.108', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/78.0.3904.108 Safari/537.36', 'Windows 10', '2019-11-27 18:50:14'),
(8, 1, '{\"role\":\"1\",\"roleText\":\"System Administrator\",\"name\":\"System Administrator\"}', '::1', 'Chrome 78.0.3904.108', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/78.0.3904.108 Safari/537.36', 'Windows 10', '2019-11-27 19:29:30'),
(9, 3, '{\"role\":\"3\",\"roleText\":\"Employee\",\"name\":\"Employee\"}', '::1', 'Chrome 78.0.3904.108', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/78.0.3904.108 Safari/537.36', 'Windows 10', '2019-11-27 19:30:11'),
(10, 1, '{\"role\":\"1\",\"roleText\":\"System Administrator\",\"name\":\"System Administrator\"}', '::1', 'Chrome 78.0.3904.108', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/78.0.3904.108 Safari/537.36', 'Windows 10', '2019-11-27 19:30:38'),
(11, 1, '{\"role\":\"1\",\"roleText\":\"System Administrator\",\"name\":\"System Administrator\"}', '::1', 'Chrome 78.0.3904.108', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/78.0.3904.108 Safari/537.36', 'Windows 10', '2019-11-27 19:53:40'),
(12, 1, '{\"role\":\"1\",\"roleText\":\"System Administrator\",\"name\":\"System Administrator\"}', '::1', 'Chrome 78.0.3904.108', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/78.0.3904.108 Safari/537.36', 'Windows 10', '2019-11-27 19:59:42'),
(13, 9, '{\"role\":\"2\",\"roleText\":\"Manager\",\"name\":\"Matheus\"}', '::1', 'Chrome 78.0.3904.108', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/78.0.3904.108 Safari/537.36', 'Windows 10', '2019-11-27 22:21:26'),
(14, 1, '{\"role\":\"1\",\"roleText\":\"System Administrator\",\"name\":\"System Administrator\"}', '::1', 'Chrome 78.0.3904.108', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/78.0.3904.108 Safari/537.36', 'Windows 10', '2019-11-27 22:25:15'),
(15, 1, '{\"role\":\"1\",\"roleText\":\"System Administrator\",\"name\":\"System Administrator\"}', '::1', 'Chrome 78.0.3904.108', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/78.0.3904.108 Safari/537.36', 'Windows 10', '2019-11-28 10:47:27'),
(16, 1, '{\"role\":\"1\",\"roleText\":\"System Administrator\",\"name\":\"System Administrator\"}', '::1', 'Chrome 78.0.3904.108', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/78.0.3904.108 Safari/537.36', 'Windows 10', '2019-11-28 12:28:22'),
(17, 9, '{\"role\":\"2\",\"roleText\":\"Manager\",\"name\":\"Matheus\"}', '::1', 'Chrome 78.0.3904.108', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/78.0.3904.108 Safari/537.36', 'Windows 10', '2019-11-28 14:01:05'),
(18, 1, '{\"role\":\"1\",\"roleText\":\"System Administrator\",\"name\":\"System Administrator\"}', '::1', 'Chrome 78.0.3904.108', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/78.0.3904.108 Safari/537.36', 'Windows 10', '2019-11-29 22:12:27'),
(19, 1, '{\"role\":\"1\",\"roleText\":\"System Administrator\",\"name\":\"System Administrator\"}', '::1', 'Chrome 78.0.3904.108', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/78.0.3904.108 Safari/537.36', 'Windows 10', '2019-11-30 12:12:41'),
(20, 1, '{\"role\":\"1\",\"roleText\":\"System Administrator\",\"name\":\"System Administrator\"}', '::1', 'Chrome 78.0.3904.108', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/78.0.3904.108 Safari/537.36', 'Windows 10', '2019-11-30 12:36:13'),
(21, 1, '{\"role\":\"1\",\"roleText\":\"System Administrator\",\"name\":\"System Administrator\"}', '::1', 'Chrome 78.0.3904.108', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/78.0.3904.108 Safari/537.36', 'Windows 10', '2019-11-30 19:43:40'),
(22, 1, '{\"role\":\"1\",\"roleText\":\"System Administrator\",\"name\":\"System Administrator\"}', '::1', 'Chrome 78.0.3904.108', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/78.0.3904.108 Safari/537.36', 'Windows 10', '2019-12-01 11:02:16'),
(23, 1, '{\"role\":\"1\",\"roleText\":\"System Administrator\",\"name\":\"System Administrator\"}', '::1', 'Chrome 78.0.3904.108', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/78.0.3904.108 Safari/537.36', 'Windows 10', '2019-12-01 21:24:09'),
(24, 1, '{\"role\":\"1\",\"roleText\":\"System Administrator\",\"name\":\"System Administrator\"}', '::1', 'Chrome 79.0.3945.79', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/79.0.3945.79 Safari/537.36', 'Windows 10', '2019-12-11 18:30:21'),
(25, 1, '{\"role\":\"1\",\"roleText\":\"System Administrator\",\"name\":\"System Administrator\"}', '::1', 'Chrome 79.0.3945.88', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/79.0.3945.88 Safari/537.36', 'Windows 10', '2020-01-02 21:45:49'),
(26, 1, '{\"role\":\"1\",\"roleText\":\"System Administrator\",\"name\":\"System Administrator\"}', '::1', 'Chrome 79.0.3945.88', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/79.0.3945.88 Safari/537.36', 'Windows 10', '2020-01-02 22:16:44'),
(27, 1, '{\"role\":\"1\",\"roleText\":\"System Administrator\",\"name\":\"System Administrator\"}', '::1', 'Chrome 79.0.3945.88', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/79.0.3945.88 Safari/537.36', 'Windows 10', '2020-01-03 10:00:21'),
(28, 1, '{\"role\":\"1\",\"roleText\":\"System Administrator\",\"name\":\"System Administrator\"}', '::1', 'Chrome 79.0.3945.88', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/79.0.3945.88 Safari/537.36', 'Windows 10', '2020-01-03 13:05:33'),
(29, 1, '{\"role\":\"1\",\"roleText\":\"System Administrator\",\"name\":\"System Administrator\"}', '::1', 'Chrome 79.0.3945.88', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/79.0.3945.88 Safari/537.36', 'Windows 10', '2020-01-03 18:13:09'),
(30, 1, '{\"role\":\"1\",\"roleText\":\"System Administrator\",\"name\":\"System Administrator\"}', '::1', 'Chrome 79.0.3945.88', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/79.0.3945.88 Safari/537.36', 'Windows 10', '2020-01-04 14:56:30'),
(31, 1, '{\"role\":\"1\",\"roleText\":\"System Administrator\",\"name\":\"System Administrator\"}', '::1', 'Chrome 79.0.3945.88', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/79.0.3945.88 Safari/537.36', 'Windows 10', '2020-01-04 15:12:52'),
(32, 1, '{\"role\":\"1\",\"roleText\":\"System Administrator\",\"name\":\"System Administrator\"}', '::1', 'Chrome 79.0.3945.88', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/79.0.3945.88 Safari/537.36', 'Windows 10', '2020-01-04 18:02:42'),
(33, 1, '{\"role\":\"1\",\"roleText\":\"System Administrator\",\"name\":\"System Administrator\"}', '::1', 'Chrome 79.0.3945.88', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/79.0.3945.88 Safari/537.36', 'Windows 10', '2020-01-05 15:29:42'),
(34, 1, '{\"role\":\"1\",\"roleText\":\"System Administrator\",\"name\":\"System Administrator\"}', '::1', 'Chrome 79.0.3945.88', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/79.0.3945.88 Safari/537.36', 'Windows 10', '2020-01-06 13:43:04'),
(35, 1, '{\"role\":\"1\",\"roleText\":\"System Administrator\",\"name\":\"System Administrator\"}', '::1', 'Chrome 79.0.3945.88', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/79.0.3945.88 Safari/537.36', 'Windows 10', '2020-01-06 20:11:10'),
(36, 1, '{\"role\":\"1\",\"roleText\":\"System Administrator\",\"name\":\"System Administrator\"}', '::1', 'Chrome 79.0.3945.88', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/79.0.3945.88 Safari/537.36', 'Windows 10', '2020-01-06 21:47:52'),
(37, 1, '{\"role\":\"1\",\"roleText\":\"System Administrator\",\"name\":\"System Administrator\"}', '::1', 'Chrome 79.0.3945.88', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/79.0.3945.88 Safari/537.36', 'Windows 10', '2020-01-07 08:25:45'),
(38, 1, '{\"role\":\"1\",\"roleText\":\"System Administrator\",\"name\":\"System Administrator\"}', '::1', 'Chrome 79.0.3945.88', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/79.0.3945.88 Safari/537.36', 'Windows 10', '2020-01-07 13:24:35'),
(39, 1, '{\"role\":\"1\",\"roleText\":\"System Administrator\",\"name\":\"System Administrator\"}', '::1', 'Chrome 79.0.3945.117', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/79.0.3945.117 Safari/537.36', 'Windows 10', '2020-01-07 19:33:50'),
(40, 9, '{\"role\":\"2\",\"roleText\":\"Manager\",\"name\":\"Matheus\"}', '::1', 'Chrome 79.0.3945.117', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/79.0.3945.117 Safari/537.36', 'Windows 10', '2020-01-07 20:52:36'),
(41, 1, '{\"role\":\"1\",\"roleText\":\"System Administrator\",\"name\":\"System Administrator\"}', '::1', 'Chrome 79.0.3945.117', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/79.0.3945.117 Safari/537.36', 'Windows 10', '2020-01-07 20:58:23'),
(42, 9, '{\"role\":\"3\",\"roleText\":\"Employee\",\"name\":\"Matheus\"}', '::1', 'Chrome 79.0.3945.117', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/79.0.3945.117 Safari/537.36', 'Windows 10', '2020-01-07 20:58:50'),
(43, 1, '{\"role\":\"1\",\"roleText\":\"System Administrator\",\"name\":\"System Administrator\"}', '::1', 'Chrome 79.0.3945.117', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/79.0.3945.117 Safari/537.36', 'Windows 10', '2020-01-07 21:00:39'),
(44, 9, '{\"role\":\"3\",\"roleText\":\"Employee\",\"name\":\"Matheus\"}', '::1', 'Chrome 79.0.3945.117', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/79.0.3945.117 Safari/537.36', 'Windows 10', '2020-01-07 21:06:20'),
(45, 1, '{\"role\":\"1\",\"roleText\":\"System Administrator\",\"name\":\"System Administrator\"}', '::1', 'Chrome 79.0.3945.117', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/79.0.3945.117 Safari/537.36', 'Windows 10', '2020-01-07 21:07:16'),
(46, 9, '{\"role\":\"3\",\"roleText\":\"Employee\",\"name\":\"Matheus\"}', '::1', 'Chrome 79.0.3945.117', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/79.0.3945.117 Safari/537.36', 'Windows 10', '2020-01-07 21:08:37'),
(47, 1, '{\"role\":\"1\",\"roleText\":\"System Administrator\",\"name\":\"System Administrator\"}', '::1', 'Chrome 79.0.3945.117', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/79.0.3945.117 Safari/537.36', 'Windows 10', '2020-01-07 21:11:41'),
(48, 9, '{\"role\":\"2\",\"roleText\":\"Manager\",\"name\":\"Matheus\"}', '::1', 'Chrome 79.0.3945.117', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/79.0.3945.117 Safari/537.36', 'Windows 10', '2020-01-07 21:13:23'),
(49, 1, '{\"role\":\"1\",\"roleText\":\"System Administrator\",\"name\":\"System Administrator\"}', '::1', 'Chrome 79.0.3945.117', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/79.0.3945.117 Safari/537.36', 'Windows 10', '2020-01-07 21:14:01'),
(50, 9, '{\"role\":\"2\",\"roleText\":\"Manager\",\"name\":\"Matheus\"}', '::1', 'Chrome 79.0.3945.117', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/79.0.3945.117 Safari/537.36', 'Windows 10', '2020-01-07 21:14:20'),
(51, 1, '{\"role\":\"1\",\"roleText\":\"System Administrator\",\"name\":\"System Administrator\"}', '::1', 'Chrome 79.0.3945.117', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/79.0.3945.117 Safari/537.36', 'Windows 10', '2020-01-07 23:10:12'),
(52, 11, '{\"role\":\"3\",\"roleText\":\"Employee\",\"name\":\"Test\"}', '::1', 'Chrome 79.0.3945.117', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/79.0.3945.117 Safari/537.36', 'Windows 10', '2020-01-07 23:10:39'),
(53, 9, '{\"role\":\"2\",\"roleText\":\"Manager\",\"name\":\"Matheus\"}', '::1', 'Chrome 79.0.3945.117', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/79.0.3945.117 Safari/537.36', 'Windows 10', '2020-01-08 19:58:32'),
(54, 1, '{\"role\":\"1\",\"roleText\":\"System Administrator\",\"name\":\"System Administrator\"}', '::1', 'Chrome 79.0.3945.117', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/79.0.3945.117 Safari/537.36', 'Windows 10', '2020-01-09 08:23:50'),
(55, 9, '{\"role\":\"2\",\"roleText\":\"Manager\",\"name\":\"Matheus\"}', '::1', 'Chrome 79.0.3945.117', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/79.0.3945.117 Safari/537.36', 'Windows 10', '2020-01-09 08:24:12'),
(56, 9, '{\"role\":\"2\",\"roleText\":\"Manager\",\"name\":\"Matheus\"}', '::1', 'Chrome 79.0.3945.117', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/79.0.3945.117 Safari/537.36', 'Windows 10', '2020-01-09 20:37:53'),
(57, 9, '{\"role\":\"2\",\"roleText\":\"Manager\",\"name\":\"Matheus\"}', '::1', 'Chrome 79.0.3945.117', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/79.0.3945.117 Safari/537.36', 'Windows 10', '2020-01-09 22:37:13'),
(58, 9, '{\"role\":\"2\",\"roleText\":\"Manager\",\"name\":\"Matheus\"}', '::1', 'Chrome 79.0.3945.117', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/79.0.3945.117 Safari/537.36', 'Windows 10', '2020-01-10 14:52:33'),
(59, 9, '{\"role\":\"2\",\"roleText\":\"Manager\",\"name\":\"Matheus\"}', '::1', 'Chrome 79.0.3945.117', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/79.0.3945.117 Safari/537.36', 'Windows 10', '2020-01-13 09:12:29'),
(60, 9, '{\"role\":\"2\",\"roleText\":\"Manager\",\"name\":\"Matheus\"}', '::1', 'Chrome 79.0.3945.117', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/79.0.3945.117 Safari/537.36', 'Windows 10', '2020-01-13 13:39:27'),
(61, 9, '{\"role\":\"2\",\"roleText\":\"Manager\",\"name\":\"Matheus\"}', '::1', 'Chrome 79.0.3945.117', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/79.0.3945.117 Safari/537.36', 'Windows 10', '2020-01-13 19:30:22'),
(62, 9, '{\"role\":\"2\",\"roleText\":\"Manager\",\"name\":\"Matheus\"}', '::1', 'Chrome 79.0.3945.117', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/79.0.3945.117 Safari/537.36', 'Windows 10', '2020-01-14 10:05:48'),
(63, 9, '{\"role\":\"2\",\"roleText\":\"Manager\",\"name\":\"Matheus\"}', '::1', 'Chrome 79.0.3945.117', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/79.0.3945.117 Safari/537.36', 'Windows 10', '2020-01-14 15:27:35'),
(64, 9, '{\"role\":\"2\",\"roleText\":\"Manager\",\"name\":\"Matheus\"}', '::1', 'Chrome 79.0.3945.117', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/79.0.3945.117 Safari/537.36', 'Windows 10', '2020-01-15 13:52:07'),
(65, 9, '{\"role\":\"2\",\"roleText\":\"Manager\",\"name\":\"Matheus\"}', '::1', 'Chrome 79.0.3945.117', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/79.0.3945.117 Safari/537.36', 'Windows 10', '2020-01-15 15:56:43'),
(66, 9, '{\"role\":\"2\",\"roleText\":\"Manager\",\"name\":\"Matheus\"}', '::1', 'Chrome 79.0.3945.117', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/79.0.3945.117 Safari/537.36', 'Windows 10', '2020-01-16 10:48:13'),
(67, 9, '{\"role\":\"2\",\"roleText\":\"Manager\",\"name\":\"Matheus\"}', '::1', 'Chrome 79.0.3945.117', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/79.0.3945.117 Safari/537.36', 'Windows 10', '2020-01-16 11:44:57'),
(68, 9, '{\"role\":\"2\",\"roleText\":\"Manager\",\"name\":\"Matheus\"}', '::1', 'Chrome 79.0.3945.117', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/79.0.3945.117 Safari/537.36', 'Windows 10', '2020-01-16 15:27:11'),
(69, 9, '{\"role\":\"2\",\"roleText\":\"Manager\",\"name\":\"Matheus\"}', '::1', 'Chrome 79.0.3945.117', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/79.0.3945.117 Safari/537.36', 'Windows 10', '2020-01-16 19:24:35'),
(70, 9, '{\"role\":\"2\",\"roleText\":\"Manager\",\"name\":\"Matheus\"}', '::1', 'Chrome 79.0.3945.117', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/79.0.3945.117 Safari/537.36', 'Windows 10', '2020-01-17 08:45:08'),
(71, 9, '{\"role\":\"2\",\"roleText\":\"Manager\",\"name\":\"Matheus\"}', '::1', 'Chrome 79.0.3945.117', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/79.0.3945.117 Safari/537.36', 'Windows 10', '2020-01-17 08:47:16'),
(72, 9, '{\"role\":\"2\",\"roleText\":\"Manager\",\"name\":\"Matheus\"}', '::1', 'Chrome 79.0.3945.117', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/79.0.3945.117 Safari/537.36', 'Windows 10', '2020-01-17 13:55:59'),
(73, 9, '{\"role\":\"2\",\"roleText\":\"Manager\",\"name\":\"Matheus\"}', '::1', 'Chrome 79.0.3945.117', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/79.0.3945.117 Safari/537.36', 'Windows 10', '2020-01-17 14:40:01'),
(74, 9, '{\"role\":\"2\",\"roleText\":\"Manager\",\"name\":\"Matheus\"}', '::1', 'Chrome 79.0.3945.117', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/79.0.3945.117 Safari/537.36', 'Windows 10', '2020-01-20 09:24:39'),
(75, 9, '{\"role\":\"2\",\"roleText\":\"Manager\",\"name\":\"Matheus\"}', '::1', 'Chrome 79.0.3945.117', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/79.0.3945.117 Safari/537.36', 'Windows 10', '2020-01-20 13:53:49'),
(76, 9, '{\"role\":\"2\",\"roleText\":\"Manager\",\"name\":\"Matheus\"}', '::1', 'Chrome 79.0.3945.117', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/79.0.3945.117 Safari/537.36', 'Windows 10', '2020-01-21 08:54:48'),
(77, 1, '{\"role\":\"1\",\"roleText\":\"System Administrator\",\"name\":\"System Administrator\"}', '::1', 'Chrome 79.0.3945.117', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/79.0.3945.117 Safari/537.36', 'Windows 10', '2020-01-21 08:54:57'),
(78, 12, '{\"role\":\"2\",\"roleText\":\"Manager\",\"name\":\"Jorge Castello\"}', '172.18.16.75', 'Chrome 79.0.3945.117', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/79.0.3945.117 Safari/537.36', 'Linux', '2020-01-21 08:56:14'),
(79, 9, '{\"role\":\"2\",\"roleText\":\"Manager\",\"name\":\"Matheus\"}', '::1', 'Chrome 79.0.3945.117', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/79.0.3945.117 Safari/537.36', 'Windows 10', '2020-01-21 09:17:53'),
(80, 9, '{\"role\":\"2\",\"roleText\":\"Manager\",\"name\":\"Matheus\"}', '::1', 'Chrome 79.0.3945.130', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/79.0.3945.130 Safari/537.36', 'Windows 10', '2020-01-22 08:36:44'),
(81, 9, '{\"role\":\"2\",\"roleText\":\"Manager\",\"name\":\"Matheus\"}', '::1', 'Chrome 79.0.3945.130', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/79.0.3945.130 Safari/537.36', 'Windows 10', '2020-01-22 12:19:12'),
(82, 9, '{\"role\":\"2\",\"roleText\":\"Manager\",\"name\":\"Matheus\"}', '::1', 'Chrome 79.0.3945.130', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/79.0.3945.130 Safari/537.36', 'Windows 10', '2020-01-22 15:35:16'),
(83, 9, '{\"role\":\"2\",\"roleText\":\"Manager\",\"name\":\"Matheus\"}', '::1', 'Chrome 79.0.3945.130', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/79.0.3945.130 Safari/537.36', 'Windows 10', '2020-01-23 17:06:15'),
(84, 9, '{\"role\":\"2\",\"roleText\":\"Manager\",\"name\":\"Matheus\"}', '::1', 'Chrome 79.0.3945.130', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/79.0.3945.130 Safari/537.36', 'Windows 10', '2020-01-23 19:52:20'),
(85, 9, '{\"role\":\"2\",\"roleText\":\"Manager\",\"name\":\"Matheus\"}', '::1', 'Chrome 79.0.3945.130', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/79.0.3945.130 Safari/537.36', 'Windows 10', '2020-01-24 16:29:54'),
(86, 9, '{\"role\":\"2\",\"roleText\":\"Manager\",\"name\":\"Matheus\"}', '::1', 'Chrome 79.0.3945.130', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/79.0.3945.130 Safari/537.36', 'Windows 10', '2020-01-24 19:37:35'),
(87, 9, '{\"role\":\"2\",\"roleText\":\"Manager\",\"name\":\"Matheus\"}', '::1', 'Chrome 79.0.3945.130', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/79.0.3945.130 Safari/537.36', 'Windows 10', '2020-01-25 09:33:18'),
(88, 9, '{\"role\":\"2\",\"roleText\":\"Manager\",\"name\":\"Matheus\"}', '::1', 'Chrome 79.0.3945.130', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/79.0.3945.130 Safari/537.36', 'Windows 10', '2020-01-27 19:36:40'),
(89, 1, '{\"role\":\"1\",\"roleText\":\"System Administrator\",\"name\":\"System Administrator\"}', '::1', 'Chrome 79.0.3945.130', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/79.0.3945.130 Safari/537.36', 'Windows 10', '2020-01-27 19:52:40'),
(90, 9, '{\"role\":\"2\",\"roleText\":\"Manager\",\"name\":\"Matheus\"}', '::1', 'Chrome 79.0.3945.130', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/79.0.3945.130 Safari/537.36', 'Windows 10', '2020-01-27 19:58:21'),
(91, 9, '{\"role\":\"2\",\"roleText\":\"Manager\",\"name\":\"Matheus\"}', '::1', 'Chrome 79.0.3945.130', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/79.0.3945.130 Safari/537.36', 'Windows 10', '2020-01-28 09:01:16'),
(92, 9, '{\"role\":\"2\",\"roleText\":\"Manager\",\"name\":\"Matheus\"}', '::1', 'Chrome 79.0.3945.130', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/79.0.3945.130 Safari/537.36', 'Windows 10', '2020-01-28 09:06:16'),
(93, 9, '{\"role\":\"2\",\"roleText\":\"Manager\",\"name\":\"Matheus\"}', '::1', 'Chrome 79.0.3945.130', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/79.0.3945.130 Safari/537.36', 'Windows 10', '2020-01-28 20:00:30'),
(94, 9, '{\"role\":\"2\",\"roleText\":\"Manager\",\"name\":\"Matheus\"}', '::1', 'Chrome 79.0.3945.130', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/79.0.3945.130 Safari/537.36', 'Windows 10', '2020-01-28 22:17:38'),
(95, 9, '{\"role\":\"2\",\"roleText\":\"Manager\",\"name\":\"Matheus\"}', '::1', 'Chrome 79.0.3945.130', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/79.0.3945.130 Safari/537.36', 'Windows 10', '2020-01-29 08:31:19'),
(96, 9, '{\"role\":\"2\",\"roleText\":\"Manager\",\"name\":\"Matheus\"}', '::1', 'Chrome 79.0.3945.130', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/79.0.3945.130 Safari/537.36', 'Windows 10', '2020-01-29 12:06:19'),
(97, 9, '{\"role\":\"2\",\"roleText\":\"Manager\",\"name\":\"Matheus\"}', '::1', 'Chrome 79.0.3945.130', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/79.0.3945.130 Safari/537.36', 'Windows 10', '2020-01-29 16:46:18'),
(98, 9, '{\"role\":\"2\",\"roleText\":\"Manager\",\"name\":\"Matheus\"}', '::1', 'Chrome 79.0.3945.130', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/79.0.3945.130 Safari/537.36', 'Windows 10', '2020-01-29 19:27:31'),
(99, 9, '{\"role\":\"2\",\"roleText\":\"Manager\",\"name\":\"Matheus\"}', '::1', 'Chrome 79.0.3945.130', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/79.0.3945.130 Safari/537.36', 'Windows 10', '2020-01-29 23:10:10'),
(100, 9, '{\"role\":\"2\",\"roleText\":\"Manager\",\"name\":\"Matheus\"}', '::1', 'Chrome 79.0.3945.130', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/79.0.3945.130 Safari/537.36', 'Windows 10', '2020-01-30 08:47:00'),
(101, 9, '{\"role\":\"2\",\"roleText\":\"Manager\",\"name\":\"Matheus\"}', '::1', 'Chrome 79.0.3945.130', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/79.0.3945.130 Safari/537.36', 'Windows 10', '2020-01-30 20:40:27'),
(102, 9, '{\"role\":\"2\",\"roleText\":\"Manager\",\"name\":\"Matheus\"}', '::1', 'Chrome 79.0.3945.130', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/79.0.3945.130 Safari/537.36', 'Windows 10', '2020-02-01 12:16:56'),
(103, 9, '{\"role\":\"2\",\"roleText\":\"Manager\",\"name\":\"Matheus\"}', '::1', 'Chrome 79.0.3945.130', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/79.0.3945.130 Safari/537.36', 'Windows 10', '2020-02-02 13:08:23'),
(104, 9, '{\"role\":\"2\",\"roleText\":\"Manager\",\"name\":\"Matheus\"}', '::1', 'Chrome 79.0.3945.130', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/79.0.3945.130 Safari/537.36', 'Windows 10', '2020-02-03 13:37:01'),
(105, 9, '{\"role\":\"2\",\"roleText\":\"Manager\",\"name\":\"Matheus\"}', '::1', 'Chrome 79.0.3945.130', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/79.0.3945.130 Safari/537.36', 'Windows 10', '2020-02-04 12:02:29'),
(106, 1, '{\"role\":\"1\",\"roleText\":\"System Administrator\",\"name\":\"System Administrator\"}', '::1', 'Chrome 79.0.3945.130', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/79.0.3945.130 Safari/537.36', 'Windows 10', '2020-02-04 18:05:50'),
(107, 9, '{\"role\":\"2\",\"roleText\":\"Manager\",\"name\":\"Matheus\"}', '::1', 'Chrome 79.0.3945.130', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/79.0.3945.130 Safari/537.36', 'Windows 10', '2020-02-04 18:07:12'),
(108, 9, '{\"role\":\"2\",\"roleText\":\"Manager\",\"name\":\"Matheus\"}', '::1', 'Chrome 80.0.3987.87', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.87 Safari/537.36', 'Windows 10', '2020-02-05 11:34:20'),
(109, 9, '{\"role\":\"2\",\"roleText\":\"Manager\",\"name\":\"Matheus\"}', '::1', 'Chrome 80.0.3987.87', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.87 Safari/537.36', 'Windows 10', '2020-02-05 16:33:01'),
(110, 9, '{\"role\":\"2\",\"roleText\":\"Manager\",\"name\":\"Matheus\"}', '::1', 'Chrome 80.0.3987.87', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.87 Safari/537.36', 'Windows 10', '2020-02-10 11:25:04'),
(111, 9, '{\"role\":\"2\",\"roleText\":\"Manager\",\"name\":\"Matheus\"}', '::1', 'Chrome 80.0.3987.87', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.87 Safari/537.36', 'Windows 10', '2020-02-10 14:24:54'),
(112, 9, '{\"role\":\"2\",\"roleText\":\"Manager\",\"name\":\"Matheus\"}', '::1', 'Chrome 80.0.3987.87', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.87 Safari/537.36', 'Windows 10', '2020-02-10 16:40:34'),
(113, 9, '{\"role\":\"2\",\"roleText\":\"Manager\",\"name\":\"Matheus\"}', '::1', 'Chrome 80.0.3987.106', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.106 Safari/537.36', 'Windows 10', '2020-02-18 16:33:20'),
(114, 9, '{\"role\":\"2\",\"roleText\":\"Manager\",\"name\":\"Matheus\"}', '::1', 'Chrome 80.0.3987.116', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.116 Safari/537.36', 'Windows 10', '2020-02-19 16:01:55'),
(115, 9, '{\"role\":\"2\",\"roleText\":\"Manager\",\"name\":\"Matheus\"}', '::1', 'Chrome 80.0.3987.116', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.116 Safari/537.36', 'Windows 10', '2020-02-20 09:35:15'),
(116, 9, '{\"role\":\"2\",\"roleText\":\"Manager\",\"name\":\"Matheus\"}', '::1', 'Chrome 80.0.3987.116', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.116 Safari/537.36', 'Windows 10', '2020-02-20 15:02:54'),
(117, 9, '{\"role\":\"2\",\"roleText\":\"Manager\",\"name\":\"Matheus\"}', '::1', 'Chrome 80.0.3987.116', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.116 Safari/537.36', 'Windows 10', '2020-02-21 09:17:44'),
(118, 9, '{\"role\":\"2\",\"roleText\":\"Manager\",\"name\":\"Matheus\"}', '::1', 'Chrome 80.0.3987.122', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.122 Safari/537.36', 'Windows 10', '2020-02-25 17:31:06'),
(119, 9, '{\"role\":\"2\",\"roleText\":\"Manager\",\"name\":\"Matheus\"}', '::1', 'Chrome 80.0.3987.122', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.122 Safari/537.36', 'Windows 10', '2020-02-26 15:29:00'),
(120, 11, '{\"role\":\"3\",\"roleText\":\"Employee\",\"name\":\"Test\"}', '172.18.16.177', 'Chrome 80.0.3987.122', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.122 Safari/537.36', 'Windows 10', '2020-03-05 15:30:09'),
(121, 1, '{\"role\":\"1\",\"roleText\":\"System Administrator\",\"name\":\"System Administrator\"}', '172.18.16.177', 'Chrome 80.0.3987.122', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.122 Safari/537.36', 'Windows 10', '2020-03-05 15:30:23'),
(122, 11, '{\"role\":\"2\",\"roleText\":\"Manager\",\"name\":\"Test\"}', '172.18.16.177', 'Chrome 80.0.3987.122', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.122 Safari/537.36', 'Windows 10', '2020-03-05 15:30:50'),
(123, 11, '{\"role\":\"2\",\"roleText\":\"Manager\",\"name\":\"Test\"}', '172.18.16.177', 'Chrome 80.0.3987.122', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.122 Safari/537.36', 'Windows 10', '2020-03-05 15:31:35'),
(124, 11, '{\"role\":\"2\",\"roleText\":\"Manager\",\"name\":\"Test\"}', '172.18.16.222', 'Chrome 80.0.3987.122', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.122 Safari/537.36', 'Windows 10', '2020-03-05 15:37:29'),
(125, 9, '{\"role\":\"2\",\"roleText\":\"Manager\",\"name\":\"Matheus\"}', '::1', 'Chrome 80.0.3987.122', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.122 Safari/537.36', 'Windows 10', '2020-03-05 15:41:25'),
(126, 9, '{\"role\":\"2\",\"roleText\":\"Manager\",\"name\":\"Matheus\"}', '::1', 'Chrome 80.0.3987.132', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.132 Safari/537.36', 'Windows 10', '2020-03-10 19:53:01'),
(127, 9, '{\"role\":\"2\",\"roleText\":\"Manager\",\"name\":\"Matheus\"}', '::1', 'Chrome 80.0.3987.132', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.132 Safari/537.36', 'Windows 10', '2020-03-11 19:43:04'),
(128, 9, '{\"role\":\"2\",\"roleText\":\"Manager\",\"name\":\"Matheus\"}', '::1', 'Chrome 80.0.3987.132', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.132 Safari/537.36', 'Windows 10', '2020-03-12 18:19:25'),
(129, 9, '{\"role\":\"2\",\"roleText\":\"Manager\",\"name\":\"Matheus\"}', '::1', 'Chrome 80.0.3987.132', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.132 Safari/537.36', 'Windows 10', '2020-03-12 18:39:03'),
(130, 9, '{\"role\":\"2\",\"roleText\":\"Manager\",\"name\":\"Matheus\"}', '::1', 'Chrome 80.0.3987.132', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.132 Safari/537.36', 'Windows 10', '2020-03-15 18:18:04'),
(131, 9, '{\"role\":\"2\",\"roleText\":\"Manager\",\"name\":\"Matheus\"}', '::1', 'Chrome 80.0.3987.132', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.132 Safari/537.36', 'Windows 10', '2020-03-17 08:49:47'),
(132, 9, '{\"role\":\"2\",\"roleText\":\"Manager\",\"name\":\"Matheus\"}', '::1', 'Chrome 80.0.3987.132', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.132 Safari/537.36', 'Windows 10', '2020-03-17 19:51:43'),
(133, 1, '{\"role\":\"1\",\"roleText\":\"System Administrator\",\"name\":\"System Administrator\"}', '::1', 'Chrome 80.0.3987.132', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.132 Safari/537.36', 'Windows 10', '2020-03-17 22:17:45'),
(134, 9, '{\"role\":\"2\",\"roleText\":\"Manager\",\"name\":\"Matheus\"}', '::1', 'Chrome 80.0.3987.132', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.132 Safari/537.36', 'Windows 10', '2020-03-17 22:17:58'),
(135, 9, '{\"role\":\"2\",\"roleText\":\"Manager\",\"name\":\"Matheus\"}', '::1', 'Chrome 80.0.3987.132', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.132 Safari/537.36', 'Windows 10', '2020-03-18 09:32:17'),
(136, 9, '{\"role\":\"2\",\"roleText\":\"Manager\",\"name\":\"Matheus\"}', '::1', 'Chrome 80.0.3987.132', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.132 Safari/537.36', 'Windows 10', '2020-03-18 21:08:31'),
(137, 9, '{\"role\":\"2\",\"roleText\":\"Manager\",\"name\":\"Matheus\"}', '::1', 'Chrome 80.0.3987.132', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.132 Safari/537.36', 'Windows 10', '2020-03-19 16:27:30'),
(138, 9, '{\"role\":\"2\",\"roleText\":\"Manager\",\"name\":\"Matheus\"}', '::1', 'Chrome 80.0.3987.149', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.149 Safari/537.36', 'Windows 10', '2020-03-23 19:22:19'),
(139, 1, '{\"role\":\"1\",\"roleText\":\"System Administrator\",\"name\":\"System Administrator\"}', '::1', 'Chrome 80.0.3987.149', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.149 Safari/537.36', 'Windows 10', '2020-03-23 21:38:35'),
(140, 9, '{\"role\":\"2\",\"roleText\":\"Manager\",\"name\":\"Matheus\"}', '::1', 'Chrome 80.0.3987.149', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.149 Safari/537.36', 'Windows 10', '2020-03-23 21:41:35'),
(141, 9, '{\"role\":\"2\",\"roleText\":\"Manager\",\"name\":\"Matheus\"}', '::1', 'Chrome 80.0.3987.149', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.149 Safari/537.36', 'Windows 10', '2020-03-23 21:44:41'),
(142, 9, '{\"role\":\"2\",\"roleText\":\"Manager\",\"name\":\"Matheus\"}', '::1', 'Chrome 80.0.3987.149', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.149 Safari/537.36', 'Windows 10', '2020-03-24 20:08:23'),
(143, 9, '{\"role\":\"2\",\"roleText\":\"Manager\",\"name\":\"Matheus\"}', '::1', 'Chrome 80.0.3987.149', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.149 Safari/537.36', 'Windows 10', '2020-03-25 19:16:36'),
(144, 9, '{\"role\":\"2\",\"roleText\":\"Manager\",\"name\":\"Matheus\"}', '::1', 'Chrome 80.0.3987.149', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.149 Safari/537.36', 'Windows 10', '2020-03-26 19:17:01'),
(145, 11, '{\"role\":\"2\",\"roleText\":\"Manager\",\"name\":\"Test\"}', '::1', 'Chrome 80.0.3987.149', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.149 Safari/537.36', 'Windows 10', '2020-03-26 20:33:05'),
(146, 1, '{\"role\":\"1\",\"roleText\":\"System Administrator\",\"name\":\"System Administrator\"}', '::1', 'Chrome 80.0.3987.149', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.149 Safari/537.36', 'Windows 10', '2020-03-26 20:33:17'),
(147, 11, '{\"role\":\"3\",\"roleText\":\"Employee\",\"name\":\"Test\"}', '::1', 'Chrome 80.0.3987.149', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.149 Safari/537.36', 'Windows 10', '2020-03-26 20:33:33'),
(148, 9, '{\"role\":\"2\",\"roleText\":\"Manager\",\"name\":\"Matheus\"}', '::1', 'Chrome 80.0.3987.149', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.149 Safari/537.36', 'Windows 10', '2020-03-26 20:35:08'),
(149, 9, '{\"role\":\"2\",\"roleText\":\"Manager\",\"name\":\"Matheus\"}', '::1', 'Chrome 80.0.3987.149', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.149 Safari/537.36', 'Windows 10', '2020-03-27 18:24:53'),
(150, 9, '{\"role\":\"2\",\"roleText\":\"Manager\",\"name\":\"Matheus\"}', '::1', 'Chrome 80.0.3987.149', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.149 Safari/537.36', 'Windows 10', '2020-03-29 16:36:35'),
(151, 9, '{\"role\":\"2\",\"roleText\":\"Manager\",\"name\":\"Matheus\"}', '::1', 'Chrome 80.0.3987.149', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.149 Safari/537.36', 'Windows 10', '2020-03-31 20:18:49'),
(152, 9, '{\"role\":\"2\",\"roleText\":\"Manager\",\"name\":\"Matheus\"}', '::1', 'Chrome 80.0.3987.149', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.149 Safari/537.36', 'Windows 10', '2020-04-01 19:43:06'),
(153, 9, '{\"role\":\"2\",\"roleText\":\"Manager\",\"name\":\"Matheus\"}', '::1', 'Chrome 80.0.3987.149', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.149 Safari/537.36', 'Windows 10', '2020-04-02 20:06:09'),
(154, 1, '{\"role\":\"1\",\"roleText\":\"System Administrator\",\"name\":\"System Administrator\"}', '::1', 'Chrome 80.0.3987.149', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.149 Safari/537.36', 'Windows 10', '2020-04-02 21:53:43'),
(155, 9, '{\"role\":\"2\",\"roleText\":\"Manager\",\"name\":\"Matheus\"}', '::1', 'Chrome 80.0.3987.149', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.149 Safari/537.36', 'Windows 10', '2020-04-03 16:22:37'),
(156, 9, '{\"role\":\"2\",\"roleText\":\"Manager\",\"name\":\"Matheus\"}', '::1', 'Chrome 80.0.3987.149', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.149 Safari/537.36', 'Windows 10', '2020-04-04 12:19:16'),
(157, 9, '{\"role\":\"2\",\"roleText\":\"Manager\",\"name\":\"Matheus\"}', '::1', 'Chrome 80.0.3987.149', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.149 Safari/537.36', 'Windows 10', '2020-04-04 16:40:59'),
(158, 9, '{\"role\":\"2\",\"roleText\":\"Manager\",\"name\":\"Matheus\"}', '::1', 'Chrome 80.0.3987.149', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.149 Safari/537.36', 'Windows 10', '2020-04-05 20:07:10'),
(159, 9, '{\"role\":\"2\",\"roleText\":\"Manager\",\"name\":\"Matheus\"}', '::1', 'Chrome 80.0.3987.149', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.149 Safari/537.36', 'Windows 10', '2020-04-06 12:21:27'),
(160, 9, '{\"role\":\"2\",\"roleText\":\"Manager\",\"name\":\"Matheus\"}', '::1', 'Chrome 80.0.3987.149', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.149 Safari/537.36', 'Windows 10', '2020-04-06 16:06:59'),
(161, 9, '{\"role\":\"2\",\"roleText\":\"Manager\",\"name\":\"Matheus\"}', '::1', 'Chrome 80.0.3987.149', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.149 Safari/537.36', 'Windows 10', '2020-04-06 21:02:30'),
(162, 9, '{\"role\":\"2\",\"roleText\":\"Manager\",\"name\":\"Matheus\"}', '::1', 'Chrome 80.0.3987.163', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.163 Safari/537.36', 'Windows 10', '2020-04-07 11:02:17'),
(163, 9, '{\"role\":\"2\",\"roleText\":\"Manager\",\"name\":\"Matheus\"}', '192.168.0.54', 'Chrome 80.0.3987.149', 'Mozilla/5.0 (Linux; Android 8.0.0; ASUS_X00QD) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.149 Mobile Safari/537.36', 'Android', '2020-04-07 11:31:22'),
(164, 9, '{\"role\":\"2\",\"roleText\":\"Manager\",\"name\":\"Matheus\"}', '::1', 'Chrome 80.0.3987.163', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.163 Safari/537.36', 'Windows 10', '2020-04-07 12:41:25'),
(165, 9, '{\"role\":\"2\",\"roleText\":\"Manager\",\"name\":\"Matheus\"}', '::1', 'Chrome 80.0.3987.163', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.163 Safari/537.36', 'Windows 10', '2020-04-07 12:48:02'),
(166, 9, '{\"role\":\"2\",\"roleText\":\"Manager\",\"name\":\"Matheus\"}', '::1', 'Chrome 80.0.3987.163', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.163 Safari/537.36', 'Windows 10', '2020-04-07 12:55:57'),
(167, 9, '{\"role\":\"2\",\"roleText\":\"Manager\",\"name\":\"Matheus\"}', '192.168.0.54', 'Chrome 80.0.3987.149', 'Mozilla/5.0 (Linux; Android 8.0.0; ASUS_X00QD) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.149 Mobile Safari/537.36', 'Android', '2020-04-07 13:00:41'),
(168, 9, '{\"role\":\"2\",\"roleText\":\"Manager\",\"name\":\"Matheus\"}', '::1', 'Chrome 80.0.3987.163', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.163 Safari/537.36', 'Windows 10', '2020-04-07 14:34:21'),
(169, 9, '{\"role\":\"2\",\"roleText\":\"Manager\",\"name\":\"Matheus\"}', '192.168.0.54', 'Chrome 80.0.3987.149', 'Mozilla/5.0 (Linux; Android 8.0.0; ASUS_X00QD) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.149 Mobile Safari/537.36', 'Android', '2020-04-07 16:16:14'),
(170, 9, '{\"role\":\"2\",\"roleText\":\"Manager\",\"name\":\"Matheus\"}', '::1', 'Chrome 80.0.3987.163', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.163 Safari/537.36', 'Windows 10', '2020-04-08 12:18:42'),
(171, 9, '{\"role\":\"2\",\"roleText\":\"Manager\",\"name\":\"Matheus\"}', '::1', 'Chrome 80.0.3987.163', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.163 Safari/537.36', 'Windows 10', '2020-04-09 14:57:35'),
(172, 9, '{\"role\":\"2\",\"roleText\":\"Manager\",\"name\":\"Matheus\"}', '::1', 'Chrome 80.0.3987.163', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.163 Safari/537.36', 'Windows 10', '2020-04-09 20:42:15'),
(173, 9, '{\"role\":\"2\",\"roleText\":\"Manager\",\"name\":\"Matheus\"}', '::1', 'Chrome 80.0.3987.163', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.163 Safari/537.36', 'Windows 10', '2020-04-13 16:31:58'),
(174, 9, '{\"role\":\"2\",\"roleText\":\"Manager\",\"name\":\"Matheus\"}', '::1', 'Chrome 80.0.3987.163', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.163 Safari/537.36', 'Windows 10', '2020-04-15 11:06:37'),
(175, 9, '{\"role\":\"2\",\"roleText\":\"Manager\",\"name\":\"Matheus\"}', '::1', 'Chrome 80.0.3987.163', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.163 Safari/537.36', 'Windows 10', '2020-04-15 15:00:11'),
(176, 9, '{\"role\":\"2\",\"roleText\":\"Manager\",\"name\":\"Matheus\"}', '192.168.0.55', 'Chrome 80.0.3987.162', 'Mozilla/5.0 (Linux; Android 8.0.0; ASUS_X00QD) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.162 Mobile Safari/537.36', 'Android', '2020-04-16 10:26:35'),
(177, 9, '{\"role\":\"2\",\"roleText\":\"Manager\",\"name\":\"Matheus\"}', '::1', 'Chrome 80.0.3987.163', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.163 Safari/537.36', 'Windows 10', '2020-04-16 10:28:12'),
(178, 9, '{\"role\":\"2\",\"roleText\":\"Manager\",\"name\":\"Matheus\"}', '::1', 'Chrome 80.0.3987.163', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.163 Safari/537.36', 'Windows 10', '2020-04-16 15:11:15'),
(179, 9, '{\"role\":\"2\",\"roleText\":\"Manager\",\"name\":\"Matheus\"}', '::1', 'Chrome 80.0.3987.163', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.163 Safari/537.36', 'Windows 10', '2020-04-17 12:11:15'),
(180, 9, '{\"role\":\"2\",\"roleText\":\"Developer\",\"name\":\"Matheus\"}', '::1', 'Chrome 80.0.3987.163', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.163 Safari/537.36', 'Windows 10', '2020-04-17 12:13:46'),
(181, 9, '{\"role\":\"2\",\"roleText\":\"Developer\",\"name\":\"Matheus\"}', '192.168.0.55', 'Chrome 80.0.3987.162', 'Mozilla/5.0 (Linux; Android 8.0.0; ASUS_X00QD) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.162 Mobile Safari/537.36', 'Android', '2020-04-17 18:07:34'),
(182, 9, '{\"role\":\"2\",\"roleText\":\"Developer\",\"name\":\"Matheus\"}', '192.168.0.50', 'Chrome 80.0.3987.163', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.163 Safari/537.36', 'Windows 10', '2020-04-17 19:17:41'),
(183, 1, '{\"role\":\"1\",\"roleText\":\"System Administrator\",\"name\":\"System Administrator\"}', '::1', 'Chrome 80.0.3987.163', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.163 Safari/537.36', 'Windows 10', '2020-04-17 19:29:03'),
(184, 11, '{\"role\":\"3\",\"roleText\":\"Key User\",\"name\":\"Test\"}', '::1', 'Chrome 80.0.3987.163', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.163 Safari/537.36', 'Windows 10', '2020-04-17 20:15:16'),
(185, 9, '{\"role\":\"2\",\"roleText\":\"Developer\",\"name\":\"Matheus\"}', '::1', 'Chrome 80.0.3987.163', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.163 Safari/537.36', 'Windows 10', '2020-04-17 20:25:02');
INSERT INTO `tbl_last_login` (`id`, `userId`, `sessionData`, `machineIp`, `userAgent`, `agentString`, `platform`, `createdDtm`) VALUES
(186, 11, '{\"role\":\"3\",\"roleText\":\"Key User\",\"name\":\"Test\"}', '::1', 'Chrome 80.0.3987.163', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.163 Safari/537.36', 'Windows 10', '2020-04-17 20:28:02'),
(187, 9, '{\"role\":\"2\",\"roleText\":\"Developer\",\"name\":\"Matheus\"}', '::1', 'Chrome 80.0.3987.163', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.163 Safari/537.36', 'Windows 10', '2020-04-17 20:31:45'),
(188, 1, '{\"role\":\"1\",\"roleText\":\"System Administrator\",\"name\":\"System Administrator\"}', '::1', 'Chrome 80.0.3987.163', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.163 Safari/537.36', 'Windows 10', '2020-04-17 20:31:56'),
(189, 11, '{\"role\":\"3\",\"roleText\":\"Key User\",\"name\":\"Test\"}', '::1', 'Chrome 80.0.3987.163', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.163 Safari/537.36', 'Windows 10', '2020-04-17 20:32:02'),
(190, 1, '{\"role\":\"1\",\"roleText\":\"System Administrator\",\"name\":\"System Administrator\"}', '::1', 'Chrome 80.0.3987.163', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.163 Safari/537.36', 'Windows 10', '2020-04-17 20:33:32'),
(191, 9, '{\"role\":\"2\",\"roleText\":\"Developer\",\"name\":\"Matheus\"}', '::1', 'Chrome 80.0.3987.163', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.163 Safari/537.36', 'Windows 10', '2020-04-17 20:37:20'),
(192, 9, '{\"role\":\"2\",\"roleText\":\"Developer\",\"name\":\"Matheus\"}', '::1', 'Chrome 80.0.3987.163', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.163 Safari/537.36', 'Windows 10', '2020-04-21 11:59:27'),
(193, 9, '{\"role\":\"2\",\"roleText\":\"Developer\",\"name\":\"Matheus\"}', '192.168.0.50', 'Chrome 80.0.3987.163', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.163 Safari/537.36', 'Windows 10', '2020-04-21 12:02:58'),
(194, 9, '{\"role\":\"2\",\"roleText\":\"Developer\",\"name\":\"Matheus\"}', '192.168.0.55', 'Chrome 80.0.3987.162', 'Mozilla/5.0 (Linux; Android 8.0.0; ASUS_X00QD) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.162 Mobile Safari/537.36', 'Android', '2020-04-21 12:03:28'),
(195, 1, '{\"role\":\"1\",\"roleText\":\"System Administrator\",\"name\":\"System Administrator\"}', '192.168.0.55', 'Chrome 80.0.3987.162', 'Mozilla/5.0 (Linux; Android 8.0.0; ASUS_X00QD) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.162 Mobile Safari/537.36', 'Android', '2020-04-21 12:06:04'),
(196, 1, '{\"role\":\"1\",\"roleText\":\"System Administrator\",\"name\":\"System Administrator\"}', '::1', 'Chrome 80.0.3987.163', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.163 Safari/537.36', 'Windows 10', '2020-04-21 12:06:54'),
(197, 9, '{\"role\":\"2\",\"roleText\":\"Developer\",\"name\":\"Matheus\"}', '::1', 'Chrome 80.0.3987.163', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.163 Safari/537.36', 'Windows 10', '2020-04-21 12:07:39'),
(198, 9, '{\"role\":\"2\",\"roleText\":\"Developer\",\"name\":\"Matheus\"}', '192.168.0.53', 'Chrome 80.0.3987.162', 'Mozilla/5.0 (Linux; Android 8.0.0; ASUS_X00QD) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.162 Mobile Safari/537.36', 'Android', '2020-04-21 14:57:18'),
(199, 11, '{\"role\":\"3\",\"roleText\":\"Key User\",\"name\":\"Test\"}', '::1', 'Chrome 80.0.3987.163', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.163 Safari/537.36', 'Windows 10', '2020-04-21 16:49:25'),
(200, 9, '{\"role\":\"2\",\"roleText\":\"Developer\",\"name\":\"Matheus\"}', '::1', 'Chrome 80.0.3987.163', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.163 Safari/537.36', 'Windows 10', '2020-04-21 16:51:01'),
(201, 11, '{\"role\":\"3\",\"roleText\":\"Key User\",\"name\":\"Test\"}', '::1', 'Chrome 80.0.3987.163', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.163 Safari/537.36', 'Windows 10', '2020-04-21 17:01:32'),
(202, 9, '{\"role\":\"2\",\"roleText\":\"Developer\",\"name\":\"Matheus\"}', '::1', 'Chrome 80.0.3987.163', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.163 Safari/537.36', 'Windows 10', '2020-04-21 17:03:10'),
(203, 9, '{\"role\":\"2\",\"roleText\":\"Developer\",\"name\":\"Matheus\"}', '::1', 'Chrome 81.0.4044.113', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/81.0.4044.113 Safari/537.36', 'Windows 10', '2020-04-22 10:32:30'),
(204, 9, '{\"role\":\"2\",\"roleText\":\"Developer\",\"name\":\"Matheus\"}', '::1', 'Chrome 81.0.4044.113', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/81.0.4044.113 Safari/537.36', 'Windows 10', '2020-04-23 10:46:07'),
(205, 1, '{\"role\":\"1\",\"roleText\":\"System Administrator\",\"name\":\"System Administrator\"}', '::1', 'Chrome 81.0.4044.113', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/81.0.4044.113 Safari/537.36', 'Windows 10', '2020-04-23 11:22:31'),
(206, 9, '{\"role\":\"2\",\"roleText\":\"Developer\",\"name\":\"Matheus\"}', '::1', 'Chrome 81.0.4044.113', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/81.0.4044.113 Safari/537.36', 'Windows 10', '2020-04-23 11:24:27'),
(207, 9, '{\"role\":\"2\",\"roleText\":\"Developer\",\"name\":\"Matheus\"}', '::1', 'Chrome 81.0.4044.113', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/81.0.4044.113 Safari/537.36', 'Windows 10', '2020-04-23 18:52:13'),
(208, 9, '{\"role\":\"2\",\"roleText\":\"Developer\",\"name\":\"Matheus\"}', '::1', 'Chrome 81.0.4044.122', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/81.0.4044.122 Safari/537.36', 'Windows 10', '2020-04-24 20:55:23'),
(209, 9, '{\"role\":\"2\",\"roleText\":\"Developer\",\"name\":\"Matheus\"}', '::1', 'Chrome 81.0.4044.122', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/81.0.4044.122 Safari/537.36', 'Windows 10', '2020-04-27 10:31:33'),
(210, 9, '{\"role\":\"2\",\"roleText\":\"Developer\",\"name\":\"Matheus\"}', '::1', 'Chrome 81.0.4044.122', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/81.0.4044.122 Safari/537.36', 'Windows 10', '2020-04-29 16:25:59'),
(211, 9, '{\"role\":\"2\",\"roleText\":\"Developer\",\"name\":\"Matheus\"}', '::1', 'Chrome 81.0.4044.129', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/81.0.4044.129 Safari/537.36', 'Windows 10', '2020-05-02 16:17:03'),
(212, 9, '{\"role\":\"2\",\"roleText\":\"Developer\",\"name\":\"Matheus\"}', '::1', 'Chrome 81.0.4044.129', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/81.0.4044.129 Safari/537.36', 'Windows 10', '2020-05-04 15:52:58'),
(213, 9, '{\"role\":\"2\",\"roleText\":\"Developer\",\"name\":\"Matheus\"}', '::1', 'Chrome 81.0.4044.129', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/81.0.4044.129 Safari/537.36', 'Windows 10', '2020-05-04 20:52:33'),
(214, 9, '{\"role\":\"2\",\"roleText\":\"Developer\",\"name\":\"Matheus\"}', '::1', 'Chrome 81.0.4044.129', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/81.0.4044.129 Safari/537.36', 'Windows 10', '2020-05-05 09:54:12'),
(215, 9, '{\"role\":\"2\",\"roleText\":\"Developer\",\"name\":\"Matheus\"}', '::1', 'Chrome 81.0.4044.129', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/81.0.4044.129 Safari/537.36', 'Windows 10', '2020-05-05 17:00:09'),
(216, 9, '{\"role\":\"2\",\"roleText\":\"Developer\",\"name\":\"Matheus\"}', '::1', 'Chrome 81.0.4044.129', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/81.0.4044.129 Safari/537.36', 'Windows 10', '2020-05-05 20:39:52'),
(217, 9, '{\"role\":\"2\",\"roleText\":\"Developer\",\"name\":\"Matheus\"}', '::1', 'Chrome 81.0.4044.138', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/81.0.4044.138 Safari/537.36', 'Windows 10', '2020-05-06 09:36:21'),
(218, 9, '{\"role\":\"2\",\"roleText\":\"Developer\",\"name\":\"Matheus\"}', '::1', 'Chrome 81.0.4044.138', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/81.0.4044.138 Safari/537.36', 'Windows 10', '2020-05-06 11:43:27'),
(219, 9, '{\"role\":\"2\",\"roleText\":\"Developer\",\"name\":\"Matheus\"}', '::1', 'Chrome 81.0.4044.138', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/81.0.4044.138 Safari/537.36', 'Windows 10', '2020-05-06 20:12:40'),
(220, 9, '{\"role\":\"2\",\"roleText\":\"Developer\",\"name\":\"Matheus\"}', '::1', 'Chrome 81.0.4044.138', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/81.0.4044.138 Safari/537.36', 'Windows 10', '2020-05-07 09:37:15'),
(221, 9, '{\"role\":\"2\",\"roleText\":\"Developer\",\"name\":\"Matheus\"}', '::1', 'Chrome 81.0.4044.138', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/81.0.4044.138 Safari/537.36', 'Windows 10', '2020-05-07 15:23:16'),
(222, 1, '{\"role\":\"1\",\"roleText\":\"System Administrator\",\"name\":\"System Administrator\"}', '::1', 'Chrome 81.0.4044.138', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/81.0.4044.138 Safari/537.36', 'Windows 10', '2020-05-07 18:03:18'),
(223, 9, '{\"role\":\"2\",\"roleText\":\"Developer\",\"name\":\"Matheus\"}', '::1', 'Chrome 81.0.4044.138', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/81.0.4044.138 Safari/537.36', 'Windows 10', '2020-05-07 18:03:39'),
(224, 9, '{\"role\":\"2\",\"roleText\":\"Developer\",\"name\":\"Matheus\"}', '::1', 'Chrome 81.0.4044.138', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/81.0.4044.138 Safari/537.36', 'Windows 10', '2020-05-07 20:18:38'),
(225, 9, '{\"role\":\"2\",\"roleText\":\"Developer\",\"name\":\"Matheus\"}', '::1', 'Chrome 81.0.4044.138', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/81.0.4044.138 Safari/537.36', 'Windows 10', '2020-05-08 11:16:28'),
(226, 9, '{\"role\":\"2\",\"roleText\":\"Developer\",\"name\":\"Matheus\"}', '::1', 'Chrome 81.0.4044.138', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/81.0.4044.138 Safari/537.36', 'Windows 10', '2020-05-08 12:41:35'),
(227, 1, '{\"role\":\"1\",\"roleText\":\"System Administrator\",\"name\":\"System Administrator\"}', '::1', 'Chrome 81.0.4044.138', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/81.0.4044.138 Safari/537.36', 'Windows 10', '2020-05-08 12:59:21'),
(228, 11, '{\"role\":\"3\",\"roleText\":\"Key User\",\"name\":\"Test\"}', '::1', 'Chrome 81.0.4044.138', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/81.0.4044.138 Safari/537.36', 'Windows 10', '2020-05-08 12:59:59'),
(229, 9, '{\"role\":\"2\",\"roleText\":\"Developer\",\"name\":\"Matheus\"}', '::1', 'Chrome 81.0.4044.138', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/81.0.4044.138 Safari/537.36', 'Windows 10', '2020-05-08 13:01:31'),
(230, 9, '{\"role\":\"2\",\"roleText\":\"Developer\",\"name\":\"Matheus\"}', '::1', 'Chrome 81.0.4044.138', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/81.0.4044.138 Safari/537.36', 'Windows 10', '2020-05-08 15:43:41'),
(231, 9, '{\"role\":\"2\",\"roleText\":\"Developer\",\"name\":\"Matheus\"}', '::1', 'Chrome 81.0.4044.138', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/81.0.4044.138 Safari/537.36', 'Windows 10', '2020-05-11 11:47:07'),
(232, 9, '{\"role\":\"2\",\"roleText\":\"Developer\",\"name\":\"Matheus\"}', '::1', 'Chrome 81.0.4044.138', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/81.0.4044.138 Safari/537.36', 'Windows 10', '2020-05-12 15:03:12'),
(233, 9, '{\"role\":\"2\",\"roleText\":\"Developer\",\"name\":\"Matheus\"}', '::1', 'Chrome 81.0.4044.138', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/81.0.4044.138 Safari/537.36', 'Windows 10', '2020-05-13 10:47:22'),
(234, 9, '{\"role\":\"2\",\"roleText\":\"Developer\",\"name\":\"Matheus\"}', '::1', 'Chrome 81.0.4044.138', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/81.0.4044.138 Safari/537.36', 'Windows 10', '2020-05-15 10:42:26'),
(235, 9, '{\"role\":\"2\",\"roleText\":\"Developer\",\"name\":\"Matheus\"}', '::1', 'Chrome 81.0.4044.138', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/81.0.4044.138 Safari/537.36', 'Windows 10', '2020-05-18 14:08:56'),
(236, 9, '{\"role\":\"2\",\"roleText\":\"Developer\",\"name\":\"Matheus\"}', '::1', 'Chrome 81.0.4044.138', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/81.0.4044.138 Safari/537.36', 'Windows 10', '2020-05-18 17:11:51'),
(237, 9, '{\"role\":\"2\",\"roleText\":\"Developer\",\"name\":\"Matheus\"}', '::1', 'Chrome 81.0.4044.138', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/81.0.4044.138 Safari/537.36', 'Windows 10', '2020-05-18 20:13:09'),
(238, 9, '{\"role\":\"2\",\"roleText\":\"Developer\",\"name\":\"Matheus\"}', '::1', 'Chrome 81.0.4044.138', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/81.0.4044.138 Safari/537.36', 'Windows 10', '2020-05-18 20:53:01'),
(239, 9, '{\"role\":\"2\",\"roleText\":\"Developer\",\"name\":\"Matheus\"}', '::1', 'Chrome 81.0.4044.138', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/81.0.4044.138 Safari/537.36', 'Windows 10', '2020-05-18 23:26:39'),
(240, 9, '{\"role\":\"2\",\"roleText\":\"Developer\",\"name\":\"Matheus\"}', '::1', 'Chrome 81.0.4044.138', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/81.0.4044.138 Safari/537.36', 'Windows 10', '2020-05-19 09:47:55'),
(241, 9, '{\"role\":\"2\",\"roleText\":\"Developer\",\"name\":\"Matheus\"}', '::1', 'Chrome 81.0.4044.138', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/81.0.4044.138 Safari/537.36', 'Windows 10', '2020-05-19 14:28:52'),
(242, 11, '{\"role\":\"3\",\"roleText\":\"Key User\",\"name\":\"Test\"}', '::1', 'Chrome 81.0.4044.138', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/81.0.4044.138 Safari/537.36', 'Windows 10', '2020-05-19 15:26:17'),
(243, 9, '{\"role\":\"2\",\"roleText\":\"Developer\",\"name\":\"Matheus\"}', '::1', 'Chrome 81.0.4044.138', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/81.0.4044.138 Safari/537.36', 'Windows 10', '2020-05-19 15:30:42'),
(244, 9, '{\"role\":\"2\",\"roleText\":\"Developer\",\"name\":\"Matheus\"}', '::1', 'Chrome 81.0.4044.138', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/81.0.4044.138 Safari/537.36', 'Windows 10', '2020-05-19 21:33:50'),
(245, 9, '{\"role\":\"2\",\"roleText\":\"Developer\",\"name\":\"Matheus\"}', '::1', 'Chrome 81.0.4044.138', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/81.0.4044.138 Safari/537.36', 'Windows 10', '2020-05-20 16:05:11'),
(246, 9, '{\"role\":\"2\",\"roleText\":\"Developer\",\"name\":\"Matheus\"}', '::1', 'Chrome 81.0.4044.138', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/81.0.4044.138 Safari/537.36', 'Windows 10', '2020-05-20 21:04:16'),
(247, 9, '{\"role\":\"2\",\"roleText\":\"Developer\",\"name\":\"Matheus\"}', '::1', 'Chrome 81.0.4044.138', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/81.0.4044.138 Safari/537.36', 'Windows 10', '2020-05-20 21:09:04'),
(248, 9, '{\"role\":\"2\",\"roleText\":\"Developer\",\"name\":\"Matheus\"}', '::1', 'Chrome 81.0.4044.138', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/81.0.4044.138 Safari/537.36', 'Windows 10', '2020-05-21 16:22:02'),
(249, 9, '{\"role\":\"2\",\"roleText\":\"Developer\",\"name\":\"Matheus\"}', '::1', 'Chrome 81.0.4044.138', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/81.0.4044.138 Safari/537.36', 'Windows 10', '2020-05-22 13:46:57'),
(250, 9, '{\"role\":\"2\",\"roleText\":\"Developer\",\"name\":\"Matheus\"}', '::1', 'Chrome 81.0.4044.138', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/81.0.4044.138 Safari/537.36', 'Windows 10', '2020-05-24 21:26:35'),
(251, 9, '{\"role\":\"2\",\"roleText\":\"Developer\",\"name\":\"Matheus\"}', '::1', 'Chrome 81.0.4044.138', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/81.0.4044.138 Safari/537.36', 'Windows 10', '2020-05-25 18:51:23'),
(252, 11, '{\"role\":\"3\",\"roleText\":\"Key User\",\"name\":\"Test\"}', '::1', 'Chrome 81.0.4044.138', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/81.0.4044.138 Safari/537.36', 'Windows 10', '2020-05-25 19:38:11'),
(253, 1, '{\"role\":\"1\",\"roleText\":\"System Administrator\",\"name\":\"System Administrator\"}', '::1', 'Chrome 81.0.4044.138', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/81.0.4044.138 Safari/537.36', 'Windows 10', '2020-05-25 19:39:53'),
(254, 9, '{\"role\":\"2\",\"roleText\":\"Developer\",\"name\":\"Matheus\"}', '::1', 'Chrome 81.0.4044.138', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/81.0.4044.138 Safari/537.36', 'Windows 10', '2020-05-25 19:43:12'),
(255, 9, '{\"role\":\"2\",\"roleText\":\"Developer\",\"name\":\"Matheus\"}', '::1', 'Chrome 81.0.4044.138', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/81.0.4044.138 Safari/537.36', 'Windows 10', '2020-05-26 21:20:56'),
(256, 9, '{\"role\":\"2\",\"roleText\":\"Developer\",\"name\":\"Matheus\"}', '::1', 'Chrome 81.0.4044.138', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/81.0.4044.138 Safari/537.36', 'Windows 10', '2020-05-26 22:26:53'),
(257, 9, '{\"role\":\"2\",\"roleText\":\"Developer\",\"name\":\"Matheus\"}', '::1', 'Chrome 81.0.4044.138', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/81.0.4044.138 Safari/537.36', 'Windows 10', '2020-05-27 16:45:12'),
(258, 9, '{\"role\":\"2\",\"roleText\":\"Developer\",\"name\":\"Matheus\"}', '::1', 'Chrome 81.0.4044.138', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/81.0.4044.138 Safari/537.36', 'Windows 10', '2020-05-27 20:03:55'),
(259, 9, '{\"role\":\"2\",\"roleText\":\"Developer\",\"name\":\"Matheus\"}', '::1', 'Chrome 83.0.4103.61', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/83.0.4103.61 Safari/537.36', 'Windows 10', '2020-05-28 14:22:50'),
(260, 9, '{\"role\":\"2\",\"roleText\":\"Developer\",\"name\":\"Matheus\"}', '::1', 'Chrome 83.0.4103.61', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/83.0.4103.61 Safari/537.36', 'Windows 10', '2020-05-28 21:44:39'),
(261, 9, '{\"role\":\"2\",\"roleText\":\"Developer\",\"name\":\"Matheus\"}', '::1', 'Chrome 83.0.4103.61', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/83.0.4103.61 Safari/537.36', 'Windows 10', '2020-05-28 22:05:00'),
(262, 11, '{\"role\":\"3\",\"roleText\":\"Key User\",\"name\":\"Test\"}', '::1', 'Chrome 83.0.4103.61', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/83.0.4103.61 Safari/537.36', 'Windows 10', '2020-05-28 23:21:29'),
(263, 9, '{\"role\":\"2\",\"roleText\":\"Developer\",\"name\":\"Matheus\"}', '::1', 'Chrome 83.0.4103.61', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/83.0.4103.61 Safari/537.36', 'Windows 10', '2020-05-28 23:23:37'),
(264, 9, '{\"role\":\"2\",\"roleText\":\"Developer\",\"name\":\"Matheus\"}', '::1', 'Chrome 83.0.4103.61', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/83.0.4103.61 Safari/537.36', 'Windows 10', '2020-05-29 14:22:19'),
(265, 9, '{\"role\":\"2\",\"roleText\":\"Developer\",\"name\":\"Matheus\"}', '192.168.0.53', 'Chrome 81.0.4044.138', 'Mozilla/5.0 (Linux; Android 8.0.0; ASUS_X00QD) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/81.0.4044.138 Mobile Safari/537.36', 'Android', '2020-05-29 16:40:33'),
(266, 11, '{\"role\":\"3\",\"roleText\":\"Key User\",\"name\":\"Test\"}', '::1', 'Chrome 83.0.4103.61', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/83.0.4103.61 Safari/537.36', 'Windows 10', '2020-05-29 19:13:04'),
(267, 9, '{\"role\":\"2\",\"roleText\":\"Developer\",\"name\":\"Matheus\"}', '::1', 'Chrome 83.0.4103.61', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/83.0.4103.61 Safari/537.36', 'Windows 10', '2020-05-29 19:15:59'),
(268, 11, '{\"role\":\"3\",\"roleText\":\"Key User\",\"name\":\"Test\"}', '::1', 'Chrome 83.0.4103.61', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/83.0.4103.61 Safari/537.36', 'Windows 10', '2020-05-29 19:31:26'),
(269, 9, '{\"role\":\"2\",\"roleText\":\"Developer\",\"name\":\"Matheus\"}', '::1', 'Chrome 83.0.4103.61', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/83.0.4103.61 Safari/537.36', 'Windows 10', '2020-05-29 19:35:14'),
(270, 9, '{\"role\":\"2\",\"roleText\":\"Developer\",\"name\":\"Matheus\"}', '::1', 'Chrome 83.0.4103.61', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/83.0.4103.61 Safari/537.36', 'Windows 10', '2020-05-29 21:52:08'),
(271, 9, '{\"role\":\"2\",\"roleText\":\"Developer\",\"name\":\"Matheus\"}', '::1', 'Chrome 83.0.4103.61', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/83.0.4103.61 Safari/537.36', 'Windows 10', '2020-06-01 12:37:36'),
(272, 11, '{\"role\":\"3\",\"roleText\":\"Key User\",\"name\":\"Test\"}', '::1', 'Chrome 83.0.4103.61', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/83.0.4103.61 Safari/537.36', 'Windows 10', '2020-06-01 14:04:27'),
(273, 1, '{\"role\":\"1\",\"roleText\":\"System Administrator\",\"name\":\"System Administrator\"}', '::1', 'Chrome 83.0.4103.61', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/83.0.4103.61 Safari/537.36', 'Windows 10', '2020-06-01 14:05:00'),
(274, 13, '{\"role\":\"3\",\"roleText\":\"Key User\",\"name\":\"Caca\"}', '::1', 'Chrome 83.0.4103.61', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/83.0.4103.61 Safari/537.36', 'Windows 10', '2020-06-01 14:09:30'),
(275, 1, '{\"role\":\"1\",\"roleText\":\"System Administrator\",\"name\":\"System Administrator\"}', '::1', 'Chrome 83.0.4103.61', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/83.0.4103.61 Safari/537.36', 'Windows 10', '2020-06-01 14:10:52'),
(276, 1, '{\"role\":\"1\",\"roleText\":\"System Administrator\",\"name\":\"System Administrator\"}', '::1', 'Chrome 83.0.4103.61', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/83.0.4103.61 Safari/537.36', 'Windows 10', '2020-06-01 14:29:13'),
(277, 9, '{\"role\":\"2\",\"roleText\":\"Developer\",\"name\":\"Matheus\"}', '::1', 'Chrome 83.0.4103.61', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/83.0.4103.61 Safari/537.36', 'Windows 10', '2020-06-01 14:33:44'),
(278, 9, '{\"role\":\"2\",\"roleText\":\"Developer\",\"name\":\"Matheus\"}', '::1', 'Chrome 83.0.4103.61', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/83.0.4103.61 Safari/537.36', 'Windows 10', '2020-06-01 20:58:27'),
(279, 11, '{\"role\":\"3\",\"roleText\":\"Key User\",\"name\":\"Test\"}', '::1', 'Chrome 83.0.4103.61', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/83.0.4103.61 Safari/537.36', 'Windows 10', '2020-06-01 21:41:30'),
(280, 9, '{\"role\":\"2\",\"roleText\":\"Developer\",\"name\":\"Matheus\"}', '::1', 'Chrome 83.0.4103.61', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/83.0.4103.61 Safari/537.36', 'Windows 10', '2020-06-01 21:41:52'),
(281, 11, '{\"role\":\"3\",\"roleText\":\"Key User\",\"name\":\"Test\"}', '::1', 'Chrome 83.0.4103.61', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/83.0.4103.61 Safari/537.36', 'Windows 10', '2020-06-01 22:57:11'),
(282, 9, '{\"role\":\"2\",\"roleText\":\"Developer\",\"name\":\"Matheus\"}', '::1', 'Chrome 83.0.4103.61', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/83.0.4103.61 Safari/537.36', 'Windows 10', '2020-06-01 22:58:50'),
(283, 1, '{\"role\":\"1\",\"roleText\":\"System Administrator\",\"name\":\"System Administrator\"}', '::1', 'Chrome 83.0.4103.61', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/83.0.4103.61 Safari/537.36', 'Windows 10', '2020-06-01 22:59:08'),
(284, 11, '{\"role\":\"3\",\"roleText\":\"Key User\",\"name\":\"Test\"}', '::1', 'Chrome 83.0.4103.61', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/83.0.4103.61 Safari/537.36', 'Windows 10', '2020-06-01 22:59:15'),
(285, 9, '{\"role\":\"2\",\"roleText\":\"Developer\",\"name\":\"Matheus\"}', '::1', 'Chrome 83.0.4103.61', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/83.0.4103.61 Safari/537.36', 'Windows 10', '2020-06-01 22:59:49'),
(286, 11, '{\"role\":\"3\",\"roleText\":\"Key User\",\"name\":\"Test\"}', '::1', 'Chrome 83.0.4103.61', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/83.0.4103.61 Safari/537.36', 'Windows 10', '2020-06-01 23:02:10'),
(287, 9, '{\"role\":\"2\",\"roleText\":\"Developer\",\"name\":\"Matheus\"}', '::1', 'Chrome 83.0.4103.61', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/83.0.4103.61 Safari/537.36', 'Windows 10', '2020-06-01 23:05:20'),
(288, 11, '{\"role\":\"3\",\"roleText\":\"Key User\",\"name\":\"Test\"}', '::1', 'Chrome 83.0.4103.61', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/83.0.4103.61 Safari/537.36', 'Windows 10', '2020-06-01 23:06:49'),
(289, 9, '{\"role\":\"2\",\"roleText\":\"Developer\",\"name\":\"Matheus\"}', '::1', 'Chrome 83.0.4103.61', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/83.0.4103.61 Safari/537.36', 'Windows 10', '2020-06-01 23:07:10'),
(290, 11, '{\"role\":\"3\",\"roleText\":\"Key User\",\"name\":\"Test\"}', '::1', 'Chrome 83.0.4103.61', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/83.0.4103.61 Safari/537.36', 'Windows 10', '2020-06-01 23:08:31'),
(291, 11, '{\"role\":\"3\",\"roleText\":\"Key User\",\"name\":\"Test\"}', '::1', 'Chrome 83.0.4103.61', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/83.0.4103.61 Safari/537.36', 'Windows 10', '2020-06-01 23:17:48'),
(292, 9, '{\"role\":\"2\",\"roleText\":\"Developer\",\"name\":\"Matheus\"}', '::1', 'Chrome 83.0.4103.61', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/83.0.4103.61 Safari/537.36', 'Windows 10', '2020-06-01 23:18:07'),
(293, 11, '{\"role\":\"3\",\"roleText\":\"Key User\",\"name\":\"Test\"}', '::1', 'Chrome 83.0.4103.61', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/83.0.4103.61 Safari/537.36', 'Windows 10', '2020-06-01 23:21:01'),
(294, 9, '{\"role\":\"2\",\"roleText\":\"Developer\",\"name\":\"Matheus\"}', '::1', 'Chrome 83.0.4103.61', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/83.0.4103.61 Safari/537.36', 'Windows 10', '2020-06-01 23:21:20'),
(295, 1, '{\"role\":\"1\",\"roleText\":\"System Administrator\",\"name\":\"System Administrator\"}', '::1', 'Chrome 83.0.4103.61', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/83.0.4103.61 Safari/537.36', 'Windows 10', '2020-06-01 23:21:46'),
(296, 9, '{\"role\":\"2\",\"roleText\":\"Developer\",\"name\":\"Matheus\"}', '::1', 'Chrome 83.0.4103.61', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/83.0.4103.61 Safari/537.36', 'Windows 10', '2020-06-02 17:15:20'),
(297, 11, '{\"role\":\"3\",\"roleText\":\"Key User\",\"name\":\"Test\"}', '::1', 'Chrome 83.0.4103.61', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/83.0.4103.61 Safari/537.36', 'Windows 10', '2020-06-02 19:27:10'),
(298, 9, '{\"role\":\"2\",\"roleText\":\"Developer\",\"name\":\"Matheus\"}', '::1', 'Chrome 83.0.4103.61', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/83.0.4103.61 Safari/537.36', 'Windows 10', '2020-06-02 19:27:19'),
(299, 9, '{\"role\":\"2\",\"roleText\":\"Developer\",\"name\":\"Matheus\"}', '::1', 'Chrome 83.0.4103.61', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/83.0.4103.61 Safari/537.36', 'Windows 10', '2020-06-02 22:08:47'),
(300, 9, '{\"role\":\"2\",\"roleText\":\"Developer\",\"name\":\"Matheus\"}', '::1', 'Chrome 83.0.4103.61', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/83.0.4103.61 Safari/537.36', 'Windows 10', '2020-06-02 22:10:41'),
(301, 1, '{\"role\":\"1\",\"roleText\":\"System Administrator\",\"name\":\"System Administrator\"}', '::1', 'Chrome 83.0.4103.61', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/83.0.4103.61 Safari/537.36', 'Windows 10', '2020-06-02 22:14:00'),
(302, 9, '{\"role\":\"2\",\"roleText\":\"Developer\",\"name\":\"Matheus\"}', '::1', 'Chrome 83.0.4103.61', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/83.0.4103.61 Safari/537.36', 'Windows 10', '2020-06-02 23:45:38'),
(303, 9, '{\"role\":\"2\",\"roleText\":\"Developer\",\"name\":\"Matheus\"}', '::1', 'Chrome 83.0.4103.61', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/83.0.4103.61 Safari/537.36', 'Windows 10', '2020-06-03 11:42:41'),
(304, 9, '{\"role\":\"2\",\"roleText\":\"Developer\",\"name\":\"Matheus\"}', '::1', 'Chrome 83.0.4103.61', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/83.0.4103.61 Safari/537.36', 'Windows 10', '2020-06-03 14:02:54'),
(305, 9, '{\"role\":\"2\",\"roleText\":\"Developer\",\"name\":\"Matheus\"}', '::1', 'Chrome 83.0.4103.61', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/83.0.4103.61 Safari/537.36', 'Windows 10', '2020-06-03 14:08:03'),
(306, 9, '{\"role\":\"2\",\"roleText\":\"Developer\",\"name\":\"Matheus\"}', '::1', 'Chrome 83.0.4103.61', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/83.0.4103.61 Safari/537.36', 'Windows 10', '2020-06-04 15:46:29'),
(307, 1, '{\"role\":\"1\",\"roleText\":\"System Administrator\",\"name\":\"System Administrator\"}', '::1', 'Chrome 83.0.4103.61', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/83.0.4103.61 Safari/537.36', 'Windows 10', '2020-06-04 15:54:28'),
(308, 9, '{\"role\":\"2\",\"roleText\":\"Developer\",\"name\":\"Matheus\"}', '::1', 'Chrome 83.0.4103.61', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/83.0.4103.61 Safari/537.36', 'Windows 10', '2020-06-04 15:54:41'),
(309, 9, '{\"role\":\"2\",\"roleText\":\"Developer\",\"name\":\"Matheus\"}', '::1', 'Chrome 83.0.4103.61', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/83.0.4103.61 Safari/537.36', 'Windows 10', '2020-06-04 21:41:09'),
(310, 9, '{\"role\":\"2\",\"roleText\":\"Developer\",\"name\":\"Matheus\"}', '::1', 'Chrome 83.0.4103.61', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/83.0.4103.61 Safari/537.36', 'Windows 10', '2020-06-05 11:57:41'),
(311, 9, '{\"role\":\"2\",\"roleText\":\"Developer\",\"name\":\"Matheus\"}', '::1', 'Chrome 83.0.4103.61', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/83.0.4103.61 Safari/537.36', 'Windows 10', '2020-06-05 12:21:02'),
(312, 9, '{\"role\":\"2\",\"roleText\":\"Developer\",\"name\":\"Matheus\"}', '::1', 'Chrome 83.0.4103.61', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/83.0.4103.61 Safari/537.36', 'Windows 10', '2020-06-07 17:42:47'),
(313, 9, '{\"role\":\"2\",\"roleText\":\"Developer\",\"name\":\"Matheus\"}', '::1', 'Chrome 83.0.4103.61', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/83.0.4103.61 Safari/537.36', 'Windows 10', '2020-06-07 17:50:24'),
(314, 9, '{\"role\":\"2\",\"roleText\":\"Developer\",\"name\":\"Matheus\"}', '::1', 'Chrome 83.0.4103.61', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/83.0.4103.61 Safari/537.36', 'Windows 10', '2020-06-07 17:59:33'),
(315, 9, '{\"role\":\"2\",\"roleText\":\"Developer\",\"name\":\"Matheus\"}', '::1', 'Chrome 83.0.4103.61', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/83.0.4103.61 Safari/537.36', 'Windows 10', '2020-06-07 18:05:21'),
(316, 9, '{\"role\":\"2\",\"roleText\":\"Developer\",\"name\":\"Matheus\"}', '::1', 'Chrome 83.0.4103.61', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/83.0.4103.61 Safari/537.36', 'Windows 10', '2020-06-07 18:43:58'),
(317, 9, '{\"role\":\"2\",\"roleText\":\"Developer\",\"name\":\"Matheus\"}', '::1', 'Chrome 83.0.4103.97', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/83.0.4103.97 Safari/537.36', 'Windows 10', '2020-06-15 15:46:46'),
(318, 9, '{\"role\":\"2\",\"roleText\":\"Developer\",\"name\":\"Matheus\"}', '::1', 'Chrome 83.0.4103.97', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/83.0.4103.97 Safari/537.36', 'Windows 10', '2020-06-16 20:25:35'),
(319, 9, '{\"role\":\"2\",\"roleText\":\"Developer\",\"name\":\"Matheus\"}', '::1', 'Chrome 83.0.4103.106', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/83.0.4103.106 Safari/537.36', 'Windows 10', '2020-06-22 10:21:00'),
(320, 9, '{\"role\":\"2\",\"roleText\":\"Developer\",\"name\":\"Matheus\"}', '::1', 'Chrome 83.0.4103.116', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/83.0.4103.116 Safari/537.36', 'Windows 10', '2020-07-07 20:16:41'),
(321, 9, '{\"role\":\"2\",\"roleText\":\"Developer\",\"name\":\"Matheus\"}', '::1', 'Chrome 83.0.4103.116', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/83.0.4103.116 Safari/537.36', 'Windows 10', '2020-07-07 22:25:20'),
(322, 9, '{\"role\":\"2\",\"roleText\":\"Developer\",\"name\":\"Matheus\"}', '::1', 'Chrome 83.0.4103.116', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/83.0.4103.116 Safari/537.36', 'Windows 10', '2020-07-08 09:43:17'),
(323, 9, '{\"role\":\"2\",\"roleText\":\"Developer\",\"name\":\"Matheus\"}', '::1', 'Chrome 83.0.4103.116', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/83.0.4103.116 Safari/537.36', 'Windows 10', '2020-07-10 15:53:44'),
(324, 9, '{\"role\":\"2\",\"roleText\":\"Developer\",\"name\":\"Matheus\"}', '::1', 'Chrome 83.0.4103.116', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/83.0.4103.116 Safari/537.36', 'Windows 10', '2020-07-13 15:56:31'),
(325, 1, '{\"role\":\"1\",\"roleText\":\"System Administrator\",\"name\":\"System Administrator\"}', '::1', 'Chrome 83.0.4103.116', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/83.0.4103.116 Safari/537.36', 'Windows 10', '2020-07-13 15:58:23'),
(326, 9, '{\"role\":\"2\",\"roleText\":\"Developer\",\"name\":\"Matheus\"}', '::1', 'Chrome 83.0.4103.116', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/83.0.4103.116 Safari/537.36', 'Windows 10', '2020-07-19 18:56:08');

-- --------------------------------------------------------

--
-- Estrutura da tabela `tbl_reset_password`
--

CREATE TABLE `tbl_reset_password` (
  `id` bigint(20) NOT NULL,
  `email` varchar(128) NOT NULL,
  `activation_id` varchar(32) NOT NULL,
  `agent` varchar(512) NOT NULL,
  `client_ip` varchar(32) NOT NULL,
  `isDeleted` tinyint(4) NOT NULL DEFAULT 0,
  `createdBy` bigint(20) NOT NULL DEFAULT 1,
  `createdDtm` datetime NOT NULL,
  `updatedBy` bigint(20) DEFAULT NULL,
  `updatedDtm` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Extraindo dados da tabela `tbl_reset_password`
--

INSERT INTO `tbl_reset_password` (`id`, `email`, `activation_id`, `agent`, `client_ip`, `isDeleted`, `createdBy`, `createdDtm`, `updatedBy`, `updatedDtm`) VALUES
(1, 'matheuspavanetti@gmail.com', '2YP6dkaGsLhKxfj', 'Chrome 79.0.3945.117', '::1', 0, 1, '2020-01-16 17:15:17', NULL, NULL),
(2, 'matheuspavanetti@gmail.com', 'gqL7OpAMmPTKJir', 'Chrome 81.0.4044.113', '::1', 0, 1, '2020-04-23 16:24:16', NULL, NULL),
(3, 'matheuspavanetti@gmail.com', '35ktyLSQFNWToeh', 'Chrome 83.0.4103.61', '::1', 0, 1, '2020-06-01 19:30:25', NULL, NULL);

-- --------------------------------------------------------

--
-- Estrutura da tabela `tbl_roles`
--

CREATE TABLE `tbl_roles` (
  `roleId` tinyint(4) NOT NULL COMMENT 'role id',
  `role` varchar(50) NOT NULL COMMENT 'role text'
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Extraindo dados da tabela `tbl_roles`
--

INSERT INTO `tbl_roles` (`roleId`, `role`) VALUES
(1, 'System Administrator'),
(2, 'Developer'),
(3, 'Key User');

-- --------------------------------------------------------

--
-- Estrutura da tabela `tbl_users`
--

CREATE TABLE `tbl_users` (
  `userId` int(11) NOT NULL,
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
  `updatedDtm` datetime DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Extraindo dados da tabela `tbl_users`
--

INSERT INTO `tbl_users` (`userId`, `email`, `password`, `name`, `mobile`, `groupId`, `roleId`, `isDeleted`, `createdBy`, `createdDtm`, `updatedBy`, `updatedDtm`) VALUES
(1, 'admin@example.com', '$2y$10$rqyS7G8jngEyE5TAaFruduuyO7m1BtWGaq401l/ptJPjiXeYTLeCG', 'System Administrator', '9890098900', NULL, 1, 0, 0, '2015-07-01 18:56:49', 1, '2019-11-27 20:20:34'),
(2, 'manager@example.com', '$2y$10$iygibOUQRUW5X6EAwEgRXOgTulmFteUD528/0mhTzICtD7gapEBwi', 'Manager', '9890098900', 2, 2, 0, 1, '2016-12-09 17:49:56', 1, '2020-07-13 20:59:00'),
(3, 'employee@example.com', '$2y$10$T54kQElg/nJHA/GNDWmLmelQViGEe/RyxX/2tvTnCsOrIvP0T7kZi', 'Employee', '9890098900', 1, 3, 1, 1, '2016-12-09 17:50:22', 1, '2020-06-03 04:40:33'),
(9, 'matheuspavanetti@gmail.com', '$2y$10$02uw6DlxzFgcS/ZpwHWXtuJVKtcH1q5oxhjXbN/GdcYdWeXt4V5ru', 'Matheus', '1298199258', 2, 2, 0, 1, '2019-11-27 19:39:58', 1, '2020-06-04 20:54:36'),
(10, 'test@nxmovies.com', '$2y$10$YfP66I6Lfomq6xtT3eAFiOT4uG9crJfq3bzIn7B6wolCExmgILSJi', 'Test', '1288121655', NULL, 3, 1, 1, '2020-01-07 01:13:53', 1, '2020-01-07 01:13:59'),
(11, 'test@email.com', '$2y$10$kve2II1dmp/6FEI1P0Y57.Y89QlVbiisJ/y.ZeHafJi80WL64HU62', 'Test', '1298199258', 18, 3, 0, 1, '2020-01-08 03:10:31', 1, '2020-07-13 20:58:56'),
(12, 'ccbcastello@gmail.com', '$2y$10$G8sh3ATplvPY3AXj2u7V3.B.EhqH86LJTdvNvl6oVVmvI/sO4GvGC', 'Jorge Castello', '9981992589', 2, 2, 0, 1, '2020-01-21 12:56:04', 1, '2020-06-03 03:56:36'),
(13, 'caca@email.com', '$2y$10$7RxuGIboJ7JxfgVVatRGXek4U123Zs5kvSGiyCCf2l6m.NJvLebTy', 'Caca', '1288121655', NULL, 3, 1, 1, '2020-06-01 19:09:24', 1, '2020-06-01 19:29:23'),
(14, 'test@test.com', '$2y$10$F10Z/3zknZ8TMuLX31RP/.vemssai.1H05a4qbBNgn9PRKTRicBOK', 'Test', '1981992589', 18, 3, 0, 1, '2020-06-03 03:40:29', 1, '2020-07-13 20:58:43');

-- --------------------------------------------------------

--
-- Estrutura da tabela `tmf`
--

CREATE TABLE `tmf` (
  `id` int(11) NOT NULL,
  `interface_id` varchar(200) COLLATE utf8_unicode_ci NOT NULL,
  `status` varchar(200) COLLATE utf8_unicode_ci NOT NULL,
  `job_name` varchar(200) COLLATE utf8_unicode_ci NOT NULL,
  `reprocess` tinyint(1) DEFAULT NULL,
  `event_text` varchar(200) COLLATE utf8_unicode_ci DEFAULT NULL,
  `dimension` varchar(200) COLLATE utf8_unicode_ci DEFAULT NULL,
  `records_total` varchar(200) COLLATE utf8_unicode_ci DEFAULT NULL,
  `records_processed` varchar(200) COLLATE utf8_unicode_ci DEFAULT NULL,
  `last_activity` datetime(6) DEFAULT NULL,
  `running_time` time(6) DEFAULT NULL,
  `distict_errors` tinyint(1) DEFAULT NULL,
  `warnings` varchar(200) COLLATE utf8_unicode_ci DEFAULT NULL,
  `hostname` varchar(200) COLLATE utf8_unicode_ci DEFAULT NULL,
  `username` varchar(200) COLLATE utf8_unicode_ci DEFAULT NULL,
  `instance_id` int(50) DEFAULT NULL,
  `start_time` datetime DEFAULT NULL,
  `msg` text COLLATE utf8_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura da tabela `tmf_error`
--

CREATE TABLE `tmf_error` (
  `id` int(11) NOT NULL,
  `tmf_id` int(11) DEFAULT NULL,
  `job_name` varchar(200) COLLATE utf8_unicode_ci DEFAULT NULL,
  `moment` datetime(6) DEFAULT NULL,
  `type` varchar(200) COLLATE utf8_unicode_ci DEFAULT NULL,
  `origin` varchar(200) COLLATE utf8_unicode_ci DEFAULT NULL,
  `message` varchar(5000) COLLATE utf8_unicode_ci DEFAULT NULL,
  `code` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

--
-- Índices para tabelas despejadas
--

--
-- Índices para tabela `ci_sessions`
--
ALTER TABLE `ci_sessions`
  ADD PRIMARY KEY (`session_id`),
  ADD KEY `last_activity_idx` (`last_activity`);

--
-- Índices para tabela `database_settings`
--
ALTER TABLE `database_settings`
  ADD PRIMARY KEY (`id`);

--
-- Índices para tabela `email_settings`
--
ALTER TABLE `email_settings`
  ADD PRIMARY KEY (`id`);

--
-- Índices para tabela `generic_settings`
--
ALTER TABLE `generic_settings`
  ADD PRIMARY KEY (`id`);

--
-- Índices para tabela `job_info`
--
ALTER TABLE `job_info`
  ADD PRIMARY KEY (`id`);

--
-- Índices para tabela `job_output`
--
ALTER TABLE `job_output`
  ADD PRIMARY KEY (`id`);

--
-- Índices para tabela `reports`
--
ALTER TABLE `reports`
  ADD PRIMARY KEY (`id`);

--
-- Índices para tabela `smtp_settings`
--
ALTER TABLE `smtp_settings`
  ADD PRIMARY KEY (`id`);

--
-- Índices para tabela `tbl_groups`
--
ALTER TABLE `tbl_groups`
  ADD PRIMARY KEY (`id`);

--
-- Índices para tabela `tbl_last_login`
--
ALTER TABLE `tbl_last_login`
  ADD PRIMARY KEY (`id`);

--
-- Índices para tabela `tbl_reset_password`
--
ALTER TABLE `tbl_reset_password`
  ADD PRIMARY KEY (`id`);

--
-- Índices para tabela `tbl_roles`
--
ALTER TABLE `tbl_roles`
  ADD PRIMARY KEY (`roleId`);

--
-- Índices para tabela `tbl_users`
--
ALTER TABLE `tbl_users`
  ADD PRIMARY KEY (`userId`);

--
-- Índices para tabela `tmf`
--
ALTER TABLE `tmf`
  ADD PRIMARY KEY (`id`);

--
-- Índices para tabela `tmf_error`
--
ALTER TABLE `tmf_error`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT de tabelas despejadas
--

--
-- AUTO_INCREMENT de tabela `database_settings`
--
ALTER TABLE `database_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de tabela `email_settings`
--
ALTER TABLE `email_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `generic_settings`
--
ALTER TABLE `generic_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `job_info`
--
ALTER TABLE `job_info`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `job_output`
--
ALTER TABLE `job_output`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `reports`
--
ALTER TABLE `reports`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `smtp_settings`
--
ALTER TABLE `smtp_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `tbl_groups`
--
ALTER TABLE `tbl_groups`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT de tabela `tbl_last_login`
--
ALTER TABLE `tbl_last_login`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=327;

--
-- AUTO_INCREMENT de tabela `tbl_reset_password`
--
ALTER TABLE `tbl_reset_password`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de tabela `tbl_roles`
--
ALTER TABLE `tbl_roles`
  MODIFY `roleId` tinyint(4) NOT NULL AUTO_INCREMENT COMMENT 'role id', AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de tabela `tbl_users`
--
ALTER TABLE `tbl_users`
  MODIFY `userId` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT de tabela `tmf`
--
ALTER TABLE `tmf`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `tmf_error`
--
ALTER TABLE `tmf_error`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
