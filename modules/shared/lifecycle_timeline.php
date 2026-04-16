<?php
/**
 * modules/shared/lifecycle_timeline.php
 * Renders a unified chronological timeline for a specific survey/project.
 */

function render_lifecycle_timeline($pdo, $survey_id) {
    if (!$survey_id) return '';

    // 1. Fetch the Survey/Project base info
    $stmtS = $pdo->prepare("SELECT * FROM project_surveys WHERE id = ?");
    $stmtS->execute([$survey_id]);
    $survey = $stmtS->fetch();
    if (!$survey) return 'Levantamiento no encontrado.';

    // 2. Aggregate all timeline events
    $events = [];

    // Hito 1: Creación del Levantamiento
    $events[] = [
        'date' => $survey['created_at'],
        'type' => 'document',
        'icon' => 'ph-file-text',
        'color' => '#6366f1',
        'title' => 'Levantamiento Creado',
        'desc' => "Iniciado por el sistema para client: " . $survey['client_name']
    ];

    // Hito 2: Visitas agendadas
    $stmtV = $pdo->prepare("SELECT * FROM schedule_events WHERE survey_id = ? ORDER BY start_datetime ASC");
    $stmtV->execute([$survey_id]);
    while ($v = $stmtV->fetch()) {
        $events[] = [
            'date' => $v['start_datetime'],
            'type' => 'event',
            'icon' => 'ph-calendar-check',
            'color' => '#10b981',
            'title' => 'Visita Técnica: ' . $v['title'],
            'desc' => ($v['status'] == 'completed' ? 'Realizada' : 'Programada') . ' en ' . ($v['location'] ?: 'Sitio')
        ];
    }

    // Hito 3: Viáticos
    $stmtEx = $pdo->prepare("SELECT * FROM viaticos WHERE survey_id = ? ORDER BY date ASC");
    $stmtEx->execute([$survey_id]);
    while ($ex = $stmtEx->fetch()) {
        $events[] = [
            'date' => $ex['date'] . ' 00:00:00',
            'type' => 'expense',
            'icon' => 'ph-money',
            'color' => '#f59e0b',
            'title' => 'Gasto de Viáticos',
            'desc' => "Reporte #" . str_pad($ex['id'], 4, '0', STR_PAD_LEFT) . " por $" . number_format($ex['total_amount'], 2)
        ];
    }

    // Sort all events by date
    usort($events, function($a, $b) {
        return strtotime($a['date']) - strtotime($b['date']);
    });

    ob_start();
    ?>
    <div class="timeline-container" style="padding: 1rem; position: relative; margin-top: 1rem;">
        <h3 style="margin-bottom: 2rem; display: flex; align-items: center; gap: 0.5rem;">
            <i class="ph-fill ph-clock-counter-clockwise" style="color: var(--primary);"></i>
            Línea de Vida del Proyecto
        </h3>

        <div style="position: relative; padding-left: 2rem; border-left: 2px dashed var(--border-color); margin-left: 1rem;">
            <?php foreach ($events as $index => $e): ?>
                <div class="timeline-item" style="position: relative; margin-bottom: 2.5rem;">
                    <!-- Circle Icon -->
                    <div style="position: absolute; left: -2.85rem; top: 0; width: 32px; height: 32px; background: var(--bg-card); border: 2px solid <?php echo $e['color']; ?>; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: <?php echo $e['color']; ?>; z-index: 2; box-shadow: 0 0 0 4px var(--bg-body);">
                        <i class="ph <?php echo $e['icon']; ?>" style="font-size: 1.1rem;"></i>
                    </div>

                    <!-- Content -->
                    <div class="card" style="padding: 1rem; border-radius: 12px; border-left: 4px solid <?php echo $e['color']; ?>; background: rgba(var(--primary-rgb), 0.02);">
                        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.25rem;">
                            <h4 style="margin: 0; font-size: 1rem; color: var(--text-primary);"><?php echo $e['title']; ?></h4>
                            <span style="font-size: 0.75rem; font-weight: bold; background: var(--bg-hover); padding: 0.2rem 0.5rem; border-radius: 4px; color: var(--text-muted);">
                                <?php echo date('d M, Y H:i', strtotime($e['date'])); ?>
                            </span>
                        </div>
                        <p style="margin: 0; font-size: 0.85rem; color: var(--text-secondary);"><?php echo $e['desc']; ?></p>
                    </div>
                </div>
            <?php endforeach; ?>

            <?php if ($survey['status'] == 'completed'): ?>
                <!-- Final Hito -->
                <div class="timeline-item" style="position: relative; margin-bottom: 0;">
                    <div style="position: absolute; left: -2.85rem; top: 0; width: 32px; height: 32px; background: #10b981; color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; z-index: 2; box-shadow: 0 0 20px rgba(16, 185, 129, 0.4);">
                        <i class="ph-fill ph-check-circle" style="font-size: 1.2rem;"></i>
                    </div>
                    <div style="padding: 1rem;">
                        <h4 style="margin: 0; color: #10b981; font-weight: 700;">PROYECTO CULMINADO</h4>
                        <p style="margin: 0; font-size: 0.85rem; color: var(--text-muted);">Entrega final realizada con éxito.</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
