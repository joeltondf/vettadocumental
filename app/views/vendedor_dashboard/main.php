<?php
// --- Configurações Iniciais ---
require_once __DIR__ . '/../../utils/DashboardProcessFormatter.php';

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

$nextLead = $nextLead ?? null;
$vendorLeads = $vendorLeads ?? [];
$gestorVendedorId = isset($_GET['vendedor_id']) ? (int) $_GET['vendedor_id'] : null;
$dashboardVendedorUrlWithVendor = $gestorVendedorId ? $dashboardVendedorUrl . '?vendedor_id=' . $gestorVendedorId : $dashboardVendedorUrl;
$listarProcessosUrl = $dashboardVendedorUrlWithVendor . ($gestorVendedorId ? '&action=listar_processos' : '?action=listar_processos');

if (!function_exists('seller_normalize_token')) {
    /**
     * Normaliza textos para uso em atributos de dados HTML.
     */
    function seller_normalize_token(string $value): string
    {
        $normalized = mb_strtolower(trim($value));
        $normalized = iconv('UTF-8', 'ASCII//TRANSLIT', $normalized) ?: $normalized;
        $normalized = preg_replace('/[^a-z0-9]+/i', '-', $normalized);
        return trim($normalized, '-');
    }
}

if (!function_exists('seller_parse_services')) {
    /**
     * Retorna uma lista única de serviços relacionados ao processo.
     */
    function seller_parse_services(array $process): array
    {
        $raw = $process['categorias_servico'] ?? ($process['tipo_servico'] ?? '');
        $items = array_filter(array_map('trim', explode(',', (string) $raw)));
        $unique = array_values(array_unique($items));

        return $unique;
    }
}

if (!function_exists('seller_priority_from_value')) {
    /**
     * Calcula prioridade de venda baseada no valor relativo do processo.
     */
    function seller_priority_from_value(float $value, float $maxValue): array
    {
        if ($maxValue <= 0) {
            return ['key' => 'baixa', 'label' => 'Baixa', 'class' => 'bg-slate-100 text-slate-700'];
        }

        $ratio = $value / $maxValue;

        if ($ratio >= 0.7) {
            return ['key' => 'alta', 'label' => 'Alta', 'class' => 'bg-rose-100 text-rose-700'];
        }

        if ($ratio >= 0.4) {
            return ['key' => 'media', 'label' => 'Média', 'class' => 'bg-amber-100 text-amber-700'];
        }

        return ['key' => 'baixa', 'label' => 'Baixa', 'class' => 'bg-emerald-100 text-emerald-700'];
    }
}

if (!function_exists('seller_deadline_meta')) {
    /**
     * Gera rótulos e chaves para urgência do processo.
     */
    function seller_deadline_meta(array $deadlineInfo): array
    {
        $state = $deadlineInfo['state'] ?? 'no_deadline';
        $days = $deadlineInfo['days'];
        $label = 'Sem prazo';

        switch ($state) {
            case 'overdue':
                $label = 'Atrasado ' . abs((int) $days) . ' dias';
                break;
            case 'due_today':
                $label = 'Entrega hoje';
                break;
            case 'due_soon':
                $label = 'Entrega em ' . (int) $days . ' dias';
                break;
            case 'on_track':
                $label = 'No prazo';
                break;
            case 'inactive':
                $label = 'Aguardando';
                break;
            case 'completed':
                $label = 'Concluído';
                break;
        }

        return [
            'key' => $state,
            'label' => $label,
            'class' => $deadlineInfo['class'] ?? 'text-gray-600',
        ];
    }
}

if (!function_exists('seller_action_bucket')) {
    /**
     * Define a categoria de ação do processo para o dashboard.
     */
    function seller_action_bucket(array $process, array $statusInfo, array $deadlineInfo): string
    {
        $normalized = $statusInfo['normalized'];
        $badgeLabel = $statusInfo['badge_label'] ?? null;

        if ($normalized === 'concluído') {
            return 'concluido';
        }

        if ($normalized === 'cancelado' || ($deadlineInfo['state'] ?? '') === 'overdue') {
            return 'risco';
        }

        if (!empty($badgeLabel)) {
            return 'aguardando';
        }

        if (in_array($normalized, ['orçamento', 'orçamento pendente', 'serviço pendente'], true)) {
            return 'agora';
        }

        if (in_array($deadlineInfo['state'] ?? '', ['due_today', 'due_soon'], true)) {
            return 'agora';
        }

        return 'aguardando';
    }
}

