<?php 
// /app/views/clientes/form.php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
$isEdit = isset($cliente['id']);
// A variável $return_url agora é definida corretamente pelo ClientesController.php

// Recupera dados do formulário em caso de erro na criação
$formData = $_SESSION['form_data'] ?? [];

// Preenche as variáveis para os campos do formulário
$nome_cliente = $isEdit ? ($cliente['nome_cliente'] ?? '') : ($formData['nome_cliente'] ?? '');
$nome_responsavel = $isEdit ? ($cliente['nome_responsavel'] ?? '') : ($formData['nome_responsavel'] ?? '');
$cpf_cnpj = $isEdit ? ($cliente['cpf_cnpj'] ?? '') : ($formData['cpf_cnpj'] ?? '');
$email = $isEdit ? ($cliente['email'] ?? '') : ($formData['email'] ?? '');
$telefone = $isEdit ? ($cliente['telefone'] ?? '') : ($formData['telefone'] ?? '');
$endereco = $isEdit ? ($cliente['endereco'] ?? '') : ($formData['endereco'] ?? '');
$cep = $isEdit ? ($cliente['cep'] ?? '') : ($formData['cep'] ?? '');
$tipo_assessoria = $isEdit ? ($cliente['tipo_assessoria'] ?? '') : ($formData['tipo_assessoria'] ?? '');
$tipo_pessoa = $isEdit ? ($cliente['tipo_pessoa'] ?? 'Jurídica') : ($formData['tipo_pessoa'] ?? 'Jurídica');

require_once __DIR__ . '/../layouts/header.php';
?>

<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-bold text-gray-800"><?php echo $isEdit ? 'Editar Cliente' : 'Cadastrar Novo Cliente'; ?></h1>
    <a href="<?php echo htmlspecialchars($return_url); ?>" class="bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded-lg shadow-md transition duration-200 ease-in-out">
        &larr; Voltar
    </a>
</div>

<?php if (isset($_SESSION['error_message'])): ?>
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
        <strong class="font-bold">Erro!</strong>
        <span class="block sm:inline"><?php echo $_SESSION['error_message']; ?></span>
    </div>
    <?php unset($_SESSION['error_message']); ?>
<?php endif; ?>

