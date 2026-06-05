<?php
// index.php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('session.gc_probability', 0);

// Configure session cookies for InfinityFree compatibility
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    @session_start(['gc_probability' => 0]);
}

if (isset($_SESSION['user_id'])) {
    header("Location: modules/dashboard/index.php");
} else {
    header("Location: modules/auth/login.php");
}
exit;
?>