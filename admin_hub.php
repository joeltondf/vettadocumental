<?php
// admin_hub.php - Centraliza cadastros e configurações administrativas

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/app/core/auth_check.php';
require_once __DIR__ . '/app/core/access_control.php';
require_once __DIR__ . '/app/controllers/ProdutosOrcamentoController.php';
require_once __DIR__ . '/app/models/CategoriaFinanceira.php';

require_permission(['admin', 'gerencia']);

$produtosController = new ProdutosOrcamentoController($pdo);
$action = $_GET['action'] ?? 'index';
$action = is_string($action) ? $action : 'index';

$id = null;
if (isset($_GET['id'])) {
    $filteredId = filter_var($_GET['id'], FILTER_VALIDATE_INT);
    if ($filteredId !== false && $filteredId > 0) {
        $id = $filteredId;
    }
}

if (in_array($action, ['store', 'update', 'delete'], true)) {
    switch ($action) {
        case 'store':
            $produtosController->store();
            break;
        case 'update':
            $produtosController->update($id);
            break;
        case 'delete':
            $produtosController->delete($id);
            break;
    }
}

$categoriaModel = new CategoriaFinanceira($pdo);
$produtos = $categoriaModel->getProdutosOrcamento(true);

$pageTitle = 'Hub Administrativo';
$bodyClass = 'bg-slate-100 text-slate-800';

require_once __DIR__ . '/app/views/layouts/header.php';
?>