if (!function_exists('seller_render_action_card')) {
    /**
     * Renderiza o card compacto de processo no painel de ações.
     */
    function seller_render_action_card(array $item): void
    {
        $statusInfo = $item['statusInfo'];
        $deadlineMeta = $item['deadlineMeta'];
        $priorityInfo = $item['priorityInfo'];
        $services = $item['services'];
        $orcamentoNumero = $item['orcamento_numero'] ?: $item['id'];
        $statusLabelClass = seller_status_label_class($statusInfo['normalized']);
        $statusBadgeClass = seller_status_badge_class($statusInfo['badge_label'] ?? null);
        $processTitle = seller_get_process_title($item);
        $clienteNome = $item['nome_cliente'] ?? '—';
        $entradaData = seller_format_date_br($item['data_criacao'] ?? null);
        $valorTotal = seller_format_currency_br($item['valor_total'] ?? 0);
        $processoUrl = 'processos.php?action=view&id=' . (int) $item['id'];
        $statusToken = seller_normalize_token($statusInfo['normalized']);
        $clienteToken = seller_normalize_token($clienteNome);
        $priorityToken = seller_normalize_token($priorityInfo['key']);
        $urgencyToken = seller_normalize_token($deadlineMeta['key']);
        $bucketToken = seller_normalize_token($item['bucket']);
        ?>
        <li class="py-4" data-processo-card data-status="<?php echo $statusToken; ?>" data-urgency="<?php echo $urgencyToken; ?>" data-client="<?php echo $clienteToken; ?>" data-priority="<?php echo $priorityToken; ?>" data-bucket="<?php echo $bucketToken; ?>">
            <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                <div class="space-y-2">
                    <div class="flex flex-wrap items-center gap-2">
                        <a href="<?php echo $processoUrl; ?>" class="font-semibold text-gray-800 hover:text-blue-600">
                            <?php echo htmlspecialchars($processTitle); ?>
                        </a>
                        <span class="text-xs font-semibold <?php echo $statusLabelClass; ?>">
                            <?php echo htmlspecialchars($statusInfo['label']); ?>
                        </span>
                        <?php if (!empty($statusInfo['badge_label'])): ?>
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium <?php echo $statusBadgeClass; ?>">
                                <?php echo htmlspecialchars($statusInfo['badge_label']); ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    <p class="text-xs text-gray-500">
                        Cliente: <span class="font-medium text-gray-700"><?php echo htmlspecialchars($clienteNome); ?></span>
                        <span class="mx-1 text-gray-400">•</span>
                        Orçamento <span class="font-mono text-gray-700">#<?php echo htmlspecialchars((string) $orcamentoNumero); ?></span>
                        <span class="mx-1 text-gray-400">•</span>
                        <?php echo htmlspecialchars($statusInfo['label']); ?>
                    </p>
                    <div class="flex flex-wrap gap-2">
                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-semibold <?php echo $deadlineMeta['class']; ?>">
                            <i class="fas fa-clock"></i>
                            <?php echo htmlspecialchars($deadlineMeta['label']); ?>
                        </span>
                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-semibold <?php echo $priorityInfo['class']; ?>">
                            <i class="fas fa-flag"></i>
                            Prioridade <?php echo htmlspecialchars($priorityInfo['label']); ?>
                        </span>
                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-semibold bg-slate-100 text-slate-700">
                            <i class="fas fa-dollar-sign"></i>
                            <?php echo htmlspecialchars($valorTotal); ?>
                        </span>
                    </div>
                    <?php if (!empty($services)): ?>
                        <div class="flex flex-wrap gap-1">
                            <?php foreach ($services as $service): ?>
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium bg-blue-50 text-blue-700">
                                    <?php echo htmlspecialchars($service); ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="flex items-center gap-3 text-xs text-gray-500">
                    <span>Entrada: <?php echo htmlspecialchars($entradaData); ?></span>
                    <a href="<?php echo $processoUrl; ?>" class="text-blue-600 hover:text-blue-700 font-semibold">Abrir</a>
                </div>
            </div>
        </li>
        <?php
    }
}

