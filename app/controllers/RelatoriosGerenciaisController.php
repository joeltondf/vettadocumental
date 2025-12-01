<?php
// /app/controllers/RelatoriosGerenciaisController.php

require_once __DIR__ . '/../utils/FinanceiroCalculator.php';

class RelatoriosGerenciaisController
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function index(): void
    {
        $dataInicio = $_GET['data_inicio'] ?? date('Y-m-01');
        $dataFim = $_GET['data_fim'] ?? date('Y-m-t');

        $caixaReal = FinanceiroCalculator::calcularCaixaReal($this->pdo, $dataInicio, $dataFim);
        $porVendedor = FinanceiroCalculator::calcularCaixaPorVendedor($this->pdo, $dataInicio, $dataFim);
        $porSdr = FinanceiroCalculator::calcularCaixaPorSdr($this->pdo, $dataInicio, $dataFim);

        $pageTitle = 'Relatórios Gerenciais';
        require_once __DIR__ . '/../views/layouts/header.php';
        require_once __DIR__ . '/../views/relatorios/gerenciais.php';
        require_once __DIR__ . '/../views/layouts/footer.php';
    }
}
