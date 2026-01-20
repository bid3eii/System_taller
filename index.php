<?php
// index.php
session_start();
if (isset($_SESSION['user_id'])) {
    header("Location: modules/dashboard/index.php");
} else {
    header("Location: modules/auth/login.php");
}
exit;
?>
