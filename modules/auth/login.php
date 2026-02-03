<?php
// modules/auth/login.php
session_start();
require_once '../../config/db.php';
require_once '../../includes/functions.php';

$error = '';

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
    </style>
</head>
<body class="login-bg">
    
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

</body>
</html>
