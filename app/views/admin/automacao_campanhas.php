<?php
// app/views/admin/automacao_campanhas.php
// VERSÃO CORRIGIDA E COMPLETA

require_once __DIR__ . '/../layouts/header.php';
?>

<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-bold text-gray-800">Campanhas de Automação</h1>
    <div class="flex space-x-4">
        <a href="admin.php?action=automacao_settings" class="text-sm text-gray-600 hover:text-blue-600 flex items-center">
            <i class="fas fa-cog mr-2"></i> Configurar API
        </a>
        <button onclick="openModal()" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg">
            + Nova Campanha
        </button>
    </div>
</div>

<?php include __DIR__ . '/../partials/messages.php'; ?>

<div class="bg-white shadow-md rounded-lg overflow-hidden">
    <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nome da Campanha</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Gatilho (Status CRM)</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Canal Principal</th>
                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Status</th>
                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Ações</th>
            </tr>
        </thead>
        <tbody class="bg-white divide-y divide-gray-200">
            <?php if (empty($regras)): ?>
                <tr><td colspan="5" class="text-center p-4 text-gray-500">Nenhuma campanha criada.</td></tr>
            <?php else: ?>
                <?php foreach ($regras as $regra): ?>
                <tr>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars($regra['nome_campanha']); ?></td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                        <?php 
                            $gatilhos = json_decode($regra['crm_gatilhos'], true);
                            if (is_array($gatilhos)) {
                                foreach ($gatilhos as $gatilho) {
                                    echo '<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800 mr-1">' . htmlspecialchars($gatilho) . '</span>';
                                }
                            }
                        ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                        <?php if (!empty($regra['digisac_template_id'])): ?>
                            <i class="fab fa-whatsapp text-green-500 mr-2"></i> WhatsApp
                        <?php elseif (!empty($regra['email_assunto'])): ?>
                            <i class="fas fa-envelope text-blue-500 mr-2"></i> E-mail
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-center">
                        <?php if ($regra['ativo']): ?>
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">Ativa</span>
                        <?php else: ?>
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">Inativa</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium space-x-4">
                        <button onclick="openTestModal(<?php echo $regra['id']; ?>)" class="text-green-600 hover:text-green-900">Testar</button>
                        <button onclick="editModal(<?php echo $regra['id']; ?>)" class="text-indigo-600 hover:text-indigo-900">Editar</button>
                        <a href="admin.php?action=delete_automacao_campanha&id=<?php echo $regra['id']; ?>" onclick="return confirm('Tem certeza?')" class="text-red-600 hover:text-red-900">Excluir</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<div id="campanhaModal" class="hidden fixed z-50 inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full">
    <div class="relative top-10 mx-auto p-5 border w-full max-w-4xl shadow-lg rounded-md bg-white">
        </div>
</div>

