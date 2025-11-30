<?php
// app/controllers/RelatoriosController.php

class RelatoriosController
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    private function authCheck(): void
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: ' . APP_URL . '/login.php');
            exit();
        }
    }

    public function index(): void
    {
        $this->authCheck();

        $filters = [
            'data_inicio' => $this->sanitizeDate($_GET['data_pagamento_1'] ?? ''),
            'data_fim' => $this->sanitizeDate($_GET['data_pagamento_2'] ?? ''),
        ];

        $pageTitle = 'Relatórios & BI';

        $visaoGeral = $this->getDashboardKpis();
        $financeiro = $this->getFinanceiroDados($filters);
        $rankingVendas = $this->getRankingVendedores();
        $preVendas = $this->getPreVendasMetrics();
        $operacional = $this->getOperacionalPendencias();

        require_once __DIR__ . '/../views/layouts/header.php';
        require_once __DIR__ . '/../views/relatorios/index.php';
        require_once __DIR__ . '/../views/layouts/footer.php';
    }

    private function sanitizeDate(?string $value): string
    {
        $trimmed = trim((string) $value);

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $trimmed) ? $trimmed : '';
    }

    private function getDashboardKpis(): array
    {
        try {
            $sql = "SELECT
                        COUNT(*) AS total_processos,
                        SUM(CASE WHEN status_processo IN ('Concluído','Finalizado') THEN 1 ELSE 0 END) AS total_finalizados,
                        SUM(COALESCE(valor_total, 0)) AS receita_prevista
                    FROM processos";
            $stmt = $this->pdo->query($sql);
            $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

            return [
                'total_processos' => (int) ($row['total_processos'] ?? 0),
                'total_finalizados' => (int) ($row['total_finalizados'] ?? 0),
                'receita_prevista' => (float) ($row['receita_prevista'] ?? 0),
            ];
        } catch (Throwable $exception) {
            error_log('Falha ao carregar KPIs gerais: ' . $exception->getMessage());
            return [
                'total_processos' => 0,
                'total_finalizados' => 0,
                'receita_prevista' => 0.0,
            ];
        }
    }

    private function getFinanceiroDados(array $filters): array
    {
        $inicio = $filters['data_inicio'] !== '' ? $filters['data_inicio'] : date('Y-m-01');
        $fim = $filters['data_fim'] !== '' ? $filters['data_fim'] : date('Y-m-t');

        try {
            $sql = "SELECT
                        p.id,
                        p.data_pagamento_1 AS data_movimento,
                        'Entrada/Parcela 1' AS tipo_lancamento,
                        c.nome_cliente AS nome_cliente,
                        p.titulo AS processo,
                        COALESCE(p.orcamento_valor_entrada, 0) AS valor,
                        p.servico_tipo,
                        'Confirmado' AS status
                    FROM processos p
                    JOIN clientes c ON p.cliente_id = c.id
                    WHERE p.data_pagamento_1 BETWEEN :data_inicio AND :data_fim

                    UNION ALL

                    SELECT
                        p.id,
                        p.data_pagamento_2 AS data_movimento,
                        'Parcela 2/Final' AS tipo_lancamento,
                        c.nome_cliente AS nome_cliente,
                        p.titulo AS processo,
                        COALESCE(p.orcamento_valor_restante, 0) AS valor,
                        p.servico_tipo,
                        'Confirmado' AS status
                    FROM processos p
                    JOIN clientes c ON p.cliente_id = c.id
                    WHERE p.data_pagamento_2 BETWEEN :data_inicio AND :data_fim

                    ORDER BY data_movimento DESC";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':data_inicio' => $inicio,
                ':data_fim' => $fim,
            ]);

            $entradas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $exception) {
            error_log('Falha ao carregar fluxo de caixa real: ' . $exception->getMessage());
            $entradas = [];
        }

        $total = array_reduce($entradas, static function ($carry, $item) {
            return $carry + (float) ($item['valor'] ?? 0);
        }, 0.0);

        $today = date('Y-m-d');
        $nextWeek = date('Y-m-d', strtotime('+7 days'));
        $evolucaoDiaria = array_fill(1, 31, 0);
        $receitaPorCategoria = [];

        $panorama = [
            'entradas_hoje' => 0.0,
            'entradas_mes' => 0.0,
            'a_receber_7' => 0.0,
            'inadimplencia' => 0.0,
        ];

        foreach ($entradas as $entrada) {
            $valor = (float) ($entrada['valor'] ?? 0);
            $data = $entrada['data_movimento'] ?? '';
            $servicoTipo = $entrada['servico_tipo'] ?? 'Não informado';

            if ($data === '') {
                continue;
            }

            $dia = (int) date('j', strtotime($data));
            if ($dia >= 1 && $dia <= 31 && date('Y-m', strtotime($data)) === date('Y-m', strtotime($inicio))) {
                $evolucaoDiaria[$dia] += $valor;
            }

            $receitaPorCategoria[$servicoTipo] = ($receitaPorCategoria[$servicoTipo] ?? 0) + $valor;

            if ($data === $today) {
                $panorama['entradas_hoje'] += $valor;
            }

            if (date('Y-m', strtotime($data)) === date('Y-m')) {
                $panorama['entradas_mes'] += $valor;
            }

            if ($data > $today && $data <= $nextWeek) {
                $panorama['a_receber_7'] += $valor;
            }

            if ($data < $today) {
                $panorama['inadimplencia'] += $valor;
            }
        }

        return [
            'entradas' => $entradas,
            'total' => $total,
            'inicio' => $inicio,
            'fim' => $fim,
            'panorama' => $panorama,
            'evolucao_diaria' => $evolucaoDiaria,
            'receita_por_categoria' => $receitaPorCategoria,
        ];
    }

    private function getRankingVendedores(): array
    {
        try {
            $sql = "SELECT
                        u.nome_completo AS vendedor,
                        COUNT(pr.id) AS total_vendas,
                        COALESCE(SUM(pr.valor_total), 0) AS valor_total,
                        CASE WHEN COUNT(pr.id) > 0 THEN COALESCE(SUM(pr.valor_total), 0) / COUNT(pr.id) ELSE 0 END AS ticket_medio
                    FROM vendedores v
                    JOIN users u ON v.user_id = u.id
                    LEFT JOIN processos pr ON pr.vendedor_id = v.id
                    GROUP BY u.nome_completo
                    ORDER BY valor_total DESC";
            $stmt = $this->pdo->query($sql);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $exception) {
            error_log('Falha ao carregar ranking de vendedores: ' . $exception->getMessage());
            return [];
        }
    }

    private function getPreVendasMetrics(): array
    {
        try {
            $sqlAgendamentos = "SELECT COUNT(*) FROM prospeccoes WHERE status LIKE 'Agend%'";
            $totalAgendamentos = (int) $this->pdo->query($sqlAgendamentos)->fetchColumn();

            $sqlConvertidos = "SELECT COUNT(*)
                               FROM prospeccoes p
                               WHERE p.status IN ('Convertido','Cliente Ativo')";
            $totalConvertidos = (int) $this->pdo->query($sqlConvertidos)->fetchColumn();

            $taxaConversao = $totalAgendamentos > 0 ? round(($totalConvertidos / $totalAgendamentos) * 100, 2) : 0;

            return [
                'total_agendamentos' => $totalAgendamentos,
                'total_convertidos' => $totalConvertidos,
                'taxa_conversao' => $taxaConversao,
            ];
        } catch (Throwable $exception) {
            error_log('Falha ao carregar métricas de pré-vendas: ' . $exception->getMessage());
            return [
                'total_agendamentos' => 0,
                'total_convertidos' => 0,
                'taxa_conversao' => 0,
            ];
        }
    }

    private function getOperacionalPendencias(): array
    {
        try {
            $sql = "SELECT
                        p.id,
                        p.titulo,
                        p.status_processo,
                        p.data_atualizacao,
                        c.nome_cliente
                    FROM processos p
                    JOIN clientes c ON c.id = p.cliente_id
                    WHERE p.status_processo IN ('Serviço Pendente', 'Em andamento')
                       OR (p.data_atualizacao IS NOT NULL AND p.data_atualizacao < DATE_SUB(NOW(), INTERVAL 5 DAY))
                    ORDER BY p.data_atualizacao ASC";
            $stmt = $this->pdo->query($sql);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $exception) {
            error_log('Falha ao carregar pendências operacionais: ' . $exception->getMessage());
            return [];
        }
    }
}
