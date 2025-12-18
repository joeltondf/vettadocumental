    <h1 class="text-3xl font-bold mb-4">Relatório dos Vendedores</h1>

    <div class="bg-white p-4 rounded-lg shadow-md mb-6">
        <form action="vendas.php" method="GET">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-6 gap-4 items-end">
                <div class="w-full">
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
                <div class="w-full">
                    <label for="cliente_id" class="block text-sm font-medium text-gray-700">Cliente</label>
                    <select name="cliente_id" id="cliente_id" class="mt-1 block w-full p-2 border-gray-300 rounded-md">
                        <option value="">Todos</option>
                        <?php foreach ($clientes as $cliente): ?>
                            <option value="<?php echo $cliente['id']; ?>" <?php echo (isset($filtros['cliente_id']) && $filtros['cliente_id'] == $cliente['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cliente['nome'] ?? 'Sem nome'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="w-full">
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
                <div class="w-full">
                    <label for="data_conversao_inicio" class="block text-sm font-medium text-gray-700">Data do Orçamento Omie (Início)</label>
                    <input type="date" name="data_conversao_inicio" id="data_conversao_inicio" value="<?php echo htmlspecialchars($filtros['data_conversao_inicio'] ?? ''); ?>" class="mt-1 block w-full p-2 border-gray-300 rounded-md">
                </div>
                <div class="w-full">
                    <label for="data_conversao_fim" class="block text-sm font-medium text-gray-700">Data do Orçamento Omie (Fim)</label>
                    <input type="date" name="data_conversao_fim" id="data_conversao_fim" value="<?php echo htmlspecialchars($filtros['data_conversao_fim'] ?? ''); ?>" class="mt-1 block w-full p-2 border-gray-300 rounded-md">
                </div>
                <div class="flex space-x-2 no-print sm:col-span-2 lg:col-span-1">
                    <button type="submit" class="w-full bg-blue-600 text-white py-2 px-4 rounded-md hover:bg-blue-700">Filtrar</button>
                    <a href="vendas.php" class="w-full text-center bg-gray-300 text-gray-800 py-2 px-4 rounded-md hover:bg-gray-400">Limpar</a>
                </div>
            </div>
        </form>
        <p class="text-sm text-gray-600 mt-3">A Data do Orçamento Omie reflete exclusivamente a data de inclusão no Omie; se estiver vazia, nada é exibido. Quando houver SDR, o vendedor recebe 4,5% (de 5%) e o SDR 0,5%.</p>
        <div class="flex flex-wrap gap-2 mt-4 no-print">
            <?php $queryString = http_build_query(array_filter($filtros)); ?>
            <a href="vendas.php?action=export_csv<?php echo $queryString ? '&' . $queryString : ''; ?>" class="bg-gray-200 text-gray-800 py-2 px-4 rounded-md hover:bg-gray-300">Exportar CSV</a>
            <a href="vendas.php?action=print<?php echo $queryString ? '&' . $queryString : ''; ?>" class="bg-gray-200 text-gray-800 py-2 px-4 rounded-md hover:bg-gray-300" target="_blank" rel="noopener">Imprimir</a>
            <button onclick="window.print()" class="bg-gray-200 text-gray-800 py-2 px-4 rounded-md hover:bg-gray-300">Imprimir (Atual)</button>
        </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 mb-8">
        <div class="bg-green-100 p-4 rounded-lg shadow text-center">
            <h4 class="text-lg font-semibold text-green-800">Valor Total Vendido</h4>
            <p class="text-2xl md:text-3xl font-bold text-green-900 mt-2">R$ <?php echo number_format($stats['valor_total_vendido'], 2, ',', '.'); ?></p>
        </div>
        <div class="bg-indigo-100 p-4 rounded-lg shadow text-center">
            <h4 class="text-lg font-semibold text-indigo-800">Ticket Médio</h4>
            <p class="text-2xl md:text-3xl font-bold text-indigo-900 mt-2">R$ <?php echo number_format($stats['ticket_medio'], 2, ',', '.'); ?></p>
        </div>
        <div class="bg-yellow-100 p-4 rounded-lg shadow text-center">
            <h4 class="text-lg font-semibold text-yellow-800">Comissão Vendedor</h4>
            <p class="text-2xl md:text-3xl font-bold text-yellow-900 mt-2">R$ <?php echo number_format($stats['comissao_vendedor'], 2, ',', '.'); ?></p>
        </div>
        <div class="bg-blue-100 p-4 rounded-lg shadow text-center">
            <h4 class="text-lg font-semibold text-blue-800">Comissão SDR</h4>
            <p class="text-2xl md:text-3xl font-bold text-blue-900 mt-2">R$ <?php echo number_format($stats['comissao_sdr'], 2, ',', '.'); ?></p>
        </div>
        <div class="bg-purple-100 p-4 rounded-lg shadow text-center">
            <h4 class="text-lg font-semibold text-purple-800">Processos</h4>
            <p class="text-2xl md:text-3xl font-bold text-purple-900 mt-2"><?php echo count($processosFiltrados); ?></p>
        </div>
        <div class="bg-white shadow-md p-4 rounded-lg">
            <h3 class="text-lg font-semibold border-b pb-3 mb-3">Ranking de Vendedores</h3>
            <ol class="list-decimal list-inside space-y-2 text-sm">
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
    
    <div class="bg-white shadow-md rounded-lg overflow-hidden mt-6">
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
            <table class="min-w-full leading-normal text-xs sm:text-sm">
                <thead>
                    <tr>
                        <th class="px-3 py-2 border-b-2 text-left whitespace-nowrap min-w-[180px]">Processo</th>
                        <th class="px-3 py-2 border-b-2 text-left whitespace-nowrap w-20">OMIE</th>
                        <th class="px-3 py-2 border-b-2 text-left whitespace-nowrap min-w-[180px]">Cliente / Assessoria</th>
                        <th class="px-3 py-2 border-b-2 text-left whitespace-nowrap">Data Orçamento Omie</th>
                        <th class="px-3 py-2 border-b-2 text-right whitespace-nowrap">Valor Total</th>
                        <th class="px-3 py-2 border-b-2 text-left whitespace-nowrap min-w-[140px]">Vendedor</th>
                        <th class="px-3 py-2 border-b-2 text-right text-yellow-700 font-semibold whitespace-nowrap">% Comissão Vend.</th>
                        <th class="px-3 py-2 border-b-2 text-right text-yellow-700 font-semibold whitespace-nowrap">Comissão Vend.</th>
                        <th class="px-3 py-2 border-b-2 text-left whitespace-nowrap min-w-[140px]">SDR</th>
                        <th class="px-3 py-2 border-b-2 text-right text-blue-700 font-semibold whitespace-nowrap">% Comissão SDR</th>
                        <th class="px-3 py-2 border-b-2 text-right text-blue-700 font-semibold whitespace-nowrap">Comissão SDR</th>
                        <th class="px-3 py-2 border-b-2 text-center whitespace-nowrap">Situação de Pagamento</th>
                        <th class="px-3 py-2 border-b-2 text-center whitespace-nowrap">Pagamento</th>
                        <th class="px-3 py-2 border-b-2 text-center whitespace-nowrap">Status do Serviço</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($processosFiltrados)): ?>
                        <tr><td colspan="14" class="text-center p-5 text-sm">Nenhum processo encontrado para os filtros selecionados.</td></tr>
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

                                $statusServico = trim((string) ($proc['status_processo'] ?? '—'));
                                $badgeServico = [
                                    'classes' => 'bg-gray-100 text-gray-800',
                                    'label' => $statusServico !== '' ? $statusServico : '—',
                                ];

                                if (stripos($statusServico, 'conclu') !== false || stripos($statusServico, 'finaliz') !== false) {
                                    $badgeServico['classes'] = 'bg-green-100 text-green-800';
                                } elseif (stripos($statusServico, 'andamento') !== false) {
                                    $badgeServico['classes'] = 'bg-yellow-100 text-yellow-800';
                                }
                            ?>
                            <tr class="text-[11px] sm:text-xs">
                                <td class="px-3 py-2 border-b align-top">
                                    <a href="processos.php?action=view&id=<?php echo $proc['id']; ?>" class="text-blue-600 hover:underline block truncate" title="<?php echo htmlspecialchars($proc['titulo']); ?>">
                                        <?php echo htmlspecialchars($proc['titulo']); ?>
                                    </a>
                                </td>
                                <td class="px-3 py-2 border-b align-top whitespace-nowrap w-20" title="<?php echo htmlspecialchars($proc['os_numero_omie'] ?? ''); ?>">
                                    <?php
                                        $numeroOmie = trim((string) ($proc['os_numero_omie'] ?? ''));
                                        echo $numeroOmie !== '' ? htmlspecialchars(mb_substr($numeroOmie, -5)) : '—';
                                    ?>
                                </td>
                                <td class="px-3 py-2 border-b align-top">
                                    <div class="flex flex-col gap-1 max-w-[220px]">
                                        <span class="font-semibold text-gray-800 truncate" title="<?php echo htmlspecialchars($proc['nome_cliente'] ?? '—'); ?>"><?php echo htmlspecialchars($proc['nome_cliente'] ?? '—'); ?></span>
                                        <?php if (($proc['tipo_assessoria'] ?? '') === 'Mensalista'): ?>
                                            <span class="bg-blue-100 text-blue-800 text-[10px] sm:text-[11px] font-medium px-2 py-1 rounded inline-flex w-fit">Mensalista</span>
                                        <?php else: ?>
                                            <span class="bg-green-100 text-green-800 text-[10px] sm:text-[11px] font-medium px-2 py-1 rounded inline-flex w-fit">À vista</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="px-3 py-2 border-b whitespace-nowrap align-top">
                                    <?php
                                        $dataOrcamentoOmie = $proc['data_inclusao_omie'] ?? null;
                                        echo !empty($dataOrcamentoOmie) ? date('d/m/Y H:i', strtotime($dataOrcamentoOmie)) : '—';
                                    ?>
                                </td>
                                <td class="px-3 py-2 border-b text-right whitespace-nowrap align-top">R$ <?php echo number_format($proc['valor_total'], 2, ',', '.'); ?></td>
                                <td class="px-3 py-2 border-b whitespace-nowrap min-w-[140px] align-top">
                                    <span class="text-purple-700 text-xs mr-1">👤</span>
                                    <span class="inline-block truncate max-w-[140px]" title="<?php echo htmlspecialchars($formatVendorName($proc['nome_vendedor'] ?? null)); ?>"><?php echo htmlspecialchars($formatVendorName($proc['nome_vendedor'] ?? null)); ?></span>
                                </td>
                                <td class="px-3 py-2 border-b text-right text-yellow-700 font-semibold whitespace-nowrap align-top">
                                    <span class="inline-flex items-center justify-end w-full"><span class="mr-1 text-yellow-500">👤</span><?php echo number_format($proc['percentual_comissao_vendedor'] ?? 0, 2, ',', '.'); ?>%</span>
                                </td>
                                <td class="px-3 py-2 border-b text-right text-yellow-700 font-semibold whitespace-nowrap align-top">
                                    <span class="inline-flex items-center justify-end w-full"><span class="mr-1 text-yellow-500">👤</span>R$ <?php echo number_format($proc['valor_comissao_vendedor'] ?? 0, 2, ',', '.'); ?></span>
                                </td>
                                <td class="px-3 py-2 border-b whitespace-nowrap min-w-[140px] align-top">
                                    <?php
                                        $sdrNome = trim((string) ($proc['nome_sdr'] ?? ''));
                                        $sdrLabel = $sdrNome !== '' ? $sdrNome : 'Sem SDR';
                                    ?>
                                    <span class="mr-1 text-blue-500">🎯</span>
                                    <span class="text-xs whitespace-nowrap"><?php echo htmlspecialchars($sdrLabel); ?></span>
                                </td>
                                <td class="px-3 py-2 border-b text-right text-blue-700 font-semibold whitespace-nowrap align-top">
                                    <span class="inline-flex items-center justify-end w-full"><span class="mr-1 text-blue-500">🎯</span><?php echo number_format($proc['percentual_comissao_sdr'] ?? 0, 2, ',', '.'); ?>%</span>
                                </td>
                                <td class="px-3 py-2 border-b text-right text-blue-700 font-semibold whitespace-nowrap align-top">
                                    <span class="inline-flex items-center justify-end w-full"><span class="mr-1 text-blue-500">🎯</span>R$ <?php echo number_format($proc['valor_comissao_sdr'] ?? 0, 2, ',', '.'); ?></span>
                                </td>
                                <td class="px-3 py-2 border-b text-center whitespace-nowrap align-top">
                                    <span class="inline-flex items-center px-3 py-1 text-xs font-semibold rounded-full <?php echo $statusConfig['class']; ?>">
                                        <?php echo $statusConfig['label']; ?>
                                    </span>
                                </td>
                                <td class="px-3 py-2 border-b text-center whitespace-nowrap align-top">
                                    <span class="inline-flex items-center px-3 py-1 text-xs font-semibold rounded-full <?php echo $corPagamento[$tipoPagamento] ?? 'bg-gray-100 text-gray-800'; ?>">
                                        <?php echo $tipoPagamento; ?>
                                    </span>
                                </td>
                                <td class="px-3 py-2 border-b text-center whitespace-nowrap align-top">
                                    <span class="inline-flex items-center px-3 py-1 text-xs font-semibold rounded-full <?php echo $badgeServico['classes']; ?>">
                                        <?php echo htmlspecialchars($badgeServico['label']); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
