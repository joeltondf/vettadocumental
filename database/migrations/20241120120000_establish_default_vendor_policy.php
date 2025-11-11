<?php

declare(strict_types=1);

class EstablishDefaultVendorPolicyMigration
{
    private PDO $pdo;
    private const DEFAULT_VENDOR_EMAIL = 'sistema@vettadocumental.com';
    private const DEFAULT_VENDOR_NAME = 'Sistema';

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function up(): void
    {
        $this->pdo->beginTransaction();

        try {
            $vendorInfo = $this->ensureDefaultVendor();
            $this->ensureConfigurationValue($vendorInfo['vendor_id']);
            $this->backfillOrphanedRecords($vendorInfo['vendor_id'], $vendorInfo['user_id']);

            $this->pdo->commit();
        } catch (Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }
    }

    private function ensureDefaultVendor(): array
    {
        $configuredVendor = $this->getConfiguredVendor();
        if ($configuredVendor !== null) {
            return $configuredVendor;
        }

        $user = $this->ensureDefaultUserExists();
        $vendorId = $this->ensureVendorRecordExists($user['id']);

        return [
            'vendor_id' => $vendorId,
            'user_id' => $user['id'],
        ];
    }

    private function getConfiguredVendor(): ?array
    {
        if (!$this->tableExists('configuracoes')) {
            return null;
        }

        $stmt = $this->pdo->prepare("SELECT valor FROM configuracoes WHERE chave = 'default_vendedor_id' LIMIT 1");
        $stmt->execute();

        $value = $stmt->fetchColumn();
        if ($value === false || $value === null || $value === '') {
            return null;
        }

        $vendorId = (int) $value;
        if ($vendorId <= 0) {
            return null;
        }

        $stmt = $this->pdo->prepare('SELECT id, user_id FROM vendedores WHERE id = :id LIMIT 1');
        $stmt->bindValue(':id', $vendorId, PDO::PARAM_INT);
        $stmt->execute();

        $vendor = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$vendor || empty($vendor['user_id'])) {
            return null;
        }

        $userId = (int) $vendor['user_id'];
        if ($userId <= 0) {
            return null;
        }

        return [
            'vendor_id' => $vendorId,
            'user_id' => $userId,
        ];
    }

    private function ensureDefaultUserExists(): array
    {
        if (!$this->tableExists('users')) {
            throw new RuntimeException('Tabela de usuários não encontrada para criar o vendedor padrão.');
        }

        $stmt = $this->pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
        $stmt->bindValue(':email', self::DEFAULT_VENDOR_EMAIL, PDO::PARAM_STR);
        $stmt->execute();

        $userId = $stmt->fetchColumn();
        if ($userId !== false) {
            $id = (int) $userId;
            $update = $this->pdo->prepare('UPDATE users SET nome_completo = :nome, perfil = "vendedor", ativo = 1 WHERE id = :id');
            $update->execute([
                ':nome' => self::DEFAULT_VENDOR_NAME,
                ':id' => $id,
            ]);

            return ['id' => $id];
        }

        $password = password_hash(bin2hex(random_bytes(16)), PASSWORD_BCRYPT);

        $insert = $this->pdo->prepare('INSERT INTO users (nome_completo, email, senha, perfil, ativo)
            VALUES (:nome, :email, :senha, "vendedor", 1)');
        $insert->execute([
            ':nome' => self::DEFAULT_VENDOR_NAME,
            ':email' => self::DEFAULT_VENDOR_EMAIL,
            ':senha' => $password,
        ]);

        return ['id' => (int) $this->pdo->lastInsertId()];
    }

    private function ensureVendorRecordExists(int $userId): int
    {
        if (!$this->tableExists('vendedores')) {
            throw new RuntimeException('Tabela de vendedores não encontrada para criar o vendedor padrão.');
        }

        $stmt = $this->pdo->prepare('SELECT id FROM vendedores WHERE user_id = :userId LIMIT 1');
        $stmt->bindValue(':userId', $userId, PDO::PARAM_INT);
        $stmt->execute();

        $vendorId = $stmt->fetchColumn();
        if ($vendorId !== false) {
            $this->pdo->prepare('UPDATE vendedores SET ativo = 1 WHERE id = :id')->execute([':id' => (int) $vendorId]);
            return (int) $vendorId;
        }

        $insert = $this->pdo->prepare('INSERT INTO vendedores (user_id, percentual_comissao, ativo)
            VALUES (:userId, 0, 1)');
        $insert->execute([':userId' => $userId]);

        return (int) $this->pdo->lastInsertId();
    }

    private function ensureConfigurationValue(int $vendorId): void
    {
        if (!$this->tableExists('configuracoes')) {
            return;
        }

        $stmt = $this->pdo->prepare('INSERT INTO configuracoes (chave, valor)
                VALUES ("default_vendedor_id", :valor)
                ON DUPLICATE KEY UPDATE valor = VALUES(valor)');
        $stmt->execute([':valor' => (string) $vendorId]);
    }

    private function backfillOrphanedRecords(int $vendorId, int $userId): void
    {
        $this->updateColumnIfExists('processos', 'vendedor_id', $vendorId);
        $this->updateColumnIfExists('orcamentos', 'vendedor_id', $vendorId);
        $this->updateColumnIfExists('prospeccoes', 'responsavel_id', $userId);
        $this->updateColumnIfExists('clientes', 'crmOwnerId', $userId);
        $this->updateColumnIfExists('comissoes', 'usuario_id', $userId);
    }

    private function updateColumnIfExists(string $table, string $column, int $value): void
    {
        if (!$this->tableHasColumn($table, $column)) {
            return;
        }

        $sql = "UPDATE {$table} SET {$column} = :value WHERE {$column} IS NULL OR {$column} = 0";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':value' => $value]);
    }

    private function tableExists(string $table): bool
    {
        $stmt = $this->pdo->prepare('SHOW TABLES LIKE :table');
        $stmt->execute([':table' => $table]);

        return (bool) $stmt->fetchColumn();
    }

    private function tableHasColumn(string $table, string $column): bool
    {
        if (!$this->tableExists($table)) {
            return false;
        }

        $stmt = $this->pdo->prepare("SHOW COLUMNS FROM {$table} LIKE :column");
        $stmt->execute([':column' => $column]);

        return (bool) $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
