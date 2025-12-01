<?php

namespace App\Services;

use DateTime;
use PDO;

class FinanceiroService
{
    public function calcularRegimeDeCaixa(
        PDO $pdo,
        string $startDate,
        string $endDate,
        ?int $vendedorId = null,
        ?int $sdrId = null,
        ?string $statusFinanceiro = null
    ): array {
        $inicio = $startDate . ' 00:00:00';
        $fim = $endDate . ' 23:59:59';

        $movimentacoes = $this->buscarMovimentacoes($pdo, $inicio, $fim, $vendedorId, $sdrId);
        $movimentacoes = $this->filtrarPorStatusFinanceiro($movimentacoes, $statusFinanceiro);

        $entradas = array_values(array_filter($movimentacoes, static function (array $movimentacao): bool {
            return ($movimentacao['tipo'] ?? '') === 'Entrada';
        }));

        $saidas = array_values(array_filter($movimentacoes, static function (array $movimentacao): bool {
            return ($movimentacao['tipo'] ?? '') === 'Saída';
        }));

        $totais = $this->montarTotais($entradas, $saidas);
        $entradasPorDia = $this->agruparEntradasPorDia($entradas);
        $previsao = $this->buscarPrevisaoPagamentos($pdo, $vendedorId, $sdrId, $statusFinanceiro);

        return [
            'pagamentos' => $entradas,
            'receitas_avulsas' => [],
            'despesas' => $saidas,
            'movimentacoes' => $movimentacoes,
            'entradas_por_dia' => $entradasPorDia,
            'por_vendedor' => $this->agruparMovimentacoesPor($entradas, 'vendedor_id'),
            'por_sdr' => $this->agruparMovimentacoesPor($entradas, 'sdr_id'),
            'totais' => $totais,
            'previsao' => $previsao,
        ];
    }

    public function calcularRegimeDeCompetencia(
        PDO $pdo,
        string $startDate,
        string $endDate,
        ?int $vendedorId = null,
        ?int $sdrId = null,
        ?string $statusFinanceiro = null
    ): array {
        $inicio = $startDate . ' 00:00:00';
        $fim = $endDate . ' 23:59:59';

        $dataCompetenciaExpr = 'COALESCE(p.data_conversao, p.data_lancamento_receita, p.data_criacao)';

        $sql = "SELECT p.id AS processo_id,
                       p.titulo AS descricao,
                       c.nome_cliente AS cliente_nome,
                       CAST(COALESCE(p.orcamento_valor_entrada, 0) + COALESCE(p.orcamento_valor_restante, 0) AS DECIMAL(10,2)) AS valor,
                       {$dataCompetenciaExpr} AS data_movimento,
                       COALESCE(p.status_financeiro, p.status_fluxo_pagamento, p.status_processo) AS status_financeiro,
                       p.vendedor_id,
                       p.sdr_id
                FROM processos p
                JOIN clientes c ON p.cliente_id = c.id
                WHERE {$dataCompetenciaExpr} BETWEEN :inicio AND :fim";

        $params = [
            ':inicio' => $inicio,
            ':fim' => $fim,
        ];

        if ($vendedorId !== null) {
            $sql .= ' AND p.vendedor_id = :vendedorId';
            $params[':vendedorId'] = $vendedorId;
        }

        if ($sdrId !== null) {
            $sql .= ' AND p.sdr_id = :sdrId';
            $params[':sdrId'] = $sdrId;
        }

        if (!empty($statusFinanceiro)) {
            $sql .= ' AND LOWER(COALESCE(p.status_financeiro, p.status_fluxo_pagamento, p.status_processo)) = :status';
            $params[':status'] = mb_strtolower($statusFinanceiro);
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        $movimentacoes = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $movimentacoes[] = [
                'tipo' => 'Entrada',
                'descricao' => $row['descricao'] ?? 'Processo #' . ($row['processo_id'] ?? ''),
                'valor' => (float) ($row['valor'] ?? 0),
                'data' => $row['data_movimento'] ?? null,
                'origem' => 'Processo',
                'cliente_nome' => $row['cliente_nome'] ?? null,
                'processo_id' => isset($row['processo_id']) ? (int) $row['processo_id'] : null,
                'processo_titulo' => $row['descricao'] ?? null,
                'data_pagamento' => $row['data_movimento'] ?? null,
                'tipo_parcela' => 'Entrada',
                'vendedor_id' => $row['vendedor_id'] ?? null,
                'sdr_id' => $row['sdr_id'] ?? null,
                'status_financeiro' => $row['status_financeiro'] ?? null,
            ];
        }

        $totais = $this->montarTotais($movimentacoes, []);

        return [
            'pagamentos' => $movimentacoes,
            'receitas_avulsas' => [],
            'despesas' => [],
            'movimentacoes' => $movimentacoes,
            'entradas_por_dia' => $this->agruparEntradasPorDia($movimentacoes),
            'por_vendedor' => $this->agruparMovimentacoesPor($movimentacoes, 'vendedor_id'),
            'por_sdr' => $this->agruparMovimentacoesPor($movimentacoes, 'sdr_id'),
            'totais' => $totais,
            'previsao' => $this->buscarPrevisaoPagamentos($pdo, $vendedorId, $sdrId, $statusFinanceiro),
        ];
    }

