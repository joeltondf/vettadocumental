<?php
/**
 * @file /app/models/Cliente.php
 * @description Model responsável pela gestão dos dados dos clientes na base de dados.
 * Inclui operações de Criar, Ler, Atualizar e Deletar (CRUD).
 */

class Cliente
{
    private $pdo;

    /**
     * Construtor da classe Cliente.
     *
     * @param PDO $pdo Uma instância do objeto PDO para a conexão com a base de dados.
     */
    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    // =======================================================================
    // MÉTODOS CRUD (Create, Read, Update, Delete)
    // =======================================================================

    /**
     * Cria um novo cliente na base de dados.
     * Realiza a verificação para evitar CPF/CNPJ duplicado.
     *
     * @param array $data Dados do cliente a serem inseridos.
     * @return int|string Retorna o ID do novo cliente em caso de sucesso,
     * ou uma string de erro 'error_duplicate_cpf_cnpj' se o CPF/CNPJ já existir.
     */

        public function getAppClients()
    {
        $stmt = $this->pdo->query("SELECT * FROM clientes WHERE is_prospect = 0 ORDER BY nome_cliente");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
public function update($id, $data)
    {
        $this->pdo->beginTransaction();
    
        try {
            $clienteAtual = $this->getById($id);
            if (!$clienteAtual) {
                throw new Exception("Cliente com ID {$id} não encontrado.");
            }
    
            $userId = $clienteAtual['user_id'];
            require_once __DIR__ . '/User.php';
            $userModel = new User($this->pdo);
    
            if (!empty($data['criar_login']) && empty($userId)) {
                if (empty($data['login_email']) || empty($data['login_senha'])) {
                    throw new Exception("O e-mail e a senha são obrigatórios para criar o acesso de login.");
                }
                if ($userModel->getByEmail($data['login_email'])) {
                    throw new Exception("O e-mail '{$data['login_email']}' já está em uso.");
                }
                $userId = $userModel->create($data['nome_cliente'], $data['login_email'], $data['login_senha'], 'cliente');
                if (!$userId) {
                    throw new Exception("Falha ao criar o registro de usuário.");
                }
            } 
            elseif (!empty($userId)) {
                $userDataToUpdate = [
                    'nome_completo' => $data['user_nome_completo'],
                    'email'         => $data['user_email'],
                    'perfil'        => 'cliente',
                    'ativo'         => 1
                ];
                $userModel->update($userId, $userDataToUpdate);
    
                if (!empty($data['user_nova_senha'])) {
                    $userModel->updatePassword($userId, $data['user_nova_senha']);
                }
            }
    
            $cpf_cnpj = empty($data['cpf_cnpj']) ? null : $data['cpf_cnpj'];
            if ($cpf_cnpj !== null) {
                $stmt = $this->pdo->prepare("SELECT id FROM clientes WHERE cpf_cnpj = ? AND id != ?");
                $stmt->execute([$cpf_cnpj, $id]);
                if ($stmt->fetch()) {
                    throw new Exception("error_duplicate_cpf_cnpj");
                }
            }
            
            $sql = "UPDATE clientes SET 
                        nome_cliente = ?, nome_responsavel = ?, cpf_cnpj = ?, 
                        email = ?, telefone = ?, endereco = ?, cep = ?, 
                        tipo_pessoa = ?, tipo_assessoria = ?, user_id = ? 
                    WHERE id = ?";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                $data['nome_cliente'], $data['nome_responsavel'] ?? null, $cpf_cnpj,
                $data['email'] ?? null, $data['telefone'] ?? null, $data['endereco'] ?? null,
                $data['cep'] ?? null, $data['tipo_pessoa'] ?? 'Jurídica', // Salva o tipo de pessoa
                $data['tipo_assessoria'] ?? null, $userId, $id
            ]);
            
            $this->pdo->commit();
            return true;
    
        } catch (Exception $e) {
            $this->pdo->rollBack();
            if ($e->getMessage() === 'error_duplicate_cpf_cnpj') {
                return 'error_duplicate_cpf_cnpj';
            }
            error_log("Erro ao atualizar cliente: " . $e->getMessage());
            $_SESSION['error_message'] = "Ocorreu um erro: " . $e->getMessage();
            return false;
        }
    }



    /**
     * Busca um cliente específico pelo seu ID.
     *
     * @param int $id O ID do cliente a ser buscado.
     * @return array|false Retorna um array associativo com os dados do cliente ou 'false' se não for encontrado.
     */
    public function getById($id)
    {
        // Usamos LEFT JOIN para que clientes sem usuário também sejam retornados.
        $sql = "SELECT 
                    c.*, 
                    u.nome_completo as user_nome_completo, 
                    u.email as user_email
                FROM clientes AS c
                LEFT JOIN users AS u ON c.user_id = u.id
                WHERE c.id = ?";
                
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }


    /**
     * Busca todos os clientes cadastrados, ordenados por nome.
     *
     * @return array Retorna um array de arrays associativos com todos os clientes.
     */
    public function getAll()
    {
        $stmt = $this->pdo->query("SELECT * FROM clientes ORDER BY nome_cliente");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Atualiza os dados de um cliente existente.
     * Realiza a verificação para garantir que o novo CPF/CNPJ não pertença a outro cliente.
     *
     * @param int $id O ID do cliente a ser atualizado.
     * @param array $data Os novos dados do cliente.
     * @return bool|string Retorna 'true' em caso de sucesso, 'false' em caso de falha na execução,
     * ou a string 'error_duplicate_cpf_cnpj' se o CPF/CNPJ já pertencer a outro registo.
     */


    /**
     * Deleta um cliente da base de dados.
     * A operação só é permitida se o cliente não estiver associado a nenhum processo.
     *
     * @param int $id O ID do cliente a ser deletado.
     * @return bool Retorna 'true' se a deleção for bem-sucedida, 'false' se houver falha
     * ou se o cliente estiver vinculado a processos.
     */
    public function delete($id)
    {
        // Verifica se o cliente está associado a algum processo para evitar exclusão indevida.
        $checkStmt = $this->pdo->prepare("SELECT COUNT(*) FROM processos WHERE cliente_id = ?");
        $checkStmt->execute([$id]);
        if ($checkStmt->fetchColumn() > 0) {
            // Impede a exclusão e retorna 'false' para indicar que a operação não foi permitida.
            return false;
        }
        
        // Se não houver processos, prossegue com a exclusão.
        $stmt = $this->pdo->prepare("DELETE FROM clientes WHERE id = ?");
        return $stmt->execute([$id]);
    }
    public function getByUserId($userId) {
    $stmt = $this->pdo->prepare("SELECT * FROM clientes WHERE user_id = ?");
    $stmt->execute([$userId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Vincula um cliente local a um ID da Conta Azul.
     * @param int $localClientId
     * @param string $contaAzulUuid
     * @return bool
     */
    public function linkContaAzulUuid(int $localClientId, string $contaAzulUuid): bool
    {
        $sql = "UPDATE clientes SET conta_azul_uuid = :ca_uuid WHERE id = :id";
        try {
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute(['ca_uuid' => $contaAzulUuid, 'id' => $localClientId]);
        } catch (PDOException $e) {
            error_log("Erro ao vincular cliente {$localClientId} com Conta Azul UUID: " . $e->getMessage());
            return false;
        }
    }
    /**
     * Cria um novo cliente na base de dados.
     * Realiza a verificação para evitar CPF/CNPJ duplicado e cria um login de usuário se solicitado.
     *
     * @param array $data Dados do cliente vindos do formulário.
     * @return int|string Retorna o ID do novo cliente em caso de sucesso,
     * ou uma string de erro específica se a validação falhar.
     */
    public function create($data)
    {
        $this->pdo->beginTransaction();

        try {
            $cpf_cnpj = empty($data['cpf_cnpj']) ? null : $data['cpf_cnpj'];
            if ($cpf_cnpj !== null) {
                $stmt = $this->pdo->prepare("SELECT id FROM clientes WHERE cpf_cnpj = ?");
                $stmt->execute([$cpf_cnpj]);
                if ($stmt->fetch()) {
                    throw new Exception("error_duplicate_cpf_cnpj");
                }
            }

            $userId = null;
            if (!empty($data['criar_login']) && !empty($data['login_email']) && !empty($data['login_senha'])) {
                require_once __DIR__ . '/User.php';
                $userModel = new User($this->pdo);

                if ($userModel->getByEmail($data['login_email'])) {
                    throw new Exception("O e-mail '{$data['login_email']}' já está em uso.");
                }
                
                $userId = $userModel->create($data['nome_cliente'], $data['login_email'], $data['login_senha'], 'cliente');
                if (!$userId) {
                    throw new Exception("Falha ao criar o registro de usuário.");
                }
            }

            // --- CORREÇÃO AQUI ---
            $sql = "INSERT INTO clientes 
                        (nome_cliente, nome_responsavel, cpf_cnpj, email, telefone, endereco, cep, tipo_pessoa, tipo_assessoria, user_id, conta_azul_uuid, is_prospect) 
                    VALUES 
                        (:nome_cliente, :nome_responsavel, :cpf_cnpj, :email, :telefone, :endereco, :cep, :tipo_pessoa, :tipo_assessoria, :user_id, :conta_azul_uuid, :is_prospect)";
            
            $stmt = $this->pdo->prepare($sql);
            
            $stmt->execute([
                ':nome_cliente' => $data['nome_cliente'],
                ':nome_responsavel' => $data['nome_responsavel'] ?? null,
                ':cpf_cnpj' => $cpf_cnpj,
                ':email' => $data['email'] ?? null,
                ':telefone' => $data['telefone'] ?? null,
                ':endereco' => $data['endereco'] ?? null,
                ':cep' => $data['cep'] ?? null,
                ':tipo_pessoa' => $data['tipo_pessoa'] ?? 'Jurídica',
                ':tipo_assessoria' => $data['tipo_assessoria'] ?? null,
                ':user_id' => $userId,
                ':conta_azul_uuid' => null, // Assumindo que conta_azul_uuid não vem do form de criação inicial
                ':is_prospect' => 0 // Define como cliente normal, e não prospecção
            ]);

            $newClientId = $this->pdo->lastInsertId();
            $this->pdo->commit();
            return $newClientId;

        } catch (Exception $e) {
            $this->pdo->rollBack();
            if ($e->getMessage() === 'error_duplicate_cpf_cnpj') {
                return 'error_duplicate_cpf_cnpj';
            }
            
            error_log("Erro ao criar cliente: " . $e->getMessage());
            if (session_status() == PHP_SESSION_NONE) { session_start(); }
            $_SESSION['error_message'] = "Ocorreu um erro ao criar o cliente: " . $e->getMessage();
            return false;
        }
    }   
    public function getPdo() {
        return $this->pdo;
    }

        /**
     * Busca apenas os clientes que são prospecções (para o CRM).
     */
    public function getCrmProspects()
    {
        $stmt = $this->pdo->prepare("SELECT * FROM clientes WHERE is_prospect = 1 ORDER BY nome_cliente ASC");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
 * Busca clientes FINAIS (não prospecções) pelo termo de pesquisa.
 * Usado na busca do dashboard principal.
 */
public function searchAppClients($searchTerm)
{
    // A query busca em vários campos, mas APENAS onde is_prospect = 0
    $sql = "SELECT * FROM clientes 
            WHERE 
                (nome_cliente LIKE :term OR nome_responsavel LIKE :term OR cpf_cnpj LIKE :term OR email LIKE :term) 
                AND is_prospect = 0 
            ORDER BY nome_cliente ASC";
    
    $stmt = $this->pdo->prepare($sql);
    $stmt->execute([':term' => '%' . $searchTerm . '%']);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}



}