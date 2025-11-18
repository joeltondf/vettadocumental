    <div class="flex items-center justify-between mb-6">
        <h1 class="text-3xl font-bold text-gray-800">Controle Financeiro</h1>

        <?php // Apenas perfis de admin ou gerencia podem ver o botão
        if (isset($_SESSION['user_perfil']) && in_array($_SESSION['user_perfil'], ['admin', 'gerencia'])): ?>
        <div class="flex space-x-4">
            <a href="produtos_orcamento.php" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg shadow-md flex items-center">
                <i class="fas fa-box-open mr-2"></i>
                Produtos/Documentos
            </a>

            <a href="categorias.php" class="bg-purple-600 hover:bg-purple-700 text-white font-bold py-2 px-4 rounded-lg shadow-md flex items-center">
                <i class="fas fa-cog mr-2"></i>
                Gerenciar Grupos
            </a>
        </div>
        <?php endif; ?>
    </div>

    <div class="bg-white p-6 rounded-lg shadow-md mb-6">
        <form method="GET" action="fluxo_caixa.php">
            <h3 class="text-xl font-semibold mb-4">Filtrar Período</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label for="data_inicio" class="block text-sm font-medium text-gray-700 mb-1">Data Inicial</label>
                    <div class="relative">
                        <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                            <i class="fas fa-calendar-alt text-gray-400"></i>
                        </div>
                        <input type="date" name="data_inicio" id="data_inicio" value="<?php echo htmlspecialchars($_GET['data_inicio'] ?? ''); ?>" class="block w-full rounded-lg border-gray-300 pl-10 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm py-2.5">
                    </div>
                </div>
                <div>
                    <label for="data_fim" class="block text-sm font-medium text-gray-700 mb-1">Data Final</label>
                    <div class="relative">
                        <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                        <i class="fas fa-calendar-alt text-gray-400"></i>
                        </div>
                        <input type="date" name="data_fim" id="data_fim" value="<?php echo htmlspecialchars($_GET['data_fim'] ?? ''); ?>" class="block w-full rounded-lg border-gray-300 pl-10 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm py-2.5">
                    </div>
                </div>
                <div class="self-end">
                    <button type="submit" class="w-full bg-blue-600 text-white py-2 px-4 rounded-md hover:bg-blue-700">Filtrar</button>
                </div>
            </div>
        </form>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <div class="bg-green-100 p-6 rounded-lg shadow-md text-center">
            <h4 class="text-lg font-semibold text-green-800">Total de Receitas</h4>
            <p class="text-3xl font-bold text-green-900 mt-2">R$ <?php echo number_format($receitas, 2, ',', '.'); ?></p>
        </div>
        <div class="bg-red-100 p-6 rounded-lg shadow-md text-center">
            <h4 class="text-lg font-semibold text-red-800">Total de Despesas</h4>
            <p class="text-3xl font-bold text-red-900 mt-2">R$ <?php echo number_format($despesas, 2, ',', '.'); ?></p>
        </div>
        <div class="bg-indigo-100 p-6 rounded-lg shadow-md text-center">
            <h4 class="text-lg font-semibold text-indigo-800">Resultado do Período</h4>
            <p class="text-3xl font-bold text-indigo-900 mt-2">R$ <?php echo number_format($resultado, 2, ',', '.'); ?></p>
        </div>
    </div>
    
