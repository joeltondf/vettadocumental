<?php
class DashboardProcessFormatter
{
    public const SETTINGS_KEY = 'tv_panel_settings';

    private const DEFAULT_COLORS = [
        'overdue' => 'bg-red-200 text-red-800',
        'due_today' => 'bg-red-200 text-red-800',
        'due_soon' => 'bg-yellow-200 text-yellow-800',
        'on_track' => 'text-green-600',
        'completed' => 'bg-green-100 text-green-800',
        'inactive' => 'text-gray-500',
        'no_deadline' => 'text-gray-500',
    ];

    private const STATUS_ALIASES = [
        'orcamento' => 'orçamento',
        'orcamento pendente' => 'orçamento pendente',
        'serviço pendente' => 'serviço pendente',
        'servico pendente' => 'serviço pendente',
        'pendente' => 'serviço pendente',
        'aprovado' => 'serviço pendente',
        'serviço em andamento' => 'serviço em andamento',
        'servico em andamento' => 'serviço em andamento',
        'em andamento' => 'serviço em andamento',
        'aguardando pagamento' => 'pendente de pagamento',
        'aguardando pagamentos' => 'pendente de pagamento',
        'aguardando documento' => 'pendente de documentos',
        'aguardando documentos' => 'pendente de documentos',
        'aguardando documentacao' => 'pendente de documentos',
        'aguardando documentação' => 'pendente de documentos',
        'pendente de pagamento' => 'pendente de pagamento',
        'pendente de documentos' => 'pendente de documentos',
        'finalizado' => 'concluído',
        'finalizada' => 'concluído',
        'concluido' => 'concluído',
        'concluida' => 'concluído',
        'arquivado' => 'cancelado',
        'arquivada' => 'cancelado',
        'recusado' => 'cancelado',
        'recusada' => 'cancelado',
    ];

    private const BADGE_LABELS = [
        'pendente de pagamento' => 'Pendente de pagamento',
        'pendente de documentos' => 'Pendente de documentos',
    ];

    private const STATUS_LABELS = [
        'orçamento' => 'Orçamento',
        'orçamento pendente' => 'Orçamento Pendente',
        'serviço pendente' => 'Serviço Pendente',
        'serviço em andamento' => 'Serviço em Andamento',
        'concluído' => 'Concluído',
        'cancelado' => 'Cancelado',
    ];

    public static function normalizeStatusForDashboard(?string $status): array
    {
        $normalizedInput = mb_strtolower(trim((string) $status));

        if ($normalizedInput === '') {
            return ['normalized' => '', 'badge_label' => null];
        }

        $normalized = self::STATUS_ALIASES[$normalizedInput] ?? $normalizedInput;
        $badgeLabel = self::BADGE_LABELS[$normalized] ?? null;

        if ($badgeLabel !== null) {
            $normalized = 'serviço em andamento';
        }

        return [
            'normalized' => $normalized,
            'badge_label' => $badgeLabel,
        ];
    }

    public static function normalizeStatusInfo(?string $status): array
    {
        $statusString = trim((string) $status);

        if ($statusString === '') {
            return ['normalized' => '', 'label' => 'N/A', 'badge_label' => null];
        }

        $baseInfo = self::normalizeStatusForDashboard($statusString);
        $normalized = $baseInfo['normalized'];
        $badgeLabel = $baseInfo['badge_label'];

        $label = self::STATUS_LABELS[$normalized] ?? $statusString;

        return [
            'normalized' => $normalized,
            'label' => $label,
            'badge_label' => $badgeLabel,
        ];
    }

    public static function getRowClass(string $statusNormalized): string
    {
        return match ($statusNormalized) {
            'orçamento', 'orçamento pendente' => 'bg-blue-50 hover:bg-blue-100',
            'serviço pendente' => 'bg-orange-50 hover:bg-orange-100',
            'serviço em andamento' => 'bg-cyan-50 hover:bg-cyan-100',
            'concluído' => 'bg-purple-50 hover:bg-purple-100',
            'cancelado' => 'bg-red-50 hover:bg-red-100',
            default => 'hover:bg-gray-50',
        };
    }

