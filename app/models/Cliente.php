<?php
/**
 * @file /app/models/Cliente.php
 * @description Model responsável pela gestão dos dados dos clientes na base de dados.
 * Inclui operações de Criar, Ler, Atualizar e Deletar (CRUD).
 */

require_once __DIR__ . '/../utils/DatabaseSchemaInspector.php';

class Cliente
{
    private $pdo;
    private ?bool $integrationCodeColumnAvailable = null;
    private ?bool $conversionDateColumnAvailable = null;

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
     * Atualiza os dados de um cliente existente.
     * Realiza a verificação para evitar CPF/CNPJ duplicado durante a edição.
     *
     * @param int $id Identificador do cliente a ser atualizado.
     * @param array $data Dados do cliente enviados pelo formulário.
     * @return bool|string Retorna true em caso de sucesso, false em falhas gerais ou
     *                     'error_duplicate_cpf_cnpj' quando CPF/CNPJ já estiver em uso.
     */
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
                        email = ?, telefone = ?, endereco = ?, numero = ?, bairro = ?, cidade = ?, estado = ?, cep = ?, 
                        tipo_pessoa = ?, tipo_assessoria = ?, user_id = ? 
                    WHERE id = ?";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                $data['nome_cliente'], $data['nome_responsavel'] ?? null, $cpf_cnpj,
                $data['email'] ?? null, $data['telefone'] ?? null, 
                $data['endereco'] ?? null, $data['numero'] ?? null, $data['bairro'] ?? null, 
                $data['cidade'] ?? null, $data['estado'] ?? null,
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
     * Marca um prospect como cliente, atualizando o campo is_prospect e, quando disponível,
     * a data de conversão.
     */
    public function promoteProspectToClient(int $clienteId): bool
    {
        if ($clienteId <= 0) {
            return false;
        }

        $cliente = $this->getById($clienteId);
        if (!$cliente) {
            return false;
        }

        $needsConversion = (int)($cliente['is_prospect'] ?? 1) === 1;
        $shouldStampConversionDate = $this->hasConversionDateColumn();

        if (!$needsConversion && !$shouldStampConversionDate) {
            return true;
        }

        $columnsToUpdate = [];
        $params = [':id' => $clienteId];

        if ($needsConversion) {
            $columnsToUpdate['is_prospect'] = 0;
        }

        if ($shouldStampConversionDate) {
            $columnsToUpdate['data_conversao'] = date('Y-m-d H:i:s');
        }

        if (empty($columnsToUpdate)) {
            return true;
        }

        $setParts = [];
        foreach ($columnsToUpdate as $column => $value) {
            $setParts[] = "$column = :$column";
            $params[":$column"] = $value;
        }

        $sql = 'UPDATE clientes SET ' . implode(', ', $setParts) . ' WHERE id = :id';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        if ($needsConversion) {
            return $stmt->rowCount() > 0;
        }

        return true;
    }


