<?php
/**
 * Componente de tabela para listagem de processos.
 * Variáveis esperadas:
 * - $processos (array)
 */

$processos = $processos ?? [];

$formatCurrency = static function ($value): string {
    $numeric = is_numeric($value) ? (float) $value : 0.0;
    return 'R$ ' . number_format($numeric, 2, ',', '.');
};

$formatDate = static function (?string $date): string {
    if (empty($date)) {
        return '—';
    }

    try {
        return (new DateTime($date))->format('d/m/Y');
    } catch (Throwable $exception) {
        return '—';
    }
};
?>

<div class="overflow-x-auto rounded-lg border border-gray-100">
    <table class="min-w-full text-sm">
        <thead>
            <tr class="bg-gray-50 text-left text-gray-600">
                <th class="p-3">Cliente</th>
                <th class="p-3">Vendedor</th>
                <th class="p-3">Entrada</th>
                <th class="p-3">Restante</th>
                <th class="p-3">Status</th>
                <th class="p-3">Pagamento 1</th>
                <th class="p-3">Pagamento 2</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($processos)): ?>
                <tr>
                    <td colspan="7" class="p-4 text-center text-gray-500">Nenhum processo encontrado para o filtro aplicado.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($processos as $processo): ?>
                    <tr class="border-b last:border-0">
                        <td class="p-3 text-gray-800"><?= htmlspecialchars($processo['cliente_nome'] ?? '—', ENT_QUOTES, 'UTF-8'); ?></td>
                        <td class="p-3 text-gray-600"><?= htmlspecialchars($processo['vendedor_nome'] ?? $processo['nome_vendedor'] ?? '—', ENT_QUOTES, 'UTF-8'); ?></td>
                        <td class="p-3 font-semibold text-emerald-700"><?= $formatCurrency($processo['orcamento_valor_entrada'] ?? $processo['valor_entrada'] ?? 0); ?></td>
                        <td class="p-3 font-semibold text-amber-700"><?= $formatCurrency($processo['orcamento_valor_restante'] ?? $processo['valor_restante'] ?? 0); ?></td>
                        <td class="p-3 text-gray-600"><?= htmlspecialchars($processo['status_financeiro'] ?? $processo['status_processo'] ?? '—', ENT_QUOTES, 'UTF-8'); ?></td>
                        <td class="p-3 text-gray-600"><?= $formatDate($processo['data_pagamento_1'] ?? null); ?></td>
                        <td class="p-3 text-gray-600"><?= $formatDate($processo['data_pagamento_2'] ?? null); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>
