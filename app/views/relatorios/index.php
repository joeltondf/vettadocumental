<?php
// app/views/relatorios/index.php
?>
<div class="max-w-7xl mx-auto px-4 py-8">
    <div class="bg-white shadow rounded-2xl border border-slate-200 p-6">
        <div class="flex items-center justify-between mb-6">
            <div>
                <p class="text-sm text-slate-500">Centro de Inteligência</p>
                <h1 class="text-3xl font-bold text-slate-800">Relatórios &amp; BI</h1>
            </div>
            <div class="flex items-center space-x-3 text-slate-500">
                <i class="fas fa-chart-line text-theme-color"></i>
                <span class="text-sm">Visão consolidada em abas</span>
            </div>
        </div>

        <div class="border-b border-slate-200 mb-6">
            <nav class="-mb-px flex flex-wrap" aria-label="Abas de Relatórios">
                <?php
                    $tabs = [
                        'visao-geral' => 'Visão Geral',
                        'financeiro' => 'Financeiro (Caixa Real)',
                        'comercial' => 'Comercial (Vendas)',
                        'pre-vendas' => 'Pré-Vendas (SDR)',
                        'operacional' => 'Operacional',
                    ];
                ?>
                <?php foreach ($tabs as $slug => $label): ?>
                    <button type="button" data-tab="<?php echo $slug; ?>" class="tab-button px-4 py-3 text-sm font-medium border-b-2 border-transparent text-slate-600 hover:text-theme-color">
                        <?php echo htmlspecialchars($label); ?>
                    </button>
                <?php endforeach; ?>
            </nav>
        </div>

        <div id="tab-visao-geral" class="tab-content">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="bg-slate-50 border border-slate-200 rounded-xl p-4">
                    <p class="text-sm text-slate-500">Processos em carteira</p>
                    <p class="text-3xl font-bold text-slate-800"><?php echo number_format($visaoGeral['total_processos'] ?? 0); ?></p>
                </div>
                <div class="bg-slate-50 border border-slate-200 rounded-xl p-4">
                    <p class="text-sm text-slate-500">Entregues/Finalizados</p>
                    <p class="text-3xl font-bold text-green-700"><?php echo number_format($visaoGeral['total_finalizados'] ?? 0); ?></p>
                </div>
                <div class="bg-slate-50 border border-slate-200 rounded-xl p-4">
                    <p class="text-sm text-slate-500">Receita Prevista</p>
                    <p class="text-3xl font-bold text-theme-color">R$ <?php echo number_format($visaoGeral['receita_prevista'] ?? 0, 2, ',', '.'); ?></p>
                </div>
            </div>
        </div>

        <div id="tab-financeiro" class="tab-content hidden">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h2 class="text-xl font-semibold text-slate-800">Regime de Caixa</h2>
                    <p class="text-sm text-slate-500">Filtrado por data de pagamento</p>
                </div>
                <form method="GET" class="flex items-center space-x-3">
                    <div>
                        <label class="text-xs text-slate-500">Data inicial</label>
                        <input type="date" name="data_pagamento_1" value="<?php echo htmlspecialchars($financeiro['inicio']); ?>" class="block border border-slate-300 rounded-md px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="text-xs text-slate-500">Data final</label>
                        <input type="date" name="data_pagamento_2" value="<?php echo htmlspecialchars($financeiro['fim']); ?>" class="block border border-slate-300 rounded-md px-3 py-2 text-sm">
                    </div>
                    <button type="submit" class="mt-5 bg-theme-color text-white px-4 py-2 rounded-md text-sm shadow hover:opacity-90">
                        Aplicar
                    </button>
                </form>
            </div>
            <div class="overflow-x-auto border border-slate-200 rounded-xl">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-4 py-3 text-left font-medium text-slate-600">Data</th>
                            <th class="px-4 py-3 text-left font-medium text-slate-600">Cliente</th>
                            <th class="px-4 py-3 text-left font-medium text-slate-600">Processo</th>
                            <th class="px-4 py-3 text-center font-medium text-slate-600">Tipo</th>
                            <th class="px-4 py-3 text-right font-medium text-slate-600">Valor</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach ($financeiro['entradas'] as $entrada): ?>
                            <tr class="hover:bg-slate-50">
                                <td class="px-4 py-3 text-slate-700"><?php echo htmlspecialchars(date('d/m/Y', strtotime($entrada['data_movimento']))); ?></td>
                                <td class="px-4 py-3 text-slate-700"><?php echo htmlspecialchars($entrada['nome_cliente'] ?? '-'); ?></td>
                                <td class="px-4 py-3 text-slate-700"><?php echo htmlspecialchars($entrada['processo'] ?? '-'); ?></td>
                                <td class="px-4 py-3 text-center text-slate-600"><?php echo htmlspecialchars($entrada['tipo_lancamento'] ?? '-'); ?></td>
                                <td class="px-4 py-3 text-right font-semibold text-slate-800">R$ <?php echo number_format((float) ($entrada['valor'] ?? 0), 2, ',', '.'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot class="bg-slate-50">
                        <tr>
                            <td colspan="4" class="px-4 py-3 text-right font-semibold text-slate-700">Total no período</td>
                            <td class="px-4 py-3 text-right font-bold text-theme-color">R$ <?php echo number_format($financeiro['total'] ?? 0, 2, ',', '.'); ?></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <div id="tab-comercial" class="tab-content hidden">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h2 class="text-xl font-semibold text-slate-800">Ranking de Vendedores</h2>
                    <p class="text-sm text-slate-500">Total vendido e ticket médio</p>
                </div>
            </div>
            <div class="overflow-x-auto border border-slate-200 rounded-xl">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-4 py-3 text-left font-medium text-slate-600">Vendedor</th>
                            <th class="px-4 py-3 text-center font-medium text-slate-600">Vendas</th>
                            <th class="px-4 py-3 text-right font-medium text-slate-600">Total Vendido</th>
                            <th class="px-4 py-3 text-right font-medium text-slate-600">Ticket Médio</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach ($rankingVendas as $linha): ?>
                            <tr class="hover:bg-slate-50">
                                <td class="px-4 py-3 text-slate-800 font-semibold"><?php echo htmlspecialchars($linha['vendedor'] ?? '—'); ?></td>
                                <td class="px-4 py-3 text-center text-slate-700"><?php echo (int) ($linha['total_vendas'] ?? 0); ?></td>
                                <td class="px-4 py-3 text-right font-semibold text-slate-800">R$ <?php echo number_format((float) ($linha['valor_total'] ?? 0), 2, ',', '.'); ?></td>
                                <td class="px-4 py-3 text-right text-slate-700">R$ <?php echo number_format((float) ($linha['ticket_medio'] ?? 0), 2, ',', '.'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div id="tab-pre-vendas" class="tab-content hidden">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                <div class="bg-slate-50 border border-slate-200 rounded-xl p-4">
                    <p class="text-sm text-slate-500">Agendamentos</p>
                    <p class="text-3xl font-bold text-slate-800"><?php echo number_format($preVendas['total_agendamentos'] ?? 0); ?></p>
                </div>
                <div class="bg-slate-50 border border-slate-200 rounded-xl p-4">
                    <p class="text-sm text-slate-500">Vendas Geradas</p>
                    <p class="text-3xl font-bold text-green-700"><?php echo number_format($preVendas['total_convertidos'] ?? 0); ?></p>
                </div>
                <div class="bg-slate-50 border border-slate-200 rounded-xl p-4">
                    <p class="text-sm text-slate-500">Taxa de Conversão</p>
                    <p class="text-3xl font-bold text-theme-color"><?php echo number_format($preVendas['taxa_conversao'] ?? 0, 2, ',', '.'); ?>%</p>
                </div>
            </div>
            <p class="text-sm text-slate-600">Métricas baseadas no status das prospecções (Agendadas x Convertidas).</p>
        </div>

        <div id="tab-operacional" class="tab-content hidden">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h2 class="text-xl font-semibold text-slate-800">Pendências Operacionais</h2>
                    <p class="text-sm text-slate-500">Processos parados ou atrasados</p>
                </div>
            </div>
            <div class="overflow-x-auto border border-slate-200 rounded-xl">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-4 py-3 text-left font-medium text-slate-600">Processo</th>
                            <th class="px-4 py-3 text-left font-medium text-slate-600">Cliente</th>
                            <th class="px-4 py-3 text-center font-medium text-slate-600">Status</th>
                            <th class="px-4 py-3 text-center font-medium text-slate-600">Última atualização</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach ($operacional as $processo): ?>
                            <tr class="hover:bg-slate-50">
                                <td class="px-4 py-3 text-slate-800 font-semibold"><?php echo htmlspecialchars($processo['titulo'] ?? '—'); ?></td>
                                <td class="px-4 py-3 text-slate-700"><?php echo htmlspecialchars($processo['nome_cliente'] ?? '—'); ?></td>
                                <td class="px-4 py-3 text-center text-slate-600"><?php echo htmlspecialchars($processo['status_processo'] ?? '—'); ?></td>
                                <td class="px-4 py-3 text-center text-slate-600"><?php echo !empty($processo['data_atualizacao']) ? date('d/m/Y H:i', strtotime($processo['data_atualizacao'])) : '—'; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
const tabButtons = document.querySelectorAll('.tab-button');
const tabContents = document.querySelectorAll('.tab-content');

function activateTab(slug) {
    tabButtons.forEach(button => {
        const isActive = button.dataset.tab === slug;
        button.classList.toggle('border-theme-color', isActive);
        button.classList.toggle('text-theme-color', isActive);
    });

    tabContents.forEach(content => {
        content.classList.toggle('hidden', content.id !== `tab-${slug}`);
    });
}

const persistedTab = localStorage.getItem('relatoriosTab') || 'visao-geral';
activateTab(persistedTab);

tabButtons.forEach(button => {
    button.addEventListener('click', () => {
        const slug = button.dataset.tab;
        activateTab(slug);
        localStorage.setItem('relatoriosTab', slug);
    });
});
</script>
