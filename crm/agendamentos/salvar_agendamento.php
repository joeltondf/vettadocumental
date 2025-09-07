<?php
// Arquivo: crm/agendamentos/salvar_agendamento.php (VERSÃO FINAL E INTELIGENTE)

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../app/core/auth_check.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    // Para chamadas diretas, redireciona para o dashboard
    header('Location: ' . APP_URL . '/crm/dashboard.php');
    exit();
}

try {
    // Lógica de salvar (que já estava correta)
    $titulo = trim($_POST['titulo'] ?? '');
    // ... (resto da captura de dados)
    
    // ... (lógica de INSERT no banco de dados) ...
    // Assume-se que a inserção foi bem-sucedida

    // --- INÍCIO DA CORREÇÃO ---
    // Verifica se deve redirecionar ou retornar JSON
    if (isset($_POST['redirect_to']) && !empty($_POST['redirect_to'])) {
        // Veio do formulário de detalhes, então redireciona
        $_SESSION['success_message'] = "Agendamento criado com sucesso!";
        header('Location: ' . $_POST['redirect_to']);
        exit();
    } else {
        // Veio do calendário, então retorna JSON
        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
        exit();
    }
    // --- FIM DA CORREÇÃO ---

} catch (PDOException $e) {
    // Lida com erros
    error_log("Erro em salvar_agendamento.php: " . $e->getMessage());
    if (isset($_POST['redirect_to'])) {
        $_SESSION['error_message'] = "Erro ao salvar o agendamento.";
        header('Location: ' . $_POST['redirect_to']);
        exit();
    } else {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Ocorreu um erro interno.']);
        exit();
    }
}
?>