<?php
// Arquivo: crm/prospeccoes/kanban.php (VERSÃO FINAL E CORRIGIDA)

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../app/core/auth_check.php';
require_once __DIR__ . '/../../app/views/layouts/header.php';

$colunas_kanban = [
    'Contato ativo', 'Primeiro contato', 'Segundo contato', 'Terceiro contato',
    'Reunião agendada', 'Proposta enviada', 'Fechamento', 'Pausar'
];

try {
    // A consulta já está correta, selecionando todos os campos necessários.
    $sql = "SELECT p.id, p.nome_prospecto, p.valor_proposto, p.status, c.nome_cliente, u.nome_completo as responsavel_nome
            FROM prospeccoes p
            LEFT JOIN clientes c ON p.cliente_id = c.id
            LEFT JOIN users u ON p.responsavel_id = u.id
            WHERE p.status IN ('" . implode("','", $colunas_kanban) . "')
            ORDER BY p.id DESC";
            
    $stmt = $pdo->query($sql);
    $prospeccoes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $prospeccoes_por_status = [];
    foreach ($colunas_kanban as $coluna) {
        $prospeccoes_por_status[$coluna] = [];
    }
    foreach ($prospeccoes as $prospeccao) {
        if (isset($prospeccoes_por_status[$prospeccao['status']])) {
            $prospeccoes_por_status[$prospeccao['status']][] = $prospeccao;
        }
    }

} catch (PDOException $e) { 
    die("Erro ao buscar prospecções: " . $e->getMessage()); 
}
?>

<!-- Estilos CSS aprimorados para o Kanban -->
<style>
    .kanban-board {
        display: flex;
        overflow-x: auto;
        padding-bottom: 1rem;
        min-height: 80vh;
        cursor: grab;
        user-select: none;
    }

    .kanban-board:active {
        cursor: grabbing;
    }

    .kanban-column {
        flex: 0 0 320px;
        margin-right: 1rem;
        background-color: #f9fafb;
        border-radius: 0.75rem;
        display: flex;
        flex-direction: column;
        transition: background-color 0.2s;
    }

    .kanban-column:hover {
        background-color: #f1f5f9;
    }

    .kanban-cards-container {
        flex-grow: 1;
        min-height: 100px;
    }

    .kanban-card {
        cursor: pointer;
        background-color: #ffffff;
        padding: 1rem;
        border-radius: 0.75rem;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        transition: transform 0.2s, background-color 0.2s;
    }

    .kanban-card:hover {
        background-color: #e5e7eb;
        transform: translateY(-5px);
    }

    .kanban-card:active {
        transform: translateY(2px);
    }

    .avatar {
        width: 32px;
        height: 32px;
        background-color: #3b82f6;
        color: white;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 9999px;
        font-size: 0.875rem;
        font-weight: bold;
    }

    .kanban-board::-webkit-scrollbar {
        height: 12px;
    }

    .kanban-board::-webkit-scrollbar-track {
        background: #e5e7eb;
        border-radius: 10px;
    }

    .kanban-board::-webkit-scrollbar-thumb {
        background-color: #9ca3af;
        border-radius: 10px;
        border: 3px solid #e5e7eb;
    }

    .kanban-board::-webkit-scrollbar-thumb:hover {
        background-color: #6b7280;
    }

    .kanban-column h3 {
        background-color: #1d4ed8;
        color: white;
        padding: 0.75rem;
        border-top-left-radius: 0.75rem;
        border-top-right-radius: 0.75rem;
    }

    .kanban-column h3 span {
        font-weight: 500;
    }
</style>


