<?php
// /app/models/Notificacao.php

class Notificacao
{
    private const PRIORITY_HIGH = 'alta';
    private const PRIORITY_MEDIUM = 'media';
    private const PRIORITY_LOW = 'baixa';

    private const PRIORITY_ORDER = [
        self::PRIORITY_HIGH => 1,
        self::PRIORITY_MEDIUM => 2,
        self::PRIORITY_LOW => 3,
    ];

    private $pdo;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    public function getDropdownNotifications(
        int $userId,
        string $grupoDestino,
        int $limit = 15,
        string $sourceTimezone = 'UTC'
    ): array {
        $result = $this->searchAlerts(
            [
                'usuario_id' => $userId,
                'grupo_destino' => $grupoDestino,
                'only_unread' => true,
            ],
            $limit,
            0,
            false,
            $sourceTimezone
        );

        return $result['notifications'];
    }

    public function getAlertFeed(
        int $userId,
        string $grupoDestino,
        int $limit = 15,
        bool $onlyUnread = true,
        string $sourceTimezone = 'UTC',
        array $options = []
    ): array {
        $filters = $options['filters'] ?? [];
        $filters['grupo_destino'] = $grupoDestino;

        $includeGroupScope = (bool)($options['include_group'] ?? false);
        if (!$includeGroupScope && empty($filters['destinatario_id'])) {
            $filters['destinatario_id'] = $userId;
        }

        if ($onlyUnread) {
            $filters['only_unread'] = true;
        }

        $grouped = (bool)($options['grouped'] ?? false);
        $offset = (int)($options['offset'] ?? 0);

        return $this->searchAlerts(
            $filters,
            $limit,
            $offset,
            $grouped,
            $sourceTimezone
        );
    }

    /**
     * Cria um novo registro de notificação no banco de dados.
     *
     * @param int $usuario_id ID do usuário que vai RECEBER a notificação.
     * @param int|null $remetente_id ID do usuário que GEROU a ação.
     * @param string $mensagem O texto da notificação.
     * @param string|null $link O link para onde o usuário será levado ao clicar.
     * @return bool
     */
    public function criar(
        int $usuarioId,
        ?int $remetenteId,
        string $mensagem,
        ?string $link,
        string $tipoAlerta,
        int $referenciaId,
        string $grupoDestino
    ): bool {
        if ($usuarioId <= 0 || $referenciaId <= 0) {
            return false;
        }

        $normalizedLink = $link !== null ? trim($link) : null;
        $tipoAlerta = trim($tipoAlerta) !== '' ? trim($tipoAlerta) : 'notificacao_generica';
        $grupoDestino = trim($grupoDestino) !== '' ? trim($grupoDestino) : 'gerencia';

        $metadata = $this->getProcessMetadata($referenciaId);
        $now = $this->currentTimestampString();
        $priority = $this->determinePriority($tipoAlerta, $now, $metadata);

        $driver = $this->resolveDriver();

        if ($driver === 'sqlite') {
            $sql = <<<SQL
                INSERT INTO notificacoes (
                    usuario_id,
                    remetente_id,
                    mensagem,
                    link,
                    tipo_alerta,
                    referencia_id,
                    grupo_destino,
                    lida,
                    resolvido,
                    prioridade,
                    data_criacao
                ) VALUES (
                    :usuario_id,
                    :remetente_id,
                    :mensagem,
                    :link,
                    :tipo_alerta,
                    :referencia_id,
                    :grupo_destino,
                    0,
                    0,
                    :prioridade,
                    CURRENT_TIMESTAMP
                )
                ON CONFLICT(usuario_id, tipo_alerta, referencia_id, grupo_destino) DO UPDATE SET
                    remetente_id = excluded.remetente_id,
                    mensagem = excluded.mensagem,
                    link = excluded.link,
                    lida = 0,
                    resolvido = 0,
                    prioridade = excluded.prioridade,
                    data_criacao = CURRENT_TIMESTAMP
            SQL;
        } else {
            $sql = <<<SQL
                INSERT INTO notificacoes (
                    usuario_id,
                    remetente_id,
                    mensagem,
                    link,
                    tipo_alerta,
                    referencia_id,
                    grupo_destino,
                    lida,
                    resolvido,
                    prioridade,
                    data_criacao
                ) VALUES (
                    :usuario_id,
                    :remetente_id,
                    :mensagem,
                    :link,
                    :tipo_alerta,
                    :referencia_id,
                    :grupo_destino,
                    0,
                    0,
                    :prioridade,
                    CURRENT_TIMESTAMP
                )
                ON DUPLICATE KEY UPDATE
                    remetente_id = VALUES(remetente_id),
                    mensagem = VALUES(mensagem),
                    link = VALUES(link),
                    lida = 0,
                    resolvido = 0,
                    prioridade = VALUES(prioridade),
                    data_criacao = CURRENT_TIMESTAMP
            SQL;
        }

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':usuario_id', $usuarioId, PDO::PARAM_INT);
            $stmt->bindValue(':remetente_id', $remetenteId, $remetenteId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $stmt->bindValue(':mensagem', $mensagem, PDO::PARAM_STR);
            $stmt->bindValue(':link', $normalizedLink, $normalizedLink === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->bindValue(':tipo_alerta', $tipoAlerta, PDO::PARAM_STR);
            $stmt->bindValue(':referencia_id', $referenciaId, PDO::PARAM_INT);
            $stmt->bindValue(':grupo_destino', $grupoDestino, PDO::PARAM_STR);
            $stmt->bindValue(':prioridade', $priority, PDO::PARAM_STR);

            return $stmt->execute();
        } catch (PDOException $exception) {
            error_log('Erro ao criar notificação: ' . $exception->getMessage());
            return false;
        }
    }