$processValues = array_map(
    static fn(array $processo): float => (float) ($processo['valor_total'] ?? 0),
    $processos ?? []
);
$maxProcessValue = !empty($processValues) ? max($processValues) : 0.0;
$processesByAction = [
    'agora' => [],
    'aguardando' => [],
    'concluido' => [],
    'risco' => [],
];
$actionItems = [];
$pendenciasCriticas = 0;
$processosEmRisco = 0;

foreach ($processos ?? [] as $processo) {
    $statusInfo = seller_normalize_status_info($processo['status_processo'] ?? '');
    $deadlineInfo = DashboardProcessFormatter::buildDeadlineDescriptor($processo);
    $deadlineMeta = seller_deadline_meta($deadlineInfo);
    $bucket = seller_action_bucket($processo, $statusInfo, $deadlineInfo);
    $priorityInfo = seller_priority_from_value((float) ($processo['valor_total'] ?? 0), $maxProcessValue);
    $services = seller_parse_services($processo);

    $item = $processo;
    $item['statusInfo'] = $statusInfo;
    $item['deadlineMeta'] = $deadlineMeta;
    $item['priorityInfo'] = $priorityInfo;
    $item['services'] = $services;
    $item['bucket'] = $bucket;

    $actionItems[] = $item;
    $processesByAction[$bucket][] = $item;

    $isUrgent = in_array($deadlineInfo['state'] ?? '', ['overdue', 'due_today'], true);
    $isBudgetFollowup = in_array($statusInfo['normalized'], ['orçamento', 'orçamento pendente'], true);
    if ($bucket === 'agora' && ($isUrgent || $isBudgetFollowup)) {
        $pendenciasCriticas++;
    }
}

$processosEmRisco = count($processesByAction['risco']);
$processosResolvidos = count($processesByAction['concluido']);
$processosAgora = count($processesByAction['agora']);
$processosAguardando = count($processesByAction['aguardando']);

$statusOptions = [];
$clienteOptions = [];
foreach ($actionItems as $item) {
    $normalizedStatus = $item['statusInfo']['normalized'] ?? '';
    if ($normalizedStatus !== '' && !isset($statusOptions[$normalizedStatus])) {
        $statusOptions[$normalizedStatus] = $item['statusInfo']['label'] ?? $normalizedStatus;
    }

    $clienteNome = trim((string) ($item['nome_cliente'] ?? ''));
    if ($clienteNome !== '' && !isset($clienteOptions[$clienteNome])) {
        $clienteOptions[$clienteNome] = $clienteNome;
    }
}
asort($statusOptions);
asort($clienteOptions);

$prioridadesLeads = $vendorLeads;
usort($prioridadesLeads, static function (array $a, array $b): int {
    return (int) ($b['qualification_score'] ?? 0) <=> (int) ($a['qualification_score'] ?? 0);
});
$prioridadesLeads = array_slice($prioridadesLeads, 0, 5);
?>
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
  <a href="#acoes-agora" class="text-blue-600 hover:underline">Ações agora</a>
  <a href="#aguardando-resposta" class="text-blue-600 hover:underline">Aguardando resposta</a>
  <a href="#risco-problema" class="text-blue-600 hover:underline">Risco / problema</a>
  <a href="#concluido-resolvido" class="text-blue-600 hover:underline">Concluído</a>
  <a href="#prioridades-vendas" class="text-blue-600 hover:underline">Prioridades de vendas</a>
  <a href="#relatorios-acao" class="text-blue-600 hover:underline">Relatórios</a>
  <a href="<?php echo $listarProcessosUrl; ?>" class="text-blue-600 hover:underline">Lista completa de processos</a>
  <?php if ($currentUserPerfil !== 'cliente'): ?>
    <a href="https://vettadocumental.com/interno/" target="_blank" rel="noopener noreferrer" class="text-blue-600 hover:underline">Metas</a>
  <?php endif; ?>
