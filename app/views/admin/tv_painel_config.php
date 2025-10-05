<div class="max-w-5xl mx-auto bg-white rounded-xl shadow-lg p-8">
    <header class="mb-8">
        <h1 class="text-3xl font-bold text-gray-800 mb-2">Configurações do Painel de TV</h1>
        <p class="text-gray-600">Personalize as cores, o intervalo de atualização e o formato do painel exibido nos monitores.</p>
    </header>

    <form action="<?php echo APP_URL; ?>/admin.php?action=save_tv_panel_config" method="POST" class="space-y-8">
        <section>
            <h2 class="text-xl font-semibold text-gray-700 mb-4">Cores por prazo</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="overdue_color" class="block text-sm font-medium text-gray-700 mb-1">Processos atrasados</label>
                    <input type="text" id="overdue_color" name="overdue_color" value="<?php echo htmlspecialchars($settings['overdue_color']); ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" placeholder="Ex.: bg-red-200 text-red-800" required>
                </div>
                <div>
                    <label for="due_today_color" class="block text-sm font-medium text-gray-700 mb-1">Vencimento hoje</label>
                    <input type="text" id="due_today_color" name="due_today_color" value="<?php echo htmlspecialchars($settings['due_today_color']); ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" required>
                </div>
                <div>
                    <label for="due_soon_color" class="block text-sm font-medium text-gray-700 mb-1">Vence em até 3 dias</label>
                    <input type="text" id="due_soon_color" name="due_soon_color" value="<?php echo htmlspecialchars($settings['due_soon_color']); ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" required>
                </div>
                <div>
                    <label for="on_track_color" class="block text-sm font-medium text-gray-700 mb-1">Prazos confortáveis</label>
                    <input type="text" id="on_track_color" name="on_track_color" value="<?php echo htmlspecialchars($settings['on_track_color']); ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" required>
                </div>
            </div>
        </section>

        <section>
            <h2 class="text-xl font-semibold text-gray-700 mb-4">Atualização automática</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 items-center">
                <div>
                    <label for="refresh_interval" class="block text-sm font-medium text-gray-700 mb-1">Intervalo padrão</label>
                    <select id="refresh_interval" name="refresh_interval" class="w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <?php $options = [60 => '1 minuto', 180 => '3 minutos', 300 => '5 minutos']; ?>
                        <?php foreach ($options as $value => $label): ?>
                            <option value="<?php echo $value; ?>" <?php echo ((int) $settings['refresh_interval'] === $value) ? 'selected' : ''; ?>><?php echo $label; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="color_scheme" class="block text-sm font-medium text-gray-700 mb-1">Tema do painel</label>
                    <select id="color_scheme" name="color_scheme" class="w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <option value="dark" <?php echo ($settings['color_scheme'] === 'dark') ? 'selected' : ''; ?>>Preto (tema escuro)</option>
                        <option value="light" <?php echo ($settings['color_scheme'] === 'light') ? 'selected' : ''; ?>>Branco (tema claro)</option>
                    </select>
                </div>
            </div>
        </section>

        <section>
            <h2 class="text-xl font-semibold text-gray-700 mb-4">Recursos visuais</h2>
            <div class="space-y-4">
                <label class="inline-flex items-center">
                    <input type="checkbox" name="enable_progress_bar" class="h-5 w-5 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500" <?php echo !empty($settings['enable_progress_bar']) ? 'checked' : ''; ?>>
                    <span class="ml-3 text-sm text-gray-700">Exibir barra de progresso por prazo</span>
                </label>
                <label class="inline-flex items-center">
                    <input type="checkbox" name="enable_alert_pulse" class="h-5 w-5 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500" <?php echo !empty($settings['enable_alert_pulse']) ? 'checked' : ''; ?>>
                    <span class="ml-3 text-sm text-gray-700">Ativar destaque animado para prazos críticos</span>
                </label>
            </div>
        </section>

        <div class="flex justify-end space-x-4">
            <a href="<?php echo APP_URL; ?>/admin.php?action=tv_panel" class="px-6 py-3 rounded-lg border border-gray-300 text-gray-700 hover:text-gray-900 hover:border-gray-400">Ver painel</a>
            <button type="submit" class="px-6 py-3 rounded-lg bg-indigo-600 text-white font-semibold hover:bg-indigo-700">Salvar configurações</button>
        </div>
    </form>
</div>