    /**
     * Busca as notificações mais recentes para um usuário específico.
     *
     * @param int $usuario_id
     * @param int $limit
     * @return array
     */
    public function getRecentes(
        int $usuarioId,
        string $grupoDestino,
        int $limit = 7,
        string $sourceTimezone = 'UTC'
    ): array {
        return $this->fetchAlerts($usuarioId, $grupoDestino, $limit, true, $sourceTimezone);
    }

    /**
     * Conta quantas notificações não lidas um usuário possui.
     *
     * @param int $usuario_id
     * @return int
     */
    public function countNaoLidas(int $usuarioId, string $grupoDestino): int
    {
        return $this->countAlerts([
            'destinatario_id' => $usuarioId,
            'grupo_destino' => $grupoDestino,
            'only_unread' => true,
        ], false);
    }

    public function delete(int $id): bool
    {
        $sql = 'DELETE FROM notificacoes WHERE id = ?';
        try {
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute([$id]);
        } catch (PDOException $exception) {
            error_log('Erro ao excluir notificação: ' . $exception->getMessage());
            return false;
        }
    }

    public function deleteByLink(string $link): bool
    {
        $links = $this->buildLinkVariants($link);
        if (empty($links)) {
            return false;
        }

        $placeholders = implode(', ', array_fill(0, count($links), '?'));
        $sql = "UPDATE notificacoes SET resolvido = 1, lida = 1 WHERE link IN ({$placeholders})";

        try {
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute($links);
        } catch (PDOException $exception) {
            error_log('Erro ao excluir notificação por link: ' . $exception->getMessage());
            return false;
        }
    }

    public function marcarComoLida(int $notificationId, int $usuarioId): bool
    {
        $notification = $this->findNotificationById($notificationId, $usuarioId);
        if ($notification === null) {
            return false;
        }

        return $this->updateNotificationsByReference($notification, ['lida' => 1]) > 0;
    }

    public function marcarListaComoLida(array $notificationIds, int $usuarioId): int
    {
        return $this->updateBatchByScope($notificationIds, $usuarioId, ['lida' => 1]);
    }

    public function marcarComoResolvida(int $notificationId, int $usuarioId): bool
    {
        $notification = $this->findNotificationById($notificationId, $usuarioId);
        if ($notification === null) {
            return false;
        }

        return $this->updateNotificationsByReference($notification, ['lida' => 1, 'resolvido' => 1]) > 0;
    }

    public function marcarListaComoResolvida(array $notificationIds, int $usuarioId): int
    {
        return $this->updateBatchByScope($notificationIds, $usuarioId, ['lida' => 1, 'resolvido' => 1]);
    }

