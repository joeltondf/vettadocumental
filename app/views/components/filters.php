<?php
/**
 * Componente de filtros reutilizável para os painéis financeiros.
 * Espera as variáveis:
 * - $action (string): rota de envio do formulário.
 * - $filters (array): valores atuais dos filtros.
 * - $vendedores, $sdrs (arrays): listas para preencher selects.
 * - $statusFinanceiroOptions (array): opções de status financeiro.
 */

$action = $action ?? '';
$filters = $filters ?? [];
$vendedores = $vendedores ?? [];
$sdrs = $sdrs ?? [];
$statusFinanceiroOptions = $statusFinanceiroOptions ?? [];
?>

<form class="grid gap-4 md:grid-cols-2 lg:grid-cols-5 bg-white p-4 rounded-lg shadow" method="get" action="<?= htmlspecialchars($action, ENT_QUOTES, 'UTF-8'); ?>">
    <div>
        <label for="start_date" class="block text-sm font-medium text-gray-700">Data inicial</label>
        <input type="date" name="start_date" id="start_date" value="<?= htmlspecialchars($filters['start_date'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-theme-color focus:ring-theme-color">
    </div>
    <div>
        <label for="end_date" class="block text-sm font-medium text-gray-700">Data final</label>
        <input type="date" name="end_date" id="end_date" value="<?= htmlspecialchars($filters['end_date'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-theme-color focus:ring-theme-color">
    </div>
    <div>
        <label for="vendedor_id" class="block text-sm font-medium text-gray-700">Vendedor</label>
        <select name="vendedor_id" id="vendedor_id" class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-theme-color focus:ring-theme-color">
            <option value="">Todos</option>
            <?php foreach ($vendedores as $vendedor): ?>
                <option value="<?= (int) $vendedor['id']; ?>" <?= isset($filters['vendedor_id']) && (int) $filters['vendedor_id'] === (int) $vendedor['id'] ? 'selected' : ''; ?>>
                    <?= htmlspecialchars($vendedor['nome_vendedor'] ?? $vendedor['nome_completo'] ?? 'Vendedor', ENT_QUOTES, 'UTF-8'); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div>
        <label for="sdr_id" class="block text-sm font-medium text-gray-700">SDR</label>
        <select name="sdr_id" id="sdr_id" class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-theme-color focus:ring-theme-color">
            <option value="">Todos</option>
            <?php foreach ($sdrs as $sdr): ?>
                <option value="<?= (int) $sdr['id']; ?>" <?= isset($filters['sdr_id']) && (int) $filters['sdr_id'] === (int) $sdr['id'] ? 'selected' : ''; ?>>
                    <?= htmlspecialchars($sdr['nome_completo'] ?? 'SDR', ENT_QUOTES, 'UTF-8'); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div>
        <label for="status_financeiro" class="block text-sm font-medium text-gray-700">Status financeiro</label>
        <select name="status_financeiro" id="status_financeiro" class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-theme-color focus:ring-theme-color">
            <option value="">Todos</option>
            <?php foreach ($statusFinanceiroOptions as $key => $label): ?>
                <option value="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>" <?= isset($filters['status_financeiro']) && $filters['status_financeiro'] === $key ? 'selected' : ''; ?>>
                    <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="md:col-span-2 lg:col-span-5 flex flex-wrap gap-2 justify-end items-end">
        <button type="submit" class="inline-flex items-center gap-2 rounded-md bg-theme-color px-4 py-2 text-white font-semibold shadow hover:opacity-90">
            <i class="fas fa-search"></i>
            Aplicar filtros
        </button>
        <a href="<?= htmlspecialchars($action, ENT_QUOTES, 'UTF-8'); ?>" class="inline-flex items-center gap-2 rounded-md bg-gray-200 px-4 py-2 text-gray-800 font-semibold shadow hover:bg-gray-300">
            <i class="fas fa-undo"></i>
            Limpar
        </a>
    </div>
</form>
