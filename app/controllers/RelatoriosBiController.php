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

        $receitasAvulsasTotal = 0.0;
        foreach ($resultado['receitas_avulsas'] as $receitaAvulsa) {
            $receitasAvulsasTotal += (float) $receitaAvulsa['valor'];
        }

        if ($receitasAvulsasTotal > 0) {
            $resultado['por_vendedor'][0] = [
                'vendedor_id' => 0,
                'total' => ($resultado['por_vendedor'][0]['total'] ?? 0) + $receitasAvulsasTotal,
                'quantidade' => ($resultado['por_vendedor'][0]['quantidade'] ?? 0) + count($resultado['receitas_avulsas']),
            ];
        }

        require_once __DIR__ . '/../views/layouts/header.php';
        require_once __DIR__ . '/../views/relatorios_bi/painel.php';
        require_once __DIR__ . '/../views/layouts/footer.php';
    }
}
