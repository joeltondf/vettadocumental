<?php

declare(strict_types=1);

class AddOsCodeToProcessosMigration
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
            $this->addCodigoOsColumn();
            $this->pdo->commit();
        } catch (Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }
    }

    private function addCodigoOsColumn(): void
    {
        $driver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'sqlite') {
            $stmt = $this->pdo->query("PRAGMA table_info('processos')");
            $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($columns as $column) {
                if (strcasecmp($column['name'] ?? '', 'codigo_os_omie') === 0) {
                    return;
                }
            }

            $this->pdo->exec("ALTER TABLE processos ADD COLUMN codigo_os_omie INTEGER NULL");
            return;
        }

        $stmt = $this->pdo->prepare('SHOW COLUMNS FROM processos LIKE :column');
        $stmt->execute(['column' => 'codigo_os_omie']);

        if ($stmt->fetch(PDO::FETCH_ASSOC)) {
            return;
        }

        $this->pdo->exec('ALTER TABLE processos ADD COLUMN codigo_os_omie BIGINT UNSIGNED NULL AFTER os_numero_omie');
    }
}
