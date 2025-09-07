<?php
// Arquivo: crm/prospeccoes/salvar.php (VERSÃO FINAL E CORRIGIDA)

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../app/core/auth_check.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $cliente_id = filter_input(INPUT_POST, 'cliente_id', FILTER_VALIDATE_INT);
    $nome_prospecto = trim($_POST['nome_prospecto'] ?? '');
    $valor_proposto_str = str_replace(',', '.', $_POST['valor_proposto'] ?? '0');
    $valor_proposto = filter_var($valor_proposto_str, FILTER_VALIDATE_FLOAT);
    $feedback_inicial = trim($_POST['feedback_inicial'] ?? '');
    $status = trim($_POST['status'] ?? '');
    
    $responsavel_id = $_SESSION['user_id']; 
    $data_prospeccao = date('Y-m-d H:i:s');

    // Validação
    if (empty($cliente_id) || empty($nome_prospecto)) {
        $_SESSION['error_message'] = "Cliente e Nome da Oportunidade são obrigatórios.";
        header('Location: ' . APP_URL . '/crm/prospeccoes/nova.php');
        exit();
    }
    
    $pdo->beginTransaction();
    try {
        $sql = "INSERT INTO prospeccoes (cliente_id, nome_prospecto, data_prospeccao, responsavel_id, feedback_inicial, valor_proposto, status) VALUES (:cliente_id, :nome_prospecto, :data_prospeccao, :responsavel_id, :feedback_inicial, :valor_proposto, :status)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':cliente_id' => $cliente_id, ':nome_prospecto' => $nome_prospecto,
            ':data_prospeccao' => $data_prospeccao, ':responsavel_id' => $responsavel_id,
            ':feedback_inicial' => $feedback_inicial, ':valor_proposto' => $valor_proposto,
            ':status' => $status
        ]);
        $prospeccao_id = $pdo->lastInsertId();

        // Insere a primeira interação
        if (!empty($feedback_inicial)) {
            $sql_interacao = "INSERT INTO interacoes (prospeccao_id, usuario_id, observacao, tipo) VALUES (?, ?, ?, ?)";
            $stmt_interacao = $pdo->prepare($sql_interacao);
            $stmt_interacao->execute([$prospeccao_id, $responsavel_id, "Observação inicial: " . $feedback_inicial, 'nota']);
        }
        
        $pdo->commit();
        
        $_SESSION['success_message'] = "Prospecção criada com sucesso!";
        header('Location: ' . APP_URL . '/crm/prospeccoes/lista.php');
        exit();

    } catch (PDOException $e) { 
        $pdo->rollBack();
        $_SESSION['error_message'] = "Erro de banco de dados: " . $e->getMessage();
        header('Location: ' . APP_URL . '/crm/prospeccoes/nova.php');
        exit();
    }
}
?>