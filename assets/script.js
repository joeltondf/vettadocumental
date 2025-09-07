/**
 * @file Script principal para funcionalidades interativas das páginas de Processos e Financeiro.
 * @summary Organiza a lógica em módulos para o formulário de processo e a edição em linha da tabela financeira.
 */

/**
 * Ponto de entrada principal do script.
 * Aguarda o carregamento completo do DOM para inicializar os módulos.
 */
document.addEventListener('DOMContentLoaded', () => {
    // Inicializa a lógica para o formulário de Processos, se existir na página.
    ProcessForm.init();

    // Inicializa a lógica para a edição em linha da tabela do Financeiro, se existir.
    InlineEditor.init();
});

/**
 * @module ProcessForm
 * @description Agrupa toda a lógica interativa para o formulário de Processos (processo-form).
 */
const ProcessForm = {
    /**
     * Elementos do DOM frequentemente utilizados.
     */
    form: null,
    formaPagamentoSelect: null,
    parcelasWrapper: null,
    parcela1Fields: null,
    parcela2Fields: null,
    parcelaToggles: null,

    /**
     * Inicializa o módulo, configurando os event listeners se o formulário existir.
     */
    init() {
        this.form = document.getElementById('processo-form');
        if (!this.form) {
            return; // Encerra se o formulário principal não for encontrado.
        }

        this._setupSectionToggles();
        this._setupPaymentLogic();
        this._setupDocumentRepeaters();
    },

    /**
     * Configura a lógica para mostrar/esconder seções com base nas checkboxes de Categoria.
     * @private
     */
    _setupSectionToggles() {
        const sectionToggles = this.form.querySelectorAll('[data-section-toggle]');
        sectionToggles.forEach(toggle => {
            const section = document.getElementById(toggle.dataset.sectionToggle);
            if (section) {
                // Garante que o estado inicial (ex: em uma página de edição) seja respeitado.
                section.classList.toggle('hidden-section', !toggle.checked);

                toggle.addEventListener('change', () => {
                    section.classList.toggle('hidden-section', !toggle.checked);
                });
            }
        });
    },

    /**
     * Configura a lógica interativa para a seleção de forma de pagamento e parcelas.
     * @private
     */
    _setupPaymentLogic() {
        // Seleciona os elementos do DOM uma única vez.
        this.formaPagamentoSelect = document.getElementById('orcamento_forma_pagamento');
        this.parcelasWrapper = document.getElementById('parcelas-wrapper');
        this.parcela1Fields = document.getElementById('parcela-1-fields');
        this.parcela2Fields = document.getElementById('parcela-2-fields');
        this.parcelaToggles = document.querySelectorAll('input[name="orcamento_parcelas"]');

        if (!this.formaPagamentoSelect || !this.parcelasWrapper || !this.parcela1Fields || !this.parcela2Fields) {
            return; // Encerra se algum elemento essencial não for encontrado.
        }

        // Adiciona os listeners
        this.formaPagamentoSelect.addEventListener('change', this._updatePaymentView.bind(this));
        this.parcelaToggles.forEach(toggle => {
            toggle.addEventListener('change', this._updatePaymentView.bind(this));
        });

        // Chama a função uma vez no início para definir o estado visual correto.
        this._updatePaymentView();
    },

    /**
     * Atualiza a visibilidade dos campos de pagamento com base nas seleções do usuário.
     * @private
     */
    _updatePaymentView() {
        const paymentMethod = this.formaPagamentoSelect.value;
        const selectedParcelaRadio = document.querySelector('input[name="orcamento_parcelas"]:checked');
        const selectedParcela = selectedParcelaRadio ? selectedParcelaRadio.value : '1';

        // Esconde todos os campos relacionados para um estado limpo.
        this.parcelasWrapper.classList.add('hidden-section');
        this.parcela1Fields.classList.add('hidden-section');
        this.parcela2Fields.classList.add('hidden-section');

        if (paymentMethod === 'A vista') {
            this.parcelasWrapper.classList.remove('hidden-section');
            if (selectedParcela === '1') {
                this.parcela1Fields.classList.remove('hidden-section');
            } else {
                this.parcela2Fields.classList.remove('hidden-section');
            }
        } else if (paymentMethod === 'Faturado') {
            // "Faturado" usa os mesmos campos da primeira parcela (Valor e Data).
            this.parcela1Fields.classList.remove('hidden-section');
        }
    },

    /**
     * Configura os botões para adicionar dinamicamente campos de documento (repetidor).
     * @private
     */
    _setupDocumentRepeaters() {
        const addButtons = this.form.querySelectorAll('[data-action="add-document-item"]');
        addButtons.forEach(button => {
            button.addEventListener('click', () => {
                const containerId = button.dataset.container;
                const templateId = button.dataset.template;
                this._addDocumentItem(containerId, templateId);
            });
        });
    },

    /**
     * Clona um template de documento e o anexa ao container especificado.
     * @param {string} containerId - O ID do elemento que conterá os itens do documento.
     * @param {string} templateId - O ID do elemento `<template>` a ser clonado.
     * @private
     */
    _addDocumentItem(containerId, templateId) {
        const container = document.getElementById(containerId);
        const template = document.getElementById(templateId);

        if (!container || !template) {
            console.error('Container ou Template do repetidor não encontrado:', containerId, templateId);
            return;
        }

        // Garante um índice único para os novos campos, contando todos os itens existentes.
        const index = document.querySelectorAll('.document-item').length;
        const clone = template.content.cloneNode(true);

        // Atualiza o atributo 'name' para garantir o envio correto dos dados como um array.
        clone.querySelectorAll('[name*="__INDEX__"]').forEach(input => {
            input.name = input.name.replace('__INDEX__', index);
        });

        container.appendChild(clone);
    }
};

