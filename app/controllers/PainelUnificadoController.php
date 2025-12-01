<?php

require_once __DIR__ . '/../services/FinanceiroService.php';
require_once __DIR__ . '/../models/Processo.php';

use App\Services\FinanceiroService;

class PainelUnificadoController
{
    private PDO $pdo;
    private Processo $processoModel;
    private FinanceiroService $financeiroService;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->processoModel = new Processo($pdo);
        $this->financeiroService = new FinanceiroService();
    }

    public function index(): void
    {
        $filters = [
            'start_date' => $_GET['start_date'] ?? date('Y-m-01'),
            'end_date' => $_GET['end_date'] ?? date('Y-m-t'),
            'vendedor' => $_GET['vendedor'] ?? null,
            'sdr' => $_GET['sdr'] ?? null,
            'status' => $_GET['status'] ?? null,
        ];

        $fluxoCaixa = $this->financeiroService->calcularRegimeDeCaixa($this->pdo, $filters['start_date'], $filters['end_date']);
        $processos = $this->processoModel->buscarPorFiltros($filters);

        $pageTitle = 'Painel Unificado';
        $vendedores = $this->processoModel->getAllVendedores();
        $sdrs = $this->processoModel->getAllSdrs();

        require_once __DIR__ . '/../views/layouts/header.php';
        require_once __DIR__ . '/../views/painel_unificado/index.php';
        require_once __DIR__ . '/../views/layouts/footer.php';
    }
}
