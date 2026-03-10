-- ============================================================
-- Script de actualización para el HOSTING
-- System Taller — 06/03/2026
-- Ejecutar en phpMyAdmin o cliente MySQL del hosting
-- ============================================================

-- ============================================================
-- PASO 1: Agregar columna invoice_number a service_orders
-- (si ya existe, el IF NOT EXISTS la omite sin error)
-- ============================================================
ALTER TABLE `service_orders`
    ADD COLUMN IF NOT EXISTS `invoice_number` VARCHAR(100) NULL DEFAULT NULL
    AFTER `payment_status`;

-- ============================================================
-- PASO 2: Actualizar la tabla comisiones
-- Opción A: Si NO existe la tabla comisiones aún en el hosting
-- Ejecuta solo este bloque si la tabla no existe:
-- ============================================================
CREATE TABLE IF NOT EXISTS `comisiones` (
  `id`               int(11)                     NOT NULL AUTO_INCREMENT,
  `fecha_servicio`   date                        NOT NULL,
  `fecha_facturacion` date                       DEFAULT NULL,
  `cliente`          varchar(255)                NOT NULL,
  `servicio`         varchar(255)                NOT NULL,
  `cantidad`         int(11)                     NOT NULL DEFAULT 1,
  `tipo`             enum('SERVICIO','PROYECTO')  NOT NULL,
  `lugar`            varchar(255)                DEFAULT NULL,
  `factura`          varchar(100)                DEFAULT NULL,
  `vendedor`         varchar(255)                NOT NULL DEFAULT '',
  `caso`             varchar(100)                NOT NULL,
  `estado`           enum('PENDIENTE','PAGADA')  NOT NULL DEFAULT 'PENDIENTE',
  `fecha_pago`       date                        DEFAULT NULL,
  `notas`            text                        DEFAULT NULL,
  `tech_id`          int(11)                     NOT NULL,
  `reference_id`     int(11)                     DEFAULT NULL,
  `created_at`       datetime                    NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================
-- Opción B: Si la tabla comisiones YA EXISTE en el hosting
-- Solo agrega las columnas faltantes:
-- ============================================================
ALTER TABLE `comisiones`
    ADD COLUMN IF NOT EXISTS `factura`           VARCHAR(100) NULL DEFAULT NULL AFTER `caso`,
    ADD COLUMN IF NOT EXISTS `tipo`              ENUM('SERVICIO','PROYECTO') NULL AFTER `cantidad`,
    ADD COLUMN IF NOT EXISTS `lugar`             VARCHAR(255) NULL DEFAULT NULL AFTER `tipo`,
    ADD COLUMN IF NOT EXISTS `vendedor`          VARCHAR(255) NOT NULL DEFAULT '' AFTER `lugar`,
    ADD COLUMN IF NOT EXISTS `reference_id`      INT(11) NULL DEFAULT NULL AFTER `tech_id`,
    ADD COLUMN IF NOT EXISTS `fecha_facturacion` DATE NULL DEFAULT NULL AFTER `fecha_servicio`;

-- ============================================================
-- VERIFICACIÓN: Confirma la estructura final
-- ============================================================
DESCRIBE `service_orders`;
DESCRIBE `comisiones`;
