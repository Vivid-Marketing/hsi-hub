-- Adminer 4.7.8 MySQL dump

SET NAMES utf8;
SET time_zone = '+00:00';
SET foreign_key_checks = 0;
SET sql_mode = 'NO_AUTO_VALUE_ON_ZERO';

SET NAMES utf8mb4;

DROP TABLE IF EXISTS `courses_pdfs_batches`;
CREATE TABLE `courses_pdfs_batches` (
  `batch_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `job_id` varchar(128) NOT NULL,
  `batch_index` int(10) unsigned NOT NULL,
  `total_batches` int(10) unsigned NOT NULL,
  `email` varchar(255) NOT NULL,
  `serialized_data` longtext NOT NULL,
  `date_entered` datetime NOT NULL DEFAULT current_timestamp(),
  `processed_at` datetime DEFAULT NULL,
  `stitched_cpdid` int(10) unsigned DEFAULT NULL,
  `status` enum('pending','processed','failed') NOT NULL DEFAULT 'pending',
  `error_message` text DEFAULT NULL,
  PRIMARY KEY (`batch_id`),
  UNIQUE KEY `uniq_job_batch` (`job_id`,`batch_index`),
  KEY `idx_job_id` (`job_id`),
  KEY `idx_status` (`status`),
  KEY `idx_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `courses_pdfs_data`;
CREATE TABLE `courses_pdfs_data` (
  `cpdid` int(14) unsigned NOT NULL AUTO_INCREMENT,
  `date_entered` datetime DEFAULT NULL,
  `serialized_data` longtext DEFAULT NULL,
  `email` text DEFAULT NULL,
  `status` text DEFAULT NULL,
  PRIMARY KEY (`cpdid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;


-- 2026-04-14 16:21:11
