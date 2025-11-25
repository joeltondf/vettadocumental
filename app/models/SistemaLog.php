<?php

class SistemaLog
{
    private $pdo;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    public function register($userId, $tabela, $registroId, $acao, $descricao, $timestamp = null): bool
    {
        $timestamp = $timestamp ?? (new DateTime('now', new DateTimeZone('UTC')))
            ->modify('-3 hours')
            ->format('Y-m-d H:i:s');

        $sql = "INSERT INTO sistema_logs (user_id, tabela, registro_id, acao, descricao, data_operacao) VALUES (:user_id, :tabela, :registro_id, :acao, :descricao, :data_operacao)";
        $stmt = $this->pdo->prepare($sql);

        return $stmt->execute([
            ':user_id' => $userId,
            ':tabela' => $tabela,
            ':registro_id' => $registroId,
            ':acao' => $acao,
            ':descricao' => $descricao,
            ':data_operacao' => $timestamp,
        ]);
    }

    public function getLogs(int $page = 1, int $perPage = 25): array
    {
        $page = max(1, $page);
        $perPage = max(1, min($perPage, 200));
        $offset = ($page - 1) * $perPage;

        $countStmt = $this->pdo->query('SELECT COUNT(*) FROM sistema_logs');
        $total = (int) $countStmt->fetchColumn();

        $stmt = $this->pdo->prepare(
            "SELECT sl.*, u.nome_completo AS user_nome
             FROM sistema_logs sl
             LEFT JOIN users u ON sl.user_id = u.id
             ORDER BY sl.data_operacao DESC
             LIMIT :limit OFFSET :offset"
        );
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'logs' => $logs,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => (int) ceil($total / $perPage),
        ];
    }
}
