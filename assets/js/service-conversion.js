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
