<?php
session_start();
require_once __DIR__ . '/app/core/auth_check.php';
require_once __DIR__ . '/config.php'; // Garante que $pdo está disponível
require_once __DIR__ . '/app/controllers/FinanceiroController.php';
require_once __DIR__ . '/app/core/access_control.php';
require_permission(['admin', 'financeiro', 'gerencia']);

// Obter a instância do PDO que deve ser definida em config.php
global $pdo;

$controller = new FinanceiroController($pdo); // Passa a instância do PDO para o controlador

// Verifica se é uma requisição AJAX para atualizar um campo
if (isset($_GET['action']) && $_GET['action'] === 'update_field') {
    $controller->updateFinancialField();
} else {
    // Coleta os parâmetros do filtro da URL para a exibição normal
    $start_date = $_GET['start_date'] ?? date('Y-m-01');
    $end_date = $_GET['end_date'] ?? date('Y-m-t');
    // Removendo o filtro 'group_by' da URL para a exibição normal,
    // mas mantendo-o para a chamada do controller para os totais agregados (que precisa de um padrão)
    $group_by_for_totals = $_GET['group_by'] ?? 'month'; // Mantém para a agregação de totais

    // Chama o método index do controlador, passando os parâmetros de filtro
    $controller->index($start_date, $end_date, $group_by_for_totals);
}
?>