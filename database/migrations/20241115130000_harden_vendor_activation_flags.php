<?php

declare(strict_types=1);

class HardenVendorActivationFlagsMigration
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
            $this->ensureActiveDefaults('users');
            $this->ensureActiveDefaults('vendedores');
            $this->synchronizeVendorProfiles();
            $this->retirePlaceholderVendor();

            $this->pdo->commit();
        } catch (Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }
    }

    private function ensureActiveDefaults(string $table): void
    {
        $stmt = $this->pdo->prepare("SHOW COLUMNS FROM {$table} LIKE 'ativo'");
        $stmt->execute();

        $column = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$column) {
            return;
        }

        $currentType = strtolower((string) ($column['Type'] ?? ''));
        $expectedDefinition = 'tinyint(1)';

        if (strpos($currentType, $expectedDefinition) === false || stripos((string) ($column['Default'] ?? ''), '1') === false) {
            $this->pdo->exec("ALTER TABLE {$table} MODIFY ativo TINYINT(1) NULL DEFAULT 1");
        }
    }

    private function synchronizeVendorProfiles(): void
    {
        $sql = "UPDATE users u
                INNER JOIN vendedores v ON v.user_id = u.id
                SET u.perfil = 'vendedor'
                WHERE u.perfil <> 'vendedor' OR u.perfil IS NULL";
        $this->pdo->exec($sql);
    }

    private function retirePlaceholderVendor(): void
    {
        $this->pdo->exec("DELETE FROM vendedores WHERE user_id = 17 OR id = 17");

        $stmt = $this->pdo->prepare('UPDATE users SET ativo = 0 WHERE id = :id');
        $stmt->execute(['id' => 17]);

        $this->pdo->exec('DELETE FROM lead_distribution_queue WHERE vendor_id = 17');
    }
}
