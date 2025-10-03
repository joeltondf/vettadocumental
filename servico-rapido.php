<?php
// /servico-rapido.php

session_start();
// Garante que apenas usuários logados possam acessar
require_once 'app/core/auth_check.php';

// Carrega a biblioteca do Google, corrigindo o erro fatal
require_once __DIR__ . '/vendor/autoload.php';
// Carrega as configurações do banco de dados e outras
require_once 'config.php'; 
// Carrega o controlador de processos
require_once 'app/controllers/ProcessosController.php';


// Inicializa o controlador
$controller = new ProcessosController($pdo);

// Define qual ação tomar com base na URL (ex: ?action=create)
$action = $_GET['action'] ?? 'create';

// Roteamento simples para as ações do serviço rápido
switch ($action) {
    case 'create':
        // Chama a função para exibir o formulário de serviço rápido
        $controller->createServicoRapido();
        break;
    case 'store':
        // Chama a função para salvar os dados do novo serviço
        $controller->storeServicoRapido();
        break;
    default:
        // Se nenhuma ação for especificada, exibe o formulário por padrão
        $controller->createServicoRapido();
        break;
}