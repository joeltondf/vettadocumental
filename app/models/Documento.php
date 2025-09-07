<?php
/**
 * @file /app/models/Documento.php
 * @description Model para gerir os dados da entidade 'Documento'.
 * Documentos são tipicamente associados a um 'Processo'.
 * Esta classe fornece a estrutura básica de CRUD para os documentos.
 */

class Documento
{
    private $pdo;

    /**
     * Construtor da classe Documento.
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
     * Cria um novo documento associado a um processo.
     *
     * @param array $data Dados do documento (ex: processo_id, tipo_documento, quantidade, etc.).
     * @return int|false Retorna o ID do novo documento ou 'false' em caso de erro.
     */
    public function create(array $data)
    {
        try {
            $sql = "INSERT INTO documentos (processo_id, categoria, tipo_documento, nome_documento, quantidade, valor_unitario) 
                    VALUES (:processo_id, :categoria, :tipo_documento, :nome_documento, :quantidade, :valor_unitario)";
            
            $stmt = $this->pdo->prepare($sql);
            
            $stmt->execute([
                ':processo_id'      => $data['processo_id'],
                ':categoria'        => $data['categoria'] ?? 'N/A',
                ':tipo_documento'   => $data['tipo_documento'] ?? 'N/A',
                ':nome_documento'   => $data['nome_documento'] ?? null,
                ':quantidade'       => $data['quantidade'] ?? 1,
                ':valor_unitario'   => $data['valor_unitario'] ?? 0.00
            ]);
            
            return $this->pdo->lastInsertId();
        } catch (PDOException $e) {
            error_log("Erro ao criar documento: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Busca um documento específico pelo seu ID.
     *
     * @param int $id O ID do documento.
     * @return array|false Um array com os dados do documento ou 'false' se não for encontrado.
     */
    public function getById($id)
    {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM documentos WHERE id = ?");
            $stmt->execute([$id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erro ao buscar documento por ID: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Busca todos os documentos de um processo específico.
     *
     * @param int $processoId O ID do processo.
     * @return array Uma lista de documentos associados ao processo. Retorna array vazio em caso de erro.
     */
    public function getByProcessoId($processoId)
    {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM documentos WHERE processo_id = ? ORDER BY id ASC");
            $stmt->execute([$processoId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erro ao buscar documentos por ID do processo: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Atualiza os dados de um documento.
     *
     * @param int $id O ID do documento a ser atualizado.
     * @param array $data Os novos dados do documento.
     * @return bool 'true' em caso de sucesso, 'false' em caso de erro.
     */
    public function update(int $id, array $data)
    {
        try {
            $sql = "UPDATE documentos SET 
                        categoria = :categoria, 
                        tipo_documento = :tipo_documento, 
                        nome_documento = :nome_documento, 
                        quantidade = :quantidade, 
                        valor_unitario = :valor_unitario 
                    WHERE id = :id";

            $stmt = $this->pdo->prepare($sql);
            
            return $stmt->execute([
                ':id'               => $id,
                ':categoria'        => $data['categoria'] ?? 'N/A',
                ':tipo_documento'   => $data['tipo_documento'] ?? 'N/A',
                ':nome_documento'   => $data['nome_documento'] ?? null,
                ':quantidade'       => $data['quantidade'] ?? 1,
                ':valor_unitario'   => $data['valor_unitario'] ?? 0.00
            ]);
        } catch (PDOException $e) {
            error_log("Erro ao atualizar documento: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Deleta um documento do banco de dados.
     *
     * @param int $id O ID do documento a ser deletado.
     * @return bool 'true' em caso de sucesso, 'false' em caso de erro.
     */
    public function delete($id)
    {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM documentos WHERE id = ?");
            return $stmt->execute([$id]);
        } catch (PDOException $e) {
            error_log("Erro ao deletar documento: " . $e->getMessage());
            return false;
        }
    }
    
        /**
     * Vincula um documento/item local a um ID de produto/serviço da Conta Azul.
     * @param int $documentoId
     * @param string $contaAzulId
     * @return bool
     */
    public function linkContaAzulId(int $documentoId, string $contaAzulId): bool
    {
        // Confirme se o nome da coluna é 'conta_azul_servico_id' na sua tabela 'documentos_processo'
        $sql = "UPDATE documentos_processo SET conta_azul_servico_id = :ca_id WHERE id = :id";
        try {
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute(['ca_id' => $contaAzulId, 'id' => $documentoId]);
        } catch (PDOException $e) {
            error_log("Erro ao vincular documento {$documentoId} com Conta Azul ID: " . $e->getMessage());
            return false;
        }
    }

}