</nav>

<section class="mb-8" id="painel-acoes">
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4 mb-4">
        <div>
            <h2 class="text-lg font-semibold text-gray-700">Painel de ações do vendedor</h2>
            <p class="text-sm text-gray-600">Foque no que exige decisão imediata, no que está aguardando resposta e no que precisa de atenção.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <button type="button" class="inline-flex items-center gap-2 px-3 py-2 rounded-lg text-sm font-semibold bg-red-50 text-red-700 hover:bg-red-100" data-quick-filter="urgent">
                <i class="fas fa-fire"></i> Processos mais urgentes
            </button>
            <button type="button" class="inline-flex items-center gap-2 px-3 py-2 rounded-lg text-sm font-semibold bg-blue-50 text-blue-700 hover:bg-blue-100" data-quick-filter="budget">
                <i class="fas fa-file-signature"></i> Follow-up de orçamento
            </button>
            <button type="button" class="inline-flex items-center gap-2 px-3 py-2 rounded-lg text-sm font-semibold bg-amber-50 text-amber-700 hover:bg-amber-100" data-quick-filter="risk">
                <i class="fas fa-triangle-exclamation"></i> Clientes em risco
            </button>
            <button type="button" class="inline-flex items-center gap-2 px-3 py-2 rounded-lg text-sm font-semibold bg-gray-100 text-gray-700 hover:bg-gray-200" data-clear-filters>
                <i class="fas fa-broom"></i> Limpar filtros
            </button>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
        <div class="bg-white p-4 rounded-lg shadow-md border border-gray-200">
            <p class="text-xs text-gray-500">O que preciso fazer agora</p>
            <p class="text-2xl font-bold text-gray-800"><?php echo $processosAgora; ?></p>
        </div>
        <div class="bg-white p-4 rounded-lg shadow-md border border-gray-200">
            <p class="text-xs text-gray-500">Aguardando resposta</p>
            <p class="text-2xl font-bold text-gray-800"><?php echo $processosAguardando; ?></p>
        </div>
        <div class="bg-white p-4 rounded-lg shadow-md border border-gray-200">
            <p class="text-xs text-gray-500">Risco / problema</p>
            <p class="text-2xl font-bold text-gray-800"><?php echo $processosEmRisco; ?></p>
        </div>
        <div class="bg-white p-4 rounded-lg shadow-md border border-gray-200">
            <p class="text-xs text-gray-500">Concluído / resolvido</p>
            <p class="text-2xl font-bold text-gray-800"><?php echo $processosResolvidos; ?></p>
        </div>
    </div>

    <div class="mt-4 bg-white p-4 rounded-lg shadow-md border border-gray-200">
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4 text-sm">
            <div>
                <label class="block text-xs font-semibold text-gray-500 mb-1" for="filter-status">Status</label>
                <select id="filter-status" class="w-full border-gray-200 rounded-lg">
                    <option value="">Todos</option>
                    <?php foreach ($statusOptions as $statusKey => $statusLabel): ?>
                        <option value="<?php echo htmlspecialchars(seller_normalize_token($statusKey)); ?>"><?php echo htmlspecialchars($statusLabel); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-500 mb-1" for="filter-urgency">Urgência</label>
                <select id="filter-urgency" class="w-full border-gray-200 rounded-lg">
                    <option value="">Todas</option>
                    <option value="overdue">Atrasado</option>
                    <option value="due-today">Entrega hoje</option>
                    <option value="due-soon">Entrega em breve</option>
                    <option value="on-track">No prazo</option>
                    <option value="inactive">Aguardando</option>
                    <option value="no-deadline">Sem prazo</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-500 mb-1" for="filter-client">Cliente</label>
                <select id="filter-client" class="w-full border-gray-200 rounded-lg">
                    <option value="">Todos</option>
                    <?php foreach ($clienteOptions as $clienteNome): ?>
                        <option value="<?php echo htmlspecialchars(seller_normalize_token($clienteNome)); ?>"><?php echo htmlspecialchars($clienteNome); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-500 mb-1" for="filter-priority">Prioridade</label>
                <select id="filter-priority" class="w-full border-gray-200 rounded-lg">
                    <option value="">Todas</option>
                    <option value="alta">Alta</option>
                    <option value="media">Média</option>
                    <option value="baixa">Baixa</option>
                </select>
            </div>
        </div>
    </div>