    public function buscarMovimentacoes(
        PDO $pdo,
        string $inicio,
        string $fim,
        ?int $vendedorId = null,
        ?int $sdrId = null
    ): array {
        $sql = "SELECT tipo,
                       descricao,
                       valor,
                       data,
                       origem,
                       vendedor_id,
                       sdr_id
                FROM v_movimentacoes
                WHERE data BETWEEN :inicio AND :fim";

        $params = [
            ':inicio' => $inicio,
            ':fim' => $fim,
        ];

        if ($vendedorId !== null) {
            $sql .= ' AND vendedor_id = :vendedorId';
            $params[':vendedorId'] = $vendedorId;
        }

        if ($sdrId !== null) {
            $sql .= ' AND sdr_id = :sdrId';
            $params[':sdrId'] = $sdrId;
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        $registros = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $registros[] = [
                'tipo' => $row['tipo'] ?? 'Entrada',
                'descricao' => $row['descricao'] ?? '',
                'valor' => (float) ($row['valor'] ?? 0),
                'data' => $row['data'] ?? null,
                'origem' => $row['origem'] ?? null,
                'processo_titulo' => $row['descricao'] ?? null,
                'data_pagamento' => $row['data'] ?? null,
                'tipo_parcela' => $row['tipo'] ?? 'Entrada',
                'vendedor_id' => $row['vendedor_id'] ?? null,
                'sdr_id' => $row['sdr_id'] ?? null,
            ];
        }

        return $registros;
    }

    private function agruparEntradasPorDia(array $entradas): array
    {
        $dias = [];

        foreach ($entradas as $registro) {
            $data = substr((string) ($registro['data_pagamento'] ?? $registro['data'] ?? ''), 0, 10);
            if ($data === '') {
                continue;
            }

            $dias[$data] = ($dias[$data] ?? 0) + (float) $registro['valor'];
        }

        ksort($dias);

        return $dias;
    }

    private function agruparMovimentacoesPor(array $movimentacoes, string $chave): array
    {
        $agrupado = [];

        foreach ($movimentacoes as $registro) {
            $id = $registro[$chave] ?? null;
            if ($id === null) {
                continue;
            }

            if (!isset($agrupado[$id])) {
                $agrupado[$id] = [
                    $chave => (int) $id,
                    'total' => 0.0,
                    'quantidade' => 0,
                ];
            }

            $agrupado[$id]['total'] += (float) $registro['valor'];
            $agrupado[$id]['quantidade']++;
        }

        return $agrupado;
    }

