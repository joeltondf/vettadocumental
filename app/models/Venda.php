<?php

class Venda
{
    private $pdo; // A conexão PDO padrão

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    public function create($data)
    {
        // Validação básica
        if (empty($data['vendedor_id']) || !isset($data['valor_total'])) {
            return false;
        }

        $statusVenda = $data['status_venda'] ?? $data['status'] ?? 'Pendente';

        $sql = "INSERT INTO vendas (vendedor_id, cliente_id, processo_id, valor_total, descricao, status_venda, data_venda, finalizado, user_id)
                VALUES (:vendedor_id, :cliente_id, :processo_id, :valor_total, :descricao, :status_venda, :data_venda, :finalizado, :user_id)";

        $stmt = $this->pdo->prepare($sql);

        $stmt->bindValue(':vendedor_id', $data['vendedor_id']);
        $stmt->bindValue(':cliente_id', $data['cliente_id'] ?? null);
        $stmt->bindValue(':processo_id', $data['processo_id'] ?? null);
        $stmt->bindValue(':valor_total', $data['valor_total']);
        $stmt->bindValue(':descricao', $data['descricao'] ?? null);
        $stmt->bindValue(':status_venda', $statusVenda);
        $stmt->bindValue(':data_venda', $data['data_venda'] ?? date('Y-m-d H:i:s'));
        $stmt->bindValue(':finalizado', $data['finalizado'] ?? 0, PDO::PARAM_INT);
        $stmt->bindValue(':user_id', $data['user_id'] ?? ($_SESSION['user_id'] ?? null));

        if ($stmt->execute()) {
            return $this->pdo->lastInsertId();
        }
        return false;
    }

    public function find($id)
    {
        $sql = "SELECT v.*, u.nome_completo as nome_vendedor 
                FROM vendas v
                JOIN vendedores vd ON v.vendedor_id = vd.id
                JOIN users u ON vd.user_id = u.id
                WHERE v.id = :id";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':id', $id);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function update($id, $data)
    {
        $registro = $this->find($id);

        if (!$registro) {
            return false;
        }

        if (!empty($registro['finalizado'])) {
            throw new RuntimeException('Registro finalizado não pode ser alterado.');
        }

        $sql = "UPDATE vendas SET
                   valor_total = :valor_total,
                   descricao = :descricao,
                   status_venda = :status_venda,
                   user_id = :user_id
                WHERE id = :id";
        
        $stmt = $this->pdo->prepare($sql);

        $stmt->bindValue(':valor_total', $data['valor_total']);
        $stmt->bindValue(':descricao', $data['descricao']);
        $stmt->bindValue(':status_venda', $data['status_venda'] ?? $data['status'] ?? $registro['status_venda']);
        $stmt->bindValue(':user_id', $data['user_id'] ?? ($_SESSION['user_id'] ?? null));
        $stmt->bindValue(':id', $id);

        return $stmt->execute();
    }

    public function finalizar($id, $userId)
    {
        $stmt = $this->pdo->prepare("UPDATE vendas SET finalizado = 1, user_id = :user_id WHERE id = :id");
        return $stmt->execute([
            ':user_id' => $userId,
            ':id' => $id,
        ]);
    }

    public function ajustar($idOriginal, $valor, $motivo, $userId)
    {
        $original = $this->find($idOriginal);

        if (!$original) {
            throw new RuntimeException('Venda original não encontrada.');
        }

        $dados = [
            'vendedor_id' => $original['vendedor_id'],
            'cliente_id' => $original['cliente_id'],
            'processo_id' => $original['processo_id'],
            'valor_total' => $valor,
            'descricao' => sprintf('Ajuste da venda #%d — %s', $idOriginal, $motivo),
            'status_venda' => 'Ajustado',
            'data_venda' => date('Y-m-d H:i:s'),
            'finalizado' => 1,
            'user_id' => $userId,
        ];

        return $this->create($dados);
    }
    
    public function getAll()
    {
        $sql = "SELECT v.*, u.nome_completo as nome_vendedor 
                FROM vendas v
                JOIN vendedores vd ON v.vendedor_id = vd.id
                JOIN users u ON vd.user_id = u.id
                ORDER BY v.data_venda DESC";
        
        $stmt = $this->pdo->query($sql); // query() funciona para consultas simples sem parâmetros
        
        // A linha abaixo é a correção principal: trocamos resultSet() por fetchAll()
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getByVendedor($vendedor_id)
    {
        $sql = "SELECT * FROM vendas WHERE vendedor_id = :vendedor_id ORDER BY data_venda DESC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':vendedor_id', $vendedor_id);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    public function getItensByIds($ids) {
        if (empty($ids)) {
            return [];
        }
        
        // Cria os placeholders (?) para a consulta IN
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        
        $sql = "SELECT * FROM venda_itens WHERE id IN ($placeholders)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($ids);
        
        return $stmt->fetchAll();
    }
}