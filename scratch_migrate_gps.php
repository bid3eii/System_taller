<?php
require_once 'config/db.php';
try {
    $pdo->exec("ALTER TABLE schedule_events ADD COLUMN latitude DECIMAL(10, 8) NULL");
    echo "Added latitude. ";
} catch (Exception $e) { echo "Lat error: " . $e->getMessage() . " "; }

try {
    $pdo->exec("ALTER TABLE schedule_events ADD COLUMN longitude DECIMAL(11, 8) NULL");
    echo "Added longitude. ";
} catch (Exception $e) { echo "Lng error: " . $e->getMessage() . " "; }
echo "Done.";
