<?php
session_start();
require_once 'config.php';
require_once 'app/core/auth_check.php';
require_once 'app/controllers/RelatoriosController.php';
require_once __DIR__ . '/app/core/access_control.php';

require_permission(['admin', 'gerencia', 'financeiro', 'supervisor', 'vendedor', 'sdr']);

$controller = new RelatoriosController($pdo);
$controller->index();
