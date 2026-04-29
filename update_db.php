<?php
require_once 'config/db.php';

echo "<pre>";

try {
    $pdo->exec("ALTER TABLE project_surveys ADD COLUMN trabajos_revisar TEXT AFTER scope_activities;");
    echo "Added trabajos_revisar to DB successfully.\n";
} catch (Exception $e) {
    echo "Error trabajos_revisar: " . $e->getMessage() . "\n";
}

try {
    $pdo->exec("ALTER TABLE project_surveys ADD COLUMN notas TEXT AFTER trabajos_revisar;");
    echo "Added notas to DB successfully.\n";
} catch (Exception $e) {
    echo "Error notas: " . $e->getMessage() . "\n";
}

try {
    $pdo->exec("ALTER TABLE project_surveys ADD COLUMN vendedor VARCHAR(100) NULL AFTER user_id;");
    echo "Added vendedor to DB successfully.\n";
} catch (Exception $e) {
    echo "Error vendedor: " . $e->getMessage() . "\n";
}

try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS project_survey_tools (
            id INT AUTO_INCREMENT PRIMARY KEY,
            survey_id INT NOT NULL,
            tool_id INT NULL,
            tool_name VARCHAR(255) NULL,
            quantity DECIMAL(10,2) DEFAULT 1.00,
            notes TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (survey_id) REFERENCES project_surveys(id) ON DELETE CASCADE,
            FOREIGN KEY (tool_id) REFERENCES tools(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    echo "Table project_survey_tools created successfully.\n";
} catch (Exception $e) {
    echo "Error project_survey_tools: " . $e->getMessage() . "\n";
}

echo "</pre>";
