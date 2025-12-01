<?php
// /relatorios.php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/app/core/auth_check.php';
require_once __DIR__ . '/app/core/access_control.php';
require_permission(['admin', 'gerencia', 'financeiro']);

require_once 'app/controllers/RelatoriosGerenciaisController.php';

$controller = new RelatoriosGerenciaisController($pdo);
$controller->index();
