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

// Refresh User Data (Throttle to once every 5 minutes to save DB resources)
$refresh_interval = 300; // 5 minutes
if (!isset($_SESSION['last_user_refresh']) || (time() - $_SESSION['last_user_refresh']) > $refresh_interval) {
    $stmtRefresh = $pdo->prepare("SELECT u.username, u.role_id, r.name as role_name 
                                  FROM users u 
                                  LEFT JOIN roles r ON u.role_id = r.id 
                                  WHERE u.id = ? AND u.status = 'active'");
    $stmtRefresh->execute([$_SESSION['user_id']]);
    $freshUser = $stmtRefresh->fetch();
    
    if ($freshUser) {
        $_SESSION['username'] = $freshUser['username'];
        $_SESSION['role_id'] = $freshUser['role_id'];
        $_SESSION['role_name'] = $freshUser['role_name'];
        $_SESSION['last_user_refresh'] = time();
    } else {
        // User deleted or inactive
        session_destroy();
        header("Location: /System_Taller/modules/auth/login.php?error=account_issue");
        exit;
    }
}

// Inactivity timeout enabled
$timeout_duration = 28800; // 8 hours
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout_duration) {
    session_unset();
    session_destroy();
    header("Location: /System_Taller/modules/auth/login.php?error=timeout");
    exit;
}
$_SESSION['last_activity'] = time();

