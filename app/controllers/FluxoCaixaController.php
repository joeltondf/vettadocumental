<?php
// /app/controllers/FluxoCaixaController.php

require_once __DIR__ . '/../models/LancamentoFinanceiro.php';
require_once __DIR__ . '/../models/CategoriaFinanceira.php';
require_once __DIR__ . '/../models/Processo.php';
require_once __DIR__ . '/../models/Venda.php';
require_once __DIR__ . '/../models/LancamentoFinanceiroLog.php';

class FluxoCaixaController {
    private $pdo;
    private $lancamentoFinanceiroModel;
    private $categoriaModel;
    private $lancamentoLogModel;

    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->lancamentoFinanceiroModel = new LancamentoFinanceiro($pdo);
        $this->categoriaModel = new CategoriaFinanceira($pdo);
        $this->lancamentoLogModel = new LancamentoFinanceiroLog($pdo);
    }

    /**
     * Verifica se o usuário está logado. Se não, redireciona para a página de login.
     */
    private function auth_check() {
        if (!isset($_SESSION['user_id'])) {
            header('Location: /login.php');
            exit();
        }
    }

    /**
     * Renderiza uma view com os dados passados.
     */
    protected function render($view, $data = []) {
        extract($data);
        // Garante que o header e footer sejam incluídos no contexto da view
        require_once __DIR__ . '/../views/layouts/header.php';
        require_once __DIR__ . '/../views/' . $view . '.php';
        require_once __DIR__ . '/../views/layouts/footer.php';
    }

    /**
     * Exibe a página principal do Fluxo de Caixa.
     */
    public function index() {
        $this->auth_check();

        // ... (código de filtros existente) ...
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

        $perfilUsuario = $_SESSION['user_perfil'] ?? '';
        $isPerfilGestor = in_array($perfilUsuario, ['admin', 'gerencia', 'financeiro'], true);
        if (!$isPerfilGestor && isset($_SESSION['user_id'])) {
            $filters['user_id'] = (int) $_SESSION['user_id'];
        }

    $lancamentos = $this->lancamentoFinanceiroModel->getAllPaginated($page, 20, $search, $filters);
    $lancamentoLogs = $this->lancamentoLogModel->getByLancamentoIds(array_column($lancamentos, 'id'));
    $totalLancamentos = $this->lancamentoFinanceiroModel->countAll($search, $filters);
    $categorias = $this->categoriaModel->getAll();

    // --- CORREÇÃO: Lógica para buscar os totais ---
    $totals = $this->lancamentoFinanceiroModel->getTotals($search, $filters);
    $receitas = $totals['receitas'] ?? 0;
    $despesas = $totals['despesas'] ?? 0;
    $resultado = $receitas - $despesas;
    // --- FIM DA CORREÇÃO ---

    // ... (lógica do relatório de serviços existente) ...
    $processoModel = new Processo($this->pdo);
    $hoje = new DateTime();
    $data_inicio_relatorio = !empty($filters['start_date']) ? $filters['start_date'] : $hoje->format('Y-m-01');
    $data_fim_relatorio = !empty($filters['end_date']) ? $filters['end_date'] : $hoje->format('Y-m-t');
    $relatorioServicos = $processoModel->getRelatorioServicosPorPeriodo($data_inicio_relatorio, $data_fim_relatorio);
    $mesRelatorio = (new DateTime($data_inicio_relatorio))->format('m/Y');

    $this->render('fluxo_caixa/painel', [
        'lancamentos' => $lancamentos,
        'totalLancamentos' => $totalLancamentos,
        'categorias' => $categorias,
        'currentPage' => $page,
        'totalPages' => ceil($totalLancamentos / 20),
        'search' => $search,
        'filters' => $filters,
        'relatorioServicos' => $relatorioServicos,
        'mesRelatorio' => $mesRelatorio,
        // --- CORREÇÃO: Enviando as variáveis para a view ---
        'receitas' => $receitas,
        'despesas' => $despesas,
        'resultado' => $resultado,
        'lancamentoLogs' => $lancamentoLogs,
    ]);
}

    private function parseCurrency(?string $valor): float
    {
        if ($valor === null) {
            return 0.0;
        }

        $normalized = str_replace(['R$', '.', ' '], '', $valor);
        $normalized = str_replace(',', '.', $normalized);

        return (float)$normalized;
    }

    public function store(): void
    {
        $this->auth_check();

        try {
            $valor = $this->parseCurrency($_POST['valor'] ?? '0');

            $dados = [
                'descricao' => $_POST['descricao'] ?? '',
                'valor' => $valor,
                'data_vencimento' => $_POST['data_lancamento'] ?? date('Y-m-d'),
                'tipo' => $_POST['tipo'] ?? 'RECEITA',
                'categoria_id' => $_POST['categoria_id'] ?? null,
                'cliente_id' => $_POST['cliente_id'] ?? null,
                'processo_id' => $_POST['processo_id'] ?? null,
                'status' => 'Pendente',
                'data_lancamento' => $_POST['data_lancamento'] ?? date('Y-m-d H:i:s'),
                'finalizado' => (int)($_POST['finalizado'] ?? 0),
                'user_id' => $_SESSION['user_id'] ?? null,
            ];

            $this->lancamentoFinanceiroModel->create($dados);
            $_SESSION['success_message'] = 'Lançamento criado com sucesso!';
        } catch (Throwable $e) {
            $_SESSION['error_message'] = 'Erro ao salvar lançamento: ' . $e->getMessage();
        }

        header('Location: /gestao_lancamentos.php');
        exit();
    }

    public function update(): void
    {
        $this->auth_check();

        try {
            $id = $_POST['id'] ?? null;
            $registro = $this->lancamentoFinanceiroModel->getById($id);

            if (!$registro) {
                $_SESSION['error_message'] = 'Lançamento não encontrado.';
                header('Location: /gestao_lancamentos.php');
                exit();
            }

            if (!empty($registro['finalizado'])) {
                $_SESSION['error_message'] = 'Registro finalizado não pode ser alterado.';
                header('Location: /gestao_lancamentos.php');
                exit();
            }

            $dados = [
                'descricao' => $_POST['descricao'] ?? $registro['descricao'],
                'valor' => $this->parseCurrency($_POST['valor'] ?? '0'),
                'data_vencimento' => $_POST['data_lancamento'] ?? $registro['data_vencimento'],
                'tipo' => $_POST['tipo'] ?? $registro['tipo_lancamento'],
                'categoria_id' => $_POST['categoria_id'] ?? $registro['categoria_id'],
                'cliente_id' => $_POST['cliente_id'] ?? $registro['cliente_id'],
                'processo_id' => $_POST['processo_id'] ?? $registro['processo_id'],
                'status' => $_POST['status'] ?? $registro['status'],
                'user_id' => $_SESSION['user_id'] ?? null,
            ];

            $this->lancamentoFinanceiroModel->update($id, $dados);
            $_SESSION['success_message'] = 'Lançamento atualizado com sucesso!';
        } catch (Throwable $e) {
            $_SESSION['error_message'] = 'Erro ao atualizar lançamento: ' . $e->getMessage();
        }

        header('Location: /gestao_lancamentos.php');
        exit();
    }

    public function finalizar(): void
    {
        $this->auth_check();

        $id = $_POST['id'] ?? null;

        if (!$id) {
            $_SESSION['error_message'] = 'Lançamento não encontrado.';
            header('Location: /gestao_lancamentos.php');
            exit();
        }

        try {
            $this->lancamentoFinanceiroModel->finalizar($id, $_SESSION['user_id'] ?? null);
            $_SESSION['success_message'] = 'Lançamento finalizado.';
        } catch (Throwable $e) {
            $_SESSION['error_message'] = 'Não foi possível finalizar: ' . $e->getMessage();
        }

        header('Location: /gestao_lancamentos.php');
        exit();
    }

    public function ajustar(): void
    {
        $this->auth_check();

        try {
            $id = (int)($_POST['id'] ?? 0);
            $valor = $this->parseCurrency($_POST['valor'] ?? '0');
            $motivo = trim($_POST['motivo'] ?? '');

            if ($id === 0 || $motivo === '') {
                throw new RuntimeException('Dados de ajuste inválidos.');
            }

            $this->lancamentoFinanceiroModel->ajustar($id, $valor, $motivo, $_SESSION['user_id'] ?? null);
            $_SESSION['success_message'] = 'Ajuste registrado com sucesso!';
        } catch (Throwable $e) {
            $_SESSION['error_message'] = 'Erro ao registrar ajuste: ' . $e->getMessage();
        }

        header('Location: /gestao_lancamentos.php');
        exit();
    }

    /**
     * API para buscar detalhes de um lançamento agregado.
     */
    public function get_detalhes_lancamento_agregado($lancamento_id) {
        $this->auth_check();
        header('Content-Type: application/json');
        
        $lancamento = $this->lancamentoFinanceiroModel->getById($lancamento_id);
        
        if (!$lancamento || !$lancamento['eh_agregado'] || empty($lancamento['itens_agregados_ids'])) {
            echo json_encode(['success' => false, 'message' => 'Lançamento não encontrado ou não é agregado.']);
            return;
        }
        
        $ids_dos_itens = json_decode($lancamento['itens_agregados_ids'], true);
        
        if (empty($ids_dos_itens)) {
            echo json_encode(['success' => false, 'message' => 'Nenhum item detalhado encontrado.']);
            return;
        }
        
        $vendaModel = new Venda($this->pdo);
        $itens_detalhados = $vendaModel->getItensByIds($ids_dos_itens);
        
        echo json_encode(['success' => true, 'itens' => $itens_detalhados]);
    }
    
    /**
     * Apaga um lançamento financeiro.
     */
    public function delete() {
        $this->auth_check();
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
            $id = $_POST['id'];
            if ($this->lancamentoFinanceiroModel->delete($id)) {
                $_SESSION['success_message'] = 'Lançamento apagado com sucesso!';
            } else {
                $_SESSION['error_message'] = 'Erro ao apagar o lançamento.';
            }
        }
        header('Location: /gestao_lancamentos.php');
        exit();
    }
}