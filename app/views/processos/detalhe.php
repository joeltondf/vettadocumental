<?php
// /app/views/processos/detalhe.php

$pageTitle = "Detalhes do Processo";

function has_service($processo, $service_name) {
    if (empty($processo['categorias_servico'])) return false;
    $services = array_map('trim', explode(',', $processo['categorias_servico']));
    return in_array($service_name, $services, true);
}

if (!function_exists('process_calculate_deadline_context')) {
    function process_calculate_deadline_context(array $processo, string $statusNormalized): array
    {
        $pauseLabels = [
            'pendente de pagamento' => 'pausado/pagamento',
            'pendente de documentos' => 'pausado/documento',
        ];

        $pauseLabel = $pauseLabels[$statusNormalized] ?? null;
        $isPaused = $pauseLabel !== null;
        $daysRemaining = null;

        if ($statusNormalized === 'concluído') {
            $daysRemaining = 0;
        } elseif ($isPaused) {
            $rawStored = $processo['prazo_dias_restantes'] ?? null;
            if ($rawStored !== null && $rawStored !== '') {
                $daysRemaining = (int) $rawStored;
            }
        } else {
            $deadline = null;

            $rawDate = $processo['data_previsao_entrega'] ?? null;
            if (!empty($rawDate)) {
                try {
                    $deadline = new DateTimeImmutable((string) $rawDate);
                } catch (Throwable $exception) {
                    $deadline = null;
                }
            }

            if ($deadline === null && !empty($processo['traducao_prazo_dias']) && !empty($processo['data_inicio_traducao'])) {
                try {
                    $start = new DateTimeImmutable((string) $processo['data_inicio_traducao']);
                    $deadline = $start->modify('+' . (int) $processo['traducao_prazo_dias'] . ' days');
                } catch (Throwable $exception) {
                    $deadline = null;
                }
            }

            if ($deadline === null && !empty($processo['prazo_dias']) && !empty($processo['data_inicio_traducao'])) {
                try {
                    $start = new DateTimeImmutable((string) $processo['data_inicio_traducao']);
                    $deadline = $start->modify('+' . (int) $processo['prazo_dias'] . ' days');
                } catch (Throwable $exception) {
                    $deadline = null;
                }
            }

            if ($deadline instanceof DateTimeImmutable) {
                $today = new DateTimeImmutable('today');
                $daysRemaining = (int) $today->diff($deadline)->format('%r%a');
            }
        }

        $display = '—';
        $badgeClass = 'text-gray-500';

        if ($daysRemaining !== null) {
            $display = (string) $daysRemaining;

            if ($isPaused) {
                $badgeClass = 'bg-slate-200 text-slate-800';
            } elseif ($daysRemaining <= 0) {
                $badgeClass = 'bg-red-200 text-red-800';
            } elseif ($daysRemaining <= 3) {
                $badgeClass = 'bg-yellow-200 text-yellow-800';
            } else {
                $badgeClass = 'text-green-600';
            }
        }

        return [
            'display' => $display,
            'class' => $badgeClass,
            'is_paused' => $isPaused,
            'pause_label' => $pauseLabel,
        ];
    }
}

if (!function_exists('process_render_deadline_badge')) {
    function process_render_deadline_badge(array $context): string
    {
        $badgeClass = htmlspecialchars($context['class'] ?? 'text-gray-500', ENT_QUOTES, 'UTF-8');
        $displayValue = htmlspecialchars($context['display'] ?? '—', ENT_QUOTES, 'UTF-8');
        $pauseBadge = '';

        if (!empty($context['is_paused']) && !empty($context['pause_label'])) {
            $pauseBadge = '<span class="px-2 py-0.5 inline-flex items-center text-[10px] uppercase tracking-wide font-semibold rounded-full bg-slate-200 text-slate-800">'
                . htmlspecialchars((string) $context['pause_label'], ENT_QUOTES, 'UTF-8')
                . '</span>';
        }

        return '<div class="flex items-center gap-2">'
            . '<span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full ' . $badgeClass . '">' . $displayValue . '</span>'
            . $pauseBadge
            . '</div>';
    }
}

if (!function_exists('format_prazo_countdown')) {
    function format_prazo_countdown(array $processo, string $statusNormalized): string
    {
        $context = process_calculate_deadline_context($processo, $statusNormalized);
        return process_render_deadline_badge($context);
    }
}

function normalize_status_info(?string $status): array {
    $normalized = mb_strtolower(trim((string)$status));

    if ($normalized === '') {
        return ['normalized' => '', 'label' => 'N/A'];
    }

    $aliasMap = [
        'orcamento' => 'orçamento',
        'orcamento pendente' => 'orçamento pendente',
        'serviço pendente' => 'serviço pendente',
        'servico pendente' => 'serviço pendente',
        'pendente' => 'serviço pendente',
        'aprovado' => 'serviço pendente',
        'serviço em andamento' => 'serviço em andamento',
        'servico em andamento' => 'serviço em andamento',
        'em andamento' => 'serviço em andamento',
        'aguardando pagamento' => 'pendente de pagamento',
        'aguardando pagamentos' => 'pendente de pagamento',
        'aguardando documento' => 'pendente de documentos',
        'aguardando documentos' => 'pendente de documentos',
        'aguardando documentacao' => 'pendente de documentos',
        'aguardando documentação' => 'pendente de documentos',
        'pendente de pagamento' => 'pendente de pagamento',
        'pendente de documentos' => 'pendente de documentos',
        'finalizado' => 'concluído',
        'finalizada' => 'concluído',
        'concluido' => 'concluído',
        'concluida' => 'concluído',
        'arquivado' => 'cancelado',
        'arquivada' => 'cancelado',
    ];

    if (isset($aliasMap[$normalized])) {
        $normalized = $aliasMap[$normalized];
    }

    $labels = [
        'orçamento' => 'Orçamento',
        'orçamento pendente' => 'Orçamento Pendente',
        'serviço pendente' => 'Serviço Pendente',
        'serviço em andamento' => 'Serviço em Andamento',
        'pendente de pagamento' => 'Pendente de pagamento',
        'pendente de documentos' => 'Pendente de documentos',
        'concluído' => 'Concluído',
        'cancelado' => 'Cancelado',
    ];

    $label = $labels[$normalized] ?? ($status === '' ? 'N/A' : $status);

    return ['normalized' => $normalized, 'label' => $label];
}

