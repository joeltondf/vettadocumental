<?php
/**
 * @var array|null $saleData Os dados completos da venda vindos da API.
 */
?>

<div class="mb-6">
    <h1 class="text-3xl font-bold text-gray-800">Detalhes da Venda</h1>
    <?php if ($saleData && isset($saleData['venda']['id'])): ?>
        <p class="text-gray-600">Exibindo informações da venda Número: <?php echo htmlspecialchars($saleData['venda']['numero'] ?? $saleData['venda']['id_legado']); ?></p>
    <?php endif; ?>
</div>

<?php if ($saleData && !isset($saleData['error']) && !isset($saleData['fault'])): ?>
    <div class="bg-white shadow-lg rounded-lg p-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-6">
            
            <div>
                <h2 class="text-xl font-bold text-gray-800 border-b pb-2 mb-4">Dados da Venda</h2>
                <div class="space-y-3 text-sm">
                    <p><strong>Situação:</strong> <span class="bg-blue-100 text-blue-800 font-semibold px-2 py-1 rounded-full"><?php echo htmlspecialchars($saleData['venda']['situacao']['descricao'] ?? 'N/A'); ?></span></p>
                    <p><strong>Número:</strong> <?php echo htmlspecialchars($saleData['venda']['numero'] ?? 'N/A'); ?></p>
                    <p><strong>Data do Compromisso:</strong> <?php echo isset($saleData['venda']['data_compromisso']) ? date('d/m/Y', strtotime($saleData['venda']['data_compromisso'])) : 'N/A'; ?></p>
                    <p><strong>Valor Total:</strong> <span class="font-bold text-lg">R$ <?php echo number_format($saleData['venda']['composicao_valor']['valor_liquido'] ?? 0, 2, ',', '.'); ?></span></p>
                </div>
            </div>

            <div>
                <h2 class="text-xl font-bold text-gray-800 border-b pb-2 mb-4">Pessoas Envolvidas</h2>
                <div class="space-y-3 text-sm">
                    <p><strong>Cliente:</strong> <?php echo htmlspecialchars($saleData['cliente']['nome'] ?? 'N/A'); ?></p>
                    <p><strong>Vendedor:</strong> <?php echo htmlspecialchars($saleData['vendedor']['nome'] ?? 'Não informado'); ?></p>
                </div>
            </div>
            
            <?php if (!empty($saleData['venda']['condicao_pagamento']['observacoes_pagamento'])): ?>
            <div class="md:col-span-2">
                 <h2 class="text-xl font-bold text-gray-800 border-b pb-2 mb-4">Observações do Pagamento</h2>
                 <div class="text-sm bg-gray-50 p-4 rounded-md border whitespace-pre-wrap">
                    <p><?php echo htmlspecialchars($saleData['venda']['condicao_pagamento']['observacoes_pagamento']); ?></p>
                 </div>
            </div>
            <?php endif; ?>

        </div>

        <?php if (!empty($saleData['venda']['itens'])): ?>
        <div class="mt-8">
            <h2 class="text-xl font-bold text-gray-800 border-b pb-2 mb-4">Itens da Venda</h2>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Descrição</th>
                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Quantidade</th>
                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Valor Unitário</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach($saleData['venda']['itens'] as $item): ?>
                            <tr>
                                <td class="px-4 py-3 text-sm"><?php echo htmlspecialchars($item['description'] ?? $item['descricao'] ?? 'Item'); ?></td>
                                <td class="px-4 py-3 text-sm text-right"><?php echo htmlspecialchars($item['quantity'] ?? 0); ?></td>
                                <td class="px-4 py-3 text-sm text-right font-mono">R$ <?php echo number_format($item['value'] ?? 0, 2, ',', '.'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

    </div>
<?php else: ?>
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg relative" role="alert">
        <strong class="font-bold">Venda não encontrada!</strong>
        <span class="block sm:inline">Não foi possível carregar os detalhes da venda solicitada. Verifique o ID e tente novamente.</span>
    </div>
<?php endif; ?>

<div class="mt-6">
    <a href="admin.php?action=ca_list_sales" class="text-blue-600 hover:text-blue-800 hover:underline">&larr; Voltar para a lista de vendas</a>
</div>