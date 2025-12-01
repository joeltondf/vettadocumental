<?php
$filters = $filters ?? [];
$dadosFluxo = $dadosFluxo ?? [];
$processos = $processos ?? [];
$vendedores = $vendedores ?? [];
$sdrs = $sdrs ?? [];
$statusFinanceiroOptions = $statusFinanceiroOptions ?? [];

$totais = $dadosFluxo['totais'] ?? ['entradas' => 0, 'saidas' => 0, 'saldo' => 0];
$entradasPorDia = $dadosFluxo['entradas_por_dia'] ?? [];
$porVendedor = $dadosFluxo['por_vendedor'] ?? [];
$pagamentos = $dadosFluxo['pagamentos'] ?? [];
$receitasAvulsas = $dadosFluxo['receitas_avulsas'] ?? [];
$despesas = $dadosFluxo['despesas'] ?? [];

$mapaVendedores = [];
foreach ($vendedores as $vendedor) {
    $mapaVendedores[(int) $vendedor['id']] = $vendedor['nome_vendedor'];
}

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

$entradasUnificadas = [];
foreach ($pagamentos as $pagamento) {
    $entradasUnificadas[] = [
        'cliente' => $pagamento['cliente_nome'] ?? '—',
        'processo' => $pagamento['processo_titulo'] ?? ('#' . ($pagamento['processo_id'] ?? '—')),
        'valor' => $pagamento['valor'] ?? 0,
        'data' => $pagamento['data_pagamento'] ?? null,
        'tipo' => $pagamento['tipo_parcela'] ?? 'Entrada',
        'direcao' => 'Entrada',
    ];
}

foreach ($receitasAvulsas as $receitaAvulsa) {
    $entradasUnificadas[] = [
        'cliente' => $receitaAvulsa['cliente_id'] ? ('Cliente #' . (int) $receitaAvulsa['cliente_id']) : 'Receita Avulsa',
        'processo' => $receitaAvulsa['processo_id'] ? ('Processo #' . (int) $receitaAvulsa['processo_id']) : '—',
        'valor' => $receitaAvulsa['valor'] ?? 0,
        'data' => $receitaAvulsa['data_lancamento'] ?? null,
        'tipo' => 'Receita Manual',
        'direcao' => 'Entrada',
    ];
}

foreach ($despesas as $despesa) {
    $entradasUnificadas[] = [
        'cliente' => $despesa['cliente_id'] ? ('Cliente #' . (int) $despesa['cliente_id']) : '—',
        'processo' => $despesa['processo_id'] ? ('Processo #' . (int) $despesa['processo_id']) : '—',
        'valor' => $despesa['valor'] ?? 0,
        'data' => $despesa['data_lancamento'] ?? null,
        'tipo' => $despesa['descricao'] ?? 'Despesa',
        'direcao' => 'Saída',
    ];
}

usort($entradasUnificadas, static function ($a, $b) {
    return strcmp($b['data'] ?? '', $a['data'] ?? '');
});
?>

