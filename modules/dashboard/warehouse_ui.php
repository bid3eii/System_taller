<style>
    /* Modern Warehouse Dashboard Styles */
    :root {
        --glass-bg: rgba(255, 255, 255, 0.03);
        --glass-border: rgba(255, 255, 255, 0.08);
        --glass-hover: rgba(255, 255, 255, 0.06);
        --neon-blue: #3b82f6;
        --neon-purple: #8b5cf6;
        --neon-green: #10b981;
        --neon-orange: #f59e0b;
    }

    body.light-mode {
        --glass-bg: rgba(0, 0, 0, 0.02);
        --glass-border: rgba(0, 0, 0, 0.06);
        --glass-hover: rgba(0, 0, 0, 0.04);
    }

    .wh-dashboard {
        display: flex;
        flex-direction: column;
        gap: 2rem;
        padding-bottom: 2rem;
    }

    /* Glass Cards */
    .wh-card {
        background: var(--glass-bg);
        border: 1px solid var(--glass-border);
        backdrop-filter: blur(12px);
        -webkit-backdrop-filter: blur(12px);
        border-radius: 20px;
        padding: 1.5rem;
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1), box-shadow 0.3s ease, border-color 0.3s ease;
        overflow: hidden;
        position: relative;
    }

    .wh-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 12px 40px rgba(0, 0, 0, 0.15);
        border-color: rgba(255, 255, 255, 0.15);
        background: var(--glass-hover);
    }

    /* Add a subtle shine effect */
    .wh-card::after {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 50%;
        height: 100%;
        background: linear-gradient(to right, rgba(255, 255, 255, 0) 0%, rgba(255, 255, 255, 0.03) 50%, rgba(255, 255, 255, 0) 100%);
        transform: skewX(-20deg);
        transition: all 0.7s ease;
    }

    .wh-card:hover::after {
        left: 200%;
    }

    /* KPI Cards Specific */
    .wh-kpis {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 1.5rem;
    }

    .wh-kpi-inner {
        display: flex;
        align-items: center;
        gap: 1.5rem;
        position: relative;
        z-index: 1;
    }

    .wh-kpi-icon {
        width: 64px;
        height: 64px;
        border-radius: 16px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 2rem;
        box-shadow: inset 0 0 20px rgba(255, 255, 255, 0.1);
    }

    .wh-kpi-icon.blue {
        background: linear-gradient(135deg, rgba(59, 130, 246, 0.2), rgba(37, 99, 235, 0.05));
        color: var(--neon-blue);
        border: 1px solid rgba(59, 130, 246, 0.2);
    }

    .wh-kpi-icon.orange {
        background: linear-gradient(135deg, rgba(245, 158, 11, 0.2), rgba(217, 119, 6, 0.05));
        color: var(--neon-orange);
        border: 1px solid rgba(245, 158, 11, 0.2);
    }

    .wh-kpi-icon.green {
        background: linear-gradient(135deg, rgba(16, 185, 129, 0.2), rgba(5, 150, 105, 0.05));
        color: var(--neon-green);
        border: 1px solid rgba(16, 185, 129, 0.2);
    }

    .wh-kpi-data {
        display: flex;
        flex-direction: column;
    }

    .wh-kpi-value {
        font-size: 2.5rem;
        font-weight: 800;
        line-height: 1;
        margin-bottom: 0.25rem;
        background: linear-gradient(to right, #fff, #94a3b8);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        font-family: 'Inter', sans-serif;
        letter-spacing: -1px;
    }

    body.light-mode .wh-kpi-value {
        background: linear-gradient(to right, #0f172a, #475569);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }

    .wh-kpi-label {
        font-size: 0.95rem;
        color: var(--text-muted);
        font-weight: 500;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    /* Main Content Grid */
    .wh-grid {
        display: grid;
        grid-template-columns: 2fr 1fr;
        gap: 2rem;
    }

    @media (max-width: 1024px) {
        .wh-grid {
            grid-template-columns: 1fr;
        }
    }

    /* Beautiful Table */
    .wh-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0 8px;
        margin-top: -8px;
    }

    .wh-table th {
        text-align: left;
        padding: 1rem 1.25rem;
        color: var(--text-muted);
        font-weight: 600;
        font-size: 0.85rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        border-bottom: 1px solid var(--glass-border);
    }

    .wh-table td {
        padding: 1.25rem;
        background: var(--glass-bg);
        transition: background 0.2s ease;
    }

    .wh-table tr td:first-child {
        border-top-left-radius: 12px;
        border-bottom-left-radius: 12px;
    }

    .wh-table tr td:last-child {
        border-top-right-radius: 12px;
        border-bottom-right-radius: 12px;
    }

    .wh-table tbody tr:hover td {
        background: var(--glass-hover);
    }

    .wh-badge {
        padding: 0.4rem 0.8rem;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 700;
        letter-spacing: 0.5px;
        display: inline-block;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    }

    .wh-badge.disponible {
        background: rgba(16, 185, 129, 0.15);
        color: var(--neon-green);
        border: 1px solid rgba(16, 185, 129, 0.3);
    }

    .wh-badge.prestado,
    .wh-badge.assigned {
        background: rgba(245, 158, 11, 0.15);
        color: var(--neon-orange);
        border: 1px solid rgba(245, 158, 11, 0.3);
    }

    .wh-badge.mantenimiento {
        background: rgba(139, 92, 246, 0.15);
        color: var(--neon-purple);
        border: 1px solid rgba(139, 92, 246, 0.3);
    }

    /* Quick Actions */
    .wh-action-btn {
        display: flex;
        align-items: center;
        gap: 1rem;
        padding: 1.25rem;
        border-radius: 16px;
        background: linear-gradient(145deg, var(--glass-bg), rgba(255, 255, 255, 0.01));
        border: 1px solid var(--glass-border);
        color: var(--text-main);
        text-decoration: none;
        transition: all 0.3s ease;
        margin-bottom: 1rem;
        position: relative;
        overflow: hidden;
    }

    .wh-action-btn::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.05), transparent);
        transform: translateX(-100%);
        transition: 0.5s;
    }

    .wh-action-btn:hover::before {
        transform: translateX(100%);
    }

    .wh-action-btn:hover {
        border-color: rgba(255, 255, 255, 0.2);
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
    }

    .wh-btn-icon {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
    }

    .wh-action-btn:nth-child(1) .wh-btn-icon {
        background: rgba(59, 130, 246, 0.2);
        color: var(--neon-blue);
    }

    .wh-action-btn:nth-child(2) .wh-btn-icon {
        background: rgba(245, 158, 11, 0.2);
        color: var(--neon-orange);
    }

    .wh-action-text {
        display: flex;
        flex-direction: column;
    }

    .wh-action-title {
        font-weight: 600;
        font-size: 1.1rem;
        margin-bottom: 0.2rem;
    }

    .wh-action-desc {
        font-size: 0.85rem;
        color: var(--text-muted);
    }

    /* Elegant Section Titles */
    .wh-section-title {
        font-size: 1.25rem;
        font-weight: 700;
        margin-bottom: 1.5rem;
        display: flex;
        align-items: center;
        gap: 0.75rem;
        color: var(--text-main);
    }

    .wh-section-title i {
        color: var(--neon-blue);
        background: rgba(59, 130, 246, 0.1);
        padding: 8px;
        border-radius: 10px;
    }

    /* Chart Container */
    .wh-chart-container {
        height: 320px;
        width: 100%;
        position: relative;
        padding: 1rem 0;
    }
