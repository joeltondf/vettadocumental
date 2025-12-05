<?php
require_once __DIR__ . '/../../utils/DashboardProcessFormatter.php';

if (!function_exists('seller_normalize_status_info')) {
    function seller_normalize_status_info(?string $status): array
    {
        return DashboardProcessFormatter::normalizeStatusInfo($status);
    }
}

if (!function_exists('seller_status_label_class')) {
    function seller_status_label_class(string $normalized): string
    {
        return match ($normalized) {
            'orçamento', 'orçamento pendente' => 'text-blue-700',
            'serviço pendente' => 'text-orange-700',
            'serviço em andamento' => 'text-cyan-700',
            'concluído' => 'text-purple-700',
            'cancelado' => 'text-red-700',
            default => 'text-gray-700',
        };
    }
}

if (!function_exists('seller_status_badge_class')) {
    function seller_status_badge_class(?string $badgeLabel): string
    {
        if ($badgeLabel === null || $badgeLabel === '') {
            return '';
        }

        $map = [
            'pendente de pagamento' => 'bg-indigo-100 text-indigo-800',
            'pendente de documentos' => 'bg-violet-100 text-violet-800',
        ];

        $key = mb_strtolower($badgeLabel);

        return $map[$key] ?? 'bg-indigo-100 text-indigo-800';
    }
}

if (!function_exists('seller_format_currency_br')) {
    function seller_format_currency_br($value): string
    {
        return 'R$ ' . number_format((float) $value, 2, ',', '.');
    }
}

if (!function_exists('seller_get_process_title')) {
    function seller_get_process_title(array $process): string
    {
        $titulo = trim((string) ($process['titulo'] ?? ''));
        if ($titulo !== '') {
            return $titulo;
        }

        $categoria = trim((string) ($process['categorias_servico'] ?? ''));
        if ($categoria !== '') {
            return $categoria;
        }

        return '—';
    }
}

if (!function_exists('seller_format_date_br')) {
    function seller_format_date_br(?string $dateValue): string
    {
        if (empty($dateValue)) {
            return '--';
        }

        $timestamp = strtotime($dateValue);
        if ($timestamp === false) {
            return '--';
        }

        return date('d/m/Y', $timestamp);
    }
}

$baseAppUrl = rtrim(APP_URL, '/');
$dashboardVendedorUrl = $baseAppUrl . '/dashboard_vendedor.php';
$gestorVendedorId = isset($_GET['vendedor_id']) ? (int) $_GET['vendedor_id'] : null;
$dashboardVendedorUrlWithVendor = $gestorVendedorId ? $dashboardVendedorUrl . '?vendedor_id=' . $gestorVendedorId : $dashboardVendedorUrl;
$pageTitle = $pageTitle ?? 'Todos os Processos';
$totalProcessesCount = $totalProcessesCount ?? count($processos ?? []);
$initialVisibleRows = 25;
?>

<div class="flex flex-col md:flex-row md:items-center md:justify-between mb-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-800"><?php echo htmlspecialchars($pageTitle); ?></h1>
        <p class="mt-1 text-gray-600">Acompanhe todos os processos do vendedor com filtros, comissões e navegação rápida.</p>
    </div>
    <div class="flex flex-wrap gap-3">
        <a href="<?php echo $dashboardVendedorUrlWithVendor; ?>" class="inline-flex items-center bg-gray-100 hover:bg-gray-200 text-gray-800 font-semibold py-2.5 px-4 rounded-lg shadow-sm transition-colors">
            <i class="fas fa-arrow-left mr-2"></i> Voltar ao painel
        </a>
    </div>
</div>

