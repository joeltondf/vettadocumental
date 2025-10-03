<?php
// /aprovacoes.php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/app/core/auth_check.php';
require_once __DIR__ . '/app/controllers/ProcessosController.php';
require_once __DIR__ . '/app/core/access_control.php';

// Apenas admins e gerentes podem acessar esta página
require_permission(['admin', 'gerencia']);

$controller = new ProcessosController($pdo);

// Ação padrão é listar os orçamentos pendentes
$action = $_GET['action'] ?? 'listarPendentes';

switch ($action) {
    case 'aprovar':
        $controller->aprovarOrcamento();
        break;
    case 'recusar':
        $controller->recusarOrcamento();
        break;
    default:
        $controller->listarPendentes();
        break;
}