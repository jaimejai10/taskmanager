-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 01, 2026 at 10:40 AM
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
  `comp_address` varchar(50) NOT NULL,
  `tin` varchar(255) NOT NULL,
  `business_type` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `companies`
--

INSERT INTO `companies` (`comp_id`, `comp_name`, `comp_address`, `tin`, `business_type`, `created_at`) VALUES
(1, 'Mabuhayone Corporation', 'Plaza Nova Bldg, Santiago Blvd, GSC', '298-506-622-000', 'Other Business Activities, N.E.C', '2024-08-27 23:10:04'),
(2, 'Honda Cars General Santos, INC.', 'J Catolico Ave, Lagao, GSC', '005-134-268-000', 'Sale and Repair of Motor Vigicles', '2024-08-27 23:10:40'),
(3, 'Peoples Rural Bank', 'Plaza Nova Bldg, Santiago Blvd, GSC', '005-135-590-000', 'Banking', '2024-08-29 09:11:21'),
(4, 'ECA Resources, Inc./ECA building', 'National Highway, City Heights, GSC', '006-623-611-000', 'Leasing', '2026-03-17 03:04:08'),
(5, 'Buildtolast Corporation', 'SAIP, National Highway, Polomolok', '010-114-555-000', 'Wholesale of Construction', '2026-03-17 03:04:09'),
(6, 'Mabuhay Technopark Corporation', 'National Highway, Lagao (1st & 3rd), GSC', '006-294-594-000', 'Leasing', '2026-03-17 03:04:08'),
(7, 'ECA Resources, INC./Cold Store Plus', 'Banisil, Brgy. Tambler', '000-623-611-001', 'Cold Storage', '2026-03-17 03:04:11'),
(8, 'Mincorn Corporation', 'Plaza Nova Bldg, Dad South, GSC', '777-399-279-000', 'Corn Milling', '0000-00-00 00:00:00'),
(9, 'Dalan Mindanao (A Foundatio), INC.', 'Plaza Nova Bldg, Dad South, GSC', '005-977-139-000', 'Corn Milling', '0000-00-00 00:00:00'),
(10, 'Ecalands Corporation', 'ECA Bldg, National Highway, GSC', '483-594-409-000', 'Real Estate Buyinng, Developing, Subdividing, Selleng', '0000-00-00 00:00:00');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `message` text NOT NULL,
  `recipient` int(11) NOT NULL,
  `type` varchar(50) NOT NULL,
  `date` date NOT NULL DEFAULT current_timestamp(),
  `is_read` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `message`, `recipient`, `type`, `date`, `is_read`) VALUES
(26, '\'Desktop\' has been assigned to you. Please review and start working on it', 7, 'New Task Assigned', '2026-03-14', 1),
(27, '\'test1\' has been assigned to you. Please review and start working on it', 7, 'New Task Assigned', '2026-03-14', 1),
(28, '\'CCTV HDD Replacement\' has been assigned to you. Please review and start working on it', 7, 'New Task Assigned', '2026-03-17', 0),
(29, '\'Network Cable Installation\' has been assigned to you. Please review and start working on it', 7, 'New Task Assigned', '2026-03-17', 0),
(30, '\'Computer System Maintenance\' has been assigned to you. Please review and start working on it', 8, 'New Task Assigned', '2026-03-17', 1),
(31, '\'Printer Troubleshooting\' has been assigned to you. Please review and start working on it', 2, 'New Task Assigned', '2026-03-17', 1),
(32, '\'UPS Battery Replacement\' has been assigned to you. Please review and start working on it', 7, 'New Task Assigned', '2026-03-17', 0),
(33, '\'test\' has been assigned to you. Please review and start working on it', 7, 'New Task Assigned', '2026-03-18', 0),
(34, '\'System Repair\' has been assigned to you. Please review and start working on it', 2, 'New Task Assigned', '2026-03-30', 1);

-- --------------------------------------------------------

--
-- Table structure for table `tasks`
--

CREATE TABLE `tasks` (
  `id` int(11) NOT NULL,
  `report_id` int(11) NOT NULL,
  `title` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `company_name` varchar(255) DEFAULT NULL,
  `assigned_to` int(11) DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `status` enum('pending','in_progress','completed') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tasks`
--

INSERT INTO `tasks` (`id`, `report_id`, `title`, `description`, `company_name`, `assigned_to`, `due_date`, `status`, `created_at`) VALUES
(39, 100901, 'CCTV HDD Replacement', 'Replaced the defective hard disk drive of the DVR to restore video recording functionality.', 'Mabuhayone Corporation', 7, '2026-03-28', 'completed', '2026-03-17 02:45:30'),
(40, 100902, 'Network Cable Installation', 'Installed new LAN cable connection to provide stable internet access for the workstation.', 'Honda Cars General Santos, INC.', 7, '2026-03-18', 'completed', '2026-03-17 02:46:03'),
(41, 100903, 'Computer System Maintenance', 'Performed system cleanup, virus scan, and software updates to improve computer performance.', 'Peoples Rural Bank', 8, '2026-03-21', 'completed', '2026-03-17 02:46:51'),
(42, 100904, 'Printer Troubleshooting', 'Diagnosed and fixed printer connectivity issue to restore normal printing operation.', 'Mabuhayone Corporation', 2, '2026-03-18', 'completed', '2026-03-17 02:47:26'),
(43, 100985, 'UPS Battery Replacement', 'Replaced old UPS battery to ensure continuous power backup during power interruptions.', 'Mabuhayone Corporation', 7, '2026-03-17', 'completed', '2026-03-17 02:48:06'),
(45, 100986, 'System Repair', 'No Hard disk', 'ECA Resources, INC./Cold Store Plus', 2, '2026-03-31', 'completed', '2026-03-30 08:31:35');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `full_name` varchar(50) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','employee') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `full_name`, `username`, `password`, `role`, `created_at`) VALUES
(1, 'Oliver', 'admin', '$2y$10$TnyR1Y43m1EIWpb0MiwE8Ocm6rj0F2KojE3PobVfQDo9HYlAHY/7O', 'admin', '2024-08-28 07:10:04'),
(2, 'Ferdinand Jereme', 'ferds', '$2y$10$AYDEfwwCROSn.NtFaMVIuuoY3e.CgimrSsHt8da3VjYQJH7QPQDuq', 'employee', '2024-08-28 07:10:40'),
(7, 'Jaime Segador', 'jaime', '$2y$10$L4p7FVZugNrc6NqWgTqN6uthoZ6sLbaH.hrPsupT9yLoLS1RcBtVi', 'employee', '2024-08-29 17:11:21'),
(8, 'Jeffrey Faeldonia', 'jeff', '$2y$10$ZQthtesHaFOPWrNOfndSh.rn4bxIO.Gg/Xf6DQpO34qCtpf0mp.9i', 'employee', '2024-08-29 17:11:34');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `tasks`
--
ALTER TABLE `tasks`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `report_id` (`report_id`),
  ADD UNIQUE KEY `report_id_2` (`report_id`),
  ADD KEY `assigned_to` (`assigned_to`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=35;

--
-- AUTO_INCREMENT for table `tasks`
--
ALTER TABLE `tasks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=46;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `tasks`
--
ALTER TABLE `tasks`
  ADD CONSTRAINT `tasks_ibfk_1` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
