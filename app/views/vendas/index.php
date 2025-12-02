    <h1 class="text-3xl font-bold mb-4">Relatório dos Vendedores</h1>

    <div class="bg-white p-4 rounded-lg shadow-md mb-6">
        <form action="vendas.php" method="GET">
            <div class="grid grid-cols-1 md:grid-cols-5 gap-4 items-end">
                <div>
                    <label for="vendedor_id" class="block text-sm font-medium text-gray-700">Vendedor</label>
                    <select name="vendedor_id" id="vendedor_id" class="mt-1 block w-full p-2 border-gray-300 rounded-md">
                        <option value="">Todos</option>
                        <?php
                            $formatVendorName = static function ($value): string {
                                $trimmed = trim((string) $value);

                                return $trimmed !== '' ? $trimmed : 'Sistema';
                            };

                            $paymentStatuses = [
                                'pago' => [
                                    'label' => 'Pago',
                                    'class' => 'bg-green-100 text-green-800'
                                ],
                                'parcial' => [
                                    'label' => 'Parcial',
                                    'class' => 'bg-yellow-100 text-yellow-800'
                                ],
                                'pendente' => [
                                    'label' => 'Pendente',
                                    'class' => 'bg-red-100 text-red-800'
                                ],
                            ];
                        ?>
                        <?php foreach ($vendedores as $vendedor): ?>
                            <option value="<?php echo $vendedor['id']; ?>" <?php echo (isset($filtros['vendedor_id']) && $filtros['vendedor_id'] == $vendedor['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($formatVendorName($vendedor['nome_vendedor'] ?? null)); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="sdr_id" class="block text-sm font-medium text-gray-700">SDR</label>
                    <select name="sdr_id" id="sdr_id" class="mt-1 block w-full p-2 border-gray-300 rounded-md">
                        <option value="">Todos</option>
                        <?php foreach ($sdrs as $sdr): ?>
                            <option value="<?php echo $sdr['id']; ?>" <?php echo (isset($filtros['sdr_id']) && $filtros['sdr_id'] == $sdr['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($formatVendorName($sdr['nome_completo'] ?? null)); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="data_inicio" class="block text-sm font-medium text-gray-700">Data de Conversão (Início)</label>
                    <input type="date" name="data_inicio" id="data_inicio" value="<?php echo htmlspecialchars($filtros['data_inicio'] ?? ''); ?>" class="mt-1 block w-full p-2 border-gray-300 rounded-md">
                </div>
                <div>
                    <label for="data_fim" class="block text-sm font-medium text-gray-700">Data de Conversão (Fim)</label>
                    <input type="date" name="data_fim" id="data_fim" value="<?php echo htmlspecialchars($filtros['data_fim'] ?? ''); ?>" class="mt-1 block w-full p-2 border-gray-300 rounded-md">
                </div>
                <div class="flex space-x-2 no-print">
                    <button type="submit" class="w-full bg-blue-600 text-white py-2 px-4 rounded-md hover:bg-blue-700">Filtrar</button>
                    <a href="vendas.php" class="w-full text-center bg-gray-300 text-gray-800 py-2 px-4 rounded-md hover:bg-gray-400">Limpar</a>
                </div>
            </div>
        </form>
        <p class="text-sm text-gray-600 mt-3">O período considera a data de conversão/serviço. Quando houver SDR, o vendedor recebe 4,5% (de 5%) e o SDR 0,5%.</p>
        <div class="flex flex-wrap gap-2 mt-4 no-print">
            <?php $queryString = http_build_query(array_filter($filtros)); ?>
            <a href="vendas.php?action=export_csv<?php echo $queryString ? '&' . $queryString : ''; ?>" class="bg-gray-200 text-gray-800 py-2 px-4 rounded-md hover:bg-gray-300">Exportar CSV</a>
            <a href="vendas.php?action=print<?php echo $queryString ? '&' . $queryString : ''; ?>" class="bg-gray-200 text-gray-800 py-2 px-4 rounded-md hover:bg-gray-300" target="_blank" rel="noopener">Imprimir</a>
            <button onclick="window.print()" class="bg-gray-200 text-gray-800 py-2 px-4 rounded-md hover:bg-gray-300">Imprimir (Atual)</button>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-6 gap-6 mb-8">
        <div class="bg-green-100 p-6 rounded-lg shadow text-center">
            <h4 class="text-lg font-semibold text-green-800">Valor Total Vendido</h4>
            <p class="text-3xl font-bold text-green-900 mt-2">R$ <?php echo number_format($stats['valor_total_vendido'], 2, ',', '.'); ?></p>
        </div>
        <div class="bg-indigo-100 p-6 rounded-lg shadow text-center">
            <h4 class="text-lg font-semibold text-indigo-800">Ticket Médio</h4>
            <p class="text-3xl font-bold text-indigo-900 mt-2">R$ <?php echo number_format($stats['ticket_medio'], 2, ',', '.'); ?></p>
        </div>
        <div class="bg-yellow-100 p-6 rounded-lg shadow text-center">
            <h4 class="text-lg font-semibold text-yellow-800">Comissão Vendedor</h4>
            <p class="text-3xl font-bold text-yellow-900 mt-2">R$ <?php echo number_format($stats['comissao_vendedor'], 2, ',', '.'); ?></p>
        </div>
        <div class="bg-blue-100 p-6 rounded-lg shadow text-center">
            <h4 class="text-lg font-semibold text-blue-800">Comissão SDR</h4>
            <p class="text-3xl font-bold text-blue-900 mt-2">R$ <?php echo number_format($stats['comissao_sdr'], 2, ',', '.'); ?></p>
        </div>
        <div class="bg-purple-100 p-6 rounded-lg shadow text-center">
            <h4 class="text-lg font-semibold text-purple-800">Processos</h4>
            <p class="text-3xl font-bold text-purple-900 mt-2"><?php echo count($processosFiltrados); ?></p>
        </div>
        <div class="bg-white shadow-md p-6 rounded-lg">
            <h3 class="text-xl font-semibold border-b pb-3 mb-3">Ranking de Vendedores</h3>
            <ol class="list-decimal list-inside space-y-2">
                <?php if (empty($stats['ranking_vendedores'])): ?>
                    <li class="text-gray-500 text-sm">Nenhum dado para exibir.</li>
                <?php else: ?>
                    <?php foreach ($stats['ranking_vendedores'] as $nome => $valor): ?>
                        <li class="p-2 rounded-md hover:bg-gray-100 flex justify-between items-center">
                            <span class="font-semibold text-sm"><?php echo htmlspecialchars($nome); ?></span>
                            <span class="text-green-700 font-bold">R$ <?php echo number_format($valor, 2, ',', '.'); ?></span>
                        </li>
                    <?php endforeach; ?>
                <?php endif; ?>
            </ol>
        </div>
    </div>
    
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 bg-white shadow-md rounded-lg overflow-hidden">
            <h3 class="text-xl font-semibold p-4 border-b">Detalhes dos Processos</h3>
            <div class="overflow-x-auto">
                <?php if (!empty($isPrint)): ?>
                    <style>
                        @media print {
                            .no-print { display: none !important; }
                            nav, footer { display: none !important; }
                            body { margin: 0 16px; background: #fff; }
                        }
                    </style>
                <?php endif; ?>
                <table class="min-w-full leading-normal">
                    <thead>
                        <tr>
                            <th class="px-5 py-3 border-b-2 text-left text-sm">Data de Entrada</th>
                            <th class="px-5 py-3 border-b-2 text-left text-sm">Processo</th>
                            <th class="px-5 py-3 border-b-2 text-left text-sm">Vendedor</th>
                            <th class="px-5 py-3 border-b-2 text-left text-sm">Data de Conversão</th>
                            <th class="px-5 py-3 border-b-2 text-right text-sm">Valor Total</th>
                            <th class="px-5 py-3 border-b-2 text-right text-sm text-yellow-700 font-semibold">% Comissão Vend.</th>
                            <th class="px-5 py-3 border-b-2 text-right text-sm text-yellow-700 font-semibold">Comissão Vend.</th>
                            <th class="px-5 py-3 border-b-2 text-right text-sm text-blue-700 font-semibold">% Comissão SDR</th>
                            <th class="px-5 py-3 border-b-2 text-right text-sm text-blue-700 font-semibold">Comissão SDR</th>
                            <th class="px-5 py-3 border-b-2 text-center text-sm">Situação de Pagamento</th>
                            <th class="px-5 py-3 border-b-2 text-center text-sm">Pagamento</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($processosFiltrados)): ?>
                            <tr><td colspan="11" class="text-center p-5 text-sm">Nenhum processo encontrado para os filtros selecionados.</td></tr>
                        <?php else: ?>
                            <?php foreach ($processosFiltrados as $proc): ?>
                                <?php
                                    $statusKey = strtolower($proc['status_financeiro'] ?? 'pendente');
                                    $statusConfig = $paymentStatuses[$statusKey] ?? $paymentStatuses['pendente'];

                                    if ($statusKey == 'pago') {
                                        $tipoPagamento = 'Entrada';
                                    } elseif ($statusKey == 'parcial') {
                                        $tipoPagamento = 'Entrada/Pendente';
                                    } else {
                                        $tipoPagamento = 'Pendente';
                                    }
                                    $corPagamento = [
                                        'Entrada' => 'bg-green-100 text-green-800',
                                        'Entrada/Pendente' => 'bg-yellow-100 text-yellow-800',
                                        'Pendente' => 'bg-red-100 text-red-800',
                                    ];
                                ?>
                                <tr>
                                    <td class="px-5 py-4 border-b text-sm text-gray-500 text-center">
                                        <?php echo !empty($proc['data_criacao']) ? date('d/m/Y', strtotime($proc['data_criacao'])) : '—'; ?>
                                    </td>
                                    <td class="px-5 py-4 border-b text-sm"><a href="processos.php?action=view&id=<?php echo $proc['id']; ?>" class="text-blue-600 hover:underline">#<?php echo $proc['id']; ?> - <?php echo htmlspecialchars($proc['titulo']); ?></a></td>
                                    <td class="px-5 py-4 border-b text-sm flex items-center">
                                        <span class="mr-1 text-yellow-500">👤</span>
                                        <?php echo htmlspecialchars($formatVendorName($proc['nome_vendedor'] ?? null)); ?>
                                    </td>
                                    <td class="px-5 py-4 border-b text-sm">
                                        <?php
                                            $dataServico = $proc['data_conversao'] ?? $proc['data_filtro'] ?? null;
                                            echo !empty($dataServico) ? date('d/m/Y', strtotime($dataServico)) : '—';
                                        ?>
                                    </td>
                                    <td class="px-5 py-4 border-b text-right text-sm">R$ <?php echo number_format($proc['valor_total'], 2, ',', '.'); ?></td>
                                    <td class="px-5 py-4 border-b text-right text-sm text-yellow-700 font-semibold">
                                        <span class="inline-flex items-center justify-end w-full"><span class="mr-1 text-yellow-500">👤</span><?php echo number_format($proc['percentual_comissao_vendedor'] ?? 0, 2, ',', '.'); ?>%</span>
                                    </td>
                                    <td class="px-5 py-4 border-b text-right text-sm text-yellow-700 font-semibold">
                                        <span class="inline-flex items-center justify-end w-full"><span class="mr-1 text-yellow-500">👤</span>R$ <?php echo number_format($proc['valor_comissao_vendedor'] ?? 0, 2, ',', '.'); ?></span>
                                    </td>
                                    <td class="px-5 py-4 border-b text-right text-sm text-blue-700 font-semibold">
                                        <span class="inline-flex items-center justify-end w-full"><span class="mr-1 text-blue-500">🎯</span><?php echo number_format($proc['percentual_comissao_sdr'] ?? 0, 2, ',', '.'); ?>%</span>
                                    </td>
                                    <td class="px-5 py-4 border-b text-right text-sm text-blue-700 font-semibold">
                                        <span class="inline-flex items-center justify-end w-full"><span class="mr-1 text-blue-500">🎯</span>R$ <?php echo number_format($proc['valor_comissao_sdr'] ?? 0, 2, ',', '.'); ?></span>
                                    </td>
                                    <td class="px-5 py-4 border-b text-center text-sm">
                                        <span class="inline-flex items-center px-3 py-1 text-sm font-semibold rounded-full <?php echo $statusConfig['class']; ?>">
                                            <?php echo $statusConfig['label']; ?>
                                        </span>
                                    </td>
                                    <td class="px-5 py-4 border-b text-center text-sm">
                                        <span class="inline-flex items-center px-3 py-1 text-sm font-semibold rounded-full <?php echo $corPagamento[$tipoPagamento] ?? 'bg-gray-100 text-gray-800'; ?>">
                                            <?php echo $tipoPagamento; ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
