<?php
// --- Configurações Iniciais ---
require_once __DIR__ . '/../../utils/DashboardProcessFormatter.php';

$activeFilters = array_filter($filters ?? []);
$hasFilters = !empty($activeFilters);
$initialProcessLimit = 50;
$baseAppUrl = rtrim(APP_URL, '/');
$dashboardVendedorUrl = $baseAppUrl . '/dashboard_vendedor.php';
$currentUserPerfil = $_SESSION['user_perfil'] ?? '';

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

$selectedStatusInfo = seller_normalize_status_info($filters['status'] ?? '');
$selectedStatusNormalized = $selectedStatusInfo['normalized'];
$nextLead = $nextLead ?? null;
$vendorLeads = $vendorLeads ?? [];
?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between mb-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Meu Painel de Vendas</h1>
        <p class="mt-1 text-gray-600">Bem-vindo(a), <?php echo htmlspecialchars($_SESSION['user_nome'] ?? ''); ?>! Acompanhe sua performance e atividades.</p>
    </div>
    <div class="flex flex-wrap gap-3">
        <a href="<?php echo $baseAppUrl; ?>/processos.php?action=create&amp;return_to=<?php echo urlencode($dashboardVendedorUrl); ?>" class="inline-flex items-center bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2.5 px-4 rounded-lg shadow-md transition-colors">
            <i class="fas fa-file-signature mr-2"></i> Criar Orçamento
        </a>
        <?php if ($currentUserPerfil === 'vendedor'): ?>
            <a href="<?php echo $baseAppUrl; ?>/clientes.php?action=create&amp;return_to=<?php echo urlencode($dashboardVendedorUrl); ?>" class="inline-flex items-center bg-emerald-600 hover:bg-emerald-700 text-white font-semibold py-2.5 px-4 rounded-lg shadow-md transition-colors">
                <i class="fas fa-user-plus mr-2"></i> Novo Cliente
            </a>
        <?php endif; ?>
        <a href="<?php echo $baseAppUrl; ?>/crm/prospeccoes/lista.php" class="inline-flex items-center bg-green-600 hover:bg-green-700 text-white font-semibold py-2.5 px-4 rounded-lg shadow-md transition-colors">
            <i class="fas fa-bullseye mr-2"></i> Ver Prospecções
        </a>
    </div>
</div>

<div class="mb-8">
    <h2 class="text-lg font-semibold text-gray-700 mb-3">Próximo lead</h2>
    <div class="bg-white p-5 rounded-lg shadow-md border border-gray-200">
        <?php if ($nextLead): ?>
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div>
                    <p class="text-sm text-gray-500">Lead</p>
                    <h3 class="text-xl font-bold text-gray-800"><?php echo htmlspecialchars($nextLead['nome_prospecto'] ?? 'Lead'); ?></h3>
                    <p class="text-sm text-gray-500 mt-1">Cliente: <?php echo htmlspecialchars($nextLead['nome_cliente'] ?? 'Não informado'); ?></p>
                    <p class="text-xs text-gray-400 mt-1">Distribuído em <?php echo !empty($nextLead['distributed_at']) ? date('d/m/Y H:i', strtotime($nextLead['distributed_at'])) : '--'; ?></p>
                    <div class="mt-2 inline-flex items-center gap-2 bg-indigo-50 text-indigo-700 px-3 py-1 rounded-full text-xs font-semibold">
                        Pontuação: <?php echo (int) ($nextLead['qualification_score'] ?? 0); ?>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <a href="<?php echo $baseAppUrl; ?>/crm/prospeccoes/detalhes.php?id=<?php echo (int) ($nextLead['id'] ?? 0); ?>" class="px-4 py-2 bg-blue-600 text-white rounded-lg shadow hover:bg-blue-700 transition-colors text-sm">Abrir lead</a>
                    <a href="<?php echo $baseAppUrl; ?>/qualificacao.php?action=create&amp;id=<?php echo (int) ($nextLead['id'] ?? 0); ?>" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors text-sm">Registrar avanço</a>
                </div>
            </div>
        <?php else: ?>
            <p class="text-sm text-gray-500">Nenhum lead pendente para atendimento imediato.</p>
        <?php endif; ?>
    </div>
</div>

