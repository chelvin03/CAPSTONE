-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 23, 2026 at 06:31 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `mcst_gym_reservation_system`
--

-- --------------------------------------------------------

--
-- Table structure for table `analytics_reports`
--

CREATE TABLE `analytics_reports` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `submitted_by` bigint(20) UNSIGNED NOT NULL,
  `report_type` varchar(20) NOT NULL,
  `period_start` date NOT NULL,
  `period_end` date NOT NULL,
  `total_reservations` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `approved_reservations` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `completed_reservations` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `total_attendees` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `status_counts` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`status_counts`)),
  `facility_counts` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`facility_counts`)),
  `submitted_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `announcements`
--

CREATE TABLE `announcements` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `created_by` bigint(20) UNSIGNED NOT NULL,
  `title` varchar(200) NOT NULL,
  `content` text NOT NULL,
  `audience` varchar(50) NOT NULL DEFAULT 'all',
  `status` varchar(30) NOT NULL DEFAULT 'draft',
  `published_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `actor_name` varchar(255) DEFAULT NULL,
  `actor_email` varchar(255) DEFAULT NULL,
  `actor_role` varchar(50) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `module` varchar(100) DEFAULT NULL,
  `event` varchar(50) DEFAULT NULL,
  `auditable_type` varchar(255) DEFAULT NULL,
  `auditable_id` bigint(20) UNSIGNED DEFAULT NULL,
  `description` text DEFAULT NULL,
  `transaction_id` char(36) DEFAULT NULL,
  `request_id` char(36) DEFAULT NULL,
  `http_method` varchar(10) DEFAULT NULL,
  `route` text DEFAULT NULL,
  `old_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`old_values`)),
  `new_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`new_values`)),
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `previous_hash` varchar(64) DEFAULT NULL,
  `entry_hash` varchar(64) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `audit_logs`
--

