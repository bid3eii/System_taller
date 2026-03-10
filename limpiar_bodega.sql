-- -----------------------------------------------------------------------------
-- SCRIPT PARA VACIAR ÚNICAMENTE EL REGISTRO DE BODEGA
-- -----------------------------------------------------------------------------

-- 1. Desactivar las restricciones de llaves foráneas para evitar errores de borrado
SET FOREIGN_KEY_CHECKS = 0;

-- 2. Vaciar por completo la tabla de garantías (donde se guardan los datos de bodega)
TRUNCATE TABLE warranties;

-- 3. Eliminar de la tabla principal todas las órdenes que fueron etiquetadas como "warranty"
DELETE FROM service_orders WHERE service_type = 'warranty';

-- 4. (Opcional) Limpiar componentes de la base de datos que hayan quedado huérfanos
-- Esto borra equipos que entraron con la bodega y ahora no tienen orden de servicio
DELETE e FROM equipments e
LEFT JOIN service_orders so ON e.id = so.equipment_id
WHERE so.id IS NULL;

-- Esto borra clientes fantasma (vacíos) creados por la importación masiva
DELETE FROM clients WHERE name = '' OR name IS NULL;

-- 5. Reactivar las llaves foráneas
SET FOREIGN_KEY_CHECKS = 1;

-- IMPORTANTE: Este script NO borrará tus Servicios de Reparación de "Clientes" formales.
