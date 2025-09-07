<?php
// /app/controllers/VendedorDashboardController.php

require_once __DIR__ . '/../models/Processo.php';
require_once __DIR__ . '/../models/Vendedor.php';
require_once __DIR__ . '/../models/Cliente.php';
require_once __DIR__ . '/../models/Prospeccao.php'; 

class VendedorDashboardController
{
    private $pdo;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    public function index()
    {
        // 1. Instancia os Models
        $processoModel = new Processo($this->pdo);
        $vendedorModel = new Vendedor($this->pdo);
        $clienteModel = new Cliente($this->pdo);
        $prospeccaoModel = new Prospeccao($this->pdo);

        // 2. Pega os dados do vendedor logado
        $userPerfil = $_SESSION['user_perfil'] ?? '';
        if (($userPerfil === 'admin' || $userPerfil === 'gerencia') && isset($_GET['vendedor_id'])) {
            // Admin/Gerência podem visualizar o painel de outro vendedor passando ?vendedor_id=ID
            $vendedorId = (int) $_GET['vendedor_id'];
            $vendedor   = $vendedorModel->getById($vendedorId);
            if (!$vendedor) { die("Erro: Vendedor não encontrado."); }
            $percentualComissao = $vendedor['percentual_comissao'];
            // Atualiza o userId para puxar estatísticas e agenda do CRM
            $userId = $vendedor['user_id'];
        } else {
            // Comportamento padrão: pega o vendedor logado
            $userId   = $_SESSION['user_id'];
            $vendedor = $vendedorModel->getByUserId($userId);
            if (!$vendedor) { die("Erro: Vendedor não encontrado."); }
            $vendedorId        = $vendedor['id'];
            $percentualComissao = $vendedor['percentual_comissao'];
        }


        // 3. Busca de dados de Processos (Vendas)
        $todosOsProcessosDoVendedor = $processoModel->getFilteredProcesses(['vendedor_id' => $vendedorId], 9999, 0);
        $totalVendasMes = $processoModel->getVendasTotalMesByVendedor($vendedorId);

        // 4. Calcula os Cards de Processos
        
        // CORREÇÃO: Busca o total de orçamentos para ajustar diretamente do banco
        $sql_orcamentos = "SELECT COUNT(id) FROM processos WHERE vendedor_id = :vendedor_id AND status_processo IN ('Orçamento Pendente', 'Recusado')";
        $stmt_orcamentos = $this->pdo->prepare($sql_orcamentos);
        $stmt_orcamentos->execute([':vendedor_id' => $vendedorId]);
        $count_orcamentos_para_ajustar = (int) $stmt_orcamentos->fetchColumn();

        $stats = [
            'processos_ativos' => 0, 
            'orcamentos_para_ajustar' => $count_orcamentos_para_ajustar, // Usa o valor da consulta direta
            'finalizados_mes' => 0, 
            'processos_atrasados' => 0
        ];
        $valorTotalFinalizado = 0; 
        $mesCorrente = date('m');
        $anoCorrente = date('Y');

        foreach ($todosOsProcessosDoVendedor as $processo) {
            if (in_array($processo['status_processo'], ['Aprovado', 'Em Andamento'])) {
                $stats['processos_ativos']++;
            }
            
            // A contagem de 'orcamentos_para_ajustar' foi movida para a consulta SQL acima.
            
            if ($processo['status_processo'] === 'Finalizado') {
                $valorTotalFinalizado += (float)$processo['valor_total'];
                if (!empty($processo['data_finalizacao_real'])) {
                    $dataFinalizacao = new DateTime($processo['data_finalizacao_real']);
                    if ($dataFinalizacao->format('m') == $mesCorrente && $dataFinalizacao->format('Y') == $anoCorrente) {
                        $stats['finalizados_mes']++;
                    }
                }
            }
            
            $prazo = $processo['traducao_prazo_data'] ?? '';
            if (!empty($prazo) && strtotime($prazo) < time() && !in_array($processo['status_processo'], ['Finalizado', 'Arquivado', 'Cancelado'])) {
                $stats['processos_atrasados']++;
            }
        }
        
        // 5. Calcula o valor da comissão
        $valorComissao = ($valorTotalFinalizado * $percentualComissao) / 100;

        // 6. Busca de dados e KPIs do CRM
        $crmStats = $prospeccaoModel->getStatsByResponsavel($userId);
        $prospeccoesPorStatus = $prospeccaoModel->getProspeccoesCountByStatus($userId);
        $proximosAgendamentos = $prospeccaoModel->getProximosAgendamentos($userId, 5);
        $labels_funil = json_encode(array_keys($prospeccoesPorStatus));
        $valores_funil = json_encode(array_values($prospeccoesPorStatus));
        
        // 7. Define a variável '$processos' para a tabela da view.
        $processos = $todosOsProcessosDoVendedor;

        // 8. Preparação para a View
        $clientesParaFiltro = $clienteModel->getAll();
        $pageTitle = 'Meu Painel';
        $hasFilters = !empty(array_filter($_GET));
        $totalProcessesCount = count($todosOsProcessosDoVendedor);
        
        // Carrega a view
        require_once __DIR__ . '/../views/layouts/header.php';
        require_once __DIR__ . '/../views/vendedor_dashboard/main.php';
        require_once __DIR__ . '/../views/layouts/footer.php';
    }
}