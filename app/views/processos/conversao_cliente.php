<?php
$formData = $formData ?? [];
$budgetProducts = $produtosOrcamento ?? [];
$subscriptionServices = $servicosMensalistas ?? [];
$processId = (int)($processo['id'] ?? 0);
$personType = $formData['lead_tipo_pessoa'] ?? 'Jurídica';
$planType = $formData['lead_tipo_cliente'] ?? 'À vista';
$cityValidation = $formData['lead_city_validation_source'] ?? 'api';
?>
<div class="max-w-5xl mx-auto space-y-8">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Converter em Serviço &mdash; Dados do Cliente</h1>
            <p class="text-sm text-gray-600">Revise e complete as informações do cliente antes de seguir para o prazo do serviço.</p>
        </div>
        <a href="processos.php?action=view&id=<?php echo $processId; ?>" class="text-sm text-blue-600 hover:underline">&larr; Voltar para o processo</a>
    </div>

    <form
        action="processos.php?action=convert_to_service_client&id=<?php echo $processId; ?>"
        method="POST"
        class="bg-white shadow rounded-lg p-6 space-y-8"
        data-conversion-step="client"
    >
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <span class="block text-sm font-semibold text-gray-700">Tipo de Pessoa</span>
                <div class="mt-2 flex space-x-4" data-person-type-group>
                    <label class="inline-flex items-center space-x-2">
                        <input type="radio" name="lead_tipo_pessoa" value="Física" <?php echo $personType === 'Física' ? 'checked' : ''; ?> data-person-type>
                        <span class="text-sm text-gray-700">Física</span>
                    </label>
                    <label class="inline-flex items-center space-x-2">
                        <input type="radio" name="lead_tipo_pessoa" value="Jurídica" <?php echo $personType !== 'Física' ? 'checked' : ''; ?> data-person-type>
                        <span class="text-sm text-gray-700">Jurídica</span>
                    </label>
                </div>
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700" for="lead_tipo_cliente">Condição Comercial</label>
                <select id="lead_tipo_cliente" name="lead_tipo_cliente" class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-orange-500 focus:border-orange-500" data-plan-select>
                    <option value="">Selecione</option>
                    <option value="À vista" <?php echo $planType === 'À vista' ? 'selected' : ''; ?>>À vista</option>
                    <option value="Mensalista" <?php echo $planType === 'Mensalista' ? 'selected' : ''; ?>>Mensalista</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700" for="lead_agreed_deadline_days">Prazo acordado (dias)</label>
                <input type="number" min="1" id="lead_agreed_deadline_days" name="lead_agreed_deadline_days" value="<?php echo htmlspecialchars($formData['lead_agreed_deadline_days'] ?? ''); ?>" class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-orange-500 focus:border-orange-500">
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-semibold text-gray-700" for="lead_nome_cliente">Nome do Cliente</label>
                <input type="text" id="lead_nome_cliente" name="lead_nome_cliente" value="<?php echo htmlspecialchars($formData['lead_nome_cliente'] ?? ''); ?>" class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-orange-500 focus:border-orange-500">
            </div>
            <div id="responsavel-wrapper" class="<?php echo $personType === 'Física' ? 'hidden' : ''; ?>">
                <label class="block text-sm font-semibold text-gray-700" for="lead_nome_responsavel">Nome do Responsável</label>
                <input type="text" id="lead_nome_responsavel" name="lead_nome_responsavel" value="<?php echo htmlspecialchars($formData['lead_nome_responsavel'] ?? ''); ?>" class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-orange-500 focus:border-orange-500" data-responsavel-field>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-semibold text-gray-700" for="lead_cpf_cnpj">CPF ou CNPJ</label>
                <input type="text" id="lead_cpf_cnpj" name="lead_cpf_cnpj" value="<?php echo htmlspecialchars($formData['lead_cpf_cnpj'] ?? ''); ?>" maxlength="18" class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-orange-500 focus:border-orange-500">
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700" for="lead_email">E-mail</label>
                <input type="email" id="lead_email" name="lead_email" value="<?php echo htmlspecialchars($formData['lead_email'] ?? ''); ?>" class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-orange-500 focus:border-orange-500">
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label class="block text-sm font-semibold text-gray-700" for="lead_telefone">Telefone</label>
                <input type="text" id="lead_telefone" name="lead_telefone" value="<?php echo htmlspecialchars($formData['lead_telefone'] ?? ''); ?>" maxlength="15" class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-orange-500 focus:border-orange-500">
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700" for="lead_cep">CEP</label>
                <input type="text" id="lead_cep" name="lead_cep" value="<?php echo htmlspecialchars($formData['lead_cep'] ?? ''); ?>" maxlength="9" class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-orange-500 focus:border-orange-500">
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700" for="lead_numero">Número</label>
                <input type="text" id="lead_numero" name="lead_numero" value="<?php echo htmlspecialchars($formData['lead_numero'] ?? ''); ?>" class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-orange-500 focus:border-orange-500">
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-semibold text-gray-700" for="lead_endereco">Endereço</label>
                <input type="text" id="lead_endereco" name="lead_endereco" value="<?php echo htmlspecialchars($formData['lead_endereco'] ?? ''); ?>" class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-orange-500 focus:border-orange-500">
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700" for="lead_complemento">Complemento</label>
                <input type="text" id="lead_complemento" name="lead_complemento" value="<?php echo htmlspecialchars($formData['lead_complemento'] ?? ''); ?>" class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-orange-500 focus:border-orange-500">
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label class="block text-sm font-semibold text-gray-700" for="lead_bairro">Bairro</label>
                <input type="text" id="lead_bairro" name="lead_bairro" value="<?php echo htmlspecialchars($formData['lead_bairro'] ?? ''); ?>" class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-orange-500 focus:border-orange-500">
            </div>
            <div data-city-autocomplete class="relative">
                <label class="block text-sm font-semibold text-gray-700" for="lead_cidade">Cidade</label>
                <input type="hidden" name="lead_city_validation_source" id="lead_city_validation_source" value="<?php echo htmlspecialchars($cityValidation); ?>">
                <input
                    type="text"
                    id="lead_cidade"
                    name="lead_cidade"
                    value="<?php echo htmlspecialchars($formData['lead_cidade'] ?? ''); ?>"
                    class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-orange-500 focus:border-orange-500"
                    autocomplete="off"
                    data-city-selected="<?php echo !empty($formData['lead_cidade']) ? '1' : '0'; ?>"
                    aria-haspopup="listbox"
                    aria-expanded="false"
                    aria-controls="lead_cidade-options"
                >
                <div id="lead_cidade-options" role="listbox" class="absolute z-10 w-full bg-white border border-gray-300 rounded-md shadow-lg mt-1 hidden max-h-56 overflow-auto"></div>
                <p id="lead_cidade-status" class="mt-1 text-xs text-gray-500 hidden" role="status" aria-live="polite"></p>
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700" for="lead_estado">Estado (UF)</label>
                <input type="text" id="lead_estado" name="lead_estado" value="<?php echo htmlspecialchars(strtoupper($formData['lead_estado'] ?? '')); ?>" maxlength="2" class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-orange-500 focus:border-orange-500 bg-gray-50 uppercase">
            </div>
        </div>

        <div
            id="subscription-services-wrapper"
            class="<?php echo $planType === 'Mensalista' ? '' : 'hidden'; ?> space-y-4"
            data-subscription-wrapper
        >
            <div class="flex items-center justify-between">
                <h3 class="text-lg font-semibold text-gray-700">Serviços Mensalistas</h3>
                <button type="button" class="inline-flex items-center px-3 py-2 rounded-md bg-orange-500 text-white text-sm font-semibold shadow-sm hover:bg-orange-600 focus:outline-none" data-add-service>
                    Adicionar serviço
                </button>
            </div>
            <div class="space-y-4" data-service-list>
                <?php if (!empty($subscriptionServices)): ?>
                    <?php foreach ($subscriptionServices as $index => $service): ?>
                        <div class="p-4 border border-gray-200 rounded-lg space-y-4" data-service-item>
                            <div class="flex items-start justify-between gap-4">
                                <div class="flex-1">
                                    <label class="block text-sm font-medium text-gray-700" for="service-product-<?php echo $index; ?>">Produto / Serviço</label>
                                    <select id="service-product-<?php echo $index; ?>" name="lead_subscription_services[<?php echo $index; ?>][productBudgetId]" class="mt-1 block w-full rounded-md border border-gray-300 shadow-sm focus:ring-orange-500 focus:border-orange-500 text-sm" data-service-select>
                                        <option value="">Selecione um produto</option>
                                        <?php foreach ($budgetProducts as $product): ?>
                                            <?php
                                                $productId = (int)($product['id'] ?? 0);
                                                $defaultValue = isset($product['valor_padrao']) ? number_format((float)$product['valor_padrao'], 2, '.', '') : '';
                                                $blockMinimum = !empty($product['bloquear_valor_minimo']);
                                                $serviceType = $product['servico_tipo'] ?? 'Nenhum';
                                            ?>
                                            <option
                                                value="<?php echo $productId; ?>"
                                                data-service-type="<?php echo htmlspecialchars($serviceType); ?>"
                                                data-service-default="<?php echo htmlspecialchars($defaultValue); ?>"
                                                data-block-minimum="<?php echo $blockMinimum ? '1' : '0'; ?>"
                                                <?php echo $productId === (int)($service['produto_orcamento_id'] ?? 0) ? 'selected' : ''; ?>
                                            >
                                                <?php echo htmlspecialchars($product['nome_categoria'] ?? 'Produto'); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <button type="button" class="text-sm text-red-600 hover:text-red-700 font-semibold" data-remove-service>Remover</button>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700" for="service-value-<?php echo $index; ?>">Valor padrão</label>
                                    <input
                                        type="text"
                                        id="service-value-<?php echo $index; ?>"
                                        name="lead_subscription_services[<?php echo $index; ?>][standardValue]"
                                        value="<?php echo htmlspecialchars($service['valor_padrao'] ?? ''); ?>"
                                        class="mt-1 block w-full rounded-md border border-gray-300 shadow-sm focus:ring-orange-500 focus:border-orange-500"
                                        data-service-value
                                    >
                                    <p class="mt-1 text-xs text-gray-500" data-minimum-hint></p>
                                </div>
                                <div class="flex items-end">
                                    <span class="inline-flex items-center px-3 py-1 rounded-full bg-gray-100 text-gray-700 text-xs font-semibold" data-service-type-badge>
                                        <?php echo htmlspecialchars($service['servico_tipo'] ?? 'Selecione um produto'); ?>
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
                            <select class="mt-1 block w-full rounded-md border border-gray-300 shadow-sm focus:ring-orange-500 focus:border-orange-500 text-sm" data-service-select data-name-template="lead_subscription_services[__index__][productBudgetId]" data-id-template="service-product-__index__">
                                <option value="">Selecione um produto</option>
                                <?php foreach ($budgetProducts as $product): ?>
                                    <?php
                                        $productId = (int)($product['id'] ?? 0);
                                        $defaultValue = isset($product['valor_padrao']) ? number_format((float)$product['valor_padrao'], 2, '.', '') : '';
                                        $blockMinimum = !empty($product['bloquear_valor_minimo']);
                                        $serviceType = $product['servico_tipo'] ?? 'Nenhum';
                                    ?>
                                    <option
                                        value="<?php echo $productId; ?>"
                                        data-service-type="<?php echo htmlspecialchars($serviceType); ?>"
                                        data-service-default="<?php echo htmlspecialchars($defaultValue); ?>"
                                        data-block-minimum="<?php echo $blockMinimum ? '1' : '0'; ?>"
                                    >
                                        <?php echo htmlspecialchars($product['nome_categoria'] ?? 'Produto'); ?>
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

        <div class="flex items-center justify-end space-x-3 pt-4 border-t border-gray-200">
            <a href="processos.php?action=view&id=<?php echo $processId; ?>" class="px-4 py-2 rounded-md border border-gray-300 text-sm font-semibold text-gray-700 hover:bg-gray-50">Cancelar</a>
            <button type="submit" class="px-4 py-2 rounded-md bg-orange-500 text-white text-sm font-semibold shadow-sm hover:bg-orange-600 focus:outline-none">
                Salvar e continuar
            </button>
        </div>
    </form>
</div>

<script src="assets/js/city-autocomplete.js"></script>
<script src="assets/js/service-conversion.js"></script>
