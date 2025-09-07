<?php
// Adicione esta lógica no início do seu ficheiro servico-rapido.php
$isEditMode = false; // Defina como false, pois este é um formulário de criação
$return_url = $_SERVER['HTTP_REFERER'] ?? 'processos.php'; // Usa a página anterior ou um padrão seguro.
$cliente_pre_selecionado_id = $_GET['cliente_id'] ?? null;


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
                <input type="text" name="titulo" id="titulo" class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500" required>
            </div>
            <div>
                <label for="os_numero_conta_azul" class="block text-sm font-medium text-gray-700">O.S. Conta Azul</label>
                <input type="number" name="os_numero_conta_azul" id="os_numero_conta_azul" class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
            </div>
            <div>
                <label for="cliente_id" class="block text-sm font-medium text-gray-700">Cliente Associado</label>
                <div class="flex items-center space-x-2 mt-1">
                    <select name="cliente_id" id="cliente_id" class="block w-full p-2 border border-gray-300 rounded-md shadow-sm">
                        <option></option>
                        <?php foreach ($clientes as $cliente): ?>
                            <option value="<?php echo $cliente['id']; ?>" <?php 
                                $isSelected = ($isEditMode && $processo['cliente_id'] == $cliente['id']) || ($cliente_pre_selecionado_id == $cliente['id']);
                                echo $isSelected ? 'selected' : ''; 
                            ?>>
                                <?php echo htmlspecialchars($cliente['nome_cliente']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
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
                            <input id="cat_rapido_<?php echo $slug; ?>" name="categorias_servico[]" type="checkbox" value="<?php echo $servico; ?>" class="h-5 w-5 border-gray-300 rounded service-checkbox <?php echo $checkboxClasses; ?>">
                            <span class="ml-3 block text-sm font-semibold <?php echo $textClasses; ?>"><?php echo $servico; ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-x-6 gap-y-4 mt-6 pt-6 border-t border-gray-200">
            <div class="md:col-span-1">
                <label for="data_inicio_traducao" class="block text-sm font-medium text-gray-700">Data de Envio para Tradutor</label>
                <input type="date" name="data_inicio_traducao" id="data_inicio_traducao" value="<?php echo date('Y-m-d'); ?>" class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm">
            </div>
            <div class="md:col-span-1">
                <label for="prazo_tipo" class="block text-sm font-medium text-gray-700">Definir Prazo Para Serviço</label>
                <select name="prazo_tipo" id="prazo_tipo" class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm">
                    <option value="dias" selected>Por Dias</option>
                    <option value="data">Por Data</option>
                </select>
            </div>
            <div class="md:col-span-1">
                <div id="prazo_dias_container">
                    <label for="traducao_prazo_dias" class="block text-sm font-medium text-gray-700">Quantidade de Dias Corridos</label>
                    <input type="number" name="traducao_prazo_dias" id="traducao_prazo_dias" placeholder="Ex: 5" class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm">
                </div>
                <div id="prazo_data_container" style="display: none;">
                    <label for="traducao_prazo_data" class="block text-sm font-medium text-gray-700">Data da Entrega</label>
                    <input type="date" name="traducao_prazo_data" id="traducao_prazo_data" class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm" disabled>
                </div>
            </div>
        </div>
    </fieldset>

    <div id="section-container-tradução" class="bg-blue-50 p-6 rounded-lg shadow-lg border border-blue-200" style="display: none;">
        <h2 class="text-xl font-semibold mb-4 border-b border-blue-200 pb-2 text-blue-800">Detalhes da Tradução</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            <div>
                <label for="total_documentos" class="block text-sm font-medium text-gray-700">Total Documentos</label>
                <input type="number" name="documentos[traducao][0][quantidade]" id="total_documentos" value="1" class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm">
                <input type="hidden" name="documentos[traducao][0][categoria]" value="Tradução">
            </div>
            <div>
                <label for="traducao_modalidade" class="block text-sm font-medium text-gray-700">Modalidade</label>
                <select name="traducao_modalidade" id="traducao_modalidade" class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm">
                    <option value="Normal">Normal</option>
                    <option value="Express">Express</option>
                </select>
            </div>
            <div>
                <label for="tradutor_id" class="block text-sm font-medium text-gray-700">Tradutor</label>
                <select name="tradutor_id" id="tradutor_id" class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm">
                    <option value="">Selecione um tradutor</option>
                    <?php foreach ($tradutores as $tradutor): ?>
                        <option value="<?php echo $tradutor['id']; ?>"><?php echo htmlspecialchars($tradutor['nome_tradutor']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="assinatura_tipo" class="block text-sm font-medium text-gray-700">Assinatura</label>
                <select name="assinatura_tipo" id="assinatura_tipo" class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm">
                    <option value="Digital">Assinatura Digital</option>
                    <option value="Física">Assinatura Física</option>
                </select>
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
                <input type="hidden" name="valor_total_hidden" id="valor_total_hidden">
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
            <input type="text" name="documentos[crc][{index}][tipo_documento]" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm p-2" placeholder="Ex: Certidão de Nascimento">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700">Nome do Titular</label>
            <input type="text" name="documentos[crc][{index}][nome_documento]" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm p-2" placeholder="Nome Completo">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700">Valor</label>
            <input type="text" name="documentos[crc][{index}][valor_unitario]" class="doc-valor mt-1 block w-full border-gray-300 rounded-md shadow-sm p-2" placeholder="0,00">
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


<script>
document.addEventListener('DOMContentLoaded', function () {
    // === Manipulação de uploads ===
    const fileInput = document.getElementById('anexos-input-field');
    const fileListContainer = document.getElementById('file-list-container');

    if (fileInput && fileListContainer) {
        fileInput.addEventListener('change', function() {
            fileListContainer.innerHTML = '';
            if (this.files.length > 0) {
                const title = document.createElement('h4');
                title.className = 'font-semibold text-gray-600';
                title.textContent = this.files.length > 1 ? 'Arquivos Anexados:' : 'Arquivo Anexado:';
                fileListContainer.appendChild(title);

                Array.from(this.files).forEach(file => {
                    const fileItem = document.createElement('div');
                    fileItem.className = 'flex items-center bg-white p-2 border rounded-md shadow-sm';
                    const icon = '<i class="fas fa-file-alt text-gray-500 mr-3"></i>';
                    const fileInfo = `<div class="flex-grow">
                        <p class="font-medium text-gray-800">${file.name}</p>
                        <p class="text-xs text-gray-500">${(file.size / 1024).toFixed(2)} KB</p>
                    </div>`;
                    fileItem.innerHTML = icon + fileInfo;
                    fileListContainer.appendChild(fileItem);
                });
            }
        });
    }

    // === Select2 para cliente ===
    $('#cliente_id').select2({
        placeholder: "Selecione um cliente...",
        allowClear: true
    });

    // === Funções de formatação ===
    function formatCurrency(valueInCents) {
        const numeric = String(valueInCents).replace(/\D/g, '');
        if (numeric === '') return 'R$\u00a00,00';
        const floatVal = parseInt(numeric, 10) / 100;
        return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(floatVal);
    }

    function parseCurrency(formattedValue) {
        if (!formattedValue || typeof formattedValue !== 'string') return 0;
        const clean = formattedValue.replace(/[^0-9,]/g, '');
        return parseFloat(clean.replace(/\./g, '').replace(',', '.')) || 0;
    }

    // === Atualiza total de documentos e valores (em centavos) ===
    function updateAllCalculations() {
        let totalDocumentos = 0;
        let totalGeralCents = 0;

        // 1. Soma documentos e valores da seção TRADUÇÃO
        const traducaoSection = document.getElementById('section-container-tradução');
        if (traducaoSection && traducaoSection.style.display !== 'none') {
            const qtdTraducao = parseInt(document.getElementById('total_documentos').value) || 0;
            totalDocumentos += qtdTraducao;
        }

        // 2. Soma documentos e valores da seção CRC
        const crcContainer = document.getElementById('documentos-container-crc');
        if (crcContainer && crcContainer.closest('[id^="section-container-"]').style.display !== 'none') {
            crcContainer.querySelectorAll('.doc-row').forEach(row => {
                totalDocumentos += 1;
                const valorInput = row.querySelector('.doc-valor');
                const valorUnit = parseCurrency(valorInput.value); // valor em reais
                const valorCents = Math.round(valorUnit * 100);    // valor em centavos (inteiro)
                totalGeralCents += valorCents;
            });
        }

        // 3. Soma APENAS os valores de APOSTILAMENTO (centavos)
        const apostilamentoSection = document.getElementById('section-container-apostilamento');
        if (apostilamentoSection && apostilamentoSection.style.display !== 'none') {
            apostilamentoSection.querySelectorAll('.doc-row').forEach(row => {
                const qtd        = parseInt(row.querySelector('.doc-qtd').value) || 0;
                const valorUnit  = parseCurrency(row.querySelector('.doc-valor').value);
                const unitCents  = Math.round(valorUnit * 100);
                const totalCents = qtd * unitCents;
                // Atualiza o campo de valor total (somente leitura)
                row.querySelector('.doc-total').value = formatCurrency(totalCents);
                totalGeralCents += totalCents;
            });
        }

        // 4. Soma APENAS os valores de POSTAGEM (centavos)
        const postagemSection = document.getElementById('section-container-postagem');
        if (postagemSection && postagemSection.style.display !== 'none') {
            postagemSection.querySelectorAll('.doc-row').forEach(row => {
                const qtd        = parseInt(row.querySelector('.doc-qtd').value) || 0;
                const valorUnit  = parseCurrency(row.querySelector('.doc-valor').value);
                const unitCents  = Math.round(valorUnit * 100);
                const totalCents = qtd * unitCents;
                row.querySelector('.doc-total').value = formatCurrency(totalCents);
                totalGeralCents += totalCents;
            });
        }

        // 5. Atualiza os displays do resumo usando formatCurrency (centavos)
        document.getElementById('total-documentos-display').textContent = totalDocumentos;
        document.getElementById('total-geral-display').textContent      = formatCurrency(totalGeralCents);
        document.getElementById('valor_total_hidden').value             = formatCurrency(totalGeralCents);
    }

    // === Máscara de moeda dinâmica e cálculo em tempo real ===
    document.body.addEventListener('input', function(e) {
        const target = e.target;

        // Aplica máscara de moeda em tempo real nas classes .doc-valor
        if (target.matches('.doc-valor')) {
            const raw = target.value.replace(/\D/g, '');
            if (raw === '') {
                target.value = '';
            } else {
                const floatVal = parseInt(raw, 10) / 100;
                target.value = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(floatVal);
            }
        }

        // Sempre que valor ou quantidade mudar, recalcula os totais
        if (target.matches('.doc-valor, .doc-qtd') || target.id === 'total_documentos') {
            updateAllCalculations();
        }
    });

    // === Lógica de prazo e seções (inalterada) ===
    const prazoTipoSelect = document.getElementById('prazo_tipo');
    if (prazoTipoSelect) {
        prazoTipoSelect.addEventListener('change', function() {
            const diasContainer = document.getElementById('prazo_dias_container');
            const dataContainer = document.getElementById('prazo_data_container');
            diasContainer.style.display = (this.value === 'dias') ? 'block' : 'none';
            document.getElementById('traducao_prazo_dias').disabled = (this.value !== 'dias');
            dataContainer.style.display = (this.value === 'data') ? 'block' : 'none';
            document.getElementById('traducao_prazo_data').disabled = (this.value !== 'data');
        });
        prazoTipoSelect.dispatchEvent(new Event('change'));
    }

    // === Mostrar/ocultar seções de serviços ===
    const servicosCheckboxes = document.querySelectorAll('.service-checkbox');
    function toggleServiceSection(checkbox) {
        const sectionId = 'section-container-' + checkbox.value.toLowerCase().replace(/\s+/g, '');
        const sectionContainer = document.getElementById(sectionId);
        if (sectionContainer) {
            sectionContainer.style.display = checkbox.checked ? 'block' : 'none';
        }
        updateAllCalculations();
    }
    servicosCheckboxes.forEach(checkbox => {
        toggleServiceSection(checkbox);
        checkbox.addEventListener('change', () => toggleServiceSection(checkbox));
    });

    // === Repetidor de documentos ===
    const sectionCounters = { crc: 0, apostilamento: 0, postagem: 0 };
    document.querySelectorAll('.add-doc-row').forEach(button => {
        button.addEventListener('click', function () {
            const section   = this.dataset.section;
            const template  = document.getElementById(`template-${section}`);
            const container = document.getElementById(`documentos-container-${section}`);
            const index     = sectionCounters[section];
            const cloneHTML = template.innerHTML.replace(/{index}/g, index);
            container.insertAdjacentHTML('beforeend', cloneHTML);
            sectionCounters[section]++;
            updateAllCalculations();
        });
    });

    // === Remover documento ===
    document.body.addEventListener('click', function(e) {
        if (e.target.classList.contains('remove-doc-row')) {
            e.target.closest('.doc-row').remove();
            updateAllCalculations();
        }
    });

    // Execução inicial
    updateAllCalculations();
});
</script>
