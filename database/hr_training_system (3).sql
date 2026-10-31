-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 31, 2026 at 01:21 PM
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
-- Database: `hr_training_system`
--

-- --------------------------------------------------------

--
-- Table structure for table `attendance`
--

CREATE TABLE `attendance` (
  `id` int(10) UNSIGNED NOT NULL,
  `registration_id` int(10) UNSIGNED NOT NULL,
  `attendance_status` enum('Present','Late','Absent','Excused') NOT NULL DEFAULT 'Present',
  `remarks` varchar(255) DEFAULT NULL,
  `marked_by` int(10) UNSIGNED DEFAULT NULL,
  `marked_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `time_in` datetime DEFAULT NULL,
  `time_out` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `attendance`
--

INSERT INTO `attendance` (`id`, `registration_id`, `attendance_status`, `remarks`, `marked_by`, `marked_at`, `time_in`, `time_out`) VALUES
(1, 3, 'Present', 'Completed session', 5, '2026-10-28 04:36:29', '2026-10-28 12:36:29', NULL),
(2, 5, 'Present', 'Completed session', 3, '2026-10-23 16:02:26', NULL, NULL),
(3, 1, 'Late', 'Time-in recorded via QR', 2, '2026-10-28 03:18:24', '2026-10-28 11:18:24', '2026-10-28 11:18:24'),
(4, 6, 'Late', 'Time-in recorded via QR', 5, '2026-10-28 04:38:52', '2026-10-28 12:38:52', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `attendance_qr_tokens`
--

CREATE TABLE `attendance_qr_tokens` (
  `id` int(10) UNSIGNED NOT NULL,
  `program_id` int(10) UNSIGNED NOT NULL,
  `action` enum('time_in','time_out') NOT NULL,
  `token_hash` char(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_by` int(10) UNSIGNED NOT NULL,
  `used_by` int(10) UNSIGNED DEFAULT NULL,
  `used_at` datetime DEFAULT NULL,
  `revoked_at` datetime DEFAULT NULL,
  `revoked_reason` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `attendance_qr_tokens`
--

INSERT INTO `attendance_qr_tokens` (`id`, `program_id`, `action`, `token_hash`, `expires_at`, `created_by`, `used_by`, `used_at`, `revoked_at`, `revoked_reason`, `created_at`) VALUES
(1, 1, 'time_in', '2cbf25d8344d1ea45c1a8a6316564225bd0a63e9a0544a312f35ba37ef3723a2', '2026-10-28 15:16:18', 3, NULL, NULL, '2026-10-28 19:16:28', 'regenerated', '2026-10-28 11:16:18'),
(2, 1, 'time_in', '07bf0f09b1e7ab00f3f73e2ba899bfac28a47bd880c34c0eb36716007a38fabb', '2026-10-28 15:16:28', 3, NULL, NULL, '2026-10-28 19:16:43', 'regenerated', '2026-10-28 11:16:28'),
(3, 1, 'time_in', '954d65102160bbcd39a2e4fc240642d9250bf7d6f185a757cdf82044316e08f2', '2026-10-28 15:16:43', 3, NULL, NULL, '2026-10-28 19:16:49', 'regenerated', '2026-10-28 11:16:43'),
(4, 1, 'time_in', '9f4182022b27e153525bf2b0f573f6fbe28c21808f834094f833e7e1c8e8e7c3', '2026-10-28 15:16:49', 3, NULL, NULL, '2026-10-28 19:17:02', 'regenerated', '2026-10-28 11:16:49'),
(5, 1, 'time_in', '942be89e2c557d9fa27e79ed29db649c58cb408e1520a437b0d396c65c84d128', '2026-10-28 14:17:02', 3, NULL, NULL, '2026-10-28 19:17:07', 'regenerated', '2026-10-28 11:17:02'),
(6, 1, 'time_in', '96fed3f086a7199fa6dc434f2a683a777d9e709742d015c7f55e7f06d17ac629', '2026-10-28 14:17:07', 3, NULL, NULL, '2026-10-28 19:17:25', 'regenerated', '2026-10-28 11:17:07'),
(7, 1, 'time_in', 'c578d1e1fe0e2f8c8aea9683c446b981b8919435f14a35f9f0b6cfc4b0dc51ac', '2026-10-28 14:17:25', 3, NULL, NULL, '2026-10-28 11:18:24', 'regenerated', '2026-10-28 11:17:25'),
(8, 1, 'time_in', '21bceaa21f51efd57ff7f84c872eb7e20532c63c6d16c7f9382168f3b1c7de8b', '2026-10-28 14:18:24', 3, 2, '2026-10-28 11:18:24', NULL, NULL, '2026-10-28 03:18:24'),
(9, 1, 'time_out', 'a5f94b4b3c5606f9a364ce00cc54ea81fbb617056b1d1d66dbc64a4446a934ca', '2026-10-28 14:18:24', 3, 2, '2026-10-28 11:18:24', NULL, NULL, '2026-10-28 03:18:24'),
(10, 1, 'time_in', '88dbe9e21263b5327aa65e95704442ec8d8deb6c5aab861535fb7b0b15280614', '2026-10-28 15:35:10', 3, NULL, NULL, '2026-10-28 12:38:36', 'regenerated', '2026-10-28 04:35:10'),
(11, 2, 'time_in', '22ba03e249a3890c9a28fa86a06c63cc890c23874ec91f2e365f838d8bf3e554', '2026-10-28 15:36:29', 3, 5, '2026-10-28 12:36:29', NULL, NULL, '2026-10-28 04:36:29'),
(12, 2, 'time_in', 'c8a0fea74cc7093e48b000322bc76656c62ca127398d3fb864c385aacdf4dc9c', '2026-10-28 15:38:15', 3, NULL, NULL, NULL, NULL, '2026-10-28 04:38:15'),
(13, 1, 'time_in', 'c3000b5eb938e62231f386e8dd6d26208a737e42bfb90940f45e121406ffba31', '2026-10-28 15:38:36', 3, NULL, NULL, '2026-10-28 12:38:52', 'regenerated', '2026-10-28 04:38:36'),
(14, 1, 'time_in', '808ee407589af6f1f300dd3666bf512d64a887837a6bdeb2a4fea2e9bca958f6', '2026-10-28 15:38:52', 3, 5, '2026-10-28 12:38:52', NULL, NULL, '2026-10-28 04:38:52');

-- --------------------------------------------------------

--
-- Table structure for table `certificates`
--

CREATE TABLE `certificates` (
  `id` int(10) UNSIGNED NOT NULL,
  `registration_id` int(10) UNSIGNED NOT NULL,
  `certificate_code` varchar(50) NOT NULL,
  `issued_at` date NOT NULL,
  `approved_by` int(10) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `certificates`
--

INSERT INTO `certificates` (`id`, `registration_id`, `certificate_code`, `issued_at`, `approved_by`) VALUES
(1, 3, 'WIS-TRN-2026-0003', '2026-09-10', 1),
(2, 5, 'WIS-TRN-2026-0005', '2026-09-10', 1);

-- --------------------------------------------------------

--
-- Table structure for table `competencies`
--

CREATE TABLE `competencies` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(140) NOT NULL,
  `category` varchar(100) NOT NULL DEFAULT 'Professional',
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `competencies`
--

INSERT INTO `competencies` (`id`, `name`, `category`, `description`) VALUES
(1, 'Programming', 'Technical', 'Programming fundamentals and application development.'),
(2, 'Networking', 'Technical', 'Network configuration, security, and troubleshooting.'),
(3, 'Digital Teaching', 'Teaching', 'Digital tools and modern instructional strategies.'),
(4, 'Data Analytics', 'Technical', 'Data literacy and data-driven decision-making.'),
(5, 'Research Methods', 'Research', 'Research planning, methods, and evaluation.'),
(6, 'Learning Management Systems', 'Teaching', 'Effective use of learning management platforms.');

-- --------------------------------------------------------

--
-- Table structure for table `employees`
--

CREATE TABLE `employees` (
  `id` int(10) UNSIGNED NOT NULL,
  `employee_code` varchar(30) NOT NULL,
  `first_name` varchar(80) NOT NULL,
  `last_name` varchar(80) NOT NULL,
  `email` varchar(160) NOT NULL,
  `phone` varchar(35) DEFAULT NULL,
  `department` varchar(100) NOT NULL,
  `job_title` varchar(120) NOT NULL,
  `specialization` varchar(120) NOT NULL,
  `hire_date` date DEFAULT NULL,
  `employment_status` enum('Active','On Leave','Inactive') NOT NULL DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `employees`
--

INSERT INTO `employees` (`id`, `employee_code`, `first_name`, `last_name`, `email`, `phone`, `department`, `job_title`, `specialization`, `hire_date`, `employment_status`, `created_at`) VALUES
(1, 'FAC-2024-018', 'Alyssa', 'Reyes', 'alyssa.reyes@westmead.edu', '0917 555 0101', 'Information Technology', 'Instructor II', 'Programming', '2021-06-15', 'Active', '2026-10-23 16:02:26'),
(2, 'FAC-2022-041', 'Marco', 'Santos', 'marco.santos@westmead.edu', '0917-555-0102', 'Information Technology', 'Assistant Professor', 'Networking', '2019-08-01', 'Active', '2026-10-23 16:02:26'),
(3, 'FAC-2021-007', 'Jazz Martin', 'Escala', 'escala.jazzmartin.c240185@gmail.com', '0994 645 0870', 'Science', 'Associate Professor', 'Digital Teaching', '2018-05-12', 'Active', '2026-10-23 16:02:26'),
(4, 'FAC-2023-026', 'Xiron', 'Tarala', 'sipxcrisp@gmail.com', '0917 555 0104', 'Mathematics', 'Instructor I', 'Data Analytics', '2022-06-10', 'Active', '2026-10-23 16:02:26'),
(5, 'FAC-2020-012', 'Sofia', 'Garcia', 'shadowsyntax6@gmail.com', '0917 555 0105', 'Business', 'Assistant Professor', 'Research Methods', '2017-11-06', 'Active', '2026-10-23 16:02:26'),
(9, 'TRN-2026-002', 'Mico', 'Umali', 'shadowsyntax67@gmail.com', '0917 555 0105', 'Information Technology', 'Instructor I', 'Data Analytics', '2026-10-23', 'Active', '2026-10-28 03:56:02');

-- --------------------------------------------------------

--
-- Table structure for table `employee_code_counters`
--

CREATE TABLE `employee_code_counters` (
  `role_prefix` varchar(3) NOT NULL,
  `registration_year` smallint(5) UNSIGNED NOT NULL,
  `last_number` int(10) UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `employee_code_counters`
--

INSERT INTO `employee_code_counters` (`role_prefix`, `registration_year`, `last_number`) VALUES
('FAC', 2026, 1),
('TRN', 2026, 2);

-- --------------------------------------------------------

--
-- Table structure for table `employee_competencies`
--

CREATE TABLE `employee_competencies` (
  `id` int(10) UNSIGNED NOT NULL,
  `employee_id` int(10) UNSIGNED NOT NULL,
  `competency_id` int(10) UNSIGNED NOT NULL,
  `current_score` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `target_score` tinyint(3) UNSIGNED NOT NULL DEFAULT 80,
  `last_assessed` date DEFAULT NULL,
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `employee_competencies`
--

INSERT INTO `employee_competencies` (`id`, `employee_id`, `competency_id`, `current_score`, `target_score`, `last_assessed`, `notes`) VALUES
(1, 1, 1, 58, 80, '2025-08-10', 'Needs advanced programming training.'),
(2, 2, 2, 72, 80, '2025-08-12', 'Needs further networking practice.'),
(3, 3, 3, 91, 80, '2025-08-15', 'Meets competency target.'),
(4, 4, 4, 64, 80, '2025-08-18', 'Needs support with analytics tools.'),
(5, 5, 5, 86, 80, '2025-08-20', 'Meets competency target.');

-- --------------------------------------------------------

--
-- Table structure for table `employee_password_setup_tokens`
--

CREATE TABLE `employee_password_setup_tokens` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `token_hash` char(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `evaluations`
--

CREATE TABLE `evaluations` (
  `id` int(10) UNSIGNED NOT NULL,
  `registration_id` int(10) UNSIGNED NOT NULL,
  `score` decimal(5,2) DEFAULT NULL,
  `feedback` text DEFAULT NULL,
  `assessed_by` int(10) UNSIGNED DEFAULT NULL,
  `assessed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `evaluations`
--

INSERT INTO `evaluations` (`id`, `registration_id`, `score`, `feedback`, `assessed_by`, `assessed_at`) VALUES
(1, 3, 92.00, 'Good application of the session concepts.', 3, '2026-10-23 16:02:26'),
(2, 5, 84.00, 'Satisfactory progress.', 3, '2026-10-23 16:02:26');

-- --------------------------------------------------------

--
-- Table structure for table `pending_employee_verifications`
--

CREATE TABLE `pending_employee_verifications` (
  `id` int(10) UNSIGNED NOT NULL,
  `email` varchar(160) NOT NULL,
  `token_hash` char(64) NOT NULL,
  `payload_json` longtext NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `registrations`
--

CREATE TABLE `registrations` (
  `id` int(10) UNSIGNED NOT NULL,
  `employee_id` int(10) UNSIGNED NOT NULL,
  `program_id` int(10) UNSIGNED NOT NULL,
  `status` enum('Registered','In Progress','Completed','Withdrawn') NOT NULL DEFAULT 'Registered',
  `registered_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `registrations`
--

INSERT INTO `registrations` (`id`, `employee_id`, `program_id`, `status`, `registered_at`) VALUES
(1, 1, 1, 'Registered', '2026-10-23 16:02:26'),
(2, 2, 3, 'Registered', '2026-10-23 16:02:26'),
(3, 3, 2, 'Completed', '2026-10-23 16:02:26'),
(5, 4, 4, 'Completed', '2026-10-23 16:02:26'),
(6, 3, 1, 'Registered', '2026-10-28 07:54:54');

-- --------------------------------------------------------

--
-- Table structure for table `training_programs`
--

CREATE TABLE `training_programs` (
  `id` int(10) UNSIGNED NOT NULL,
  `title` varchar(180) NOT NULL,
  `category` varchar(120) NOT NULL,
  `specialization` varchar(120) NOT NULL,
  `description` text DEFAULT NULL,
  `trainer_user_id` int(10) UNSIGNED DEFAULT NULL,
  `start_date` date NOT NULL,
  `end_date` date DEFAULT NULL,
  `start_time` time DEFAULT NULL,
  `venue` varchar(160) DEFAULT NULL,
  `capacity` smallint(5) UNSIGNED NOT NULL DEFAULT 30,
  `status` enum('Draft','Upcoming','Ongoing','Completed','Cancelled') NOT NULL DEFAULT 'Upcoming',
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `training_programs`
--

INSERT INTO `training_programs` (`id`, `title`, `category`, `specialization`, `description`, `trainer_user_id`, `start_date`, `end_date`, `start_time`, `venue`, `capacity`, `status`, `created_by`, `created_at`) VALUES
(1, 'Advanced Programming Training', 'Technical', 'Programming', 'Hands-on programming and application development training.', 3, '2026-10-22', '2026-10-23', '09:00:00', 'Computer Laboratory 2', 30, 'Upcoming', 1, '2026-10-23 16:02:26'),
(2, 'Digital Teaching Strategies', 'Teaching', 'Digital Teaching', 'Practical use of digital tools for classroom instruction.', 3, '2026-10-28', '2026-10-28', '13:00:00', 'AVR 1', 40, 'Upcoming', 1, '2026-10-23 16:02:26'),
(3, 'Networking Fundamentals Refresher', 'Technical', 'Networking', 'Network setup, diagnostics, and security basics.', 3, '2026-11-05', '2026-11-06', '09:30:00', 'ICT Lab', 25, 'Upcoming', 1, '2026-10-23 16:02:26'),
(4, 'Data Literacy for Faculty', 'Technical', 'Data Analytics', 'Use data to support teaching and decision making.', 3, '2026-09-10', '2026-09-10', '10:00:00', 'Training Room A', 25, 'Completed', 1, '2026-10-23 16:02:26'),
(5, 'Advanced Database Seminar', 'Technical', 'Programming', NULL, 8, '2026-10-28', '2026-10-28', '21:00:00', 'Computer Laboratory', 30, 'Upcoming', 1, '2026-10-28 13:27:17');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(10) UNSIGNED NOT NULL,
  `employee_id` int(10) UNSIGNED DEFAULT NULL,
  `email` varchar(160) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('admin','employee','trainer') NOT NULL,
  `first_name` varchar(80) NOT NULL,
  `last_name` varchar(80) NOT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `employee_id`, `email`, `password_hash`, `role`, `first_name`, `last_name`, `active`, `created_at`) VALUES
(1, NULL, 'fabul.dheo.c240544@gmail.com', '$2y$12$GrzNk13m1LhjtCaxKB.oOOCsAWV2ZLa7fJX2jHOqjt4Zsr5j/XIOO', 'admin', 'Dheo', 'Fabul', 1, '2026-10-23 16:02:26'),
(2, 1, 'alyssa.reyes@westmead.edu', '$2y$12$GrzNk13m1LhjtCaxKB.oOOCsAWV2ZLa7fJX2jHOqjt4Zsr5j/XIOO', 'employee', 'Alyssa', 'Reyes', 1, '2026-10-23 16:02:26'),
(3, NULL, 'gabriel.lim@westmead.edu', '$2y$12$GrzNk13m1LhjtCaxKB.oOOCsAWV2ZLa7fJX2jHOqjt4Zsr5j/XIOO', 'trainer', 'Gabriel', 'Lim', 1, '2026-10-23 16:02:26'),
(4, 2, 'marco.santos@westmead.edu', '$2y$12$GrzNk13m1LhjtCaxKB.oOOCsAWV2ZLa7fJX2jHOqjt4Zsr5j/XIOO', 'employee', 'Marco', 'Santos', 1, '2026-10-23 16:02:26'),
(5, 3, 'escala.jazzmartin.c240185@gmail.com', '$2y$12$GrzNk13m1LhjtCaxKB.oOOCsAWV2ZLa7fJX2jHOqjt4Zsr5j/XIOO', 'employee', 'Jazz Martin', 'Escala', 1, '2026-10-23 16:02:26'),
(8, 9, 'shadowsyntax67@gmail.com', '$2y$10$oX9XFk8wq71orzyND5M7te4sIci33CYQbOlZ10G7McC8xpWJZPWo2', 'trainer', 'Mico', 'Umali', 1, '2026-10-28 03:56:02');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `attendance`
--
ALTER TABLE `attendance`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `registration_id` (`registration_id`),
  ADD KEY `fk_att_marker` (`marked_by`);

--
-- Indexes for table `attendance_qr_tokens`
--
ALTER TABLE `attendance_qr_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_qr_token_hash` (`token_hash`),
  ADD KEY `idx_qr_program_action` (`program_id`,`action`),
  ADD KEY `idx_qr_expires_at` (`expires_at`),
  ADD KEY `fk_qr_creator` (`created_by`),
  ADD KEY `fk_qr_user` (`used_by`);

--
-- Indexes for table `certificates`
--
ALTER TABLE `certificates`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `registration_id` (`registration_id`),
  ADD UNIQUE KEY `certificate_code` (`certificate_code`),
  ADD KEY `fk_cert_approver` (`approved_by`);

--
-- Indexes for table `competencies`
--
ALTER TABLE `competencies`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `employees`
--
ALTER TABLE `employees`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `employee_code` (`employee_code`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `employee_code_counters`
--
ALTER TABLE `employee_code_counters`
  ADD PRIMARY KEY (`role_prefix`,`registration_year`);

--
-- Indexes for table `employee_competencies`
--
ALTER TABLE `employee_competencies`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_employee_competency` (`employee_id`,`competency_id`),
  ADD KEY `fk_ec_competency` (`competency_id`);

--
-- Indexes for table `employee_password_setup_tokens`
--
ALTER TABLE `employee_password_setup_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_password_setup_user` (`user_id`),
  ADD UNIQUE KEY `uq_password_setup_token` (`token_hash`),
  ADD KEY `idx_password_setup_expiry` (`expires_at`);

--
-- Indexes for table `evaluations`
--
ALTER TABLE `evaluations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `registration_id` (`registration_id`),
  ADD KEY `fk_eval_assessor` (`assessed_by`);

--
-- Indexes for table `pending_employee_verifications`
--
ALTER TABLE `pending_employee_verifications`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_pending_employee_email` (`email`),
  ADD UNIQUE KEY `uq_pending_employee_token` (`token_hash`),
  ADD KEY `idx_pending_employee_expiry` (`expires_at`);

--
-- Indexes for table `registrations`
--
ALTER TABLE `registrations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_registration` (`employee_id`,`program_id`),
  ADD KEY `fk_reg_program` (`program_id`);

--
-- Indexes for table `training_programs`
--
ALTER TABLE `training_programs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_program_trainer` (`trainer_user_id`),
  ADD KEY `fk_program_creator` (`created_by`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `fk_users_employee` (`employee_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `attendance`
--
ALTER TABLE `attendance`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `attendance_qr_tokens`
--
ALTER TABLE `attendance_qr_tokens`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `certificates`
--
ALTER TABLE `certificates`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `competencies`
--
ALTER TABLE `competencies`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `employees`
--
ALTER TABLE `employees`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `employee_competencies`
--
ALTER TABLE `employee_competencies`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `employee_password_setup_tokens`
--
ALTER TABLE `employee_password_setup_tokens`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `evaluations`
--
ALTER TABLE `evaluations`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `pending_employee_verifications`
--
ALTER TABLE `pending_employee_verifications`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `registrations`
--
ALTER TABLE `registrations`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `training_programs`
--
ALTER TABLE `training_programs`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `attendance`
--
ALTER TABLE `attendance`
  ADD CONSTRAINT `fk_att_marker` FOREIGN KEY (`marked_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_att_registration` FOREIGN KEY (`registration_id`) REFERENCES `registrations` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `attendance_qr_tokens`
--
ALTER TABLE `attendance_qr_tokens`
  ADD CONSTRAINT `fk_qr_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_qr_program` FOREIGN KEY (`program_id`) REFERENCES `training_programs` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_qr_user` FOREIGN KEY (`used_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `certificates`
--
ALTER TABLE `certificates`
  ADD CONSTRAINT `fk_cert_approver` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_cert_registration` FOREIGN KEY (`registration_id`) REFERENCES `registrations` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `employee_competencies`
--
ALTER TABLE `employee_competencies`
  ADD CONSTRAINT `fk_ec_competency` FOREIGN KEY (`competency_id`) REFERENCES `competencies` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_ec_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `employee_password_setup_tokens`
--
ALTER TABLE `employee_password_setup_tokens`
  ADD CONSTRAINT `fk_password_setup_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `evaluations`
--
ALTER TABLE `evaluations`
  ADD CONSTRAINT `fk_eval_assessor` FOREIGN KEY (`assessed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_eval_registration` FOREIGN KEY (`registration_id`) REFERENCES `registrations` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `registrations`
--
ALTER TABLE `registrations`
  ADD CONSTRAINT `fk_reg_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_reg_program` FOREIGN KEY (`program_id`) REFERENCES `training_programs` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `training_programs`
--
ALTER TABLE `training_programs`
  ADD CONSTRAINT `fk_program_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_program_trainer` FOREIGN KEY (`trainer_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_users_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
