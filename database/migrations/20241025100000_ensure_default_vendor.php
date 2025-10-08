<?php

declare(strict_types=1);

final class EnsureDefaultVendorMigration
{
    private const COMPANY_NAME = 'Empresa';
    private const COMPANY_EMAIL = 'empresa@empresa.com';

    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function up(): void
    {
        $this->pdo->beginTransaction();

        try {
            $defaultVendorId = $this->getConfiguredVendorId();

            if ($defaultVendorId === null || !$this->vendorExists($defaultVendorId)) {
                $defaultVendorId = $this->ensureCompanyVendor();
                $this->saveDefaultVendorId($defaultVendorId);
            }

            if ($defaultVendorId === null) {
                throw new RuntimeException('Não foi possível definir um vendedor padrão para os processos.');
            }

            $this->assignVendorToTable('processos', $defaultVendorId);
            $this->assignVendorToTable('servicos', $defaultVendorId);
            $this->assignVendorToTable('orcamentos', $defaultVendorId);

            $this->pdo->commit();
        } catch (Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }
    }

    private function getConfiguredVendorId(): ?int
    {
        if (!$this->tableExists('configuracoes')) {
            return null;
        }

        $stmt = $this->pdo->prepare('SELECT valor FROM configuracoes WHERE chave = :chave LIMIT 1');
        $stmt->execute([':chave' => 'default_vendedor_id']);
        $value = $stmt->fetch(PDO::FETCH_COLUMN);

        return $value !== false && $value !== null && $value !== '' ? (int)$value : null;
    }

    private function vendorExists(int $vendorId): bool
    {
        if (!$this->tableExists('vendedores')) {
            return false;
        }

        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM vendedores WHERE id = :id');
        $stmt->execute([':id' => $vendorId]);

        return (int)$stmt->fetch(PDO::FETCH_COLUMN) > 0;
    }

    private function ensureCompanyVendor(): int
    {
        if (!$this->tableExists('users') || !$this->tableExists('vendedores')) {
            throw new RuntimeException('As tabelas necessárias para criar o vendedor padrão não estão disponíveis.');
        }

        $existingVendorId = $this->findVendorByEmail(self::COMPANY_EMAIL);
        if ($existingVendorId !== null) {
            return $existingVendorId;
        }

        $userId = $this->ensureCompanyUser();
        $vendorId = $this->insertVendor($userId);

        return $vendorId;
    }

    private function findVendorByEmail(string $email): ?int
    {
        $sql = 'SELECT v.id
                FROM vendedores AS v
                JOIN users AS u ON v.user_id = u.id
                WHERE u.email = :email
                LIMIT 1';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':email' => $email]);
        $result = $stmt->fetch(PDO::FETCH_COLUMN);

        return $result !== false ? (int)$result : null;
    }

    private function ensureCompanyUser(): int
    {
        $stmt = $this->pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
        $stmt->execute([':email' => self::COMPANY_EMAIL]);
        $existingId = $stmt->fetch(PDO::FETCH_COLUMN);

        if ($existingId !== false) {
            return (int)$existingId;
        }

        $columns = $this->getTableColumns('users');
        if (empty($columns)) {
            throw new RuntimeException('Não foi possível determinar as colunas da tabela users.');
        }

        $placeholders = [
            ':nome' => self::COMPANY_NAME,
            ':email' => self::COMPANY_EMAIL,
            ':senha' => password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT),
            ':perfil' => 'vendedor',
        ];

        if (in_array('ativo', $columns, true)) {
            $sql = 'INSERT INTO users (nome_completo, email, senha, perfil, ativo)
                    VALUES (:nome, :email, :senha, :perfil, :ativo)';
            $placeholders[':ativo'] = 1;
        } else {
            $sql = 'INSERT INTO users (nome_completo, email, senha, perfil)
                    VALUES (:nome, :email, :senha, :perfil)';
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($placeholders);

        return (int)$this->pdo->lastInsertId();
    }

    private function insertVendor(int $userId): int
    {
        $columns = $this->getTableColumns('vendedores');
        if (empty($columns)) {
            throw new RuntimeException('Não foi possível determinar as colunas da tabela vendedores.');
        }

        $fields = ['user_id'];
        $params = [':user_id' => $userId];

        if (in_array('percentual_comissao', $columns, true)) {
            $fields[] = 'percentual_comissao';
            $params[':percentual_comissao'] = 0;
        }

        if (in_array('data_contratacao', $columns, true)) {
            $fields[] = 'data_contratacao';
            $params[':data_contratacao'] = date('Y-m-d');
        }

        if (in_array('ativo', $columns, true)) {
            $fields[] = 'ativo';
            $params[':ativo'] = 1;
        }

        $placeholders = implode(', ', array_keys($params));
        $sql = sprintf(
            'INSERT INTO vendedores (%s) VALUES (%s)',
            implode(', ', $fields),
            $placeholders
        );

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return (int)$this->pdo->lastInsertId();
    }

    private function saveDefaultVendorId(int $vendorId): void
    {
        if (!$this->tableExists('configuracoes')) {
            throw new RuntimeException('A tabela configuracoes é necessária para salvar o vendedor padrão.');
        }

        $driver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $sql = 'INSERT INTO configuracoes (chave, valor)
                    VALUES (:chave, :valor)
                    ON CONFLICT(chave) DO UPDATE SET valor = excluded.valor';
        } else {
            $sql = 'INSERT INTO configuracoes (chave, valor)
                    VALUES (:chave, :valor)
                    ON DUPLICATE KEY UPDATE valor = VALUES(valor)';
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':chave' => 'default_vendedor_id',
            ':valor' => (string)$vendorId,
        ]);
    }

    private function assignVendorToTable(string $table, int $vendorId): void
    {
        if (!$this->tableExists($table) || !$this->columnExists($table, 'vendedor_id')) {
            return;
        }

        $sql = sprintf('UPDATE %s SET vendedor_id = :vendor WHERE vendedor_id IS NULL', $table);
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':vendor' => $vendorId]);
    }

    private function tableExists(string $table): bool
    {
        $driver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'sqlite') {
            $stmt = $this->pdo->prepare("SELECT name FROM sqlite_master WHERE type = 'table' AND name = :table");
            $stmt->execute([':table' => $table]);
            return $stmt->fetch(PDO::FETCH_ASSOC) !== false;
        }

        $stmt = $this->pdo->prepare('SHOW TABLES LIKE :table');
        $stmt->execute([':table' => $table]);

        return $stmt->fetch(PDO::FETCH_ASSOC) !== false;
    }

    private function columnExists(string $table, string $column): bool
    {
        $columns = $this->getTableColumns($table);
        return in_array($column, $columns, true);
    }

    private function getTableColumns(string $table): array
    {
        $driver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        try {
            if ($driver === 'sqlite') {
                $stmt = $this->pdo->prepare("PRAGMA table_info('" . $table . "')");
                $stmt->execute();
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                return array_map(static fn(array $row): string => $row['name'], $rows);
            }

            $stmt = $this->pdo->prepare('SHOW COLUMNS FROM ' . $table);
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return array_map(static fn(array $row): string => $row['Field'], $rows);
        } catch (Throwable $exception) {
            return [];
        }
    }
}
