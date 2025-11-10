<?php
// Arquivo: crm/agendamentos/api_eventos.php (VERSÃO CORRIGIDA E INTEGRADA)

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../app/core/auth_check.php';

$currentUserId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;
$currentPerfil = $_SESSION['user_perfil'] ?? '';
$managementProfiles = ['admin', 'gerencia', 'supervisor', 'master'];

// --- CONSULTA SQL CORRIGIDA ---
// Alterações:
// 1. JOIN com 'users' e usa 'nome_completo'
// 2. JOIN com 'clientes' e usa 'nome_cliente'
// 3. O JOIN com clientes agora é feito a partir do agendamento, não da prospecção
$context = $_GET['context'] ?? '';

if ($context === 'sdr-table') {
    $sql = "SELECT
                a.id,
                a.titulo,
                a.data_inicio,
                a.data_fim,
                a.status,
                a.usuario_id,
                u.nome_completo AS responsavel_nome,
                u.perfil AS perfil,
                a.prospeccao_id,
                c.nome_cliente
            FROM agendamentos a
            LEFT JOIN prospeccoes p ON a.prospeccao_id = p.id
            LEFT JOIN clientes c ON a.cliente_id = c.id
            LEFT JOIN users u ON a.usuario_id = u.id";
} else {
    $sql = "SELECT
                a.id,
                a.titulo,
                a.data_inicio as start,
                a.data_fim as end,
                a.status,
                a.local_link,
                a.observacoes,
                a.usuario_id,
                u.nome_completo as responsavel,
                u.perfil as perfil,
                a.prospeccao_id,
                c.nome_cliente
            FROM agendamentos a
            JOIN users u ON a.usuario_id = u.id
            LEFT JOIN clientes c ON a.cliente_id = c.id
            LEFT JOIN prospeccoes p ON a.prospeccao_id = p.id";
}

$params = [];
$where_clauses = [];

// --- LÓGICA DE PERMISSÃO CORRIGIDA ---
// Usa 'user_perfil' e os nomes de perfil corretos
if ($context === 'sdr-table') {
    $where_clauses[] = "a.data_inicio >= NOW()";

    if (in_array($currentPerfil, ['sdr'], true)) {
        $where_clauses[] = "p.sdrId = :current_sdr";
        $params[':current_sdr'] = $currentUserId;
    } elseif (!in_array($currentPerfil, $managementProfiles, true)) {
        $where_clauses[] = "a.usuario_id = :current_user";
        $params[':current_user'] = $currentUserId;
    }

    if (!empty($_GET['q'])) {
        $search = '%' . strtolower(trim($_GET['q'])) . '%';
        $where_clauses[] = "(LOWER(a.titulo) LIKE :search OR LOWER(c.nome_cliente) LIKE :search OR LOWER(u.nome_completo) LIKE :search OR LOWER(a.status) LIKE :search)";
        $params[':search'] = $search;
    }
} else {
    if (in_array($currentPerfil, $managementProfiles, true)) {
        $responsavelFiltro = filter_input(INPUT_GET, 'responsavel_id', FILTER_VALIDATE_INT);
        if ($responsavelFiltro !== null && $responsavelFiltro !== false) {
            $where_clauses[] = "a.usuario_id = :responsavel_id";
            $params[':responsavel_id'] = $responsavelFiltro;
        }
    } elseif ($currentPerfil === 'sdr') {
        $vendedorIds = [];
        if ($currentUserId > 0) {
            $stmtVendedores = $pdo->prepare('SELECT DISTINCT vendedorId FROM distribuicao_leads WHERE sdrId = :sdr_id');
            $stmtVendedores->execute([':sdr_id' => $currentUserId]);
            $vendedorIds = array_map('intval', $stmtVendedores->fetchAll(PDO::FETCH_COLUMN));
        }

        $usuariosVisiveis = array_values(array_filter(array_unique(array_merge([$currentUserId], $vendedorIds)), static function ($id) {
            return $id > 0;
        }));
        $responsavelFiltro = filter_input(INPUT_GET, 'responsavel_id', FILTER_VALIDATE_INT);

        if ($responsavelFiltro !== null && $responsavelFiltro !== false) {
            if (in_array($responsavelFiltro, $usuariosVisiveis, true)) {
                $where_clauses[] = "a.usuario_id = :responsavel_id";
                $params[':responsavel_id'] = $responsavelFiltro;
            } else {
                $where_clauses[] = '1 = 0';
            }
        } else {
            if (!empty($usuariosVisiveis)) {
                $placeholders = [];
                foreach ($usuariosVisiveis as $indice => $usuarioIdVisivel) {
                    $paramName = ':sdr_visible_' . $indice;
                    $placeholders[] = $paramName;
                    $params[$paramName] = $usuarioIdVisivel;
                }
                $where_clauses[] = 'a.usuario_id IN (' . implode(', ', $placeholders) . ')';
            } else {
                $where_clauses[] = 'a.usuario_id = :current_user';
                $params[':current_user'] = $currentUserId;
            }
        }
    } else {
        $where_clauses[] = "a.usuario_id = :user_id";
        $params[':user_id'] = $currentUserId;
    }
}

if (!empty($where_clauses)) {
    $sql .= " WHERE " . implode(" AND ", $where_clauses);
}

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $eventos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    header('Content-Type: application/json');

    if ($context === 'sdr-table') {
        echo json_encode(array_map(static function ($evento) {
            return [
                'id' => (int) $evento['id'],
                'titulo' => $evento['titulo'],
                'data_inicio' => $evento['data_inicio'],
                'data_fim' => $evento['data_fim'],
                'status' => $evento['status'],
                'responsavel_nome' => $evento['responsavel_nome'],
                'prospeccao_id' => $evento['prospeccao_id'],
                'nome_cliente' => $evento['nome_cliente']
            ];
        }, $eventos));
        return;
    }

    // Adiciona cores e prepara dados para o tooltip
    $eventos_formatados = array_map(static function($evento) {
        $perfil = $evento['perfil'] ?? '';
        $cor = '#007bff';

        if ($perfil === 'sdr') {
            $cor = '#28a745';
        } elseif (in_array($perfil, ['gerencia', 'admin', 'master', 'supervisor'], true)) {
            $cor = '#6f42c1';
        }

        $evento['backgroundColor'] = $cor;
        $evento['borderColor'] = $cor;
        $evento['color'] = $cor;
        $evento['extendedProps'] = [
            'responsavel' => $evento['responsavel'],
            'cliente' => $evento['nome_cliente'],
            'prospeccao_id' => $evento['prospeccao_id'],
            'local_link' => $evento['local_link'],
            'observacoes' => $evento['observacoes'],
            'usuario_id' => (int) $evento['usuario_id'],
            'perfil' => $perfil,
            'canDelete' => $evento['usuario_id'] == ($_SESSION['user_id'] ?? null)
                || in_array($_SESSION['user_perfil'] ?? '', ['admin', 'gerencia', 'supervisor', 'master'], true)
        ];
        return $evento;
    }, $eventos);

    echo json_encode($eventos_formatados);

} catch (PDOException $e) {
    header('Content-Type: application/json');
    http_response_code(500); // Erro de servidor
    error_log("Erro na API de eventos: " . $e->getMessage());
    echo json_encode(['error' => 'Erro ao buscar eventos.']);
}
?>