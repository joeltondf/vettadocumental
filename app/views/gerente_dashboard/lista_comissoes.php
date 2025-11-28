<?php
$baseAppUrl = rtrim(APP_URL, '/');
$gerenteDashboardUrl = $baseAppUrl . '/gerente_dashboard.php';
$filters = $filters ?? [];
$processos = $processos ?? [];
$totais = $totais ?? [
    'valor_total' => 0,
    'comissao_vendedor' => 0,
    'comissao_sdr' => 0,
];
$statusOptions = $statusOptions ?? [];

if (!function_exists('manager_format_currency')) {
    function manager_format_currency($value): string
    {
        return 'R$ ' . number_format((float) $value, 2, ',', '.');
    }
}

if (!function_exists('manager_format_date_br')) {
    function manager_format_date_br(?string $value): string
    {
        if (empty($value)) {
            return '--';
        }

        $timestamp = strtotime($value);

        return $timestamp ? date('d/m/Y', $timestamp) : '--';
    }
}

if (!function_exists('manager_commission_class')) {
    function manager_commission_class(?string $status): string
    {
        $normalized = mb_strtolower(trim((string) $status), 'UTF-8');

        return match ($normalized) {
            'serviço em andamento' => 'text-orange-600 font-semibold',
            'concluído', 'finalizado' => 'text-green-600 font-semibold',
            default => 'text-gray-700 font-semibold',
        };
    }
}

$totalProcessos = count($processos);
?>

<div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3 mb-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-800"><?php echo htmlspecialchars($pageTitle); ?></h1>
        <p class="text-gray-600 mt-1">Visualize as comissões de vendedores e SDRs em processos convertidos.</p>
    </div>
    <div class="flex flex-wrap gap-3">
        <a href="<?php echo $gerenteDashboardUrl; ?>" class="inline-flex items-center bg-gray-100 hover:bg-gray-200 text-gray-800 font-semibold py-2 px-4 rounded-lg shadow-sm transition-colors">
            <i class="fas fa-arrow-left mr-2"></i> Voltar ao painel
        </a>
    </div>
</div>