    /**
     * Busca clientes cadastrados, ordenados por nome.
     *
     * @param bool $includeProspects Define se prospects também devem ser retornados.
     * @return array Lista de clientes conforme o filtro informado.
     */
    public function getAll(bool $includeProspects = false)
    {
        $sql = 'SELECT * FROM clientes';
        if (!$includeProspects) {
            $sql .= ' WHERE is_prospect = 0';
        }
        $sql .= ' ORDER BY nome_cliente';

        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Busca apenas os prospects cadastrados.
     */
    public function getProspects(): array
    {
        $sql = 'SELECT * FROM clientes WHERE is_prospect = 1 ORDER BY nome_cliente';
        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Busca apenas os clientes finais utilizados no sistema principal.
     *
     * @return array Lista de clientes com is_prospect = 0.
     */
    public function getAppClients(): array
    {
        return $this->getAll();
    }

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
                        (nome_cliente, nome_responsavel, cpf_cnpj, email, telefone, endereco, numero, bairro, cidade, estado, cep, tipo_pessoa, tipo_assessoria, user_id, is_prospect) 
                    VALUES 
                        (:nome_cliente, :nome_responsavel, :cpf_cnpj, :email, :telefone, :endereco, :numero, :bairro, :cidade, :estado, :cep, :tipo_pessoa, :tipo_assessoria, :user_id, :is_prospect)";
            
            $stmt = $this->pdo->prepare($sql);
            
            $stmt->execute([
                ':nome_cliente' => $data['nome_cliente'],
                ':nome_responsavel' => $data['nome_responsavel'] ?? null,
                ':cpf_cnpj' => $cpf_cnpj,
                ':email' => $data['email'] ?? null,
                ':telefone' => $data['telefone'] ?? null,
                ':endereco' => $data['endereco'] ?? null,
                ':numero' => $data['numero'] ?? null,
                ':bairro' => $data['bairro'] ?? null,
                ':cidade' => $data['cidade'] ?? null,
                ':estado' => $data['estado'] ?? null,
                ':cep' => $data['cep'] ?? null,
                ':tipo_pessoa' => $data['tipo_pessoa'] ?? 'Jurídica',
                ':tipo_assessoria' => $data['tipo_assessoria'] ?? null,
                ':user_id' => $userId,
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

    /**
     * Vincula o ID do cliente retornado pela API da Omie ao registro local.
     *
     * @param int $clientId O ID do cliente no banco de dados local.
     * @param int $omieId O código do cliente retornado pela Omie.
     * @return bool Retorna true em caso de sucesso.
     */
    public function linkOmieId($clientId, $omieId)
    {
        return $this->updateIntegrationIdentifiers((int)$clientId, null, (int)$omieId);
    }

    public function updateIntegrationIdentifiers(int $clientId, ?string $integrationCode, ?int $omieId = null): bool
    {
        $fields = [];
        $params = [];

        if ($integrationCode !== null && $this->supportsIntegrationCodeColumn()) {
            $fields[] = 'codigo_cliente_integracao = :integration_code';
            $params[':integration_code'] = $integrationCode;
        }

        if ($omieId !== null) {
            $fields[] = 'omie_id = :omie_id';
            $params[':omie_id'] = $omieId;
        }

        if (empty($fields)) {
            return true;
        }

        $params[':id'] = $clientId;
        $sql = 'UPDATE clientes SET ' . implode(', ', $fields) . ' WHERE id = :id';
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }
    public function getPdo() {
        return $this->pdo;
    }

    public function supportsIntegrationCodeColumn(): bool
    {
        if ($this->integrationCodeColumnAvailable === null) {
            $this->integrationCodeColumnAvailable = DatabaseSchemaInspector::hasColumn($this->pdo, 'clientes', 'codigo_cliente_integracao');
        }

        return $this->integrationCodeColumnAvailable;
    }

    private function hasConversionDateColumn(): bool
    {
        if ($this->conversionDateColumnAvailable === null) {
            $this->conversionDateColumnAvailable = DatabaseSchemaInspector::hasColumn($this->pdo, 'clientes', 'data_conversao');
        }

        return $this->conversionDateColumnAvailable;
    }

        /**
     * Busca apenas os clientes que são prospecções (para o CRM).
     */
    public function getCrmProspects()
    {
        $sql = "SELECT c.*, (
                    SELECT COUNT(*)
                    FROM prospeccoes p
                    WHERE p.cliente_id = c.id
                ) AS totalProspeccoes
                FROM clientes c
                WHERE c.is_prospect = 1
                ORDER BY c.nome_cliente ASC";

        $stmt = $this->pdo->prepare($sql);
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
    /**
     * NOVO MÉTODO: Busca os serviços e preços de um cliente mensalista.
     */
    public function getServicosMensalista($cliente_id) {
        // A CORREÇÃO ESTÁ AQUI: Adicionamos "cf.servico_tipo" à consulta SELECT
        $sql = "SELECT csm.*, cf.nome_categoria, cf.servico_tipo 
                FROM cliente_servicos_mensalistas csm
                JOIN categorias_financeiras cf ON csm.produto_orcamento_id = cf.id
                WHERE csm.cliente_id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$cliente_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }


    /**
     * NOVO MÉTODO: Salva a lista de serviços e preços de um cliente mensalista.
     */
    public function salvarServicosMensalista($cliente_id, $servicos) {
        // Primeiro, apaga a lista antiga para garantir consistência.
        $this->pdo->prepare("DELETE FROM cliente_servicos_mensalistas WHERE cliente_id = ?")->execute([$cliente_id]);

        if (empty($servicos)) {
            return true; // Se não houver serviços, apenas termina.
        }

        $sql = "INSERT INTO cliente_servicos_mensalistas (cliente_id, produto_orcamento_id, valor_padrao) VALUES (?, ?, ?)";
        $stmt = $this->pdo->prepare($sql);

        foreach ($servicos as $servico) {
            // Garante que apenas linhas com dados válidos sejam salvas.
            if (!empty($servico['produto_orcamento_id']) && isset($servico['valor_padrao'])) {
                $stmt->execute([
                    $cliente_id,
                    $servico['produto_orcamento_id'],
                    $servico['valor_padrao']
                ]);
            }
        }
        return true;
    }
    public function getServicoContratadoPorNome($clienteId, $nomeCategoria) {
        $sql = "SELECT csm.*, cf.nome_categoria 
                FROM cliente_servicos_mensalistas csm
                JOIN categorias_financeiras cf ON csm.produto_orcamento_id = cf.id
                WHERE csm.cliente_id = :cliente_id AND cf.nome_categoria = :nome_categoria";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':cliente_id' => $clienteId, ':nome_categoria' => $nomeCategoria]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

}