<?php
require_once __DIR__ . '/User.php';
require_once __DIR__ . '/Configuracao.php';

class Vendedor
{
    private $pdo;
    private $userModel;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
        $this->userModel = new User($pdo);
    }

    public function getAll()
    {
        // CORREÇÃO APLICADA AQUI
        $sql = "SELECT 
                    v.id, 
                    v.user_id,
                    v.percentual_comissao,
                    u.nome_completo AS nome_vendedor,
                    u.email,
                    u.ativo
                FROM vendedores AS v
                JOIN users AS u ON v.user_id = u.id
                ORDER BY u.nome_completo ASC";
        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getById($vendedor_id)
    {
        // CORREÇÃO APLICADA AQUI
        $sql = "SELECT 
                    v.id,
                    v.user_id,
                    v.percentual_comissao,
                    u.nome_completo,
                    u.email,
                    u.ativo
                FROM vendedores v
                JOIN users u ON v.user_id = u.id
                WHERE v.id = :vendedor_id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['vendedor_id' => $vendedor_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public function getByUserId($user_id)
    {
        $stmt = $this->pdo->prepare("SELECT * FROM vendedores WHERE user_id = :user_id");
        $stmt->execute(['user_id' => $user_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function create($data)
    {
        $this->pdo->beginTransaction();

        try {
            $ativo = isset($data['ativo']) ? (int) $data['ativo'] : 1;
            $ativo = $ativo === 1 ? 1 : 0;

            $userId = $this->userModel->create(
                $data['nome_completo'],
                $data['email'],
                $data['senha'],
                'vendedor',
                $ativo
            );

            if (!$userId) {
                $_SESSION['error_message'] = $_SESSION['error_message'] ?? "Não foi possível criar o registro de usuário.";
                $this->pdo->rollBack();
                return false;
            }

            // CORREÇÃO APLICADA AQUI
            $sql = "INSERT INTO vendedores (user_id, percentual_comissao, data_contratacao, ativo) 
                    VALUES (:user_id, :percentual_comissao, :data_contratacao, :ativo)";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':user_id' => $userId,
                ':percentual_comissao' => $data['percentual_comissao'] ?? 0,
                ':data_contratacao' => !empty($data['data_contratacao']) ? $data['data_contratacao'] : null,
                ':ativo' => $ativo
            ]);

            $this->pdo->commit();
            return true;

        } catch (PDOException $e) {
            $this->pdo->rollBack();
            if ($e->getCode() == '23000') {
                 $_SESSION['error_message'] = "Erro: O e-mail informado já está em uso.";
            } else {
                 $_SESSION['error_message'] = "Erro de banco de dados: " . $e->getMessage();
            }
            return false;
        }
    }

    public function update($vendedor_id, $data)
{
    $this->pdo->beginTransaction();
    try {
        $vendedor = $this->getById($vendedor_id);
        if (!$vendedor) {
            $_SESSION['error_message'] = "Vendedor não encontrado para atualização.";
            $this->pdo->rollBack();
            return false;
        }
        $userId = $vendedor['user_id'];
        
        $userData = [
            'nome_completo' => $data['nome_completo'],
            'email'         => $data['email'],
            'perfil'        => 'vendedor',
            'ativo'         => $data['ativo'] ?? 1
        ];
        $this->userModel->update($userId, $userData);

        if (!empty($data['senha'])) {
            $this->userModel->updatePassword($userId, $data['senha']);
        }
        
        // ===== INÍCIO DA CORREÇÃO =====
        // Adicionamos o campo `ativo` à query de UPDATE da tabela `vendedores`
        $sql = "UPDATE vendedores SET
                    percentual_comissao = :percentual_comissao,
                    data_contratacao = :data_contratacao,
                    ativo = :ativo
                WHERE id = :vendedor_id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':percentual_comissao' => $data['percentual_comissao'] ?? 0,
            ':data_contratacao' => !empty($data['data_contratacao']) ? $data['data_contratacao'] : null,
            ':ativo' => $data['ativo'] ?? 1, // Adiciona o valor de 'ativo' aqui
            ':vendedor_id' => $vendedor_id
        ]);
        // ===== FIM DA CORREÇÃO =====

        $this->pdo->commit();
        return true;
    } catch (Exception $e) {
        $this->pdo->rollBack();
        $_SESSION['error_message'] = "Erro ao atualizar: " . $e->getMessage();
        return false;
    }
}


    public function delete($vendedor_id)
    {
        $this->pdo->beginTransaction();

        try {
            $vendedor = $this->getById($vendedor_id);
            if (!$vendedor) {
                $this->pdo->rollBack();
                return false;
            }

            $configuracao = new Configuracao($this->pdo);
            $defaultVendorId = (int)($configuracao->get('default_vendedor_id') ?? 0);

            if ($defaultVendorId <= 0) {
                throw new RuntimeException('Vendedor padrão não configurado.');
            }

            if ((int)$vendedor_id === $defaultVendorId) {
                throw new RuntimeException('Não é possível excluir o vendedor padrão do sistema.');
            }

            $defaultVendorUserId = $this->getUserIdByVendedorId($defaultVendorId);
            if ($defaultVendorUserId === null) {
                throw new RuntimeException('Configuração de vendedor padrão inválida.');
            }

            $vendorUserId = (int)$vendedor['user_id'];
            $this->reassignVendorOwnership(
                (int)$vendedor_id,
                $vendorUserId,
                $defaultVendorId,
                $defaultVendorUserId
            );

            $stmt = $this->pdo->prepare('DELETE FROM vendedores WHERE id = :id');
            $stmt->execute([':id' => $vendedor_id]);

            $this->userModel->delete($vendorUserId);

            $this->pdo->commit();
            return true;
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            $_SESSION['error_message'] = $_SESSION['error_message'] ?? 'Erro ao remover o vendedor: ' . $e->getMessage();
            return false;
        }
    }

    private function reassignVendorOwnership(int $vendorId, int $vendorUserId, int $defaultVendorId, int $defaultVendorUserId): void
    {
        if ($vendorId === $defaultVendorId) {
            return;
        }

        $tables = [
            'processos' => [
                'vendor_columns' => ['vendedor_id'],
                'responsible_columns' => ['responsavel_id'],
            ],
            'orcamentos' => [
                'vendor_columns' => ['vendedor_id'],
                'responsible_columns' => ['responsavel_id'],
            ],
            'distribuicao_leads' => [
                'vendor_columns' => ['vendedor_id', 'vendedorId'],
                'responsible_columns' => ['responsavel_id', 'responsavelId'],
            ],
            'prospeccoes' => [
                'vendor_columns' => ['vendedor_id', 'vendedorId'],
                'responsible_columns' => ['responsavel_id', 'responsavelId'],
            ],
        ];

        foreach ($tables as $table => $columns) {
            foreach ($columns['vendor_columns'] as $column) {
                $this->updateColumnIfExists($table, $column, $vendorId, $defaultVendorId);
            }

            foreach ($columns['responsible_columns'] as $column) {
                $this->updateColumnIfExists($table, $column, $vendorUserId, $defaultVendorUserId);
            }
        }
    }

    private function updateColumnIfExists(string $table, string $column, int $oldValue, int $newValue): void
    {
        if ($oldValue === $newValue) {
            return;
        }

        if (!$this->columnExists($table, $column)) {
            return;
        }

        $sql = sprintf('UPDATE `%s` SET `%s` = :newValue WHERE `%s` = :oldValue', $table, $column, $column);
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':newValue', $newValue, PDO::PARAM_INT);
        $stmt->bindValue(':oldValue', $oldValue, PDO::PARAM_INT);
        $stmt->execute();
    }

    private function columnExists(string $table, string $column): bool
    {
        if (!$this->tableExists($table)) {
            return false;
        }

        $sql = sprintf('SHOW COLUMNS FROM `%s` LIKE :column', $table);
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':column' => $column]);

        return (bool)$stmt->fetch(PDO::FETCH_ASSOC);
    }

    private function tableExists(string $table): bool
    {
        $stmt = $this->pdo->prepare('SHOW TABLES LIKE :table');
        $stmt->execute([':table' => $table]);

        return (bool)$stmt->fetch(PDO::FETCH_NUM);
    }

        /**
     * Busca o ID de usuário (da tabela `users`) com base no ID do vendedor (da tabela `vendedores`).
     * Essencial para encontrar para quem enviar a notificação.
     *
     * @param int $vendedor_id O ID da tabela `vendedores`.
     * @return int|null O ID correspondente da tabela `users`.
     */
    public function getUserIdByVendedorId(?int $vendedor_id): ?int
    {
        if ($vendedor_id === null) {
            return null;
        }

        $sql = "SELECT user_id FROM vendedores WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$vendedor_id]);
        $result = $stmt->fetchColumn();

        return $result ? (int)$result : null;
    }
}
