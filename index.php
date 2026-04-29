<?php
// index.php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('session.gc_probability', 0);

@session_start(['gc_probability' => 0]);
if (isset($_SESSION['user_id'])) {
    header("Location: modules/dashboard/index.php");
} else {
    header("Location: modules/auth/login.php");
}
exit;
?>