</section>

<section class="grid grid-cols-1 xl:grid-cols-2 gap-6 mb-8">
    <div class="bg-white shadow-md rounded-lg border border-gray-200" id="acoes-agora">
        <div class="px-6 py-4 border-b">
            <h3 class="text-lg font-semibold text-gray-800">O que preciso fazer agora</h3>
            <p class="text-xs text-gray-500">Pendências, orçamentos para follow-up e urgências do dia.</p>
        </div>
        <div class="p-4">
            <ul class="divide-y divide-gray-200" data-action-list>
                <?php if (empty($processesByAction['agora'])): ?>
                    <li class="py-4 text-sm text-gray-500" data-empty-state>Nenhuma ação imediata pendente.</li>
                <?php else: ?>
                    <?php foreach ($processesByAction['agora'] as $item): ?>
                        <?php seller_render_action_card($item); ?>
                    <?php endforeach; ?>
                    <li class="py-4 text-sm text-gray-500 hidden" data-empty-state>Nenhuma ação imediata com os filtros atuais.</li>
                <?php endif; ?>
            </ul>
        </div>
    </div>

    <div class="bg-white shadow-md rounded-lg border border-gray-200" id="aguardando-resposta">
        <div class="px-6 py-4 border-b">
            <h3 class="text-lg font-semibold text-gray-800">Aguardando resposta</h3>
            <p class="text-xs text-gray-500">Itens que dependem do cliente ou de outras equipes.</p>
        </div>
        <div class="p-4">
            <ul class="divide-y divide-gray-200" data-action-list>
                <?php if (empty($processesByAction['aguardando'])): ?>
                    <li class="py-4 text-sm text-gray-500" data-empty-state>Nenhuma solicitação aguardando resposta.</li>
                <?php else: ?>
                    <?php foreach ($processesByAction['aguardando'] as $item): ?>
                        <?php seller_render_action_card($item); ?>
                    <?php endforeach; ?>
                    <li class="py-4 text-sm text-gray-500 hidden" data-empty-state>Nenhuma solicitação aguardando resposta com os filtros atuais.</li>
                <?php endif; ?>
            </ul>
        </div>
    </div>

    <div class="bg-white shadow-md rounded-lg border border-gray-200" id="risco-problema">
        <div class="px-6 py-4 border-b">
            <h3 class="text-lg font-semibold text-gray-800">Risco / problema</h3>
            <p class="text-xs text-gray-500">Processos com atrasos, cancelamentos ou bloqueios.</p>
        </div>
        <div class="p-4">
            <ul class="divide-y divide-gray-200" data-action-list>
                <?php if (empty($processesByAction['risco'])): ?>
                    <li class="py-4 text-sm text-gray-500" data-empty-state>Nenhum processo em risco no momento.</li>
                <?php else: ?>
                    <?php foreach ($processesByAction['risco'] as $item): ?>
                        <?php seller_render_action_card($item); ?>
                    <?php endforeach; ?>
                    <li class="py-4 text-sm text-gray-500 hidden" data-empty-state>Nenhum processo em risco com os filtros atuais.</li>
                <?php endif; ?>
            </ul>
        </div>
    </div>

    <div class="bg-white shadow-md rounded-lg border border-gray-200" id="concluido-resolvido">
        <div class="px-6 py-4 border-b">
            <h3 class="text-lg font-semibold text-gray-800">Concluído / resolvido</h3>
            <p class="text-xs text-gray-500">Processos finalizados e orçamentos aprovados.</p>
        </div>
        <div class="p-4">
            <ul class="divide-y divide-gray-200" data-action-list>
                <?php if (empty($processesByAction['concluido'])): ?>
                    <li class="py-4 text-sm text-gray-500" data-empty-state>Nenhum processo concluído recentemente.</li>
                <?php else: ?>
                    <?php foreach ($processesByAction['concluido'] as $item): ?>
                        <?php seller_render_action_card($item); ?>
                    <?php endforeach; ?>
                    <li class="py-4 text-sm text-gray-500 hidden" data-empty-state>Nenhum processo concluído com os filtros atuais.</li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</section>

