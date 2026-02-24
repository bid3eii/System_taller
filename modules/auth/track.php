<?php
// modules/auth/track.php
require_once '../../config/db.php';
require_once '../../includes/functions.php';

$search = $_GET['order_id'] ?? '';
$order_data = null;
$history_data = [];
$error = '';

if ($search) {
    // Clean input: remove common prefixes and symbols
    $raw_input = strtoupper($search);
    $digits_only = str_replace(['CASO', 'ORD-', '#', ' '], '', $raw_input);
    $clean_id = ltrim($digits_only, '0'); 

    if (empty($clean_id)) $clean_id = '0'; // Fallback

    $stmt = $pdo->prepare("
        SELECT so.*, c.name as client_name, e.brand, e.model, u.username as tech_full_name
        FROM service_orders so
        LEFT JOIN clients c ON so.client_id = c.id
        LEFT JOIN equipments e ON so.equipment_id = e.id
        LEFT JOIN users u ON so.assigned_tech_id = u.id
        WHERE so.id = ?
    ");
    $stmt->execute([$clean_id]);
    $order_data = $stmt->fetch();

    if ($order_data) {
        // Enforce strict digit matching
        // A case is #000001. We accept "1" or "000001", but NOT "01", "001", etc.
        $padded_id = str_pad($order_data['id'], 6, '0', STR_PAD_LEFT);
        $raw_id = (string)$order_data['id'];

        if ($digits_only !== $padded_id && $digits_only !== $raw_id) {
            $order_data = null;
            $error = "Número de caso inválido. Ingrese el número exacto (ej: $padded_id o $raw_id).";
        }
    }

    if ($order_data) {
        // Fetch History for the Stepper Notes
        $stmtH = $pdo->prepare("
            SELECT h.*, u.username as user_name 
            FROM service_order_history h
            LEFT JOIN users u ON h.user_id = u.id
            WHERE h.service_order_id = ?
            ORDER BY h.created_at ASC
        ");
        $stmtH->execute([$order_data['id']]);
        $history_raw = $stmtH->fetchAll();
        
        // Group notes by action (status change) to show in tooltip
        foreach($history_raw as $h) {
            $history_data[$h['action']][] = [
                'date' => date('d/m H:i', strtotime($h['created_at'])),
                'note' => $h['notes'],
                'user' => $h['user_name']
            ];
        }
    } else {
        $error = "No se encontró ninguna orden con ese número.";
    }
}

// Status Mapping for Stepper
$steps = [
    'received' => ['label' => 'Recibido', 'icon' => 'ph-hand-pointing'],
    'diagnosing' => ['label' => 'Diagnóstico', 'icon' => 'ph-stethoscope'],
    'pending_approval' => ['label' => 'En Espera', 'icon' => 'ph-timer'],
    'in_repair' => ['label' => 'Reparación', 'icon' => 'ph-wrench'],
    'ready' => ['label' => 'Listo', 'icon' => 'ph-check-circle']
];

$current_status = $order_data['status'] ?? '';
$status_order = array_keys($steps);
$current_index = array_search($current_status, $status_order);
if ($current_index === false && $current_status === 'delivered') $current_index = 4; // Treat delivered as Ready for the public tracker
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seguimiento de Equipo - System Taller</title>
    <!-- Phosphor Icons -->
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <link rel="icon" type="image/png" href="../../assets/favicon.png">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-glow: rgba(99, 102, 241, 0.4);
            --bg-dark: #0f172a;
            --card-bg: rgba(30, 41, 59, 0.7);
            --text-main: white;
            --text-mute: rgba(255, 255, 255, 0.4);
            --border-glass: rgba(255, 255, 255, 0.1);
            --input-bg: rgba(15, 23, 42, 0.5);
            --step-bg: #1e293b;
            --tooltip-bg: #1e293b;
        }

        body.light-mode {
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            color: #0f172a;
            --text-main: #0f172a;
            --text-mute: #64748b;
            --card-bg: rgba(255, 255, 255, 0.9);
            --border-glass: rgba(0, 0, 0, 0.05);
            --input-bg: white;
            --step-bg: #f1f5f9;
            --tooltip-bg: #f8fafc;
        }

        body {
            font-family: 'Outfit', sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            background: radial-gradient(circle at top right, #1e1b4b, #0f172a);
            color: var(--text-main);
            padding: 1rem;
            margin: 0;
            overflow-x: hidden;
            transition: background 0.5s ease;
        }
        .container {
            width: 100%;
            max-width: 600px;
            animation: fadeIn 0.6s ease-out;
            position: relative;
            z-index: 10;
        }
        .track-card {
            background: var(--card-bg);
            backdrop-filter: blur(24px);
            border: 1px solid var(--border-glass);
            border-radius: 32px;
            padding: 2.5rem;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            position: relative;
        }
        body.light-mode .track-card {
            box-shadow: 0 20px 40px -10px rgba(0, 0, 0, 0.05);
        }
        .track-card::before {
            content: '';
            position: absolute;
            top: -1px;
            left: 10%;
            right: 10%;
            height: 1px;
            background: linear-gradient(90deg, transparent, var(--primary-500), transparent);
        }
        .header {
            text-align: center;
            margin-bottom: 2.5rem;
        }
        .logo-box {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #4f46e5, #6366f1);
            border-radius: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            font-size: 2.5rem;
            color: white !important;
            box-shadow: 0 10px 25px rgba(79, 70, 229, 0.4);
            animation: float 4s infinite ease-in-out;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }
        .form-control {
            width: 100%;
            padding: 1.25rem 1.25rem 1.25rem 3.5rem;
            background: var(--input-bg);
            border: 1px solid var(--border-glass);
            border-radius: 16px;
            color: var(--text-main);
            font-size: 1.1rem;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        body.light-mode .form-control {
            border-color: #cbd5e1;
            box-shadow: 0 2px 4px rgba(0,0,0,0.02);
        }
        .form-control:focus {
            border-color: var(--primary-500);
            background: var(--input-bg);
            box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.2);
            outline: none;
        }
        .input-wrapper {
            position: relative;
            margin-bottom: 1.5rem;
        }
        .input-icon {
            position: absolute;
            left: 1.25rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--primary-400);
            font-size: 1.5rem;
        }
        .btn-track {
            width: 100%;
            height: 3.5rem;
            font-weight: 600;
            font-size: 1.1rem;
            border-radius: 16px;
            justify-content: center;
            transition: all 0.3s;
            background: var(--primary-600);
            border: none;
            color: white;
            cursor: pointer;
            box-shadow: 0 4px 15px rgba(99, 102, 241, 0.3);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .btn-track:hover {
            background: var(--primary-500);
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(99, 102, 241, 0.4);
        }

        /* STEPPER STYLES */
        .stepper {
            display: flex;
            justify-content: space-between;
            margin: 3rem 0;
            position: relative;
            padding: 0 10px;
        }
        .stepper::before {
            content: '';
            position: absolute;
            top: 20px;
            left: 30px;
            right: 30px;
            height: 2px;
            background: var(--border-glass);
            z-index: 1;
        }
        body.light-mode .stepper::before {
            background: #e2e8f0;
        }
        .step {
            display: flex;
            flex-direction: column;
            align-items: center;
            z-index: 2;
            position: relative;
            width: 80px;
        }
        .step-circle {
            width: 40px;
            height: 40px;
            background: var(--step-bg);
            border: 2px solid var(--border-glass);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            margin-bottom: 0.75rem;
            transition: all 0.4s ease;
            position: relative;
            cursor: pointer;
            color: var(--text-mute);
        }
        body.light-mode .step-circle {
            border-color: #cbd5e1;
        }
        .step.active .step-circle {
            background: var(--primary-600);
            border-color: var(--primary-400);
            box-shadow: 0 0 15px var(--primary-glow);
            animation: pulse-border 2s infinite;
            color: white;
        }
        .step.completed .step-circle {
            background: #10b981;
            border-color: #34d399;
            color: white;
        }
        .step-label {
            font-size: 0.7rem;
            font-weight: 500;
            color: var(--text-mute);
            text-align: center;
            transition: color 0.3s;
        }
        .step.active .step-label, .step.completed .step-label {
            color: var(--text-main);
        }

        /* TOOLTIP / NOTES */
        .note-tooltip {
            position: absolute;
            bottom: 120%;
            left: 50%;
            transform: translateX(-50%) translateY(10px);
            background: var(--tooltip-bg);
            border: 1px solid var(--border-glass);
            border-radius: 12px;
            padding: 1rem;
            width: 220px;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s;
            z-index: 100;
            box-shadow: 0 10px 25px rgba(0,0,0,0.5);
            pointer-events: none;
            color: var(--text-main);
        }
        body.light-mode .note-tooltip {
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            border-color: #e2e8f0;
        }
        .step-circle:hover .note-tooltip {
            opacity: 1;
            visibility: visible;
            transform: translateX(-50%) translateY(0);
        }
        .note-tooltip::after {
            content: '';
            position: absolute;
            top: 100%;
            left: 50%;
            transform: translateX(-50%);
            border: 8px solid transparent;
            border-top-color: var(--tooltip-bg);
        }
        .note-item {
            margin-bottom: 0.75rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid var(--border-glass);
        }
        body.light-mode .note-item {
            border-bottom-color: #f1f5f9;
        }
        .note-item:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }
        .note-meta {
            font-size: 0.65rem;
            color: var(--primary-400);
            margin-bottom: 0.25rem;
            display: flex;
            justify-content: space-between;
        }
        .note-text {
            font-size: 0.8rem;
            line-height: 1.4;
            color: var(--text-main);
            opacity: 0.9;
        }

        /* DETAIL CARD */
        .info-container {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid var(--border-glass);
            border-radius: 20px;
            padding: 1.5rem;
            margin-top: 1rem;
        }
        body.light-mode .info-container {
            background: #f8fafc;
            border-color: #e2e8f0;
        }
        .detail-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 0;
            border-bottom: 1px solid var(--border-glass);
        }
        body.light-mode .detail-item {
            border-bottom-color: #f1f5f9;
        }
        .detail-item:last-child {
            border-bottom: none;
        }
        .detail-label {
            font-size: 0.9rem;
            color: var(--text-mute);
        }
        .detail-value {
            font-weight: 500;
            color: var(--text-main);
        }

        @keyframes pulse-border {
            0% { box-shadow: 0 0 0 0 rgba(99, 102, 241, 0.4); }
            70% { box-shadow: 0 0 0 10px rgba(99, 102, 241, 0); }
            100% { box-shadow: 0 0 0 0 rgba(99, 102, 241, 0); }
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--text-mute);
            text-decoration: none;
            font-size: 0.9rem;
            margin-bottom: 1.5rem;
            transition: all 0.3s;
        }
        .back-btn:hover {
            color: var(--text-main);
            transform: translateX(-5px);
        }

        /* Theme Toggle styles from login.php */
        .theme-toggle {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            width: 56px;
            height: 56px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-600), var(--primary-500));
            border: 2px solid var(--border-glass);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.3), 0 0 20px var(--primary-glow);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            transition: all 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55), opacity 0.4s ease, transform 0.4s ease;
            overflow: hidden;
            outline: none;
        }

        .theme-toggle.hidden {
            opacity: 0;
            transform: translateX(100px);
            pointer-events: none;
        }
        .theme-toggle:hover {
            transform: scale(1.1) rotate(10deg);
        }
        .theme-toggle-icon {
            font-size: 1.5rem;
            color: white;
            transition: all 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55);
            position: absolute;
        }
        .theme-toggle-icon.sun { opacity: 0; transform: rotate(-180deg) scale(0); }
        .theme-toggle-icon.moon { opacity: 1; transform: rotate(0deg) scale(1); }
        
        body.light-mode .theme-toggle-icon.sun { opacity: 1; transform: rotate(0deg) scale(1); }
        body.light-mode .theme-toggle-icon.moon { opacity: 0; transform: rotate(180deg) scale(0); }

        /* Mobile Adjustments */
        @media (max-width: 500px) {
            .stepper::before { left: 15px; right: 15px; }
            .step { width: 60px; }
            .step-circle { width: 32px; height: 32px; font-size: 1rem; }
            .note-tooltip { width: 180px; left: 0%; transform: translateY(10px); }
            .step-circle:hover .note-tooltip { transform: translateY(0); }
            .track-card { padding: 1.5rem; }
        }
    </style>
