<?php
// /app/controllers/VendedorDashboardController.php

require_once __DIR__ . '/../models/Processo.php';
require_once __DIR__ . '/../models/Vendedor.php';
require_once __DIR__ . '/../models/Cliente.php';
require_once __DIR__ . '/../models/Prospeccao.php';
require_once __DIR__ . '/../models/Comissao.php';
require_once __DIR__ . '/../utils/DashboardProcessFormatter.php';

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
        $clienteModel = new Cliente($this->pdo);
        $prospeccaoModel = new Prospeccao($this->pdo);
        $comissaoModel = new Comissao($this->pdo);

        // 2. Pega os dados do vendedor logado ou informado para gestão
        [$vendedorId, $userId, $percentualComissao] = $this->resolveVendedorContext();

        $timezone = new DateTimeZone('America/Sao_Paulo');
        $now = new DateTime('now', $timezone);
        $currentMonthStart = (clone $now)->modify('first day of this month')->setTime(0, 0, 0);
        $currentMonthEnd = (clone $currentMonthStart)->modify('last day of this month')->setTime(23, 59, 59);
        $lastMonthStart = (clone $currentMonthStart)->modify('-1 month');
        $lastMonthEnd = (clone $currentMonthStart)->modify('-1 second');

        $monthStartStr = $currentMonthStart->format('Y-m-d H:i:s');
        $monthEndStr = $currentMonthEnd->format('Y-m-d H:i:s');
        $lastMonthStartStr = $lastMonthStart->format('Y-m-d H:i:s');
        $lastMonthEndStr = $lastMonthEnd->format('Y-m-d H:i:s');

        $orcamentosMesAtual = $processoModel->getVendorBudgetsByMonth($vendedorId, $monthStartStr, $monthEndStr);
        $servicosMesAtual = $processoModel->getVendorServicesByMonth($vendedorId, $monthStartStr, $monthEndStr);
        $servicosAtivosMesAnterior = $processoModel->getVendorActiveServicesFromLastMonth($vendedorId, $lastMonthStartStr, $lastMonthEndStr);

        foreach ($servicosMesAtual as &$processoAtual) {
            $processoAtual['comissaoVendedor'] = $comissaoModel->getCommissionByProcessAndUser((int)($processoAtual['id'] ?? 0), $vendedorId);
            $sdrId = isset($processoAtual['sdr_id']) ? (int)$processoAtual['sdr_id'] : null;
            $processoAtual['comissaoSdr'] = $sdrId ? $comissaoModel->getCommissionByProcessAndUser((int)$processoAtual['id'], $sdrId) : 0.0;
        }
        unset($processoAtual);

        foreach ($servicosAtivosMesAnterior as &$processoAnterior) {
            $processoAnterior['comissaoVendedor'] = $comissaoModel->getCommissionByProcessAndUser((int)($processoAnterior['id'] ?? 0), $vendedorId);
            $sdrId = isset($processoAnterior['sdr_id']) ? (int)$processoAnterior['sdr_id'] : null;
            $processoAnterior['comissaoSdr'] = $sdrId ? $comissaoModel->getCommissionByProcessAndUser((int)$processoAnterior['id'], $sdrId) : 0.0;
        }
        unset($processoAnterior);


        // 3. Busca de dados de Processos (Vendas)
        $filters = $this->buildFiltersFromRequest($_GET ?? []);
        $filters['vendedor_id'] = $vendedorId;

        $todosOsProcessosDoVendedor = $processoModel->getFilteredProcesses($filters, 9999, 0);
        $totalVendasMes = $processoModel->getVendasTotalMesByVendedor($vendedorId);

        // 4. Calcula os Cards de Processos

        $stats = [
            'processos_ativos' => 0,
            'finalizados_mes' => 0,
            'processos_atrasados' => 0
        ];
        $valorTotalFinalizado = 0; 
        $mesCorrente = date('m');
        $anoCorrente = date('Y');

        foreach ($todosOsProcessosDoVendedor as $processo) {
            $statusInfo = DashboardProcessFormatter::normalizeStatusInfo($processo['status_processo'] ?? '');
            $statusNormalized = $statusInfo['normalized'];
            if (in_array($statusNormalized, ['serviço pendente', 'serviço em andamento'], true)) {
                $stats['processos_ativos']++;
            }

            if ($statusNormalized === 'concluído') {
                $valorTotalFinalizado += (float)$processo['valor_total'];
                if (!empty($processo['data_finalizacao_real'])) {
                    $dataFinalizacao = new DateTime($processo['data_finalizacao_real']);
                    if ($dataFinalizacao->format('m') == $mesCorrente && $dataFinalizacao->format('Y') == $anoCorrente) {
                        $stats['finalizados_mes']++;
                    }
                }
            }
            
            $prazo = $processo['data_previsao_entrega'] ?? '';
            if (!empty($prazo) && strtotime($prazo) < time() && !in_array($statusNormalized, ['concluído', 'cancelado'], true)) {
                $stats['processos_atrasados']++;
            }
        }
        
        // 5. Calcula o valor da comissão
        $valorComissao = ($valorTotalFinalizado * $percentualComissao) / 100;

        // 6. Busca de dados e KPIs do CRM
        $crmStats = $prospeccaoModel->getStatsByResponsavel($userId);
        $prospeccoesPorStatus = $prospeccaoModel->getProspeccoesCountByStatus($userId);
        $proximosAgendamentos = $prospeccaoModel->getProximosAgendamentos($userId, 5);
        $nextLead = $prospeccaoModel->getNextLeadForVendor($userId);
        $vendorLeads = $prospeccaoModel->getVendorLeads($userId);
        $labels_funil = json_encode(array_keys($prospeccoesPorStatus));
        $valores_funil = json_encode(array_values($prospeccoesPorStatus));
        
        // 7. Define a variável '$processos' para a tabela da view.
        $processos = $todosOsProcessosDoVendedor;

        // 8. Preparação para a View
        $clientesParaFiltro = $clienteModel->getAll();
        $pageTitle = 'Meu Painel';
        $filtrosAtuais = $this->cleanFilterValues($_GET ?? []);
        $hasFilters = !empty($filtrosAtuais);
        $totalProcessesCount = count($todosOsProcessosDoVendedor);

        $filters = $filtrosAtuais;
        $currentVendedorId = $vendedorId;

        // Carrega a view
        require_once __DIR__ . '/../views/layouts/header.php';
        require_once __DIR__ . '/../views/vendedor_dashboard/main.php';
        require_once __DIR__ . '/../views/layouts/footer.php';
    }

    public function listarOrcamentos(): void
    {
        $processoModel = new Processo($this->pdo);
        $clienteModel = new Cliente($this->pdo);
        $comissaoModel = new Comissao($this->pdo);

        [$vendedorId, , ] = $this->resolveVendedorContext();

        $filters = $this->buildListFilters($_GET ?? []);
        $dataInicio = $this->formatDateFilter($filters['data_inicio'] ?? null, 'start');
        $dataFim = $this->formatDateFilter($filters['data_fim'] ?? null, 'end');

        $orcamentos = $processoModel->getVendorBudgets($vendedorId, $dataInicio, $dataFim, $filters);
        $orcamentos = $this->appendCommissions($orcamentos, $vendedorId, $comissaoModel);

        $clientesParaFiltro = $clienteModel->getAll();
        $pageTitle = 'Todos os Orçamentos';
        $currentVendedorId = $vendedorId;

        require_once __DIR__ . '/../views/layouts/header.php';
        require_once __DIR__ . '/../views/vendedor_dashboard/lista_orcamentos.php';
        require_once __DIR__ . '/../views/layouts/footer.php';
    }

    public function listarServicos(): void
    {
        $processoModel = new Processo($this->pdo);
        $clienteModel = new Cliente($this->pdo);
        $comissaoModel = new Comissao($this->pdo);

        [$vendedorId, , ] = $this->resolveVendedorContext();

        $filters = $this->buildListFilters($_GET ?? []);
        $dataInicio = $this->formatDateFilter($filters['data_inicio'] ?? null, 'start');
        $dataFim = $this->formatDateFilter($filters['data_fim'] ?? null, 'end');

        $servicos = $processoModel->getVendorServices($vendedorId, $dataInicio, $dataFim, $filters);
        $servicos = $this->appendCommissions($servicos, $vendedorId, $comissaoModel);

        $clientesParaFiltro = $clienteModel->getAll();
        $pageTitle = 'Todos os Serviços';
        $currentVendedorId = $vendedorId;

        require_once __DIR__ . '/../views/layouts/header.php';
        require_once __DIR__ . '/../views/vendedor_dashboard/lista_servicos.php';
        require_once __DIR__ . '/../views/layouts/footer.php';
    }

    public function listarProcessos(): void
    {
        $processoModel = new Processo($this->pdo);
        $clienteModel = new Cliente($this->pdo);
        $comissaoModel = new Comissao($this->pdo);

        [$vendedorId, , ] = $this->resolveVendedorContext();

        $filters = $this->buildFiltersFromRequest($_GET ?? []);
        $filters['vendedor_id'] = $vendedorId;

        $totalProcessesCount = $processoModel->getTotalFilteredProcessesCount($filters);
        $limit = $totalProcessesCount > 0 ? $totalProcessesCount : 0;
        $processos = $limit > 0 ? $processoModel->getFilteredProcesses($filters, $limit, 0) : [];
        $processos = $this->appendCommissions($processos, $vendedorId, $comissaoModel);

        $clientesParaFiltro = $clienteModel->getAll();
        $pageTitle = 'Todos os Processos';
        $currentVendedorId = $vendedorId;
        $filtrosAtuais = $this->cleanFilterValues($_GET ?? []);

        require_once __DIR__ . '/../views/layouts/header.php';
        require_once __DIR__ . '/../views/vendedor_dashboard/lista_processos.php';
        require_once __DIR__ . '/../views/layouts/footer.php';
    }

    private function buildFiltersFromRequest(array $request): array
    {
        $allowedKeys = ['titulo', 'cliente_id', 'os_numero', 'tipo_servico', 'status', 'data_inicio', 'data_fim', 'filtro_card'];
        $filters = [];

        foreach ($allowedKeys as $key) {
            if (!isset($request[$key])) {
                continue;
            }

            $value = is_string($request[$key]) ? trim($request[$key]) : $request[$key];
            if ($value === '' || $value === null) {
                continue;
            }

            if ($key === 'cliente_id') {
                $intValue = (int) $value;
                if ($intValue > 0) {
                    $filters[$key] = $intValue;
                }
                continue;
            }

            $filters[$key] = $value;
        }

        if (!empty($filters['status'])) {
            $statusInfo = DashboardProcessFormatter::normalizeStatusInfo($filters['status']);
            $filters['status'] = $statusInfo['label'];
        }

        return $filters;
    }

    private function cleanFilterValues(array $request): array
    {
        $allowedKeys = ['titulo', 'cliente_id', 'os_numero', 'tipo_servico', 'status', 'data_inicio', 'data_fim', 'filtro_card'];
        $result = [];

        foreach ($allowedKeys as $key) {
            if (!isset($request[$key]) || is_array($request[$key])) {
                continue;
            }

            $trimmed = trim((string) $request[$key]);
            if ($trimmed === '') {
                continue;
            }

            if ($key === 'status') {
                $statusInfo = DashboardProcessFormatter::normalizeStatusInfo($trimmed);
                $result[$key] = $statusInfo['label'];
                continue;
            }

            $result[$key] = $trimmed;
        }

        return $result;
    }

    private function buildListFilters(array $request): array
    {
        $allowedKeys = ['titulo', 'cliente_id', 'status', 'data_inicio', 'data_fim'];
        $filters = [];

        foreach ($allowedKeys as $key) {
            if (!isset($request[$key])) {
                continue;
            }

            $value = is_string($request[$key]) ? trim($request[$key]) : $request[$key];
            if ($value === '' || $value === null) {
                continue;
            }

            if ($key === 'cliente_id') {
                $intValue = (int) $value;
                if ($intValue > 0) {
                    $filters[$key] = $intValue;
                }
                continue;
            }

            $filters[$key] = $value;
        }

        return $filters;
    }

    private function resolveVendedorContext(): array
    {
        $vendedorModel = new Vendedor($this->pdo);
        $userPerfil = $_SESSION['user_perfil'] ?? '';

        if (in_array($userPerfil, ['admin', 'gerencia', 'supervisor'], true) && isset($_GET['vendedor_id'])) {
            $vendedorId = (int) $_GET['vendedor_id'];
            $vendedor   = $vendedorModel->getById($vendedorId);
            if (!$vendedor) {
                die('Erro: Vendedor não encontrado.');
            }

            return [$vendedorId, (int) $vendedor['user_id'], (float) $vendedor['percentual_comissao']];
        }

        $userId = (int) ($_SESSION['user_id'] ?? 0);
        $vendedor = $vendedorModel->getByUserId($userId);
        if (!$vendedor) {
            die('Erro: Vendedor não encontrado.');
        }

        return [(int) $vendedor['id'], $userId, (float) $vendedor['percentual_comissao']];
    }

    private function formatDateFilter(?string $value, string $type): ?string
    {
        if (empty($value)) {
            return null;
        }

        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return null;
        }

        $date = date('Y-m-d', $timestamp);

        return $type === 'start' ? $date . ' 00:00:00' : $date . ' 23:59:59';
    }

    private function appendCommissions(array $processos, int $vendedorId, Comissao $comissaoModel): array
    {
        foreach ($processos as &$processo) {
            $processoId = (int) ($processo['id'] ?? 0);
            $processo['comissaoVendedor'] = $comissaoModel->getCommissionByProcessAndUser($processoId, $vendedorId);

            $sdrId = isset($processo['sdr_id']) ? (int) $processo['sdr_id'] : null;
            $processo['comissaoSdr'] = $sdrId ? $comissaoModel->getCommissionByProcessAndUser($processoId, $sdrId) : 0.0;
        }

        unset($processo);

        return $processos;
    }
}