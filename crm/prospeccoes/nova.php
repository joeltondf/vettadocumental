<?php
// Arquivo: crm/prospeccoes/nova.php (VERSÃO FINAL COM SUBMISSÃO ROBUSTA)

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../app/core/auth_check.php';
require_once __DIR__ . '/../../app/utils/LeadCategory.php';

try {
    $leadCategories = LeadCategory::all();
    $userPerfil = $_SESSION['user_perfil'] ?? '';
    $userId = (int)($_SESSION['user_id'] ?? 0);

    $sqlClientes = "SELECT id, nome_cliente, nome_responsavel, crmOwnerId FROM clientes WHERE is_prospect = 1";
    $paramsClientes = [];

    if ($userPerfil === 'vendedor' && $userId > 0) {
        $sqlClientes .= " AND crmOwnerId = :ownerId";
        $paramsClientes[':ownerId'] = $userId;
    }

    $sqlClientes .= " ORDER BY nome_cliente ASC";

    $stmt_clientes = $pdo->prepare($sqlClientes);
    $stmt_clientes->execute($paramsClientes);
    $clientes = $stmt_clientes->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Erro ao buscar leads: " . $e->getMessage());
}

$cliente_pre_selecionado_id = filter_input(INPUT_GET, 'cliente_id', FILTER_VALIDATE_INT);

if (!empty($cliente_pre_selecionado_id)) {
    $clienteDisponivel = array_filter($clientes, static function (array $cliente) use ($cliente_pre_selecionado_id) {
        return (int)$cliente['id'] === (int)$cliente_pre_selecionado_id;
    });

    if (empty($clienteDisponivel)) {
        $cliente_pre_selecionado_id = null;
    }
}

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

    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert">
            <p><?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?></p>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
            <p><?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?></p>
        </div>
    <?php endif; ?>

    <?php if (empty($clientes)): ?>
        <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 mb-6" role="alert">
            <p class="font-bold">Nenhum lead encontrado!</p>
            <p>Você precisa cadastrar um lead antes. <a href="<?php echo APP_URL; ?>/crm/clientes/novo.php" class="font-bold underline hover:text-yellow-800">Clique aqui para cadastrar.</a></p>
        </div>
    <?php else: ?>
        <form action="<?php echo APP_URL; ?>/crm/prospeccoes/salvar.php" method="POST" id="form-nova-prospeccao" class="space-y-6">
            <div>
                <label for="cliente_id" class="block text-sm font-medium text-gray-700">Nome do Lead</label>
                <div class="flex items-center space-x-2 mt-1">
                    <select name="cliente_id" id="cliente_id" class="block w-full">
                        <option></option>
                        <?php foreach ($clientes as $cliente): ?>
                            <option value="<?php echo $cliente['id']; ?>" <?php echo ($cliente['id'] == $cliente_pre_selecionado_id) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cliente['nome_cliente']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <a href="<?php echo APP_URL; ?>/crm/clientes/novo.php?redirect_url=<?php echo urlencode(APP_URL . '/crm/prospeccoes/nova.php'); ?>"
                       class="flex-shrink-0 bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded-md text-sm">
                        Novo Lead
                    </a>
                </div>
            </div>

            <div>
                <label for="lead_category" class="block text-sm font-medium text-gray-700">Categoria do Lead</label>
                <select name="lead_category" id="lead_category" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3">
                    <?php foreach ($leadCategories as $category): ?>
                        <option value="<?php echo htmlspecialchars($category); ?>" <?php echo $category === LeadCategory::DEFAULT ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($category); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
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
            placeholder: "Selecione um lead...",
            allowClear: true
        });

        const form = document.getElementById('form-nova-prospeccao');
        const leadSelect = document.getElementById('cliente_id');
        if (form) {
            form.addEventListener('submit', function(event) {
                const clienteId = $('#cliente_id').val();

                if (!clienteId || clienteId === '') {
                    event.preventDefault();
                    alert('Erro: Por favor, selecione um lead associado.');
                    return;
                }
            });
        }
        // --- FIM DA CORREÇÃO PRINCIPAL ---
    });
</script>

<?php 
require_once __DIR__ . '/../../app/views/layouts/footer.php'; 
?>