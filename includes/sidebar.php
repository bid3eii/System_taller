<?php
// includes/sidebar.php (Now actually a Navbar)
?>
<header class="navbar">
    <!-- Brand -->
    <a href="<?php echo BASE_URL; ?>modules/dashboard/index.php" class="navbar-brand"
        style="text-decoration: none; color: inherit;">
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
        if (isset($_SESSION['user_id'])) {
            try {
                $stmtO = $pdo->prepare("SELECT navbar_order FROM users WHERE id = ?");
                $stmtO->execute([$_SESSION['user_id']]);
                $storedOrder = $stmtO->fetchColumn();
                if ($storedOrder)
                    $navbar_order = json_decode($storedOrder, true);
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
                        if ($storedOrder)
                            $navbar_order = json_decode($storedOrder, true);
                    } catch (Exception $ex) {
                        // Suppress further errors to keep site running
                    }
                }
            }
        }

        // 2. Define Menu Items
        $menu_items = [];

        // --- SECTION 1: TALLER (Workshop) ---
        
        // Clients
        if (can_access_module('clients', $pdo)) {
            $menu_items['clients'] = [
                'type' => 'link',
                'url' => BASE_URL . 'modules/clients/index.php',
                'icon' => 'ph-users',
                'label' => 'Clientes',
                'active' => strpos($_SERVER['REQUEST_URI'], 'clients') !== false
            ];
        }

        // --- EQUIPO ---
        if (can_access_module('equipment', $pdo)) {
            $menu_items['equipment_menu'] = [
                'type' => 'dropdown',
                'url' => '#',
                'icon' => 'ph-monitor',
                'label' => 'Solicitud',
                'active' => (strpos($_SERVER['REQUEST_URI'], 'modules/equipment/') !== false && strpos($_SERVER['REQUEST_URI'], 'type=warranty') === false),
                'children' => [
                    ['url' => BASE_URL . 'modules/equipment/entry.php', 'icon' => 'ph-arrow-right-in', 'label' => 'Entrada'],
                    ['url' => BASE_URL . 'modules/equipment/exit.php', 'icon' => 'ph-arrow-left-out', 'label' => 'Salida'],
                    ['url' => BASE_URL . 'modules/equipment/history.php', 'icon' => 'ph-clock-counter-clockwise', 'label' => 'Historial S/N']
                ]
            ];
        }

        // --- BODEGA ---
        if (can_access_module('new_warranty', $pdo)) {
            if ($_SESSION['role_id'] == 5) {
                // For Bodega role, show items directly in navbar
                $menu_items['bodega_ingreso'] = [
                    'type' => 'link',
                    'url' => BASE_URL . 'modules/equipment/entry.php?type=warranty',
                    'icon' => 'ph-plus-circle',
                    'label' => 'Nuevo Ingreso',
                    'active' => strpos($_SERVER['REQUEST_URI'], 'entry.php?type=warranty') !== false
                ];
                $menu_items['bodega_registros'] = [
                    'type' => 'link',
                    'url' => BASE_URL . 'modules/warranties/database.php',
                    'icon' => 'ph-database',
                    'label' => 'Registros de Bodega',
                    'active' => strpos($_SERVER['REQUEST_URI'], 'warranties/database.php') !== false
                ];
                $menu_items['bodega_categorias'] = [
                    'type' => 'link',
                    'url' => BASE_URL . 'modules/categories/index.php',
                    'icon' => 'ph-list-dashes',
                    'label' => 'Categorías',
                    'active' => strpos($_SERVER['REQUEST_URI'], 'modules/categories/') !== false
                ];
            } else {
                // For others, keep the dropdown
                $menu_items['bodega_menu'] = [
                    'type' => 'dropdown',
                    'url' => '#',
                    'icon' => 'ph-package',
                    'label' => 'Bodega',
                    'active' => (strpos($_SERVER['REQUEST_URI'], 'type=warranty') !== false || strpos($_SERVER['REQUEST_URI'], 'warranties/database.php') !== false || strpos($_SERVER['REQUEST_URI'], 'modules/categories/') !== false),
                    'children' => [
                        ['url' => BASE_URL . 'modules/equipment/entry.php?type=warranty', 'icon' => 'ph-plus-circle', 'label' => 'Nuevo Ingreso Bodega'],
                        ['url' => BASE_URL . 'modules/warranties/database.php', 'icon' => 'ph-database', 'label' => 'Registros de Bodega'],
                        ['url' => BASE_URL . 'modules/categories/index.php', 'icon' => 'ph-list-dashes', 'label' => 'Categorías']
                    ]
                ];
            }
        }

        // Services (Renamed from Solicitud)
        $can_services = can_access_module('services', $pdo);
        $can_warranties = can_access_module('warranties', $pdo);
        $can_history = can_access_module('history', $pdo);

        if ($can_services || $can_warranties || $can_history) {
            $srv_children = [];
            if ($can_services)
                $srv_children[] = ['url' => BASE_URL . 'modules/services/index.php', 'icon' => 'ph-wrench', 'label' => 'Servicios'];
            if ($can_warranties)
                $srv_children[] = ['url' => BASE_URL . 'modules/warranties/index.php', 'icon' => 'ph-shield-check', 'label' => 'Servicio de Garantía'];
            if ($can_history)
                $srv_children[] = ['url' => BASE_URL . 'modules/history/index.php', 'icon' => 'ph-clock-counter-clockwise', 'label' => 'Historial General'];

            $menu_items['services'] = [
                'type' => 'dropdown',
                'url' => '#',
                'icon' => 'ph-clipboard-text',
                'label' => 'Servicios',
                'active' => (strpos($_SERVER['REQUEST_URI'], 'modules/services/') !== false || (strpos($_SERVER['REQUEST_URI'], 'modules/warranties/') !== false && strpos($_SERVER['REQUEST_URI'], 'database.php') === false) || strpos($_SERVER['REQUEST_URI'], 'modules/history/') !== false),
                'children' => $srv_children
            ];
        }

        // --- SECTION 2: CAMPO (Field Operations) ---

        // Agenda
        $can_sch = can_access_module('schedule', $pdo);
        $can_tagenda = can_access_module('tech_agenda', $pdo);
        $is_admin_role = isset($_SESSION['role_id']) && in_array($_SESSION['role_id'], [1, 7]);
        $can_master = $is_admin_role || can_access_module('master_visit_control', $pdo);

        if ($can_sch || $can_tagenda || $can_master) {
            $age_children = [];
            if ($can_sch)
                $age_children[] = ['url' => BASE_URL . 'modules/schedule/index.php', 'icon' => 'ph-calendar-blank', 'label' => 'Agenda / Visitas'];
            if ($can_tagenda)
                $age_children[] = ['url' => BASE_URL . 'modules/tech_agenda/index.php', 'icon' => 'ph-calendar-check', 'label' => 'Mi Agenda Técnica'];
            if ($can_master)
                $age_children[] = ['url' => BASE_URL . 'modules/tech_agenda/master_control.php', 'icon' => 'ph-monitor', 'label' => 'Control Maestro de Visitas'];

            $menu_items['agenda'] = [
                'type' => 'dropdown',
                'url' => '#',
                'icon' => 'ph-map-trifold',
                'label' => 'Agenda',
                'active' => (strpos($_SERVER['REQUEST_URI'], 'modules/schedule/') !== false || strpos($_SERVER['REQUEST_URI'], 'modules/tech_agenda/') !== false),
                'children' => $age_children
            ];
        }

        // --- SECTION 3: PROYECTOS Y FINANZAS (Management) ---

        // Projects
        $can_proy = can_access_module('proyectos', $pdo) || $is_admin_role;
        $can_surveys = can_access_module('surveys', $pdo);
        $can_viat = can_access_module('viaticos', $pdo);
        $can_phist = can_access_module('project_history', $pdo);

        if ($can_proy || $can_surveys || $can_viat || $can_phist) {
            $pro_children = [];
            if ($can_proy)
                $pro_children[] = ['url' => BASE_URL . 'modules/proyectos/index.php', 'icon' => 'ph-kanban', 'label' => 'Proyectos'];
            if ($can_surveys)
                $pro_children[] = ['url' => BASE_URL . 'modules/levantamientos/index.php', 'icon' => 'ph-clipboard', 'label' => 'Levantamientos'];
            if ($can_viat)
                $pro_children[] = ['url' => BASE_URL . 'modules/viaticos/index.php', 'icon' => 'ph-money', 'label' => 'Viáticos'];
            if ($can_phist)
                $pro_children[] = ['url' => BASE_URL . 'modules/project_history/index.php', 'icon' => 'ph-books', 'label' => 'Historial Proyectos'];

            $menu_items['projects_group'] = [
                'type' => 'dropdown',
                'url' => '#',
                'icon' => 'ph-folder-open',
                'label' => 'Proyectos',
                'active' => (strpos($_SERVER['REQUEST_URI'], 'modules/proyectos/') !== false || strpos($_SERVER['REQUEST_URI'], 'modules/viaticos/') !== false || strpos($_SERVER['REQUEST_URI'], 'modules/levantamientos/') !== false || strpos($_SERVER['REQUEST_URI'], 'modules/project_history/') !== false),
                'children' => $pro_children
            ];
        }

        // Administration (Financial/Backoffice)
        $can_com = can_access_module('comisiones', $pdo);
        $can_anex = can_access_module('anexos', $pdo);
        $can_fact = can_access_module('reporte_facturas', $pdo) || $is_admin_role;

        if ($can_com || $can_anex || $can_fact) {
            $adm_children = [];
            if ($can_com)
                $adm_children[] = ['url' => BASE_URL . 'modules/comisiones/index.php', 'icon' => 'ph-coins', 'label' => 'Panel de Incentivos'];
            if ($can_anex)
                $adm_children[] = ['url' => BASE_URL . 'modules/anexos/index.php', 'icon' => 'ph-file-pdf', 'label' => 'Anexos Yazaki'];
            if ($can_fact)
                $adm_children[] = ['url' => BASE_URL . 'modules/proyectos/reporte_facturas.php', 'icon' => 'ph-receipt', 'label' => 'Reporte de Facturas'];

            $menu_items['admin_group'] = [
                'type' => 'dropdown',
                'url' => '#',
                'icon' => 'ph-briefcase',
                'label' => 'Administración',
                'active' => (strpos($_SERVER['REQUEST_URI'], 'modules/comisiones/') !== false || strpos($_SERVER['REQUEST_URI'], 'modules/anexos/') !== false || strpos($_SERVER['REQUEST_URI'], 'reporte_facturas.php') !== false),
                'children' => $adm_children
            ];
        }

        // --- SECTION 4: SISTEMA (System Utils) ---
        
        // Reports
        if (can_access_module('reports', $pdo)) {
            $menu_items['reports'] = [
                'type' => 'link',
                'url' => BASE_URL . 'modules/reports/index.php',
                'icon' => 'ph-chart-bar',
                'label' => 'Reportes',
                'active' => strpos($_SERVER['REQUEST_URI'], 'reports') !== false
            ];
        }

        // Tools
        if (can_access_module('tools', $pdo)) {
            $menu_items['tools'] = [
                'type' => 'link',
                'url' => BASE_URL . 'modules/tools/index.php',
                'icon' => 'ph-wrench',
                'label' => 'Herramientas',
                'active' => strpos($_SERVER['REQUEST_URI'], 'tools') !== false
            ];
        }

        // Audit Log (Moved to Utilities)
        if ($_SESSION['role_name'] === 'SuperAdmin') {
            $menu_items['audit_log'] = [
                'type' => 'link',
                'url' => BASE_URL . 'modules/admin/audit_logs.php',
                'icon' => 'ph-shield-check',
                'label' => 'Auditoría',
                'active' => strpos($_SERVER['REQUEST_URI'], 'modules/admin/audit_logs.php') !== false
            ];
        }

        // 3. Determine Final Order
        $default_keys = array_keys($menu_items);
        $final_keys = [];
        if (!empty($navbar_order) && is_array($navbar_order)) {
            foreach ($navbar_order as $key) {
                if (isset($menu_items[$key])) {
                    $final_keys[] = $key;
                }
            }
            foreach ($default_keys as $key) {
                if (!in_array($key, $final_keys)) {
                    $final_keys[] = $key;
                }
            }
        } else {
            $final_keys = $default_keys;
        }

        // 4. Render
        $separators = ['agenda', 'projects_group', 'reports'];

        foreach ($final_keys as $key) {
            $item = $menu_items[$key];
            $activeClass = $item['active'] ? 'active' : '';

            if (in_array($key, $separators)) {
                echo '<div style="width: 1px; height: 24px; background: var(--border-color); margin: 0 0.5rem; opacity: 0.5;"></div>';
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
                foreach ($item['children'] as $child) {
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
            <?php echo strtoupper(substr($_SESSION['full_name'] ?? $_SESSION['username'] ?? 'U', 0, 1)); ?>
        </div>
        <div style="line-height: 1.2;">
            <p class="text-sm font-medium"><?php echo htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username'] ?? 'User'); ?></p>
            <p class="text-xs text-muted"><?php echo htmlspecialchars($_SESSION['role_name'] ?? 'Role'); ?></p>
        </div>
        <i class="ph-bold ph-caret-down"
            style="font-size: 0.8rem; margin-left: 0.5rem; color: var(--text-secondary);"></i>

        <div class="dropdown-content" style="left: auto; right: 0; min-width: 180px; top: 100%;">
            <a href="<?php echo BASE_URL; ?>modules/profile/index.php" class="dropdown-item">
                <i class="ph ph-user-circle"></i> Mi Perfil
            </a>
            <?php if (can_access_module('settings', $pdo)): ?>
                <a href="<?php echo BASE_URL; ?>modules/settings/index.php" class="dropdown-item">
                    <i class="ph ph-gear"></i> Configuración
                </a>
            <?php endif; ?>
            <div style="height: 1px; background: var(--border-color); margin: 0.25rem 0;"></div>
            <a href="<?php echo BASE_URL; ?>modules/auth/logout.php" class="dropdown-item"
                style="color: var(--danger);">
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
    (function () {
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
        themeToggle.addEventListener('click', function () {
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
        document.addEventListener('mousemove', function (e) {
            checkProximity(e.clientX, e.clientY);
        });

        // Keep button visible when hovering over it
        themeToggle.addEventListener('mouseenter', function () {
            clearTimeout(hideTimeout);
            showButton();
        });

        // Start hide timer when mouse leaves button
        themeToggle.addEventListener('mouseleave', function (e) {
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