<div class="bg-white p-8 rounded-md shadow-lg border border-gray-200 mb-8">
    <h3 id="form-title" class="text-2xl font-bold text-gray-800 mb-6 border-b-2 border-gray-100 pb-5">Adicionar Novo Lançamento 💰</h3>
    <form id="lancamento-form" action="fluxo_caixa.php?action=store" method="POST" class="space-y-6">
        <input type="hidden" name="id" id="lancamento_id">
        <input type="hidden" name="finalizado" id="finalizado" value="0">

        <div id="finalizado-alert" class="hidden bg-yellow-50 border border-yellow-200 text-yellow-800 px-4 py-3 rounded-md">
            Este lançamento está finalizado e não pode ser editado. Para correções, registre um ajuste.
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="md:col-span-2">
                <label for="categoria_id" class="block text-sm font-semibold text-gray-700 mb-2">Grupo / Descrição</label>
                <div class="relative">
                    <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-4 text-gray-400">
                        <i class="fas fa-tags"></i>
                    </div>
                    <select name="categoria_id" id="categoria_id" required class="block w-full rounded-md border-gray-300 pl-12 pr-4 shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm py-3 transition-colors duration-200 border border-gray-300">
                        <option value="">Selecione para ver o tipo...</option>
                        <?php
                        $grouped_categorias = [];
                        foreach ($categorias as $cat) {
                            $grouped_categorias[$cat['grupo_principal']][] = $cat;
                        }
                        foreach ($grouped_categorias as $grupo => $items):
                        ?>
                            <optgroup label="<?php echo htmlspecialchars($grupo); ?>">
                                <?php foreach ($items as $item): ?>
                                    <?php
                                    // *** Alteração importante ***
                                    // Verifica se a categoria é de serviço (servico_tipo diferente de 'Nenhum').
                                    // Se for, pula a exibição para evitar lançamentos de serviço nesta tela.
                                    if ($item['servico_tipo'] !== 'Nenhum') {
                                        continue;
                                    }
                                    $textColorClass = $item['tipo_lancamento'] === 'RECEITA' ? 'text-green-600' : 'text-red-600';
                                    ?>
                                    <option value="<?php echo $item['id']; ?>" class="<?php echo $textColorClass; ?> font-medium">
                                        <?php echo htmlspecialchars($item['nome_categoria']); ?> (<?php echo $item['tipo_lancamento']; ?>)
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- resto do formulário continua inalterado -->
            <div>
                <label for="valor" class="block text-sm font-semibold text-gray-700 mb-2">Valor</label>
                <div class="relative rounded-md shadow-sm">
                    <input
                        type="text"
                        name="valor"
                        id="valor"
                        data-currency-input
                        required
                        class="block w-full rounded-md border-gray-300 pr-4 pl-12 focus:ring-blue-500 focus:border-blue-500 text-sm py-3 border border-gray-300"
                        placeholder="R$ 0,00"
                    >
                    <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-4 text-gray-400">
                        <i class="fas fa-dollar-sign"></i>
                    </div>
                </div>
            </div>

            <div>
                <label for="data_lancamento" class="block text-sm font-semibold text-gray-700 mb-2">Data do Lançamento</label>
                <div class="relative">
                    <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-4 text-gray-400">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                    <input type="date" name="data_lancamento" id="data_lancamento" required value="<?php echo date('Y-m-d'); ?>" class="block w-full rounded-md border-gray-300 pl-12 pr-4 shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm py-3 border border-gray-300">
                </div>
            </div>
        </div>

        <div>
            <label for="descricao" class="block text-sm font-semibold text-gray-700 mb-2">Observações</label>
            <div class="relative">
                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-4 text-gray-400">
                    <i class="fas fa-pencil-alt"></i>
                </div>
                <input type="text" name="descricao" id="descricao" required class="block w-full rounded-md border-gray-300 pl-12 pr-4 shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm py-3 border border-gray-300" placeholder="Ex: Pagamento de conta de luz">
            </div>
        </div>

        <div class="text-right mt-8 border-t border-gray-100 pt-6">
            <button id="submit-button" type="submit" class="bg-green-600 hover:bg-green-700 text-white font-bold py-3 px-8 rounded-lg shadow-md transition-all duration-300 transform hover:scale-105">
                <i class="fas fa-check mr-2"></i>
                Salvar Lançamento
            </button>
        </div>
    </form>
