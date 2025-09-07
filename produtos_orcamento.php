<?php
// produtos_orcamento.php

require_once 'config.php';
require_once __DIR__ . '/app/controllers/ProdutosOrcamentoController.php';

$controller = new ProdutosOrcamentoController($pdo);

$action = $_GET['action'] ?? 'index';
$id = $_GET['id'] ?? null;

switch ($action) {
    case 'store':
        $controller->store();
        break;
    case 'update':
        $controller->update($id);
        break;
    case 'delete':
        $controller->delete($id);
        break;
    default:
        $controller->index();
        break;
}