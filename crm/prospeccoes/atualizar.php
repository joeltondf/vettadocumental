<?php
// Arquivo: crm/prospeccoes/atualizar.php (VERSÃO ATUALIZADA)

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../app/core/auth_check.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . APP_URL . '/crm/prospeccoes/lista.php');
    exit;
}

$prospeccao_id = filter_input(INPUT_POST, 'prospeccao_id', FILTER_VALIDATE_INT);
$action = $_POST['action'] ?? '';
$user_id = $_SESSION['user_id'];

if (!$prospeccao_id) {
    die("ID da prospecção inválido.");
}

try {
    if ($action === 'add_interaction') {
        $observacao = trim($_POST['observacao'] ?? '');
        $tipoInteracao = $_POST['tipo_interacao'] ?? 'nota';
        $tipoPermitidos = ['nota', 'chamada', 'reuniao'];
        if (!in_array($tipoInteracao, $tipoPermitidos, true)) {
            $tipoInteracao = 'nota';
        }

        $resultadoChamada = trim($_POST['resultado'] ?? '');
        if ($tipoInteracao === 'chamada' && $resultadoChamada !== '') {
            $observacao = 'Resultado da chamada: ' . $resultadoChamada . (empty($observacao) ? '' : ' — ' . $observacao);
        }

        if (!empty($observacao)) {
            $sql = "INSERT INTO interacoes (prospeccao_id, usuario_id, observacao, tipo) VALUES (?, ?, ?, ?)";
            $pdo->prepare($sql)->execute([$prospeccao_id, $user_id, $observacao, $tipoInteracao]);
        }

    } elseif ($action === 'update_prospect') {

        $allowedLeadCategories = ['Entrada', 'Qualificado', 'Com Orçamento', 'Em Negociação', 'Cliente Ativo', 'Sem Interesse'];
        $leadCategory = $_POST['lead_category'] ?? 'Entrada';
        if (!in_array($leadCategory, $allowedLeadCategories, true)) {
            $leadCategory = 'Entrada';
        }

        $new_data = [
            'data_reuniao_agendada' => !empty($_POST['data_reuniao_agendada']) ? $_POST['data_reuniao_agendada'] : null,
            'reuniao_compareceu' => isset($_POST['reuniao_compareceu']) ? 1 : 0,
            'lead_category' => $leadCategory
        ];

        $sql_update = "UPDATE prospeccoes SET
                            data_reuniao_agendada = :data_reuniao_agendada,
                            reuniao_compareceu = :reuniao_compareceu,
                            leadCategory = :lead_category
                        WHERE id = :id";
        $stmt_update = $pdo->prepare($sql_update);
        $stmt_update->execute(array_merge($new_data, ['id' => $prospeccao_id]));
    }

    $pdo->prepare("UPDATE prospeccoes SET data_ultima_atualizacao = NOW() WHERE id = ?")->execute([$prospeccao_id]);

    $_SESSION['success_message'] = "Prospecção atualizada com sucesso!";
    header("Location: " . APP_URL . "/crm/prospeccoes/detalhes.php?id=" . $prospeccao_id);
    exit;

} catch (PDOException $e) {
    die("Erro ao atualizar a prospecção: " . $e->getMessage());
}
?>