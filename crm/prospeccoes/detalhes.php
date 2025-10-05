<?php
// Arquivo: crm/prospeccoes/detalhes.php (VERSÃO CORRIGIDA)

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../app/core/auth_check.php';

$user_perfil = $_SESSION['user_perfil'];
$prospeccao_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$prospeccao_id) {
    header("Location: " . APP_URL . "/crm/prospeccoes/lista.php");
    exit;
}

function formatSaoPauloDate(?string $dateTime, string $format = 'd/m/Y H:i', string $sourceTimezone = 'UTC'): string
{
    if ($dateTime === null || trim($dateTime) === '') {
        return '';
    }

    try {
        $date = new \DateTime($dateTime, new \DateTimeZone($sourceTimezone));
        $date->setTimezone(new \DateTimeZone('America/Sao_Paulo'));

        return $date->format($format);
    } catch (\Exception $exception) {
        $timestamp = strtotime($dateTime);

        if ($timestamp === false) {
            return $dateTime;
        }

        return date($format, $timestamp);
    }
}

try {
    // A consulta já está correta
    $stmt = $pdo->prepare("
        SELECT p.*, u.nome_completo AS responsavel_nome, c.nome_cliente, c.nome_responsavel AS lead_responsavel_nome,
               c.telefone AS cliente_telefone, c.canal_origem AS cliente_canal_origem
        FROM prospeccoes p
        LEFT JOIN users u ON p.responsavel_id = u.id
        LEFT JOIN clientes c ON p.cliente_id = c.id
        WHERE p.id = :id
    ");
    $stmt->execute(['id' => $prospeccao_id]);
    $prospect = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$prospect) {
        header("Location: " . APP_URL . "/crm/prospeccoes/lista.php");
        exit;
    }

    // A busca de interações também está correta
    $stmt_interacoes = $pdo->prepare("
        SELECT i.observacao, i.data_interacao, i.tipo, u.nome_completo AS usuario_nome 
        FROM interacoes i 
        LEFT JOIN users u ON i.usuario_id = u.id 
        WHERE i.prospeccao_id = :prospeccao_id 
        ORDER BY i.data_interacao DESC
    ");
    $stmt_interacoes->execute(['prospeccao_id' => $prospeccao_id]);
    $interacoes = $stmt_interacoes->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Erro ao carregar dados da prospecção: " . $e->getMessage());
}

$leadCategories = ['Entrada', 'Qualificado', 'Com Orçamento', 'Em Negociação', 'Cliente Ativo', 'Sem Interesse'];
$currentLeadCategory = $prospect['leadCategory'] ?? 'Entrada';
if (!in_array($currentLeadCategory, $leadCategories, true)) {
    $currentLeadCategory = 'Entrada';
}

require_once __DIR__ . '/../../app/views/layouts/header.php';
?>

<?php if (isset($_GET['request_sent'])): ?>
<div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg relative" role="alert">
  <strong class="font-bold">Sucesso!</strong>
  <span class="block sm:inline">Sua solicitação de exclusão foi enviada para a gerência/supervisão.</span>
</div>
<?php endif; ?>


<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    
    <div class="lg:col-span-2">
        <div class="bg-white shadow sm:rounded-lg">
            <div class="px-4 py-5 sm:p-6">
                <?php
                    $leadResponsavelNome = $prospect['lead_responsavel_nome'] ?? $prospect['nome_prospecto'] ?? '';
                    $leadNome = $prospect['nome_cliente'] ?? 'Lead não vinculado';
                    $clienteTelefone = $prospect['cliente_telefone'] ?? '';
                    $canalOrigem = $prospect['cliente_canal_origem'] ?? '';
                    $statusAtual = $prospect['status'] ?? '';
                ?>
                <div class="flex justify-between items-start mb-6">
                    <?php $leadResponsavelNome = $prospect['lead_responsavel_nome'] ?? $prospect['nome_prospecto'] ?? ''; ?>
                    <div>
                        <h2 class="text-2xl font-bold text-gray-900"><?php echo htmlspecialchars($leadNome); ?></h2>
                        <p class="text-sm text-gray-500">Responsável: <span class="font-medium text-indigo-600"><?php echo htmlspecialchars($leadResponsavelNome); ?></span></p>
                        <?php if (!empty($statusAtual) || !empty($currentLeadCategory)): ?>
                            <div class="mt-2 flex flex-wrap gap-2">
                                <?php if (!empty($statusAtual)): ?>
                                    <span class="inline-flex items-center rounded-full bg-blue-100 px-3 py-1 text-xs font-semibold text-blue-800 uppercase tracking-wide"><?php echo htmlspecialchars($statusAtual); ?></span>
                                <?php endif; ?>
                                <span class="inline-flex items-center rounded-full bg-gray-200 px-3 py-1 text-xs font-semibold text-gray-700 uppercase tracking-wide">Categoria: <?php echo htmlspecialchars($currentLeadCategory); ?></span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="flex items-center space-x-2">
                    <a href="<?php echo APP_URL; ?>/crm/prospeccoes/aprovar.php?id=<?php echo $prospect['id']; ?>" class="bg-green-600 text-white font-bold py-2 px-4 rounded-lg hover:bg-green-700 shadow-sm">
                        Aprovar
                    </a>

                    <a href="<?php echo APP_URL; ?>/crm/agendamentos/novo_agendamento.php?prospeccao_id=<?php echo $prospect['id']; ?>" class="bg-green-600 text-white font-bold py-2 px-4 rounded-lg hover:bg-green-700 shadow-sm">
                            Agendar
                        </a>
                        <?php if (in_array($user_perfil, ['admin', 'gerencia', 'supervisor'])): ?>
                            <form action="<?php echo APP_URL; ?>/crm/prospeccoes/excluir_prospeccao.php" method="POST" class="inline">
                                <input type="hidden" name="id" value="<?php echo $prospect['id']; ?>">
                                <button type="submit" class="bg-red-600 text-white font-bold py-2 px-4 rounded-lg hover:bg-red-700 shadow-sm"
                                        onclick="return confirm('ATENÇÃO:\n\nTem certeza que deseja excluir esta prospecção?\n\nEsta ação é irreversível e apagará também todo o histórico de interações.');">
                                    Excluir
                                </button>
                            </form>
                        <?php else: ?>
                            <button type="button" onclick="openModal()" class="bg-red-500 text-white font-bold py-2 px-4 rounded-lg hover:bg-red-600 shadow-sm">
                                Solicitar Exclusão
                            </button>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="border border-gray-200 rounded-lg p-4 mb-6 bg-gray-50">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide">Nome</label>
                            <input type="text" value="<?php echo htmlspecialchars($leadNome); ?>" class="mt-1 block w-full border border-gray-200 rounded-md shadow-sm py-2 px-3 bg-white text-gray-700" disabled>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide">Nome do Responsável (Lead)</label>
                            <input type="text" value="<?php echo htmlspecialchars($leadResponsavelNome); ?>" class="mt-1 block w-full border border-gray-200 rounded-md shadow-sm py-2 px-3 bg-white text-gray-700" disabled>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide">Telefone do Cliente</label>
                            <input type="text" value="<?php echo htmlspecialchars($clienteTelefone); ?>" class="mt-1 block w-full border border-gray-200 rounded-md shadow-sm py-2 px-3 bg-white text-gray-700" disabled>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide">Origem da Chamada</label>
                            <input type="text" value="<?php echo htmlspecialchars($canalOrigem); ?>" class="mt-1 block w-full border border-gray-200 rounded-md shadow-sm py-2 px-3 bg-white text-gray-700" disabled>
                        </div>
                    </div>
                </div>

                <form action="<?php echo APP_URL; ?>/crm/prospeccoes/atualizar.php" method="POST" class="space-y-6">
                    <input type="hidden" name="prospeccao_id" value="<?php echo $prospect['id']; ?>">
                    <input type="hidden" name="action" value="update_prospect">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="data_reuniao_agendada" class="block text-sm font-medium text-gray-700">Data da Reunião</label>
                            <input type="datetime-local" name="data_reuniao_agendada" id="data_reuniao_agendada" value="<?php echo !empty($prospect['data_reuniao_agendada']) ? htmlspecialchars(formatSaoPauloDate($prospect['data_reuniao_agendada'], 'Y-m-d\TH:i')) : ''; ?>" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3">
                        </div>
                        <div>
                            <label for="lead_category" class="block text-sm font-medium text-gray-700">Categoria do Lead</label>
                            <select name="lead_category" id="lead_category" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3">
                                <?php foreach ($leadCategories as $category): ?>
                                    <option value="<?php echo htmlspecialchars($category); ?>" <?php echo ($currentLeadCategory === $category) ? 'selected' : ''; ?>><?php echo htmlspecialchars($category); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="sm:col-span-1 flex items-center pt-4">
                            <input type="checkbox" name="reuniao_compareceu" id="reuniao_compareceu" value="1" <?php echo (!empty($prospect['reuniao_compareceu'])) ? 'checked' : ''; ?> class="h-4 w-4 text-indigo-600 border-gray-300 rounded">
                            <label for="reuniao_compareceu" class="ml-2 block text-sm font-medium text-gray-700">Compareceu?</label>
                        </div>
                    </div>

                    <div class="flex justify-end pt-4 border-t mt-6">
                        <a href="<?php echo APP_URL; ?>/crm/prospeccoes/lista.php" class="bg-gray-200 text-gray-700 font-bold py-2 px-4 rounded-lg hover:bg-gray-300 mr-3">Voltar</a>
                        <button type="submit" class="bg-blue-600 text-white font-bold py-2 px-4 rounded-lg hover:bg-blue-700">Salvar Alterações</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Coluna da Direita: Card de Atividades e Histórico -->
    <div class="space-y-6">
        <div class="bg-white shadow sm:rounded-lg">
            <div class="px-4 py-5 sm:p-6">
                <h3 class="text-lg font-medium leading-6 text-gray-900">Registrar Atividade</h3>
                    <div class="mt-4 grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <button onclick="openActivityModal('nota')" class="w-full bg-gray-600 text-white text-sm font-bold py-1.5 px-2 rounded-lg hover:bg-gray-700 flex items-center justify-center">
                            <svg class="h-4 w-4 mr-1.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                            </svg>
                            <span>Nota</span>
                        </button>
                        <button onclick="openActivityModal('chamada')" class="w-full bg-blue-600 text-white text-sm font-bold py-1.5 px-2 rounded-lg hover:bg-blue-700 flex items-center justify-center">
                            <svg class="h-4 w-4 mr-1.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path>
                            </svg>
                            <span>Chamada</span>
                        </button>
                        <button onclick="openActivityModal('reuniao')" class="w-full bg-green-600 text-white text-sm font-bold py-1.5 px-2 rounded-lg hover:bg-green-700 flex items-center justify-center">
                            <svg class="h-4 w-4 mr-1.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                            </svg>
                            <span>Reunião</span>
                        </button>
                    </div>
            </div>
        </div>

        <div class="bg-white shadow sm:rounded-lg">
            <div class="px-4 py-5 sm:p-6">
                <h3 class="text-lg font-medium text-gray-900">Histórico</h3>
                <div class="mt-4 flow-root">
                    <ul role="list" class="-mb-8">
                        <?php if (empty($interacoes)): ?>
                            <li><p class="text-gray-500 text-sm">Nenhuma interação registrada.</p></li>
                        <?php else: ?>
                            <?php foreach ($interacoes as $interacao): ?>
                            <?php
                                $tipo = $interacao['tipo'];
                                $bg_color = 'bg-gray-400'; $icon_svg = '';
                                if ($tipo == 'nota') { $bg_color = 'bg-gray-500'; $icon_svg = '<svg class="h-5 w-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>'; } 
                                elseif ($tipo == 'chamada') { $bg_color = 'bg-blue-500'; $icon_svg = '<svg class="h-5 w-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path></svg>'; } 
                                elseif ($tipo == 'reuniao') { $bg_color = 'bg-green-500'; $icon_svg = '<svg class="h-5 w-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>'; } 
                                elseif ($tipo == 'log_sistema') { $bg_color = 'bg-yellow-400'; $icon_svg = '<svg class="h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path d="M17.414 2.586a2 2 0 00-2.828 0L7 10.172V13h2.828l7.586-7.586a2 2 0 000-2.828z" /><path fill-rule="evenodd" d="M2 6a2 2 0 012-2h4a1 1 0 010 2H4v10h10v-4a1 1 0 112 0v4a2 2 0 01-2 2H4a2 2 0 01-2-2V6z" clip-rule="evenodd" /></svg>'; }
                            ?>
                            <li>
                                <div class="relative pb-8">
                                    <span class="absolute top-4 left-4 -ml-px h-full w-0.5 bg-gray-200"></span>
                                    <div class="relative flex space-x-3">
                                        <div><span class="h-8 w-8 rounded-full <?php echo $bg_color; ?> flex items-center justify-center ring-8 ring-white"><?php echo $icon_svg; ?></span></div>
                                        <div class="min-w-0 flex-1 pt-1.5 flex justify-between space-x-4">
                                            <div>
                                                <p class="text-sm text-gray-500"><?php echo htmlspecialchars($interacao['usuario_nome'] ?? 'Sistema'); ?></p>
                                                <p class="mt-1 text-sm text-gray-700"><?php echo nl2br(htmlspecialchars($interacao['observacao'])); ?></p>
                                            </div>
                                            <div class="text-right text-sm whitespace-nowrap text-gray-500"><time><?php echo htmlspecialchars(formatSaoPauloDate($interacao['data_interacao'])); ?></time></div>
                                        </div>
                                    </div>
                                </div>
                            </li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Dinâmico para Registrar Atividades -->
<div id="activityModal" class="hidden fixed z-50 inset-0 overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75" onclick="closeActivityModal()"></div>
        <div class="bg-white rounded-lg shadow-xl m-4 max-w-lg w-full z-10">
            <form action="<?php echo APP_URL; ?>/crm/prospeccoes/atualizar.php" method="POST">
                <input type="hidden" name="prospeccao_id" value="<?php echo $prospect['id']; ?>">
                <input type="hidden" name="action" value="add_interaction">
                <input type="hidden" name="tipo_interacao" id="tipo_interacao_hidden" value="">
                <div class="p-6">
                    <h3 id="modalTitle" class="text-xl font-bold text-gray-900">Registrar Atividade</h3>
                    <div class="mt-4 space-y-4">
                        <div id="resultadoChamada" class="hidden">
                            <label for="resultado" class="block text-sm font-medium text-gray-700">Resultado da Chamada</label>
                            <select name="resultado" id="resultado" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                                <option>Atendeu</option><option>Não atendeu</option><option>Caixa Postal</option><option>Número errado</option>
                            </select>
                        </div>
                        <div>
                            <label for="observacao" class="block text-sm font-medium text-gray-700">Observações</label>
                            <textarea name="observacao" id="observacao" rows="4" required class="mt-1 block w-full border-gray-300 rounded-md shadow-sm"></textarea>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 px-6 py-3 flex justify-end space-x-3">
                    <button type="button" onclick="closeActivityModal()" class="bg-white border border-gray-300 text-gray-700 font-bold py-2 px-4 rounded-lg hover:bg-gray-50">Cancelar</button>
                    <button type="submit" class="bg-blue-600 text-white font-bold py-2 px-4 rounded-lg hover:bg-blue-700">Salvar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal de Solicitação de Exclusão -->
<div id="deleteRequestModal" class="hidden fixed z-50 inset-0 overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true"></div>
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
        <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
            <form action="<?php echo APP_URL; ?>/crm/prospeccoes/solicitar_exclusao.php" method="POST">
                <input type="hidden" name="prospeccao_id" value="<?php echo $prospect['id']; ?>">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <div class="sm:flex sm:items-start">
                        <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10">
                            <svg class="h-6 w-6 text-red-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                        </div>
                        <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                            <h3 class="text-lg leading-6 font-medium text-gray-900">Solicitar Exclusão da Prospecção</h3>
                            <div class="mt-2">
                                <p class="text-sm text-gray-500">Informe o motivo. Sua solicitação será enviada por e-mail para a gerência/supervisão.</p>
                                <textarea name="motivo" rows="4" required class="mt-2 block w-full border border-gray-300 rounded-md shadow-sm" placeholder="Digite o motivo da exclusão..."></textarea>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button type="submit" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-700 sm:ml-3 sm:w-auto sm:text-sm">Enviar Solicitação</button>
                    <button type="button" onclick="closeModal()" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">Cancelar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    const activityModal = document.getElementById('activityModal');
    const modalTitle = document.getElementById('modalTitle');
    const tipoInteracaoHidden = document.getElementById('tipo_interacao_hidden');
    const resultadoChamadaDiv = document.getElementById('resultadoChamada');

    function openActivityModal(tipo) {
        tipoInteracaoHidden.value = tipo;
        if (tipo === 'nota') { modalTitle.innerText = 'Adicionar Nova Nota'; resultadoChamadaDiv.classList.add('hidden'); } 
        else if (tipo === 'chamada') { modalTitle.innerText = 'Registrar Chamada Telefônica'; resultadoChamadaDiv.classList.remove('hidden'); } 
        else if (tipo === 'reuniao') { modalTitle.innerText = 'Registrar Reunião'; resultadoChamadaDiv.classList.add('hidden'); }
        activityModal.classList.remove('hidden');
    }

    function closeActivityModal() { activityModal.classList.add('hidden'); }

    const deleteModal = document.getElementById('deleteRequestModal');
    function openModal() { deleteModal.classList.remove('hidden'); }
    function closeModal() { deleteModal.classList.add('hidden'); }
</script>

<?php 
require_once __DIR__ . '/../../app/views/layouts/footer.php'; 
?>