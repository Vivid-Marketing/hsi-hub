-- Adminer 4.7.8 MySQL dump

SET NAMES utf8;
SET time_zone = '+00:00';
SET foreign_key_checks = 0;
SET sql_mode = 'NO_AUTO_VALUE_ON_ZERO';

DROP TABLE IF EXISTS `course_api_append_data`;
CREATE TABLE `course_api_append_data` (
  `capdid` int(14) unsigned NOT NULL AUTO_INCREMENT,
  `craft_id` int(14) DEFAULT NULL,
  `slug` text DEFAULT NULL,
  `title` text DEFAULT NULL,
  `cldId` text DEFAULT NULL,
  `status` text DEFAULT NULL,
  `recommended` text DEFAULT NULL,
  `pricingTier` text DEFAULT NULL,
  `languages` text DEFAULT NULL,
  `lessonAffiliation` text DEFAULT NULL,
  `last_updated` datetime DEFAULT NULL,
  PRIMARY KEY (`capdid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;


DROP TABLE IF EXISTS `course_api_to_delete`;
CREATE TABLE `course_api_to_delete` (
  `ctdlid` int(14) unsigned NOT NULL AUTO_INCREMENT,
  `craftid` text DEFAULT NULL,
  `title` text DEFAULT NULL,
  `cldid` text DEFAULT NULL,
  PRIMARY KEY (`ctdlid`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;


DROP TABLE IF EXISTS `course_api_to_delete_backup`;
CREATE TABLE `course_api_to_delete_backup` (
  `ctdlid` int(14) unsigned NOT NULL AUTO_INCREMENT,
  `craftid` text DEFAULT NULL,
  `title` text DEFAULT NULL,
  `cldid` text DEFAULT NULL,
  `date_backed_up` datetime DEFAULT NULL,
  PRIMARY KEY (`ctdlid`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;


-- 2026-04-10 15:58:11
