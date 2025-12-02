<?php

require_once __DIR__ . '/BaseReportController.php';
require_once __DIR__ . '/../services/FinanceiroService.php';
require_once __DIR__ . '/../models/Processo.php';

use App\Services\FinanceiroService;

class PainelUnificadoController extends BaseReportController
{
    private Processo $processoModel;
    private FinanceiroService $financeiroService;

    public function __construct(PDO $pdo)
    {
        parent::__construct($pdo);
        $this->processoModel = new Processo($pdo);
        $this->financeiroService = new FinanceiroService();
    }

    public function index(array $filters): void
    {
        $this->startSessionAndAuth(['gestao', 'admin']);

        $pageTitle = 'Painel Unificado';
        $dateFilters = $this->sanitizeDateFilters();
        $startDate = $dateFilters['start_date'];
        $endDate = $dateFilters['end_date'];

        $dadosFluxo = $this->financeiroService->calcularRegimeDeCaixa(
            $this->pdo,
            $startDate,
            $endDate,
            $filters['vendedor_id'] ?? null,
            $filters['sdr_id'] ?? null,
            $filters['status_financeiro'] ?? null
        );

        $modelFilters = [
            'data_inicio' => $startDate . ' 00:00:00',
            'data_fim' => $endDate . ' 23:59:59',
            'vendedor_id' => $filters['vendedor_id'] ?? null,
            'cliente_id' => $filters['cliente_id'] ?? null,
            'sdr_id' => $filters['sdr_id'] ?? null,
        ];

        $processos = $this->processoModel->getFinancialData($modelFilters);
        if (!empty($filters['status_financeiro'])) {
            $statusFiltro = mb_strtolower((string) $filters['status_financeiro']);
            $processos = array_values(array_filter($processos, static function ($processo) use ($statusFiltro) {
                $status = mb_strtolower((string) ($processo['status_financeiro'] ?? $processo['status_processo'] ?? ''));
                return $status === $statusFiltro;
            }));
        }
        $vendedores = $this->processoModel->getAllVendedores();
        $sdrs = $this->processoModel->getAllSdrs();
        $statusFinanceiroOptions = $this->processoModel->getStatusFinanceiroOptions();

        require_once __DIR__ . '/../views/layouts/header.php';
        require_once __DIR__ . '/../views/painel_unificado/index.php';
        require_once __DIR__ . '/../views/layouts/footer.php';
    }
}
