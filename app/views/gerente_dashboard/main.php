<?php
$valoresLeads     = $valoresLeads ?? [];
$labelsLeads      = $labelsLeads ?? [];
$valoresPrevisto  = $valoresPrevisto ?? [];
$labelsPrevisto   = $labelsPrevisto ?? [];
$valoresAprovados = $valoresAprovados ?? [];
$labelsAprovados  = $labelsAprovados ?? [];
$valoresFinalizados = $valoresFinalizados ?? [];
$labelsFinalizados  = $labelsFinalizados ?? [];
?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<h1 class="text-2xl font-bold text-gray-800 mb-5"><?= htmlspecialchars($pageTitle) ?></h1>

<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
  <div class="p-4 bg-white shadow rounded">
    <h3 class="text-sm font-semibold text-gray-500">Total de Vendas (R$)</h3>
    <p class="text-xl font-bold text-green-600">
      R$ <?= number_format(array_sum($valoresPrevisto), 2, ',', '.') ?>
    </p>
  </div>
  <div class="p-4 bg-white shadow rounded">
    <h3 class="text-sm font-semibold text-gray-500">Orçamentos Aprovados</h3>
    <p class="text-xl font-bold text-blue-600">
      <?= array_sum($valoresAprovados) ?>
    </p>
  </div>
  <div class="p-4 bg-white shadow rounded">
    <h3 class="text-sm font-semibold text-gray-500">Vendas Concluídas</h3>
    <p class="text-xl font-bold text-purple-600">
      <?= array_sum($valoresFinalizados) ?>
    </p>
  </div>
  <div class="p-4 bg-white shadow rounded">
    <h3 class="text-sm font-semibold text-gray-500">Leads Criados</h3>
    <p class="text-xl font-bold text-orange-600">
      <?= array_sum($valoresLeads) ?>
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
    <h3 class="text-lg font-semibold mb-2">Orçamentos Aprovados por Vendedor</h3>
    <canvas id="aprovadosChart" class="w-full h-full"></canvas>
  </div>
  <div class="bg-white p-4 shadow rounded h-64">
    <h3 class="text-lg font-semibold mb-2">Vendas Concluídas por Vendedor</h3>
    <canvas id="finalizadosChart" class="w-full h-full"></canvas>
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

  // Orçamentos Aprovados
  new Chart(document.getElementById('aprovadosChart').getContext('2d'), {
    type: 'bar',
    data: {
      labels: <?= json_encode($labelsAprovados) ?>,
      datasets: [{
        label: 'Orçamentos Aprovados',
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
