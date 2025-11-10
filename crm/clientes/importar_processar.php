<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../app/core/auth_check.php';
require_once __DIR__ . '/../../app/utils/PhoneUtils.php';
require_once __DIR__ . '/../../app/utils/DatabaseSchemaInspector.php';

function insertImportedProspects(PDO $pdo, array $rows, ?int $assignedOwnerId): int
{
    if (empty($rows)) {
        return 0;
    }

    $hasPhoneDDI = DatabaseSchemaInspector::hasColumn($pdo, 'clientes', 'telefone_ddi');
    $hasPhoneDDD = DatabaseSchemaInspector::hasColumn($pdo, 'clientes', 'telefone_ddd');
    $hasPhoneNumero = DatabaseSchemaInspector::hasColumn($pdo, 'clientes', 'telefone_numero');

    $columns = [
        'nome_cliente',
        'nome_responsavel',
        'email',
        'telefone',
        'canal_origem',
        'categoria',
        'is_prospect',
        'crmOwnerId',
    ];

    $placeholders = [
        ':nome_cliente',
        ':nome_responsavel',
        ':email',
        ':telefone',
        ':canal_origem',
        ':categoria',
        '1',
        ':crm_owner_id',
    ];

    if ($hasPhoneDDI) {
        $columns[] = 'telefone_ddi';
        $placeholders[] = ':telefone_ddi';
    }

    if ($hasPhoneDDD) {
        $columns[] = 'telefone_ddd';
        $placeholders[] = ':telefone_ddd';
    }

    if ($hasPhoneNumero) {
        $columns[] = 'telefone_numero';
        $placeholders[] = ':telefone_numero';
    }

    $insertSql = sprintf(
        'INSERT INTO clientes (%s) VALUES (%s)',
        implode(', ', $columns),
        implode(', ', $placeholders)
    );

    $insertStmt = $pdo->prepare($insertSql);
    $created = 0;

    $pdo->beginTransaction();

    try {
        foreach ($rows as $row) {
            $telefoneLegacy = null;
            if (!empty($row['phone_combinado'])) {
                $telefoneLegacy = $row['phone_combinado'];
            } elseif (!empty($row['phone_raw'])) {
                $telefoneLegacy = $row['phone_raw'];
            }

            $params = [
                ':nome_cliente' => $row['company_name'],
                ':nome_responsavel' => $row['contact_name'] !== '' ? $row['contact_name'] : null,
                ':email' => $row['email'] !== '' ? $row['email'] : null,
                ':telefone' => $telefoneLegacy,
                ':canal_origem' => $row['channel'],
                ':categoria' => 'Entrada',
                ':crm_owner_id' => $assignedOwnerId,
            ];

            if ($hasPhoneDDI) {
                $params[':telefone_ddi'] = $row['phone_ddi'] ?? null;
            }

            if ($hasPhoneDDD) {
                $params[':telefone_ddd'] = $row['phone_ddd'] ?? null;
            }

            if ($hasPhoneNumero) {
                $params[':telefone_numero'] = $row['phone_numero'] ?? null;
            }

            $insertStmt->execute($params);
            $created++;
        }

        $pdo->commit();
    } catch (Throwable $exception) {
        $pdo->rollBack();
        throw $exception;
    }

    return $created;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . APP_URL . '/crm/clientes/lista.php');
    exit();
}

$currentUserId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
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

$defaultChannel = $_POST['default_channel'] ?? 'Outro';
if (!in_array($defaultChannel, $channelOptions, true)) {
    $defaultChannel = 'Outro';
}

$delimiter = $_POST['delimiter'] ?? ';';
$allowedDelimiters = [';', ',', "\t"];
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

    if (!$assignedOwnerId) {
        $_SESSION['error_message'] = 'Selecione o vendedor responsável pelos leads importados.';
        header('Location: ' . APP_URL . '/crm/clientes/importar.php');
        exit();
    }

    $stmtOwner = $pdo->prepare("SELECT id FROM users WHERE id = :id AND perfil = 'vendedor' AND (ativo = 1 OR ativo IS NULL)");
    $stmtOwner->execute([':id' => $assignedOwnerId]);

    if (!$stmtOwner->fetchColumn()) {
        $_SESSION['error_message'] = 'Vendedor inválido selecionado. Tente novamente.';
        header('Location: ' . APP_URL . '/crm/clientes/importar.php');
        exit();
    }
}

