<?php
// Adicione esta lógica no início do seu ficheiro servico-rapido.php
$isEditMode = false; // Defina como false, pois este é um formulário de criação
$return_url = $_SERVER['HTTP_REFERER'] ?? 'processos.php'; // Usa a página anterior ou um padrão seguro.
$formData = $formData ?? [];
$cliente_pre_selecionado_id = $_GET['cliente_id'] ?? ($formData['cliente_id'] ?? null);
$financeiroServicos = $financeiroServicos ?? [
    'Tradução' => [],
    'CRC' => [],
    'Apostilamento' => [],
    'Postagem' => [],
];

$categoriasSelecionadas = [];
if (!empty($formData['categorias_servico'])) {
    if (is_array($formData['categorias_servico'])) {
        $categoriasSelecionadas = $formData['categorias_servico'];
    } else {
        $categoriasSelecionadas = array_filter(array_map('trim', explode(',', (string) $formData['categorias_servico'])));
    }
}
$prazoTipoSelecionado = $formData['prazo_tipo'] ?? 'dias';


?>

    <div>
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-2xl font-bold text-gray-800">Cadastrar Novo Serviço</h1>
             
            <a href="<?php echo htmlspecialchars($return_url); ?>" class="bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded-lg shadow-md transition duration-200 ease-in-out">
                &larr; Voltar
            </a>
        </div>

