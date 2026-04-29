<?php
require_once 'config/db.php';

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
    echo "Error creating table project_survey_tools: " . $e->getMessage() . "\n";
}