$rawStatus = $processo['status_processo'] ?? '';
$statusInfo = normalize_status_info($rawStatus);
$statusLabel = $statusInfo['label'];
$statusNormalized = $statusInfo['normalized'];
$status_classes = 'bg-gray-100 text-gray-800';
switch ($statusNormalized) {
    case 'orçamento':
    case 'orçamento pendente':
        $status_classes = 'bg-yellow-100 text-yellow-800';
        break;
    case 'serviço pendente':
        $status_classes = 'bg-orange-100 text-orange-800';
        break;
    case 'serviço em andamento':
        $status_classes = 'bg-cyan-100 text-cyan-800';
        break;
    case 'pendente de pagamento':
        $status_classes = 'bg-indigo-100 text-indigo-800';
        break;
    case 'pendente de documentos':
        $status_classes = 'bg-violet-100 text-violet-800';
        break;
    case 'concluído':
        $status_classes = 'bg-green-100 text-green-800';
        break;
    case 'cancelado':
        $status_classes = 'bg-red-100 text-red-800';
        break;
}
$statusLabel = $statusLabel ?: 'N/A';
$leadConversionContext = $leadConversionContext ?? ['shouldRender' => false];
$isAprovadoOuSuperior = in_array($statusNormalized, ['serviço pendente', 'serviço em andamento', 'pendente de pagamento', 'pendente de documentos', 'concluído'], true);
$isManager = in_array($_SESSION['user_perfil'] ?? '', ['admin', 'gerencia', 'supervisor'], true);
$isBudgetPending = $statusNormalized === 'orçamento pendente';
$shouldHideStatusPanel = $isManager && $isBudgetPending;
$isServicePending = $statusNormalized === 'serviço pendente';
$baseAppUrl = defined('APP_URL') ? APP_URL : '';
$prospectionId = isset($processo['prospeccao_id']) ? (int) $processo['prospeccao_id'] : 0;
$prospectionCode = trim((string) ($processo['prospeccao_codigo'] ?? ''));
$prospectionName = trim((string) ($processo['prospeccao_nome'] ?? ''));
$prospectionLabel = $prospectionCode !== ''
    ? $prospectionCode
    : ($prospectionId > 0 ? ('ID #' . $prospectionId) : null);
?>


<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Detalhes: <?php echo htmlspecialchars($processo['titulo'] ?? 'Processo'); ?></h1>
        <a href="dashboard.php" class="text-sm text-blue-600 hover:underline">&larr; Voltar</a>
    </div>
    <div class="flex items-center space-x-4">
        <span id="display-status-badge" class="px-3 py-1.5 inline-flex text-sm leading-5 font-semibold rounded-full <?php echo $status_classes; ?>">
            <?php echo htmlspecialchars($statusLabel); ?>
        </span>
        <?php if (!empty($leadConversionContext['shouldRender'])): ?>
            <a
                href="processos.php?action=convert_to_service_client&id=<?php echo (int)$processo['id']; ?>"
                class="bg-orange-500 hover:bg-orange-600 text-white font-bold py-2 px-4 rounded-lg shadow-sm"
            >
                Converter em Serviço
            </a>
        <?php endif; ?>
        <a
            href="processos.php?action=exibir_orcamento&id=<?php echo $processo['id']; ?>"
            target="_blank"
            rel="noopener noreferrer"
            class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg shadow-sm"
        >
            Exibir Orçamento
        </a>
        <a href="processos.php?action=edit&id=<?php echo $processo['id']; ?>&return_to=<?php echo urlencode('processos.php?action=view&id=' . $processo['id']); ?>" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded-lg shadow-sm">
            Editar Processo
        </a>
    </div>
</div>

<div id="feedback-container" class="hidden mb-4"></div>

