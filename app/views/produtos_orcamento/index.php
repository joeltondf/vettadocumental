<?php
// app/views/produtos_orcamento/index.php
$pageTitle = 'Produtos de Orçamento';
?>

<div class="container mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-3xl font-bold text-gray-800">Produtos/Documentos</h1>
        <a href="fluxo_caixa.php" class="bg-gray-600 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded-lg shadow-md flex items-center">
            <i class="fas fa-arrow-left mr-2"></i>
            Voltar ao Fluxo de Caixa
        </a>
    </div>

    <?php include_once __DIR__ . '/../partials/messages.php'; ?>

    <div class="bg-white p-6 rounded-2xl shadow-lg border border-gray-200 mb-6">
        <h3 id="form-title" class="text-xl font-bold text-gray-800 mb-5 border-b-2 border-gray-100 pb-4">Adicionar / Editar Produto</h3>
        <form id="produto-form" action="produtos_orcamento.php?action=store" method="POST">
            <input type="hidden" name="id" id="produto_id">
            
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-x-6 gap-y-5">
                <div class="lg:col-span-2">
                    <label for="nome_categoria" class="block text-sm font-medium text-gray-700 mb-1">Nome / Descrição</label>
                    <div class="relative">
                        <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                            <i class="fas fa-pencil-alt text-gray-400"></i>
                        </div>
                        <input type="text" name="nome_categoria" id="nome_categoria" required class="block w-full p-2 border border-gray-300 rounded-md shadow-sm pl-10 shadow-sm py-2.5" placeholder="Ex: Tradução Juramentada de Passaporte">
                    </div>
                </div>

                <div>
                    <label for="valor_padrao" class="block text-sm font-medium text-gray-700 mb-1">Valor Padrão (R$)</label>
                    <div class="relative">
                         <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                            <i class="fas fa-dollar-sign text-gray-400"></i>
                        </div>
                        <input type="number" step="0.01" name="valor_padrao" id="valor_padrao" class="block w-full p-2 border border-gray-300 rounded-md shadow-sm pl-10 shadow-sm py-2.5" placeholder="Ex: 150.00">
                    </div>
                </div>

                <div>
                    <label for="servico_tipo" class="block text-sm font-medium text-gray-700 mb-1">Tipo de Serviço</label>
                     <div class="relative">
                        <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                            <i class="fas fa-briefcase text-gray-400"></i>
                        </div>
                        <select name="servico_tipo" id="servico_tipo" class="block w-full p-2 border border-gray-300 rounded-md shadow-sm pl-10 shadow-sm py-2.5">
                            <option value="Tradução">Serviço de Tradução</option>
                            <option value="CRC">Serviço de CRC</option>
                        </select>
                    </div>
                </div>

                 <div>
                    <label for="ativo" class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                    <div class="relative">
                        <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                           <i class="fas fa-power-off text-gray-400"></i>
                        </div>
                        <select name="ativo" id="ativo" class="block w-full p-2 border border-gray-300 rounded-md shadow-sm pl-10 shadow-sm py-2.5">
                            <option value="1">Ativo</option>
                            <option value="0">Inativo</option>
                        </select>
                    </div>
                </div>
                
                <div class="flex items-end pb-2 lg:col-start-2">
                    <label class="flex items-center">
                        <input type="checkbox" name="bloquear_valor_minimo" value="1" id="bloquear_valor_minimo" class="h-4 w-4 text-indigo-600 ring-gray-400 rounded">
                        <span class="ml-2 text-sm text-gray-700">Usar como valor mínimo</span>
                    </label>
                </div>
            </div>

            <div class="flex items-center justify-end mt-6 border-t pt-5 space-x-3">
                 <button type="button" onclick="resetForm()" class="text-sm font-medium text-gray-600 px-5 py-2.5 rounded-lg border ring-gray-400 hover:bg-gray-100">
                    Cancelar
                 </button>
                 <button type="submit" id="submit-button" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2.5 px-6 rounded-lg shadow-md transition-transform transform hover:scale-105">
                    <i class="fas fa-save mr-2"></i>
                    Salvar Produto
                </button>
            </div>
        </form>
    </div>
    <div class="overflow-x-auto bg-white rounded-lg shadow">
        <table class="min-w-full divide-y divide-gray-200">
             <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nome</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Valor Padrão</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Tipo de Serviço</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Ações</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php foreach ($produtos as $produto): ?>
                <tr>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?= htmlspecialchars($produto['nome_categoria']) ?></td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-center">R$ <?= number_format($produto['valor_padrao'] ?? 0, 2, ',', '.') ?></td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-center"><?= htmlspecialchars($produto['servico_tipo']) ?></td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-center">
                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?= $produto['ativo'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                            <?= $produto['ativo'] ? 'Ativo' : 'Inativo' ?>
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-center">
                        <button onclick='editProduto(<?= json_encode($produto, JSON_HEX_APOS) ?>)' class="text-indigo-600 hover:text-indigo-900">Editar</button>
                        <a href="produtos_orcamento.php?action=delete&id=<?= $produto['id'] ?>" class="text-red-600 hover:text-red-900 ml-4" onclick="return confirm('Tem certeza que deseja excluir este produto?')">Excluir</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function editProduto(produto) {
    const form = document.getElementById('produto-form');
    const title = form.querySelector('h3'); // Usando h3 como alvo do título
    const submitButton = document.getElementById('submit-button');

    if(title) title.innerText = 'Editar Produto';
    form.action = `produtos_orcamento.php?action=update&id=${produto.id}`;
    submitButton.innerHTML = '<i class="fas fa-save mr-2"></i> Atualizar Produto';
    
    document.getElementById('produto_id').value = produto.id;
    document.getElementById('nome_categoria').value = produto.nome_categoria;
    document.getElementById('valor_padrao').value = produto.valor_padrao;
    document.getElementById('servico_tipo').value = produto.servico_tipo;
    document.getElementById('ativo').value = produto.ativo;
    document.getElementById('bloquear_valor_minimo').checked = produto.bloquear_valor_minimo == 1;

    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function resetForm() {
    const form = document.getElementById('produto-form');
    const title = form.querySelector('h3');
    const submitButton = document.getElementById('submit-button');

    if(title) title.innerText = 'Adicionar Novo Produto';
    form.action = 'produtos_orcamento.php?action=store';
    form.reset();
    document.getElementById('produto_id').value = '';
    submitButton.innerHTML = '<i class="fas fa-save mr-2"></i> Salvar Produto';
}
</script>