    public function resolverPorReferencia(string $tipoAlerta, int $referenciaId, ?string $grupoDestino = null): void
    {
        $tipoAlerta = trim($tipoAlerta);
        if ($tipoAlerta === '' || $referenciaId <= 0) {
            return;
        }

        $conditions = ['tipo_alerta = :tipo_alerta', 'referencia_id = :referencia_id'];
        $params = [
            ':tipo_alerta' => $tipoAlerta,
            ':referencia_id' => $referenciaId,
        ];

        if ($grupoDestino !== null && trim($grupoDestino) !== '') {
            $conditions[] = 'grupo_destino = :grupo_destino';
            $params[':grupo_destino'] = trim($grupoDestino);
        }

        $sql = 'UPDATE notificacoes SET resolvido = 1, lida = 1 WHERE ' . implode(' AND ', $conditions);
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
    }

    public function getFilterOptions(int $usuarioId, string $grupoDestino, bool $includeGroup = false): array
    {
        $conditions = ['n.grupo_destino = :grupo_destino'];
        $params = [':grupo_destino' => trim($grupoDestino) !== '' ? trim($grupoDestino) : 'gerencia'];

        if (!$includeGroup) {
            $conditions[] = 'n.usuario_id = :usuario_id';
            $params[':usuario_id'] = $usuarioId;
        }

        $where = implode(' AND ', $conditions);

        $tipos = $this->fetchDistinctValues('n.tipo_alerta', $where, $params);
        $prioridades = $this->fetchDistinctValues('n.prioridade', $where, $params);
        if (!empty($prioridades)) {
            $orderMap = [
                self::PRIORITY_HIGH => 0,
                self::PRIORITY_MEDIUM => 1,
                self::PRIORITY_LOW => 2,
            ];
            usort($prioridades, static function (string $a, string $b) use ($orderMap): int {
                return ($orderMap[$a] ?? 99) <=> ($orderMap[$b] ?? 99);
            });
        }
        $status = $this->fetchDistinctValues('p.status_processo', $where, $params, true);

        $clientes = $this->fetchDistinctClients($where, $params);
        $usuarios = $this->fetchDistinctUsuarios($where, $params);

        return [
            'tipos' => $tipos,
            'prioridades' => $prioridades,
            'status' => $status,
            'clientes' => $clientes,
            'usuarios' => $usuarios,
        ];
    }

    public static function resolveGroupForProfile(string $perfil): string
    {
        return trim($perfil) === 'vendedor' ? 'vendedor' : 'gerencia';
    }

    private function searchAlerts(
        array $filters,
        int $limit,
        int $offset,
        bool $grouped,
        string $sourceTimezone
    ): array {
        [$conditions, $params, $joins] = $this->buildQueryParts($filters);

        $limit = max(1, $limit);
        $offset = max(0, $offset);

        $orderClause = $this->buildPriorityOrderExpression('n.prioridade') . ', n.data_criacao DESC, n.id DESC';

        $sql = <<<SQL
            SELECT
                n.*,
                p.status_processo,
                p.titulo AS processo_titulo,
                p.data_criacao AS processo_data_criacao,
                p.data_entrada AS processo_data_entrada,
                p.data_previsao_entrega,
                p.data_finalizacao_real,
                p.cliente_id,
                c.nome_cliente,
                u.nome_completo AS destinatario_nome
            FROM notificacoes AS n
            {$joins}
            WHERE {$conditions}
            ORDER BY {$orderClause}
            LIMIT :limit OFFSET :offset
        SQL;

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->bindValue(':priority_high', self::PRIORITY_HIGH, PDO::PARAM_STR);
        $stmt->bindValue(':priority_medium', self::PRIORITY_MEDIUM, PDO::PARAM_STR);
        $stmt->bindValue(':priority_low', self::PRIORITY_LOW, PDO::PARAM_STR);
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $notifications = array_map(
            fn (array $row): array => $this->hydrateNotification($row, $sourceTimezone),
            $rows
        );

        $total = $this->countAlerts($filters, $grouped);

        if ($grouped) {
            $groupedNotifications = $this->groupNotificationsByReference($notifications);
            $groupedNotifications = $this->sortNotificationGroups($groupedNotifications);

            return [
                'total' => $total,
                'groups' => $groupedNotifications,
                'notifications' => [],
            ];
        }

        foreach ($notifications as &$notification) {
            unset($notification['raw_data_criacao']);
        }
        unset($notification);

        return [
            'total' => $total,
            'notifications' => $notifications,
        ];
    }