<div class="flex flex-col lg:flex-row gap-6">

    <div class="lg:w-2/3 space-y-6">

        <div class="bg-white shadow-lg rounded-lg p-6">
            <h2 class="text-xl font-semibold text-gray-700 border-b pb-3 mb-4">Informações Gerais</h2>
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                <div>
                    <p class="text-sm font-medium text-gray-500">Nº Orçamento</p>
                    <p class="text-lg text-gray-800"><?php echo htmlspecialchars($processo['orcamento_numero'] ?? 'N/A'); ?></p>
                </div>
                <div>
                    <p class="text-sm font-medium text-gray-500">Cliente / Assessoria</p>
                    <p class="text-lg text-gray-800"><?php echo htmlspecialchars($processo['nome_cliente'] ?? 'N/A'); ?></p>
                </div>
                <div>
                    <p class="text-sm font-medium text-gray-500">Data de Entrada</p>
                    <p class="text-lg text-gray-800"><?php echo isset($processo['data_criacao']) ? date('d/m/Y', strtotime($processo['data_criacao'])) : 'N/A'; ?></p>
                </div>
                <div>
                    <p class="text-sm font-medium text-gray-500">Vendedor Responsável</p>
                    <p class="text-lg text-gray-800"><?php echo htmlspecialchars($processo['nome_vendedor'] ?? 'N/A'); ?></p>
                </div>
                <div class="md:col-span-2">
                    <p class="text-sm font-medium text-gray-500">Prospecção de Origem</p>
                    <?php if ($prospectionId > 0): ?>
                        <?php $prospectionLink = rtrim($baseAppUrl, '/') . '/crm/prospeccoes/detalhes.php?id=' . $prospectionId; ?>
                        <a
                            href="<?php echo htmlspecialchars($prospectionLink); ?>"
                            class="text-blue-600 hover:text-blue-800"
                            title="<?php echo htmlspecialchars($prospectionName !== '' ? $prospectionName : 'Abrir prospecção'); ?>"
                        >
                            <?php echo htmlspecialchars($prospectionLabel ?? ('ID #' . $prospectionId)); ?>
                        </a>
                    <?php else: ?>
                        <p class="text-lg text-gray-400">Não vinculado</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="bg-white shadow-lg rounded-lg p-6">
            <h2 class="text-xl font-semibold text-gray-700 border-b pb-3 mb-4">
                <?php if ($processo['status_processo'] == 'Orçamento'): ?>
                    Serviços Orçados
                <?php else: ?>
                    Serviços Contratados
                <?php endif; ?>
                </h2>
            <div class="space-y-4">
                <?php if (has_service($processo, 'Tradução')): ?>
                    <div class="p-3 bg-blue-50 rounded-md">
                        <h3 class="font-semibold text-blue-800">Tradução</h3>
                        <div class="grid grid-cols-2 gap-4 mt-2 text-sm">
                            <p><strong>Idioma:</strong> <?php echo htmlspecialchars($processo['idioma'] ?? 'N/A'); ?></p>
                            <p><strong>Assinatura:</strong> <?php echo htmlspecialchars($processo['modalidade_assinatura'] ?? 'N/A'); ?></p>
                        </div>
                    </div>
                <?php endif; ?>
                <?php if (has_service($processo, 'Apostilamento')): ?>
                    <div class="p-3 bg-yellow-50 rounded-md">
                        <h3 class="font-semibold text-yellow-800">Apostilamento</h3>
                        <div class="grid grid-cols-3 gap-4 mt-2 text-sm">
                            <p><strong>Qtd:</strong> <?php echo htmlspecialchars($processo['apostilamento_quantidade'] ?? '0'); ?></p>
                            <p><strong>Valor Unit.:</strong> R$ <?php echo number_format($processo['apostilamento_valor_unitario'] ?? 0, 2, ',', '.'); ?></p>
                            <p><strong>Subtotal:</strong> R$ <?php echo number_format(($processo['apostilamento_quantidade'] ?? 0) * ($processo['apostilamento_valor_unitario'] ?? 0), 2, ',', '.'); ?></p>
                        </div>
                    </div>
                <?php endif; ?>
                <?php if (has_service($processo, 'Postagem')): ?>
                    <div class="p-3 bg-purple-50 rounded-md">
                        <h3 class="font-semibold text-purple-800">Postagem / Envio</h3>
                        <div class="grid grid-cols-3 gap-4 mt-2 text-sm">
                            <p><strong>Qtd:</strong> <?php echo htmlspecialchars($processo['postagem_quantidade'] ?? '0'); ?></p>
                            <p><strong>Valor Unit.:</strong> R$ <?php echo number_format($processo['postagem_valor_unitario'] ?? 0, 2, ',', '.'); ?></p>
                            <p><strong>Subtotal:</strong> R$ <?php echo number_format(($processo['postagem_quantidade'] ?? 0) * ($processo['postagem_valor_unitario'] ?? 0), 2, ',', '.'); ?></p>
                        </div>
                    </div>
                <?php endif; ?>
                <?php if (has_service($processo, 'Outros')): ?>
                    <?php
                    $outrosDocs = array_filter($documentos, static function ($doc) {
                        $categoria = isset($doc['categoria']) ? mb_strtolower((string) $doc['categoria'], 'UTF-8') : '';
                        return $categoria === 'outros';
                    });
                    $outrosQuantidade = count($outrosDocs);
                    $outrosTotal = array_reduce($outrosDocs, static function ($carry, $doc) {
                        return $carry + (float) ($doc['valor_unitario'] ?? 0);
                    }, 0.0);
                    ?>
                    <div class="p-3 bg-gray-50 rounded-md">
                        <h3 class="font-semibold text-gray-800">Outros Serviços</h3>
                        <div class="grid grid-cols-2 gap-4 mt-2 text-sm">
                            <p><strong>Itens:</strong> <?= $outrosQuantidade; ?></p>
                            <p><strong>Total:</strong> R$ <?= number_format($outrosTotal, 2, ',', '.'); ?></p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="bg-white shadow-lg rounded-lg p-6">
            <h2 class="text-xl font-semibold text-gray-700 border-b pb-3 mb-4">Documentos do Processo</h2>
            <?php if (empty($documentos)): ?>
                <p class="text-gray-500 text-center py-4">Nenhum documento adicionado.</p>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nome Específico</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Categoria</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tipo</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Valor</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($documentos as $doc): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars($doc['nome_documento'] ?? 'N/A'); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($doc['categoria'] ?? ''); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($doc['tipo_documento'] ?? ''); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-right">R$ <?php echo number_format($doc['valor_unitario'] ?? 0, 2, ',', '.'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <div class="bg-white shadow-lg rounded-lg p-6">
            <div class="flex justify-between items-center border-b pb-3 mb-4">
                <h2 class="text-xl font-semibold text-gray-700">Etapa de Tradução</h2>
                <button data-modal-target="modal-etapa-traducao" class="modal-trigger text-sm bg-gray-200 hover:bg-gray-300 text-gray-800 font-bold py-1 px-3 rounded-lg">Editar</button>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-x-6 gap-y-4 text-sm">
                <div>
                    <p class="font-medium text-gray-500">Tradutor</p>
                    <p class="text-gray-800" id="display-nome_tradutor"><?php echo htmlspecialchars($processo['nome_tradutor'] ?? 'VETTA'); ?></p>
                </div>
                <div>
                    <p class="font-medium text-gray-500">Qtd. Documentos</p>
                    <p class="text-gray-800" id="display-document_count">
                        <?php
                            // Soma a quantidade de todos os documentos do processo
                            $total_docs = 0;
                            if (!empty($documentos)) {
                                foreach ($documentos as $doc) {
                                    $total_docs += (int)($doc['quantidade'] ?? 1);
                                }
                            }
                            echo $total_docs;
                        ?>
                    </p>
                </div>

                <div>
                    <p class="font-medium text-gray-500">Modalidade</p>
                    <p class="text-gray-800" id="display-traducao_modalidade"><?php echo htmlspecialchars($processo['traducao_modalidade'] ?? 'Normal'); ?></p>
                </div>
                <div>
                    <p class="font-medium text-gray-500">Envio para Tradutor</p>
                    <p class="text-gray-800" id="display-data_inicio_traducao"><?php echo isset($processo['data_inicio_traducao']) ? date('d/m/Y', strtotime($processo['data_inicio_traducao'])) : 'Pendente'; ?></p>
                </div>
                <div class="md:col-span-2">
                    <p class="font-medium text-gray-500">Prazo do Serviço</p>
                    <p class="text-gray-800" id="display-prazo_dias">
                        <?php
                            echo format_prazo_countdown($processo, $statusNormalized);
                        ?>
                    </p>
                </div>

            </div>
        </div>

        <div class="bg-white shadow-lg rounded-lg p-6">
            <div class="flex justify-between items-center border-b pb-3 mb-4">
                <h2 class="text-xl font-semibold text-gray-700">Assinatura da Tradutora</h2>
                <button data-modal-target="modal-etapa-assinatura" class="modal-trigger text-sm bg-gray-200 hover:bg-gray-300 text-gray-800 font-bold py-1 px-3 rounded-lg">Editar</button>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-x-6 gap-y-4 text-sm">
                <div>
                    <p class="font-medium text-gray-500">Tipo de Assinatura</p>
                    <p class="text-gray-800" id="display-assinatura_tipo"><?php echo htmlspecialchars($processo['assinatura_tipo'] ?? 'Digital'); ?></p>
                </div>
                <div>
                    <p class="font-medium text-gray-500">Envio da Tradução</p>
                    <p class="text-gray-800" id="display-data_envio_assinatura"><?php echo isset($processo['data_envio_assinatura']) ? date('d/m/Y', strtotime($processo['data_envio_assinatura'])) : 'Pendente'; ?></p>
                </div>
                <div>
                    <p class="font-medium text-gray-500">Data de Devolução</p>
                    <p class="text-gray-800" id="display-data_devolucao_assinatura"><?php echo isset($processo['data_devolucao_assinatura']) ? date('d/m/Y', strtotime($processo['data_devolucao_assinatura'])) : 'Pendente'; ?></p>
                </div>
            </div>
        </div>

        <div class="bg-white shadow-lg rounded-lg p-6">
            <div class="flex justify-between items-center border-b pb-3 mb-4">
                <h2 class="text-xl font-semibold text-gray-700">Cartório | Cliente</h2>
                <button data-modal-target="modal-etapa-cartorio" class="modal-trigger text-sm bg-gray-200 hover:bg-gray-300 text-gray-800 font-bold py-1 px-3 rounded-lg">Editar</button>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-4 text-sm">
                <div>
                    <p class="font-medium text-gray-500">Finalização</p>
                    <p class="text-gray-800" id="display-finalizacao_tipo"><?php echo htmlspecialchars($processo['finalizacao_tipo'] ?? 'Cliente'); ?></p>
                </div>
                <div>
                    <p class="font-medium text-gray-500">Data de Envio para Cartório</p>
                    <p class="text-gray-800" id="display-data_envio_cartorio"><?php echo isset($processo['data_envio_cartorio']) ? date('d/m/Y', strtotime($processo['data_envio_cartorio'])) : 'Pendente'; ?></p>
                </div>
            </div>
        </div>
    </div>

    <div class="lg:w-1/3 space-y-6">
        <?php if ($isManager && ($isBudgetPending || $isServicePending)): ?>
        <div class="bg-white shadow-lg rounded-lg p-6">
            <div class="flex items-center justify-between border-b pb-3 mb-4">
                <h2 class="text-xl font-semibold text-gray-700">Ações de Aprovação</h2>
                <span class="text-sm text-gray-500">Visíveis apenas para gerência</span>
            </div>
            <?php if ($isBudgetPending): ?>
                <div class="space-y-3">
                    <div class="flex flex-wrap justify-end gap-2">
                        <a href="processos.php?action=aprovar_orcamento&id=<?= $processo['id']; ?>" class="inline-flex justify-center items-center px-4 py-2 text-sm font-semibold rounded-md bg-green-600 text-white shadow hover:bg-green-700">
                            Aprovar orçamento
                        </a>
                        <form action="processos.php?action=recusar_orcamento" method="POST" class="flex flex-wrap items-center justify-end gap-2">
                            <input type="hidden" name="id" value="<?= $processo['id']; ?>">
                            <label for="motivo_recusa_detalhe" class="sr-only">Motivo do cancelamento</label>
                            <input id="motivo_recusa_detalhe" type="text" name="motivo_recusa" class="w-full sm:w-60 px-3 py-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-transparent" placeholder="Motivo do cancelamento" required>
                            <button type="submit" class="inline-flex justify-center items-center px-4 py-2 text-sm font-semibold rounded-md bg-red-600 text-white shadow hover:bg-red-700">
                                Cancelar orçamento
                            </button>
                        </form>
                    </div>
                </div>
            <?php elseif ($isServicePending): ?>
                <div class="flex flex-wrap justify-end gap-2">
                    <form action="processos.php?action=change_status" method="POST" class="inline-flex">
                        <input type="hidden" name="id" value="<?= $processo['id']; ?>">
                        <input type="hidden" name="status_processo" value="Serviço em Andamento">
                        <button type="submit" class="inline-flex justify-center items-center px-4 py-2 text-sm font-semibold rounded-md bg-green-600 text-white shadow hover:bg-green-700">
                            Aprovar serviço
                        </button>
                    </form>
                    <form action="processos.php?action=change_status" method="POST" class="inline-flex">
                        <input type="hidden" name="id" value="<?= $processo['id']; ?>">
                        <input type="hidden" name="status_processo" value="Orçamento Pendente">
                        <button type="submit" class="inline-flex justify-center items-center px-4 py-2 text-sm font-semibold rounded-md bg-yellow-600 text-white shadow hover:bg-yellow-700">
                            Solicitar ajustes
                        </button>
                    </form>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        <?php if ($isAprovadoOuSuperior): ?>
        <div class="bg-white shadow-lg rounded-lg p-6 border-l-4 border-green-500">
            <div class="border-b pb-3 mb-4">
                <h2 class="text-xl font-semibold text-gray-700">Dados de Faturamento</h2>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-4 text-sm">
                <div>
                    <p class="font-medium text-gray-500">Ordem de Serviço (Omie)</p>
                    <p class="text-gray-800 text-lg" id="display-os_numero_omie">
                        <?php 
                            $osOmie = $processo['os_numero_omie'] ?? null;
                            // Exibe apenas os últimos 5 dígitos para facilitar a leitura
                            $osOmieFormatado = $osOmie ? substr((string)$osOmie, -5) : 'Aguardando Geração...';
                            echo htmlspecialchars($osOmieFormatado);
                        ?>
                    </p>
                </div>
            </div>
            <div class="mt-4 pt-4 border-t border-gray-200">
                <p class="font-medium text-gray-500">Comprovante de Pagamento</p>
                <?php if (!empty($paymentProofAttachments)): ?>
                    <ul class="mt-2 space-y-2">
                        <?php foreach ($paymentProofAttachments as $anexo): ?>
                            <li class="flex items-center justify-between text-sm text-gray-700 bg-gray-50 border border-gray-200 rounded-md px-3 py-2">
                                <a
                                    href="<?= '/' . htmlspecialchars($anexo['caminho_arquivo']); ?>"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="text-blue-600 hover:underline"
                                >
                                    <?= htmlspecialchars($anexo['nome_arquivo_original']); ?>
                                </a>
                                <?php if (!empty($processo['id'])): ?>
                                    <a
                                        href="processos.php?action=excluir_anexo&id=<?= $processo['id'] ?>&anexo_id=<?= $anexo['id'] ?>"
                                        class="text-red-500 hover:text-red-700 text-xs font-semibold"
                                        onclick="return confirm('Tem certeza que deseja excluir este anexo?');"
                                    >
                                        Remover
                                    </a>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p class="text-xs text-gray-500 mt-2">Nenhum comprovante enviado.</p>
                <?php endif; ?>
            </div>
            <?php
            // Botão para gerar OS Manualmente
            if (
                in_array($_SESSION['user_perfil'], ['admin', 'gerencia', 'supervisor']) &&
                empty($processo['os_numero_omie']) &&
                $isAprovadoOuSuperior
            ):
            ?>
                <div class="mt-4 pt-4 border-t border-gray-200">
                    <a href="processos.php?action=gerar_os_omie_manual&id=<?php echo $processo['id']; ?>"
                       class="w-full text-center block bg-blue-600 text-white font-bold py-2 px-4 rounded-lg hover:bg-blue-700 text-sm"
                       onclick="return confirm('Tem certeza que deseja tentar gerar a Ordem de Serviço na Omie agora?');">
                        <i class="fas fa-sync-alt mr-2"></i> Gerar OS na Omie Manualmente
                    </a>
                    <p class="text-xs text-gray-500 mt-2 text-center">Use esta opção se a geração automática falhou ao aprovar o processo.</p>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        <?php if (in_array($processo['status_processo'], ['Cancelado', 'Recusado'], true) && $_SESSION['user_perfil'] === 'vendedor'): ?>
            <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 mb-6 rounded-lg shadow-md" role="alert">
                <p class="font-bold text-lg">Orçamento cancelado</p>

                <?php if (!empty($processo['motivo_recusa'])): ?>
                    <p class="mt-2"><strong>Motivo do cancelamento:</strong> <?php echo nl2br(htmlspecialchars($processo['motivo_recusa'])); ?></p>
                <?php endif; ?>

                <p class="mt-3">Revise o orçamento e <a href="processos.php?action=edit&id=<?php echo $processo['id']; ?>" class="font-bold underline hover:text-yellow-800">faça os ajustes necessários</a>. Em seguida, você pode reenviar diretamente ao cliente.</p>

                <form action="processos.php?action=change_status" method="POST" class="mt-4">
                    <input type="hidden" name="id" value="<?php echo $processo['id']; ?>">
                    <input type="hidden" name="status_processo" value="Orçamento">
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg">
                        Reenviar ao Cliente
                    </button>
                </form>
            </div>
        <?php endif; ?>
        

        <?php if (isset($_SESSION['user_perfil']) && in_array($_SESSION['user_perfil'], ['admin', 'gerencia', 'supervisor', 'financeiro'])): ?>
        <?php endif; ?>
        <?php if (!$shouldHideStatusPanel && isset($_SESSION['user_perfil']) && in_array($_SESSION['user_perfil'], ['admin', 'gerencia', 'supervisor'])): ?>
            <div class="bg-white shadow-lg rounded-lg p-6">
                <h2 class="text-xl font-semibold text-gray-700 border-b pb-3 mb-4">Painel de Ações</h2>

                <form id="status-change-form" action="processos.php?action=change_status" method="POST" class="space-y-4">
                    <input type="hidden" name="id" value="<?php echo $processo['id']; ?>">

                    <input type="hidden" name="data_inicio_traducao" id="hidden_data_inicio_traducao" data-original-name="data_inicio_traducao">
                    <input type="hidden" name="prazo_dias" id="hidden_prazo_dias" data-original-name="prazo_dias">
                    <div>
                        <label for="status_processo" class="block text-sm font-medium text-gray-700">Mudar Status para:</label>
                        <select id="status_processo" name="status_processo" class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                            <?php $statusOptions = ['Orçamento Pendente', 'Orçamento', 'Serviço Pendente', 'Serviço em Andamento', 'Pendente de pagamento', 'Pendente de documentos', 'Concluído', 'Cancelado']; ?>
                            <?php foreach ($statusOptions as $stat): ?>
                                <?php $optionInfo = normalize_status_info($stat); ?>
                                <option value="<?php echo $optionInfo['label']; ?>" <?php echo ($statusNormalized === $optionInfo['normalized']) ? 'selected' : ''; ?>>
                                    <?php echo $optionInfo['label']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="w-full bg-indigo-600 text-white font-bold py-2 px-4 rounded-lg hover:bg-indigo-700">
                        Atualizar Status
                    </button>
                </form>

                <div class="mt-4 pt-4 border-t border-gray-200 space-y-2">
                    <a href="processos.php?action=delete&id=<?php echo $processo['id']; ?>"
                       onclick="return confirm('ATENÇÃO!\nEsta ação é irreversível e irá apagar permanentemente este processo e todos os seus dados.\n\nTem certeza que deseja excluir?');"
                       class="w-full text-center block bg-red-600 text-white font-bold py-2 px-4 rounded-lg hover:bg-red-700 text-sm">
                        Excluir Processo
                    </a>
                </div>
            </div>
        <?php endif; ?>

        <div class="bg-white p-6 rounded-lg shadow-md border border-gray-200">
            <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
                <i class="fas fa-paperclip mr-2 text-gray-400"></i>Arquivos do Processo
            </h3>
            <div class="space-y-6">
                <div>
                    <h4 class="text-sm font-semibold text-blue-700 mb-2 uppercase tracking-wide">Tradução</h4>
                    <?php if (!empty($translationAttachments)): ?>
                        <ul class="space-y-2">
                            <?php foreach ($translationAttachments as $anexo): ?>
                                <li class="flex items-center justify-between text-sm text-gray-700 bg-blue-50 border border-blue-100 rounded-md px-3 py-2">
                                    <a href="visualizar_anexo.php?id=<?= $anexo['id'] ?>" target="_blank" class="text-blue-600 hover:underline">
                                        <?= htmlspecialchars($anexo['nome_arquivo_original']); ?>
                                    </a>
                                    <?php if (!empty($processo['id'])): ?>
                                        <a href="processos.php?action=excluir_anexo&id=<?= $processo['id'] ?>&anexo_id=<?= $anexo['id'] ?>" class="text-red-500 hover:text-red-700 text-xs font-semibold" onclick="return confirm('Tem certeza que deseja excluir este anexo?');">Remover</a>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p class="text-xs text-gray-500">Nenhum arquivo de tradução.</p>
                    <?php endif; ?>
                </div>
                <div>
                    <h4 class="text-sm font-semibold text-green-700 mb-2 uppercase tracking-wide">CRC</h4>
                    <?php if (!empty($crcAttachments)): ?>
                        <ul class="space-y-2">
                            <?php foreach ($crcAttachments as $anexo): ?>
                                <li class="flex items-center justify-between text-sm text-gray-700 bg-green-50 border border-green-100 rounded-md px-3 py-2">
                                    <a href="visualizar_anexo.php?id=<?= $anexo['id'] ?>" target="_blank" class="text-green-600 hover:underline">
                                        <?= htmlspecialchars($anexo['nome_arquivo_original']); ?>
                                    </a>
                                    <?php if (!empty($processo['id'])): ?>
                                        <a href="processos.php?action=excluir_anexo&id=<?= $processo['id'] ?>&anexo_id=<?= $anexo['id'] ?>" class="text-red-500 hover:text-red-700 text-xs font-semibold" onclick="return confirm('Tem certeza que deseja excluir este anexo?');">Remover</a>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p class="text-xs text-gray-500">Nenhum arquivo de CRC.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="bg-white shadow-lg rounded-lg p-6">
            <h2 class="text-xl font-semibold text-gray-700 border-b pb-3 mb-4">Comentários</h2>
            <form id="comment-form" class="mb-4">
                <input type="hidden" name="processo_id" value="<?php echo $processo['id']; ?>">
                <textarea name="comentario" rows="3" class="w-full border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" placeholder="Deixe um comentário..." required></textarea>
                <button type="submit" class="mt-2 w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg">Publicar</button>
            </form>
            <div id="comments-list" class="space-y-4 max-h-96 overflow-y-auto pr-2">
                <?php if (empty($comentarios)): ?>
                    <p id="no-comments" class="text-sm text-gray-500 text-center">Nenhum comentário ainda.</p>
                <?php else: ?>
                    <?php foreach($comentarios as $comentario): ?>
                        <div class="bg-gray-50 p-3 rounded-lg">
                            <div class="flex items-center justify-between mb-1">
                                <p class="text-sm font-semibold text-gray-800"><?php echo htmlspecialchars($comentario['nome_completo']); ?></p>
                                <p class="text-xs text-gray-500"><?php echo date('d/m/Y H:i', strtotime($comentario['data_comentario'])); ?></p>
                            </div>
                            <p class="text-sm text-gray-600"><?php echo nl2br(htmlspecialchars($comentario['comentario'])); ?></p>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- MODAL PARA REQUERIMENTOS DA MUDANÇA DE STATUS (ORÇAMENTO -> APROVADO) -->
