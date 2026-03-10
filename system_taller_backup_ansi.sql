-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: system_taller
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
-- Table structure for table `anexo_tools`
--

DROP TABLE IF EXISTS `anexo_tools`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `anexo_tools` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `anexo_id` int(11) NOT NULL,
  `row_index` int(11) NOT NULL COMMENT 'Posici??n del 1 al 15',
  `tool_id` int(11) DEFAULT NULL COMMENT 'ID de la herramienta si viene de bodega',
  `custom_description` varchar(255) DEFAULT NULL COMMENT 'Descripci??n manual si no es herramienta de bodega',
  `quantity` decimal(10,2) NOT NULL DEFAULT 1.00,
  PRIMARY KEY (`id`),
  KEY `anexo_id` (`anexo_id`),
  KEY `tool_id` (`tool_id`),
  CONSTRAINT `anexo_tools_ibfk_1` FOREIGN KEY (`anexo_id`) REFERENCES `anexos_yazaki` (`id`) ON DELETE CASCADE,
  CONSTRAINT `anexo_tools_ibfk_2` FOREIGN KEY (`tool_id`) REFERENCES `tools` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `anexo_tools`
--

LOCK TABLES `anexo_tools` WRITE;
/*!40000 ALTER TABLE `anexo_tools` DISABLE KEYS */;
INSERT INTO `anexo_tools` VALUES (2,3,1,1,'Taladro',1.00),(3,4,1,1,'Taladro',1.00);
/*!40000 ALTER TABLE `anexo_tools` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `anexos_yazaki`
--

DROP TABLE IF EXISTS `anexos_yazaki`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `anexos_yazaki` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `survey_id` int(11) DEFAULT NULL COMMENT 'Vinculado a un levantamiento opcionalmente',
  `user_id` int(11) NOT NULL COMMENT 'Usuario que cre?? el anexo',
  `client_name` varchar(255) DEFAULT NULL COMMENT 'Nombre de empresa receptora',
  `status` enum('draft','generated') DEFAULT 'draft',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `survey_id` (`survey_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `anexos_yazaki_ibfk_1` FOREIGN KEY (`survey_id`) REFERENCES `project_surveys` (`id`) ON DELETE SET NULL,
  CONSTRAINT `anexos_yazaki_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `anexos_yazaki`
--

LOCK TABLES `anexos_yazaki` WRITE;
/*!40000 ALTER TABLE `anexos_yazaki` DISABLE KEYS */;
INSERT INTO `anexos_yazaki` VALUES (3,NULL,1,'YAZAKI DE NICARAGUA SA','generated','2026-02-28 18:23:15'),(4,NULL,1,'YAZAKI DE NICARAGUA SA','generated','2026-03-02 21:02:17');
/*!40000 ALTER TABLE `anexos_yazaki` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `audit_logs`
--

DROP TABLE IF EXISTS `audit_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `audit_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `table_name` varchar(50) NOT NULL,
  `record_id` int(11) NOT NULL,
  `action` enum('INSERT','UPDATE','DELETE','EDICION','UPDATE STATUS') NOT NULL,
  `old_value` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `new_value` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `table_record` (`table_name`,`record_id`),
  CONSTRAINT `audit_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=32 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `audit_logs`
--

LOCK TABLES `audit_logs` WRITE;
/*!40000 ALTER TABLE `audit_logs` DISABLE KEYS */;
INSERT INTO `audit_logs` VALUES (1,'users',1,'UPDATE',NULL,'{\"event\":\"login\"}',1,'127.0.0.1','2026-02-27 19:49:36'),(2,'users',20,'UPDATE',NULL,'{\"event\":\"login\"}',20,'127.0.0.1','2026-02-27 20:00:23'),(3,'clients',1,'INSERT',NULL,'{\"name\":\"Test Client\",\"tax_id\":\"123456789\",\"phone\":\"\",\"email\":\"\",\"address\":\"\"}',20,'127.0.0.1','2026-02-27 20:01:07'),(4,'users',1,'UPDATE',NULL,'{\"event\":\"login\"}',1,'127.0.0.1','2026-02-27 20:08:15'),(5,'users',1,'UPDATE',NULL,'{\"event\":\"login\"}',1,'127.0.0.1','2026-02-27 20:13:01'),(6,'users',1,'UPDATE',NULL,'{\"event\":\"login\"}',1,'127.0.0.1','2026-02-27 20:13:42'),(7,'users',1,'UPDATE',NULL,'{\"event\":\"login\"}',1,'127.0.0.1','2026-02-28 14:49:54'),(8,'users',1,'UPDATE',NULL,'{\"event\":\"login\"}',1,'127.0.0.1','2026-03-02 14:18:26'),(9,'users',1,'UPDATE',NULL,'{\"event\":\"login\"}',1,'127.0.0.1','2026-03-02 15:13:27'),(10,'users',20,'UPDATE',NULL,'{\"event\":\"login\"}',20,'127.0.0.1','2026-03-02 15:13:49'),(11,'users',1,'UPDATE',NULL,'{\"event\":\"login\"}',1,'127.0.0.1','2026-03-02 15:21:21'),(12,'users',1,'UPDATE',NULL,'{\"event\":\"login\"}',1,'127.0.0.1','2026-03-03 15:33:03'),(13,'users',20,'UPDATE',NULL,'{\"event\":\"login\"}',20,'127.0.0.1','2026-03-03 19:29:37'),(14,'users',1,'UPDATE',NULL,'{\"event\":\"login\"}',1,'127.0.0.1','2026-03-04 16:17:55'),(15,'users',1,'UPDATE',NULL,'{\"event\":\"login\"}',1,'127.0.0.1','2026-03-04 17:49:07'),(16,'users',20,'UPDATE',NULL,'{\"event\":\"login\"}',20,'127.0.0.1','2026-03-04 17:49:52'),(17,'users',1,'UPDATE',NULL,'{\"event\":\"login\"}',1,'127.0.0.1','2026-03-04 17:54:09'),(18,'project_surveys',1,'',NULL,'\"approved\"',1,'127.0.0.1','2026-03-04 20:27:11'),(19,'project_surveys',1,'',NULL,'\"approved\"',1,'127.0.0.1','2026-03-04 20:28:00'),(20,'users',1,'UPDATE',NULL,'{\"event\":\"login\"}',1,'127.0.0.1','2026-03-04 21:40:55'),(21,'users',1,'UPDATE',NULL,'{\"event\":\"login\"}',1,'127.0.0.1','2026-03-04 21:43:11'),(22,'users',1,'UPDATE',NULL,'{\"event\":\"login\"}',1,'127.0.0.1','2026-03-05 17:29:16'),(23,'users',20,'UPDATE',NULL,'{\"event\":\"login\"}',20,'127.0.0.1','2026-03-05 17:30:25'),(24,'project_surveys',1,'',NULL,'\"draft\"',1,'127.0.0.1','2026-03-05 20:23:05'),(25,'users',1,'UPDATE',NULL,'{\"event\":\"login\"}',1,'127.0.0.1','2026-03-06 15:02:07'),(26,'users',1,'UPDATE',NULL,'{\"event\":\"login\"}',1,'127.0.0.1','2026-03-06 15:16:11'),(27,'2',0,'UPDATE STATUS','\"draft\"','\"in_progress\"',1,'127.0.0.1','2026-03-06 15:31:26'),(28,'2',0,'UPDATE STATUS','\"pendiente\"','\"pagado (Comisi\\u00f3n T\\u00e9c. Generada)\"',1,'127.0.0.1','2026-03-06 15:32:06'),(29,'2',0,'UPDATE STATUS','\"in_progress\"','\"approved\"',1,'127.0.0.1','2026-03-06 16:01:04'),(30,'1',0,'UPDATE STATUS','\"draft\"','\"approved\"',1,'127.0.0.1','2026-03-06 16:01:20'),(31,'users',1,'UPDATE',NULL,'{\"event\":\"login\"}',1,'127.0.0.1','2026-03-06 17:30:27');
/*!40000 ALTER TABLE `audit_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `clients`
--

DROP TABLE IF EXISTS `clients`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `clients` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `tax_id` varchar(50) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `is_third_party` tinyint(1) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `clients`
--

LOCK TABLES `clients` WRITE;
/*!40000 ALTER TABLE `clients` DISABLE KEYS */;
INSERT INTO `clients` VALUES (1,'Test Client','123456789','','','',0,'2026-02-27 20:01:07'),(2,'asdasd','asdasdas','asdasda','asdasd@asda.com','asdasd',1,'2026-03-05 19:15:37');
/*!40000 ALTER TABLE `clients` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `comisiones`
--

DROP TABLE IF EXISTS `comisiones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `comisiones` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `fecha_servicio` date NOT NULL,
  `fecha_facturacion` date DEFAULT NULL,
  `cliente` varchar(255) NOT NULL,
  `servicio` varchar(255) NOT NULL,
  `cantidad` int(11) NOT NULL DEFAULT 1,
  `tipo` enum('SERVICIO','PROYECTO') NOT NULL,
  `lugar` varchar(255) DEFAULT NULL,
  `factura` varchar(100) DEFAULT NULL,
  `vendedor` varchar(255) NOT NULL,
  `caso` varchar(100) NOT NULL,
  `estado` enum('PENDIENTE','PAGADA') NOT NULL DEFAULT 'PENDIENTE',
  `fecha_pago` date DEFAULT NULL,
  `notas` text DEFAULT NULL,
  `tech_id` int(11) NOT NULL,
  `reference_id` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `comisiones`
--

LOCK TABLES `comisiones` WRITE;
/*!40000 ALTER TABLE `comisiones` DISABLE KEYS */;
INSERT INTO `comisiones` VALUES (2,'2026-03-04',NULL,'Constructora Demo','Instalaci??n de C??maras IP',1,'PROYECTO',NULL,NULL,'tecnico','Proyecto_#1','PENDIENTE',NULL,NULL,20,1,'2026-03-04 15:09:04'),(3,'2026-03-05',NULL,'asdasd','PC qweqwrfasd ',1,'SERVICIO',NULL,NULL,'tecnico','Servicio_#0002','PENDIENTE',NULL,NULL,20,2,'2026-03-05 13:19:19'),(4,'2026-03-06',NULL,'Empresa de Prueba S.A.','Proyecto Test para Edici??n',1,'PROYECTO',NULL,NULL,'superadmin','Proyecto_#0002','PENDIENTE',NULL,NULL,1,2,'2026-03-06 09:32:06');
/*!40000 ALTER TABLE `comisiones` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `diagnosis_images`
--

DROP TABLE IF EXISTS `diagnosis_images`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `diagnosis_images` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `service_order_id` int(11) NOT NULL,
  `image_path` varchar(255) NOT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `service_order_id` (`service_order_id`),
  CONSTRAINT `diagnosis_images_ibfk_1` FOREIGN KEY (`service_order_id`) REFERENCES `service_orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `diagnosis_images`
--

LOCK TABLES `diagnosis_images` WRITE;
/*!40000 ALTER TABLE `diagnosis_images` DISABLE KEYS */;
/*!40000 ALTER TABLE `diagnosis_images` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `equipments`
--

DROP TABLE IF EXISTS `equipments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `equipments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `client_id` int(11) NOT NULL,
  `brand` varchar(500) DEFAULT NULL,
  `model` varchar(50) DEFAULT NULL,
  `submodel` varchar(100) DEFAULT NULL,
  `serial_number` varchar(50) DEFAULT NULL,
  `type` varchar(50) DEFAULT NULL COMMENT 'Laptop, Desktop, Printer, etc.',
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `client_id` (`client_id`),
  CONSTRAINT `equipments_ibfk_1` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `equipments`
--

LOCK TABLES `equipments` WRITE;
/*!40000 ALTER TABLE `equipments` DISABLE KEYS */;
INSERT INTO `equipments` VALUES (1,1,'MarcaPrueba','ModeloPrueba',NULL,'SN12345','Laptop',NULL),(2,2,'qweqwrfasd','','','asdgqwreasd','PC','2026-03-05 19:15:37');
/*!40000 ALTER TABLE `equipments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `permissions`
--

DROP TABLE IF EXISTS `permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `permissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(50) NOT NULL,
  `description` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=49 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `permissions`
--

LOCK TABLES `permissions` WRITE;
/*!40000 ALTER TABLE `permissions` DISABLE KEYS */;
INSERT INTO `permissions` VALUES (1,'module_dashboard','Acceso al Dashboard'),(2,'module_clients','Gesti??????n de Clientes'),(3,'module_equipment','Gesti??????n de Equipos'),(4,'module_tools','Gesti??????n de Herramientas'),(5,'module_services','Gesti??????n de Servicios'),(6,'module_warranties','Gesti??????n de Garant?????as'),(7,'module_history','Ver Historial'),(8,'module_users','Gesti??????n de Usuarios'),(9,'module_reports','Ver Reportes'),(10,'module_settings','Configuraci??????n del Sistema'),(11,'module_new_warranty','Registrar Nueva Garant?????a'),(12,'equipment_reentry','Permitir reingreso de equipos en salida'),(13,'module_users_delete','Eliminar Usuarios'),(14,'tools','Acceso al m??????dulo de herramientas'),(15,'module_equipment_entry','Registrar Entrada'),(16,'module_equipment_exit','Registrar Salida'),(17,'module_settings_general','Config. General'),(18,'module_settings_roles','Gesti??????n de Roles'),(19,'module_settings_modules','Control de M??????dulos'),(20,'module_settings_users','Gesti??????n de Usuarios (Admin)'),(21,'module_settings_restore','Restaurar Sistema'),(22,'module_re_enter_workshop','Reingresar a Taller'),(23,'module_clients_delete','Eliminar Clientes'),(24,'module_view_all_entries','Ver todos los equipos ingresados (sin estar asignados)'),(29,'surveys_status','Levantamientos: Cambiar estado (Aprobar)'),(31,'project_history','Historial de Proyectos'),(32,'anexos','Gesti??n de Anexos Yazaki'),(33,'module_surveys','Levantamientos'),(34,'module_project_history','Historial Proyectos'),(35,'module_anexos','Anexos Yazaki'),(36,'module_surveys_add','Crear Levantamiento'),(37,'module_surveys_edit','Editar Levantamiento'),(38,'module_surveys_delete','Eliminar Levantamiento'),(39,'module_surveys_view_all','Ver Levantamientos de Todos'),(40,'module_viaticos','Acceso m??dulo Vi??ticos'),(41,'module_viaticos_add','Crear Vi??tico'),(42,'module_viaticos_edit','Editar Vi??tico'),(43,'module_viaticos_delete','Eliminar Vi??tico'),(44,'module_comisiones','Acceso al m??dulo de Comisiones'),(45,'module_assign_equipment','Asignar / Reasignar Equipos'),(46,'module_comisiones_add','Crear Comision Manual'),(47,'module_comisiones_edit','Editar Comision'),(48,'module_comisiones_delete','Eliminar Comision');
/*!40000 ALTER TABLE `permissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `project_materials`
--

DROP TABLE IF EXISTS `project_materials`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `project_materials` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `survey_id` int(11) NOT NULL,
  `item_description` varchar(255) NOT NULL,
  `quantity` decimal(10,2) DEFAULT 1.00,
  `unit` varchar(20) DEFAULT 'unidades',
  `notes` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `survey_id` (`survey_id`),
  CONSTRAINT `fk_material_survey` FOREIGN KEY (`survey_id`) REFERENCES `project_surveys` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `project_materials`
--

LOCK TABLES `project_materials` WRITE;
/*!40000 ALTER TABLE `project_materials` DISABLE KEYS */;
INSERT INTO `project_materials` VALUES (1,2,'Bobina UTP Cat6',1.00,'Caja',NULL),(2,2,'C??maras IP 4MP',4.00,'Unidad',NULL),(3,3,'C??mara IP Domo 2MP',7.00,'unidades',''),(4,3,'C??mara IP Bullet 2MP',1.00,'unidades',''),(5,3,'Radio Enlace 5GHz',2.00,'unidades',''),(6,3,'Switch PoE',1.00,'unidades',''),(7,3,'UPS Interactivo',1.00,'unidades',''),(8,3,'Bandeja de Rack',1.00,'unidades',''),(9,3,'Cable de Red UTP Cap6',1.00,'unidades',''),(10,3,'Escalerilla Met??lica',1.00,'unidades',''),(11,3,'Velcro',1.00,'unidades',''),(12,3,'Conectores RJ45',25.00,'unidades',''),(13,3,'Bridas Pl??sticas',1.00,'unidades',''),(14,3,'Tubo PVC 3/4',10.00,'unidades',''),(15,3,'Cajas met??licas 4x4',5.00,'unidades','');
/*!40000 ALTER TABLE `project_materials` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `project_surveys`
--

DROP TABLE IF EXISTS `project_surveys`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `project_surveys` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `client_name` varchar(255) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `general_description` text DEFAULT NULL,
  `scope_activities` text DEFAULT NULL,
  `estimated_time` varchar(100) DEFAULT NULL,
  `personnel_required` varchar(100) DEFAULT NULL,
  `status` enum('draft','submitted','approved','in_progress','completed') DEFAULT 'draft',
  `payment_status` enum('pendiente','pagado') NOT NULL DEFAULT 'pendiente',
  `invoice_number` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `fk_survey_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `project_surveys`
--

LOCK TABLES `project_surveys` WRITE;
/*!40000 ALTER TABLE `project_surveys` DISABLE KEYS */;
INSERT INTO `project_surveys` VALUES (1,'Constructora Demo',20,'Instalaci??n de C??maras IP','Proyecto de prueba para validar el flujo completo de levantamiento a comisi??n. El proyecto ya est?? en estatus Fidelizado, listo para ser Aprobado.','1. Instalaci??n de NVR\n2. Tendido de cable UTP exterior\n3. Configuraci??n de Puntos de Acceso','2 d??as','1 T??cnico Principal, 1 Auxiliar','approved','pagado',NULL,'2026-03-03 19:39:40'),(2,'Empresa de Prueba S.A.',1,'Proyecto Test para Edici??n','Este es un proyecto autogenerado para probar el historial de ediciones.','<p>Instalaci??n de c??maras y cableado estructurado b??sico.</p>','3 D??as','Tecnico1','approved','pagado',NULL,'2026-03-05 20:28:17'),(3,'Mastertec (Nuevo Local Master)',1,'Instalaci??n de camaras','Se realizar?? la habilitaci??n de infraestructura de red y videovigilancia en el nuevo local. El proyecto contempla la instalaci??n de un sistema de c??maras de seguridad IP y la implementaci??n de un enlace inal??mbrico punto a punto para la interconexi??n de datos.','<p>&nbsp;</p>\r\n<p style=\"margin-left: 36.0pt; text-indent: -18.0pt; mso-list: l0 level1 lfo1; tab-stops: list 36.0pt;\"><!-- [if !supportLists]--><span style=\"font-size: 10.0pt; mso-bidi-font-size: 12.0pt; font-family: Symbol; mso-fareast-font-family: Symbol; mso-bidi-font-family: Symbol;\"><span style=\"mso-list: Ignore;\">&middot;<span style=\"font: 7.0pt \'Times New Roman\';\">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; </span></span></span><!--[endif]--><span class=\"citation-78\">Suministro e instalaci&oacute;n de <strong>2 c&aacute;maras IP Bullet</strong> (Hikvision HK-DS2CD1023G2-LIU) para exteriores/per&iacute;metro. </span></p>\r\n<p style=\"margin-left: 36.0pt; text-indent: -18.0pt; mso-list: l0 level1 lfo1; tab-stops: list 36.0pt;\"><!-- [if !supportLists]--><span style=\"font-size: 10.0pt; mso-bidi-font-size: 12.0pt; font-family: Symbol; mso-fareast-font-family: Symbol; mso-bidi-font-family: Symbol;\"><span style=\"mso-list: Ignore;\">&middot;<span style=\"font: 7.0pt \'Times New Roman\';\">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; </span></span></span><!--[endif]--><span class=\"citation-77\">Suministro e instalaci&oacute;n de <strong>7 c&aacute;maras IP Domo</strong> (Hikvision DS2CD1123G) para &aacute;reas internas. </span></p>\r\n<p style=\"margin-left: 36.0pt; text-indent: -18.0pt; mso-list: l0 level1 lfo1; tab-stops: list 36.0pt;\"><!-- [if !supportLists]--><span style=\"font-size: 10.0pt; mso-bidi-font-size: 12.0pt; font-family: Symbol; mso-fareast-font-family: Symbol; mso-bidi-font-family: Symbol;\"><span style=\"mso-list: Ignore;\">&middot;<span style=\"font: 7.0pt \'Times New Roman\';\">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; </span></span></span><!--[endif]-->Suministro e instalaci&oacute;n de <strong>2 Radios de Enlace</strong> (Ubiquiti NanoStation M5 5GHz) para conexi&oacute;n de datos.</p>\r\n<p style=\"margin-left: 36.0pt; text-indent: -18.0pt; mso-list: l0 level1 lfo1; tab-stops: list 36.0pt;\"><!-- [if !supportLists]--><span style=\"font-size: 10.0pt; mso-bidi-font-size: 12.0pt; font-family: Symbol; mso-fareast-font-family: Symbol; mso-bidi-font-family: Symbol;\"><span style=\"mso-list: Ignore;\">&middot;<span style=\"font: 7.0pt \'Times New Roman\';\">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; </span></span></span><!--[endif]-->Instalaci&oacute;n de <strong>1 Switch PoE</strong> (16 Puertos) para centralizaci&oacute;n de c&aacute;maras y antenas.</p>\r\n<p style=\"margin-left: 36.0pt; text-indent: -18.0pt; mso-list: l0 level1 lfo1; tab-stops: list 36.0pt;\"><!-- [if !supportLists]--><span style=\"font-size: 10.0pt; mso-bidi-font-size: 12.0pt; font-family: Symbol; mso-fareast-font-family: Symbol; mso-bidi-font-family: Symbol;\"><span style=\"mso-list: Ignore;\">&middot;<span style=\"font: 7.0pt \'Times New Roman\';\">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; </span></span></span><!--[endif]-->Instalaci&oacute;n de <strong>1 UPS de 750W</strong> para respaldo el&eacute;ctrico de equipos activos.</p>\r\n<p style=\"margin-left: 36.0pt; text-indent: -18.0pt; mso-list: l0 level1 lfo1; tab-stops: list 36.0pt;\"><!-- [if !supportLists]--><span style=\"font-size: 10.0pt; mso-bidi-font-size: 12.0pt; font-family: Symbol; mso-fareast-font-family: Symbol; mso-bidi-font-family: Symbol;\"><span style=\"mso-list: Ignore;\">&middot;<span style=\"font: 7.0pt \'Times New Roman\';\">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; </span></span></span><!--[endif]-->Instalaci&oacute;n de <strong>1 Bandeja de Rack</strong> para soporte de equipos.</p>\r\n<p style=\"margin-left: 36.0pt; text-indent: -18.0pt; mso-list: l0 level1 lfo1; tab-stops: list 36.0pt;\">&nbsp;</p>\r\n<p><span class=\"citation-69\">NOTAS Y OBSERVACIONES CR&Iacute;TICAS: </span></p>\r\n<p style=\"margin-left: 36.0pt; text-indent: -18.0pt; mso-list: l0 level1 lfo1; tab-stops: list 36.0pt;\"><!-- [if !supportLists]-->1.<span style=\"font-variant-numeric: normal; font-variant-east-asian: normal; font-variant-alternates: normal; font-size-adjust: none; font-language-override: normal; font-kerning: auto; font-optical-sizing: auto; font-feature-settings: normal; font-variation-settings: normal; font-variant-position: normal; font-variant-emoji: normal; font-stretch: normal; font-size: 7pt; line-height: normal; font-family: \'Times New Roman\';\">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; </span><!--[endif]--><strong>Riesgo Ambiental (Plaga de Aves):</strong> Se ha detectado una alta presencia de palomas en la estructura del edificio. Las heces de estas aves representan un riesgo corrosivo severo para los equipos expuestos (C&aacute;maras Bullet y Antenas). Se recomienda programar mantenimientos preventivos mensuales o la instalaci&oacute;n futura de protecciones f&iacute;sicas).</p>\r\n<p style=\"margin-left: 36.0pt; text-indent: -18.0pt; mso-list: l0 level1 lfo1; tab-stops: list 36.0pt;\"><!-- [if !supportLists]-->2.<span style=\"font-variant-numeric: normal; font-variant-east-asian: normal; font-variant-alternates: normal; font-size-adjust: none; font-language-override: normal; font-kerning: auto; font-optical-sizing: auto; font-feature-settings: normal; font-variation-settings: normal; font-variant-position: normal; font-variant-emoji: normal; font-stretch: normal; font-size: 7pt; line-height: normal; font-family: \'Times New Roman\';\">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; </span><!--[endif]--><strong>Material de Bodega:</strong> Parte de la canalizaci&oacute;n (escalerilla) ser&aacute; reutilizada del inventario de \"da&ntilde;ado/recuperado\" de bodega para optimizar costos.</p>\r\n<p style=\"margin-left: 36.0pt; text-indent: -18.0pt; mso-list: l0 level1 lfo1; tab-stops: list 36.0pt;\"><!-- [if !supportLists]-->3.<span style=\"font-variant-numeric: normal; font-variant-east-asian: normal; font-variant-alternates: normal; font-size-adjust: none; font-language-override: normal; font-kerning: auto; font-optical-sizing: auto; font-feature-settings: normal; font-variation-settings: normal; font-variant-position: normal; font-variant-emoji: normal; font-stretch: normal; font-size: 7pt; line-height: normal; font-family: \'Times New Roman\';\">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; </span><!--[endif]--><strong>Configuraci&oacute;n de Grabaci&oacute;n:</strong> Las c&aacute;maras ser&aacute;n centralizadas en el Switch. <span class=\"citation-68\">La gesti&oacute;n de grabaci&oacute;n depender&aacute; de la asignaci&oacute;n de almacenamiento en servidor o NVR existente en la red principal (seg&uacute;n topolog&iacute;a de la empresa). </span></p>\r\n<p style=\"margin-left: 36.0pt; text-indent: -18.0pt; mso-list: l0 level1 lfo1; tab-stops: list 36.0pt;\">&nbsp;</p>\r\n<p style=\"margin-left: 36.0pt; text-indent: -18.0pt; mso-list: l0 level1 lfo1; tab-stops: list 36.0pt;\"><!-- [if !supportLists]--><span class=\"citation-67\">4.<span style=\"font-variant-numeric: normal; font-variant-east-asian: normal; font-variant-alternates: normal; font-size-adjust: none; font-language-override: normal; font-kerning: auto; font-optical-sizing: auto; font-feature-settings: normal; font-variation-settings: normal; font-variant-position: normal; font-variant-emoji: normal; font-stretch: normal; font-size: 7pt; line-height: normal; font-family: \'Times New Roman\';\">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; </span></span><!--[endif]--><span class=\"citation-67\"><strong>Tiempo de Ejecuci&oacute;n:</strong> Este trabajo ser&aacute; realizado estimado en 2 a 3 d&iacute;as h&aacute;biles.</span></p>\r\n<p style=\"margin-left: 36.0pt; text-indent: -18.0pt; mso-list: l0 level1 lfo1; tab-stops: list 36.0pt;\"><!-- [if !supportLists]--><span style=\"font-size: 10.0pt; mso-bidi-font-size: 12.0pt; font-family: Symbol; mso-fareast-font-family: Symbol; mso-bidi-font-family: Symbol;\"><span style=\"mso-list: Ignore;\">&middot;<span style=\"font: 7.0pt \'Times New Roman\';\">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; </span></span></span><!--[endif]-->Instalaci&oacute;n de <strong>Escalerilla</strong> (Material recuperado de bodega) para canalizaci&oacute;n principal.</p>\r\n<p style=\"margin-left: 36.0pt; text-indent: -18.0pt; mso-list: l0 level1 lfo1; tab-stops: list 36.0pt;\"><!-- [if !supportLists]--><span style=\"font-size: 10.0pt; mso-bidi-font-size: 12.0pt; font-family: Symbol; mso-fareast-font-family: Symbol; mso-bidi-font-family: Symbol;\"><span style=\"mso-list: Ignore;\">&middot;<span style=\"font: 7.0pt \'Times New Roman\';\">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; </span></span></span><!--[endif]--><span class=\"citation-76\">Cableado UTP Cat6 100% Cobre para <strong>11 puntos de red</strong> (9 C&aacute;maras + 2 Radios). </span></p>\r\n<p style=\"margin-left: 36.0pt; text-indent: -18.0pt; mso-list: l0 level1 lfo1; tab-stops: list 36.0pt;\"><!-- [if !supportLists]--><span style=\"font-size: 10.0pt; mso-bidi-font-size: 12.0pt; font-family: Symbol; mso-fareast-font-family: Symbol; mso-bidi-font-family: Symbol;\"><span style=\"mso-list: Ignore;\">&middot;<span style=\"font: 7.0pt \'Times New Roman\';\">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; </span></span></span><!--[endif]-->Suministro de accesorios de fijaci&oacute;n: Velcro, bridas pl&aacute;sticas y conectores RJ45.</p>\r\n<p style=\"margin-left: 36.0pt; text-indent: -18.0pt; mso-list: l0 level1 lfo1; tab-stops: list 36.0pt;\">&nbsp;</p>\r\n<p><span class=\"citation-75\">TRABAJO A REALIZAR: </span></p>\r\n<p style=\"margin-left: 36.0pt; text-indent: -18.0pt; mso-list: l1 level1 lfo2; tab-stops: list 36.0pt;\"><!-- [if !supportLists]--><span style=\"font-size: 10.0pt; mso-bidi-font-size: 12.0pt; font-family: Symbol; mso-fareast-font-family: Symbol; mso-bidi-font-family: Symbol;\"><span style=\"mso-list: Ignore;\">&middot;<span style=\"font: 7.0pt \'Times New Roman\';\">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; </span></span></span><!--[endif]--><strong>Instalaci&oacute;n de Infraestructura:</strong> Montaje de escalerilla en ruta principal y peinado de cableado utilizando velcro para organizaci&oacute;n est&eacute;tica (no bridas en el mazo de cables).</p>\r\n<p style=\"margin-left: 36.0pt; text-indent: -18.0pt; mso-list: l1 level1 lfo2; tab-stops: list 36.0pt;\"><!-- [if !supportLists]--><span style=\"font-size: 10.0pt; mso-bidi-font-size: 12.0pt; font-family: Symbol; mso-fareast-font-family: Symbol; mso-bidi-font-family: Symbol;\"><span style=\"mso-list: Ignore;\">&middot;<span style=\"font: 7.0pt \'Times New Roman\';\">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; </span></span></span><!--[endif]--><strong>Montaje de C&aacute;maras:</strong></p>\r\n<p style=\"margin-left: 72.0pt; text-indent: -18.0pt; mso-list: l1 level2 lfo2; tab-stops: list 72.0pt;\"><!-- [if !supportLists]--><span style=\"font-size: 10.0pt; mso-bidi-font-size: 12.0pt; font-family: \'Courier New\'; mso-fareast-font-family: \'Courier New\';\"><span style=\"mso-list: Ignore;\">o<span style=\"font: 7.0pt \'Times New Roman\';\">&nbsp;&nbsp;&nbsp; </span></span></span><!--[endif]--><span class=\"citation-74\">2 C&aacute;maras Bullet en per&iacute;metro exterior/entrada. </span></p>\r\n<p style=\"margin-left: 72.0pt; text-indent: -18.0pt; mso-list: l1 level2 lfo2; tab-stops: list 72.0pt;\"><!-- [if !supportLists]--><span style=\"font-size: 10.0pt; mso-bidi-font-size: 12.0pt; font-family: \'Courier New\'; mso-fareast-font-family: \'Courier New\';\"><span style=\"mso-list: Ignore;\">o<span style=\"font: 7.0pt \'Times New Roman\';\">&nbsp;&nbsp;&nbsp; </span></span></span><!--[endif]--><span class=\"citation-73\">7 C&aacute;maras Domo distribuidas en: oficinas, pasillos, recepci&oacute;n y &aacute;reas operativas seg&uacute;n plano. </span></p>\r\n<p style=\"margin-left: 36.0pt; text-indent: -18.0pt; mso-list: l1 level1 lfo2; tab-stops: list 36.0pt;\"><!-- [if !supportLists]--><span style=\"font-size: 10.0pt; mso-bidi-font-size: 12.0pt; font-family: Symbol; mso-fareast-font-family: Symbol; mso-bidi-font-family: Symbol;\"><span style=\"mso-list: Ignore;\">&middot;<span style=\"font: 7.0pt \'Times New Roman\';\">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; </span></span></span><!--[endif]--><strong>Conectividad:</strong></p>\r\n<p style=\"margin-left: 72.0pt; text-indent: -18.0pt; mso-list: l1 level2 lfo2; tab-stops: list 72.0pt;\"><!-- [if !supportLists]--><span style=\"font-size: 10.0pt; mso-bidi-font-size: 12.0pt; font-family: \'Courier New\'; mso-fareast-font-family: \'Courier New\';\"><span style=\"mso-list: Ignore;\">o<span style=\"font: 7.0pt \'Times New Roman\';\">&nbsp;&nbsp;&nbsp; </span></span></span><!--[endif]--><span class=\"citation-72\">Ponchado de conectores RJ45 Cat6 en extremos (C&aacute;maras y Switch). </span></p>\r\n<p style=\"margin-left: 72.0pt; text-indent: -18.0pt; mso-list: l1 level2 lfo2; tab-stops: list 72.0pt;\"><!-- [if !supportLists]--><span style=\"font-size: 10.0pt; mso-bidi-font-size: 12.0pt; font-family: \'Courier New\'; mso-fareast-font-family: \'Courier New\';\"><span style=\"mso-list: Ignore;\">o<span style=\"font: 7.0pt \'Times New Roman\';\">&nbsp;&nbsp;&nbsp; </span></span></span><!--[endif]--><span class=\"citation-71\">Centralizaci&oacute;n de todo el cableado directo al Switch PoE ubicado en la bandeja del Rack. </span></p>\r\n<p style=\"margin-left: 36.0pt; text-indent: -18.0pt; mso-list: l1 level1 lfo2; tab-stops: list 36.0pt;\"><!-- [if !supportLists]--><span style=\"font-size: 10.0pt; mso-bidi-font-size: 12.0pt; font-family: Symbol; mso-fareast-font-family: Symbol; mso-bidi-font-family: Symbol;\"><span style=\"mso-list: Ignore;\">&middot;<span style=\"font: 7.0pt \'Times New Roman\';\">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; </span></span></span><!--[endif]--><strong>Enlace Inal&aacute;mbrico:</strong> Alineaci&oacute;n y configuraci&oacute;n de radios Ubiquiti M5 en frecuencia 5GHz para evitar interferencias.</p>\r\n<p style=\"margin-left: 36.0pt; text-indent: -18.0pt; mso-list: l1 level1 lfo2; tab-stops: list 36.0pt;\"><!-- [if !supportLists]--><span class=\"citation-70\"><span style=\"font-size: 10.0pt; mso-bidi-font-size: 12.0pt; font-family: Symbol; mso-fareast-font-family: Symbol; mso-bidi-font-family: Symbol;\"><span style=\"mso-list: Ignore;\">&middot;<span style=\"font: 7.0pt \'Times New Roman\';\">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; </span></span></span></span><!--[endif]--><span class=\"citation-70\"><strong>Configuraci&oacute;n:</strong> Ajuste de direccionamiento IP, pruebas de funcionamiento y ajuste de &aacute;ngulos de visi&oacute;n. </span></p>','5','','draft','pendiente',NULL,'2026-03-06 16:12:02');
/*!40000 ALTER TABLE `project_surveys` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `role_permissions`
--

DROP TABLE IF EXISTS `role_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `role_permissions` (
  `role_id` int(11) NOT NULL,
  `permission_id` int(11) NOT NULL,
  PRIMARY KEY (`role_id`,`permission_id`),
  KEY `permission_id` (`permission_id`),
  CONSTRAINT `role_permissions_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `role_permissions_ibfk_2` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `role_permissions`
--

LOCK TABLES `role_permissions` WRITE;
/*!40000 ALTER TABLE `role_permissions` DISABLE KEYS */;
INSERT INTO `role_permissions` VALUES (1,1),(1,2),(1,3),(1,4),(1,5),(1,6),(1,7),(1,8),(1,9),(1,10),(1,11),(1,12),(1,13),(1,14),(1,15),(1,16),(1,17),(1,18),(1,19),(1,20),(1,21),(1,22),(1,23),(1,24),(1,29),(1,31),(1,32),(1,33),(1,34),(1,35),(1,36),(1,37),(1,38),(1,39),(1,40),(1,41),(1,42),(1,43),(1,44),(1,45),(1,46),(1,47),(1,48),(3,1),(3,2),(3,3),(3,4),(3,5),(3,6),(3,7),(3,15),(3,16),(3,22),(3,33),(3,34),(3,35),(3,36),(4,1),(4,2),(4,3),(4,4),(4,5),(4,6),(4,7),(4,15),(4,16),(4,22),(4,24),(5,1),(7,1),(7,2),(7,3),(7,4),(7,5),(7,6),(7,7),(7,11),(7,15),(7,16),(7,22);
/*!40000 ALTER TABLE `role_permissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `roles`
--

DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `roles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL COMMENT 'Administrador, Supervisor, T?????cnico, Recepci??????n, Almac?????n',
  `description` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `roles`
--

LOCK TABLES `roles` WRITE;
/*!40000 ALTER TABLE `roles` DISABLE KEYS */;
INSERT INTO `roles` VALUES (1,'Administrador','Acceso total al sistema'),(3,'T??cnico','Realizaci??????n de trabajos y asignaciones'),(4,'Recepci??n','Registro de entrada y entrega de equipos'),(5,'Almac??n','Gesti??????n de herramientas e inventario'),(7,'Admin',NULL);
/*!40000 ALTER TABLE `roles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `service_order_history`
--

DROP TABLE IF EXISTS `service_order_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `service_order_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `service_order_id` int(11) NOT NULL,
  `action` varchar(50) NOT NULL,
  `notes` text DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `service_order_id` (`service_order_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `service_order_history_ibfk_1` FOREIGN KEY (`service_order_id`) REFERENCES `service_orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `service_order_history_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `service_order_history`
--

LOCK TABLES `service_order_history` WRITE;
/*!40000 ALTER TABLE `service_order_history` DISABLE KEYS */;
INSERT INTO `service_order_history` VALUES (1,2,'received','Equipo ingresado al taller',1,'2026-03-05 19:15:37'),(2,2,'updated','T??cnico asignado: tecnico',1,'2026-03-05 19:16:02'),(3,2,'updated','Comisi??n auto-generada para t??cnico por pago',1,'2026-03-05 19:19:19'),(4,2,'updated','Estado de pago actualizado a: PAGADO',1,'2026-03-05 19:19:19');
/*!40000 ALTER TABLE `service_order_history` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `service_orders`
--

DROP TABLE IF EXISTS `service_orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `service_orders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `display_id` int(11) DEFAULT NULL,
  `entry_doc_number` int(11) DEFAULT NULL,
  `diagnosis_number` int(11) DEFAULT NULL,
  `repair_number` int(11) DEFAULT NULL,
  `exit_doc_number` int(11) DEFAULT NULL,
  `invoice_number` varchar(50) DEFAULT NULL,
  `equipment_id` int(11) NOT NULL,
  `client_id` int(11) NOT NULL,
  `owner_name` varchar(255) DEFAULT NULL,
  `contact_name` varchar(100) DEFAULT NULL,
  `service_type` enum('service','warranty') DEFAULT 'service',
  `assigned_tech_id` int(11) DEFAULT NULL,
  `status` enum('received','diagnosing','pending_approval','in_repair','ready','delivered','cancelled') DEFAULT 'received',
  `payment_status` enum('pendiente','pagado') NOT NULL DEFAULT 'pendiente',
  `problem_reported` text DEFAULT NULL,
  `accessories_received` text DEFAULT NULL,
  `entry_notes` text DEFAULT NULL,
  `entry_date` datetime DEFAULT NULL,
  `entry_signature_path` varchar(255) DEFAULT NULL,
  `diagnosis_notes` text DEFAULT NULL,
  `work_done` text DEFAULT NULL,
  `parts_replaced` text DEFAULT NULL,
  `final_cost` decimal(10,2) DEFAULT 0.00,
  `exit_date` datetime DEFAULT NULL,
  `exit_signature_path` varchar(255) DEFAULT NULL,
  `authorized_by_user_id` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `diagnosis_procedure` text DEFAULT NULL,
  `diagnosis_conclusion` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `equipment_id` (`equipment_id`),
  KEY `client_id` (`client_id`),
  KEY `assigned_tech_id` (`assigned_tech_id`),
  KEY `authorized_by_user_id` (`authorized_by_user_id`),
  CONSTRAINT `service_orders_ibfk_1` FOREIGN KEY (`equipment_id`) REFERENCES `equipments` (`id`),
  CONSTRAINT `service_orders_ibfk_2` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`),
  CONSTRAINT `service_orders_ibfk_3` FOREIGN KEY (`assigned_tech_id`) REFERENCES `users` (`id`),
  CONSTRAINT `service_orders_ibfk_4` FOREIGN KEY (`authorized_by_user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `service_orders`
--

LOCK TABLES `service_orders` WRITE;
/*!40000 ALTER TABLE `service_orders` DISABLE KEYS */;
INSERT INTO `service_orders` VALUES (1,NULL,NULL,NULL,NULL,NULL,NULL,1,1,NULL,NULL,'service',1,'delivered','pendiente','Prueba de generaci??n de comisi??n desde Servicio',NULL,NULL,'2026-03-05 13:07:16',NULL,NULL,NULL,NULL,0.00,NULL,NULL,NULL,NULL,NULL,NULL),(2,NULL,1,NULL,NULL,NULL,'asdasda',2,2,'asdfqwe',NULL,'service',20,'received','pagado','asdffweas','asdafqweqweasd','','2026-03-05 13:15:37',NULL,NULL,NULL,NULL,0.00,NULL,NULL,NULL,'2026-03-05 19:15:37',NULL,NULL);
/*!40000 ALTER TABLE `service_orders` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `site_settings`
--

DROP TABLE IF EXISTS `site_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `site_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(50) NOT NULL,
  `setting_value` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB AUTO_INCREMENT=31 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `site_settings`
--

LOCK TABLES `site_settings` WRITE;
/*!40000 ALTER TABLE `site_settings` DISABLE KEYS */;
INSERT INTO `site_settings` VALUES (1,'system_logo','logo_1769441505.png'),(2,'company_name','Mastertec'),(3,'company_address','Parraquia La Merced 2c al sur 10vrs al oeste'),(4,'company_phone','+505 8850 2649'),(5,'print_footer_text','Declaraci??????n de Conformidad: El cliente declara recibir el equipo a su entera satisfacci??????n, habiendo verificado su funcionamiento. La empresa no se hace responsable por fallas posteriores no relacionadas con el servicio efectuado.'),(8,'company_email','atencionalcliente@mastertec.com.ni'),(22,'print_entry_text','1.	No nos responsabilizamos por perdida de informaci??????n en medios de almacenamiento como discos duros interno o externos al momento del ingreso o en el proceso de diagn??????stico.\r\n2.	Equipos deben ser retirados en un m?????ximo de 30 d?????as calendarios despu?????s de notificado trabajo finalizado. Despu?????s de este tiempo si el cliente no se presenta a retirar, autoriza a MASTERTEC a desechar el equipo.\r\n3.	En caso de no reparar equipo, cliente deber????? pagar el diagn??????stico correspondiente.\r\n4.	Para consulta del estado de su equipo favor escribirnos a: atencionalcliente@mastertec.com.ni\r\n5.	Tiempo de diagn??????stico m?????nimo 48 horas.'),(23,'print_diagnosis_text',''),(24,'print_delivery_text','');
/*!40000 ALTER TABLE `site_settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `system_sequences`
--

DROP TABLE IF EXISTS `system_sequences`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `system_sequences` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(50) NOT NULL,
  `current_value` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `system_sequences`
--

LOCK TABLES `system_sequences` WRITE;
/*!40000 ALTER TABLE `system_sequences` DISABLE KEYS */;
INSERT INTO `system_sequences` VALUES (1,'diagnosis',0),(2,'repair',0),(3,'exit_doc',0),(4,'entry_doc',1);
/*!40000 ALTER TABLE `system_sequences` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tool_assignment_items`
--

DROP TABLE IF EXISTS `tool_assignment_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tool_assignment_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `assignment_id` int(11) NOT NULL,
  `tool_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `status` enum('pending','delivered','returned') DEFAULT 'pending',
  `delivery_confirmed` tinyint(1) DEFAULT 0,
  `return_confirmed` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `assignment_id` (`assignment_id`),
  KEY `tool_id` (`tool_id`),
  CONSTRAINT `tool_assignment_items_ibfk_1` FOREIGN KEY (`assignment_id`) REFERENCES `tool_assignments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `tool_assignment_items_ibfk_2` FOREIGN KEY (`tool_id`) REFERENCES `tools` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tool_assignment_items`
--

LOCK TABLES `tool_assignment_items` WRITE;
/*!40000 ALTER TABLE `tool_assignment_items` DISABLE KEYS */;
/*!40000 ALTER TABLE `tool_assignment_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tool_assignments`
--

DROP TABLE IF EXISTS `tool_assignments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tool_assignments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `project_name` varchar(255) NOT NULL,
  `assigned_to` varchar(255) NOT NULL,
  `technician_1` varchar(255) DEFAULT NULL,
  `technician_2` varchar(255) DEFAULT NULL,
  `technician_3` varchar(255) DEFAULT NULL,
  `delivery_date` date NOT NULL,
  `return_date` date DEFAULT NULL,
  `observations` text DEFAULT NULL,
  `status` enum('pending','delivered','returned') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tool_assignments`
--

LOCK TABLES `tool_assignments` WRITE;
/*!40000 ALTER TABLE `tool_assignments` DISABLE KEYS */;
/*!40000 ALTER TABLE `tool_assignments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tool_loans`
--

DROP TABLE IF EXISTS `tool_loans`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tool_loans` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tool_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL COMMENT 'Technician borrowing the tool',
  `loan_date` datetime DEFAULT NULL,
  `return_date` datetime DEFAULT NULL,
  `status` enum('active','returned') DEFAULT 'active',
  `notes` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `tool_id` (`tool_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `tool_loans_ibfk_1` FOREIGN KEY (`tool_id`) REFERENCES `tools` (`id`),
  CONSTRAINT `tool_loans_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tool_loans`
--

LOCK TABLES `tool_loans` WRITE;
/*!40000 ALTER TABLE `tool_loans` DISABLE KEYS */;
/*!40000 ALTER TABLE `tool_loans` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tools`
--

DROP TABLE IF EXISTS `tools`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tools` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `status` enum('available','assigned','maintenance','lost') DEFAULT 'available',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tools`
--

LOCK TABLES `tools` WRITE;
/*!40000 ALTER TABLE `tools` DISABLE KEYS */;
INSERT INTO `tools` VALUES (1,'Taladro','este es un test',1,'available','2026-02-28 15:58:03','2026-02-28 15:58:03');
/*!40000 ALTER TABLE `tools` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_custom_modules`
--

DROP TABLE IF EXISTS `user_custom_modules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_custom_modules` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `module_name` varchar(50) NOT NULL,
  `is_enabled` tinyint(1) DEFAULT 1,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_module` (`user_id`,`module_name`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `user_custom_modules_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=102 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Overrides for specific users to access/block modules';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_custom_modules`
--

LOCK TABLES `user_custom_modules` WRITE;
/*!40000 ALTER TABLE `user_custom_modules` DISABLE KEYS */;
/*!40000 ALTER TABLE `user_custom_modules` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `role_id` int(11) NOT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT NULL,
  `signature_path` varchar(255) DEFAULT NULL,
  `navbar_order` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `role_id` (`role_id`),
  CONSTRAINT `users_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'superadmin','$2y$10$9Sy.JQE3Lo8yd2CXqF1d/eRpszhcgHZy2kR.3IIoZ1ju98A8D7CUe','superadmin@taller.com',1,'active','2026-01-05 18:16:13',NULL,'[\"clients\",\"equipment\",\"new_warranty\",\"tools\",\"requests\",\"reports\"]'),(20,'tecnico','$2y$10$AQ0zGjV2RgPCfHa6Bbn3QuqH1WdiZA79s4360itnxxW1KGG6YBeo2','tecnico@sys.com',3,'active',NULL,NULL,NULL);
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `viatico_amounts`
--

DROP TABLE IF EXISTS `viatico_amounts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `viatico_amounts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `viatico_id` int(11) NOT NULL,
  `concept_id` int(11) NOT NULL,
  `column_id` int(11) NOT NULL,
  `amount` decimal(10,2) DEFAULT 0.00,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_cell` (`viatico_id`,`concept_id`,`column_id`),
  KEY `concept_id` (`concept_id`),
  KEY `column_id` (`column_id`),
  CONSTRAINT `viatico_amounts_ibfk_1` FOREIGN KEY (`viatico_id`) REFERENCES `viaticos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `viatico_amounts_ibfk_2` FOREIGN KEY (`concept_id`) REFERENCES `viatico_concepts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `viatico_amounts_ibfk_3` FOREIGN KEY (`column_id`) REFERENCES `viatico_columns` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `viatico_amounts`
--

LOCK TABLES `viatico_amounts` WRITE;
/*!40000 ALTER TABLE `viatico_amounts` DISABLE KEYS */;
INSERT INTO `viatico_amounts` VALUES (6,1,6,2,160.00),(7,1,7,2,150.00),(8,1,8,2,130.00),(9,1,9,2,80.00),(10,1,10,2,80.00);
/*!40000 ALTER TABLE `viatico_amounts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `viatico_columns`
--

DROP TABLE IF EXISTS `viatico_columns`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `viatico_columns` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `viatico_id` int(11) NOT NULL,
  `tech_id` int(11) DEFAULT NULL,
  `tech_name` varchar(255) NOT NULL,
  `display_order` int(11) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `viatico_id` (`viatico_id`),
  KEY `tech_id` (`tech_id`),
  CONSTRAINT `viatico_columns_ibfk_1` FOREIGN KEY (`viatico_id`) REFERENCES `viaticos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `viatico_columns_ibfk_2` FOREIGN KEY (`tech_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `viatico_columns`
--

LOCK TABLES `viatico_columns` WRITE;
/*!40000 ALTER TABLE `viatico_columns` DISABLE KEYS */;
INSERT INTO `viatico_columns` VALUES (2,1,20,'tecnico',0);
/*!40000 ALTER TABLE `viatico_columns` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `viatico_concepts`
--

DROP TABLE IF EXISTS `viatico_concepts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `viatico_concepts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `viatico_id` int(11) NOT NULL,
  `type` enum('predetermined','custom') NOT NULL,
  `category` enum('food','transport','other') NOT NULL,
  `label` varchar(100) NOT NULL,
  `display_order` int(11) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `viatico_id` (`viatico_id`),
  CONSTRAINT `viatico_concepts_ibfk_1` FOREIGN KEY (`viatico_id`) REFERENCES `viaticos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `viatico_concepts`
--

LOCK TABLES `viatico_concepts` WRITE;
/*!40000 ALTER TABLE `viatico_concepts` DISABLE KEYS */;
INSERT INTO `viatico_concepts` VALUES (6,1,'predetermined','food','Desayuno',0),(7,1,'predetermined','food','Almuerzo',0),(8,1,'predetermined','food','Cena',0),(9,1,'predetermined','transport','AM',0),(10,1,'predetermined','transport','PM',0);
/*!40000 ALTER TABLE `viatico_concepts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `viaticos`
--

DROP TABLE IF EXISTS `viaticos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `viaticos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `project_title` varchar(255) NOT NULL,
  `date` date NOT NULL,
  `total_amount` decimal(10,2) DEFAULT 0.00,
  `created_by` int(11) NOT NULL,
  `status` enum('draft','submitted','paid') DEFAULT 'draft',
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `viaticos_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `viaticos`
--

LOCK TABLES `viaticos` WRITE;
/*!40000 ALTER TABLE `viaticos` DISABLE KEYS */;
INSERT INTO `viaticos` VALUES (1,'Test','2026-03-02',600.00,1,'draft','2026-03-02 14:59:29');
/*!40000 ALTER TABLE `viaticos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `warranties`
--

DROP TABLE IF EXISTS `warranties`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `warranties` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `service_order_id` int(11) DEFAULT NULL COMMENT 'If warranty is for a repair',
  `equipment_id` int(11) DEFAULT NULL COMMENT 'If warranty is for an equipment sale/item',
  `supplier_name` varchar(100) DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `status` enum('active','expired','void','claimed') DEFAULT 'active',
  `docs_path` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `product_code` varchar(50) DEFAULT NULL COMMENT 'Codigo',
  `sales_invoice_number` varchar(50) DEFAULT NULL COMMENT 'Factura de Venta',
  `master_entry_invoice` varchar(50) DEFAULT NULL COMMENT 'Factura de ingreso a master',
  `master_entry_date` date DEFAULT NULL COMMENT 'Fecha que ingreso a master',
  `duration_months` int(11) DEFAULT 0,
  `terms` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `service_order_id` (`service_order_id`),
  KEY `equipment_id` (`equipment_id`),
  CONSTRAINT `warranties_ibfk_1` FOREIGN KEY (`service_order_id`) REFERENCES `service_orders` (`id`),
  CONSTRAINT `warranties_ibfk_2` FOREIGN KEY (`equipment_id`) REFERENCES `equipments` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `warranties`
--

LOCK TABLES `warranties` WRITE;
/*!40000 ALTER TABLE `warranties` DISABLE KEYS */;
/*!40000 ALTER TABLE `warranties` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-03-06 14:00:03
