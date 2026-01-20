<?php
// reset_password.php
require_once 'config/db.php';

$new_password = 'administrador';
$password_hash = password_hash($new_password, PASSWORD_DEFAULT);
$username = 'admin';

try {
    $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE username = ?");
    $stmt->execute([$password_hash, $username]);
    
    echo "¡Contraseña actualizada con éxito!<br>";
    echo "Usuario: <b>$username</b><br>";
    echo "Nueva Contraseña: <b>$new_password</b><br>";
    echo "<br><a href='modules/auth/login.php'>Ir al Login</a>";
    
} catch (PDOException $e) {
    echo "Error al actualizar: " . $e->getMessage();
}
?>