    public static function buildDeadlineDescriptor(array $process, array $colors = []): array
    {
        $colors = array_merge(self::DEFAULT_COLORS, $colors);
        $statusInfo = self::normalizeStatusInfo($process['status_processo'] ?? '');
        $statusNormalized = $statusInfo['normalized'];
        $badgeLabel = $statusInfo['badge_label'] ?? null;

        $descriptor = [
            'label' => 'A definir',
            'class' => $colors['no_deadline'],
            'state' => 'no_deadline',
            'days' => null,
            'deadlineDate' => null,
        ];

        if ($statusNormalized === 'concluído') {
            $descriptor['label'] = 'Concluído';
            $descriptor['class'] = $colors['completed'];
            $descriptor['state'] = 'completed';
            return $descriptor;
        }

        $pausedStatuses = ['cancelado', 'orçamento', 'orçamento pendente'];
        $isPaused = $badgeLabel !== null;

        if (in_array($statusNormalized, $pausedStatuses, true) || $isPaused) {
            $descriptor['label'] = 'N/A';
            $descriptor['class'] = $colors['inactive'];
            $descriptor['state'] = 'inactive';
            return $descriptor;
        }

        $deadlineDate = self::extractDeadlineDate($process);
        if ($deadlineDate === null) {
            return $descriptor;
        }

        $descriptor['deadlineDate'] = $deadlineDate;

        $today = new DateTimeImmutable('today');
        $diff = $today->diff($deadlineDate);
        $daysRemaining = (int) $diff->format('%r%a');
        $descriptor['days'] = $daysRemaining;

        if ($daysRemaining < 0) {
            $descriptor['label'] = abs($daysRemaining) . ' dia(s) vencido(s)';
            $descriptor['class'] = $colors['overdue'];
            $descriptor['state'] = 'overdue';
        } elseif ($daysRemaining === 0) {
            $descriptor['label'] = 'Vence hoje';
            $descriptor['class'] = $colors['due_today'];
            $descriptor['state'] = 'due_today';
        } elseif ($daysRemaining <= 3) {
            $descriptor['label'] = $daysRemaining . ' dias';
            $descriptor['class'] = $colors['due_soon'];
            $descriptor['state'] = 'due_soon';
        } else {
            $descriptor['label'] = $daysRemaining . ' dias';
            $descriptor['class'] = $colors['on_track'];
            $descriptor['state'] = 'on_track';
        }

        return $descriptor;
    }

    public static function getServiceBadges(?string $services): array
    {
        $map = [
            'Tradução' => ['label' => 'Trad.', 'class' => 'bg-blue-100 text-blue-800'],
            'CRC' => ['label' => 'CRC', 'class' => 'bg-teal-100 text-teal-800'],
            'Apostilamento' => ['label' => 'Apost.', 'class' => 'bg-purple-100 text-purple-800'],
            'Postagem' => ['label' => 'Post.', 'class' => 'bg-orange-100 text-orange-800'],
            'Outros' => ['label' => 'Out.', 'class' => 'bg-gray-100 text-gray-800'],
        ];

        $badges = [];
        $servicesList = array_filter(array_map('trim', explode(',', (string) $services)));

        foreach ($servicesList as $service) {
            if (isset($map[$service])) {
                $badges[] = $map[$service];
            }
        }

        return $badges;
    }

    public static function extractDeadlineDate(array $process): ?DateTimeImmutable
    {
        if (!empty($process['data_previsao_entrega'])) {
            return self::createImmutableDate($process['data_previsao_entrega']);
        }

        if (!empty($process['prazo_dias'])) {
            $baseDate = $process['data_criacao'] ?? $process['data_inicio_traducao'] ?? null;
            if ($baseDate) {
                $start = self::createImmutableDate($baseDate);
                if ($start !== null) {
                    return $start->modify('+' . (int) $process['prazo_dias'] . ' days');
                }
            }
        }

        return null;
    }

    private static function createImmutableDate(string $value): ?DateTimeImmutable
    {
        try {
            return new DateTimeImmutable($value);
        } catch (Exception $exception) {
            return null;
        }
    }
}
