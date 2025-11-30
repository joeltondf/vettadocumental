<?php
$baseAppUrl = rtrim(APP_URL, '/');
$valoresLeads     = $valoresLeads ?? [];
$labelsLeads      = $labelsLeads ?? [];
$valoresPrevisto  = $valoresPrevisto ?? [];
$labelsPrevisto   = $labelsPrevisto ?? [];
$valoresAprovados = $valoresAprovados ?? [];
$labelsAprovados  = $labelsAprovados ?? [];
$valoresFinalizados = $valoresFinalizados ?? [];
$labelsFinalizados  = $labelsFinalizados ?? [];
$commissionReportUrl = $baseAppUrl . '/gerente_dashboard.php?action=listar_comissoes';
$sdrSummary = $sdrSummary ?? [];
$sdrVendorSummary = $sdrVendorSummary ?? [];
$vendorCommissionReport = $vendorCommissionReport ?? [];
$sdrLeadStatusSummary = $sdrLeadStatusSummary ?? [];
$vendorLeadTreatmentSummary = $vendorLeadTreatmentSummary ?? [];
$servicesSummary = $servicesSummary ?? [
    'totalIniciados' => 0,
    'totalFinalizados' => 0,
    'totalPendentes' => 0,
    'valorFinalizado' => 0.0,
    'detalhes' => [],
];
$budgetOverview = $budgetOverview ?? [
    'budgetCount' => 0,
    'budgetValue' => 0.0,
    'pipelineCount' => 0,
    'pipelineValue' => 0.0,
    'closedCount' => 0,
    'closedValue' => 0.0,
    'averageBudgetValue' => 0.0,
];
$vendorPerformanceRanking = $vendorPerformanceRanking ?? [];
$sdrPerformanceRanking = $sdrPerformanceRanking ?? [];

$totalSdrLeads = array_sum(array_column($sdrSummary, 'totalLeads'));
$assignedSdrLeads = array_sum(array_column($sdrSummary, 'assignedLeads'));
$convertedSdrLeads = array_sum(array_column($sdrSummary, 'convertedLeads'));
$totalSdrAppointments = array_sum(array_column($sdrSummary, 'totalAppointments'));
$averageSdrConversion = $totalSdrLeads > 0 ? ($convertedSdrLeads / $totalSdrLeads) * 100 : 0;
?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3 mb-5">
  <h1 class="text-2xl font-bold text-gray-800"><?= htmlspecialchars($pageTitle) ?></h1>
  <a href="<?= $baseAppUrl; ?>/gerente/dashboard/leads.php" class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-lg shadow hover:bg-blue-700 transition-colors">
    <i class="fas fa-users-cog"></i>
    <span>Leads em tratamento</span>
  </a>
  <a href="<?= $commissionReportUrl; ?>" class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white rounded-lg shadow hover:bg-indigo-700 transition-colors">
    <i class="fas fa-file-invoice-dollar"></i>
    <span>Relatório de comissões</span>
  </a>
</div>

<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
  <div class="p-4 bg-white shadow rounded">
    <h3 class="text-sm font-semibold text-gray-500">Valor em Orçamento (R$)</h3>
    <p class="text-xl font-bold text-green-600">
      R$ <?= number_format($budgetOverview['budgetValue'], 2, ',', '.') ?>
    </p>
    <p class="text-xs text-gray-500 mt-1"><?= (int) $budgetOverview['budgetCount'] ?> orçamentos</p>
  </div>
  <div class="p-4 bg-white shadow rounded">
    <h3 class="text-sm font-semibold text-gray-500">Valor em Pipeline (R$)</h3>
    <p class="text-xl font-bold text-blue-600">
      R$ <?= number_format($budgetOverview['pipelineValue'], 2, ',', '.') ?>
    </p>
    <p class="text-xs text-gray-500 mt-1"><?= (int) $budgetOverview['pipelineCount'] ?> serviços</p>
  </div>
  <div class="p-4 bg-white shadow rounded">
    <h3 class="text-sm font-semibold text-gray-500">Valor Fechado (R$)</h3>
    <p class="text-xl font-bold text-purple-600">
      R$ <?= number_format($budgetOverview['closedValue'], 2, ',', '.') ?>
    </p>
    <p class="text-xs text-gray-500 mt-1"><?= (int) $budgetOverview['closedCount'] ?> fechamentos</p>
  </div>
  <div class="p-4 bg-white shadow rounded">
    <h3 class="text-sm font-semibold text-gray-500">Ticket Médio dos Orçamentos (R$)</h3>
    <p class="text-xl font-bold text-orange-600">
      R$ <?= number_format($budgetOverview['averageBudgetValue'], 2, ',', '.') ?>
    </p>
  </div>