<div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between border-b border-gray-200 pb-4 mb-4">
    <div class="flex items-center justify-between gap-3">
        <h1 class="text-2xl font-bold text-gray-800">Funil de Vendas (Kanban)</h1>
        <a href="<?php echo APP_URL; ?>/crm/prospeccoes/lista.php" class="bg-gray-200 text-gray-700 font-bold py-2 px-4 rounded-lg hover:bg-gray-300">Ver em Lista</a>
    </div>
    <div class="flex flex-wrap items-center gap-3">
        <button id="toggleSelectionBtn" class="bg-blue-600 text-white font-bold py-2 px-4 rounded-lg hover:bg-blue-700 transition-colors">Selecionar múltiplos</button>
        <div id="bulkActions" class="hidden items-center gap-2">
            <span class="text-sm text-gray-600">Selecionados: <span id="selectedCount">0</span></span>
            <select id="bulkStatusSelect" class="border border-gray-300 rounded-lg py-2 px-3 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                <?php foreach ($colunas_kanban as $coluna): ?>
                    <option value="<?php echo htmlspecialchars($coluna); ?>"><?php echo htmlspecialchars($coluna); ?></option>
                <?php endforeach; ?>
            </select>
            <button id="bulkMoveBtn" class="bg-green-600 text-white font-bold py-2 px-4 rounded-lg hover:bg-green-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed" disabled>Mover selecionados</button>
        </div>
    </div>
</div>

<!-- O Painel Kanban -->
<div class="kanban-board" id="kanbanBoard">
    <?php foreach ($colunas_kanban as $status): ?>
        <div class="kanban-column">
            <h3 class="font-bold text-gray-700 p-3 border-b"><?php echo $status; ?> (<?php echo count($prospeccoes_por_status[$status]); ?>)</h3>
            <div class="p-3 kanban-cards-container space-y-3" data-status="<?php echo htmlspecialchars($status); ?>">
                <?php foreach ($prospeccoes_por_status[$status] as $prospeccao): ?>
                    <div class="bg-white p-3 rounded-lg shadow-sm kanban-card" data-id="<?php echo $prospeccao['id']; ?>">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="font-semibold text-sm text-gray-800"><?php echo htmlspecialchars($prospeccao['nome_prospecto']); ?></p>
                                <p class="text-xs text-gray-600 mt-1"><?php echo htmlspecialchars($prospeccao['nome_cliente'] ?? 'Lead não associado'); ?></p>
                            </div>
                            <input type="checkbox" class="card-checkbox hidden mt-1 h-4 w-4 text-blue-600 rounded border-gray-300 focus:ring-blue-500" aria-label="Selecionar lead">
                        </div>
                        <div class="flex justify-between items-center mt-3">
                            <p class="text-xs text-blue-600 font-bold">R$ <?php echo number_format($prospeccao['valor_proposto'] ?? 0, 2, ',', '.'); ?></p>
                            <?php if (!empty($prospeccao['responsavel_nome'])): ?>
                                <div class="avatar" title="<?php echo htmlspecialchars($prospeccao['responsavel_nome']); ?>">
                                    <?php echo strtoupper(substr($prospeccao['responsavel_nome'], 0, 2)); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<!-- Modal de Detalhes (escondido por padrão) -->
