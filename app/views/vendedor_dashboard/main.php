<?php
// --- Configurações Iniciais ---
require_once __DIR__ . '/../../utils/DashboardProcessFormatter.php';

$activeFilters = array_filter($filters ?? []);
$hasFilters = !empty($activeFilters);
$initialProcessLimit = 5;
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
$gestorVendedorId = isset($_GET['vendedor_id']) ? (int) $_GET['vendedor_id'] : null;
$dashboardVendedorUrlWithVendor = $gestorVendedorId ? $dashboardVendedorUrl . '?vendedor_id=' . $gestorVendedorId : $dashboardVendedorUrl;
$listarOrcamentosUrl = $dashboardVendedorUrlWithVendor . ($gestorVendedorId ? '&action=listar_orcamentos' : '?action=listar_orcamentos');
$listarServicosUrl = $dashboardVendedorUrlWithVendor . ($gestorVendedorId ? '&action=listar_servicos' : '?action=listar_servicos');
$listarProcessosUrl = $dashboardVendedorUrlWithVendor . ($gestorVendedorId ? '&action=listar_processos' : '?action=listar_processos');
$orcamentosResumo = array_slice($orcamentosMesAtual ?? [], 0, 5);
$servicosResumo = array_slice($servicosMesAtual ?? [], 0, 5);
$servicosAtivosResumo = array_slice($servicosAtivosMesAnterior ?? [], 0, 5);
$processosResumo = array_slice($processos ?? [], 0, $initialProcessLimit);
$orcamentosPossuemMais = count($orcamentosMesAtual ?? []) > 5;
$servicosPossuemMais = count($servicosMesAtual ?? []) > 5;
$servicosAtivosPossuemMais = count($servicosAtivosMesAnterior ?? []) > 5;
$processosPossuemMais = count($processos ?? []) > $initialProcessLimit;
?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between mb-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Meu Painel de Vendas</h1>
        <p class="mt-1 text-gray-600">Bem-vindo(a), <?php echo htmlspecialchars($_SESSION['user_nome'] ?? ''); ?>! Acompanhe sua performance e atividades.</p>
    </div>
    <div class="flex flex-wrap gap-3">
        <a href="<?php echo $baseAppUrl; ?>/processos.php?action=create&amp;return_to=<?php echo urlencode($dashboardVendedorUrlWithVendor); ?>" class="inline-flex items-center bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2.5 px-4 rounded-lg shadow-md transition-colors">
            <i class="fas fa-file-signature mr-2"></i> Criar Orçamento
        </a>
        <?php if ($currentUserPerfil === 'vendedor'): ?>
            <a href="<?php echo $baseAppUrl; ?>/clientes.php?action=create&amp;return_to=<?php echo urlencode($dashboardVendedorUrlWithVendor); ?>" class="inline-flex items-center bg-emerald-600 hover:bg-emerald-700 text-white font-semibold py-2.5 px-4 rounded-lg shadow-md transition-colors">
                <i class="fas fa-user-plus mr-2"></i> Novo Cliente
            </a>
        <?php endif; ?>
        <a href="<?php echo $baseAppUrl; ?>/crm/prospeccoes/lista.php" class="inline-flex items-center bg-green-600 hover:bg-green-700 text-white font-semibold py-2.5 px-4 rounded-lg shadow-md transition-colors">
            <i class="fas fa-bullseye mr-2"></i> Ver Prospecções
        </a>
    </div>
</div>

<nav class="flex flex-wrap gap-4 text-sm mb-6">
  <a href="#meu-proximo-lead" class="text-blue-600 hover:underline">Próximo Lead</a>
  <a href="#leads-em-acompanhamento" class="text-blue-600 hover:underline">Leads</a>
  <a href="#orcamentos-resumo" class="text-blue-600 hover:underline">Orçamentos</a>
  <a href="#servicos-resumo" class="text-blue-600 hover:underline">Serviços</a>
  <a href="#processos-resumo" class="text-blue-600 hover:underline">Processos</a>
</nav>

<div class="mb-8" id="meu-proximo-lead">
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

<div class="mb-8" id="leads-em-acompanhamento">
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

