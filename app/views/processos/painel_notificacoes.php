<?php
$groups = $alertFeed['groups'] ?? [];
if (empty($groups) && !empty($alertFeed['notifications'] ?? [])) {
    $groups = array_map(static function (array $notification): array {
        return [
            'referencia_id' => $notification['referencia_id'] ?? null,
            'processo_titulo' => $notification['processo_titulo'] ?? null,
            'cliente_id' => $notification['cliente_id'] ?? null,
            'cliente_nome' => $notification['nome_cliente'] ?? null,
            'status_processo' => $notification['status_processo'] ?? null,
            'prioridade' => $notification['prioridade'] ?? 'media',
            'notifications' => [$notification],
        ];
    }, $alertFeed['notifications']);
}

$totalAlerts = (int)($alertFeed['total'] ?? 0);
$isManager = ($isManager ?? false) || in_array($_SESSION['user_perfil'] ?? '', ['admin', 'gerencia', 'supervisor'], true);
$filterOptions = $filterOptions ?? ['tipos' => [], 'prioridades' => [], 'status' => [], 'clientes' => [], 'usuarios' => []];
$appliedFilters = $appliedFilters ?? [
    'status' => 'aberto',
    'tipo' => [],
    'prioridade' => [],
    'status_processo' => [],
    'cliente_id' => '',
    'periodo_inicio' => '',
    'periodo_fim' => '',
    'usuario' => '',
];
$hasAdvancedFilters = !empty($appliedFilters['tipo'])
    || !empty($appliedFilters['prioridade'])
    || !empty($appliedFilters['status_processo'])
    || (!empty($appliedFilters['usuario']) && $appliedFilters['usuario'] !== '');

if (!function_exists('notification_type_metadata')) {
    function notification_type_metadata(?string $alertType): array
    {
        $alertType = strtolower(trim((string)$alertType));
        $baseMap = [
            'processo_pendente_orcamento' => ['label' => 'Orçamento pendente', 'classes' => 'bg-indigo-100 text-indigo-700 border border-indigo-200'],
            'processo_pendente_servico' => ['label' => 'Serviço pendente', 'classes' => 'bg-blue-100 text-blue-700 border border-blue-200'],
            'processo_servico_pendente' => ['label' => 'Serviço aguardando execução', 'classes' => 'bg-blue-100 text-blue-700 border border-blue-200'],
            'processo_orcamento_recusado' => ['label' => 'Orçamento recusado', 'classes' => 'bg-red-100 text-red-700 border border-red-200'],
            'processo_orcamento_enviado' => ['label' => 'Orçamento enviado', 'classes' => 'bg-cyan-100 text-cyan-700 border border-cyan-200'],
            'processo_servico_aprovado' => ['label' => 'Serviço aprovado', 'classes' => 'bg-emerald-100 text-emerald-700 border border-emerald-200'],
            'processo_orcamento_aprovado' => ['label' => 'Orçamento aprovado', 'classes' => 'bg-emerald-100 text-emerald-700 border border-emerald-200'],
            'processo_cancelado' => ['label' => 'Processo cancelado', 'classes' => 'bg-slate-200 text-slate-700 border border-slate-300'],
            'processo_orcamento_cancelado' => ['label' => 'Orçamento cancelado', 'classes' => 'bg-slate-200 text-slate-700 border border-slate-300'],
            'prospeccao_exclusao' => ['label' => 'Solicitação de exclusão', 'classes' => 'bg-rose-100 text-rose-700 border border-rose-200'],
            'prospeccao_generica' => ['label' => 'Prospecção', 'classes' => 'bg-fuchsia-100 text-fuchsia-700 border border-fuchsia-200'],
            'processo_generico' => ['label' => 'Processo', 'classes' => 'bg-amber-100 text-amber-700 border border-amber-200'],
            'notificacao_generica' => ['label' => 'Alerta', 'classes' => 'bg-amber-100 text-amber-700 border border-amber-200'],
        ];

        if (isset($baseMap[$alertType])) {
            return $baseMap[$alertType];
        }

        $label = ucfirst(str_replace('_', ' ', $alertType ?: 'alerta'));

        return [
            'label' => $label,
            'classes' => 'bg-slate-100 text-slate-700 border border-slate-200',
        ];
    }
}