<div id="modal-status-requirements" class="modal-overlay hidden">
    <div class="modal-content rounded-lg shadow-sm border border-gray-200 bg-white p-5 sm:p-6">
        <!-- Cabeçalho -->
        <div class="mb-4 sm:mb-5">
            <h3 class="text-xl sm:text-2xl font-semibold tracking-tight text-gray-800">
                Aprovação de Orçamento
            </h3>
            <p class="text-sm text-gray-600 mt-1">
                Para aprovar este orçamento, por favor, defina o prazo do serviço.
            </p>
        </div>

        <!-- Corpo do formulário do modal -->
        <div class="space-y-5">
            <!-- Data de envio para o Tradutor -->
            <div>
                <label for="modal_req_data_inicio_traducao" class="block text-sm font-medium text-gray-700 mb-1">
                    Data de Envio para o Tradutor <span class="text-red-500">*</span>
                </label>
                <input type="date" id="modal_req_data_inicio_traducao" class="w-full rounded-md border border-gray-300 px-3 py-2 focus:ring-1 focus:ring-gray-400" value="<?php echo date('Y-m-d'); ?>">
                <p class="mt-1 text-xs text-gray-500">
                    Preenche automaticamente com a data de hoje — ajuste se necessário.
                </p>
            </div>

            <!-- Prazo do Serviço -->
            <div class="border-t border-gray-100 pt-4">
                <label for="modal_req_prazo_dias" class="block text-sm font-medium text-gray-700 mb-1">
                    Prazo do Serviço (dias) <span class="text-red-500">*</span>
                </label>
                <input
                    type="number"
                    min="1"
                    id="modal_req_prazo_dias"
                    class="w-full rounded-md border border-gray-300 px-3 py-2 focus:ring-1 focus:ring-gray-400"
                    placeholder="Ex.: 5"
                    required
                >
                <p class="mt-1 text-xs text-gray-500">Informe o prazo em dias corridos.</p>
            </div>
        </div>

        <!-- Ações -->
        <div class="mt-6 sm:mt-7 flex items-center justify-end gap-2 sm:gap-3">
            <button type="button" id="cancel-status-change" class="modal-close-btn inline-flex items-center justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                Cancelar
            </button>
            <button type="button" id="confirm-status-change" class="modal-save-btn inline-flex items-center justify-center rounded-md bg-gray-800 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-400">
                Salvar e Mudar Status
            </button>
        </div>
    </div>