</style>

<div class="wh-dashboard">

    <!-- Premium KPIs -->
    <div class="wh-kpis">
        <a href="../equipment/entry.php?type=warranty" class="wh-card" style="text-decoration: none;">
            <div class="wh-kpi-inner">
                <div class="wh-kpi-icon blue">
                    <i class="ph <?php echo $kpi1_icon; ?>"></i>
                </div>
                <div class="wh-kpi-data">
                    <span class="wh-kpi-value">
                        <?php echo $kpi1_val; ?>
                    </span>
                    <span class="wh-kpi-label"><?php echo $kpi1_label; ?></span>
                </div>
            </div>
        </a>

        <a href="../warranties/database.php" class="wh-card" style="text-decoration: none;">
            <div class="wh-kpi-inner">
                <div class="wh-kpi-icon green">
                    <i class="ph <?php echo $kpi2_icon; ?>"></i>
                </div>
                <div class="wh-kpi-data">
                    <span class="wh-kpi-value">
                        <?php echo $kpi2_val; ?>
                    </span>
                    <span class="wh-kpi-label"><?php echo $kpi2_label; ?></span>
                </div>
            </div>
        </a>

        <a href="../warranties/database.php" class="wh-card" style="text-decoration: none;">
            <div class="wh-kpi-inner">
                <div class="wh-kpi-icon orange">
                    <i class="ph <?php echo $kpi3_icon; ?>"></i>
                </div>
                <div class="wh-kpi-data">
                    <span class="wh-kpi-value">
                        <?php echo $kpi3_val; ?>
                    </span>
                    <span class="wh-kpi-label"><?php echo $kpi3_label; ?></span>
                </div>
            </div>
        </a>
    </div>

    <!-- Main Grid -->
    <div class="wh-grid">

        <!-- Left Column: Activity List -->
        <div class="wh-card" style="padding: 2rem;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                <h3 class="wh-section-title" style="margin: 0;">
                    <i class="ph-fill ph-clock-counter-clockwise"></i>
                    Últimos Ingresos
                </h3>
                <a href="../warranties/database.php" class="btn btn-sm btn-secondary"
                    style="border-radius: 20px; padding: 0.4rem 1rem;">Ver Todo</a>
            </div>

            <div style="overflow-x: auto;">
                <table class="wh-table">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Cliente</th>
                            <th>Equipo / Serie</th>
                            <th>Cód. Producto</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($recentItems) > 0): ?>
                            <?php foreach ($recentItems as $item): ?>
                                <tr>
                                    <td style="color: var(--text-muted); font-weight: 500;">
                                        <?php echo date('d/m  H:i', strtotime($item['date'])); ?>
                                    </td>
                                    <td>
                                        <div style="display: flex; align-items: center; gap: 0.75rem;">
                                            <div
                                                style="width: 32px; height: 32px; border-radius: 50%; background: linear-gradient(135deg, rgba(255,255,255,0.1), rgba(255,255,255,0)); display: flex; align-items: center; justify-content: center; border: 1px solid rgba(255,255,255,0.05);">
                                                <i class="ph ph-user" style="color: var(--text-secondary);"></i>
                                            </div>
                                            <span style="font-weight: 600; color: var(--text-main);">
                                                <?php echo htmlspecialchars($item['client_name']); ?>
                                            </span>
                                        </div>
                                    </td>
                                    <td>
                                        <div style="font-weight: 500;">
                                            <?php echo htmlspecialchars($item['brand'] . ' ' . $item['model']); ?></div>
                                        <div style="font-size: 0.8rem; color: var(--text-muted);">
                                            <?php echo htmlspecialchars($item['serial_number']); ?></div>
                                    </td>
                                    <td>
                                        <span class="wh-badge"
                                            style="background: rgba(59, 130, 246, 0.15); color: var(--neon-blue); border: 1px solid rgba(59, 130, 246, 0.3);">
                                            <?php echo htmlspecialchars($item['product_code']); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" style="text-align: center; padding: 3rem; color: var(--text-muted);">
                                    <i class="ph ph-file-dashed"
                                        style="font-size: 3rem; opacity: 0.5; margin-bottom: 1rem; display: block;"></i>
                                    No hay ingresos recientes en bodega.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Right Column: Chart & Actions -->
        <div style="display: flex; flex-direction: column; gap: 2rem;">

            <div class="wh-card" style="padding: 2rem;">
                <h3 class="wh-section-title">
                    <i class="ph-fill ph-chart-pie-slice"></i>
                    Estado General
                </h3>
                <div class="wh-chart-container">
                    <canvas id="whStatusChart"></canvas>
                </div>
            </div>

            <div class="wh-card" style="padding: 2rem;">
                <h3 class="wh-section-title">
                    <i class="ph-fill ph-lightning"></i>
                    Accesos Rápidos
                </h3>
                <div style="margin-top: 1rem;">
                    <a href="../equipment/entry.php?type=warranty" class="wh-action-btn">
                        <div class="wh-btn-icon"><i class="ph ph-plus-circle"></i></div>
                        <div class="wh-action-text">
                            <span class="wh-action-title">Nuevo Registro</span>
                            <span class="wh-action-desc">Registrar partes en bodega</span>
                        </div>
                    </a>

                    <a href="../warranties/database.php" class="wh-action-btn">
                        <div class="wh-btn-icon"><i class="ph ph-database"></i></div>
                        <div class="wh-action-text">
                            <span class="wh-action-title">Ver Registros</span>
                            <span class="wh-action-desc">Consultar base de datos histórica</span>
                        </div>
                    </a>
                </div>
            </div>

        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const isLightModewh = document.body.classList.contains('light-mode');
        const textColwh = isLightModewh ? '#475569' : '#cbd5e1';

        const ctxWhStatus = document.getElementById('whStatusChart').getContext('2d');

        // Gradient definitions
        const gradientBlue = ctxWhStatus.createLinearGradient(0, 0, 0, 400);
        gradientBlue.addColorStop(0, '#60a5fa');
        gradientBlue.addColorStop(1, '#2563eb');

        const gradientOrange = ctxWhStatus.createLinearGradient(0, 0, 0, 400);
        gradientOrange.addColorStop(0, '#fbbf24');
        gradientOrange.addColorStop(1, '#d97706');

        const gradientPurple = ctxWhStatus.createLinearGradient(0, 0, 0, 400);
        gradientPurple.addColorStop(0, '#a78bfa');
        gradientPurple.addColorStop(1, '#7c3aed');

        const gradientGreen = ctxWhStatus.createLinearGradient(0, 0, 0, 400);
        gradientGreen.addColorStop(0, '#34d399');
        gradientGreen.addColorStop(1, '#059669');
        
        const gradientGray = ctxWhStatus.createLinearGradient(0, 0, 0, 400);
        gradientGray.addColorStop(0, '#94a3b8');
        gradientGray.addColorStop(1, '#475569');

        new Chart(ctxWhStatus, {
            type: 'doughnut',
            data: {
                labels: ['Vigentes', 'Expiradas'],
                datasets: [{
                    data: [<?php echo $chartData['active']; ?>, <?php echo $chartData['expired']; ?>],
                    backgroundColor: [
                        gradientGreen,    // Vigente
                        gradientGray      // Expirada
                    ],
                    borderWidth: 0,
                    hoverOffset: 8,
                    borderRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            color: textColwh,
                            padding: 20,
                            font: {
                                family: "'Inter', sans-serif",
                                size: 13,
                                weight: '500'
                            },
                            usePointStyle: true,
                            pointStyle: 'circle'
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0,0,0,0.8)',
                        titleFont: { size: 14, family: "'Inter', sans-serif" },
                        bodyFont: { size: 14, family: "'Inter', sans-serif", weight: 'bold' },
                        padding: 12,
                        cornerRadius: 8,
                        displayColors: true
                    }
                },
                cutout: '75%',
                animation: {
                    animateScale: true,
                    animateRotate: true
                }
            }
        });
    });
</script>