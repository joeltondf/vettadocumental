<?php
session_start();

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/app/core/auth_check.php';
require_once __DIR__ . '/app/core/access_control.php';
require_permission(['admin', 'financeiro', 'gerencia']);

function sanitizeDate(?string $value): string
{
    $trimmed = trim((string) $value);

    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $trimmed) ? $trimmed : '';
}

function fetchRealCashFlow(PDO $pdo, string $startDate, string $endDate): array
{
    $conditions = [];
    $params = [];

    if ($startDate !== '') {
        $conditions[] = 'DATE(p.data_pagamento_1) >= :entrada_inicio';
        $conditions[] = 'DATE(p.data_pagamento_2) >= :entrada_inicio';
        $params[':entrada_inicio'] = $startDate;
    }

    if ($endDate !== '') {
        $conditions[] = 'DATE(p.data_pagamento_1) <= :entrada_fim';
        $conditions[] = 'DATE(p.data_pagamento_2) <= :entrada_fim';
        $params[':entrada_fim'] = $endDate;
    }

    $whereEntrada = [];
    $whereRestante = [];

    foreach ($conditions as $condition) {
        if (str_contains($condition, 'data_pagamento_1')) {
            $whereEntrada[] = str_replace('data_pagamento_2', 'data_pagamento_1', $condition);
        }
        if (str_contains($condition, 'data_pagamento_2')) {
            $whereRestante[] = $condition;
        }
    }

    $whereEntradaSql = empty($whereEntrada) ? '' : ' AND ' . implode(' AND ', $whereEntrada);
    $whereRestanteSql = empty($whereRestante) ? '' : ' AND ' . implode(' AND ', $whereRestante);

    $sql = "(
                SELECT p.data_pagamento_1 AS data_recebimento,
                       c.nome_cliente AS cliente,
                       p.titulo AS processo,
                       'Entrada' AS tipo,
                       COALESCE(p.orcamento_valor_entrada, p.valor_recebido, 0) AS valor
                FROM processos p
                JOIN clientes c ON p.cliente_id = c.id
                WHERE p.data_pagamento_1 IS NOT NULL {$whereEntradaSql}
            )
            UNION ALL
            (
                SELECT p.data_pagamento_2 AS data_recebimento,
                       c.nome_cliente AS cliente,
                       p.titulo AS processo,
                       'Restante' AS tipo,
                       COALESCE(p.orcamento_valor_restante, p.valor_restante, 0) AS valor
                FROM processos p
                JOIN clientes c ON p.cliente_id = c.id
                WHERE p.data_pagamento_2 IS NOT NULL {$whereRestanteSql}
            )
            ORDER BY data_recebimento ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function fetchSalesByCompetence(PDO $pdo, string $startDate, string $endDate): array
{
    $where = [];
    $params = [];

    if ($startDate !== '') {
        $where[] = 'DATE(p.data_conversao) >= :competencia_inicio';
        $params[':competencia_inicio'] = $startDate;
    }

    if ($endDate !== '') {
        $where[] = 'DATE(p.data_conversao) <= :competencia_fim';
        $params[':competencia_fim'] = $endDate;
    }

    $whereSql = empty($where) ? '' : ' AND ' . implode(' AND ', $where);

    $sql = "SELECT p.data_conversao,
                   c.nome_cliente AS cliente,
                   p.titulo AS processo,
                   COALESCE(p.valor_total, p.orcamento_valor_entrada + p.orcamento_valor_restante) AS valor
            FROM processos p
            JOIN clientes c ON p.cliente_id = c.id
            WHERE p.data_conversao IS NOT NULL {$whereSql}
            ORDER BY p.data_conversao ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function fetchAccountsReceivable(PDO $pdo): array
{
    $sql = "SELECT c.nome_cliente AS cliente,
                   p.data_pagamento_2 AS vencimento,
                   CASE WHEN p.data_pagamento_1 IS NULL THEN COALESCE(p.orcamento_valor_entrada, p.valor_recebido, 0) ELSE 0 END AS valor_entrada_pendente,
                   CASE WHEN p.data_pagamento_2 IS NULL THEN COALESCE(p.orcamento_valor_restante, p.valor_restante, 0) ELSE 0 END AS valor_restante_pendente
            FROM processos p
            JOIN clientes c ON p.cliente_id = c.id
            WHERE p.status_fluxo_pagamento IN ('Pendente', 'Parcial')
            ORDER BY c.nome_cliente ASC";

    $stmt = $pdo->query($sql);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$entradaInicio = sanitizeDate($_GET['entrada_inicio'] ?? '');
$entradaFim = sanitizeDate($_GET['entrada_fim'] ?? '');
$competenciaInicio = sanitizeDate($_GET['competencia_inicio'] ?? '');
$competenciaFim = sanitizeDate($_GET['competencia_fim'] ?? '');

$realCashFlow = fetchRealCashFlow($pdo, $entradaInicio, $entradaFim);
$salesCompetence = fetchSalesByCompetence($pdo, $competenciaInicio, $competenciaFim);
$accountsReceivable = fetchAccountsReceivable($pdo);

function formatCurrency($value): string
{
    return 'R$ ' . number_format((float) $value, 2, ',', '.');
}

function formatDate(?string $value): string
{
    if (empty($value)) {
        return '-';
    }

    try {
        return (new DateTime($value))->format('d/m/Y');
    } catch (Exception $exception) {
        return $value;
    }
}

function sumCashFlow(array $entries): float
{
    return array_reduce($entries, static function ($carry, $item) {
        return $carry + (float) ($item['valor'] ?? 0);
    }, 0.0);
}

$totalCashFlow = sumCashFlow($realCashFlow);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatório de Caixa Real</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
</head>
<body class="bg-light">
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Relatório de Caixa Real</h1>
        <a href="dashboard.php" class="btn btn-secondary">&larr; Voltar</a>
    </div>

    <ul class="nav nav-tabs" id="cashFlowTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="entradas-tab" data-bs-toggle="tab" data-bs-target="#entradas" type="button" role="tab" aria-controls="entradas" aria-selected="true">Fluxo de Caixa Real</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="vendas-tab" data-bs-toggle="tab" data-bs-target="#vendas" type="button" role="tab" aria-controls="vendas" aria-selected="false">Vendas (Competência)</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="receber-tab" data-bs-toggle="tab" data-bs-target="#receber" type="button" role="tab" aria-controls="receber" aria-selected="false">Contas a Receber</button>
        </li>
    </ul>
    <div class="tab-content bg-white border border-top-0 rounded-bottom p-3" id="cashFlowTabsContent">
        <div class="tab-pane fade show active" id="entradas" role="tabpanel" aria-labelledby="entradas-tab">
            <form method="GET" class="row g-3 align-items-end mb-3">
                <div class="col-md-4">
                    <label for="entrada_inicio" class="form-label">Data Início (Pagamento)</label>
                    <input type="date" name="entrada_inicio" id="entrada_inicio" class="form-control" value="<?php echo htmlspecialchars($entradaInicio); ?>">
                </div>
                <div class="col-md-4">
                    <label for="entrada_fim" class="form-label">Data Fim (Pagamento)</label>
                    <input type="date" name="entrada_fim" id="entrada_fim" class="form-control" value="<?php echo htmlspecialchars($entradaFim); ?>">
                </div>
                <div class="col-md-4 d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Filtrar</button>
                    <a href="relatorio_caixa_real.php" class="btn btn-outline-secondary">Limpar</a>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-sm table-striped align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Data Recebimento</th>
                            <th>Cliente</th>
                            <th>Processo</th>
                            <th>Tipo</th>
                            <th class="text-end">Valor</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($realCashFlow)): ?>
                            <tr>
                                <td colspan="5" class="text-center text-muted">Nenhum recebimento encontrado no período.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($realCashFlow as $entrada): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars(formatDate($entrada['data_recebimento'])); ?></td>
                                    <td><?php echo htmlspecialchars($entrada['cliente']); ?></td>
                                    <td><?php echo htmlspecialchars($entrada['processo']); ?></td>
                                    <td><?php echo htmlspecialchars($entrada['tipo']); ?></td>
                                    <td class="text-end fw-semibold"><?php echo htmlspecialchars(formatCurrency($entrada['valor'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                    <tfoot class="table-light">
                        <tr>
                            <th colspan="4" class="text-end">Total do período</th>
                            <th class="text-end fw-bold"><?php echo htmlspecialchars(formatCurrency($totalCashFlow)); ?></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <div class="tab-pane fade" id="vendas" role="tabpanel" aria-labelledby="vendas-tab">
            <form method="GET" class="row g-3 align-items-end mb-3">
                <div class="col-md-4">
                    <label for="competencia_inicio" class="form-label">Data Início (Conversão)</label>
                    <input type="date" name="competencia_inicio" id="competencia_inicio" class="form-control" value="<?php echo htmlspecialchars($competenciaInicio); ?>">
                </div>
                <div class="col-md-4">
                    <label for="competencia_fim" class="form-label">Data Fim (Conversão)</label>
                    <input type="date" name="competencia_fim" id="competencia_fim" class="form-control" value="<?php echo htmlspecialchars($competenciaFim); ?>">
                </div>
                <div class="col-md-4 d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Filtrar</button>
                    <a href="relatorio_caixa_real.php#vendas" class="btn btn-outline-secondary">Limpar</a>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-sm table-striped align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Data Conversão</th>
                            <th>Cliente</th>
                            <th>Processo</th>
                            <th class="text-end">Valor</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($salesCompetence)): ?>
                            <tr>
                                <td colspan="4" class="text-center text-muted">Nenhuma venda encontrada no período.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($salesCompetence as $sale): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars(formatDate($sale['data_conversao'])); ?></td>
                                    <td><?php echo htmlspecialchars($sale['cliente']); ?></td>
                                    <td><?php echo htmlspecialchars($sale['processo']); ?></td>
                                    <td class="text-end fw-semibold"><?php echo htmlspecialchars(formatCurrency($sale['valor'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="tab-pane fade" id="receber" role="tabpanel" aria-labelledby="receber-tab">
            <div class="table-responsive">
                <table class="table table-sm table-striped align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Cliente</th>
                            <th>Vencimento</th>
                            <th class="text-end">Valor Entrada Pendente</th>
                            <th class="text-end">Valor Restante Pendente</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($accountsReceivable)): ?>
                            <tr>
                                <td colspan="4" class="text-center text-muted">Nenhum recebimento pendente encontrado.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($accountsReceivable as $account): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($account['cliente']); ?></td>
                                    <td><?php echo htmlspecialchars(formatDate($account['vencimento'])); ?></td>
                                    <td class="text-end fw-semibold"><?php echo htmlspecialchars(formatCurrency($account['valor_entrada_pendente'])); ?></td>
                                    <td class="text-end fw-semibold"><?php echo htmlspecialchars(formatCurrency($account['valor_restante_pendente'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