<section class="mb-8" id="prioridades-vendas">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3 mb-3">
        <h2 class="text-lg font-semibold text-gray-700">Prioridades de vendas</h2>
        <?php if (!empty($vendorLeads)): ?>
            <button type="button" class="inline-flex items-center gap-2 text-sm text-gray-600 hover:text-gray-800" data-sort-vendor-score>
                Ordenar por pontuação
                <i class="fas fa-sort-amount-down-alt text-xs"></i>
            </button>
        <?php endif; ?>
    </div>
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="bg-white p-5 rounded-lg shadow-md border border-gray-200 lg:col-span-1">
            <h3 class="text-sm font-semibold text-gray-600 mb-3">Próximo lead</h3>
            <?php if ($nextLead): ?>
                <div class="space-y-3">
                    <div>
                        <p class="text-xs text-gray-500">Lead</p>
                        <h4 class="text-lg font-bold text-gray-800"><?php echo htmlspecialchars($nextLead['nome_prospecto'] ?? 'Lead'); ?></h4>
                        <p class="text-xs text-gray-500 mt-1">Cliente: <?php echo htmlspecialchars($nextLead['nome_cliente'] ?? 'Não informado'); ?></p>
                        <p class="text-xs text-gray-400 mt-1">Distribuído em <?php echo !empty($nextLead['distributed_at']) ? date('d/m/Y H:i', strtotime($nextLead['distributed_at'])) : '--'; ?></p>
                        <div class="mt-2 inline-flex items-center gap-2 bg-indigo-50 text-indigo-700 px-3 py-1 rounded-full text-xs font-semibold">
                            Pontuação: <?php echo (int) ($nextLead['qualification_score'] ?? 0); ?>
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <a href="<?php echo $baseAppUrl; ?>/crm/prospeccoes/detalhes.php?id=<?php echo (int) ($nextLead['id'] ?? 0); ?>" class="px-4 py-2 bg-blue-600 text-white rounded-lg shadow hover:bg-blue-700 transition-colors text-sm">Abrir lead</a>
                        <a href="<?php echo $baseAppUrl; ?>/qualificacao.php?action=create&amp;id=<?php echo (int) ($nextLead['id'] ?? 0); ?>" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors text-sm">Registrar avanço</a>
                    </div>
                </div>
            <?php else: ?>
                <p class="text-sm text-gray-500">Nenhum lead pendente para atendimento imediato.</p>
            <?php endif; ?>
        </div>
        <div class="bg-white p-5 rounded-lg shadow-md border border-gray-200 lg:col-span-2">
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
</section>

<section class="mb-8" id="relatorios-acao">
    <h2 class="text-lg font-semibold text-gray-700 mb-3">Relatórios centrados em ação</h2>
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
        <div class="bg-white p-4 rounded-lg shadow-md border border-gray-200">
            <p class="text-xs text-gray-500">Pendências críticas</p>
            <p class="text-2xl font-bold text-gray-800"><?php echo $pendenciasCriticas; ?></p>
            <p class="text-xs text-gray-400 mt-1">Itens que precisam de ação imediata.</p>
        </div>
        <div class="bg-white p-4 rounded-lg shadow-md border border-gray-200">
            <p class="text-xs text-gray-500">Processos em risco</p>
            <p class="text-2xl font-bold text-gray-800"><?php echo $processosEmRisco; ?></p>
            <p class="text-xs text-gray-400 mt-1">Atrasados ou cancelados.</p>
        </div>
        <div class="bg-white p-4 rounded-lg shadow-md border border-gray-200">
            <p class="text-xs text-gray-500">O que foi resolvido</p>
            <p class="text-2xl font-bold text-gray-800"><?php echo $processosResolvidos; ?></p>
            <p class="text-xs text-gray-400 mt-1">Processos concluídos.</p>
        </div>
        <div class="bg-white p-4 rounded-lg shadow-md border border-gray-200">
            <p class="text-xs text-gray-500">Prioridades de vendas</p>
            <p class="text-2xl font-bold text-gray-800"><?php echo count($prioridadesLeads); ?></p>
            <p class="text-xs text-gray-400 mt-1">Leads com maior potencial.</p>
        </div>
    </div>
