-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: zbb
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Current Database: `zbb`
--

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `zbb` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci */;

USE `zbb`;

--
-- Table structure for table `abrechnung_has_fartens`
--

DROP TABLE IF EXISTS `abrechnung_has_fartens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `abrechnung_has_fartens` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `abrechnung_id` bigint(20) unsigned NOT NULL,
  `fahrt_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `abrechnung_has_fartens_abrechnung_id_foreign` (`abrechnung_id`),
  KEY `abrechnung_has_fartens_fahrt_id_foreign` (`fahrt_id`),
  CONSTRAINT `abrechnung_has_fartens_abrechnung_id_foreign` FOREIGN KEY (`abrechnung_id`) REFERENCES `abrechnungens` (`id`) ON DELETE CASCADE,
  CONSTRAINT `abrechnung_has_fartens_fahrt_id_foreign` FOREIGN KEY (`fahrt_id`) REFERENCES `fahrtens` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
