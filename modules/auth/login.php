<?php
// modules/auth/login.php
session_start();
require_once '../../config/db.php';
require_once '../../includes/functions.php';

$error = '';

if (isset($_GET['error'])) {
    switch ($_GET['error']) {
        case 'timeout':
            $error = 'Tu sesión ha expirado por inactividad. Por favor, ingresa nuevamente.';
            break;
        case 'account_issue':
            $error = 'Tu cuenta ha sido desactivada o eliminada.';
            break;
        default:
            $error = '';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = clean($_POST['username']);
    $password = $_POST['password'];

    if (empty($username) || empty($password)) {
        $error = "Por favor ingrese ambos campos.";
    } else {
        $stmt = $pdo->prepare("SELECT u.*, r.name as role_name FROM users u JOIN roles r ON u.role_id = r.id WHERE u.username = ? AND u.status = 'active'");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && $user['username'] === $username && password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role_id'] = $user['role_id'];
            $_SESSION['role_name'] = $user['role_name'];
            $_SESSION['last_activity'] = time();

            // PRE-LOAD PERMISSIONS & MODULE ACCESS (PERFORMANCE OPTIMIZATION)
            $_SESSION['permissions_codes'] = [];
            $_SESSION['module_overrides'] = [];

            if ($user['role_id'] != 1) { // Skip for Admin
                // 1. Load Role Permissions
                $stmtP = $pdo->prepare("
                    SELECT p.code 
                    FROM role_permissions rp 
                    JOIN permissions p ON rp.permission_id = p.id 
                    WHERE rp.role_id = ?
                ");
                $stmtP->execute([$user['role_id']]);
                $_SESSION['permissions_codes'] = $stmtP->fetchAll(PDO::FETCH_COLUMN);

                // 2. Load User Module Overrides
                $stmtO = $pdo->prepare("SELECT module_name, is_enabled FROM user_custom_modules WHERE user_id = ?");
                $stmtO->execute([$user['id']]);
                $overrides = $stmtO->fetchAll();
                foreach($overrides as $ov) {
                    $_SESSION['module_overrides'][$ov['module_name']] = (bool)$ov['is_enabled'];
                }
            }
            
            log_audit($pdo, 'users', $user['id'], 'UPDATE', null, ['event' => 'login'], $user['id'], $_SERVER['REMOTE_ADDR']);

            header("Location: ../../modules/dashboard/index.php");
            exit;
        } else {
            $error = "Credenciales incorrectas.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - System Taller</title>
    <!-- Phosphor Icons -->
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <link rel="icon" type="image/png" href="../../assets/favicon.png">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>
        body {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
        }
        .login-card {
            width: 100%;
            max-width: 480px;
            padding: 3rem 2rem;
            border: 1px solid rgba(255, 255, 255, 0.1);
            background: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(20px);
            border-radius: 24px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        }
        .input-group {
            position: relative;
            margin-bottom: 1.5rem;
        }
        .input-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--slate-400);
            pointer-events: none;
            font-size: 1.25rem;
            transition: color 0.3s;
        }
        .form-control {
            width: 100%;
            padding-left: 3rem;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--border-color);
            color: white;
            height: 3.5rem;
            border-radius: 12px;
            font-size: 1rem;
        }
        .form-control:focus {
            background: rgba(255, 255, 255, 0.08);
            border-color: var(--primary-500);
        }
        .form-control:focus + .input-icon {
            color: var(--primary-500);
        }
        .brand-logo-large {
            width: 64px;
            height: 64px;
            background: linear-gradient(135deg, var(--primary-600), var(--primary-500));
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            color: white;
            margin: 0 auto 1.5rem;
            box-shadow: 0 0 30px var(--primary-glow);
        }

        /* Floating Tools Animation */
        .floating-tools {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: -1;
            overflow: hidden;
        }
        .tool-icon {
            position: absolute;
            color: rgba(255, 255, 255, 0.06);
            font-size: 4rem;
            animation: float 20s infinite linear;
        }
        .tool-icon:nth-child(1) { top: 10%; left: 10%; animation-duration: 25s; font-size: 5rem; }
        .tool-icon:nth-child(2) { top: 20%; left: 80%; animation-duration: 30s; animation-delay: -5s; }
        .tool-icon:nth-child(3) { top: 80%; left: 15%; animation-duration: 22s; animation-delay: -10s; font-size: 6rem; }
        .tool-icon:nth-child(4) { top: 70%; left: 85%; animation-duration: 28s; animation-delay: -2s; }
        .tool-icon:nth-child(5) { top: 40%; left: 40%; animation-duration: 35s; animation-delay: -15s; font-size: 3rem; }
        .tool-icon:nth-child(6) { top: 50%; left: 90%; animation-duration: 24s; animation-delay: -8s; }
        .tool-icon:nth-child(7) { top: 15%; left: 50%; animation-duration: 29s; animation-delay: -12s; }
        .tool-icon:nth-child(8) { top: 85%; left: 60%; animation-duration: 32s; animation-delay: -6s; font-size: 5rem; }

        @keyframes float {
            0% { transform: translate(0, 0) rotate(0deg); }
            33% { transform: translate(30px, -50px) rotate(10deg); }
            66% { transform: translate(-20px, 20px) rotate(-5deg); }
            100% { transform: translate(0, 0) rotate(0deg); }
        }

        /* LIGHT MODE OVERRIDES */
        body.light-mode.login-bg {
            background: linear-gradient(135deg, #f1f5f9 0%, #cbd5e1 100%);
        }
        
        body.light-mode .login-card {
            background: rgba(255, 255, 255, 0.85);
            border: 1px solid rgba(0, 0, 0, 0.05);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.1);
        }
        
        body.light-mode h1 {
            color: #0f172a !important;
        }
        
        body.light-mode .text-muted {
            color: #64748b !important;
        }

        body.light-mode .form-control {
            background: white;
            border-color: #cbd5e1;
            color: #0f172a;
        }

        body.light-mode .form-control:focus {
            border-color: var(--primary-500);
            box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.2);
        }

        body.light-mode .tool-icon {
            color: rgba(0,0,0, 0.1);
        }
        
        body.light-mode .input-icon {
            color: #94a3b8;
        }

        body.light-mode .form-control:focus + .input-icon {
            color: var(--primary-500);
        }
    </style>
