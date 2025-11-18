<?php

class VendaLog
{
    private $pdo;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    public function getByVendaIds(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $sql = "SELECT * FROM vendas_logs WHERE venda_id IN ($placeholders) ORDER BY data_operacao DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($ids);

        $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $grouped = [];
        foreach ($logs as $log) {
            $grouped[$log['venda_id']][] = $log;
        }

        return $grouped;
    }
}
