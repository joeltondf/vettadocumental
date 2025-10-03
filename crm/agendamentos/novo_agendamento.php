<?php
// Arquivo: crm/agendamentos/novo_agendamento.php (VERSÃO FINAL E CORRIGIDA)

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../app/core/auth_check.php';

$prospeccao_id = filter_input(INPUT_GET, 'prospeccao_id', FILTER_VALIDATE_INT);
if (!$prospeccao_id) {
    die("Prospecção inválida.");
}

try {
    $stmt = $pdo->prepare("SELECT p.nome_prospecto, c.nome_cliente FROM prospeccoes p JOIN clientes c ON p.cliente_id = c.id WHERE p.id = ?");
    $stmt->execute([$prospeccao_id]);
    $dados_prospeccao = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$dados_prospeccao) {
        die("Prospecção não encontrada.");
    }
} catch (PDOException $e) {
    die("Erro ao buscar dados da prospecção: " . $e->getMessage());
}

require_once __DIR__ . '/../../app/views/layouts/header.php';
?>

<div class="bg-white shadow sm:rounded-lg p-6">
    <h1 class="text-2xl font-bold text-gray-800 mb-2">Novo Agendamento</h1>
    <!-- Correção 3: Exibir 'nome_cliente' -->
    <p class="text-sm text-gray-600 mb-6">Para a oportunidade: <span class="font-semibold"><?php echo htmlspecialchars($dados_prospeccao['nome_prospecto']); ?></span> com o lead <span class="font-semibold"><?php echo htmlspecialchars($dados_prospeccao['nome_cliente']); ?></span>.</p>

<form action="<?php echo APP_URL; ?>/crm/agendamentos/salvar_agendamento.php" method="POST" class="space-y-6">
        <input type="hidden" name="prospeccao_id" value="<?php echo $prospeccao_id; ?>">
        
        <input type="hidden" name="redirect_to" value="<?php echo APP_URL; ?>/crm/prospeccoes/detalhes.php?id=<?php echo $prospeccao_id; ?>">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        </div>        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="md:col-span-2">
                <label for="titulo" class="block text-sm font-medium text-gray-700">Título do Evento</label>
                <input type="text" name="titulo" id="titulo" required class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
            </div>
            <div>
                <label for="data_inicio" class="block text-sm font-medium text-gray-700">Início</label>
                <input type="datetime-local" name="data_inicio" id="data_inicio" required class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
            </div>
            <div>
                <label for="data_fim" class="block text-sm font-medium text-gray-700">Fim</label>
                <input type="datetime-local" name="data_fim" id="data_fim" required class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
            </div>
            <div class="md:col-span-2">
                <label for="local_link" class="block text-sm font-medium text-gray-700">Canal / Link da Reunião (Google Meet, etc.)</label>
                <input type="text" name="local_link" id="local_link" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
            </div>
        </div>
        <div>
            <label for="observacoes" class="block text-sm font-medium text-gray-700">Observações</label>
            <textarea name="observacoes" id="observacoes" rows="4" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm"></textarea>
        </div>

        <div class="flex justify-end pt-4 border-t">
            <a href="<?php echo APP_URL; ?>/crm/prospeccoes/detalhes.php?id=<?php echo $prospeccao_id; ?>" class="bg-gray-200 text-gray-700 font-bold py-2 px-4 rounded-lg">Cancelar</a>
            <button type="submit" class="ml-3 bg-blue-600 text-white font-bold py-2 px-4 rounded-lg">Salvar Agendamento</button>
        </div>
    </form>
</div>

<?php 
// Correção 5: Caminho correto para o footer
require_once __DIR__ . '/../../app/views/layouts/footer.php'; 
?>