</div>

<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
  <div class="p-4 bg-white shadow rounded">
    <h3 class="text-sm font-semibold text-gray-500">Leads SRD Gerados</h3>
    <p class="text-xl font-bold text-indigo-600">
      <?= $totalSdrLeads ?>
    </p>
  </div>
  <div class="p-4 bg-white shadow rounded">
    <h3 class="text-sm font-semibold text-gray-500">Leads Direcionados</h3>
    <p class="text-xl font-bold text-teal-600">
      <?= $assignedSdrLeads ?>
    </p>
  </div>
  <div class="p-4 bg-white shadow rounded">
    <h3 class="text-sm font-semibold text-gray-500">Leads Convertidos</h3>
    <p class="text-xl font-bold text-rose-600">
      <?= $convertedSdrLeads ?>
    </p>
    <p class="text-xs text-gray-500 mt-1">Taxa média <?= number_format($averageSdrConversion, 2, ',', '.') ?>%</p>
  </div>
  <div class="p-4 bg-white shadow rounded">
    <h3 class="text-sm font-semibold text-gray-500">Agendamentos Marcados</h3>
    <p class="text-xl font-bold text-gray-700">
      <?= $totalSdrAppointments ?>
    </p>
  </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 gap-8 ">
  <div class="bg-white p-4 shadow rounded h-64">
    <h3 class="text-lg font-semibold mb-2">Leads por Vendedor</h3>
    <canvas id="leadsChart" class="w-full h-full"></canvas>
  </div>
  <div class="bg-white p-4 shadow rounded h-64">
    <h3 class="text-lg font-semibold mb-2">Vendas Previstas por Vendedor (R$)</h3>
    <canvas id="previstoChart" class="w-full h-full"></canvas>
  </div>
  <div class="bg-white p-4 shadow rounded h-64">
    <h3 class="text-lg font-semibold mb-2">Serviços Pendentes por Vendedor</h3>
    <canvas id="aprovadosChart" class="w-full h-full"></canvas>
  </div>
  <div class="bg-white p-4 shadow rounded h-64">
    <h3 class="text-lg font-semibold mb-2">Vendas Concluídas por Vendedor</h3>
    <canvas id="finalizadosChart" class="w-full h-full"></canvas>
  </div>
</div>


