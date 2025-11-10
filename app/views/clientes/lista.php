<?php 
// /app/views/clientes/lista.php

require_once __DIR__ . '/../layouts/header.php'; 

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$filters = isset($filters) && is_array($filters)
    ? $filters
    : [
        'busca_nome'   => $_GET['busca_nome'] ?? '',
        'tipo_pessoa'  => $_GET['tipo_pessoa'] ?? '',
        'tipo_servico' => $_GET['tipo_servico'] ?? '',
    ];

$formatClientPhone = static function (array $cliente): string {
    $rawPhone = $cliente['telefone'] ?? '';
    $ddiValue = $cliente['telefone_ddi'] ?? '';

    $digits = stripNonDigits((string) $rawPhone);
    $ddiDigits = stripNonDigits((string) $ddiValue);

    if ($digits === '') {
        return '';
    }

    if ($ddiDigits !== '' && strpos($digits, $ddiDigits) === 0 && strlen($digits) > strlen($ddiDigits)) {
        $digits = substr($digits, strlen($ddiDigits));
    } elseif ($ddiDigits === '' && strlen($digits) > 11) {
        $ddiDigits = substr($digits, 0, strlen($digits) - 11);
        $digits = substr($digits, -11);
    }

    try {
        $parts = extractPhoneParts($digits);
        $ddiToUse = $ddiDigits !== '' ? $ddiDigits : '55';

        return formatInternationalPhone($ddiToUse, $parts['ddd'] ?? '', $parts['phone'] ?? '');
    } catch (Throwable $exception) {
        return (string) $rawPhone;
    }
};

?>

    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold">Lista de Clientes</h1>
        <a href="clientes.php?action=create&return_to=clientes.php" class="bg-blue-600 text-white py-2 px-4 rounded-md hover:bg-blue-700">
            + Novo Cliente
        </a>
    </div>

    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
            <span class="block sm:inline"><?php echo $_SESSION['success_message']; ?></span>
        </div>
        <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>
    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
            <span class="block sm:inline"><?php echo $_SESSION['error_message']; ?></span>
        </div>
        <?php unset($_SESSION['error_message']); ?>
    <?php endif; ?>

    <div class="bg-white shadow-md rounded-lg p-6 mb-6">
        <form method="get" action="clientes.php" class="flex flex-col md:flex-row md:items-end gap-4">
            <div class="flex-1">
                <label for="busca_nome" class="block text-sm font-medium text-gray-700 mb-1">Buscar por nome:</label>
                <input
                    type="text"
                    id="busca_nome"
                    name="busca_nome"
                    placeholder="Digite o nome do cliente..."
                    value="<?php echo htmlspecialchars($filters['busca_nome'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                >
            </div>

            <div class="md:w-48">
                <label for="tipo_pessoa" class="block text-sm font-medium text-gray-700 mb-1">Tipo de Cliente:</label>
                <select
                    id="tipo_pessoa"
                    name="tipo_pessoa"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                >
                    <option value="" <?php echo ($filters['tipo_pessoa'] ?? '') === '' ? 'selected' : ''; ?>>Todos</option>
                    <option value="Física" <?php echo ($filters['tipo_pessoa'] ?? '') === 'Física' ? 'selected' : ''; ?>>Pessoa Física</option>
                    <option value="Jurídica" <?php echo ($filters['tipo_pessoa'] ?? '') === 'Jurídica' ? 'selected' : ''; ?>>Pessoa Jurídica</option>
                </select>
            </div>

            <div class="md:w-48">
                <label for="tipo_servico" class="block text-sm font-medium text-gray-700 mb-1">Tipo de Serviço:</label>
                <select
                    id="tipo_servico"
                    name="tipo_servico"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                >
                    <option value="" <?php echo ($filters['tipo_servico'] ?? '') === '' ? 'selected' : ''; ?>>Todos</option>
                    <option value="Assessoria" <?php echo ($filters['tipo_servico'] ?? '') === 'Assessoria' ? 'selected' : ''; ?>>Assessoria</option>
                    <option value="Balcão" <?php echo ($filters['tipo_servico'] ?? '') === 'Balcão' ? 'selected' : ''; ?>>Balcão</option>
                </select>
            </div>

            <div class="flex gap-3 md:ml-auto">
                <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 transition">Buscar</button>
                <a href="clientes.php" class="bg-gray-200 text-gray-700 px-4 py-2 rounded-md hover:bg-gray-300 transition">Limpar Filtros</a>
            </div>
        </form>
    </div>

    <div class="bg-white shadow-md rounded-lg overflow-hidden">
        <table class="min-w-full leading-normal">
            <thead>
                <tr>
                    <th class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                        Nome
                    </th>
                    <th class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                        CPF/CNPJ
                    </th>
                    <th class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                        Email
                    </th>
                    <th class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                        Telefone
                    </th>
                    <th class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                        Ações
                    </th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($clientes)): ?>
                    <tr>
                        <td colspan="5" class="px-5 py-5 border-b border-gray-200 bg-white text-sm text-center">
                            Nenhum cliente cadastrado.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($clientes as $cliente): ?>
                        <tr>
                            <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm">
                                <p class="text-gray-900 whitespace-no-wrap flex items-center">
                                    <?php echo htmlspecialchars($cliente['nome_cliente'] ?? ''); ?>
                                    <?php
                                        $tipoServicoCliente = trim((string) ($cliente['tipo_servico'] ?? ''));
                                        if ($tipoServicoCliente !== ''):
                                            $badgeClasses = $tipoServicoCliente === 'Balcão'
                                                ? 'bg-yellow-100 text-yellow-800'
                                                : 'bg-blue-100 text-blue-800';
                                    ?>
                                        <span class="ml-3 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold <?php echo $badgeClasses; ?>">
                                            <?php echo htmlspecialchars($tipoServicoCliente); ?>
                                        </span>
                                    <?php endif; ?>
                                </p>
                            </td>
                            <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm">
                                <p class="text-gray-900 whitespace-no-wrap"><?php echo htmlspecialchars($cliente['cpf_cnpj'] ?? ''); ?></p>
                            </td>
                            <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm">
                                <p class="text-gray-900 whitespace-no-wrap"><?php echo htmlspecialchars($cliente['email'] ?? ''); ?></p>
                            </td>
                            <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm">
                                <p class="text-gray-900 whitespace-no-wrap"><?php echo htmlspecialchars($formatClientPhone($cliente)); ?></p>
                            </td>
                            <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm">
                                <a href="clientes.php?action=edit&id=<?php echo $cliente['id']; ?>" class="text-indigo-600 hover:text-indigo-900 mr-4">Editar</a>
                                <a href="clientes.php?action=delete&id=<?php echo $cliente['id']; ?>" class="text-red-600 hover:text-red-900" onclick="return confirm('Tem certeza que deseja excluir este cliente?');">Excluir</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

<?php 
require_once __DIR__ . '/../layouts/footer.php'; 
?>