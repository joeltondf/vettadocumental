<?php
// /processos_aprovar.php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/app/core/auth_check.php';
require_once __DIR__ . '/app/core/access_control.php';
require_permission(['admin', 'gerencia']); // Apenas gerentes e admins podem aprovar

// Lógica para buscar orçamentos com status 'Orçamento Pendente'
$stmt = $pdo->prepare("SELECT p.*, c.nome_cliente, u.nome_completo as nome_vendedor FROM processos p JOIN clientes c ON p.cliente_id = c.id JOIN users u ON p.colaborador_id = u.id WHERE p.status_processo = 'Orçamento Pendente'");
$stmt->execute();
$orcamentos_pendentes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Exemplo ao APROVAR um orçamento
$stmt_update = $pdo->prepare("UPDATE processos SET status_processo = 'Aprovado' WHERE id = :id");
$stmt_update->execute([':id' => $id_do_processo]);

// Inserir notificação para o vendedor
$mensagem = "Seu orçamento #" . $orcamento['orcamento_numero'] . " foi APROVADO!";
$link = "/processos.php?action=view&id=" . $id_do_processo;
$stmt_notify = $pdo->prepare("INSERT INTO notificacoes (usuario_id, mensagem, link) VALUES (?, ?, ?)");
$stmt_notify->execute([$id_do_vendedor, $mensagem, $link]);

// Incluir o header
require_once __DIR__ . '/app/views/layouts/header.php';
?>

<?php
require_once __DIR__ . '/app/views/layouts/footer.php';
?>