<div class="max-w-7xl mx-auto py-6 px-4">
    <div class="bg-white shadow rounded-lg p-6">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">Auditoria do Sistema</h1>
                <p class="text-sm text-gray-500">Histórico de ações realizadas pelos usuários.</p>
            </div>
        </div>

        <?php if (!empty($_SESSION['error_message'])): ?>
            <div class="mb-4 p-3 bg-red-100 border border-red-200 text-red-700 rounded">
                <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
            </div>
        <?php endif; ?>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Data/Hora</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Usuário</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tabela</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Registro</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ação</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Descrição</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($logsData['logs'] as $log): ?>
                        <tr>
                            <td class="px-4 py-2 text-sm text-gray-700 whitespace-nowrap"><?php echo htmlspecialchars($log['data_operacao']); ?></td>
                            <td class="px-4 py-2 text-sm text-gray-700 whitespace-nowrap"><?php echo htmlspecialchars($log['user_nome'] ?? 'Sistema'); ?></td>
                            <td class="px-4 py-2 text-sm text-gray-700 whitespace-nowrap"><?php echo htmlspecialchars($log['tabela']); ?></td>
                            <td class="px-4 py-2 text-sm text-gray-700 whitespace-nowrap">#<?php echo htmlspecialchars($log['registro_id']); ?></td>
                            <td class="px-4 py-2 text-sm text-gray-700 whitespace-nowrap"><?php echo htmlspecialchars(ucfirst($log['acao'])); ?></td>
                            <td class="px-4 py-2 text-sm text-gray-700"><?php echo htmlspecialchars($log['descricao']); ?></td>
                        </tr>
                    <?php endforeach; ?>

                    <?php if (empty($logsData['logs'])): ?>
                        <tr>
                            <td colspan="6" class="px-4 py-4 text-center text-gray-500">Nenhum log encontrado.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($logsData['total_pages'] > 1): ?>
            <div class="flex items-center justify-between mt-4">
                <div class="text-sm text-gray-600">
                    Página <?php echo $logsData['page']; ?> de <?php echo $logsData['total_pages']; ?>
                    (<?php echo $logsData['total']; ?> registros)
                </div>
                <div class="space-x-2">
                    <?php if ($logsData['page'] > 1): ?>
                        <a href="?action=index&page=<?php echo $logsData['page'] - 1; ?>&per_page=<?php echo $logsData['per_page']; ?>" class="px-3 py-1 bg-gray-200 text-gray-700 rounded">Anterior</a>
                    <?php endif; ?>
                    <?php if ($logsData['page'] < $logsData['total_pages']): ?>
                        <a href="?action=index&page=<?php echo $logsData['page'] + 1; ?>&per_page=<?php echo $logsData['per_page']; ?>" class="px-3 py-1 bg-theme-color text-white rounded">Próxima</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