<div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-10">
  <div class="bg-white p-4 rounded-lg shadow overflow-x-auto">
    <h3 class="text-lg font-semibold mb-4">Resumo do Trabalho do SRD</h3>
    <table class="min-w-full divide-y divide-gray-200 text-sm">
      <thead class="bg-gray-100">
        <tr>
          <th class="px-4 py-2 text-left font-semibold text-gray-700">SDR</th>
          <th class="px-4 py-2 text-center font-semibold text-gray-700">Leads</th>
          <th class="px-4 py-2 text-center font-semibold text-gray-700">Direcionados</th>
          <th class="px-4 py-2 text-center font-semibold text-gray-700">Convertidos</th>
          <th class="px-4 py-2 text-center font-semibold text-gray-700">Conversão (%)</th>
          <th class="px-4 py-2 text-right font-semibold text-gray-700">Orçamento (R$)</th>
          <th class="px-4 py-2 text-right font-semibold text-gray-700">Convertido (R$)</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-200">
        <?php foreach ($sdrSummary as $item): ?>
          <tr class="hover:bg-gray-50">
            <td class="px-4 py-2"><?= htmlspecialchars($item['sdrName']) ?></td>
            <td class="px-4 py-2 text-center"><?= (int) $item['totalLeads'] ?></td>
            <td class="px-4 py-2 text-center"><?= (int) $item['assignedLeads'] ?></td>
            <td class="px-4 py-2 text-center"><?= (int) $item['convertedLeads'] ?></td>
            <td class="px-4 py-2 text-center"><?= number_format($item['conversionRate'], 2, ',', '.') ?></td>
            <td class="px-4 py-2 text-right">R$ <?= number_format($item['totalBudget'], 2, ',', '.') ?></td>
            <td class="px-4 py-2 text-right">R$ <?= number_format($item['convertedBudget'], 2, ',', '.') ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (empty($sdrSummary)): ?>
          <tr>
            <td colspan="7" class="px-4 py-3 text-center text-gray-500">Nenhum dado encontrado para o período selecionado.</td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <div class="bg-white p-4 rounded-lg shadow overflow-x-auto">
    <h3 class="text-lg font-semibold mb-4">Conversões do SRD por Vendedor</h3>
    <table class="min-w-full divide-y divide-gray-200 text-sm">
      <thead class="bg-gray-100">
        <tr>
          <th class="px-4 py-2 text-left font-semibold text-gray-700">Vendedor</th>
          <th class="px-4 py-2 text-center font-semibold text-gray-700">Leads SRD</th>
          <th class="px-4 py-2 text-center font-semibold text-gray-700">Convertidos</th>
          <th class="px-4 py-2 text-center font-semibold text-gray-700">Conversão (%)</th>
          <th class="px-4 py-2 text-right font-semibold text-gray-700">Orçamento (R$)</th>
          <th class="px-4 py-2 text-right font-semibold text-gray-700">Convertido (R$)</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-200">
        <?php foreach ($sdrVendorSummary as $item): ?>
          <tr class="hover:bg-gray-50">
            <td class="px-4 py-2"><?= htmlspecialchars($item['vendorName']) ?></td>
            <td class="px-4 py-2 text-center"><?= (int) $item['totalLeads'] ?></td>
            <td class="px-4 py-2 text-center"><?= (int) $item['convertedLeads'] ?></td>
            <td class="px-4 py-2 text-center"><?= number_format($item['conversionRate'], 2, ',', '.') ?></td>
            <td class="px-4 py-2 text-right">R$ <?= number_format($item['totalBudget'], 2, ',', '.') ?></td>
            <td class="px-4 py-2 text-right">R$ <?= number_format($item['convertedBudget'], 2, ',', '.') ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (empty($sdrVendorSummary)): ?>
          <tr>
            <td colspan="6" class="px-4 py-3 text-center text-gray-500">Nenhum dado encontrado para o período selecionado.</td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="mt-10 bg-white p-4 rounded-lg shadow">
  <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3 mb-4">
    <div>
      <p class="text-sm uppercase text-gray-500 font-semibold">Performance de Equipe</p>
      <h3 class="text-xl font-bold text-gray-800">Rankings e KPIs por função</h3>
    </div>
    <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-blue-50 text-blue-700 text-sm font-semibold">
      <i class="fas fa-chart-line"></i> Visão comparativa
    </span>
  </div>

  <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <div class="border border-gray-100 rounded-lg overflow-hidden">
      <div class="px-4 py-3 bg-gray-50 flex items-center justify-between">
        <h4 class="text-md font-semibold text-gray-800">Ranking de Vendedores</h4>
        <span class="text-xs text-gray-500">Ordenado por valor vendido</span>
      </div>
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
          <thead class="bg-gray-100">
            <tr>
              <th class="px-4 py-2 text-left font-semibold text-gray-700">Posição</th>
              <th class="px-4 py-2 text-left font-semibold text-gray-700">Vendedor</th>
              <th class="px-4 py-2 text-center font-semibold text-gray-700">Qtd. Vendas</th>
              <th class="px-4 py-2 text-right font-semibold text-gray-700">Total Vendido (R$)</th>
              <th class="px-4 py-2 text-right font-semibold text-gray-700">Ticket Médio (R$)</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200">
            <?php foreach ($vendorPerformanceRanking as $index => $item): ?>
              <tr class="hover:bg-gray-50">
                <td class="px-4 py-2 font-semibold text-gray-700">#<?= $index + 1 ?></td>
                <td class="px-4 py-2 text-gray-800"><?= htmlspecialchars($item['vendedor']) ?></td>
                <td class="px-4 py-2 text-center"><?= (int) ($item['qtd_vendas'] ?? 0) ?></td>
                <td class="px-4 py-2 text-right text-green-700 font-semibold">R$ <?= number_format((float) ($item['total_vendido'] ?? 0), 2, ',', '.') ?></td>
                <td class="px-4 py-2 text-right text-gray-700">R$ <?= number_format((float) ($item['ticket_medio'] ?? 0), 2, ',', '.') ?></td>
              </tr>
            <?php endforeach; ?>
            <?php if (empty($vendorPerformanceRanking)): ?>
              <tr>
                <td colspan="5" class="px-4 py-3 text-center text-gray-500">Nenhum resultado para o período selecionado.</td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div class="border border-gray-100 rounded-lg overflow-hidden">
      <div class="px-4 py-3 bg-gray-50 flex items-center justify-between">
        <h4 class="text-md font-semibold text-gray-800">Performance de SDRs</h4>
        <span class="text-xs text-gray-500">Conversão e atividades</span>
      </div>
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
          <thead class="bg-gray-100">
            <tr>
              <th class="px-4 py-2 text-left font-semibold text-gray-700">Posição</th>
              <th class="px-4 py-2 text-left font-semibold text-gray-700">SDR</th>
              <th class="px-4 py-2 text-center font-semibold text-gray-700">Leads</th>
              <th class="px-4 py-2 text-center font-semibold text-gray-700">Agendamentos</th>
              <th class="px-4 py-2 text-center font-semibold text-gray-700">Convertidos</th>
              <th class="px-4 py-2 text-center font-semibold text-gray-700">Taxa (%)</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200">
            <?php foreach ($sdrPerformanceRanking as $index => $item): ?>
              <tr class="hover:bg-gray-50">
                <td class="px-4 py-2 font-semibold text-gray-700">#<?= $index + 1 ?></td>
                <td class="px-4 py-2 text-gray-800"><?= htmlspecialchars($item['sdr']) ?></td>
                <td class="px-4 py-2 text-center"><?= (int) ($item['total_leads'] ?? 0) ?></td>
                <td class="px-4 py-2 text-center"><?= (int) ($item['total_agendamentos'] ?? 0) ?></td>
                <td class="px-4 py-2 text-center"><?= (int) ($item['leads_convertidos'] ?? 0) ?></td>
                <td class="px-4 py-2 text-center text-indigo-700 font-semibold"><?= number_format((float) ($item['taxa_conversao'] ?? 0), 2, ',', '.') ?></td>
              </tr>
            <?php endforeach; ?>
            <?php if (empty($sdrPerformanceRanking)): ?>
              <tr>
                <td colspan="6" class="px-4 py-3 text-center text-gray-500">Nenhum resultado para o período selecionado.</td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-10">
  <div class="bg-white p-4 rounded-lg shadow overflow-x-auto">
    <h3 class="text-lg font-semibold mb-4">Status dos Leads por SDR</h3>
    <table class="min-w-full divide-y divide-gray-200 text-sm">
      <thead class="bg-gray-100">
        <tr>
          <th class="px-4 py-2 text-left font-semibold text-gray-700">SDR</th>
          <th class="px-4 py-2 text-center font-semibold text-gray-700">Leads Recebidos</th>
          <th class="px-4 py-2 text-center font-semibold text-gray-700">Leads Tratados</th>
          <th class="px-4 py-2 text-center font-semibold text-gray-700">Leads Convertidos</th>
          <th class="px-4 py-2 text-center font-semibold text-gray-700">Leads Perdidos</th>
          <th class="px-4 py-2 text-center font-semibold text-gray-700">Tempo Médio (dias)</th>
          <th class="px-4 py-2 text-center font-semibold text-gray-700">Taxa de Atendimento</th>
          <th class="px-4 py-2 text-center font-semibold text-gray-700">Taxa de Conversão</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-200">
        <?php foreach ($sdrLeadStatusSummary as $item): ?>
          <tr class="hover:bg-gray-50">
            <td class="px-4 py-2"><?= htmlspecialchars($item['sdrName']) ?></td>
            <td class="px-4 py-2 text-center"><?= (int) $item['totalLeads'] ?></td>
            <td class="px-4 py-2 text-center"><?= (int) $item['treatedLeads'] ?></td>
            <td class="px-4 py-2 text-center"><?= (int) $item['convertedLeads'] ?></td>
            <td class="px-4 py-2 text-center"><?= (int) $item['lostLeads'] ?></td>
            <td class="px-4 py-2 text-center"><?= number_format($item['avgTreatmentDays'], 2, ',', '.') ?></td>
            <td class="px-4 py-2 text-center"><?= number_format($item['attendanceRate'], 2, ',', '.') ?>%</td>
            <td class="px-4 py-2 text-center"><?= number_format($item['conversionRate'], 2, ',', '.') ?>%</td>
          </tr>
        <?php endforeach; ?>
        <?php if (empty($sdrLeadStatusSummary)): ?>
          <tr>
            <td colspan="8" class="px-4 py-3 text-center text-gray-500">Nenhum dado encontrado para o período selecionado.</td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <div class="bg-white p-4 rounded-lg shadow overflow-x-auto">
    <h3 class="text-lg font-semibold mb-4">Tratamento de Leads por Vendedor</h3>
    <table class="min-w-full divide-y divide-gray-200 text-sm">
      <thead class="bg-gray-100">
        <tr>
          <th class="px-4 py-2 text-left font-semibold text-gray-700">Vendedor</th>
          <th class="px-4 py-2 text-center font-semibold text-gray-700">Leads Repassados</th>
          <th class="px-4 py-2 text-center font-semibold text-gray-700">Leads Tratados</th>
          <th class="px-4 py-2 text-center font-semibold text-gray-700">Leads Convertidos</th>
          <th class="px-4 py-2 text-center font-semibold text-gray-700">Pendentes</th>
          <th class="px-4 py-2 text-center font-semibold text-gray-700">Taxa de Conversão</th>
          <th class="px-4 py-2 text-center font-semibold text-gray-700">Percentual Pendente</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-200">
        <?php foreach ($vendorLeadTreatmentSummary as $item): ?>
          <tr class="hover:bg-gray-50">
            <td class="px-4 py-2"><?= htmlspecialchars($item['vendorName']) ?></td>
            <td class="px-4 py-2 text-center"><?= (int) $item['totalLeads'] ?></td>
            <td class="px-4 py-2 text-center"><?= (int) $item['treatedLeads'] ?></td>
            <td class="px-4 py-2 text-center"><?= (int) $item['convertedLeads'] ?></td>
            <td class="px-4 py-2 text-center"><?= (int) $item['pendingLeads'] ?></td>
            <td class="px-4 py-2 text-center"><?= number_format($item['conversionRate'], 2, ',', '.') ?>%</td>
            <td class="px-4 py-2 text-center"><?= number_format($item['pendingPercent'], 2, ',', '.') ?>%</td>
          </tr>
        <?php endforeach; ?>
        <?php if (empty($vendorLeadTreatmentSummary)): ?>
          <tr>
            <td colspan="7" class="px-4 py-3 text-center text-gray-500">Nenhum dado encontrado para o período selecionado.</td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="grid grid-cols-1 gap-6 mt-10">
  <div class="bg-white p-4 rounded-lg shadow overflow-x-auto">
    <h3 class="text-lg font-semibold mb-4">Resumo de Serviços</h3>
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4">
      <div class="p-3 bg-gray-50 rounded shadow-sm">
        <p class="text-xs text-gray-500">Serviços Iniciados</p>
        <p class="text-xl font-bold text-gray-800"><?= (int) $servicesSummary['totalIniciados'] ?></p>
      </div>
      <div class="p-3 bg-gray-50 rounded shadow-sm">
        <p class="text-xs text-gray-500">Serviços Finalizados</p>
        <p class="text-xl font-bold text-green-600"><?= (int) $servicesSummary['totalFinalizados'] ?></p>
      </div>
      <div class="p-3 bg-gray-50 rounded shadow-sm">
        <p class="text-xs text-gray-500">Serviços Pendentes</p>
        <p class="text-xl font-bold text-orange-600"><?= (int) $servicesSummary['totalPendentes'] ?></p>
      </div>
      <div class="p-3 bg-gray-50 rounded shadow-sm">
        <p class="text-xs text-gray-500">Valor Finalizado (R$)</p>
        <p class="text-xl font-bold text-blue-600">R$ <?= number_format($servicesSummary['valorFinalizado'], 2, ',', '.') ?></p>
      </div>
    </div>

    <table class="min-w-full divide-y divide-gray-200 text-sm">
      <thead class="bg-gray-100">
        <tr>
          <th class="px-4 py-2 text-left font-semibold text-gray-700">Tipo de Serviço</th>
          <th class="px-4 py-2 text-center font-semibold text-gray-700">Iniciados</th>
          <th class="px-4 py-2 text-center font-semibold text-gray-700">Finalizados</th>
          <th class="px-4 py-2 text-center font-semibold text-gray-700">Pendentes</th>
          <th class="px-4 py-2 text-right font-semibold text-gray-700">Valor Finalizado (R$)</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-200">
        <?php foreach ($servicesSummary['detalhes'] as $item): ?>
          <tr class="hover:bg-gray-50">
            <td class="px-4 py-2"><?= htmlspecialchars($item['tipo']) ?></td>
            <td class="px-4 py-2 text-center"><?= (int) $item['totalIniciados'] ?></td>
            <td class="px-4 py-2 text-center"><?= (int) $item['totalFinalizados'] ?></td>
            <td class="px-4 py-2 text-center"><?= (int) $item['totalPendentes'] ?></td>
            <td class="px-4 py-2 text-right">R$ <?= number_format($item['valorFinalizado'], 2, ',', '.') ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (empty($servicesSummary['detalhes'])): ?>
          <tr>
            <td colspan="5" class="px-4 py-3 text-center text-gray-500">Nenhum dado encontrado para o período selecionado.</td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="grid grid-cols-1 gap-6 mt-6">
  <div class="bg-white p-4 rounded-lg shadow overflow-x-auto">
    <h3 class="text-lg font-semibold mb-4">Comissão por Vendedor</h3>
    <table class="min-w-full divide-y divide-gray-200 text-sm">
      <thead class="bg-gray-100">
        <tr>
          <th class="px-4 py-2 text-left font-semibold text-gray-700">Vendedor</th>
          <th class="px-4 py-2 text-center font-semibold text-gray-700">Negócios</th>
          <th class="px-4 py-2 text-right font-semibold text-gray-700">Valor Vendido (R$)</th>
          <th class="px-4 py-2 text-right font-semibold text-gray-700">Ticket Médio (R$)</th>
          <th class="px-4 py-2 text-right font-semibold text-gray-700">Comissão (R$)</th>
          <th class="px-4 py-2 text-center font-semibold text-gray-700">% Base</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-200">
        <?php foreach ($vendorCommissionReport as $item): ?>
          <tr class="hover:bg-gray-50">
            <td class="px-4 py-2"><?= htmlspecialchars($item['vendorName']) ?></td>
            <td class="px-4 py-2 text-center"><?= (int) $item['dealsCount'] ?></td>
            <td class="px-4 py-2 text-right">R$ <?= number_format($item['totalSales'], 2, ',', '.') ?></td>
            <td class="px-4 py-2 text-right">R$ <?= number_format($item['averageTicket'], 2, ',', '.') ?></td>
            <td class="px-4 py-2 text-right">R$ <?= number_format($item['totalCommission'], 2, ',', '.') ?></td>
            <td class="px-4 py-2 text-center"><?= number_format($item['commissionPercent'], 2, ',', '.') ?>%</td>
          </tr>
        <?php endforeach; ?>
        <?php if (empty($vendorCommissionReport)): ?>
          <tr>
            <td colspan="6" class="px-4 py-3 text-center text-gray-500">Nenhum dado de comissão encontrado para o período selecionado.</td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>


