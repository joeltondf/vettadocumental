<?php
$orientationClass = $orientationClass ?? 'tv-panel-portrait';
$refreshInterval = $settings['refresh_interval'] ?? 60;
$progressEnabled = !empty($settings['enable_progress_bar']);
$alertEnabled = !empty($settings['enable_alert_pulse']);
$processesForPartial = $processes ?? [];
$tvEndpoint = APP_URL . '/admin.php?action=tv_panel_data';
$colorConfig = [
    'overdue' => $settings['overdue_color'] ?? 'bg-red-200 text-red-800',
    'due_today' => $settings['due_today_color'] ?? 'bg-red-200 text-red-800',
    'due_soon' => $settings['due_soon_color'] ?? 'bg-yellow-200 text-yellow-800',
    'on_track' => $settings['on_track_color'] ?? 'text-green-600',
];
?>
<div class="tv-panel-container <?php echo htmlspecialchars($orientationClass); ?>" data-tv-panel
     data-endpoint="<?php echo htmlspecialchars($tvEndpoint); ?>"
     data-refresh-interval="<?php echo (int) $refreshInterval; ?>"
     data-progress-enabled="<?php echo $progressEnabled ? '1' : '0'; ?>"
     data-alert-enabled="<?php echo $alertEnabled ? '1' : '0'; ?>">
    <header class="tv-panel-header">
        <div class="tv-panel-meta">
            <div class="tv-panel-clock" data-tv-clock></div>
            <a href="<?php echo APP_URL; ?>/admin.php?action=tv_panel_config" class="tv-panel-config-link">
                <i class="fas fa-sliders-h mr-2"></i>Configurações
            </a>
        </div>
    </header>

    <section class="tv-panel-table-wrapper">
        <?php if (empty($processesForPartial)): ?>
            <div class="tv-panel-empty">Nenhum processo disponível no momento.</div>
        <?php else: ?>
            <?php
                $processes = $processesForPartial;
                $showActions = false;
                $showProgress = $progressEnabled;
                $highlightAnimations = $alertEnabled;
                $deadlineColors = $colorConfig;
                $allowLinks = false;
                require __DIR__ . '/../dashboard/partials/process_table.php';
            ?>
        <?php endif; ?>
    </section>

    <footer class="tv-panel-footer">
        <div>
            <span class="font-semibold">Atualização automática:</span> a cada <span data-tv-interval><?php echo (int) ($refreshInterval / 60); ?></span> minuto(s)
        </div>
        <div>
            <span class="font-semibold">Última atualização:</span> <span data-tv-last-update>Carregando...</span>
        </div>
        <div>
            <span class="font-semibold">Total de processos:</span> <span data-tv-total><?php echo count($processesForPartial ?? []); ?></span>
        </div>
    </footer>
</div>

<script src="<?php echo APP_URL; ?>/assets/js/tv-panel.js"></script>