<div id="cardModal" class="hidden fixed z-50 inset-0 overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75"></div>
        <div id="modalContent" class="bg-white rounded-lg shadow-xl m-4 max-w-2xl w-full z-10 p-6 space-y-4">
            <p class="text-center">Carregando...</p>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        // Define a URL base para as chamadas de API
        const API_BASE_URL = '<?php echo APP_URL; ?>/crm/prospeccoes';
        
        const containers = document.querySelectorAll('.kanban-cards-container');
        const sortableInstances = [];

        containers.forEach(container => {
            const sortable = new Sortable(container, {
                group: 'kanban',
                animation: 150,
                ghostClass: 'sortable-ghost',
                onEnd: handleSortEnd
            });
            sortableInstances.push(sortable);
        });

        const toggleSelectionBtn = document.getElementById('toggleSelectionBtn');
        const bulkActions = document.getElementById('bulkActions');
        const selectedCountEl = document.getElementById('selectedCount');
        const bulkStatusSelect = document.getElementById('bulkStatusSelect');
        const bulkMoveBtn = document.getElementById('bulkMoveBtn');

        const selectionElementsExist = toggleSelectionBtn && bulkActions && selectedCountEl && bulkStatusSelect && bulkMoveBtn;

        let selectionMode = false;
        const selectedCards = new Set();

        if (selectionElementsExist) {
            toggleSelectionBtn.addEventListener('click', () => {
                selectionMode = !selectionMode;
                toggleSelectionBtn.textContent = selectionMode ? 'Cancelar seleção' : 'Selecionar múltiplos';
                bulkActions.classList.toggle('hidden', !selectionMode);

                if (!selectionMode) {
                    selectedCards.clear();
                }

                refreshSelectionState();
            });

            bulkMoveBtn.addEventListener('click', () => {
                if (selectedCards.size === 0) {
                    return;
                }

                const targetStatus = bulkStatusSelect.value;

                if (!targetStatus) {
                    alert('Selecione uma coluna de destino.');
                    return;
                }

                bulkMoveBtn.disabled = true;

                const ids = Array.from(selectedCards);
                const updatePromises = ids.map(id => postStatusChange(id, targetStatus));

                Promise.allSettled(updatePromises).then(results => {
                    const hasError = results.some(result => result.status === 'rejected');
                    if (hasError) {
                        alert('Alguns leads não foram atualizados corretamente. A página será recarregada.');
                    }
                    window.location.reload();
                });
            });
        }

        function postStatusChange(prospeccaoId, novoStatus) {
            return fetch(`${API_BASE_URL}/atualizar_status_kanban.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ prospeccao_id: prospeccaoId, novo_status: novoStatus })
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Erro ao atualizar o status.');
                }
                return response.json();
            })
            .then(data => {
                if (!data.success) {
                    throw new Error(data.message || 'Erro ao atualizar o status.');
                }
                return data;
            });
        }

        function handleSortEnd(evt) {
            const prospeccaoId = evt.item.dataset.id;
            const novoStatus = evt.to.dataset.status;

            postStatusChange(prospeccaoId, novoStatus).catch(() => {
                alert('Erro ao atualizar.');
                location.reload();
            });
        }

        function toggleCardSelection(card, shouldSelect) {
            if (!selectionElementsExist) {
                return;
            }

            const cardId = card.dataset.id;
            if (!cardId) {
                return;
            }

            if (shouldSelect) {
                selectedCards.add(cardId);
            } else {
                selectedCards.delete(cardId);
            }

            refreshSelectionState();
        }

        function refreshSelectionState() {
            if (!selectionElementsExist) {
                return;
            }

            sortableInstances.forEach(instance => instance.option('disabled', selectionMode));

            document.querySelectorAll('.kanban-card').forEach(card => {
                const checkbox = card.querySelector('.card-checkbox');
                if (!checkbox) {
                    return;
                }

                if (selectionMode) {
                    checkbox.classList.remove('hidden');
                    const isSelected = selectedCards.has(card.dataset.id);
                    checkbox.checked = isSelected;
                    card.classList.toggle('ring-2', isSelected);
                    card.classList.toggle('ring-blue-500', isSelected);
                    card.classList.toggle('bg-blue-50', isSelected);
                } else {
                    checkbox.classList.add('hidden');
                    checkbox.checked = false;
                    card.classList.remove('ring-2', 'ring-blue-500', 'bg-blue-50');
                }
            });

            selectedCountEl.textContent = selectedCards.size;
            bulkMoveBtn.disabled = selectedCards.size === 0;
        }

        const modal = document.getElementById('cardModal');
        const modalContent = document.getElementById('modalContent');

        document.querySelectorAll('.kanban-card').forEach(card => {
            const checkbox = card.querySelector('.card-checkbox');

            if (selectionElementsExist && checkbox) {
                checkbox.addEventListener('click', (event) => event.stopPropagation());
                checkbox.addEventListener('change', (event) => {
                    toggleCardSelection(card, event.target.checked);
                });
            }

            card.addEventListener('click', function(event) {
                if (selectionElementsExist && selectionMode) {
                    const isSelected = selectedCards.has(card.dataset.id);
                    toggleCardSelection(card, !isSelected);
                    event.preventDefault();
                    return;
                }

                const prospeccaoId = this.dataset.id;
                openCardModal(prospeccaoId);
            });
        });

        if (selectionElementsExist) {
            refreshSelectionState();
        }

        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                modal.classList.add('hidden');
            }
        });

        // --- INÍCIO DA CORREÇÃO: FUNÇÕES DO MODAL COMPLETAS ---
        function openCardModal(id) {
            modal.classList.remove('hidden');
            modalContent.innerHTML = '<p class="text-center">Carregando detalhes...</p>';

            fetch(`${API_BASE_URL}/api_get_prospeccao_details.php?id=${id}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const p = data.prospect;
                        let interacoesHtml = '';
                        data.interacoes.forEach(i => {
                            const dataFormatada = new Date(i.data_interacao).toLocaleString('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
                            interacoesHtml += `<div class="text-xs border-t pt-2 mt-2"><p class="text-gray-500">${i.usuario_nome || 'Sistema'} - ${dataFormatada}</p><p class="text-gray-800">${i.observacao}</p></div>`;
                        });

                        modalContent.innerHTML = `
                            <div class="flex justify-between items-start">
                                <div>
                                    <h3 class="text-xl font-bold text-gray-900">${p.nome_prospecto}</h3>
                                    <p class="text-sm text-gray-600">${p.cliente_nome}</p>
                                </div>
                                <button onclick="document.getElementById('cardModal').classList.add('hidden')" class="text-gray-400 hover:text-gray-600 text-2xl font-bold">&times;</button>
                            </div>
                            <div>
                                <h4 class="font-semibold text-sm">Últimas Interações</h4>
                                <div class="mt-2 space-y-2 max-h-40 overflow-y-auto p-2 bg-gray-50 rounded">${interacoesHtml || '<p class="text-xs text-gray-500">Nenhuma interação recente.</p>'}</div>
                            </div>
                            <div>
                                <h4 class="font-semibold text-sm">Adicionar Nota Rápida</h4>
                                <form onsubmit="addQuickNote(event, ${p.id})">
                                    <textarea name="observacao" class="mt-1 w-full border-gray-300 rounded-md shadow-sm" rows="2" required></textarea>
                                    <div class="text-right mt-2">
                                        <button type="submit" class="bg-blue-600 text-white font-bold py-1 px-3 rounded-lg text-sm">Adicionar</button>
                                    </div>
                                </form>
                            </div>
                            <div class="text-center border-t pt-3">
                                <a href="detalhes.php?id=${p.id}" class="text-sm text-indigo-600 hover:underline">Ver todos os detalhes &rarr;</a>
                            </div>
                        `;
                    } else {
                        modalContent.innerHTML = `<p class="text-red-500">Erro: ${data.message}</p>`;
                    }
                });
        }

        window.addQuickNote = function(event, prospeccaoId) {
            event.preventDefault();
            const form = event.target;
            const textarea = form.querySelector('textarea');
            
            const formData = new FormData();
            formData.append('action', 'add_interaction');
            formData.append('prospeccao_id', prospeccaoId);
            formData.append('observacao', textarea.value);

            fetch(`${API_BASE_URL}/atualizar.php`, {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (response.ok) {
                    // Recarrega os detalhes do modal para mostrar a nova nota
                    openCardModal(prospeccaoId);
                } else {
                    alert('Erro ao adicionar a nota.');
                }
            });
        }
        // --- FIM DA CORREÇÃO ---

        const board = document.getElementById('kanbanBoard');
        let isDown = false;
        let startX;
        let scrollLeft;

        board.addEventListener('mousedown', (e) => {
            if (e.target.closest('.kanban-card')) return;
            isDown = true;
            board.classList.add('active');
            startX = e.pageX - board.offsetLeft;
            scrollLeft = board.scrollLeft;
        });
        board.addEventListener('mouseleave', () => { isDown = false; board.classList.remove('active'); });
        board.addEventListener('mouseup', () => { isDown = false; board.classList.remove('active'); });
        board.addEventListener('mousemove', (e) => {
            if (!isDown) return;
            e.preventDefault();
            const x = e.pageX - board.offsetLeft;
            const walk = (x - startX) * 2;
            board.scrollLeft = scrollLeft - walk;
        });
    });
</script>

<?php 
require_once __DIR__ . '/../../app/views/layouts/footer.php'; 
?>