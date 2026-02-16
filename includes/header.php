<?php
// includes/header.php
if (!isset($page_title)) $page_title = 'System Taller';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> - System Taller</title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="<?php echo BASE_URL; ?>assets/favicon.png?v=<?php echo time(); ?>">
    
    <!-- CSS -->
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/style.css?v=<?php echo time(); ?>">
    
    <!-- Icons (Phosphor Icons for a clean, modern look) -->
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    
    <!-- Chart.js for Reports -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="<?php echo isset($_COOKIE['theme']) && $_COOKIE['theme'] == 'light' ? 'light-mode' : ''; ?>">
    <div class="wrapper">
