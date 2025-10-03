(function () {
    'use strict';

    const parseCurrency = (value) => {
        if (value === null || value === undefined) {
            return null;
        }
        let normalized = String(value).trim();
        if (normalized === '') {
            return null;
        }
        normalized = normalized.replace(/[^0-9,.-]/g, '');
        if (normalized === '' || normalized === '-' || normalized === '.' || normalized === ',') {
            return null;
        }
        const commaPos = normalized.lastIndexOf(',');
        const dotPos = normalized.lastIndexOf('.');
        let decimalSeparator = null;
        if (commaPos > -1 && dotPos > -1) {
            decimalSeparator = commaPos > dotPos ? ',' : '.';
        } else if (commaPos > -1) {
            decimalSeparator = ',';
        } else if (dotPos > -1) {
            decimalSeparator = '.';
        }
        if (decimalSeparator !== null) {
            const thousandSeparator = decimalSeparator === ',' ? '.' : ',';
            normalized = normalized.split(thousandSeparator).join('');
            normalized = normalized.replace(decimalSeparator, '.');
        } else {
            normalized = normalized.replace(/[.,]/g, '');
        }
        const result = parseFloat(normalized);
        return Number.isNaN(result) ? null : result;
    };

    const formatCurrency = (value) => {
        if (value === null || value === undefined || Number.isNaN(value)) {
            return 'Não informado';
        }
        return value.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
    };

    const setupClientForm = (form) => {
        const responsavelWrapper = form.querySelector('#responsavel-wrapper');
        const personTypeInputs = form.querySelectorAll('[data-person-type]');
        const planSelect = form.querySelector('[data-plan-select]');
        const subscriptionWrapper = form.querySelector('[data-subscription-wrapper]');
        const addServiceButton = form.querySelector('[data-add-service]');
        const serviceList = form.querySelector('[data-service-list]');
        const serviceTemplate = form.querySelector('template[data-service-template]');

        const toggleResponsavel = () => {
            if (!responsavelWrapper) {
                return;
            }
            const selected = form.querySelector('[data-person-type]:checked');
            if (selected && selected.value === 'Física') {
                responsavelWrapper.classList.add('hidden');
            } else {
                responsavelWrapper.classList.remove('hidden');
            }
        };

        personTypeInputs.forEach((input) => {
            input.addEventListener('change', toggleResponsavel);
        });
        toggleResponsavel();

        const toggleSubscriptionVisibility = () => {
            if (!subscriptionWrapper || !planSelect) {
                return;
            }
            subscriptionWrapper.classList[planSelect.value === 'Mensalista' ? 'remove' : 'add']('hidden');
        };

        if (planSelect) {
            planSelect.addEventListener('change', toggleSubscriptionVisibility);
            toggleSubscriptionVisibility();
        }

        if (!serviceList) {
            return;
        }

        let serviceIndex = serviceList.querySelectorAll('[data-service-item]').length;

        const updateServiceItem = (item) => {
            if (!item) {
                return;
            }
            const select = item.querySelector('[data-service-select]');
            const valueInput = item.querySelector('[data-service-value]');
            const badge = item.querySelector('[data-service-type-badge]');
            const hint = item.querySelector('[data-minimum-hint]');

            const updateFromOption = () => {
                if (!select) {
                    return;
                }
                const option = select.options[select.selectedIndex];
                const serviceType = option ? option.getAttribute('data-service-type') : null;
                const defaultValue = option ? option.getAttribute('data-service-default') : null;
                const blockMinimum = option ? option.getAttribute('data-block-minimum') === '1' : false;

                if (badge) {
                    badge.textContent = serviceType || 'Selecione um produto';
                }

                if (valueInput && defaultValue && valueInput.value === '') {
                    valueInput.value = defaultValue;
                }

                if (hint) {
                    if (blockMinimum && defaultValue) {
                        hint.textContent = `Valor mínimo: R$ ${defaultValue.replace('.', ',')}`;
                        hint.classList.remove('hidden');
                    } else {
                        hint.textContent = '';
                        hint.classList.add('hidden');
                    }
                }
            };

            if (select) {
                select.addEventListener('change', updateFromOption);
                updateFromOption();
            }
        };

        if (addServiceButton && serviceTemplate) {
            addServiceButton.addEventListener('click', () => {
                const fragment = serviceTemplate.content.cloneNode(true);
                const newItem = fragment.querySelector('[data-service-item]');
                if (!newItem) {
                    return;
                }

                newItem.querySelectorAll('[data-name-template]').forEach((element) => {
                    const templateName = element.getAttribute('data-name-template');
                    if (templateName) {
                        element.name = templateName.replace(/__index__/g, String(serviceIndex));
                        element.removeAttribute('data-name-template');
                    }
                });

                newItem.querySelectorAll('[data-id-template]').forEach((element) => {
                    const templateId = element.getAttribute('data-id-template');
                    if (templateId) {
                        element.id = templateId.replace(/__index__/g, String(serviceIndex));
                        element.removeAttribute('data-id-template');
                    }
                });

                newItem.querySelectorAll('[data-label-template]').forEach((label) => {
                    const templateFor = label.getAttribute('data-label-template');
                    if (templateFor) {
                        label.setAttribute('for', templateFor.replace(/__index__/g, String(serviceIndex)));
                        label.removeAttribute('data-label-template');
                    }
                });

                serviceList.appendChild(fragment);
                serviceIndex += 1;
                const appendedItem = serviceList.querySelector('[data-service-item]:last-of-type');
                updateServiceItem(appendedItem);
            });
        }

        serviceList.addEventListener('click', (event) => {
            const trigger = event.target.closest('[data-remove-service]');
            if (!trigger) {
                return;
            }
            const item = trigger.closest('[data-service-item]');
            if (item) {
                item.remove();
            }
        });

        serviceList.querySelectorAll('[data-service-item]').forEach(updateServiceItem);
    };

    const setupDeadlineForm = (form) => {
        const typeInputs = form.querySelectorAll('[data-deadline-type]');
        const daysWrapper = form.querySelector('#deadline-days-wrapper');
        const dateWrapper = form.querySelector('#deadline-date-wrapper');

        const toggleDeadlineInputs = () => {
            const selected = form.querySelector('[data-deadline-type]:checked');
            const type = selected ? selected.value : 'dias';
            if (daysWrapper) {
                daysWrapper.classList[type === 'dias' ? 'remove' : 'add']('hidden');
            }
            if (dateWrapper) {
                dateWrapper.classList[type === 'data' ? 'remove' : 'add']('hidden');
            }
        };

        typeInputs.forEach((input) => input.addEventListener('change', toggleDeadlineInputs));
        toggleDeadlineInputs();
    };

    const setupPaymentForm = (form) => {
        const methodSelect = form.querySelector('[data-payment-method]');
        const totalInput = form.querySelector('[data-total-value]');
        const entryInput = form.querySelector('[data-entry-value]');
        const totalDisplay = form.querySelector('[data-total-display]');
        const balanceDisplay = form.querySelector('[data-balance-display]');
        const parcelasWrapper = form.querySelector('#parcelas-wrapper');
        const secondInstallmentWrapper = form.querySelector('#segunda-parcela-wrapper');
        const secondProofWrapper = form.querySelector('#segunda-comprovante-wrapper');

        const togglePaymentSections = () => {
            const method = methodSelect ? methodSelect.value : 'À vista';
            const shouldShowInstallments = method === 'Parcelado';
            if (parcelasWrapper) {
                parcelasWrapper.classList[shouldShowInstallments ? 'remove' : 'add']('hidden');
            }
            if (secondInstallmentWrapper) {
                secondInstallmentWrapper.classList[shouldShowInstallments ? 'remove' : 'add']('hidden');
            }
            if (secondProofWrapper) {
                secondProofWrapper.classList[shouldShowInstallments ? 'remove' : 'add']('hidden');
            }
        };

        const updateSummary = () => {
            const total = totalInput ? parseCurrency(totalInput.value) : null;
            const entry = entryInput ? parseCurrency(entryInput.value) : null;
            if (totalDisplay) {
                totalDisplay.textContent = total !== null ? formatCurrency(total) : 'Não informado';
            }
            if (balanceDisplay) {
                if (total !== null && entry !== null) {
                    const balance = total - entry;
                    balanceDisplay.textContent = formatCurrency(balance >= 0 ? balance : 0);
                } else {
                    balanceDisplay.textContent = '-';
                }
            }
        };

        if (methodSelect) {
            methodSelect.addEventListener('change', () => {
                togglePaymentSections();
                updateSummary();
            });
        }

        if (totalInput) {
            totalInput.addEventListener('input', updateSummary);
        }

        if (entryInput) {
            entryInput.addEventListener('input', updateSummary);
        }

        togglePaymentSections();
        updateSummary();
    };

    document.addEventListener('DOMContentLoaded', () => {
        const clientForm = document.querySelector('[data-conversion-step="client"]');
        if (clientForm) {
            setupClientForm(clientForm);
        }

        const deadlineForm = document.querySelector('[data-conversion-step="deadline"]');
        if (deadlineForm) {
            setupDeadlineForm(deadlineForm);
        }

        const paymentForm = document.querySelector('[data-conversion-step="payment"]');
        if (paymentForm) {
            setupPaymentForm(paymentForm);
        }
    });
})();