$filePath = $_FILES['csv_file']['tmp_name'];
$handle = fopen($filePath, 'r');

if (!$handle) {
    $_SESSION['error_message'] = 'Não foi possível abrir o arquivo enviado.';
    header('Location: ' . APP_URL . '/crm/clientes/importar.php');
    exit();
}

$skippedCount = 0;
$errorRows = [];
$rowNumber = 0;
$rows = [];
$duplicates = [];

try {
    if ($hasHeader) {
        fgetcsv($handle, 0, $delimiter);
    }

    $phoneColumns = ['telefone'];
    $phoneConditions = [
        "(:telefone <> '' AND REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(telefone, '(', ''), ')', ''), '-', ''), ' ', ''), '.', ''), '+', '') = :telefone)"
    ];

    $hasPhoneDDI = DatabaseSchemaInspector::hasColumn($pdo, 'clientes', 'telefone_ddi');
    $hasPhoneDDD = DatabaseSchemaInspector::hasColumn($pdo, 'clientes', 'telefone_ddd');
    $hasPhoneNumero = DatabaseSchemaInspector::hasColumn($pdo, 'clientes', 'telefone_numero');

    if ($hasPhoneDDI) {
        $phoneColumns[] = 'telefone_ddi';
    }

    if ($hasPhoneDDD) {
        $phoneColumns[] = 'telefone_ddd';
    }

    if ($hasPhoneNumero) {
        $phoneColumns[] = 'telefone_numero';
    }

    if ($hasPhoneDDD && $hasPhoneNumero) {
        $phoneConditions[] = "(:telefone_numero <> '' AND telefone_numero = :telefone_numero AND telefone_ddd = :telefone_ddd)";
    }

    $duplicateSql = sprintf(
        "SELECT id, nome_cliente, email, %s FROM clientes WHERE is_prospect = 1 AND ((:email <> '' AND email = :email) OR %s OR (:nome_cliente <> '' AND LOWER(nome_cliente) = LOWER(:nome_cliente))) LIMIT 1",
        implode(', ', $phoneColumns),
        implode(' OR ', $phoneConditions)
    );

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

        $telefoneData = [
            'raw' => $telefoneRaw,
            'ddi' => '55',
            'ddd' => null,
            'numero' => null,
            'combinado' => '',
            'valido' => false,
            'erro' => null,
        ];

        if ($telefoneRaw !== '') {
            try {
                $digits = stripNonDigits($telefoneRaw);

                $ddiDetectado = '55';
                if (strlen($digits) > 11) {
                    if (strncmp($digits, '55', 2) === 0 && strlen($digits) > 12) {
                        $ddiDetectado = '55';
                        $digits = substr($digits, 2);
                    } elseif (strlen($digits) >= 13) {
                        for ($ddiLen = 3; $ddiLen >= 1; $ddiLen--) {
                            $possibleDDI = substr($digits, 0, $ddiLen);
                            $remainingDigits = substr($digits, $ddiLen);

                            if (strlen($remainingDigits) >= 10 && strlen($remainingDigits) <= 13) {
                                $ddiDetectado = $possibleDDI;
                                $digits = $remainingDigits;
                                break;
                            }
                        }
                    }
                }

                $parts = extractPhoneParts($digits);

                $telefoneData['ddi'] = $ddiDetectado;
                $telefoneData['ddd'] = $parts['ddd'];
                $telefoneData['numero'] = $parts['phone'];

                if ($parts['ddd'] !== null && $parts['phone'] !== null) {
                    $telefoneData['combinado'] = $ddiDetectado . $parts['ddd'] . $parts['phone'];
                    $telefoneData['valido'] = true;
                }
            } catch (InvalidArgumentException $e) {
                $telefoneData['erro'] = $e->getMessage();
                $telefoneData['combinado'] = stripNonDigits($telefoneRaw);
            }
        }

        $telefoneDigits = $telefoneData['combinado'] !== '' ? $telefoneData['combinado'] : stripNonDigits($telefoneRaw);

        $duplicateParams = [
            ':email' => $email,
            ':telefone' => $telefoneDigits,
            ':nome_cliente' => $nomeCliente,
        ];

        if ($hasPhoneDDD && $hasPhoneNumero) {
            $duplicateParams[':telefone_numero'] = $telefoneData['numero'] ?? '';
            $duplicateParams[':telefone_ddd'] = $telefoneData['ddd'] ?? '';
        }

        $duplicateStmt->execute($duplicateParams);

        $existingLead = $duplicateStmt->fetch(PDO::FETCH_ASSOC) ?: null;

        $rowData = [
            'row_index' => $rowNumber,
            'company_name' => $nomeCliente,
            'contact_name' => $nomeResponsavel,
            'email' => $email,
            'phone_raw' => $telefoneRaw,
            'phone_ddi' => $telefoneData['ddi'],
            'phone_ddd' => $telefoneData['ddd'],
            'phone_numero' => $telefoneData['numero'],
            'phone_combinado' => $telefoneData['combinado'],
            'phone_valido' => $telefoneData['valido'],
            'phone_erro' => $telefoneData['erro'],
            'channel' => $defaultChannel,
            'duplicate' => $existingLead ? [
                'id' => (int) ($existingLead['id'] ?? 0),
                'name' => $existingLead['nome_cliente'] ?? '',
                'email' => $existingLead['email'] ?? '',
                'phone' => $existingLead['telefone'] ?? '',
                'phone_ddi' => $existingLead['telefone_ddi'] ?? null,
                'phone_ddd' => $existingLead['telefone_ddd'] ?? null,
                'phone_numero' => $existingLead['telefone_numero'] ?? null,
            ] : null,
        ];

        $rows[] = $rowData;

        if ($existingLead) {
            $duplicates[] = $rowData;
        }
    }
} finally {
    fclose($handle);
}