<div class="w-full bg-white p-5 rounded-lg shadow-xl border border-gray-200 mb-6">
    <h4 class="text-lg font-semibold text-gray-800 mb-4">Filtros</h4>
    <form action="<?php echo $dashboardVendedorUrl; ?>" method="GET" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <input type="hidden" name="action" value="listar_processos" />
        <?php if ($gestorVendedorId): ?>
            <input type="hidden" name="vendedor_id" value="<?php echo $gestorVendedorId; ?>">
        <?php endif; ?>
        <div>
            <label for="titulo" class="text-sm font-semibold text-gray-700 mb-1 block">Serviço / Processo</label>
            <input type="text" id="titulo" name="titulo" value="<?php echo htmlspecialchars($filters['titulo'] ?? ''); ?>" class="w-full border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500" placeholder="Buscar por nome ou palavra-chave">
        </div>
        <div>
            <label for="cliente_id" class="text-sm font-semibold text-gray-700 mb-1 block">Cliente</label>
            <select id="cliente_id" name="cliente_id" class="w-full border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                <option value="">Todos</option>
                <?php foreach ($clientesParaFiltro as $cliente): ?>
                    <option value="<?php echo (int) $cliente['id']; ?>" <?php echo isset($filters['cliente_id']) && (int) $filters['cliente_id'] === (int) $cliente['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($cliente['nome_cliente']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="status" class="text-sm font-semibold text-gray-700 mb-1 block">Status</label>
            <select id="status" name="status" class="w-full border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                <option value="">Todos</option>
                <?php
                    $statusOptions = ['Orçamento', 'Orçamento Pendente', 'Serviço Pendente', 'Serviço em Andamento', 'Pago - A Enviar', 'Pendente de Pagamento', 'Pendente de Documentos', 'Concluído', 'Cancelado'];
                    foreach ($statusOptions as $option):
                        $optionInfo = seller_normalize_status_info($option);
                        $optionLabel = $optionInfo['label'];
                        if (!empty($optionInfo['badge_label'])) {
                            $optionLabel .= ' (' . $optionInfo['badge_label'] . ')';
                        }
                ?>
                    <option value="<?php echo htmlspecialchars($option, ENT_QUOTES, 'UTF-8'); ?>" <?php echo ($filters['status'] ?? '') === $option ? 'selected' : ''; ?>><?php echo htmlspecialchars($optionLabel); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 sm:col-span-2 lg:col-span-2">
            <div>
                <label for="data_inicio" class="text-sm font-semibold text-gray-700 mb-1 block">Data início</label>
                <input type="date" id="data_inicio" name="data_inicio" value="<?php echo htmlspecialchars($filters['data_inicio'] ?? ''); ?>" class="w-full border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
            </div>
            <div>
                <label for="data_fim" class="text-sm font-semibold text-gray-700 mb-1 block">Data fim</label>
                <input type="date" id="data_fim" name="data_fim" value="<?php echo htmlspecialchars($filters['data_fim'] ?? ''); ?>" class="w-full border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
            </div>
        </div>
        <div class="sm:col-span-2 lg:col-span-4 flex flex-wrap gap-3 justify-end">
            <button type="submit" class="inline-flex items-center bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded-lg shadow-md transition-colors">
                <i class="fas fa-search mr-2"></i> Aplicar filtros
            </button>
            <a href="<?php echo $dashboardVendedorUrl; ?>?action=listar_processos" class="inline-flex items-center bg-gray-100 hover:bg-gray-200 text-gray-800 font-semibold py-2 px-4 rounded-lg shadow-sm transition-colors">
                Limpar
            </a>
        </div>
    </form>
</div>

<div class="bg-white p-5 rounded-lg shadow-xl border border-gray-200">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3 mb-4">
        <h2 class="text-lg font-semibold text-gray-800">Lista completa de processos</h2>
        <span class="text-sm text-gray-500">Total: <?php echo (int) $totalProcessesCount; ?> registro(s)</span>
    </div>

    <?php if (empty($processos)): ?>
        <p class="text-sm text-gray-500 text-center py-6">Nenhum processo encontrado com os filtros selecionados.</p>
    <?php else: ?>
        <div class="overflow-x-auto">
            <div class="overflow-y-auto max-h-[500px]">
            <table class="min-w-full divide-y divide-gray-200 text-sm" id="processos-table">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-3 py-2 text-left font-medium text-gray-500 uppercase tracking-wider">Código</th>
                        <th class="px-3 py-2 text-left font-medium text-gray-500 uppercase tracking-wider">Título / Família</th>
                        <th class="px-3 py-2 text-left font-medium text-gray-500 uppercase tracking-wider">Cliente</th>
                        <th class="px-3 py-2 text-left font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-3 py-2 text-left font-medium text-gray-500 uppercase tracking-wider">Vendedor / SDR</th>
                        <th class="px-3 py-2 text-right font-medium text-gray-500 uppercase tracking-wider">Valor total</th>
                        <th class="px-3 py-2 text-right font-medium text-gray-500 uppercase tracking-wider">Comissão Vendedor</th>
                        <th class="px-3 py-2 text-right font-medium text-gray-500 uppercase tracking-wider">Atualização</th>
                        <th class="px-3 py-2 text-center font-medium text-gray-500 uppercase tracking-wider">Ações</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($processos as $index => $processo): ?>
                        <?php
                            $statusInfo = seller_normalize_status_info($processo['status_processo'] ?? '');
                            $statusLabelClass = seller_status_label_class($statusInfo['normalized']);
                            $statusBadgeClass = seller_status_badge_class($statusInfo['badge_label'] ?? null);
                            $codigo = $processo['os_numero_omie'] ?? $processo['id'];
                            $showCommission = !in_array($statusInfo['normalized'], ['orçamento', 'orçamento pendente'], true);
                            $commissionClass = match ($statusInfo['normalized']) {
                                'serviço pendente', 'serviço em andamento' => 'text-orange-600',
                                'concluído', 'finalizado' => 'text-green-600',
                                default => 'text-gray-700',
                            };
                        ?>
                        <tr class="hover:bg-gray-50" data-process-row data-row-index="<?php echo $index; ?>" <?php echo $index >= $initialVisibleRows ? 'style="display:none;"' : ''; ?>>
                            <td class="px-3 py-2 whitespace-nowrap font-mono text-gray-700">#<?php echo htmlspecialchars($codigo); ?></td>
                            <td class="px-3 py-2 whitespace-nowrap text-gray-700">
                                <a href="<?php echo $baseAppUrl; ?>/processos.php?action=view&amp;id=<?php echo (int) $processo['id']; ?>" class="text-blue-600 hover:underline">
                                    <?php echo htmlspecialchars(seller_get_process_title($processo)); ?>
                                </a>
                            </td>
                            <td class="px-3 py-2 whitespace-nowrap text-gray-600"><?php echo htmlspecialchars($processo['nome_cliente'] ?? '—'); ?></td>
                            <td class="px-3 py-2 whitespace-nowrap">
                                <div class="flex flex-wrap items-center gap-1 text-xs font-semibold <?php echo $statusLabelClass; ?>">
                                    <span><?php echo htmlspecialchars($statusInfo['label']); ?></span>
                                    <?php if (!empty($statusInfo['badge_label'])): ?>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-medium <?php echo $statusBadgeClass; ?>">
                                            <?php echo htmlspecialchars($statusInfo['badge_label']); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="px-3 py-2 whitespace-nowrap text-gray-700">
                                <div class="flex flex-col text-xs leading-tight">
                                    <span class="font-semibold text-gray-800"><?php echo htmlspecialchars($processo['nome_vendedor'] ?? '—'); ?></span>
                                    <span class="text-gray-600">SDR: <?php echo !empty($processo['sdr_id']) ? '#' . (int) $processo['sdr_id'] : '—'; ?></span>
                                </div>
                            </td>
                            <td class="px-3 py-2 whitespace-nowrap text-right text-gray-700 font-semibold"><?php echo seller_format_currency_br($processo['valor_total'] ?? 0); ?></td>
                            <td class="px-3 py-2 whitespace-nowrap text-right font-semibold <?php echo $commissionClass; ?>">
                                <?php if ($showCommission): ?>
                                    <?php echo seller_format_currency_br($processo['comissaoVendedor'] ?? 0); ?>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                            <td class="px-3 py-2 whitespace-nowrap text-right text-gray-600"><?php echo seller_format_date_br($processo['data_criacao'] ?? null); ?></td>
                            <td class="px-3 py-2 whitespace-nowrap text-center">
                                <a href="<?php echo $baseAppUrl; ?>/processos.php?action=view&amp;id=<?php echo (int) $processo['id']; ?>" class="inline-flex items-center gap-2 text-sm font-semibold text-indigo-600 hover:text-indigo-800">
                                    <i class="fas fa-external-link-alt"></i>
                                    Ver
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
        <?php if ($totalProcessesCount > $initialVisibleRows): ?>
            <div class="mt-4 text-center">
                <button id="load-more-processos" class="inline-flex items-center bg-gray-200 hover:bg-gray-300 text-gray-800 font-semibold py-2 px-6 rounded-lg shadow-sm">
                    Carregar mais
                </button>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const rows = Array.from(document.querySelectorAll('[data-process-row]'));
        const loadMoreBtn = document.getElementById('load-more-processos');
        if (!loadMoreBtn) return;

        let visible = <?php echo $initialVisibleRows; ?>;
        const step = 25;

        loadMoreBtn.addEventListener('click', function (event) {
            event.preventDefault();
            visible += step;
            rows.slice(0, visible).forEach(row => row.style.display = '');
            if (visible >= rows.length) {
                loadMoreBtn.style.display = 'none';
            }
        });
    });
</script>