</div>


<div id="modal-etapa-traducao" class="modal-overlay hidden">
    <div class="modal-content rounded-lg shadow-sm border border-gray-200 bg-white p-5 sm:p-6">
    <form class="modal-form space-y-5" data-action="processos.php?action=update_etapas">
        <input type="hidden" name="id" value="<?php echo $processo['id']; ?>">

        <!-- Cabeçalho -->
        <div>
        <h3 class="text-xl sm:text-2xl font-semibold tracking-tight text-gray-800">Editar Etapa de Tradução</h3>
        <p class="text-sm text-gray-600 mt-1">Defina o tradutor, modalidade, data de envio e o prazo do serviço.</p>
        </div>

        <!-- Quantidade de documentos -->
        <div class="rounded-md border border-gray-100 bg-gray-50 p-3 sm:p-4">
        <label class="block text-sm font-medium text-gray-700">Quantidade de Documentos</label>
        <p class="mt-1 text-gray-800 font-semibold"><?php echo count($documentos); ?> documento(s)</p>
        </div>

        <!-- Tradutor + Modalidade -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4">
        <div>
            <label for="modal_tradutor_id" class="block text-sm font-medium text-gray-700 mb-1">Tradutor</label>
            <select
            name="tradutor_id"
            id="modal_tradutor_id"
            class="w-full rounded-md border border-gray-300 px-3 py-2 focus:ring-1 focus:ring-gray-400"
            >
            <option value="" <?php echo (empty($processo['tradutor_id'])) ? 'selected' : ''; ?>>VETTA (Padrão)</option>
            <?php if (!empty($tradutores)): ?>
                <?php foreach($tradutores as $tradutor): ?>
                <option value="<?php echo $tradutor['id']; ?>"
                    <?php echo (isset($processo['tradutor_id']) && $processo['tradutor_id'] == $tradutor['id']) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($tradutor['nome_tradutor']); ?>
                </option>
                <?php endforeach; ?>
            <?php endif; ?>
            </select>
        </div>

        <div>
            <label for="modal_traducao_modalidade" class="block text-sm font-medium text-gray-700 mb-1">Modalidade</label>
            <select
            name="traducao_modalidade"
            id="modal_traducao_modalidade"
            class="w-full rounded-md border border-gray-300 px-3 py-2 focus:ring-1 focus:ring-gray-400"
            >
            <option value="Normal"  <?php echo ($processo['traducao_modalidade'] == 'Normal')  ? 'selected' : ''; ?>>Normal</option>
            <option value="Express" <?php echo ($processo['traducao_modalidade'] == 'Express') ? 'selected' : ''; ?>>Express</option>
            </select>
        </div>
        </div>

        <!-- Data de envio para tradutor -->
        <div>
        <label for="modal_data_inicio_traducao" class="block text-sm font-medium text-gray-700 mb-1">
            Data de Envio para Tradutor
        </label>
        <input
            type="date"
            name="data_inicio_traducao"
            id="modal_data_inicio_traducao"
            class="w-full rounded-md border border-gray-300 px-3 py-2 focus:ring-1 focus:ring-gray-400"
            value="<?php echo htmlspecialchars($processo['data_inicio_traducao'] ?? date('Y-m-d')); ?>"
        >
        <p class="mt-1 text-xs text-gray-500">Obrigatória apenas quando houver prazo de tradução maior que zero.</p>
        </div>

        <!-- Prazo do Serviço -->
        <div class="border-t border-gray-100 pt-4">
        <label for="modal_traducao_prazo_dias" class="block text-sm font-medium text-gray-700 mb-2">
            Prazo do Serviço (dias)
        </label>
        <input
            type="number"
            name="traducao_prazo_dias"
            id="modal_traducao_prazo_dias"
            class="w-full rounded-md border border-gray-300 px-3 py-2 focus:ring-1 focus:ring-gray-400"
            value="<?php echo htmlspecialchars($processo['traducao_prazo_dias'] ?? $processo['prazo_dias'] ?? ''); ?>"
            placeholder="Ex.: 5"
        >
        <p class="mt-1 text-xs text-gray-500">Informe um número inteiro de dias. Use zero ou deixe em branco para remover o prazo.</p>
        </div>

        <!-- Ações -->
        <div class="pt-2 flex items-center justify-end gap-2 sm:gap-3">
        <button type="button" class="modal-close-btn inline-flex items-center justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
            Cancelar
        </button>
        <button type="submit" class="modal-save-btn inline-flex items-center justify-center rounded-md bg-gray-800 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-400">
            Atualizar
        </button>
        </div>
    </form>
    </div>

