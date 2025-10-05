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
require_once __DIR__ . '/../../app/views/layouts/crm_start.php';
?>

    <section class="crm-section">
        <div class="crm-section-header">
            <h1 class="crm-title"><?php echo $pageTitle; ?></h1>
            <div class="crm-actions">
                <a href="<?php echo APP_URL; ?>/crm/clientes/importar.php" class="bg-emerald-600 text-white font-semibold py-2.5 px-4 rounded-xl hover:bg-emerald-700 transition inline-flex items-center">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" /></svg>
                    Importar Leads
                </a>
                <a href="<?php echo APP_URL; ?>/crm/clientes/novo.php" class="bg-blue-600 text-white font-semibold py-2.5 px-4 rounded-xl hover:bg-blue-700 transition inline-flex items-center">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" /></svg>
                    Novo Lead
                </a>
            </div>
        </div>
    </section>

    <?php if (isset($_SESSION['success_message'])): ?>
        <section class="crm-section">
            <div class="crm-card crm-card--tight bg-emerald-50 border border-emerald-200 text-emerald-800">
                <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
            </div>
        </section>
    <?php endif; ?>
    <?php if (isset($_SESSION['error_message'])): ?>
        <section class="crm-section">
            <div class="crm-card crm-card--tight bg-red-50 border border-red-200 text-red-700">
                <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
            </div>
        </section>
    <?php endif; ?>
    <?php if (isset($_SESSION['import_summary'])): ?>
        <?php $summary = $_SESSION['import_summary']; unset($_SESSION['import_summary']); ?>
        <section class="crm-section">
            <div class="crm-card">
                <h2 class="crm-card-title">Resumo da importação</h2>
                <ul class="mt-2 text-sm list-disc list-inside space-y-1 text-gray-700">
                    <li>Leads criados: <?php echo (int)($summary['created'] ?? 0); ?></li>
                    <li>Linhas ignoradas: <?php echo (int)($summary['skipped'] ?? 0); ?></li>
                    <li>Registros duplicados: <?php echo (int)($summary['duplicates'] ?? 0); ?></li>
                </ul>
                <?php if (!empty($summary['errors'])): ?>
                    <div class="mt-4 text-sm text-gray-700">
                        <p class="font-semibold">Ocorrências:</p>
                        <ul class="list-disc list-inside space-y-1">
                            <?php foreach ($summary['errors'] as $errorMessage): ?>
                                <li><?php echo htmlspecialchars($errorMessage); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    <?php endif; ?>

    <section class="crm-section">
        <div class="crm-card">
            <div class="crm-table-wrapper">
                <table class="crm-table">
                    <thead>
                        <tr>
                            <th>Nome / Empresa</th>
                            <th>Lead</th>
                            <th class="text-center">Status</th>
                            <th class="text-center">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($clientes)): ?>
                            <tr>
                                <td colspan="4" class="py-6 text-center text-gray-500">Nenhuma prospecção encontrada.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($clientes as $cliente): ?>
                                <?php
                                    $totalProspeccoes = (int) ($cliente['totalProspeccoes'] ?? 0);
                                    $hasProspection = $totalProspeccoes > 0;
                                ?>
                                <tr>
                                    <td>
                                        <p class="font-semibold text-gray-900"><?php echo htmlspecialchars($cliente['nome_cliente']); ?></p>
                                    </td>
                                    <td>
                                        <p class="text-gray-900"><?php echo htmlspecialchars($cliente['email']); ?></p>
                                        <p class="text-gray-600 mt-1"><?php echo htmlspecialchars($cliente['telefone']); ?></p>
                                    </td>
                                    <td class="text-center">
                                        <?php
                                            $statusClass = $hasProspection
                                                ? 'inline-flex px-3 py-1 text-xs font-semibold text-emerald-700 bg-emerald-100 rounded-full'
                                                : 'inline-flex px-3 py-1 text-xs font-semibold text-red-700 bg-red-100 rounded-full';
                                            $statusLabel = $hasProspection ? 'Prospecção' : 'Sem prospecção';
                                        ?>
                                        <span class="<?php echo $statusClass; ?>"><?php echo $statusLabel; ?></span>
                                    </td>
                                    <td class="text-center">
                                        <div class="flex justify-center items-center gap-4">
                                            <?php if (!$hasProspection): ?>
                                                <a href="<?php echo APP_URL; ?>/crm/prospeccoes/nova.php?cliente_id=<?php echo $cliente['id']; ?>" class="text-emerald-600 hover:text-emerald-800 font-semibold">Criar prospecção</a>
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
    </section>

<?php require_once __DIR__ . '/../../app/views/layouts/crm_end.php'; ?>