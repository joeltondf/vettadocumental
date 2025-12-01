<?php

class FinanceiroCalculator
{
    public static function calcularRegimeDeCaixa(PDO $pdo, string $startDate, string $endDate): array
    {
        $inicio = $startDate . ' 00:00:00';
        $fim = $endDate . ' 23:59:59';

        $pagamentos = self::buscarPagamentosDeProcessos($pdo, $inicio, $fim);
        $receitasAvulsas = self::buscarLancamentosPorTipo($pdo, $inicio, $fim, 'RECEITA');
        $despesas = self::buscarLancamentosPorTipo($pdo, $inicio, $fim, 'DESPESA');

        $movimentacoes = self::montarMovimentacoes($pagamentos, $receitasAvulsas, $despesas);
        $totais = self::montarTotais($pagamentos, $receitasAvulsas, $despesas);
        $entradasPorDia = self::agruparEntradasPorDia($pagamentos, $receitasAvulsas);
        $previsao = self::buscarPrevisaoPagamentos($pdo);

        return [
            'pagamentos' => $pagamentos,
            'receitas_avulsas' => $receitasAvulsas,
            'despesas' => $despesas,
            'movimentacoes' => $movimentacoes,
            'entradas_por_dia' => $entradasPorDia,
            'por_vendedor' => self::agruparPorChave($pagamentos, 'vendedor_id'),
            'por_sdr' => self::agruparPorChave($pagamentos, 'sdr_id'),
            'totais' => $totais,
            'previsao' => $previsao,
        ];
    }

    private static function buscarPagamentosDeProcessos(PDO $pdo, string $inicio, string $fim): array
    {
        $sql = "SELECT p.id AS processo_id,
                       p.titulo,
                       p.vendedor_id,
                       p.sdr_id,
                       c.nome_cliente AS cliente_nome,
                       CAST(COALESCE(p.orcamento_valor_entrada, 0) AS DECIMAL(10,2)) AS orcamento_valor_entrada,
                       CAST(COALESCE(p.orcamento_valor_restante, 0) AS DECIMAL(10,2)) AS orcamento_valor_restante,
                       p.data_pagamento_1,
                       p.data_pagamento_2
                FROM processos p
                JOIN clientes c ON p.cliente_id = c.id
                WHERE (p.data_pagamento_1 BETWEEN :inicio AND :fim)
                   OR (p.data_pagamento_2 BETWEEN :inicio AND :fim)";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([':inicio' => $inicio, ':fim' => $fim]);

        $registros = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if (!empty($row['data_pagamento_1']) && self::dataNoPeriodo($row['data_pagamento_1'], $inicio, $fim)) {
                $registros[] = [
                    'processo_id' => (int) $row['processo_id'],
                    'cliente_nome' => $row['cliente_nome'],
                    'processo_titulo' => $row['titulo'],
                    'valor' => (float) $row['orcamento_valor_entrada'],
                    'data_pagamento' => $row['data_pagamento_1'],
                    'tipo_parcela' => 'Entrada',
                    'vendedor_id' => $row['vendedor_id'] ?? null,
                    'sdr_id' => $row['sdr_id'] ?? null,
                ];
            }

            if (!empty($row['data_pagamento_2']) && self::dataNoPeriodo($row['data_pagamento_2'], $inicio, $fim)) {
                $registros[] = [
                    'processo_id' => (int) $row['processo_id'],
                    'cliente_nome' => $row['cliente_nome'],
                    'processo_titulo' => $row['titulo'],
                    'valor' => (float) $row['orcamento_valor_restante'],
                    'data_pagamento' => $row['data_pagamento_2'],
                    'tipo_parcela' => 'Restante',
                    'vendedor_id' => $row['vendedor_id'] ?? null,
                    'sdr_id' => $row['sdr_id'] ?? null,
                ];
            }
        }