</div>

<div id="modal-etapa-assinatura" class="modal-overlay hidden">
    <div class="modal-content rounded-lg shadow-sm border border-gray-200 bg-white p-5 sm:p-6">
        <form class="modal-form space-y-5" data-action="processos.php?action=update_etapas">
            <input type="hidden" name="id" value="<?php echo $processo['id']; ?>">

            <!-- Cabeçalho -->
            <div>
            <h3 class="text-xl sm:text-2xl font-semibold tracking-tight text-gray-800">
                Editar Assinatura da Tradutora
            </h3>
            <p class="text-sm text-gray-600 mt-1">
                Defina o tipo de assinatura e as datas de envio e devolução.
            </p>
            </div>

            <!-- Tipo de Assinatura -->
            <div>
            <label for="modal_assinatura_tipo" class="block text-sm font-medium text-gray-700 mb-1">
                Tipo de Assinatura
            </label>
            <select
                name="assinatura_tipo"
                id="modal_assinatura_tipo"
                class="w-full rounded-md border border-gray-300 px-3 py-2 focus:ring-1 focus:ring-gray-400"
            >
                <option value="Física" <?php echo ($processo['assinatura_tipo'] == 'Física') ? 'selected' : ''; ?>>Física</option>
                <option value="Digital" <?php echo ($processo['assinatura_tipo'] == 'Digital') ? 'selected' : ''; ?>>Digital</option>
            </select>
            </div>

            <!-- Data de Envio da Tradução -->
            <div>
            <label for="modal_data_envio_assinatura" class="block text-sm font-medium text-gray-700 mb-1">
                Envio da Tradução
            </label>
            <input
                type="date"
                name="data_envio_assinatura"
                id="modal_data_envio_assinatura"
                class="w-full rounded-md border border-gray-300 px-3 py-2 focus:ring-1 focus:ring-gray-400"
                value="<?php echo htmlspecialchars($processo['data_envio_assinatura'] ?? date('Y-m-d')); ?>"
            >
            </div>

            <!-- Data de Devolução -->
            <div>
            <label for="modal_data_devolucao_assinatura" class="block text-sm font-medium text-gray-700 mb-1">
                Data de Devolução
            </label>
            <input
                type="date"
                name="data_devolucao_assinatura"
                id="modal_data_devolucao_assinatura"
                class="w-full rounded-md border border-gray-300 px-3 py-2 focus:ring-1 focus:ring-gray-400"
                value="<?php echo htmlspecialchars($processo['data_devolucao_assinatura'] ?? ''); ?>"
            >
            </div>

            <!-- Ações -->
            <div class="pt-2 flex items-center justify-end gap-2 sm:gap-3">
            <button
                type="button"
                class="modal-close-btn inline-flex items-center justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
            >
                Cancelar
            </button>
            <button
                type="submit"
                class="modal-save-btn inline-flex items-center justify-center rounded-md bg-gray-800 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-400"
            >
                Atualizar
            </button>
            </div>
        </form>