<div class="max-w-7xl mx-auto px-4 py-8">
    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
        <aside class="bg-white rounded-xl shadow border border-slate-200 p-5 h-max lg:sticky lg:top-6">
            <div class="flex items-center space-x-3 mb-6">
                <div class="p-3 rounded-lg bg-slate-100 text-theme-color">
                    <i class="fas fa-cog"></i>
                </div>
                <div>
                    <p class="text-sm text-slate-500">Configurações</p>
                    <h2 class="text-xl font-bold text-slate-800">Hub Administrativo</h2>
                </div>
            </div>
            <nav class="space-y-2" aria-label="Menu Administrativo">
                <a href="#produtos-orcamento" class="flex items-center justify-between px-3 py-2 rounded-lg border border-slate-200 bg-slate-50 text-slate-800 font-medium hover:border-theme-color">
                    <span><i class="fas fa-box-open mr-2 text-theme-color"></i>Produtos de Orçamento</span>
                    <i class="fas fa-chevron-right text-xs"></i>
                </a>
                <a href="<?php echo APP_URL; ?>/users.php" class="flex items-center justify-between px-3 py-2 rounded-lg border border-slate-200 text-slate-700 hover:border-theme-color">
                    <span><i class="fas fa-users-cog mr-2 text-slate-600"></i>Usuários &amp; Permissões</span>
                    <i class="fas fa-chevron-right text-xs"></i>
                </a>
                <a href="<?php echo APP_URL; ?>/categorias.php" class="flex items-center justify-between px-3 py-2 rounded-lg border border-slate-200 text-slate-700 hover:border-theme-color">
                    <span><i class="fas fa-tags mr-2 text-slate-600"></i>Categorias Financeiras</span>
                    <i class="fas fa-chevron-right text-xs"></i>
                </a>
                <a href="<?php echo APP_URL; ?>/tradutores.php" class="flex items-center justify-between px-3 py-2 rounded-lg border border-slate-200 text-slate-700 hover:border-theme-color">
                    <span><i class="fas fa-language mr-2 text-slate-600"></i>Tradutores</span>
                    <i class="fas fa-chevron-right text-xs"></i>
                </a>
                <a href="<?php echo APP_URL; ?>/admin.php?action=smtp_settings" class="flex items-center justify-between px-3 py-2 rounded-lg border border-slate-200 text-slate-700 hover:border-theme-color">
                    <span><i class="fas fa-envelope-open-text mr-2 text-slate-600"></i>Configurações de E-mail/SMTP</span>
                    <i class="fas fa-chevron-right text-xs"></i>
                </a>
            </nav>
        </aside>

        <main class="lg:col-span-3">
            <section id="produtos-orcamento" class="bg-white rounded-xl shadow border border-slate-200 p-6">
                <div class="flex items-center justify-between mb-5">
                    <div>
                        <p class="text-sm text-slate-500">Cadastros Auxiliares</p>
                        <h1 class="text-2xl font-bold text-slate-800">Produtos de Orçamento</h1>
                    </div>
                    <div class="text-sm text-slate-500 flex items-center space-x-2">
                        <i class="fas fa-shield-alt text-theme-color"></i>
                        <span>Acesso restrito a Administração</span>
                    </div>
                </div>

                <?php include_once __DIR__ . '/app/views/partials/messages.php'; ?>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <div class="border border-slate-200 rounded-lg p-5 bg-slate-50">
                        <h3 id="form-title" class="text-lg font-semibold text-slate-800 mb-4">Adicionar / Editar Produto</h3>
                        <form id="produto-form" action="admin_hub.php?action=store" method="POST" class="space-y-4">
                            <input type="hidden" name="id" id="produto_id">
                            <input type="hidden" name="codigo_integracao" id="codigo_integracao" value="">
                            <input type="hidden" name="codigo" id="codigo" value="">
                            <input type="hidden" name="unidade" id="unidade" value="UN">
                            <input type="hidden" name="ncm" id="ncm" value="0000.00.00">
                            <input type="hidden" name="cfop" id="cfop" value="">
                            <input type="hidden" name="codigo_servico_municipal" id="codigo_servico_municipal" value="">

                            <div>
                                <label for="nome_categoria" class="block text-sm font-medium text-slate-700 mb-1">Nome do Produto</label>
                                <div class="relative">
                                    <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                                        <i class="fas fa-pencil-alt"></i>
                                    </div>
                                    <input type="text" name="nome_categoria" id="nome_categoria" required class="block w-full p-2 border border-slate-300 rounded-md shadow-sm pl-10 py-2.5" placeholder="Ex: Tradução Juramentada de Passaporte">
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label for="valor_padrao" class="block text-sm font-medium text-slate-700 mb-1">Valor do Produto (R$)</label>
                                    <div class="relative">
                                        <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                                            <i class="fas fa-dollar-sign"></i>
                                        </div>
                                        <input type="text" name="valor_padrao" id="valor_padrao" data-currency-input required class="block w-full p-2 border border-slate-300 rounded-md shadow-sm pl-10 py-2.5" placeholder="Ex: R$ 150,00">
                                    </div>
                                </div>

                                <div>
                                    <label for="servico_tipo" class="block text-sm font-medium text-slate-700 mb-1">Tipo de Serviço Vinculado</label>
                                    <select name="servico_tipo" id="servico_tipo" class="block w-full p-2 border border-slate-300 rounded-md shadow-sm">
                                        <option value="Nenhum">Não vincular a um serviço específico</option>
                                        <option value="Tradução">Tradução</option>
                                        <option value="CRC">CRC</option>
                                        <option value="Apostilamento">Apostilamento</option>
                                        <option value="Postagem">Postagem</option>
                                        <option value="Outros">Outros</option>
                                    </select>
                                    <p class="text-xs text-slate-500 mt-1">A seleção determina em quais formulários o produto estará disponível.</p>
                                </div>
                            </div>

                            <div>
                                <span class="block text-sm font-medium text-slate-700 mb-2">Status do Produto</span>
                                <div class="flex items-center space-x-6">
                                    <label class="inline-flex items-center cursor-pointer">
                                        <input type="radio" name="ativo" value="1" id="status-ativo" class="h-4 w-4 text-blue-600 border-slate-300" checked>
                                        <span class="ml-2 text-sm text-slate-700">Ativo</span>
                                    </label>
                                    <label class="inline-flex items-center cursor-pointer">
                                        <input type="radio" name="ativo" value="0" id="status-inativo" class="h-4 w-4 text-blue-600 border-slate-300">
                                        <span class="ml-2 text-sm text-slate-700">Inativo</span>
                                    </label>
                                </div>
                            </div>

                            <div class="text-sm text-slate-600 bg-white border border-slate-200 rounded-lg p-4">
                                <p class="flex items-center">
                                    <i class="fas fa-info-circle text-blue-500 mr-2"></i>
                                    Os códigos de integração, unidade (<strong>UN</strong>) e NCM (<strong>0000.00.00</strong>) são definidos automaticamente e enviados para a Omie junto com o cadastro.
                                </p>
                            </div>

                            <div class="flex items-center space-x-2">
                                <input type="checkbox" name="bloquear_valor_minimo" value="1" id="bloquear_valor_minimo" class="h-4 w-4 text-indigo-600 ring-slate-400 rounded">
                                <label for="bloquear_valor_minimo" class="text-sm text-slate-700">Exigir este valor como mínimo nos formulários</label>
                            </div>

                            <div class="flex items-center space-x-2">
                                <input type="checkbox" name="sincronizar_omie" value="1" id="sincronizar_omie" class="h-4 w-4 text-indigo-600 ring-slate-400 rounded">
                                <label for="sincronizar_omie" class="text-sm text-slate-700">Sincronizar com a Omie após salvar</label>
                            </div>

                            <div class="flex items-center justify-end pt-4 space-x-3 border-t border-slate-200">
                                <button type="button" onclick="resetForm()" class="text-sm font-medium text-slate-600 px-5 py-2.5 rounded-lg border border-slate-300 hover:bg-slate-100">
                                    Cancelar
                                </button>
                                <button type="submit" id="submit-button" class="bg-theme-color hover:opacity-90 text-white font-bold py-2.5 px-6 rounded-lg shadow-md transition-transform transform hover:scale-105">
                                    <i class="fas fa-save mr-2"></i>
                                    Salvar Produto
                                </button>
                            </div>
                        </form>
                    </div>

                    <div class="overflow-hidden border border-slate-200 rounded-lg">
                        <div class="px-4 py-3 bg-slate-50 border-b border-slate-200 flex items-center justify-between">
                            <h3 class="text-lg font-semibold text-slate-800">Produtos cadastrados</h3>
                            <span class="text-xs text-slate-500">Total: <?php echo count($produtos); ?></span>
                        </div>
                        <div class="overflow-y-auto max-h-[620px]">
                            <table class="min-w-full divide-y divide-slate-200 text-sm">
                                <thead class="bg-slate-50 sticky top-0">
                                    <tr>
                                        <th class="px-4 py-3 text-left font-medium text-slate-600">Nome</th>
                                        <th class="px-4 py-3 text-center font-medium text-slate-600">Serviço</th>
                                        <th class="px-4 py-3 text-center font-medium text-slate-600">Valor Padrão</th>
                                        <th class="px-4 py-3 text-center font-medium text-slate-600">Valor Mínimo?</th>
                                        <th class="px-4 py-3 text-center font-medium text-slate-600">Código Omie</th>
                                        <th class="px-4 py-3 text-center font-medium text-slate-600">Integração</th>
                                        <th class="px-4 py-3 text-center font-medium text-slate-600">Status</th>
                                        <th class="px-4 py-3 text-center font-medium text-slate-600">Ações</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-slate-100">
                                    <?php foreach ($produtos as $produto): ?>
                                        <tr class="hover:bg-slate-50">
                                            <td class="px-4 py-3 font-medium text-slate-900"><?= htmlspecialchars($produto['nome_categoria']) ?></td>
                                            <?php $tipoServico = $produto['servico_tipo'] ?? 'Nenhum'; ?>
                                            <td class="px-4 py-3 text-center text-slate-600">
                                                <?= $tipoServico === 'Nenhum' ? '—' : htmlspecialchars($tipoServico) ?>
                                            </td>
                                            <td class="px-4 py-3 text-center text-slate-700">R$ <?= number_format($produto['valor_padrao'] ?? 0, 2, ',', '.') ?></td>
                                            <?php $exigeMinimo = !empty($produto['bloquear_valor_minimo']); ?>
                                            <td class="px-4 py-3 text-center text-slate-600"><?= $exigeMinimo ? 'Sim' : 'Não' ?></td>
                                            <td class="px-4 py-3 text-center text-slate-600"><?= htmlspecialchars($produto['omie_codigo_produto'] ?? '—') ?></td>
                                            <td class="px-4 py-3 text-center text-slate-600"><?= htmlspecialchars($produto['omie_codigo_integracao'] ?? '—') ?></td>
                                            <td class="px-4 py-3 text-center">
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?= $produto['ativo'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                                                    <?= $produto['ativo'] ? 'Ativo' : 'Inativo' ?>
                                                </span>
                                            </td>
                                            <td class="px-4 py-3 text-center space-x-3">
                                                <button onclick='editProduto(<?= json_encode($produto, JSON_HEX_APOS) ?>)' class="text-indigo-600 hover:text-indigo-900 font-semibold">Editar</button>
                                                <a href="admin_hub.php?action=delete&id=<?= $produto['id'] ?>" class="text-red-600 hover:text-red-900 font-semibold" onclick="return confirm('Tem certeza que deseja excluir este produto?')">Excluir</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </section>
        </main>
    </div>
</div>

<script>
function resolveProdutoId(produto) {
    if (!produto || typeof produto !== 'object') {
        return null;
    }

    const possibleId = produto.id ?? produto.ID ?? produto.local_produto_id ?? produto.cf_id ?? produto['cf.id'] ?? null;
    if (possibleId === null || possibleId === undefined) {
        return null;
    }

    const parsed = parseInt(possibleId, 10);
    return Number.isInteger(parsed) && parsed > 0 ? parsed : null;
}

function setProdutoStatus(value) {
    const ativoInput = document.getElementById('status-ativo');
    const inativoInput = document.getElementById('status-inativo');

    if (!ativoInput || !inativoInput) {
        return;
    }

    const normalizedValue = Number(value) === 1 ? 1 : 0;
    ativoInput.checked = normalizedValue === 1;
    inativoInput.checked = normalizedValue === 0;
}

function setCurrencyValue(input, value) {
    if (!input) {
        return;
    }

    if (window.CurrencyMask && typeof window.CurrencyMask.setValue === 'function') {
        window.CurrencyMask.setValue(input, value ?? '');
        return;
    }

    input.value = value ?? '';
}

function editProduto(produto) {
    const form = document.getElementById('produto-form');
    const title = document.getElementById('form-title');
    const submitButton = document.getElementById('submit-button');

    const produtoId = resolveProdutoId(produto);
    if (!produtoId) {
        alert('Não foi possível identificar o produto selecionado para edição.');
        return;
    }

    form.action = 'admin_hub.php?action=update&id=' + produtoId;

    document.getElementById('produto_id').value = produtoId;
    document.getElementById('nome_categoria').value = produto.nome_categoria ?? '';

    const valorInput = document.getElementById('valor_padrao');
    setCurrencyValue(valorInput, produto.valor_padrao ?? '');

    document.getElementById('servico_tipo').value = produto.servico_tipo ?? 'Nenhum';
    setProdutoStatus(produto.ativo ?? 1);

    document.getElementById('codigo_integracao').value = produto.omie_codigo_integracao ?? '';
    document.getElementById('codigo').value = produto.omie_codigo_produto ?? '';
    document.getElementById('unidade').value = produto.unidade ?? 'UN';
    document.getElementById('ncm').value = produto.ncm ?? '0000.00.00';
    document.getElementById('cfop').value = produto.cfop ?? '';
    document.getElementById('codigo_servico_municipal').value = produto.codigo_servico_municipal ?? '';

    document.getElementById('bloquear_valor_minimo').checked = Boolean(produto.bloquear_valor_minimo);

    title.textContent = 'Editar Produto';
    submitButton.innerHTML = '<i class="fas fa-save mr-2"></i>Atualizar Produto';
}

function resetForm() {
    const form = document.getElementById('produto-form');
    form.reset();
    form.action = 'admin_hub.php?action=store';

    document.getElementById('produto_id').value = '';
    document.getElementById('codigo_integracao').value = '';
    document.getElementById('codigo').value = '';
    document.getElementById('unidade').value = 'UN';
    document.getElementById('ncm').value = '0000.00.00';
    document.getElementById('cfop').value = '';
    document.getElementById('codigo_servico_municipal').value = '';

    document.getElementById('servico_tipo').value = 'Nenhum';
    setProdutoStatus(1);

    document.getElementById('bloquear_valor_minimo').checked = false;
    document.getElementById('sincronizar_omie').checked = false;

    const title = document.getElementById('form-title');
    const submitButton = document.getElementById('submit-button');
    title.textContent = 'Adicionar / Editar Produto';
    submitButton.innerHTML = '<i class="fas fa-save mr-2"></i>Salvar Produto';
}
</script>

<?php require_once __DIR__ . '/app/views/layouts/footer.php'; ?>
