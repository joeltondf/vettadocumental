<?php

declare(strict_types=1);

class AddUltimoSeqItemOsToProcessosMigration
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function up(): void
    {
        $this->pdo->beginTransaction();

        try {
            $this->addUltimoSeqColumn();
            $this->pdo->commit();
        } catch (Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }
    }

    private function addUltimoSeqColumn(): void
    {
        $driver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'sqlite') {
            $stmt = $this->pdo->query("PRAGMA table_info('processos')");
            $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($columns as $column) {
                if (strcasecmp($column['name'] ?? '', 'ultimo_seq_item_os') === 0) {
                    return;
                }
            }

            $this->pdo->exec("ALTER TABLE processos ADD COLUMN ultimo_seq_item_os INTEGER NULL DEFAULT 0");
            return;
        }

        $stmt = $this->pdo->prepare('SHOW COLUMNS FROM processos LIKE :column');
        $stmt->execute(['column' => 'ultimo_seq_item_os']);

        if ($stmt->fetch(PDO::FETCH_ASSOC)) {
            return;
        }

        $this->pdo->exec('ALTER TABLE processos ADD COLUMN ultimo_seq_item_os INT UNSIGNED NULL DEFAULT 0 AFTER codigo_os_omie');
    }
}
