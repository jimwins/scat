-- MySQL dump 10.13  Distrib 5.6.11, for osx10.6 (x86_64)
--
-- Host: localhost    Database: scat
-- ------------------------------------------------------
-- Server version	5.6.11

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `barcode`
--

DROP TABLE IF EXISTS `barcode`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `barcode` (
  `code` varchar(255) NOT NULL,
  `item` int(10) unsigned NOT NULL,
  `quantity` int(10) unsigned NOT NULL DEFAULT '1',
  PRIMARY KEY (`item`,`code`),
  KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `brand`
--

DROP TABLE IF EXISTS `brand`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `brand` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `hostedpayment_txn`
--

DROP TABLE IF EXISTS `hostedpayment_txn`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `hostedpayment_txn` (
  `txn` int(10) unsigned NOT NULL,
  `hostedpayment` varchar(255) NOT NULL,
  `validationcode` varchar(50) NOT NULL,
  `created` datetime NOT NULL
) ENGINE=MEMORY DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `item`
--

DROP TABLE IF EXISTS `item`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `item` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `brand` int(10) unsigned DEFAULT NULL,
  `retail_price` decimal(9,2) NOT NULL DEFAULT '0.00',
  `discount_type` enum('percentage','relative','fixed') DEFAULT NULL,
  `discount` decimal(9,2) DEFAULT NULL,
  `taxfree` tinyint(4) NOT NULL DEFAULT '0',
  `minimum_quantity` int(10) unsigned NOT NULL DEFAULT '1',
  `active` tinyint(1) NOT NULL,
  `deleted` tinyint(4) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `payment`
--

DROP TABLE IF EXISTS `payment`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `payment` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `txn` int(10) unsigned NOT NULL,
  `method` enum('cash','change','credit','square','gift','check','dwolla','discount','withdrawal','bad','donation','internal') NOT NULL,
  `amount` decimal(9,3) NOT NULL,
  `cc_txn` varchar(10) DEFAULT NULL,
  `cc_approval` varchar(30) DEFAULT NULL,
  `cc_lastfour` varchar(4) DEFAULT NULL,
  `cc_expire` varchar(4) DEFAULT NULL,
  `cc_type` varchar(32) DEFAULT NULL,
  `discount` decimal(9,2) DEFAULT NULL,
  `processed` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `txn` (`txn`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `person`
--

DROP TABLE IF EXISTS `person`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `person` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `role` enum('customer','employee','vendor') DEFAULT 'customer',
  `name` varchar(255) DEFAULT NULL,
  `company` varchar(255) DEFAULT NULL,
  `address` text,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(255) DEFAULT NULL,
  `tax_id` varchar(255) DEFAULT NULL,
  `payment_account_id` varchar(50) DEFAULT NULL,
  `active` tinyint(1) NOT NULL,
  `deleted` tinyint(4) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `timeclock`
--

DROP TABLE IF EXISTS `timeclock`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `timeclock` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `person` int(10) unsigned NOT NULL,
  `start` datetime NOT NULL,
  `end` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `timeclock_audit`
--

DROP TABLE IF EXISTS `timeclock_audit`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `timeclock_audit` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `entry` int(10) unsigned DEFAULT NULL,
  `before_start` datetime DEFAULT NULL,
  `after_start` datetime DEFAULT NULL,
  `before_end` datetime DEFAULT NULL,
  `after_end` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `txn`
--

DROP TABLE IF EXISTS `txn`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `txn` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `number` int(10) unsigned NOT NULL,
  `created` datetime NOT NULL,
  `filled` datetime DEFAULT NULL,
  `paid` datetime DEFAULT NULL,
  `type` enum('correction','vendor','customer','drawer') NOT NULL,
  `person` int(10) unsigned DEFAULT NULL,
  `tax_rate` decimal(9,3) NOT NULL,
  `returned_from` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `type` (`type`,`number`),
  KEY `created` (`created`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `txn_line`
--

DROP TABLE IF EXISTS `txn_line`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `txn_line` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `txn` int(10) unsigned NOT NULL,
  `line` int(10) unsigned DEFAULT NULL,
  `item` int(10) unsigned DEFAULT NULL,
  `ordered` int(11) NOT NULL,
  `allocated` int(11) NOT NULL DEFAULT '0',
  `override_name` varchar(255) DEFAULT NULL,
  `retail_price` decimal(9,2) NOT NULL,
  `discount_type` enum('percentage','relative','fixed') DEFAULT NULL,
  `discount` decimal(9,2) DEFAULT NULL,
  `discount_manual` tinyint(4) NOT NULL DEFAULT '0',
  `taxfree` tinyint(4) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `txn` (`txn`,`line`),
  KEY `item` (`item`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `txn_note`
--

DROP TABLE IF EXISTS `txn_note`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `txn_note` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `txn` int(10) unsigned NOT NULL,
  `entered` datetime NOT NULL,
  `content` text,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `vendor_item`
--

DROP TABLE IF EXISTS `vendor_item`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `vendor_item` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `vendor` int(10) unsigned NOT NULL,
  `item` int(10) unsigned DEFAULT NULL,
  `code` varchar(255) DEFAULT NULL,
  `name` varchar(255) DEFAULT NULL,
  `retail_price` decimal(9,2) NOT NULL,
  `net_price` decimal(9,2) NOT NULL,
  `promo_price` decimal(9,2) DEFAULT NULL,
  `barcode` varchar(20) DEFAULT NULL,
  `purchase_quantity` int(11) NOT NULL,
  `category` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `item` (`item`),
  KEY `vendor` (`vendor`),
  KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping routines for database 'scat'
--
/*!50003 DROP FUNCTION IF EXISTS `ROUND_TO_EVEN` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8 */ ;
/*!50003 SET character_set_results = utf8 */ ;
/*!50003 SET collation_connection  = utf8_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = '' */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` FUNCTION `ROUND_TO_EVEN`(value decimal(32,16), places int) RETURNS decimal(32,16)
BEGIN  RETURN IF(ABS(value - TRUNCATE(value, places)) * POWER(10, places + 1) = 5            AND NOT CONVERT(TRUNCATE(abs(value) * POWER(10, places), 0),                            UNSIGNED) % 2 = 1,            TRUNCATE(value, places), ROUND(value, places));
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 DROP FUNCTION IF EXISTS `sale_price` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8 */ ;
/*!50003 SET character_set_results = utf8 */ ;
/*!50003 SET collation_connection  = utf8_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = '' */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` FUNCTION `sale_price`(retail_price decimal(9,2), type char(32), discount decimal(9,2)) RETURNS decimal(9,2)
BEGIN   RETURN IF(type IS NOT NULL AND type != '',             CASE type             WHEN 'percentage' THEN               CAST(ROUND_TO_EVEN(retail_price * ((100 - discount) / 100), 2) AS DECIMAL(9,2))             WHEN 'relative' THEN               (retail_price - discount)             WHEN 'fixed' THEN               (discount)             END,             retail_price); END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2014-02-07 15:36:25