<div class="mb-8">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3 mb-3">
        <h2 class="text-lg font-semibold text-gray-700">Leads em acompanhamento</h2>
        <?php if (!empty($vendorLeads)): ?>
            <button type="button" class="inline-flex items-center gap-2 text-sm text-gray-600 hover:text-gray-800" data-sort-vendor-score>
                Ordenar por pontuação
                <i class="fas fa-sort-amount-down-alt text-xs"></i>
            </button>
        <?php endif; ?>
    </div>
    <div class="bg-white p-5 rounded-lg shadow-md border border-gray-200">
        <?php if (empty($vendorLeads)): ?>
            <p class="text-sm text-gray-500 text-center py-6">Nenhum lead atribuído para você no momento.</p>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm" id="vendor-leads-table">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase tracking-wider">Lead</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase tracking-wider">Cliente</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-4 py-3 text-center font-medium text-gray-500 uppercase tracking-wider">Pontuação</th>
                            <th class="px-4 py-3 text-right font-medium text-gray-500 uppercase tracking-wider">Atualização</th>
                            <th class="px-4 py-3 text-center font-medium text-gray-500 uppercase tracking-wider">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($vendorLeads as $leadRow): ?>
                            <?php
                                $vendorLeadScore = (int) ($leadRow['qualification_score'] ?? 0);
                                $vendorLeadUpdated = isset($leadRow['data_ultima_atualizacao'])
                                    ? date('d/m/Y H:i', strtotime($leadRow['data_ultima_atualizacao']))
                                    : '--';
                            ?>
                            <tr data-lead-row data-score="<?php echo $vendorLeadScore; ?>">
                                <td class="px-4 py-3 font-semibold text-gray-700"><?php echo htmlspecialchars($leadRow['nome_prospecto'] ?? 'Lead'); ?></td>
                                <td class="px-4 py-3 text-gray-600"><?php echo htmlspecialchars($leadRow['nome_cliente'] ?? 'Cliente não informado'); ?></td>
                                <td class="px-4 py-3 text-gray-600"><?php echo htmlspecialchars($leadRow['status'] ?? 'Sem status'); ?></td>
                                <td class="px-4 py-3 text-center">
                                    <span class="inline-flex items-center justify-center px-2 py-1 rounded-full bg-indigo-50 text-indigo-600 font-semibold"><?php echo $vendorLeadScore; ?></span>
                                </td>
                                <td class="px-4 py-3 text-right text-gray-600"><?php echo $vendorLeadUpdated; ?></td>
                                <td class="px-4 py-3 text-center">
                                    <a href="<?php echo $baseAppUrl; ?>/crm/prospeccoes/detalhes.php?id=<?php echo (int) ($leadRow['id'] ?? 0); ?>" class="inline-flex items-center px-3 py-1 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors text-xs">
                                        Abrir
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="mb-8">
    <h2 class="text-lg font-semibold text-gray-700 mb-3">Resumo de Performance</h2>
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
        <div class="bg-white p-5 rounded-lg shadow-md flex items-center justify-between">
            <div>
                <p class="text-gray-500 text-sm">Vendas (Mês)</p>
                <p class="text-2xl font-bold text-gray-800">R$ <?php echo number_format($totalVendasMes ?? 0, 2, ',', '.'); ?></p>
            </div>
            <span class="bg-purple-100 text-purple-600 p-3 rounded-full"><i class="fas fa-dollar-sign"></i></span>
        </div>
        <div class="bg-white p-5 rounded-lg shadow-md flex items-center justify-between">
            <div>
                <p class="text-gray-500 text-sm">Comissão Estimada</p>
                <p class="text-2xl font-bold text-gray-800">R$ <?php echo number_format($valorComissao ?? 0, 2, ',', '.'); ?></p>
            </div>
            <span class="bg-teal-100 text-teal-600 p-3 rounded-full"><i class="fas fa-hand-holding-usd"></i></span>
        </div>
        <div class="bg-white p-5 rounded-lg shadow-md flex items-center justify-between">
            <div>
                <p class="text-gray-500 text-sm">Conversão Geral</p>
                <p class="text-2xl font-bold text-gray-800"><?php echo number_format($crmStats['taxa_conversao'] ?? 0, 1, ',', '.'); ?>%</p>
            </div>
            <span class="bg-yellow-100 text-yellow-600 p-3 rounded-full"><i class="fas fa-chart-line"></i></span>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
    <div class="lg:col-span-2 bg-white p-5 rounded-lg shadow-xl border border-gray-200">
        <h4 class="text-xl font-bold text-gray-800 mb-4">Funil de Prospecção Ativa</h4>
        <div style="height: 300px;">
            <canvas id="funilVendasChart"></canvas>
        </div>
    </div>
    <div class="bg-white p-5 rounded-lg shadow-xl border border-gray-200">
        <h4 class="text-xl font-bold text-gray-800 mb-4">Próximos Agendamentos</h4>
        <ul class="space-y-3">
            <?php if (empty($proximosAgendamentos)): ?>
                <li class="text-center text-gray-500 py-4">Nenhum agendamento futuro.</li>
            <?php else: ?>
                <?php foreach ($proximosAgendamentos as $ag): ?>
                <li class="border-b pb-2">
                    <p class="font-semibold text-sm text-gray-800"><?php echo htmlspecialchars($ag['titulo']); ?></p>
                    <p class="text-xs text-gray-500"><?php echo htmlspecialchars($ag['nome_cliente'] ?? 'Cliente não definido'); ?></p>
                    <p class="text-xs font-bold text-indigo-600"><?php echo date('d/m/Y H:i', strtotime($ag['data_inicio'])); ?></p>
                </li>
                <?php endforeach; ?>
            <?php endif; ?>
        </ul>
    </div>
