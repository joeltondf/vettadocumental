<div class="container mx-auto px-4 sm:px-6 lg:px-8 py-8 bg-gray-50">

    <div class="mb-6 border-b pb-4">
        <h1 class="text-2xl font-bold text-gray-800">Painel de Notificações</h1>
        <p class="mt-1 text-sm text-gray-500">Processos que requerem sua atenção.</p>
    </div>

    <?php $isManager = in_array($_SESSION['user_perfil'] ?? '', ['admin', 'gerencia', 'supervisor'], true); ?>
    <div class="bg-white shadow-md rounded-lg overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Processo
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Tipo de Serviço
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Cliente
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Status
                        </th>
                        <th scope="col" class="relative px-6 py-3">
                            <span class="sr-only">Ações</span>
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($processos_pendentes as $processo): ?>
                        <?php
                            $statusAtual = $processo['status'] ?? $processo['status_processo'] ?? '';
                            $statusNormalized = mb_strtolower($statusAtual);
                            $isBudgetPending = $statusNormalized === 'orçamento pendente';
                            $isServicePending = $statusNormalized === 'serviço pendente';
                        ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                #<?= htmlspecialchars($processo['titulo']) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                <?= htmlspecialchars($processo['tipo_servico']) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                <?= htmlspecialchars($processo['nome_cliente']) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                <?php
                                    // Define classes de cor com base no status do processo
                                    $status_class = 'bg-gray-100 text-gray-800'; // Cor padrão
                                    if ($processo['status'] === 'Pendente') {
                                        $status_class = 'bg-yellow-100 text-yellow-800';
                                    }
                                ?>
                                <span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full <?= $status_class ?>">
                                    <?= htmlspecialchars($processo['status']) ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <div class="flex flex-col space-y-3 items-end">
                                    <a href="processos.php?action=view&id=<?= $processo['id']; ?>" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                        Ver Detalhes
                                    </a>
                                    <?php if ($isManager && $isBudgetPending): ?>
                                        <div class="flex flex-col w-full space-y-2">
                                            <div class="flex flex-wrap justify-end gap-2">
                                                <a href="processos.php?action=aprovar_orcamento&id=<?= $processo['id']; ?>" class="inline-flex justify-center items-center px-4 py-2 text-sm font-semibold rounded-md bg-green-600 text-white shadow hover:bg-green-700">
                                                    Aprovar orçamento
                                                </a>
                                                <form action="processos.php?action=recusar_orcamento" method="POST" class="flex flex-wrap items-center justify-end gap-2">
                                                    <input type="hidden" name="id" value="<?= $processo['id']; ?>">
                                                    <input type="text" name="motivo_recusa" class="w-full sm:w-56 px-3 py-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-transparent" placeholder="Motivo da recusa" required>
                                                    <button type="submit" class="inline-flex justify-center items-center px-4 py-2 text-sm font-semibold rounded-md bg-red-600 text-white shadow hover:bg-red-700">
                                                        Recusar orçamento
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    <?php elseif ($isManager && $isServicePending): ?>
                                        <div class="flex flex-wrap justify-end gap-2 w-full">
                                            <form action="processos.php?action=change_status" method="POST" class="inline-flex">
                                                <input type="hidden" name="id" value="<?= $processo['id']; ?>">
                                                <input type="hidden" name="status_processo" value="Serviço em andamento">
                                                <button type="submit" class="inline-flex justify-center items-center px-4 py-2 text-sm font-semibold rounded-md bg-green-600 text-white shadow hover:bg-green-700">
                                                    Aprovar serviço
                                                </button>
                                            </form>
                                            <form action="processos.php?action=change_status" method="POST" class="inline-flex">
                                                <input type="hidden" name="id" value="<?= $processo['id']; ?>">
                                                <input type="hidden" name="status_processo" value="Orçamento Pendente">
                                                <button type="submit" class="inline-flex justify-center items-center px-4 py-2 text-sm font-semibold rounded-md bg-yellow-600 text-white shadow hover:bg-yellow-700">
                                                    Solicitar ajustes
                                                </button>
                                            </form>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>