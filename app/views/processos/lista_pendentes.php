<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-bold text-gray-800">Aprovação de Orçamentos</h1>
</div>

<div class="bg-white p-6 rounded-lg shadow-md w-full mx-auto">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nº</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Cliente</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Vendedor</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Valor</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Data</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Ações</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if (empty($processos_pendentes)): ?>
                    <tr>
                        <td colspan="6" class="px-6 py-4 text-center text-gray-500">Nenhum orçamento pendente no momento.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($processos_pendentes as $processo): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap"><a href="processos.php?action=view&id=<?php echo $processo['id']; ?>" class="text-blue-600 hover:underline">#<?php echo htmlspecialchars($processo['orcamento_numero']); ?></a></td>
                            <td class="px-6 py-4 whitespace-nowrap"><?php echo htmlspecialchars($processo['nome_cliente']); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap"><?php echo htmlspecialchars($processo['nome_vendedor']); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap">R$ <?php echo number_format($processo['valor_total'], 2, ',', '.'); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap"><?php echo date('d/m/Y', strtotime($processo['data_entrada'])); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                <a href="aprovacoes.php?action=aprovar&id=<?php echo $processo['id']; ?>" onclick="return confirm('Tem certeza que deseja aprovar este orçamento?');" class="bg-green-500 hover:bg-green-600 text-white font-bold py-1 px-3 rounded text-sm">Aprovar</a>
                                <button onclick="openRecusarModal(<?php echo $processo['id']; ?>, '<?php echo htmlspecialchars($processo['orcamento_numero']); ?>')" class="bg-red-500 hover:bg-red-600 text-white font-bold py-1 px-3 rounded text-sm ml-2">Recusar</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div id="recusarModal" class="fixed z-10 inset-0 overflow-y-auto hidden">
    <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 transition-opacity" aria-hidden="true">
            <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
        </div>
        <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
            <form action="aprovacoes.php?action=recusar" method="POST">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <h3 class="text-lg leading-6 font-medium text-gray-900" id="modalTitle">Recusar Orçamento #</h3>
                    <div class="mt-2">
                        <input type="hidden" name="id" id="processo_id_recusar">
                        <label for="motivo_recusa" class="block text-sm font-medium text-gray-700">Motivo da Recusa (será enviado ao vendedor)</label>
                        <textarea id="motivo_recusa" name="motivo_recusa" rows="4" class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm" required></textarea>
                    </div>
                </div>
                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button type="submit" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-700 sm:ml-3 sm:w-auto sm:text-sm">Confirmar Recusa</button>
                    <button type="button" onclick="closeRecusarModal()" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 sm:mt-0 sm:w-auto sm:text-sm">Cancelar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openRecusarModal(id, numero) {
    document.getElementById('processo_id_recusar').value = id;
    document.getElementById('modalTitle').innerText = 'Recusar Orçamento #' + numero;
    document.getElementById('recusarModal').classList.remove('hidden');
}
function closeRecusarModal() {
    document.getElementById('recusarModal').classList.add('hidden');
}
</script>