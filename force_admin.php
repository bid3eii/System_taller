<?php
$pdo = new PDO('mysql:host=localhost;dbname=system_taller;charset=utf8mb4', 'root', '');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$hash = password_hash('administrador', PASSWORD_DEFAULT);

$pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
$pdo->exec("DELETE FROM users");
$stmt = $pdo->prepare("INSERT INTO users (id, username, password_hash, email, role_id, status) VALUES (1, 'superadmin', ?, 'admin@taller.com', 1, 'active')");
$stmt->execute([$hash]);

// Ensure role 1 exists
$pdo->exec("INSERT IGNORE INTO roles (id, name, description) VALUES (1, 'Admin Restore', 'Admin temp')");

$pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

$users = $pdo->query("SELECT * FROM users")->fetchAll(PDO::FETCH_ASSOC);
print_r($users);
