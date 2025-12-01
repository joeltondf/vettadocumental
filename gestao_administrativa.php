<?php
session_start();

require_once __DIR__ . '/app/core/auth_check.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/app/controllers/FinanceiroController.php';
require_once __DIR__ . '/app/core/access_control.php';

require_permission(['admin', 'financeiro', 'gerencia', 'vendedor']);

global $pdo;

$controller = new FinanceiroController($pdo);
$userPerfil = $_SESSION['user_perfil'] ?? '';
$forcedVendedorId = null;
if ($userPerfil === 'vendedor' && isset($_SESSION['user_id'])) {
    $forcedVendedorId = $controller->getVendedorIdFromUser((int) $_SESSION['user_id']);
}

$action = filter_input(INPUT_GET, 'action', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: '';

if ($action === 'update_field') {
    $controller->updateFinancialField();
    return;
}

$startDate = filter_input(INPUT_GET, 'start_date', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: date('Y-m-01');
$endDate = filter_input(INPUT_GET, 'end_date', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: date('Y-m-t');
$groupBy = filter_input(INPUT_GET, 'group_by', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: 'month';
$vendedorId = $forcedVendedorId ?? (filter_input(INPUT_GET, 'vendedor_id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: null);
$clienteId = filter_input(INPUT_GET, 'cliente_id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: null;
$sdrId = filter_input(INPUT_GET, 'sdr_id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: null;

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) {
    $startDate = date('Y-m-01');
}

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) {
    $endDate = date('Y-m-t');
}

$allowedGroupings = ['day', 'month', 'year'];
if (!in_array($groupBy, $allowedGroupings, true)) {
    $groupBy = 'month';
}


$filters = [
    'start_date' => $startDate,
    'end_date' => $endDate,
    'group_by' => $groupBy,
    'vendedor_id' => $vendedorId,
    'cliente_id' => $clienteId,
    'sdr_id' => $sdrId,
];

$controller->index($filters);