$duplicateCount = count($duplicates);
$validRows = array_filter($rows, static fn(array $row) => $row['duplicate'] === null);

if ($duplicateCount === 0) {
    try {
        $createdCount = insertImportedProspects($pdo, array_values($validRows), $assignedOwnerId);
    } catch (Throwable $exception) {
        error_log('Erro na importação de leads: ' . $exception->getMessage());
        $_SESSION['error_message'] = 'Não foi possível concluir a importação. Tente novamente.';
        header('Location: ' . APP_URL . '/crm/clientes/importar.php');
        exit();
    }

    $_SESSION['import_summary'] = [
        'created' => $createdCount,
        'skipped' => $skippedCount,
        'duplicates' => 0,
        'discarded' => 0,
        'errors' => array_slice($errorRows, 0, 5),
    ];

    if ($createdCount > 0) {
        $_SESSION['success_message'] = "Importação concluída: {$createdCount} lead(s) criado(s).";
    } elseif (!empty($errorRows) || $skippedCount > 0) {
        $_SESSION['error_message'] = 'Nenhum lead foi importado. Verifique o arquivo e tente novamente.';
    }

    header('Location: ' . APP_URL . '/crm/clientes/lista.php');
    exit();
}

$token = bin2hex(random_bytes(16));

$_SESSION['lead_import_batches'][$token] = [
    'rows' => $rows,
    'assigned_owner_id' => $assignedOwnerId,
    'default_channel' => $defaultChannel,
    'skipped' => $skippedCount,
    'errors' => array_slice($errorRows, 0, 5),
    'uploader_id' => $currentUserId,
    'created_at' => time(),
];

header('Location: ' . APP_URL . '/crm/clientes/importar_revisao.php?token=' . urlencode($token));
exit();
