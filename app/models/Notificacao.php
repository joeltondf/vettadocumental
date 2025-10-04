<?php
// /app/models/Notificacao.php

class Notificacao
{
    private $pdo;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    public function getDropdownNotifications(int $userId, int $limit = 15, string $sourceTimezone = 'UTC'): array
    {
        $statusToIgnore = ['Aprovado', 'Concluído', 'Finalizado'];
        $statusPlaceholders = [];

        foreach ($statusToIgnore as $index => $status) {
            $statusPlaceholders[] = ':status_' . $index;
        }

        $statusFilter = '';

        if (!empty($statusPlaceholders)) {
            $statusFilter = 'AND (p.id IS NULL OR p.status_processo NOT IN (' . implode(', ', $statusPlaceholders) . '))';
        } else {
            $statusFilter = 'AND (p.id IS NULL)';
        }

        $sql = "
            SELECT n.*
            FROM notificacoes n
            LEFT JOIN processos p ON p.id = CAST(SUBSTRING_INDEX(n.link, 'id=', -1) AS UNSIGNED)
            WHERE n.usuario_id = :usuario_id
              {$statusFilter}
            ORDER BY n.data_criacao DESC
            LIMIT :limit
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':usuario_id', $userId, PDO::PARAM_INT);

        foreach ($statusToIgnore as $index => $status) {
            $stmt->bindValue(':status_' . $index, $status, PDO::PARAM_STR);
        }

        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(function (array $notification) use ($sourceTimezone) {
            $notification['link'] = $this->normalizeNotificationLink($notification['link'] ?? null);

            $convertedDate = $this->convertToTimezone($notification['data_criacao'] ?? null, 'Y-m-d H:i:s', 'America/Sao_Paulo', $sourceTimezone);
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

            return $notification;
        }, $notifications);
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
    public function criar(int $usuario_id, ?int $remetente_id, string $mensagem, ?string $link): bool
    {
        $sql = "INSERT INTO notificacoes (usuario_id, remetente_id, mensagem, link, lida, data_criacao) 
                VALUES (?, ?, ?, ?, 0, NOW())";
        
        try {
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute([$usuario_id, $remetente_id, $mensagem, $link]);
        } catch (PDOException $e) {
            // Em um ambiente de produção, é bom registrar o erro.
            error_log("Erro ao criar notificação: " . $e->getMessage());
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
    public function getRecentes(int $usuario_id, int $limit = 7): array
    {
        $sql = "SELECT * FROM notificacoes 
                WHERE usuario_id = ? 
                ORDER BY data_criacao DESC 
                LIMIT ?";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(1, $usuario_id, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Conta quantas notificações não lidas um usuário possui.
     *
     * @param int $usuario_id
     * @return int
     */
    public function countNaoLidas(int $usuario_id): int
    {
        $sql = "SELECT COUNT(id) FROM notificacoes WHERE usuario_id = ? AND lida = 0";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$usuario_id]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Exclui uma notificação específica pelo seu ID.
     *
     * @param int $id
     * @return bool
     */
    public function delete(int $id): bool
    {
        $sql = "DELETE FROM notificacoes WHERE id = ?";
        try {
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute([$id]);
        } catch (PDOException $e) {
            error_log("Erro ao excluir notificação: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Exclui notificações com base no link, útil para limpar alertas de processos.
     *
     * @param string $link
     * @return bool
     */
    public function deleteByLink(string $link): bool
    {
        $links = $this->buildLinkVariants($link);
        if (empty($links)) {
            return false;
        }

        $placeholders = implode(', ', array_fill(0, count($links), '?'));
        $sql = "DELETE FROM notificacoes WHERE link IN ({$placeholders})";

        try {
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute($links);
        } catch (PDOException $e) {
            error_log("Erro ao excluir notificação por link: " . $e->getMessage());
            return false;
        }
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