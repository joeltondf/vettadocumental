<?php

require_once __DIR__ . '/../utils/FinanceiroCalculator.php';
require_once __DIR__ . '/../services/FinanceiroService.php';
require_once __DIR__ . '/../models/Processo.php';

use App\Services\FinanceiroService;

class RelatoriosBiController
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

    public function index(array $filters): void
    {
        $pageTitle = 'Relatórios & BI';
        $resultado = $this->financeiroService->calcularRegimeDeCaixa($this->pdo, $filters['start_date'], $filters['end_date']);

        $vendedores = $this->processoModel->getAllVendedores();
        $sdrs = $this->processoModel->getAllSdrs();

        require_once __DIR__ . '/../views/layouts/header.php';
        require_once __DIR__ . '/../views/relatorios_bi/painel.php';
        require_once __DIR__ . '/../views/layouts/footer.php';
    }
}