<div class="grid grid-cols-1 gap-6 mt-10">
  <!-- Formulário de filtro -->
  <div class="bg-white p-4 rounded-lg shadow">
    <form method="GET" class="space-y-4">
      <div>
        <label class="block text-sm font-medium text-gray-700">Data Início:</label>
        <input type="date" name="data_inicio"
               value="<?= htmlspecialchars($_GET['data_inicio'] ?? '') ?>"
               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700">Data Fim:</label>
        <input type="date" name="data_fim"
               value="<?= htmlspecialchars($_GET['data_fim'] ?? '') ?>"
               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
      </div>
      <div>
        <button type="submit"
                class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none">
          Filtrar
        </button>
      </div>
    </form>
  </div>

  <!-- Tabela de performance -->
  <div class="bg-white p-4 rounded-lg shadow overflow-x-auto">
    <table class="min-w-full divide-y divide-gray-200 text-sm">
      <thead class="bg-gray-100">
        <tr>
          <th class="px-4 py-2 text-left font-semibold text-gray-700">Vendedor</th>
          <th class="px-4 py-2 text-right font-semibold text-gray-700">Total de Vendas (R$)</th>
          <th class="px-4 py-2 text-center font-semibold text-gray-700">Novos Leads (Mês)</th>
          <th class="px-4 py-2 text-center font-semibold text-gray-700">Taxa de Conversão (%)</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-200">
        <?php foreach ($performance as $item): ?>
          <tr class="hover:bg-gray-50">
            <td class="px-4 py-2"><?= htmlspecialchars($item['nome']) ?></td>
            <td class="px-4 py-2 text-right"><?= number_format($item['total_vendas'], 2, ',', '.') ?></td>
            <td class="px-4 py-2 text-center"><?= htmlspecialchars($item['novos_leads_mes']) ?></td>
            <td class="px-4 py-2 text-center"><?= number_format($item['taxa_conversao'], 2, ',', '.') ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>


