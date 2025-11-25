<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/app/core/auth_check.php';
require_once __DIR__ . '/app/core/access_control.php';
require_once __DIR__ . '/app/controllers/LogsController.php';

require_permission(['admin', 'gerencia', 'supervisor']);

$controller = new LogsController($pdo);
$action = $_GET['action'] ?? 'index';

switch ($action) {
    case 'index':
    default:
        $controller->index();
        break;
}
