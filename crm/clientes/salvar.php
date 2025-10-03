<?php
// Arquivo: crm/clientes/salvar.php (VERSÃO FINAL E CORRIGIDA)

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../app/core/auth_check.php';
require_once __DIR__ . '/../../app/utils/PhoneUtils.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Recebe os dados com os nomes corretos do formulário
    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    $nome_cliente = trim($_POST['nome_cliente']);
    $nome_responsavel = trim($_POST['nome_responsavel']);
    $email = trim($_POST['email']);
    $telefone = trim($_POST['telefone']);
    $canal_origem = trim($_POST['canal_origem']);
    $categoria = trim($_POST['categoria']);
    $redirectUrl = $id ? APP_URL . "/crm/clientes/editar_cliente.php?id=$id" : APP_URL . "/crm/clientes/novo.php";

    // Validação
    if (empty($nome_cliente) || empty($canal_origem)) {
        $_SESSION['error_message'] = "Nome do Cliente e Canal de Origem são obrigatórios.";
        header('Location: ' . $redirectUrl);
        exit();
    }

    try {
        if ($telefone !== '') {
            $parts = extractPhoneParts($telefone);
            $telefone = ($parts['ddd'] ?? '') . ($parts['phone'] ?? '');
        }
    } catch (InvalidArgumentException $exception) {
        $_SESSION['error_message'] = $exception->getMessage();
        header('Location: ' . $redirectUrl);
        exit();
    }

    try {
        if ($id) {
            $stmtCheckProspect = $pdo->prepare("SELECT is_prospect FROM clientes WHERE id = :id");
            $stmtCheckProspect->execute([':id' => $id]);
            $clienteExistente = $stmtCheckProspect->fetch(PDO::FETCH_ASSOC);

            if (!$clienteExistente) {
                $_SESSION['error_message'] = "Cliente não encontrado.";
                header('Location: ' . APP_URL . '/crm/clientes/lista.php');
                exit();
            }

            if ((int) ($clienteExistente['is_prospect'] ?? 0) !== 1) {
                $_SESSION['error_message'] = "Este contato já foi convertido em cliente e deve ser gerenciado pelo sistema principal.";
                header('Location: ' . APP_URL . '/crm/clientes/lista.php');
                exit();
            }

            $sql = "UPDATE clientes SET nome_cliente = :nome_cliente, nome_responsavel = :nome_responsavel, email = :email, telefone = :telefone, canal_origem = :canal_origem, categoria = :categoria, is_prospect = :is_prospect WHERE id = :id";
            $params = [
                ':nome_cliente' => $nome_cliente,
                ':nome_responsavel' => $nome_responsavel,
                ':email' => $email,
                ':telefone' => $telefone,
                ':canal_origem' => $canal_origem,
                ':categoria' => $categoria,
                ':is_prospect' => 1,
                ':id' => $id
            ];
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            $_SESSION['success_message'] = "Cliente atualizado com sucesso!";
            $redirect_location = APP_URL . '/crm/clientes/lista.php';

        } else {
            $sql = "INSERT INTO clientes (nome_cliente, nome_responsavel, email, telefone, canal_origem, categoria, is_prospect)
                    VALUES (:nome_cliente, :nome_responsavel, :email, :telefone, :canal_origem, :categoria, :is_prospect)";
            $params = [
                ':nome_cliente' => $nome_cliente,
                ':nome_responsavel' => $nome_responsavel,
                ':email' => $email,
                ':telefone' => $telefone,
                ':canal_origem' => $canal_origem,
                ':categoria' => $categoria,
                ':is_prospect' => 1
            ];
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            $novo_cliente_id = $pdo->lastInsertId();
            $_SESSION['success_message'] = "Contato criado com sucesso! Agora cadastre a prospecção.";

            $redirectBase = $_POST['redirect_url'] ?? '';
            if (empty($redirectBase) || strpos($redirectBase, APP_URL) !== 0) {
                $redirectBase = APP_URL . '/crm/prospeccoes/nova.php';
            }

            $separator = strpos($redirectBase, '?') === false ? '?' : '&';
            $redirect_location = $redirectBase . $separator . 'cliente_id=' . $novo_cliente_id;
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