<?php
// Carrega os Models necessários para o relatório
require_once __DIR__ . '/../models/Processo.php';
require_once __DIR__ . '/../models/Vendedor.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../core/access_control.php';

class VendasController {
    private $pdo;
    private $processoModel;
    private $vendedorModel;
    private $userModel;

    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->processoModel = new Processo($this->pdo);
        $this->vendedorModel = new Vendedor($this->pdo);
        $this->userModel = new User($this->pdo);
    }

    /**
     * Exibe o painel de relatório de vendas com filtros e estatísticas.
     */
    public function index() {
        require_permission(['admin', 'gerencia']);

        // Pega os filtros da URL (se houver)
        $filtros = [
            'vendedor_id' => $_GET['vendedor_id'] ?? null,
            'sdr_id' => $_GET['sdr_id'] ?? null,
            'cliente_id' => $_GET['cliente_id'] ?? null,
            'data_entrada_inicio' => $_GET['data_entrada_inicio'] ?? null,
            'data_entrada_fim' => $_GET['data_entrada_fim'] ?? null,
            'data_conversao_inicio' => $_GET['data_conversao_inicio'] ?? null,
            'data_conversao_fim' => $_GET['data_conversao_fim'] ?? null,
        ];

        $action = $_GET['action'] ?? null;

        if ($action === 'export_csv') {
            $this->exportCsv($filtros);
            return;
        }

        if ($action === 'print') {
            $this->printReport($filtros);
            return;
        }

        $reportData = $this->prepareReportData($filtros);
        extract($reportData);

        // Carrega a view do relatório
        $pageTitle = "Relatório de Vendas";
        require_once __DIR__ . '/../views/layouts/header.php';
        require_once __DIR__ . '/../views/vendas/index.php';
        require_once __DIR__ . '/../views/layouts/footer.php';
    }

    public function exportCsv(array $filtros): void
    {
        require_permission(['admin', 'gerencia']);

        $resultadoComissoes = $this->processoModel->getCommissionsByFilter($filtros);
        $processos = $resultadoComissoes['processos'];

        foreach ($processos as &$proc) {
            $proc = $this->normalizeSdrData($proc);
        }

        unset($proc);

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="relatorio_vendas.csv"');

        $output = fopen('php://output', 'w');
        fputcsv($output, [
            'Data de Entrada',
            'Processo',
            'Data de Conversão',
            'Valor Total',
            'Vendedor',
            '% Comissão Vend.',
            'Comissão Vend.',
            'SDR',
            '% Comissão SDR',
            'Comissão SDR',
            'Situação de Pagamento',
            'Pagamento',
            'Status do Serviço'
        ], ';');

        $paymentStatuses = [
            'pago' => 'Pago',
            'parcial' => 'Parcial',
            'pendente' => 'Pendente',
        ];

        foreach ($processos as $proc) {
            $statusKey = strtolower($proc['status_financeiro'] ?? 'pendente');
            $statusLabel = $paymentStatuses[$statusKey] ?? $paymentStatuses['pendente'];

            if ($statusKey == 'pago') {
                $tipoPagamento = 'Entrada';
            } elseif ($statusKey == 'parcial') {
                $tipoPagamento = 'Entrada/Pendente';
            } else {
                $tipoPagamento = 'Pendente';
            }

            $dataConversao = $proc['data_conversao'] ?? ($proc['data_filtro'] ?? null);

            fputcsv($output, [
                !empty($proc['data_entrada']) ? date('d/m/Y', strtotime($proc['data_entrada'])) : '—',
                '#' . $proc['id'] . ' - ' . ($proc['titulo'] ?? ''),
                !empty($dataConversao) ? date('d/m/Y', strtotime($dataConversao)) : '—',
                number_format($proc['valor_total'], 2, ',', '.'),
                $proc['nome_vendedor'],
                number_format($proc['percentual_comissao_vendedor'] ?? 0, 2, ',', '.') . '%',
                number_format($proc['valor_comissao_vendedor'] ?? 0, 2, ',', '.'),
                $proc['nome_sdr'] ?? '—',
                number_format($proc['percentual_comissao_sdr'] ?? 0, 2, ',', '.') . '%',
                number_format($proc['valor_comissao_sdr'] ?? 0, 2, ',', '.'),
                $statusLabel,
                $tipoPagamento,
                $proc['status_processo'] ?? '—',
            ], ';');
        }

        fclose($output);
        exit;
    }

    public function printReport(array $filtros): void
    {
        require_permission(['admin', 'gerencia']);

        $reportData = $this->prepareReportData($filtros);
        extract($reportData);

        $isPrint = true;
        $pageTitle = "Relatório de Vendas";
        require_once __DIR__ . '/../views/layouts/header.php';
        require_once __DIR__ . '/../views/vendas/index.php';
        // Intencionalmente sem rodapé para impressão limpa
    }

    private function prepareReportData(array $filtros): array
    {
        $resultadoComissoes = $this->processoModel->getCommissionsByFilter($filtros);
        $processosFiltrados = $resultadoComissoes['processos'];
        $totais = $resultadoComissoes['totais'];

        $stats = [
            'valor_total_vendido' => $totais['valor_total'],
            'total_documentos' => 0,
            'comissao_vendedor' => $totais['comissao_vendedor'],
            'comissao_sdr' => $totais['comissao_sdr'],
            'ticket_medio' => 0,
            'ranking_vendedores' => []
        ];

        $vendasPorVendedor = [];

        foreach ($processosFiltrados as &$proc) {
            $proc = $this->normalizeSdrData($proc);

            $stats['total_documentos'] += $proc['total_documentos'];

            $vendedorNome = $proc['nome_vendedor'];
            if (!isset($vendasPorVendedor[$vendedorNome])) {
                $vendasPorVendedor[$vendedorNome] = 0;
            }
            $vendasPorVendedor[$vendedorNome] += $proc['valor_total'];
        }

        unset($proc);

        arsort($vendasPorVendedor);
        $stats['ranking_vendedores'] = $vendasPorVendedor;
        $stats['ticket_medio'] = count($processosFiltrados) > 0 ? $stats['valor_total_vendido'] / count($processosFiltrados) : 0;

        $totais['comissao_vendedor'] = array_sum(array_map(static fn($proc) => (float) ($proc['valor_comissao_vendedor'] ?? 0), $processosFiltrados));
        $totais['comissao_sdr'] = array_sum(array_map(static fn($proc) => (float) ($proc['valor_comissao_sdr'] ?? 0), $processosFiltrados));
        $stats['comissao_vendedor'] = $totais['comissao_vendedor'];
        $stats['comissao_sdr'] = $totais['comissao_sdr'];

        $vendedores = $this->vendedorModel->getAll();
        $sdrs = [];
        try {
            $stmt = $this->pdo->prepare("SELECT DISTINCT u.id, u.nome_completo FROM processos p JOIN users u ON p.sdr_id = u.id WHERE p.sdr_id IS NOT NULL ORDER BY u.nome_completo ASC");
            $stmt->execute();
            $sdrs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $exception) {
            error_log('Erro ao carregar SDRs para filtro: ' . $exception->getMessage());
        }

        $clientes = $this->processoModel->getAllClientes();

        return compact('filtros', 'processosFiltrados', 'totais', 'stats', 'vendedores', 'sdrs', 'clientes');
    }

    private function normalizeSdrData(array $proc): array
    {
        if (empty($proc['sdr_id'])) {
            $proc['nome_sdr'] = 'Sem SDR';

            if (($proc['percentual_comissao_vendedor'] ?? 0) == 0) {
                $proc['percentual_comissao_vendedor'] = 0.5;
                $proc['valor_comissao_vendedor'] = round((float) ($proc['valor_total'] ?? 0) * 0.005, 2);
            }

            $proc['percentual_comissao_sdr'] = 0;
            $proc['valor_comissao_sdr'] = 0;

            return $proc;
        }

        $sdrUser = $this->userModel->getById((int)$proc['sdr_id']);
        $proc['nome_sdr'] = $sdrUser['nome_completo'] ?? null;

        return $proc;
    }
    public function converter_para_servico() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $venda_id = $_POST['venda_id'];
        $this->vendaModel->atualizarStatus($venda_id, 'Serviço');

        $venda = $this->vendaModel->getVendaById($venda_id);
        $itens = $this->vendaModel->getItensVenda($venda_id);
        
        // Dados para o novo processo
        $dadosProcesso = [
            'cliente_id' => $venda['cliente_id'],
            'descricao' => 'Processo originado do orçamento #' . $venda_id,
            'status' => 'Não Iniciado',
            'valor_total' => $venda['valor_total'],
            'venda_origem_id' => $venda_id
        ];
        
        $processo_id = $this->processoModel->criar($dadosProcesso);

        // Lógica para Lançamentos Financeiros
        $lancamentoFinanceiroModel = new LancamentoFinanceiro($this->pdo);
        $categoriaModel = new CategoriaFinanceira($this->pdo);
        
        $produtosAgregados = ['Tradução' => 0, 'CRC' => 0, 'Outros' => 0];
        $idsProdutosAgregados = ['Tradução' => [], 'CRC' => [], 'Outros' => []];

        foreach ($itens as $item) {
            $categoria = $categoriaModel->getById($item['categoria_id']);
            
            // Verifica se é um 'Produto de Orçamento'
            if ($categoria && $categoria['eh_produto_orcamento'] == 1) {
                if ($categoria['servico_tipo'] === 'Tradução') {
                    $produtosAgregados['Tradução'] += $item['valor'];
                    $idsProdutosAgregados['Tradução'][] = $item['id'];
                } elseif ($categoria['servico_tipo'] === 'CRC') {
                    $produtosAgregados['CRC'] += $item['valor'];
                    $idsProdutosAgregados['CRC'][] = $item['id'];
                } elseif ($categoria['servico_tipo'] === 'Outros') {
                    $produtosAgregados['Outros'] += $item['valor'];
                    $idsProdutosAgregados['Outros'][] = $item['id'];
                }

                if (!array_key_exists($serviceType, $produtosAgregados)) {
                    $produtosAgregados[$serviceType] = 0.0;
                    $idsProdutosAgregados[$serviceType] = [];
                }

                $produtosAgregados[$serviceType] += (float)$item['valor'];
                $idsProdutosAgregados[$serviceType][] = $item['id'];
            } else {
                // Comportamento antigo: cria lançamento individual
                $dadosLancamento = [
                    'descricao' => $item['descricao'],
                    'valor' => $item['valor'],
                    'data_vencimento' => date('Y-m-d'),
                    'tipo' => 'RECEITA',
                    'categoria_id' => $item['categoria_id'],
                    'cliente_id' => $venda['cliente_id'],
                    'processo_id' => $processo_id,
                    'status' => 'Pendente',
                    'finalizado' => 0,
                    'user_id' => $_SESSION['user_id'] ?? null,
                ];
                $lancamentoFinanceiroModel->create($dadosLancamento);
            }
        }

        // Criar lançamentos agregados, se houver
        foreach ($produtosAgregados as $tipoServico => $valorTotal) {
            if ($valorTotal <= 0) {
                continue;
            }

            // Encontrar uma categoria correspondente para o lançamento agregado
            $categoriaAgregada = $categoriaModel->findByServiceType($tipoServico);

            $dadosLancamentoAgregado = [
                'descricao' => $tipoServico . ' — Orçamento #' . $venda_id,
                'valor' => number_format((float)$valorTotal, 2, '.', ''),
                'data_vencimento' => date('Y-m-d'),
                'tipo' => 'RECEITA',
                'categoria_id' => $categoriaAgregada ? $categoriaAgregada['id'] : null, // Usa a categoria encontrada
                'cliente_id' => $venda['cliente_id'],
                'processo_id' => $processo_id,
                'status' => 'Pendente',
                'eh_agregado' => 1, // Novo campo para identificar
                'itens_agregados_ids' => json_encode($idsProdutosAgregados[$tipoServico]), // Salva os IDs dos itens originais
                'finalizado' => 0,
                'user_id' => $_SESSION['user_id'] ?? null,
            ];
            $lancamentoFinanceiroModel->create($dadosLancamentoAgregado);
        }

        $_SESSION['success_message'] = 'Orçamento convertido para serviço com sucesso!';
        header('Location: /processos/detalhe/' . $processo_id);
        exit();
    }
}
}