</div>

</div>

<div id="modal-etapa-cartorio" class="modal-overlay hidden">
    <div class="modal-content rounded-lg shadow-sm border border-gray-200 bg-white p-5 sm:p-6">
        <form class="modal-form space-y-5" data-action="processos.php?action=update_etapas">
            <input type="hidden" name="id" value="<?php echo $processo['id']; ?>">

            <!-- Cabeçalho -->
            <div>
            <h3 class="text-xl sm:text-2xl font-semibold tracking-tight text-gray-800">
                Editar Etapa Cartório | Cliente
            </h3>
            <p class="text-sm text-gray-600 mt-1">
                Defina o tipo de finalização e a data de envio para o cartório.
            </p>
            </div>

            <!-- Finalização -->
            <div>
            <label for="modal_finalizacao_tipo" class="block text-sm font-medium text-gray-700 mb-1">
                Finalização
            </label>
            <select
                name="finalizacao_tipo"
                id="modal_finalizacao_tipo"
                class="w-full rounded-md border border-gray-300 px-3 py-2 focus:ring-1 focus:ring-gray-400"
            >
                <option value="Cliente" <?php echo ($processo['finalizacao_tipo'] == 'Cliente') ? 'selected' : ''; ?>>Cliente</option>
                <option value="Cartório" <?php echo ($processo['finalizacao_tipo'] == 'Cartório') ? 'selected' : ''; ?>>Cartório</option>
            </select>
            </div>

            <!-- Data de Envio para Cartório -->
            <div>
            <label for="modal_data_envio_cartorio" class="block text-sm font-medium text-gray-700 mb-1">
                Data de Envio para Cartório
            </label>
            <input
                type="date"
                name="data_envio_cartorio"
                id="modal_data_envio_cartorio"
                class="w-full rounded-md border border-gray-300 px-3 py-2 focus:ring-1 focus:ring-gray-400"
                value="<?php echo htmlspecialchars($processo['data_envio_cartorio'] ?? date('Y-m-d')); ?>"
            >
            </div>

            <!-- Ações -->
            <div class="pt-2 flex items-center justify-end gap-2 sm:gap-3">
            <button
                type="button"
                class="modal-close-btn inline-flex items-center justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
            >
                Cancelar
            </button>
            <button
                type="submit"
                class="modal-save-btn inline-flex items-center justify-center rounded-md bg-gray-800 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-400"
            >
                Atualizar
            </button>
            </div>
        </form>
    </div>
</div>

<style>
.modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.6);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1000;
    transition: opacity 0.3s ease;
}
.modal-content {
    background: white;
    padding: 2rem;
    border-radius: 0.5rem;
    width: 90%;
    max-width: 500px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.3);
    transform: translateY(-20px);
    transition: transform 0.3s ease;
}
.modal-overlay.hidden {
    opacity: 0;
    pointer-events: none;
}
.modal-overlay.hidden .modal-content {
    transform: translateY(-30px);
}
.modal-actions {
    margin-top: 1.5rem;
    display: flex;
    justify-content: flex-end;
    gap: 0.75rem;
}
.modal-close-btn {
    background-color: #e5e7eb;
    padding: 0.5rem 1rem;
    border: 1px solid #d1d5db;
    border-radius: 0.375rem;
    font-weight: bold;
    cursor: pointer;
}
.modal-save-btn {
    background-color: #2563eb;
    color: white;
    padding: 0.5rem 1rem;
    border-radius: 0.375rem;
    font-weight: bold;
    cursor: pointer;
}
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}
.animate-fade-in {
    animation: fadeIn 0.4s ease-out forwards;
}
</style>


