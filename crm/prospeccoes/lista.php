<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../app/core/auth_check.php';

$pageTitle = "Lista de Prospecções";
$bodyClass = 'crm-layout';

// --- INÍCIO DA LÓGICA DE FILTRO E CONTROLE DE ACESSO ---
$where_clauses = [];
$params = [];

// Filtros do formulário
$search_term = $_GET['search'] ?? '';
$search_status = $_GET['status'] ?? '';
$search_responsavel = $_GET['responsavel_id'] ?? '';
$search_data_inicio = $_GET['data_inicio'] ?? '';
$search_data_fim = $_GET['data_fim'] ?? '';

if (!empty($search_term)) {
    $where_clauses[] = "(p.nome_prospecto LIKE :term OR c.nome_cliente LIKE :term)";
    $params[':term'] = '%' . $search_term . '%';
}
if (!empty($search_status)) {
    $where_clauses[] = "p.status = :status";
    $params[':status'] = $search_status;
}
if (!empty($search_data_inicio)) {
    $where_clauses[] = "p.data_prospeccao >= :data_inicio";
    $params[':data_inicio'] = $search_data_inicio;
}
if (!empty($search_data_fim)) {
    // Adiciona 1 dia para incluir o dia final completo na busca
    $data_fim_ajustada = date('Y-m-d', strtotime($search_data_fim . ' +1 day'));
    $where_clauses[] = "p.data_prospeccao < :data_fim";
    $params[':data_fim'] = $data_fim_ajustada;
}


// Controle de Acesso por Perfil
$user_perfil = $_SESSION['user_perfil'] ?? '';
$user_id = $_SESSION['user_id'] ?? 0;

if ($user_perfil === 'vendedor') {
    // Vendedores veem apenas as suas prospecções
    $where_clauses[] = "p.responsavel_id = :user_id";
    $params[':user_id'] = $user_id;
} else {
    // Admin, Gerência ou Supervisão podem filtrar por responsável
    if (!empty($search_responsavel)) {
        $where_clauses[] = "p.responsavel_id = :responsavel_id";
        $params[':responsavel_id'] = $search_responsavel;
    }
}

// Busca a lista de vendedores para o filtro (apenas para Admin/Gerência/Supervisão)
$responsaveis = [];
if ($user_perfil !== 'vendedor') {
    try {
        $stmt_users = $pdo->query("SELECT id, nome_completo FROM users WHERE perfil IN ('vendedor', 'admin', 'gerencia', 'supervisor') ORDER BY nome_completo");
        $responsaveis = $stmt_users->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // Não interrompe a execução, apenas não exibe o filtro de responsáveis
    }
}


// Construção da consulta SQL final
$sql_where = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

$sql = "SELECT 
            p.*, 
            c.nome_cliente, 
            u.nome_completo as nome_responsavel 
        FROM prospeccoes p
        LEFT JOIN clientes c ON p.cliente_id = c.id
        LEFT JOIN users u ON p.responsavel_id = u.id
        $sql_where
        ORDER BY p.data_prospeccao DESC";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $prospeccoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Erro ao buscar prospecções: " . $e->getMessage());
}

// Lista de status para o dropdown
$status_list = ['prospecção', 'qualificação', 'apresentação', 'negociação', 'fechamento', 'pós-venda', 'perdido'];


// --- FIM DA LÓGICA DE CONSULTA ---

