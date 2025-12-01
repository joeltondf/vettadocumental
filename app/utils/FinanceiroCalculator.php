<?php

use App\Services\FinanceiroService;

require_once __DIR__ . '/../services/FinanceiroService.php';

class FinanceiroCalculator
{
    /**
     * Mantém assinatura original e delega para o novo serviço centralizado.
     */
    public static function calcularRegimeDeCaixa(PDO $pdo, string $startDate, string $endDate): array
    {
        $service = new FinanceiroService();
        return $service->calcularRegimeDeCaixa($pdo, $startDate, $endDate);
    }

    /**
     * Ponte para o cálculo de competência preservando compatibilidade.
     */
    public static function calcularRegimeDeCompetencia(PDO $pdo, string $startDate, string $endDate): array
    {
        $service = new FinanceiroService();
        return $service->calcularRegimeDeCompetencia($pdo, $startDate, $endDate);
    }
}
