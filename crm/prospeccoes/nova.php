<?php
// Arquivo: crm/prospeccoes/nova.php (VERSÃO FINAL COM SUBMISSÃO ROBUSTA)

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../app/core/auth_check.php';

try {
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

require_once __DIR__ . '/../../app/views/layouts/crm_start.php';
?>

<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<style>
    /* Estilos do Select2 */
    .select2-container .select2-selection--single { height: 2.5rem; border: 1px solid #d1d5db; border-radius: 0.375rem; }
    .select2-container--default .select2-selection--single .select2-selection__rendered { line-height: 2.5rem; padding-left: 0.75rem; }
    .select2-container--default .select2-selection--single .select2-selection__arrow { height: 2.5rem; }
</style>

<section class="crm-section">
    <div class="crm-card">
        <h1 class="crm-card-title">Criar Nova Prospecção</h1>

        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-emerald-800">
                <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-red-700">
                <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
            </div>
        <?php endif; ?>

        <?php if (empty($clientes)): ?>
            <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-amber-700">
                <p class="font-semibold">Nenhum lead encontrado!</p>
                <p>Você precisa cadastrar um lead antes. <a href="<?php echo APP_URL; ?>/crm/clientes/novo.php" class="font-semibold underline hover:text-amber-800">Clique aqui para cadastrar.</a></p>
            </div>
        <?php else: ?>
            <form action="<?php echo APP_URL; ?>/crm/prospeccoes/salvar.php" method="POST" id="form-nova-prospeccao" class="space-y-6 mt-6">
                <div>
                    <label for="cliente_id" class="block text-sm font-medium text-gray-700">Nome do Lead</label>
                    <div class="flex flex-col sm:flex-row sm:items-center sm:space-x-3 mt-2 gap-3">
                        <select name="cliente_id" id="cliente_id" class="block w-full">
                            <option></option>
                            <?php foreach ($clientes as $cliente): ?>
                                <option value="<?php echo $cliente['id']; ?>" <?php echo ($cliente['id'] == $cliente_pre_selecionado_id) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cliente['nome_cliente']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <a href="<?php echo APP_URL; ?>/crm/clientes/novo.php?redirect_url=<?php echo urlencode(APP_URL . '/crm/prospeccoes/nova.php'); ?>"
                           class="inline-flex items-center justify-center bg-emerald-600 hover:bg-emerald-700 text-white font-semibold py-2.5 px-4 rounded-xl text-sm">
                            Novo Lead
                        </a>
                    </div>
                </div>

                <div class="flex flex-col sm:flex-row justify-end gap-3 pt-4">
                    <a href="<?php echo APP_URL; ?>/crm/prospeccoes/lista.php" class="inline-flex justify-center items-center bg-slate-200 text-slate-800 font-medium py-2.5 px-4 rounded-xl hover:bg-slate-300 transition">Cancelar</a>
                    <button type="submit" class="inline-flex justify-center items-center bg-blue-600 text-white font-medium py-2.5 px-4 rounded-xl hover:bg-blue-700 transition">Salvar Prospecção</button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</section>

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

<?php require_once __DIR__ . '/../../app/views/layouts/crm_end.php'; ?>