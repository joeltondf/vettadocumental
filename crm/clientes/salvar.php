<?php
// Arquivo: crm/clientes/salvar.php (VERSÃO FINAL E CORRIGIDA)

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../app/core/auth_check.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Recebe os dados com os nomes corretos do formulário
    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    $nome_cliente = trim($_POST['nome_cliente']);
    $nome_responsavel = trim($_POST['nome_responsavel']);
    $email = trim($_POST['email']);
    $telefone = trim($_POST['telefone']);
    $canal_origem = trim($_POST['canal_origem']);
    $categoria = trim($_POST['categoria']);

    // Validação
    if (empty($nome_cliente) || empty($canal_origem)) {
        $_SESSION['error_message'] = "Nome do Cliente e Canal de Origem são obrigatórios.";
        $redirect_url = $id ? APP_URL . "/crm/clientes/editar_cliente.php?id=$id" : APP_URL . "/crm/clientes/novo.php";
        header("Location: " . $redirect_url);
        exit();
    }
    
try {
    if ($id) {
        // LÓGICA DE UPDATE (EDIÇÃO) - Esta parte permanece a mesma
        $sql = "UPDATE clientes SET nome_cliente = :nome_cliente, nome_responsavel = :nome_responsavel, email = :email, telefone = :telefone, canal_origem = :canal_origem, categoria = :categoria WHERE id = :id";
        $params = [
            ':nome_cliente' => $nome_cliente,
            ':nome_responsavel' => $nome_responsavel,
            ':email' => $email,
            ':telefone' => $telefone,
            ':canal_origem' => $canal_origem,
            ':categoria' => $categoria,
            ':id' => $id
        ];
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        $_SESSION['success_message'] = "Cliente atualizado com sucesso!";
        $redirect_location = APP_URL . '/crm/clientes/lista.php';

    } else {
        // LÓGICA DE INSERT (CRIAÇÃO) - Lógica de redirecionamento corrigida
        $sql = "INSERT INTO clientes (nome_cliente, nome_responsavel, email, telefone, canal_origem, categoria) 
                VALUES (:nome_cliente, :nome_responsavel, :email, :telefone, :canal_origem, :categoria)";
        $params = [
            ':nome_cliente' => $nome_cliente,
            ':nome_responsavel' => $nome_responsavel,
            ':email' => $email,
            ':telefone' => $telefone,
            ':canal_origem' => $canal_origem,
            ':categoria' => $categoria
        ];
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        $novo_cliente_id = $pdo->lastInsertId();
        $_SESSION['success_message'] = "Cliente criado com sucesso!";

        // **LÓGICA DE REDIRECIONAMENTO AJUSTADA**
        // Verifica se veio de uma página específica, como a de nova prospecção
        if (!empty($_POST['redirect_url'])) {
            // Adiciona o ID do novo cliente à URL de retorno para pré-seleção
            $redirect_location = $_POST['redirect_url'] . '?cliente_id=' . $novo_cliente_id;
        } else {
            // Se não, usa o redirecionamento padrão para a lista de clientes
            $redirect_location = APP_URL . '/crm/clientes/lista.php';
        }
    }

    header('Location: ' . $redirect_location);
    exit;

} catch (PDOException $e) {
    die("Erro ao salvar cliente: " . $e->getMessage());
}

} else {
    // Se a requisição não for POST, redireciona para a lista
    header('Location: ' . APP_URL . '/crm/clientes/lista.php');
    exit();
}
?>