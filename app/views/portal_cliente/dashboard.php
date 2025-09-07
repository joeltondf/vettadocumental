    <h1 class="text-2xl font-bold text-gray-800 mb-2">Meus Processos</h1>
    <p class="text-gray-600 mb-6">Acompanhe aqui o andamento de todos os seus serviços contratados.</p>

    <div class="bg-white shadow-md rounded-lg overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Processo</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Data de Entrada</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (empty($processos)): ?>
                        <tr>
                            <td colspan="3" class="px-6 py-12 text-center text-gray-500">Você ainda não possui processos cadastrados.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($processos as $processo): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap font-medium text-gray-800"><?php echo htmlspecialchars($processo['titulo']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-gray-600"><?php echo date('d/m/Y', strtotime($processo['data_criacao'])); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">
                                        <?php echo htmlspecialchars($processo['status_processo']); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
