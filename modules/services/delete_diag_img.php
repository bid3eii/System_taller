<?php
session_start();
require_once '../../config/db.php';

// Disable silent failure for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Security check
if (!isset($_SESSION['user_id'])) {
    die("<h1>Error de Acceso</h1><p>No has iniciado sesión o tu sesión ha expirado.</p><a href='../../modules/auth/login.php'>Ir al Login</a>");
}

$imgId = intval($_GET['img_id'] ?? 0);
$orderId = intval($_GET['order_id'] ?? 0);
$type = $_GET['type'] ?? 'service'; // 'service' or 'warranty'

$error = null;

if ($imgId > 0 && $orderId > 0) {
    try {
        // Find the image and verify it belongs to the order
        $stmt = $pdo->prepare("SELECT image_path FROM diagnosis_images WHERE id = ? AND service_order_id = ?");
        $stmt->execute([$imgId, $orderId]);
        $imgData = $stmt->fetch();

        if ($imgData) {
            // Delete file from disk
            $filePath = '../../' . $imgData['image_path'];
            if (file_exists($filePath)) {
                if (!unlink($filePath)) {
                    $error = "Error: El servidor no tiene permisos para borrar el archivo físico: $filePath";
                }
            }
            
            if (!$error) {
                // Delete from database
                $stmtDel = $pdo->prepare("DELETE FROM diagnosis_images WHERE id = ?");
                if (!$stmtDel->execute([$imgId])) {
                    $error = "Error: No se pudo eliminar el registro de la base de datos.";
                }
            }
        } else {
            $error = "Error: La imagen (ID $imgId) no existe o no pertenece a la orden (ID $orderId).";
        }
    } catch (Exception $e) {
        $error = "Error excepcional: " . $e->getMessage();
    }
} else {
    $error = "Error: Parámetros inválidos. img_id=$imgId, order_id=$orderId";
}

if ($error) {
    echo "<h1>Error al eliminar imagen</h1>";
    echo "<p style='color:red; font-weight:bold;'>$error</p>";
    echo "<hr>";
    echo "<p>Si el problema persiste, contacta al administrador.</p>";
    echo "<button onclick='window.history.back()'>Volver</button>";
} else {
    // Redirect back to the caller page
    $backUrl = ($type === 'warranty') ? '../warranties/view.php' : 'view.php';
    header("Location: $backUrl?id=$orderId&msg=success");
}
exit;
