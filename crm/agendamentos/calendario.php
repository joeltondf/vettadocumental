<?php
// Arquivo: crm/agendamentos/calendario.php (VERSÃO FINAL CORRIGIDA)

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../app/core/auth_check.php';
require_once __DIR__ . '/../../app/views/layouts/header.php'; 

$responsavel_filtrado = null;
if (in_array($_SESSION['user_perfil'], ['admin', 'gerencia', 'supervisor']) && isset($_GET['responsavel_id']) && !empty($_GET['responsavel_id'])) {
    $responsavel_filtrado = filter_input(INPUT_GET, 'responsavel_id', FILTER_VALIDATE_INT);
}

try {
    $stmt_users = $pdo->query("SELECT id, nome_completo, perfil FROM users ORDER BY nome_completo");
    $todos_usuarios = $stmt_users->fetchAll(PDO::FETCH_ASSOC);

    $usuarios_filtro = array_filter($todos_usuarios, function($user) {
        return in_array($user['perfil'], ['vendedor', 'gerencia', 'supervisor', 'admin']);
    });

    $clientes = $pdo->query("SELECT id, nome_cliente AS nome FROM clientes WHERE is_prospect = 1 ORDER BY nome_cliente")
        ->fetchAll(PDO::FETCH_ASSOC);
    $prospeccoes = $pdo->query("SELECT id, nome_prospecto FROM prospeccoes ORDER BY nome_prospecto")->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Ocorreu um erro ao carregar os dados da agenda: " . $e->getMessage());
}
?>
<link href='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css' rel='stylesheet' />
<style>
    /* Estilos para o tooltip (Mantidos) */
    .tippy-box {
        background-color: #333;
        color: white;
        border-radius: 8px;
        font-size: 0.875rem; /* 14px */
    }
    .tippy-content { padding: 12px 16px; } /* Aumentado o padding */
    .tippy-arrow { color: #333; }
    #calendar { cursor: default; } /* Cursor padrão, muda em eventos */
    .fc-daygrid-day { cursor: pointer; } /* Cursor de ponteiro nos dias para indicar que são clicáveis */

    /* Estilos Tailwind customizados para o modal */
    .modal-overlay {
        background-color: rgba(0, 0, 0, 0.5); /* Fundo escuro semi-transparente */
    }
</style>

    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold">Agenda de Reuniões</h1>

        <?php if (in_array($_SESSION['user_perfil'], ['admin', 'gerencia', 'supervisor'])): ?>
            <form method="GET" action="<?php echo APP_URL; ?>/crm/agendamentos/calendario.php" class="flex items-center ml-auto">
                <label for="responsavel_id" class="mr-2 text-gray-700">Ver agenda de:</label>
                <select name="responsavel_id" id="responsavel_id" onchange="this.form.submit()" class="form-select border border-gray-300 rounded-md py-1 px-2 text-sm">
                    <option value="">Toda a equipe</option>
                    <?php foreach($usuarios_filtro as $usuario): ?>
                        <option value="<?= $usuario['id']; ?>" <?= ($responsavel_filtrado == $usuario['id']) ? 'selected' : ''; ?>>
                            <?= htmlspecialchars($usuario['nome_completo']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
        <?php endif; ?>
    </div>

    <div class="bg-white shadow-md rounded-lg p-6">
        <div id='calendar'></div>
    </div>

<div id="modalNovoAgendamento" class="fixed inset-0 z-50 overflow-auto bg-black bg-opacity-50 flex items-center justify-center hidden">
    <div class="bg-white rounded-lg shadow-xl p-6 m-4 max-w-lg w-full">
        <div class="flex justify-between items-center pb-3">
            <h5 class="text-xl font-semibold" id="modalNovoAgendamentoLabel">Criar Novo Agendamento</h5>
            <button type="button" id="btnCloseModal" class="text-gray-400 hover:text-gray-600 focus:outline-none focus:ring-2 focus:ring-gray-300 rounded-full p-1 transition-colors">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>
        <div class="modal-body">
            <form id="formNovoAgendamento" class="space-y-4">
                <div>
                    <label for="titulo" class="block text-sm font-medium text-gray-700">Título do Agendamento</label>
                    <input type="text" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2 focus:ring-blue-500 focus:border-blue-500 sm:text-sm" id="titulo" name="titulo" required>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="cliente_id" class="block text-sm font-medium text-gray-700">Lead (Opcional)</label>
                        <select class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2 focus:ring-blue-500 focus:border-blue-500 sm:text-sm" id="cliente_id" name="cliente_id">
                            <option value="">Nenhum</option>
                            <?php foreach ($clientes as $cliente): ?>
                                <option value="<?= $cliente['id'] ?>"><?= htmlspecialchars($cliente['nome']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="prospeccao_id" class="block text-sm font-medium text-gray-700">Prospecção (Opcional)</label>
                        <select class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2 focus:ring-blue-500 focus:border-blue-500 sm:text-sm" id="prospeccao_id" name="prospeccao_id">
                            <option value="">Nenhuma</option>
                            <?php foreach ($prospeccoes as $prospeccao): ?>
                                <option value="<?= $prospeccao['id'] ?>"><?= htmlspecialchars($prospeccao['nome_prospecto']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="data_inicio_hora" class="block text-sm font-medium text-gray-700">Hora de Início</label>
                        <input type="time" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2 focus:ring-blue-500 focus:border-blue-500 sm:text-sm" id="data_inicio_hora" name="data_inicio_hora" required>
                        <input type="hidden" id="data_inicio" name="data_inicio">
                    </div>
                    <div>
                        <label for="data_fim_hora" class="block text-sm font-medium text-gray-700">Hora de Fim</label>
                        <input type="time" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2 focus:ring-blue-500 focus:border-blue-500 sm:text-sm" id="data_fim_hora" name="data_fim_hora" required>
                        <input type="hidden" id="data_fim" name="data_fim">
                    </div>
                </div>
                <div>
                    <label for="local_link" class="block text-sm font-medium text-gray-700">Local ou Link da Reunião</label>
                    <input type="text" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2 focus:ring-blue-500 focus:border-blue-500 sm:text-sm" id="local_link" name="local_link">
                </div>
                <div>
                    <label for="observacoes" class="block text-sm font-medium text-gray-700">Observações</label>
                    <textarea class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2 focus:ring-blue-500 focus:border-blue-500 sm:text-sm" id="observacoes" name="observacoes" rows="3"></textarea>
                </div>
                <input type="hidden" name="usuario_id" value="<?= $_SESSION['user_id'] ?>">
            </form>
        </div>
        <div class="flex justify-end gap-2 mt-4 pt-4 border-t border-gray-200">
            <button type="button" id="btnCancelarAgendamento" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-gray-300">Cancelar</button>
            <button type="button" id="btnSalvarAgendamento" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500">Salvar Agendamento</button>
        </div>
    </div>
</div>

<div id="modalNovaProspeccao" class="fixed inset-0 z-[60] overflow-auto bg-black bg-opacity-75 flex items-center justify-center hidden">
    <div class="bg-white rounded-lg shadow-xl p-6 m-4 max-w-md w-full">
        <h5 class="text-xl font-semibold mb-4">Cadastrar Nova Prospecção</h5>
        <form id="formNovaProspeccao" class="space-y-4">
            <input type="hidden" id="nova_prospeccao_cliente_id" name="cliente_id">
            <div>
                <label for="nome_prospecto_novo" class="block text-sm font-medium text-gray-700">Nome do Prospecto</label>
                <input type="text" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2" id="nome_prospecto_novo" name="nome_prospecto" required>
            </div>
            <div class="flex justify-end gap-2 pt-4 border-t border-gray-200">
                <button type="button" id="btnCancelarNovaProspeccao" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300">Cancelar</button>
                <button type="button" id="btnSalvarNovaProspeccao" class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700">Salvar Prospecção</button>
            </div>
        </form>
    </div>
</div>


<script src='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js'></script>
<script src='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/locales/pt-br.js'></script>
<script src="https://unpkg.com/@popperjs/core@2"></script>
<script src="https://unpkg.com/tippy.js@6"></script>


<script>
document.addEventListener('DOMContentLoaded', function() {
    var calendarEl = document.getElementById('calendar');
    var modalNovoAgendamento = document.getElementById('modalNovoAgendamento');
    var formNovoAgendamento = document.getElementById('formNovoAgendamento');
    var btnCloseModal = document.getElementById('btnCloseModal');
    var btnCancelarAgendamento = document.getElementById('btnCancelarAgendamento');
    
    var dataInicioHoraInput = document.getElementById('data_inicio_hora');
    var dataFimHoraInput = document.getElementById('data_fim_hora');
    
    var dataInicioHiddenInput = document.getElementById('data_inicio');
    var dataFimHiddenInput = document.getElementById('data_fim');

    var selectedCalendarDate = null; // Armazena a data clicada no calendário
    var selectedDateStr = null; // Armazena a data clicada no calendário como string 'YYYY-MM-DD'

    // Referências para o modal de nova prospecção
    var modalNovaProspeccao = document.getElementById('modalNovaProspeccao');
    var formNovaProspeccao = document.getElementById('formNovaProspeccao');
    var btnCancelarNovaProspeccao = document.getElementById('btnCancelarNovaProspeccao');
    var btnSalvarNovaProspeccao = document.getElementById('btnSalvarNovaProspeccao');
    var novaProspeccaoClienteIdInput = document.getElementById('nova_prospeccao_cliente_id');
    
    // Define as URLs base para as chamadas de API
    const API_BASE_URL = '<?php echo APP_URL; ?>/crm/agendamentos';
    const API_PROSPECCAO_URL = '<?php echo APP_URL; ?>/crm/prospeccoes';

    var calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        locale: 'pt-br',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek'
        },
        events: `${API_BASE_URL}/api_eventos.php` + '<?php echo $responsavel_filtrado ? "?responsavel_id=$responsavel_filtrado" : ""; ?>',
        
        selectable: true,
        dateClick: function(info) {
            formNovoAgendamento.reset();
            
            selectedCalendarDate = new Date(info.date);
            var offset = selectedCalendarDate.getTimezoneOffset();
            selectedCalendarDate = new Date(selectedCalendarDate.getTime() - (offset * 60 * 1000));
            
            // Define selectedDateStr no formato 'YYYY-MM-DD'
            const year = selectedCalendarDate.getFullYear();
            const month = String(selectedCalendarDate.getMonth() + 1).padStart(2, '0');
            const day = String(selectedCalendarDate.getDate()).padStart(2, '0');
            selectedDateStr = `${year}-${month}-${day}`;

            dataInicioHoraInput.value = selectedCalendarDate.toTimeString().slice(0, 5); // HH:MM
            
            var dataFimPadrao = new Date(selectedCalendarDate.getTime() + 60 * 60 * 1000); // Adiciona 1 hora
            dataFimHoraInput.value = dataFimPadrao.toTimeString().slice(0, 5); // HH:MM

            document.getElementById('cliente_id').value = '';
            document.getElementById('prospeccao_id').innerHTML = '<option value="">Nenhuma</option>';

            modalNovoAgendamento.classList.remove('hidden');
            updateHiddenDateTime();
        },

        eventClick: function(info) {
            info.jsEvent.preventDefault(); 

            if (info.el._tippy) {
                info.el._tippy.destroy();
            }

            const props = info.event.extendedProps;
            let link_prospeccao = props.prospeccao_id ? `<a href="${API_PROSPECCAO_URL}/detalhes.php?id=${props.prospeccao_id}" class="block mt-3 text-center bg-blue-500 text-white py-2 px-4 rounded-md text-sm hover:bg-blue-600 transition-colors">Ver Prospecção</a>` : '';

            let content = `
                <div class="text-left text-sm">
                    <p class="font-bold text-base mb-2">${info.event.title}</p>
                    <p class="mb-1 text-gray-200"><strong>Cliente:</strong> ${props.cliente || 'N/A'}</p>
                    <p class="mb-1 text-gray-200"><strong>Responsável:</strong> ${props.responsavel}</p>
                    ${props.local_link ? `<p class="mb-1"><strong>Link:</strong> <a href="${props.local_link}" target="_blank" class="text-blue-400 hover:underline">Acessar Reunião</a></p>` : ''}
                    ${props.observacoes ? `<p class="mt-3 border-t border-gray-500 pt-2 text-gray-200"><strong>Obs:</strong> ${props.observacoes}</p>` : ''}
                    ${link_prospeccao}
                </div>
            `;
            
            tippy(info.el, {
                content: content,
                allowHTML: true,
                trigger: 'manual', 
                interactive: true, 
                placement: 'top',
                animation: 'scale-subtle',
                appendTo: () => document.body,
                onClickOutside(instance, event) {
                    instance.hide();
                },
            }).show();
        },
        eventDidMount: function(info) {
             info.el.style.cursor = 'pointer';
        }
    });

    calendar.render();

    // Event listeners para fechar o modal principal
    btnCloseModal.addEventListener('click', function() {
        modalNovoAgendamento.classList.add('hidden');
    });

    btnCancelarAgendamento.addEventListener('click', function() {
        modalNovoAgendamento.classList.add('hidden');
    });

    modalNovoAgendamento.addEventListener('click', function(event) {
        if (event.target === modalNovoAgendamento) {
            modalNovoAgendamento.classList.add('hidden');
        }
    });

    // --- Lógica dos Campos de Hora para preencher os campos hidden de datetime ---
    function updateHiddenDateTime() {
        if (selectedDateStr && dataInicioHoraInput.value && dataFimHoraInput.value) {
            dataInicioHiddenInput.value = `${selectedDateStr} ${dataInicioHoraInput.value}:00`;
            dataFimHiddenInput.value = `${selectedDateStr} ${dataFimHoraInput.value}:00`;
        }
    }

    dataInicioHoraInput.addEventListener('change', updateHiddenDateTime);
    dataFimHoraInput.addEventListener('change', updateHiddenDateTime);


    // --- Lógica de Interligação Cliente <-> Prospecção ---

    document.getElementById('cliente_id').addEventListener('change', function() {
        var clienteId = this.value;
        var prospeccaoSelect = document.getElementById('prospeccao_id');
        prospeccaoSelect.innerHTML = '<option value="">Nenhuma</option>'; 

        if (clienteId) {
            fetch(`${API_BASE_URL}/api_dados_relacionados.php?action=get_prospeccoes_by_cliente&cliente_id=${clienteId}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok: ' + response.statusText);
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.length > 0) {
                        data.forEach(function(prospeccao) {
                            var option = document.createElement('option');
                            option.value = prospeccao.id;
                            option.textContent = prospeccao.text;
                            prospeccaoSelect.appendChild(option);
                        });
                        prospeccaoSelect.value = data[0].id; // Pré-seleciona a primeira
                    } else {
                        alert('Nenhuma prospecção encontrada para este cliente. Por favor, cadastre uma nova prospecção.');
                        novaProspeccaoClienteIdInput.value = clienteId;
                        modalNovaProspeccao.classList.remove('hidden');
                    }
                })
                .catch(error => {
                    console.error('Erro ao buscar prospecções por cliente:', error);
                    alert('Erro ao carregar prospecções. Verifique o console.');
                });

        }
    });

    document.getElementById('prospeccao_id').addEventListener('change', function() {
        var prospeccaoId = this.value;
        var clienteSelect = document.getElementById('cliente_id');
        
        if (prospeccaoId) {
            fetch(`${API_BASE_URL}/api_dados_relacionados.php?action=get_prospeccao_details&prospeccao_id=${prospeccaoId}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok: ' + response.statusText);
                    }
                    return response.json();
                })
                .then(data => {
                    if (data && data.cliente_id) {
                        clienteSelect.value = data.cliente_id;
                    } else {
                        alert('Esta prospecção não está associada a nenhum lead.');
                    }
                })
                .catch(error => {
                    console.error('Erro ao buscar detalhes da prospecção:', error);
                    alert('Erro ao carregar detalhes da prospecção. Verifique o console.');
                });
        }
    });


    // Lógica para salvar o agendamento do modal via AJAX
    formNovoAgendamento.addEventListener('submit', function(event) {
        event.preventDefault(); // Previne o envio padrão do formulário

        updateHiddenDateTime(); 

        var formData = new FormData(formNovoAgendamento);

        fetch(`${API_BASE_URL}/salvar_agendamento.php`, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                modalNovoAgendamento.classList.add('hidden');
                calendar.refetchEvents();
                alert('Agendamento salvo com sucesso!');
            } else {
                alert('Erro ao salvar agendamento: ' + (data.message || 'Verifique os dados.'));
            }
        })
        .catch(error => {
            console.error('Erro no Fetch:', error);
            alert('Ocorreu um erro de comunicação. Tente novamente.');
        });
    });

    // Adiciona o gatilho de submissão ao botão Salvar
    document.getElementById('btnSalvarAgendamento').addEventListener('click', () => {
        formNovoAgendamento.requestSubmit();
    });


    // --- Lógica do Modal de Nova Prospecção ---
    btnCancelarNovaProspeccao.addEventListener('click', function() {
        modalNovaProspeccao.classList.add('hidden');
    });

    modalNovaProspeccao.addEventListener('click', function(event) {
        if (event.target === modalNovaProspeccao) {
            modalNovaProspeccao.classList.add('hidden');
        }
    });

    btnSalvarNovaProspeccao.addEventListener('click', function() {
        var novaProspeccaoNome = document.getElementById('nome_prospecto_novo').value.trim();
        var clienteIdParaNovaProspeccao = document.getElementById('nova_prospeccao_cliente_id').value;

        if (!novaProspeccaoNome) {
            alert('Por favor, insira o nome do prospecto.');
            return;
        }
        if (!clienteIdParaNovaProspeccao) {
            alert('Não foi possível associar a nova prospecção a um lead. Tente novamente.');
            return;
        }
        
        fetch(`${API_PROSPECCAO_URL}/salvar_prospeccao_simulado.php`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `nome_prospecto=${encodeURIComponent(novaProspeccaoNome)}&cliente_id=${encodeURIComponent(clienteIdParaNovaProspeccao)}&usuario_id=<?= $_SESSION['user_id'] ?>`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Nova prospecção cadastrada com sucesso!');
                modalNovaProspeccao.classList.add('hidden');
                formNovaProspeccao.reset();

                var clienteSelect = document.getElementById('cliente_id');
                var currentClienteId = clienteSelect.value;
                if (currentClienteId) {
                    var event = new Event('change');
                    clienteSelect.dispatchEvent(event);

                    if (data.new_prospeccao_id) {
                            setTimeout(() => {
                                document.getElementById('prospeccao_id').value = data.new_prospeccao_id;
                            }, 200);
                    }
                }

            } else {
                alert('Erro ao cadastrar nova prospecção: ' + (data.message || 'Verifique os dados.'));
            }
        })
        .catch(error => {
            console.error('Erro no Fetch ao salvar nova prospecção:', error);
            alert('Ocorreu um erro de comunicação ao cadastrar a nova prospecção.');
        });
    });

});
</script>

<?php 
require_once __DIR__ . '/../../app/views/layouts/footer.php'; 
?>