-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 20, 2026 at 09:25 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `task_management_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `companies`
--

CREATE TABLE `companies` (
  `comp_id` int(11) NOT NULL,
  `comp_name` varchar(50) NOT NULL,
  `mobile_no` varchar(20) DEFAULT NULL,
  `messenger_link` varchar(255) DEFAULT NULL,
  `comp_address` varchar(50) NOT NULL,
  `tin` varchar(255) NOT NULL,
  `business_type` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `companies`
--

INSERT INTO `companies` (`comp_id`, `comp_name`, `mobile_no`, `messenger_link`, `comp_address`, `tin`, `business_type`, `created_at`) VALUES
(1, 'Mabuhayone Corporation', NULL, NULL, 'Plaza Nova Bldg, Santiago Blvd, GSC', '298-506-622-000', 'Other Business Activities, N.E.C', '2024-08-27 23:10:04'),
(2, 'Honda Cars General Santos, INC.', NULL, 'https://www.facebook.com/messages/t/1278316510447179', 'J Catolico Ave, Lagao, GSC', '005-134-268-000', 'Sale and Repair of Motor Vigicles', '2024-08-27 23:10:40'),
(3, 'Peoples Rural Bank', NULL, 'https://www.facebook.com/messages/t/1259940589592355', 'Plaza Nova Bldg, Santiago Blvd, GSC', '005-135-590-000', 'Banking', '2024-08-29 09:11:21'),
(4, 'ECA Resources, Inc./ECA building', NULL, 'https://www.facebook.com/messages/t/2111978712992994', 'National Highway, City Heights, GSC', '006-623-611-000', 'Leasing', '2026-03-17 03:04:08'),
(5, 'Buildtolast Corporation', NULL, 'https://www.facebook.com/messages/t/1932016250770549', 'SAIP, National Highway, Polomolok', '010-114-555-000', 'Wholesale of Construction', '2026-03-17 03:04:09'),
(6, 'Mabuhay Technopark Corporation', NULL, 'https://www.facebook.com/messages/t/6726882334044034', 'National Highway, Lagao (1st & 3rd), GSC', '006-294-594-000', 'Leasing', '2026-03-17 03:04:08'),
(7, 'ECA Resources, INC./Cold Store Plus', NULL, 'https://www.facebook.com/messages/t/26731477889871599', 'Banisil, Brgy. Tambler', '000-623-611-001', 'Cold Storage', '2026-03-17 03:04:11'),
(8, 'Mincorn Corporation', NULL, 'https://www.facebook.com/messages/t/3335369286623713', 'Plaza Nova Bldg, Dad South, GSC', '777-399-279-000', 'Corn Milling', '0000-00-00 00:00:00'),
(9, 'Dalan Mindanao (A Foundatio), INC.', NULL, NULL, 'Plaza Nova Bldg, Dad South, GSC', '005-977-139-000', 'Corn Milling', '0000-00-00 00:00:00'),
(10, 'Ecalands Corporation', NULL, NULL, 'ECA Bldg, National Highway, GSC', '483-594-409-000', 'Real Estate Buyinng, Developing, Subdividing, Selleng', '0000-00-00 00:00:00');

-- --------------------------------------------------------

--
-- Table structure for table `computers`
--

CREATE TABLE `computers` (
  `computer_id` int(11) NOT NULL,
  `tag_no` varchar(50) NOT NULL,
  `computer_name` varchar(100) DEFAULT NULL,
  `company_name` varchar(100) DEFAULT NULL,
  `location` varchar(100) DEFAULT NULL,
  `device_type` enum('Desktop','Laptop','All-in-One') NOT NULL,
  `cpu_brand` varchar(50) DEFAULT NULL,
  `cpu_model` varchar(100) DEFAULT NULL,
  `cpu_serial` varchar(100) DEFAULT NULL,
  `processor` varchar(100) DEFAULT NULL,
  `ram` varchar(50) DEFAULT NULL,
  `storage` varchar(100) DEFAULT NULL,
  `graphics` varchar(100) DEFAULT NULL,
  `operating_system` varchar(100) DEFAULT NULL,
  `monitor_brand` varchar(50) DEFAULT NULL,
  `monitor_model` varchar(100) DEFAULT NULL,
  `monitor_serial` varchar(100) DEFAULT NULL,
  `monitor_size` varchar(20) DEFAULT NULL,
  `ups_brand` varchar(50) DEFAULT NULL,
  `ups_model` varchar(100) DEFAULT NULL,
  `ups_serial` varchar(100) DEFAULT NULL,
  `ups_battery_date_replace` varchar(20) DEFAULT NULL,
  `ip_address` varchar(50) DEFAULT NULL,
  `mac_address` varchar(50) DEFAULT NULL,
  `anydesk_id` varchar(50) DEFAULT NULL,
  `anydesk_password` varchar(50) DEFAULT NULL,
  `purchase_date` date DEFAULT NULL,
  `warranty_expiry` date DEFAULT NULL,
  `supplier` varchar(100) DEFAULT NULL,
  `purchase_price` decimal(10,2) DEFAULT NULL,
  `status` enum('Active','Available','Under Repair','Disposed') DEFAULT 'Active',
  `remarks` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `leave_requests`
--

CREATE TABLE `leave_requests` (
  `leave_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(11) NOT NULL,
  `leave_type` varchar(50) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `reason` text DEFAULT NULL,
  `status` enum('Pending','Approved','Rejected','Cancelled') NOT NULL DEFAULT 'Pending',
  `reviewed_by` int(11) DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `review_remarks` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `leave_requests`
