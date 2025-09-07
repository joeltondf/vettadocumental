<?php
/**
 * @file /app/controllers/FinanceiroController.php
 * @description Controller dedicado a gerar relatórios financeiros e
 * fornecer endpoints para interações financeiras, como atualizações inline.
 */
require_once __DIR__ . '/../models/Processo.php';

class FinanceiroController
{
    private $pdo;
    private $processoModel;

    /**
     * Construtor da classe.
     * Inicia o model de Processo, que contém a lógica financeira.
     *
     * @param PDO $pdo Uma instância do objeto PDO.
     */
    public function __construct($pdo)
    {
        $this->pdo = $pdo;
        $this->processoModel = new Processo($this->pdo);
    }

    // =======================================================================
    // AÇÃO PRINCIPAL DE RELATÓRIO
    // =======================================================================

    /**
     * Renderiza a página principal do relatório financeiro.
     * Agrega dados de resumo, a lista detalhada de processos e dados para os filtros.
     *
     * @param string $start_date Data de início para os filtros.
     * @param string $end_date Data de fim para os filtros.
     * @param string $group_by (Não utilizado neste trecho, mas pode ser para futuros gráficos).
     */
    public function index($start_date, $end_date, $group_by)
    {
        // 1. Monta o array de filtros para a busca no model
        $filters = [
            'data_inicio' => $start_date,
            'data_fim' => $end_date,
            'vendedor_id' => $_GET['vendedor_id'] ?? null,
            'forma_pagamento_id' => $_GET['forma_pagamento_id'] ?? null,
            'cliente_id' => $_GET['cliente_id'] ?? null, // <<< ADICIONAR 1: Recebe o ID do cliente do filtro.
        ];

        // 2. Busca os dados principais com base nos filtros
        // Lista detalhada de processos para a tabela
        $processos = $this->processoModel->getFinancialData($filters);
        // Cards com o resumo do período (total, recebido, a receber)
        // Opcional: Você pode querer que o resumo também considere os filtros.
        // Se sim, passaria $filters aqui também. Por enquanto, mantive como estava.
        $overallSummary = $this->processoModel->getOverallFinancialSummary($start_date, $end_date, $filters);

        // 3. Busca dados para popular os menus de filtro na view
        $vendedores = $this->processoModel->getAllVendedores();
        $formas_pagamento = $this->processoModel->getAllFormasPagamento();
        $clientes = $this->processoModel->getAllClientes(); // <<< ADICIONAR 2: Busca a lista de todos os clientes.

        // 4. Prepara variáveis para a view
        $pageTitle = "Relatório Financeiro";

        // 5. Renderiza a página (agora a variável $clientes estará disponível na view)
        require_once __DIR__ . '/../views/layouts/header.php';
        require_once __DIR__ . '/../views/financeiro/lista.php'; // Ou o nome que você deu ao seu arquivo de view
        require_once __DIR__ . '/../views/layouts/footer.php';
    }

    // =======================================================================
    // ENDPOINT AJAX
    // =======================================================================

    /**
     * Endpoint para atualização inline de campos financeiros via AJAX.
     * Espera uma requisição POST com 'id', 'field' e 'value'.
     * Retorna uma resposta JSON com o status da operação.
     */
    public function updateFinancialField()
    {
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405); // Method Not Allowed
            echo json_encode(['status' => 'error', 'message' => 'Método não permitido.']);
            exit;
        }

        $processId = $_POST['id'] ?? null;
        $field = $_POST['field'] ?? null;
        $value = $_POST['value'] ?? null;

        if ($processId && $field && isset($value)) {
            $success = $this->processoModel->updateProcessFinancialField((int)$processId, $field, $value);
            
            if ($success) {
                echo json_encode(['status' => 'success', 'message' => 'Campo atualizado com sucesso.']);
            } else {
                http_response_code(500); // Internal Server Error
                echo json_encode(['status' => 'error', 'message' => 'Falha ao atualizar o campo no banco de dados.']);
            }
        } else {
            http_response_code(400); // Bad Request
            echo json_encode(['status' => 'error', 'message' => 'Dados inválidos ou incompletos.']);
        }
        
        exit; // Termina a execução após a resposta AJAX
    }
}