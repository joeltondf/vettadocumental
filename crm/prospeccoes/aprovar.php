<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../app/core/auth_check.php';
require_once __DIR__ . '/../../app/models/Prospeccao.php'; // Adicionado

$prospeccao_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$prospeccao_id) {
    header("Location: " . APP_URL . "/crm/prospeccoes/lista.php");
    exit;
}

if ($prospeccao && $prospeccao['cliente_id']) {
    $vendedor_id = $prospeccao['vendedor_id'] ?? null;
    $location = '/crm/clientes/editar_cliente.php?id=' . $prospeccao['cliente_id'] . '&prospeccao_id=' . $prospeccao_id;
    if ($vendedor_id) {
        $location .= '&vendedor_id=' . $vendedor_id;
    }
    header('Location: ' . $location);
    exit;
}

try {
    // Busca os dados da prospecção usando o Model para maior consistência
    $prospeccaoModel = new Prospeccao($pdo);
    $prospeccao = $prospeccaoModel->getById($prospeccao_id);

    if (!$prospeccao) {
        $_SESSION['error_message'] = "Prospecção não encontrada.";
        header("Location: " . APP_URL . "/crm/prospeccoes/lista.php");
        exit;
    }
    

    // Atualiza o status da prospecção para 'Convertido'
    $stmt_update = $pdo->prepare("UPDATE prospeccoes SET status = 'Convertido' WHERE id = :id");
    $stmt_update->execute([':id' => $prospeccao_id]);

    // Redireciona para o formulário de edição de cliente, passando os dados
    // da prospecção para o próximo passo.
    $queryParams = http_build_query([
        'prospeccao_id' => $prospeccao_id,
        'nome_servico' => $prospeccao['nome_prospecto'],
        'valor_inicial' => $prospeccao['valor_proposto']
    ]);

    header("Location: " . APP_URL . "/clientes.php?action=edit&id=" . $prospeccao['cliente_id'] . "&" . $queryParams);
    exit;

} catch (PDOException $e) {
    die("Erro ao aprovar prospecção: " . $e->getMessage());
}
?>