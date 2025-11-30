<?php
session_start();

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/app/core/auth_check.php';
require_once __DIR__ . '/app/core/access_control.php';
require_permission(['admin', 'gerencia', 'supervisor', 'vendedor', 'sdr']);

require_once __DIR__ . '/app/models/LancamentoFinanceiro.php';
require_once __DIR__ . '/app/models/CategoriaFinanceira.php';
require_once __DIR__ . '/app/models/LancamentoFinanceiroLog.php';
require_once __DIR__ . '/app/models/Processo.php';
require_once __DIR__ . '/app/models/Comissao.php';
require_once __DIR__ . '/app/models/Vendedor.php';
require_once __DIR__ . '/app/models/User.php';

$userPerfil = $_SESSION['user_perfil'] ?? 'guest';
$view = isset($_GET['view']) ? preg_replace('/[^a-z_]/', '', $_GET['view']) : 'dashboard';

$availableViews = [
    'dashboard' => 'Dashboard de BI',
    'caixa' => 'Fluxo de Caixa',
    'vendas' => 'Relatório de Vendas',
];

// Restrições simples para vendedores
if ($userPerfil === 'vendedor') {
    $view = 'vendas';
} elseif (!array_key_exists($view, $availableViews)) {
    $view = 'dashboard';
}

$data_inicio = $_GET['data_inicio'] ?? date('Y-m-01');
$data_fim = $_GET['data_fim'] ?? date('Y-m-t');

$viewData = compact('view', 'availableViews');

switch ($view) {
    case 'caixa':
        $page = $_GET['page'] ?? 1;
        $search = $_GET['search'] ?? '';
        $startDate = $_GET['start_date'] ?? $_GET['data_inicio'] ?? null;
        $endDate = $_GET['end_date'] ?? $_GET['data_fim'] ?? null;

        $filters = [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'type' => $_GET['type'] ?? null,
            'status' => $_GET['status'] ?? null,
            'category' => $_GET['category'] ?? null,
        ];

        $lancamentoFinanceiroModel = new LancamentoFinanceiro($pdo);
        $categoriaModel = new CategoriaFinanceira($pdo);
        $lancamentoLogModel = new LancamentoFinanceiroLog($pdo);
        $processoModel = new Processo($pdo);

        $lancamentos = $lancamentoFinanceiroModel->getAllPaginated($page, 20, $search, $filters);
        $lancamentoLogs = $lancamentoLogModel->getByLancamentoIds(array_column($lancamentos, 'id'));
        $totalLancamentos = $lancamentoFinanceiroModel->countAll($search, $filters);
        $categorias = $categoriaModel->getAll();

        $totals = $lancamentoFinanceiroModel->getTotals($search, $filters);
        $receitas = $totals['receitas'] ?? 0;
        $despesas = $totals['despesas'] ?? 0;
        $resultado = $receitas - $despesas;

        $hoje = new DateTime();
        $data_inicio_relatorio = !empty($filters['start_date']) ? $filters['start_date'] : $hoje->format('Y-m-01');
        $data_fim_relatorio = !empty($filters['end_date']) ? $filters['end_date'] : $hoje->format('Y-m-t');
        $relatorioServicos = $processoModel->getRelatorioServicosPorPeriodo($data_inicio_relatorio, $data_fim_relatorio);
        $mesRelatorio = (new DateTime($data_inicio_relatorio))->format('m/Y');

        $viewData = array_merge($viewData, compact(
            'lancamentos',
            'totalLancamentos',
            'categorias',
            'page',
            'search',
            'filters',
            'relatorioServicos',
            'mesRelatorio',
            'receitas',
            'despesas',
            'resultado',
            'lancamentoLogs'
        ));
        break;
    case 'vendas':
        $processoModel = new Processo($pdo);
        $vendedorModel = new Vendedor($pdo);
        $comissaoModel = new Comissao($pdo);
        $userModel = new User($pdo);

        $userId = $_SESSION['user_id'];
        $user = $userModel->getById($userId);
        $perfil = $user['perfil'] ?? null;

        $filters = [
            'data_inicio' => $data_inicio,
            'data_fim' => $data_fim,
        ];

        $comissoes = [];
        if ($perfil === 'vendedor') {
            $vendedor = $vendedorModel->getByUserId($userId);
            if (!$vendedor) {
                die('Vendedor não encontrado.');
            }
            $filters['vendedor_id'] = $vendedor['id'];
            $comissoes = $comissaoModel->getByVendedor($vendedor['id']);
        } elseif ($perfil === 'sdr') {
            $vendedor = [
                'percentual_comissao' => 0,
                'id' => $userId,
            ];
            $filters['sdr_id'] = $userId;
        } else {
            die('Usuário não autorizado para visualizar este relatório.');
        }

        $vendasDoPeriodo = $processoModel->getSalesByFilter($filters);

        $vendasMensais = [];
        foreach ($vendasDoPeriodo as $venda) {
            $mes = date('Y-m', strtotime($venda['data_criacao']));
            if (!isset($vendasMensais[$mes])) {
                $vendasMensais[$mes] = 0;
            }
            $vendasMensais[$mes] += $venda['valor_total'];
        }
        ksort($vendasMensais);

        $labels_vendas = json_encode(array_keys($vendasMensais));
        $valores_vendas = json_encode(array_values($vendasMensais));

        $viewData = array_merge($viewData, compact(
            'data_inicio',
            'data_fim',
            'vendedor',
            'vendasDoPeriodo',
            'labels_vendas',
            'valores_vendas',
            'comissoes'
        ));
        break;
    default:
        $lancamentoFinanceiroModel = new LancamentoFinanceiro($pdo);
        $totals = $lancamentoFinanceiroModel->getTotals();
        $receitaTotal = $totals['receitas'] ?? 0;
        $despesaTotal = $totals['despesas'] ?? 0;

        $comissaoModel = new Comissao($pdo);
        $comissoes = $comissaoModel->getByVendedor($_SESSION['user_id'] ?? 0);
        $totalComissoes = array_sum(array_column($comissoes, 'valor_comissao'));

        $alertas = [];

        $viewData = array_merge($viewData, compact(
            'receitaTotal',
            'despesaTotal',
            'totalComissoes',
            'alertas'
        ));
        break;
}

$viewFile = __DIR__ . '/app/views/relatorios/' . $view . '.php';

if (!file_exists($viewFile)) {
    http_response_code(404);
    echo 'View não encontrada';
    exit;
}

extract($viewData);

require __DIR__ . '/app/views/relatorios/layout_base.php';
