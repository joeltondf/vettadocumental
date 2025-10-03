<?php
// Arquivo: crm/prospeccoes/solicitar_exclusao.php (VERSÃO CORRIGIDA E INTEGRADA)

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../app/core/auth_check.php';
require_once __DIR__ . '/../../app/services/EmailService.php'; // Usa o serviço de e-mail

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . APP_URL . '/crm/prospeccoes/lista.php');
    exit;
}

$prospeccao_id = filter_input(INPUT_POST, 'prospeccao_id', FILTER_VALIDATE_INT);
$motivo = trim($_POST['motivo']);
$solicitante_id = $_SESSION['user_id'];
$solicitante_nome = $_SESSION['user_nome'];

if (!$prospeccao_id || empty($motivo)) {
    // Correção 1: Redirecionamento absoluto
    header('Location: ' . APP_URL . '/crm/prospeccoes/detalhes.php?id=' . $prospeccao_id . '&error=motivo_obrigatorio');
    exit;
}

try {
    // Correção 2: Usa 'nome_cliente' na consulta
    $stmt = $pdo->prepare("
        SELECT p.id_texto, p.nome_prospecto, c.nome_cliente
        FROM prospeccoes p
        LEFT JOIN clientes c ON p.cliente_id = c.id
        WHERE p.id = ?
    ");
    $stmt->execute([$prospeccao_id]);
    $prospect = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$prospect) {
        die("Prospecção não encontrada.");
    }

    // Monta o corpo do e-mail
    $assunto = "Solicitação de Exclusão de Prospecção: " . $prospect['id_texto'];
    $corpo_email = "
        <p>Olá Gerência/Supervisão,</p>
        <p>O colaborador <strong>" . htmlspecialchars($solicitante_nome) . "</strong> solicitou a exclusão da seguinte prospecção:</p>
        <ul>
            <li><strong>ID:</strong> " . htmlspecialchars($prospect['id_texto']) . "</li>
            <li><strong>Oportunidade:</strong> " . htmlspecialchars($prospect['nome_prospecto']) . "</li>
            <li><strong>Lead:</strong> " . htmlspecialchars($prospect['nome_cliente']) . "</li>
        </ul>
        <p><strong>Motivo da solicitação:</strong></p>
        <p style='padding: 10px; border: 1px solid #eee; background-color: #f9f9f9;'>" . nl2br(htmlspecialchars($motivo)) . "</p>
        <p>Por favor, analise e, se aprovado, realize a exclusão no sistema.</p>
        <p>Obrigado.</p>
    ";

    // Envia o e-mail usando o EmailService
    $emailService = new EmailService($pdo);
    $enviado = $emailService->sendEmail(EMAIL_FROM_ADDRESS, $assunto, $corpo_email);

    // Correção 3: Redirecionamentos absolutos
    if ($enviado) {
        $_SESSION['success_message'] = "Solicitação de exclusão enviada com sucesso!";
        header("Location: " . APP_URL . "/crm/prospeccoes/detalhes.php?id=" . $prospeccao_id);
    } else {
        $_SESSION['error_message'] = "Falha ao enviar o e-mail de solicitação.";
        header("Location: " . APP_URL . "/crm/prospeccoes/detalhes.php?id=" . $prospeccao_id);
    }
    exit;

} catch (Exception $e) {
    die("Erro ao processar a solicitação: " . $e->getMessage());
}
?>