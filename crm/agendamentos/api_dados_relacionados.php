<?php
// Arquivo: crm/agendamentos/api_dados_relacionados.php (VERSÃO CORRIGIDA)

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../app/core/auth_check.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'get_prospeccoes_by_cliente':
            $cliente_id = filter_input(INPUT_GET, 'cliente_id', FILTER_VALIDATE_INT);
            if (!$cliente_id) {
                echo json_encode(['error' => 'ID do cliente inválido.']);
                exit();
            }
            $stmt = $pdo->prepare("SELECT id, nome_prospecto AS text FROM prospeccoes WHERE cliente_id = ? ORDER BY nome_prospecto");
            $stmt->execute([$cliente_id]);
            $prospeccoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode($prospeccoes);
            break;

        case 'get_prospeccao_details':
            $prospeccao_id = filter_input(INPUT_GET, 'prospeccao_id', FILTER_VALIDATE_INT);
            if (!$prospeccao_id) {
                echo json_encode(['error' => 'ID da prospecção inválido.']);
                exit();
            }
            $stmt = $pdo->prepare("SELECT id, cliente_id, nome_prospecto FROM prospeccoes WHERE id = ?");
            $stmt->execute([$prospeccao_id]);
            $prospeccao = $stmt->fetch(PDO::FETCH_ASSOC);
            echo json_encode($prospeccao);
            break;

        case 'search_clientes':
            $term = $_GET['term'] ?? '';
            // Correção: Usar 'nome_cliente' em vez de 'nome_empresa'
            $stmt = $pdo->prepare("SELECT id, nome_cliente AS text FROM clientes WHERE nome_cliente LIKE ? AND is_prospect = 1 ORDER BY nome_cliente LIMIT 10");
            $stmt->execute(["%$term%"]);
            $clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['results' => $clientes]);
            break;

        case 'search_prospeccoes':
            $term = $_GET['term'] ?? '';
            $stmt = $pdo->prepare("SELECT id, nome_prospecto AS text FROM prospeccoes WHERE nome_prospecto LIKE ? ORDER BY nome_prospecto LIMIT 10");
            $stmt->execute(["%$term%"]);
            $prospeccoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['results' => $prospeccoes]);
            break;
            
        case 'get_cliente_by_id':
            $cliente_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
            if (!$cliente_id) {
                echo json_encode(['error' => 'ID do contato inválido.']);
                exit();
            }
            // Correção: Usar 'nome_cliente' em vez de 'nome_empresa'
            $stmt = $pdo->prepare("SELECT id, nome_cliente AS text FROM clientes WHERE id = ?");
            $stmt->execute([$cliente_id]);
            $cliente = $stmt->fetch(PDO::FETCH_ASSOC);
            echo json_encode($cliente);
            break;

        case 'get_prospeccao_by_id':
            $prospeccao_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
            if (!$prospeccao_id) {
                echo json_encode(['error' => 'ID da prospecção inválido.']);
                exit();
            }
            $stmt = $pdo->prepare("SELECT id, nome_prospecto AS text FROM prospeccoes WHERE id = ?");
            $stmt->execute([$prospeccao_id]);
            $prospeccao = $stmt->fetch(PDO::FETCH_ASSOC);
            echo json_encode($prospeccao);
            break;

        default:
            echo json_encode(['error' => 'Ação inválida.']);
            break;
    }
} catch (PDOException $e) {
    error_log("Erro na API de dados relacionados: " . $e->getMessage());
    echo json_encode(['error' => 'Erro interno do servidor.']);
}
?>