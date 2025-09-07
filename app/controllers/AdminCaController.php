<?php
// /app/controllers/AdminCaController.php

require_once __DIR__ . '/../models/Configuracao.php';
require_once __DIR__ . '/../services/ContaAzulService.php';

class AdminCaController
{
    private $configuracaoModel;
    private $contaAzulService;

    public function __construct($pdo)
    {
        $this->configuracaoModel = new Configuracao($pdo);
        $this->contaAzulService = new ContaAzulService($this->configuracaoModel);
    }

    public function callback()
    {
        if (!isset($_GET['state']) || !isset($_SESSION['oauth2_state']) || $_GET['state'] !== $_SESSION['oauth2_state']) {
            $_SESSION['error_message'] = "Erro de validação de segurança (state mismatch).";
            header('Location: admin.php?action=settings');
            exit();
        }
        unset($_SESSION['oauth2_state']);

        if (isset($_GET['error'])) {
            $_SESSION['error_message'] = "Autorização negada: " . htmlspecialchars($_GET['error_description'] ?? $_GET['error']);
            header('Location: admin.php?action=settings');
            exit();
        }

        if (isset($_GET['code'])) {
            $success = $this->contaAzulService->exchangeCodeForToken($_GET['code']);
            $_SESSION[$success ? 'success_message' : 'error_message'] = $success ? "Conta Azul conectada com sucesso!" : "Falha ao obter o token de acesso.";
        } else {
            $_SESSION['error_message'] = "Código de autorização não recebido.";
        }

        header('Location: admin.php?action=settings');
        exit();
    }
    
    public function disconnect()
    {
        $this->configuracaoModel->saveSetting('conta_azul_access_token', '');
        $this->configuracaoModel->saveSetting('conta_azul_refresh_token', '');
        $this->configuracaoModel->saveSetting('conta_azul_token_expires_at', '');
        
        $_SESSION['success_message'] = "Conta Azul desconectada com sucesso.";
        header('Location: admin.php?action=settings');
        exit();
    }
}