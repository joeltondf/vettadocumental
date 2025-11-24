<?php
$baseAppUrl = rtrim(APP_URL, '/');
$leads = $leads ?? [];
$sdrs = $sdrs ?? [];
$vendors = $vendors ?? [];
$statusOptions = $statusOptions ?? [];
$filters = $filters ?? [];

function formatDate(?string $date): string
{
    if (empty($date)) {
        return '--';
    }

    $timestamp = strtotime($date);
    return $timestamp ? date('d/m/Y', $timestamp) : '--';
}

function formatDateTime(?string $date): string
{
    if (empty($date)) {
        return '--';
    }

    $timestamp = strtotime($date);
    return $timestamp ? date('d/m/Y H:i', $timestamp) : '--';
}

function formatCurrency(?float $value): string
{
    if ($value === null) {
        return '--';
    }

    return 'R$ ' . number_format((float)$value, 2, ',', '.');
}

function daysInTreatment(?string $startDate, ?string $endDate = null): string
{
    if (empty($startDate)) {
        return '--';
    }

    $start = new DateTime($startDate);
    $end = $endDate ? new DateTime($endDate) : new DateTime();

    return (string) $start->diff($end)->days;
}
?>

<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Leads em tratamento</h1>
        <p class="mt-1 text-gray-600">Acompanhe todas as prospecções com visão de SDR, vendedor, processo e comissões.</p>
    </div>
    <a href="<?php echo $baseAppUrl; ?>/gerente_dashboard.php" class="inline-flex items-center bg-gray-200 hover:bg-gray-300 text-gray-800 font-semibold py-2.5 px-4 rounded-lg shadow-md transition-colors">
        <i class="fas fa-arrow-left mr-2"></i> Voltar ao painel
    </a>
</div>

