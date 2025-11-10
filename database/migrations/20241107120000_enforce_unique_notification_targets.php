<?php

declare(strict_types=1);

class EnforceUniqueNotificationTargetsMigration
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
            $this->removeDuplicateNotifications();
            $this->dropLegacyUniqueIndex();
            $this->addCompositeUniqueIndex();

            $this->pdo->commit();
        } catch (Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }
    }

    private function removeDuplicateNotifications(): void
    {
        if ($this->isSqlite()) {
            $this->pdo->exec(<<<SQL
                DELETE FROM notificacoes
                WHERE id NOT IN (
                    SELECT MAX(id) FROM notificacoes
                    GROUP BY usuario_id, tipo_alerta, referencia_id, grupo_destino
                )
            SQL);

            return;
        }

        $this->pdo->exec(<<<SQL
            DELETE n1
            FROM notificacoes n1
            JOIN notificacoes n2
              ON n1.usuario_id = n2.usuario_id
             AND n1.tipo_alerta = n2.tipo_alerta
             AND ((n1.referencia_id IS NULL AND n2.referencia_id IS NULL) OR n1.referencia_id = n2.referencia_id)
             AND n1.grupo_destino = n2.grupo_destino
             AND n1.id < n2.id
        SQL);
    }

    private function dropLegacyUniqueIndex(): void
    {
        if ($this->isSqlite()) {
            $this->pdo->exec('DROP INDEX IF EXISTS uniq_notificacoes_evento');

            return;
        }

        $stmt = $this->pdo->prepare("SHOW INDEX FROM notificacoes WHERE Key_name = :name");
        $stmt->execute(['name' => 'uniq_notificacoes_evento']);

        if ($stmt->fetch(PDO::FETCH_ASSOC)) {
            $this->pdo->exec('ALTER TABLE notificacoes DROP INDEX uniq_notificacoes_evento');
        }
    }

    private function addCompositeUniqueIndex(): void
    {
        if ($this->isSqlite()) {
            $this->pdo->exec(
                'CREATE UNIQUE INDEX IF NOT EXISTS uniq_notificacoes_usuario_alerta '
                . 'ON notificacoes (usuario_id, tipo_alerta, referencia_id, grupo_destino)'
            );

            return;
        }

        $stmt = $this->pdo->prepare("SHOW INDEX FROM notificacoes WHERE Key_name = :name");
        $stmt->execute(['name' => 'uniq_notificacoes_usuario_alerta']);

        if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
            $this->pdo->exec(
                'ALTER TABLE notificacoes ADD UNIQUE INDEX '
                . 'uniq_notificacoes_usuario_alerta (usuario_id, tipo_alerta, referencia_id, grupo_destino)'
            );
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