<form action="servico-rapido.php?action=store" method="POST" enctype="multipart/form-data" class="bg-white shadow-lg rounded-lg p-8 space-y-6">
    
    <fieldset class="border border-gray-200 rounded-md p-6">
        <legend class="text-lg font-semibold text-gray-700 px-2 bg-white ml-4">Detalhes do Serviço</legend>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mt-4">
            <div>
                <label for="titulo" class="block text-sm font-medium text-gray-700">Nome do Serviço / Família</label>
                <input type="text" name="titulo" id="titulo" class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500" value="<?php echo htmlspecialchars($formData['titulo'] ?? ''); ?>" required>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Número da OS Omie</label>
                <div class="mt-1 block w-full p-2 border border-dashed border-gray-300 rounded-md bg-gray-50 text-sm text-gray-600">
                    Será gerada automaticamente pela Omie após a criação do serviço.
                </div>
                <p class="text-xs text-gray-500 mt-1">O número será exibido no dashboard quando a Omie retornar a OS.</p>
            </div>
            <div>
                <label for="cliente_id" class="block text-sm font-medium text-gray-700">Cliente Associado</label>
                <div class="flex items-center space-x-2 mt-1">
                    <select name="cliente_id" id="cliente_id" class="block w-full p-2 border border-gray-300 rounded-md shadow-sm">
                        <option value=""></option>
                        <?php foreach ($clientes as $cliente): ?>
                            <option
                                value="<?php echo $cliente['id']; ?>"
                                data-tipo-assessoria="<?php echo $cliente['tipo_assessoria']; ?>"
                                <?php
                                    $isSelected = ($cliente_pre_selecionado_id == $cliente['id']);
                                    echo $isSelected ? 'selected' : '';
                                ?>>
                                <?php echo htmlspecialchars($cliente['nome_cliente']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                                <input type="hidden" id="cliente_tipo_assessoria" name="cliente_tipo_assessoria" value="<?php echo htmlspecialchars($formData['cliente_tipo_assessoria'] ?? ''); ?>">

                    <a href="<?php echo APP_URL; ?>/clientes.php?action=create&return_to=<?php echo urlencode(APP_URL . $_SERVER['REQUEST_URI']); ?>" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-3 rounded-md text-sm whitespace-nowrap" title="Adicionar Novo Cliente">
                        +
                    </a>
                </div>
            </div>
            <div class="md:col-span-3">
                <label class="block text-sm font-semibold text-gray-700">Serviços Contratados *</label>
                <div class="mt-2 grid grid-cols-2 sm:grid-cols-4 gap-x-4 gap-y-4">
                    <?php
                    $servicos_lista = ['Tradução', 'CRC', 'Apostilamento', 'Postagem'];
                    $slug_map = ['Tradução' => 'tradução', 'CRC' => 'crc', 'Apostilamento' => 'apostilamento', 'Postagem' => 'postagem'];
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
                    foreach ($servicos_lista as $servico):
                        $slug = $slug_map[$servico];
                        $labelClasses = $labelColorMap[$servico];
                        $checkboxClasses = $checkboxColorMap[$servico];
                        $textClasses = $textColorMap[$servico];
                    ?>
                        <label for="cat_rapido_<?php echo $slug; ?>" class="flex items-center p-3 border rounded-lg cursor-pointer transition-all duration-200 <?php echo $labelClasses; ?>">
                            <input id="cat_rapido_<?php echo $slug; ?>" name="categorias_servico[]" type="checkbox" value="<?php echo $servico; ?>" class="h-5 w-5 border-gray-300 rounded service-checkbox <?php echo $checkboxClasses; ?>" <?php echo in_array($servico, $categoriasSelecionadas, true) ? 'checked' : ''; ?>>
                            <span class="ml-3 block text-sm font-semibold <?php echo $textClasses; ?>"><?php echo $servico; ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-x-6 gap-y-4 mt-6 pt-6 border-t border-gray-200">
            <div class="md:col-span-1">
                <label for="data_inicio_traducao" class="block text-sm font-medium text-gray-700">Data de Envio para Tradutor</label>
                <input type="date" name="data_inicio_traducao" id="data_inicio_traducao" value="<?php echo htmlspecialchars($formData['data_inicio_traducao'] ?? date('Y-m-d')); ?>" class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm">
            </div>
            <div class="md:col-span-1">
                <label for="prazo_tipo" class="block text-sm font-medium text-gray-700">Definir Prazo Para Serviço</label>
                <select name="prazo_tipo" id="prazo_tipo" class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm">
                    <option value="dias" <?php echo ($prazoTipoSelecionado === 'dias') ? 'selected' : ''; ?>>Por Dias</option>
                    <option value="data" <?php echo ($prazoTipoSelecionado === 'data') ? 'selected' : ''; ?>>Por Data</option>
                </select>
            </div>
            <div class="md:col-span-1">
                <div id="prazo_dias_container" class="<?php echo ($prazoTipoSelecionado === 'data') ? 'hidden' : ''; ?>">
                    <label for="traducao_prazo_dias" class="block text-sm font-medium text-gray-700">Quantidade de Dias Corridos</label>
                    <input type="number" name="traducao_prazo_dias" id="traducao_prazo_dias" placeholder="Ex: 5" value="<?php echo htmlspecialchars($formData['traducao_prazo_dias'] ?? ''); ?>" class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm" <?php echo ($prazoTipoSelecionado === 'data') ? 'disabled' : ''; ?>>
                </div>
                <div id="prazo_data_container" class="<?php echo ($prazoTipoSelecionado === 'data') ? '' : 'hidden'; ?>">
                    <label for="traducao_prazo_data" class="block text-sm font-medium text-gray-700">Data da Entrega</label>
                    <input type="date" name="traducao_prazo_data" id="traducao_prazo_data" value="<?php echo htmlspecialchars($formData['traducao_prazo_data'] ?? ''); ?>" class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm" <?php echo ($prazoTipoSelecionado === 'data') ? '' : 'disabled'; ?>>
                </div>
            </div>
        </div>
    </fieldset>

        <div id="section-container-tradução" class="bg-blue-50 p-6 rounded-lg shadow-lg border border-blue-200" style="display: none;">
            <h2 class="text-xl font-semibold mb-4 border-b border-blue-200 pb-2 text-blue-800">Detalhes da Tradução</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <div>
                    <label for="traducao_modalidade" class="block text-sm font-medium text-gray-700">Modalidade</label>
                    <select name="traducao_modalidade" id="traducao_modalidade" class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm">
                        <option value="Normal" <?php echo (($formData['traducao_modalidade'] ?? 'Normal') === 'Normal') ? 'selected' : ''; ?>>Normal</option>
                        <option value="Express" <?php echo (($formData['traducao_modalidade'] ?? '') === 'Express') ? 'selected' : ''; ?>>Express</option>
                    </select>
                </div>
                <div>
                    <label for="tradutor_id" class="block text-sm font-medium text-gray-700">Tradutor</label>
                    <select name="tradutor_id" id="tradutor_id" class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm">
                        <option value="">Selecione um tradutor</option>
                        <?php foreach ($tradutores as $tradutor): ?>
                            <option value="<?php echo $tradutor['id']; ?>" <?php echo (($formData['tradutor_id'] ?? '') == $tradutor['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($tradutor['nome_tradutor']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="assinatura_tipo" class="block text-sm font-medium text-gray-700">Assinatura</label>
                    <select name="assinatura_tipo" id="assinatura_tipo" class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm">
                        <option value="Digital" <?php echo (($formData['assinatura_tipo'] ?? 'Digital') === 'Digital') ? 'selected' : ''; ?>>Assinatura Digital</option>
                        <option value="Física" <?php echo (($formData['assinatura_tipo'] ?? '') === 'Física') ? 'selected' : ''; ?>>Assinatura Física</option>
                    </select>
                </div>
            </div>
            
            <div class="mt-6 pt-6 border-t border-blue-200">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="font-semibold text-gray-700">Documentos para Tradução</h3>
                    <button type="button" onclick="adicionarDocumento('tradução')" class="bg-blue-500 text-white font-semibold py-2 px-4 rounded-lg hover:bg-blue-600 text-sm">
                        <i class="fas fa-plus mr-2"></i>Adicionar Documento
                    </button>
                </div>
                <div id="documentos-container-tradução" class="space-y-4">
                    </div>
            </div>
        </div>
    <?php
    $sections = [
        'crc' => ['title' => 'Documentos CRC', 'category' => 'CRC', 'color' => 'green'],
        'apostilamento' => ['title' => 'Etapa Apostilamento', 'category' => 'Apostilamento', 'color' => 'yellow'],
        'postagem' => ['title' => 'Etapa Postagem / Envio', 'category' => 'Postagem', 'color' => 'purple']
    ];
    ?>

    <?php foreach ($sections as $key => $section): ?>
    <div id="section-container-<?php echo strtolower($section['category']); ?>" class="bg-<?php echo $section['color']; ?>-50 p-6 rounded-lg shadow-lg border border-<?php echo $section['color']; ?>-200" style="display: none;">
        <div class="flex justify-between items-center mb-4 border-b border-<?php echo $section['color']; ?>-200 pb-2">
            <h2 class="text-xl font-semibold text-<?php echo $section['color']; ?>-800"><?php echo $section['title']; ?></h2>
            <button type="button" class="add-doc-row bg-blue-600 text-white px-4 py-2 rounded-md text-sm hover:bg-blue-700 transition duration-150 ease-in-out" data-section="<?php echo $key; ?>">Adicionar</button>
        </div>
        <div id="documentos-container-<?php echo $key; ?>" class="space-y-4">
        </div>
    </div>
    <?php endforeach; ?>

    <fieldset class="border border-gray-200 rounded-md p-6 mt-6">
        <legend class="text-lg font-semibold text-gray-700 px-2 bg-white ml-4">Resumo e Anexos</legend>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mt-4 items-start">

            <div>
                <label class="block text-sm font-medium text-gray-500">Total Documentos</label>
                <p id="total-documentos-display" class="mt-1 text-xl font-bold text-gray-800">0</p>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-500">Valor Total do Processo</label>
                <p id="total-geral-display" class="mt-1 text-xl font-bold text-green-600">R$ 0,00</p>
                <input type="hidden" name="valor_total_hidden" id="valor_total_hidden" value="<?php echo htmlspecialchars($formData['valor_total_hidden'] ?? ''); ?>">
            </div>

            <div>
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Anexar Arquivos</h3>
                
                <div>
                    <label for="anexos" class="block text-sm font-medium text-gray-700 mb-2">Selecione um ou mais arquivos</label>
                    <input type="file" name="anexos[]" id="anexos" multiple 
                        class="block w-full text-sm text-gray-500
                                file:mr-4 file:py-2 file:px-4
                                file:rounded-full file:border-0
                                file:text-sm file:font-semibold
                                file:bg-blue-50 file:text-blue-700
                                hover:file:bg-blue-100">
                    <p class="mt-1 text-xs text-gray-500">Você pode selecionar múltiplos arquivos segurando a tecla Ctrl (ou Cmd em Mac).</p>
                </div>

                <?php if (!empty($anexos)): ?>
                    <div class="mt-6 pt-4 border-t">
                        <h4 class="text-md font-medium text-gray-700 mb-2">Arquivos Anexados:</h4>
                        <ul class="list-disc pl-5 space-y-2">
                            <?php foreach ($anexos as $anexo): ?>
                                <li class="text-sm text-gray-600 flex justify-between items-center">
                                    <a href="visualizar_anexo.php?id=<?= $anexo['id'] ?>" target="_blank" class="text-blue-600 hover:underline">
                                        <?= htmlspecialchars($anexo['nome_arquivo_original']) ?>
                                    </a>
                                    <a href="processos.php?action=excluir_anexo&id=<?= $processo['id'] ?>&anexo_id=<?= $anexo['id'] ?>" 
                                    class="text-red-500 hover:text-red-700 text-xs font-semibold"
                                    onclick="return confirm('Tem certeza que deseja excluir este anexo?');">
                                    Excluir
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </fieldset>

    <div class="flex items-center justify-end mt-8 pt-6 border-t border-gray-200">
        <a href="dashboard.php" class="bg-gray-200 text-gray-700 px-6 py-2 rounded-md hover:bg-gray-300 transition duration-150 ease-in-out mr-4">Cancelar</a>
        <button type="submit" class="bg-green-600 text-white font-bold px-6 py-2 rounded-md hover:bg-green-700 transition duration-150 ease-in-out">Salvar Serviço</button>
    </div>
</form>

    </div>

<template id="template-crc">
    <div class="doc-row grid grid-cols-1 md:grid-cols-4 gap-4 p-4 border border-gray-200 rounded-md bg-gray-50 relative items-end">
        <input type="hidden" name="documentos[crc][{index}][categoria]" value="CRC">
        <div>
            <label class="block text-sm font-medium text-gray-700">Tipo de Documento</label>
            <select
                name="documentos[crc][{index}][tipo_documento]"
                class="servico-select mt-1 block w-full border-gray-300 rounded-md shadow-sm p-2"
                data-servico-tipo="CRC"
            >
                <option value="">Selecione o serviço...</option>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700">Nome do Titular</label>
            <input type="text" name="documentos[crc][{index}][nome_documento]" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm p-2" placeholder="Nome Completo">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700">Valor</label>
            <input type="text" name="documentos[crc][{index}][valor_unitario]" class="doc-valor valor-servico mt-1 block w-full border-gray-300 rounded-md shadow-sm p-2" placeholder="0,00">
        </div>
        <div class="flex justify-end md:justify-start">
            <button type="button" class="remove-doc-row bg-red-500 text-white px-4 py-2 rounded-md hover:bg-red-600 text-sm transition duration-150 ease-in-out">Remover</button>
        </div>
    </div>
</template>

<template id="template-apostilamento">
    <div class="doc-row grid grid-cols-1 md:grid-cols-4 gap-4 p-4 border border-gray-200 rounded-md bg-gray-50 relative items-end">
        <input type="hidden" name="documentos[apostilamento][{index}][categoria]" value="Apostilamento">
        <div class="md:col-span-1">
            <label class="block text-sm font-medium text-gray-700">Quantidade</label>
            <input type="number" name="documentos[apostilamento][{index}][quantidade]" value="1" class="doc-qtd mt-1 block w-full border-gray-300 rounded-md shadow-sm p-2">
        </div>
        <div class="md:col-span-1">
            <label class="block text-sm font-medium text-gray-700">Valor Unitário (R$)</label>
            <input type="text" name="documentos[apostilamento][{index}][valor_unitario]" class="doc-valor mt-1 block w-full border-gray-300 rounded-md shadow-sm p-2" placeholder="0,00">
        </div>
        <div class="md:col-span-1">
            <label class="block text-sm font-medium text-gray-700">Valor Total (R$)</label>
            <input type="text" name="documentos[apostilamento][{index}][valor_total]" readonly class="doc-total bg-gray-100 mt-1 block w-full border-gray-300 rounded-md shadow-sm p-2">
        </div>
        <div class="flex justify-end md:justify-start">
            <button type="button" class="remove-doc-row bg-red-500 text-white px-4 py-2 rounded-md hover:bg-red-600 text-sm transition duration-150 ease-in-out">Remover</button>
        </div>
    </div>
</template>

<template id="template-postagem">
    <div class="doc-row grid grid-cols-1 md:grid-cols-4 gap-4 p-4 border border-gray-200 rounded-md bg-gray-50 relative items-end">
        <input type="hidden" name="documentos[postagem][{index}][categoria]" value="Postagem">
        <div class="md:col-span-1">
            <label class="block text-sm font-medium text-gray-700">Quantidade</label>
            <input type="number" name="documentos[postagem][{index}][quantidade]" value="1" class="doc-qtd mt-1 block w-full border-gray-300 rounded-md shadow-sm p-2">
        </div>
        <div class="md:col-span-1">
            <label class="block text-sm font-medium text-gray-700">Valor Unitário (R$)</label>
            <input type="text" name="documentos[postagem][{index}][valor_unitario]" class="doc-valor mt-1 block w-full border-gray-300 rounded-md shadow-sm p-2" placeholder="0,00">
        </div>
        <div class="md:col-span-1">
            <label class="block text-sm font-medium text-gray-700">Valor Total (R$)</label>
            <input type="text" name="documentos[postagem][{index}][valor_total]" readonly class="doc-total bg-gray-100 mt-1 block w-full border-gray-300 rounded-md shadow-sm p-2">
        </div>
        <div class="flex justify-end md:justify-start">
            <button type="button" class="remove-doc-row bg-red-500 text-white px-4 py-2 rounded-md hover:bg-red-600 text-sm transition duration-150 ease-in-out">Remover</button>
        </div>
    </div>
</template>



<template id="template-tradução-avista">
    <div class="doc-row grid grid-cols-12 gap-4 items-center">
        <input type="hidden" name="documentos[tradução][{index}][categoria]" value="Tradução">
        <div class="col-span-5">
            <label class="block text-xs font-medium text-gray-500 sr-only">Tipo de Documento *</label>
            <select
                name="documentos[tradução][{index}][tipo_documento]"
                class="servico-select mt-1 block w-full border-gray-300 rounded-md shadow-sm p-2"
                data-servico-tipo="Tradução"
            >
                <option value="">Selecione o serviço...</option>
            </select>
        </div>
        <div class="col-span-4">
            <label class="block text-xs font-medium text-gray-500 sr-only">Titular do Documento *</label>
            <input
                type="text"
                name="documentos[tradução][{index}][nome_documento]"
                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm p-2 text-sm"
                placeholder="Titular do documento"
            >
        </div>
        <div class="col-span-2">
            <label class="block text-xs font-medium text-gray-500 sr-only">Valor *</label>
            <input type="text" name="documentos[tradução][{index}][valor_unitario]" class="doc-valor valor-servico mt-1 block w-full border-gray-300 rounded-md shadow-sm p-2" placeholder="R$ 0,00">
        </div>
        <div class="col-span-1 text-right">
            <button type="button" class="remove-doc-row text-red-500 hover:text-red-700 font-bold text-xl">&times;</button>
        </div>
    </div>
</template>

<template id="template-tradução-mensalista">
    <div class="doc-row grid grid-cols-12 gap-4 items-center">
        <input type="hidden" name="documentos[tradução][{index}][categoria]" value="Tradução">
        <div class="col-span-5">
            <label class="block text-xs font-medium text-gray-500 sr-only">Serviço Contratado *</label>
            <select name="documentos[tradução][{index}][tipo_documento]" class="servico-select mensalista-servico-select mt-1 block w-full border-gray-300 rounded-md shadow-sm p-2" data-servico-tipo="Tradução">
                <option value="">Selecione o serviço contratado...</option>
            </select>
        </div>
        <div class="col-span-4">
            <label class="block text-xs font-medium text-gray-500 sr-only">Titular do Documento *</label>
            <input
                type="text"
                name="documentos[tradução][{index}][nome_documento]"
                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm p-2 text-sm"
                placeholder="Titular do documento"
            >
        </div>
        <div class="col-span-2">
            <label class="block text-xs font-medium text-gray-500 sr-only">Valor *</label>
            <input type="text"
                name="documentos[tradução][{index}][valor_unitario]"
                class="doc-valor valor-servico mensalista-valor mt-1 block w-full border-gray-300 rounded-md shadow-sm p-2"
                placeholder="R$ 0,00"
            />
        </div>
        <div class="col-span-1 flex items-center justify-end">
            <button type="button" class="remove-doc-row text-red-500 hover:text-red-700 font-bold text-xl">&times;</button>
        </div>
    </div>
</template>



<script>
// Script responsável por lidar com interações do formulário de serviço rápido.
document.addEventListener('DOMContentLoaded', function () {
    // === ELEMENTOS DO FORMULÁRIO E VARIÁVEIS GLOBAIS ===
    const clienteSelect = document.getElementById('cliente_id');
    const tipoAssessoriaInput = document.getElementById('cliente_tipo_assessoria');
    const servicosCheckboxes = document.querySelectorAll('.service-checkbox');
    const userPerfil = "<?php echo $_SESSION['user_perfil'] ?? 'colaborador'; ?>";
    const sectionCounters = { tradução: 0, crc: 0, apostilamento: 0, postagem: 0 };
    const financeServices = <?php echo json_encode($financeiroServicos, JSON_UNESCAPED_UNICODE); ?>;
    const isGestor = ['admin', 'gerencia', 'supervisor'].includes(userPerfil);
    const minValueAlertMessage = 'Atenção: O valor informado está abaixo do mínimo cadastrado. A supervisão irá validar e o serviço ficará pendente até a aprovação.';
    let clienteCache = { tipo: 'À vista', servicos: [] };

    // --- Campo oculto para controle de status proposto ---
    // Criado dinamicamente para que o backend saiba se o serviço deve iniciar 'Pendente' ou 'Em andamento'.
    const formElement = document.querySelector('form');
    if (formElement && !document.getElementById('status_proposto')) {
        const hiddenStatusInput = document.createElement('input');
        hiddenStatusInput.type = 'hidden';
        hiddenStatusInput.name = 'status_proposto';
        hiddenStatusInput.id = 'status_proposto';
        hiddenStatusInput.value = 'Em andamento'; // valor padrão
        formElement.appendChild(hiddenStatusInput);
    }

    // === INICIALIZAÇÃO DO SELECT2 ===
        $(clienteSelect).select2({
            placeholder: "Selecione um cliente...",
            allowClear: true
        });

    // Lógica para clientes mensalistas: carrega serviços via API.
    $(clienteSelect).on('change', function() {
        const selectedOption = $(this).find('option:selected');
        const tipo = selectedOption.data('tipo-assessoria') || 'À vista';
        if (tipoAssessoriaInput) {
            tipoAssessoriaInput.value = tipo;
        }
        clienteCache.tipo = tipo;

        const clienteId = selectedOption.val();

        if (tipo === 'Mensalista' && clienteId) {
            clienteCache.servicos = [];
            refreshAllServiceSelects();
            updateAllCalculations();
            evaluateValorMinimo();

            fetch(`api_cliente.php?id=${clienteId}`)
                .then(response => response.json())
                .then(data => {
                    clienteCache.servicos = (data.success && Array.isArray(data.servicos)) ? data.servicos : [];
                    refreshAllServiceSelects();
                    updateAllCalculations();
                    evaluateValorMinimo();
                })
                .catch(() => {
                    clienteCache.servicos = [];
                    refreshAllServiceSelects();
                    updateAllCalculations();
                    evaluateValorMinimo();
                });
            return;
        }

        clienteCache.servicos = [];
        refreshAllServiceSelects();
        updateAllCalculations();
        evaluateValorMinimo();
    });
    $(clienteSelect).trigger('change');

    function getAvailableServices(servicoTipo) {
        if (!servicoTipo) {
            return [];
        }

        if (clienteCache.tipo === 'Mensalista') {
            const personalizados = clienteCache.servicos
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

        const placeholderOption = document.createElement('option');
        placeholderOption.value = '';
        placeholderOption.textContent = 'Selecione o serviço...';
        selectElement.appendChild(placeholderOption);

        availableServices.forEach(item => {
            if (!item || !item.nome_categoria) {
                return;
            }

            const option = document.createElement('option');
            option.value = item.nome_categoria;
            option.textContent = item.nome_categoria;

            if (item.valor_padrao !== undefined && item.valor_padrao !== null && item.valor_padrao !== '') {
                option.dataset.valorPadrao = item.valor_padrao;
                option.dataset.valor = item.valor_padrao;
            }

            if (item.bloquear_valor_minimo !== undefined) {
                option.dataset.bloqueado = item.bloquear_valor_minimo ? '1' : '0';
            } else {
                option.dataset.bloqueado = clienteCache.tipo === 'Mensalista' ? '1' : '0';
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
        const valorInput = selectElement.closest('.doc-row')?.querySelector('.valor-servico');
        if (!valorInput) {
            return;
        }

        const rawValor = selectedOption?.dataset?.valorPadrao;
        const parsedValor = rawValor !== undefined ? parseFloat(rawValor) : null;

        if (parsedValor !== null && !Number.isNaN(parsedValor)) {
            valorInput.value = formatCurrency(Math.round(parsedValor * 100));
        }

        const bloqueado = selectedOption?.dataset?.bloqueado === '1';
        if (bloqueado && parsedValor !== null && !Number.isNaN(parsedValor)) {
            valorInput.dataset.minValor = parsedValor;
        } else if (clienteCache.tipo === 'Mensalista' && parsedValor !== null && !Number.isNaN(parsedValor)) {
            valorInput.dataset.minValor = parsedValor;
        } else {
            valorInput.dataset.minValor = '0';
        }
    }

    function refreshAllServiceSelects() {
        document.querySelectorAll('.servico-select').forEach(select => {
            populateServiceSelect(select, true);
            applyServiceSelection(select);
        });
    }

    // === ADICIONAR DOCUMENTOS ===
    window.adicionarDocumento = function(section, data = null, autoAdded = false) {
        const isMensalista = clienteCache.tipo === 'Mensalista' && section === 'tradução';
        let templateId;
        if (section === 'tradução') {
            templateId = isMensalista ? 'template-tradução-mensalista' : 'template-tradução-avista';
        } else {
            templateId = `template-${section}`;
        }
        const template = document.getElementById(templateId);
        if (!template) { console.error(`Template ${templateId} não encontrado!`); return; }
        const container = document.getElementById(`documentos-container-${section}`);
        const index = sectionCounters[section]++;
        container.insertAdjacentHTML('beforeend', template.innerHTML.replace(/\{index\}/g, index));
        const newRow = container.lastElementChild;
        const select = newRow.querySelector('.servico-select');
        const nomeInput = newRow.querySelector('input[name*="[nome_documento]"]');
        if (select) {
            populateServiceSelect(select);

            if (data && data.nome_categoria) {
                select.value = data.nome_categoria;
                if (!select.value) {
                    const customOption = new Option(data.nome_categoria, data.nome_categoria, true, true);
                    select.add(customOption);
                }
                const valorInput = newRow.querySelector('.valor-servico');
                if (valorInput && data.valor_padrao !== undefined) {
                    const valorPadrao = parseFloat(data.valor_padrao);
                    if (!Number.isNaN(valorPadrao)) {
                        valorInput.value = formatCurrency(Math.round(valorPadrao * 100));
                        valorInput.dataset.minValor = valorPadrao;
                    }
                }
                if (autoAdded) {
                    const removeBtn = newRow.querySelector('.remove-doc-row');
                    if (removeBtn) {
                        removeBtn.style.display = 'none';
                    }
                }
            }

            applyServiceSelection(select);
        }

        if (nomeInput && data && data.nome_documento) {
            nomeInput.value = data.nome_documento;
        }
        updateAllCalculations();
        evaluateValorMinimo();
    };

    // === EVENTOS DINÂMICOS ===
    document.body.addEventListener('change', function(e) {
        if (e.target.matches('.servico-select')) {
            applyServiceSelection(e.target);
            updateAllCalculations();
            evaluateValorMinimo();
        }
    });

    document.body.addEventListener('click', e => {
        const target = e.target.closest('.remove-doc-row');
        if (target) {
            target.closest('.doc-row').remove();
            updateAllCalculations();
            evaluateValorMinimo();
        }
    });

    document.body.addEventListener('input', function(e) {
        const target = e.target;
        if (target.matches('.valor-servico')) {
            const raw = target.value.replace(/\D/g, '');
            target.value = (raw === '') ? '' : formatCurrency(raw);
            triggerMinValueAlert(target, minValueAlertMessage);
        } else if (target.matches('.doc-valor')) {
            const raw = target.value.replace(/\D/g, '');
            target.value = (raw === '') ? '' : formatCurrency(raw);
        }
        if (target.matches('.doc-valor, .doc-qtd, .valor-servico') || target.id === 'total_documentos') {
            updateAllCalculations();
            evaluateValorMinimo();
        }
    });

    // === FUNÇÕES AUXILIARES ===
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

    function updateAllCalculations() {
        let totalDocumentos = 0;
        let totalGeralCents = 0;
        ['tradução', 'crc', 'apostilamento', 'postagem'].forEach(sectionKey => {
            const sectionContainer = document.getElementById(`section-container-${sectionKey.toLowerCase()}`);
            if (sectionContainer && sectionContainer.style.display !== 'none') {
                const container = document.getElementById(`documentos-container-${sectionKey.toLowerCase()}`);
                container.querySelectorAll('.doc-row').forEach(row => {
                    const qtd = parseInt(row.querySelector('.doc-qtd')?.value || 1);
                    totalDocumentos += qtd;
                    const valorUnit = parseCurrency(row.querySelector('.doc-valor')?.value || '0');
                    const totalLinhaCents = Math.round(valorUnit * 100) * qtd;
                    totalGeralCents += totalLinhaCents;
                    const totalInput = row.querySelector('.doc-total');
                    if(totalInput) totalInput.value = formatCurrency(totalLinhaCents);
                });
            }
        });
        const totalDocsDisplay = document.getElementById('total-documentos-display');
        if(totalDocsDisplay) totalDocsDisplay.textContent = totalDocumentos;
        const totalGeralDisplay = document.getElementById('total-geral-display');
        if(totalGeralDisplay) totalGeralDisplay.textContent = formatCurrency(totalGeralCents);
        const hiddenTotal = document.getElementById('valor_total_hidden');
        if(hiddenTotal) hiddenTotal.value = totalGeralCents / 100;
    }

    function toggleServiceSection(checkbox) {
        const sectionId = `section-container-${checkbox.value.toLowerCase().replace(/\s+/g, '')}`;
        const section = document.getElementById(sectionId);

        if (section) {
            section.style.display = checkbox.checked ? 'block' : 'none';
        }

        updateAllCalculations();
        evaluateValorMinimo();
    }

    function evaluateValorMinimo() {
        const statusInput = document.getElementById('status_proposto');
        if (!statusInput) {
            return;
        }

        if (isGestor) {
            statusInput.value = 'Em andamento';
            return;
        }

        let pendente = false;
        document.querySelectorAll('.valor-servico').forEach(input => {
            const min = parseFloat(input.dataset.minValor || '0');
            const atual = parseCurrency(input.value);
            if (min > 0 && atual < min) {
                pendente = true;
            }
        });

        statusInput.value = pendente ? 'Pendente' : 'Em andamento';
    }

    // Este bloco agora funcionará, pois 'servicosCheckboxes' foi definido no início
    servicosCheckboxes.forEach(cb => {
        toggleServiceSection(cb); // Verifica o estado inicial
        cb.addEventListener('change', () => toggleServiceSection(cb)); // Adiciona o evento de clique
    });

    function formatCurrency(valueInCents) {
        if (isNaN(valueInCents)) return '';
        return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(valueInCents / 100);
    }
    function parseCurrency(formattedValue) {
        if (!formattedValue || typeof formattedValue !== 'string') return 0;
        return parseFloat(String(formattedValue).replace(/\D/g, '')) / 100 || 0;
    }

    // Gatilho inicial de cálculos e status
    updateAllCalculations();
    evaluateValorMinimo();

    const prazoTipoSelect = document.getElementById('prazo_tipo');
    const togglePrazoFields = () => {
        const diasContainer = document.getElementById('prazo_dias_container');
        const dataContainer = document.getElementById('prazo_data_container');
        const diasInput = document.getElementById('traducao_prazo_dias');
        const dataInput = document.getElementById('traducao_prazo_data');

        if (!diasContainer || !dataContainer) {
            return;
        }

        if (prazoTipoSelect && prazoTipoSelect.value === 'data') {
            diasContainer.classList.add('hidden');
            dataContainer.classList.remove('hidden');
            if (diasInput) {
                diasInput.setAttribute('disabled', 'disabled');
            }
            if (dataInput) {
                dataInput.removeAttribute('disabled');
            }
            return;
        }

        dataContainer.classList.add('hidden');
        diasContainer.classList.remove('hidden');
        if (dataInput) {
            dataInput.setAttribute('disabled', 'disabled');
        }
        if (diasInput) {
            diasInput.removeAttribute('disabled');
        }
    };

    if (prazoTipoSelect) {
        prazoTipoSelect.addEventListener('change', togglePrazoFields);
        togglePrazoFields();
    }
    
    updateAllCalculations(); // Execução inicial
});
</script>