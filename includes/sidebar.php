<?php
// includes/sidebar.php (Now actually a Navbar)
?>
<header class="navbar">
    <!-- Brand -->
    <a href="/System_Taller/modules/dashboard/index.php" class="navbar-brand" style="text-decoration: none; color: inherit;">
        <div class="brand-logo-small">
            <i class="ph-bold ph-wrench"></i>
        </div>
        <div>
            <h3 style="margin:0; font-size: 1.1rem;">System<span style="color: var(--primary-500);">Taller</span></h3>
        </div>
    </a>
    

    <!-- Menu -->
    <nav class="navbar-menu">
        <?php 
            // 1. Fetch User Order
            $navbar_order = [];
            if(isset($_SESSION['user_id'])){
                try {
                    $stmtO = $pdo->prepare("SELECT navbar_order FROM users WHERE id = ?");
                    $stmtO->execute([$_SESSION['user_id']]);
                    $storedOrder = $stmtO->fetchColumn();
                    if($storedOrder) $navbar_order = json_decode($storedOrder, true);
                } catch (PDOException $e) {
                    // Check for invalid column error (1054 or 42S22)
                    if (strpos($e->getMessage(), 'Unknown column') !== false || $e->getCode() == '42S22') {
                        try {
                            // Attempt to self-heal
                            $pdo->exec("ALTER TABLE users ADD COLUMN navbar_order TEXT DEFAULT NULL");
                            // Retry fetch
                            $stmtO = $pdo->prepare("SELECT navbar_order FROM users WHERE id = ?");
                            $stmtO->execute([$_SESSION['user_id']]);
                            $storedOrder = $stmtO->fetchColumn();
                            if($storedOrder) $navbar_order = json_decode($storedOrder, true);
                        } catch (Exception $ex) {
                            // Suppress further errors to keep site running
                        }
                    }
                }
            }

            // 2. Define Menu Items
            $menu_items = [];

            // Clients
            if(can_access_module('clients', $pdo)) {
                $menu_items['clients'] = [
                    'type' => 'link',
                    'url' => '/System_Taller/modules/clients/index.php',
                    'icon' => 'ph-users',
                    'label' => 'Clientes',
                    'active' => strpos($_SERVER['REQUEST_URI'], 'clients') !== false
                ];
            }

            // Equipment
            if(can_access_module('equipment', $pdo)) {
                 $menu_items['equipment'] = [
                    'type' => 'dropdown',
                    'url' => '#',
                    'icon' => 'ph-desktop',
                    'label' => 'Equipos',
                    'active' => (strpos($_SERVER['REQUEST_URI'], 'equipment') !== false && strpos($_SERVER['REQUEST_URI'], 'type=warranty') === false),
                    'children' => [
                        ['url' => '/System_Taller/modules/equipment/entry.php', 'icon' => 'ph-arrow-right-in', 'label' => 'Entrada'],
                        ['url' => '/System_Taller/modules/equipment/exit.php', 'icon' => 'ph-arrow-left-out', 'label' => 'Salida']
                    ]
                ];
            }

            // New Warranty
            if(can_access_module('new_warranty', $pdo)) {
                 $menu_items['new_warranty'] = [
                    'type' => 'link',
                    'url' => '/System_Taller/modules/equipment/entry.php?type=warranty',
                    'icon' => 'ph-plus-circle',
                    'label' => 'Registro de Garantía',
                    'active' => ((strpos($_SERVER['REQUEST_URI'], 'entry.php') !== false && isset($_GET['type']) && $_GET['type'] === 'warranty') || (isset($_GET['return_to']) && $_GET['return_to'] === 'entry'))
                ];
            }

            // Tools
            if(can_access_module('tools', $pdo)) {
                 $menu_items['tools'] = [
                    'type' => 'link',
                    'url' => '/System_Taller/modules/tools/index.php',
                    'icon' => 'ph-wrench',
                    'label' => 'Herramientas',
                    'active' => strpos($_SERVER['REQUEST_URI'], 'tools') !== false
                ];
            }

            // Requests (Solicitud)
            $can_services = can_access_module('services', $pdo);
            $can_warranties = can_access_module('warranties', $pdo);
            $can_history = can_access_module('history', $pdo);
            
            if ($can_services || $can_warranties || $can_history) {
                $children = [];
                if($can_services) $children[] = ['url' => '/System_Taller/modules/services/index.php', 'icon' => 'ph-wrench', 'label' => 'Servicios'];
                if($can_warranties) $children[] = ['url' => '/System_Taller/modules/warranties/index.php', 'icon' => 'ph-shield-check', 'label' => 'Garantías'];
                if($can_history) $children[] = ['url' => '/System_Taller/modules/history/index.php', 'icon' => 'ph-clock-counter-clockwise', 'label' => 'Historial'];

                $menu_items['requests'] = [
                    'type' => 'dropdown',
                    'url' => '#',
                    'icon' => 'ph-clipboard-text',
                    'label' => 'Solicitud',
                    'active' => ((strpos($_SERVER['REQUEST_URI'], 'warranties') !== false || strpos($_SERVER['REQUEST_URI'], 'services') !== false) && (!isset($_GET['return_to']) || $_GET['return_to'] !== 'entry')),
                    'children' => $children
                ];
            }

            // Reports
            if(can_access_module('reports', $pdo)) {
                 $menu_items['reports'] = [
                    'type' => 'link',
                    'url' => '/System_Taller/modules/reports/index.php',
                    'icon' => 'ph-chart-bar',
                    'label' => 'Reportes',
                    'active' => strpos($_SERVER['REQUEST_URI'], 'reports') !== false
                ];
            }

            // 3. Determine Final Order
            $default_keys = array_keys($menu_items);
            // If user has saved order, prioritize it
            $final_keys = [];
            if (!empty($navbar_order) && is_array($navbar_order)) {
                // Add saved keys if they exist in valid items
                foreach($navbar_order as $key) {
                    if(isset($menu_items[$key])) {
                        $final_keys[] = $key;
                    }
                }
                // Add any newly added modules that weren't in user's saved list
                foreach($default_keys as $key) {
                    if(!in_array($key, $final_keys)) {
                        $final_keys[] = $key;
                    }
                }
            } else {
                $final_keys = $default_keys;
            }

            // 4. Render
            foreach($final_keys as $key) {
                $item = $menu_items[$key];
                $activeClass = $item['active'] ? 'active' : '';
                
                if ($key === 'reports') {
                    // Retain specific separator for reports if desired, or make it generic
                    echo '<div style="width: 1px; height: 24px; background: var(--border-color); margin: 0 0.5rem;"></div>';
                }

                if ($item['type'] === 'link') {
                    echo "<a href=\"{$item['url']}\" class=\"nav-link {$activeClass}\">";
                    echo "<i class=\"ph {$item['icon']}\"></i> {$item['label']}";
                    echo "</a>";
                } elseif ($item['type'] === 'dropdown') {
                    echo "<div class=\"dropdown\">";
                    echo "<a href=\"#\" class=\"nav-link {$activeClass}\">";
                    echo "<i class=\"ph {$item['icon']}\"></i> {$item['label']} <i class=\"ph-bold ph-caret-down\" style=\"font-size: 0.8rem;\"></i>";
                    echo "</a>";
                    echo "<div class=\"dropdown-content\">";
                    foreach($item['children'] as $child) {
                        echo "<a href=\"{$child['url']}\" class=\"dropdown-item\">";
                        echo "<i class=\"ph {$child['icon']}\"></i> {$child['label']}";
                        echo "</a>";
                    }
                    echo "</div>";
                    echo "</div>";
                }
            }
        ?>
    </nav>
    
    <!-- User Profile & Dropdown -->
    <div class="navbar-user dropdown" style="cursor: pointer; padding-right: 0;">
        <div class="user-avatar-sm">
            <?php echo strtoupper(substr($_SESSION['username'] ?? 'U', 0, 1)); ?>
        </div>
        <div style="line-height: 1.2;">
            <p class="text-sm font-medium"><?php echo htmlspecialchars($_SESSION['username'] ?? 'User'); ?></p>
            <p class="text-xs text-muted"><?php echo htmlspecialchars($_SESSION['role_name'] ?? 'Role'); ?></p>
        </div>
        <i class="ph-bold ph-caret-down" style="font-size: 0.8rem; margin-left: 0.5rem; color: var(--text-secondary);"></i>
        
        <div class="dropdown-content" style="left: auto; right: 0; min-width: 180px; top: 100%;">
            <a href="/System_Taller/modules/profile/index.php" class="dropdown-item">
                <i class="ph ph-user-circle"></i> Mi Perfil
            </a>
            <?php if(can_access_module('settings', $pdo)): ?>
            <a href="/System_Taller/modules/settings/index.php" class="dropdown-item">
                <i class="ph ph-gear"></i> Configuración
            </a>
            <?php endif; ?>
            <div style="height: 1px; background: var(--border-color); margin: 0.25rem 0;"></div>
            <a href="/System_Taller/modules/auth/logout.php" class="dropdown-item" style="color: var(--danger);">
                <i class="ph ph-sign-out"></i> Salir
            </a>
        </div>
    </div>
</header>

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

<div class="scroll-wrapper">
<main class="main-content">