<div class="mx-auto max-w-7xl px-4 py-8 space-y-6">
    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
            <p class="text-sm text-gray-500">Painel consolidado de finanças e operações</p>
            <h1 class="text-3xl font-bold text-gray-800">Painel Unificado</h1>
        </div>
        <?php
            $action = 'painel.php';
            require __DIR__ . '/../components/filters.php';
        ?>
    </div>

    <div class="flex flex-wrap gap-2" role="tablist">
        <button class="tab-btn active" data-tab="aba-caixa">Fluxo de Caixa</button>
        <button class="tab-btn" data-tab="aba-vendedor">Desempenho de Vendedores</button>
        <button class="tab-btn" data-tab="aba-processos">Gestão de Processos</button>
    </div>

    <div class="tab-panel" id="aba-caixa">
        <div class="grid gap-4 md:grid-cols-3">
            <div class="rounded-lg bg-white p-4 shadow">
                <p class="text-sm text-gray-500">Total Entradas (Período)</p>
                <p class="text-2xl font-semibold text-emerald-700"><?= $formatCurrency($totais['entradas'] ?? 0); ?></p>
            </div>
            <div class="rounded-lg bg-white p-4 shadow">
                <p class="text-sm text-gray-500">Total Saídas</p>
                <p class="text-2xl font-semibold text-rose-700"><?= $formatCurrency($totais['saidas'] ?? 0); ?></p>
            </div>
            <div class="rounded-lg bg-white p-4 shadow">
                <p class="text-sm text-gray-500">Saldo do Período</p>
                <p class="text-2xl font-semibold text-indigo-700"><?= $formatCurrency($totais['saldo'] ?? 0); ?></p>
            </div>
        </div>

        <div class="mt-6 rounded-lg bg-white p-4 shadow">
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-lg font-semibold text-gray-800">Evolução diária (entradas pagas)</h3>
                <p class="text-sm text-gray-500">Somatório diário por data de pagamento</p>
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
            <h3 class="text-lg font-semibold text-gray-800 mb-3">Entradas e saídas confirmadas</h3>
            <div class="flex justify-end gap-2 mb-3">
                <button type="button" onclick="window.print();" class="inline-flex items-center gap-2 rounded-md bg-gray-600 px-3 py-2 text-white text-sm font-semibold shadow hover:bg-gray-700">
                    <i class="fas fa-print"></i> Imprimir
                </button>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="bg-gray-50 text-left text-gray-600">
                            <th class="p-3">Cliente</th>
                            <th class="p-3">Origem</th>
                            <th class="p-3">Valor</th>
                            <th class="p-3">Data</th>
                            <th class="p-3">Tipo</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($entradasUnificadas)): ?>
                            <tr><td colspan="5" class="p-4 text-center text-gray-500">Nenhum registro no período.</td></tr>
                        <?php else: ?>
                            <?php foreach ($entradasUnificadas as $registro): ?>
                                <tr class="border-b last:border-0">
                                    <td class="p-3 text-gray-800"><?= htmlspecialchars($registro['cliente']); ?></td>
                                    <td class="p-3 text-gray-600"><?= htmlspecialchars($registro['processo']); ?></td>
                                    <td class="p-3 font-semibold <?= $registro['direcao'] === 'Saída' ? 'text-rose-700' : 'text-emerald-700'; ?>"><?= $formatCurrency($registro['valor'] * ($registro['direcao'] === 'Saída' ? -1 : 1)); ?></td>
                                    <td class="p-3 text-gray-600"><?= $formatDate($registro['data']); ?></td>
                                    <td class="p-3 text-gray-600"><?= htmlspecialchars($registro['direcao'] . ' • ' . $registro['tipo']); ?></td>
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
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-lg font-semibold text-gray-800">Vendedor x Valor recebido no caixa</h3>
                <button type="button" onclick="window.print();" class="inline-flex items-center gap-2 rounded-md bg-gray-600 px-3 py-2 text-white text-sm font-semibold shadow hover:bg-gray-700">
                    <i class="fas fa-print"></i> Imprimir
                </button>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="bg-gray-50 text-left text-gray-600">
                            <th class="p-3">Vendedor</th>
                            <th class="p-3">Valor Total Recebido</th>
                            <th class="p-3">Qtd Pagamentos</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($porVendedor)): ?>
                            <tr><td colspan="3" class="p-4 text-center text-gray-500">Nenhum recebimento encontrado.</td></tr>
                        <?php else: ?>
                            <?php foreach ($porVendedor as $dados): ?>
                                <?php $nome = $mapaVendedores[$dados['vendedor_id'] ?? 0] ?? ('Vendedor #' . (int) ($dados['vendedor_id'] ?? 0)); ?>
                                <tr class="border-b last:border-0">
                                    <td class="p-3 text-gray-800"><?= htmlspecialchars($nome); ?></td>
                                    <td class="p-3 font-semibold text-emerald-700"><?= $formatCurrency($dados['total'] ?? 0); ?></td>
                                    <td class="p-3 text-gray-600"><?php echo (int) ($dados['quantidade'] ?? 0); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="tab-panel hidden" id="aba-processos">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <h3 class="text-lg font-semibold text-gray-800">Gestão de Processos</h3>
            <div class="flex flex-wrap gap-2">
                <button type="button" onclick="window.print();" class="inline-flex items-center gap-2 rounded-md bg-gray-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-gray-700">
                    <span class="fas fa-print" aria-hidden="true"></span>
                    Imprimir
                </button>
            </div>
        </div>
        <div class="mt-3">
            <?php
                $processos = $processos ?? [];
                require __DIR__ . '/../components/table_processos.php';
            ?>
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
