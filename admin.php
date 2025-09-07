<?php
// /admin.php - Roteador Central de Administração

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/app/core/auth_check.php';

if (!isset($_SESSION['user_perfil']) || !in_array($_SESSION['user_perfil'], ['admin', 'gerencia'])) {
    $_SESSION['error_message'] = "Você não tem permissão para acessar esta área.";
    header('Location: dashboard.php');
    exit();
}

// Carrega os controllers necessários
require_once __DIR__ . '/app/controllers/AdminController.php';
require_once __DIR__ . '/app/controllers/VendedoresController.php';
require_once __DIR__ . '/app/controllers/ProcessosController.php'; 

// ---- LÓGICA DE ROTEAMENTO ----

$action = $_GET['action'] ?? 'index';

// Instancia o controller principal que agora gerencia a maioria das ações
$adminController = new AdminController($pdo);

// O switch decide qual método chamar.
switch ($action) {
    // --- Rotas de Administração Geral (AdminController) ---
    case 'index':
    case 'dashboard':
    default:
        $adminController->index();
        break;
    case 'settings':
        $adminController->settings();
        break;
    case 'save_settings':
        $adminController->saveSettings();
        break;
    case 'tradutores':
        $adminController->listTradutores();
        break;
    case 'config':
        $adminController->showConfiguracoes();
        break;

    // Adiciona o 'case' que faltava para a ação de salvar a aparência
    case 'save_config':
        $adminController->saveConfiguracoes();
        break;
    
    // =============================================================
    // NOVAS ROTAS PARA CONFIGURAÇÃO CENTRALIZADA DE SMTP
    // =============================================================
    case 'smtp_settings':
        $adminController->showSmtpSettings();
        break;
    
    case 'save_smtp_settings':
        $adminController->saveSmtpSettings();
        break;
    // ADICIONE ESTE NOVO BLOCO
    case 'test_smtp':
        $adminController->testSmtpConnection();
        break;
    // =============================================================
    
    // --- ROTAS DE GESTÃO DE USUÁRIOS (AdminController) ---
    case 'users':
        $adminController->listUsers();
        break;
    case 'create_user':
        $adminController->createUser();
        break;
    case 'store_user':
        $adminController->storeUser();
        break;
    case 'edit_user':
        $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
        $adminController->editUser($id);
        break;
    case 'update_user':
        $adminController->updateUser();
        break;
    case 'delete_user':
        $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
        $adminController->deleteUser($id);
        break;
        
    // --- ROTAS DE GESTÃO DE VENDEDORES (AdminController) ---
    case 'vendedores':
        $adminController->listVendedores();
        break;
        
    // --- ROTAS DE AUTOMAÇÃO (AdminController) ---
    case 'automacao_campanhas':
        $adminController->showAutomacaoCampanhas();
        break;
    case 'automacao_settings':
        $adminController->showAutomacaoSettings();
        break;
    case 'save_automacao_settings':
        $adminController->saveAutomacaoSettings();
        break;
    case 'store_automacao_campanha':
        $adminController->storeAutomacaoCampanha();
        break;
    case 'update_automacao_campanha':
        $adminController->updateAutomacaoCampanha();
        break;
    case 'delete_automacao_campanha':
        $adminController->deleteAutomacaoCampanha();
        break;
    case 'get_automacao_campanha': 
        $adminController->getAutomacaoCampanhaJson();
        break;
    
    // --- ROTAS AJAX DA API DIGISAC ---
    case 'get_digisac_conexoes':
        $adminController->getDigisacConexoesJson();
        break;
    case 'get_digisac_templates':
        $adminController->getDigisacTemplatesJson();
        break;
    case 'get_digisac_users':
        $adminController->getDigisacUsersJson();
        break;

    // --- Rotas de Processos (ainda pode usar o ProcessosController se tiver lógica complexa) ---
    case 'processos':
        $processosController = new ProcessosController($pdo);
        $processosController->index();
        break;

    // --- NOVA ROTA PARA O TESTE ---
    case 'test_automacao_campanha':
        $adminController->testAutomacaoCampanha();
        break;
}