</div>

    <div class="my-6 bg-white p-6 rounded-lg shadow">
        <h3 class="text-lg font-semibold text-gray-800 mb-4">
            Resumo de Serviços no Período Selecionado (<?= $mesRelatorio ?>)
        </h3>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <?php
            $serviceCards = [
                'Tradução' => ['title' => 'Serviços de Tradução', 'wrapper' => 'bg-blue-50 border-blue-200', 'titleClass' => 'text-blue-800', 'accent' => 'text-blue-900'],
                'CRC' => ['title' => 'Serviços de CRC', 'wrapper' => 'bg-purple-50 border-purple-200', 'titleClass' => 'text-purple-800', 'accent' => 'text-purple-900'],
                'Outros' => ['title' => 'Serviços Outros', 'wrapper' => 'bg-gray-50 border-gray-200', 'titleClass' => 'text-gray-800', 'accent' => 'text-gray-900'],
            ];
            foreach ($serviceCards as $tipo => $config):
                $dados = $relatorioServicos[$tipo] ?? ['quantidade' => 0, 'valor_total' => 0];
            ?>
                <div class="<?= htmlspecialchars($config['wrapper']); ?> p-4 rounded-lg border">
                    <h4 class="text-md font-semibold <?= htmlspecialchars($config['titleClass']); ?>">
                        <?= htmlspecialchars($config['title']); ?>
                    </h4>
                    <p class="text-2xl font-bold <?= htmlspecialchars($config['accent']); ?> mt-2">
                        <?= (int) $dados['quantidade']; ?>
                    </p>
                    <p class="text-sm text-gray-600">serviços realizados</p>
                    <p class="text-lg font-semibold text-green-700 mt-3">
                        R$ <?= number_format((float) $dados['valor_total'], 2, ',', '.'); ?>
                    </p>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="overflow-x-auto bg-white rounded-lg shadow">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-100">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Data</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Categoria</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Descrição</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Valor</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Ações</th>

                </tr>
            </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach($lancamentos as $lancamento): ?>
                        <?php
                            // Lógica para determinar classes e atributos da linha
                            $is_aggregated = isset($lancamento['eh_agregado']) && $lancamento['eh_agregado'];
                            $row_class = '';
                            $row_attrs = '';

                            if ($is_aggregated) {
                                // Linha agregada: cor neutra, cursor de ponteiro e classe para JS
                                $row_class = 'bg-blue-50 cursor-pointer hover:bg-blue-100 aggregated-row';
                                $row_attrs = 'data-lancamento-id="' . $lancamento['id'] . '"';
                            } else {
                                // Linha normal: cor baseada no tipo de lançamento
                                // A coluna agora é 'tipo_lancamento'
                                $row_class = $lancamento['tipo_lancamento'] == 'RECEITA' ? 'bg-green-50' : 'bg-red-50';
                            }
                        ?>

                        <tr class="<?php echo $row_class; ?>" <?php echo $row_attrs; ?>>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo date('d/m/Y', strtotime($lancamento['data_lancamento'])); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($lancamento['nome_categoria']); ?></td>
                            <td class="px-6 py-4 text-sm text-gray-900">
                                <?php echo htmlspecialchars($lancamento['descricao']); ?>
                                <?php if (!empty($lancamento['finalizado'])): ?>
                                    <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">Finalizado</span>
                                <?php endif; ?>
                                <?php if ($is_aggregated): ?>
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 inline-block ml-1 text-blue-600" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd" />
                                    <path fill-rule="evenodd" d="M10.5 6.5a.5.5 0 01.5.5v2.5H13a.5.5 0 010 1h-2.5V13a.5.5 0 01-1 0v-2.5H7a.5.5 0 010-1h2.5V7a.5.5 0 01.5-.5z" clip-rule="evenodd" />
                                    </svg>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-mono <?php echo $lancamento['tipo_lancamento'] == 'RECEITA' ? 'text-green-700' : 'text-red-700'; ?>">
                                <?php echo ($lancamento['tipo_lancamento'] == 'RECEITA' ? '+ ' : '- ') . 'R$ ' . number_format($lancamento['valor'], 2, ',', '.'); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center text-sm">
                                <?php if (!empty($lancamento['finalizado'])): ?>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">Finalizado</span>
                                <?php else: ?>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-yellow-100 text-yellow-800">Em edição</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-medium">
                                <div class="flex items-center justify-center space-x-3">
                                    <button type="button" class="text-indigo-600 hover:text-indigo-900"
                                            onclick='editLancamento(<?php echo json_encode($lancamento, JSON_HEX_APOS); ?>)'>
                                        <?php echo !empty($lancamento['finalizado']) ? 'Visualizar' : 'Editar'; ?>
                                    </button>

                                    <button type="button" class="text-blue-600 hover:text-blue-900" onclick="openHistoryModal(<?php echo $lancamento['id']; ?>)">Histórico</button>

                                    <button type="button" class="text-amber-600 hover:text-amber-900" onclick='openAdjustModal(<?php echo $lancamento['id']; ?>, <?php echo json_encode($lancamento['descricao'], JSON_HEX_APOS); ?>)'>Ajustar</button>

                                    <?php if (empty($lancamento['finalizado'])): ?>
                                        <form action="/fluxo_caixa.php?action=finalizar" method="POST" style="display: inline;">
                                            <input type="hidden" name="id" value="<?php echo $lancamento['id']; ?>">
                                            <button type="submit" class="text-green-700 hover:text-green-900" style="background:none; border:none; padding:0; font:inherit; cursor:pointer;">Finalizar</button>
                                        </form>
                                        <form action="/fluxo-caixa/delete" method="POST" style="display: inline;" onsubmit="return confirm('Tem certeza que deseja apagar este lançamento?');">
                                            <input type="hidden" name="id" value="<?php echo $lancamento['id']; ?>">
                                            <button type="submit" class="text-red-600 hover:text-red-900" style="background:none; border:none; padding:0; font:inherit; cursor:pointer;">
                                                Apagar
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        
                        <?php if ($is_aggregated): ?>
                            <tr class="detail-row hidden" id="details-<?php echo $lancamento['id']; ?>">
                                <td colspan="6" class="px-6 py-4 bg-gray-50">
                                    <div class="details-content text-center">
                                        <p class="text-gray-500">Carregando detalhes...</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>

                    <?php endforeach; ?>
                </tbody>
        </table>
    </div>

    <div id="history-modal" class="hidden fixed inset-0 bg-gray-900 bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-3xl p-6 relative">
            <button class="absolute top-3 right-3 text-gray-500 hover:text-gray-700" onclick="closeHistoryModal()">
                <i class="fas fa-times"></i>
            </button>
            <h3 class="text-xl font-semibold text-gray-800 mb-4">Histórico do Lançamento</h3>
            <div id="history-content" class="max-h-96 overflow-y-auto space-y-3"></div>
        </div>
    </div>

    <div id="adjust-modal" class="hidden fixed inset-0 bg-gray-900 bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-lg p-6 relative">
            <button class="absolute top-3 right-3 text-gray-500 hover:text-gray-700" onclick="closeAdjustModal()">
                <i class="fas fa-times"></i>
            </button>
            <h3 class="text-xl font-semibold text-gray-800 mb-4">Registrar Ajuste</h3>
            <form id="adjust-form" action="/fluxo_caixa.php?action=ajustar" method="POST" class="space-y-4">
                <input type="hidden" name="id" id="adjust-id">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Lançamento</label>
                    <p id="adjust-description" class="text-gray-800 text-sm"></p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1" for="adjust-valor">Valor do Ajuste</label>
                    <input type="text" name="valor" id="adjust-valor" class="w-full border rounded-md px-3 py-2" placeholder="Ex: -150,00" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1" for="adjust-motivo">Justificativa</label>
                    <textarea name="motivo" id="adjust-motivo" rows="3" class="w-full border rounded-md px-3 py-2" placeholder="Explique o motivo do ajuste" required></textarea>
                </div>
                <div class="text-right">
                    <button type="submit" class="bg-amber-600 hover:bg-amber-700 text-white font-semibold px-4 py-2 rounded-md">Salvar Ajuste</button>
                </div>
            </form>
        </div>
    </div>