<script>
document.addEventListener('DOMContentLoaded', function () {
  // Leads
  new Chart(document.getElementById('leadsChart').getContext('2d'), {
    type: 'bar',
    data: {
      labels: <?= json_encode($labelsLeads) ?>,
      datasets: [{
        label: 'Total de Leads',
        data: <?= json_encode($valoresLeads) ?>,
        backgroundColor: 'rgba(246, 173, 85, 0.7)'
      }]
    },
    options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true } } }
  });

  // Valor Previsto
  new Chart(document.getElementById('previstoChart').getContext('2d'), {
    type: 'bar',
    data: {
      labels: <?= json_encode($labelsPrevisto) ?>,
      datasets: [{
        label: 'Valor Previsto (R$)',
        data: <?= json_encode($valoresPrevisto) ?>,
        backgroundColor: 'rgba(59, 130, 246, 0.7)'
      }]
    },
    options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true } } }
  });

  // Serviços Pendentes
  new Chart(document.getElementById('aprovadosChart').getContext('2d'), {
    type: 'bar',
    data: {
      labels: <?= json_encode($labelsAprovados) ?>,
      datasets: [{
        label: 'Serviços Pendentes',
        data: <?= json_encode($valoresAprovados) ?>,
        backgroundColor: 'rgba(16, 185, 129, 0.7)'
      }]
    },
    options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true } } }
  });

  // Vendas Concluídas
  new Chart(document.getElementById('finalizadosChart').getContext('2d'), {
    type: 'bar',
    data: {
      labels: <?= json_encode($labelsFinalizados) ?>,
      datasets: [{
        label: 'Vendas Concluídas',
        data: <?= json_encode($valoresFinalizados) ?>,
        backgroundColor: 'rgba(139, 92, 246, 0.7)'
      }]
    },
    options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true } } }
  });
});
</script>