<div class="bg-white p-6 rounded-lg shadow-md border border-gray-200 mb-6">
    <form method="GET" action="<?php echo $baseAppUrl; ?>/gerente/dashboard/leads.php" class="grid grid-cols-1 md:grid-cols-5 gap-4">
        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">SDR</label>
            <select name="sdr_id" class="w-full border-gray-300 rounded-lg">
                <option value="">Todos</option>
                <?php foreach ($sdrs as $sdr): ?>
                    <option value="<?php echo (int)$sdr['id']; ?>" <?php echo !empty($filters['sdr_id']) && (int)$filters['sdr_id'] === (int)$sdr['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($sdr['nome_completo']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">Vendedor</label>
            <select name="vendor_id" class="w-full border-gray-300 rounded-lg">
                <option value="">Todos</option>
                <?php foreach ($vendors as $vendor): ?>
                    <option value="<?php echo (int)$vendor['user_id']; ?>" <?php echo !empty($filters['vendor_id']) && (int)$filters['vendor_id'] === (int)$vendor['user_id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($vendor['nome_vendedor']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">Status do lead</label>
            <select name="status" class="w-full border-gray-300 rounded-lg">
                <option value="">Todos</option>
                <?php foreach ($statusOptions as $status): ?>
                    <option value="<?php echo htmlspecialchars($status); ?>" <?php echo ($filters['status'] ?? '') === $status ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($status); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">Data inicial</label>
            <input type="date" name="data_inicio" value="<?php echo htmlspecialchars($filters['data_inicio'] ?? ''); ?>" class="w-full border-gray-300 rounded-lg" />
        </div>
        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">Data final</label>
            <input type="date" name="data_fim" value="<?php echo htmlspecialchars($filters['data_fim'] ?? ''); ?>" class="w-full border-gray-300 rounded-lg" />
        </div>
        <div class="md:col-span-5 flex items-center gap-3">
            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg shadow hover:bg-blue-700 transition-colors">Filtrar</button>
            <a href="<?php echo $baseAppUrl; ?>/gerente/dashboard/leads.php" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-lg shadow hover:bg-gray-300 transition-colors">Limpar filtros</a>
        </div>
    </form>
</div>

<div class="bg-white p-6 rounded-lg shadow-md border border-gray-200">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3 mb-4">
        <h2 class="text-lg font-semibold text-gray-800">Lista de leads</h2>
        <div class="relative w-full md:w-80">
            <span class="absolute inset-y-0 left-3 flex items-center text-gray-400"><i class="fas fa-search"></i></span>
            <input type="text" id="lead-search" placeholder="Buscar por nome, cliente ou status" class="w-full pl-10 pr-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
        </div>
    </div>

    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200" id="manager-lead-table">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">ID</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Data de Prospecção / Última atualização</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Nome do Lead</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">SDR responsável</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Status do lead</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Vendedor designado</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Cliente</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Status do processo</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Comissão SDR</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Comissão Vendedor</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Status do cliente</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Dias em tratamento</th>
                    <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase">Ações</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200" data-lead-body>
                <?php if (empty($leads)): ?>
                    <tr>
                        <td colspan="13" class="px-4 py-6 text-center text-gray-500 text-sm">Nenhum lead encontrado para os filtros selecionados.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($leads as $lead): ?>
                        <?php
                            $leadId = (int) ($lead['id'] ?? 0);
                            $clientName = $lead['nome_cliente'] ?? 'Cliente não informado';
                            $processId = $lead['processo_id'] ?? null;
                            $statusLead = $lead['status'] ?? 'Sem status';
                            $clienteStatus = ((int)($lead['is_prospect'] ?? 1) === 1) ? 'Prospect' : 'Cliente convertido';
                            $conversionDate = !empty($lead['data_conversao']) ? formatDate($lead['data_conversao']) : null;
                            $treatmentDays = daysInTreatment($lead['data_prospeccao'] ?? null, $lead['data_ultima_atualizacao'] ?? null);
                        ?>
                        <tr data-lead-row>
                            <td class="px-4 py-3 text-sm font-semibold text-gray-700">#<?php echo $leadId; ?></td>
                            <td class="px-4 py-3 text-sm text-gray-600">
                                <div class="font-semibold">Prospecção: <?php echo formatDate($lead['data_prospeccao'] ?? null); ?></div>
                                <div class="text-xs text-gray-500">Última atualização: <?php echo formatDateTime($lead['data_ultima_atualizacao'] ?? null); ?></div>
                            </td>
                            <td class="px-4 py-3 text-sm font-semibold text-gray-700"><?php echo htmlspecialchars($lead['nome_prospecto'] ?? 'Lead'); ?></td>
                            <td class="px-4 py-3 text-sm text-gray-600"><?php echo htmlspecialchars($lead['sdrName'] ?? 'Sem SDR'); ?></td>
                            <td class="px-4 py-3 text-sm">
                                <span class="inline-flex items-center px-2 py-1 rounded-full bg-blue-50 text-blue-600"><?php echo htmlspecialchars($statusLead); ?></span>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-600"><?php echo htmlspecialchars($lead['vendorName'] ?? 'Aguardando vendedor'); ?></td>
                            <td class="px-4 py-3 text-sm text-gray-600"><?php echo htmlspecialchars($clientName); ?></td>
                            <td class="px-4 py-3 text-sm text-gray-600"><?php echo htmlspecialchars($lead['status_processo'] ?? 'Sem processo'); ?></td>
                            <td class="px-4 py-3 text-sm text-gray-600"><?php echo formatCurrency(isset($lead['sdr_commission']) ? (float)$lead['sdr_commission'] : null); ?></td>
                            <td class="px-4 py-3 text-sm text-gray-600"><?php echo formatCurrency(isset($lead['vendor_commission']) ? (float)$lead['vendor_commission'] : null); ?></td>
                            <td class="px-4 py-3 text-sm text-gray-600">
                                <?php echo htmlspecialchars($clienteStatus); ?>
                                <?php if ($conversionDate): ?>
                                    <p class="text-xs text-gray-500">Convertido em <?php echo $conversionDate; ?></p>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-600 text-center"><?php echo $treatmentDays; ?></td>
                            <td class="px-4 py-3 text-center text-sm">
                                <div class="flex items-center justify-center gap-2">
                                    <a href="<?php echo $baseAppUrl; ?>/crm/prospeccoes/detalhes.php?id=<?php echo $leadId; ?>" class="px-3 py-1 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors text-xs">Ver prospecção</a>
                                    <?php if (!empty($processId)): ?>
                                        <a href="<?php echo $baseAppUrl; ?>/processos.php?action=view&id=<?php echo (int)$processId; ?>" class="px-3 py-1 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors text-xs">Ver processo</a>
                                    <?php endif; ?>
                                    <?php if ((int)($lead['is_prospect'] ?? 1) === 1 && !empty($lead['cliente_id'] ?? null)): ?>
                                        <a href="<?php echo $baseAppUrl; ?>/clientes.php?action=edit&id=<?php echo (int)$lead['cliente_id']; ?>" class="px-3 py-1 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors text-xs">Promover a cliente</a>
                                    <?php endif; ?>
                                </div>
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
        const rows = Array.from(document.querySelectorAll('#manager-lead-table [data-lead-row]'));

        searchInput?.addEventListener('input', () => {
            const term = searchInput.value.trim().toLowerCase();
            rows.forEach(row => {
                const haystack = row.innerText.toLowerCase();
                row.classList.toggle('hidden', term !== '' && !haystack.includes(term));
            });
        });
    });
</script>
