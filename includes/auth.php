<?php
// includes/auth.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    // Redirect to login
    header("Location: /System_Taller/modules/auth/login.php");
    exit;
}

// Inactivity timeout (optional, e.g. 30 mins)
$timeout_duration = 1800; // 30 minutes
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout_duration) {
    session_unset();
    session_destroy();
    header("Location: /System_Taller/modules/auth/login.php?error=timeout");
    exit;
}
$_SESSION['last_activity'] = time();
?>
