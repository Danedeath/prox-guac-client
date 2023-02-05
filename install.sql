CREATE DATABASE  IF NOT EXISTS `dashboard` /*!40100 DEFAULT CHARACTER SET utf8mb4 */;
USE `dashboard`;
-- MySQL dump 10.13  Distrib 8.0.30, for Win64 (x86_64)
--
-- Host: 192.168.30.25    Database: dashboard
-- ------------------------------------------------------
-- Server version	5.7.39

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `connections`
--

DROP TABLE IF EXISTS `connections`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `connections` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(45) NOT NULL,
  `owner` varchar(45) DEFAULT NULL,
  `os` varchar(45) DEFAULT NULL,
  `hostname` varchar(45) NOT NULL,
  `port` int(11) NOT NULL,
  `protocol` varchar(4) NOT NULL,
  `node` varchar(45) DEFAULT NULL,
  `username` varchar(256) NOT NULL,
  `password` varchar(256) NOT NULL,
  `drive` varchar(60) DEFAULT NULL,
  `sharedwith` json DEFAULT NULL,
  `creation` datetime DEFAULT CURRENT_TIMESTAMP,
  `lastactive` datetime DEFAULT NULL,
  `modified` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`,`name`),
  UNIQUE KEY `name_UNIQUE` (`name`),
  KEY `connOwnerID_idx` (`owner`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;


--
-- Table structure for table `permissions`
--

DROP TABLE IF EXISTS `permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `permissions` (
  `key` varchar(20) NOT NULL,
  `word` varchar(45) NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `permissions`
--

LOCK TABLES `permissions` WRITE;
/*!40000 ALTER TABLE `permissions` DISABLE KEYS */;
INSERT INTO `permissions` VALUES ('admin','Admin Acccess'),('connections','Connection Management'),('conn_addshare','Add Connnection Users'),('conn_clone','Clone Connections'),('conn_conn','Connect to Connections'),('conn_cpus','Edit Connection CPUs'),('conn_create','Create Connections'),('conn_delete','Delete Connections'),('conn_edit','Modify Connections'),('conn_memory','Edit Connection Memory'),('conn_node','Move Connections'),('conn_resetpass','Reset Connection Passwords'),('conn_revert','Revert Connections'),('conn_rmshare','Remove Connection Users'),('conn_snap','Snapshot Connections'),('conn_status','Change Connection Status'),('conn_viewip','View Connection IPs'),('profile','User Profile Access'),('roles','Role Management'),('role_addperm','Add new Role perms'),('role_adduser','Add Users to Roles'),('role_create','Create Roles'),('role_delete','Delete Roles'),('role_edit','Modify Roles'),('role_rmperm','Remove role perms'),('role_rmuser','Remove Users from Roles'),('settings','Settings Management'),('sett_create','Create new settings'),('sett_disable','Disable Settings'),('sett_edit','Modify Settings'),('sett_enable','Enable Settings'),('userman','User Management'),('userman_addperm','Add User Permissions'),('userman_conn','View User Connections'),('userman_create','Create Users'),('userman_delete','Delete Users'),('userman_edit','Edit Users'),('userman_perm','User Permission Access'),('userman_reset','Reset User Passwords'),('userman_rmperm','Remove User Permissions'),('userman_vms','User Console Access');
/*!40000 ALTER TABLE `permissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `reset_tokens`
--

DROP TABLE IF EXISTS `reset_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `reset_tokens` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user` varchar(45) NOT NULL,
  `token` varchar(45) NOT NULL,
  `expires` int(11) DEFAULT NULL,
  `valid` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `token_users_idx` (`user`),
  CONSTRAINT `token_users` FOREIGN KEY (`user`) REFERENCES `users` (`username`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `reset_tokens`
--

LOCK TABLES `reset_tokens` WRITE;
/*!40000 ALTER TABLE `reset_tokens` DISABLE KEYS */;
/*!40000 ALTER TABLE `reset_tokens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `role_permissions`
--

DROP TABLE IF EXISTS `role_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `role_permissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `role_id` int(11) NOT NULL,
  `admin` int(1) DEFAULT '0',
  `userman` int(1) DEFAULT '0',
  `connections` int(1) DEFAULT '0',
  `settings` int(1) DEFAULT '0',
  `roles` int(1) DEFAULT '0',
  `profile` int(1) DEFAULT '0',
  `userman_create` int(1) DEFAULT '0',
  `userman_delete` int(1) DEFAULT '0',
  `userman_edit` int(1) DEFAULT '0',
  `userman_reset` int(1) DEFAULT '0',
  `userman_vms` int(1) DEFAULT '0',
  `userman_conn` int(1) DEFAULT '0',
  `userman_perm` int(1) DEFAULT '0',
  `userman_addperm` int(1) DEFAULT '0',
  `userman_rmperm` int(1) DEFAULT '0',
  `conn_create` int(1) DEFAULT '0',
  `conn_delete` int(1) DEFAULT '0',
  `conn_edit` int(1) DEFAULT '0',
  `conn_snap` int(1) DEFAULT '0',
  `conn_revert` int(1) DEFAULT '0',
  `conn_clone` int(1) DEFAULT '0',
  `conn_resetpass` int(1) DEFAULT '0',
  `conn_memory` int(1) DEFAULT '0',
  `conn_cpus` int(1) DEFAULT '0',
  `conn_status` int(1) DEFAULT '0',
  `conn_node` int(1) DEFAULT '0',
  `conn_viewip` int(1) DEFAULT '0',
  `conn_addshare` int(1) DEFAULT '0',
  `conn_rmshare` int(1) DEFAULT '0',
  `conn_conn` int(1) DEFAULT '0',
  `role_create` int(1) DEFAULT '0',
  `role_delete` int(1) DEFAULT '0',
  `role_edit` int(1) DEFAULT '0',
  `role_adduser` int(1) DEFAULT '0',
  `role_rmuser` int(1) DEFAULT '0',
  `role_addperm` int(1) DEFAULT '0',
  `role_rmperm` int(1) DEFAULT '0',
  `sett_edit` int(1) DEFAULT '0',
  `sett_create` int(1) DEFAULT '0',
  `sett_enable` int(1) DEFAULT '0',
  `sett_disable` int(1) DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `RoleID_idx` (`role_id`),
  CONSTRAINT `RoleID` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `role_permissions`
--

LOCK TABLES `role_permissions` WRITE;
/*!40000 ALTER TABLE `role_permissions` DISABLE KEYS */;
INSERT INTO `role_permissions` VALUES (1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1),(2,2,1,1,0,1,0,1,0,0,1,1,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0);
/*!40000 ALTER TABLE `role_permissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `roles`
--

DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `roles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(45) DEFAULT NULL,
  `description` tinytext,
  `creation` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name_UNIQUE` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COMMENT='The collection of roles containing their names, a short description, and their permission ident';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `roles`
--

LOCK TABLES `roles` WRITE;
/*!40000 ALTER TABLE `roles` DISABLE KEYS */;
INSERT INTO `roles` VALUES (1,'Administrator','the default admin group for the dashboard','2022-12-29 16:59:23','2022-12-29 16:59:23'),(2,'Users','the default user group for the dashboard','2022-12-29 16:59:23','2022-12-29 16:59:23');
/*!40000 ALTER TABLE `roles` ENABLE KEYS */;
UNLOCK TABLES;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `dashboard`.`role_AFTER_INSERT` AFTER INSERT ON `roles` FOR EACH ROW
BEGIN
insert into role_permssions (role_id) values (new.id);
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Table structure for table `settings`
--

DROP TABLE IF EXISTS `settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `settings` (
  `name` varchar(45) NOT NULL,
  `display` varchar(45) NOT NULL,
  `group` varchar(45) NOT NULL,
  `value` varchar(255) DEFAULT NULL,
  `tip` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `settings`
--

LOCK TABLES `settings` WRITE;
/*!40000 ALTER TABLE `settings` DISABLE KEYS */;
INSERT INTO `settings` VALUES ('cape_sandbox','Cape Sandbox','gen','','Sandbox URL'),('default_store','Default Storage','prox','','Proxmox storage device to use'),('gaucd_port','Port','guacd','','GuacD server port'),('guacd_drive','Drive Path','guacd','','GuacD shared drive'),('guacd_enc_key','Encryption Key','guacd','','GuacD encryption key'),('guacd_host','Hostname','guacd','','GuacD server hostname'),('guacd_secret','Secret','guacd','','GuacD host auth key'),('login','Enable Logon','tog','1','Enable/Disable if you want users to be able to logon'),('max_inst','Max Machines','gen','5','Maximum number of VMs a user can have'),('online','Dashboard Status','tog','1','User frontend access'),('proxy','Proxy Support','tog','1','Enable if  dashboard is behind a proxy'),('prox_host','Hostname','prox','','Proxmox Hostname'),('prox_key','Token','prox','','Proxmox API token'),('prox_key_id','Token Name','prox','','Proxmox API token name'),('prox_pass','Token User Pass','prox','','Proxmox user password'),('prox_port','Port','prox','','Proxmox web port'),('prox_user','Token Username','prox','','Prxomox user'),('title','Title','gen','','Title of the dashboard');
/*!40000 ALTER TABLE `settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_permissions`
--

DROP TABLE IF EXISTS `user_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_permissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `admin` int(1) DEFAULT '0',
  `userman` int(1) DEFAULT '0',
  `connections` int(1) DEFAULT '0',
  `settings` int(1) DEFAULT '0',
  `roles` int(1) DEFAULT '0',
  `profile` int(1) DEFAULT '0',
  `userman_create` int(1) DEFAULT '0',
  `userman_delete` int(1) DEFAULT '0',
  `userman_edit` int(1) DEFAULT '0',
  `userman_reset` int(1) DEFAULT '0',
  `userman_vms` int(1) DEFAULT '0',
  `userman_conn` int(1) DEFAULT '0',
  `userman_perm` int(1) DEFAULT '0',
  `userman_addperm` int(1) DEFAULT '0',
  `userman_rmperm` int(1) DEFAULT '0',
  `conn_create` int(1) DEFAULT '0',
  `conn_delete` int(1) DEFAULT '0',
  `conn_edit` int(1) DEFAULT '0',
  `conn_snap` int(1) DEFAULT '0',
  `conn_revert` int(1) DEFAULT '0',
  `conn_clone` int(1) DEFAULT '0',
  `conn_resetpass` int(1) DEFAULT '0',
  `conn_memory` int(1) DEFAULT '0',
  `conn_cpus` int(1) DEFAULT '0',
  `conn_status` int(1) DEFAULT '0',
  `conn_node` int(1) DEFAULT '0',
  `conn_viewip` int(1) DEFAULT '0',
  `conn_addshare` int(1) DEFAULT '0',
  `conn_rmshare` int(1) DEFAULT '0',
  `conn_conn` int(1) DEFAULT '0',
  `role_create` int(1) DEFAULT '0',
  `role_delete` int(1) DEFAULT '0',
  `role_edit` int(1) DEFAULT '0',
  `role_adduser` int(1) DEFAULT '0',
  `role_rmuser` int(1) DEFAULT '0',
  `role_addperm` int(1) DEFAULT '0',
  `role_rmperm` int(1) DEFAULT '0',
  `sett_edit` int(1) DEFAULT '0',
  `sett_create` int(1) DEFAULT '0',
  `sett_enable` int(1) DEFAULT '0',
  `sett_disable` int(1) DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `userPermID_idx` (`user_id`),
  CONSTRAINT `userPerm` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_permissions`
--

LOCK TABLES `user_permissions` WRITE;
/*!40000 ALTER TABLE `user_permissions` DISABLE KEYS */;
INSERT INTO `user_permissions` VALUES (1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,0,0,0),(2,2,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0),(3,3,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0);
/*!40000 ALTER TABLE `user_permissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_roles`
--

DROP TABLE IF EXISTS `user_roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_roles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `role_id` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `userRoleIDs_idx` (`user_id`),
  KEY `userRolesRoleID_idx` (`role_id`),
  CONSTRAINT `userRolesRoleID` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION,
  CONSTRAINT `userRolesUserID` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COMMENT='The table containing all the roles for each user';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_roles`
--

LOCK TABLES `user_roles` WRITE;
/*!40000 ALTER TABLE `user_roles` DISABLE KEYS */;
INSERT INTO `user_roles` VALUES (1,1,2),(1,1,1);
/*!40000 ALTER TABLE `user_roles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_sessions`
--

DROP TABLE IF EXISTS `user_sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_sessions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `session_id` varchar(45) NOT NULL,
  `user_ip` varchar(45) NOT NULL,
  `start_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expires` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(45) NOT NULL,
  `email` varchar(45) NOT NULL,
  `password` longtext NOT NULL,
  `reg_date` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_login` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `status` int(1) NOT NULL DEFAULT '1',
  `is_locked` int(1) NOT NULL DEFAULT '0',
  `attempts` int(2) NOT NULL DEFAULT '0',
  `locked_date` datetime DEFAULT NULL,
  PRIMARY KEY (`id`,`email`),
  KEY `usernames` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'admin','admin@pap.local','$2y$10$tohlm9jG7yB2lqhK2WY2u.N8O.6J2lypb5YQ7aA1sruDPA34Fvx6e','2023-01-06 01:28:23','2023-02-05 11:50:13',1,0,0,NULL);
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION' */ ;

DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `dashboard`.`users_AFTER_INSERT` AFTER INSERT ON `users` FOR EACH ROW
BEGIN
insert into user_permissions (user_id) values (new.id);
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Dumping events for database 'dashboard'
--

--
-- Dumping routines for database 'dashboard'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2023-02-05 12:03:23
