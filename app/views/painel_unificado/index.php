<?php
/** @var array $fluxoCaixa */
/** @var array $processos */
/** @var array $filters */
/** @var array $vendedores */
/** @var array $sdrs */
?>
<div class="container-fluid py-3">
    <h1 class="h3 mb-3">Painel Unificado</h1>
    <?php require __DIR__ . '/../components/filters.php'; ?>

    <ul class="nav nav-tabs" id="painelTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="tab-fluxo" data-bs-toggle="tab" data-bs-target="#fluxo" type="button" role="tab">Fluxo de Caixa</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="tab-vendedores" data-bs-toggle="tab" data-bs-target="#vendedores" type="button" role="tab">Desempenho de Vendedores</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="tab-processos" data-bs-toggle="tab" data-bs-target="#processos" type="button" role="tab">Gestão de Processos</button>
        </li>
    </ul>
    <div class="tab-content border border-top-0 p-3" id="painelTabsContent">
        <div class="tab-pane fade show active" id="fluxo" role="tabpanel">
            <div class="row mb-3">
                <div class="col-md-4">
                    <div class="card text-bg-success">
                        <div class="card-body">
                            <h6 class="card-title">Entradas</h6>
                            <p class="card-text fs-4">R$ <?php echo number_format($fluxoCaixa['totais']['entradas'] ?? 0, 2, ',', '.'); ?></p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card text-bg-danger">
                        <div class="card-body">
                            <h6 class="card-title">Saídas</h6>
                            <p class="card-text fs-4">R$ <?php echo number_format($fluxoCaixa['totais']['saidas'] ?? 0, 2, ',', '.'); ?></p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card text-bg-primary">
                        <div class="card-body">
                            <h6 class="card-title">Saldo</h6>
                            <p class="card-text fs-4">R$ <?php echo number_format($fluxoCaixa['totais']['saldo'] ?? 0, 2, ',', '.'); ?></p>
                        </div>
                    </div>
                </div>
            </div>
            <h5>Movimentações</h5>
            <table class="table table-striped">
                <thead>
                <tr>
                    <th>Data</th>
                    <th>Tipo</th>
                    <th>Descrição</th>
                    <th>Cliente</th>
                    <th>Origem</th>
                    <th class="text-end">Valor</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($fluxoCaixa['movimentacoes'] ?? [] as $mov): ?>
                    <tr>
                        <td><?php echo htmlspecialchars(substr((string) ($mov['data'] ?? ''), 0, 10)); ?></td>
                        <td><?php echo htmlspecialchars($mov['tipo'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($mov['descricao'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($mov['cliente'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($mov['origem'] ?? ''); ?></td>
                        <td class="text-end">R$ <?php echo number_format((float) ($mov['valor'] ?? 0), 2, ',', '.'); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="tab-pane fade" id="vendedores" role="tabpanel">
            <h5 class="mb-3">Entradas por vendedor</h5>
            <table class="table table-hover">
                <thead>
                <tr>
                    <th>ID</th>
                    <th>Total</th>
                    <th>Quantidade</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($fluxoCaixa['por_vendedor'] ?? [] as $vendedor): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($vendedor['vendedor_id'] ?? $vendedor['vendedor'] ?? ''); ?></td>
                        <td>R$ <?php echo number_format((float) ($vendedor['total'] ?? 0), 2, ',', '.'); ?></td>
                        <td><?php echo (int) ($vendedor['quantidade'] ?? 0); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="tab-pane fade" id="processos" role="tabpanel">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h5 class="mb-0">Gestão de Processos</h5>
                <div class="btn-group">
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['export' => 'csv'])); ?>" class="btn btn-outline-secondary">Exportar CSV</a>
                    <button onclick="window.print()" class="btn btn-outline-secondary">Imprimir</button>
                </div>
            </div>
            <?php require __DIR__ . '/../components/table_processos.php'; ?>
        </div>
    </div>
</div>