INSERT INTO `audit_logs` (`id`, `user_id`, `actor_name`, `actor_email`, `actor_role`, `action`, `module`, `event`, `auditable_type`, `auditable_id`, `description`, `transaction_id`, `request_id`, `http_method`, `route`, `old_values`, `new_values`, `ip_address`, `user_agent`, `previous_hash`, `entry_hash`, `created_at`) VALUES
(1, 2, NULL, NULL, NULL, 'reservation.approved', 'reservation', 'approved', 'App\\Models\\Reservation', 9, 'reservation approved', NULL, NULL, NULL, NULL, NULL, '{\"previous_status\":\"new\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '0000000000000000000000000000000000000000000000000000000000000000', 'a2cd296394d1757966424ec72df57b96df87797f5d679738fda0cf06cd8a1d74', '2026-09-05 10:44:10'),
(2, 2, NULL, NULL, NULL, 'backup.restored', 'backup', 'restored', 'App\\Models\\BackupRecord', 2, 'backup restored', NULL, NULL, NULL, NULL, NULL, '{\"filename\":\"mcst-gym-2026-09-18_014253-2cff6d.mcstbak\",\"source_created_at\":\"2026-09-18T01:42:53+08:00\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', 'a2cd296394d1757966424ec72df57b96df87797f5d679738fda0cf06cd8a1d74', 'c7732823aa42bbc3185da1b00f5fb6c9d712e0613884819d1b81ba52ba358bca', '2026-09-17 17:50:17'),
(3, 2, NULL, NULL, NULL, 'backup.verified', 'backup', 'verified', 'App\\Models\\BackupRecord', 1, 'backup verified', NULL, NULL, NULL, NULL, NULL, '{\"filename\":\"mcst-gym-2026-09-18_013939-1b6894.mcstbak\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', 'c7732823aa42bbc3185da1b00f5fb6c9d712e0613884819d1b81ba52ba358bca', 'cac6af53b999381c5ad9a0d6f13ae1e8e780499d03ed3ec69f8c5bd161b79921', '2026-09-17 17:50:54'),
(4, 2, NULL, NULL, NULL, 'backup.verified', 'backup', 'verified', 'App\\Models\\BackupRecord', 2, 'backup verified', NULL, NULL, NULL, NULL, NULL, '{\"filename\":\"mcst-gym-2026-09-18_014253-2cff6d.mcstbak\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', 'cac6af53b999381c5ad9a0d6f13ae1e8e780499d03ed3ec69f8c5bd161b79921', 'a0c5ca42546ca3e0e21612ccf7dc2fd0f491991df5e159fb3fa69c23e2e0b10f', '2026-09-17 17:50:59'),
(5, 2, 'Admin MCST', 'admin@mcst.edu.ph', 'admin', 'authentication.login', 'authentication', 'login', 'App\\Models\\User', 2, 'Authentication Login', '781eb94a-0cc1-4c4d-a267-6a6f9e3a4374', '2df0904d-cfd6-4613-8142-cf6b1372aabc', 'POST', 'login', NULL, '{\"guard\":\"web\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', 'a0c5ca42546ca3e0e21612ccf7dc2fd0f491991df5e159fb3fa69c23e2e0b10f', '6d025aef4f9b15ea1fc141180f3b1a3a37a1eee2703f38f5051d23426c9d8ed1', '2026-09-18 02:31:20'),
(8, 2, 'Admin MCST', 'admin@mcst.edu.ph', 'admin', 'backup.restored', 'backup', 'restored', 'App\\Models\\BackupRecord', 4, 'Backup Restored', '69b1d56c-36e7-41d0-85de-0120f8257132', 'dca31d04-f840-4884-ab80-812481ec7c82', 'POST', 'admin.backups.restore', NULL, '{\"filename\":\"imported-2026-09-18_113026-d6be80.mcstbak\",\"source_created_at\":\"2026-09-18T11:29:27+08:00\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '6d025aef4f9b15ea1fc141180f3b1a3a37a1eee2703f38f5051d23426c9d8ed1', 'cc0ebfb101a78752b912574ae3e003665bd69ffb5353bf83bed8d7d7ace181ee', '2026-09-18 03:31:15'),
(9, 2, 'Admin MCST', 'admin@mcst.edu.ph', 'admin', 'backup.verified', 'backup', 'verified', 'App\\Models\\BackupRecord', 4, 'Backup Verified', '2e457e0e-8f94-4876-9ae0-372408be3578', 'a55ae69d-8ad5-4579-942e-58564e2d05ea', 'POST', 'admin.backups.verify', NULL, '{\"filename\":\"imported-2026-09-18_113026-d6be80.mcstbak\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', 'cc0ebfb101a78752b912574ae3e003665bd69ffb5353bf83bed8d7d7ace181ee', 'c242f9947aa2ad9d6be0dc4f06bf358ef257d455087826cfa53b0b908dcd35ec', '2026-09-18 03:32:09'),
(10, 2, 'Admin MCST', 'admin@mcst.edu.ph', 'admin', 'backup.verified', 'backup', 'verified', 'App\\Models\\BackupRecord', 3, 'Backup Verified', '7f926a32-8b6b-4e7d-a928-01458483a792', '88042d45-dfa9-44b2-b63e-7f973c393655', 'POST', 'admin.backups.verify', NULL, '{\"filename\":\"mcst-gym-2026-09-18_112927-685dd1.mcstbak\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', 'c242f9947aa2ad9d6be0dc4f06bf358ef257d455087826cfa53b0b908dcd35ec', '5cd7550c7a8ce161336152084792e6569a0bc6c9e082205dcbb2c8151b3e7c8e', '2026-09-18 03:32:15'),
(11, 2, 'Admin MCST', 'admin@mcst.edu.ph', 'admin', 'backup.verified', 'backup', 'verified', 'App\\Models\\BackupRecord', 2, 'Backup Verified', '18d779c7-b81d-4276-8ca4-4b1821484cf7', 'bbc9668a-b9ef-41e9-b590-9b929fa09543', 'POST', 'admin.backups.verify', NULL, '{\"filename\":\"mcst-gym-2026-09-18_014253-2cff6d.mcstbak\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '5cd7550c7a8ce161336152084792e6569a0bc6c9e082205dcbb2c8151b3e7c8e', 'd5dff06be0674a6c5e3cffb0c8826ed7bccabd80c9110a16a869b031642ccc3b', '2026-09-18 03:32:18'),
(12, 2, 'Admin MCST', 'admin@mcst.edu.ph', 'admin', 'backup.verified', 'backup', 'verified', 'App\\Models\\BackupRecord', 1, 'Backup Verified', 'fdf2c859-3cd8-4c21-bac1-329abfd17e70', '78e8bb6b-b5a0-45a5-96db-8dc93ee451cd', 'POST', 'admin.backups.verify', NULL, '{\"filename\":\"mcst-gym-2026-09-18_013939-1b6894.mcstbak\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', 'd5dff06be0674a6c5e3cffb0c8826ed7bccabd80c9110a16a869b031642ccc3b', '5d45ca05fcce2a94c96d7fa022ef0e6dbc6e98520d00e84fa1154f7b3daadffd', '2026-09-18 03:32:22'),
(13, 2, 'Admin MCST', 'admin@mcst.edu.ph', 'admin', 'backup.verified', 'backup', 'verified', 'App\\Models\\BackupRecord', 4, 'Backup Verified', 'f9df9283-baac-4341-92a1-b2e414867a2f', '811922e9-8f71-462a-91dd-4c8cbac1d887', 'POST', 'admin.backups.verify', NULL, '{\"filename\":\"imported-2026-09-18_113026-d6be80.mcstbak\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '5d45ca05fcce2a94c96d7fa022ef0e6dbc6e98520d00e84fa1154f7b3daadffd', '334e29eaea6111644e01f92baa0200677a207148a0a1bb17fc87a5970fd79e2a', '2026-09-18 03:32:27'),
(14, 2, 'Admin MCST', 'admin@mcst.edu.ph', 'admin', 'backup.verified', 'backup', 'verified', 'App\\Models\\BackupRecord', 4, 'Backup Verified', '4e120913-08bc-46c2-9d9f-e35ed1d3be08', 'd267ffb0-04e4-4d57-b4e4-aa63492cd91a', 'POST', 'admin.backups.verify', NULL, '{\"filename\":\"imported-2026-09-18_113026-d6be80.mcstbak\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '334e29eaea6111644e01f92baa0200677a207148a0a1bb17fc87a5970fd79e2a', 'c0fd572a9dd688f9b77e29d1e859b07dc53f503ea47fa4c33d89e6f288d41186', '2026-09-18 03:32:32'),
(15, 2, 'Admin MCST', 'admin@mcst.edu.ph', 'admin', 'backup.verified', 'backup', 'verified', 'App\\Models\\BackupRecord', 4, 'Backup Verified', 'bd95a6f5-7140-46eb-9d50-c12f27c73aad', 'f4128b42-6d3a-4af8-ab8a-16ead6196b0d', 'POST', 'admin.backups.verify', NULL, '{\"filename\":\"imported-2026-09-18_113026-d6be80.mcstbak\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', 'c0fd572a9dd688f9b77e29d1e859b07dc53f503ea47fa4c33d89e6f288d41186', 'f2552502a2983af01882669a623c4c34acd5d8084cfbc7a47fd0435005e7d4f2', '2026-09-18 03:32:38'),
(16, 2, 'Admin MCST', 'admin@mcst.edu.ph', 'admin', 'backup.verified', 'backup', 'verified', 'App\\Models\\BackupRecord', 4, 'Backup Verified', '6c0d47f7-5cff-4981-adb6-8bad07deb123', '47587783-362e-4b6c-a5e6-2e24292a63c4', 'POST', 'admin.backups.verify', NULL, '{\"filename\":\"imported-2026-09-18_113026-d6be80.mcstbak\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', 'f2552502a2983af01882669a623c4c34acd5d8084cfbc7a47fd0435005e7d4f2', '85a0cc1ebb92908f1a5d8aded462657867bcf0ba6d29318a1900c9ffd1629e8f', '2026-09-18 03:32:41'),
(17, 2, 'Admin MCST', 'admin@mcst.edu.ph', 'admin', 'backup.verified', 'backup', 'verified', 'App\\Models\\BackupRecord', 4, 'Backup Verified', 'c7dd1ee6-eed0-4b58-9201-a1ae226ecd65', 'eb39c411-8bea-4388-89a7-cd6e202cbecc', 'POST', 'admin.backups.verify', NULL, '{\"filename\":\"imported-2026-09-18_113026-d6be80.mcstbak\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '85a0cc1ebb92908f1a5d8aded462657867bcf0ba6d29318a1900c9ffd1629e8f', '4d352338b1777b685cea0a8ded6978de171766cf32a64225c0cadd9fe620f673', '2026-09-18 03:32:46'),
(18, 2, 'Admin MCST', 'admin@mcst.edu.ph', 'admin', 'backup.verified', 'backup', 'verified', 'App\\Models\\BackupRecord', 4, 'Backup Verified', '366bd1e9-6ace-4bee-a182-f1533d7ba2d3', '8ace72b6-2029-4da3-845e-dff5e166184d', 'POST', 'admin.backups.verify', NULL, '{\"filename\":\"imported-2026-09-18_113026-d6be80.mcstbak\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '4d352338b1777b685cea0a8ded6978de171766cf32a64225c0cadd9fe620f673', '6962a4406ac6816a9a9cfd0f9e94b73f05788273ac437e9859d78f0e6f89e29a', '2026-09-18 03:32:51'),
(19, 2, 'Admin MCST', 'admin@mcst.edu.ph', 'admin', 'backup.verified', 'backup', 'verified', 'App\\Models\\BackupRecord', 4, 'Backup Verified', 'cab56759-ced6-435f-b80c-627a339476ae', 'e2a9c76f-8acf-4c19-9b34-665abdf164d3', 'POST', 'admin.backups.verify', NULL, '{\"filename\":\"imported-2026-09-18_113026-d6be80.mcstbak\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '6962a4406ac6816a9a9cfd0f9e94b73f05788273ac437e9859d78f0e6f89e29a', '308c230687018c0b998818062b5e6ce38fdb5708f1a53ae0ae9e3f0d6027c11a', '2026-09-18 03:32:57'),
(20, 2, 'Admin MCST', 'admin@mcst.edu.ph', 'admin', 'backup.verified', 'backup', 'verified', 'App\\Models\\BackupRecord', 4, 'Backup Verified', 'd07a44b4-6131-4b57-9f89-f2a52d12fac7', '6256a5cc-9df7-431a-b184-db286e9740c4', 'POST', 'admin.backups.verify', NULL, '{\"filename\":\"imported-2026-09-18_113026-d6be80.mcstbak\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '308c230687018c0b998818062b5e6ce38fdb5708f1a53ae0ae9e3f0d6027c11a', '819a923dfbd5c08efe04fa488ee98f91e05556414866b4aee313e599a0b69b0c', '2026-09-18 03:33:00'),
(21, 2, 'Admin MCST', 'admin@mcst.edu.ph', 'admin', 'backup.verified', 'backup', 'verified', 'App\\Models\\BackupRecord', 4, 'Backup Verified', '0389117b-4e0d-41f1-a907-adee5296f92c', 'e979e03c-b6e0-41a8-8628-be49c1bb4ae8', 'POST', 'admin.backups.verify', NULL, '{\"filename\":\"imported-2026-09-18_113026-d6be80.mcstbak\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '819a923dfbd5c08efe04fa488ee98f91e05556414866b4aee313e599a0b69b0c', '0ea0c395075f18a992551aea5b9201b57b82a7c717980ce9308a8bf1aec88bc0', '2026-09-18 03:33:02'),
(22, 2, 'Admin MCST', 'admin@mcst.edu.ph', 'admin', 'backup.verified', 'backup', 'verified', 'App\\Models\\BackupRecord', 4, 'Backup Verified', '1358bc15-7c28-47f6-aa48-317e5a22a83c', '174393a3-2921-425c-909c-61ce998d8af4', 'POST', 'admin.backups.verify', NULL, '{\"filename\":\"imported-2026-09-18_113026-d6be80.mcstbak\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '0ea0c395075f18a992551aea5b9201b57b82a7c717980ce9308a8bf1aec88bc0', '8873097f43f4f46720ab21e62665f924746a508eb2fd9b9a9dbf0c3cde931275', '2026-09-18 03:33:04'),
(23, 2, 'Admin MCST', 'admin@mcst.edu.ph', 'admin', 'backup.verified', 'backup', 'verified', 'App\\Models\\BackupRecord', 4, 'Backup Verified', '4c01b7c9-683f-48e3-b85c-efe109b3c36c', '855b5e7a-9d27-4065-ad16-99bd68d34fe4', 'POST', 'admin.backups.verify', NULL, '{\"filename\":\"imported-2026-09-18_113026-d6be80.mcstbak\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '8873097f43f4f46720ab21e62665f924746a508eb2fd9b9a9dbf0c3cde931275', 'f8a179f3cc3ca5738b286c34c2f4ab9733461def04339df8e87695b5552775f7', '2026-09-18 03:33:07'),
(24, 2, 'Admin MCST', 'admin@mcst.edu.ph', 'admin', 'backup.verified', 'backup', 'verified', 'App\\Models\\BackupRecord', 4, 'Backup Verified', 'd57302fa-b666-4e83-868e-d8336282f09e', 'df5ff866-1478-4b0a-aa51-a589d0594313', 'POST', 'admin.backups.verify', NULL, '{\"filename\":\"imported-2026-09-18_113026-d6be80.mcstbak\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', 'f8a179f3cc3ca5738b286c34c2f4ab9733461def04339df8e87695b5552775f7', 'f966af60e2253dff2507590f06347ac23005f7f1eb9054d276222cf6a9b7ede0', '2026-09-18 03:33:09'),
(25, 2, 'Admin MCST', 'admin@mcst.edu.ph', 'admin', 'backup.verified', 'backup', 'verified', 'App\\Models\\BackupRecord', 4, 'Backup Verified', 'ea0a9fcb-e285-468b-a5e2-cb4563a854e2', '8870f202-a572-43e7-a988-c0bfd556127d', 'POST', 'admin.backups.verify', NULL, '{\"filename\":\"imported-2026-09-18_113026-d6be80.mcstbak\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', 'f966af60e2253dff2507590f06347ac23005f7f1eb9054d276222cf6a9b7ede0', '1378e584459f5af5d2b56a38e63d062c7185f736e4b96a1aa798bf63586026fe', '2026-09-18 03:33:11'),
(26, 2, 'Admin MCST', 'admin@mcst.edu.ph', 'admin', 'backup.verified', 'backup', 'verified', 'App\\Models\\BackupRecord', 4, 'Backup Verified', '4ba257d4-a35f-4514-902c-39ff9c38b803', '8e95001d-7480-43d5-93ee-982730ffffc1', 'POST', 'admin.backups.verify', NULL, '{\"filename\":\"imported-2026-09-18_113026-d6be80.mcstbak\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '1378e584459f5af5d2b56a38e63d062c7185f736e4b96a1aa798bf63586026fe', 'b96ebe7e39eb5b7793fa69e3fd46f95738a95ef90574c14039284a77ac74e56b', '2026-09-18 03:33:14'),
(27, 2, 'Admin MCST', 'admin@mcst.edu.ph', 'admin', 'backup.verified', 'backup', 'verified', 'App\\Models\\BackupRecord', 4, 'Backup Verified', '229367e0-249b-4db0-a882-c621ed035e09', 'bea45dc5-e0cc-4b43-9691-97ae4cc5b19f', 'POST', 'admin.backups.verify', NULL, '{\"filename\":\"imported-2026-09-18_113026-d6be80.mcstbak\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', 'b96ebe7e39eb5b7793fa69e3fd46f95738a95ef90574c14039284a77ac74e56b', 'bb74e0736e8ec7a27bc2f94aa2c7bc5c6d02c9251a2b63c434af924dc5d216c8', '2026-09-18 03:33:15'),
(28, 2, 'Admin MCST', 'admin@mcst.edu.ph', 'admin', 'backup.verified', 'backup', 'verified', 'App\\Models\\BackupRecord', 4, 'Backup Verified', '95ce162b-538e-431e-94a3-c6ff30f87fac', 'c8bc57e7-1fd4-4bf6-9318-d24a846a7520', 'POST', 'admin.backups.verify', NULL, '{\"filename\":\"imported-2026-09-18_113026-d6be80.mcstbak\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', 'bb74e0736e8ec7a27bc2f94aa2c7bc5c6d02c9251a2b63c434af924dc5d216c8', '33eb6fd89d8e76a04f2dfd86aae381f6fbe18b5d8eea44802b200337ccd7dc1b', '2026-09-18 03:33:18'),
(29, 2, 'Admin MCST', 'admin@mcst.edu.ph', 'admin', 'backup.verified', 'backup', 'verified', 'App\\Models\\BackupRecord', 4, 'Backup Verified', '1dc81b25-7fdc-4391-9d72-a97090d3d589', '27f217bb-1d30-4efd-b68d-d60551752aba', 'POST', 'admin.backups.verify', NULL, '{\"filename\":\"imported-2026-09-18_113026-d6be80.mcstbak\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '33eb6fd89d8e76a04f2dfd86aae381f6fbe18b5d8eea44802b200337ccd7dc1b', '9678223df18159c0c0b49e542e610c2aa4edc251a0d1ec6b7b13d49ec1dcd349', '2026-09-18 03:33:22'),
(30, 2, 'Admin MCST', 'admin@mcst.edu.ph', 'admin', 'backup.verified', 'backup', 'verified', 'App\\Models\\BackupRecord', 4, 'Backup Verified', '3ada767f-34e6-457f-b634-8388b4f27054', '86feef91-b141-49f0-a292-aff1dafd0099', 'POST', 'admin.backups.verify', NULL, '{\"filename\":\"imported-2026-09-18_113026-d6be80.mcstbak\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '9678223df18159c0c0b49e542e610c2aa4edc251a0d1ec6b7b13d49ec1dcd349', '50307d4fb91dba4b535914742dd80d6871cd1552301a76134158bbb1ec23f9bb', '2026-09-18 03:33:27'),
(31, 2, 'Admin MCST', 'admin@mcst.edu.ph', 'admin', 'backup.verified', 'backup', 'verified', 'App\\Models\\BackupRecord', 3, 'Backup Verified', '741048a1-a225-452c-bb0d-ca5288fbd1b7', '4f9a3832-9cb4-49d4-aca6-d0f600944045', 'POST', 'admin.backups.verify', NULL, '{\"filename\":\"mcst-gym-2026-09-18_112927-685dd1.mcstbak\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '50307d4fb91dba4b535914742dd80d6871cd1552301a76134158bbb1ec23f9bb', 'f3a0966a467865e9f21dceacf0174c179d9b9a21e862db4a11fd5f7a4fc3be15', '2026-09-18 03:33:29'),
(32, 2, 'Admin MCST', 'admin@mcst.edu.ph', 'admin', 'backup.verified', 'backup', 'verified', 'App\\Models\\BackupRecord', 1, 'Backup Verified', 'ccc9d30b-6967-44b9-874b-0b85186e8d69', 'ecabdf3f-1cb4-4186-a5bc-11970459858c', 'POST', 'admin.backups.verify', NULL, '{\"filename\":\"mcst-gym-2026-09-18_013939-1b6894.mcstbak\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', 'f3a0966a467865e9f21dceacf0174c179d9b9a21e862db4a11fd5f7a4fc3be15', 'f08aac7a112733b48107afe1fd4ebb0ebc458d5137f6023cd1af2bbb09bda75f', '2026-09-18 03:33:31'),
(33, 2, 'Admin MCST', 'admin@mcst.edu.ph', 'admin', 'backup.verified', 'backup', 'verified', 'App\\Models\\BackupRecord', 1, 'Backup Verified', 'f06c1592-7d40-4c40-9727-73a3437d19ac', '75fefe7f-21b8-4369-9e76-5eaaecef85e7', 'POST', 'admin.backups.verify', NULL, '{\"filename\":\"mcst-gym-2026-09-18_013939-1b6894.mcstbak\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', 'f08aac7a112733b48107afe1fd4ebb0ebc458d5137f6023cd1af2bbb09bda75f', '960504e7fd54d30a54520b1cbea0e6e4e361ab51a4a5ce4f9c9139e65096337a', '2026-09-18 03:33:33'),
(34, 2, 'Admin MCST', 'admin@mcst.edu.ph', 'admin', 'user.updated', 'user', 'updated', 'App\\Models\\User', 2, 'User Updated', 'b651a4b1-7979-4aa1-a1e6-daf1af333476', 'e9e10a11-dd8e-4ba8-be1d-28796fc72dc5', 'POST', 'logout', '{\"remember_token\":\"[REDACTED]\"}', '{\"remember_token\":\"[REDACTED]\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '960504e7fd54d30a54520b1cbea0e6e4e361ab51a4a5ce4f9c9139e65096337a', '8ebc7cbeeb777464d361ea681045478b3114d85a5b5402223bf328d5344fb799', '2026-09-18 03:38:44'),
(35, 2, 'Admin MCST', 'admin@mcst.edu.ph', 'admin', 'authentication.logout', 'authentication', 'logout', 'App\\Models\\User', 2, 'Authentication Logout', 'b651a4b1-7979-4aa1-a1e6-daf1af333476', 'e9e10a11-dd8e-4ba8-be1d-28796fc72dc5', 'POST', 'logout', NULL, '{\"guard\":\"web\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '8ebc7cbeeb777464d361ea681045478b3114d85a5b5402223bf328d5344fb799', '5fa157052e7687594b3d4be57c5f9c37be6029dac2239fcba147aa4a6a8c88f8', '2026-09-18 03:38:45'),
(36, 2, 'Admin MCST', 'admin@mcst.edu.ph', 'admin', 'authentication.login', 'authentication', 'login', 'App\\Models\\User', 2, 'Authentication Login', '9870e305-8f57-4e44-963f-e4de3ee65a8b', 'f0726f47-c63e-4f38-ae21-616ba1732cf8', 'POST', 'login', NULL, '{\"guard\":\"web\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '5fa157052e7687594b3d4be57c5f9c37be6029dac2239fcba147aa4a6a8c88f8', '98860b4fbd99efac582b3eeb823643f89e151df7d9116fe0990255c162d3efce', '2026-09-18 03:40:20'),
(37, 2, 'Admin MCST', 'admin@mcst.edu.ph', 'admin', 'authentication.login', 'authentication', 'login', 'App\\Models\\User', 2, 'Authentication Login', '6882bcf5-5e20-439d-9bde-0f659a9dd3e3', 'fad707d6-e47b-4bbd-9dc1-4feac8e7405c', 'POST', 'login', NULL, '{\"guard\":\"web\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '98860b4fbd99efac582b3eeb823643f89e151df7d9116fe0990255c162d3efce', 'dfe55f5e0e49b6ed3b020b961e5f23443adf3892d3b56e766b3e7c71b5691323', '2026-09-23 02:39:41');

-- --------------------------------------------------------

--
-- Table structure for table `backup_records`
--

CREATE TABLE `backup_records` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `filename` varchar(255) NOT NULL,
  `disk` varchar(255) NOT NULL DEFAULT 'local',
  `path` varchar(255) NOT NULL,
  `size_bytes` bigint(20) UNSIGNED NOT NULL,
  `checksum` varchar(64) NOT NULL,
  `table_count` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `row_count` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `file_count` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `status` varchar(30) NOT NULL DEFAULT 'ready',
  `type` varchar(30) NOT NULL DEFAULT 'manual',
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `verified_at` timestamp NULL DEFAULT NULL,
  `restored_at` timestamp NULL DEFAULT NULL,
  `last_error` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `backup_records`
--

INSERT INTO `backup_records` (`id`, `filename`, `disk`, `path`, `size_bytes`, `checksum`, `table_count`, `row_count`, `file_count`, `status`, `type`, `created_by`, `verified_at`, `restored_at`, `last_error`, `created_at`, `updated_at`) VALUES
(1, 'mcst-gym-2026-09-18_013939-1b6894.mcstbak', 'local', 'backups/mcst-gym-2026-09-18_013939-1b6894.mcstbak', 1519732, 'c5001f016adeb44476f0ab26c2b97c3622f1cd7953b955159d61f48fb9451f51', 44, 62, 5, 'ready', 'command', NULL, '2026-09-18 03:33:33', NULL, NULL, '2026-09-17 17:39:40', '2026-09-18 03:33:33'),
(2, 'mcst-gym-2026-09-18_014253-2cff6d.mcstbak', 'local', 'backups/mcst-gym-2026-09-18_014253-2cff6d.mcstbak', 1518624, 'fe4ab9306e09593a5018e3582f0b05ef9044c7ac5e590d9f66018c635d7dac5f', 36, 41, 5, 'ready', 'scheduled', NULL, '2026-09-18 03:32:18', '2026-09-17 17:50:17', NULL, '2026-09-17 17:42:53', '2026-09-18 03:32:18'),
(3, 'mcst-gym-2026-09-18_112927-685dd1.mcstbak', 'local', 'backups/mcst-gym-2026-09-18_112927-685dd1.mcstbak', 1520212, '832647c7becf8861eaa49ff0a272c58c9abc2f7ee8581afaa7ef7b8d0f41d204', 36, 45, 5, 'ready', 'manual', 2, '2026-09-18 03:33:29', NULL, NULL, '2026-09-18 03:29:28', '2026-09-18 03:33:29'),
(4, 'imported-2026-09-18_113026-d6be80.mcstbak', 'local', 'backups/imported-2026-09-18_113026-d6be80.mcstbak', 1520212, '832647c7becf8861eaa49ff0a272c58c9abc2f7ee8581afaa7ef7b8d0f41d204', 36, 45, 5, 'ready', 'imported', 2, '2026-09-18 03:33:27', '2026-09-18 03:31:15', NULL, '2026-09-18 03:30:26', '2026-09-18 03:33:27');

-- --------------------------------------------------------

--
-- Table structure for table `cache`
--

CREATE TABLE `cache` (
  `key` varchar(255) NOT NULL,
  `value` mediumtext NOT NULL,
  `expiration` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cache_locks`
--

CREATE TABLE `cache_locks` (
  `key` varchar(255) NOT NULL,
  `owner` varchar(255) NOT NULL,
  `expiration` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `email_templates`
--

CREATE TABLE `email_templates` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `key` varchar(255) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `body` text NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `equipment`
--

CREATE TABLE `equipment` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `equipment_name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `total_quantity` int(10) UNSIGNED NOT NULL,
  `unit` varchar(50) NOT NULL DEFAULT 'piece',
  `status` varchar(30) NOT NULL DEFAULT 'available',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `equipment`
--

INSERT INTO `equipment` (`id`, `equipment_name`, `description`, `total_quantity`, `unit`, `status`, `created_at`, `updated_at`) VALUES
(4, 'Chairs', 'Stackable event chairs', 100, 'piece', 'available', '2026-08-26 14:41:37', '2026-08-26 14:41:37'),
(5, 'Tables', 'Multipurpose event tables', 20, 'piece', 'available', '2026-08-26 14:41:37', '2026-08-26 14:41:37'),
(6, 'Lighting Equipment', 'Portable event lighting equipment', 10, 'set', 'available', '2026-08-26 14:41:37', '2026-08-26 14:41:37'),
(7, 'Sports Equipment', 'Assorted balls, nets, and training equipment', 25, 'set', 'available', '2026-08-26 14:41:37', '2026-08-26 14:41:37');

-- --------------------------------------------------------

--
-- Table structure for table `facilities`
--

CREATE TABLE `facilities` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `facility_name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `location` varchar(150) DEFAULT NULL,
  `capacity` int(10) UNSIGNED NOT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'available',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `facilities`
--

INSERT INTO `facilities` (`id`, `facility_name`, `description`, `location`, `capacity`, `status`, `created_at`, `updated_at`) VALUES
(1, 'MCST Main Gymnasium', 'Main indoor gymnasium for school and community events', 'Welfareville Compound, Mandaluyong City', 600, 'available', '2026-07-24 18:36:45', '2026-08-01 07:28:47');

-- --------------------------------------------------------

--
-- Table structure for table `failed_jobs`
--

CREATE TABLE `failed_jobs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `uuid` varchar(255) NOT NULL,
  `connection` text NOT NULL,
  `queue` text NOT NULL,
  `payload` longtext NOT NULL,
  `exception` longtext NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `feedback`
--

CREATE TABLE `feedback` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `reservation_id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `rating` tinyint(3) UNSIGNED NOT NULL,
  `comments` text DEFAULT NULL,
  `is_anonymous` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `jobs`
--

CREATE TABLE `jobs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `queue` varchar(255) NOT NULL,
  `payload` longtext NOT NULL,
  `attempts` tinyint(3) UNSIGNED NOT NULL,
  `reserved_at` int(10) UNSIGNED DEFAULT NULL,
  `available_at` int(10) UNSIGNED NOT NULL,
  `created_at` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `job_batches`
--

CREATE TABLE `job_batches` (
  `id` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `total_jobs` int(11) NOT NULL,
  `pending_jobs` int(11) NOT NULL,
  `failed_jobs` int(11) NOT NULL,
  `failed_job_ids` longtext NOT NULL,
  `options` mediumtext DEFAULT NULL,
  `cancelled_at` int(11) DEFAULT NULL,
  `created_at` int(11) NOT NULL,
  `finished_at` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `migrations`
--

CREATE TABLE `migrations` (
  `id` int(10) UNSIGNED NOT NULL,
  `migration` varchar(255) NOT NULL,
  `batch` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `migrations`
--

INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES
(1, '0001_01_01_000000_create_users_table', 1),
(2, '0001_01_01_000001_create_cache_table', 1),
(3, '0001_01_01_000002_create_jobs_table', 1),
(4, '2026_07_21_004242_create_requestor_profiles_table', 1),
(5, '2026_07_21_012329_create_facilities_table', 1),
(6, '2026_07_21_013610_create_reservations_table', 1),
(7, '2026_07_21_013900_create_reservation_status_histories_table', 1),
(8, '2026_07_21_014127_create_reservation_documents_table', 1),
(9, '2026_07_21_014503_create_equipment_table', 1),
(10, '2026_07_21_014801_create_reservation_equipment_table', 1),
(11, '2026_07_21_015009_create_announcements_table', 1),
(12, '2026_07_21_015230_create_feedback_table', 1),
(13, '2026_07_21_015350_create_audit_logs_table', 1),
(14, '2026_07_21_020331_create_notifications_table', 1),
(15, '2026_07_25_120648_update_reservations_for_public_requestors', 2),
(16, '2026_08_01_150000_add_unique_index_to_facilities_name', 3),
(17, '2026_08_03_000000_allow_public_reservation_document_uploads', 4),
(18, '2026_08_24_230000_add_custom_equipment_request_to_reservations', 5),
(19, '2026_08_26_120000_create_analytics_reports_table', 6),
(20, '2026_08_26_130000_add_admin_control_tables', 7),
(21, '2026_09_18_120000_create_backup_records_table', 8),
(22, '2026_09_18_130000_harden_audit_logs', 9),
(23, '2026_09_18_140000_rehash_audit_logs_for_actor_snapshots', 10);

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` char(36) NOT NULL,
  `type` varchar(255) NOT NULL,
  `notifiable_type` varchar(255) NOT NULL,
  `notifiable_id` bigint(20) UNSIGNED NOT NULL,
  `data` text NOT NULL,
  `read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `password_reset_tokens`
--

CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `requestor_profiles`
--

CREATE TABLE `requestor_profiles` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `student_or_employee_number` varchar(50) DEFAULT NULL,
  `organization_name` varchar(255) DEFAULT NULL,
  `department_or_office` varchar(255) DEFAULT NULL,
  `street_address` varchar(255) DEFAULT NULL,
  `barangay` varchar(100) DEFAULT NULL,
  `valid_id_type` varchar(100) DEFAULT NULL,
  `valid_id_number` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `reservations`
--

CREATE TABLE `reservations` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `reference_number` varchar(50) NOT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `facility_id` bigint(20) UNSIGNED NOT NULL,
  `reservation_type` varchar(50) NOT NULL,
  `event_name` varchar(255) NOT NULL,
  `event_type` varchar(100) DEFAULT NULL,
  `purpose` text NOT NULL,
  `contact_person` varchar(255) NOT NULL,
  `contact_email` varchar(255) DEFAULT NULL,
  `contact_number` varchar(30) NOT NULL,
  `expected_attendees` int(10) UNSIGNED NOT NULL,
  `reservation_date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `setup_time` time DEFAULT NULL,
  `cleanup_time` time DEFAULT NULL,
  `requested_equipment` varchar(255) DEFAULT NULL,
  `requested_equipment_quantity` int(10) UNSIGNED DEFAULT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'new',
  `priority_number` int(10) UNSIGNED DEFAULT NULL,
  `admin_notes` text DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `cancellation_reason` text DEFAULT NULL,
  `approved_by` bigint(20) UNSIGNED DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `rejected_by` bigint(20) UNSIGNED DEFAULT NULL,
  `rejected_at` timestamp NULL DEFAULT NULL,
  `cancelled_by` bigint(20) UNSIGNED DEFAULT NULL,
  `cancelled_at` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `reservations`
--

INSERT INTO `reservations` (`id`, `reference_number`, `user_id`, `facility_id`, `reservation_type`, `event_name`, `event_type`, `purpose`, `contact_person`, `contact_email`, `contact_number`, `expected_attendees`, `reservation_date`, `start_time`, `end_time`, `setup_time`, `cleanup_time`, `requested_equipment`, `requested_equipment_quantity`, `status`, `priority_number`, `admin_notes`, `rejection_reason`, `cancellation_reason`, `approved_by`, `approved_at`, `rejected_by`, `rejected_at`, `cancelled_by`, `cancelled_at`, `completed_at`, `created_at`, `updated_at`) VALUES
(1, 'MCST-20260725-FBGDG6', 2, 1, 'school', 'BASKETBALL', 'SPORT', 'FFSDBZZZZZ', 'CHE', NULL, '09462744629', 30, '2026-07-29', '14:00:00', '17:00:00', '13:00:00', '18:00:00', NULL, NULL, 'rejected', NULL, NULL, 'AYOKO', NULL, NULL, NULL, 2, '2026-07-24 21:07:56', NULL, NULL, NULL, '2026-07-24 21:07:30', '2026-07-24 21:07:56'),
(2, 'MCST-20260725-KUQ5CA', 2, 1, 'government', 'GOVERNMENT', 'FINANCIAL', 'MONEY MONEY', 'THEA', NULL, '09462744629', 300, '2026-08-01', '08:00:00', '15:00:00', '07:00:00', '16:00:00', NULL, NULL, 'approved', NULL, NULL, NULL, NULL, 2, '2026-07-25 04:04:12', NULL, NULL, NULL, NULL, NULL, '2026-07-24 21:09:40', '2026-07-25 04:04:12'),
(3, 'MCST-20260801-RGWWQO', NULL, 1, 'student', 'concert ni jimboy', 'Concert', 'kakanta si jimboy', 'Vincent Telesforo', 'telesforovincent024@gmail.com', '09218923116', 800, '2026-08-02', '16:23:00', '16:26:00', '13:18:00', '22:29:00', NULL, NULL, 'cancelled', NULL, NULL, NULL, 'limit exceed', 2, '2026-08-01 08:25:01', NULL, NULL, 2, '2026-08-01 08:26:36', NULL, '2026-08-01 08:24:06', '2026-08-01 08:26:36'),
(4, 'MCST-20260801-ELH8SE', NULL, 1, 'student', 'concert ni jimboy', 'Concert', 'kakanta si jimboy', 'Vincent Telesforo', 'telesforovincent024@gmail.com', '09218923116', 800, '2026-08-02', '16:23:00', '16:26:00', '13:18:00', '22:29:00', NULL, NULL, 'new', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-08-01 08:29:59', '2026-08-01 08:29:59'),
(5, 'MCST-20260801-WMQV1P', 2, 1, 'government', 'GOVERNMENT', 'FINANCIAL', 'financial', 'secretary of mayor', NULL, '09231345876', 700, '2026-08-11', '08:00:00', '17:00:00', '07:00:00', '17:30:00', NULL, NULL, 'rejected', NULL, NULL, 'no slot', NULL, NULL, NULL, 2, '2026-08-01 09:27:28', NULL, NULL, NULL, '2026-08-01 09:26:11', '2026-08-01 09:27:28'),
(6, 'MCST-20260815-6SEGB1', NULL, 1, 'organization', 'BASKETBALL', 'SPORT', 'Sportyy', 'Chelvin Gayle', 'chelvin.alegro@mcst.edu.ph', '09462744629', 100, '2026-08-18', '15:00:00', '21:00:00', NULL, NULL, NULL, NULL, 'new', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-08-15 05:55:20', '2026-08-15 05:55:20'),
(7, 'MCST-20260824-QTYPIW', NULL, 1, 'organization', 'BASKETBALL', 'sports', ',mnvnJHoias', 'CHELVIN ALEGRO', 'chelvin.alegro@mcst.edu.ph', '09462744629', 25, '2026-08-25', '11:29:00', '15:00:00', NULL, NULL, NULL, NULL, 'new', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-08-24 15:25:01', '2026-08-24 15:25:01'),
(8, 'MCST-20260826-STPILB', NULL, 1, 'student', 'sport', 'SPORT', 'ckmsdnfOS:BV', 'CHELVIN ALEGRO', 'chelvin.alegro@mcst.edu.ph', '09462744629', 189, '2026-08-29', '10:00:00', '14:00:00', NULL, NULL, NULL, NULL, 'new', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-08-26 15:02:25', '2026-08-26 15:02:25'),
(9, 'MCST-20260905-VQJYTJ', NULL, 1, 'organization', 'GOVERNMENT', 'FINANCIAL', 'FINANCIAL ASSISTANCE', 'CHELVIN ALEGRO', 'chelvin.alegro@mcst.edu.ph', '09462744629', 196, '2026-09-08', '14:00:00', '17:00:00', NULL, NULL, 'CHAIR', 50, 'approved', NULL, NULL, NULL, NULL, 2, '2026-09-05 10:44:10', NULL, NULL, NULL, NULL, NULL, '2026-09-05 10:42:56', '2026-09-05 10:44:10');

-- --------------------------------------------------------

--
-- Table structure for table `reservation_documents`
--

CREATE TABLE `reservation_documents` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `reservation_id` bigint(20) UNSIGNED NOT NULL,
  `uploaded_by` bigint(20) UNSIGNED DEFAULT NULL,
  `document_type` varchar(50) NOT NULL,
  `original_filename` varchar(255) NOT NULL,
  `stored_filename` varchar(255) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `mime_type` varchar(100) NOT NULL,
  `file_size` bigint(20) UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `reservation_documents`
--

INSERT INTO `reservation_documents` (`id`, `reservation_id`, `uploaded_by`, `document_type`, `original_filename`, `stored_filename`, `file_path`, `mime_type`, `file_size`, `created_at`, `updated_at`) VALUES
(1, 6, NULL, 'permit', '640315217_34743059891974525_8805912437103087903_n.jpg', 'a3f6bbdb-a604-4d42-8830-d245cd1902e3.jpg', 'reservation-documents/MCST-20260815-6SEGB1/a3f6bbdb-a604-4d42-8830-d245cd1902e3.jpg', 'image/jpeg', 108812, '2026-08-15 05:55:20', '2026-08-15 05:55:20'),
(2, 7, NULL, 'permit', 'image-removebg-preview.png', '3b69e833-fcb1-4b1d-b0cd-9e44fa5998b8.png', 'reservation-documents/MCST-20260824-QTYPIW/3b69e833-fcb1-4b1d-b0cd-9e44fa5998b8.png', 'image/png', 221204, '2026-08-24 15:25:01', '2026-08-24 15:25:01'),
(3, 8, NULL, 'permit', 'image-removebg-preview.png', '9bd5a868-b6f9-4dd8-80ae-43be05a0aa9a.png', 'reservation-documents/MCST-20260826-STPILB/9bd5a868-b6f9-4dd8-80ae-43be05a0aa9a.png', 'image/png', 221204, '2026-08-26 15:02:25', '2026-08-26 15:02:25'),
(4, 9, NULL, 'permit', 'Grey Clean CV Resume Photo.pdf (1).pdf', '8136410e-4cba-4975-804a-ce6211036457.pdf', 'reservation-documents/MCST-20260905-VQJYTJ/8136410e-4cba-4975-804a-ce6211036457.pdf', 'application/pdf', 111478, '2026-09-05 10:42:56', '2026-09-05 10:42:56');

-- --------------------------------------------------------

--
-- Table structure for table `reservation_equipment`
--

CREATE TABLE `reservation_equipment` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `reservation_id` bigint(20) UNSIGNED NOT NULL,
  `equipment_id` bigint(20) UNSIGNED NOT NULL,
  `quantity_requested` int(10) UNSIGNED NOT NULL,
  `quantity_approved` int(10) UNSIGNED DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `reservation_status_histories`
--

CREATE TABLE `reservation_status_histories` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `reservation_id` bigint(20) UNSIGNED NOT NULL,
  `changed_by` bigint(20) UNSIGNED DEFAULT NULL,
  `previous_status` varchar(30) DEFAULT NULL,
  `new_status` varchar(30) NOT NULL,
  `remarks` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `reservation_status_histories`
--

INSERT INTO `reservation_status_histories` (`id`, `reservation_id`, `changed_by`, `previous_status`, `new_status`, `remarks`, `created_at`, `updated_at`) VALUES
(1, 1, 2, NULL, 'new', 'Reservation created.', '2026-07-24 21:07:30', '2026-07-24 21:07:30'),
(2, 1, 2, 'new', 'rejected', 'AYOKO', '2026-07-24 21:07:56', '2026-07-24 21:07:56'),
(3, 2, 2, NULL, 'new', 'Reservation created.', '2026-07-24 21:09:40', '2026-07-24 21:09:40'),
(4, 2, 2, 'new', 'approved', 'Reservation approved.', '2026-07-25 04:04:12', '2026-07-25 04:04:12'),
(5, 3, NULL, NULL, 'new', 'Reservation submitted by requestor.', '2026-08-01 08:24:06', '2026-08-01 08:24:06'),
(6, 3, 2, 'new', 'approved', 'Reservation approved.', '2026-08-01 08:25:01', '2026-08-01 08:25:01'),
(7, 3, 2, 'approved', 'cancelled', 'limit exceed', '2026-08-01 08:26:36', '2026-08-01 08:26:36'),
(8, 4, NULL, NULL, 'new', 'Reservation submitted by requestor.', '2026-08-01 08:29:59', '2026-08-01 08:29:59'),
(9, 5, 2, NULL, 'new', 'Reservation created.', '2026-08-01 09:26:11', '2026-08-01 09:26:11'),
(10, 5, 2, 'new', 'rejected', 'no slot', '2026-08-01 09:27:28', '2026-08-01 09:27:28'),
(11, 6, NULL, NULL, 'new', 'Reservation submitted by requestor.', '2026-08-15 05:55:20', '2026-08-15 05:55:20'),
(12, 7, NULL, NULL, 'new', 'Reservation submitted by requestor.', '2026-08-24 15:25:01', '2026-08-24 15:25:01'),
(13, 8, NULL, NULL, 'new', 'Reservation submitted by requestor.', '2026-08-26 15:02:25', '2026-08-26 15:02:25'),
(14, 9, NULL, NULL, 'new', 'Reservation submitted by requestor.', '2026-09-05 10:42:56', '2026-09-05 10:42:56'),
(15, 9, 2, 'new', 'approved', 'Reservation approved.', '2026-09-05 10:44:10', '2026-09-05 10:44:10');

-- --------------------------------------------------------

--
-- Table structure for table `schedule_blocks`
--

CREATE TABLE `schedule_blocks` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `facility_id` bigint(20) UNSIGNED DEFAULT NULL,
  `starts_on` date NOT NULL,
  `ends_on` date NOT NULL,
  `start_time` time DEFAULT NULL,
  `end_time` time DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `reason` text DEFAULT NULL,
  `created_by` bigint(20) UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sessions`
--

CREATE TABLE `sessions` (
  `id` varchar(255) NOT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `payload` longtext NOT NULL,
  `last_activity` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `system_settings`
--

CREATE TABLE `system_settings` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `key` varchar(255) NOT NULL,
  `value` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `email` varchar(255) NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `role` varchar(30) NOT NULL DEFAULT 'requestor',
  `can_priority_override` tinyint(1) NOT NULL DEFAULT 0,
  `requestor_category` varchar(50) DEFAULT NULL,
  `contact_number` varchar(11) DEFAULT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'pending',
  `remember_token` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `first_name`, `last_name`, `email`, `email_verified_at`, `password`, `role`, `can_priority_override`, `requestor_category`, `contact_number`, `status`, `remember_token`, `created_at`, `updated_at`) VALUES
(1, 'Test', 'Account', 'test.account@gmail.com', NULL, '$2y$12$lQIAypeEkQZ8Kus.SbLArep4uCEWkrmlbSHCunUP2jtkt.cCRib52', 'requestor', 0, 'student', '09462744629', 'pending', 'HzD3pX3LetAgm7uyL7e9CRCMBsgilgEY62dkknu78TJgTi22POeTyi3p6o1p', '2026-07-21 07:19:31', '2026-07-21 07:19:31'),
(2, 'Admin', 'MCST', 'admin@mcst.edu.ph', '2026-07-21 18:52:17', '$2y$12$Qnz3LHkzCFtW.cMU7YDcf.Vhw4pW29vHGUaEiyU/4jKIzjdEO8BD.', 'admin', 0, NULL, NULL, 'approved', 'clHrKMNVK7EvV5KSYOVyejwzUUJYgWqIsrhEA9NWEExDcWQwucY2weHEWXFa', NULL, '2026-07-21 18:52:29'),
(3, 'Chelvin Gayle', 'Alegro', 'chelvin.alegro@mcst.edu.ph', NULL, '$2y$12$9e.eou/TWEKYnFHcipxPOOmNu8qmTNyzvhJTvAkI/TXm5b7nsFBSm', 'staff', 0, NULL, '09462744629', 'pending', NULL, '2026-07-24 16:45:45', '2026-07-24 16:45:45'),
(4, 'Staff', 'Account', 'staff@mcst.edu.ph', '2026-07-24 17:17:28', '$2y$12$aKT8grnu/IDuPZpfeL8HqO/cbt7yOP9jD9gyTo/uR1vVqVNoy95AK', 'staff', 0, NULL, '09462744629', 'approved', NULL, '2026-07-24 16:51:06', '2026-07-24 17:18:07'),
(5, 'Severino', 'Bedis', 'bedis@gmail.com.ph', NULL, '$2y$12$zaSr2W3iY1B5Ssk1WSQnk.r2Y7Z0LtzUKedFTYIR7P.xqXDyyOwPC', 'staff', 0, NULL, '09462744629', 'approved', NULL, '2026-07-25 07:15:03', '2026-07-25 07:15:03');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `analytics_reports`
--
ALTER TABLE `analytics_reports`
  ADD PRIMARY KEY (`id`),
  ADD KEY `analytics_reports_submitted_by_foreign` (`submitted_by`),
  ADD KEY `analytics_reports_report_type_submitted_at_index` (`report_type`,`submitted_at`);

--
-- Indexes for table `announcements`
--
ALTER TABLE `announcements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `announcements_created_by_foreign` (`created_by`),
  ADD KEY `announcements_visibility_index` (`status`,`published_at`,`expires_at`),
  ADD KEY `announcements_audience_index` (`audience`),
  ADD KEY `announcements_status_index` (`status`);

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `audit_logs_entry_hash_unique` (`entry_hash`),
  ADD KEY `audit_logs_auditable_index` (`auditable_type`,`auditable_id`),
  ADD KEY `audit_logs_user_date_index` (`user_id`,`created_at`),
  ADD KEY `audit_logs_action_index` (`action`),
  ADD KEY `audit_logs_module_index` (`module`),
  ADD KEY `audit_logs_event_index` (`event`),
  ADD KEY `audit_logs_transaction_id_index` (`transaction_id`),
  ADD KEY `audit_logs_request_id_index` (`request_id`);

--
-- Indexes for table `backup_records`
--
ALTER TABLE `backup_records`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `backup_records_filename_unique` (`filename`),
  ADD KEY `backup_records_created_by_foreign` (`created_by`),
  ADD KEY `backup_records_status_index` (`status`);

--
-- Indexes for table `cache`
--
ALTER TABLE `cache`
  ADD PRIMARY KEY (`key`);

--
-- Indexes for table `cache_locks`
--
ALTER TABLE `cache_locks`
  ADD PRIMARY KEY (`key`);

--
-- Indexes for table `email_templates`
--
ALTER TABLE `email_templates`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email_templates_key_unique` (`key`);

--
-- Indexes for table `equipment`
--
ALTER TABLE `equipment`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `equipment_equipment_name_unique` (`equipment_name`),
  ADD KEY `equipment_status_index` (`status`);

--
-- Indexes for table `facilities`
--
ALTER TABLE `facilities`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `facilities_facility_name_unique` (`facility_name`),
  ADD KEY `facilities_status_index` (`status`);

--
-- Indexes for table `failed_jobs`
--
ALTER TABLE `failed_jobs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`);

--
-- Indexes for table `feedback`
--
ALTER TABLE `feedback`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `feedback_unique_reservation_user` (`reservation_id`,`user_id`),
  ADD KEY `feedback_user_id_foreign` (`user_id`),
  ADD KEY `feedback_rating_index` (`rating`,`created_at`);

--
-- Indexes for table `jobs`
--
ALTER TABLE `jobs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `jobs_queue_index` (`queue`);

--
-- Indexes for table `job_batches`
--
ALTER TABLE `job_batches`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `migrations`
--
ALTER TABLE `migrations`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `notifications_notifiable_type_notifiable_id_index` (`notifiable_type`,`notifiable_id`);

--
-- Indexes for table `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  ADD PRIMARY KEY (`email`);

--
-- Indexes for table `requestor_profiles`
--
ALTER TABLE `requestor_profiles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `requestor_profiles_user_id_unique` (`user_id`);

--
-- Indexes for table `reservations`
--
ALTER TABLE `reservations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `reservations_reference_number_unique` (`reference_number`),
  ADD KEY `reservations_approved_by_foreign` (`approved_by`),
  ADD KEY `reservations_rejected_by_foreign` (`rejected_by`),
  ADD KEY `reservations_cancelled_by_foreign` (`cancelled_by`),
  ADD KEY `reservations_schedule_index` (`facility_id`,`reservation_date`,`start_time`,`end_time`),
  ADD KEY `reservations_date_status_index` (`reservation_date`,`status`),
  ADD KEY `reservations_reservation_type_index` (`reservation_type`),
  ADD KEY `reservations_reservation_date_index` (`reservation_date`),
  ADD KEY `reservations_status_index` (`status`),
  ADD KEY `reservations_user_id_foreign` (`user_id`);

--
-- Indexes for table `reservation_documents`
--
ALTER TABLE `reservation_documents`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `reservation_documents_stored_filename_unique` (`stored_filename`),
  ADD KEY `reservation_documents_uploaded_by_foreign` (`uploaded_by`),
  ADD KEY `reservation_documents_index` (`reservation_id`,`document_type`);

--
-- Indexes for table `reservation_equipment`
--
ALTER TABLE `reservation_equipment`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `reservation_equipment_unique` (`reservation_id`,`equipment_id`),
  ADD KEY `reservation_equipment_lookup_index` (`equipment_id`,`reservation_id`);

--
-- Indexes for table `reservation_status_histories`
--
ALTER TABLE `reservation_status_histories`
  ADD PRIMARY KEY (`id`),
  ADD KEY `reservation_status_histories_changed_by_foreign` (`changed_by`),
  ADD KEY `reservation_status_history_index` (`reservation_id`,`created_at`);

--
-- Indexes for table `schedule_blocks`
--
ALTER TABLE `schedule_blocks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `schedule_blocks_facility_id_foreign` (`facility_id`),
  ADD KEY `schedule_blocks_created_by_foreign` (`created_by`),
  ADD KEY `schedule_blocks_starts_on_ends_on_index` (`starts_on`,`ends_on`);

--
-- Indexes for table `sessions`
--
ALTER TABLE `sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sessions_user_id_index` (`user_id`),
  ADD KEY `sessions_last_activity_index` (`last_activity`);

--
-- Indexes for table `system_settings`
--
ALTER TABLE `system_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `system_settings_key_unique` (`key`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `users_email_unique` (`email`),
  ADD KEY `users_role_index` (`role`),
  ADD KEY `users_requestor_category_index` (`requestor_category`),
  ADD KEY `users_status_index` (`status`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `analytics_reports`
--
ALTER TABLE `analytics_reports`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `announcements`
--
ALTER TABLE `announcements`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=38;

--
-- AUTO_INCREMENT for table `backup_records`
--
ALTER TABLE `backup_records`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `email_templates`
--
ALTER TABLE `email_templates`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `equipment`
--
ALTER TABLE `equipment`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `facilities`
--
ALTER TABLE `facilities`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `failed_jobs`
--
ALTER TABLE `failed_jobs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `feedback`
--
ALTER TABLE `feedback`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `jobs`
--
ALTER TABLE `jobs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `migrations`
--
ALTER TABLE `migrations`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `requestor_profiles`
--
ALTER TABLE `requestor_profiles`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `reservations`
--
ALTER TABLE `reservations`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `reservation_documents`
--
ALTER TABLE `reservation_documents`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `reservation_equipment`
--
ALTER TABLE `reservation_equipment`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `reservation_status_histories`
--
ALTER TABLE `reservation_status_histories`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `schedule_blocks`
--
ALTER TABLE `schedule_blocks`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `system_settings`
--
ALTER TABLE `system_settings`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `analytics_reports`
--
ALTER TABLE `analytics_reports`
  ADD CONSTRAINT `analytics_reports_submitted_by_foreign` FOREIGN KEY (`submitted_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `announcements`
--
ALTER TABLE `announcements`
  ADD CONSTRAINT `announcements_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD CONSTRAINT `audit_logs_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `backup_records`
--
ALTER TABLE `backup_records`
  ADD CONSTRAINT `backup_records_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `feedback`
--
ALTER TABLE `feedback`
  ADD CONSTRAINT `feedback_reservation_id_foreign` FOREIGN KEY (`reservation_id`) REFERENCES `reservations` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `feedback_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `requestor_profiles`
--
ALTER TABLE `requestor_profiles`
  ADD CONSTRAINT `requestor_profiles_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `reservations`
--
ALTER TABLE `reservations`
  ADD CONSTRAINT `reservations_approved_by_foreign` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `reservations_cancelled_by_foreign` FOREIGN KEY (`cancelled_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `reservations_facility_id_foreign` FOREIGN KEY (`facility_id`) REFERENCES `facilities` (`id`),
  ADD CONSTRAINT `reservations_rejected_by_foreign` FOREIGN KEY (`rejected_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `reservations_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `reservation_documents`
--
ALTER TABLE `reservation_documents`
  ADD CONSTRAINT `reservation_documents_reservation_id_foreign` FOREIGN KEY (`reservation_id`) REFERENCES `reservations` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `reservation_documents_uploaded_by_foreign` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `reservation_equipment`
--
ALTER TABLE `reservation_equipment`
  ADD CONSTRAINT `reservation_equipment_equipment_id_foreign` FOREIGN KEY (`equipment_id`) REFERENCES `equipment` (`id`),
  ADD CONSTRAINT `reservation_equipment_reservation_id_foreign` FOREIGN KEY (`reservation_id`) REFERENCES `reservations` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `reservation_status_histories`
--
ALTER TABLE `reservation_status_histories`
  ADD CONSTRAINT `reservation_status_histories_changed_by_foreign` FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `reservation_status_histories_reservation_id_foreign` FOREIGN KEY (`reservation_id`) REFERENCES `reservations` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `schedule_blocks`
--
ALTER TABLE `schedule_blocks`
  ADD CONSTRAINT `schedule_blocks_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `schedule_blocks_facility_id_foreign` FOREIGN KEY (`facility_id`) REFERENCES `facilities` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
