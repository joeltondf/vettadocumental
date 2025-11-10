<?php

declare(strict_types=1);

class AddDeadlinePauseColumnsToProcessosMigration
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
            $this->addColumnIfNotExists(
                'processos',
                'prazo_pausado_em',
                "DATETIME NULL DEFAULT NULL AFTER data_previsao_entrega"
            );

            $this->addColumnIfNotExists(
                'processos',
                'prazo_dias_restantes',
                "INT NULL DEFAULT NULL AFTER prazo_pausado_em"
            );

            $this->pdo->commit();
        } catch (Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }
    }

    private function addColumnIfNotExists(string $table, string $column, string $definition): void
    {
        $stmt = $this->pdo->prepare("SHOW COLUMNS FROM {$table} LIKE :column");
        $stmt->execute(['column' => $column]);

        if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
            $this->pdo->exec("ALTER TABLE {$table} ADD COLUMN {$column} {$definition}");
        }
    }
}
