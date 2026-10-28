ALTER TABLE `attendance`
  ADD COLUMN `time_in` DATETIME NULL AFTER `marked_at`,
  ADD COLUMN `time_out` DATETIME NULL AFTER `time_in`;

CREATE TABLE `attendance_qr_tokens` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `program_id` INT UNSIGNED NOT NULL,
  `action` ENUM('time_in','time_out') NOT NULL,
  `token_hash` CHAR(64) NOT NULL,
  `expires_at` DATETIME NOT NULL,
  `created_by` INT UNSIGNED NOT NULL,
  `used_by` INT UNSIGNED NULL,
  `used_at` DATETIME NULL,
  `revoked_at` DATETIME NULL,
  `revoked_reason` VARCHAR(100) NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_qr_token_hash` (`token_hash`),
  KEY `idx_qr_program_action` (`program_id`,`action`),
  KEY `idx_qr_expires_at` (`expires_at`),
  CONSTRAINT `fk_qr_program` FOREIGN KEY (`program_id`) REFERENCES `training_programs` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_qr_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_qr_user` FOREIGN KEY (`used_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE INDEX `idx_attendance_time_in` ON `attendance` (`time_in`);
CREATE INDEX `idx_attendance_time_out` ON `attendance` (`time_out`);
