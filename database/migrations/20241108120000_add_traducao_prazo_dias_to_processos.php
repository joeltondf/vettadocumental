<?php

declare(strict_types=1);

class AddTraducaoPrazoDiasToProcessosMigration
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
                'traducao_prazo_dias',
                'INT NULL DEFAULT NULL AFTER prazo_dias'
            );

            $this->synchronizeExistingDeadlines();

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

    private function synchronizeExistingDeadlines(): void
    {
        $sql = "UPDATE processos
                SET traducao_prazo_dias = prazo_dias
                WHERE prazo_dias IS NOT NULL";
        $this->pdo->exec($sql);
    }
}
