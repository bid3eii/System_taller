-- Script final para crear la nueva tabla de comisiones en el Hosting

-- 1. Renombrar la tabla vieja por seguridad (para no perder datos si los hubiera)
ALTER TABLE `comisiones` RENAME TO `comisiones_antiguas_del_sistema`;

-- 2. Crear la tabla de comisiones con la estructura moderna EXACTA
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
