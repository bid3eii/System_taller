<?php
// modules/auth/logout.php
ini_set('session.gc_probability', 0);
session_start();
session_unset();
session_destroy();
header("Location: login.php");
exit;
?>
