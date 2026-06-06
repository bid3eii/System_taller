<?php
// modules/auth/logout.php
require_once dirname(__DIR__, 2) . '/config/db.php';
safe_session_start();
session_unset();
session_destroy();
header("Location: login.php");
exit;
?>
