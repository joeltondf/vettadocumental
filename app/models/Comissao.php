<?php
// /app/models/Comissao.php

class Comissao
{
    private $pdo;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Busca todas as comissões de um vendedor específico.
     *
     * @param int $vendedor_id O ID do vendedor.
     * @return array
     */
    public function getByVendedor($vendedor_id)
    {
        // ===== CORREÇÃO APLICADA AQUI =====
        // A consulta agora junta 'comissoes' com 'processos' (em vez da inexistente 'vendas').
        // 'p.data_criacao' é usada como a data da venda.
        $sql = "SELECT 
                    c.*, 
                    p.data_criacao as data_venda, 
                    p.valor_total as valor_da_venda
                FROM comissoes c
                JOIN processos p ON c.venda_id = p.id
                WHERE c.vendedor_id = :vendedor_id
                ORDER BY p.data_criacao DESC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':vendedor_id' => $vendedor_id]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Calcula e grava (ou atualiza) a comissão para um processo/venda.
     *
     * @param int $processo_id
     * @param int $vendedor_id
     * @param float $valor_venda
     * @return bool
     */
    public function calcularEGravarComissao($processo_id, $vendedor_id, $valor_venda)
    {
        try {
            // 1. Buscar o percentual de comissão padrão
            $stmt_config = $this->pdo->prepare("SELECT valor FROM configuracoes_comissao WHERE tipo_regra = 'percentual_padrao' AND ativo = 1 LIMIT 1");
            $stmt_config->execute();
            $config = $stmt_config->fetch();
            $percentual = $config ? $config['valor'] : 5.00; // 5% como fallback

            // 2. Calcular o valor da comissão
            $valor_comissao = $valor_venda * ($percentual / 100);

            // 3. Insere ou atualiza a comissão. A coluna 'venda_id' na tabela comissoes se refere ao 'processo_id'.
            $sql = "INSERT INTO comissoes (venda_id, vendedor_id, percentual_comissao, valor_comissao, status_comissao) 
                    VALUES (:venda_id, :vendedor_id, :percentual, :valor, 'Pendente')
                    ON DUPLICATE KEY UPDATE 
                        percentual_comissao = VALUES(percentual_comissao), 
                        valor_comissao = VALUES(valor_comissao), 
                        status_comissao = 'Pendente'";
            
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute([
                ':venda_id' => $processo_id,
                ':vendedor_id' => $vendedor_id,
                ':percentual' => $percentual,
                ':valor' => $valor_comissao
            ]);

        } catch (PDOException $e) {
            error_log("Erro ao calcular comissão: " . $e->getMessage());
            return false;
        }
    }
}