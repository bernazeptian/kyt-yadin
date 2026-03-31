-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Mar 31, 2026 at 06:10 AM
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
-- Database: `kyt_yadin`
--

-- --------------------------------------------------------

--
-- Table structure for table `corrective_actions`
--

CREATE TABLE `corrective_actions` (
  `id` int(11) NOT NULL,
  `report_id` int(11) NOT NULL,
  `action_desc` varchar(255) NOT NULL,
  `pic_user_id` int(11) DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `status` enum('open','progress','done') NOT NULL DEFAULT 'open',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `corrective_actions`
--

INSERT INTO `corrective_actions` (`id`, `report_id`, `action_desc`, `pic_user_id`, `due_date`, `status`, `created_at`, `updated_at`) VALUES
(2, 3, 'Testing Corrective Action Assign To Gunawan', 3, '2026-03-21', 'done', '2026-03-18 10:44:08', '2026-03-18 10:59:34');

-- --------------------------------------------------------

--
-- Table structure for table `departments`
--

CREATE TABLE `departments` (
  `id` int(11) NOT NULL,
  `code` varchar(10) NOT NULL,
  `name` varchar(100) NOT NULL,
  `head_id` int(11) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `departments`
--

INSERT INTO `departments` (`id`, `code`, `name`, `head_id`, `description`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'PGA', 'Personnel & General Affairs', 4, 'PGA Department', 1, '2026-03-17 14:55:31', '2026-03-17 14:55:40');

-- --------------------------------------------------------

--
-- Table structure for table `hiyari_reports`
--

CREATE TABLE `hiyari_reports` (
  `id` int(11) NOT NULL,
  `report_number` varchar(20) NOT NULL,
  `report_date` date NOT NULL,
  `department_id` int(11) DEFAULT NULL,
  `location_id` int(11) DEFAULT NULL,
  `category` enum('unsafe_action','unsafe_condition','near_miss') DEFAULT NULL,
  `description` text DEFAULT NULL,
  `likelihood` varchar(1) DEFAULT NULL COMMENT 'A=Almost Certain B=Likely C=Moderate D=Unlikely E=Rare',
  `severity` tinyint(4) DEFAULT NULL COMMENT '1=Insignificant 2=Minor 3=Moderate 4=Major 5=Catastrophic',
  `risk_level` enum('low','medium','high','extreme') DEFAULT NULL,
  `status` enum('open','in_progress','closed') NOT NULL DEFAULT 'open',
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `hiyari_reports`
--

INSERT INTO `hiyari_reports` (`id`, `report_number`, `report_date`, `department_id`, `location_id`, `category`, `description`, `likelihood`, `severity`, `risk_level`, `status`, `created_by`, `created_at`, `updated_at`) VALUES
(2, 'HH-202603-001', '2026-03-18', 1, 1, 'near_miss', 'Kabel Melintang', 'A', 1, 'extreme', 'open', 8, '2026-03-18 10:39:09', '2026-03-18 10:39:09'),
(3, 'HH-202603-002', '2026-03-18', 1, 1, 'near_miss', 'Kabel Melintang', 'A', 1, 'extreme', 'closed', 8, '2026-03-18 10:42:47', '2026-03-18 10:59:34');

-- --------------------------------------------------------

--
-- Table structure for table `hiyari_report_comments`
--

CREATE TABLE `hiyari_report_comments` (
  `id` int(11) NOT NULL,
  `report_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `comment` text NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `hiyari_report_photos`
--

CREATE TABLE `hiyari_report_photos` (
  `id` int(11) NOT NULL,
  `report_id` int(11) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `uploaded_by` int(11) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `hiyari_report_photos`
--

INSERT INTO `hiyari_report_photos` (`id`, `report_id`, `file_path`, `uploaded_by`, `created_at`) VALUES
(1, 3, 'uploads/hiyari/hiyari_3_69ba1f3731f33.png', 8, '2026-03-18 10:42:47');

-- --------------------------------------------------------

--
-- Table structure for table `locations`
--

CREATE TABLE `locations` (
  `id` int(11) NOT NULL,
  `code` varchar(10) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `locations`
--

INSERT INTO `locations` (`id`, `code`, `name`, `description`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'OFA', 'Office Administration', 'Office A Administration', 1, '2026-03-17 14:55:13', '2026-03-17 14:55:13');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `type` enum('info','success','warning','danger') NOT NULL DEFAULT 'info',
  `url` varchar(255) DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `title`, `message`, `type`, `url`, `is_read`, `created_at`) VALUES
(2, 3, 'New Corrective Action Assigned', 'You have been assigned a corrective action for report HH-202603-002: Testing Corrective Action Assign To Gunawan...', 'warning', '/kyt-yadin/hiyari/view?id=3', 0, '2026-03-18 10:44:08');

-- --------------------------------------------------------

--
-- Table structure for table `push_subscriptions`
--

CREATE TABLE `push_subscriptions` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `endpoint` text NOT NULL,
  `p256dh` text NOT NULL,
  `auth` text NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `employee_id` varchar(20) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `department_id` int(11) DEFAULT NULL,
  `role` tinyint(1) NOT NULL DEFAULT 3,
  `reset_token` varchar(100) DEFAULT NULL,
  `reset_expires` datetime DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `employee_id`, `name`, `email`, `password`, `department_id`, `role`, `reset_token`, `reset_expires`, `is_active`, `created_at`, `updated_at`) VALUES
(2, '2023029', 'Bernaz', 'bernaz_septian@yanmar.com', '$2y$12$iu6MimNk7Swf5wMKe1tnZuKr6jo6SfZtRFWuAhUuqv6kfws.cmYKS', NULL, 1, NULL, NULL, 1, '2026-03-17 14:51:24', '2026-03-18 09:26:30'),
(3, '2021040', 'Gunawan Wicaksono', 'gunawan_wicaksono@yanmar.com', '$2y$10$rFmuMSGgAoMzfUNs41/LquN1a/61yEe1Q5okRHXp1D7.1Qf2XVnCu', NULL, 3, NULL, NULL, 1, '2026-03-17 14:52:46', '2026-03-17 14:56:05'),
(4, '2018108', 'Taufan Budiyana', 'taufan_budiyana@yanmar.com', '$2y$10$AFM9zZHUO/jD9lF7BMf0OemucWbpV4Lj3StbmWWlnJkoXg37gHCtS', NULL, 2, NULL, NULL, 1, '2026-03-17 14:53:30', '2026-03-17 14:53:30'),
(8, '2025006', 'Pratiway', 'pratiwi_kurnia@yanmar.com', '$2y$10$fEHVfs.SGlyLf/nIOIQzguZxXsCUsWWeAlQ5qHMf27oHW0G9bkBsK', NULL, 3, NULL, NULL, 1, '2026-03-18 10:34:42', '2026-03-18 10:34:42');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `corrective_actions`
--
ALTER TABLE `corrective_actions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_action_report` (`report_id`),
  ADD KEY `fk_action_user` (`pic_user_id`);

--
-- Indexes for table `departments`
--
ALTER TABLE `departments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_dept_code` (`code`),
  ADD KEY `fk_dept_head` (`head_id`);

--
-- Indexes for table `hiyari_reports`
--
ALTER TABLE `hiyari_reports`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_report_number` (`report_number`),
  ADD KEY `fk_report_dept` (`department_id`),
  ADD KEY `fk_report_loc` (`location_id`),
  ADD KEY `fk_report_user` (`created_by`);

--
-- Indexes for table `hiyari_report_comments`
--
ALTER TABLE `hiyari_report_comments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_comment_report` (`report_id`),
  ADD KEY `fk_comment_user` (`user_id`);

--
-- Indexes for table `hiyari_report_photos`
--
ALTER TABLE `hiyari_report_photos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_photo_report` (`report_id`);

--
-- Indexes for table `locations`
--
ALTER TABLE `locations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_loc_code` (`code`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_notif_user` (`user_id`);

--
-- Indexes for table `push_subscriptions`
--
ALTER TABLE `push_subscriptions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_push_user` (`user_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_employee_id` (`employee_id`),
  ADD UNIQUE KEY `uq_email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `corrective_actions`
--
ALTER TABLE `corrective_actions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `departments`
--
ALTER TABLE `departments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `hiyari_reports`
--
ALTER TABLE `hiyari_reports`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `hiyari_report_comments`
--
ALTER TABLE `hiyari_report_comments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `hiyari_report_photos`
--
ALTER TABLE `hiyari_report_photos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `locations`
--
ALTER TABLE `locations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `push_subscriptions`
--
ALTER TABLE `push_subscriptions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `corrective_actions`
--
ALTER TABLE `corrective_actions`
  ADD CONSTRAINT `fk_action_report` FOREIGN KEY (`report_id`) REFERENCES `hiyari_reports` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_action_user` FOREIGN KEY (`pic_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `departments`
--
ALTER TABLE `departments`
  ADD CONSTRAINT `fk_dept_head` FOREIGN KEY (`head_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `hiyari_reports`
--
ALTER TABLE `hiyari_reports`
  ADD CONSTRAINT `fk_report_dept` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_report_loc` FOREIGN KEY (`location_id`) REFERENCES `locations` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_report_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `hiyari_report_comments`
--
ALTER TABLE `hiyari_report_comments`
  ADD CONSTRAINT `fk_comment_report` FOREIGN KEY (`report_id`) REFERENCES `hiyari_reports` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_comment_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `hiyari_report_photos`
--
ALTER TABLE `hiyari_report_photos`
  ADD CONSTRAINT `fk_photo_report` FOREIGN KEY (`report_id`) REFERENCES `hiyari_reports` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `fk_notif_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `push_subscriptions`
--
ALTER TABLE `push_subscriptions`
  ADD CONSTRAINT `fk_push_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
