<?php
// /app/utils/FinanceiroCalculator.php

class FinanceiroCalculator
{
    public static function calcularCaixaReal(PDO $pdo, string $dataInicio, string $dataFim): array
    {
        $inicio = $dataInicio !== '' ? $dataInicio : date('Y-m-01');
        $fim = $dataFim !== '' ? $dataFim : date('Y-m-t');

        $entradaParcela1 = self::sumQuery(
            $pdo,
            "SELECT SUM(COALESCE(orcamento_valor_entrada, 0)) FROM processos WHERE data_pagamento_1 BETWEEN :inicio AND :fim",
            $inicio,
            $fim
        );

        $entradaParcela2 = self::sumQuery(
            $pdo,
            "SELECT SUM(COALESCE(orcamento_valor_restante, 0)) FROM processos WHERE data_pagamento_2 BETWEEN :inicio AND :fim",
            $inicio,
            $fim
        );

        $receitasAvulsas = self::sumQuery(
            $pdo,
            "SELECT SUM(COALESCE(valor, 0)) FROM lancamentos_financeiros WHERE tipo_lancamento = 'RECEITA' AND data_lancamento BETWEEN :inicio AND :fim",
            $inicio,
            $fim
        );

        $despesas = self::sumQuery(
            $pdo,
            "SELECT SUM(COALESCE(valor, 0)) FROM lancamentos_financeiros WHERE tipo_lancamento = 'DESPESA' AND data_lancamento BETWEEN :inicio AND :fim",
            $inicio,
            $fim
        );

        $entradas = ($entradaParcela1 + $entradaParcela2 + $receitasAvulsas);

        return [
            'entradas' => (float) $entradas,
            'despesas' => (float) $despesas,
            'saldo' => (float) ($entradas - $despesas),
        ];
    }

    public static function calcularCaixaPorVendedor(PDO $pdo, string $dataInicio, string $dataFim): array
    {
        $inicio = $dataInicio !== '' ? $dataInicio : date('Y-m-01');
        $fim = $dataFim !== '' ? $dataFim : date('Y-m-t');

        $sql = "SELECT v.id AS vendedor_id, COALESCE(u.nome_completo, v.nome) AS nome_vendedor,
                       SUM(COALESCE(p.valor_total, 0)) AS total_vendido
                FROM processos p
                LEFT JOIN vendedores v ON p.vendedor_id = v.id
                LEFT JOIN users u ON v.user_id = u.id
                WHERE p.data_conversao BETWEEN :inicio AND :fim
                GROUP BY v.id, nome_vendedor";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([':inicio' => $inicio, ':fim' => $fim]);
        $vendedores = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($vendedores as &$vendedor) {
            $vendedor['total_recebido'] = self::sumQuery(
                $pdo,
                "SELECT 
                        SUM(CASE WHEN p.data_pagamento_1 BETWEEN :inicio AND :fim THEN COALESCE(p.orcamento_valor_entrada, 0) ELSE 0 END) +
                        SUM(CASE WHEN p.data_pagamento_2 BETWEEN :inicio AND :fim THEN COALESCE(p.orcamento_valor_restante, 0) ELSE 0 END)
                 FROM processos p
                 WHERE p.vendedor_id = :vendedor_id",
                $inicio,
                $fim,
                [':vendedor_id' => $vendedor['vendedor_id']]
            );
        }

        return $vendedores;
    }

    public static function calcularCaixaPorSdr(PDO $pdo, string $dataInicio, string $dataFim): array
    {
        $inicio = $dataInicio !== '' ? $dataInicio : date('Y-m-01');
        $fim = $dataFim !== '' ? $dataFim : date('Y-m-t');

        $sql = "SELECT COALESCE(p.sdr_id, p.colaborador_id) AS sdr_id,
                       COALESCE(u.nome_completo, 'SDR não definido') AS nome_sdr,
                       COUNT(p.id) AS qtd_vendas
                FROM processos p
                LEFT JOIN users u ON u.id = COALESCE(p.sdr_id, p.colaborador_id)
                WHERE p.data_conversao BETWEEN :inicio AND :fim
                GROUP BY sdr_id, nome_sdr";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([':inicio' => $inicio, ':fim' => $fim]);
        $sdres = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($sdres as &$sdr) {
            $sdr['total_recebido'] = self::sumQuery(
                $pdo,
                "SELECT 
                        SUM(CASE WHEN p.data_pagamento_1 BETWEEN :inicio AND :fim THEN COALESCE(p.orcamento_valor_entrada, 0) ELSE 0 END) +
                        SUM(CASE WHEN p.data_pagamento_2 BETWEEN :inicio AND :fim THEN COALESCE(p.orcamento_valor_restante, 0) ELSE 0 END)
                 FROM processos p
                 WHERE COALESCE(p.sdr_id, p.colaborador_id) = :sdr_id",
                $inicio,
                $fim,
                [':sdr_id' => $sdr['sdr_id']]
            );
        }

        return $sdres;
    }

    private static function sumQuery(PDO $pdo, string $sql, string $inicio, string $fim, array $extraParams = []): float
    {
        $stmt = $pdo->prepare($sql);
        $params = array_merge([
            ':inicio' => $inicio,
            ':fim' => $fim,
        ], $extraParams);
        $stmt->execute($params);

        return (float) $stmt->fetchColumn();
    }
}