</section>

<script>
    document.addEventListener('DOMContentLoaded', function () {
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

        const actionCards = Array.from(document.querySelectorAll('[data-processo-card]'));
        const filterStatus = document.getElementById('filter-status');
        const filterUrgency = document.getElementById('filter-urgency');
        const filterClient = document.getElementById('filter-client');
        const filterPriority = document.getElementById('filter-priority');
        const quickButtons = document.querySelectorAll('[data-quick-filter]');
        const clearButton = document.querySelector('[data-clear-filters]');
        let quickFilter = '';

        const toggleEmptyStates = () => {
            document.querySelectorAll('[data-action-list]').forEach((list) => {
                const visibleItems = list.querySelectorAll('[data-processo-card]:not(.hidden)');
                const emptyState = list.querySelector('[data-empty-state]');
                if (emptyState) {
                    emptyState.classList.toggle('hidden', visibleItems.length > 0);
                }
            });
        };

        const applyFilters = () => {
            const statusValue = filterStatus?.value ?? '';
            const urgencyValue = filterUrgency?.value ?? '';
            const clientValue = filterClient?.value ?? '';
            const priorityValue = filterPriority?.value ?? '';

            actionCards.forEach((card) => {
                const matchStatus = !statusValue || card.dataset.status === statusValue;
                const matchUrgency = !urgencyValue || card.dataset.urgency === urgencyValue;
                const matchClient = !clientValue || card.dataset.client === clientValue;
                const matchPriority = !priorityValue || card.dataset.priority === priorityValue;
                let matchQuick = true;

                if (quickFilter === 'urgent') {
                    matchQuick = ['overdue', 'due-today'].includes(card.dataset.urgency);
                } else if (quickFilter === 'budget') {
                    matchQuick = ['orcamento', 'orcamento-pendente'].includes(card.dataset.status);
                } else if (quickFilter === 'risk') {
                    matchQuick = card.dataset.bucket === 'risco';
                }

                card.classList.toggle('hidden', !(matchStatus && matchUrgency && matchClient && matchPriority && matchQuick));
            });

            toggleEmptyStates();
        };

        [filterStatus, filterUrgency, filterClient, filterPriority].forEach((select) => {
            if (!select) {
                return;
            }
            select.addEventListener('change', applyFilters);
        });

        quickButtons.forEach((button) => {
            button.addEventListener('click', () => {
                const selected = button.dataset.quickFilter ?? '';
                quickFilter = quickFilter === selected ? '' : selected;
                quickButtons.forEach((btn) => {
                    const isActive = btn.dataset.quickFilter === quickFilter && quickFilter !== '';
                    btn.classList.toggle('ring-2', isActive);
                    btn.classList.toggle('ring-blue-200', isActive);
                });
                applyFilters();
            });
        });

        if (clearButton) {
            clearButton.addEventListener('click', () => {
                if (filterStatus) filterStatus.value = '';
                if (filterUrgency) filterUrgency.value = '';
                if (filterClient) filterClient.value = '';
                if (filterPriority) filterPriority.value = '';
                quickFilter = '';
                quickButtons.forEach((btn) => {
                    btn.classList.remove('ring-2', 'ring-blue-200');
                });
                applyFilters();
            });
        }

        applyFilters();
    });
</script>
