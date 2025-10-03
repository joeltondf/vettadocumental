<?php
// Simulação de dados para que o template funcione (substitua pelo seu carregamento de dados real)
// require_once __DIR__ . '/../bootstrap.php'; // Exemplo de como você carregaria seus dados
// $filters = $_GET;
// $overallSummary = ['total_valor_total' => 50000, 'total_valor_entrada' => 35000, 'total_valor_restante' => 15000, 'media_valor_documento' => 2500];
// $formas_pagamento = [['id' => 1, 'nome' => 'PIX'], ['id' => 2, 'nome' => 'Boleto'], ['id' => 3, 'nome' => 'Cartão de Crédito']];
// $vendedores = [['id' => 1, 'nome_vendedor' => 'João Silva'], ['id' => 2, 'nome_vendedor' => 'Maria Oliveira']];
// $clientes = [['id' => 101, 'nome' => 'Empresa A'], ['id' => 102, 'nome' => 'Cliente B Final']];
// $aggregatedTotals = ['2025-07' => ['valor_total' => 30000, 'valor_entrada' => 20000, 'valor_restante' => 10000], '2025-06' => ['valor_total' => 20000, 'valor_entrada' => 15000, 'valor_restante' => 5000]];
// $processos = [
//     ['id' => 1, 'os_numero_omie' => '12345', 'titulo' => 'Tradução Contrato Social', 'nome_cliente' => 'Empresa A', 'nome_vendedor' => 'João Silva', 'forma_pagamento_id' => 1, 'categorias_servico' => 'Tradução,Apostilamento', 'total_documentos' => 5, 'data_criacao' => '2025-07-15', 'valor_total' => 1500.50, 'orcamento_valor_entrada' => 1000, 'orcamento_valor_restante' => 500.50, 'orcamento_parcelas' => 2, 'data_pagamento_1' => '2025-07-16', 'data_pagamento_2' => '2025-08-16'],
//     ['id' => 2, 'os_numero_omie' => '12346', 'titulo' => 'Apostilamento de Certidão', 'nome_cliente' => 'Cliente B Final', 'nome_vendedor' => 'Maria Oliveira', 'forma_pagamento_id' => 2, 'categorias_servico' => 'Apostilamento', 'total_documentos' => 2, 'data_criacao' => '2025-07-20', 'valor_total' => 800.00, 'orcamento_valor_entrada' => 800.00, 'orcamento_valor_restante' => 0.00, 'orcamento_parcelas' => 1, 'data_pagamento_1' => '2025-07-21', 'data_pagamento_2' => null],
// ];

require_once __DIR__ . '/../layouts/header.php'; 
?>