    private function montarTotais(array $entradas, array $saidas): array
    {
        $totalEntradas = array_reduce($entradas, static function ($carry, $item) {
            return $carry + (float) $item['valor'];
        }, 0.0);

        $totalSaidas = array_reduce($saidas, static function ($carry, $item) {
            return $carry + (float) $item['valor'];
        }, 0.0);

        $saldo = round($totalEntradas - $totalSaidas, 2);

        return [
            'entradas' => round($totalEntradas, 2),
            'saidas' => round($totalSaidas, 2),
            'saldo' => $saldo,
        ];
    }

    private function filtrarPorStatusFinanceiro(array $movimentacoes, ?string $statusFinanceiro): array
    {
        if (empty($statusFinanceiro)) {
            return $movimentacoes;
        }

        $statusFiltro = mb_strtolower($statusFinanceiro);

        return array_values(array_filter($movimentacoes, static function (array $movimentacao) use ($statusFiltro): bool {
            if (!isset($movimentacao['status_financeiro'])) {
                return true;
            }

            return mb_strtolower((string) $movimentacao['status_financeiro']) === $statusFiltro;
        }));
    }

    private function buscarPrevisaoPagamentos(
        PDO $pdo,
        ?int $vendedorId = null,
        ?int $sdrId = null,
        ?string $statusFinanceiro = null
    ): array {
        $hoje = (new DateTime('now'))->format('Y-m-d');

        $sql = "SELECT id AS processo_id,
                       titulo,
                       cliente_id,
                       vendedor_id,
                       sdr_id,
                       CAST(COALESCE(orcamento_valor_entrada, 0) AS DECIMAL(10,2)) AS orcamento_valor_entrada,
                       CAST(COALESCE(orcamento_valor_restante, 0) AS DECIMAL(10,2)) AS orcamento_valor_restante,
                       data_pagamento_1,
                       data_pagamento_2,
                       status_processo
                FROM processos
                WHERE (
                        data_pagamento_1 IS NULL OR data_pagamento_1 > :hoje
                      )
                   OR (
                        data_pagamento_2 IS NULL OR data_pagamento_2 > :hoje
                      )
                  AND (status_processo IS NULL OR status_processo NOT IN ('Concluído', 'Finalizado', 'Cancelado', 'Recusado'))";

        $params = [':hoje' => $hoje];

        if ($vendedorId !== null) {
            $sql .= ' AND vendedor_id = :vendedorId';
            $params[':vendedorId'] = $vendedorId;
        }

        if ($sdrId !== null) {
            $sql .= ' AND sdr_id = :sdrId';
            $params[':sdrId'] = $sdrId;
        }

        if (!empty($statusFinanceiro)) {
            $sql .= ' AND LOWER(COALESCE(status_financeiro, status_fluxo_pagamento, status_processo)) = :status';
            $params[':status'] = mb_strtolower($statusFinanceiro);
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        $futuros = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if (empty($row['data_pagamento_1']) || $row['data_pagamento_1'] > $hoje) {
                $futuros[] = [
                    'processo_id' => (int) $row['processo_id'],
                    'titulo' => $row['titulo'],
                    'cliente_id' => $row['cliente_id'],
                    'valor' => (float) $row['orcamento_valor_entrada'],
                    'data_prevista' => $row['data_pagamento_1'],
                    'parcela' => 'Entrada',
                ];
            }

            if (empty($row['data_pagamento_2']) || $row['data_pagamento_2'] > $hoje) {
                $futuros[] = [
                    'processo_id' => (int) $row['processo_id'],
                    'titulo' => $row['titulo'],
                    'cliente_id' => $row['cliente_id'],
                    'valor' => (float) $row['orcamento_valor_restante'],
                    'data_prevista' => $row['data_pagamento_2'],
                    'parcela' => 'Restante',
                ];
            }
        }

        usort($futuros, static function ($a, $b) {
            return strcmp($a['data_prevista'] ?? '', $b['data_prevista'] ?? '');
        });

        return $futuros;
    }

}