        return $registros;
    }

    private static function buscarLancamentosPorTipo(PDO $pdo, string $inicio, string $fim, string $tipo): array
    {
        $sql = "SELECT id,
                       descricao,
                       processo_id,
                       cliente_id,
                       CAST(valor AS DECIMAL(10,2)) AS valor,
                       data_lancamento,
                       tipo_lancamento
                FROM lancamentos_financeiros
                WHERE tipo_lancamento = :tipo
                  AND data_lancamento BETWEEN :inicio AND :fim";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':tipo' => $tipo,
            ':inicio' => $inicio,
            ':fim' => $fim,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private static function montarMovimentacoes(array $pagamentos, array $receitas, array $despesas): array
    {
        $movimentacoes = [];

        foreach ($pagamentos as $pagamento) {
            $movimentacoes[] = [
                'tipo' => 'Entrada',
                'descricao' => $pagamento['processo_titulo'] ?? 'Processo #' . ($pagamento['processo_id'] ?? ''),
                'valor' => (float) $pagamento['valor'],
                'data' => $pagamento['data_pagamento'] ?? null,
                'cliente' => $pagamento['cliente_nome'] ?? null,
                'origem' => 'Processo',
            ];
        }

        foreach ($receitas as $receita) {
            $movimentacoes[] = [
                'tipo' => 'Entrada',
                'descricao' => $receita['descricao'] ?? 'Receita avulsa',
                'valor' => (float) $receita['valor'],
                'data' => $receita['data_lancamento'] ?? null,
                'cliente' => $receita['cliente_id'] ? ('Cliente #' . (int) $receita['cliente_id']) : null,
                'origem' => 'Lançamento manual',
            ];
        }

        foreach ($despesas as $despesa) {
            $movimentacoes[] = [
                'tipo' => 'Saída',
                'descricao' => $despesa['descricao'] ?? 'Despesa',
                'valor' => (float) $despesa['valor'],
                'data' => $despesa['data_lancamento'] ?? null,
                'cliente' => $despesa['cliente_id'] ? ('Cliente #' . (int) $despesa['cliente_id']) : null,
                'origem' => 'Lançamento manual',
            ];
        }

        usort($movimentacoes, static function ($a, $b) {
            return strcmp($b['data'] ?? '', $a['data'] ?? '');
        });

        return $movimentacoes;
    }

    private static function agruparEntradasPorDia(array $pagamentos, array $receitasAvulsas): array
    {
        $dias = [];

        foreach ($pagamentos as $registro) {
            $data = substr((string) $registro['data_pagamento'], 0, 10);
            $dias[$data] = ($dias[$data] ?? 0) + (float) $registro['valor'];
        }

        foreach ($receitasAvulsas as $receita) {
            $data = substr((string) $receita['data_lancamento'], 0, 10);
            $dias[$data] = ($dias[$data] ?? 0) + (float) $receita['valor'];
        }

        ksort($dias);

        return $dias;
    }

    private static function agruparPorChave(array $pagamentos, string $chave): array
    {
        $agrupado = [];

        foreach ($pagamentos as $registro) {
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

    private static function montarTotais(array $pagamentos, array $receitasAvulsas, array $despesas): array
    {
        $totalEntradasProcessos = array_reduce($pagamentos, static function ($carry, $item) {
            return $carry + (float) $item['valor'];
        }, 0.0);

        $totalReceitasAvulsas = array_reduce($receitasAvulsas, static function ($carry, $item) {
            return $carry + (float) $item['valor'];
        }, 0.0);

        $totalSaidas = array_reduce($despesas, static function ($carry, $item) {
            return $carry + (float) $item['valor'];
        }, 0.0);

        $totalEntradas = round($totalEntradasProcessos + $totalReceitasAvulsas, 2);
        $saldo = round($totalEntradas - $totalSaidas, 2);

        return [
            'entradas' => $totalEntradas,
            'saidas' => round($totalSaidas, 2),
            'saldo' => $saldo,
        ];
    }

    private static function buscarPrevisaoPagamentos(PDO $pdo): array
    {
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

        $stmt = $pdo->prepare($sql);
        $stmt->execute([':hoje' => $hoje]);

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

    private static function dataNoPeriodo(string $data, string $inicio, string $fim): bool
    {
        return $data >= $inicio && $data <= $fim;
    }
}
