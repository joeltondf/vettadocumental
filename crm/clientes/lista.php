<?php
//  crm/clientes/lista.php 

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../app/core/auth_check.php';
require_once __DIR__ . '/../../app/models/Cliente.php';

$clienteModel = new Cliente($pdo);
$currentUserId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
$currentUserPerfil = $_SESSION['user_perfil'] ?? '';
$clientes = $clienteModel->getCrmProspects($currentUserId, $currentUserPerfil);

$pageTitle = "CRM - Lista de Leads";
require_once __DIR__ . '/../../app/views/layouts/header.php';
?>

<div class="max-w-1xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

    <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center mb-6 gap-4">
        <h1 class="text-3xl font-bold text-gray-800"><?php echo $pageTitle; ?></h1>
        <div class="flex flex-col sm:flex-row gap-3">
            <a href="<?php echo APP_URL; ?>/crm/clientes/importar.php" class="bg-green-600 text-white py-2 px-4 rounded-md shadow-md hover:bg-green-700 transition duration-300 flex items-center justify-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" /></svg>
                Importar Leads
            </a>
            <a href="<?php echo APP_URL; ?>/crm/clientes/novo.php" class="bg-blue-600 text-white py-2 px-4 rounded-md shadow-md hover:bg-blue-700 transition duration-300 flex items-center justify-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" /></svg>
                Novo Lead
            </a>
        </div>
    </div>

    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert">
            <p><?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?></p>
        </div>
    <?php endif; ?>
    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
            <p><?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?></p>
        </div>
    <?php endif; ?>
    <?php if (isset($_SESSION['import_summary'])): ?>
        <?php $summary = $_SESSION['import_summary']; unset($_SESSION['import_summary']); ?>
        <div class="bg-blue-50 border-l-4 border-blue-500 text-blue-700 p-4 mb-6">
            <p class="font-semibold">Resumo da importação</p>
            <ul class="mt-2 text-sm list-disc list-inside space-y-1">
                <li>Leads criados: <?php echo (int)($summary['created'] ?? 0); ?></li>
                <li>Linhas ignoradas: <?php echo (int)($summary['skipped'] ?? 0); ?></li>
                <li>Registros duplicados: <?php echo (int)($summary['duplicates'] ?? 0); ?></li>
            </ul>
            <?php if (!empty($summary['errors'])): ?>
                <div class="mt-3 text-sm">
                    <p class="font-semibold">Ocorrências:</p>
                    <ul class="list-disc list-inside space-y-1">
                        <?php foreach ($summary['errors'] as $errorMessage): ?>
                            <li><?php echo htmlspecialchars($errorMessage); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <div class="bg-white shadow-lg rounded-lg overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full leading-normal">
                <thead>
                    <tr>
                        <th class="px-6 py-3 border-b-2 border-gray-200 bg-gray-50 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Nome / Empresa</th>
                        <th class="px-6 py-3 border-b-2 border-gray-200 bg-gray-50 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Lead</th>
                        <th class="px-6 py-3 border-b-2 border-gray-200 bg-gray-50 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 border-b-2 border-gray-200 bg-gray-50 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($clientes)): ?>
                        <tr>
                            <td colspan="4" class="px-6 py-5 border-b border-gray-200 bg-white text-sm text-center text-gray-500">Nenhuma prospecção encontrada.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($clientes as $cliente): ?>
                            <?php
                                $totalProspeccoes = (int) ($cliente['totalProspeccoes'] ?? 0);
                                $hasProspection = $totalProspeccoes > 0;
                            ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 border-b border-gray-200 bg-white text-sm">
                                    <p class="text-gray-900 whitespace-no-wrap font-medium"><?php echo htmlspecialchars($cliente['nome_cliente']); ?></p>
                                </td>
                                <td class="px-6 py-4 border-b border-gray-200 bg-white text-sm">
                                    <p class="text-gray-900 whitespace-no-wrap"><?php echo htmlspecialchars($cliente['email']); ?></p>
                                    <p class="text-gray-600 whitespace-no-wrap mt-1"><?php echo htmlspecialchars($cliente['telefone']); ?></p>
                                </td>
                                <td class="px-6 py-4 border-b border-gray-200 bg-white text-sm text-center">
                                    <?php
                                        $statusClass = $hasProspection
                                            ? 'inline-block px-3 py-1 text-sm font-semibold text-green-800 bg-green-200 rounded-full'
                                            : 'inline-block px-3 py-1 text-sm font-semibold text-red-800 bg-red-200 rounded-full';
                                        $statusLabel = $hasProspection ? 'Prospecção' : 'Sem prospecção';
                                    ?>
                                    <span class="<?php echo $statusClass; ?>"><?php echo $statusLabel; ?></span>
                                </td>
                                <td class="px-6 py-4 border-b border-gray-200 bg-white text-sm text-center">
                                    <div class="flex justify-center items-center space-x-3">
                                        <?php if (!$hasProspection): ?>
                                            <a href="<?php echo APP_URL; ?>/crm/prospeccoes/nova.php?cliente_id=<?php echo $cliente['id']; ?>" class="text-green-600 hover:text-green-800 font-semibold">Criar prospecção</a>
                                        <?php endif; ?>
                                        <a href="<?php echo APP_URL; ?>/crm/clientes/editar_cliente.php?id=<?php echo $cliente['id']; ?>" class="text-blue-600 hover:text-blue-800 font-semibold">Editar</a>

                                        <form action="<?php echo APP_URL; ?>/crm/clientes/excluir_cliente.php" method="POST" onsubmit="return confirm('Tem certeza que deseja excluir este lead?');">
                                            <input type="hidden" name="id" value="<?php echo $cliente['id']; ?>">
                                            <button type="submit" class="text-red-600 hover:text-red-800 font-semibold">Excluir</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/../../app/views/layouts/footer.php';
?>