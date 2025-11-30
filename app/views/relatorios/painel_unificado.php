<div class="max-w-7xl mx-auto py-8 px-4">
    <div class="flex items-center justify-between mb-6">
        <div>
            <p class="text-sm text-gray-500">Centro de Inteligência</p>
            <h1 class="text-3xl font-bold text-theme-color">Relatórios Gerenciais</h1>
        </div>
        <form method="GET" class="flex items-center space-x-2 bg-white shadow-sm rounded-lg p-3">
            <div>
                <label class="block text-xs text-gray-500">Início</label>
                <input type="date" name="data_inicio" value="<?php echo htmlspecialchars($filters['start_date']); ?>" class="border rounded px-2 py-1 text-sm" />
            </div>
            <div>
                <label class="block text-xs text-gray-500">Fim</label>
                <input type="date" name="data_fim" value="<?php echo htmlspecialchars($filters['end_date']); ?>" class="border rounded px-2 py-1 text-sm" />
            </div>
            <button type="submit" class="bg-theme-color text-white px-4 py-2 rounded-lg text-sm font-semibold">Aplicar</button>
        </form>
    </div>

    <div class="bg-white rounded-lg shadow-md">
        <div class="border-b flex flex-wrap">
            <button data-tab="financeiro" class="tab-button px-4 py-3 text-sm font-semibold border-b-4 border-transparent hover:text-theme-color <?php echo $financeiro ? 'active' : 'hidden'; ?>">Financeiro / Caixa</button>
            <button data-tab="comercial" class="tab-button px-4 py-3 text-sm font-semibold border-b-4 border-transparent hover:text-theme-color">Comercial / Vendas</button>
            <button data-tab="sdr" class="tab-button px-4 py-3 text-sm font-semibold border-b-4 border-transparent hover:text-theme-color">Pré-Vendas / SDR</button>
            <button data-tab="operacional" class="tab-button px-4 py-3 text-sm font-semibold border-b-4 border-transparent hover:text-theme-color">Operacional</button>
        </div>

        <div class="p-6">
            <?php if ($financeiro): ?>
            <div data-content="financeiro" class="tab-content">
                <div class="grid md:grid-cols-3 gap-4 mb-4">
                    <div class="p-4 bg-slate-50 rounded-lg border">
                        <p class="text-sm text-gray-500">Total Recebido</p>
                        <p class="text-2xl font-bold text-green-600">R$ <?php echo number_format($financeiro['resumo']['total_recebido'] ?? 0, 2, ',', '.'); ?></p>
                    </div>
                    <div class="p-4 bg-slate-50 rounded-lg border">
                        <p class="text-sm text-gray-500">Total Restante</p>
                        <p class="text-2xl font-bold text-amber-600">R$ <?php echo number_format($financeiro['resumo']['total_restante'] ?? 0, 2, ',', '.'); ?></p>
                    </div>
                    <div class="p-4 bg-slate-50 rounded-lg border">
                        <p class="text-sm text-gray-500">Total Geral</p>
                        <p class="text-2xl font-bold text-theme-color">R$ <?php echo number_format($financeiro['resumo']['total_geral'] ?? 0, 2, ',', '.'); ?></p>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-3 py-2 text-left font-semibold">Processo</th>
                                <th class="px-3 py-2 text-left font-semibold">Cliente</th>
                                <th class="px-3 py-2 text-left font-semibold">Valor</th>
                                <th class="px-3 py-2 text-left font-semibold">Recebido</th>
                                <th class="px-3 py-2 text-left font-semibold">Restante</th>
                                <th class="px-3 py-2 text-left font-semibold">Data Pagamento</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php foreach ($financeiro['transacoes'] as $linha): ?>
                                <tr>
                                    <td class="px-3 py-2"><?php echo htmlspecialchars($linha['titulo'] ?? ''); ?></td>
                                    <td class="px-3 py-2"><?php echo htmlspecialchars($linha['cliente_nome'] ?? ''); ?></td>
                                    <td class="px-3 py-2 font-semibold">R$ <?php echo number_format((float)($linha['valor_total'] ?? 0), 2, ',', '.'); ?></td>
                                    <td class="px-3 py-2 text-green-700">R$ <?php echo number_format((float)($linha['valor_recebido'] ?? 0), 2, ',', '.'); ?></td>
                                    <td class="px-3 py-2 text-amber-700">R$ <?php echo number_format((float)($linha['valor_restante'] ?? 0), 2, ',', '.'); ?></td>
                                    <td class="px-3 py-2"><?php echo !empty($linha['data_pagamento']) ? date('d/m/Y', strtotime($linha['data_pagamento'])) : '-'; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

            <div data-content="comercial" class="tab-content <?php echo $financeiro ? 'hidden' : ''; ?>">
                <div class="grid md:grid-cols-3 gap-4 mb-6">
                    <div class="p-4 rounded-lg border bg-slate-50">
                        <p class="text-sm text-gray-500">Total Vendido</p>
                        <p class="text-3xl font-bold text-theme-color">R$ <?php echo number_format($comercial['total_vendido'] ?? 0, 2, ',', '.'); ?></p>
                    </div>
                    <div class="p-4 rounded-lg border bg-slate-50">
                        <p class="text-sm text-gray-500">Maior Vendedor</p>
                        <p class="text-2xl font-semibold text-green-700"><?php echo htmlspecialchars($comercial['melhor_vendedor'] ?? '-'); ?></p>
                    </div>
                    <div class="p-4 rounded-lg border bg-slate-50">
                        <p class="text-sm text-gray-500">Ticket Médio</p>
                        <p class="text-3xl font-bold text-amber-600">R$ <?php echo number_format($comercial['ticket_medio'] ?? 0, 2, ',', '.'); ?></p>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-3 py-2 text-left font-semibold">Vendedor</th>
                                <th class="px-3 py-2 text-left font-semibold">Qtd. Vendas</th>
                                <th class="px-3 py-2 text-left font-semibold">Total Vendido</th>
                                <th class="px-3 py-2 text-left font-semibold">Ticket Médio</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php foreach ($comercial['ranking'] as $linha): ?>
                                <tr>
                                    <td class="px-3 py-2 font-semibold"><?php echo htmlspecialchars($linha['vendedor'] ?? ''); ?></td>
                                    <td class="px-3 py-2"><?php echo (int) ($linha['qtd_vendas'] ?? 0); ?></td>
                                    <td class="px-3 py-2">R$ <?php echo number_format((float)($linha['total_vendido'] ?? 0), 2, ',', '.'); ?></td>
                                    <td class="px-3 py-2">R$ <?php echo number_format((float)($linha['ticket_medio'] ?? 0), 2, ',', '.'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div data-content="sdr" class="tab-content hidden">
                <div class="grid md:grid-cols-2 gap-6 mb-6">
                    <div class="p-4 bg-slate-50 rounded-lg border">
                        <canvas id="sdrChart"></canvas>
                    </div>
                    <div class="p-4 bg-slate-50 rounded-lg border overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-3 py-2 text-left font-semibold">SDR</th>
                                    <th class="px-3 py-2 text-left font-semibold">Agendamentos</th>
                                    <th class="px-3 py-2 text-left font-semibold">Leads Convertidos</th>
                                    <th class="px-3 py-2 text-left font-semibold">Taxa (%)</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <?php foreach ($sdr['ranking'] as $linha): ?>
                                    <tr>
                                        <td class="px-3 py-2 font-semibold"><?php echo htmlspecialchars($linha['sdr'] ?? ''); ?></td>
                                        <td class="px-3 py-2"><?php echo (int) ($linha['total_agendamentos'] ?? 0); ?></td>
                                        <td class="px-3 py-2"><?php echo (int) ($linha['leads_convertidos'] ?? 0); ?></td>
                                        <td class="px-3 py-2"><?php echo number_format((float)($linha['taxa_conversao'] ?? 0), 2, ',', '.'); ?>%</td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div data-content="operacional" class="tab-content hidden">
                <div class="grid md:grid-cols-2 gap-4">
                    <div class="p-4 bg-slate-50 rounded-lg border">
                        <h3 class="text-lg font-semibold mb-3">Processos por Status</h3>
                        <div class="space-y-2">
                            <?php foreach ($operacional['por_status'] as $linha): ?>
                                <div class="flex items-center justify-between bg-white rounded px-3 py-2 border">
                                    <span class="capitalize text-gray-700"><?php echo htmlspecialchars($linha['status'] ?? ''); ?></span>
                                    <span class="font-bold text-theme-color"><?php echo (int) ($linha['total'] ?? 0); ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="p-4 bg-slate-50 rounded-lg border">
                        <h3 class="text-lg font-semibold mb-3">Alertas de Atraso</h3>
                        <?php if (empty($operacional['atrasados'])): ?>
                            <p class="text-gray-500 text-sm">Nenhum atraso identificado no período.</p>
                        <?php else: ?>
                            <div class="space-y-2">
                                <?php foreach ($operacional['atrasados'] as $proc): ?>
                                    <div class="border-l-4 border-red-500 bg-white rounded px-3 py-2">
                                        <p class="font-semibold text-red-700"><?php echo htmlspecialchars($proc['titulo'] ?? ''); ?></p>
                                        <p class="text-xs text-gray-600">Prev. Entrega: <?php echo date('d/m/Y', strtotime($proc['data_previsao_entrega'])); ?> | Status: <?php echo htmlspecialchars($proc['status_processo'] ?? ''); ?></p>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    const tabButtons = document.querySelectorAll('.tab-button');
    const tabContents = document.querySelectorAll('.tab-content');

    tabButtons.forEach(button => {
        button.addEventListener('click', () => {
            const target = button.getAttribute('data-tab');
            tabButtons.forEach(btn => btn.classList.remove('border-theme-color', 'text-theme-color'));
            tabContents.forEach(content => content.classList.add('hidden'));

            button.classList.add('border-theme-color', 'text-theme-color');
            document.querySelector(`[data-content="${target}"]`).classList.remove('hidden');
        });
    });

    const sdrCtx = document.getElementById('sdrChart');
    if (sdrCtx) {
        new Chart(sdrCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($sdr['labels']); ?>,
                datasets: [
                    {
                        label: 'Agendamentos',
                        data: <?php echo json_encode($sdr['agendamentos']); ?>,
                        backgroundColor: '#0ea5e9',
                    },
                    {
                        label: 'Realizados',
                        data: <?php echo json_encode($sdr['convertidos']); ?>,
                        backgroundColor: '#22c55e',
                    }
                ]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { position: 'top' }
                }
            }
        });
    }
</script>
