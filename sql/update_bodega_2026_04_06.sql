-- Script de actualización de Base de Datos para el Módulo de Bodega
-- Generado el: 2026-04-06

-- 1. Asegurar que existe el cliente interno de Bodega para el inventario
INSERT INTO clients (name, phone, email, tax_id, address, is_third_party, created_at)
SELECT 'Bodega - Inventario', 'N/A', '', '', 'Bodega Local', 0, NOW()
WHERE NOT EXISTS (SELECT 1 FROM clients WHERE name = 'Bodega - Inventario');

-- 2. Asegurar que la tabla de garantías tenga las columnas necesarias para bodega
-- (Ejecutar solo si no existen)
ALTER TABLE `warranties` 
    ADD COLUMN IF NOT EXISTS `product_code` VARCHAR(50) DEFAULT NULL AFTER `created_at`,
    ADD COLUMN IF NOT EXISTS `sales_invoice_number` VARCHAR(50) DEFAULT NULL AFTER `product_code`,
    ADD COLUMN IF NOT EXISTS `master_entry_invoice` VARCHAR(50) DEFAULT NULL AFTER `sales_invoice_number`,
    ADD COLUMN IF NOT EXISTS `master_entry_date` DATE DEFAULT NULL AFTER `master_entry_invoice`,
    ADD COLUMN IF NOT EXISTS `duration_months` INT(11) DEFAULT 0 AFTER `master_entry_date`;

-- 3. Si tienes registros antiguos en Stock sin cliente asignado, 
-- puedes ejecutar este comando para que aparezcan en la pestaña "En Stock":
-- (Nota: Cambiar el ID '11' por el ID real del cliente 'Bodega - Inventario' generado arriba)
-- UPDATE service_orders so 
-- JOIN equipments e ON so.equipment_id = e.id 
-- SET so.client_id = (SELECT id FROM clients WHERE name = 'Bodega - Inventario'),
--     e.client_id = (SELECT id FROM clients WHERE name = 'Bodega - Inventario')
-- WHERE so.service_type = 'warranty' AND so.client_id IS NULL;
