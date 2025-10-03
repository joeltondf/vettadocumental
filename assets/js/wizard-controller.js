(function () {
    'use strict';

    function parseCurrency(value) {
        if (value === null || value === undefined) {
            return null;
        }
        if (typeof value === 'number') {
            return value;
        }
        const cleaned = String(value).replace(/[^0-9,.-]/g, '').replace(/\.(?=\d{3})/g, '').replace(',', '.');
        if (cleaned === '') {
            return null;
        }
        const numberValue = Number(cleaned);
        return Number.isNaN(numberValue) ? null : numberValue;
    }

    function formatCurrency(value) {
        if (value === null || Number.isNaN(value)) {
            return '-';
        }
        return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(value);
    }

    function closestAncestor(element, selector) {
        return element ? element.closest(selector) : null;
    }

    function initCityAutocomplete(input) {
        if (!window.CityAutocomplete || !input) {
            return null;
        }
        const wrapper = closestAncestor(input, '[data-city-autocomplete]');
        const ufInput = document.getElementById('lead_estado');
        const listId = input.id ? `${input.id}-options` : 'city-options';
        const list = document.getElementById(listId) || (() => {
            const listbox = document.createElement('div');
            listbox.id = listId;
            listbox.setAttribute('role', 'listbox');
            listbox.className = 'absolute z-10 w-full bg-white border border-gray-300 rounded-md shadow-lg mt-1 hidden max-h-56 overflow-auto';
            wrapper.appendChild(listbox);
            return listbox;
        })();
        const statusElement = document.getElementById('lead_cidade-status');
        const hiddenSource = document.getElementById('lead_city_validation_source');

        const fetchCities = async (term, uf, signal) => {
            const params = new URLSearchParams({ term });
            if (uf) {
                params.append('uf', uf);
            }
            const response = await fetch(`buscar-cidades.php?${params.toString()}`, { signal });
            if (!response.ok) {
                throw new Error('Não foi possível consultar as cidades.');
            }
            const payload = await response.json();
            if (!payload.success) {
                throw new Error(payload.message || 'Não foi possível carregar as cidades.');
            }
            const cities = payload.cities || payload.data || payload.cidades || [];
            return cities.map((city) => ({
                cNome: city.name || city.cNome || '',
                cUF: city.state || city.cUF || '',
                cCod: city.code || city.cCod || '',
                nCodIBGE: city.ibgeCode || city.nCodIBGE || null,
            }));
        };

        const autocomplete = new CityAutocomplete({
            input,
            ufInput,
            list,
            statusElement,
            fetchCities,
            debounceDelay: 400,
            onSelect(city) {
                if (!city) {
                    return;
                }
                input.dataset.citySelected = '1';
                if (ufInput) {
                    ufInput.value = (city.cUF || '').toUpperCase();
                }
                if (hiddenSource) {
                    hiddenSource.value = 'api';
                }
            },
            onClear() {
                input.dataset.citySelected = '0';
                if (hiddenSource) {
                    hiddenSource.value = 'database';
                }
                if (ufInput) {
                    ufInput.value = '';
                }
            }
        });

        autocomplete.init();

        input.addEventListener('input', () => {
            input.dataset.citySelected = '0';
            if (hiddenSource) {
                hiddenSource.value = 'database';
            }
        });

        return autocomplete;
    }

    function collectServiceItems(container) {
        return Array.from(container.querySelectorAll('[data-service-item]'));
    }

    function renumberServices(container) {
        collectServiceItems(container).forEach((item, index) => {
            item.querySelectorAll('label[data-label-template]').forEach((label) => {
                const template = label.dataset.labelTemplate;
                if (template) {
                    label.htmlFor = template.replace(/__index__/g, String(index));
                }
            });

            item.querySelectorAll('select, input').forEach((field) => {
                const nameTemplate = field.dataset.nameTemplate;
                if (nameTemplate) {
                    field.name = nameTemplate.replace(/__index__/g, String(index));
                } else if (field.name) {
                    const updatedName = field.name.replace(/lead_subscription_services\[[0-9]+\]/, `lead_subscription_services[${index}]`);
                    field.name = updatedName;
                }

                const idTemplate = field.dataset.idTemplate;
                if (idTemplate) {
                    field.id = idTemplate.replace(/__index__/g, String(index));
                } else if (field.id) {
                    field.id = field.id.replace(/-(\d+)$/, `-${index}`);
                }
            });
        });
    }

    function updateServiceDetails(item, option) {
        const badge = item.querySelector('[data-service-type-badge]');
        const valueInput = item.querySelector('[data-service-value]');
        const minimumHint = item.querySelector('[data-minimum-hint]');
        if (!option || !valueInput) {
            if (badge) {
                badge.textContent = 'Selecione um produto';
            }
            if (minimumHint) {
                minimumHint.textContent = '';
            }
            return;
        }
        const serviceType = option.dataset.servicoTipo || 'Nenhum';
        const defaultValue = option.dataset.valorPadrao || '';
        const requiresMinimum = option.dataset.bloquearMinimo === '1';

        if (badge) {
            badge.textContent = serviceType;
        }

        if (!valueInput.value && defaultValue) {
            valueInput.value = defaultValue;
        }

        if (minimumHint) {
            minimumHint.textContent = requiresMinimum && defaultValue
                ? `Valor mínimo permitido: R$ ${Number(defaultValue).toLocaleString('pt-BR', { minimumFractionDigits: 2 })}`
                : '';
            minimumHint.dataset.minimumValue = defaultValue;
            minimumHint.dataset.requiresMinimum = requiresMinimum ? '1' : '0';
        }
    }

    function updateResponsavelVisibility(form, tipoPessoa) {
        const container = form.querySelector('[data-responsavel-container]');
        const input = form.querySelector('#lead_nome_responsavel');
        if (!container || !input) {
            return;
        }
        if (tipoPessoa === 'Física') {
            container.classList.add('hidden');
            input.removeAttribute('required');
        } else {
            container.classList.remove('hidden');
            input.setAttribute('required', 'required');
        }
    }

    function toggleSubscriptionSection(form, tipoAssessoria) {
        const wrapper = form.querySelector('[data-subscription-container]');
        if (!wrapper) {
            return;
        }
        if (tipoAssessoria === 'Mensalista') {
            wrapper.classList.remove('hidden');
        } else {
            wrapper.classList.add('hidden');
        }
    }

    function updateInstallments(form, method) {
        const installmentsField = form.querySelector('[data-wizard-installments]');
        const secondInstallment = form.querySelector('[data-second-installment]');
        const secondProof = form.querySelector('[data-second-proof]');
        if (!installmentsField) {
            return;
        }
        if (method === 'Parcelado') {
            installmentsField.value = Math.max(2, parseInt(installmentsField.value || '2', 10));
            if (secondInstallment) {
                secondInstallment.classList.remove('hidden');
            }
            if (secondProof) {
                secondProof.classList.remove('hidden');
            }
        } else {
            installmentsField.value = '1';
            if (secondInstallment) {
                secondInstallment.classList.add('hidden');
            }
            if (secondProof) {
                secondProof.classList.add('hidden');
            }
            const secondDate = form.querySelector('#data_pagamento_2');
            if (secondDate) {
                secondDate.value = '';
            }
            const secondFile = form.querySelector('#payment_proof_balance');
            if (secondFile) {
                secondFile.value = '';
            }
        }
    }

    function updateBalanceDisplay(form) {
        const totalInput = form.querySelector('[data-wizard-total]');
        const entryInput = form.querySelector('[data-entry-value]');
        const balanceDisplay = form.querySelector('[data-balance-display]');
        if (!totalInput || !entryInput || !balanceDisplay) {
            return;
        }
        const total = parseCurrency(totalInput.value);
        const entry = parseCurrency(entryInput.value);
        if (total === null || entry === null) {
            balanceDisplay.textContent = '-';
            return;
        }
        const balance = total - entry;
        balanceDisplay.textContent = formatCurrency(balance);
    }

    function validateSubscriptionServices(form) {
        const tipoAssessoria = form.querySelector('#lead_tipo_cliente');
        if (!tipoAssessoria || tipoAssessoria.value !== 'Mensalista') {
            return true;
        }
        const container = form.querySelector('[data-service-list]');
        if (!container) {
            return true;
        }
        const items = collectServiceItems(container);
        if (items.length === 0) {
            showFeedback(form, 'Adicione pelo menos um serviço mensalista.');
            return false;
        }
        for (const item of items) {
            const select = item.querySelector('[data-service-select]');
            const valueInput = item.querySelector('[data-service-value]');
            const hint = item.querySelector('[data-minimum-hint]');
            if (!select || !valueInput) {
                continue;
            }
            if (!select.value) {
                select.focus();
                showFeedback(form, 'Informe o produto do serviço mensalista.');
                return false;
            }
            if (!valueInput.value) {
                valueInput.focus();
                showFeedback(form, 'Informe o valor do serviço mensalista.');
                return false;
            }
            const requiresMinimum = hint && hint.dataset.requiresMinimum === '1';
            const minimumValue = hint && hint.dataset.minimumValue ? parseCurrency(hint.dataset.minimumValue) : null;
            const typedValue = parseCurrency(valueInput.value);
            if (requiresMinimum && minimumValue !== null && typedValue !== null && typedValue < minimumValue) {
                valueInput.focus();
                showFeedback(form, 'O valor informado está abaixo do mínimo permitido para o serviço mensalista.');
                return false;
            }
        }
        return true;
    }

    function showFeedback(form, message, type) {
        const feedback = form.querySelector('[data-wizard-feedback]');
        if (!feedback) {
            return;
        }
        feedback.textContent = message || '';
        if (!message) {
            feedback.className = 'text-sm text-gray-600';
        } else if (type === 'error') {
            feedback.className = 'text-sm text-red-600 font-medium';
        } else {
            feedback.className = 'text-sm text-green-600 font-medium';
        }
    }

    function clearFeedback(form) {
        showFeedback(form, '');
    }

    function getStepIdentifier(stepIndex, totalSteps) {
        if (stepIndex === 0) {
            return 'client';
        }
        if (stepIndex === 1 && totalSteps >= 3) {
            return 'deadline';
        }
        return null;
    }

    async function submitWizardStep(form, stepIndex, totalSteps) {
        const endpoint = form?.dataset?.stepEndpoint || '';
        const stepIdentifier = getStepIdentifier(stepIndex, totalSteps);

        if (!endpoint || !stepIdentifier) {
            return { success: true };
        }

        const formData = new FormData(form);
        formData.append('lead_conversion_step', stepIdentifier);

        try {
            const response = await fetch(endpoint, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            const payload = await response.json().catch(() => null);
            if (!payload) {
                return {
                    success: false,
                    message: 'Não foi possível interpretar a resposta do servidor.',
                    type: 'error',
                };
            }

            if (!response.ok || !payload.success) {
                return {
                    success: false,
                    message: payload.message || 'Não foi possível salvar a etapa.',
                    type: payload.alertType || 'error',
                };
            }

            return {
                success: true,
                message: payload.message || '',
                type: payload.alertType || 'success',
            };
        } catch (error) {
            return {
                success: false,
                message: 'Falha de comunicação com o servidor. Tente novamente.',
                type: 'error',
            };
        }
    }

    function validateVisibleFields(step) {
        const fields = Array.from(step.querySelectorAll('input, select, textarea'));
        for (const field of fields) {
            if (field.type === 'hidden' || field.disabled) {
                continue;
            }
            if (field.offsetParent === null) {
                continue;
            }
            if (!field.reportValidity()) {
                return false;
            }
        }
        return true;
    }

    function validateStep(form, stepIndex, steps) {
        const step = steps[stepIndex];
        if (!step) {
            return true;
        }
        clearFeedback(form);
        if (!validateVisibleFields(step)) {
            return false;
        }
        if (stepIndex === 0) {
            const cityInput = form.querySelector('#lead_cidade');
            const citySource = form.querySelector('#lead_city_validation_source');
            if (cityInput && citySource && citySource.value === 'api' && cityInput.dataset.citySelected !== '1') {
                cityInput.focus();
                showFeedback(form, 'Selecione uma cidade sugerida pela busca.');
                return false;
            }
            if (!validateSubscriptionServices(form)) {
                return false;
            }
        }
        if (stepIndex === 2) {
            const total = parseCurrency(form.querySelector('[data-wizard-total]')?.value);
            const entry = parseCurrency(form.querySelector('[data-entry-value]')?.value);
            const method = form.querySelector('#forma_cobranca')?.value;
            if (entry === null || entry <= 0) {
                showFeedback(form, 'Informe o valor pago ou de entrada.', 'error');
                return false;
            }
            if (total !== null && entry > total) {
                showFeedback(form, 'O valor de entrada não pode ser maior que o total.', 'error');
                return false;
            }
            if (method === 'Parcelado' && total !== null && entry >= total) {
                showFeedback(form, 'Para parcelamentos o valor de entrada deve ser menor que o total.', 'error');
                return false;
            }
        }
        return true;
    }

    function updateStepState(stepperButtons, steps, prevButton, nextButton, submitButton, index) {
        steps.forEach((step, stepIndex) => {
            if (stepIndex === index) {
                step.classList.remove('hidden');
            } else {
                step.classList.add('hidden');
            }
        });
        stepperButtons.forEach((button, buttonIndex) => {
            if (buttonIndex === index) {
                button.classList.remove('bg-gray-200', 'text-gray-600');
                button.classList.add('bg-orange-500', 'text-white');
            } else {
                button.classList.add('bg-gray-200', 'text-gray-600');
                button.classList.remove('bg-orange-500', 'text-white');
            }
        });
        if (prevButton) {
            prevButton.disabled = index === 0;
        }
        if (nextButton) {
            nextButton.classList.toggle('hidden', index === steps.length - 1);
        }
        if (submitButton) {
            submitButton.classList.toggle('hidden', index !== steps.length - 1);
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        const wizards = document.querySelectorAll('[data-lead-conversion-wizard]');
        if (!wizards.length) {
            return;
        }

        wizards.forEach((wizard) => {
            const form = wizard.querySelector('[data-wizard-form]');
            if (!form) {
                return;
            }
            const steps = Array.from(form.querySelectorAll('[data-step]'));
            const stepperButtons = Array.from(wizard.querySelectorAll('[data-stepper-button]'));
            const prevButton = form.querySelector('[data-wizard-prev]');
            const nextButton = form.querySelector('[data-wizard-next]');
            const submitButton = form.querySelector('[data-wizard-submit]');
            const tipoPessoaInputs = form.querySelectorAll('input[name="lead_tipo_pessoa"]');
            const tipoClienteSelect = form.querySelector('#lead_tipo_cliente');
            const paymentMethodSelect = form.querySelector('#forma_cobranca');
            const entryInput = form.querySelector('[data-entry-value]');
            const serviceList = form.querySelector('[data-service-list]');
            const serviceTemplate = form.querySelector('[data-service-template]');
            const addServiceButton = form.querySelector('[data-add-service]');
            const cityInput = form.querySelector('#lead_cidade');

            let currentStep = 0;
            let isProcessingStep = false;
            const totalSteps = steps.length;

            updateStepState(stepperButtons, steps, prevButton, nextButton, submitButton, currentStep);

            if (cityInput) {
                initCityAutocomplete(cityInput);
            }

            tipoPessoaInputs.forEach((input) => {
                input.addEventListener('change', (event) => {
                    updateResponsavelVisibility(form, event.target.value);
                });
            });
            updateResponsavelVisibility(form, form.querySelector('input[name="lead_tipo_pessoa"]:checked')?.value || 'Jurídica');

            if (tipoClienteSelect) {
                tipoClienteSelect.addEventListener('change', (event) => {
                    toggleSubscriptionSection(form, event.target.value);
                });
                toggleSubscriptionSection(form, tipoClienteSelect.value);
            }

            if (paymentMethodSelect) {
                paymentMethodSelect.addEventListener('change', (event) => {
                    updateInstallments(form, event.target.value);
                    updateBalanceDisplay(form);
                });
                updateInstallments(form, paymentMethodSelect.value);
            }

            if (entryInput) {
                entryInput.addEventListener('input', () => updateBalanceDisplay(form));
            }
            updateBalanceDisplay(form);

            if (serviceList && serviceTemplate) {
                serviceList.querySelectorAll('select[data-service-select]').forEach((select) => {
                    updateServiceDetails(closestAncestor(select, '[data-service-item]'), select.options[select.selectedIndex]);
                });
                serviceList.addEventListener('change', (event) => {
                    if (event.target.matches('[data-service-select]')) {
                        updateServiceDetails(closestAncestor(event.target, '[data-service-item]'), event.target.options[event.target.selectedIndex]);
                    }
                });
                serviceList.addEventListener('click', (event) => {
                    if (event.target.matches('[data-remove-service]')) {
                        const item = closestAncestor(event.target, '[data-service-item]');
                        if (item) {
                            item.remove();
                            renumberServices(serviceList);
                        }
                    }
                });
                if (addServiceButton) {
                    addServiceButton.addEventListener('click', () => {
                        const templateSource = serviceTemplate.content || serviceTemplate;
                        const templateRoot = templateSource.firstElementChild;
                        if (!templateRoot) {
                            return;
                        }
                        const newItem = templateRoot.cloneNode(true);
                        serviceList.appendChild(newItem);
                        renumberServices(serviceList);
                        updateServiceDetails(newItem, newItem.querySelector('[data-service-select]')?.selectedOptions?.[0]);
                    });
                }
            }

            if (prevButton) {
                prevButton.addEventListener('click', () => {
                    if (currentStep > 0) {
                        currentStep -= 1;
                        clearFeedback(form);
                        updateStepState(stepperButtons, steps, prevButton, nextButton, submitButton, currentStep);
                    }
                });
            }

            if (nextButton) {
                nextButton.addEventListener('click', async () => {
                    if (isProcessingStep) {
                        return;
                    }
                    if (!validateStep(form, currentStep, steps)) {
                        showFeedback(form, form.querySelector('[data-wizard-feedback]')?.textContent || 'Corrija os campos destacados.', 'error');
                        return;
                    }
                    if (currentStep >= totalSteps - 1) {
                        return;
                    }

                    isProcessingStep = true;
                    nextButton.disabled = true;
                    const resultadoEtapa = await submitWizardStep(form, currentStep, totalSteps);
                    nextButton.disabled = false;
                    isProcessingStep = false;

                    if (!resultadoEtapa.success) {
                        showFeedback(form, resultadoEtapa.message || 'Não foi possível salvar a etapa.', resultadoEtapa.type || 'error');
                        return;
                    }

                    currentStep += 1;
                    if (resultadoEtapa.message) {
                        showFeedback(form, resultadoEtapa.message, resultadoEtapa.type || 'success');
                    } else {
                        clearFeedback(form);
                    }
                    updateStepState(stepperButtons, steps, prevButton, nextButton, submitButton, currentStep);
                });
            }

            stepperButtons.forEach((button, index) => {
                button.addEventListener('click', async () => {
                    if (index === currentStep) {
                        return;
                    }
                    if (isProcessingStep) {
                        return;
                    }
                    const direction = index > currentStep ? 1 : -1;
                    let targetIndex = currentStep;
                    let ultimoResultado = null;
                    while (targetIndex !== index) {
                        if (direction > 0 && !validateStep(form, targetIndex, steps)) {
                            showFeedback(form, 'Finalize o passo atual antes de avançar.', 'error');
                            return;
                        }
                        if (direction > 0) {
                            isProcessingStep = true;
                            if (nextButton) {
                                nextButton.disabled = true;
                            }
                            const resultadoEtapa = await submitWizardStep(form, targetIndex, totalSteps);
                            if (nextButton) {
                                nextButton.disabled = false;
                            }
                            isProcessingStep = false;
                            if (!resultadoEtapa.success) {
                                showFeedback(form, resultadoEtapa.message || 'Não foi possível salvar a etapa.', resultadoEtapa.type || 'error');
                                return;
                            }
                            ultimoResultado = resultadoEtapa;
                        }
                        targetIndex += direction;
                    }
                    currentStep = index;
                    if (ultimoResultado && ultimoResultado.message) {
                        showFeedback(form, ultimoResultado.message, ultimoResultado.type || 'success');
                    } else {
                        clearFeedback(form);
                    }
                    updateStepState(stepperButtons, steps, prevButton, nextButton, submitButton, currentStep);
                });
            });

            form.addEventListener('submit', (event) => {
                if (!validateStep(form, currentStep, steps)) {
                    event.preventDefault();
                    showFeedback(form, 'Revise os campos obrigatórios antes de concluir.', 'error');
                }
            });
        });
    });
})();