require_once __DIR__ . '/../../app/views/layouts/crm_start.php';
?>
    <section class="crm-section">
        <div class="crm-section-header">
            <h1 class="crm-title">Lista de Prospecções</h1>
            <div class="crm-actions">
                <a href="nova.php" class="bg-blue-600 text-white font-semibold py-2.5 px-4 rounded-xl hover:bg-blue-700 transition">+ Nova Prospecção</a>
                <a href="kanban.php" class="bg-slate-600 text-white font-semibold py-2.5 px-4 rounded-xl hover:bg-slate-700 transition">Ver Kanban</a>
            </div>
        </div>
        <div class="crm-card">
            <h2 class="crm-card-title">Filtros</h2>
            <form action="" method="GET" class="space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                    <div>
                        <label for="search" class="block text-sm font-medium text-gray-600 mb-2">Buscar</label>
                        <input type="text" name="search" id="search" class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" value="<?php echo htmlspecialchars($search_term); ?>" placeholder="Nome do prospecto ou lead...">
                    </div>
                    <div>
                        <label for="status" class="block text-sm font-medium text-gray-600 mb-2">Status</label>
                        <select name="status" id="status" class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <option value="">Todos</option>
                            <?php foreach ($status_list as $status_item): ?>
                                <option value="<?php echo $status_item; ?>" <?php echo ($search_status === $status_item) ? 'selected' : ''; ?>>
                                    <?php echo ucfirst($status_item); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php if ($user_perfil !== 'vendedor'): ?>
                    <div>
                        <label for="responsavel_id" class="block text-sm font-medium text-gray-600 mb-2">Responsável</label>
                        <select name="responsavel_id" id="responsavel_id" class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <option value="">Todos</option>
                            <?php foreach ($responsaveis as $responsavel): ?>
                                <option value="<?php echo $responsavel['id']; ?>" <?php echo ($search_responsavel == $responsavel['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($responsavel['nome_completo']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="data_inicio" class="block text-sm font-medium text-gray-600 mb-2">De</label>
                            <input type="date" name="data_inicio" id="data_inicio" class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" value="<?php echo htmlspecialchars($search_data_inicio); ?>">
                        </div>
                        <div>
                            <label for="data_fim" class="block text-sm font-medium text-gray-600 mb-2">Até</label>
                            <input type="date" name="data_fim" id="data_fim" class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" value="<?php echo htmlspecialchars($search_data_fim); ?>">
                        </div>
                    </div>
                </div>
                <div class="flex flex-col sm:flex-row justify-end gap-3">
                    <a href="lista.php" class="inline-flex justify-center items-center bg-slate-200 text-slate-800 font-medium px-6 py-2 rounded-xl hover:bg-slate-300 transition">Limpar</a>
                    <button type="submit" class="inline-flex justify-center items-center bg-blue-600 text-white font-medium px-6 py-2 rounded-xl hover:bg-blue-700 transition">Filtrar</button>
                </div>
            </form>
        </div>
    </section>
    <section class="crm-section">
        <div class="crm-card crm-card--tight">
            <h2 class="crm-card-title">Resultado</h2>
            <div class="crm-table-wrapper">
                <table class="crm-table">
                    <thead>
                        <tr>
                            <th>Prospecto</th>
                            <th>Lead</th>
                            <th>Status</th>
                            <th class="text-right">Valor</th>
                            <th>Responsável</th>
                            <th>Data</th>
                            <th class="text-center">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($prospeccoes)): ?>
                            <tr>
                                <td colspan="7" class="py-6 text-center text-gray-500">Nenhuma prospecção encontrada com os filtros aplicados.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($prospeccoes as $prospeccao): ?>
                                <tr>
                                    <td>
                                        <div class="font-semibold text-gray-900"><?php echo htmlspecialchars($prospeccao['nome_prospecto']); ?></div>
                                        <div class="text-sm text-gray-500">Origem: <?php echo htmlspecialchars($prospeccao['origem'] ?? 'Não informado'); ?></div>
                                    </td>
                                    <td><?php echo htmlspecialchars($prospeccao['nome_cliente'] ?? 'Lead não vinculado'); ?></td>
                                    <td>
                                        <span class="inline-flex px-3 py-1 rounded-full text-xs font-semibold bg-blue-100 text-blue-600">
                                            <?php echo htmlspecialchars(ucfirst($prospeccao['status'])); ?>
                                        </span>
                                    </td>
                                    <td class="text-right">R$ <?php echo number_format($prospeccao['valor_proposto'] ?? 0, 2, ',', '.'); ?></td>
                                    <td><?php echo htmlspecialchars($prospeccao['nome_responsavel'] ?? 'N/A'); ?></td>
                                    <td><?php echo date('d/m/Y', strtotime($prospeccao['data_prospeccao'])); ?></td>
                                    <td class="text-center">
                                        <a href="detalhes.php?id=<?php echo $prospeccao['id']; ?>" class="text-blue-600 hover:text-blue-800 font-semibold">Detalhes</a>
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