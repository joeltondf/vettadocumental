<?php
// /clientes.php (CORRIGIDO)

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/app/core/auth_check.php';
require_once __DIR__ . '/app/controllers/ClientesController.php';
require_once __DIR__ . '/app/core/access_control.php';

$controller = new ClientesController($pdo);

$action = $_POST['action'] ?? $_GET['action'] ?? 'index';
$id = $_GET['id'] ?? null;

// ===================================================================
// LÓGICA DE PERMISSÃO CORRIGIDA
// ===================================================================
// Verifica a permissão baseado na AÇÃO específica, em vez de bloquear o arquivo inteiro.
switch ($action) {
    case 'create':
    case 'store':
    case 'index':
        // Permite que vendedores acessem a listagem e o formulário de criação de clientes.
        require_permission(['admin', 'gerencia', 'supervisor', 'colaborador', 'vendedor']);
        break;

    case 'edit':
    case 'update':
        // Permite que vendedores editem clientes ligados ao seu fluxo de conversão.
        require_permission(['admin', 'gerencia', 'supervisor', 'vendedor']);
        break;

    case 'delete':
        require_permission(['admin', 'gerencia', 'supervisor', 'colaborador']);
        break;

    default:
        require_permission(['admin', 'gerencia', 'supervisor', 'colaborador']);
        break;
}
// ===================================================================

// O roteamento continua o mesmo
switch ($action) {
    case 'create':
        $controller->create();
        break;
    case 'store':
        $controller->store();
        break;
    case 'edit':
        $controller->edit($id);
        break;
    case 'update':
        $controller->update();
        break;
    case 'delete':
        $controller->delete($id);
        break;
    case 'get_json':
        $controller->getClienteJson();
        break;
    
    case 'index':
    default:
        $controller->index();
        break;
}
?>