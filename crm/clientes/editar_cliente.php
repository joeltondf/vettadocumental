<?php
// Arquivo: crm/clientes/editar_cliente.php (VERSÃO FINAL COM BOTÃO DE EXCLUIR)

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../app/core/auth_check.php';

// Valida o ID do cliente na URL
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    header('Location: ' . APP_URL . '/crm/clientes/lista.php');
    exit;
}

try {
    // Busca os dados do cliente
    $stmt = $pdo->prepare("SELECT * FROM clientes WHERE id = ?");
    $stmt->execute([$id]);
    $cliente = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$cliente) {
        $_SESSION['error_message'] = "Cliente não encontrado.";
        header('Location: ' . APP_URL . '/crm/clientes/lista.php');
        exit;
    }

} catch (PDOException $e) {
    die("Erro ao buscar dados do cliente: " . $e->getMessage());
}

$pageTitle = "Editar Cliente";
require_once __DIR__ . '/../../app/views/layouts/header.php';
?>

<div class="bg-white shadow px-4 py-5 sm:rounded-lg sm:p-6">
    <div class="md:flex md:items-center md:justify-between border-b border-gray-200 pb-4">
        <h1 class="text-2xl font-bold leading-7 text-gray-900 sm:text-3xl sm:truncate">
            Editar Cliente: <span class="text-blue-600"><?php echo htmlspecialchars($cliente['nome_cliente']); ?></span>
        </h1>
    </div>

    <form action="<?php echo APP_URL; ?>/crm/clientes/salvar.php" method="POST" class="space-y-6 mt-6">
        <input type="hidden" name="id" value="<?php echo $cliente['id']; ?>">
        <?php if (isset($_GET['prospeccao_id'])): ?>
            <input type="hidden" name="prospeccao_id" value="<?php echo htmlspecialchars($_GET['prospeccao_id']); ?>">
        <?php endif; ?>

        <?php if (isset($_GET['vendedor_id'])): ?>
            <input type="hidden" name="vendedor_id" value="<?php echo htmlspecialchars($_GET['vendedor_id']); ?>">
        <?php endif; ?>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label for="nome_cliente" class="block text-sm font-medium text-gray-700">Nome do Cliente / Empresa</label>
                <input type="text" name="nome_cliente" id="nome_cliente" required class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3" value="<?php echo htmlspecialchars($cliente['nome_cliente']); ?>">
            </div>
            <div>
                <label for="nome_responsavel" class="block text-sm font-medium text-gray-700">Nome do Contato Principal</label>
                <input type="text" name="nome_responsavel" id="nome_responsavel" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3" value="<?php echo htmlspecialchars($cliente['nome_responsavel']); ?>">
            </div>
            <div>
                <label for="email" class="block text-sm font-medium text-gray-700">Email</label>
                <input id="email" name="email" type="email" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3" value="<?php echo htmlspecialchars($cliente['email']); ?>">
            </div>
            <div>
                <label for="telefone" class="block text-sm font-medium text-gray-700">Telefone</label>
                <input type="tel" name="telefone" id="telefone" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3" value="<?php echo htmlspecialchars($cliente['telefone']); ?>">
            </div>
            <div>
                <label for="canal_origem" class="block text-sm font-medium text-gray-700">Canal de Origem</label>
                <select name="canal_origem" id="canal_origem" required class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3">
                    <option value="Call" <?php echo ($cliente['canal_origem'] == 'Call') ? 'selected' : ''; ?>>Call</option>
                    <option value="LinkedIn" <?php echo ($cliente['canal_origem'] == 'LinkedIn') ? 'selected' : ''; ?>>LinkedIn</option>
                    <option value="Instagram" <?php echo ($cliente['canal_origem'] == 'Instagram') ? 'selected' : ''; ?>>Instagram</option>
                    <option value="Whatsapp" <?php echo ($cliente['canal_origem'] == 'Whatsapp') ? 'selected' : ''; ?>>Whatsapp</option>
                    <option value="Indicação Cliente" <?php echo ($cliente['canal_origem'] == 'Indicação Cliente') ? 'selected' : ''; ?>>Indicação Cliente</option>
                    <option value="Indicação Cartório" <?php echo ($cliente['canal_origem'] == 'Indicação Cartório') ? 'selected' : ''; ?>>Indicação Cartório</option>
                    <option value="Website" <?php echo ($cliente['canal_origem'] == 'Website') ? 'selected' : ''; ?>>Website</option>
                    <option value="Bitrix" <?php echo ($cliente['canal_origem'] == 'Bitrix') ? 'selected' : ''; ?>>Bitrix</option>
                    <option value="Evento" <?php echo ($cliente['canal_origem'] == 'Evento') ? 'selected' : ''; ?>>Evento</option>
                    <option value="Outro" <?php echo ($cliente['canal_origem'] == 'Outro') ? 'selected' : ''; ?>>Outro</option>
                </select>
            </div>
            <div>
                <label for="categoria" class="block text-sm font-medium text-gray-700">Categoria</label>
                <select name="categoria" id="categoria" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3">
                    <option value="Entrada" <?php echo ($cliente['categoria'] == 'Entrada') ? 'selected' : ''; ?>>Entrada</option>
                    <option value="Qualificado" <?php echo ($cliente['categoria'] == 'Qualificado') ? 'selected' : ''; ?>>Qualificado</option>
                    <option value="Com Orçamento" <?php echo ($cliente['categoria'] == 'Com Orçamento') ? 'selected' : ''; ?>>Com Orçamento</option>
                    <option value="Em Negociação" <?php echo ($cliente['categoria'] == 'Em Negociação') ? 'selected' : ''; ?>>Em Negociação</option>
                    <option value="Cliente Ativo" <?php echo ($cliente['categoria'] == 'Cliente Ativo') ? 'selected' : ''; ?>>Cliente Ativo</option>
                    <option value="Sem Interesse" <?php echo ($cliente['categoria'] == 'Sem Interesse') ? 'selected' : ''; ?>>Sem Interesse</option>
                </select>
            </div>
        </div>

        <div class="pt-5 flex justify-between items-center border-t border-gray-200 mt-6">
            <div>
                <button type="button" onclick="confirmarExclusao()" class="bg-red-600 text-white font-bold py-2 px-4 rounded-lg hover:bg-red-700 transition duration-300">
                    Excluir Cliente
                </button>
            </div>

            <div>
                <a href="<?php echo APP_URL; ?>/crm/clientes/lista.php" class="bg-gray-200 text-gray-700 font-bold py-2 px-4 rounded-lg hover:bg-gray-300 mr-3 transition duration-300">Cancelar</a>
                <button type="submit" class="bg-blue-600 text-white font-bold py-2 px-4 rounded-lg hover:bg-blue-700 transition duration-300">Salvar Alterações</button>
            </div>
        </div>
    </form>
</div>

<form id="formExcluirCliente" action="<?php echo APP_URL; ?>/crm/clientes/excluir_cliente.php" method="POST" class="hidden">
    <input type="hidden" name="id" value="<?php echo $cliente['id']; ?>">
</form>

<script>
    function confirmarExclusao() {
        if (confirm('ATENÇÃO:\n\nTem certeza que deseja excluir este cliente?\n\nTodas as prospecções ligadas a ele ficarão sem cliente associado.')) {
            document.getElementById('formExcluirCliente').submit();
        }
    }
</script>

<?php 
require_once __DIR__ . '/../../app/views/layouts/footer.php'; 
?>
