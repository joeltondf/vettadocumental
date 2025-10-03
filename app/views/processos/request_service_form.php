<?php
$context = $leadConversionContext ?? [];
$clienteContext = $context['cliente'] ?? [];
$produtosOrcamento = $context['produtos'] ?? [];
$servicosMensalistas = $context['servicosMensalistas'] ?? [];
$valorTotalProcesso = $context['valorTotal'] ?? ($processo['valor_total'] ?? '');
$formaCobrancaAtual = $context['formaCobranca'] ?? ($processo['orcamento_forma_pagamento'] ?? 'À vista');
$parcelasAtuais = (int)($context['parcelas'] ?? ($processo['orcamento_parcelas'] ?? 1));
$valorEntradaAtual = $context['valorEntrada'] ?? ($processo['orcamento_valor_entrada'] ?? '');
$dataPagamento1Atual = $context['dataPagamento1'] ?? ($processo['data_pagamento_1'] ?? '');
$dataPagamento2Atual = $context['dataPagamento2'] ?? ($processo['data_pagamento_2'] ?? '');
$dataInicioTraducaoAtual = $context['dataInicioTraducao'] ?? date('Y-m-d');
$traducaoPrazoTipoAtual = $context['traducaoPrazoTipo'] ?? 'dias';
$traducaoPrazoDiasAtual = $context['traducaoPrazoDias'] ?? '';
$traducaoPrazoDataAtual = $context['traducaoPrazoData'] ?? '';
$tipoPessoaAtual = $clienteContext['tipo_pessoa'] ?? 'Jurídica';
$tipoAssessoriaAtual = $clienteContext['tipo_assessoria'] ?? 'À vista';
$cidadeValidationSource = $clienteContext['cidade_validation_source'] ?? 'api';
$cidadeAtual = $clienteContext['cidade'] ?? '';
$estadoAtual = $clienteContext['estado'] ?? '';
$nomeResponsavelAtual = $clienteContext['nome_responsavel'] ?? '';
$prazoAcordado = $clienteContext['prazo_acordado_dias'] ?? ($context['prazoAcordadoDias'] ?? '');
?>
<div class="bg-white shadow-lg rounded-lg p-6 border border-orange-200" data-lead-conversion-wizard>
    <div class="flex items-center justify-between mb-4">
        <div>
            <h2 class="text-xl font-semibold text-gray-800">Converter Orçamento em Serviço</h2>
            <p class="text-sm text-gray-600">Revise os dados do lead, defina o prazo da tradução e configure o pagamento.</p>
        </div>
        <span class="inline-flex items-center px-3 py-1 rounded-full bg-orange-100 text-orange-700 text-xs font-semibold">
            Fluxo orientado
        </span>
    </div>
    <form
        id="lead-conversion-form"
        class="space-y-6"
        action="processos.php?action=change_status"
        method="POST"
        enctype="multipart/form-data"
        data-wizard-form
        data-step-endpoint="processos.php?action=lead_conversion_step"
    >
        <input type="hidden" name="id" value="<?php echo (int)$processo['id']; ?>">
        <input type="hidden" name="status_processo" value="<?php echo htmlspecialchars($context['statusDestino'] ?? 'Serviço Pendente'); ?>">
        <input type="hidden" name="lead_conversion_required" value="1">
        <input type="hidden" name="valor_total" value="<?php echo htmlspecialchars($valorTotalProcesso); ?>" data-wizard-total>
        <input type="hidden" name="parcelas" value="<?php echo htmlspecialchars($parcelasAtuais ?: 1); ?>" data-wizard-installments>

        <div class="flex items-center space-x-4" data-wizard-stepper>
            <button type="button" class="flex-1 py-2 rounded-md bg-orange-500 text-white font-semibold focus:outline-none" data-stepper-button data-active-step="1">1. Cliente</button>
            <button type="button" class="flex-1 py-2 rounded-md bg-gray-200 text-gray-600 font-semibold focus:outline-none" data-stepper-button data-active-step="2">2. Prazo</button>
            <button type="button" class="flex-1 py-2 rounded-md bg-gray-200 text-gray-600 font-semibold focus:outline-none" data-stepper-button data-active-step="3">3. Pagamento</button>
        </div>

        <div class="rounded-lg border border-gray-200 p-6 space-y-6" data-step="1">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <span class="block text-sm font-semibold text-gray-700">Tipo de Pessoa</span>
                    <div class="mt-2 flex space-x-4">
                        <label class="inline-flex items-center space-x-2">
                            <input type="radio" name="lead_tipo_pessoa" value="Física" <?php echo $tipoPessoaAtual === 'Física' ? 'checked' : ''; ?>>
                            <span class="text-sm text-gray-700">Física</span>
                        </label>
                        <label class="inline-flex items-center space-x-2">
                            <input type="radio" name="lead_tipo_pessoa" value="Jurídica" <?php echo $tipoPessoaAtual !== 'Física' ? 'checked' : ''; ?>>
                            <span class="text-sm text-gray-700">Jurídica</span>
                        </label>
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700" for="lead_tipo_cliente">Condição Comercial</label>
                    <select id="lead_tipo_cliente" name="lead_tipo_cliente" class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-orange-500 focus:border-orange-500">
                        <option value="">Selecione</option>
                        <option value="À vista" <?php echo $tipoAssessoriaAtual === 'À vista' ? 'selected' : ''; ?>>À vista</option>
                        <option value="Mensalista" <?php echo $tipoAssessoriaAtual === 'Mensalista' ? 'selected' : ''; ?>>Mensalista</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700" for="lead_agreed_deadline_days">Prazo acordado (dias)</label>
                    <input type="number" min="1" id="lead_agreed_deadline_days" name="lead_agreed_deadline_days" value="<?php echo htmlspecialchars($prazoAcordado ?? ''); ?>" class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-orange-500 focus:border-orange-500">
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700" for="lead_nome_cliente">Nome do Cliente</label>
                    <input type="text" id="lead_nome_cliente" name="lead_nome_cliente" value="<?php echo htmlspecialchars($clienteContext['nome_cliente'] ?? ($processo['nome_cliente'] ?? '')); ?>" class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-orange-500 focus:border-orange-500">
                </div>
                <div data-responsavel-container class="<?php echo $tipoPessoaAtual === 'Física' ? 'hidden' : ''; ?>">
                    <label class="block text-sm font-semibold text-gray-700" for="lead_nome_responsavel">Nome do Responsável</label>
                    <input type="text" id="lead_nome_responsavel" name="lead_nome_responsavel" value="<?php echo htmlspecialchars($nomeResponsavelAtual); ?>" class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-orange-500 focus:border-orange-500">
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700" for="lead_cpf_cnpj">CPF ou CNPJ</label>
                    <input type="text" id="lead_cpf_cnpj" name="lead_cpf_cnpj" value="<?php echo htmlspecialchars($clienteContext['cpf_cnpj'] ?? ''); ?>" maxlength="18" class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-orange-500 focus:border-orange-500">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700" for="lead_email">E-mail</label>
                    <input type="email" id="lead_email" name="lead_email" value="<?php echo htmlspecialchars($clienteContext['email'] ?? ''); ?>" class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-orange-500 focus:border-orange-500">
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700" for="lead_telefone">Telefone</label>
                    <input type="text" id="lead_telefone" name="lead_telefone" value="<?php echo htmlspecialchars($clienteContext['telefone'] ?? ''); ?>" maxlength="15" class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-orange-500 focus:border-orange-500">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700" for="lead_cep">CEP</label>
                    <input type="text" id="lead_cep" name="lead_cep" value="<?php echo htmlspecialchars($clienteContext['cep'] ?? ''); ?>" maxlength="9" class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-orange-500 focus:border-orange-500">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700" for="lead_numero">Número</label>
                    <input type="text" id="lead_numero" name="lead_numero" value="<?php echo htmlspecialchars($clienteContext['numero'] ?? ''); ?>" class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-orange-500 focus:border-orange-500">
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700" for="lead_endereco">Endereço</label>
                    <input type="text" id="lead_endereco" name="lead_endereco" value="<?php echo htmlspecialchars($clienteContext['endereco'] ?? ''); ?>" class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-orange-500 focus:border-orange-500">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700" for="lead_complemento">Complemento</label>
                    <input type="text" id="lead_complemento" name="lead_complemento" value="<?php echo htmlspecialchars($clienteContext['complemento'] ?? ''); ?>" class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-orange-500 focus:border-orange-500">
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700" for="lead_bairro">Bairro</label>
                    <input type="text" id="lead_bairro" name="lead_bairro" value="<?php echo htmlspecialchars($clienteContext['bairro'] ?? ''); ?>" class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-orange-500 focus:border-orange-500">
                </div>
                <div data-city-autocomplete class="relative">
                    <label class="block text-sm font-semibold text-gray-700" for="lead_cidade">Cidade</label>
                    <input type="hidden" name="lead_city_validation_source" id="lead_city_validation_source" value="<?php echo htmlspecialchars($cidadeValidationSource); ?>">
                    <input
                        type="text"
                        id="lead_cidade"
                        name="lead_cidade"
                        value="<?php echo htmlspecialchars($cidadeAtual); ?>"
                        class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-orange-500 focus:border-orange-500"
                        autocomplete="off"
                        data-city-selected="<?php echo $cidadeAtual !== '' ? '1' : '0'; ?>"
                        data-initial-city-selected="<?php echo $cidadeAtual !== '' ? '1' : '0'; ?>"
                        aria-haspopup="listbox"
                        data-wizard-validate-on-value="true"
                        aria-expanded="false"
                        aria-controls="lead_cidade-options"
                    >
                    <div id="lead_cidade-options" role="listbox" class="absolute z-10 w-full bg-white border border-gray-300 rounded-md shadow-lg mt-1 hidden max-h-56 overflow-auto"></div>
                    <p id="lead_cidade-status" class="mt-1 text-xs text-gray-500 hidden" role="status" aria-live="polite"></p>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700" for="lead_estado">Estado (UF)</label>
                    <input type="text" id="lead_estado" name="lead_estado" value="<?php echo htmlspecialchars(strtoupper($estadoAtual)); ?>" maxlength="2" class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-orange-500 focus:border-orange-500 bg-gray-50 uppercase">
                </div>
            </div>

            <div id="subscription-services-wrapper" class="<?php echo $tipoAssessoriaAtual === 'Mensalista' ? '' : 'hidden'; ?> mt-6 space-y-4" data-subscription-container>
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-gray-700">Serviços Mensalistas</h3>
                    <button type="button" class="inline-flex items-center px-3 py-2 rounded-md bg-orange-500 text-white text-sm font-semibold shadow-sm hover:bg-orange-600 focus:outline-none" data-add-service>
                        Adicionar serviço
                    </button>
                </div>
                <div class="space-y-4" data-service-list>
                    <?php if (!empty($servicosMensalistas)): ?>
                        <?php foreach ($servicosMensalistas as $index => $servico): ?>
                            <?php $valorServico = isset($servico['valor_padrao']) ? number_format((float)$servico['valor_padrao'], 2, '.', '') : ''; ?>
                            <div class="p-4 border border-gray-200 rounded-lg space-y-4" data-service-item>
                                <div class="flex items-start justify-between gap-4">
                                    <div class="flex-1">
                                        <label class="block text-sm font-medium text-gray-700" for="service-product-<?php echo $index; ?>">Produto / Serviço</label>
                                        <select id="service-product-<?php echo $index; ?>" name="lead_subscription_services[<?php echo $index; ?>][productBudgetId]" class="mt-1 block w-full rounded-md border border-gray-300 shadow-sm focus:ring-orange-500 focus:border-orange-500 text-sm" data-service-select>
                                            <option value="">Selecione um produto</option>
                                            <?php foreach ($produtosOrcamento as $produto): ?>
                                                <?php
                                                    $valorPadraoProduto = isset($produto['valor_padrao']) ? number_format((float)$produto['valor_padrao'], 2, '.', '') : '';
                                                    $bloquearMinimo = !empty($produto['bloquear_valor_minimo']);
                                                    $servicoTipoProduto = $produto['servico_tipo'] ?? 'Nenhum';
                                                ?>
                                                <option
                                                    value="<?php echo $produto['id']; ?>"
                                                    data-servico-tipo="<?php echo htmlspecialchars($servicoTipoProduto); ?>"
                                                    data-valor-padrao="<?php echo htmlspecialchars($valorPadraoProduto); ?>"
                                                    data-bloquear-minimo="<?php echo $bloquearMinimo ? '1' : '0'; ?>"
                                                    <?php echo (int)$produto['id'] === (int)$servico['produto_orcamento_id'] ? 'selected' : ''; ?>
                                                >
                                                    <?php echo htmlspecialchars($produto['nome_categoria']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <button type="button" class="text-sm text-red-600 hover:text-red-700 font-semibold" data-remove-service>Remover</button>
                                </div>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700" for="service-value-<?php echo $index; ?>">Valor padrão</label>
                                        <input type="text" id="service-value-<?php echo $index; ?>" name="lead_subscription_services[<?php echo $index; ?>][standardValue]" value="<?php echo htmlspecialchars($valorServico); ?>" class="mt-1 block w-full rounded-md border border-gray-300 shadow-sm focus:ring-orange-500 focus:border-orange-500" data-service-value>
                                        <p class="mt-1 text-xs text-gray-500" data-minimum-hint></p>
                                    </div>
                                    <div class="flex items-end">
                                        <span class="inline-flex items-center px-3 py-1 rounded-full bg-gray-100 text-gray-700 text-xs font-semibold" data-service-type-badge>
                                            <?php echo htmlspecialchars($servico['servico_tipo'] ?? 'Nenhum'); ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <template data-service-template>
                    <div class="p-4 border border-gray-200 rounded-lg space-y-4" data-service-item>
                        <div class="flex items-start justify-between gap-4">
                            <div class="flex-1">
                                <label class="block text-sm font-medium text-gray-700" data-label-template="service-product-__index__">Produto / Serviço</label>
                                <select
                                    class="mt-1 block w-full rounded-md border border-gray-300 shadow-sm focus:ring-orange-500 focus:border-orange-500 text-sm"
                                    data-service-select
                                    data-name-template="lead_subscription_services[__index__][productBudgetId]"
                                    data-id-template="service-product-__index__"
                                >
                                    <option value="">Selecione um produto</option>
                                    <?php foreach ($produtosOrcamento as $produto): ?>
                                        <?php
                                            $valorPadraoProduto = isset($produto['valor_padrao']) ? number_format((float)$produto['valor_padrao'], 2, '.', '') : '';
                                            $bloquearMinimo = !empty($produto['bloquear_valor_minimo']);
                                            $servicoTipoProduto = $produto['servico_tipo'] ?? 'Nenhum';
                                        ?>
                                        <option
                                            value="<?php echo $produto['id']; ?>"
                                            data-servico-tipo="<?php echo htmlspecialchars($servicoTipoProduto); ?>"
                                            data-valor-padrao="<?php echo htmlspecialchars($valorPadraoProduto); ?>"
                                            data-bloquear-minimo="<?php echo $bloquearMinimo ? '1' : '0'; ?>"
                                        >
                                            <?php echo htmlspecialchars($produto['nome_categoria']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <button type="button" class="text-sm text-red-600 hover:text-red-700 font-semibold" data-remove-service>Remover</button>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700" data-label-template="service-value-__index__">Valor padrão</label>
                                <input
                                    type="text"
                                    class="mt-1 block w-full rounded-md border border-gray-300 shadow-sm focus:ring-orange-500 focus:border-orange-500"
                                    data-service-value
                                    data-name-template="lead_subscription_services[__index__][standardValue]"
                                    data-id-template="service-value-__index__"
                                >
                                <p class="mt-1 text-xs text-gray-500" data-minimum-hint></p>
                            </div>
                            <div class="flex items-end">
                                <span class="inline-flex items-center px-3 py-1 rounded-full bg-gray-100 text-gray-700 text-xs font-semibold" data-service-type-badge>Selecione um produto</span>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        <div class="rounded-lg border border-gray-200 p-6 space-y-6 hidden" data-step="2">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700" for="data_inicio_traducao">Data de início</label>
                    <input type="date" id="data_inicio_traducao" name="data_inicio_traducao" value="<?php echo htmlspecialchars($dataInicioTraducaoAtual); ?>" class="mt-1 block w-full rounded-md border border-gray-300 shadow-sm focus:ring-orange-500 focus:border-orange-500">
                </div>
                <div>
                    <span class="block text-sm font-semibold text-gray-700">Tipo de prazo</span>
                    <div class="mt-2 flex space-x-6">
                        <label class="inline-flex items-center space-x-2">
                            <input type="radio" name="traducao_prazo_tipo" value="dias" <?php echo $traducaoPrazoTipoAtual === 'data' ? '' : 'checked'; ?>>
                            <span class="text-sm text-gray-700">Dias</span>
                        </label>
                        <label class="inline-flex items-center space-x-2">
                            <input type="radio" name="traducao_prazo_tipo" value="data" <?php echo $traducaoPrazoTipoAtual === 'data' ? 'checked' : ''; ?>>
                            <span class="text-sm text-gray-700">Data específica</span>
                        </label>
                    </div>
                </div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div data-deadline-days-container class="<?php echo $traducaoPrazoTipoAtual === 'data' ? 'hidden' : ''; ?>">
                    <label class="block text-sm font-semibold text-gray-700" for="traducao_prazo_dias">Dias para entrega</label>
                    <input type="number" min="1" id="traducao_prazo_dias" name="traducao_prazo_dias" value="<?php echo htmlspecialchars($traducaoPrazoDiasAtual); ?>" class="mt-1 block w-full rounded-md border border-gray-300 shadow-sm focus:ring-orange-500 focus:border-orange-500">
                </div>
                <div data-deadline-date-container class="<?php echo $traducaoPrazoTipoAtual === 'data' ? '' : 'hidden'; ?>">
                    <label class="block text-sm font-semibold text-gray-700" for="traducao_prazo_data">Data de entrega</label>
                    <input type="date" id="traducao_prazo_data" name="traducao_prazo_data" value="<?php echo htmlspecialchars($traducaoPrazoDataAtual); ?>" class="mt-1 block w-full rounded-md border border-gray-300 shadow-sm focus:ring-orange-500 focus:border-orange-500">
                </div>
            </div>
        </div>

        <div class="rounded-lg border border-gray-200 p-6 space-y-6 hidden" data-step="3">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700" for="forma_cobranca">Forma de cobrança</label>
                    <select id="forma_cobranca" name="forma_cobranca" class="mt-1 block w-full rounded-md border border-gray-300 shadow-sm focus:ring-orange-500 focus:border-orange-500" data-payment-method>
                        <option value="À vista" <?php echo $formaCobrancaAtual === 'À vista' ? 'selected' : ''; ?>>À vista</option>
                        <option value="Parcelado" <?php echo $formaCobrancaAtual === 'Parcelado' ? 'selected' : ''; ?>>Parcelado</option>
                        <option value="Mensal" <?php echo $formaCobrancaAtual === 'Mensal' ? 'selected' : ''; ?>>Mensal</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700" for="valor_entrada">Valor pago / entrada</label>
                    <input type="text" id="valor_entrada" name="valor_entrada" value="<?php echo htmlspecialchars($valorEntradaAtual); ?>" class="mt-1 block w-full rounded-md border border-gray-300 shadow-sm focus:ring-orange-500 focus:border-orange-500" data-entry-value>
                </div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700" for="data_pagamento_1">Data do pagamento / 1ª parcela</label>
                    <input type="date" id="data_pagamento_1" name="data_pagamento_1" value="<?php echo htmlspecialchars($dataPagamento1Atual); ?>" class="mt-1 block w-full rounded-md border border-gray-300 shadow-sm focus:ring-orange-500 focus:border-orange-500">
                </div>
                <div data-second-installment class="<?php echo $formaCobrancaAtual === 'Parcelado' ? '' : 'hidden'; ?>">
                    <label class="block text-sm font-semibold text-gray-700" for="data_pagamento_2">Data 2ª parcela</label>
                    <input type="date" id="data_pagamento_2" name="data_pagamento_2" value="<?php echo htmlspecialchars($dataPagamento2Atual); ?>" class="mt-1 block w-full rounded-md border border-gray-300 shadow-sm focus:ring-orange-500 focus:border-orange-500">
                </div>
            </div>
            <div class="rounded-md bg-gray-50 p-4">
                <p class="text-sm text-gray-700"><strong>Valor total:</strong> <span data-total-display><?php echo $valorTotalProcesso !== '' ? 'R$ ' . number_format((float)$valorTotalProcesso, 2, ',', '.') : 'Não informado'; ?></span></p>
                <p class="text-sm text-gray-700 mt-1"><strong>Saldo restante:</strong> <span data-balance-display>-</span></p>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700" for="payment_proof_entry">Comprovante de pagamento</label>
                    <input type="file" id="payment_proof_entry" name="payment_proof_entry" accept=".pdf,.png,.jpg,.jpeg,.webp" class="mt-1 block w-full text-sm text-gray-600">
                </div>
                <div data-second-proof class="<?php echo $formaCobrancaAtual === 'Parcelado' ? '' : 'hidden'; ?>">
                    <label class="block text-sm font-semibold text-gray-700" for="payment_proof_balance">Comprovante saldo</label>
                    <input type="file" id="payment_proof_balance" name="payment_proof_balance" accept=".pdf,.png,.jpg,.jpeg,.webp" class="mt-1 block w-full text-sm text-gray-600">
                </div>
            </div>
        </div>

        <div class="flex items-center justify-between pt-4 border-t border-gray-200">
            <button type="button" class="px-4 py-2 rounded-md border border-gray-300 text-gray-700 text-sm font-semibold hover:bg-gray-50" data-wizard-prev>Voltar</button>
            <div class="flex items-center space-x-3">
                <p class="text-sm text-gray-600" data-wizard-feedback></p>
                <button type="button" class="px-4 py-2 rounded-md bg-orange-500 text-white text-sm font-semibold shadow-sm hover:bg-orange-600 focus:outline-none" data-wizard-next>Avançar</button>
                <button type="submit" class="px-4 py-2 rounded-md bg-green-600 text-white text-sm font-semibold shadow-sm hover:bg-green-700 focus:outline-none hidden" data-wizard-submit>Concluir Conversão</button>
            </div>
        </div>
    </form>
</div>
