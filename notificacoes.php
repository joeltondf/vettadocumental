<?php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/app/controllers/ProcessosController.php';
require_once __DIR__ . '/app/models/Processo.php';
require_once __DIR__ . '/app/models/User.php';
require_once __DIR__ . '/app/models/Notificacao.php';

// Garante que o usuário esteja logado
if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}

$controller = new ProcessosController($pdo);

// Ação padrão agora é exibir o painel de notificações
$action = $_GET['action'] ?? 'painelNotificacoes';

switch ($action) {
    case 'markRead':
        $notificationId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
        if ($notificationId) {
            $notificacaoModel = new Notificacao($pdo);
            if ($notificacaoModel->marcarComoLida($notificationId, (int)($_SESSION['user_id'] ?? 0))) {
                $_SESSION['success_message'] = 'Notificação marcada como lida.';
            } else {
                $_SESSION['error_message'] = 'Não foi possível atualizar a notificação.';
            }
        }
        header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? APP_URL . '/notificacoes.php'));
        exit;
    case 'markResolved':
        $notificationId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
        if ($notificationId) {
            $notificacaoModel = new Notificacao($pdo);
            if ($notificacaoModel->marcarComoResolvida($notificationId, (int)($_SESSION['user_id'] ?? 0))) {
                $_SESSION['success_message'] = 'Notificação marcada como resolvida.';
            } else {
                $_SESSION['error_message'] = 'Não foi possível atualizar a notificação.';
            }
        }
        header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? APP_URL . '/notificacoes.php'));
        exit;
    case 'batchUpdate':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            exit;
        }

        $rawInput = file_get_contents('php://input');
        $parsedInput = $_POST;
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (stripos($contentType, 'application/json') !== false && !empty($rawInput)) {
            $decoded = json_decode($rawInput, true);
            if (is_array($decoded)) {
                $parsedInput = $decoded;
            }
        }

        $ids = $parsedInput['ids'] ?? [];
        if (!is_array($ids)) {
            $ids = [$ids];
        }
        $ids = array_values(array_filter(array_map('intval', $ids), static fn (int $id): bool => $id > 0));

        $operation = strtolower(trim((string)($parsedInput['batch_action'] ?? $parsedInput['operation'] ?? '')));
        $notificacaoModel = new Notificacao($pdo);
        $userId = (int)($_SESSION['user_id'] ?? 0);
        $updated = 0;

        if (empty($ids)) {
            $response = ['status' => 'error', 'message' => 'Selecione ao menos uma notificação.'];
        } else {
            if ($operation === 'mark_resolved') {
                $updated = $notificacaoModel->marcarListaComoResolvida($ids, $userId);
            } elseif ($operation === 'mark_read') {
                $updated = $notificacaoModel->marcarListaComoLida($ids, $userId);
            }

            if ($updated > 0) {
                $response = ['status' => 'success', 'updated' => $updated];
                $_SESSION['success_message'] = sprintf('%d notificações atualizadas.', $updated);
            } else {
                $response = ['status' => 'error', 'message' => 'Nenhuma notificação pôde ser atualizada.'];
                $_SESSION['error_message'] = $response['message'];
            }
        }

        $wantsJson = stripos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false
            || stripos($contentType, 'application/json') !== false;

        if ($wantsJson) {
            header('Content-Type: application/json');
            echo json_encode($response);
            exit;
        }

        header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? APP_URL . '/notificacoes.php'));
        exit;
    case 'aprovar':
        // Mantemos a lógica de aprovação, caso ainda seja usada
        $controller->aprovarOrcamento();
        break;
    case 'recusar':
        // Mantemos a lógica de recusa, caso ainda seja usada
        $controller->recusarOrcamento();
        break;
    default:
        $controller->painelNotificacoes();
        break;
}