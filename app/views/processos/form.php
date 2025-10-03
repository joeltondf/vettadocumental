<?php
// /app/views/processos/form.php
$isEditMode = isset($processo) && $processo !== null;
$cliente_pre_selecionado_id = $_GET['cliente_id'] ?? null;
$return_url = $_GET['return_to'] ?? 'processos.php';
global $pdo; // Torna a conexão PDO disponível no escopo da view
// Verificamos os parâmetros que são passados pela URL após a atualização do cliente
$fromProspeccao = isset($_GET['cliente_id']) && (isset($_GET['titulo']) || isset($_GET['prospeccao_id']));
$processStatus = isset($processo['status_processo']) ? $processo['status_processo'] : null;



// 2. Obtém os dados do usuário logado
$isVendedor = (isset($_SESSION['user_perfil']) && $_SESSION['user_perfil'] === 'vendedor');
$loggedInVendedorId = null;
if ($isVendedor && isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare("SELECT id FROM vendedores WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $vendedor_logado = $stmt->fetch();
    if ($vendedor_logado) {
        $loggedInVendedorId = $vendedor_logado['id'];
    }
}

// 3. Define qual cliente e vendedor devem ser pré-selecionados
$isEditMode = isset($processo) && $processo !== null;
$formData = $formData ?? [];
if ($isEditMode) {
    $processo = array_merge($processo ?? [], $formData);
} else {
    $processo = $formData;
}
$cliente_id_selecionado = $_GET['cliente_id'] ?? ($processo['cliente_id'] ?? null);

// Se for conversão, o vendedor é o logado. Se for edição, é o que está salvo no processo.
$vendedor_id_selecionado = $fromProspeccao ? $loggedInVendedorId : ($processo['vendedor_id'] ?? null);

// 4. Define se os campos devem ser desabilitados
$disableFields = false;



// O restante da sua lógica original continua aqui...
$return_url = $_GET['return_to'] ?? 'processos.php';
$tipos_traducao = $tipos_traducao ?? [];
$tipos_crc = $tipos_crc ?? [];
$financeiroServicos = [
    'Tradução' => $tipos_traducao,
    'CRC' => $tipos_crc,
];
$translationAttachments = isset($translationAttachments) && is_array($translationAttachments) ? $translationAttachments : [];
$crcAttachments = isset($crcAttachments) && is_array($crcAttachments) ? $crcAttachments : [];
$paymentProofAttachments = isset($paymentProofAttachments) && is_array($paymentProofAttachments) ? $paymentProofAttachments : [];
$reuseTranslationForCrc = !empty($processo['reuseTraducaoForCrc'] ?? $formData['reuseTraducaoForCrc'] ?? null);
?>

<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-bold text-gray-800"><?php echo $isEditMode ? 'Editar Orçamento' : 'Cadastrar Novo Orçamento'; ?></h1>
    <a href="<?php echo htmlspecialchars($return_url); ?>" class="bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded-lg shadow-md transition duration-200 ease-in-out">
        &larr; Voltar
    </a>
</div>

<form action="processos.php?action=<?php echo $isEditMode ? 'update' : 'store'; ?>" method="POST" enctype="multipart/form-data" id="processo-form" class="bg-white shadow-lg rounded-lg p-8 space-y-6">
    
    <input type="hidden" name="return_to" value="<?php echo htmlspecialchars($return_url); ?>">
    <?php if ($isEditMode): ?>
        <input type="hidden" name="id" value="<?php echo $processo['id']; ?>">
    <?php endif; ?>
    <?php
    // A variável $isEditMode já existe no topo deste arquivo.
    // Usaremos ela para definir o status inicial APENAS no modo de CRIAÇÃO.
    if (!$isEditMode) {
        // Se NÃO for modo de edição (ou seja, é um novo orçamento)
        if ($_SESSION['user_perfil'] === 'vendedor') {
            // Cenário 1: Vendedor cria -> Status = "Orçamento"
            echo '<input type="hidden" name="status_processo" value="Orçamento">';
        } else {
            echo '<input type="hidden" name="status_processo" value="Orçamento">';
        }
    }
    // Se for modo de edição, nenhum campo de status é adicionado, 
    // então o status original do processo é preservado ao salvar.
    ?>

    <?php if ($disableFields): ?>
        <input type="hidden" name="cliente_id" value="<?php echo htmlspecialchars($cliente_id_selecionado); ?>">
        <input type="hidden" name="vendedor_id" value="<?php echo htmlspecialchars($vendedor_id_selecionado); ?>">
    <?php endif; ?>

    <fieldset class="border border-gray-200 rounded-md p-6 space-y-6">
        <legend class="text-lg font-semibold text-gray-700 px-2 bg-white ml-4">Informações Gerais</legend>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            <div>
                <label for="orcamento_numero" class="block text-sm font-medium text-gray-700">Nº Orçamento</label>
                <input type="text" name="orcamento_numero" id="orcamento_numero" class="mt-1 block w-full p-2 bg-gray-100 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500" value="<?php echo htmlspecialchars($processo['orcamento_numero'] ?? 'Será gerado ao salvar'); ?>" readonly>
            </div>
            <div class="md:col-span-2 lg:col-span-1">
                <label for="titulo" class="block text-sm font-medium text-gray-700">Nome do Serviço / Família *</label>
                <input type="text" name="titulo" id="titulo" class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500" value="<?php echo htmlspecialchars($processo['titulo'] ?? ''); ?>" required>
            </div>
            <div class="md:col-span-2 lg:col-span-1">
                <?php
                    $clienteLabel = $isVendedor ? 'Lead' : 'Cliente (Assessoria) *';
                ?>
                <label for="cliente_id" class="block text-sm font-medium text-gray-700"><?php echo $clienteLabel; ?></label>
                <div class="flex items-center space-x-2 mt-1">
                    <select name="cliente_id" id="cliente_id" class="block w-full p-2 border border-gray-300 rounded-md shadow-sm" required <?php if ($disableFields) echo 'disabled'; ?>>
                        <option value="">Selecione...</option>
                        <?php if (!empty($clientes)): foreach ($clientes as $cliente): ?>
                            <option value="<?php echo $cliente['id']; ?>" data-tipo-assessoria="<?php echo $cliente['tipo_assessoria'] ?? ''; ?>" <?php echo ($cliente_id_selecionado == $cliente['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cliente['nome_cliente']); ?>
                            </option>
                        <?php endforeach; endif; ?>
                    </select>
                    <a href="clientes.php?action=create&return_to=<?php echo urlencode(APP_URL . $_SERVER['REQUEST_URI']); ?>" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-3 rounded-md text-sm whitespace-nowrap" title="Adicionar Novo Cliente">+</a>
                </div>
            </div>
            <div>
                <label for="vendedor_id" class="block text-sm font-medium text-gray-700">Vendedor *</label>
                <select name="vendedor_id" id="vendedor_id" class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm" required <?php if ($disableFields) echo 'disabled'; ?>>
                    <option value="">Selecione...</option>
                    <?php if (!empty($vendedores)): foreach ($vendedores as $vendedor): ?>
                        <option value="<?php echo $vendedor['id']; ?>" <?php echo ($vendedor_id_selecionado == $vendedor['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($vendedor['nome_vendedor']); ?>
                        </option>
                    <?php endforeach; endif; ?>
                </select>
            </div>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div>
                <label for="orcamento_origem" class="block text-sm font-medium text-gray-700">Origem do Orçamento</label>
                <select name="orcamento_origem" id="orcamento_origem" class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                    <option value="">Selecione a origem</option>
                    <?php
                    $origens = ['Bitrix', 'Facebook', 'Instagram', 'Google', 'Indicação Cartório', 'Indicação Cliente', 'Website', 'LinkedIn', 'Whatsapp'];
                    foreach ($origens as $origem) {
                        $selected = (($processo['orcamento_origem'] ?? '') == $origem) ? 'selected' : '';
                        echo "<option value='{$origem}' {$selected}>{$origem}</option>";
                    }
                    ?>
                </select>
            </div>
        </div>
    </fieldset>

    <fieldset class="border border-gray-200 rounded-md p-6 mt-6">
        <legend class="text-lg font-semibold text-gray-700 px-2 bg-white ml-4">Serviços Orçados</legend>
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mt-4">
            <?php
            $categorias = ['Tradução', 'CRC', 'Apostilamento', 'Postagem'];
            $categoriasRaw = $processo['categorias_servico'] ?? [];
            if (is_string($categoriasRaw)) {
                $categorias_selecionadas = array_filter(array_map('trim', explode(',', $categoriasRaw)));
            } elseif (is_array($categoriasRaw)) {
                $categorias_selecionadas = $categoriasRaw;
            } else {
                $categorias_selecionadas = [];
            }
            $slug_map = ['Tradução' => 'traducao', 'CRC' => 'crc', 'Apostilamento' => 'apostilamento', 'Postagem' => 'postagem'];
            $labelColorMap = [
                'Tradução' => 'border-blue-300 bg-blue-50 hover:bg-blue-100',
                'CRC' => 'border-green-300 bg-green-50 hover:bg-green-100',
                'Apostilamento' => 'border-yellow-300 bg-yellow-50 hover:bg-yellow-100',
                'Postagem' => 'border-purple-300 bg-purple-50 hover:bg-purple-100',
            ];
            $checkboxColorMap = [
                'Tradução' => 'text-blue-600 focus:ring-blue-500',
                'CRC' => 'text-green-600 focus:ring-green-500',
                'Apostilamento' => 'text-yellow-600 focus:ring-yellow-500',
                'Postagem' => 'text-purple-600 focus:ring-purple-500',
            ];
            $textColorMap = [
                'Tradução' => 'text-blue-800',
                'CRC' => 'text-green-800',
                'Apostilamento' => 'text-yellow-800',
                'Postagem' => 'text-purple-800',
            ];
            foreach ($categorias as $cat):
                $slug = $slug_map[$cat];
                $labelClasses = $labelColorMap[$cat];
                $checkboxClasses = $checkboxColorMap[$cat];
                $textClasses = $textColorMap[$cat];
            ?>
                <label for="cat_<?php echo $slug; ?>" class="flex items-center p-3 border rounded-lg cursor-pointer transition-all duration-200 <?php echo $labelClasses; ?>">
                    <input id="cat_<?php echo $slug; ?>" name="categorias_servico[]" type="checkbox" value="<?php echo $cat; ?>" class="h-5 w-5 border-gray-300 rounded service-checkbox <?php echo $checkboxClasses; ?>" data-target="section-<?php echo $slug; ?>" <?php echo in_array($cat, $categorias_selecionadas) ? 'checked' : ''; ?>>
                    <span class="ml-3 block text-sm font-semibold <?php echo $textClasses; ?>"><?php echo $cat; ?></span>
                </label>
            <?php endforeach; ?>
        </div>
    </fieldset>

    <div id="section-traducao" class="service-section hidden">
        <fieldset class="border border-blue-200 rounded-md p-6 bg-blue-50 space-y-6">
            <legend class="text-lg font-semibold text-blue-800 px-2 ml-4">Detalhes da Tradução</legend>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="idioma" class="block text-sm font-medium text-gray-700">Idioma</label>
                    <select name="idioma" id="idioma" class="mt-1 block w-full p-2 border border-blue-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                        <option value="">Selecione o idioma</option>
                        <?php
                        $idiomas = ['Italiano', 'Espanhol', 'Inglês', 'Francês', 'Alemão'];
                        foreach ($idiomas as $idioma):
                            $selected = (($processo['idioma'] ?? '') === $idioma) ? 'selected' : '';
                            echo "<option value='{$idioma}' {$selected}>{$idioma}</option>";
                        endforeach;
                        ?>
                    </select>
                </div>
                <div>
                    <label for="modalidade_assinatura" class="block text-sm font-medium text-gray-700">Modalidade da Assinatura</label>
                    <select name="modalidade_assinatura" id="modalidade_assinatura" class="mt-1 block w-full p-2 border border-blue-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                        <option value="">Selecione a modalidade</option>
                        <option value="Assinatura Digital" <?php echo (($processo['modalidade_assinatura'] ?? '') == 'Assinatura Digital') ? 'selected' : ''; ?>>Assinatura Digital</option>
                        <option value="Assinatura Física" <?php echo (($processo['modalidade_assinatura'] ?? '') == 'Assinatura Física') ? 'selected' : ''; ?>>Assinatura Física</option>
                    </select>
                </div>
            </div>
            <div class="space-y-4">
                <div class="flex items-center justify-between">
                    <h3 class="text-md font-semibold text-gray-800">Anexar documentos de tradução</h3>
                    <span class="text-xs text-blue-700 font-medium" data-upload-counter="translation">0 arquivos novos</span>
                </div>
                <div class="space-y-2">
                    <label class="sr-only" for="translationFiles">Escolher arquivos</label>
                    <input type="file" name="translationFiles[]" id="translationFiles" multiple data-preview-target="translation" class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-blue-100 file:text-blue-700 hover:file:bg-blue-200">
                    <p class="text-xs text-gray-500">Inclua tradução, referências e outros documentos necessários.</p>
                    <ul class="text-xs text-gray-700 bg-white border border-blue-100 rounded-md divide-y" data-upload-preview="translation" data-empty-message="Nenhum arquivo selecionado.">
                        <li class="py-2 px-3 text-gray-500" data-upload-placeholder="translation">Nenhum arquivo selecionado.</li>
                    </ul>
                </div>
                <?php if (!empty($translationAttachments)): ?>
                    <div class="rounded-md border border-blue-200 bg-white p-4">
                        <h4 class="text-sm font-semibold text-blue-700 mb-2">Arquivos já anexados</h4>
                        <ul class="space-y-2">
                            <?php foreach ($translationAttachments as $anexo): ?>
                                <li class="flex items-center justify-between text-sm text-gray-700">
                                    <a href="visualizar_anexo.php?id=<?= $anexo['id'] ?>" target="_blank" class="text-blue-600 hover:underline">
                                        <?= htmlspecialchars($anexo['nome_arquivo_original']); ?>
                                    </a>
                                    <?php if (!empty($processo['id'])): ?>
                                        <a href="processos.php?action=excluir_anexo&id=<?= $processo['id'] ?>&anexo_id=<?= $anexo['id'] ?>" class="text-red-500 hover:text-red-700 text-xs font-semibold" onclick="return confirm('Tem certeza que deseja excluir este anexo?');">Remover</a>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php else: ?>
                    <div class="rounded-md border border-blue-100 bg-white p-4 text-sm text-gray-500">Documentos para Tradução: Nenhum documento listado.</div>
                <?php endif; ?>
            </div>
            <div class="space-y-4">
                <div class="flex justify-between items-center">
                    <h3 class="text-md font-medium text-gray-800">Documentos para Tradução</h3>
                    <button type="button" class="add-doc-btn bg-blue-600 hover:bg-blue-700 text-white text-sm font-bold py-1.5 px-4 rounded-md transition duration-200 ease-in-out" data-type="traducao">Adicionar + Documento</button>
                </div>
                <div class="doc-container space-y-3" data-container-type="traducao"></div>
            </div>
        </fieldset>
    </div>

    <div id="section-crc" class="service-section hidden">
        <fieldset class="border border-green-200 rounded-md p-6 bg-green-50 space-y-6">
            <legend class="text-lg font-semibold text-green-800 px-2 ml-4">Documentos CRC</legend>
            <div class="space-y-4">
                <div class="flex items-center justify-between">
                    <h3 class="text-md font-semibold text-gray-800">Anexar documentos CRC</h3>
                    <span class="text-xs text-green-700 font-medium" data-upload-counter="crc">0 arquivos novos</span>
                </div>
                <div class="space-y-2">
                    <label class="sr-only" for="crcFiles">Escolher arquivos</label>
                    <input type="file" name="crcFiles[]" id="crcFiles" multiple data-preview-target="crc" class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-green-100 file:text-green-700 hover:file:bg-green-200" <?php echo $reuseTranslationForCrc ? 'disabled' : ''; ?>>
                    <div class="flex items-center space-x-2">
                        <input type="checkbox" id="reuseTranslationForCrc" name="reuseTraducaoForCrc" value="1" class="h-4 w-4 text-green-600 border-gray-300 rounded focus:ring-green-500" <?php echo $reuseTranslationForCrc ? 'checked' : ''; ?>>
                        <label for="reuseTranslationForCrc" class="text-xs text-gray-700">Reutilizar automaticamente os arquivos enviados para Tradução</label>
                    </div>
                    <p class="text-xs text-gray-500">Inclua arquivos legais para a etapa do CRC, como por exemplo, certidões.</p>
                    <ul class="text-xs text-gray-700 bg-white border border-green-100 rounded-md divide-y" data-upload-preview="crc" data-empty-message="Nenhum arquivo selecionado.">
                        <li class="py-2 px-3 text-gray-500" data-upload-placeholder="crc"><?php echo $reuseTranslationForCrc ? 'Os arquivos de tradução serão reutilizados.' : 'Nenhum arquivo selecionado.'; ?></li>
                    </ul>
                </div>
            </div>
            <div class="space-y-4">
                <div class="flex justify-between items-center">
                    <h3 class="text-md font-medium text-gray-800">Documentos para CRC</h3>
                    <button type="button" class="add-doc-btn bg-green-600 hover:bg-green-700 text-white text-sm font-bold py-1.5 px-4 rounded-md transition duration-200 ease-in-out" data-type="crc">Adicionar + Documento</button>
                </div>
                <div class="doc-container space-y-3" data-container-type="crc"></div>
            </div>
            <?php if (!empty($crcAttachments)): ?>
                <div class="rounded-md border border-green-200 bg-white p-4">
                    <h4 class="text-sm font-semibold text-green-700 mb-2">Arquivos já anexados</h4>
                    <ul class="space-y-2">
                        <?php foreach ($crcAttachments as $anexo): ?>
                            <li class="flex items-center justify-between text-sm text-gray-700">
                                <a href="visualizar_anexo.php?id=<?= $anexo['id'] ?>" target="_blank" class="text-green-600 hover:underline">
                                    <?= htmlspecialchars($anexo['nome_arquivo_original']); ?>
                                </a>
                                <?php if (!empty($processo['id'])): ?>
                                    <a href="processos.php?action=excluir_anexo&id=<?= $processo['id'] ?>&anexo_id=<?= $anexo['id'] ?>" class="text-red-500 hover:text-red-700 text-xs font-semibold" onclick="return confirm('Tem certeza que deseja excluir este anexo?');">Remover</a>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
        </fieldset>
    </div>

    <div class="mt-6">
        <fieldset class="border border-gray-200 rounded-md p-6 bg-gray-50 space-y-4">
            <div class="flex items-center justify-between">
                <legend class="text-lg font-semibold text-gray-700 px-2">Comprovantes de Pagamento</legend>
                <span class="text-xs text-gray-600 font-medium" data-upload-counter="payment">0 arquivos novos</span>
            </div>
            <div class="space-y-2">
                <label class="sr-only" for="paymentProofFiles">Escolher arquivos</label>
                <input type="file" name="paymentProofFiles[]" id="paymentProofFiles" multiple data-preview-target="payment" class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-gray-200 file:text-gray-700 hover:file:bg-gray-300">
                <p class="text-xs text-gray-500">Faça o upload de comprovantes de pagamento para registrar entradas financeiras.</p>
                <ul class="text-xs text-gray-700 bg-white border border-gray-200 rounded-md divide-y" data-upload-preview="payment" data-empty-message="Nenhum arquivo selecionado.">
                    <li class="py-2 px-3 text-gray-500" data-upload-placeholder="payment">Nenhum arquivo selecionado.</li>
                </ul>
            </div>
            <?php if (!empty($paymentProofAttachments)): ?>
                <div class="rounded-md border border-gray-200 bg-white p-4">
                    <h4 class="text-sm font-semibold text-gray-700 mb-2">Comprovantes já anexados</h4>
                    <ul class="space-y-2">
                        <?php foreach ($paymentProofAttachments as $anexo): ?>
                            <li class="flex items-center justify-between text-sm text-gray-700">
                                <a href="visualizar_anexo.php?id=<?= $anexo['id'] ?>" target="_blank" class="text-gray-700 hover:underline">
                                    <?= htmlspecialchars($anexo['nome_arquivo_original']); ?>
                                </a>
                                <?php if (!empty($processo['id'])): ?>
                                    <a href="processos.php?action=excluir_anexo&id=<?= $processo['id'] ?>&anexo_id=<?= $anexo['id'] ?>" class="text-red-500 hover:text-red-700 text-xs font-semibold" onclick="return confirm('Tem certeza que deseja excluir este anexo?');">Remover</a>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
        </fieldset>
    </div>

    <div id="section-apostilamento" class="service-section hidden">
        <fieldset class="border border-yellow-300 rounded-md p-6 bg-yellow-50">
            <legend class="text-lg font-semibold text-yellow-800 px-2 ml-4">Etapa Apostilamento</legend>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mt-4">
                <div>
                    <label for="apostilamento_quantidade" class="block text-sm font-medium text-gray-700">Quantidade</label>
                    <input type="number" name="apostilamento_quantidade" id="apostilamento_quantidade" class="mt-1 block w-full p-2 calculation-trigger border border-yellow-300 rounded-md shadow-sm focus:ring-yellow-500 focus:border-yellow-500" value="<?php echo htmlspecialchars($processo['apostilamento_quantidade'] ?? '0'); ?>">
                </div>
                <div>
                    <label for="apostilamento_valor_unitario" class="block text-sm font-medium text-gray-700">Valor Unitário (R$)</label>
                    <input type="text" name="apostilamento_valor_unitario" id="apostilamento_valor_unitario" class="mt-1 block w-full p-2 calculation-trigger border border-yellow-300 rounded-md shadow-sm focus:ring-yellow-500 focus:border-yellow-500" placeholder="0,00" value="<?php echo htmlspecialchars($processo['apostilamento_valor_unitario'] ?? '0,00'); ?>">
                </div>
                <div>
                    <label for="apostilamento_valor_total" class="block text-sm font-medium text-gray-700">Valor Total (R$)</label>
                    <input type="text" name="apostilamento_valor_total" id="apostilamento_valor_total" class="mt-1 block w-full p-2 border border-yellow-300 rounded-md shadow-sm bg-gray-100" value="<?php echo htmlspecialchars($processo['apostilamento_valor_total'] ?? '0,00'); ?>" readonly>
                </div>
            </div>
        </fieldset>
    </div>

    <div id="section-postagem" class="service-section hidden">
        <fieldset class="border border-purple-300 rounded-md p-6 bg-purple-50">
            <legend class="text-lg font-semibold text-purple-800 px-2 ml-4">Etapa Postagem / Envio</legend>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mt-4">
                <div>
                    <label for="postagem_quantidade" class="block text-sm font-medium text-gray-700">Quantidade</label>
                    <input type="number" name="postagem_quantidade" id="postagem_quantidade" class="mt-1 block w-full p-2 calculation-trigger border border-purple-300 rounded-md shadow-sm focus:ring-purple-500 focus:border-purple-500" value="<?php echo htmlspecialchars($processo['postagem_quantidade'] ?? '0'); ?>">
                </div>
                <div>
                    <label for="postagem_valor_unitario" class="block text-sm font-medium text-gray-700">Valor Unitário (R$)</label>
                    <input type="text" name="postagem_valor_unitario" id="postagem_valor_unitario" class="mt-1 block w-full p-2 calculation-trigger border border-purple-300 rounded-md shadow-sm focus:ring-purple-500 focus:border-purple-500" placeholder="0,00" value="<?php echo htmlspecialchars($processo['postagem_valor_unitario'] ?? '0,00'); ?>">
                </div>
                <div>
                    <label for="postagem_valor_total" class="block text-sm font-medium text-gray-700">Valor Total (R$)</label>
                    <input type="text" name="postagem_valor_total" id="postagem_valor_total" class="mt-1 block w-full p-2 border border-purple-300 rounded-md shadow-sm bg-gray-100" value="<?php echo htmlspecialchars($processo['postagem_valor_total'] ?? '0,00'); ?>" readonly>
                </div>
            </div>
        </fieldset>
    </div>

<fieldset class="border border-gray-200 rounded-md p-6 mt-6">
    <legend class="text-lg font-semibold text-gray-700 px-2 bg-white ml-4">Resumo do Orçamento</legend>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mt-4 items-center">
        <div>
            <label class="block text-sm font-medium text-gray-500">Total de Documentos</label>
            <p id="total-documentos" class="mt-1 text-xl font-bold text-gray-800">0</p>
        </div>
        <div class="md:col-span-2">
            <label for="resumo_valor_total" class="block text-sm font-medium text-gray-500">Valor Total do Orçamento (R$)</label>
            <input type="text" id="resumo_valor_total" class="mt-1 block w-full p-3 text-xl font-semibold text-green-600 border border-green-200 rounded-md bg-green-50" value="R$ 0,00" readonly>
            <input type="hidden" name="valor_total_hidden" id="valor_total_hidden">
        </div>
    </div>
</fieldset>
    <div class="flex items-center justify-end mt-8 pt-6 border-t border-gray-200">
        <a href="<?php echo htmlspecialchars($return_url); ?>" class="bg-gray-200 hover:bg-gray-300 text-gray-800 font-bold py-2 px-4 rounded-lg mr-4 transition duration-200 ease-in-out">Cancelar</a>
        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg shadow-md transition duration-200 ease-in-out">
            <?php echo $isEditMode ? 'Atualizar Processo' : 'Salvar Processo'; ?>
        </button>
    </div>
</form>

<template id="doc-traducao-template">
    <div class="doc-item grid grid-cols-1 md:grid-cols-12 gap-3 p-4 border border-gray-200 rounded-md bg-white shadow-sm items-center">
        <div class="flex items-center justify-center md:col-span-1 doc-number text-gray-500 font-bold text-lg"></div>
        
        <div class="md:col-span-5">
            <label class="block text-xs font-medium text-gray-500 sr-only">Tipo de Documento *</label>
            <select name="docs[__INDEX__][tipo_documento]" class="mt-1 block w-full p-2 text-sm border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 servico-select" data-servico-tipo="Tradução">
                <option value="">Selecione o tipo...</option>
                <?php foreach($tipos_traducao as $tipo): ?>
                    <option value="<?php echo htmlspecialchars($tipo['nome_categoria']); ?>"
                            data-valor-padrao="<?php echo $tipo['valor_padrao']; ?>"
                            data-bloqueado="<?php echo $tipo['bloquear_valor_minimo']; ?>">
                        <?php echo htmlspecialchars($tipo['nome_categoria']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="md:col-span-4">
            <label class="block text-xs font-medium text-gray-500 sr-only">Titular do Documento *</label>
            <input type="text" name="docs[__INDEX__][nome_documento]" class="mt-1 block w-full p-2 text-sm border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500" placeholder="Titular do documento">
        </div>
        
        <div class="md:col-span-1">
            <label class="block text-xs font-medium text-gray-500 sr-only">Valor *</label>
            <input type="text" name="docs[__INDEX__][valor_unitario]" class="mt-1 block w-full p-2 text-sm doc-price valor-servico calculation-trigger border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500" placeholder="Valor">
        </div>

        <div class="md:col-span-1 flex items-center justify-center">
            <button type="button" class="remove-doc-btn bg-red-500 text-white rounded-full h-7 w-7 flex items-center justify-center font-bold text-sm hover:bg-red-600 transition duration-200 ease-in-out" aria-label="Remover documento">X</button>
        </div>
        
        <input type="hidden" name="docs[__INDEX__][quantidade]" value="1">
        <input type="hidden" name="docs[__INDEX__][categoria]" value="Tradução">
    </div>
</template>


<template id="doc-crc-template">
    <div class="doc-item grid grid-cols-1 md:grid-cols-12 gap-3 p-4 border border-gray-200 rounded-md bg-white shadow-sm items-center">
        <div class="flex items-center justify-center md:col-span-1 doc-number text-gray-500 font-bold text-lg"></div>
        <div class="md:col-span-6">
            <label class="block text-xs font-medium text-gray-500 sr-only">Tipo de Documento *</label>
            <select name="docs[__INDEX__][tipo_documento]" class="mt-1 block w-full p-2 text-sm border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 servico-select" data-servico-tipo="CRC">
                <option value="">Selecione o tipo...</option>
                <?php foreach($tipos_crc as $tipo): ?>
                    <option value="<?php echo htmlspecialchars($tipo['nome_categoria']); ?>"
                            data-valor-padrao="<?php echo $tipo['valor_padrao']; ?>"
                            data-bloqueado="<?php echo $tipo['bloquear_valor_minimo']; ?>">
                        <?php echo htmlspecialchars($tipo['nome_categoria']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="md:col-span-3">
            <label class="block text-xs font-medium text-gray-500 sr-only">Titular do Documento *</label>
            <input type="text" name="docs[__INDEX__][nome_documento]" class="mt-1 block w-full p-2 text-sm border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500" placeholder="Titular do documento">
        </div>
        <div class="md:col-span-1">
            <label class="block text-xs font-medium text-gray-500 sr-only">Valor *</label>
            <input type="number" name="docs[__INDEX__][quantidade]" value="1" min="1" class="hidden">
            <input type="text" name="docs[__INDEX__][valor_unitario]" class="mt-1 block w-full p-2 text-sm doc-price valor-servico calculation-trigger border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500" placeholder="Valor">
        </div>
        <div class="md:col-span-1 flex items-center justify-center">
            <button type="button" class="remove-doc-btn bg-red-500 text-white rounded-full h-7 w-7 flex items-center justify-center font-bold text-sm hover:bg-red-600 transition duration-200 ease-in-out" aria-label="Remover documento">X</button>
        </div>
        <input type="hidden" name="docs[__INDEX__][categoria]" value="CRC">
    </div>
</template>
<script>
document.addEventListener('DOMContentLoaded', function() {
    $('#cliente_id').select2({
        placeholder: "Selecione ou digite para buscar...",
        allowClear: true
    });

    const clienteSelect = document.getElementById('cliente_id');
    const userProfile = "<?php echo $_SESSION['user_perfil'] ?? ''; ?>";
    const isGestor = ['admin', 'gerencia', 'supervisor'].includes(userProfile);
    const clienteState = { tipo: 'À vista', servicos: [] };
    const budgetMinAlertMessage = 'Atenção: O valor informado está abaixo do mínimo cadastrado. A supervisão irá validar e o orçamento ficará pendente até a aprovação.';
    const financeServices = <?php echo json_encode($financeiroServicos, JSON_UNESCAPED_UNICODE); ?>;
    const statusHiddenInput = document.querySelector('input[name="status_processo"]');
    if (statusHiddenInput) {
        statusHiddenInput.dataset.originalStatus = statusHiddenInput.value || 'Orçamento';
    }

    // Funções de formatação
    function formatCurrency(value) {
        const numeric = String(value).replace(/\D/g, '');
        if (numeric === '') return 'R$\u00a00,00';
        const floatVal = parseInt(numeric, 10) / 100;
        return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(floatVal);
    }

    function parseCurrency(formattedValue) {
        if (!formattedValue || typeof formattedValue !== 'string') return 0;
        const clean = formattedValue.replace(/[^0-9,]/g, '');
        return parseFloat(clean.replace(/\./g, '').replace(',', '.')) || 0;
    }

    function triggerMinValueAlert(input, message) {
        if (!input) {
            return;
        }

        if (isGestor) {
            delete input.dataset.alertBelowMinShown;
            return;
        }

        const minValor = parseFloat(input.dataset.minValor || '0');
        const valorAtual = parseCurrency(input.value);

        if (Number.isNaN(minValor) || Number.isNaN(valorAtual)) {
            delete input.dataset.alertBelowMinShown;
            return;
        }

        if (minValor > 0 && valorAtual < minValor) {
            if (input.dataset.alertBelowMinShown !== 'true') {
                alert(message);
                input.dataset.alertBelowMinShown = 'true';
            }
        } else {
            delete input.dataset.alertBelowMinShown;
        }
    }

    function formatInteger(value) {
        if (!value) return '0';
        return String(value).replace(/\D/g, '');
    }

    function setPreviewMessage(target, message = null) {
        const preview = document.querySelector(`[data-upload-preview="${target}"]`);
        if (!preview) {
            return;
        }

        preview.innerHTML = '';
        const item = document.createElement('li');
        item.className = 'py-2 px-3 text-gray-500';
        item.dataset.uploadPlaceholder = target;
        item.textContent = message || preview.dataset.emptyMessage || 'Nenhum arquivo selecionado.';
        preview.appendChild(item);
    }

    function refreshUploadSummary(input) {
        if (!input) {
            return;
        }

        const target = input.dataset.previewTarget;
        if (!target) {
            return;
        }

        const preview = document.querySelector(`[data-upload-preview="${target}"]`);
        const counter = document.querySelector(`[data-upload-counter="${target}"]`);
        if (!preview) {
            return;
        }

        const files = Array.from(input.files || []);
        if (files.length === 0) {
            setPreviewMessage(target);
            if (counter) {
                counter.textContent = '0 arquivos novos';
            }
            return;
        }

        preview.innerHTML = '';
        files.forEach(file => {
            const item = document.createElement('li');
            item.className = 'py-2 px-3 flex items-center justify-between gap-3';

            const nameSpan = document.createElement('span');
            nameSpan.className = 'truncate font-medium text-gray-700';
            nameSpan.textContent = file.name;

            const sizeSpan = document.createElement('span');
            sizeSpan.className = 'text-gray-500 text-[11px] uppercase';
            sizeSpan.textContent = `${(file.size / 1024).toFixed(1)} KB`;

            item.appendChild(nameSpan);
            item.appendChild(sizeSpan);
            preview.appendChild(item);
        });

        if (counter) {
            counter.textContent = files.length === 1 ? '1 arquivo novo' : `${files.length} arquivos novos`;
        }
    }

    const uploadInputs = document.querySelectorAll('input[type="file"][data-preview-target]');
    uploadInputs.forEach(input => {
        input.addEventListener('change', () => refreshUploadSummary(input));
    });

    const reuseCrcCheckbox = document.getElementById('reuseTranslationForCrc');
    const crcInput = document.getElementById('crcFiles');
    if (reuseCrcCheckbox && crcInput) {
        const applyReuseState = () => {
            if (reuseCrcCheckbox.checked) {
                crcInput.value = '';
                crcInput.setAttribute('disabled', 'disabled');
                setPreviewMessage('crc', 'Os arquivos de tradução serão reutilizados.');
                const counter = document.querySelector('[data-upload-counter="crc"]');
                if (counter) {
                    counter.textContent = '0 arquivos novos';
                }
            } else {
                crcInput.removeAttribute('disabled');
                setPreviewMessage('crc');
            }
        };

        reuseCrcCheckbox.addEventListener('change', applyReuseState);
        applyReuseState();
    }

    function getAvailableServices(servicoTipo) {
        if (!servicoTipo) {
            return [];
        }

        if (clienteState.tipo === 'Mensalista') {
            const personalizados = clienteState.servicos
                .filter(servico => servico.servico_tipo === servicoTipo)
                .map(servico => ({
                    nome_categoria: servico.nome_categoria,
                    valor_padrao: servico.valor_padrao,
                    bloquear_valor_minimo: 1
                }));

            if (personalizados.length > 0) {
                return personalizados;
            }
        }

        return financeServices[servicoTipo] || [];
    }

    function populateServiceSelect(selectElement, preserveValue = false) {
        if (!selectElement) {
            return;
        }

        const servicoTipo = selectElement.dataset.servicoTipo || '';
        const currentValue = preserveValue ? selectElement.value : '';
        const availableServices = getAvailableServices(servicoTipo);

        selectElement.innerHTML = '';

        const placeholder = document.createElement('option');
        placeholder.value = '';
        placeholder.textContent = 'Selecione o tipo...';
        selectElement.appendChild(placeholder);

        availableServices.forEach(item => {
            if (!item || !item.nome_categoria) {
                return;
            }

            const option = document.createElement('option');
            option.value = item.nome_categoria;
            option.textContent = item.nome_categoria;

            if (item.valor_padrao !== undefined && item.valor_padrao !== null && item.valor_padrao !== '') {
                option.dataset.valorPadrao = item.valor_padrao;
            }
            if (item.bloquear_valor_minimo !== undefined) {
                option.dataset.bloqueado = item.bloquear_valor_minimo ? '1' : '0';
            } else {
                option.dataset.bloqueado = clienteState.tipo === 'Mensalista' ? '1' : '0';
            }

            selectElement.appendChild(option);
        });

        if (currentValue) {
            const match = Array.from(selectElement.options).find(opt => opt.value === currentValue);
            if (match) {
                match.selected = true;
            } else {
                const customOption = document.createElement('option');
                customOption.value = currentValue;
                customOption.textContent = currentValue;
                customOption.selected = true;
                customOption.dataset.custom = '1';
                selectElement.appendChild(customOption);
            }
        }
    }

    function applyServiceSelection(selectElement) {
        if (!selectElement) {
            return;
        }

        const selectedOption = selectElement.options[selectElement.selectedIndex];
        const valorInput = selectElement.closest('.doc-item')?.querySelector('.valor-servico');
        if (!valorInput) {
            return;
        }

        const rawValor = selectedOption?.dataset?.valorPadrao;
        const parsedValor = rawValor !== undefined ? parseFloat(rawValor) : null;

        if (parsedValor !== null && !Number.isNaN(parsedValor)) {
            valorInput.value = formatCurrency(parsedValor * 100);
        }

        const bloqueado = selectedOption?.dataset?.bloqueado === '1';
        if (bloqueado && parsedValor !== null && !Number.isNaN(parsedValor)) {
            valorInput.dataset.minValor = parsedValor;
        } else if (clienteState.tipo === 'Mensalista' && parsedValor !== null && !Number.isNaN(parsedValor)) {
            valorInput.dataset.minValor = parsedValor;
        } else {
            valorInput.dataset.minValor = '0';
        }
    }

    function refreshAllDocumentSelects() {
        document.querySelectorAll('.servico-select').forEach(select => {
            populateServiceSelect(select, true);
            applyServiceSelection(select);
        });
    }

    async function handleClienteChange(triggerRefresh = true) {
        if (!clienteSelect) {
            return;
        }

        const selectedOption = clienteSelect.options[clienteSelect.selectedIndex];
        const tipo = selectedOption ? (selectedOption.dataset.tipoAssessoria || 'À vista') : 'À vista';
        clienteState.tipo = tipo;

        if (tipo === 'Mensalista' && selectedOption && selectedOption.value) {
            clienteState.servicos = [];
            try {
                const response = await fetch(`api_cliente.php?id=${selectedOption.value}`);
                const data = await response.json();
                clienteState.servicos = (data.success && Array.isArray(data.servicos)) ? data.servicos : [];
            } catch (error) {
                clienteState.servicos = [];
            }
        } else {
            clienteState.servicos = [];
        }

        if (triggerRefresh) {
            refreshAllDocumentSelects();
            updateAllCalculations();
            evaluateBudgetMinValues();
        }
    }

    function evaluateBudgetMinValues() {
        const statusInput = statusHiddenInput;
        if (!statusInput) {
            return;
        }

        if (isGestor) {
            statusInput.value = statusInput.dataset.originalStatus || statusInput.value;
            return;
        }

        const originalStatus = statusInput.dataset.originalStatus || statusInput.value || 'Orçamento';
        let pendente = false;

        document.querySelectorAll('.valor-servico').forEach(input => {
            const min = parseFloat(input.dataset.minValor || '0');
            const atual = parseCurrency(input.value);
            if (min > 0 && atual < min) {
                pendente = true;
            }
        });

        statusInput.value = originalStatus;
    }

    if (clienteSelect) {
        $('#cliente_id').on('change', () => {
            handleClienteChange(true);
        });
    }
    // Aplica a máscara de moeda a todos os campos relevantes em tempo real,
    // inclusive os que forem criados dinamicamente (.doc-price).
    document.body.addEventListener('input', function(e) {
        const target = e.target;
        if (target.matches('#apostilamento_valor_unitario, #postagem_valor_unitario, .doc-price')) {
            const raw = target.value.replace(/\D/g, '');
            if (raw === '') {
                target.value = '';
                return;
            }
            const floatVal = parseInt(raw, 10) / 100;
            target.value = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(floatVal);
            if (target.classList && target.classList.contains('valor-servico')) {
                triggerMinValueAlert(target, budgetMinAlertMessage);
                evaluateBudgetMinValues();
            }
        }
    });

    // Função de cálculo (mantém a lógica original, mas sem prefixar "R$" duas vezes)
    function updateAllCalculations() {
        let totalDocumentos = 0;
        let totalGeral = 0;

        document.querySelectorAll('.doc-price').forEach(input => {
            const row = input.closest('.doc-item');
            if (row && !row.closest('.service-section.hidden')) {
                totalGeral += parseCurrency(input.value);
                totalDocumentos++;
            }
        });

        const apostilamentoSection = document.getElementById('section-apostilamento');
        if (apostilamentoSection && !apostilamentoSection.classList.contains('hidden')) {
            const quantidadeInput = document.getElementById('apostilamento_quantidade');
            const valorUnitarioInput = document.getElementById('apostilamento_valor_unitario');
            const totalInput = document.getElementById('apostilamento_valor_total');
            const qtd = parseInt(quantidadeInput.value, 10) || 0;
            const valorUnitario = parseCurrency(valorUnitarioInput.value);
            const totalApos = qtd * valorUnitario;
            if (totalInput) {
                totalInput.value = formatCurrency(totalApos * 100);
            }
            totalGeral += totalApos;
        }

        const postagemSection = document.getElementById('section-postagem');
        if (postagemSection && !postagemSection.classList.contains('hidden')) {
            const quantidadeInput = document.getElementById('postagem_quantidade');
            const valorUnitarioInput = document.getElementById('postagem_valor_unitario');
            const totalInput = document.getElementById('postagem_valor_total');
            const qtd = parseInt(quantidadeInput.value, 10) || 0;
            const valorUnitario = parseCurrency(valorUnitarioInput.value);
            const totalPost = qtd * valorUnitario;
            if (totalInput) {
                totalInput.value = formatCurrency(totalPost * 100);
            }
            totalGeral += totalPost;
        }

        const totalDocumentosEl = document.getElementById('total-documentos');
        const resumoTotalInput = document.getElementById('resumo_valor_total');
        const hiddenTotalInput = document.getElementById('valor_total_hidden');

        if (totalDocumentosEl) {
            totalDocumentosEl.textContent = totalDocumentos;
        }
        if (resumoTotalInput) {
            resumoTotalInput.value = formatCurrency(totalGeral * 100);
        }
        if (hiddenTotalInput) {
            hiddenTotalInput.value = formatCurrency(totalGeral * 100);
        }
    }

    const form = document.getElementById('processo-form');
    if (form) {
        // Formatação e cálculo no blur
        form.addEventListener('blur', function(e) {
            const target = e.target;
            if (target.matches('#apostilamento_valor_unitario, #postagem_valor_unitario, .doc-price')) {
                const parsedValue = parseCurrency(target.value);
                target.value = formatCurrency(parsedValue * 100);
            }
            if (target.matches('#apostilamento_quantidade, #postagem_quantidade')) {
                target.value = formatInteger(target.value);
            }
            if (target.classList && target.classList.contains('valor-servico')) {
                triggerMinValueAlert(target, budgetMinAlertMessage);
            }
            if (target.matches('.calculation-trigger')) {
                updateAllCalculations();
                evaluateBudgetMinValues();
            }
        }, true);
    }


    // O restante do código permanece o mesmo
    function toggleServiceSections() {
        document.querySelectorAll('.service-checkbox').forEach(checkbox => {
            const targetSection = document.getElementById(checkbox.dataset.target);
            if (targetSection) {
                targetSection.classList.toggle('hidden', !checkbox.checked);
            }
        });
        updateAllCalculations();
        evaluateBudgetMinValues();
    }

    let docIndex = 0;
    const categoryMap = {'Tradução': 'traducao', 'CRC': 'crc'};

    function addDocumentRow(type) {
        const template = document.getElementById(`doc-${type}-template`);
        const container = document.querySelector(`[data-container-type="${type}"]`);
        if (!template || !container) return;

        // --- INÍCIO DA VALIDAÇÃO ---
        const lastRow = container.querySelector('.doc-item:last-child');
        if (lastRow) {
            const tipoSelect = lastRow.querySelector('select[name*="[tipo_documento]"]');
            const nomeInput = lastRow.querySelector('input[name*="[nome_documento]"]');
            const valorInput = lastRow.querySelector('.doc-price');

            const clearErrorHighlights = () => {
                lastRow.querySelectorAll('input, select').forEach(el => el.style.borderColor = '');
            };

            if (!tipoSelect.value || !nomeInput.value || !valorInput.value || parseCurrency(valorInput.value) === 0) {
                alert('Por favor, preencha todos os campos da linha anterior (Tipo, Nome e Valor) antes de adicionar uma nova.');
                
                if (!tipoSelect.value) tipoSelect.style.borderColor = 'red';
                if (!nomeInput.value) nomeInput.style.borderColor = 'red';
                if (!valorInput.value || parseCurrency(valorInput.value) === 0) valorInput.style.borderColor = 'red';

                setTimeout(clearErrorHighlights, 3000);
                return;
            }
            clearErrorHighlights();
        }

        const cloneHTML = template.innerHTML.replace(/__INDEX__/g, docIndex);
        container.insertAdjacentHTML('beforeend', cloneHTML);
        const newRow = container.lastElementChild;
        const select = newRow ? newRow.querySelector('.servico-select') : null;
        if (select) {
            populateServiceSelect(select);
            applyServiceSelection(select);
        }
        docIndex++;
        updateNumbering();
        updateAllCalculations();
        evaluateBudgetMinValues();
    }

    function updateNumbering() {
        document.querySelectorAll('.doc-container').forEach(container => {
            container.querySelectorAll('.doc-item').forEach((row, index) => {
                row.querySelector('.doc-number').textContent = index + 1;
            });
        });
    }
    
    function togglePrazoInputs() {
        const prazoTipoRadio = document.querySelector('input[name="traducao_prazo_tipo"]:checked');
        if (!prazoTipoRadio) return;
        const prazoTipo = prazoTipoRadio.value;
        const prazoDiaContainer = document.getElementById('prazo_dia_container');
        const prazoDataContainer = document.getElementById('prazo_data_container');
        if (prazoDiaContainer && prazoDataContainer) {
            prazoDiaContainer.classList.toggle('hidden', prazoTipo !== 'dias');
            prazoDataContainer.classList.toggle('hidden', prazoTipo !== 'data');
        }
    }

    form.addEventListener('change', function(e) {
        if (e.target.matches('.servico-select')) {
            applyServiceSelection(e.target);
            updateAllCalculations();
            evaluateBudgetMinValues();
            return;
        }

        if (e.target.matches('select[name*="[tipo_documento]"]')) {
            const selectedOption = e.target.options[e.target.selectedIndex];
            const row = e.target.closest('.doc-item');
            const valorInput = row.querySelector('input[name*="[valor_unitario]"]');
            const valorPadrao = selectedOption.dataset.valorPadrao;
            const isBloqueado = selectedOption.dataset.bloqueado === '1';

            if (valorInput && valorPadrao) {
                valorInput.value = formatCurrency(parseFloat(valorPadrao) * 100);
                valorInput.dataset.minValor = isBloqueado ? valorPadrao : '0';
                valorInput.dispatchEvent(new Event('blur', { bubbles: true }));
            }
            evaluateBudgetMinValues();
        }
    });

    // Removido o event listener 'blur' duplicado e a validação de min-valor foi movida para o 'blur' unificado
    // Note que a validação de min-valor agora será executada dentro do novo event listener de 'blur'
    
    form.addEventListener('click', function(e) {
        if (e.target.classList.contains('add-doc-btn')) addDocumentRow(e.target.dataset.type);
        if (e.target.classList.contains('remove-doc-btn')) {
            e.target.closest('.doc-item').remove();
            updateNumbering();
            updateAllCalculations();
            evaluateBudgetMinValues();
        }
    });

    document.querySelectorAll('.service-checkbox').forEach(cb => cb.addEventListener('change', toggleServiceSections));
    document.querySelectorAll('input[name="traducao_prazo_tipo"]').forEach(radio => radio.addEventListener('change', togglePrazoInputs));
    
    function loadExistingData() {
        docIndex = 0;
        const existingDocs = <?php echo json_encode($documentos ?? []); ?>;
        
        existingDocs.forEach(doc => {
            const type = categoryMap[doc.categoria] || doc.categoria.toLowerCase();
            if (document.getElementById(`doc-${type}-template`)) {
                addDocumentRow(type);
                const newRow = document.querySelector(`[data-container-type="${type}"]`).lastElementChild;
                const docIndexForLoad = docIndex - 1;
                const selectElement = newRow.querySelector(`select[name="docs[${docIndexForLoad}][tipo_documento]"]`);
                if (selectElement) {
                    selectElement.value = doc.tipo_documento;
                    if (selectElement.value !== doc.tipo_documento && doc.tipo_documento) {
                        const customOption = new Option(doc.tipo_documento, doc.tipo_documento, true, true);
                        selectElement.add(customOption);
                    }
                    applyServiceSelection(selectElement);
                }
                newRow.querySelector(`input[name="docs[${docIndexForLoad}][nome_documento]"]`).value = doc.nome_documento;
                const valorInput = newRow.querySelector(`input[name="docs[${docIndexForLoad}][valor_unitario]"]`);
                if (valorInput) {
                    valorInput.value = formatCurrency(parseFloat(doc.valor_unitario) * 100);
                }
            }
        });
        
        document.querySelectorAll('.calculation-trigger').forEach(field => {
            if (field.value) {
                if (field.id.includes('quantidade')) {
                    field.value = formatInteger(field.value);
                } else {
                    field.value = formatCurrency(field.value.replace('.', ''));
                }
            }
        });
        
        toggleServiceSections();
        togglePrazoInputs();
        updateAllCalculations();
        evaluateBudgetMinValues();
    }
    
    handleClienteChange(false).then(() => {
        loadExistingData();
    });
});
</script>