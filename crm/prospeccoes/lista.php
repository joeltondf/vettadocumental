<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../app/core/auth_check.php';
require_once __DIR__ . '/../../app/utils/LeadCategory.php';

$pageTitle = "Lista de Prospecções";

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

require_once __DIR__ . '/../../app/views/layouts/header.php';
?>

<div class="container mx-auto px-4 py-8">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-800">Lista de Prospecções</h1>
        <div>
            <a href="nova.php" class="bg-blue-600 text-white font-bold py-2 px-4 rounded-lg hover:bg-blue-700 shadow-sm">
                + Nova Prospecção
            </a>
            <a href="kanban.php" class="bg-gray-500 text-white font-bold py-2 px-4 rounded-lg hover:bg-gray-600 shadow-sm ml-2">
                Ver Kanban
            </a>
        </div>
    </div>

    <div class="bg-white p-6 rounded-lg shadow-md mb-6">
    <h2 class="text-lg font-semibold text-gray-700 mb-4">Filtros</h2>
    <form action="" method="GET" class="space-y-6">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            <!-- Campo de busca -->
            <div>
                <label for="search" class="block text-sm font-medium text-gray-600 mb-2">Buscar</label>
                <input type="text" name="search" id="search" class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" value="<?php echo htmlspecialchars($search_term); ?>" placeholder="Nome do prospecto ou lead...">
            </div>

            <!-- Campo de status -->
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

            <!-- Campo de responsável (visível apenas se o perfil não for 'vendedor') -->
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

            <!-- Campos de data -->
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

        <!-- Botões -->
        <div class="flex justify-between items-center space-x-2">
            <a href="lista.php" class="bg-gray-300 text-gray-700 font-bold py-2 px-4 rounded-lg hover:bg-gray-400 transition duration-200">Limpar</a>
            <button type="submit" class="bg-blue-600 text-white font-bold py-2 px-4 rounded-lg hover:bg-blue-700 transition duration-200">Filtrar</button>
        </div>
    </form>
</div>



    <div class="bg-white overflow-x-auto shadow-md rounded-lg">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Prospecto</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Lead</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Categoria</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Valor</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Responsável</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Data</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Ações</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if (empty($prospeccoes)): ?>
                    <tr>
                        <td colspan="7" class="px-6 py-4 text-center text-gray-500">Nenhuma prospecção encontrada com os filtros aplicados.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($prospeccoes as $prospeccao): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars($prospeccao['nome_prospecto']); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600"><?php echo htmlspecialchars($prospeccao['nome_cliente'] ?? 'Lead não vinculado'); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                <?php $leadCategory = $prospeccao['leadCategory'] ?? LeadCategory::DEFAULT; ?>
                                <span class="inline-flex items-center rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-700">
                                    <?php echo htmlspecialchars($leadCategory); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                <span class="
                                    <?php 
                                        $status = strtolower($prospeccao['status']); // Converte para minúsculo para garantir que a comparação seja feita de forma insensível ao caso

                                        switch ($status) {
                                            case 'cliente ativo':
                                                echo 'px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800';
                                                break;
                                            case 'primeiro contato':
                                                echo 'px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800';
                                                break;
                                            case 'segundo contato':
                                                echo 'px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800';
                                                break;
                                            case 'terceiro contato':
                                                echo 'px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-indigo-100 text-indigo-800';
                                                break;
                                            case 'reunião agendada':
                                                echo 'px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-purple-100 text-purple-800';
                                                break;
                                            case 'proposta enviada':
                                                echo 'px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-teal-100 text-teal-800';
                                                break;
                                            case 'fechamento':
                                                echo 'px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800';
                                                break;
                                            case 'pausar':
                                                echo 'px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800';
                                                break;
                                            default:
                                                echo 'px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800';
                                                break;
                                        }
                                    ?>
                                ">
                                    <?php echo htmlspecialchars(ucfirst($prospeccao['status'])); ?>
                                </span>
                            </td>

                            <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-gray-600">R$ <?php echo number_format($prospeccao['valor_proposto'] ?? 0, 2, ',', '.'); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600"><?php echo htmlspecialchars($prospeccao['nome_responsavel'] ?? 'N/A'); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600"><?php echo date('d/m/Y', strtotime($prospeccao['data_prospeccao'])); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-center">
                                <a href="detalhes.php?id=<?php echo $prospeccao['id']; ?>" class="text-indigo-600 hover:text-indigo-900">Detalhes</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../../app/views/layouts/footer.php'; ?>