<script>
document.addEventListener('DOMContentLoaded', function() {

  //-----------------------------------------------------
  // Helpers
  //-----------------------------------------------------
  const $id = (x) => document.getElementById(x);
  const setTodayIfEmpty = (inputEl) => {
    if (inputEl && !inputEl.value) {
      const t = new Date();
      const yyyy = t.getFullYear();
      const mm = String(t.getMonth() + 1).padStart(2, '0');
      const dd = String(t.getDate()).padStart(2, '0');
      inputEl.value = `${yyyy}-${mm}-${dd}`;
    }
  };

  //-----------------------------------------------------
  // Lógica 1: Validação de Mudança de Status para "Serviço Pendente"
  //-----------------------------------------------------
  const statusSelect = $id('status_processo');
  const statusForm = $id('status-change-form'); // O formulário principal
  const requirementsModal = $id('modal-status-requirements'); // O novo modal
  const confirmStatusChangeBtn = $id('confirm-status-change');
  const cancelStatusChangeBtn = $id('cancel-status-change');

  // hiddens do form principal (usados só na mudança de status)
  const hEnvio = $id('hidden_data_inicio_traducao');
  const hPrazoDias = $id('hidden_prazo_dias');
  const hiddenDeadlineFields = [hEnvio, hPrazoDias];

  const registerOriginalName = (field) => {
    if (!field) return;
    if (field.dataset.originalName) return;
    const original = field.getAttribute('data-original-name') || field.getAttribute('name');
    if (original) {
      field.dataset.originalName = original;
    }
  };

  const disableHiddenDeadlineFields = () => {
    hiddenDeadlineFields.forEach(field => {
      if (!field) return;
      registerOriginalName(field);
      if (field.dataset.originalName) {
        field.removeAttribute('name');
      }
      field.value = '';
    });
  };

  const enableHiddenDeadlineFields = (fieldsToEnable = []) => {
    const uniqueFields = Array.from(new Set(fieldsToEnable.filter(Boolean)));
    uniqueFields.forEach(field => {
      registerOriginalName(field);
      const original = field.dataset.originalName;
      if (original) {
        field.setAttribute('name', original);
      }
    });
  };

  disableHiddenDeadlineFields();

  // valor vindo do PHP no template
  let originalStatus = (typeof <?php echo json_encode($statusLabel ?? null); ?> !== 'undefined')
    ? <?php echo json_encode($statusLabel ?? null); ?>
    : null;

  // Lógica para o novo modal de requisitos de status
  if (statusForm && statusSelect && requirementsModal) {
    statusForm.addEventListener('submit', function(e) {
      const newStatus = statusSelect.value;
      const requiresModal = (originalStatus === 'Orçamento' && newStatus === 'Serviço Pendente');

      if (requiresModal) {
        e.preventDefault(); // Impede o envio direto do formulário
        requirementsModal.classList.remove('hidden'); // Mostra o modal
      } else {
        disableHiddenDeadlineFields();
      }
    });
  }

  if (cancelStatusChangeBtn && statusSelect && requirementsModal) {
    cancelStatusChangeBtn.addEventListener('click', () => {
      statusSelect.value = originalStatus; // Restaura o valor original do select
      requirementsModal.classList.add('hidden'); // Esconde o modal
      disableHiddenDeadlineFields();
    });
  }

  if (confirmStatusChangeBtn) {
    confirmStatusChangeBtn.addEventListener('click', () => {
      const envioCampo = $id('modal_req_data_inicio_traducao');
      const prazoCampo = $id('modal_req_prazo_dias');
      const envio = envioCampo ? envioCampo.value : '';
      const prazoTexto = prazoCampo ? prazoCampo.value.trim() : '';
      const prazoNumero = prazoTexto === '' ? null : parseInt(prazoTexto, 10);

      const erros = [];
      if (prazoTexto !== '' && Number.isNaN(prazoNumero)) {
        erros.push('Informe um número inteiro válido para o prazo de tradução ou deixe o campo em branco.');
      }
      if (prazoNumero !== null && prazoNumero < 0) {
        erros.push('O prazo de tradução não pode ser negativo.');
      }
      if (prazoNumero !== null && prazoNumero > 0 && !envio) {
        erros.push('Informe a Data de Envio para o Tradutor.');
      }

      if (erros.length) {
        alert(erros.join('\n'));
        return;
      }

      disableHiddenDeadlineFields();

      const fieldsToEnable = [];
      if (hEnvio) {
        hEnvio.value = envio;
        fieldsToEnable.push(hEnvio);
      }
      if (hPrazoDias) {
        hPrazoDias.value = prazoTexto;
        fieldsToEnable.push(hPrazoDias);
      }

      enableHiddenDeadlineFields(fieldsToEnable);

      statusForm?.submit();
    });
  }

  //-----------------------------------------------------
  // Lógica 2: Modais de Edição de Etapas (AJAX)
  //-----------------------------------------------------
  function showFeedback(message, type = 'success') {
    const feedbackContainer = $id('feedback-container');
    if (!feedbackContainer) return;
    feedbackContainer.innerHTML = message;
    feedbackContainer.className = (type === 'success')
      ? 'bg-green-100 border-l-4 border-green-500 text-green-700 p-4'
      : 'bg-red-100 border-l-4 border-red-500 text-red-700 p-4';
    feedbackContainer.classList.remove('hidden');
    setTimeout(() => { feedbackContainer.classList.add('hidden'); }, 5000);
  }

  const modalTriggers = document.querySelectorAll('.modal-trigger');
  const modalCloseButtons = document.querySelectorAll('.modal-close-btn');
  const overlays = document.querySelectorAll('.modal-overlay');

  modalTriggers.forEach(button => {
    button.addEventListener('click', () => {
      const targetModal = $id(button.dataset.modalTarget);      
      if (targetModal) {
        // auto-preenche datas (se existirem) com hoje, quando abrir
        const autoDateInputs = targetModal.querySelectorAll('input[type="date"][data-autofill-today], #modal_data_inicio_traducao, #modal_data_envio_assinatura, #modal_data_devolucao_assinatura, #modal_data_envio_cartorio');
        autoDateInputs.forEach(inp => setTodayIfEmpty(inp));
        targetModal.classList.remove('hidden');
      }
    });
  });

  function closeModal(modal) {
    // Não fecha o modal de requisitos de status, pois ele tem sua própria lógica de cancelamento
    if (modal && modal.id !== 'modal-status-requirements') {
        modal.classList.add('hidden');
    }
  }

  modalCloseButtons.forEach(button => {
    // O botão de cancelar do modal de status já tem sua própria lógica, então não precisa de um listener aqui
    if (button.id !== 'cancel-status-change') {
        button.addEventListener('click', () => closeModal(button.closest('.modal-overlay')));
    }
  });

  overlays.forEach(overlay => overlay.addEventListener('click', (e) => {
    if (e.target === overlay) {
        // Não fecha o modal de requisitos de status ao clicar fora
        if (overlay.id !== 'modal-status-requirements') {
            closeModal(overlay);
        }
    }
  }));

  document.querySelectorAll('.modal-form').forEach(form => {
    form.addEventListener('submit', function(e) {
      e.preventDefault();
      const formData = new FormData(this);
      const url = this.getAttribute('data-action');
      const modal = this.closest('.modal-overlay');

      fetch(url, { method: 'POST', body: formData })
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            showFeedback(data.message || 'Atualizado com sucesso!', 'success');
            if (data.updated_data) {
              for (const [key, value] of Object.entries(data.updated_data)) {
                if (key.startsWith('status_')) continue;
                const displayElement = $id(`display-${key}`);
                if (displayElement) displayElement.innerHTML = value;
              }
              const statusBadge = $id('display-status-badge');
              if (statusBadge && data.updated_data.status_processo) {
                statusBadge.textContent = data.updated_data.status_processo;
                statusBadge.className = `px-3 py-1.5 inline-flex text-sm leading-5 font-semibold rounded-full ${data.updated_data.status_processo_classes}`;
              }
            }
            closeModal(modal);
          } else {
            showFeedback(data.message || 'Ocorreu um erro.', 'error');
          }
        })
        .catch((error) => {
          console.error('Fetch error:', error);
          showFeedback('Erro de conexão. Verifique o console.', 'error');
        });
    });
  });

  //-----------------------------------------------------
  // Lógica 3: Comentários
  //-----------------------------------------------------
  const commentForm = $id('comment-form');
  if (commentForm) {
    const commentTextarea = commentForm.querySelector('textarea');
    const commentSubmitButton = commentForm.querySelector('button[type="submit"]');
    let isCommentSubmitting = false;

    if (commentTextarea) {
      commentTextarea.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' && !event.shiftKey) {
          event.preventDefault();
          if (typeof commentForm.requestSubmit === 'function') {
            commentForm.requestSubmit();
          } else {
            commentForm.dispatchEvent(new Event('submit', { cancelable: true }));
          }
        }
      });
    }

    commentForm.addEventListener('submit', function(e) {
      e.preventDefault();
      if (isCommentSubmitting) {
        return;
      }

      const formData = new FormData(this);
      const url = 'processos.php?action=store_comment_ajax';
      isCommentSubmitting = true;
      if (commentSubmitButton) {
        commentSubmitButton.disabled = true;
        commentSubmitButton.classList.add('opacity-60', 'cursor-not-allowed');
      }

      fetch(url, { method: 'POST', body: formData })
        .then(response => response.json())
        .then(data => {
          if (data.success && data.comment) {
            if (commentTextarea) {
              commentTextarea.value = '';
            }
            const commentsList = $id('comments-list');
            const noCommentsMessage = $id('no-comments');
            if (noCommentsMessage) noCommentsMessage.remove();
            const newCommentHtml = `
              <div class="bg-gray-50 p-3 rounded-lg animate-fade-in">
                <div class="flex items-center justify-between mb-1">
                  <p class="text-sm font-semibold text-gray-800">${data.comment.author}</p>
                  <p class="text-xs text-gray-500">${data.comment.date}</p>
                </div>
                <p class="text-sm text-gray-600">${data.comment.text}</p>
              </div>`;
            if (commentsList) {
              commentsList.insertAdjacentHTML('afterbegin', newCommentHtml);
              commentsList.scrollTop = 0;
            }
            showFeedback('Comentário publicado!', 'success');
          } else {
            showFeedback(data.message || 'Não foi possível publicar.', 'error');
          }
        })
        .catch(error => {
          console.error('Fetch error:', error);
          showFeedback('Erro de conexão.', 'error');
        })
        .finally(() => {
          isCommentSubmitting = false;
          if (commentSubmitButton) {
            commentSubmitButton.disabled = false;
            commentSubmitButton.classList.remove('opacity-60', 'cursor-not-allowed');
          }
        });
    });
  }

});
</script>
