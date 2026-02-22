-- Migration to fix timezone issues
-- This removes DEFAULT CURRENT_TIMESTAMP from all timestamp columns
-- After this, PHP will explicitly set datetime values using local timezone

USE system_taller;

-- Remove DEFAULT CURRENT_TIMESTAMP from timestamp columns
ALTER TABLE users MODIFY created_at TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE clients MODIFY created_at TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE equipments MODIFY created_at TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE service_orders 
  MODIFY created_at TIMESTAMP NULL DEFAULT NULL,
  MODIFY entry_date DATETIME NULL DEFAULT NULL;
ALTER TABLE service_order_history MODIFY created_at TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE tools MODIFY created_at TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE tool_loans MODIFY loan_date DATETIME NULL DEFAULT NULL;
ALTER TABLE warranties MODIFY created_at TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE user_custom_modules MODIFY updated_at TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE audit_logs MODIFY created_at TIMESTAMP NULL DEFAULT NULL;