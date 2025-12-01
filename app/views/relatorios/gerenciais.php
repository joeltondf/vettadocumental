<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-3xl font-bold text-gray-800">Relatórios Gerenciais</h1>
        <p class="text-gray-600">Visão consolidada do caixa real, desempenho de vendas e SDR.</p>
    </div>
</div>

<div class="bg-white p-6 rounded-lg shadow-md mb-6">
    <form method="GET" action="relatorios.php" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
        <div>
            <label for="data_inicio" class="block text-sm font-medium text-gray-700">Data Inicial</label>
            <input type="date" name="data_inicio" id="data_inicio" value="<?php echo htmlspecialchars($dataInicio); ?>" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
        </div>
        <div>
            <label for="data_fim" class="block text-sm font-medium text-gray-700">Data Final</label>
            <input type="date" name="data_fim" id="data_fim" value="<?php echo htmlspecialchars($dataFim); ?>" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
        </div>
        <div class="md:col-span-2 flex space-x-3">
            <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700">Atualizar</button>
            <a href="relatorios.php" class="bg-gray-100 text-gray-800 px-4 py-2 rounded-md hover:bg-gray-200">Limpar</a>
        </div>
    </form>
</div>

<div class="bg-white rounded-lg shadow-md mb-8">
    <div class="border-b px-6 pt-4">
        <nav class="flex space-x-6" aria-label="Tabs">
            <button class="tab-trigger py-4 text-sm font-medium text-theme-color border-b-2 border-theme-color" data-target="tab-caixa">Caixa Real</button>
            <button class="tab-trigger py-4 text-sm font-medium text-gray-600 hover:text-theme-color" data-target="tab-vendedores">Performance Vendedores</button>
            <button class="tab-trigger py-4 text-sm font-medium text-gray-600 hover:text-theme-color" data-target="tab-sdr">Performance SDR</button>
        </nav>
    </div>

    <div class="p-6">
        <div id="tab-caixa" class="tab-content">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                <div class="bg-green-50 border border-green-200 p-4 rounded-lg">
                    <p class="text-sm text-green-700">Entradas</p>
                    <p class="text-2xl font-bold text-green-900">R$ <?php echo number_format($caixaReal['entradas'], 2, ',', '.'); ?></p>
                </div>
                <div class="bg-red-50 border border-red-200 p-4 rounded-lg">
                    <p class="text-sm text-red-700">Saídas</p>
                    <p class="text-2xl font-bold text-red-900">R$ <?php echo number_format($caixaReal['despesas'], 2, ',', '.'); ?></p>
                </div>
                <div class="bg-indigo-50 border border-indigo-200 p-4 rounded-lg">
                    <p class="text-sm text-indigo-700">Saldo</p>
                    <p class="text-2xl font-bold text-indigo-900">R$ <?php echo number_format($caixaReal['saldo'], 2, ',', '.'); ?></p>
                </div>
            </div>
            <div>
                <canvas id="chart-caixa"></canvas>
            </div>
        </div>

        <div id="tab-vendedores" class="tab-content hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Vendedor</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Total Vendido</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Total Recebido</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (empty($porVendedor)): ?>
                            <tr><td colspan="3" class="px-4 py-4 text-center text-gray-500">Nenhum registro no período.</td></tr>
                        <?php else: ?>
                            <?php foreach ($porVendedor as $linha): ?>
                                <tr>
                                    <td class="px-4 py-3 text-sm text-gray-800"><?php echo htmlspecialchars($linha['nome_vendedor'] ?? 'Sem vendedor'); ?></td>
                                    <td class="px-4 py-3 text-sm text-right font-semibold text-gray-900">R$ <?php echo number_format($linha['total_vendido'], 2, ',', '.'); ?></td>
                                    <td class="px-4 py-3 text-sm text-right font-semibold text-green-700">R$ <?php echo number_format($linha['total_recebido'], 2, ',', '.'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div id="tab-sdr" class="tab-content hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">SDR</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Vendas Geradas</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Total Recebido</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (empty($porSdr)): ?>
                            <tr><td colspan="3" class="px-4 py-4 text-center text-gray-500">Nenhum registro no período.</td></tr>
                        <?php else: ?>
                            <?php foreach ($porSdr as $linha): ?>
                                <tr>
                                    <td class="px-4 py-3 text-sm text-gray-800"><?php echo htmlspecialchars($linha['nome_sdr'] ?? 'Sem SDR'); ?></td>
                                    <td class="px-4 py-3 text-sm text-right font-semibold text-gray-900"><?php echo (int) ($linha['qtd_vendas'] ?? 0); ?></td>
                                    <td class="px-4 py-3 text-sm text-right font-semibold text-green-700">R$ <?php echo number_format($linha['total_recebido'], 2, ',', '.'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    const tabs = document.querySelectorAll('.tab-trigger');
    const contents = document.querySelectorAll('.tab-content');

    tabs.forEach((button) => {
        button.addEventListener('click', () => {
            contents.forEach((content) => content.classList.add('hidden'));
            tabs.forEach((tab) => tab.classList.remove('border-theme-color', 'text-theme-color', 'border-b-2'));
            const target = document.getElementById(button.dataset.target);
            if (target) {
                target.classList.remove('hidden');
            }
            button.classList.add('border-theme-color', 'text-theme-color', 'border-b-2');
        });
    });

    const chartCaixa = document.getElementById('chart-caixa');
    if (chartCaixa) {
        new Chart(chartCaixa, {
            type: 'line',
            data: {
                labels: ['<?php echo htmlspecialchars($dataInicio); ?>', '<?php echo htmlspecialchars($dataFim); ?>'],
                datasets: [{
                    label: 'Saldo acumulado',
                    data: [0, <?php echo json_encode($caixaReal['saldo']); ?>],
                    backgroundColor: 'rgba(59, 130, 246, 0.2)',
                    borderColor: 'rgba(59, 130, 246, 1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.3,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true }
                }
            },
        });
    }
</script>
