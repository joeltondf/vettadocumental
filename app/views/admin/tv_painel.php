<?php
$refreshInterval = $settings['refresh_interval'] ?? 60;
$alertEnabled = !empty($settings['enable_alert_pulse']);
$tvEndpoint = APP_URL . '/admin.php?action=tv_panel_data';
$colorConfig = [
    'overdue' => $settings['overdue_color'] ?? 'bg-red-200 text-red-800',
    'due_today' => $settings['due_today_color'] ?? 'bg-red-200 text-red-800',
    'due_soon' => $settings['due_soon_color'] ?? 'bg-yellow-200 text-yellow-800',
    'on_track' => $settings['on_track_color'] ?? 'text-green-600',
];
$theme = $theme ?? ($settings['color_scheme'] ?? 'dark');
$themeClass = $theme === 'light' ? 'tv-panel-theme-light' : 'tv-panel-theme-dark';
$tableThemeClass = $theme === 'light' ? 'divide-y divide-gray-200 tv-panel-table' : 'divide-y divide-slate-800 tv-panel-table';
$theadThemeClass = $theme === 'light' ? 'bg-gray-50 text-gray-600' : 'tv-panel-thead-dark text-slate-200';
$tbodyThemeClass = $theme === 'light' ? 'bg-white divide-y divide-gray-200' : 'divide-y divide-slate-800 tv-panel-tbody-dark';
$showRowStatusColors = $theme === 'light';
$panelsList = $panels ?? [];
if (empty($panelsList) && isset($processes) && is_array($processes)) {
    $panelsList = [
        [
            'id' => 'panel_processes',
            'title' => 'Processos Ativos',
            'statuses' => [],
            'processes' => $processes,
        ],
    ];
}
?>
<div class="tv-panel-wrapper <?php echo htmlspecialchars($themeClass); ?>">
    <div class="tv-panel-grid">
        <?php foreach ($panelsList as $panelItem): ?>
            <?php
                $panelId = $panelItem['id'] ?? ('panel_' . uniqid());
                $panelTitle = $panelItem['title'] ?? 'Painel';
                $panelStatuses = $panelItem['statuses'] ?? [];
                $panelProcesses = $panelItem['processes'] ?? [];
                $panelEndpoint = $tvEndpoint . '&panel_id=' . urlencode($panelId);
                $processes = $panelProcesses;
                $showActions = false;
                $highlightAnimations = $alertEnabled;
                $deadlineColors = $colorConfig;
                $allowLinks = false;
                $tableBodyId = 'processes-table-body-' . $panelId;
            ?>
            <div class="tv-panel-container" data-tv-panel
                 data-panel-id="<?php echo htmlspecialchars($panelId); ?>"
                 data-endpoint="<?php echo htmlspecialchars($panelEndpoint); ?>"
                 data-refresh-interval="<?php echo (int) $refreshInterval; ?>"
                 data-alert-enabled="<?php echo $alertEnabled ? '1' : '0'; ?>">
                <header class="tv-panel-header">
                    <div>
                        <h2 class="tv-panel-title"><?php echo htmlspecialchars($panelTitle); ?></h2>
                        <div class="tv-panel-statuses">
                            <?php if (!empty($panelStatuses)): ?>
                                <?php foreach ($panelStatuses as $statusLabel): ?>
                                    <span class="tv-panel-status-pill"><?php echo htmlspecialchars($statusLabel); ?></span>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <span class="tv-panel-status-pill tv-panel-status-pill-muted">Todos os processos ativos</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <a href="<?php echo APP_URL; ?>/admin.php?action=tv_panel_config" class="tv-panel-config-link">
                        <i class="fas fa-sliders-h mr-2"></i>Configurações
                    </a>
                </header>

                <section class="tv-panel-table-wrapper">
                    <?php if (empty($panelProcesses)): ?>
                        <div class="tv-panel-empty">Nenhum processo disponível no momento.</div>
                    <?php else: ?>
                        <?php
                            require __DIR__ . '/../dashboard/partials/process_table.php';
                        ?>
                    <?php endif; ?>
                </section>

                <footer class="tv-panel-footer">
                    <div class="tv-panel-footer-info">
                        <div>
                            <span class="font-semibold">Atualização automática:</span> a cada <span data-tv-interval><?php echo (int) ($refreshInterval / 60); ?></span> minuto(s)
                        </div>
                        <div>
                            <span class="font-semibold">Última atualização:</span> <span data-tv-last-update>Carregando...</span>
                        </div>
                        <div>
                            <span class="font-semibold">Total de processos:</span> <span data-tv-total><?php echo count($panelProcesses ?? []); ?></span>
                        </div>
                    </div>
                    <div class="tv-panel-meta-inline">
                        <div class="tv-panel-clock" data-tv-clock></div>
                    </div>
                </footer>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<script src="<?php echo APP_URL; ?>/assets/js/tv-panel.js"></script>