    private function countAlerts(array $filters, bool $grouped): int
    {
        [$conditions, $params, $joins] = $this->buildQueryParts($filters);
        $countExpression = $grouped
            ? 'COUNT(DISTINCT COALESCE(n.referencia_id, n.id))'
            : 'COUNT(*)';

        $sql = <<<SQL
            SELECT {$countExpression}
            FROM notificacoes AS n
            {$joins}
            WHERE {$conditions}
        SQL;

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();

        return (int)$stmt->fetchColumn();
    }

    private function buildQueryParts(array $filters): array
    {
        $conditions = ['1 = 1'];
        $params = [];
        $joins = 'LEFT JOIN processos AS p ON p.id = n.referencia_id '
            . 'LEFT JOIN clientes AS c ON c.id = p.cliente_id '
            . 'LEFT JOIN users AS u ON u.id = n.usuario_id';

        if (!empty($filters['grupo_destino'])) {
            $conditions[] = 'n.grupo_destino = :grupo_destino';
            $params[':grupo_destino'] = $filters['grupo_destino'];
        }

        if (!empty($filters['destinatario_id'])) {
            $conditions[] = 'n.usuario_id = :destinatario_id';
            $params[':destinatario_id'] = (int)$filters['destinatario_id'];
        }

        if (!empty($filters['usuario'])) {
            $conditions[] = 'n.usuario_id = :usuario_filter';
            $params[':usuario_filter'] = (int)$filters['usuario'];
        }

        if (!empty($filters['tipo'])) {
            $types = (array)$filters['tipo'];
            $placeholders = $this->bindArrayValues($types, ':tipo');
            $conditions[] = 'n.tipo_alerta IN (' . implode(', ', array_keys($placeholders)) . ')';
            $params += $placeholders;
        }

        if (!empty($filters['prioridade'])) {
            $prioridades = (array)$filters['prioridade'];
            $placeholders = $this->bindArrayValues($prioridades, ':prioridade');
            $conditions[] = 'n.prioridade IN (' . implode(', ', array_keys($placeholders)) . ')';
            $params += $placeholders;
        }

        if (!empty($filters['status'])) {
            $status = (string)$filters['status'];
            switch ($status) {
                case 'lido':
                    $conditions[] = 'n.lida = 1';
                    break;
                case 'nao_lido':
                    $conditions[] = 'n.lida = 0';
                    $conditions[] = 'n.resolvido = 0';
                    break;
                case 'resolvido':
                    $conditions[] = 'n.resolvido = 1';
                    break;
                case 'todos':
                    break;
                default:
                    $conditions[] = 'n.resolvido = 0';
                    break;
            }
        } elseif (!empty($filters['only_unread'])) {
            $conditions[] = 'n.lida = 0';
            $conditions[] = 'n.resolvido = 0';
        } else {
            $conditions[] = 'n.resolvido = 0';
        }

        if (!empty($filters['cliente_id'])) {
            $conditions[] = 'p.cliente_id = :cliente_id';
            $params[':cliente_id'] = (int)$filters['cliente_id'];
        }

        if (!empty($filters['periodo_inicio'])) {
            $conditions[] = 'n.data_criacao >= :periodo_inicio';
            $params[':periodo_inicio'] = $filters['periodo_inicio'];
        }

        if (!empty($filters['periodo_fim'])) {
            $conditions[] = 'n.data_criacao <= :periodo_fim';
            $params[':periodo_fim'] = $filters['periodo_fim'];
        }

        if (!empty($filters['status_processo'])) {
            $statuses = (array)$filters['status_processo'];
            $placeholders = $this->bindArrayValues($statuses, ':status_processo');
            $conditions[] = 'p.status_processo IN (' . implode(', ', array_keys($placeholders)) . ')';
            $params += $placeholders;
        }

        return [implode(' AND ', $conditions), $params, $joins];
    }

    private function buildPriorityOrderExpression(string $column): string
    {
        return sprintf(
            'CASE %s WHEN :priority_high THEN 0 WHEN :priority_medium THEN 1 WHEN :priority_low THEN 2 ELSE 3 END',
            $column
        );
    }

