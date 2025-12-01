<?php
session_start();

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/app/core/auth_check.php';
require_once __DIR__ . '/app/core/access_control.php';
require_once __DIR__ . '/app/controllers/PainelUnificadoController.php';

require_permission(['admin', 'financeiro', 'gerencia']);

$controller = new PainelUnificadoController($pdo);
$controller->index();