--

INSERT INTO `leave_requests` (`leave_id`, `user_id`, `leave_type`, `start_date`, `end_date`, `reason`, `status`, `reviewed_by`, `reviewed_at`, `review_remarks`, `created_at`, `updated_at`) VALUES
(2, 2, 'Sick Leave', '2026-11-05', '2026-11-05', 'Medical appointment', 'Approved', 2, '2026-10-29 08:49:52', 'Approved for the requested date', '2026-10-29 00:49:52', '2026-10-29 00:49:52'),
(3, 2, 'Vacation Leave', '2026-12-12', '2026-12-12', 'Personal emergency', 'Approved', NULL, '2026-11-30 08:49:52', 'Approved for the requested date', '2026-10-29 00:49:52', '2026-11-11 02:13:41'),
(5, 7, 'Vacation Leave', '2026-12-10', '2026-12-20', 'Vacation', 'Pending', 0, '2026-11-29 08:50:50', 'Approved for the requested date', '2026-10-29 00:50:50', '2026-11-12 02:59:19');

-- --------------------------------------------------------

--
-- Table structure for table `logs`
--

CREATE TABLE `logs` (
  `log_id` int(11) NOT NULL,
  `activity` varchar(50) NOT NULL,
  `user` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `notif_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `recipient` int(11) NOT NULL,
  `type` varchar(50) NOT NULL,
  `date` date NOT NULL DEFAULT current_timestamp(),
  `is_read` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`notif_id`, `message`, `recipient`, `type`, `date`, `is_read`) VALUES
(39, '\'Monitor Replacement Request\' has been assigned to you. Please review and start working on it', 8, 'New Task Assigned', '2026-05-19', 0),
(40, '\'Network Cable Fixing\' has been assigned to you. Please review and start working on it', 8, 'New Task Assigned', '2026-05-19', 0),
(41, '\'Backup Email\' has been assigned to you. Please review and start working on it', 8, 'New Task Assigned', '2026-05-19', 0),
(42, '\'ECCO Server Installation Assistance â€“ Windows Server 2012 R2\' has been assigned to you. Please review and start working on it', 7, 'New Task Assigned', '2026-05-20', 1),
(43, '\'Slow Internet Connection\' has been assigned to you. Please review and start working on it', 7, 'New Task Assigned', '2026-05-20', 1),
(44, '\'Epson l120 not turning on\' has been assigned to you. Please review and start working on it', 7, 'New Task Assigned', '2026-05-22', 1),
(45, '\'NO CONNECTION SAIP\' has been assigned to you. Please review and start working on it', 2, 'New Task Assigned', '2026-05-22', 1),
(46, '\'no power of computer\' has been assigned to you. Please review and start working on it', 8, 'New Task Assigned', '2026-05-22', 0),
(48, '\'HR Request Network on New Office\' has been assigned to you. Please review and start working on it', 7, 'New Task Assigned', '2026-05-25', 0),
(49, '\'Xero Sites cant access Sales invoice page\' has been assigned to you. Please review and start working on it', 2, 'New Task Assigned', '2026-05-25', 0),
(50, '\'Zero Accounting - Invoice\' has been assigned to you. Please review and start working on it', 2, 'New Task Assigned', '2026-05-25', 0),
(51, '\'Restore Deleted Data\' has been assigned to you. Please review and start working on it', 2, 'New Task Assigned', '2026-05-25', 0),
(52, '\'Service Laptop Problem\' has been assigned to you. Please review and start working on it', 7, 'New Task Assigned', '2026-05-26', 0),
(53, '\'NO ACCESS TO INVOICE PAGE\' has been assigned to you. Please review and start working on it', 7, 'New Task Assigned', '2026-05-26', 0),
(54, '\'DATA TRANSFER\' has been assigned to you. Please review and start working on it', 7, 'New Task Assigned', '2026-05-26', 0),
(55, '\'UPS Battery Replacement\' has been assigned to you. Please review and start working on it', 7, 'New Task Assigned', '2026-05-26', 0),
(56, '\'New Laptop Setup\' has been assigned to you. Please review and start working on it', 7, 'New Task Assigned', '2026-05-26', 0),
(57, '\'Epson L120\' has been assigned to you. Please review and start working on it', 7, 'New Task Assigned', '2026-05-28', 0),
(58, '\'123\' has been assigned to you. Please review and start working on it', 2, 'New Task Assigned', '2026-05-29', 0),
(59, '\'123\' has been assigned to you. Please review and start working on it', 7, 'New Task Assigned', '2026-05-29', 0),
(60, '\'123\' has been assigned to you. Please review and start working on it', 8, 'New Task Assigned', '2026-05-29', 0),
(61, '\'Data Recovery\' has been assigned to you. Please review and start working on it', 7, 'New Task Assigned', '2026-05-29', 0),
(62, '\'No internet connection\' has been assigned to you. Please review and start working on it', 7, 'New Task Assigned', '2026-05-29', 0),
(63, '\'NEW CCTV INSTALLATION\' has been assigned to you. Please review and start working on it', 2, 'New Task Assigned', '2026-05-30', 0),
(64, '\'TRANSFER COMPUTER\' has been assigned to you. Please review and start working on it', 7, 'New Task Assigned', '2026-06-23', 0);

-- --------------------------------------------------------

--
-- Table structure for table `tasks`
--

CREATE TABLE `tasks` (
  `task_id` int(11) NOT NULL,
  `report_id` int(11) NOT NULL,
  `category` varchar(100) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `priority` varchar(20) NOT NULL DEFAULT 'Medium',
  `company_id` int(11) NOT NULL,
  `requested_by` varchar(150) NOT NULL,
  `requester_no` varchar(30) NOT NULL,
  `assigned_id` int(11) DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'Pending',
  `attachment_path` varchar(255) DEFAULT NULL,
  `requester_ipadd` varchar(45) DEFAULT NULL,
  `device_info` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `assigned_to` int(11) DEFAULT NULL,
  `diagnosis` text DEFAULT NULL,
  `action_taken` text DEFAULT NULL,
  `recommendation` text DEFAULT NULL
) ENGINE=InnoDB AVG_ROW_LENGTH=936 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tasks`
--

INSERT INTO `tasks` (`task_id`, `report_id`, `category`, `title`, `description`, `priority`, `company_id`, `requested_by`, `requester_no`, `assigned_id`, `due_date`, `status`, `attachment_path`, `requester_ipadd`, `device_info`, `created_at`, `updated_at`, `assigned_to`, `diagnosis`, `action_taken`, `recommendation`) VALUES
(50, 100986, 'Hardware', 'Monitor Replacement ', 'The monitor display is fading and affecting visibility during work operations. Requesting replacement of the defective monitor to restore proper display and productivity.', 'Medium', 3, 'NOVA', '', 7, '2026-05-19', 'Completed', NULL, '150.150.150.243', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-19 00:19:35', '2026-11-18 02:47:25', 8, NULL, NULL, NULL),
(51, 100992, 'Network', 'Network Cable Fixing', '-FOLLOWUP JOB ON NETWORK DATED APR 27 202\r\n- DIAGNOSED AN INTERNET ISSUDE FROM MAINLINE RESOLVED ON THAT DAY ALSO', 'Medium', 5, 'Aida tagalog', '', 3, '2026-05-19', 'Completed', NULL, '150.150.150.157', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Mobile Safari/537.36', '2026-05-19 00:24:12', '2026-11-18 02:47:22', 8, NULL, NULL, NULL),
(52, 100993, 'Email', 'Backup Email', 'Storage is already full need to backup', 'Medium', 7, 'ELDGER DAVE BUTT', '', 4, '2026-05-19', 'Completed', NULL, '150.150.150.130', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/109.0.0.0 Safari/537.36', '2026-05-19 05:45:18', '2026-11-18 02:47:19', 8, NULL, NULL, NULL),
(53, 100994, 'Server', 'ECCO Server Installa', 'Requesting IT assistance for the installation and setup of the ECCO server using Windows Server 2012 R2. Support is needed to ensure proper installation, configuration, and functionality of the server environment.', 'Medium', 8, 'Steve Parker', '', 7, '2026-05-21', 'Completed', NULL, '150.150.150.243', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-20 01:15:04', '2026-11-13 01:12:39', 7, '', NULL, ''),
(54, 100995, 'Internet', 'Slow Internet Connec', 'Cant Access Chat GPT', 'Medium', 3, 'GERELYN CALAMBRO', '', 7, '2026-05-20', 'Completed', NULL, '150.150.150.186', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', '2026-05-20 08:45:40', '2026-11-18 02:47:15', 7, NULL, NULL, NULL),
(55, 100996, 'Printer', 'Epson l120 not turni', '', 'Medium', 2, 'Valmike', '', 7, '2026-05-30', 'Completed', NULL, '150.150.150.157', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Mobile Safari/537.36', '2026-05-22 09:08:55', '2026-11-13 01:12:48', 7, '', NULL, ''),
(56, 100997, 'Internet', 'NO CONNECTION SAIP', 'SAKA TOWER KAPOI JEPOI', 'Medium', 1, 'jan', '', 5, '0000-00-00', 'Completed', NULL, '150.150.150.243', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-22 09:13:33', '2026-11-18 02:47:10', 2, NULL, NULL, NULL),
(57, 100998, 'Hardware', 'no power of computer', 'suddenly turn-off its power', 'Medium', 2, 'jerel vaz', '', 7, '2026-05-26', 'Completed', NULL, '150.150.150.184', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-22 09:23:59', '2026-11-09 23:44:11', 8, '', NULL, ''),
(59, 101000, 'Internet', 'HR Request Network o', 'For internet connection preparation', 'Medium', 7, 'Jerel Velasco', '09072943742', 1, '2026-05-25', 'Completed', NULL, '150.150.150.243', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-25 06:13:26', '2026-11-18 02:47:07', 7, NULL, NULL, NULL),
(60, 101001, 'Cloud / System', 'Xero Sites cant acce', '', 'Medium', 8, 'Marilyn Ramos', '09072943742', 8, '2026-05-25', 'Completed', NULL, '150.150.150.243', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-25 06:15:24', '2026-11-18 02:47:02', 2, NULL, NULL, NULL),
(61, 101002, 'Cloud / System', 'Zero Accounting - In', 'Cant access invoice menu', 'Medium', 4, 'Marylin Ramos', '09224555861', 2, '2026-05-26', 'Completed', NULL, '150.150.150.242', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-25 06:20:59', '2026-11-18 02:46:58', 2, NULL, NULL, NULL),
(63, 101003, 'Backup / Data', 'Restore Deleted Data', 'Restore Deleted Data from Shared Services Folder', 'Medium', 7, 'Gerelyn Calambro', '09703918874', 2, '2026-05-25', 'Completed', NULL, '150.150.150.243', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-25 06:26:46', '2026-11-18 02:46:19', 2, NULL, NULL, NULL),
(64, 101004, 'Laptop', 'Service Laptop Probl', 'Laptop screen is flickering, Some keyboard keys are not responding, No Wifi and Slow Performance / Lag', 'Medium', 2, 'Kate Lape', '09325478098', 6, '2026-05-30', 'Completed', NULL, '150.150.150.243', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-26 00:30:25', '2026-11-18 02:46:17', 7, NULL, NULL, NULL),
(65, 101005, 'Cloud / System', 'NO ACCESS TO INVOICE', 'NO ACCESS TO INVOICE PAGE', 'Medium', 8, 'MARY GRACE PERALES', '09487110376', 1, '2026-05-26', 'Completed', NULL, '150.150.150.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/109.0.0.0 Safari/537.36', '2026-05-26 03:42:12', '2026-11-18 02:46:14', 7, NULL, NULL, NULL),
(66, 101006, 'Backup / Data', 'DATA TRANSFER', 'DATA TRANSFER', 'Medium', 1, 'MARY GRACE PERALES', '09487110376', 9, '2026-05-26', 'Completed', NULL, '150.150.150.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/109.0.0.0 Safari/537.36', '2026-05-26 03:43:05', '2026-11-18 02:46:08', 7, NULL, NULL, NULL),
(67, 101007, 'Hardware', 'UPS Battery Replacem', 'UPS Batteries replaced 7 pcs', 'Medium', 4, 'Marivic Fuentes', '09072943742', 8, '2026-05-29', 'Completed', NULL, '150.150.150.243', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-26 08:51:13', '2026-11-18 02:45:57', 7, NULL, NULL, NULL),
(68, 101008, 'Laptop', 'New Laptop Setup', 'Request list:\r\n Thunderbird, Nitro, Reinstall MS Office and also backup Data', 'Medium', 2, 'Cecil San Pedro', '09072943742', 7, '2026-05-28', 'Completed', NULL, '150.150.150.243', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-26 08:53:28', '2026-11-12 07:11:10', 7, '', NULL, ''),
(69, 101009, 'Printer', 'Epson L120', 'Cannot Connect to other units', 'Medium', 3, 'Noriel', '09072943742', 3, '2026-05-29', 'Completed', NULL, '150.150.150.243', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-05-28 07:26:40', '2026-11-05 01:12:50', 7, NULL, NULL, NULL),
(76, 101013, 'Hardware', 'TRANSFER COMPUTER', 'FAO OFFICE REQUEST TRANSFER TO MITP', 'Medium', 1, 'Marilyn Ramos', '09072943742', 7, '2026-06-23', 'Completed', NULL, '150.150.150.243', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-23 06:56:06', '2026-11-05 01:12:46', NULL, NULL, NULL, NULL),
(78, 101014, 'Software', 'Quickbooks', 'Request Installation Quickbooks 2014', 'Medium', 1, 'Marilyn Ramos', '09090909090', 7, '2026-10-28', 'Completed', NULL, '150.150.150.243', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36', '2026-10-27 07:20:39', '2026-11-05 01:12:46', NULL, NULL, NULL, NULL),
(82, 101018, 'Software', 'Quickbooks', 'Request to install QB 2014', 'Urgent', 1, 'Jaime Segador', '09072943742', 8, '2026-10-29', 'Completed', NULL, '150.150.150.243', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36', '2026-10-28 01:59:36', '2026-11-05 00:50:11', NULL, NULL, NULL, NULL),
(83, 101019, 'Others', 'TRANSPO', 'PAHATUD KO SA MITP SI JAIMES BII', 'Urgent', 1, 'NIPA', '09072943742', 2, '2026-10-29', 'Completed', NULL, '150.150.150.243', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36', '2026-10-28 02:06:49', '2026-11-06 03:16:40', NULL, NULL, NULL, NULL),
(84, 101020, 'Printer', 'Printer Connection', 'Printer Connection', 'Urgent', 1, 'Marilyn Ramos', '09508909347', 8, NULL, 'Completed', NULL, '150.150.150.212', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36', '2026-11-03 06:43:58', '2026-11-05 00:49:38', NULL, NULL, NULL, NULL),
(85, 101021, 'Internet', 'Internet connection', 'Internet connection on FAO heads\' cellphone units', 'Urgent', 1, 'Marilyn Ramos', '09508909347', 8, NULL, 'Completed', NULL, '150.150.150.212', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36', '2026-11-04 03:18:35', '2026-11-06 03:16:36', NULL, NULL, NULL, NULL),
(87, 101022, 'Hardware', 'SSD Installation', 'Request to install new SSD for HDD and PC reformat and install new program', 'Medium', 5, 'Christian', '09090909090', 7, '2026-11-06', 'Completed', NULL, '150.150.150.243', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36', '2026-11-05 04:19:51', '2026-11-05 04:22:09', NULL, NULL, NULL, NULL),
(88, 101023, 'Others', 'Laptop Setup', 'Install new apps and HDS for new laptop', 'Medium', 2, 'Kate Lape', '09090909090', 2, '2026-11-05', 'Completed', NULL, '150.150.150.243', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36', '2026-11-05 04:21:02', '2026-11-05 04:22:30', NULL, NULL, NULL, NULL),
(89, 101024, 'Software', 'UPDATE HDS', 'Update HDS into 1.014 version', 'Low', 2, 'Kate Lape', '09090909090', 7, '2026-11-14', 'Completed', NULL, '150.150.150.243', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36', '2026-11-05 04:23:39', '2026-11-12 07:11:26', NULL, '', NULL, ''),
(90, 101025, 'Wi-Fi', 'Request Wifi', 'Requesting Wifi Connection at new HRA Office', 'Urgent', 1, 'Jerel Velasco', '09090909090', 2, '2026-11-06', 'Completed', NULL, '150.150.150.243', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36', '2026-11-06 01:46:38', '2026-11-06 01:48:16', NULL, NULL, NULL, NULL),
(91, 101026, 'Printer', 'MagConnect ko sa printer ni maam joy', 'pa connect lang ko wala pa ko ka connect sa ilahang printer', 'Low', 1, 'Mosquera, Jervin', '09911111111', NULL, '2026-11-13', 'Completed', NULL, '150.150.150.89', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36', '2026-11-06 02:47:52', '2026-11-12 01:26:49', NULL, NULL, NULL, NULL),
(93, 101027, 'Printer', 'network printer', 'please connect me to maam joy\'s printer. tnx', 'Medium', 1, 'Santulio, Chona', '09222222222', 8, NULL, 'Completed', NULL, '150.150.150.89', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36', '2026-11-06 02:58:29', '2026-11-06 03:02:03', NULL, NULL, NULL, NULL),
(94, 101028, 'Printer', 'Workforce printer-LAN cabling', 'LAN cabling installation for the Workforce printer', 'Urgent', 1, 'Marilyn Ramos', '09508909347', 7, '2026-12-11', 'Completed', NULL, '150.150.150.212', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36', '2026-11-06 03:34:03', '2026-11-12 07:10:51', NULL, '', NULL, ''),
(95, 101029, 'Printer', 'Printer Malfunction- Request for Technical Assistance', 'The printer is currently not working and unable to print documents.', 'Urgent', 1, 'Dianne Lili', '09776107959', 7, NULL, 'Completed', NULL, '150.150.150.174', 'Mozilla/5.0 (Linux; Android 15; V2352 Build/AP3A.240905.015.A2_NNCS; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/156.0.8078.47 Mobile Safari/537.36 [FB_IAB/FB4A;FBAV/585.0.0.53.94;]', '2026-11-06 05:52:33', '2026-11-12 07:09:23', NULL, 'No Wire Existing', NULL, ''),
(97, 101030, 'Software', 'Update HDS (OLD LAPTOP)', 'Update HDS OLD LAPTOP', 'Medium', 2, 'Kate Lape', '09090909090', 7, '2026-11-14', 'In Progress', NULL, '150.150.150.243', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36', '2026-11-12 06:45:34', '2026-11-19 02:48:30', NULL, 'Not turning ON', NULL, 'Not turning ON'),
(98, 101031, 'Internet', 'No Internet', 'No internet Connection', 'High', 3, 'Noriel Ballaret', '09090909090', 7, '2026-11-14', 'Completed', NULL, '150.150.150.243', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36', '2026-11-12 06:50:23', '2026-11-12 07:10:26', NULL, '', NULL, ''),
(99, 101032, 'Others', 'requesting for employee biometrics', 'pa add kami ng bagong employee dito sa build to last corporation', 'Medium', 5, 'Aime', '09111111111', 8, NULL, 'Completed', NULL, '150.150.150.89', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36', '2026-11-13 01:49:04', '2026-11-13 03:02:27', NULL, 'na', NULL, 'na'),
(100, 101033, 'Others', 'requesting for employee biometrics', 'pa add po kami ng employee biometrics dito sa ECA HW', 'Medium', 4, 'Grace', '09222222222', 8, NULL, 'Completed', NULL, '150.150.150.89', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36', '2026-11-13 01:51:08', '2026-11-13 03:02:54', NULL, 'na', NULL, 'na'),
(101, 101034, 'Hardware', 'speaker not functioning', 'kalit lang wala naggana amon nga speaker. pa help kami please.', 'Medium', 3, 'Thea T-boli', '09333333333', 8, NULL, 'Completed', NULL, '150.150.150.89', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36', '2026-11-13 01:53:36', '2026-11-13 03:02:05', NULL, 'upon checking the hardware from them the device change its default speaker', NULL, 'no recommendation'),
(102, 101035, 'System/Application', 'lag', 'ga lag akong pc.', 'Medium', 1, 'Gillian', '09444444444', 8, NULL, 'Completed', NULL, '150.150.150.89', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36', '2026-11-13 01:55:15', '2026-11-13 02:59:57', NULL, 'HDD performance and health is okay i think the wire or the port is malfunctioning', NULL, 'no recommendation'),
(103, 101036, 'Email', 'EMAIL RESTORATION', 'To restore back-up email.', 'Medium', 4, 'Mary Grace Perales', '09486082112', 7, '2026-11-13', 'Completed', NULL, '150.150.150.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36', '2026-11-13 06:35:49', '2026-11-16 00:20:26', NULL, 'No Link from Local Folder', NULL, ''),
(104, 101037, 'Printer', 'paper feed epson l110', 'paper feed ang printer. need help', 'Medium', 1, 'Diane lili', '09111111111', 8, NULL, 'Completed', NULL, '150.150.150.89', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36', '2026-11-16 07:24:12', '2026-11-16 07:29:56', NULL, 'paper feed problem', NULL, 'deep cleaning'),
(106, 101039, 'Others', 'UPS request for repair', 'nag brown out. bigla namatay ang PC ko', 'Medium', 1, 'Mojado, Marivic', '09111111111', 8, NULL, 'In Progress', NULL, '150.150.150.89', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36', '2026-11-17 03:41:32', '2026-11-20 05:44:09', NULL, 'battery not charging', 'checked', 'replacement battery'),
(110, 101041, 'Network', 'PEOPLES BANK na wifi', 'wala ang wifi since kahapon. paadtuan po please dili mi mka access sa cctv.', 'High', 3, 'Rey Montero', '09954216471', 8, NULL, 'Pending', NULL, '150.150.150.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/109.0.0.0 Safari/537.36', '2026-11-20 03:28:36', '2026-11-20 03:28:36', NULL, NULL, NULL, NULL),
(111, 101042, 'Email', 'Expired certification of thunderbird', 'Expired certification of thunderbird', 'Medium', 3, 'Candice Saga', '09284506764', 7, '2026-11-20', 'Pending', NULL, '150.150.150.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36', '2026-11-20 03:33:02', '2026-11-20 03:33:02', NULL, NULL, NULL, NULL),
(112, 101043, 'Internet', 'NO INTERNET CONNECTION', 'NO INTERNET CONNECTION 3 units', 'Urgent', 7, 'CSP', '09090909090', 8, '2026-11-20', 'Pending', NULL, '150.150.150.243', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36', '2026-11-20 03:41:23', '2026-11-20 03:41:23', NULL, NULL, NULL, NULL),
(113, 101044, 'CCTV', 'Re Cabling CCTV', 'need Re Cabling CCTV Wires..5 camera', 'Low', 4, 'Ronald Huang', '09090909090', 7, '2026-11-30', 'Pending', NULL, '150.150.150.243', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36', '2026-11-20 03:47:03', '2026-11-20 03:47:03', NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `task_report_sequence`
--

CREATE TABLE `task_report_sequence` (
  `sequence_id` int(11) NOT NULL,
  `last_number` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `task_report_sequence`
--

INSERT INTO `task_report_sequence` (`sequence_id`, `last_number`) VALUES
(1, 101044);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `employee_no` int(11) DEFAULT NULL,
  `full_name` varchar(50) NOT NULL,
  `position` varchar(20) DEFAULT NULL,
  `mobile_no` varchar(20) DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `date_hired` date DEFAULT NULL,
  `blood_type` varchar(5) DEFAULT NULL,
  `sss_no` varchar(30) DEFAULT NULL,
  `pagibig_no` varchar(30) DEFAULT NULL,
  `philhealth_no` varchar(30) DEFAULT NULL,
  `tin_no` varchar(30) DEFAULT NULL,
  `emergency_contact_name` varchar(150) DEFAULT NULL,
  `emergency_contact_number` varchar(25) DEFAULT NULL,
  `emergency_contact_relationship` varchar(80) DEFAULT NULL,
  `emergency_contact_address` text DEFAULT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','employee','mishead','misuser') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `presence_status` enum('Available','Busy','On Leave') NOT NULL DEFAULT 'Available',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `employee_no`, `full_name`, `position`, `mobile_no`, `date_of_birth`, `date_hired`, `blood_type`, `sss_no`, `pagibig_no`, `philhealth_no`, `tin_no`, `emergency_contact_name`, `emergency_contact_number`, `emergency_contact_relationship`, `emergency_contact_address`, `username`, `password`, `role`, `created_at`, `presence_status`, `updated_at`) VALUES
(2, 1090, 'Ferdinand Jereme', 'MIS Head', '09224555861', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'ferds', '$2y$10$AYDEfwwCROSn.NtFaMVIuuoY3e.CgimrSsHt8da3VjYQJH7QPQDuq', 'mishead', '2024-08-28 07:10:40', 'Available', '2026-11-20 06:43:43'),
(7, 1091, 'Jaime Segador', 'MIS Associate', '09072943742', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'jaime', '$2y$10$L4p7FVZugNrc6NqWgTqN6uthoZ6sLbaH.hrPsupT9yLoLS1RcBtVi', 'misuser', '2024-08-29 17:11:21', 'Available', '2026-11-20 06:44:21'),
(8, 1089, 'Jeffrey Faeldonia', 'MIS Associate', '09959870068', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'jeff', '$2y$10$ZQthtesHaFOPWrNOfndSh.rn4bxIO.Gg/Xf6DQpO34qCtpf0mp.9i', 'misuser', '2024-08-29 17:11:34', 'Available', '2026-11-20 06:43:16');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `computers`
--
ALTER TABLE `computers`
  ADD PRIMARY KEY (`computer_id`),
  ADD UNIQUE KEY `tag_no` (`tag_no`);

--
-- Indexes for table `leave_requests`
--
ALTER TABLE `leave_requests`
  ADD PRIMARY KEY (`leave_id`),
  ADD KEY `idx_leave_user` (`user_id`),
  ADD KEY `idx_leave_status` (`status`),
  ADD KEY `idx_leave_dates` (`start_date`,`end_date`);

--
-- Indexes for table `logs`
--
ALTER TABLE `logs`
  ADD PRIMARY KEY (`log_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`notif_id`);

--
-- Indexes for table `tasks`
--
ALTER TABLE `tasks`
  ADD PRIMARY KEY (`task_id`),
  ADD UNIQUE KEY `report_id` (`report_id`),
  ADD UNIQUE KEY `report_id_2` (`report_id`),
  ADD UNIQUE KEY `report_id_3` (`report_id`),
  ADD KEY `assigned_to` (`assigned_to`);

--
-- Indexes for table `task_report_sequence`
--
ALTER TABLE `task_report_sequence`
  ADD PRIMARY KEY (`sequence_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `computers`
--
ALTER TABLE `computers`
  MODIFY `computer_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `leave_requests`
--
ALTER TABLE `leave_requests`
  MODIFY `leave_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `logs`
--
ALTER TABLE `logs`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `notif_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=65;

--
-- AUTO_INCREMENT for table `tasks`
--
ALTER TABLE `tasks`
  MODIFY `task_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=114;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `tasks`
--
ALTER TABLE `tasks`
  ADD CONSTRAINT `tasks_ibfk_1` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`user_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
