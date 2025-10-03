<?php
// Arquivo: crm/prospeccoes/salvar.php (VERSÃO FINAL E CORRIGIDA)

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../app/core/auth_check.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $cliente_id = filter_input(INPUT_POST, 'cliente_id', FILTER_VALIDATE_INT);
    $responsavel_id = $_SESSION['user_id'];
    $userPerfil = $_SESSION['user_perfil'] ?? '';
    $data_prospeccao = date('Y-m-d H:i:s');

    // Validação
    if (empty($cliente_id)) {
        $_SESSION['error_message'] = "Lead associado é obrigatório.";
        header('Location: ' . APP_URL . '/crm/prospeccoes/nova.php');
        exit();
    }

    try {
        $stmtCliente = $pdo->prepare("SELECT nome_cliente, nome_responsavel, crmOwnerId FROM clientes WHERE id = :id AND is_prospect = 1");
        $stmtCliente->execute([':id' => $cliente_id]);
        $cliente = $stmtCliente->fetch(PDO::FETCH_ASSOC);

        if (!$cliente) {
            $_SESSION['error_message'] = "Lead não encontrado ou já convertido.";
            header('Location: ' . APP_URL . '/crm/prospeccoes/nova.php');
            exit();
        }

        if ($userPerfil === 'vendedor' && (int)($cliente['crmOwnerId'] ?? 0) !== (int)$responsavel_id) {
            $_SESSION['error_message'] = "Você não tem permissão para utilizar este lead.";
            header('Location: ' . APP_URL . '/crm/prospeccoes/nova.php');
            exit();
        }

        $nome_prospecto = trim($cliente['nome_responsavel'] ?? '');
        if ($nome_prospecto === '') {
            $nome_prospecto = trim($cliente['nome_cliente'] ?? '');
        }

        if ($nome_prospecto === '') {
            $nome_prospecto = 'Lead #' . $cliente_id;
        }

    } catch (PDOException $exception) {
        $_SESSION['error_message'] = "Erro ao validar o lead selecionado.";
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
            ':feedback_inicial' => '', ':valor_proposto' => 0,
            ':status' => 'Cliente ativo'
        ]);
        $prospeccao_id = $pdo->lastInsertId();

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