<?php
// includes/header.php
if (!isset($page_title)) $page_title = 'System Taller';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo esc($page_title); ?> - System Taller</title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="<?php echo BASE_URL; ?>assets/favicon.png?v=<?php echo time(); ?>">
    
    <!-- CSS -->
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/style.css?v=<?php echo time(); ?>">
    
    <!-- Icons (Phosphor Icons for a clean, modern look) -->
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <!-- Chart.js for Reports -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<?php
$gerencia_class = isset($_SESSION['role_id']) ? ' role-gerencia-layout' : '';
?>
<body class="<?php echo isset($_COOKIE['theme']) && $_COOKIE['theme'] == 'light' ? 'light-mode' : ''; echo $gerencia_class; ?>">
    <script>
        // Aplicar estado del sidebar antes de que el navegador dibuje la pantalla para evitar el efecto de "abrir y cerrar"
        if (localStorage.getItem('sidebarCollapsed') === 'true' && document.body.classList.contains('role-gerencia-layout')) {
            document.body.classList.add('sidebar-collapsed');
        }
    </script>
    <div class="wrapper">
