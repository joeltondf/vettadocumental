<?php
require_once __DIR__ . '/../../../utils/DashboardProcessFormatter.php';

$deadlineColors = $deadlineColors ?? [];
$showActions = $showActions ?? true;
$showProgress = $showProgress ?? false;
$highlightAnimations = $highlightAnimations ?? false;
$allowLinks = $allowLinks ?? true;
$showRowStatusColors = $showRowStatusColors ?? true;
$progressTrackClass = $progressTrackClass ?? 'bg-slate-200';

foreach ($processes as $processo):
    $statusInfo = DashboardProcessFormatter::normalizeStatusInfo($processo['status_processo'] ?? '');
    $statusNormalized = $statusInfo['normalized'];
    $rowClass = $showRowStatusColors ? DashboardProcessFormatter::getRowClass($statusNormalized) : '';
    $deadlineDescriptor = DashboardProcessFormatter::buildDeadlineDescriptor($processo, $deadlineColors);
    $serviceBadges = DashboardProcessFormatter::getServiceBadges($processo['categorias_servico'] ?? '');
    $deadlineClass = $deadlineDescriptor['class'];
    $deadlineLabel = $deadlineDescriptor['label'];
    $progressValue = $deadlineDescriptor['progress'];
    $progressClass = $deadlineDescriptor['progress_class'] ?? 'bg-slate-500';
    $rowHighlight = '';

    if ($highlightAnimations && in_array($deadlineDescriptor['state'], ['overdue', 'due_today'], true)) {
        $rowHighlight = 'animate-pulse';
    }
?>
<tr class="<?php echo trim($rowClass . ' ' . $rowHighlight); ?>" data-process-id="<?php echo (int) ($processo['id'] ?? 0); ?>">
    <td class="px-3 py-1 whitespace-nowrap text-xs font-medium">
        <?php
            $fullTitle = htmlspecialchars(mb_strtoupper($processo['titulo'] ?? 'N/A'));
            $shortTitle = htmlspecialchars(mb_strtoupper(mb_strimwidth($processo['titulo'] ?? 'N/A', 0, 25, '...')));
        ?>
        <?php if ($allowLinks): ?>
            <a href="processos.php?action=view&id=<?php echo (int) ($processo['id'] ?? 0); ?>" class="text-blue-600 hover:text-blue-800 hover:underline truncate block" title="<?php echo $fullTitle; ?>">
                <?php echo $shortTitle; ?>
            </a>
        <?php else: ?>
            <span class="block truncate" title="<?php echo $fullTitle; ?>"><?php echo $shortTitle; ?></span>
        <?php endif; ?>
    </td>
    <td class="px-3 py-1 whitespace-nowrap text-xs text-gray-500 truncate" title="<?php echo htmlspecialchars(mb_strtoupper($processo['nome_cliente'] ?? 'N/A')); ?>">
        <span class="block truncate" title="<?php echo htmlspecialchars(mb_strtoupper($processo['nome_cliente'] ?? 'N/A')); ?>">
            <?php echo htmlspecialchars(mb_strtoupper(mb_strimwidth($processo['nome_cliente'] ?? 'N/A', 0, 20, '...'))); ?>
        </span>
    </td>
    <td class="px-3 py-1 whitespace-nowrap text-xs text-gray-500 text-center">
        <?php echo (int) ($processo['total_documentos_soma'] ?? 0); ?>
    </td>
    <td class="px-3 py-1 whitespace-nowrap text-xs text-gray-500">
        <?php
            $osOmie = $processo['os_numero_omie'] ?? null;
            $formattedOmie = $osOmie ? substr((string) $osOmie, -5) : 'Aguardando Omie';
            echo htmlspecialchars($formattedOmie);
        ?>
    </td>
    <td class="px-3 py-1 whitespace-nowrap text-xs text-gray-500">
        <?php foreach ($serviceBadges as $badge): ?>
            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $badge['class']; ?> mr-1"><?php echo $badge['label']; ?></span>
        <?php endforeach; ?>
    </td>
    <td class="px-3 py-1 whitespace-nowrap text-xs text-gray-500">
        <?php echo !empty($processo['data_criacao']) ? date('d/m/Y', strtotime($processo['data_criacao'])) : 'N/A'; ?>
    </td>
    <td class="px-3 py-1 whitespace-nowrap text-xs text-gray-500">
        <?php echo !empty($processo['data_inicio_traducao']) ? date('d/m/Y', strtotime($processo['data_inicio_traducao'])) : 'N/A'; ?>
    </td>
    <td class="px-3 py-1 whitespace-nowrap text-xs font-medium">
        <div class="flex items-center gap-3">
            <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $deadlineClass; ?>">
                <?php echo htmlspecialchars($deadlineLabel); ?>
            </span>
            <?php if ($showProgress && $progressValue !== null): ?>
                <div class="flex-1 h-2 rounded-full overflow-hidden <?php echo htmlspecialchars($progressTrackClass); ?>">
                    <div class="h-2 <?php echo htmlspecialchars($progressClass); ?> transition-all duration-500" style="width: <?php echo $progressValue; ?>%"></div>
                </div>
            <?php endif; ?>
        </div>
    </td>
    <?php if ($showActions): ?>
    <td class="px-3 py-1 whitespace-nowrap text-center text-xs font-medium">
        <div class="relative inline-block p-1">
            <svg class="w-6 h-6 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                 data-tooltip-trigger data-process-id="<?php echo (int) ($processo['id'] ?? 0); ?>"
                 data-tooltip-content-json='<?php
                    $statusAssinaturaTexto = 'Pendente';
                    $statusAssinaturaClasse = 'bg-yellow-100 text-yellow-800';
                    if (!empty($processo['data_devolucao_assinatura'])) {
                        $statusAssinaturaTexto = 'Enviado';
                        $statusAssinaturaClasse = 'bg-green-100 text-green-800';
                    }
                    $nomeTradutor = htmlspecialchars($processo['nome_tradutor'] ?? 'Não definido', ENT_QUOTES, 'UTF-8');
                    $modalidadeTraducao = htmlspecialchars($processo['traducao_modalidade'] ?? 'N/A', ENT_QUOTES, 'UTF-8');
                    $envioCartorio = !empty($processo['data_envio_cartorio']) ? date('d/m/Y', strtotime($processo['data_envio_cartorio'])) : 'Pendente';
                    $tooltipHtml = '<div class="flex flex-col gap-2 text-left whitespace-normal leading-relaxed text-sm">'
                        . '<p class="font-semibold text-xs uppercase tracking-wide text-gray-200 border-b border-gray-600 pb-2">Detalhes Rápidos</p>'
                        . '<p><strong>Tradutor:</strong> ' . $nomeTradutor . '</p>'
                        . '<p><strong>Modalidade:</strong> ' . $modalidadeTraducao . '</p>'
                        . '<p><strong>Status Ass.:</strong> <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full '
                        . $statusAssinaturaClasse . '">' . $statusAssinaturaTexto . '</span></p>'
                        . '<p><strong>Envio Cartório:</strong> ' . $envioCartorio . '</p>'
                        . '</div>'
                        . '<div class="tooltip-arrow" data-tooltip-arrow></div>';
                    echo $tooltipHtml;
                 ?>'>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M13 16h-1v-4h-1m1-4h.01M12 18.5a6.5 6.5 0 110-13 6.5 6.5 0 010 13z" />
            </svg>
        </div>
    </td>
    <?php endif; ?>
</tr>
<?php endforeach; ?>
