<?php


class FinanceiroCalculator
{
    public static function calcularRegimeDeCaixa(PDO $pdo, string $startDate, string $endDate): array
    {
        $inicio = $startDate . ' 00:00:00';
        $fim = $endDate . ' 23:59:59';

        $pagamentos = self::buscarPagamentosDeProcessos($pdo, $inicio, $fim);
        $receitasAvulsas = self::buscarLancamentosAvulsos($pdo, $inicio, $fim, 'RECEITA');
        $despesas = self::buscarLancamentosAvulsos($pdo, $inicio, $fim, 'DESPESA');

        $entradasPorDia = self::agruparEntradasPorDia($pagamentos, $receitasAvulsas);
        $totais = self::montarTotais($pagamentos, $receitasAvulsas, $despesas);

        return [
            'pagamentos' => $pagamentos,
            'receitas_avulsas' => $receitasAvulsas,
            'despesas' => $despesas,
            'entradas_por_dia' => $entradasPorDia,
            'por_vendedor' => self::agruparPorVendedor($pagamentos),
            'por_sdr' => self::agruparPorSdr($pagamentos),
            'totais' => $totais,
            'kpis' => self::montarKpis($pagamentos, $receitasAvulsas, $despesas, $fim),
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

    private static function buscarLancamentosAvulsos(PDO $pdo, string $inicio, string $fim, string $tipo): array
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

    private static function agruparEntradasPorDia(array $pagamentos, array $receitasAvulsas): array
    {
        $dias = [];

        foreach ($pagamentos as $registro) {
            $data = substr($registro['data_pagamento'], 0, 10);
            $dias[$data] = ($dias[$data] ?? 0) + (float) $registro['valor'];
        }

        foreach ($receitasAvulsas as $receita) {
            $data = substr($receita['data_lancamento'], 0, 10);
            $dias[$data] = ($dias[$data] ?? 0) + (float) $receita['valor'];
        }

        ksort($dias);

        return $dias;
    }

    private static function agruparPorVendedor(array $pagamentos): array
    {
        $agrupado = [];

        foreach ($pagamentos as $registro) {
            $vendedorId = $registro['vendedor_id'] ?? null;
            if ($vendedorId === null) {
                continue;
            }

            if (!isset($agrupado[$vendedorId])) {
                $agrupado[$vendedorId] = [
                    'vendedor_id' => (int) $vendedorId,
                    'total' => 0.0,
                    'quantidade' => 0,
                ];
            }

            $agrupado[$vendedorId]['total'] += (float) $registro['valor'];
            $agrupado[$vendedorId]['quantidade']++;
        }

        return $agrupado;
    }

    private static function agruparPorSdr(array $pagamentos): array
    {
        $agrupado = [];

        foreach ($pagamentos as $registro) {
            $sdrId = $registro['sdr_id'] ?? null;
            if ($sdrId === null) {
                continue;
            }

            if (!isset($agrupado[$sdrId])) {
                $agrupado[$sdrId] = [
                    'sdr_id' => (int) $sdrId,
                    'total' => 0.0,
                    'quantidade' => 0,
                ];
            }

            $agrupado[$sdrId]['total'] += (float) $registro['valor'];
            $agrupado[$sdrId]['quantidade']++;
        }

        return $agrupado;
    }

    private static function montarTotais(array $pagamentos, array $receitasAvulsas, array $despesas): array
    {
        $totalRecebido = 0.0;
        foreach ($pagamentos as $registro) {
            $totalRecebido += (float) $registro['valor'];
        }

        $totalReceitasAvulsas = 0.0;
        foreach ($receitasAvulsas as $receita) {
            $totalReceitasAvulsas += (float) $receita['valor'];
        }

        $totalDespesas = 0.0;
        foreach ($despesas as $despesa) {
            $totalDespesas += (float) $despesa['valor'];
        }

        $totalReceitas = round($totalRecebido + $totalReceitasAvulsas, 2);
        $saldo = round($totalReceitas - $totalDespesas, 2);

        return [
            'receitas' => $totalReceitas,
            'despesas' => round($totalDespesas, 2),
            'saldo' => $saldo,
        ];
    }

    private static function montarKpis(array $pagamentos, array $receitasAvulsas, array $despesas, string $fim): array
    {
        $inadimplencia = self::calcularInadimplencia($despesas, $fim);
        $totais = self::montarTotais($pagamentos, $receitasAvulsas, $despesas);

        $melhorVendedor = self::obterMelhor($pagamentos, 'vendedor_id');
        $melhorSdr = self::obterMelhor($pagamentos, 'sdr_id');

        return [
            'faturamento_total' => $totais['receitas'],
            'inadimplencia' => $inadimplencia,
            'melhor_vendedor' => $melhorVendedor,
            'melhor_sdr' => $melhorSdr,
        ];
    }

    private static function calcularInadimplencia(array $despesas, string $fim): float
    {
        $limite = new DateTime($fim);
        $limite->modify('-30 days');
        $total = 0.0;

        foreach ($despesas as $despesa) {
            $dataLancamento = new DateTime($despesa['data_lancamento']);
            if ($dataLancamento <= $limite) {
                $total += (float) $despesa['valor'];
            }
        }

        return round($total, 2);
    }

    private static function obterMelhor(array $pagamentos, string $chave): array
    {
        $agrupado = [];

        foreach ($pagamentos as $registro) {
            if (!isset($registro[$chave]) || $registro[$chave] === null) {
                continue;
            }

            $id = (int) $registro[$chave];
            if (!isset($agrupado[$id])) {
                $agrupado[$id] = 0.0;
            }

            $agrupado[$id] += (float) $registro['valor'];
        }

        if (empty($agrupado)) {
            return ['id' => null, 'total' => 0.0];
        }

        arsort($agrupado);
        $id = array_key_first($agrupado);

        return ['id' => $id, 'total' => round($agrupado[$id], 2)];
    }

    private static function dataNoPeriodo(string $data, string $inicio, string $fim): bool
    {
        return $data >= $inicio && $data <= $fim;
    }
}
