<?php
// modules/dashboard/index.php

require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

$page_title = 'Dashboard';
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';
?>

<div class="animate-enter">
    <div style="display: flex; justify-content: space-between; align-items: end; margin-bottom: 2rem;">
        <div>
            <h1 style="margin-bottom: 0.5rem;">Dashboard</h1>
            <p class="text-muted">Bienvenido de nuevo, <?php echo htmlspecialchars($_SESSION['username']); ?></p>
        </div>
        <div style="display: flex; gap: 1rem;">
            <button class="btn btn-secondary"><i class="ph ph-calendar"></i> <?php echo date('d M, Y'); ?></button>
            <a href="../equipment/entry.php" class="btn btn-primary"><i class="ph ph-plus"></i> Nueva Entrada</a>
        </div>
    </div>
    
    <!-- Stats Grid -->
    <div class="stats-grid">
        <!-- Card 1 -->
        <div class="card stat-card delay-1">
            <div class="stat-icon" style="background: rgba(99, 102, 241, 0.1); color: var(--primary-500);">
                <i class="ph ph-desktop"></i>
            </div>
            <div>
                <h3 class="stat-value">0</h3>
                <p class="stat-label">Equipos en Reparación</p>
            </div>
        </div>

        <!-- Card 2 -->
        <div class="card stat-card delay-1">
            <div class="stat-icon" style="background: rgba(245, 158, 11, 0.1); color: var(--warning);">
                <i class="ph ph-clock"></i>
            </div>
            <div>
                <h3 class="stat-value">0</h3>
                <p class="stat-label">Pendientes de Diagnóstico</p>
            </div>
        </div>

        <!-- Card 3 -->
        <div class="card stat-card delay-2">
            <div class="stat-icon" style="background: rgba(16, 185, 129, 0.1); color: var(--success);">
                <i class="ph ph-check-circle"></i>
            </div>
            <div>
                <h3 class="stat-value">0</h3>
                <p class="stat-label">Completados Hoy</p>
            </div>
        </div>
        
        <!-- Card 4 -->
        <div class="card stat-card delay-2">
            <div class="stat-icon" style="background: rgba(239, 68, 68, 0.1); color: var(--danger);">
                <i class="ph ph-currency-dollar"></i>
            </div>
            <div>
                <h3 class="stat-value">Q0.00</h3>
                <p class="stat-label">Ingresos (Mes Actual)</p>
            </div>
        </div>
    </div>

    <!-- Main Content Area -->
    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1.5rem;">
        
        <!-- Recent Activity -->
        <div class="card delay-3">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                <h3>Actividad Reciente</h3>
                <a href="#" class="text-sm text-muted" style="text-decoration: none;">Ver todo</a>
            </div>
            
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Equipo</th>
                            <th>Cliente</th>
                            <th>Estado</th>
                            <th>Fecha</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td colspan="4" style="text-align: center; padding: 2rem; color: var(--text-secondary);">
                                <i class="ph ph-clipboard-text" style="font-size: 2rem; margin-bottom: 0.5rem; display: block;"></i>
                                No hay actividad reciente para mostrar.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Quick Actions / Mini Status -->
        <div class="card delay-3">
            <h3 style="margin-bottom: 1.5rem;">Estado de Herramientas</h3>
            
            <div style="display: flex; flex-direction: column; gap: 1rem;">
                <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.75rem; background: var(--bg-hover); border-radius: var(--radius);">
                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                        <div style="width: 8px; height: 8px; border-radius: 50%; background: var(--success);"></div>
                        <span class="text-sm font-medium">Disponibles</span>
                    </div>
                    <span class="font-bold">0</span>
                </div>
                
                <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.75rem; background: var(--bg-hover); border-radius: var(--radius);">
                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                        <div style="width: 8px; height: 8px; border-radius: 50%; background: var(--warning);"></div>
                        <span class="text-sm font-medium">En Uso</span>
                    </div>
                    <span class="font-bold">0</span>
                </div>
                
                <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.75rem; background: var(--bg-hover); border-radius: var(--radius);">
                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                        <div style="width: 8px; height: 8px; border-radius: 50%; background: var(--danger);"></div>
                        <span class="text-sm font-medium">Mantenimiento</span>
                    </div>
                    <span class="font-bold">0</span>
                </div>
            </div>
            
            <button class="btn btn-secondary w-full" style="width: 100%; margin-top: 1.5rem;">Ir a Inventario</button>
        </div>
    </div>
</div>

<?php
require_once '../../includes/footer.php';
?>
