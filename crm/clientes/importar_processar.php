<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../app/core/auth_check.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . APP_URL . '/crm/clientes/lista.php');
    exit();
}

$currentUserId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
$currentUserPerfil = $_SESSION['user_perfil'] ?? '';

if (!$currentUserId) {
    $_SESSION['error_message'] = 'Sessão expirada. Faça login novamente.';
    header('Location: ' . APP_URL . '/login.php');
    exit();
}

if (!isset($_FILES['csv_file']) || !is_uploaded_file($_FILES['csv_file']['tmp_name'])) {
    $_SESSION['error_message'] = 'Nenhum arquivo foi enviado para importação.';
    header('Location: ' . APP_URL . '/crm/clientes/importar.php');
    exit();
}

$channelOptions = [
    'Call',
    'LinkedIn',
    'Instagram',
    'Whatsapp',
    'Indicação Cliente',
    'Indicação Cartório',
    'Website',
    'Bitrix',
    'Evento',
    'Outro'
];

$categoryOptions = [
    'Entrada',
    'Qualificado',
    'Com Orçamento',
    'Em Negociação',
    'Cliente Ativo',
    'Sem Interesse'
];

$defaultChannel = $_POST['default_channel'] ?? 'Outro';
if (!in_array($defaultChannel, $channelOptions, true)) {
    $defaultChannel = 'Outro';
}

$defaultCategory = $_POST['default_category'] ?? 'Entrada';
if (!in_array($defaultCategory, $categoryOptions, true)) {
    $defaultCategory = 'Entrada';
}

$delimiter = $_POST['delimiter'] ?? ';';
$allowedDelimiters = [';', ',', '\t'];
if (!in_array($delimiter, $allowedDelimiters, true)) {
    $delimiter = ';';
}

$hasHeader = isset($_POST['has_header']);

$assignedOwnerId = null;
if ($currentUserPerfil === 'vendedor') {
    $assignedOwnerId = $currentUserId;
} else {
    $assignedOwnerId = isset($_POST['assigned_owner']) && $_POST['assigned_owner'] !== ''
        ? (int) $_POST['assigned_owner']
        : null;

    if ($assignedOwnerId) {
        $stmtOwner = $pdo->prepare("SELECT id FROM users WHERE id = :id AND perfil = 'vendedor'");
        $stmtOwner->execute([':id' => $assignedOwnerId]);
        if (!$stmtOwner->fetchColumn()) {
            $assignedOwnerId = null;
        }
    }
}

$filePath = $_FILES['csv_file']['tmp_name'];
$handle = fopen($filePath, 'r');

if (!$handle) {
    $_SESSION['error_message'] = 'Não foi possível abrir o arquivo enviado.';
    header('Location: ' . APP_URL . '/crm/clientes/importar.php');
    exit();
}

$createdCount = 0;
$skippedCount = 0;
$duplicateCount = 0;
$errorRows = [];
$rowNumber = 0;

$pdo->beginTransaction();

try {
    if ($hasHeader) {
        fgetcsv($handle, 0, $delimiter);
    }

    $insertSql = "INSERT INTO clientes (nome_cliente, nome_responsavel, email, telefone, canal_origem, categoria, is_prospect, crmOwnerId)
                  VALUES (:nome_cliente, :nome_responsavel, :email, :telefone, :canal_origem, :categoria, 1, :crm_owner_id)";
    $insertStmt = $pdo->prepare($insertSql);

    $duplicateSql = "SELECT id FROM clientes
                     WHERE is_prospect = 1 AND (
                         (:email <> '' AND email = :email) OR
                         (:telefone <> '' AND REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(telefone, '(', ''), ')', ''), '-', ''), ' ', ''), '.', ''), '+', '') = :telefone) OR
                         (:nome_cliente <> '' AND LOWER(nome_cliente) = LOWER(:nome_cliente))
                     )
                     LIMIT 1";
    $duplicateStmt = $pdo->prepare($duplicateSql);

    while (($data = fgetcsv($handle, 0, $delimiter)) !== false) {
        $rowNumber++;

        $nomeCliente = trim($data[0] ?? '');
        $nomeResponsavel = trim($data[1] ?? '');
        $emailRaw = trim($data[2] ?? '');
        $telefoneRaw = trim($data[3] ?? '');

        if ($nomeCliente === '' && $emailRaw === '' && $telefoneRaw === '') {
            $skippedCount++;
            continue;
        }

        if ($nomeCliente === '') {
            $skippedCount++;
            $errorRows[] = "Linha {$rowNumber}: Nome do Lead é obrigatório.";
            continue;
        }

        $email = filter_var($emailRaw, FILTER_VALIDATE_EMAIL) ? $emailRaw : '';
        $telefoneDigits = preg_replace('/\D+/', '', $telefoneRaw);

        $duplicateStmt->execute([
            ':email' => $email,
            ':telefone' => $telefoneDigits,
            ':nome_cliente' => $nomeCliente,
        ]);

        if ($duplicateStmt->fetchColumn()) {
            $duplicateCount++;
            continue;
        }

        $insertStmt->execute([
            ':nome_cliente' => $nomeCliente,
            ':nome_responsavel' => $nomeResponsavel !== '' ? $nomeResponsavel : null,
            ':email' => $email !== '' ? $email : null,
            ':telefone' => $telefoneRaw !== '' ? $telefoneRaw : null,
            ':canal_origem' => $defaultChannel,
            ':categoria' => $defaultCategory,
            ':crm_owner_id' => $assignedOwnerId,
        ]);

        $createdCount++;
    }

    $pdo->commit();
} catch (Throwable $exception) {
    $pdo->rollBack();
    fclose($handle);

    error_log('Erro na importação de leads: ' . $exception->getMessage());
    $_SESSION['error_message'] = 'Não foi possível concluir a importação. Tente novamente.';
    header('Location: ' . APP_URL . '/crm/clientes/importar.php');
    exit();
}

fclose($handle);

$_SESSION['import_summary'] = [
    'created' => $createdCount,
    'skipped' => $skippedCount,
    'duplicates' => $duplicateCount,
    'errors' => array_slice($errorRows, 0, 5),
];

if ($createdCount > 0) {
    $_SESSION['success_message'] = "Importação concluída: {$createdCount} lead(s) criado(s).";
} elseif ($duplicateCount > 0) {
    $_SESSION['success_message'] = 'Importação concluída sem novos leads. Todos já existiam.';
} elseif (!empty($errorRows) || $skippedCount > 0) {
    $_SESSION['error_message'] = 'Nenhum lead foi importado. Verifique o arquivo e tente novamente.';
}

header('Location: ' . APP_URL . '/crm/clientes/lista.php');
exit();
?>
