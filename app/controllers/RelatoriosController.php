<?php
/**
 * Controlador responsável por centralizar indicadores gerenciais em uma única página.
 */

require_once __DIR__ . '/../models/Processo.php';
require_once __DIR__ . '/../models/Prospeccao.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Vendedor.php';

class RelatoriosController
{
    private PDO $pdo;
    private Processo $processoModel;
    private Prospeccao $prospeccaoModel;
    private User $userModel;
    private Vendedor $vendedorModel;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->processoModel = new Processo($pdo);
        $this->prospeccaoModel = new Prospeccao($pdo);
        $this->userModel = new User($pdo);
        $this->vendedorModel = new Vendedor($pdo);
    }

    public function index(): void
    {
        $startDate = $_GET['data_inicio'] ?? date('Y-m-01');
        $endDate = $_GET['data_fim'] ?? date('Y-m-t');
        $userId = $_SESSION['user_id'] ?? null;
        $userProfile = $_SESSION['user_perfil'] ?? 'guest';

        $filters = [
            'start_date' => $startDate,
            'end_date' => $endDate,
        ];

        $financeAllowed = in_array($userProfile, ['admin', 'gerencia', 'financeiro'], true);

        $financeiro = $financeAllowed ? $this->getFinanceiroDados($filters) : null;
        $comercial = $this->getComercialDados($filters, $userProfile, $userId);
        $sdr = $this->getSdrDados($filters);
        $operacional = $this->getOperacionalDados($filters);

        $pageTitle = 'Relatórios Gerenciais';

        require_once __DIR__ . '/../views/layouts/header.php';
        require_once __DIR__ . '/../views/relatorios/painel_unificado.php';
        require_once __DIR__ . '/../views/layouts/footer.php';
    }

    private function getFinanceiroDados(array $filters): array
    {
        $modelFilters = [
            'start_date' => $filters['start_date'] ?? date('Y-m-01'),
            'end_date' => $filters['end_date'] ?? date('Y-m-t'),
        ];

        $rawFilters = [
            'start_date' => $modelFilters['start_date'],
            'end_date' => $modelFilters['end_date'],
            'group_by' => 'day',
        ];

        return [
            'transacoes' => $this->processoModel->getFinancialData($rawFilters),
            'resumo' => $this->processoModel->getOverallFinancialSummary($modelFilters['start_date'], $modelFilters['end_date'], $rawFilters),
            'totais' => $this->processoModel->getAggregatedFinancialTotals($modelFilters['start_date'], $modelFilters['end_date'], $rawFilters, 'day'),
        ];
    }

    private function getComercialDados(array $filters, string $userProfile, ?int $userId): array
    {
        $startDate = $filters['start_date'] ?? null;
        $endDate = $filters['end_date'] ?? null;

        $conditions = ["p.status_processo IN ('Concluído', 'Serviço em andamento', 'Pendente de pagamento')", 'p.valor_total > 0'];
        $params = [];

        if (!empty($startDate)) {
            $conditions[] = 'p.data_criacao >= :startDate';
            $params[':startDate'] = $startDate . ' 00:00:00';
        }

        if (!empty($endDate)) {
            $conditions[] = 'p.data_criacao <= :endDate';
            $params[':endDate'] = $endDate . ' 23:59:59';
        }

        if ($userProfile === 'vendedor' && $userId !== null) {
            $vendedor = $this->vendedorModel->getByUserId($userId);
            $vendedorId = $vendedor['id'] ?? null;
            if ($vendedorId !== null) {
                $conditions[] = 'p.vendedor_id = :vendedorId';
                $params[':vendedorId'] = $vendedorId;
            } else {
                $conditions[] = '1 = 0';
            }
        }

        $whereSql = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

        $sql = "SELECT
                    u.nome_completo AS vendedor,
                    COUNT(p.id) AS qtd_vendas,
                    SUM(p.valor_total) AS total_vendido,
                    AVG(p.valor_total) AS ticket_medio
                FROM processos p
                JOIN vendedores v ON p.vendedor_id = v.id
                JOIN users u ON v.user_id = u.id
                $whereSql
                GROUP BY p.vendedor_id, u.nome_completo
                ORDER BY total_vendido DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $ranking = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $totalVendido = array_sum(array_map(static fn($v) => (float) ($v['total_vendido'] ?? 0), $ranking));
        $ticketMedio = count($ranking) > 0 ? ($totalVendido / array_sum(array_map(static fn($v) => (int) ($v['qtd_vendas'] ?? 0), $ranking)) ?: 0) : 0;
        $topSeller = $ranking[0]['vendedor'] ?? 'N/A';

        return [
            'ranking' => $ranking,
            'total_vendido' => $totalVendido,
            'ticket_medio' => $ticketMedio,
            'melhor_vendedor' => $topSeller,
        ];
    }

    private function getSdrDados(array $filters): array
    {
        $startDate = $filters['start_date'] ?? null;
        $endDate = $filters['end_date'] ?? null;

        $ranking = $this->prospeccaoModel->getSdrPerformanceRanking($startDate, $endDate);

        $labels = array_column($ranking, 'sdr');
        $agendamentos = array_column($ranking, 'total_agendamentos');
        $convertidos = array_column($ranking, 'leads_convertidos');

        return [
            'ranking' => $ranking,
            'labels' => $labels,
            'agendamentos' => $agendamentos,
            'convertidos' => $convertidos,
        ];
    }

    private function getOperacionalDados(array $filters): array
    {
        $startDate = $filters['start_date'] ?? null;
        $endDate = $filters['end_date'] ?? null;

        $conditions = [];
        $params = [];

        if (!empty($startDate)) {
            $conditions[] = 'p.data_criacao >= :startDate';
            $params[':startDate'] = $startDate . ' 00:00:00';
        }

        if (!empty($endDate)) {
            $conditions[] = 'p.data_criacao <= :endDate';
            $params[':endDate'] = $endDate . ' 23:59:59';
        }

        $whereSql = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

        $sql = "SELECT LOWER(p.status_processo) AS status, COUNT(*) AS total
                FROM processos p
                $whereSql
                GROUP BY LOWER(p.status_processo)";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $porStatus = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $alertasSql = "SELECT p.id, p.titulo, p.data_previsao_entrega, p.status_processo
                       FROM processos p
                       $whereSql
                       AND p.data_previsao_entrega IS NOT NULL
                       AND p.data_previsao_entrega < CURDATE()
                       AND LOWER(p.status_processo) NOT IN ('concluído', 'concluido', 'finalizado', 'cancelado', 'recusado')";

        $stmtAlertas = $this->pdo->prepare($alertasSql);
        $stmtAlertas->execute($params);
        $atrasados = $stmtAlertas->fetchAll(PDO::FETCH_ASSOC);

        return [
            'por_status' => $porStatus,
            'atrasados' => $atrasados,
        ];
    }
}
