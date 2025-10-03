<?php
// /app/models/Prospeccao.php

class Prospeccao 
{
    private $pdo;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Busca estatísticas de prospecção para um responsável específico.
     * @param int $responsavel_id O ID do usuário (vendedor).
     * @return array
     */
    public function getStatsByResponsavel(int $responsavel_id): array
    {
        $stats = [
            'novos_leads_mes' => 0,
            'reunioes_agendadas_mes' => 0,
            'taxa_conversao' => 0
        ];

        // Novos leads no mês atual
        $stmt_leads = $this->pdo->prepare("SELECT COUNT(*) FROM prospeccoes WHERE responsavel_id = :id AND MONTH(data_prospeccao) = MONTH(CURDATE()) AND YEAR(data_prospeccao) = YEAR(CURDATE())");
        $stmt_leads->execute(['id' => $responsavel_id]);
        $stats['novos_leads_mes'] = $stmt_leads->fetchColumn();

        // Reuniões agendadas no mês atual
        $stmt_reunioes = $this->pdo->prepare("SELECT COUNT(*) FROM agendamentos WHERE usuario_id = :id AND MONTH(data_inicio) = MONTH(CURDATE()) AND YEAR(data_inicio) = YEAR(CURDATE())");
        $stmt_reunioes->execute(['id' => $responsavel_id]);
        $stats['reunioes_agendadas_mes'] = $stmt_reunioes->fetchColumn();

        // Taxa de conversão (total)
        $stmt_total = $this->pdo->prepare("SELECT COUNT(*) FROM prospeccoes WHERE responsavel_id = :id");
        $stmt_total->execute(['id' => $responsavel_id]);
        $total_prospeccoes = $stmt_total->fetchColumn();

        $stmt_convertidos = $this->pdo->prepare("SELECT COUNT(*) FROM prospeccoes WHERE responsavel_id = :id AND status = 'Convertido'");
        $stmt_convertidos->execute(['id' => $responsavel_id]);
        $total_convertidos = $stmt_convertidos->fetchColumn();

        if ($total_prospeccoes > 0) {
            $stats['taxa_conversao'] = ($total_convertidos / $total_prospeccoes) * 100;
        }

        return $stats;
    }

    /**
     * Conta o número de prospecções em cada status para o funil de vendas.
     * @param int $responsavel_id
     * @return array
     */
    public function getProspeccoesCountByStatus(int $responsavel_id): array
    {
        $sql = "SELECT status, COUNT(*) as total 
                FROM prospeccoes 
                WHERE responsavel_id = :id 
                AND status NOT IN ('Convertido', 'Descartado', 'Inativo', 'Pausa')
                GROUP BY status";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $responsavel_id]);
        
        // Retorna um array no formato ['Status' => total]
        return $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    /**
     * Busca os próximos agendamentos de um usuário.
     * @param int $responsavel_id
     * @param int $limit
     * @return array
     */
    public function getProximosAgendamentos(int $responsavel_id, int $limit = 5): array
    {
        $sql = "SELECT a.titulo, a.data_inicio, c.nome_cliente
                FROM agendamentos a
                LEFT JOIN clientes c ON a.cliente_id = c.id
                WHERE a.usuario_id = :id AND a.data_inicio >= CURDATE()
                ORDER BY a.data_inicio ASC
                LIMIT :limit";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':id', $responsavel_id, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

        /**
     * CONTA o número de prospecções que precisam de retorno.
     */
    public function countProspectsForReturn($days, $excluded_statuses, $user_id, $user_perfil)
    {
        $sql_conditions = "status NOT IN (" . str_repeat('?,', count($excluded_statuses) - 1) . "?) AND data_ultima_atualizacao <= NOW() - INTERVAL ? DAY";
        $params = array_merge($excluded_statuses, [$days]);

        if ($user_perfil === 'vendedor') {
            $sql_conditions .= " AND responsavel_id = ?";
            $params[] = $user_id;
        }

        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM prospeccoes WHERE " . $sql_conditions);
        $stmt->execute($params);
        return $stmt->fetchColumn();
    }

    /**
     * LISTA as prospecções mais urgentes que precisam de retorno.
     */
    public function getUrgentProspectsForReturn($limit, $days, $excluded_statuses, $user_id, $user_perfil)
    {
        $sql_conditions = "p.status NOT IN (" . str_repeat('?,', count($excluded_statuses) - 1) . "?) AND p.data_ultima_atualizacao <= NOW() - INTERVAL ? DAY";
        $params = array_merge($excluded_statuses, [$days]);

        if ($user_perfil === 'vendedor') {
            $sql_conditions .= " AND p.responsavel_id = ?";
            $params[] = $user_id;
        }

        $sql = "SELECT p.id, p.nome_prospecto, DATEDIFF(NOW(), p.data_ultima_atualizacao) as dias_sem_contato 
                FROM prospeccoes p
                WHERE " . $sql_conditions . "
                ORDER BY p.data_ultima_atualizacao ASC 
                LIMIT ?";
        
        $stmt = $this->pdo->prepare($sql);

        // Bind parameters with correct types
        $i = 1;
        foreach ($excluded_statuses as $status) {
            $stmt->bindValue($i++, $status, PDO::PARAM_STR);
        }
        $stmt->bindValue($i++, (int)$days, PDO::PARAM_INT);
        if ($user_perfil === 'vendedor') {
            $stmt->bindValue($i++, (int)$user_id, PDO::PARAM_INT);
        }
        $stmt->bindValue($i, (int)$limit, PDO::PARAM_INT); // Explicitly cast limit to an integer

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
        /**
     * Busca todos os usuários do sistema para preencher filtros e selects.
     * @return array
     */
        public function getAllUsers(): array
        {
            // Busca apenas usuários que podem ser responsáveis por prospecções.
            $sql = "SELECT id, nome_completo 
                    FROM users 
                    WHERE perfil IN ('admin', 'gerencia', 'supervisor', 'vendedor')
                    ORDER BY nome_completo ASC";
            
            try {
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute();
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (PDOException $e) {
                error_log("Erro ao buscar todos os usuários: " . $e->getMessage());
                return []; // Retorna um array vazio em caso de erro.
            }
        }
        public function getById(int $id)
        {
            $stmt = $this->pdo->prepare("SELECT * FROM prospeccoes WHERE id = ?");
            $stmt->execute([$id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        }
}