<div class="ontainer mx-auto px-4 py-8" id="report-content">
    <div class="flex justify-between items-center mb-6 print-hide">
        <h1 class="text-3xl font-bold text-gray-800">Relatório de Serviços</h1>

        <div class="flex space-x-2">
            <button id="export-csv-btn" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline text-sm">
                Exportar para CSV
            </button>
            <button id="print-btn" class="bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline text-sm">
                Imprimir
            </button>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8 print-hide">
        <div class="bg-white shadow-md rounded-lg p-4 text-center">
            <h3 class="text-base font-semibold text-gray-600 mb-1">Valor Total Período</h3>
            <p class="text-2xl font-bold text-blue-600">R$ <?php echo number_format($overallSummary['total_valor_total'] ?? 0, 2, ',', '.'); ?></p>
        </div>
        <div class="bg-white shadow-md rounded-lg p-4 text-center">
            <h3 class="text-base font-semibold text-gray-600 mb-1">Entrada Recebida</h3>
            <p class="text-2xl font-bold text-green-600">R$ <?php echo number_format($overallSummary['total_valor_entrada'] ?? 0, 2, ',', '.'); ?></p>
        </div>
        <div class="bg-white shadow-md rounded-lg p-4 text-center">
            <h3 class="text-base font-semibold text-gray-600 mb-1">Valor Restante</h3>
            <p class="text-2xl font-bold text-red-600">R$ <?php echo number_format($overallSummary['total_valor_restante'] ?? 0, 2, ',', '.'); ?></p>
        </div>
        <div class="bg-white shadow-md rounded-lg p-4 text-center">
            <h3 class="text-base font-semibold text-gray-600 mb-1">Média por Documento (Período)</h3>
            <p class="text-2xl font-bold text-purple-600">R$ <?php echo number_format($overallSummary['media_valor_documento'] ?? 0, 2, ',', '.'); ?></p>
        </div>
    </div>


    <div class="bg-white shadow-md rounded-lg p-4 mb-6 print-hide">
        <h2 class="text-xl font-semibold mb-3 text-gray-700">Filtrar Dados Financeiros</h2>
        <form method="GET" action="/financeiro.php" class="grid grid-cols-1 md:grid-cols-6 gap-3 items-end">
            <div>
                <label for="start_date" class="block text-gray-700 text-sm font-bold mb-1">Data Inicial (Criação):</label>
                <input type="date" name="start_date" id="start_date"
                       value="<?php echo htmlspecialchars($_GET['start_date'] ?? date('Y-m-01')); ?>"
                       class="shadow appearance-none border rounded w-full py-1 px-2 text-sm text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
            </div>
            <div>
                <label for="end_date" class="block text-gray-700 text-sm font-bold mb-1">Data Final (Criação):</label>
                <input type="date" name="end_date" id="end_date"
                       value="<?php echo htmlspecialchars($_GET['end_date'] ?? date('Y-m-t')); ?>"
                       class="shadow appearance-none border rounded w-full py-1 px-2 text-sm text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
            </div>

            <div>
                <label for="cliente_id" class="block text-gray-700 text-sm font-bold mb-1">Cliente:</label>
                <select name="cliente_id" id="cliente_id"
                        class="shadow appearance-none border rounded w-full py-1 px-2 text-sm text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                    <option value="">Todos os Clientes</option>
                    <?php
                    // IMPORTANTE: Você precisará passar a variável $clientes do seu controller para cá.
                    if (!empty($clientes)):
                        foreach ($clientes as $cliente): ?>
                            <option value="<?php echo htmlspecialchars($cliente['id']); ?>"
                                <?php echo ((string)($filters['cliente_id'] ?? '') === (string)$cliente['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cliente['nome']); ?>
                            </option>
                        <?php endforeach;
                    endif;
                    ?>
                </select>
            </div>

            <div>
                <label for="forma_pagamento_id" class="block text-gray-700 text-sm font-bold mb-1">Forma Pagto:</label>
                <select name="forma_pagamento_id" id="forma_pagamento_id"
                        class="shadow appearance-none border rounded w-full py-1 px-2 text-sm text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                    <option value="todos">Todas</option>
                    <?php if (!empty($formas_pagamento)) : foreach ($formas_pagamento as $forma): ?>
                        <option value="<?php echo htmlspecialchars($forma['id']); ?>"
                            <?php echo ((string)($filters['forma_pagamento_id'] ?? '') === (string)$forma['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($forma['nome']); ?>
                        </option>
                    <?php endforeach; endif; ?>
                </select>
            </div>
            <div>
                <label for="vendedor_id" class="block text-gray-700 text-sm font-bold mb-1">Vendedor:</label>
                <select name="vendedor_id" id="vendedor_id"
                        class="shadow appearance-none border rounded w-full py-1 px-2 text-sm text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                    <option value="">Todos Vendedores</option>
                    <?php if (!empty($vendedores)) : foreach ($vendedores as $vendedor): ?>
                        <option value="<?php echo htmlspecialchars($vendedor['id']); ?>"
                            <?php echo ((string)($filters['vendedor_id'] ?? '') === (string)$vendedor['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($vendedor['nome_vendedor']); ?>
                        </option>
                    <?php endforeach; endif; ?>
                </select>
            </div>
            <div>
                <button type="submit"
                        class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline text-sm w-full">
                    Aplicar Filtro
                </button>
            </div>
        </form>
    </div>

    <?php if (!empty($aggregatedTotals)): ?>
        <div class="bg-white shadow-md rounded-lg p-4 mb-6">
            <h2 class="text-xl font-semibold mb-3 text-gray-700">Totais de Processos Finalizados (Por Mês)</h2>
            <div class="overflow-x-auto">
                <table class="min-w-full bg-white">
                    <thead class="bg-gray-200 text-gray-600 uppercase text-xs leading-normal">
                        <tr>
                            <th class="py-2 px-2 text-left">Período</th>
                            <th class="py-2 px-2 text-right">Valor Total</th>
                            <th class="py-2 px-2 text-right">Valor Entrada</th>
                            <th class="py-2 px-2 text-right">Valor Restante</th>
                        </tr>
                    </thead>
                    <tbody class="text-gray-600 text-xs font-light">
                        <?php foreach ($aggregatedTotals as $period => $totals): ?>
                            <tr class="border-b border-gray-200 hover:bg-gray-100">
                                <td class="py-2 px-2 text-left whitespace-no-wrap"><?php echo htmlspecialchars($period); ?></td>
                                <td class="py-2 px-2 text-right">R$ <?php echo number_format($totals['valor_total'], 2, ',', '.'); ?></td>
                                <td class="py-2 px-2 text-right">R$ <?php echo number_format($totals['valor_entrada'], 2, ',', '.'); ?></td>
                                <td class="py-2 px-2 text-right">R$ <?php echo number_format($totals['valor_restante'], 2, ',', '.'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php else: ?>
        <p class="text-gray-600 mb-6 text-xs print-hide">Nenhum total agregado de processos finalizados encontrado para o período selecionado.</p>
    <?php endif; ?>

    <h2 class="text-xl font-semibold mb-3 text-gray-700">Processos Individuais</h2>
    <div class="bg-white shadow-md rounded-lg overflow-x-auto">
        <table id="processos-table" class="min-w-full leading-normal">
            <thead>
                <tr>
                    <th class="px-1 py-1 border-b-2 border-gray-400 bg-gray-100 text-left text-[9px] font-semibold text-gray-600 uppercase tracking-wider">OS</th>
                    <th class="px-1 py-1 border-b-2 border-gray-400 bg-gray-100 text-left text-[9px] font-semibold text-gray-600 uppercase tracking-wider table-col-title">Título</th>
                    <th class="px-1 py-1 border-b-2 border-gray-400 bg-gray-100 text-left text-[9px] font-semibold text-gray-600 uppercase tracking-wider table-col-client">Cliente</th>
                    <th class="px-1 py-1 border-b-2 border-gray-400 bg-gray-100 text-left text-[9px] font-semibold text-gray-600 uppercase tracking-wider table-col-seller">Vendedor</th>
                    <th class="px-1 py-1 border-b-2 border-gray-400 bg-gray-100 text-left text-[9px] font-semibold text-gray-600 uppercase tracking-wider table-col-pay-form">Forma Pagto</th>
                    <th class="px-1 py-1 border-b-2 border-gray-400 bg-gray-100 text-left text-[9px] font-semibold text-gray-600 uppercase tracking-wider table-col-service-type">Tipo Serviço</th>
                    <th class="px-1 py-1 border-b-2 border-gray-400 bg-gray-100 text-center text-[9px] font-semibold text-gray-600 uppercase tracking-wider table-col-docs">Nº Docs</th>
                    <th class="px-1 py-1 border-b-2 border-gray-400 bg-gray-100 text-center text-[9px] font-semibold text-gray-600 uppercase tracking-wider table-col-date">Data Criação</th>
                    <th class="px-1 py-1 border-b-2 border-gray-400 bg-gray-100 text-right text-[9px] font-semibold text-gray-600 uppercase tracking-wider table-col-value">Total</th>
                    <th class="px-1 py-1 border-b-2 border-gray-400 bg-gray-100 text-right text-[9px] font-semibold text-gray-600 uppercase tracking-wider table-col-value">Entrada</th>
                    <th class="px-1 py-1 border-b-2 border-gray-400 bg-gray-100 text-right text-[9px] font-semibold text-gray-600 uppercase tracking-wider table-col-value">Restante</th>
                    <th class="px-1 py-1 border-b-2 border-gray-400 bg-gray-100 text-center text-[9px] font-semibold text-gray-600 uppercase tracking-wider table-col-tiny">Parcelas</th>
                    <th class="px-1 py-1 border-b-2 border-gray-400 bg-gray-100 text-center text-[9px] font-semibold text-gray-600 uppercase tracking-wider table-col-date">Data 1ª Parcela</th>
                    <th class="px-1 py-1 border-b-2 border-gray-400 bg-gray-100 text-center text-[9px] font-semibold text-gray-600 uppercase tracking-wider table-col-date">Data 2ª Parcela</th>
                    <th class="px-1 py-1 border-b-2 border-gray-400 bg-gray-100 text-center text-[9px] font-semibold text-gray-600 uppercase tracking-wider table-col-status">Status Pagto</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($processos)): ?>
                    <tr>
                        <td colspan="15" class="px-1 py-1 border-b border-gray-200 bg-white text-xs text-center"> Nenhum processo para exibir para o período ou filtros selecionados.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($processos as $processo): ?>
                        <tr data-process-id="<?php echo htmlspecialchars($processo['id']); ?>">
                            <td class="px-1 py-1 border-b border-gray-200 bg-white text-xs">
                                <span class="text-blue-600 hover:text-blue-900 whitespace-no-wrap">
                                    <?php echo htmlspecialchars(empty($processo['os_numero_omie']) ? 'Aguardando Omie' : $processo['os_numero_omie']); ?>
                                </span>
                            </td>
                            <td class="px-1 py-1 border-b border-gray-200 bg-white text-xs text-truncate">
                                <p class="text-gray-900" title="<?php echo htmlspecialchars($processo['titulo'] ?? ''); ?>"><?php echo htmlspecialchars($processo['titulo'] ?? ''); ?></p>
                            </td>
                            <td class="px-1 py-1 border-b border-gray-200 bg-white text-xs text-truncate">
                                <p class="text-gray-900" title="<?php echo htmlspecialchars($processo['nome_cliente'] ?? ''); ?>"><?php echo htmlspecialchars($processo['nome_cliente'] ?? ''); ?></p>
                            </td>
                            <td class="px-1 py-1 border-b border-gray-200 bg-white text-xs text-truncate">
                                <p class="text-gray-900" title="<?php echo htmlspecialchars($processo['nome_vendedor'] ?? ''); ?>"><?php echo htmlspecialchars($processo['nome_vendedor'] ?? ''); ?></p>
                            </td>
                            <td class="px-1 py-1 border-b border-gray-200 bg-white text-xs">
                                <select name="forma_pagamento_id"
                                        class="editable-select shadow-sm border rounded w-full py-1 px-2 text-xs text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                                        data-process-id="<?php echo $processo['id']; ?>"
                                        data-field="forma_pagamento_id">
                                    <option value="">Selecione...</option>
                                    <?php if (!empty($formas_pagamento)) : foreach ($formas_pagamento as $forma): ?>
                                        <option value="<?php echo htmlspecialchars($forma['id']); ?>"
                                            <?php if (isset($processo['forma_pagamento_id']) && $processo['forma_pagamento_id'] == $forma['id']) echo 'selected'; ?>>
                                            <?php echo htmlspecialchars($forma['nome']); ?>
                                        </option>
                                    <?php endforeach; endif; ?>
                                </select>
                            </td>

                            <td class="px-2 py-2 border-b border-gray-200 bg-white text-xs text-truncate">
                                <?php
                                    $servicos_originais = $processo['categorias_servico'] ?? '';
                                    if (!empty($servicos_originais)) {
                                        $mapa_abreviacoes = ['Tradução' => 'trad.', 'CRC' => 'crc', 'Apostilamento' => 'apost.', 'Postagem' => 'post.'];
                                        $servicos_array = explode(',', $servicos_originais);
                                        $servicos_abreviados_array = [];
                                        foreach ($servicos_array as $servico) {
                                            $servico_limpo = trim($servico);
                                            $servicos_abreviados_array[] = $mapa_abreviacoes[$servico_limpo] ?? $servico_limpo;
                                        }
                                        $texto_abreviado = implode(', ', $servicos_abreviados_array);
                                    } else {
                                        $texto_abreviado = 'N/A';
                                    }
                                ?>
                                <p class="text-gray-900" title="<?php echo htmlspecialchars($servicos_originais); ?>">
                                    <?php echo htmlspecialchars($texto_abreviado); ?>
                                </p>
                            </td>

                            <td class="px-1 py-1 border-b border-gray-200 bg-white text-xs text-center">
                                <p class="text-gray-900 whitespace-no-wrap"><?php echo htmlspecialchars($processo['total_documentos'] ?? 0); ?></p>
                            </td>
                            <td class="px-1 py-1 border-b border-gray-200 bg-white text-xs text-center">
                                <p class="text-gray-900 whitespace-no-wrap">
                                    <?php echo $processo['data_criacao'] ? date('d/m/Y', strtotime($processo['data_criacao'])) : 'N/A'; ?>
                                </p>
                            </td>
                            <td class="px-1 py-1 border-b border-gray-200 bg-white text-xs text-right editable" data-field="valor_total" data-value-type="currency">
                                <span class="editable-content">R$ <?php echo number_format($processo['valor_total'] ?? 0, 2, ',', '.'); ?></span>
                                <input type="text" class="edit-input hidden w-full text-right" value="<?php echo number_format($processo['valor_total'] ?? 0, 2, ',', '.'); ?>">
                            </td>
                            <td class="px-1 py-1 border-b border-gray-200 bg-white text-xs text-right editable" data-field="orcamento_valor_entrada" data-value-type="currency">
                                <span class="editable-content">R$ <?php echo number_format($processo['orcamento_valor_entrada'] ?? 0, 2, ',', '.'); ?></span>
                                <input type="text" class="edit-input hidden w-full text-right" value="<?php echo number_format($processo['orcamento_valor_entrada'] ?? 0, 2, ',', '.'); ?>">
                            </td>
                            <td class="px-1 py-1 border-b border-gray-200 bg-white text-xs text-right editable" data-field="orcamento_valor_restante" data-value-type="currency">
                                <span class="editable-content">R$ <?php echo number_format($processo['orcamento_valor_restante'] ?? 0, 2, ',', '.'); ?></span>
                                <input type="text" class="edit-input hidden w-full text-right" value="<?php echo number_format($processo['orcamento_valor_restante'] ?? 0, 2, ',', '.'); ?>">
                            </td>
                            <td class="px-1 py-1 border-b border-gray-200 bg-white text-xs text-center">
                                <p class="text-gray-900 whitespace-no-wrap"><?php echo htmlspecialchars($processo['orcamento_parcelas'] ?? 'N/A'); ?></p>
                            </td>
                            <td class="px-1 py-1 border-b border-gray-200 bg-white text-xs text-center editable" data-field="data_pagamento_1" data-value-type="date">
                                <span class="editable-content"><?php echo $processo['data_pagamento_1'] ? date('d/m/Y', strtotime($processo['data_pagamento_1'])) : 'N/A'; ?></span>
                                <input type="date" class="edit-input hidden w-full text-center" value="<?php echo $processo['data_pagamento_1'] ?? ''; ?>">
                            </td>
                            <td class="px-1 py-1 border-b border-gray-200 bg-white text-xs text-center editable" data-field="data_pagamento_2" data-value-type="date">
                                <span class="editable-content"><?php echo $processo['data_pagamento_2'] ? date('d/m/Y', strtotime($processo['data_pagamento_2'])) : 'N/A'; ?></span>
                                <input type="date" class="edit-input hidden w-full text-center" value="<?php echo $processo['data_pagamento_2'] ?? ''; ?>">
                            </td>
                            <td class="px-1 py-1 border-b border-gray-200 bg-white text-xs text-center">
                                <?php
                                    $status_pagamento_class = 'bg-red-200 text-red-900';
                                    $status_pagamento_text = 'Pendente';
                                    if (($processo['valor_total'] ?? 0) > 0 && ($processo['orcamento_valor_restante'] ?? 0) <= 0.01) {
                                        $status_pagamento_class = 'bg-green-200 text-green-900';
                                        $status_pagamento_text = 'Pago';
                                    } elseif (($processo['orcamento_valor_entrada'] ?? 0) > 0 && ($processo['orcamento_valor_restante'] ?? 0) > 0) {
                                        $status_pagamento_class = 'bg-yellow-200 text-yellow-900';
                                        $status_pagamento_text = 'Parcial';
                                    }
                                ?>
                                <span class="relative inline-block px-2 py-0.5 font-semibold <?php echo $status_pagamento_class; ?> leading-tight rounded-full text-xs">
                                    <span class="relative"><?php echo $status_pagamento_text; ?></span>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<style>
/* Estilos visuais gerais */
.table-col-title, .table-col-client, .table-col-seller, .table-col-pay-form, .table-col-service-type {
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.table-col-title { max-width: 150px; } .table-col-client { max-width: 120px; } .table-col-seller { max-width: 80px; } .table-col-pay-form { max-width: 80px; } .table-col-service-type { max-width: 100px; } .table-col-docs { max-width: 60px; } .table-col-date { max-width: 90px; } .table-col-value { max-width: 100px; } .table-col-tiny { max-width: 60px; } .table-col-status { max-width: 80px; }
.editable input.edit-input { border: 1px solid #ccc; padding: 2px 3px; box-shadow: inset 0 1px 2px rgba(0,0,0,0.1); border-radius: 4px; font-size: 0.75rem; line-height: 1rem; box-sizing: border-box; }
.editable input[type="date"].edit-input { min-width: 100px; }
.editable.saving { background-color: #e0f2fe; } .editable.error { background-color: #ffebee; } .editable.success { background-color: #e8f5e9; }
.editable .editable-content.hidden + .edit-input { display: block; } .editable .editable-content { display: inline-block; } .editable .edit-input { display: none; }
</style>

<style>
@media print {
    /* Define o layout da página de impressão como paisagem para acomodar a tabela */
    @page {
        size: A4 landscape;
        margin: 1.5cm;
    }

    body, #report-content {
        background-color: #fff !important;
        color: #000 !important;
        width: 100% !important;
        margin: 0 !important;
        padding: 0 !important;
        box-shadow: none !important;
    }
    
    /* Esconde elementos desnecessários na impressão */
    .print-hide, 
    header, /* Esconde o header principal do site (ajuste o seletor se for diferente) */
    footer, /* Esconde o footer principal do site (ajuste o seletor se for diferente) */
    .editable-select,
    .editable input
    {
        display: none !important;
    }

    /* Garante que os containers do relatório fiquem visíveis e bem formatados */
    .bg-white, .overflow-x-auto, #report-content {
        box-shadow: none !important;
        border: none !important;
        overflow: visible !important;
    }
    
    /* Ajusta a tabela para a impressão */
    table {
        width: 100%;
        font-size: 8pt; /* Reduz a fonte para caber mais colunas */
        border-collapse: collapse;
    }

    th, td {
        border: 1px solid #ccc; /* Adiciona borda para melhor visualização */
        padding: 4px;
        white-space: normal;
        word-wrap: break-word;
    }

    th {
        background-color: #f2f2f2 !important;
    }
    
    /* Força o navegador a imprimir as cores de fundo dos status */
    .bg-red-200, .bg-green-200, .bg-yellow-200 {
        print-color-adjust: exact;
        -webkit-print-color-adjust: exact;
    }
    
    a {
        text-decoration: none;
        color: inherit;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const printBtn = document.getElementById('print-btn');
    if (printBtn) {
        printBtn.addEventListener('click', () => window.print());
    }

    const exportBtn = document.getElementById('export-csv-btn');
    if (exportBtn) {
        exportBtn.addEventListener('click', () => {
            const table = document.getElementById('processos-table');
            if (table) {
                exportTableToCSV(table, 'relatorio-servicos.csv');
            }
        });
    }

    /**
     * Exporta a tabela para CSV formatado para Excel (ponto e vírgula, números e datas corretos).
     */
    function exportTableToCSV(table, filename) {
        const rows = table.querySelectorAll('tr');
        let csv = [];
        const separator = ';'; // Ponto e vírgula para compatibilidade com Excel no Brasil

        const headers = [];
        rows[0].querySelectorAll('th').forEach(header => {
            headers.push(`"${header.innerText.trim()}"`);
        });
        csv.push(headers.join(separator));
        
        for (let i = 1; i < rows.length; i++) {
            const row = [], cols = rows[i].querySelectorAll('td');
            
            if (cols.length === 1 && cols[0].getAttribute('colspan')) continue;

            cols.forEach(col => {
                let data = '';
                const editableContent = col.querySelector('.editable-content');

                if (col.querySelector('select')) {
                    const select = col.querySelector('select');
                    data = select.selectedIndex > 0 ? select.options[select.selectedIndex].text : '';
                } else if (editableContent) {
                    const dataType = col.dataset.valueType;
                    const rawText = editableContent.innerText.trim();

                    if (dataType === 'currency') {
                        // Converte 'R$ 1.234,56' para '1234.56'
                        data = rawText.replace('R$', '').replace(/\./g, '').replace(',', '.').trim();
                    } else if (dataType === 'date') {
                        // Converte 'dd/mm/YYYY' para 'YYYY-MM-DD'
                        if (rawText && rawText !== 'N/A') {
                            const parts = rawText.split('/');
                            if (parts.length === 3) {
                                data = `${parts[2]}-${parts[1]}-${parts[0]}`;
                            }
                        }
                    } else {
                        data = rawText;
                    }
                } else {
                    data = col.innerText.trim();
                }
                
                data = data.replace(/"/g, '""'); // Escapa aspas duplas
                row.push(`"${data}"`);
            });
            csv.push(row.join(separator));
        }

        downloadCSV(csv.join('\n'), filename);
    }

    function downloadCSV(csvContent, filename) {
        // Usa BOM para garantir a correta interpretação de caracteres UTF-8 no Excel
        const csvFile = new Blob(["\uFEFF" + csvContent], { type: 'text/csv;charset=utf-8;' });
        const downloadLink = document.createElement('a');
        
        downloadLink.download = filename;
        downloadLink.href = window.URL.createObjectURL(csvFile);
        downloadLink.style.display = 'none';

        document.body.appendChild(downloadLink);
        downloadLink.click();
        document.body.removeChild(downloadLink);
    }
});
</script>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>