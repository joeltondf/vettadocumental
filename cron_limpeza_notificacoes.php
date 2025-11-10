<?php
if (!isset($pdo)) {
    require_once __DIR__ . '/config.php';
}

if (!isset($pdo)) {
    throw new RuntimeException('Conexão com o banco de dados não encontrada.');
}

$defaultDays = 15;
$retentionDays = (int)($_ENV['NOTIFICATION_ARCHIVE_DAYS'] ?? getenv('NOTIFICATION_ARCHIVE_DAYS') ?? $defaultDays);
$retentionDays = $retentionDays > 0 ? $retentionDays : $defaultDays;

$driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
$isSqlite = $driver === 'sqlite';

$pendingStatuses = [
    'Orçamento',
    'Orçamento Pendente',
    'Serviço Pendente',
    'Serviço pendente',
    'Serviço em Andamento',
    'Serviço em andamento',
    'Pendente de pagamento',
    'Pendente de documentos',
];

if ($isSqlite) {
    $updateOld = $pdo->prepare(
        "UPDATE notificacoes SET resolvido = 1, lida = 1 "
        . "WHERE resolvido = 0 AND datetime(data_criacao) < datetime('now', '-' || :days || ' days')"
    );
    $updateOld->bindValue(':days', $retentionDays, PDO::PARAM_INT);
    $updateOld->execute();
    $updatedByDate = $updateOld->rowCount();

    if (!empty($pendingStatuses)) {
        $placeholders = implode(', ', array_fill(0, count($pendingStatuses), '?'));
        $updateStatus = $pdo->prepare(
            "UPDATE notificacoes SET resolvido = 1, lida = 1 "
            . "WHERE resolvido = 0 AND referencia_id IN ("
            . "SELECT id FROM processos WHERE status_processo IS NOT NULL AND status_processo NOT IN ({$placeholders})"
            . ")"
        );
        foreach ($pendingStatuses as $index => $status) {
            $updateStatus->bindValue($index + 1, $status, PDO::PARAM_STR);
        }
        $updateStatus->execute();
        $updatedByStatus = $updateStatus->rowCount();
    } else {
        $updatedByStatus = 0;
    }

    $totalUpdated = $updatedByDate + $updatedByStatus;
} else {
    $statusPlaceholders = implode(', ', array_fill(0, count($pendingStatuses), '?'));
    $sql = <<<SQL
        UPDATE notificacoes AS n
        LEFT JOIN processos AS p ON p.id = n.referencia_id
        SET n.resolvido = 1, n.lida = 1
        WHERE n.resolvido = 0
          AND (
            n.data_criacao < DATE_SUB(NOW(), INTERVAL :days DAY)
            OR (
                p.status_processo IS NOT NULL
                AND p.status_processo NOT IN ({$statusPlaceholders})
            )
          )
    SQL;

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':days', $retentionDays, PDO::PARAM_INT);
    foreach ($pendingStatuses as $index => $status) {
        $stmt->bindValue($index + 1, $status, PDO::PARAM_STR);
    }
    $stmt->execute();
    $totalUpdated = $stmt->rowCount();
}

echo sprintf("%d notificações arquivadas automaticamente (limite %d dias).\n", $totalUpdated, $retentionDays);
