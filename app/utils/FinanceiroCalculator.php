<?php

require_once __DIR__ . '/../services/FinanceiroService.php';

use App\Services\FinanceiroService;

class FinanceiroCalculator
{
    public static function calcularRegimeDeCaixa(
        PDO $pdo,
        string $startDate,
        string $endDate,
        ?int $vendedorId = null,
        ?int $sdrId = null,
        ?string $statusFinanceiro = null
    ): array
    {
        $service = new FinanceiroService();

        return $service->calcularRegimeDeCaixa($pdo, $startDate, $endDate, $vendedorId, $sdrId, $statusFinanceiro);
    }

    public static function calcularRegimeDeCompetencia(
        PDO $pdo,
        string $startDate,
        string $endDate,
        ?int $vendedorId = null,
        ?int $sdrId = null,
        ?string $statusFinanceiro = null
    ): array
    {
        $service = new FinanceiroService();

        return $service->calcularRegimeDeCompetencia($pdo, $startDate, $endDate, $vendedorId, $sdrId, $statusFinanceiro);
    }
}
