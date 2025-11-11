<?php
require_once __DIR__ . '/User.php';
require_once __DIR__ . '/Processo.php';

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
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $defaultVendorId = Processo::getDefaultVendorId($this->pdo);
        if ($defaultVendorId !== null) {
            foreach ($rows as &$row) {
                if ((int) ($row['id'] ?? 0) === $defaultVendorId) {
                    $row['nome_vendedor'] = 'Sistema';
                }
            }
            unset($row);
        }

        return $rows;
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
        $vendor = $stmt->fetch(PDO::FETCH_ASSOC);

        $defaultVendorId = Processo::getDefaultVendorId($this->pdo);
        if ($vendor && $defaultVendorId !== null && (int) ($vendor['id'] ?? 0) === $defaultVendorId) {
            $vendor['nome_vendedor'] = 'Sistema';
        }

        return $vendor;
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

    public function delete($vendedor_id)
    {
        $this->pdo->beginTransaction();
        try {
            $vendedor = $this->getById($vendedor_id);
            if (!$vendedor) {
                $this->pdo->rollBack();
                return false;
            }

            $defaultVendorId = Processo::getDefaultVendorId($this->pdo);

            if ($defaultVendorId === null) {
                throw new RuntimeException('Vendedor padrão não configurado.');
            }

            if ((int) $vendedor_id === (int) $defaultVendorId) {
                throw new RuntimeException('Não é possível excluir o vendedor padrão.');
            }

            $defaultVendor = $this->getById($defaultVendorId);
            if (!$defaultVendor || empty($defaultVendor['user_id'])) {
                throw new RuntimeException('Usuário do vendedor padrão não encontrado.');
            }

            $defaultVendorUserId = (int) $defaultVendor['user_id'];
            $deletedVendorUserId = (int) ($vendedor['user_id'] ?? 0);

            $updateMap = [
                ['table' => 'processos', 'column' => 'vendedor_id', 'target' => $defaultVendorId, 'match' => (int) $vendedor_id],
                ['table' => 'prospeccoes', 'column' => 'responsavel_id', 'target' => $defaultVendorUserId, 'match' => $deletedVendorUserId],
                ['table' => 'orcamentos', 'column' => 'vendedor_id', 'target' => $defaultVendorId, 'match' => (int) $vendedor_id],
                ['table' => 'clientes', 'column' => 'crmOwnerId', 'target' => $defaultVendorUserId, 'match' => $deletedVendorUserId],
                ['table' => 'comissoes', 'column' => 'usuario_id', 'target' => $defaultVendorUserId, 'match' => $deletedVendorUserId],
            ];

            foreach ($updateMap as $entry) {
                $table = $entry['table'];
                $column = $entry['column'];
                $targetValue = $entry['target'];
                $matchValue = $entry['match'];

                if (!$this->tableHasColumn($table, $column)) {
                    continue;
                }

                $sql = "UPDATE {$table} SET {$column} = :defaultVendor WHERE {$column} = :vendor";
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute([
                    ':defaultVendor' => $targetValue,
                    ':vendor' => $matchValue,
                ]);
            }

            $stmt = $this->pdo->prepare('DELETE FROM vendedores WHERE id = :id');
            $stmt->execute([':id' => $vendedor_id]);

            $this->userModel->delete($vendedor['user_id']);

            $this->pdo->commit();
            return true;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            $_SESSION['error_message'] = $_SESSION['error_message'] ?? $e->getMessage();
            return false;
        }
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