</div>

<div class="space-y-6 mb-8">
    <div class="bg-white shadow-md rounded-lg border border-gray-200">
        <div class="px-6 py-4 border-b">
            <h3 class="text-lg font-semibold text-gray-800">Orçamentos - Mês atual</h3>
        </div>
        <div class="p-4">
            <?php if (empty($orcamentosMesAtual)): ?>
                <p class="text-sm text-gray-500 text-center py-6">Nenhum orçamento registrado neste mês.</p>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-3 py-2 text-left font-medium text-gray-500 uppercase tracking-wider">Código</th>
                                <th class="px-3 py-2 text-left font-medium text-gray-500 uppercase tracking-wider">Título / Família</th>
                                <th class="px-3 py-2 text-left font-medium text-gray-500 uppercase tracking-wider">Cliente</th>
                                <th class="px-3 py-2 text-left font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-3 py-2 text-left font-medium text-gray-500 uppercase tracking-wider">Data de entrada</th>
                                <th class="px-3 py-2 text-right font-medium text-gray-500 uppercase tracking-wider">Valor total</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($orcamentosMesAtual as $orcamento): ?>
                                <?php
                                    $codigo = !empty($orcamento['orcamento_numero']) ? $orcamento['orcamento_numero'] : $orcamento['id'];
                                    $statusInfo = seller_normalize_status_info($orcamento['status_processo'] ?? '');
                                    $statusLabelClass = seller_status_label_class($statusInfo['normalized']);
                                    $statusBadgeClass = seller_status_badge_class($statusInfo['badge_label'] ?? null);
                                ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-3 py-2 whitespace-nowrap font-mono text-gray-700">#<?php echo htmlspecialchars($codigo); ?></td>
                                    <td class="px-3 py-2 whitespace-nowrap text-gray-700">
                                        <a href="processos.php?action=view&amp;id=<?php echo (int) $orcamento['id']; ?>" class="text-blue-600 hover:underline">
                                            <?php echo htmlspecialchars(seller_get_process_title($orcamento)); ?>
                                        </a>
                                    </td>
                                    <td class="px-3 py-2 whitespace-nowrap text-gray-600"><?php echo htmlspecialchars($orcamento['nome_cliente'] ?? '—'); ?></td>
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
                                    <td class="px-3 py-2 whitespace-nowrap text-gray-600"><?php echo seller_format_date_br($orcamento['data_criacao'] ?? null); ?></td>
                                    <td class="px-3 py-2 whitespace-nowrap text-right text-gray-700 font-semibold"><?php echo seller_format_currency_br($orcamento['valor_total'] ?? 0); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="bg-white shadow-md rounded-lg border border-gray-200">
        <div class="px-6 py-4 border-b">
            <h3 class="text-lg font-semibold text-gray-800">Serviços - Mês atual</h3>
        </div>
        <div class="p-4">
            <?php if (empty($servicosMesAtual)): ?>
                <p class="text-sm text-gray-500 text-center py-6">Nenhum serviço aberto neste mês.</p>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-3 py-2 text-left font-medium text-gray-500 uppercase tracking-wider">Código</th>
                                <th class="px-3 py-2 text-left font-medium text-gray-500 uppercase tracking-wider">Título / Família</th>
                                <th class="px-3 py-2 text-left font-medium text-gray-500 uppercase tracking-wider">Cliente</th>
                                <th class="px-3 py-2 text-left font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-3 py-2 text-left font-medium text-gray-500 uppercase tracking-wider">Data de entrada</th>
                                <th class="px-3 py-2 text-right font-medium text-gray-500 uppercase tracking-wider">Valor total</th>
                                <th class="px-3 py-2 text-right font-medium text-gray-500 uppercase tracking-wider">Comissão do Vendedor</th>
                                <th class="px-3 py-2 text-right font-medium text-gray-500 uppercase tracking-wider">Comissão do SDR</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($servicosMesAtual as $servico): ?>
                                <?php
                                    $codigo = !empty($servico['orcamento_numero']) ? $servico['orcamento_numero'] : $servico['id'];
                                    $statusInfo = seller_normalize_status_info($servico['status_processo'] ?? '');
                                    $statusLabelClass = seller_status_label_class($statusInfo['normalized']);
                                    $statusBadgeClass = seller_status_badge_class($statusInfo['badge_label'] ?? null);
                                ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-3 py-2 whitespace-nowrap font-mono text-gray-700">#<?php echo htmlspecialchars($codigo); ?></td>
                                    <td class="px-3 py-2 whitespace-nowrap text-gray-700">
                                        <a href="processos.php?action=view&amp;id=<?php echo (int) $servico['id']; ?>" class="text-blue-600 hover:underline">
                                            <?php echo htmlspecialchars(seller_get_process_title($servico)); ?>
                                        </a>
                                    </td>
                                    <td class="px-3 py-2 whitespace-nowrap text-gray-600"><?php echo htmlspecialchars($servico['nome_cliente'] ?? '—'); ?></td>
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
                                    <td class="px-3 py-2 whitespace-nowrap text-gray-600"><?php echo seller_format_date_br($servico['data_criacao'] ?? null); ?></td>
                                    <td class="px-3 py-2 whitespace-nowrap text-right text-gray-700 font-semibold"><?php echo seller_format_currency_br($servico['valor_total'] ?? 0); ?></td>
                                    <td class="px-3 py-2 whitespace-nowrap text-right text-gray-700 font-semibold"><?php echo seller_format_currency_br($servico['comissaoVendedor'] ?? 0); ?></td>
                                    <td class="px-3 py-2 whitespace-nowrap text-right text-gray-700 font-semibold"><?php echo seller_format_currency_br($servico['comissaoSdr'] ?? 0); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="bg-white shadow-md rounded-lg border border-gray-200">
        <div class="px-6 py-4 border-b">
            <h3 class="text-lg font-semibold text-gray-800">Serviços em andamento - Iniciados no mês anterior</h3>
        </div>
        <div class="p-4">
            <?php if (empty($servicosAtivosMesAnterior)): ?>
                <p class="text-sm text-gray-500 text-center py-6">Nenhum serviço ativo iniciado no mês anterior.</p>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-3 py-2 text-left font-medium text-gray-500 uppercase tracking-wider">Código</th>
                                <th class="px-3 py-2 text-left font-medium text-gray-500 uppercase tracking-wider">Título / Família</th>
                                <th class="px-3 py-2 text-left font-medium text-gray-500 uppercase tracking-wider">Cliente</th>
                                <th class="px-3 py-2 text-left font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-3 py-2 text-left font-medium text-gray-500 uppercase tracking-wider">Data de entrada</th>
                                <th class="px-3 py-2 text-right font-medium text-gray-500 uppercase tracking-wider">Valor total</th>
                                <th class="px-3 py-2 text-right font-medium text-gray-500 uppercase tracking-wider">Comissão do Vendedor</th>
                                <th class="px-3 py-2 text-right font-medium text-gray-500 uppercase tracking-wider">Comissão do SDR</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($servicosAtivosMesAnterior as $servicoAnterior): ?>
                                <?php
                                    $codigo = !empty($servicoAnterior['orcamento_numero']) ? $servicoAnterior['orcamento_numero'] : $servicoAnterior['id'];
                                    $statusInfo = seller_normalize_status_info($servicoAnterior['status_processo'] ?? '');
                                    $statusLabelClass = seller_status_label_class($statusInfo['normalized']);
                                    $statusBadgeClass = seller_status_badge_class($statusInfo['badge_label'] ?? null);
                                ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-3 py-2 whitespace-nowrap font-mono text-gray-700">#<?php echo htmlspecialchars($codigo); ?></td>
                                    <td class="px-3 py-2 whitespace-nowrap text-gray-700">
                                        <a href="processos.php?action=view&amp;id=<?php echo (int) $servicoAnterior['id']; ?>" class="text-blue-600 hover:underline">
                                            <?php echo htmlspecialchars(seller_get_process_title($servicoAnterior)); ?>
                                        </a>
                                    </td>
                                    <td class="px-3 py-2 whitespace-nowrap text-gray-600"><?php echo htmlspecialchars($servicoAnterior['nome_cliente'] ?? '—'); ?></td>
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
                                    <td class="px-3 py-2 whitespace-nowrap text-gray-600"><?php echo seller_format_date_br($servicoAnterior['data_criacao'] ?? null); ?></td>
                                    <td class="px-3 py-2 whitespace-nowrap text-right text-gray-700 font-semibold"><?php echo seller_format_currency_br($servicoAnterior['valor_total'] ?? 0); ?></td>
                                    <td class="px-3 py-2 whitespace-nowrap text-right text-gray-700 font-semibold"><?php echo seller_format_currency_br($servicoAnterior['comissaoVendedor'] ?? 0); ?></td>
                                    <td class="px-3 py-2 whitespace-nowrap text-right text-gray-700 font-semibold"><?php echo seller_format_currency_br($servicoAnterior['comissaoSdr'] ?? 0); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="w-full bg-white p-5 rounded-lg shadow-xl border border-gray-200 mb-6">
    <h4 class="text-xl font-bold text-gray-800 mb-5 border-b pb-2">Filtrar Meus Processos</h4>
    <form action="dashboard_vendedor.php" method="GET" id="filter-form">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 mb-5">
            <div>
                <label for="titulo" class="text-sm font-semibold text-gray-700 mb-1 block">Serviço/Processo</label>
                <input type="text" id="titulo" name="titulo" placeholder="Digite para buscar" class="w-full px-3 py-1.5 text-sm border border-gray-300 rounded-lg" value="<?php echo htmlspecialchars($filters['titulo'] ?? ''); ?>">
            </div>
            <div>
                <label for="tipo_servico" class="text-sm font-semibold text-gray-700 mb-1 block">Tipo de Serviço</label>
                <select id="tipo_servico" name="tipo_servico" class="w-full px-3 py-1.5 text-sm border border-gray-300 rounded-lg">
                    <option value="">Todos os Tipos</option>
                    <?php $tipos = ['Tradução', 'CRC', 'Apostilamento', 'Postagem', 'Outros']; foreach($tipos as $tipo): ?>
                        <option value="<?php echo $tipo; ?>" <?php echo (($filters['tipo_servico'] ?? '') == $tipo) ? 'selected' : ''; ?>><?php echo $tipo; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="status" class="text-sm font-semibold text-gray-700 mb-1 block">Status</label>
                <select id="status" name="status" class="w-full px-3 py-1.5 text-sm border border-gray-300 rounded-lg">
                    <option value="">Todos os Status</option>
                    <?php $statusOptions = ['Orçamento Pendente', 'Orçamento', 'Serviço Pendente', 'Serviço em Andamento', 'Pendente de pagamento', 'Pendente de documentos', 'Concluído', 'Cancelado']; foreach ($statusOptions as $option): ?>
                        <?php
                            $optionInfo = seller_normalize_status_info($option);
                            $optionLabel = $optionInfo['label'];
                            if (!empty($optionInfo['badge_label'])) {
                                $optionLabel .= ' (' . $optionInfo['badge_label'] . ')';
                            }
                        ?>
                        <option value="<?php echo htmlspecialchars($option, ENT_QUOTES, 'UTF-8'); ?>" <?php echo ($selectedStatusNormalized === $optionInfo['normalized']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($optionLabel); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
             <div>
                <label for="data_inicio" class="text-sm font-semibold text-gray-700 mb-1 block">Data de Entrada (Início)</label>
                <input type="date" id="data_inicio" name="data_inicio" class="w-full px-3 py-1.5 text-sm border border-gray-300 rounded-lg" value="<?php echo htmlspecialchars($filters['data_inicio'] ?? ''); ?>">
            </div>
            <div>
                <label for="data_fim" class="text-sm font-semibold text-gray-700 mb-1 block">Data de Entrada (Fim)</label>
                <input type="date" id="data_fim" name="data_fim" class="w-full px-3 py-1.5 text-sm border border-gray-300 rounded-lg" value="<?php echo htmlspecialchars($filters['data_fim'] ?? ''); ?>">
            </div>
        </div>
        <div class="flex items-center justify-end mt-5 space-x-2">
            <a href="dashboard_vendedor.php" class="text-sm font-medium text-gray-600 px-5 py-2.5 rounded-lg border border-gray-300 hover:bg-gray-100">Limpar Filtros</a>
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-6 rounded-lg shadow-md"><i class="fas fa-filter mr-2"></i>Filtrar</button>
        </div>
    </form>
</div>

<div class="bg-white shadow-md rounded-lg">
    <div class="px-6 py-4 border-b">
        <h3 class="text-lg font-medium leading-6 text-gray-900"><?php echo $hasFilters ? 'Resultados da Busca' : 'Meus Últimos Processos'; ?></h3>
    </div>
    <div class="overflow-x-auto">
        <table id="processos-table" class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-3 py-2 text-left text-sm font-medium text-gray-500 uppercase">Família</th>
                    <th class="px-3 py-2 text-left text-sm font-medium text-gray-500 uppercase">Assessoria</th>
                    <th class="px-3 py-2 text-center text-sm font-medium text-gray-500 uppercase">Doc.</th>
                    <th class="px-3 py-2 text-left text-sm font-medium text-gray-500 uppercase">Serviços</th>
                    <th class="px-3 py-2 text-left text-sm font-medium text-gray-500 uppercase">Entrada</th>
                    <th class="px-3 py-2 text-right text-sm font-medium text-gray-500 uppercase">Valor Total</th>
                    <th class="px-3 py-2 text-center text-sm font-medium text-gray-500 uppercase">Detalhes</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if (empty($processos)): ?>
                    <tr><td colspan="7" class="text-center py-12 text-gray-500">Nenhum processo encontrado.</td></tr>
                <?php else: ?>
                    <?php foreach ($processos as $processo): ?>
                        <?php
                            $rowClass = 'hover:bg-gray-50';
                            $statusInfo = seller_normalize_status_info($processo['status_processo'] ?? '');
                            $statusAtual = $statusInfo['normalized'];
                            switch ($statusAtual) {
                                case 'orçamento':
                                    $rowClass = 'bg-blue-50 hover:bg-blue-100';
                                    break;
                                case 'orçamento pendente':
                                    $rowClass = 'bg-yellow-50 hover:bg-yellow-100';
                                    break;
                                case 'serviço em andamento':
                                    $rowClass = 'bg-indigo-50 hover:bg-indigo-100';
                                    break;
                                case 'serviço pendente':
                                    $rowClass = 'bg-orange-50 hover:bg-orange-100';
                                    break;
                                case 'concluído':
                                    $rowClass = 'bg-green-50 hover:bg-green-100';
                                    break;
                                case 'cancelado':
                                    $rowClass = 'bg-red-50 hover:bg-red-100';
                                    break;
                            }

                            $statusLabelClass = seller_status_label_class($statusAtual);
                            $statusBadgeClass = seller_status_badge_class($statusInfo['badge_label'] ?? null);
                        ?>
                        <tr class="<?php echo $rowClass; ?>">
                            <td class="px-3 py-2 whitespace-nowrap text-sm font-medium text-gray-800">
                                <a href="processos.php?action=view&amp;id=<?php echo $processo['id']; ?>" class="text-blue-600 hover:underline">
                                    <?php echo htmlspecialchars($processo['titulo']); ?>
                                </a>
                                <div class="mt-1 flex flex-wrap items-center gap-1 text-xs font-semibold <?php echo $statusLabelClass; ?>">
                                    <span><?php echo htmlspecialchars($statusInfo['label']); ?></span>
                                    <?php if (!empty($statusInfo['badge_label'])): ?>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-medium <?php echo $statusBadgeClass; ?>">
                                            <?php echo htmlspecialchars($statusInfo['badge_label']); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="px-3 py-2 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($processo['nome_cliente']); ?></td>
                            <td class="px-3 py-2 whitespace-nowrap text-sm text-gray-500 text-center"><?php echo $processo['total_documentos_soma']; ?></td>
                            <td class="px-3 py-2 whitespace-nowrap text-sm text-gray-500">
                                <?php
                                $servicos = array_filter(array_map('trim', explode(',', $processo['categorias_servico'] ?? '')));
                                $serviceBadgeMap = [
                                    'Tradução' => ['label' => 'Trad.', 'class' => 'bg-blue-100 text-blue-800'],
                                    'CRC' => ['label' => 'CRC', 'class' => 'bg-green-100 text-green-800'],
                                    'Apostilamento' => ['label' => 'Apost.', 'class' => 'bg-yellow-100 text-yellow-800'],
                                    'Postagem' => ['label' => 'Post.', 'class' => 'bg-purple-100 text-purple-800'],
                                    'Outros' => ['label' => 'Out.', 'class' => 'bg-gray-100 text-gray-800'],
                                ];
                                foreach ($servicos as $servico) {
                                    if(isset($serviceBadgeMap[$servico])) {
                                        $badge = $serviceBadgeMap[$servico];
                                        echo '<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full ' . $badge['class'] . ' mr-1">' . htmlspecialchars($badge['label']) . '</span>';
                                    }
                                }
                                ?>
                            </td>
                            <td class="px-3 py-2 whitespace-nowrap text-sm text-gray-500"><?php echo date('d/m/Y', strtotime($processo['data_criacao'])); ?></td>
                            <td class="px-3 py-2 whitespace-nowrap text-sm text-right font-mono"><?php echo 'R$ ' . number_format($processo['valor_total'] ?? 0, 2, ',', '.'); ?></td>
                            <td class="px-3 py-2 whitespace-nowrap text-center">
                                <a href="processos.php?action=view&amp;id=<?php echo $processo['id']; ?>" class="inline-flex items-center gap-2 text-sm font-semibold text-indigo-600 hover:text-indigo-800">
                                    <i class="fas fa-external-link-alt"></i>
                                    Ver
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <div id="load-more-container" class="text-center p-4">
        <?php if ($totalProcessesCount > count($processos)): ?>
            <button id="load-more-btn" class="bg-gray-200 hover:bg-gray-300 text-gray-800 font-bold py-2 px-6 rounded-lg">
                Carregar Mais
            </button>
        <?php endif; ?>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        // Gráfico de Funil de Vendas
        const ctxFunil = document.getElementById('funilVendasChart');
        if (ctxFunil) {
            new Chart(ctxFunil, {
                type: 'bar',
                data: {
                    labels: <?php echo $labels_funil; ?>,
                    datasets: [{
                        label: 'Leads no Funil',
                        data: <?php echo $valores_funil; ?>,
                        backgroundColor: 'rgba(59, 130, 246, 0.7)',
                        borderColor: 'rgba(59, 130, 246, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    indexAxis: 'y',
                    scales: {
                        x: { beginAtZero: true }
                    },
                    plugins: {
                        legend: { display: false }
                    }
                }
            });
        }

        const vendorSortButton = document.querySelector('[data-sort-vendor-score]');
        const vendorTableBody = document.querySelector('#vendor-leads-table tbody');
        if (vendorSortButton && vendorTableBody) {
            const vendorRows = Array.from(vendorTableBody.querySelectorAll('[data-lead-row]'));
            let vendorSortDescending = true;

            vendorSortButton.addEventListener('click', () => {
                const sortedRows = vendorRows.slice().sort((a, b) => {
                    const scoreA = parseInt(a.dataset.score ?? '0', 10);
                    const scoreB = parseInt(b.dataset.score ?? '0', 10);
                    return vendorSortDescending ? scoreB - scoreA : scoreA - scoreB;
                });

                sortedRows.forEach(row => vendorTableBody.appendChild(row));
                vendorSortDescending = !vendorSortDescending;

                const icon = vendorSortButton.querySelector('i');
                if (icon) {
                    icon.classList.toggle('fa-sort-amount-down-alt', vendorSortDescending);
                    icon.classList.toggle('fa-sort-amount-up-alt', !vendorSortDescending);
                }
            });
        }

        const loadMoreBtn = document.getElementById('load-more-btn');
        const tableBody = document.querySelector('#processos-table tbody');
        let offset = <?php echo count($processos); ?>;
        const totalProcesses = <?php echo $totalProcessesCount; ?>;

        if (loadMoreBtn && tableBody) {
            loadMoreBtn.addEventListener('click', function () {
                loadMoreBtn.textContent = 'Carregando...';
                loadMoreBtn.disabled = true;

                const currentParams = new URLSearchParams(window.location.search);
                currentParams.set('ajax', '1');
                currentParams.set('offset', offset);

                fetch(`dashboard_vendedor.php?${currentParams.toString()}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.length > 0) {
                            data.forEach(processo => {
                                const statusInfo = normalizeStatus(processo.status_processo);
                                const rowClass = resolveRowClass(statusInfo);
                                const statusLabelClass = resolveStatusLabelClass(statusInfo.normalized);
                                const badgeKey = (statusInfo.badgeLabel ?? '').toLowerCase();
                                const statusBadgeClass = badgeClassByStatus[badgeKey] ?? 'bg-indigo-100 text-indigo-800';
                                const statusBadgeHtml = statusInfo.badgeLabel
                                    ? `<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-medium ${statusBadgeClass}">${escapeHTML(statusInfo.badgeLabel)}</span>`
                                    : '';

                                const row = `
                                    <tr class="${rowClass}">
                                        <td class="px-3 py-2 whitespace-nowrap text-sm font-medium">
                                            <a href="processos.php?action=view&id=${processo.id}" class="text-blue-600 hover:underline">
                                                ${escapeHTML(processo.titulo)}
                                            </a>
                                            <div class="mt-1 flex flex-wrap items-center gap-1 text-xs font-semibold ${statusLabelClass}">
                                                <span>${escapeHTML(statusInfo.label)}</span>
                                                ${statusBadgeHtml}
                                            </div>
                                        </td>
                                        <td class="px-3 py-2 whitespace-nowrap text-sm text-gray-500">${escapeHTML(processo.nome_cliente)}</td>
                                        <td class="px-3 py-2 whitespace-nowrap text-sm text-gray-500 text-center">${processo.total_documentos_soma}</td>
                                        <td class="px-3 py-2 whitespace-nowrap text-sm text-gray-500">${formatServices(processo.categorias_servico)}</td>
                                        <td class="px-3 py-2 whitespace-nowrap text-sm text-gray-500">${formatDate(processo.data_criacao)}</td>
                                        <td class="px-3 py-2 whitespace-nowrap text-sm text-right font-mono">R$ ${formatCurrency(processo.valor_total)}</td>
                                        <td class="px-3 py-2 whitespace-nowrap text-center">
                                            <a href="processos.php?action=view&id=${processo.id}" class="inline-flex items-center gap-2 text-sm font-semibold text-indigo-600 hover:text-indigo-800">
                                                <i class="fas fa-external-link-alt"></i>
                                                Ver
                                            </a>
                                        </td>
                                    </tr>
                                `;
                                tableBody.insertAdjacentHTML('beforeend', row);
                            });

                            offset += data.length;
                            loadMoreBtn.textContent = 'Carregar Mais';
                            loadMoreBtn.disabled = false;
                        }
                        
                        if (offset >= totalProcesses) {
                            loadMoreBtn.style.display = 'none';
                        }
                    })
                    .catch(error => {
                        console.error('Erro ao carregar mais processos:', error);
                        loadMoreBtn.textContent = 'Erro ao carregar';
                    });
            });
        }

        function escapeHTML(str) {
            if (str === null || str === undefined) return '';
            return str.replace(/[&<>'"]/g, tag => ({
                '&': '&amp;', '<': '&lt;', '>': '&gt;',
                "'": '&#39;', '"': '&quot;'
            }[tag]));
        }

        function formatDate(dateString) {
            if (!dateString) return '';
            const date = new Date(dateString);
            return date.toLocaleDateString('pt-BR', { timeZone: 'UTC' });
        }

        function formatCurrency(value) {
            const number = parseFloat(value) || 0;
            return number.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        function formatServices(servicesString) {
            if (!servicesString) return '';
            const serviceMap = {
                'Tradução': { label: 'Trad.', class: 'bg-blue-100 text-blue-800' },
                'CRC': { label: 'CRC', class: 'bg-teal-100 text-teal-800' },
                'Apostilamento': { label: 'Apost.', class: 'bg-purple-100 text-purple-800' },
                'Postagem': { label: 'Post.', class: 'bg-orange-100 text-orange-800' },
                'Outros': { label: 'Out.', class: 'bg-gray-100 text-gray-800' }
            };

            return servicesString
                .split(',')
                .map(service => {
                    const trimmed = service.trim();
                    const info = serviceMap[trimmed];

                    if (!info) {
                        return '';
                    }

                    return `<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full ${info.class} mr-1">${info.label}</span>`;
                })
                .filter(Boolean)
                .join('');
        }

        const normalizeStatus = (status) => {
            const normalizedInput = (status ?? '').toString().trim().toLowerCase();
            const aliases = {
                'orcamento': 'orçamento',
                'orcamento pendente': 'orçamento pendente',
                'serviço pendente': 'serviço pendente',
                'servico pendente': 'serviço pendente',
                'pendente': 'serviço pendente',
                'aprovado': 'serviço pendente',
                'serviço em andamento': 'serviço em andamento',
                'servico em andamento': 'serviço em andamento',
                'em andamento': 'serviço em andamento',
                'aguardando pagamento': 'pendente de pagamento',
                'aguardando pagamentos': 'pendente de pagamento',
                'aguardando documento': 'pendente de documentos',
                'aguardando documentos': 'pendente de documentos',
                'aguardando documentacao': 'pendente de documentos',
                'aguardando documentação': 'pendente de documentos',
                'pendente de pagamento': 'pendente de pagamento',
                'pendente de documentos': 'pendente de documentos',
                'finalizado': 'concluído',
                'finalizada': 'concluído',
                'concluido': 'concluído',
                'concluida': 'concluído',
                'arquivado': 'cancelado',
                'arquivada': 'cancelado',
                'recusado': 'cancelado',
                'recusada': 'cancelado'
            };

            const labelMap = {
                'orçamento': 'Orçamento',
                'orçamento pendente': 'Orçamento Pendente',
                'serviço pendente': 'Serviço Pendente',
                'serviço em andamento': 'Serviço em Andamento',
                'concluído': 'Concluído',
                'cancelado': 'Cancelado',
            };

            const badgeLabels = {
                'pendente de pagamento': 'Pendente de pagamento',
                'pendente de documentos': 'Pendente de documentos',
            };

            let normalized = aliases[normalizedInput] ?? normalizedInput;
            let badgeLabel = null;

            if (badgeLabels[normalized]) {
                badgeLabel = badgeLabels[normalized];
                normalized = 'serviço em andamento';
            }

            const label = labelMap[normalized] ?? (status || 'N/A');

            return {
                normalized,
                label,
                badgeLabel,
            };
        };

        const badgeClassByStatus = {
            'pendente de pagamento': 'bg-indigo-100 text-indigo-800',
            'pendente de documentos': 'bg-violet-100 text-violet-800',
        };

        const resolveStatusLabelClass = (normalized) => {
            switch (normalized) {
                case 'orçamento':
                case 'orçamento pendente':
                    return 'text-blue-700';
                case 'serviço pendente':
                    return 'text-orange-700';
                case 'serviço em andamento':
                    return 'text-cyan-700';
                case 'concluído':
                    return 'text-purple-700';
                case 'cancelado':
                    return 'text-red-700';
                default:
                    return 'text-gray-700';
            }
        };

        function resolveRowClass(status) {
            const statusInfo = typeof status === 'object' ? status : normalizeStatus(status);
            const normalized = statusInfo.normalized;
            switch (normalized) {
                case 'orçamento':
                    return 'bg-blue-50 hover:bg-blue-100';
                case 'orçamento pendente':
                    return 'bg-yellow-50 hover:bg-yellow-100';
                case 'serviço em andamento':
                    return 'bg-indigo-50 hover:bg-indigo-100';
                case 'serviço pendente':
                    return 'bg-orange-50 hover:bg-orange-100';
                case 'concluído':
                    return 'bg-green-50 hover:bg-green-100';
                case 'cancelado':
                    return 'bg-red-50 hover:bg-red-100';
                default:
                    return 'hover:bg-gray-50';
            }
        }
    });
</script>
