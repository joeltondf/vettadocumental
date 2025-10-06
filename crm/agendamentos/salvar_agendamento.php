<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../app/core/auth_check.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . APP_URL . '/crm/dashboard.php');
    exit();
}

$redirectTo = isset($_POST['redirect_to']) && $_POST['redirect_to'] !== '' ? $_POST['redirect_to'] : null;

function normalizeDateTime(?string $value): ?string
{
    if ($value === null) {
        return null;
    }

    $value = trim($value);

    if ($value === '') {
        return null;
    }

    $formats = [
        'Y-m-d H:i:s',
        'Y-m-d H:i',
        'Y-m-d\TH:i',
        'Y-m-d\TH:i:s'
    ];

    foreach ($formats as $format) {
        $date = DateTime::createFromFormat($format, $value);
        if ($date instanceof DateTime) {
            return $date->format('Y-m-d H:i:s');
        }
    }

    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        return $value . ' 00:00:00';
    }

    return null;
}

function respondWithError(string $message, ?string $redirectTo): void
{
    if ($redirectTo) {
        $_SESSION['error_message'] = $message;
        header('Location: ' . $redirectTo);
    } else {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => $message]);
    }

    exit();
}

function respondWithSuccess(?string $redirectTo): void
{
    if ($redirectTo) {
        $_SESSION['success_message'] = 'Agendamento criado com sucesso!';
        header('Location: ' . $redirectTo);
    } else {
        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
    }

    exit();
}

try {
    $titulo = trim($_POST['titulo'] ?? '');
    if ($titulo === '') {
        throw new InvalidArgumentException('Informe o título do agendamento.');
    }

    $usuarioId = isset($_POST['usuario_id']) && $_POST['usuario_id'] !== ''
        ? (int) $_POST['usuario_id']
        : (int) ($_SESSION['user_id'] ?? 0);

    if ($usuarioId <= 0) {
        throw new InvalidArgumentException('Usuário responsável inválido.');
    }

    $clienteId = isset($_POST['cliente_id']) && $_POST['cliente_id'] !== ''
        ? (int) $_POST['cliente_id']
        : null;

    $prospeccaoId = isset($_POST['prospeccao_id']) && $_POST['prospeccao_id'] !== ''
        ? (int) $_POST['prospeccao_id']
        : null;

    $dataInicio = normalizeDateTime($_POST['data_inicio'] ?? null);
    $dataFim = normalizeDateTime($_POST['data_fim'] ?? null);

    if (!$dataInicio && !empty($_POST['data_dia']) && !empty($_POST['data_inicio_hora'])) {
        $dataInicio = normalizeDateTime($_POST['data_dia'] . ' ' . $_POST['data_inicio_hora']);
    }

    if (!$dataFim && !empty($_POST['data_dia']) && !empty($_POST['data_fim_hora'])) {
        $dataFim = normalizeDateTime($_POST['data_dia'] . ' ' . $_POST['data_fim_hora']);
    }

    if (!$dataInicio || !$dataFim) {
        throw new InvalidArgumentException('Informe data e hora válidas.');
    }

    $inicioDateTime = new DateTime($dataInicio);
    $fimDateTime = new DateTime($dataFim);

    if ($fimDateTime <= $inicioDateTime) {
        throw new InvalidArgumentException('O horário final deve ser posterior ao início.');
    }

    if ($prospeccaoId && !$clienteId) {
        $stmtProspeccao = $pdo->prepare('SELECT cliente_id FROM prospeccoes WHERE id = :id');
        $stmtProspeccao->bindValue(':id', $prospeccaoId, PDO::PARAM_INT);
        $stmtProspeccao->execute();
        $clienteIdFromProspeccao = $stmtProspeccao->fetchColumn();

        if ($clienteIdFromProspeccao) {
            $clienteId = (int) $clienteIdFromProspeccao;
        }
    }

    $localLink = trim($_POST['local_link'] ?? '');
    $observacoes = trim($_POST['observacoes'] ?? '');
    $status = trim($_POST['status'] ?? 'Confirmado');
    if ($status === '') {
        $status = 'Confirmado';
    }

    $sql = 'INSERT INTO agendamentos (
                titulo,
                cliente_id,
                prospeccao_id,
                usuario_id,
                data_inicio,
                data_fim,
                local_link,
                observacoes,
                status
            ) VALUES (
                :titulo,
                :cliente_id,
                :prospeccao_id,
                :usuario_id,
                :data_inicio,
                :data_fim,
                :local_link,
                :observacoes,
                :status
            )';

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':titulo', $titulo, PDO::PARAM_STR);
    if ($clienteId !== null) {
        $stmt->bindValue(':cliente_id', $clienteId, PDO::PARAM_INT);
    } else {
        $stmt->bindValue(':cliente_id', null, PDO::PARAM_NULL);
    }
    if ($prospeccaoId !== null) {
        $stmt->bindValue(':prospeccao_id', $prospeccaoId, PDO::PARAM_INT);
    } else {
        $stmt->bindValue(':prospeccao_id', null, PDO::PARAM_NULL);
    }
    $stmt->bindValue(':usuario_id', $usuarioId, PDO::PARAM_INT);
    $stmt->bindValue(':data_inicio', $dataInicio);
    $stmt->bindValue(':data_fim', $dataFim);
    if ($localLink !== '') {
        $stmt->bindValue(':local_link', $localLink, PDO::PARAM_STR);
    } else {
        $stmt->bindValue(':local_link', null, PDO::PARAM_NULL);
    }
    if ($observacoes !== '') {
        $stmt->bindValue(':observacoes', $observacoes, PDO::PARAM_STR);
    } else {
        $stmt->bindValue(':observacoes', null, PDO::PARAM_NULL);
    }
    $stmt->bindValue(':status', $status, PDO::PARAM_STR);

    $stmt->execute();

    respondWithSuccess($redirectTo);
} catch (InvalidArgumentException $exception) {
    respondWithError($exception->getMessage(), $redirectTo);
} catch (PDOException $exception) {
    error_log('Erro em salvar_agendamento.php: ' . $exception->getMessage());
    respondWithError('Ocorreu um erro interno ao salvar o agendamento.', $redirectTo);
}
