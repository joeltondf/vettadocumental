<?php

declare(strict_types=1);

class AddPrioridadeToNotificacoesMigration
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
                'notificacoes',
                'prioridade',
                "VARCHAR(16) NOT NULL DEFAULT 'media' AFTER resolvido"
            );
            $this->addIndexIfNotExists(
                'notificacoes',
                'idx_notificacoes_prioridade',
                'INDEX idx_notificacoes_prioridade (prioridade)'
            );

            $this->pdo->exec(<<<SQL
                UPDATE notificacoes
                SET prioridade = CASE
                    WHEN tipo_alerta = 'processo_pendente_orcamento' THEN 'alta'
                    WHEN tipo_alerta = 'processo_orcamento_recusado' THEN 'alta'
                    WHEN tipo_alerta = 'processo_cancelado' THEN 'alta'
                    WHEN tipo_alerta = 'processo_servico_pendente' THEN 'media'
                    ELSE prioridade
                END
            SQL);

            $this->pdo->commit();
        } catch (Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }
    }

    private function addColumnIfNotExists(string $table, string $column, string $definition): void
    {
        if ($this->isSqlite()) {
            $statement = $this->pdo->query("PRAGMA table_info({$table})");
            $columns = $statement->fetchAll(PDO::FETCH_COLUMN, 1);
            if (!in_array($column, $columns, true)) {
                $this->pdo->exec("ALTER TABLE {$table} ADD COLUMN {$column} VARCHAR(16) NOT NULL DEFAULT 'media'");
            }

            return;
        }

        $stmt = $this->pdo->prepare("SHOW COLUMNS FROM {$table} LIKE :column");
        $stmt->execute(['column' => $column]);

        if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
            $this->pdo->exec("ALTER TABLE {$table} ADD COLUMN {$column} {$definition}");
        }
    }

    private function addIndexIfNotExists(string $table, string $indexName, string $definition): void
    {
        if ($this->isSqlite()) {
            return;
        }

        $stmt = $this->pdo->prepare("SHOW INDEX FROM {$table} WHERE Key_name = :indexName");
        $stmt->execute(['indexName' => $indexName]);

        if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
            $this->pdo->exec("ALTER TABLE {$table} ADD {$definition}");
        }
    }

    private function isSqlite(): bool
    {
        try {
            return $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';
        } catch (Throwable $exception) {
            return false;
        }
    }
}
