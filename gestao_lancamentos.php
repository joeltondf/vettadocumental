<?php
// /gestao_lancamentos.php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/app/core/auth_check.php';
require_once __DIR__ . '/app/core/access_control.php';
require_permission(['admin','gerencia','financeiro','vendedor']);
require_once 'app/controllers/FluxoCaixaController.php';

$controller = new FluxoCaixaController($pdo);
$controller->index();
