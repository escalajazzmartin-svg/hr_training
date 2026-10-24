CREATE DATABASE IF NOT EXISTS hr_training_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE hr_training_system;
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS certificates;
DROP TABLE IF EXISTS evaluations;
DROP TABLE IF EXISTS attendance;
DROP TABLE IF EXISTS registrations;
DROP TABLE IF EXISTS training_programs;
DROP TABLE IF EXISTS employee_competencies;
DROP TABLE IF EXISTS competencies;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS employees;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE employees (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  employee_code VARCHAR(30) NOT NULL UNIQUE,
  first_name VARCHAR(80) NOT NULL,
  last_name VARCHAR(80) NOT NULL,
  email VARCHAR(160) NOT NULL UNIQUE,
  phone VARCHAR(35) DEFAULT NULL,
  department VARCHAR(100) NOT NULL,
  job_title VARCHAR(120) NOT NULL,
  specialization VARCHAR(120) NOT NULL,
  hire_date DATE DEFAULT NULL,
  employment_status ENUM('Active','On Leave','Inactive') NOT NULL DEFAULT 'Active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  employee_id INT UNSIGNED DEFAULT NULL,
  email VARCHAR(160) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('admin','employee','trainer') NOT NULL,
  first_name VARCHAR(80) NOT NULL,
  last_name VARCHAR(80) NOT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_users_employee FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE competencies (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(140) NOT NULL UNIQUE,
  category VARCHAR(100) NOT NULL DEFAULT 'Professional',
  description TEXT DEFAULT NULL
) ENGINE=InnoDB;

CREATE TABLE employee_competencies (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  employee_id INT UNSIGNED NOT NULL,
  competency_id INT UNSIGNED NOT NULL,
  current_score TINYINT UNSIGNED NOT NULL DEFAULT 0,
  target_score TINYINT UNSIGNED NOT NULL DEFAULT 80,
  last_assessed DATE DEFAULT NULL,
  notes TEXT DEFAULT NULL,
  UNIQUE KEY uq_employee_competency (employee_id, competency_id),
  CONSTRAINT fk_ec_employee FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
  CONSTRAINT fk_ec_competency FOREIGN KEY (competency_id) REFERENCES competencies(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE training_programs (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(180) NOT NULL,
  category VARCHAR(120) NOT NULL,
  specialization VARCHAR(120) NOT NULL,
  description TEXT DEFAULT NULL,
  trainer_user_id INT UNSIGNED DEFAULT NULL,
  start_date DATE NOT NULL,
  end_date DATE DEFAULT NULL,
  start_time TIME DEFAULT NULL,
  venue VARCHAR(160) DEFAULT NULL,
  capacity SMALLINT UNSIGNED NOT NULL DEFAULT 30,
  status ENUM('Draft','Upcoming','Ongoing','Completed','Cancelled') NOT NULL DEFAULT 'Upcoming',
  created_by INT UNSIGNED DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_program_trainer FOREIGN KEY (trainer_user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_program_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE registrations (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  employee_id INT UNSIGNED NOT NULL,
  program_id INT UNSIGNED NOT NULL,
  status ENUM('Registered','In Progress','Completed','Withdrawn') NOT NULL DEFAULT 'Registered',
  registered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_registration (employee_id, program_id),
  CONSTRAINT fk_reg_employee FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
  CONSTRAINT fk_reg_program FOREIGN KEY (program_id) REFERENCES training_programs(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE attendance (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  registration_id INT UNSIGNED NOT NULL UNIQUE,
  attendance_status ENUM('Present','Late','Absent','Excused') NOT NULL DEFAULT 'Present',
  remarks VARCHAR(255) DEFAULT NULL,
  marked_by INT UNSIGNED DEFAULT NULL,
  marked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_att_registration FOREIGN KEY (registration_id) REFERENCES registrations(id) ON DELETE CASCADE,
  CONSTRAINT fk_att_marker FOREIGN KEY (marked_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE evaluations (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  registration_id INT UNSIGNED NOT NULL UNIQUE,
  score DECIMAL(5,2) DEFAULT NULL,
  feedback TEXT DEFAULT NULL,
  assessed_by INT UNSIGNED DEFAULT NULL,
  assessed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_eval_registration FOREIGN KEY (registration_id) REFERENCES registrations(id) ON DELETE CASCADE,
  CONSTRAINT fk_eval_assessor FOREIGN KEY (assessed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE certificates (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  registration_id INT UNSIGNED NOT NULL UNIQUE,
  certificate_code VARCHAR(50) NOT NULL UNIQUE,
  issued_at DATE NOT NULL,
  approved_by INT UNSIGNED DEFAULT NULL,
  CONSTRAINT fk_cert_registration FOREIGN KEY (registration_id) REFERENCES registrations(id) ON DELETE CASCADE,
  CONSTRAINT fk_cert_approver FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

INSERT INTO employees (id, employee_code, first_name, last_name, email, phone, department, job_title, specialization, hire_date, employment_status) VALUES
(1,'FAC-2024-018','Alyssa','Reyes','alyssa.reyes@westmead.edu','0917-555-0101','Information Technology','Instructor II','Programming','2021-06-15','Active'),
(2,'FAC-2022-041','Marco','Santos','marco.santos@westmead.edu','0917-555-0102','Information Technology','Assistant Professor','Networking','2019-08-01','Active'),
(3,'FAC-2021-007','Lena','Villanueva','lena.villanueva@westmead.edu','0917-555-0103','Science','Associate Professor','Digital Teaching','2018-05-12','Active'),
(4,'FAC-2023-026','Daniel','Cruz','daniel.cruz@westmead.edu','0917-555-0104','Mathematics','Instructor I','Data Analytics','2022-06-10','Active'),
(5,'FAC-2020-012','Sofia','Garcia','sofia.garcia@westmead.edu','0917-555-0105','Business','Assistant Professor','Research Methods','2017-11-06','Active'),
(6,'FAC-2024-031','Noah','Flores','noah.flores@westmead.edu','0917-555-0106','English','Instructor I','Digital Teaching','2023-06-19','Active');

INSERT INTO users (id, employee_id, email, password_hash, role, first_name, last_name, active) VALUES
(1,NULL,'hr.officer@sthelena.edu','$2y$12$GrzNk13m1LhjtCaxKB.oOOCsAWV2ZLa7fJX2jHOqjt4Zsr5j/XIOO','admin','Dheo','Fabul',1),
(2,1,'alyssa.reyes@westmead.edu','$2y$12$GrzNk13m1LhjtCaxKB.oOOCsAWV2ZLa7fJX2jHOqjt4Zsr5j/XIOO','employee','Alyssa','Reyes',1),
(3,NULL,'gabriel.lim@westmead.edu','$2y$12$GrzNk13m1LhjtCaxKB.oOOCsAWV2ZLa7fJX2jHOqjt4Zsr5j/XIOO','trainer','Gabriel','Lim',1),
(4,2,'marco.santos@westmead.edu','$2y$12$GrzNk13m1LhjtCaxKB.oOOCsAWV2ZLa7fJX2jHOqjt4Zsr5j/XIOO','employee','Marco','Santos',1),
(5,3,'lena.villanueva@westmead.edu','$2y$12$GrzNk13m1LhjtCaxKB.oOOCsAWV2ZLa7fJX2jHOqjt4Zsr5j/XIOO','employee','Lena','Villanueva',1);

INSERT INTO competencies (id, name, category, description) VALUES
(1,'Programming','Technical','Programming fundamentals and application development.'),
(2,'Networking','Technical','Network configuration, security, and troubleshooting.'),
(3,'Digital Teaching','Teaching','Digital tools and modern instructional strategies.'),
(4,'Data Analytics','Technical','Data literacy and data-driven decision-making.'),
(5,'Research Methods','Research','Research planning, methods, and evaluation.'),
(6,'Learning Management Systems','Teaching','Effective use of learning management platforms.');

INSERT INTO employee_competencies (employee_id, competency_id, current_score, target_score, last_assessed, notes) VALUES
(1,1,58,80,'2025-08-10','Needs advanced programming training.'),
(2,2,72,80,'2025-08-12','Needs further networking practice.'),
(3,3,91,80,'2025-08-15','Meets competency target.'),
(4,4,64,80,'2025-08-18','Needs support with analytics tools.'),
(5,5,86,80,'2025-08-20','Meets competency target.'),
(6,3,61,80,'2025-08-22','Needs digital teaching workshop.');

INSERT INTO training_programs (id,title,category,specialization,description,trainer_user_id,start_date,end_date,start_time,venue,capacity,status,created_by) VALUES
(1,'Advanced Programming Training','Technical','Programming','Hands-on programming and application development training.',3,'2026-10-22','2026-10-23','09:00:00','Computer Laboratory 2',30,'Upcoming',1),
(2,'Digital Teaching Strategies','Teaching','Digital Teaching','Practical use of digital tools for classroom instruction.',3,'2026-10-28','2026-10-28','13:00:00','AVR 1',40,'Upcoming',1),
(3,'Networking Fundamentals Refresher','Technical','Networking','Network setup, diagnostics, and security basics.',3,'2026-11-05','2026-11-06','09:30:00','ICT Lab',25,'Upcoming',1),
(4,'Data Literacy for Faculty','Technical','Data Analytics','Use data to support teaching and decision making.',3,'2026-09-10','2026-09-10','10:00:00','Training Room A',25,'Completed',1);

INSERT INTO registrations (id,employee_id,program_id,status) VALUES
(1,1,1,'Registered'),(2,2,3,'Registered'),(3,3,2,'Completed'),(4,6,2,'Registered'),(5,4,4,'Completed');
INSERT INTO attendance (registration_id,attendance_status,remarks,marked_by) VALUES (3,'Present','Completed session',3),(5,'Present','Completed session',3);
INSERT INTO evaluations (registration_id,score,feedback,assessed_by) VALUES (3,92,'Good application of the session concepts.',3),(5,84,'Satisfactory progress.',3);
INSERT INTO certificates (registration_id,certificate_code,issued_at,approved_by) VALUES (3,'WIS-TRN-2026-0003','2026-09-10',1),(5,'WIS-TRN-2026-0005','2026-09-10',1);