/**
 * @module InlineEditor
 * @description Gerencia a funcionalidade de edição em linha para tabelas financeiras.
 */
const InlineEditor = {
    // Constante para a URL da API de atualização.
    API_URL: '/financeiro.php?action=update_field',

    /**
     * Inicializa o módulo, configurando listeners para células e selects editáveis.
     */
    init() {
        this._setupEditableCells();
        this._setupEditableSelects();
    },

    /**
     * Envia os dados atualizados para o servidor via Fetch API.
     * @param {string} id - O ID do processo a ser atualizado.
     * @param {string} field - O nome do campo no banco de dados.
     * @param {string} value - O novo valor para o campo.
     * @returns {Promise<object>} - A promessa que resolve com os dados da resposta JSON.
     * @private
     */
    async _updateField(id, field, value) {
        const response = await fetch(this.API_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `id=${id}&field=${field}&value=${encodeURIComponent(value)}`
        });

        if (!response.ok) {
            const errorData = await response.json().catch(() => ({ message: 'Erro desconhecido do servidor.' }));
            throw new Error(errorData.message || 'A resposta do servidor não foi bem-sucedida.');
        }

        return response.json();
    },

    /**
     * Configura a lógica para células de tabela que se transformam em inputs.
     * @private
     */
    _setupEditableCells() {
        const editableCells = document.querySelectorAll('.editable');
        editableCells.forEach(cell => {
            const displaySpan = cell.querySelector('.editable-content');
            const editInput = cell.querySelector('.edit-input');

            if (!displaySpan || !editInput) return;

            // Mostra o input ao clicar no texto
            displaySpan.addEventListener('click', () => {
                displaySpan.classList.add('hidden');
                editInput.classList.remove('hidden');
                
                if (cell.dataset.valueType === 'currency') {
                    editInput.value = editInput.value.replace('R$', '').replace(/\./g, '').replace(',', '.').trim();
                }
                
                editInput.focus();
            });
            
            // Reverte para o modo de exibição ao pressionar 'Escape'
            editInput.addEventListener('keydown', (event) => {
                if (event.key === 'Escape') {
                    editInput.value = displaySpan.textContent; // Reverte para o valor original
                    displaySpan.classList.remove('hidden');
                    editInput.classList.add('hidden');
                }
            });

            // Salva o valor quando o input perde o foco
            editInput.addEventListener('blur', () => {
                const processId = cell.closest('tr').dataset.processId;
                const field = cell.dataset.field;
                let newValue = editInput.value;

                // --- Validação e formatação antes do envio ---
                if (cell.dataset.valueType === 'currency') {
                    newValue = newValue.replace(',', '.');
                    if (isNaN(parseFloat(newValue))) {
                        alert('Por favor, insira um valor numérico válido.');
                        return; // Não prossegue com o salvamento
                    }
                } else if (cell.dataset.valueType === 'date' && newValue) {
                     const dateObj = new Date(newValue + 'T00:00:00'); // Evita problemas de fuso
                    if (isNaN(dateObj.getTime())) {
                        alert('Por favor, insira uma data válida no formato AAAA-MM-DD.');
                        return;
                    }
                }
                
                this._updateField(processId, field, newValue)
                    .then(data => {
                        if (data.status === 'success') {
                            // --- Atualiza o texto de exibição com o valor formatado ---
                            if (cell.dataset.valueType === 'currency') {
                                displaySpan.textContent = parseFloat(newValue).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
                            } else if (cell.dataset.valueType === 'date' && newValue) {
                                const [year, month, day] = newValue.split('-');
                                displaySpan.textContent = `${day}/${month}/${year}`;
                            } else if (cell.dataset.valueType === 'date' && !newValue) {
                                 displaySpan.textContent = 'N/A';
                            } else {
                                displaySpan.textContent = newValue;
                            }
                        } else {
                            throw new Error(data.message || 'Falha ao atualizar o campo.');
                        }
                    })
                    .catch(error => {
                        alert(`Erro ao salvar: ${error.message}`);
                        editInput.value = displaySpan.textContent; // Reverte visualmente
                    })
                    .finally(() => {
                        // Sempre esconde o input e mostra o texto no final
                        displaySpan.classList.remove('hidden');
                        editInput.classList.add('hidden');
                    });
            });
        });
    },

    /**
     * Configura a lógica para selects editáveis (como forma de pagamento) na tabela.
     * @private
     */
    _setupEditableSelects() {
        const paymentSelects = document.querySelectorAll('.editable-select[data-field="forma_pagamento_id"]');
        paymentSelects.forEach(select => {
            select.addEventListener('change', () => {
                const processId = select.dataset.processId;
                const field = select.dataset.field;
                const value = select.value;

                select.classList.add('saving'); // Feedback visual: salvando

                this._updateField(processId, field, value)
                    .then(data => {
                        if (data.status === 'success') {
                            select.classList.remove('saving');
                            select.classList.add('success');
                            setTimeout(() => select.classList.remove('success'), 1500);
                        } else {
                            throw new Error(data.message || 'Ocorreu um erro na atualização.');
                        }
                    })
                    .catch(error => {
                        console.error('Erro ao atualizar forma de pagamento:', error);
                        alert(`Erro: ${error.message}`);
                        select.classList.remove('saving');
                        select.classList.add('error');
                        setTimeout(() => select.classList.remove('error'), 2000);
                        // Opcional: reverter a seleção para o valor original em caso de erro.
                    });
            });
        });
    }
};