</head>
<body class="dark-mode"> <!-- Initially set dark-mode, will be updated by JS -->
    <div class="container">
        <div class="track-card">
            <a href="login.php" class="back-btn">
                <i class="ph-bold ph-arrow-left"></i> Volver al Inicio
            </a>
            
            <div class="header">
                <div class="logo-box">
                    <i class="ph-fill ph-magnifying-glass"></i>
                </div>
                <h1 style="margin: 0; font-size: 2rem; font-weight: 700;">Seguimiento</h1>
                <p style="color: var(--text-mute); margin-top: 0.5rem;">Consulta el progreso de tu equipo</p>
            </div>

            <form method="GET" action="">
                <div class="input-wrapper">
                    <input type="text" name="order_id" class="form-control" placeholder="Ingresa # de Orden o Caso" value="<?php echo htmlspecialchars($search); ?>" required autofocus>
                    <i class="ph-fill ph-hash input-icon"></i>
                </div>
                <button type="submit" class="btn-track">
                    Buscar Caso <i class="ph-bold ph-arrow-right"></i>
                </button>
            </form>

            <?php if ($error): ?>
                <div style="margin-top: 2rem; background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.2); color: #ef4444; padding: 1.25rem; border-radius: 16px; text-align: center; animation: shake 0.5s;">
                    <i class="ph-fill ph-warning-circle" style="font-size: 1.2rem; vertical-align: middle; margin-right: 0.5rem;"></i>
                    <?php echo $error; ?>
                </div>
                <style>
                    @keyframes shake {
                        0%, 100% { transform: translateX(0); }
                        25% { transform: translateX(-10px); }
                        75% { transform: translateX(10px); }
                    }
                </style>
            <?php endif; ?>

            <?php if ($order_data): ?>
                <!-- STEPPER PROGRESS BAR -->
                <div class="stepper">
                    <?php 
                    $idx = 0;
                    foreach ($steps as $key => $config): 
                        $statusClass = '';
                        if ($idx < $current_index) $statusClass = 'completed';
                        elseif ($idx == $current_index) $statusClass = 'active';
                        
                        $notes = $history_data[$key] ?? [];
                    ?>
                        <div class="step <?php echo $statusClass; ?>">
                            <div class="step-circle">
                                <i class="ph-fill <?php echo ($statusClass == 'completed') ? 'ph-check' : $config['icon']; ?>"></i>
                                
                                <?php if (!empty($notes)): ?>
                                    <div class="note-tooltip">
                                        <div style="font-weight: 700; font-size: 0.75rem; margin-bottom: 0.75rem; color: var(--primary-500);">Notas de Actividad</div>
                                        <?php foreach ($notes as $n): ?>
                                            <div class="note-item">
                                                <div class="note-meta">
                                                    <span><?php echo $n['user']; ?></span>
                                                    <span><?php echo $n['date']; ?></span>
                                                </div>
                                                <div class="note-text"><?php echo nl2br(htmlspecialchars($n['note'])); ?></div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php elseif ($idx <= $current_index): ?>
                                    <div class="note-tooltip">
                                        <div class="note-text" style="text-align: center;">Sin comentarios adicionales en este paso.</div>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <span class="step-label"><?php echo $config['label']; ?></span>
                        </div>
                    <?php 
                    $idx++;
                    endforeach; 
                    ?>
                </div>

                <!-- INFO CARDS -->
                <div class="info-container">
                    <div class="detail-item">
                        <span class="detail-label">Cliente</span>
                        <span class="detail-value"><?php echo htmlspecialchars($order_data['client_name']); ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Equipo</span>
                        <span class="detail-value"><?php echo htmlspecialchars($order_data['brand'] . ' ' . $order_data['model']); ?></span>
                    </div>
                    <?php if ($order_data['tech_full_name']): ?>
                    <div class="detail-item">
                        <span class="detail-label">Técnico</span>
                        <span class="detail-value"><?php echo htmlspecialchars($order_data['tech_full_name']); ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="detail-item">
                        <span class="detail-label">Ingreso</span>
                        <span class="detail-value"><?php echo date('d/m/Y', strtotime($order_data['entry_date'])); ?></span>
                    </div>
                </div>

                <div style="text-align: center; margin-top: 2rem;">
                    <p style="font-size: 0.8rem; color: var(--text-mute);">Desliza el mouse sobre los círculos para ver bitácora técnica.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Theme Toggle Button -->
    <button class="theme-toggle" id="themeToggle" title="Cambiar tema">
        <i class="ph-fill ph-sun theme-toggle-icon sun"></i>
        <i class="ph-fill ph-moon theme-toggle-icon moon"></i>
    </button>

    <script>
        // Theme Logic
        const themeToggle = document.getElementById('themeToggle');
        const body = document.body;
        let hideTimeout;
        
        // Load preference
        const savedTheme = localStorage.getItem('theme') || 'dark';
        if (savedTheme === 'light') body.classList.add('light-mode');

        themeToggle.addEventListener('click', () => {
            body.classList.toggle('light-mode');
            const newTheme = body.classList.contains('light-mode') ? 'light' : 'dark';
            localStorage.setItem('theme', newTheme);
        });

        // Proximity Logic (from login.php)
        const PROXIMITY_THRESHOLD = 200;
        
        function hideButton() { themeToggle.classList.add('hidden'); }
        function showButton() { themeToggle.classList.remove('hidden'); }
        
        function checkProximity(mouseX, mouseY) {
            const windowWidth = window.innerWidth;
            const windowHeight = window.innerHeight;
            const distanceFromRight = windowWidth - mouseX;
            const distanceFromBottom = windowHeight - mouseY;
            
            if (distanceFromRight <= PROXIMITY_THRESHOLD && distanceFromBottom <= PROXIMITY_THRESHOLD) {
                clearTimeout(hideTimeout);
                showButton();
            } else {
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

        // Mobile touch for tooltips
        document.querySelectorAll('.step-circle').forEach(circle => {
            circle.addEventListener('touchstart', function() {
                const tooltip = this.querySelector('.note-tooltip');
                if (tooltip) {
                    const isVisible = tooltip.style.visibility === 'visible';
                    document.querySelectorAll('.note-tooltip').forEach(t => {
                        t.style.opacity = '0';
                        t.style.visibility = 'hidden';
                    });
                    if (!isVisible) {
                        tooltip.style.opacity = '1';
                        tooltip.style.visibility = 'visible';
                        tooltip.style.transform = 'translateX(-50%) translateY(0)';
                    }
                }
            });
        });
    </script>
</body>
</html>
