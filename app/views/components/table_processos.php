<?php
/** @var array $processos */
?>
<table class="table table-striped table-bordered">
    <thead>
    <tr>
        <th>ID</th>
        <th>Cliente</th>
        <th>Título</th>
        <th>Vendedor</th>
        <th>SDR</th>
        <th>Entrada</th>
        <th>Restante</th>
        <th>Status</th>
        <th>Pagamento 1</th>
        <th>Pagamento 2</th>
    </tr>
    </thead>
    <tbody>
    <?php foreach ($processos as $processo): ?>
        <tr>
            <td><?php echo (int) $processo['id']; ?></td>
            <td><?php echo htmlspecialchars($processo['cliente_nome'] ?? ''); ?></td>
            <td><?php echo htmlspecialchars($processo['titulo'] ?? ''); ?></td>
            <td><?php echo htmlspecialchars($processo['vendedor_nome'] ?? ''); ?></td>
            <td><?php echo htmlspecialchars($processo['sdr_nome'] ?? ''); ?></td>
            <td>R$ <?php echo number_format((float) ($processo['orcamento_valor_entrada'] ?? 0), 2, ',', '.'); ?></td>
            <td>R$ <?php echo number_format((float) ($processo['orcamento_valor_restante'] ?? 0), 2, ',', '.'); ?></td>
            <td><?php echo htmlspecialchars($processo['status_processo'] ?? ''); ?></td>
            <td><?php echo htmlspecialchars($processo['data_pagamento_1'] ?? ''); ?></td>
            <td><?php echo htmlspecialchars($processo['data_pagamento_2'] ?? ''); ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
