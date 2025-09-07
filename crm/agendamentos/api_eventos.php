<?php
// Arquivo: crm/agendamentos/api_eventos.php (VERSÃO CORRIGIDA E INTEGRADA)

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../app/core/auth_check.php';

// --- CONSULTA SQL CORRIGIDA ---
// Alterações:
// 1. JOIN com 'users' e usa 'nome_completo'
// 2. JOIN com 'clientes' e usa 'nome_cliente'
// 3. O JOIN com clientes agora é feito a partir do agendamento, não da prospecção
$sql = "SELECT 
            a.id, 
            a.titulo, 
            a.data_inicio as start, 
            a.data_fim as end,
            a.status,
            a.local_link,
            a.observacoes,
            u.nome_completo as responsavel,
            a.prospeccao_id,
            c.nome_cliente
        FROM agendamentos a
        JOIN users u ON a.usuario_id = u.id
        LEFT JOIN clientes c ON a.cliente_id = c.id
        LEFT JOIN prospeccoes p ON a.prospeccao_id = p.id";

$params = [];
$where_clauses = [];

// --- LÓGICA DE PERMISSÃO CORRIGIDA ---
// Usa 'user_perfil' e os nomes de perfil corretos
if (!in_array($_SESSION['user_perfil'], ['admin', 'gerencia'])) {
    $where_clauses[] = "a.usuario_id = :user_id";
    $params[':user_id'] = $_SESSION['user_id'];
}
elseif (isset($_GET['responsavel_id']) && !empty($_GET['responsavel_id'])) {
    $where_clauses[] = "a.usuario_id = :responsavel_id";
    $params[':responsavel_id'] = filter_input(INPUT_GET, 'responsavel_id', FILTER_VALIDATE_INT);
}

if (!empty($where_clauses)) {
    $sql .= " WHERE " . implode(" AND ", $where_clauses);
}

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $eventos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Adiciona cores e prepara dados para o tooltip
    $eventos_formatados = array_map(function($evento) {
        $colors = ['Confirmado' => '#3788d8', 'Pendente' => '#f0ad4e', 'Realizado' => '#5cb85c', 'Cancelado' => '#d9534f'];
        $evento['color'] = $colors[$evento['status']] ?? '#777';
        
        $evento['extendedProps'] = [
            'responsavel' => $evento['responsavel'],
            'cliente' => $evento['nome_cliente'], // Usa a coluna correta
            'prospeccao_id' => $evento['prospeccao_id'],
            'local_link' => $evento['local_link'],
            'observacoes' => $evento['observacoes']
        ];
        return $evento;
    }, $eventos);

    header('Content-Type: application/json');
    echo json_encode($eventos_formatados);

} catch (PDOException $e) {
    header('Content-Type: application/json');
    http_response_code(500); // Erro de servidor
    error_log("Erro na API de eventos: " . $e->getMessage());
    echo json_encode(['error' => 'Erro ao buscar eventos.']);
}
?>