    private function hydrateNotification(array $notification, string $sourceTimezone): array
    {
        $notification['link'] = $this->normalizeNotificationLink($notification['link'] ?? null);

        $rawDate = $notification['data_criacao'] ?? null;
        $notification['raw_data_criacao'] = $rawDate;
        $convertedDate = $this->convertToTimezone(
            $rawDate,
            'Y-m-d H:i:s',
            'America/Sao_Paulo',
            $sourceTimezone
        );
        $notification['data_criacao'] = $convertedDate;

        if ($convertedDate === '') {
            $notification['display_date'] = '';
        } else {
            try {
                $displayDate = new \DateTime($convertedDate, new \DateTimeZone('America/Sao_Paulo'));
                $notification['display_date'] = $displayDate->format('d/m/Y H:i');
            } catch (\Exception $exception) {
                $notification['display_date'] = $convertedDate;
            }
        }

        $metadata = [
            'status_processo' => $notification['status_processo'] ?? null,
            'cliente_id' => $notification['cliente_id'] ?? null,
        ];

        $priority = $this->determinePriority(
            (string)($notification['tipo_alerta'] ?? 'notificacao_generica'),
            $rawDate,
            $metadata
        );

        if (!empty($notification['id'])) {
            $notificationId = (int)$notification['id'];
            $storedPriority = $notification['prioridade'] ?? null;
            if ($storedPriority !== $priority) {
                $this->syncPriorityForNotification($notificationId, $priority);
            }
            $notification['prioridade'] = $priority;
        }

        return $notification;
    }

    private function groupNotificationsByReference(array $notifications): array
    {
        $groups = [];

        foreach ($notifications as $notification) {
            $key = $notification['referencia_id'] ?? null;
            if ($key === null || (int)$key === 0) {
                $key = 'individual-' . $notification['id'];
            }

            if (!isset($groups[$key])) {
                $groups[$key] = [
                    'referencia_id' => $notification['referencia_id'] ?? null,
                    'processo_titulo' => $notification['processo_titulo'] ?? null,
                    'cliente_id' => $notification['cliente_id'] ?? null,
                    'cliente_nome' => $notification['nome_cliente'] ?? null,
                    'status_processo' => $notification['status_processo'] ?? null,
                    'prioridade' => $notification['prioridade'] ?? self::PRIORITY_MEDIUM,
                    'notifications' => [],
                ];
            }

            $groups[$key]['notifications'][] = $notification;

            $currentPriority = $groups[$key]['prioridade'];
            $notificationPriority = $notification['prioridade'] ?? self::PRIORITY_MEDIUM;
            if ($this->priorityOrder($notificationPriority) < $this->priorityOrder($currentPriority)) {
                $groups[$key]['prioridade'] = $notificationPriority;
            }
        }

        return array_values($groups);
    }

    private function sortNotificationGroups(array $groups): array
    {
        foreach ($groups as &$group) {
            $group['latest_timestamp'] = $this->groupLatestTimestamp($group['notifications'] ?? []);
        }
        unset($group);

        usort($groups, function (array $a, array $b): int {
            $priorityComparison = $this->priorityOrder($a['prioridade'] ?? self::PRIORITY_MEDIUM)
                <=> $this->priorityOrder($b['prioridade'] ?? self::PRIORITY_MEDIUM);

            if ($priorityComparison !== 0) {
                return $priorityComparison;
            }

            return ($b['latest_timestamp'] ?? 0) <=> ($a['latest_timestamp'] ?? 0);
        });

        foreach ($groups as &$group) {
            unset($group['latest_timestamp']);
            foreach ($group['notifications'] as &$notification) {
                unset($notification['raw_data_criacao']);
            }
            unset($notification);
        }
        unset($group);

        return $groups;
    }

    private function groupLatestTimestamp(array $notifications): int
    {
        $latest = 0;

        foreach ($notifications as $notification) {
            $rawDate = $notification['raw_data_criacao'] ?? null;
            if ($rawDate === null || trim((string)$rawDate) === '') {
                continue;
            }

            try {
                $timestamp = (new \DateTimeImmutable($rawDate, new \DateTimeZone('UTC')))->getTimestamp();
            } catch (\Exception $exception) {
                continue;
            }

            if ($timestamp > $latest) {
                $latest = $timestamp;
            }
        }

        return $latest;
    }

