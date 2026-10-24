-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 17, 2026 at 02:36 PM
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
  `marked_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `attendance`
--

INSERT INTO `attendance` (`id`, `registration_id`, `attendance_status`, `remarks`, `marked_by`, `marked_at`) VALUES
(1, 3, 'Present', 'Completed session', 3, '2026-10-17 09:18:03'),
(2, 5, 'Present', 'Completed session', 3, '2026-10-17 09:18:03');

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `employees`
--

INSERT INTO `employees` (`id`, `employee_code`, `first_name`, `last_name`, `email`, `phone`, `department`, `job_title`, `specialization`, `hire_date`, `employment_status`, `created_at`) VALUES
(1, 'FAC-2024-018', 'Alyssa', 'Reyes', 'alyssa.reyes@westmead.edu', '0917-555-0101', 'Information Technology', 'Instructor II', 'Programming', '2021-06-15', 'Active', '2026-10-17 09:18:03'),
(2, 'FAC-2022-041', 'Marco', 'Santos', 'marco.santos@westmead.edu', '0917-555-0102', 'Information Technology', 'Assistant Professor', 'Networking', '2019-08-01', 'Active', '2026-10-17 09:18:03'),
(3, 'FAC-2021-007', 'Lena', 'Villanueva', 'lena.villanueva@westmead.edu', '0917-555-0103', 'Science', 'Associate Professor', 'Digital Teaching', '2018-05-12', 'Active', '2026-10-17 09:18:03'),
(4, 'FAC-2023-026', 'Daniel', 'Cruz', 'daniel.cruz@westmead.edu', '0917-555-0104', 'Mathematics', 'Instructor I', 'Data Analytics', '2022-06-10', 'Active', '2026-10-17 09:18:03'),
(5, 'FAC-2020-012', 'Sofia', 'Garcia', 'sofia.garcia@westmead.edu', '0917-555-0105', 'Business', 'Assistant Professor', 'Research Methods', '2017-11-06', 'Active', '2026-10-17 09:18:03'),
(6, 'FAC-2024-031', 'Noah', 'Flores', 'noah.flores@westmead.edu', '0917-555-0106', 'English', 'Instructor I', 'Digital Teaching', '2023-06-19', 'Active', '2026-10-17 09:18:03');

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `employee_competencies`
--

INSERT INTO `employee_competencies` (`id`, `employee_id`, `competency_id`, `current_score`, `target_score`, `last_assessed`, `notes`) VALUES
(1, 1, 1, 58, 80, '2025-08-10', 'Needs advanced programming training.'),
(2, 2, 2, 72, 80, '2025-08-12', 'Needs further networking practice.'),
(3, 3, 3, 91, 80, '2025-08-15', 'Meets competency target.'),
(4, 4, 4, 64, 80, '2025-08-18', 'Needs support with analytics tools.'),
(5, 5, 5, 86, 80, '2025-08-20', 'Meets competency target.'),
(6, 6, 3, 61, 80, '2025-08-22', 'Needs digital teaching workshop.');

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `evaluations`
--

INSERT INTO `evaluations` (`id`, `registration_id`, `score`, `feedback`, `assessed_by`, `assessed_at`) VALUES
(1, 3, 92.00, 'Good application of the session concepts.', 3, '2026-10-17 09:18:03'),
(2, 5, 84.00, 'Satisfactory progress.', 3, '2026-10-17 09:18:03');

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `registrations`
--

INSERT INTO `registrations` (`id`, `employee_id`, `program_id`, `status`, `registered_at`) VALUES
(1, 1, 1, 'Registered', '2026-10-17 09:18:03'),
(2, 2, 3, 'Registered', '2026-10-17 09:18:03'),
(3, 3, 2, 'Completed', '2026-10-17 09:18:03'),
(4, 6, 2, 'Registered', '2026-10-17 09:18:03'),
(5, 4, 4, 'Completed', '2026-10-17 09:18:03');

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `training_programs`
--

INSERT INTO `training_programs` (`id`, `title`, `category`, `specialization`, `description`, `trainer_user_id`, `start_date`, `end_date`, `start_time`, `venue`, `capacity`, `status`, `created_by`, `created_at`) VALUES
(1, 'Advanced Programming Training', 'Technical', 'Programming', 'Hands-on programming and application development training.', 3, '2026-10-22', '2026-10-23', '09:00:00', 'Computer Laboratory 2', 30, 'Upcoming', 1, '2026-10-17 09:18:03'),
(2, 'Digital Teaching Strategies', 'Teaching', 'Digital Teaching', 'Practical use of digital tools for classroom instruction.', 3, '2026-10-28', '2026-10-28', '13:00:00', 'AVR 1', 40, 'Upcoming', 1, '2026-10-17 09:18:03'),
(3, 'Networking Fundamentals Refresher', 'Technical', 'Networking', 'Network setup, diagnostics, and security basics.', 3, '2026-11-05', '2026-11-06', '09:30:00', 'ICT Lab', 25, 'Upcoming', 1, '2026-10-17 09:18:03'),
(4, 'Data Literacy for Faculty', 'Technical', 'Data Analytics', 'Use data to support teaching and decision making.', 3, '2026-09-10', '2026-09-10', '10:00:00', 'Training Room A', 25, 'Completed', 1, '2026-10-17 09:18:03');

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `employee_id`, `email`, `password_hash`, `role`, `first_name`, `last_name`, `active`, `created_at`) VALUES
(1, NULL, 'hr.officer@sthelena.edu', '$2y$12$GrzNk13m1LhjtCaxKB.oOOCsAWV2ZLa7fJX2jHOqjt4Zsr5j/XIOO', 'admin', 'Dheo', 'Fabul', 1, '2026-10-17 09:18:03'),
(2, 1, 'alyssa.reyes@westmead.edu', '$2y$12$GrzNk13m1LhjtCaxKB.oOOCsAWV2ZLa7fJX2jHOqjt4Zsr5j/XIOO', 'employee', 'Alyssa', 'Reyes', 1, '2026-10-17 09:18:03'),
(3, NULL, 'gabriel.lim@westmead.edu', '$2y$12$GrzNk13m1LhjtCaxKB.oOOCsAWV2ZLa7fJX2jHOqjt4Zsr5j/XIOO', 'trainer', 'Gabriel', 'Lim', 1, '2026-10-17 09:18:03'),
(4, 2, 'marco.santos@westmead.edu', '$2y$12$GrzNk13m1LhjtCaxKB.oOOCsAWV2ZLa7fJX2jHOqjt4Zsr5j/XIOO', 'employee', 'Marco', 'Santos', 1, '2026-10-17 09:18:03'),
(5, 3, 'lena.villanueva@westmead.edu', '$2y$12$GrzNk13m1LhjtCaxKB.oOOCsAWV2ZLa7fJX2jHOqjt4Zsr5j/XIOO', 'employee', 'Lena', 'Villanueva', 1, '2026-10-17 09:18:03');

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
-- Indexes for table `employee_competencies`
--
ALTER TABLE `employee_competencies`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_employee_competency` (`employee_id`,`competency_id`),
  ADD KEY `fk_ec_competency` (`competency_id`);

--
-- Indexes for table `evaluations`
--
ALTER TABLE `evaluations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `registration_id` (`registration_id`),
  ADD KEY `fk_eval_assessor` (`assessed_by`);

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
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

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
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `employee_competencies`
--
ALTER TABLE `employee_competencies`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `evaluations`
--
ALTER TABLE `evaluations`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `registrations`
--
ALTER TABLE `registrations`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `training_programs`
--
ALTER TABLE `training_programs`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

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
