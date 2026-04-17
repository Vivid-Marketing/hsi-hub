-- Adminer 4.7.8 MySQL dump

SET NAMES utf8;
SET time_zone = '+00:00';
SET foreign_key_checks = 0;
SET sql_mode = 'NO_AUTO_VALUE_ON_ZERO';

DROP TABLE IF EXISTS `cld_api_tokens`;
CREATE TABLE `cld_api_tokens` (
  `cldatkid` int(14) unsigned NOT NULL AUTO_INCREMENT,
  `token` text DEFAULT NULL,
  `datetime_created` datetime DEFAULT NULL,
  PRIMARY KEY (`cldatkid`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;


DROP TABLE IF EXISTS `course_api_data`;
CREATE TABLE `course_api_data` (
  `capdid` int(14) unsigned NOT NULL AUTO_INCREMENT,
  `title` text CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `cldId` text CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `salesLibraryTopic` text CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `courseTopic` text CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `collections` text CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `vendorId` text CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `vendorName` text CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `libraryId` text CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `libraryName` text CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `lessonId` text CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `ej4CourseNumber` text CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `lessonModality` text CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `hsiProgramID` text CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `lessonLength` text CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `lessonAffiliations` text DEFAULT NULL,
  `locale` text CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `allLocales` text CHARACTER SET utf8 COLLATE utf8_unicode_ci DEFAULT NULL,
  `courseLanguageCategoriesSlug` text CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `pricingTier` text CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `cldImageUrl` text CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `courseImageUrl` text CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `courseImageThumbUrl` text CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `courseInformation` text DEFAULT NULL,
  `marketingDescription` text DEFAULT NULL,
  `courseOutline` text DEFAULT NULL,
  `courseObjectives` text DEFAULT NULL,
  `courseRegulations` text DEFAULT NULL,
  `parentCldid` text DEFAULT NULL,
  `isRecommended` varchar(255) DEFAULT NULL,
  `dateAdded` datetime DEFAULT NULL,
  PRIMARY KEY (`capdid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;


DROP TABLE IF EXISTS `course_api_data_backup`;
CREATE TABLE `course_api_data_backup` (
  `capdid` int(14) unsigned NOT NULL AUTO_INCREMENT,
  `title` text DEFAULT NULL,
  `cldId` text DEFAULT NULL,
  `salesLibraryTopic` text DEFAULT NULL,
  `courseTopic` text DEFAULT NULL,
  `collections` text DEFAULT NULL,
  `vendorId` text DEFAULT NULL,
  `vendorName` text DEFAULT NULL,
  `libraryId` text DEFAULT NULL,
  `libraryName` text DEFAULT NULL,
  `lessonId` text DEFAULT NULL,
  `ej4CourseNumber` text DEFAULT NULL,
  `lessonModality` text DEFAULT NULL,
  `hsiProgramID` text DEFAULT NULL,
  `lessonLength` text DEFAULT NULL,
  `lessonAffiliations` text DEFAULT NULL,
  `locale` text DEFAULT NULL,
  `allLocales` text CHARACTER SET utf8 COLLATE utf8_unicode_ci DEFAULT NULL,
  `courseLanguageCategoriesSlug` text DEFAULT NULL,
  `pricingTier` text DEFAULT NULL,
  `cldImageUrl` text DEFAULT NULL,
  `courseImageUrl` text DEFAULT NULL,
  `courseImageThumbUrl` text DEFAULT NULL,
  `courseInformation` text CHARACTER SET utf8 COLLATE utf8_general_ci DEFAULT NULL,
  `marketingDescription` text CHARACTER SET utf8 COLLATE utf8_general_ci DEFAULT NULL,
  `courseOutline` text CHARACTER SET utf8 COLLATE utf8_general_ci DEFAULT NULL,
  `courseObjectives` text CHARACTER SET utf8 COLLATE utf8_general_ci DEFAULT NULL,
  `courseRegulations` text CHARACTER SET utf8 COLLATE utf8_general_ci DEFAULT NULL,
  `parentCldid` text CHARACTER SET utf8 COLLATE utf8_general_ci DEFAULT NULL,
  `date_backed_up` datetime DEFAULT NULL,
  `isRecommended` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`capdid`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;


DROP TABLE IF EXISTS `course_api_data_backup_staging`;
CREATE TABLE `course_api_data_backup_staging` (
  `capdid` int(14) unsigned NOT NULL AUTO_INCREMENT,
  `title` text DEFAULT NULL,
  `cldId` text DEFAULT NULL,
  `salesLibraryTopic` text DEFAULT NULL,
  `courseTopic` text DEFAULT NULL,
  `collections` text DEFAULT NULL,
  `vendorId` text DEFAULT NULL,
  `vendorName` text DEFAULT NULL,
  `libraryId` text DEFAULT NULL,
  `libraryName` text DEFAULT NULL,
  `lessonId` text DEFAULT NULL,
  `ej4CourseNumber` text DEFAULT NULL,
  `lessonModality` text DEFAULT NULL,
  `hsiProgramID` text DEFAULT NULL,
  `lessonLength` text DEFAULT NULL,
  `lessonAffiliations` text DEFAULT NULL,
  `locale` text DEFAULT NULL,
  `allLocales` text CHARACTER SET utf8 COLLATE utf8_unicode_ci DEFAULT NULL,
  `courseLanguageCategoriesSlug` text DEFAULT NULL,
  `pricingTier` text DEFAULT NULL,
  `cldImageUrl` text DEFAULT NULL,
  `courseImageUrl` text DEFAULT NULL,
  `courseImageThumbUrl` text DEFAULT NULL,
  `courseInformation` text CHARACTER SET utf8 COLLATE utf8_general_ci DEFAULT NULL,
  `marketingDescription` text CHARACTER SET utf8 COLLATE utf8_general_ci DEFAULT NULL,
  `courseOutline` text CHARACTER SET utf8 COLLATE utf8_general_ci DEFAULT NULL,
  `courseObjectives` text CHARACTER SET utf8 COLLATE utf8_general_ci DEFAULT NULL,
  `courseRegulations` text CHARACTER SET utf8 COLLATE utf8_general_ci DEFAULT NULL,
  `parentCldid` text CHARACTER SET utf8 COLLATE utf8_general_ci DEFAULT NULL,
  `date_backed_up` datetime DEFAULT NULL,
  `isRecommended` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`capdid`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;


DROP TABLE IF EXISTS `course_api_data_singles`;
CREATE TABLE `course_api_data_singles` (
  `capdid` int(14) unsigned NOT NULL AUTO_INCREMENT,
  `title` text CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `cldId` text CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `salesLibraryTopic` text CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `courseTopic` text CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `collections` text CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `vendorId` text CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `vendorName` text CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `libraryId` text CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `libraryName` text CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `lessonId` text CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `ej4CourseNumber` text CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `lessonModality` text CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `hsiProgramID` text CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `lessonLength` text CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `lessonAffiliations` text DEFAULT NULL,
  `locale` text CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `allLocales` text CHARACTER SET utf8 COLLATE utf8_unicode_ci DEFAULT NULL,
  `courseLanguageCategoriesSlug` text CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `pricingTier` text CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `cldImageUrl` text CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `courseImageUrl` text CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `courseImageThumbUrl` text CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `courseInformation` text DEFAULT NULL,
  `marketingDescription` text DEFAULT NULL,
  `courseOutline` text DEFAULT NULL,
  `courseObjectives` text DEFAULT NULL,
  `courseRegulations` text DEFAULT NULL,
  `parentCldid` text DEFAULT NULL,
  `isRecommended` varchar(255) DEFAULT NULL,
  `dateAdded` datetime DEFAULT NULL,
  PRIMARY KEY (`capdid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;


DROP TABLE IF EXISTS `course_api_data_staging`;
CREATE TABLE `course_api_data_staging` (
  `capdid` int(14) unsigned NOT NULL AUTO_INCREMENT,
  `title` text CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `cldId` text CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `salesLibraryTopic` text CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `courseTopic` text CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `collections` text CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `vendorId` text CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `vendorName` text CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `libraryId` text CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `libraryName` text CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `lessonId` text CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `ej4CourseNumber` text CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `lessonModality` text CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `hsiProgramID` text CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `lessonLength` text CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `lessonAffiliations` text DEFAULT NULL,
  `locale` text CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `allLocales` text CHARACTER SET utf8 COLLATE utf8_unicode_ci DEFAULT NULL,
  `courseLanguageCategoriesSlug` text CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `pricingTier` text CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `cldImageUrl` text CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `courseImageUrl` text CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `courseImageThumbUrl` text CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `courseInformation` text DEFAULT NULL,
  `marketingDescription` text DEFAULT NULL,
  `courseOutline` text DEFAULT NULL,
  `courseObjectives` text DEFAULT NULL,
  `courseRegulations` text DEFAULT NULL,
  `parentCldid` text DEFAULT NULL,
  `isRecommended` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`capdid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;


-- 2026-04-10 15:53:20
