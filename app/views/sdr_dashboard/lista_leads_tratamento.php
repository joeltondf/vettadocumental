<?php $baseAppUrl = rtrim(APP_URL, '/'); ?>
<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Leads em Tratamento</h1>
        <p class="mt-1 text-gray-600">Veja como os leads estão sendo tratados e o status de cada um.</p>
    </div>
    <a href="<?= $baseAppUrl; ?>/sdr_dashboard.php" class="inline-flex items-center bg-gray-200 hover:bg-gray-300 text-gray-800 font-semibold py-2.5 px-4 rounded-lg shadow-md transition-colors">
        <i class="fas fa-arrow-left mr-2"></i> Voltar ao painel
    </a>
</div>
<div class="bg-white p-6 rounded-lg shadow-md border">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3 mb-4">
        <h2 class="text-lg font-semibold text-gray-800">Leads em tratamento</h2>
        <div class="relative w-full md:w-80">
            <span class="absolute inset-y-0 left-3 flex items-center text-gray-400">
                <i class="fas fa-search"></i>
            </span>
            <input type="text" id="lead-search" placeholder="Filtrar por nome, cliente ou status" class="w-full pl-10 pr-4 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-sm text-gray-700">
        </div>
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Lead</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Cliente</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Status Lead</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Vendedor</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Status Processo</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Atualização</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Dias sem atualização</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Dias proc. sem atualização</th>
                    <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">Ações</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if (empty($leads)): ?>
                    <tr>
                        <td colspan="9" class="text-center py-6">Nenhum lead em tratamento no momento.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($leads as $lead): ?>
                        <?php
                            $leadId = (int) ($lead['id'] ?? 0);
                            $leadName = $lead['nome_prospecto'] ?? 'Lead';
                            $clientName = $lead['nome_cliente'] ?? 'Cliente não informado';
                            $statusLead = $lead['status'] ?? 'Sem status';
                            $vendorName = $lead['vendor_name'] ?? 'Aguardando vendedor';
                            $statusProcesso = $lead['status_processo'] ?? '--';
                            $updatedAt = !empty($lead['data_ultima_atualizacao']) ? date('d/m/Y H:i', strtotime($lead['data_ultima_atualizacao'])) : '--';
                            $diasSemAtualizacao = (int) ($lead['dias_desde_atualizacao'] ?? 0);
                            $diasProc = isset($lead['dias_processo_sem_atualizacao']) ? (int) $lead['dias_processo_sem_atualizacao'] : null;
                        ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 text-sm font-semibold text-gray-700"><?= htmlspecialchars($leadName) ?></td>
                            <td class="px-4 py-3 text-sm text-gray-600"><?= htmlspecialchars($clientName) ?></td>
                            <td class="px-4 py-3 text-sm text-gray-600"><?= htmlspecialchars($statusLead) ?></td>
                            <td class="px-4 py-3 text-sm text-gray-600"><?= htmlspecialchars($vendorName) ?></td>
                            <td class="px-4 py-3 text-sm text-gray-600"><?= htmlspecialchars($statusProcesso ?: '--') ?></td>
                            <td class="px-4 py-3 text-sm text-right text-gray-600"><?= $updatedAt ?></td>
                            <td class="px-4 py-3 text-sm text-right text-gray-600"><?= $diasSemAtualizacao ?> dia(s)</td>
                            <td class="px-4 py-3 text-sm text-right text-gray-600"><?= $diasProc !== null ? ($diasProc . ' dia(s)') : '--' ?></td>
                            <td class="px-4 py-3 text-sm text-center">
                                <a href="<?= $baseAppUrl; ?>/crm/prospeccoes/detalhes.php?id=<?= $leadId ?>" class="px-3 py-1 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 text-xs">Histórico</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const searchInput = document.getElementById('lead-search');
        const rows = Array.from(document.querySelectorAll('tbody tr'));
        searchInput?.addEventListener('input', () => {
            const term = searchInput.value.trim().toLowerCase();
            rows.forEach(row => {
                const haystack = row.innerText.toLowerCase();
                row.classList.toggle('hidden', term !== '' && !haystack.includes(term));
            });
        });
    });
</script>
