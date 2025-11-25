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
    <div class="flex flex-col gap-3 mb-4">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
            <h2 class="text-lg font-semibold text-gray-800">Leads em tratamento</h2>
            <button id="clear-filters" type="button" class="inline-flex items-center self-start md:self-center bg-white border border-gray-300 text-gray-700 hover:bg-gray-50 px-3 py-2 rounded-lg text-sm font-medium shadow-sm">
                <i class="fas fa-undo mr-2"></i> Limpar filtros
            </button>
        </div>
        <div class="bg-gray-50 border border-gray-200 rounded-lg p-4 shadow-inner">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
                <div class="md:col-span-2">
                    <label for="lead-search" class="block text-xs font-semibold text-gray-600 mb-1 uppercase tracking-wide">Busca rápida</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-3 flex items-center text-gray-400">
                            <i class="fas fa-search"></i>
                        </span>
                        <input type="text" id="lead-search" placeholder="Filtrar por qualquer coluna" class="w-full pl-10 pr-4 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-sm text-gray-700 bg-white">
                    </div>
                </div>
                <div>
                    <label for="lead-name" class="block text-xs font-semibold text-gray-600 mb-1 uppercase tracking-wide">Nome do lead</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-3 flex items-center text-gray-400">
                            <i class="fas fa-user"></i>
                        </span>
                        <input type="text" id="lead-name" placeholder="Ex.: João Silva" class="w-full pl-10 pr-4 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-sm text-gray-700 bg-white">
                    </div>
                </div>
                <div>
                    <label for="lead-status" class="block text-xs font-semibold text-gray-600 mb-1 uppercase tracking-wide">Status do lead</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-3 flex items-center text-gray-400">
                            <i class="fas fa-flag"></i>
                        </span>
                        <input type="text" id="lead-status" placeholder="Ex.: Contatado" class="w-full pl-10 pr-4 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-sm text-gray-700 bg-white">
                    </div>
                </div>
                <div>
                    <label for="lead-vendor" class="block text-xs font-semibold text-gray-600 mb-1 uppercase tracking-wide">Vendedor</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-3 flex items-center text-gray-400">
                            <i class="fas fa-user-tie"></i>
                        </span>
                        <input type="text" id="lead-vendor" placeholder="Ex.: Maria" class="w-full pl-10 pr-4 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-sm text-gray-700 bg-white">
                    </div>
                </div>
            </div>
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
                        <?php
                            $leadNameFilter = strtolower($leadName);
                            $statusFilter = strtolower($statusLead);
                            $vendorFilter = strtolower($vendorName);
                        ?>
                        <tr class="hover:bg-gray-50" data-lead-id="<?= $leadId ?>" data-lead-name="<?= htmlspecialchars($leadNameFilter, ENT_QUOTES, 'UTF-8') ?>" data-lead-status="<?= htmlspecialchars($statusFilter, ENT_QUOTES, 'UTF-8') ?>" data-lead-vendor="<?= htmlspecialchars($vendorFilter, ENT_QUOTES, 'UTF-8') ?>">
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
                    <tr id="no-results-row" class="hidden">
                        <td colspan="9" class="text-center py-6 text-gray-600">Nenhum lead encontrado com os filtros aplicados.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const searchInput = document.getElementById('lead-search');
        const nameInput = document.getElementById('lead-name');
        const statusInput = document.getElementById('lead-status');
        const vendorInput = document.getElementById('lead-vendor');
        const clearButton = document.getElementById('clear-filters');
        const rows = Array.from(document.querySelectorAll('tbody tr[data-lead-id]'));
        const noResultsRow = document.getElementById('no-results-row');

        const normalize = (value) => (value || '').trim().toLowerCase();

        const applyFilters = () => {
            const globalTerm = normalize(searchInput?.value);
            const nameTerm = normalize(nameInput?.value);
            const statusTerm = normalize(statusInput?.value);
            const vendorTerm = normalize(vendorInput?.value);
            let visibleCount = 0;

            rows.forEach(row => {
                const haystack = row.innerText.toLowerCase();
                const name = row.dataset.leadName || '';
                const status = row.dataset.leadStatus || '';
                const vendor = row.dataset.leadVendor || '';

                const matchesGlobal = !globalTerm || haystack.includes(globalTerm);
                const matchesName = !nameTerm || name.includes(nameTerm);
                const matchesStatus = !statusTerm || status.includes(statusTerm);
                const matchesVendor = !vendorTerm || vendor.includes(vendorTerm);

                const shouldShow = matchesGlobal && matchesName && matchesStatus && matchesVendor;
                row.classList.toggle('hidden', !shouldShow);
                if (shouldShow) {
                    visibleCount += 1;
                }
            });

            if (noResultsRow) {
                noResultsRow.classList.toggle('hidden', visibleCount !== 0);
            }
        };

        [searchInput, nameInput, statusInput, vendorInput].forEach(input => {
            input?.addEventListener('input', applyFilters);
        });

        clearButton?.addEventListener('click', () => {
            if (searchInput) searchInput.value = '';
            if (nameInput) nameInput.value = '';
            if (statusInput) statusInput.value = '';
            if (vendorInput) vendorInput.value = '';
            applyFilters();
        });

        applyFilters();
    });
</script>
