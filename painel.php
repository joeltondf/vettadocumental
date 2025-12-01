<?php
session_start();

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/app/core/auth_check.php';
require_once __DIR__ . '/app/core/access_control.php';
require_once __DIR__ . '/app/controllers/PainelUnificadoController.php';

require_permission(['admin', 'financeiro', 'gerencia']);

$startDate = filter_input(INPUT_GET, 'start_date', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: date('Y-m-01');
$endDate = filter_input(INPUT_GET, 'end_date', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: date('Y-m-t');
$vendedorId = filter_input(INPUT_GET, 'vendedor_id', FILTER_VALIDATE_INT) ?: null;
$sdrId = filter_input(INPUT_GET, 'sdr_id', FILTER_VALIDATE_INT) ?: null;
$statusFinanceiro = filter_input(INPUT_GET, 'status_financeiro', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: null;

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) {
    $startDate = date('Y-m-01');
}

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) {
    $endDate = date('Y-m-t');
}

$filters = [
    'start_date' => $startDate,
    'end_date' => $endDate,
    'vendedor_id' => $vendedorId,
    'sdr_id' => $sdrId,
    'status_financeiro' => $statusFinanceiro,
];

$controller = new PainelUnificadoController($pdo);
$controller->index($filters);
