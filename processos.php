<?php
// /processos.php (CORRIGIDO)

session_start();
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/app/core/auth_check.php';
require_once __DIR__ . '/app/core/access_control.php';
require_once __DIR__ . '/app/controllers/ProcessosController.php';

$controller = new ProcessosController($pdo);

$action = $_GET['action'] ?? 'index';
$id = $_GET['id'] ?? null;
$anexo_id = $_GET['anexo_id'] ?? null;


// ===================================================================
// LÓGICA DE PERMISSÃO CORRIGIDA
// ===================================================================
switch ($action) {
    case 'create':
    case 'store':
        // Permite que todos os perfis autorizados criem processos.
        require_permission(['admin', 'gerencia', 'vendedor']);
        break;

    case 'edit':
    case 'change_status':
        // Permite que o vendedor acesse estas rotas.
        require_permission(['admin', 'gerencia', 'vendedor']);
        break;

    case 'update':
        // Permite atualização por vendedor, além de admin e gerência.
        require_permission(['admin', 'gerencia', 'vendedor']);
        break;
    case 'aprovar_orcamento':
    case 'recusar_orcamento':
    case 'delete':
        // Exclusão continua restrita.
        require_permission(['admin', 'gerencia']);
        break;
    
    // Para as demais ações (view, index, etc.), não aplicamos uma regra geral aqui,
    // pois o próprio controller deve filtrar o que o usuário pode ver.
}
// ===================================================================

switch ($action) {
    case 'create':
        $controller->create();
        break;
    case 'store':
        $controller->store();
        break;
    case 'edit':
        $controller->edit($id);
        break;
    case 'update':
        // Não existe método update no controller; store() trata tanto de criação quanto de atualização
        $controller->store();
        break;
    case 'view':
        $controller->view($id);
        break;
    case 'aprovar_orcamento':
        $controller->aprovarOrcamento();
        break;
    case 'recusar_orcamento':
        $controller->recusarOrcamento();
        break;
    case 'store_comment_ajax':
        $controller->storeCommentAjax();
        break;
    case 'update_etapas':
        $controller->updateEtapas();
        break;
    case 'show':
        $controller->show();
        break;
    case 'create_ca_sale':
        $controller->createContaAzulSale();
        break;
    case 'create_ca_quote':
        $controller->createContaAzulQuote();
        break;
    case 'test_create_sale':
        $controller->testCreateSale();
        break;
    case 'change_status':
        $controller->changeStatus();
        break;
    case 'delete':
        $controller->delete($id); // Ajuste aqui para passar o $id
        break;
    case 'index':
    default:
        $controller->index();
        break;
    case 'detalhe':
        $controller->detalhe($id); 
        break;
    case 'excluir_anexo': // Nova rota
        $controller->excluir_anexo($id, $anexo_id);
        break;

}
?>