<?php
session_start();
require_once 'config.php';
require_once 'app/core/auth_check.php';
require_once 'app/controllers/FluxoCaixaController.php';
require_once __DIR__ . '/app/core/access_control.php';
require_permission(['admin', 'financeiro', 'gerencia', 'vendedor']);

$controller = new FluxoCaixaController($pdo);
$action = $_GET['action'] ?? 'index';

$method = $_SERVER['REQUEST_METHOD']; // Captura o método: GET, POST, etc.

switch ($action) {
    case 'store':
        if ($method === 'POST') {
            $controller->store();
        }
        break;
    case 'update':
        if ($method === 'POST') {
            $controller->update();
        }
        break;
    case 'finalizar':
        if ($method === 'POST') {
            $controller->finalizar();
        }
        break;
    case 'ajustar':
        if ($method === 'POST') {
            $controller->ajustar();
        }
        break;

    case 'delete':
        if ($method === 'POST') {
            $controller->delete();
        }
        break;

    default:
        $controller->index();
        break;
}