// --- Início do código para o menu mobile ---

// Executa o script apenas quando o conteúdo da página estiver totalmente carregado.
document.addEventListener('DOMContentLoaded', function () {
    
    // Seleciona os elementos do menu no HTML
    const mobileMenuButton = document.getElementById('mobile-menu-button');
    const mobileMenu = document.getElementById('mobile-menu');
    const hamburgerIcon = document.getElementById('hamburger-icon');
    const closeIcon = document.getElementById('close-icon');

    // Verifica se o botão do menu realmente existe na página antes de adicionar o evento
    if (mobileMenuButton) {
        mobileMenuButton.addEventListener('click', function () {
            // Alterna a classe 'hidden' no painel do menu para mostrá-lo ou escondê-lo
            mobileMenu.classList.toggle('hidden');

            // Alterna a visibilidade dos ícones de hamburger e 'X'
            hamburgerIcon.classList.toggle('hidden');
            closeIcon.classList.toggle('hidden');

            // Atualiza o atributo aria-expanded para acessibilidade (bom para leitores de tela)
            const isExpanded = mobileMenuButton.getAttribute('aria-expanded') === 'true';
            mobileMenuButton.setAttribute('aria-expanded', !isExpanded);
        });
    }

});

// --- Fim do código para o menu mobile ---


