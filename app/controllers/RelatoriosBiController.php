<?php

require_once __DIR__ . '/../utils/FinanceiroCalculator.php';
require_once __DIR__ . '/../models/Processo.php';

class RelatoriosBiController
{
    private PDO $pdo;
    private Processo $processoModel;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->processoModel = new Processo($pdo);
    }

    public function index(array $filters): void
    {
        $pageTitle = 'Relatórios & BI';
        $resultado = FinanceiroCalculator::calcularRegimeDeCaixa($this->pdo, $filters['start_date'], $filters['end_date']);

        $vendedores = $this->processoModel->getAllVendedores();
        $sdrs = $this->processoModel->getAllSdrs();

        require_once __DIR__ . '/../views/layouts/header.php';
        require_once __DIR__ . '/../views/relatorios_bi/painel.php';
        require_once __DIR__ . '/../views/layouts/footer.php';
    }
}