<div class="bg-white p-5 rounded-lg shadow-lg border border-gray-200 mb-6">
    <h4 class="text-lg font-semibold text-gray-800 mb-4">Filtros</h4>
    <form action="<?php echo $gerenteDashboardUrl; ?>" method="GET" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <input type="hidden" name="action" value="listar_comissoes">
        <div>
            <label for="data_inicio" class="text-sm font-semibold text-gray-700 mb-1 block">Data inicial (conversão)</label>
            <input type="date" id="data_inicio" name="data_inicio" value="<?php echo htmlspecialchars($filters['data_inicio'] ?? ''); ?>" class="w-full border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
        </div>
        <div>
            <label for="data_fim" class="text-sm font-semibold text-gray-700 mb-1 block">Data final (conversão)</label>
            <input type="date" id="data_fim" name="data_fim" value="<?php echo htmlspecialchars($filters['data_fim'] ?? ''); ?>" class="w-full border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
        </div>
        <div>
            <label for="vendedor_id" class="text-sm font-semibold text-gray-700 mb-1 block">Vendedor</label>
            <select id="vendedor_id" name="vendedor_id" class="w-full border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                <option value="">Todos</option>
                <?php foreach ($vendedores as $vendedor): ?>
                    <option value="<?php echo (int) $vendedor['id']; ?>" <?php echo (isset($filters['vendedor_id']) && (int) $filters['vendedor_id'] === (int) $vendedor['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($vendedor['nome_vendedor'] ?? $vendedor['nome_completo'] ?? ''); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="sdr_id" class="text-sm font-semibold text-gray-700 mb-1 block">SDR</label>
            <select id="sdr_id" name="sdr_id" class="w-full border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                <option value="">Todos</option>
                <?php foreach ($sdrs as $sdr): ?>
                    <option value="<?php echo (int) $sdr['id']; ?>" <?php echo (isset($filters['sdr_id']) && (int) $filters['sdr_id'] === (int) $sdr['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($sdr['nome_completo'] ?? $sdr['nome']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="status" class="text-sm font-semibold text-gray-700 mb-1 block">Status</label>
            <select id="status" name="status" class="w-full border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                <option value="">Todos</option>
                <?php foreach ($statusOptions as $status): ?>
                    <option value="<?php echo htmlspecialchars($status); ?>" <?php echo (isset($filters['status']) && $filters['status'] === $status) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($status); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="sm:col-span-2 lg:col-span-4 flex flex-wrap gap-3 justify-end">
            <button type="submit" class="inline-flex items-center bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded-lg shadow-md transition-colors">
                <i class="fas fa-search mr-2"></i> Aplicar filtros
            </button>
            <a href="<?php echo $gerenteDashboardUrl; ?>?action=listar_comissoes" class="inline-flex items-center bg-gray-100 hover:bg-gray-200 text-gray-800 font-semibold py-2 px-4 rounded-lg shadow-sm transition-colors">
                Limpar
            </a>
        </div>
    </form>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-5 gap-4 mb-6">
    <div class="p-4 bg-white rounded-lg shadow border border-gray-200">
        <p class="text-sm font-semibold text-gray-600">Processos listados</p>
        <p class="text-2xl font-bold text-gray-900"><?php echo $totalProcessos; ?></p>
    </div>
    <div class="p-4 bg-white rounded-lg shadow border border-gray-200">
        <p class="text-sm font-semibold text-gray-600">Valor total</p>
        <p class="text-2xl font-bold text-blue-700"><?php echo manager_format_currency($totais['valor_total'] ?? 0); ?></p>
    </div>
    <div class="p-4 bg-white rounded-lg shadow border border-gray-200">
        <p class="text-sm font-semibold text-gray-600">Comissão Vendedor</p>
        <p class="text-2xl font-bold text-green-700"><?php echo manager_format_currency($totais['comissao_vendedor'] ?? 0); ?></p>
    </div>
    <div class="p-4 bg-white rounded-lg shadow border border-gray-200">
        <p class="text-sm font-semibold text-gray-600">Comissão SDR</p>
        <p class="text-2xl font-bold text-indigo-700"><?php echo manager_format_currency($totais['comissao_sdr'] ?? 0); ?></p>
    </div>
    <div class="p-4 bg-white rounded-lg shadow border border-gray-200">
        <p class="text-sm font-semibold text-gray-600">Ticket médio</p>
        <p class="text-2xl font-bold text-purple-700"><?php echo manager_format_currency($ticketMedio ?? 0); ?></p>
    </div>
</div>

<div class="bg-white p-5 rounded-lg shadow-xl border border-gray-200">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3 mb-4">
        <h2 class="text-lg font-semibold text-gray-800">Comissões por processo</h2>
        <span class="text-sm text-gray-500">Total: <?php echo $totalProcessos; ?> registro(s)</span>
    </div>

    <?php if (empty($processos)): ?>
        <p class="text-sm text-gray-500 text-center py-6">Nenhum processo encontrado para os filtros selecionados.</p>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-3 py-2 text-left font-medium text-gray-500 uppercase tracking-wider">Código</th>
                        <th class="px-3 py-2 text-left font-medium text-gray-500 uppercase tracking-wider">Família / Título</th>
                        <th class="px-3 py-2 text-left font-medium text-gray-500 uppercase tracking-wider">Cliente</th>
                        <th class="px-3 py-2 text-left font-medium text-gray-500 uppercase tracking-wider">Data de Conversão</th>
                        <th class="px-3 py-2 text-right font-medium text-gray-500 uppercase tracking-wider">Valor Total</th>
                        <th class="px-3 py-2 text-right font-medium text-gray-500 uppercase tracking-wider">% Comissão Vendedor</th>
                        <th class="px-3 py-2 text-right font-medium text-gray-500 uppercase tracking-wider">Valor Comissão Vendedor</th>
                        <th class="px-3 py-2 text-right font-medium text-gray-500 uppercase tracking-wider">% Comissão SDR</th>
                        <th class="px-3 py-2 text-right font-medium text-gray-500 uppercase tracking-wider">Valor Comissão SDR</th>
                        <th class="px-3 py-2 text-left font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($processos as $processo): ?>
                        <?php
                            $codigo = !empty($processo['orcamento_numero']) ? $processo['orcamento_numero'] : $processo['id'];
                            $commissionClass = manager_commission_class($processo['status_processo'] ?? '');
                        ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-3 py-2 whitespace-nowrap font-mono text-gray-700">#<?php echo htmlspecialchars($codigo); ?></td>
                            <td class="px-3 py-2 whitespace-nowrap text-gray-700">
                                <?php echo htmlspecialchars($processo['categorias_servico'] ?? $processo['titulo'] ?? '—'); ?>
                            </td>
                            <td class="px-3 py-2 whitespace-nowrap text-gray-600"><?php echo htmlspecialchars($processo['nome_cliente'] ?? '—'); ?></td>
                            <td class="px-3 py-2 whitespace-nowrap text-gray-600"><?php echo manager_format_date_br($processo['data_conversao'] ?? null); ?></td>
                            <td class="px-3 py-2 whitespace-nowrap text-right text-gray-700 font-semibold"><?php echo manager_format_currency($processo['valor_total'] ?? 0); ?></td>
                            <td class="px-3 py-2 whitespace-nowrap text-right text-gray-700 font-semibold"><?php echo number_format($processo['percentual_comissao_vendedor'] ?? 0, 2, ',', '.'); ?>%</td>
                            <td class="px-3 py-2 whitespace-nowrap text-right <?php echo $commissionClass; ?>"><?php echo manager_format_currency($processo['valor_comissao_vendedor'] ?? 0); ?></td>
                            <td class="px-3 py-2 whitespace-nowrap text-right text-gray-700 font-semibold"><?php echo number_format($processo['percentual_comissao_sdr'] ?? 0, 2, ',', '.'); ?>%</td>
                            <td class="px-3 py-2 whitespace-nowrap text-right text-gray-700 font-semibold">
                                <div><?php echo manager_format_currency($processo['valor_comissao_sdr'] ?? 0); ?></div>
                                <div class="text-xs text-gray-500">
                                    <?php echo !empty($processo['nome_sdr']) ? htmlspecialchars($processo['nome_sdr']) : '—'; ?>
                                </div>
                            </td>
                            <td class="px-3 py-2 whitespace-nowrap text-gray-700"><?php echo htmlspecialchars($processo['status_processo'] ?? '—'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
