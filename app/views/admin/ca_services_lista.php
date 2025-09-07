<?php
/**
 * @var array $sales A lista de vendas.
 * @var int $currentPage
 * @var int $totalPages
 * @var bool $isFirstPage
 * @var bool $isLastPage
 */
?>

<div class="mb-6">
    <h1 class="text-3xl font-bold text-gray-800">Vendas da Conta Azul</h1>
    <p class="text-gray-600">Consulte e filtre as vendas registradas na sua conta.</p>
</div>

<div class="mb-4 p-4 bg-gray-50 rounded-lg border">
    <form action="admin.php" method="GET">
        <input type="hidden" name="action" value="ca_list_sales">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
            <div>
                <label for="data_inicial" class="block text-sm font-medium text-gray-700">Data Inicial</label>
                <input type="date" name="data_inicial" id="data_inicial" value="<?php echo htmlspecialchars($_GET['data_inicial'] ?? ''); ?>" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
            </div>
            <div>
                <label for="data_final" class="block text-sm font-medium text-gray-700">Data Final</label>
                <input type="date" name="data_final" id="data_final" value="<?php echo htmlspecialchars($_GET['data_final'] ?? ''); ?>" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
            </div>
            <div class="md:col-span-2 flex justify-end space-x-2">
                <a href="admin.php?action=ca_list_sales" class="bg-gray-200 text-gray-700 font-bold py-2 px-4 rounded-lg hover:bg-gray-300">Limpar</a>
                <button type="submit" class="bg-blue-600 text-white font-bold py-2 px-4 rounded-lg hover:bg-blue-700">Filtrar</button>
            </div>
        </div>
    </form>
</div>

<div class="bg-white shadow-lg rounded-lg overflow-hidden">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Número</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Cliente</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Data</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Valor Total</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if (empty($sales)): ?>
                    <tr>
                        <td colspan="5" class="px-6 py-4 text-center text-gray-500">Nenhuma venda encontrada para os filtros selecionados.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($sales as $sale): ?>
                        <tr>
                            <td class="px-6 py-4 text-sm font-medium text-blue-600 hover:text-blue-800">
                                <a href="admin.php?action=ca_show_sale&id=<?php echo htmlspecialchars($sale['id']); ?>"><?php echo htmlspecialchars($sale['numero'] ?? '#'.$sale['id_legado']); ?></a>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-800"><?php echo htmlspecialchars($sale['cliente']['nome']); ?></td>
                            <td class="px-6 py-4 text-sm text-gray-500 text-center"><?php echo date('d/m/Y', strtotime($sale['data_emissao'])); ?></td>
                            <td class="px-6 py-4 text-sm text-center">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                    <?php echo htmlspecialchars($sale['status']); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-700 text-right font-mono">R$ <?php echo number_format($sale['total'], 2, ',', '.'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if (isset($totalPages) && $totalPages > 1): ?>
    <div class="px-6 py-3 bg-gray-50 border-t flex items-center justify-between">
        <div><p class="text-sm text-gray-700">Página <span class="font-medium"><?php echo $currentPage + 1; ?></span> de <span class="font-medium"><?php echo $totalPages; ?></span></p></div>
        <div>
            <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px">
                <?php $queryString = http_build_query(array_merge($_GET, ['page' => $currentPage - 1])); ?>
                <a href="?<?php echo $queryString; ?>" class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50 <?php echo $isFirstPage ? 'pointer-events-none opacity-50' : ''; ?>">Anterior</a>
                <?php $queryString = http_build_query(array_merge($_GET, ['page' => $currentPage + 1])); ?>
                <a href="?<?php echo $queryString; ?>" class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50 <?php echo $isLastPage ? 'pointer-events-none opacity-50' : ''; ?>">Próxima</a>
            </nav>
        </div>
    </div>
    <?php endif; ?>
</div>