</head>
<body class="login-bg">
    
    <!-- Tracking Button -->
    <a href="track.php" style="position: fixed; top: 2rem; right: 2rem; display: flex; align-items: center; gap: 0.5rem; background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.1); padding: 0.75rem 1.25rem; border-radius: 100px; color: white; text-decoration: none; font-size: 0.9rem; backdrop-filter: blur(10px); transition: all 0.3s; z-index: 100;" onmouseover="this.style.background='rgba(255,255,255,0.1)'; this.style.borderColor='var(--primary-500)';" onmouseout="this.style.background='rgba(255,255,255,0.05)'; this.style.borderColor='rgba(255,255,255,0.1)';">
        <i class="ph ph-magnifying-glass"></i>
        <span>Seguimiento de Equipo</span>
    </a>
    
    <!-- Animated Background -->
    <div class="floating-tools">
        <i class="ph ph-wrench tool-icon"></i>
        <i class="ph ph-gear tool-icon"></i>
        <i class="ph ph-screwdriver tool-icon"></i>
        <i class="ph ph-desktop tool-icon"></i>
        <i class="ph ph-hammer tool-icon"></i>
        <i class="ph ph-plugs tool-icon"></i>
        <i class="ph ph-wifi-high tool-icon"></i>
        <i class="ph ph-cpu tool-icon"></i>
    </div>
    
    <div class="login-card animate-enter">
        <div class="text-center mb-5">
            <div class="brand-logo-large">
                <i class="ph-bold ph-wrench"></i>
            </div>
            <h1 style="font-size: 1.75rem; margin-bottom: 0.5rem;">Bienvenido</h1>
            <p class="text-muted">Ingresa a tu cuenta para continuar</p>
        </div>

        <?php if($error): ?>
            <div style="background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.2); color: #fca5a5; padding: 1rem; border-radius: 12px; margin-bottom: 2rem; display: flex; align-items: center; gap: 0.75rem; font-size: 0.9rem;">
                <i class="ph-fill ph-warning-circle" style="font-size: 1.25rem;"></i>
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="input-group">
                <input type="text" id="username" name="username" class="form-control" placeholder="Nombre de usuario" required autocomplete="off">
                <i class="ph ph-user input-icon"></i>
            </div>
            
            <div class="input-group">
                <input type="password" id="password" name="password" class="form-control" placeholder="Contraseña" required>
                <i class="ph ph-lock-key input-icon"></i>
            </div>
            


            <button type="submit" class="btn btn-primary" style="width: 100%; height: 3.5rem; font-size: 1rem; justify-content: center;">
                Iniciar Sesión <i class="ph-bold ph-arrow-right"></i>
            </button>
        </form>
        
        <div style="text-align: center; margin-top: 2rem; border-top: 1px solid var(--border-color); padding-top: 1.5rem;">
            <p class="text-muted text-sm">&copy; <?php echo date('Y'); ?> System Taller. Todos los derechos reservados.</p>
            <!-- <p class="text-muted text-xs" style="margin-top: 0.25rem; opacity: 0.6;">Desarrollado por Bryan Rivas</p> -->
            <p class="text-muted text-xs" style="margin-top: 0.5rem; opacity: 0.5; font-style: italic;">Nota: "Este producto es para uso interno, no puede ser comercializado"</p>
        </div>
    </div>

    <!-- Theme Toggle Button -->
    <button class="theme-toggle" id="themeToggle" data-tooltip="Cambiar tema" aria-label="Toggle theme">
        <i class="ph-fill ph-sun theme-toggle-icon sun"></i>
        <i class="ph-fill ph-moon theme-toggle-icon moon"></i>
    </button>

    <script>
    // Theme Toggle Functionality
    (function() {
        const themeToggle = document.getElementById('themeToggle');
        const body = document.body;
        let hideTimeout;
        
        // Check for saved theme preference or default to dark mode
        const currentTheme = localStorage.getItem('theme') || 'dark';
        
        // Apply saved theme on page load
        if (currentTheme === 'light') {
            body.classList.add('light-mode');
        }
        
        // Toggle theme on button click
        themeToggle.addEventListener('click', function() {
            // Add ripple effect
            this.classList.add('ripple');
            setTimeout(() => this.classList.remove('ripple'), 600);
            
            // Toggle light mode class
            body.classList.toggle('light-mode');
            
            // Save preference to localStorage
            const theme = body.classList.contains('light-mode') ? 'light' : 'dark';
            localStorage.setItem('theme', theme);
            
            // Update tooltip
            this.setAttribute('data-tooltip', 
                theme === 'light' ? 'Modo oscuro' : 'Modo claro'
            );
        });
        
        // Set initial tooltip
        themeToggle.setAttribute('data-tooltip', 
            currentTheme === 'light' ? 'Modo oscuro' : 'Modo claro'
        );
        
        // Proximity-based auto-hide functionality
        const PROXIMITY_THRESHOLD = 200; // pixels from bottom-right corner
        
        function hideButton() {
            themeToggle.classList.add('hidden');
        }
        
        function showButton() {
            themeToggle.classList.remove('hidden');
        }
        
        function checkProximity(mouseX, mouseY) {
            const windowWidth = window.innerWidth;
            const windowHeight = window.innerHeight;
            
            // Calculate distance from bottom-right corner
            const distanceFromRight = windowWidth - mouseX;
            const distanceFromBottom = windowHeight - mouseY;
            
            // Show button if mouse is within threshold of bottom-right corner
            if (distanceFromRight <= PROXIMITY_THRESHOLD && distanceFromBottom <= PROXIMITY_THRESHOLD) {
                clearTimeout(hideTimeout);
                showButton();
            } else {
                // Hide after a short delay when mouse leaves the area
                clearTimeout(hideTimeout);
                hideTimeout = setTimeout(hideButton, 500);
            }
        }
        
        // Track mouse position
        document.addEventListener('mousemove', function(e) {
            checkProximity(e.clientX, e.clientY);
        });
        
        // Keep button visible when hovering over it
        themeToggle.addEventListener('mouseenter', function() {
            clearTimeout(hideTimeout);
            showButton();
        });
        
        // Start hide timer when mouse leaves button
        themeToggle.addEventListener('mouseleave', function(e) {
            checkProximity(e.clientX, e.clientY);
        });
        
        // Initially hide the button
        hideButton();
        
        // Optional: Add pulse animation for first-time users
        if (!localStorage.getItem('themeToggleSeen')) {
            // Show button with pulse for first-time users
            showButton();
            themeToggle.classList.add('pulse');
            setTimeout(() => {
                themeToggle.classList.remove('pulse');
                localStorage.setItem('themeToggleSeen', 'true');
                // Hide after pulse animation
                hideTimeout = setTimeout(hideButton, 2000);
            }, 6000); // Pulse for 6 seconds
        }
    })();
    </script>
</body>
</html>