<form action="clientes.php" method="POST" class="bg-white p-6 rounded-lg shadow-lg border border-gray-200" autocomplete="off">
    <!-- Campo invisível para enganar o autocomplete -->
    <input type="text" name="fake_username" style="position: absolute; top: -5000px;" tabindex="-1" autocomplete="username">
    <input type="password" name="fake_password" style="position: absolute; top: -5000px;" tabindex="-1" autocomplete="new-password">
    
    <input type="hidden" name="return_to" value="<?php echo htmlspecialchars($return_url); ?>">
    <?php if (isset($_GET['prospeccao_id'])): ?>
        <input type="hidden" name="continue_prospeccao_id" value="<?php echo htmlspecialchars($_GET['prospeccao_id']); ?>">
        <input type="hidden" name="continue_nome_servico" value="<?php echo htmlspecialchars($_GET['nome_servico']); ?>">
        <input type="hidden" name="continue_valor_inicial" value="<?php echo htmlspecialchars($_GET['valor_inicial']); ?>">
    <?php endif; ?>
    <input type="hidden" name="action" value="<?php echo $isEdit ? 'update' : 'store'; ?>">
    <?php if ($isEdit): ?>
        <input type="hidden" name="id" value="<?php echo $cliente['id']; ?>">
    <?php endif; ?>

    <div class="mb-6">
        <label class="block text-sm font-semibold text-gray-700 mb-2">Tipo de Cliente</label>
        <div class="flex items-center space-x-6">
            <label class="flex items-center cursor-pointer">
                <input type="radio" name="tipo_pessoa" value="Jurídica" class="h-4 w-4 text-blue-600 border-gray-300"  <?php echo ($tipo_pessoa === 'Jurídica') ? 'checked' : ''; ?>>
                <span class="ml-2 text-sm text-gray-700">Pessoa Jurídica</span>
            </label>
            <label class="flex items-center cursor-pointer">
                <input type="radio" name="tipo_pessoa" value="Física" class="h-4 w-4 text-blue-600 border-gray-300" <?php echo ($tipo_pessoa === 'Física') ? 'checked' : ''; ?>>
                <span class="ml-2 text-sm text-gray-700">Pessoa Física</span>
            </label>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div class="md:col-span-2">
            <label for="nome_cliente" id="label_nome_cliente" class="block text-sm font-semibold text-gray-700">Nome do Cliente / Empresa *</label>
            <input type="text" id="nome_cliente" name="nome_cliente" autocomplete="nope" value="<?php echo htmlspecialchars($nome_cliente); ?>" class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500" required>
        </div>

        <div id="container_nome_responsavel">
            <label for="nome_responsavel" class="block text-sm font-semibold text-gray-700">Nome do Responsável</label>
            <input type="text" id="nome_responsavel" name="nome_responsavel" autocomplete="nope" value="<?php echo htmlspecialchars($nome_responsavel); ?>" class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
        </div>

        <div>
            <label for="cpf_cnpj" id="label_cpf_cnpj" class="block text-sm font-semibold text-gray-700">CPF/CNPJ</label>
            <input type="text" id="cpf_cnpj" name="cpf_cnpj" autocomplete="nope" value="<?php echo htmlspecialchars($cpf_cnpj); ?>" class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
        </div>

        <div>
            <label for="email" class="block text-sm font-semibold text-gray-700">E-mail</label>
            <input type="email" id="email" name="email" autocomplete="nope" value="<?php echo htmlspecialchars($email); ?>" class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
        </div>

        <div>
            <label for="telefone" class="block text-sm font-semibold text-gray-700">Telefone</label>
            <input type="text" id="telefone" name="telefone" autocomplete="nope" value="<?php echo htmlspecialchars($telefone); ?>" class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500" maxlength="15">
        </div>

        <div class="md:col-span-2">
            <label for="endereco" class="block text-sm font-semibold text-gray-700">Endereço</label>
            <textarea id="endereco" name="endereco" autocomplete="nope" rows="3" class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"><?php echo htmlspecialchars($endereco); ?></textarea>
        </div>

        <div>
            <label for="cep" class="block text-sm font-semibold text-gray-700">CEP</label>
            <input type="text" id="cep" name="cep" autocomplete="nope" value="<?php echo htmlspecialchars($cep); ?>" readonly onfocus="this.removeAttribute('readonly');" class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500" maxlength="9">
        </div>

        <div>
            <label for="tipo_assessoria" class="block text-sm font-semibold text-gray-700">Tipo de Assessoria</label>
            <select id="tipo_assessoria" name="tipo_assessoria" class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                <option value="" <?php echo ($tipo_assessoria == '') ? 'selected' : ''; ?>>Não definido</option>
                <option value="À vista" <?php echo ($tipo_assessoria == 'À vista') ? 'selected' : ''; ?>>À vista</option>
                <option value="Mensalista" <?php echo ($tipo_assessoria == 'Mensalista') ? 'selected' : ''; ?>>Mensalista</option>
            </select>
        </div>
    </div>
    
    <?php if (!$isEdit || ($isEdit && empty($cliente['user_id']))): ?>
        <div class="md:col-span-2 mt-4 pt-4 border-t border-gray-200">
            <label class="inline-flex items-center cursor-pointer">
                <input type="checkbox" id="criar_login_checkbox" name="criar_login" value="1" class="h-5 w-5 rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                <span class="ml-3 text-sm font-semibold text-gray-700">Criar acesso de login para este cliente?</span>
            </label>
        </div>
    <?php endif; ?>
    
    <div id="login-fields-container" class="hidden md:col-span-2 grid grid-cols-1 md:grid-cols-2 gap-6 mt-4">
        <div>
            <label for="login_email" class="block text-sm font-medium text-gray-700">Email de Acesso *</label>
            <input type="email" id="login_email" name="login_email" autocomplete="new-email" class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500" placeholder="Email que será usado para o login">
        </div>
        <div>
            <label for="login_senha" class="block text-sm font-medium text-gray-700">Senha de Acesso *</label>
            <input type="password" id="login_senha" name="login_senha" autocomplete="new-password" class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500" placeholder="Defina uma senha segura">
        </div>
    </div>
    
    <div class="flex items-center justify-end mt-6 pt-5 border-t border-gray-200">
        <a href="<?php echo htmlspecialchars($return_url); ?>" class="bg-white py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 mr-3">Cancelar</a>
        <button type="submit" class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
            <?php echo $isEdit ? 'Atualizar Cliente' : 'Salvar Cliente'; ?>
        </button>
    </div>
