<?php
if (!function_exists('client_portal_normalize_status_info')) {
    function client_portal_normalize_status_info(?string $status): array
    {
        $normalized = mb_strtolower(trim((string) $status));

        if ($normalized === '') {
            return ['normalized' => '', 'label' => 'N/A'];
        }

        $aliases = [
            'orcamento' => 'orçamento',
            'orcamento pendente' => 'orçamento pendente',
            'serviço pendente' => 'pendente',
            'servico pendente' => 'pendente',
            'serviço em andamento' => 'em andamento',
            'servico em andamento' => 'em andamento',
            'finalizado' => 'concluído',
            'finalizada' => 'concluído',
            'concluido' => 'concluído',
            'concluida' => 'concluído',
            'arquivado' => 'cancelado',
            'arquivada' => 'cancelado',
        ];

        if (isset($aliases[$normalized])) {
            $normalized = $aliases[$normalized];
        }

        $labels = [
            'orçamento' => 'Orçamento',
            'orçamento pendente' => 'Orçamento Pendente',
            'aprovado' => 'Aprovado',
            'em andamento' => 'Em andamento',
            'concluído' => 'Concluído',
            'cancelado' => 'Cancelado',
            'pendente' => 'Pendente',
        ];

        $label = $labels[$normalized] ?? ($status === '' ? 'N/A' : $status);

        return ['normalized' => $normalized, 'label' => $label];
    }
}
?>

    <h1 class="text-2xl font-bold text-gray-800 mb-2">Meus Processos</h1>
    <p class="text-gray-600 mb-6">Acompanhe aqui o andamento de todos os seus serviços contratados.</p>

    <div class="bg-white shadow-md rounded-lg overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Processo</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Data de Entrada</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (empty($processos)): ?>
                        <tr>
                            <td colspan="3" class="px-6 py-12 text-center text-gray-500">Você ainda não possui processos cadastrados.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($processos as $processo): ?>
                            <?php
                                $statusInfo = client_portal_normalize_status_info($processo['status_processo'] ?? '');
                                $statusLabel = $statusInfo['label'];
                                $statusNormalized = $statusInfo['normalized'];
                                $statusClasses = 'bg-gray-100 text-gray-800';

                                switch ($statusNormalized) {
                                    case 'orçamento':
                                        $statusClasses = 'bg-yellow-100 text-yellow-800';
                                        break;
                                    case 'orçamento pendente':
                                        $statusClasses = 'bg-yellow-200 text-yellow-900';
                                        break;
                                    case 'aprovado':
                                        $statusClasses = 'bg-blue-100 text-blue-800';
                                        break;
                                    case 'em andamento':
                                        $statusClasses = 'bg-indigo-100 text-indigo-800';
                                        break;
                                    case 'pendente':
                                        $statusClasses = 'bg-orange-100 text-orange-800';
                                        break;
                                    case 'concluído':
                                        $statusClasses = 'bg-green-100 text-green-800';
                                        break;
                                    case 'cancelado':
                                        $statusClasses = 'bg-red-100 text-red-800';
                                        break;
                                }
                            ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap font-medium text-gray-800"><?php echo htmlspecialchars($processo['titulo']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-gray-600"><?php echo date('d/m/Y', strtotime($processo['data_criacao'])); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $statusClasses; ?>">
                                        <?php echo htmlspecialchars($statusLabel); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
