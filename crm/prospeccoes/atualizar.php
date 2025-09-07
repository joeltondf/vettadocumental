<?php
// Arquivo: crm/prospeccoes/atualizar.php (VERSÃO ATUALIZADA)

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../app/core/auth_check.php';
// Adiciona a referência ao Model de Processo
require_once __DIR__ . '/../../app/models/Processo.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . APP_URL . '/crm/prospeccoes/lista.php');
    exit;
}

$prospeccao_id = filter_input(INPUT_POST, 'prospeccao_id', FILTER_VALIDATE_INT);
$action = $_POST['action'] ?? '';
$user_id = $_SESSION['user_id'];
$user_nome = $_SESSION['user_nome']; 

if (!$prospeccao_id) {
    die("ID da prospecção inválido.");
}

try {
    if ($action === 'add_interaction') {
        // Lógica para adicionar interação (continua a mesma)
        $observacao = trim($_POST['observacao']);
        if (!empty($observacao)) {
            $sql = "INSERT INTO interacoes (prospeccao_id, usuario_id, observacao, tipo) VALUES (?, ?, ?, ?)";
            $pdo->prepare($sql)->execute([$prospeccao_id, $user_id, $observacao, 'nota']);
        }

    } elseif ($action === 'update_prospect') {
        
        $stmt_old = $pdo->prepare("SELECT * FROM prospeccoes WHERE id = ?");
        $stmt_old->execute([$prospeccao_id]);
        $old_data = $stmt_old->fetch(PDO::FETCH_ASSOC);

        $new_data = [
            'nome_prospecto' => trim($_POST['nome_prospecto']),
            'status' => trim($_POST['status']),
            'valor_proposto' => !empty($_POST['valor_proposto']) ? (float)str_replace(',', '.', $_POST['valor_proposto']) : null,
            'data_reuniao_agendada' => !empty($_POST['data_reuniao_agendada']) ? $_POST['data_reuniao_agendada'] : null,
            'reuniao_compareceu' => isset($_POST['reuniao_compareceu']) ? 1 : 0
        ];

        $sql_update = "UPDATE prospeccoes SET 
                            nome_prospecto = :nome_prospecto,
                            status = :status, 
                            valor_proposto = :valor_proposto,
                            data_reuniao_agendada = :data_reuniao_agendada, 
                            reuniao_compareceu = :reuniao_compareceu
                        WHERE id = :id";
        $stmt_update = $pdo->prepare($sql_update);
        $stmt_update->execute(array_merge($new_data, ['id' => $prospeccao_id]));

        // --- INÍCIO DA NOVA LÓGICA DE CRIAÇÃO DE PROCESSO ---
        // Verifica se o status foi alterado para 'Fechamento' ou 'Convertido' e se ainda não foi gerado
        $statusGatilho = ['Fechamento', 'Convertido'];
        if (in_array($new_data['status'], $statusGatilho) && !in_array($old_data['status'], $statusGatilho)) {
            
            $processoModel = new Processo($pdo);
            $dadosParaProcesso = [
                'cliente_id' => $old_data['cliente_id'],
                'titulo' => $new_data['nome_prospecto'],
                'valor_proposto' => $new_data['valor_proposto'],
                'vendedor_id' => $old_data['responsavel_id']
            ];

            $novoProcessoId = $processoModel->createFromProspeccao($dadosParaProcesso);

            if ($novoProcessoId) {
                 // ATUALIZA O CLIENTE PARA NÃO SER MAIS UM PROSPECT
                $stmt_update_cliente = $pdo->prepare("UPDATE clientes SET is_prospect = 0 WHERE id = ?");
                $stmt_update_cliente->execute([$old_data['cliente_id']]);
                // Se o processo foi criado, redireciona para ele
                $_SESSION['success_message'] = "Prospecção convertida com sucesso! Novo processo #{$novoProcessoId} criado.";
                header("Location: " . APP_URL . "/processos.php?action=view&id=" . $novoProcessoId);
                exit();
            } else {
                $_SESSION['error_message'] = "A prospecção foi atualizada, mas houve um erro ao criar o processo vinculado.";
            }
        }
        // --- FIM DA NOVA LÓGICA ---
        
        // Lógica de logs (continua a mesma)
        $logs = [];
        if ($old_data['status'] != $new_data['status']) {
            $logs[] = "Status alterado de '{$old_data['status']}' para '{$new_data['status']}'.";
        }
        // ... (outras comparações de log)
        
        if (!empty($logs)) {
            $sql_log = "INSERT INTO interacoes (prospeccao_id, usuario_id, observacao, tipo) VALUES (?, ?, ?, 'log_sistema')";
            $stmt_log = $pdo->prepare($sql_log);
            foreach ($logs as $log_message) {
                $stmt_log->execute([$prospeccao_id, $user_id, $log_message . " (por {$user_nome})"]);
            }
        }
    }

    $pdo->prepare("UPDATE prospeccoes SET data_ultima_atualizacao = NOW() WHERE id = ?")->execute([$prospeccao_id]);
    
    $_SESSION['success_message'] = "Prospecção atualizada com sucesso!";
    header("Location: " . APP_URL . "/crm/prospeccoes/detalhes.php?id=" . $prospeccao_id);
    exit;

} catch (PDOException $e) {
    die("Erro ao atualizar a prospecção: " . $e->getMessage());
}
?>