<style>
    /* Premium Warehouse Dashboard Aesthetics */
    .wh-premium-card {
        background: var(--bg-card);
        border: 1px solid var(--border-color);
        border-radius: 20px;
        position: relative;
        overflow: hidden;
        transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
    }
    .wh-premium-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 12px 30px rgba(0, 0, 0, 0.1);
        border-color: rgba(255, 255, 255, 0.1);
    }
    .wh-premium-card::before {
        content: '';
        position: absolute;
        top: 0; left: -100%;
        width: 50%; height: 100%;
        background: linear-gradient(to right, transparent, rgba(255,255,255,0.03), transparent);
        transform: skewX(-25deg);
        transition: 0.7s;
    }
    .wh-premium-card:hover::before {
        left: 200%;
    }

    .wh-stat-icon {
        width: 56px; height: 56px;
        border-radius: 16px;
        display: flex; align-items: center; justify-content: center;
        font-size: 1.75rem;
        position: relative;
    }
    .wh-stat-icon::after {
        content: '';
        position: absolute;
        inset: 0;
        border-radius: inherit;
        filter: blur(10px);
        opacity: 0.5;
        z-index: -1;
    }
    
    .bg-gradient-blue { background: linear-gradient(135deg, rgba(59, 130, 246, 0.2), rgba(37, 99, 235, 0.1)); color: #3b82f6; }
    .bg-gradient-blue::after { background: #3b82f6; }
    
    .bg-gradient-green { background: linear-gradient(135deg, rgba(16, 185, 129, 0.2), rgba(5, 150, 105, 0.1)); color: #10b981; }
    .bg-gradient-green::after { background: #10b981; }
    
    .bg-gradient-orange { background: linear-gradient(135deg, rgba(245, 158, 11, 0.2), rgba(217, 119, 6, 0.1)); color: #f59e0b; }
    .bg-gradient-orange::after { background: #f59e0b; }

    .wh-table-row {
        transition: all 0.2s ease;
        border-bottom: 1px solid var(--border-color);
    }
    .wh-table-row:hover {
        background: rgba(255, 255, 255, 0.02);
        transform: scale(1.01);
    }
    .wh-table-row td {
        padding: 1rem 0.75rem;
    }
    .wh-avatar {
        width: 36px; height: 36px;
        border-radius: 10px;
        background: linear-gradient(135deg, var(--bg-card), rgba(255,255,255,0.05));
        display: flex; align-items: center; justify-content: center;
        border: 1px solid var(--border-color);
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }

    .wh-badge-premium {
        padding: 0.35rem 0.8rem;
        border-radius: 20px;
        font-weight: 700;
        font-size: 0.75rem;
        letter-spacing: 0.5px;
        background: rgba(255, 255, 255, 0.05);
        border: 1px solid rgba(255, 255, 255, 0.1);
        color: var(--text-primary);
        box-shadow: inset 0 1px 1px rgba(255,255,255,0.1);
    }

    .action-card {
        display: flex;
        align-items: center;
        gap: 1.25rem;
        padding: 1.25rem;
        border-radius: 16px;
        background: var(--bg-card);
        border: 1px solid var(--border-color);
        text-decoration: none;
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
    }
    .action-card:hover {
        border-color: var(--primary);
        box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        transform: translateY(-2px);
    }
    .action-card .icon-box {
        width: 48px; height: 48px;
        border-radius: 12px;
        display: flex; align-items: center; justify-content: center;
        font-size: 1.5rem;
        transition: 0.3s ease;
    }
    .action-card:hover .icon-box {
        transform: scale(1.1) rotate(5deg);
    }
    .text-gradient {
        background: linear-gradient(to right, #fff, #94a3b8);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }
</style>

<div class="animate-enter" style="display: flex; flex-direction: column; gap: 2rem;">

    <!-- TOP PREMIUN KPIS -->
    <div class="stats-grid">
        <a href="../equipment/entry.php?type=warranty" class="card stat-card wh-premium-card" style="text-decoration: none;">
            <div class="wh-stat-icon bg-gradient-blue" style="margin-bottom: 1rem;">
                <i class="ph <?php echo $kpi1_icon; ?>"></i>
            </div>
            <div style="font-size: 2rem; font-weight: 800; color: var(--text-primary); line-height: 1;">
                <?php echo $kpi1_val; ?>
            </div>
            <div style="color: var(--text-secondary); font-size: 0.95rem; margin-top: 0.5rem; font-weight: 500;">
                <?php echo $kpi1_label; ?>
            </div>
        </a>

        <a href="../warranties/database.php?tab=stock" class="card stat-card wh-premium-card" style="text-decoration: none;">
            <div class="wh-stat-icon bg-gradient-green" style="margin-bottom: 1rem;">
                <i class="ph <?php echo $kpi2_icon; ?>"></i>
            </div>
            <div style="font-size: 2rem; font-weight: 800; color: var(--text-primary); line-height: 1;">
                <?php echo $kpi2_val; ?>
            </div>
            <div style="color: var(--text-secondary); font-size: 0.95rem; margin-top: 0.5rem; font-weight: 500;">
                <?php echo $kpi2_label; ?>
            </div>
        </a>

        <a href="../warranties/database.php?tab=sold" class="card stat-card wh-premium-card" style="text-decoration: none;">
            <div class="wh-stat-icon bg-gradient-orange" style="margin-bottom: 1rem;">
                <i class="ph <?php echo $kpi3_icon; ?>"></i>
            </div>
            <div style="font-size: 2rem; font-weight: 800; color: var(--text-primary); line-height: 1;">
                <?php echo $kpi3_val; ?>
            </div>
            <div style="color: var(--text-secondary); font-size: 0.95rem; margin-top: 0.5rem; font-weight: 500;">
                <?php echo $kpi3_label; ?>
            </div>
        </a>
    </div>

    <!-- MAIN DASHBOARD CONTENT -->
    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 2rem;">

        <!-- Left Column: Activity List -->
        <div class="wh-premium-card" style="padding: 1.5rem;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; padding-bottom: 1rem; border-bottom: 1px solid var(--border-color);">
                <h3 style="margin: 0; display: flex; align-items: center; gap: 0.6rem; font-size: 1.3rem;">
                    <div style="width: 32px; height: 32px; border-radius: 8px; background: rgba(59, 130, 246, 0.1); display: flex; align-items: center; justify-content: center;">
                        <i class="ph-fill ph-clock-counter-clockwise" style="color: #3b82f6;"></i>
                    </div>
                    Últimos Ingresos
                </h3>
                <a href="../warranties/database.php" class="btn btn-sm btn-secondary" style="border-radius: 20px; padding: 0.4rem 1.2rem; font-weight: 600;">Administrar Todo</a>
            </div>

            <div class="table-container" style="overflow-x: auto; margin: -0.5rem;">
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr>
                            <th style="padding: 0.75rem 1rem; text-align: left; color: var(--text-secondary); font-size: 0.8rem; text-transform: uppercase; letter-spacing: 1px;">Fecha</th>
                            <th style="padding: 0.75rem 1rem; text-align: left; color: var(--text-secondary); font-size: 0.8rem; text-transform: uppercase; letter-spacing: 1px;">Cliente</th>
                            <th style="padding: 0.75rem 1rem; text-align: left; color: var(--text-secondary); font-size: 0.8rem; text-transform: uppercase; letter-spacing: 1px;">Equipo / Serie</th>
                            <th style="padding: 0.75rem 1rem; text-align: left; color: var(--text-secondary); font-size: 0.8rem; text-transform: uppercase; letter-spacing: 1px;">Cód. Producto</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($recentItems) > 0): ?>
                            <?php foreach ($recentItems as $item): ?>
                                <tr class="wh-table-row">
                                    <td style="color: var(--text-secondary); font-weight: 500; font-size: 0.9rem; padding-left: 1rem;">
                                        <?php echo date('d/m  H:i', strtotime($item['date'])); ?>
                                    </td>
                                    <td>
                                        <div style="display: flex; align-items: center; gap: 0.85rem;">
                                            <div class="wh-avatar">
                                                <i class="ph-fill ph-user" style="color: var(--primary);"></i>
                                            </div>
                                            <span style="font-weight: 600; color: var(--text-primary); font-size: 0.95rem;">
                                                <?php echo htmlspecialchars($item['client_name']); ?>
                                            </span>
                                        </div>
                                    </td>
                                    <td>
                                        <div style="font-weight: 600; color: var(--text-primary); margin-bottom: 0.2rem;">
                                            <?php echo htmlspecialchars($item['brand'] . ' ' . $item['model']); ?>
                                        </div>
                                        <div style="font-size: 0.8rem; color: var(--text-secondary); font-family: monospace; letter-spacing: 0.5px;">
                                            S/N: <?php echo htmlspecialchars($item['serial_number']); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="wh-badge-premium">
                                            <i class="ph ph-barcode" style="margin-right: 4px; vertical-align: -1px;"></i>
                                            <?php echo htmlspecialchars($item['product_code']); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" style="text-align: center; padding: 4rem; color: var(--text-secondary);">
                                    <i class="ph-duotone ph-package" style="font-size: 4rem; opacity: 0.3; margin-bottom: 1rem; display: block;"></i>
                                    <span style="font-size: 1.1rem;">No hay ingresos recientes en bodega.</span>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Right Column: Chart & Actions -->
        <div style="display: flex; flex-direction: column; gap: 2rem;">

            <div class="wh-premium-card" style="padding: 1.5rem;">
                <h3 style="margin: 0 0 1.5rem 0; display: flex; align-items: center; gap: 0.6rem; font-size: 1.2rem;">
                    <i class="ph-fill ph-chart-pie-slice" style="color: #10b981;"></i>
                    Estado General
                </h3>
                <div style="height: 280px; width: 100%; position: relative;">
                    <canvas id="whStatusChart"></canvas>
                </div>
            </div>

            <div class="wh-premium-card" style="padding: 1.5rem;">
                <h3 style="margin: 0 0 1.5rem 0; display: flex; align-items: center; gap: 0.6rem; font-size: 1.2rem;">
                    <i class="ph-fill ph-lightning" style="color: #f59e0b;"></i>
                    Accesos Rápidos
                </h3>
                <div style="display: flex; flex-direction: column; gap: 1rem;">
                    
                    <a href="../equipment/entry.php?type=warranty" class="action-card">
                        <div class="icon-box" style="background: rgba(59, 130, 246, 0.15); color: #3b82f6; box-shadow: inset 0 0 10px rgba(59,130,246,0.1);">
                            <i class="ph-fill ph-package"></i>
                        </div>
                        <div style="text-align: left;">
                            <div style="font-weight: 700; color: var(--text-primary); font-size: 1.1rem; margin-bottom: 0.2rem;">Nuevo Ingreso</div>
                            <div class="text-sm" style="color: var(--text-secondary);">Registrar nuevas partes en bodega</div>
                        </div>
                        <i class="ph ph-caret-right" style="margin-left: auto; color: var(--text-secondary); font-size: 1.2rem;"></i>
                    </a>

                    <a href="../warranties/database.php" class="action-card">
                        <div class="icon-box" style="background: rgba(16, 185, 129, 0.15); color: #10b981; box-shadow: inset 0 0 10px rgba(16,185,129,0.1);">
                            <i class="ph-fill ph-database"></i>
                        </div>
                        <div style="text-align: left;">
                            <div style="font-weight: 700; color: var(--text-primary); font-size: 1.1rem; margin-bottom: 0.2rem;">Base de Datos</div>
                            <div class="text-sm" style="color: var(--text-secondary);">Explorar el histórico completo</div>
                        </div>
                        <i class="ph ph-caret-right" style="margin-left: auto; color: var(--text-secondary); font-size: 1.2rem;"></i>
                    </a>

                </div>
            </div>

        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const isLightModewh = document.body.classList.contains('light-mode');
        const textColwh = isLightModewh ? '#475569' : '#94a3b8';

        const ctxWhStatus = document.getElementById('whStatusChart').getContext('2d');

        // Stunning modern gradients
        const gradientGreen = ctxWhStatus.createLinearGradient(0, 0, 0, 400);
        gradientGreen.addColorStop(0, '#34d399');
        gradientGreen.addColorStop(1, '#059669');

        const gradientGray = ctxWhStatus.createLinearGradient(0, 0, 0, 400);
        gradientGray.addColorStop(0, '#94a3b8');
        gradientGray.addColorStop(1, '#334155');

        new Chart(ctxWhStatus, {
            type: 'doughnut',
            data: {
                labels: ['Vigentes', 'Expiradas'],
                datasets: [{
                    data: [<?php echo $chartData['active']; ?>, <?php echo $chartData['expired']; ?>],
                    backgroundColor: [
                        gradientGreen,    
                        gradientGray      
                    ],
                    borderWidth: 0,
                    hoverOffset: 12,
                    borderRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                layout: {
                    padding: { bottom: 20 }
                },
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            color: textColwh,
                            padding: 25,
                            font: {
                                family: "'Inter', sans-serif",
                                size: 13,
                                weight: '600'
                            },
                            usePointStyle: true,
                            pointStyle: 'circle'
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(15, 23, 42, 0.9)',
                        titleFont: { size: 14, family: "'Inter', sans-serif" },
                        bodyFont: { size: 15, family: "'Inter', sans-serif", weight: 'bold' },
                        padding: 16,
                        cornerRadius: 12,
                        boxPadding: 8,
                        borderColor: 'rgba(255,255,255,0.1)',
                        borderWidth: 1
                    }
                },
                cutout: '78%',
                animation: {
                    animateScale: true,
                    animateRotate: true,
                    duration: 1500,
                    easing: 'easeOutQuart'
                }
            }
        });
    });
</script>
