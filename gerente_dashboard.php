<?php
session_start();

require_once 'config.php';
require_once 'app/core/auth_check.php';
require_once 'app/core/access_control.php';

// Permite apenas perfis admin, gerencia ou supervisor acessar o painel gerencial
require_permission(['admin', 'gerencia', 'supervisor']);

// Inclui o novo controlador do painel gerencial
require_once 'app/controllers/GerenteDashboardController.php';

// Instancia o controlador e renderiza a página
$controller = new GerenteDashboardController($pdo);
$action = $_GET['action'] ?? 'index';

switch ($action) {
    case 'listar_comissoes':
        $controller->listarComissoes();
        break;
    case 'leads':
        $controller->leads();
        break;
    default:
        $controller->index();
}