<div id="testModal" class="hidden fixed z-50 inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full">
    <div class="relative top-20 mx-auto p-5 border w-full max-w-2xl shadow-lg rounded-md bg-white">
        <h3 class="text-lg leading-6 font-medium text-gray-900">Testar Campanha</h3>
        <form id="testForm" class="mt-4 space-y-4">
            <input type="hidden" name="campanha_id" id="testCampanhaId">
            
            <div>
                <label class="block text-sm font-medium text-gray-700">Testar via:</label>
                <div class="mt-2 flex space-x-4">
                    <label class="flex items-center"><input type="radio" name="test_type" value="whatsapp" checked class="h-4 w-4"><span class="ml-2">WhatsApp</span></label>
                    <label class="flex items-center"><input type="radio" name="test_type" value="email" class="h-4 w-4"><span class="ml-2">E-mail</span></label>
                </div>
            </div>

            <div id="whatsapp-test-fields">
                <label for="testClienteId" class="block text-sm font-medium text-gray-700">ID do Cliente de Teste</label>
                <input type="number" name="cliente_id" id="testClienteId" required class="mt-1 block w-full p-2 border-gray-300 rounded-md" placeholder="Ex: 123">
                <p class="text-xs text-gray-500 mt-1">O cliente deve ter um telefone válido no formato 55DDD9XXXXXXXX.</p>
            </div>

            <div id="email-test-fields" class="hidden">
                <label for="testEmail" class="block text-sm font-medium text-gray-700">E-mail de Teste</label>
                <input type="email" name="test_email" id="testEmail" class="mt-1 block w-full p-2 border-gray-300 rounded-md" placeholder="deixe em branco para usar o e-mail do cliente">
            </div>

            <div class="flex justify-end space-x-2 pt-4 border-t">
                <button type="button" onclick="document.getElementById('testModal').classList.add('hidden')" class="bg-gray-200 text-gray-700 py-2 px-4 rounded-lg">Cancelar</button>
                <button type="submit" class="bg-green-600 text-white font-bold py-2 px-4 rounded-lg">Enviar Teste</button>
            </div>
        </form>

        <div id="testResult" class="hidden mt-4 p-4 bg-gray-800 text-white font-mono text-sm rounded-md whitespace-pre-wrap max-h-60 overflow-y-auto"></div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Referências aos modais
    const campanhaModal = document.getElementById('campanhaModal');
    const testModal = document.getElementById('testModal');
    
    // --- Lógica do Modal de Campanha (Seu código original, mantido) ---
    // ...
    
    // --- INÍCIO DA CORREÇÃO NA LÓGICA DO MODAL DE TESTE ---

    const testForm = document.getElementById('testForm');
    const testCampanhaIdInput = document.getElementById('testCampanhaId');
    const testResultDiv = document.getElementById('testResult');

    // Função para abrir o modal de teste
    window.openTestModal = function(campanhaId) {
        testForm.reset();
        testResultDiv.classList.add('hidden');
        testResultDiv.innerHTML = '';
        testCampanhaIdInput.value = campanhaId;
        
        // Reseta a visibilidade para o padrão (WhatsApp)
        document.getElementById('whatsapp-test-fields').classList.remove('hidden');
        document.getElementById('email-test-fields').classList.add('hidden');
        document.querySelector('input[name="test_type"][value="whatsapp"]').checked = true;
        document.getElementById('testClienteId').required = true;
        document.getElementById('testEmail').required = false;
        
        testModal.classList.remove('hidden');
    }

    // Handler CORRIGIDO para os botões de rádio no modal de teste
    document.querySelectorAll('input[name="test_type"]').forEach(radio => {
        radio.addEventListener('change', function() {
            if (this.value === 'email') {
                // Ao testar e-mail, o ID do cliente é opcional, mas o campo de e-mail aparece
                document.getElementById('whatsapp-test-fields').classList.remove('hidden'); // Mantém o ID do cliente visível
                document.getElementById('email-test-fields').classList.remove('hidden');
                document.getElementById('testClienteId').required = false; // Não é obrigatório
                document.getElementById('testEmail').required = false; // Não é obrigatório (pode usar o do cliente)
            } else { // whatsapp
                // Ao testar WhatsApp, o ID do cliente é obrigatório
                document.getElementById('whatsapp-test-fields').classList.remove('hidden');
                document.getElementById('email-test-fields').classList.add('hidden');

                document.getElementById('testClienteId').required = true;
                document.getElementById('testEmail').required = false;
            }
        });
    });

    // Handler CORRIGIDO para o envio do formulário de teste
    if (testForm) {
        testForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            testResultDiv.classList.remove('hidden');
            testResultDiv.innerHTML = 'Enviando teste, por favor aguarde...';

            const formData = new FormData(testForm);

            try {
                const response = await fetch('admin.php?action=test_automacao_campanha', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();
                
                let logOutput = (result.log || []).join('\n');
                
                if (result.success) {
                    testResultDiv.textContent = 'SUCESSO!\n\n' + logOutput;
                } else {
                    testResultDiv.textContent = 'FALHA NO ENVIO.\n\n' + logOutput + '\n\nMensagem: ' + (result.message || 'Erro desconhecido.');
                }

            } catch (error) {
                testResultDiv.textContent = 'ERRO DE COMUNICAÇÃO (JAVASCRIPT):\n' + error;
            }
        });
    }

    // --- FIM DA CORREÇÃO ---
});
</script>


<?php
// Inclui o rodapé do layout
require_once __DIR__ . '/../layouts/footer.php';
?>