<script>
    const lancamentoLogs = <?php echo json_encode($lancamentoLogs); ?>;

    // A função de edição agora está no escopo global, acessível pelo onclick.
    function setCurrencyValue(input, value) {
        if (!input) {
            return;
        }

        if (window.CurrencyMask && typeof window.CurrencyMask.setValue === 'function') {
            window.CurrencyMask.setValue(input, value ?? '');
            return;
        }

        input.value = value ?? '';
    }

    function editLancamento(lancamento) {
        const form = document.getElementById('lancamento-form');
        const title = document.getElementById('form-title');
        const idInput = document.getElementById('lancamento_id');
        const submitButton = form.querySelector('button[type="submit"]');
        const finalizadoInput = document.getElementById('finalizado');
        const alertBox = document.getElementById('finalizado-alert');
        const editableFields = form.querySelectorAll('input:not(#finalizado):not(#lancamento_id), select');

        // Muda o título e a ação do formulário
        title.textContent = 'Editar Lançamento';
        form.action = 'fluxo_caixa.php?action=update';

        // Preenche os campos do formulário
        idInput.value = lancamento.id;
        document.getElementById('categoria_id').value = lancamento.categoria_id;
        document.getElementById('descricao').value = lancamento.descricao;
        document.getElementById('data_lancamento').value = lancamento.data_lancamento;

        // Formata e preenche o valor
        const valorInput = document.getElementById('valor');
        setCurrencyValue(valorInput, lancamento.valor);

        const isFinalizado = Number(lancamento.finalizado) === 1;
        finalizadoInput.value = isFinalizado ? 1 : 0;

        editableFields.forEach(field => {
            field.disabled = isFinalizado;
        });

        submitButton.disabled = isFinalizado;
        submitButton.classList.toggle('opacity-50', isFinalizado);
        alertBox.classList.toggle('hidden', !isFinalizado);
        title.textContent = isFinalizado ? 'Visualizar Lançamento Finalizado' : 'Editar Lançamento';
        submitButton.classList.remove('bg-green-600', 'hover:bg-green-700', 'bg-gray-400', 'cursor-not-allowed');
        if (isFinalizado) {
            submitButton.innerHTML = '<i class="fas fa-lock mr-2"></i>Registro Finalizado';
            submitButton.classList.add('bg-gray-400');
        } else {
            editableFields.forEach(field => field.disabled = false);
            alertBox.classList.add('hidden');
            submitButton.innerHTML = '<i class="fas fa-save mr-2"></i> Atualizar Lançamento';
            submitButton.classList.add('bg-indigo-600', 'hover:bg-indigo-700');
        }
        
        // Rola a página para o topo
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function openHistoryModal(lancamentoId) {
        const modal = document.getElementById('history-modal');
        const container = document.getElementById('history-content');
        const logs = lancamentoLogs[lancamentoId] || [];

        if (!logs.length) {
            container.innerHTML = '<p class="text-sm text-gray-600">Nenhuma alteração registrada para este lançamento.</p>';
        } else {
            container.innerHTML = logs.map(log => {
                const createdAt = log.created_at || 'Sem data';
                const operacao = log.operacao || log.operation || 'Operação';
                return `<div class="border border-gray-200 rounded-md p-3">
                            <p class="text-sm text-gray-700"><strong>${operacao}</strong> em ${createdAt}</p>
                            <pre class="text-xs bg-gray-50 rounded-md p-2 mt-2 whitespace-pre-wrap">${JSON.stringify(log, null, 2)}</pre>
                        </div>`;
            }).join('');
        }

        modal.classList.remove('hidden');
    }

    function closeHistoryModal() {
        document.getElementById('history-modal').classList.add('hidden');
    }

    function openAdjustModal(id, description) {
        document.getElementById('adjust-id').value = id;
        document.getElementById('adjust-description').textContent = description || '';
        document.getElementById('adjust-valor').value = '';
        document.getElementById('adjust-motivo').value = '';
        document.getElementById('adjust-modal').classList.remove('hidden');
    }

    function closeAdjustModal() {
        document.getElementById('adjust-modal').classList.add('hidden');
    }

    // O código da máscara de moeda continua dentro do DOMContentLoaded,
    // pois ele precisa que os elementos da página já existam.
    document.addEventListener('DOMContentLoaded', function() {
    const aggregatedRows = document.querySelectorAll('.aggregated-row');

    aggregatedRows.forEach(row => {
        row.addEventListener('click', function(event) {
            // Evita que o clique em botões de ação dispare o drill-down
            if (event.target.closest('a, button')) {
                return;
            }

            const lancamentoId = this.dataset.lancamentoId;
            const detailRow = document.getElementById(`details-${lancamentoId}`);
            const detailsContent = detailRow.querySelector('.details-content');

            // Se os detalhes já estiverem abertos, feche-os
            if (!detailRow.classList.contains('hidden')) {
                detailRow.classList.add('hidden');
                return;
            }

            // Busca os detalhes via API
            fetch(`/fluxo-caixa/get_detalhes_lancamento_agregado/${lancamentoId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.itens.length > 0) {
                        let html = '<h4 class="font-semibold mb-2 text-gray-700">Itens do Orçamento:</h4>';
                        html += '<ul class="list-disc pl-5 space-y-1 text-sm text-gray-600">';
                        data.itens.forEach(item => {
                            const valorFormatado = parseFloat(item.valor).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
                            html += `<li>${item.descricao} - ${valorFormatado}</li>`;
                        });
                        html += '</ul>';
                        detailsContent.innerHTML = html;
                    } else {
                        detailsContent.innerHTML = '<p class="text-sm text-gray-500">Nenhum detalhe encontrado.</p>';
                    }
                    // Esconde todas as outras linhas de detalhe e mostra a atual
                    document.querySelectorAll('.detail-row').forEach(r => r.classList.add('hidden'));
                    detailRow.classList.remove('hidden');
                })
                .catch(error => {
                    console.error('Erro ao buscar detalhes:', error);
                    detailsContent.innerHTML = '<p class="text-sm text-red-500">Erro ao carregar os detalhes.</p>';
                    detailRow.classList.remove('hidden');
                });
        });
    });
});
</script>