<div class="space-y-6 mb-8" id="orcamentos-resumo">
    <div class="bg-white shadow-md rounded-lg border border-gray-200">
        <div class="px-6 py-4 border-b">
            <h3 class="text-lg font-semibold text-gray-800">Orçamentos - Mês atual</h3>
        </div>
        <div class="p-4">
            <?php if (empty($orcamentosResumo)): ?>
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
                            <?php foreach ($orcamentosResumo as $orcamento): ?>
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
                <?php if ($orcamentosPossuemMais): ?>
                    <div class="mt-4 text-right">
                        <a href="<?php echo $listarOrcamentosUrl; ?>" class="text-blue-600 hover:underline font-semibold">Ver todos os orçamentos</a>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="bg-white shadow-md rounded-lg border border-gray-200" id="servicos-resumo">
        <div class="px-6 py-4 border-b">
            <h3 class="text-lg font-semibold text-gray-800">Serviços - Mês atual</h3>
        </div>
        <div class="p-4">
            <?php if (empty($servicosResumo)): ?>
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
                            <?php foreach ($servicosResumo as $servico): ?>
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
                <?php if ($servicosPossuemMais): ?>
                    <div class="mt-4 text-right">
                        <a href="<?php echo $listarServicosUrl; ?>" class="text-blue-600 hover:underline font-semibold">Ver todos os serviços</a>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="bg-white shadow-md rounded-lg border border-gray-200">
        <div class="px-6 py-4 border-b">
            <h3 class="text-lg font-semibold text-gray-800">Serviços em andamento - Iniciados no mês anterior</h3>
        </div>
        <div class="p-4">
            <?php if (empty($servicosAtivosResumo)): ?>
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
                            <?php foreach ($servicosAtivosResumo as $servicoAnterior): ?>
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
                <?php if ($servicosAtivosPossuemMais): ?>
                    <div class="mt-4 text-right">
                        <a href="<?php echo $listarServicosUrl; ?>" class="text-blue-600 hover:underline font-semibold">Ver todos os serviços</a>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="w-full bg-white p-5 rounded-lg shadow-xl border border-gray-200 mb-6" id="processos-resumo">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
        <div>
            <h4 class="text-xl font-bold text-gray-800 mb-1">Processos (resumo)</h4>
            <p class="text-sm text-gray-600">Visualize rapidamente os processos mais recentes. A listagem completa está disponível na página dedicada.</p>
        </div>
        <div class="flex items-center gap-3">
            <button type="button" id="toggle-processos-resumo" class="inline-flex items-center bg-gray-100 hover:bg-gray-200 text-gray-800 font-semibold py-2 px-4 rounded-lg shadow-sm transition-colors">
                <i class="fas fa-chevron-down mr-2"></i> Mostrar resumo
            </button>
            <a href="<?php echo $listarProcessosUrl; ?>" class="inline-flex items-center bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded-lg shadow-md transition-colors">
                <i class="fas fa-list mr-2"></i> Ver todos
            </a>
        </div>
    </div>

    <div id="processos-resumo-content" class="mt-5 hidden">
        <?php if (empty($processosResumo)): ?>
            <p class="text-sm text-gray-500 text-center py-6">Nenhum processo recente encontrado.</p>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-3 py-2 text-left font-medium text-gray-500 uppercase tracking-wider">Família</th>
                            <th class="px-3 py-2 text-left font-medium text-gray-500 uppercase tracking-wider">Cliente</th>
                            <th class="px-3 py-2 text-left font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-3 py-2 text-left font-medium text-gray-500 uppercase tracking-wider">Entrada</th>
                            <th class="px-3 py-2 text-right font-medium text-gray-500 uppercase tracking-wider">Valor total</th>
                            <th class="px-3 py-2 text-center font-medium text-gray-500 uppercase tracking-wider">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($processosResumo as $processo): ?>
                            <?php
                                $statusInfo = seller_normalize_status_info($processo['status_processo'] ?? '');
                                $statusLabelClass = seller_status_label_class($statusInfo['normalized']);
                                $statusBadgeClass = seller_status_badge_class($statusInfo['badge_label'] ?? null);
                            ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-3 py-2 whitespace-nowrap text-gray-800 font-semibold"><?php echo htmlspecialchars($processo['titulo'] ?? '--'); ?></td>
                                <td class="px-3 py-2 whitespace-nowrap text-gray-600"><?php echo htmlspecialchars($processo['nome_cliente'] ?? '--'); ?></td>
                                <td class="px-3 py-2 whitespace-nowrap">
                                    <div class="flex flex-wrap items-center gap-1 text-xs font-semibold <?php echo $statusLabelClass; ?>">
                                        <span><?php echo htmlspecialchars($statusInfo['label']); ?></span>
                                        <?php if (!empty($statusInfo['badge_label'])): ?>
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-medium <?php echo $statusBadgeClass; ?>"><?php echo htmlspecialchars($statusInfo['badge_label']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="px-3 py-2 whitespace-nowrap text-gray-600"><?php echo seller_format_date_br($processo['data_criacao'] ?? null); ?></td>
                                <td class="px-3 py-2 whitespace-nowrap text-right text-gray-700 font-semibold"><?php echo seller_format_currency_br($processo['valor_total'] ?? 0); ?></td>
                                <td class="px-3 py-2 whitespace-nowrap text-center">
                                    <a href="processos.php?action=view&amp;id=<?php echo (int) $processo['id']; ?>" class="inline-flex items-center gap-2 text-sm font-semibold text-indigo-600 hover:text-indigo-800">
                                        <i class="fas fa-external-link-alt"></i>
                                        Ver
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($processosPossuemMais): ?>
                <div class="mt-4 text-right">
                    <a href="<?php echo $listarProcessosUrl; ?>" class="text-blue-600 hover:underline font-semibold">Ver todos os <?php echo (int) $totalProcessesCount; ?> processos</a>
                </div>
            <?php endif; ?>
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

        const toggleResumoBtn = document.getElementById('toggle-processos-resumo');
        const resumoContent = document.getElementById('processos-resumo-content');

        if (toggleResumoBtn && resumoContent) {
            let expanded = false;

            toggleResumoBtn.addEventListener('click', () => {
                expanded = !expanded;
                resumoContent.classList.toggle('hidden', !expanded);

                const icon = toggleResumoBtn.querySelector('i');
                if (icon) {
                    icon.classList.toggle('fa-chevron-down', !expanded);
                    icon.classList.toggle('fa-chevron-up', expanded);
                    toggleResumoBtn.textContent = expanded ? ' Ocultar resumo' : ' Mostrar resumo';
                    toggleResumoBtn.prepend(icon);
                } else {
                    toggleResumoBtn.textContent = expanded ? 'Ocultar resumo' : 'Mostrar resumo';
                }
            });
        }
    });
</script>
