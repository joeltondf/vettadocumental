<?php
require_once __DIR__ . '/../models/Vendedor.php';
require_once __DIR__ . '/../models/Processo.php';
require_once __DIR__ . '/../models/Prospeccao.php';
require_once __DIR__ . '/../models/User.php';

class GerenteDashboardController
{
    private $pdo;
    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    public function index()
    {
        $vendedorModel  = new Vendedor($this->pdo);
        $processoModel  = new Processo($this->pdo);
        $prospeccaoModel= new Prospeccao($this->pdo);

        // Filtros de datas (opcionais)
        $dataInicio = $_GET['data_inicio'] ?? null;
        $dataFim    = $_GET['data_fim']    ?? null;

        $startDate = $this->normalizeDate($dataInicio);
        $endDate   = $this->normalizeDate($dataFim);

        // Lista todos os vendedores
        $vendedores = $vendedorModel->getAll();
        $performance = [];

        $vendorPerformanceRanking = $processoModel->getVendorPerformanceRanking($startDate, $endDate);
        $sdrPerformanceRanking = $prospeccaoModel->getSdrPerformanceRanking($startDate, $endDate);

        foreach ($vendedores as $vendedor) {
            $vId = $vendedor['id'];
            $userId = $vendedor['user_id'];

            // Prepara filtros para buscar processos (vendas) desse vendedor
            $filters = ['vendedor_id' => $vId];
            if ($dataInicio) {
                $filters['data_inicio'] = $dataInicio;
            }
            if ($dataFim) {
                $filters['data_fim'] = $dataFim;
            }

            // Recupera todos os processos do vendedor com o filtro de datas
            $processos = $processoModel->getFilteredProcesses($filters, 9999, 0);

            // Soma o valor total das vendas
            $totalVendas = 0;
            foreach ($processos as $p) {
                $totalVendas += (float) $p['valor_total'];
            }

            // Estatísticas de prospecção (novos leads, reuniões, taxa de conversão)
            $prospecStats = $prospeccaoModel->getStatsByResponsavel($userId);

            $performance[] = [
                'id'              => $vId,
                'nome'            => $vendedor['nome_vendedor'],
                'total_vendas'    => $totalVendas,
                'novos_leads_mes' => $prospecStats['novos_leads_mes'],
                'taxa_conversao'  => $prospecStats['taxa_conversao'],
            ];
        }
            $stmt = $this->pdo->prepare("
                SELECT v.id,
                    u.nome_completo AS nome_vendedor,
                    COUNT(p.id) AS total_leads
                FROM vendedores v
                JOIN users u ON v.user_id = u.id
                LEFT JOIN prospeccoes p ON u.id = p.responsavel_id
                GROUP BY v.id, u.nome_completo
            ");
            $stmt->execute();
            $dadosLeads = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $labelsLeads  = array_column($dadosLeads, 'nome_vendedor');
            $valoresLeads = array_column($dadosLeads, 'total_leads');

            // Total de leads (prospecções) por vendedor
            $stmt = $this->pdo->prepare("
                SELECT v.id,
                    u.nome_completo AS nome_vendedor,
                    COUNT(p.id) AS total_aprovados
                FROM vendedores v
                JOIN users u ON v.user_id = u.id
                LEFT JOIN processos p
                    ON v.id = p.vendedor_id
                    AND p.status_processo = 'Serviço Pendente'
                GROUP BY v.id, u.nome_completo
            ");
            $stmt->execute();
            $dadosAprovados = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $labelsAprovados  = array_column($dadosAprovados, 'nome_vendedor');
            $valoresAprovados = array_column($dadosAprovados, 'total_aprovados');

            // Vendas concluídas por vendedor
            $stmt = $this->pdo->prepare("
                SELECT v.id,
                    u.nome_completo AS nome_vendedor,
                    COUNT(p.id) AS total_finalizados
                FROM vendedores v
                JOIN users u ON v.user_id = u.id
                LEFT JOIN processos p
                    ON v.id = p.vendedor_id
                    AND p.status_processo IN ('Concluído', 'Finalizado')
                GROUP BY v.id, u.nome_completo
            ");
            $stmt->execute();
            $dadosFinalizados = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $labelsFinalizados  = array_column($dadosFinalizados, 'nome_vendedor');
            $valoresFinalizados = array_column($dadosFinalizados, 'total_finalizados');

            $stmt = $this->pdo->prepare("
                SELECT v.id,
                    u.nome_completo AS nome_vendedor,
                    COALESCE(SUM(p.valor_total), 0) AS valor_previsto
                FROM vendedores v
                JOIN users u ON v.user_id = u.id
                LEFT JOIN processos p
                    ON v.id = p.vendedor_id
                    AND p.status_processo IN ('Serviço Pendente', 'Serviço pendente', 'Serviço em Andamento', 'Serviço em andamento', 'Pendente de pagamento', 'Pendente de documentos')
                GROUP BY v.id, u.nome_completo
            ");
            $stmt->execute();
            $dadosPrevistos = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $labelsPrevisto  = array_column($dadosPrevistos, 'nome_vendedor');
            $valoresPrevisto = array_column($dadosPrevistos, 'valor_previsto');


        $sdrSummary = $prospeccaoModel->getSdrWorkSummary($startDate, $endDate);
        $sdrVendorSummary = $prospeccaoModel->getSdrVendorConversionSummary($startDate, $endDate);
        $vendorCommissionReport = $processoModel->getVendorCommissionSummary($startDate, $endDate);
        $budgetOverview = $processoModel->getBudgetPipelineSummary($startDate, $endDate);
        $sdrLeadStatusSummary = $prospeccaoModel->getSdrLeadStatusSummary($startDate, $endDate);
        $vendorLeadTreatmentSummary = $prospeccaoModel->getVendorLeadTreatmentSummary($startDate, $endDate);
        $servicesSummary = $processoModel->getServicesSummary($startDate, $endDate);

        // Nome da página para a view
        $pageTitle = 'Painel de Gestão';

        // Carrega as views padrão (header/footer) e a nova view de conteúdo
        require_once __DIR__ . '/../views/layouts/header.php';
        require_once __DIR__ . '/../views/gerente_dashboard/main.php';
        require_once __DIR__ . '/../views/layouts/footer.php';
    }

    private function normalizeDate(?string $date): ?string
    {
        if (empty($date)) {
            return null;
        }

        $parsed = \DateTime::createFromFormat('Y-m-d', $date);

        return $parsed instanceof \DateTime ? $parsed->format('Y-m-d') : null;
    }

    public function listarComissoes(): void
    {
        $processoModel = new Processo($this->pdo);
        $vendedorModel = new Vendedor($this->pdo);
        $userModel = new User($this->pdo);

        $filters = [
            'data_inicio' => $this->normalizeDate($_GET['data_inicio'] ?? null),
            'data_fim' => $this->normalizeDate($_GET['data_fim'] ?? null),
            'vendedor_id' => !empty($_GET['vendedor_id']) ? (int) $_GET['vendedor_id'] : null,
            'sdr_id' => !empty($_GET['sdr_id']) ? (int) $_GET['sdr_id'] : null,
            'status' => isset($_GET['status']) ? trim((string) $_GET['status']) : null,
        ];

        $filters = array_filter($filters, static fn($value) => $value !== null && $value !== '');

        $commissionReport = $processoModel->getCommissionsByFilter($filters);
        $processos = $commissionReport['processos'] ?? [];
        $totais = $commissionReport['totais'] ?? [
            'valor_total' => 0,
            'comissao_vendedor' => 0,
            'comissao_sdr' => 0,
        ];

        $totalProcessos = count($processos);
        $ticketMedio = $totalProcessos > 0 ? ($totais['valor_total'] / $totalProcessos) : 0.0;

        $vendedores = $vendedorModel->getAll();
        $sdrs = $userModel->getActiveSdrs();
        $statusOptions = [
            'Serviço Pendente',
            'Serviço pendente',
            'Serviço em Andamento',
            'Serviço em andamento',
            'Pendente de pagamento',
            'Pendente de documentos',
            'Concluído',
            'Finalizado',
        ];

        $pageTitle = 'Relatório de Comissões';

        require_once __DIR__ . '/../views/layouts/header.php';
        require_once __DIR__ . '/../views/gerente_dashboard/lista_comissoes.php';
        require_once __DIR__ . '/../views/layouts/footer.php';
    }

    public function leads(): void
    {
        $prospeccaoModel = new Prospeccao($this->pdo);
        $userModel = new User($this->pdo);
        $vendedorModel = new Vendedor($this->pdo);

        $filters = [
            'sdr_id' => $_REQUEST['sdr_id'] ?? null,
            'vendor_id' => $_REQUEST['vendor_id'] ?? null,
            'status' => $_REQUEST['status'] ?? null,
            'data_inicio' => $this->normalizeDate($_REQUEST['data_inicio'] ?? null),
            'data_fim' => $this->normalizeDate($_REQUEST['data_fim'] ?? null),
        ];

        $leads = $prospeccaoModel->getManagerLeadList($filters);
        $sdrs = $userModel->getActiveSdrs();
        $vendors = $vendedorModel->getAll();

        $statusOptions = array_values(array_unique(array_filter(array_map(static function ($lead) {
            return $lead['status'] ?? null;
        }, $leads))));

        $pageTitle = 'Leads em tratamento';

        require_once __DIR__ . '/../views/layouts/header.php';
        require_once __DIR__ . '/../views/gerente_dashboard/lista_leads.php';
        require_once __DIR__ . '/../views/layouts/footer.php';
    }
}