if (!function_exists('notification_type_label')) {
    function notification_type_label(string $alertType): string
    {
        $metadata = notification_type_metadata($alertType);

        return $metadata['label'] ?? ucfirst(str_replace('_', ' ', $alertType));
    }
}

if (!function_exists('format_notification_group_label')) {
    function format_notification_group_label(string $group): string
    {
        return $group === 'vendedor' ? 'vendedores' : 'gestão';
    }
}

if (!function_exists('notification_priority_metadata')) {
    function notification_priority_metadata(?string $priority): array
    {
        $priority = strtolower(trim((string)$priority));
        $map = [
            'alta' => ['label' => 'Alta', 'classes' => 'bg-red-100 text-red-700 border border-red-200'],
            'media' => ['label' => 'Média', 'classes' => 'bg-yellow-100 text-yellow-700 border border-yellow-200'],
            'baixa' => ['label' => 'Baixa', 'classes' => 'bg-green-100 text-green-700 border border-green-200'],
        ];

        return $map[$priority] ?? $map['media'];
    }
}
?>

<div class="container mx-auto px-4 sm:px-6 lg:px-8 py-8 bg-gray-50">
    <div class="mb-6 border-b pb-4">
        <h1 class="text-2xl font-bold text-gray-800">Painel de Notificações</h1>
        <p class="mt-1 text-sm text-gray-500">
            Alertas ativos direcionados ao grupo de <?php echo htmlspecialchars(format_notification_group_label($grupoDestino ?? 'gerencia')); ?>.
        </p>
    </div>

    <form method="GET" action="<?php echo APP_URL; ?>/notificacoes.php" class="bg-white shadow rounded-lg p-6 space-y-6">
        <div>
            <div class="flex items-center justify-between gap-3">
                <h2 class="text-sm font-semibold text-gray-700 uppercase tracking-wide">Filtros principais</h2>
                <button
                    type="button"
                    class="inline-flex items-center gap-2 text-sm font-medium text-indigo-600 hover:text-indigo-700 focus:outline-none"
                    data-toggle-advanced
                    aria-expanded="<?php echo $hasAdvancedFilters ? 'true' : 'false'; ?>"
                    aria-controls="advanced-filters"
                >
                    <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M3 5a1 1 0 011-1h12a1 1 0 01.8 1.6l-4.2 5.6V16a1 1 0 01-1.447.894l-3-1.5A1 1 0 017 14.5v-3.3L2.2 5.6A1 1 0 013 5z" clip-rule="evenodd" />
                    </svg>
                    <span data-toggle-label><?php echo $hasAdvancedFilters ? 'Ocultar filtros avançados' : 'Mostrar filtros avançados'; ?></span>
                </button>
            </div>

            <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
                <div>
                    <label for="status" class="block text-sm font-medium text-gray-700">Status</label>
                    <select id="status" name="status" class="mt-1 block w-full rounded-md border-gray-200 bg-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                        <?php
                            $statusOptions = [
                                'aberto' => 'Abertas',
                                'nao_lido' => 'Não lidas',
                                'lido' => 'Lidas',
                                'resolvido' => 'Resolvidas',
                                'todos' => 'Todas',
                            ];
                            $currentStatus = $appliedFilters['status'] ?? 'aberto';
                            foreach ($statusOptions as $value => $label):
                        ?>
                            <option value="<?php echo htmlspecialchars($value); ?>" <?php echo $currentStatus === $value ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label for="cliente_id" class="block text-sm font-medium text-gray-700">Cliente</label>
                    <select id="cliente_id" name="cliente_id" class="mt-1 block w-full rounded-md border-gray-200 bg-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                        <option value="">Todos</option>
                        <?php foreach ($filterOptions['clientes'] as $cliente): ?>
                            <option value="<?php echo (int)$cliente['id']; ?>" <?php echo ((string)($appliedFilters['cliente_id'] ?? '') === (string)$cliente['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cliente['label']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label for="periodo_inicio" class="block text-sm font-medium text-gray-700">Período inicial</label>
                    <input
                        type="date"
                        id="periodo_inicio"
                        name="periodo_inicio"
                        value="<?php echo htmlspecialchars($appliedFilters['periodo_inicio'] ?? ''); ?>"
                        class="mt-1 block w-full rounded-md border-gray-200 bg-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                    >
                </div>

                <div>
                    <label for="periodo_fim" class="block text-sm font-medium text-gray-700">Período final</label>
                    <input
                        type="date"
                        id="periodo_fim"
                        name="periodo_fim"
                        value="<?php echo htmlspecialchars($appliedFilters['periodo_fim'] ?? ''); ?>"
                        class="mt-1 block w-full rounded-md border-gray-200 bg-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                    >
                </div>
            </div>
        </div>

        <div id="advanced-filters" class="rounded-lg border border-dashed border-gray-200 bg-gray-50 p-4<?php echo $hasAdvancedFilters ? '' : ' hidden'; ?>" data-advanced-container>
            <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-600">Filtros avançados</h3>
            <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
                <div>
                    <label for="tipo" class="block text-sm font-medium text-gray-700">Tipo de alerta</label>
                    <select id="tipo" name="tipo[]" multiple size="5" class="mt-1 block w-full rounded-md border-gray-200 bg-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                        <?php foreach ($filterOptions['tipos'] as $tipo): ?>
                            <option value="<?php echo htmlspecialchars($tipo); ?>" <?php echo in_array($tipo, $appliedFilters['tipo'] ?? [], true) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars(notification_type_label($tipo)); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="mt-1 text-xs text-gray-500">Use Ctrl (ou Cmd) para selecionar múltiplos tipos.</p>
                </div>

                <div>
                    <label for="prioridade" class="block text-sm font-medium text-gray-700">Prioridade</label>
                    <select id="prioridade" name="prioridade[]" multiple size="4" class="mt-1 block w-full rounded-md border-gray-200 bg-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                        <?php foreach (['alta' => 'Alta', 'media' => 'Média', 'baixa' => 'Baixa'] as $value => $label): ?>
                            <option value="<?php echo htmlspecialchars($value); ?>" <?php echo in_array($value, $appliedFilters['prioridade'] ?? [], true) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label for="status_processo" class="block text-sm font-medium text-gray-700">Status do processo</label>
                    <select id="status_processo" name="status_processo[]" multiple size="5" class="mt-1 block w-full rounded-md border-gray-200 bg-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                        <?php foreach ($filterOptions['status'] as $statusProcesso): ?>
                            <option value="<?php echo htmlspecialchars($statusProcesso); ?>" <?php echo in_array($statusProcesso, $appliedFilters['status_processo'] ?? [], true) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($statusProcesso); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <?php if ($isManager): ?>
                    <div>
                        <label for="usuario" class="block text-sm font-medium text-gray-700">Destinatário</label>
                        <select id="usuario" name="usuario" class="mt-1 block w-full rounded-md border-gray-200 bg-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                            <option value="">Meus alertas</option>
                            <option value="todos" <?php echo ($appliedFilters['usuario'] ?? '') === 'todos' ? 'selected' : ''; ?>>Todos do grupo</option>
                            <?php foreach ($filterOptions['usuarios'] as $usuario): ?>
                                <option value="<?php echo htmlspecialchars((string)$usuario['id']); ?>" <?php echo ((string)($appliedFilters['usuario'] ?? '') === (string)$usuario['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($usuario['label']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="text-xs text-gray-500">
                Combine os filtros para refinar a visualização das notificações.
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    Aplicar filtros
                </button>
                <a href="<?php echo APP_URL; ?>/notificacoes.php" class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md shadow-sm text-gray-700 bg-white hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    Limpar filtros
                </a>
            </div>
        </div>
    </form>

    <form id="batchForm" action="<?php echo APP_URL; ?>/notificacoes.php?action=batchUpdate" method="POST" class="mt-8 space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <p class="text-sm text-gray-600">
                <?php echo $totalAlerts === 1 ? '1 notificação encontrada.' : $totalAlerts . ' notificações encontradas.'; ?>
            </p>
            <div class="flex flex-wrap items-center gap-2">
                <button type="submit" name="batch_action" value="mark_read" class="inline-flex items-center px-4 py-2 rounded-md text-sm font-semibold text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    Marcar como lidas
                </button>
                <button type="submit" name="batch_action" value="mark_resolved" class="inline-flex items-center px-4 py-2 rounded-md text-sm font-semibold text-white bg-emerald-600 hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500">
                    Marcar como resolvidas
                </button>
                <button type="button" data-select-all class="inline-flex items-center px-4 py-2 rounded-md text-sm font-semibold text-gray-700 bg-gray-100 hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-gray-300">
                    Selecionar tudo
                </button>
            </div>
        </div>

        <?php if (empty($groups)): ?>
            <div class="bg-white rounded-lg shadow p-8 text-center text-gray-500">
                Nenhuma notificação para os filtros selecionados.
            </div>
        <?php else: ?>
            <div class="space-y-4">
                <?php foreach ($groups as $index => $group): ?>
                    <?php
                        $groupId = 'notification-group-' . $index;
                        $priorityMeta = notification_priority_metadata($group['prioridade'] ?? 'media');
                        $title = $group['processo_titulo'] ?? 'Notificações gerais';
                        $clienteNome = $group['cliente_nome'] ?? null;
                        $statusProcesso = $group['status_processo'] ?? null;
                    ?>
                    <?php
                        $borderClass = 'border-yellow-500';
                        switch ($group['prioridade'] ?? 'media') {
                            case 'alta':
                                $borderClass = 'border-red-500';
                                break;
                            case 'baixa':
                                $borderClass = 'border-green-500';
                                break;
                        }
                    ?>
                    <section class="bg-white shadow rounded-lg overflow-hidden border-l-4 <?php echo $borderClass; ?>" data-notification-group="<?php echo htmlspecialchars($groupId); ?>">
                        <header class="flex flex-wrap items-start justify-between gap-4 p-4">
                            <div>
                                <h2 class="text-lg font-semibold text-gray-800 flex items-center gap-2">
                                    <?php echo htmlspecialchars($title); ?>
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold <?php echo $priorityMeta['classes']; ?>">
                                        Prioridade <?php echo htmlspecialchars($priorityMeta['label']); ?>
                                    </span>
                                </h2>
                                <div class="mt-1 text-sm text-gray-500 space-x-2">
                                    <?php if ($group['referencia_id'] ?? null): ?>
                                        <span>Processo #<?php echo (int)$group['referencia_id']; ?></span>
                                    <?php endif; ?>
                                    <?php if ($clienteNome): ?>
                                        <span>Cliente: <?php echo htmlspecialchars($clienteNome); ?></span>
                                    <?php endif; ?>
                                    <?php if ($statusProcesso): ?>
                                        <span>Status: <?php echo htmlspecialchars($statusProcesso); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="flex items-center gap-3">
                                <label class="inline-flex items-center gap-2 text-sm text-gray-600">
                                    <input type="checkbox" class="group-select h-4 w-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500" data-group-target="<?php echo htmlspecialchars($groupId); ?>">
                                    Selecionar grupo
                                </label>
                                <button type="button" class="toggle-group inline-flex items-center px-3 py-2 rounded-md text-sm font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-indigo-500" data-group-target="<?php echo htmlspecialchars($groupId); ?>">
                                    Exibir notificações
                                </button>
                            </div>
                        </header>
                        <div class="hidden border-t border-gray-100" data-group-content="<?php echo htmlspecialchars($groupId); ?>">
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="w-12 px-4 py-3">
                                                <span class="sr-only">Selecionar</span>
                                            </th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Mensagem</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tipo</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Recebida em</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Ações</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <?php foreach ($group['notifications'] as $notification): ?>
                                            <?php
                                                $notificationId = (int)($notification['id'] ?? 0);
                                                $link = $notification['link'] ?? '#';
                                                $displayDate = $notification['display_date'] ?? '';
                                                $alertType = $notification['tipo_alerta'] ?? 'notificacao_generica';
                                                $typeMeta = notification_type_metadata($alertType);
                                                $isRead = !empty($notification['lida']);
                                                $rowClasses = $isRead ? 'bg-white' : 'bg-yellow-50';
                                                $referenceId = (int)($notification['referencia_id'] ?? 0);
                                            ?>
                                            <tr class="<?php echo $rowClasses; ?>">
                                                <td class="px-4 py-3 text-center">
                                                    <input
                                                        type="checkbox"
                                                        name="ids[]"
                                                        value="<?php echo $notificationId; ?>"
                                                        class="h-4 w-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500"
                                                        data-group-target="<?php echo htmlspecialchars($groupId); ?>"
                                                    >
                                                </td>
                                                <td class="px-4 py-3 text-sm text-gray-900">
                                                    <?php echo htmlspecialchars($notification['mensagem'] ?? ''); ?>
                                                </td>
                                                <td class="px-4 py-3 text-sm">
                                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold <?php echo $typeMeta['classes']; ?>">
                                                        <?php echo htmlspecialchars($typeMeta['label']); ?>
                                                    </span>
                                                </td>
                                                <td class="px-4 py-3 text-sm text-gray-600">
                                                    <?php echo htmlspecialchars($displayDate); ?>
                                                </td>
                                                <td class="px-4 py-3 text-sm">
                                                    <?php if (!empty($notification['resolvido'])): ?>
                                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full bg-emerald-100 text-emerald-700 text-xs font-semibold">Resolvida</span>
                                                    <?php elseif ($isRead): ?>
                                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full bg-gray-100 text-gray-700 text-xs font-semibold">Lida</span>
                                                    <?php else: ?>
                                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full bg-yellow-200 text-yellow-800 text-xs font-semibold">Nova</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="px-4 py-3 text-right text-sm">
                                                    <div class="flex flex-wrap items-center justify-end gap-2">
                                                        <?php if ($isManager && $referenceId > 0 && $alertType === 'processo_pendente_orcamento'): ?>
                                                            <a href="<?php echo APP_URL; ?>/processos.php?action=aprovar_orcamento&id=<?php echo $referenceId; ?>" class="inline-flex items-center px-3 py-1.5 rounded-md text-xs font-medium text-white bg-green-600 hover:bg-green-700">
                                                                Aprovar orçamento
                                                            </a>
                                                            <form action="<?php echo APP_URL; ?>/processos.php?action=recusar_orcamento" method="POST" class="flex items-center gap-2">
                                                                <input type="hidden" name="id" value="<?php echo $referenceId; ?>">
                                                                <label for="motivo_recusa_<?php echo $notificationId; ?>" class="sr-only">Motivo do cancelamento</label>
                                                                <input id="motivo_recusa_<?php echo $notificationId; ?>" name="motivo_recusa" type="text" required placeholder="Motivo" class="w-28 md:w-40 px-2 py-1 text-xs border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-red-500">
                                                                <button type="submit" class="inline-flex items-center px-3 py-1.5 rounded-md text-xs font-medium text-white bg-red-600 hover:bg-red-700">
                                                                    Cancelar orçamento
                                                                </button>
                                                            </form>
                                                        <?php elseif ($isManager && $referenceId > 0 && $alertType === 'processo_pendente_servico'): ?>
                                                            <form action="<?php echo APP_URL; ?>/processos.php?action=change_status" method="POST">
                                                                <input type="hidden" name="id" value="<?php echo $referenceId; ?>">
                                                                <input type="hidden" name="status_processo" value="Serviço em Andamento">
                                                                <button type="submit" class="inline-flex items-center px-3 py-1.5 rounded-md text-xs font-medium text-white bg-emerald-600 hover:bg-emerald-700">
                                                                    Aprovar serviço
                                                                </button>
                                                            </form>
                                                            <form action="<?php echo APP_URL; ?>/processos.php?action=change_status" method="POST">
                                                                <input type="hidden" name="id" value="<?php echo $referenceId; ?>">
                                                                <input type="hidden" name="status_processo" value="Orçamento Pendente">
                                                                <button type="submit" class="inline-flex items-center px-3 py-1.5 rounded-md text-xs font-medium text-white bg-yellow-600 hover:bg-yellow-700">
                                                                    Solicitar ajustes
                                                                </button>
                                                            </form>
                                                        <?php endif; ?>

                                                        <?php if (!empty($link) && $link !== '#'): ?>
                                                            <a href="<?php echo htmlspecialchars(APP_URL . $link); ?>" class="inline-flex items-center px-3 py-1.5 rounded-md text-xs font-medium text-white bg-indigo-600 hover:bg-indigo-700">
                                                                Abrir
                                                            </a>
                                                        <?php endif; ?>
                                                        <a href="<?php echo APP_URL; ?>/notificacoes.php?action=markRead&id=<?php echo $notificationId; ?>" class="inline-flex items-center px-3 py-1.5 rounded-md text-xs font-medium text-gray-700 bg-white border border-gray-300 hover:bg-gray-100">
                                                            Marcar como lida
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </section>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </form>
</div>

<script>
(function () {
    const toggleButtons = document.querySelectorAll('.toggle-group');
    const selectAllButton = document.querySelector('[data-select-all]');

    toggleButtons.forEach((button) => {
        button.addEventListener('click', () => {
            const target = button.getAttribute('data-group-target');
            if (!target) {
                return;
            }

            const content = document.querySelector(`[data-group-content="${CSS.escape(target)}"]`);
            if (content) {
                content.classList.toggle('hidden');
                button.textContent = content.classList.contains('hidden') ? 'Exibir notificações' : 'Ocultar notificações';
            }
        });
    });

    document.querySelectorAll('.group-select').forEach((checkbox) => {
        checkbox.addEventListener('change', () => {
            const target = checkbox.getAttribute('data-group-target');
            if (!target) {
                return;
            }

            const checkboxes = document.querySelectorAll(`input[data-group-target="${CSS.escape(target)}"]`);
            checkboxes.forEach((item) => {
                item.checked = checkbox.checked;
            });
        });
    });

    if (selectAllButton) {
        selectAllButton.addEventListener('click', () => {
            const checkboxes = document.querySelectorAll('input[name="ids[]"]');
            const shouldSelectAll = Array.from(checkboxes).some((checkbox) => !checkbox.checked);
            checkboxes.forEach((checkbox) => {
                checkbox.checked = shouldSelectAll;
            });
        });
    }

    const advancedToggle = document.querySelector('[data-toggle-advanced]');
    const advancedContainer = document.querySelector('[data-advanced-container]');
    if (advancedToggle && advancedContainer) {
        const label = advancedToggle.querySelector('[data-toggle-label]');

        advancedToggle.addEventListener('click', () => {
            const isHidden = advancedContainer.classList.toggle('hidden');
            const isOpen = !isHidden;
            advancedToggle.setAttribute('aria-expanded', String(isOpen));
            if (label) {
                label.textContent = isOpen ? 'Ocultar filtros avançados' : 'Mostrar filtros avançados';
            }
        });
    }
})();
</script>
