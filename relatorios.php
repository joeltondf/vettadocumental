<?php
// relatorios.php - Hub de relatórios e BI

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/app/core/auth_check.php';
require_once __DIR__ . '/app/controllers/RelatoriosController.php';

$controller = new RelatoriosController($pdo);
$controller->index();
