<?php
// /app/models/LancamentoFinanceiro.php

class LancamentoFinanceiro {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function create($data) {
        $sql = "INSERT INTO lancamentos_financeiros (descricao, valor, data_vencimento, tipo_lancamento, categoria_id, cliente_id, processo_id, status, eh_agregado, itens_agregados_ids, data_lancamento, finalizado, user_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            $data['descricao'],
            $data['valor'],
            $data['data_vencimento'],
            $data['tipo'],
            $data['categoria_id'],
            $data['cliente_id'] ?? null,
            $data['processo_id'] ?? null,
            $data['status'] ?? 'Pendente',
            $data['eh_agregado'] ?? 0,
            $data['itens_agregados_ids'] ?? null,
            $data['data_lancamento'] ?? date('Y-m-d H:i:s'),
            $data['finalizado'] ?? 0,
            $data['user_id'] ?? ($_SESSION['user_id'] ?? null)
        ]);
    }

    public function getById($id) {
        $stmt = $this->pdo->prepare("SELECT * FROM lancamentos_financeiros WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public function delete($id) {
        $registro = $this->getById($id);

        if (!$registro || !empty($registro['finalizado'])) {
            return false;
        }

        $stmt = $this->pdo->prepare("DELETE FROM lancamentos_financeiros WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public function update($id, $data) {
        $registro = $this->getById($id);

        if (!$registro) {
            return false;
        }

        if (!empty($registro['finalizado'])) {
            throw new RuntimeException('Registro finalizado não pode ser alterado.');
        }

        $sql = "UPDATE lancamentos_financeiros
                SET descricao = :descricao,
                    valor = :valor,
                    data_vencimento = :data_vencimento,
                    tipo_lancamento = :tipo,
                    categoria_id = :categoria_id,
                    cliente_id = :cliente_id,
                    processo_id = :processo_id,
                    status = :status,
                    user_id = :user_id
                WHERE id = :id";

        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            ':descricao' => $data['descricao'],
            ':valor' => $data['valor'],
            ':data_vencimento' => $data['data_vencimento'],
            ':tipo' => $data['tipo'],
            ':categoria_id' => $data['categoria_id'],
            ':cliente_id' => $data['cliente_id'] ?? null,
            ':processo_id' => $data['processo_id'] ?? null,
            ':status' => $data['status'] ?? $registro['status'],
            ':user_id' => $data['user_id'] ?? ($_SESSION['user_id'] ?? null),
            ':id' => $id,
        ]);
    }

    public function finalizar($id, $userId) {
        $stmt = $this->pdo->prepare("UPDATE lancamentos_financeiros SET finalizado = 1, user_id = :user_id WHERE id = :id");
        return $stmt->execute([
            ':user_id' => $userId,
            ':id' => $id,
        ]);
    }

    public function ajustar($idOriginal, $valor, $motivo, $userId) {
        $original = $this->getById($idOriginal);

        if (!$original) {
            throw new RuntimeException('Lançamento original não encontrado.');
        }

        $descricao = sprintf('Ajuste do lançamento #%d — %s', $idOriginal, $motivo);

        $dados = [
            'descricao' => $descricao,
            'valor' => $valor,
            'data_vencimento' => date('Y-m-d'),
            'tipo' => $original['tipo_lancamento'],
            'categoria_id' => $original['categoria_id'],
            'cliente_id' => $original['cliente_id'] ?? null,
            'processo_id' => $original['processo_id'] ?? null,
            'status' => 'Pendente',
            'eh_agregado' => $original['eh_agregado'] ?? 0,
            'itens_agregados_ids' => $original['itens_agregados_ids'] ?? null,
            'data_lancamento' => date('Y-m-d H:i:s'),
            'finalizado' => 1,
            'user_id' => $userId,
        ];

        return $this->create($dados);
    }

    public function getAllPaginated($page = 1, $perPage = 20, $search = '', $filters = []) {
        $offset = ($page - 1) * $perPage;
        $params = [];
        
        $sql = "SELECT lf.*, cf.nome_categoria 
                FROM lancamentos_financeiros lf
                LEFT JOIN categorias_financeiras cf ON lf.categoria_id = cf.id
                WHERE 1=1";

        if (!empty($search)) {
            $sql .= " AND (lf.descricao LIKE :search OR cf.nome_categoria LIKE :search)";
            $params[':search'] = "%$search%";
        }

        if (!empty($filters['start_date'])) {
            $sql .= " AND lf.data_vencimento >= :start_date";
            $params[':start_date'] = $filters['start_date'];
        }
        if (!empty($filters['end_date'])) {
            $sql .= " AND lf.data_vencimento <= :end_date";
            $params[':end_date'] = $filters['end_date'];
        }
        if (!empty($filters['type'])) {
            $sql .= " AND lf.tipo_lancamento = :type"; // CORRIGIDO AQUI
            $params[':type'] = $filters['type'];
        }
        if (!empty($filters['status'])) {
            $sql .= " AND lf.status = :status";
            $params[':status'] = $filters['status'];
        }
        if (!empty($filters['category'])) {
            $sql .= " AND lf.categoria_id = :category";
            $params[':category'] = $filters['category'];
        }

        if (!empty($filters['user_id'])) {
            $sql .= " AND lf.user_id = :user_id";
            $params[':user_id'] = $filters['user_id'];
        }

        $sql .= " ORDER BY lf.data_vencimento DESC, lf.id DESC LIMIT :limit OFFSET :offset";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':limit', (int) $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', (int) $offset, PDO::PARAM_INT);
        
        foreach ($params as $key => &$val) {
            $stmt->bindParam($key, $val);
        }

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function countAll($search = '', $filters = []) {
        $params = [];
        $sql = "SELECT COUNT(lf.id) 
                FROM lancamentos_financeiros lf
                LEFT JOIN categorias_financeiras cf ON lf.categoria_id = cf.id
                WHERE 1=1";

        if (!empty($search)) {
            $sql .= " AND (lf.descricao LIKE :search OR cf.nome_categoria LIKE :search)";
            $params[':search'] = "%$search%";
        }

        if (!empty($filters['start_date'])) {
            $sql .= " AND lf.data_vencimento >= :start_date";
            $params[':start_date'] = $filters['start_date'];
        }
        if (!empty($filters['end_date'])) {
            $sql .= " AND lf.data_vencimento <= :end_date";
            $params[':end_date'] = $filters['end_date'];
        }
        if (!empty($filters['type'])) {
            $sql .= " AND lf.tipo_lancamento = :type"; // CORRIGIDO AQUI
            $params[':type'] = $filters['type'];
        }
        if (!empty($filters['status'])) {
            $sql .= " AND lf.status = :status";
            $params[':status'] = $filters['status'];
        }
        if (!empty($filters['category'])) {
            $sql .= " AND lf.categoria_id = :category";
            $params[':category'] = $filters['category'];
        }

        if (!empty($filters['user_id'])) {
            $sql .= " AND lf.user_id = :user_id";
            $params[':user_id'] = $filters['user_id'];
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn();
    }

    public function getTotals($search = '', $filters = []) {
        $params = [];
        $sql = "SELECT 
                    SUM(CASE WHEN tipo_lancamento = 'RECEITA' THEN valor ELSE 0 END) as receitas, -- CORRIGIDO AQUI
                    SUM(CASE WHEN tipo_lancamento = 'DESPESA' THEN valor ELSE 0 END) as despesas -- CORRIGIDO AQUI
                FROM lancamentos_financeiros lf
                WHERE 1=1";

        if (!empty($search)) {
            $sql = str_replace('FROM lancamentos_financeiros lf', 'FROM lancamentos_financeiros lf LEFT JOIN categorias_financeiras cf ON lf.categoria_id = cf.id', $sql);
            $sql .= " AND (lf.descricao LIKE :search OR cf.nome_categoria LIKE :search)";
            $params[':search'] = "%$search%";
        }

        if (!empty($filters['start_date'])) {
            $sql .= " AND lf.data_vencimento >= :start_date";
            $params[':start_date'] = $filters['start_date'];
        }
        if (!empty($filters['end_date'])) {
            $sql .= " AND lf.data_vencimento <= :end_date";
            $params[':end_date'] = $filters['end_date'];
        }
        if (!empty($filters['type'])) {
            $sql .= " AND lf.tipo_lancamento = :type"; // CORRIGIDO AQUI
            $params[':type'] = $filters['type'];
        }
        if (!empty($filters['status'])) {
            $sql .= " AND lf.status = :status";
            $params[':status'] = $filters['status'];
        }
        if (!empty($filters['category'])) {
            $sql .= " AND lf.categoria_id = :category";
            $params[':category'] = $filters['category'];
        }

        if (!empty($filters['user_id'])) {
            $sql .= " AND lf.user_id = :user_id";
            $params[':user_id'] = $filters['user_id'];
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}