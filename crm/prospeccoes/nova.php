<?php
// Arquivo: crm/prospeccoes/nova.php (VERSÃO FINAL COM SUBMISSÃO ROBUSTA)

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../app/core/auth_check.php';

try {
    $stmt_clientes = $pdo->query("SELECT id, nome_cliente FROM clientes ORDER BY nome_cliente ASC");
    $clientes = $stmt_clientes->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Erro ao buscar clientes: " . $e->getMessage());
}

$cliente_pre_selecionado_id = filter_input(INPUT_GET, 'cliente_id', FILTER_VALIDATE_INT);

require_once __DIR__ . '/../../app/views/layouts/header.php';
?>

<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<style>
    /* Estilos do Select2 */
    .select2-container .select2-selection--single { height: 2.5rem; border: 1px solid #d1d5db; border-radius: 0.375rem; }
    .select2-container--default .select2-selection--single .select2-selection__rendered { line-height: 2.5rem; padding-left: 0.75rem; }
    .select2-container--default .select2-selection--single .select2-selection__arrow { height: 2.5rem; }
</style>

<div class="bg-white shadow px-4 py-5 sm:rounded-lg sm:p-6">
    <h1 class="text-2xl font-bold text-gray-800 mb-6">Criar Nova Prospecção</h1>
    
    <?php if (empty($clientes)): ?>
        <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 mb-6" role="alert">
            <p class="font-bold">Nenhum cliente encontrado!</p>
            <p>Você precisa cadastrar um cliente antes. <a href="<?php echo APP_URL; ?>/crm/clientes/novo.php" class="font-bold underline hover:text-yellow-800">Clique aqui para cadastrar.</a></p>
        </div>
    <?php else: ?>
        <form action="<?php echo APP_URL; ?>/crm/prospeccoes/salvar.php" method="POST" id="form-nova-prospeccao" class="space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="md:col-span-2">
                    <label for="cliente_id" class="block text-sm font-medium text-gray-700">Cliente Associado</label>
                    <div class="flex items-center space-x-2 mt-1">
                        <select name="cliente_id" id="cliente_id" class="block w-full">
                            <option></option> <?php foreach ($clientes as $cliente): ?>
                                <option value="<?php echo $cliente['id']; ?>" <?php echo ($cliente['id'] == $cliente_pre_selecionado_id) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cliente['nome_cliente']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <a href="<?php echo APP_URL; ?>/crm/clientes/novo.php?redirect_url=<?php echo urlencode(APP_URL . '/crm/prospeccoes/nova.php'); ?>" 
                        class="flex-shrink-0 bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded-md text-sm">
                            Novo Cliente
                        </a>
                    </div>
                </div>                
                <div>
                    <label for="nome_prospecto" class="block text-sm font-medium text-gray-700">Nome da Oportunidade</label>
                    <input type="text" name="nome_prospecto" id="nome_prospecto" required class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3">
                </div>
                <div>
                    <label for="valor_proposto" class="block text-sm font-medium text-gray-700">Valor Proposto (R$)</label>
                    <input type="text" name="valor_proposto" id="valor_proposto" required class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3">
                </div>
                <div class="md:col-span-2">
                    <label for="status" class="block text-sm font-medium text-gray-700">Status Inicial</label>
                    <select name="status" id="status" required class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3">
                        <option value="Cliente ativo">Cliente ativo</option>
                        <option value="Primeiro contato">Primeiro contato</option>
                        <option value="Segundo contato">Segundo contato</option>
                        <option value="Terceiro contato">Terceiro contato</option>
                        <option value="Reunião agendada">Reunião agendada</option>
                        <option value="Proposta enviada">Proposta enviada</option>
                        <option value="Fechamento">Fechamento</option>
                        <option value="Pausar">Pausar</option>
                    </select>
                </div>
            </div>
            
            <div>
                <label for="feedback_inicial" class="block text-sm font-medium text-gray-700">Observações Iniciais</label>
                <textarea name="feedback_inicial" id="feedback_inicial" rows="4" required class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3"></textarea>
            </div>
            
            <div class="flex justify-end pt-4">
                <a href="<?php echo APP_URL; ?>/crm/prospeccoes/lista.php" class="bg-gray-200 text-gray-700 font-bold py-2 px-4 rounded hover:bg-gray-300 mr-3">Cancelar</a>
                <button type="submit" class="bg-blue-600 text-white font-bold py-2 px-4 rounded hover:bg-blue-700">Salvar Prospecção</button>
            </div>
        </form>
    <?php endif; ?>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // --- INÍCIO DA CORREÇÃO PRINCIPAL ---
        
        $('#cliente_id').select2({
            placeholder: "Selecione um cliente...",
            allowClear: true
        });

        const form = document.getElementById('form-nova-prospeccao');

        if (form) {
            form.addEventListener('submit', function(event) {
                // Pega o valor do Select2 no momento exato do envio
                const clienteId = $('#cliente_id').val();

                // Se o valor for nulo ou vazio, impede o envio e mostra um alerta
                if (!clienteId || clienteId === '') {
                    event.preventDefault(); // Impede o envio do formulário
                    alert('Erro: Por favor, selecione um cliente associado.');
                }
                // Se houver um valor, o formulário será enviado normalmente.
            });
        }
        // --- FIM DA CORREÇÃO PRINCIPAL ---
    });
</script>

<?php 
require_once __DIR__ . '/../../app/views/layouts/footer.php'; 
?>