    private function findNotificationById(int $notificationId, int $usuarioId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM notificacoes WHERE id = :id AND usuario_id = :usuario_id LIMIT 1'
        );
        $stmt->bindValue(':id', $notificationId, PDO::PARAM_INT);
        $stmt->bindValue(':usuario_id', $usuarioId, PDO::PARAM_INT);
        $stmt->execute();

        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result !== false ? $result : null;
    }

    private function updateBatchByScope(array $notificationIds, int $usuarioId, array $fields): int
    {
        $notifications = $this->loadNotificationsByIds($notificationIds, $usuarioId);
        if (empty($notifications)) {
            return 0;
        }

        $processed = [];
        $totalAffected = 0;

        foreach ($notifications as $notification) {
            $scopeKey = $this->buildNotificationScopeKey($notification);
            if (isset($processed[$scopeKey])) {
                continue;
            }

            $affected = $this->updateNotificationsByReference($notification, $fields);
            if ($affected > 0) {
                $totalAffected += $affected;
            }

            $processed[$scopeKey] = true;
        }

        return $totalAffected;
    }

    private function loadNotificationsByIds(array $ids, int $usuarioId): array
    {
        $filteredIds = array_values(array_unique(array_map('intval', $ids)));
        $filteredIds = array_filter($filteredIds, static fn (int $id): bool => $id > 0);

        if (empty($filteredIds)) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($filteredIds), '?'));
        $sql = "SELECT * FROM notificacoes WHERE id IN ({$placeholders}) AND usuario_id = ?";

        $stmt = $this->pdo->prepare($sql);
        foreach (array_values($filteredIds) as $index => $id) {
            $stmt->bindValue($index + 1, $id, PDO::PARAM_INT);
        }
        $stmt->bindValue(count($filteredIds) + 1, $usuarioId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function buildNotificationScopeKey(array $notification): string
    {
        $type = trim((string)($notification['tipo_alerta'] ?? ''));
        $reference = (int)($notification['referencia_id'] ?? 0);

        if ($type !== '' && $reference > 0) {
            $group = trim((string)($notification['grupo_destino'] ?? ''));
            return $type . '|' . $reference . '|' . $group;
        }

        return 'id|' . ($notification['id'] ?? uniqid('', true));
    }

    private function updateNotificationsByReference(array $notification, array $fields): int
    {
        if (empty($fields)) {
            return 0;
        }

        $setParts = [];
        $params = [];
        foreach ($fields as $field => $value) {
            $placeholder = ':set_' . $field;
            $setParts[] = sprintf('%s = %s', $field, $placeholder);
            $params[$placeholder] = $value;
        }

        $sql = 'UPDATE notificacoes SET ' . implode(', ', $setParts) . ' WHERE id = :id';
        $params[':id'] = (int)($notification['id'] ?? 0);

        $type = trim((string)($notification['tipo_alerta'] ?? ''));
        $reference = (int)($notification['referencia_id'] ?? 0);
        $group = trim((string)($notification['grupo_destino'] ?? ''));

        if ($type !== '' && $reference > 0) {
            $sql .= ' OR (tipo_alerta = :tipo_alerta AND referencia_id = :referencia_id';
            $params[':tipo_alerta'] = $type;
            $params[':referencia_id'] = $reference;

            if ($group !== '') {
                $sql .= ' AND grupo_destino = :grupo_destino';
                $params[':grupo_destino'] = $group;
            }

            $sql .= ')';
        }

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();

        return $stmt->rowCount();
    }

    private function priorityOrder(string $priority): int
    {
        $normalized = strtolower(trim($priority));
        return self::PRIORITY_ORDER[$normalized] ?? 99;
    }

    private function bindArrayValues(array $values, string $baseName): array
    {
        $placeholders = [];
        $base = ltrim($baseName, ':');

        foreach (array_values($values) as $index => $value) {
            $placeholder = sprintf(':%s_%d', $base, $index);
            $placeholders[$placeholder] = $value;
        }

        return $placeholders;
    }

    private function fetchDistinctValues(
        string $column,
        string $where,
        array $params,
        bool $includeJoins = false
    ): array {
        $joins = $includeJoins
            ? 'LEFT JOIN processos AS p ON p.id = n.referencia_id '
                . 'LEFT JOIN clientes AS c ON c.id = p.cliente_id '
                . 'LEFT JOIN users AS u ON u.id = n.usuario_id'
            : '';

        $sql = "SELECT DISTINCT {$column} AS value FROM notificacoes AS n {$joins} "
            . "WHERE {$where} AND {$column} IS NOT NULL AND {$column} <> '' ORDER BY value";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();

        $values = $stmt->fetchAll(PDO::FETCH_COLUMN);

        return array_values(array_filter(
            array_map(static fn ($value) => (string)$value, $values),
            static fn (string $value): bool => $value !== ''
        ));
    }

    private function fetchDistinctClients(string $where, array $params): array
    {
        $sql = <<<SQL
            SELECT DISTINCT c.id AS id, c.nome_cliente AS nome
            FROM notificacoes AS n
            LEFT JOIN processos AS p ON p.id = n.referencia_id
            LEFT JOIN clientes AS c ON c.id = p.cliente_id
            WHERE {$where} AND c.id IS NOT NULL
            ORDER BY c.nome_cliente
        SQL;

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_values(array_map(
            static fn (array $row): array => [
                'id' => (int)$row['id'],
                'label' => (string)$row['nome'],
            ],
            array_filter($rows, static fn (array $row): bool => ($row['id'] ?? null) !== null)
        ));
    }

    private function fetchDistinctUsuarios(string $where, array $params): array
    {
        $sql = <<<SQL
            SELECT DISTINCT n.usuario_id AS id, u.nome_completo AS nome
            FROM notificacoes AS n
            LEFT JOIN users AS u ON u.id = n.usuario_id
            WHERE {$where}
            ORDER BY COALESCE(u.nome_completo, n.usuario_id)
        SQL;

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_values(array_map(
            static function (array $row): array {
                $id = (int)$row['id'];
                $nome = trim((string)($row['nome'] ?? ''));

                return [
                    'id' => $id,
                    'label' => $nome !== '' ? $nome : 'Usuário #' . $id,
                ];
            },
            array_filter($rows, static fn (array $row): bool => ($row['id'] ?? null) !== null)
        ));
    }

    private function determinePriority(string $tipoAlerta, ?string $dataCriacao, array $metadata = []): string
    {
        $tipoAlerta = trim($tipoAlerta);
        $ageDays = $this->calculateAgeInDays($dataCriacao);
        $status = strtolower(trim((string)($metadata['status_processo'] ?? '')));

        if (in_array($tipoAlerta, ['processo_orcamento_recusado', 'processo_cancelado'], true)) {
            return self::PRIORITY_HIGH;
        }

        if (in_array($status, ['concluido', 'concluído', 'finalizado', 'arquivado'], true)) {
            return self::PRIORITY_LOW;
        }

        if (in_array($status, ['cancelado', 'recusado'], true)) {
            return self::PRIORITY_MEDIUM;
        }

        switch ($tipoAlerta) {
            case 'processo_pendente_orcamento':
                if ($ageDays >= 7) {
                    return self::PRIORITY_HIGH;
                }
                if ($ageDays >= 3) {
                    return self::PRIORITY_MEDIUM;
                }

                return self::PRIORITY_LOW;
            case 'processo_pendente_servico':
            case 'processo_servico_pendente':
                if ($ageDays >= 5) {
                    return self::PRIORITY_HIGH;
                }
                if ($ageDays >= 2) {
                    return self::PRIORITY_MEDIUM;
                }

                return self::PRIORITY_LOW;
            case 'prospeccao_generica':
                if ($ageDays >= 10) {
                    return self::PRIORITY_HIGH;
                }
                if ($ageDays >= 5) {
                    return self::PRIORITY_MEDIUM;
                }

                return self::PRIORITY_LOW;
            default:
                if ($ageDays >= 10) {
                    return self::PRIORITY_HIGH;
                }
                if ($ageDays >= 4) {
                    return self::PRIORITY_MEDIUM;
                }

                return self::PRIORITY_LOW;
        }
    }

    private function calculateAgeInDays(?string $dateTime): int
    {
        if ($dateTime === null || trim($dateTime) === '') {
            return 0;
        }

        try {
            $createdAt = new \DateTimeImmutable($dateTime, new \DateTimeZone('UTC'));
            $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
            $interval = $createdAt->diff($now);

            return (int)$interval->format('%a');
        } catch (\Exception $exception) {
            return 0;
        }
    }

    private function getProcessMetadata(int $referenciaId): array
    {
        if ($referenciaId <= 0) {
            return [];
        }

        $stmt = $this->pdo->prepare(
            'SELECT p.status_processo, p.titulo, p.cliente_id, c.nome_cliente '
            . 'FROM processos AS p '
            . 'LEFT JOIN clientes AS c ON c.id = p.cliente_id '
            . 'WHERE p.id = :id'
        );
        $stmt->bindValue(':id', $referenciaId, PDO::PARAM_INT);
        $stmt->execute();

        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result !== false ? $result : [];
    }

    private function currentTimestampString(): string
    {
        return gmdate('Y-m-d H:i:s');
    }

    private function findOpenNotification(
        int $usuarioId,
        string $tipoAlerta,
        int $referenciaId,
        string $grupoDestino
    ): ?array {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM notificacoes '
            . 'WHERE usuario_id = :usuario_id '
            . 'AND tipo_alerta = :tipo_alerta '
            . 'AND referencia_id = :referencia_id '
            . 'AND grupo_destino = :grupo_destino '
            . 'LIMIT 1'
        );
        $stmt->bindValue(':usuario_id', $usuarioId, PDO::PARAM_INT);
        $stmt->bindValue(':tipo_alerta', $tipoAlerta, PDO::PARAM_STR);
        $stmt->bindValue(':referencia_id', $referenciaId, PDO::PARAM_INT);
        $stmt->bindValue(':grupo_destino', $grupoDestino, PDO::PARAM_STR);
        $stmt->execute();

        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result !== false ? $result : null;
    }

    private function resolveDriver(): string
    {
        try {
            return (string) $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        } catch (Throwable $exception) {
            return 'mysql';
        }
    }

    private function syncPriorityForNotification(int $notificationId, string $priority): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE notificacoes SET prioridade = :prioridade '
            . 'WHERE id = :id AND prioridade <> :prioridade'
        );
        $stmt->bindValue(':prioridade', $priority, PDO::PARAM_STR);
        $stmt->bindValue(':id', $notificationId, PDO::PARAM_INT);
        $stmt->execute();
    }

    private function normalizeNotificationLink(?string $link): string
    {
        if ($link === null) {
            return '#';
        }

        $trimmedLink = trim($link);

        if ($trimmedLink === '') {
            return '#';
        }

        if (!defined('APP_URL')) {
            return $this->isAbsoluteUrl($trimmedLink)
                ? $trimmedLink
                : $this->ensureLeadingSlash($trimmedLink);
        }

        $baseUrl = rtrim((string)APP_URL, '/');

        if ($this->isAbsoluteUrl($trimmedLink)) {
            if (strpos($trimmedLink, $baseUrl) === 0) {
                $relative = substr($trimmedLink, strlen($baseUrl));
                return $this->ensureLeadingSlash($relative);
            }

            return $trimmedLink;
        }

        return $this->ensureLeadingSlash($trimmedLink);
    }

    private function convertToTimezone(?string $dateTime, string $format, string $targetTimezone, string $sourceTimezone): string
    {
        if ($dateTime === null || trim($dateTime) === '') {
            return '';
        }

        try {
            $date = new \DateTime($dateTime, new \DateTimeZone($sourceTimezone));
            $date->setTimezone(new \DateTimeZone($targetTimezone));

            return $date->format($format);
        } catch (\Exception $exception) {
            return $dateTime;
        }
    }

    private function buildLinkVariants(string $link): array
    {
        $trimmedLink = trim($link);
        if ($trimmedLink === '') {
            return [];
        }

        $variants = [$trimmedLink];

        $normalizedLink = $this->normalizeNotificationLink($trimmedLink);
        if ($normalizedLink !== '#' && $normalizedLink !== $trimmedLink) {
            $variants[] = $normalizedLink;
        }

        if (!defined('APP_URL')) {
            return $variants;
        }

        $baseUrl = rtrim((string)APP_URL, '/');
        $relativeFromAbsolute = $this->stripBaseUrl($trimmedLink, $baseUrl);

        if ($relativeFromAbsolute !== null) {
            $variants[] = $relativeFromAbsolute;
        } elseif (!$this->isAbsoluteUrl($trimmedLink)) {
            $relativePath = $this->ensureLeadingSlash($trimmedLink);
            $variants[] = $relativePath;
            $variants[] = $baseUrl . $relativePath;
        }

        return array_values(array_unique($variants));
    }

    private function stripBaseUrl(string $link, string $baseUrl): ?string
    {
        if (strpos($link, $baseUrl) !== 0) {
            return null;
        }

        $relative = substr($link, strlen($baseUrl));
        return $this->ensureLeadingSlash($relative);
    }

    private function ensureLeadingSlash(string $path): string
    {
        return '/' . ltrim($path, '/');
    }

    private function isAbsoluteUrl(string $link): bool
    {
        return (bool)preg_match('/^https?:\/\//i', $link);
    }
}