<?php

require_once __DIR__ . '/../models/SistemaLog.php';

class LogsController
{
    private $logModel;

    public function __construct($pdo)
    {
        $this->logModel = new SistemaLog($pdo);

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    public function index()
    {
        if (!isset($_SESSION['user_perfil']) || !in_array($_SESSION['user_perfil'], ['admin', 'gerencia', 'supervisor'], true)) {
            $_SESSION['error_message'] = 'Você não tem permissão para acessar os logs do sistema.';
            header('Location: dashboard.php');
            exit();
        }

        $pageTitle = 'Auditoria do Sistema';
        $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
        $perPage = isset($_GET['per_page']) ? max(1, (int)$_GET['per_page']) : 25;

        $logsData = $this->logModel->getLogs($page, $perPage);

        require __DIR__ . '/../views/layouts/header.php';
        require __DIR__ . '/../views/logs/index.php';
        require __DIR__ . '/../views/layouts/footer.php';
    }
}
