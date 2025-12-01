<?php
$filters = $filters ?? [];
$resultado = $resultado ?? [];
$vendedores = $vendedores ?? [];
$sdrs = $sdrs ?? [];

$totais = $resultado['totais'] ?? ['receitas' => 0, 'despesas' => 0, 'saldo' => 0];
$kpis = $resultado['kpis'] ?? ['faturamento_total' => 0, 'inadimplencia' => 0, 'melhor_vendedor' => ['id' => null, 'total' => 0], 'melhor_sdr' => ['id' => null, 'total' => 0]];
$entradasPorDia = $resultado['entradas_por_dia'] ?? [];
$pagamentos = $resultado['pagamentos'] ?? [];
$receitasAvulsas = $resultado['receitas_avulsas'] ?? [];
$porVendedor = $resultado['por_vendedor'] ?? [];
$porSdr = $resultado['por_sdr'] ?? [];

$mapaVendedores = [];
foreach ($vendedores as $vendedor) {
    $mapaVendedores[(int) $vendedor['id']] = $vendedor['nome_vendedor'];
}
$mapaVendedores[0] = 'Receita Avulsa / Sem vendedor';

$mapaSdrs = [];
foreach ($sdrs as $sdr) {
    $mapaSdrs[(int) $sdr['id']] = $sdr['nome_completo'];
}
$mapaSdrs[0] = 'Sem SDR';

$formatCurrency = static function ($valor): string {
    $numero = is_numeric($valor) ? (float) $valor : 0.0;
    return 'R$ ' . number_format($numero, 2, ',', '.');
};

$formatDate = static function (?string $data): string {
    if (empty($data)) {
        return '—';
    }

    try {
        $dt = new DateTime($data);
        return $dt->format('d/m/Y');
    } catch (Throwable $th) {
        return '—';
    }
};

$recebimentosConfirmados = [];
foreach ($pagamentos as $pagamento) {
    $recebimentosConfirmados[] = [
        'cliente' => $pagamento['cliente_nome'] ?? '—',
        'processo' => $pagamento['processo_titulo'] ?? ('#' . ($pagamento['processo_id'] ?? '—')),
        'valor' => $pagamento['valor'] ?? 0,
        'data' => $pagamento['data_pagamento'] ?? null,
    ];
}

foreach ($receitasAvulsas as $receitaAvulsa) {
    $recebimentosConfirmados[] = [
        'cliente' => $receitaAvulsa['cliente_id'] ? ('Cliente #' . (int) $receitaAvulsa['cliente_id']) : 'Receita Avulsa',
        'processo' => $receitaAvulsa['processo_id'] ? ('Processo #' . (int) $receitaAvulsa['processo_id']) : '—',
        'valor' => $receitaAvulsa['valor'] ?? 0,
        'data' => $receitaAvulsa['data_lancamento'] ?? null,
    ];
}
?>