</form>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const radiosTipoPessoa = document.querySelectorAll('input[name="tipo_pessoa"]');
    const labelNomeCliente = document.getElementById('label_nome_cliente');
    const labelCpfCnpj = document.getElementById('label_cpf_cnpj');
    const containerNomeResponsavel = document.getElementById('container_nome_responsavel');
    const inputNomeResponsavel = document.getElementById('nome_responsavel');

    // Função para validar CPF
    function validarCpf(cpf) {
        cpf = cpf.replace(/\D/g, '');
        if (cpf.length !== 11) return false;
        
        // Verifica se todos os números são iguais
        if (/^(\d)\1{10}$/.test(cpf)) return false;
        
        // Validação do primeiro dígito
        let soma = 0;
        for (let i = 0; i < 9; i++) {
            soma += parseInt(cpf.charAt(i)) * (10 - i);
        }
        let resto = 11 - (soma % 11);
        let digito1 = resto === 10 || resto === 11 ? 0 : resto;
        if (digito1 !== parseInt(cpf.charAt(9))) return false;
        
        // Validação do segundo dígito
        soma = 0;
        for (let i = 0; i < 10; i++) {
            soma += parseInt(cpf.charAt(i)) * (11 - i);
        }
        resto = 11 - (soma % 11);
        let digito2 = resto === 10 || resto === 11 ? 0 : resto;
        if (digito2 !== parseInt(cpf.charAt(10))) return false;
        
        return true;
    }

    // Função para validar CNPJ
    function validarCnpj(cnpj) {
        cnpj = cnpj.replace(/\D/g, '');
        if (cnpj.length !== 14) return false;
        
        // Verifica se todos os números são iguais
        if (/^(\d)\1{13}$/.test(cnpj)) return false;
        
        // Validação do primeiro dígito
        let tamanho = cnpj.length - 2;
        let numeros = cnpj.substring(0, tamanho);
        let digitos = cnpj.substring(tamanho);
        let soma = 0;
        let pos = tamanho - 7;
        
        for (let i = tamanho; i >= 1; i--) {
            soma += numeros.charAt(tamanho - i) * pos--;
            if (pos < 2) pos = 9;
        }
        
        let resultado = soma % 11 < 2 ? 0 : 11 - soma % 11;
        if (resultado !== parseInt(digitos.charAt(0))) return false;
        
        // Validação do segundo dígito
        tamanho = tamanho + 1;
        numeros = cnpj.substring(0, tamanho);
        soma = 0;
        pos = tamanho - 7;
        
        for (let i = tamanho; i >= 1; i--) {
            soma += numeros.charAt(tamanho - i) * pos--;
            if (pos < 2) pos = 9;
        }
        
        resultado = soma % 11 < 2 ? 0 : 11 - soma % 11;
        if (resultado !== parseInt(digitos.charAt(1))) return false;
        
        return true;
    }

    // Adiciona validação no envio do formulário
    const form = document.querySelector('form');
    if (form) {
        form.addEventListener('submit', function(e) {
            const tipoSelecionado = document.querySelector('input[name="tipo_pessoa"]:checked').value;
            const valorCpfCnpj = cpfCnpjInput.value;
            
            if (valorCpfCnpj) {
                let valido = false;
                if (tipoSelecionado === 'Física') {
                    valido = validarCpf(valorCpfCnpj);
                    if (!valido) {
                        e.preventDefault();
                        alert('CPF inválido! Por favor, verifique o número digitado.');
                        cpfCnpjInput.focus();
                    }
                } else {
                    valido = validarCnpj(valorCpfCnpj);
                    if (!valido) {
                        e.preventDefault();
                        alert('CNPJ inválido! Por favor, verifique o número digitado.');
                        cpfCnpjInput.focus();
                    }
                }
            }
        });
    }

    // Função específica para máscara de CPF
    function aplicarMascaraCpf(value) {
        // Remove tudo que não for número
        value = value.replace(/\D/g, '');
        
        // Limita a 11 dígitos
        value = value.substring(0, 11);
        
        // Aplica a máscara de CPF: 000.000.000-00
        if (value.length > 0) {
            value = value.replace(/^(\d{1,3})/, '$1');
            if (value.length > 3) {
                value = value.replace(/^(\d{3})(\d{1,3})/, '$1.$2');
            }
            if (value.length > 6) {
                value = value.replace(/^(\d{3})\.(\d{3})(\d{1,3})/, '$1.$2.$3');
            }
            if (value.length > 9) {
                value = value.replace(/^(\d{3})\.(\d{3})\.(\d{3})(\d{1,2})/, '$1.$2.$3-$4');
            }
        }
        
        return value;
    }

    // Função específica para máscara de CNPJ
    function aplicarMascaraCnpj(value) {
        // Remove tudo que não for número
        value = value.replace(/\D/g, '');
        
        // Limita a 14 dígitos
        value = value.substring(0, 14);
        
        // Aplica a máscara de CNPJ: 00.000.000/0000-00
        if (value.length > 0) {
            value = value.replace(/^(\d{1,2})/, '$1');
            if (value.length > 2) {
                value = value.replace(/^(\d{2})(\d{1,3})/, '$1.$2');
            }
            if (value.length > 5) {
                value = value.replace(/^(\d{2})\.(\d{3})(\d{1,3})/, '$1.$2.$3');
            }
            if (value.length > 8) {
                value = value.replace(/^(\d{2})\.(\d{3})\.(\d{3})(\d{1,4})/, '$1.$2.$3/$4');
            }
            if (value.length > 12) {
                value = value.replace(/^(\d{2})\.(\d{3})\.(\d{3})\/(\d{4})(\d{1,2})/, '$1.$2.$3/$4-$5');
            }
        }
        
        return value;
    }

    // Função para máscara de telefone
    function aplicarMascaraTelefone(value) {
        value = value.replace(/\D/g, '');
        
        if (value.length <= 10) {
            // Telefone fixo: (00) 0000-0000
            value = value.replace(/^(\d{2})(\d)/, '($1) $2');
            value = value.replace(/(\d{4})(\d)/, '$1-$2');
        } else {
            // Celular: (00) 00000-0000
            value = value.substring(0, 11);
            value = value.replace(/^(\d{2})(\d)/, '($1) $2');
            value = value.replace(/(\d{5})(\d)/, '$1-$2');
        }
        
        return value;
    }

    // Função para máscara de CEP
    function aplicarMascaraCep(value) {
        value = value.replace(/\D/g, '');
        value = value.substring(0, 8);
        value = value.replace(/(\d{5})(\d)/, '$1-$2');
        return value;
    }

    // Adiciona evento de input para CPF/CNPJ
    const cpfCnpjInput = document.getElementById('cpf_cnpj');
    if (cpfCnpjInput) {
        cpfCnpjInput.addEventListener('input', function(e) {
            const tipoSelecionado = document.querySelector('input[name="tipo_pessoa"]:checked').value;
            
            if (tipoSelecionado === 'Física') {
                e.target.value = aplicarMascaraCpf(e.target.value);
            } else {
                e.target.value = aplicarMascaraCnpj(e.target.value);
            }
        });

        // Adiciona evento de paste para CPF/CNPJ
        cpfCnpjInput.addEventListener('paste', function(e) {
            e.preventDefault();
            const pastedText = (e.clipboardData || window.clipboardData).getData('text');
            const tipoSelecionado = document.querySelector('input[name="tipo_pessoa"]:checked').value;
            
            if (tipoSelecionado === 'Física') {
                e.target.value = aplicarMascaraCpf(pastedText);
            } else {
                e.target.value = aplicarMascaraCnpj(pastedText);
            }
        });

        // Adiciona evento de keypress para bloquear caracteres não numéricos
        cpfCnpjInput.addEventListener('keypress', function(e) {
            // Permite backspace, delete, tab, escape, enter
            const specialKeys = [8, 9, 27, 13];
            if (specialKeys.indexOf(e.keyCode) !== -1) {
                return;
            }
            
            // Garante que é um número
            const char = String.fromCharCode(e.which);
            if (!/[0-9]/.test(char)) {
                e.preventDefault();
                return;
            }
            
            // Verifica o limite de caracteres baseado no tipo
            const tipoSelecionado = document.querySelector('input[name="tipo_pessoa"]:checked').value;
            const valorAtual = e.target.value.replace(/\D/g, '');
            
            if (tipoSelecionado === 'Física' && valorAtual.length >= 11) {
                e.preventDefault();
            } else if (tipoSelecionado === 'Jurídica' && valorAtual.length >= 14) {
                e.preventDefault();
            }
        });

        // Adiciona evento keydown para permitir teclas de navegação
        cpfCnpjInput.addEventListener('keydown', function(e) {
            // Permite: backspace, delete, tab, escape, enter, end, home, setas
            const allowedKeys = [8, 9, 27, 13, 35, 36, 37, 38, 39, 40, 46];
            if (allowedKeys.indexOf(e.keyCode) !== -1) {
                return;
            }
            
            // Permite Ctrl+A, Ctrl+C, Ctrl+V, Ctrl+X
            if ((e.ctrlKey || e.metaKey) && [65, 67, 86, 88].indexOf(e.keyCode) !== -1) {
                return;
            }
            
            // Bloqueia se não for número
            if ((e.shiftKey || (e.keyCode < 48 || e.keyCode > 57)) && (e.keyCode < 96 || e.keyCode > 105)) {
                e.preventDefault();
            }
        });
    }

    // Adiciona evento de input para Telefone
    const telefoneInput = document.getElementById('telefone');
    if (telefoneInput) {
        telefoneInput.addEventListener('input', function(e) {
            e.target.value = aplicarMascaraTelefone(e.target.value);
        });

        telefoneInput.addEventListener('paste', function(e) {
            setTimeout(() => {
                e.target.value = aplicarMascaraTelefone(e.target.value);
            }, 100);
        });
    }

    // Adiciona evento de input para CEP
    const cepInput = document.getElementById('cep');
    if (cepInput) {
        cepInput.addEventListener('input', function(e) {
            e.target.value = aplicarMascaraCep(e.target.value);
        });

        cepInput.addEventListener('paste', function(e) {
            setTimeout(() => {
                e.target.value = aplicarMascaraCep(e.target.value);
            }, 100);
        });
    }

    // Função para ajustar formulário baseado no tipo de pessoa
    function ajustarFormulario() {
        const tipoSelecionado = document.querySelector('input[name="tipo_pessoa"]:checked').value;
        const valorAtual = cpfCnpjInput.value.replace(/\D/g, '');

        if (tipoSelecionado === 'Física') {
            labelNomeCliente.textContent = 'Nome Completo *';
            labelCpfCnpj.textContent = 'CPF';
            containerNomeResponsavel.style.display = 'none';
            inputNomeResponsavel.removeAttribute('required');
            cpfCnpjInput.setAttribute('placeholder', '000.000.000-00');
            
            // Se o valor atual é maior que 11 dígitos (era um CNPJ), limpa o campo
            if (valorAtual.length > 11) {
                cpfCnpjInput.value = '';
            } else if (valorAtual.length > 0) {
                // Reaplica a máscara de CPF
                cpfCnpjInput.value = aplicarMascaraCpf(valorAtual);
            }
        } else { // Jurídica
            labelNomeCliente.textContent = 'Nome da Empresa *';
            labelCpfCnpj.textContent = 'CNPJ';
            containerNomeResponsavel.style.display = 'block';
            inputNomeResponsavel.setAttribute('required', 'required');
            cpfCnpjInput.setAttribute('placeholder', '00.000.000/0000-00');
            
            // Se tinha um CPF válido (11 dígitos), limpa o campo
            if (valorAtual.length > 0 && valorAtual.length <= 11) {
                cpfCnpjInput.value = '';
            } else if (valorAtual.length > 11) {
                // Reaplica a máscara de CNPJ
                cpfCnpjInput.value = aplicarMascaraCnpj(valorAtual);
            }
        }
    }
    
    radiosTipoPessoa.forEach(radio => {
        radio.addEventListener('change', ajustarFormulario);
    });
    
    // Executa a função na carga da página para ajustar o estado inicial
    ajustarFormulario();
    
    // Aplica máscara inicial se houver valor no campo (caso de edição)
    if (cpfCnpjInput && cpfCnpjInput.value) {
        const tipoSelecionado = document.querySelector('input[name="tipo_pessoa"]:checked').value;
        if (tipoSelecionado === 'Física') {
            cpfCnpjInput.value = aplicarMascaraCpf(cpfCnpjInput.value);
        } else {
            cpfCnpjInput.value = aplicarMascaraCnpj(cpfCnpjInput.value);
        }
    }

    // Lógica do checkbox de login
    const checkbox = document.getElementById('criar_login_checkbox');
    const container = document.getElementById('login-fields-container');
    if (checkbox) {
        checkbox.addEventListener('change', function() {
            const emailInput = document.getElementById('login_email');
            const senhaInput = document.getElementById('login_senha');
            
            if (this.checked) {
                container.classList.remove('hidden');
                emailInput.required = true;
                senhaInput.required = true;
            } else {
                container.classList.add('hidden');
                emailInput.required = false;
                senhaInput.required = false;
            }
        });
    }

    // Prevenir autocomplete de forma mais agressiva
    const allInputs = document.querySelectorAll('input[type="text"], input[type="email"], input[type="password"], textarea');
    allInputs.forEach(input => {
        // Define atributos adicionais para prevenir autocomplete
        input.setAttribute('autocomplete', 'nope');
        input.setAttribute('autocorrect', 'off');
        input.setAttribute('autocapitalize', 'off');
        input.setAttribute('spellcheck', 'false');
        
        // Adiciona um pequeno delay no focus para evitar preenchimento automático
        input.addEventListener('focus', function() {
            this.setAttribute('autocomplete', 'nope');
        });
    });

    // Limpa os campos no carregamento (útil para prevenir autocomplete do navegador)
    setTimeout(function() {
        if (!<?php echo $isEdit ? 'true' : 'false'; ?>) {
            // Só limpa se for um novo cadastro
            const form = document.querySelector('form');
            if (form && !form.dataset.loaded) {
                form.dataset.loaded = 'true';
            }
        }
    }, 100);
});
</script>