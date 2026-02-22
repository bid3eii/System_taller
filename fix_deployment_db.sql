-- Script corregido para limpieza de registros huérfanos antes de exportar
-- Ejecuta este script en tu base de datos local (System_Taller) antes de volver a exportar.
-- Esto soluciona el error #1452 FOREIGN KEY constraint failed

-- 1. Eliminar imágenes de diagnóstico que no tienen una orden de servicio válida
DELETE FROM diagnosis_images 
WHERE service_order_id NOT IN (SELECT id FROM service_orders);

-- 2. Eliminar historial de órdenes huérfanas invalidas
DELETE FROM service_order_history 
WHERE service_order_id NOT IN (SELECT id FROM service_orders);

SELECT "Base de datos limpia. Ahora puedes exportar de nuevo." as Mensaje;