<div class="mx-auto max-w-7xl px-4 py-8 space-y-6">
    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
            <p class="text-sm text-gray-500">Regime de Caixa • Data de pagamento real</p>
            <h1 class="text-3xl font-bold text-gray-800">Relatórios &amp; BI</h1>
        </div>
        <form class="flex flex-wrap gap-3 items-end" method="get" action="relatorios_bi.php">
            <div>
                <label for="start_date" class="block text-sm font-medium text-gray-700">Data inicial</label>
                <input type="date" name="start_date" id="start_date" value="<?= htmlspecialchars($filters['start_date'] ?? ''); ?>" class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            </div>
            <div>
                <label for="end_date" class="block text-sm font-medium text-gray-700">Data final</label>
                <input type="date" name="end_date" id="end_date" value="<?= htmlspecialchars($filters['end_date'] ?? ''); ?>" class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            </div>
            <button type="submit" class="inline-flex items-center gap-2 rounded-md bg-theme-color px-4 py-2 text-white font-semibold shadow hover:opacity-90">
                <i class="fas fa-search"></i>
                Aplicar
            </button>
        </form>
    </div>

    <div class="flex flex-wrap gap-2" role="tablist">
        <button class="tab-btn active" data-tab="aba-caixa">Caixa Real (Empresa)</button>
        <button class="tab-btn" data-tab="aba-vendedor">Performance Vendedores</button>
        <button class="tab-btn" data-tab="aba-sdr">Performance SDR</button>
        <button class="tab-btn" data-tab="aba-gerencial">Gerencial</button>
    </div>

    <div class="tab-panel" id="aba-caixa">
        <div class="grid gap-4 md:grid-cols-3">
            <div class="rounded-lg bg-white p-4 shadow">
                <p class="text-sm text-gray-500">Receitas (entradas + avulsas)</p>
                <p class="text-2xl font-semibold text-emerald-700"><?= $formatCurrency($totais['receitas'] ?? 0); ?></p>
            </div>
            <div class="rounded-lg bg-white p-4 shadow">
                <p class="text-sm text-gray-500">Despesas</p>
                <p class="text-2xl font-semibold text-rose-700"><?= $formatCurrency($totais['despesas'] ?? 0); ?></p>
            </div>
            <div class="rounded-lg bg-white p-4 shadow">
                <p class="text-sm text-gray-500">Saldo do período</p>
                <p class="text-2xl font-semibold text-indigo-700"><?= $formatCurrency($totais['saldo'] ?? 0); ?></p>
            </div>
        </div>

        <div class="mt-6 rounded-lg bg-white p-4 shadow">
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-lg font-semibold text-gray-800">Entradas por dia</h3>
                <p class="text-sm text-gray-500">Somatório diário de pagamentos efetivos</p>
            </div>
            <div class="space-y-2">
                <?php if (empty($entradasPorDia)): ?>
                    <p class="text-sm text-gray-500">Nenhum recebimento no período.</p>
                <?php else: ?>
                    <?php
                    $maior = max($entradasPorDia);
                    foreach ($entradasPorDia as $dia => $valor):
                        $percentual = $maior > 0 ? round(($valor / $maior) * 100) : 0;
                    ?>
                        <div>
                            <div class="flex justify-between text-sm text-gray-600">
                                <span><?= $formatDate($dia); ?></span>
                                <span><?= $formatCurrency($valor); ?></span>
                            </div>
                            <div class="h-3 rounded bg-gray-100">
                                <div class="h-3 rounded bg-theme-color" style="width: <?= $percentual; ?>%"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="mt-6 rounded-lg bg-white p-4 shadow">
            <h3 class="text-lg font-semibold text-gray-800 mb-3">Recebimentos confirmados</h3>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="bg-gray-50 text-left text-gray-600">
                            <th class="p-3">Cliente</th>
                            <th class="p-3">Processo</th>
                            <th class="p-3">Valor</th>
                            <th class="p-3">Data</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recebimentosConfirmados)): ?>
                            <tr><td colspan="4" class="p-4 text-center text-gray-500">Nenhum registro no período.</td></tr>
                        <?php else: ?>
                            <?php foreach ($recebimentosConfirmados as $recebimento): ?>
                                <tr class="border-b last:border-0">
                                    <td class="p-3 text-gray-800"><?= htmlspecialchars($recebimento['cliente']); ?></td>
                                    <td class="p-3 text-gray-600"><?= htmlspecialchars($recebimento['processo']); ?></td>
                                    <td class="p-3 font-semibold text-emerald-700"><?= $formatCurrency($recebimento['valor']); ?></td>
                                    <td class="p-3 text-gray-600"><?= $formatDate($recebimento['data']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="tab-panel hidden" id="aba-vendedor">
        <div class="rounded-lg bg-white p-4 shadow">
            <h3 class="text-lg font-semibold text-gray-800 mb-3">Recebimentos por vendedor</h3>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="bg-gray-50 text-left text-gray-600">
                            <th class="p-3">Vendedor</th>
                            <th class="p-3">Total Recebido</th>
                            <th class="p-3">Ticket Médio</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($porVendedor)): ?>
                            <tr><td colspan="3" class="p-4 text-center text-gray-500">Nenhum recebimento encontrado.</td></tr>
                        <?php else: ?>
                            <?php foreach ($porVendedor as $dados): ?>
                                <?php
                                    $qtd = (int) ($dados['quantidade'] ?? 0);
                                    $ticketMedio = $qtd > 0 ? (($dados['total'] ?? 0) / $qtd) : 0;
                                    $nome = $mapaVendedores[$dados['vendedor_id'] ?? 0] ?? ('Vendedor #' . (int) ($dados['vendedor_id'] ?? 0));
                                ?>
                                <tr class="border-b last:border-0">
                                    <td class="p-3 text-gray-800"><?= htmlspecialchars($nome); ?></td>
                                    <td class="p-3 font-semibold text-emerald-700"><?= $formatCurrency($dados['total'] ?? 0); ?></td>
                                    <td class="p-3 text-gray-600"><?= $formatCurrency($ticketMedio); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="tab-panel hidden" id="aba-sdr">
        <div class="rounded-lg bg-white p-4 shadow">
            <h3 class="text-lg font-semibold text-gray-800 mb-3">Recebimentos por SDR</h3>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="bg-gray-50 text-left text-gray-600">
                            <th class="p-3">SDR</th>
                            <th class="p-3">Total Gerado (R$)</th>
                            <th class="p-3">Qtd Vendas Pagas</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($porSdr)): ?>
                            <tr><td colspan="3" class="p-4 text-center text-gray-500">Nenhum recebimento encontrado.</td></tr>
                        <?php else: ?>
                            <?php foreach ($porSdr as $dados): ?>
                                <?php
                                    $nome = $mapaSdrs[$dados['sdr_id'] ?? 0] ?? ('SDR #' . (int) ($dados['sdr_id'] ?? 0));
                                ?>
                                <tr class="border-b last:border-0">
                                    <td class="p-3 text-gray-800"><?= htmlspecialchars($nome); ?></td>
                                    <td class="p-3 font-semibold text-indigo-700"><?= $formatCurrency($dados['total'] ?? 0); ?></td>
                                    <td class="p-3 text-gray-600"><?php echo (int) ($dados['quantidade'] ?? 0); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="tab-panel hidden" id="aba-gerencial">
        <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-lg bg-white p-4 shadow">
                <p class="text-sm text-gray-500">Faturamento total</p>
                <p class="text-2xl font-semibold text-emerald-700"><?= $formatCurrency($kpis['faturamento_total'] ?? 0); ?></p>
            </div>
            <div class="rounded-lg bg-white p-4 shadow">
                <p class="text-sm text-gray-500">Inadimplência (&gt;30 dias)</p>
                <p class="text-2xl font-semibold text-rose-700"><?= $formatCurrency($kpis['inadimplencia'] ?? 0); ?></p>
            </div>
            <div class="rounded-lg bg-white p-4 shadow">
                <p class="text-sm text-gray-500">Melhor vendedor</p>
                <p class="text-lg font-semibold text-gray-800">
                    <?php
                        $melhorVendedorId = $kpis['melhor_vendedor']['id'] ?? null;
                        echo $melhorVendedorId !== null ? htmlspecialchars($mapaVendedores[$melhorVendedorId] ?? ('Vendedor #' . $melhorVendedorId)) : '—';
                    ?>
                </p>
                <p class="text-sm text-emerald-700 font-semibold"><?= $formatCurrency($kpis['melhor_vendedor']['total'] ?? 0); ?></p>
            </div>
            <div class="rounded-lg bg-white p-4 shadow">
                <p class="text-sm text-gray-500">Melhor SDR</p>
                <p class="text-lg font-semibold text-gray-800">
                    <?php
                        $melhorSdrId = $kpis['melhor_sdr']['id'] ?? null;
                        echo $melhorSdrId !== null ? htmlspecialchars($mapaSdrs[$melhorSdrId] ?? ('SDR #' . $melhorSdrId)) : '—';
                    ?>
                </p>
                <p class="text-sm text-indigo-700 font-semibold"><?= $formatCurrency($kpis['melhor_sdr']['total'] ?? 0); ?></p>
            </div>
        </div>
    </div>
</div>

<style>
    .tab-btn {
        padding: 0.5rem 1rem;
        border-radius: 0.375rem;
        font-size: 0.875rem;
        font-weight: 600;
        background-color: #ffffff;
        color: #374151;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
        transition: background-color 0.2s ease, color 0.2s ease;
    }

    .tab-btn:hover {
        background-color: #f9fafb;
    }

    .tab-btn.active {
        background-color: var(--theme-color);
        color: #ffffff;
    }
</style>

<script>
    document.querySelectorAll('.tab-btn').forEach((btn) => {
        btn.addEventListener('click', () => {
            const target = btn.dataset.tab;

            document.querySelectorAll('.tab-btn').forEach((b) => b.classList.remove('active'));
            document.querySelectorAll('.tab-panel').forEach((panel) => panel.classList.add('hidden'));

            btn.classList.add('active');
            document.getElementById(target)?.classList.remove('hidden');
        });
    });
</script>
