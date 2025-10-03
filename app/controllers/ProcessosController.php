<?php
/**
 * @file /app/controllers/ProcessosController.php
 * @description Controller principal para gerir todas as operações relacionadas a Processos.
 * Inclui CRUD, fluxos especializados, ações AJAX e integração com serviços externos.
 */

// Seus includes e a declaração da classe estão corretos.
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../models/Processo.php';
require_once __DIR__ . '/../models/Documento.php';
require_once __DIR__ . '/../models/Cliente.php';
require_once __DIR__ . '/../models/Vendedor.php';
require_once __DIR__ . '/../models/Tradutor.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../services/ContaAzulService.php';
require_once __DIR__ . '/../models/Configuracao.php';
require_once __DIR__ . '/../models/CategoriaFinanceira.php'; 
require_once __DIR__ . '/../models/LancamentoFinanceiro.php';
require_once __DIR__ . '/../services/EmailService.php';
require_once __DIR__ . '/../models/Notificacao.php'; 


class ProcessosController
{
    private $pdo;
    private $processoModel;
    private $clienteModel;
    private $vendedorModel;
    private $tradutorModel;
    private $userModel;
    private $documentoModel;
    private $configModel;
    private $contaAzulService;
    private $notificacaoModel; 

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
        $this->processoModel = new Processo($pdo);
        $this->clienteModel = new Cliente($pdo);
        $this->vendedorModel = new Vendedor($pdo);
        $this->tradutorModel = new Tradutor($pdo);
        $this->userModel = new User($pdo);
        $this->documentoModel = new Documento($pdo);
        $this->configModel = new Configuracao($this->pdo);
        $this->contaAzulService = new ContaAzulService($this->configModel);
        $this->notificacaoModel = new Notificacao($pdo);
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }

    }

    private function auth_check() {
        if (!isset($_SESSION['user_id'])) {
            header('Location: /login.php');
            exit();
        }
    }

    protected function render($view, $data = []) {
        extract($data);
        require_once __DIR__ . '/../views/layouts/header.php';
        require_once __DIR__ . '/../views/processos/' . $view . '.php';
        require_once __DIR__ . '/../views/layouts/footer.php';
    }

    
    public function index()
    {
        $pageTitle = "Gestão de Processos";
        $processos = $this->processoModel->getAll();
        
        require_once __DIR__ . '/../views/layouts/header.php';
        require_once __DIR__ . '/../views/processos/lista.php';
        require_once __DIR__ . '/../views/layouts/footer.php';
    }

    public function view($id)
    {
        if (!$id) {
            header('Location: dashboard.php');
            exit();
        }
        $processoData = $this->processoModel->getById($id);
        if (!$processoData) {
            $_SESSION['error_message'] = "Processo não encontrado!";
            header('Location: dashboard.php');
            exit();
        }

        $anexos = $this->processoModel->getAnexosPorCategoria($id, 'anexo');
        $comprovantes = $this->processoModel->getAnexosPorCategoria($id, 'comprovante');
        $pageTitle = "Detalhes: " . htmlspecialchars($processoData['processo']['titulo']);
        $processo = $processoData['processo'];
        $documentos = $processoData['documentos'];
        $tradutores = $this->tradutorModel->getAll();   
        $comentarios = $this->processoModel->getComentariosByProcessoId($id);

        $clientId = $this->configModel->getSetting('conta_azul_client_id');
        $clientSecret = $this->configModel->getSetting('conta_azul_client_secret');
        $isContaAzulConnected = !empty($clientId) && !empty($clientSecret);
        $this->render('detalhe', [
            'processo' => $processoData['processo'],
            'documentos' => $processoData['documentos'],
            'anexos' => $anexos, // Envia os anexos para a view
            'tradutores' => $this->tradutorModel->getAll(),
            'comprovantes' => $comprovantes,
            'comentarios' => $this->processoModel->getComentariosByProcessoId($id),
            'isContaAzulConnected' => !empty($this->configModel->getSetting('conta_azul_client_id')),
            'pageTitle' => $pageTitle
        ]);

        require_once __DIR__ . '/../views/layouts/header.php';
        require_once __DIR__ . '/../views/processos/detalhe.php';
        require_once __DIR__ . '/../views/layouts/footer.php';
    }

    public function create()
    {
        $pageTitle = "Cadastrar Novo Processo";
        $clientes = $this->clienteModel->getAll();
        $vendedores = $this->vendedorModel->getAll();
        $tradutores = $this->tradutorModel->getAll();
        $processo = null;
        $documentos = [];

        $categoriaModel = new CategoriaFinanceira($this->pdo);
        $tipos_traducao = $categoriaModel->getReceitasPorServico('Tradução');
        $tipos_crc = $categoriaModel->getReceitasPorServico('CRC');

        require_once __DIR__ . '/../views/layouts/header.php';
        require_once __DIR__ . '/../views/processos/form.php';
        require_once __DIR__ . '/../views/layouts/footer.php';
    }

public function store()
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $id_existente = $_POST['id'] ?? null;
        
        // --- LÓGICA DE CRIAÇÃO (quando o formulário não envia um ID) ---
        if (empty($id_existente)) {
            // ---- CRIAÇÃO DE UM NOVO PROCESSO ----
            $novo_id = $this->processoModel->create($_POST, $_FILES);
            
            if ($novo_id) {
                // Busca todos os dados do processo recém-criado
                $dados_completos = $this->processoModel->getById($novo_id);
                $processo = $dados_completos['processo'];

                // Tenta enviar o e-mail e notificações
                try {
                    $destinatarios = $this->configModel->get('alert_emails');
                    if (!empty($destinatarios)) {
                        $assunto = "Novo Orçamento #{$processo['orcamento_numero']} - Cliente: {$processo['nome_cliente']}";
                        
                        // Corpo do E-mail (mantido como no seu original)
                        $corpo = "
                        <!DOCTYPE html>
                        <html>
                        <head>
                            <meta charset='UTF-8'>
                            <style>
                                body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; margin: 0; padding: 0; background-color: #f4f4f7; }
                                .container { width: 100%; max-width: 600px; margin: 20px auto; background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
                                .header { background-color: #28a745; color: #ffffff; padding: 20px; text-align: center; }
                                .header h1 { margin: 0; font-size: 24px; }
                                .content { padding: 30px; }
                                .content p { color: #555555; line-height: 1.6; }
                                .details-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                                .details-table td { padding: 12px 15px; border-bottom: 1px solid #eeeeee; }
                                .details-table tr:nth-child(even) { background-color: #f9f9f9; }
                                .details-table td:first-child { font-weight: bold; color: #333333; width: 180px; }
                                .button-container { text-align: center; margin-top: 30px; text-decoration: none; }
                                .button { display: inline-block; padding: 12px 25px; background-color: #28a745; color: #ffffff; text-decoration: none; border-radius: 5px; font-weight: bold; }
                                .footer { background-color: #f4f4f7; padding: 20px; text-align: center; color: #888888; font-size: 12px; }
                            </style>
                        </head>
                        <body>
                            <div class='container'>
                                <div class='header'>
                                    <h1>Novo Serviço Rápido</h1>
                                </div>
                                <div class='content'>
                                    <p>Um novo serviço foi cadastrado no sistema. Seguem os detalhes:</p>
                                    <table class='details-table'>
                                        <tr>
                                            <td>Orçamento Nº:</td>
                                            <td>" . htmlspecialchars($processo['orcamento_numero']) . "</td>
                                        </tr>
                                        <tr>
                                            <td>Cliente:</td>
                                            <td>" . htmlspecialchars($processo['nome_cliente']) . "</td>
                                        </tr>
                                        <tr>
                                            <td>Serviço(s):</td>
                                            <td>" . htmlspecialchars($processo['categorias_servico'] ?? 'Não informado') . "</td>
                                        </tr>
                                        <tr>
                                            <td>Tradutor:</td>
                                            <td>" . htmlspecialchars($processo['nome_tradutor'] ?? 'Não definido') . "</td>
                                        </tr>
                                        <tr>
                                            <td>Valor Total:</td>
                                            <td><strong>R$ " . number_format($processo['valor_total'], 2, ',', '.') . "</strong></td>
                                        </tr>
                                    </table>
                                    <div class='button-container'>
                                        <a href='https://" . $_SERVER['HTTP_HOST'] . "/processos.php?action=view&id={$novo_id}' class='button'>
                                        Ver Detalhes no Sistema
                                        </a>
                                    </div>
                                </div>
                                <div class='footer'>
                                    Este é um e-mail automático.
                                </div>
                            </div>
                        </body>
                        </html>
                        ";

                        $emailService = new EmailService($this->pdo);
                        $emailService->sendEmail($destinatarios, $assunto, $corpo);
                    }
                    
                    // Lógica de Notificação
                    $mensagem = "Novo orçamento pendente (#{$processo['orcamento_numero']}) de {$processo['nome_cliente']}.";
                    $link = "/processos.php?action=view&id={$novo_id}";
                    $gerentes_ids = $this->userModel->getIdsByPerfil(['admin', 'gerencia']); 
                    foreach ($gerentes_ids as $gerente_id) {
                        $this->notificacaoModel->criar($gerente_id, $_SESSION['user_id'], $mensagem, $link);
                    }

                } catch (Exception $e) {
                    error_log("FALHA DE E-MAIL/NOTIFICAÇÃO (Orçamento #{$novo_id}): " . $e->getMessage());
                }

                $_SESSION['message'] = "Processo cadastrado com sucesso!";
            } else {
                $_SESSION['error_message'] = "Erro ao criar o processo no banco de dados.";
            }

            // REDIRECIONAMENTO PARA NOVOS PROCESSOS
            if (isset($_SESSION['user_perfil']) && $_SESSION['user_perfil'] === 'vendedor') {
                header('Location: dashboard_vendedor.php');
            } else {
                header('Location: dashboard.php');
            }
            exit;

        } 
        // --- LÓGICA DE ATUALIZAÇÃO (quando o formulário envia um ID) ---
        else {
            $this->processoModel->update($id_existente, $_POST, $_FILES);
            $_SESSION['message'] = "Processo atualizado com sucesso!";

            // REDIRECIONAMENTO CORRETO APÓS ATUALIZAR
            header('Location: processos.php?action=view&id=' . $id_existente);
            exit;
        }
    }
}
    public function edit($id)
    {
        $this->auth_check();
        if (!$id) {
            header('Location: dashboard.php');
            exit();
        }

        $processoData = $this->processoModel->getById($id);
        if (!$processoData) {
            $_SESSION['error_message'] = "Processo não encontrado!";
            header('Location: dashboard.php');
            exit();
        }

        // Define a variável $processo para facilitar o acesso
        $anexos = $this->processoModel->getAnexosPorCategoria($id, 'anexo');
        $comprovantes = $this->processoModel->getAnexosPorCategoria($id, 'comprovante');
        $processo = $processoData['processo'];

        if ($_SESSION['user_perfil'] === 'vendedor') {
            // Busca o ID do usuário associado ao vendedor do processo
            $vendedor_user_id = $this->vendedorModel->getUserIdByVendedorId($processo['vendedor_id']);

            // A ÚNICA REGRA: Vendedor só pode editar o próprio orçamento.
            if ($vendedor_user_id != $_SESSION['user_id']) {
                $_SESSION['error_message'] = "Você não tem permissão para editar este orçamento.";
                header('Location: dashboard.php');
                exit();
            }
        } 
        // Garante que apenas admin e gerência possam acessar
        elseif (!in_array($_SESSION['user_perfil'], ['admin', 'gerencia'])) {
            $_SESSION['error_message'] = "Você não tem permissão para acessar esta página.";
            header('Location: dashboard.php');
            exit();
        }

        // --- FIM DA VERIFICAÇÃO DE PERMISSÃO ---

        // 3. O restante do seu código original continua aqui, pois a permissão já foi validada
        $pageTitle = "Editar Processo: " . htmlspecialchars($processo['titulo']);
        $documentos = $processoData['documentos'];
        $clientes = $this->clienteModel->getAll();
        $vendedores = $this->vendedorModel->getAll();
        $tradutores = $this->tradutorModel->getAll();

        $categoriaModel = new CategoriaFinanceira($this->pdo);
        $tipos_traducao = $categoriaModel->getReceitasPorServico('Tradução');
        $tipos_crc = $categoriaModel->getReceitasPorServico('CRC');
        $this->render('form', [
            'processo' => $processo,
            'documentos' => $processoData['documentos'],
            'anexos' => $anexos, // Envia os anexos para a view
            'comprovantes' => $comprovantes,
            'clientes' => $this->clienteModel->getAll(),
            'vendedores' => $this->vendedorModel->getAll(),
            'tradutores' => $this->tradutorModel->getAll(),
            'tipos_traducao' => $categoriaModel->getReceitasPorServico('Tradução'),
            'tipos_crc' => $categoriaModel->getReceitasPorServico('CRC'),
            'pageTitle' => $pageTitle
        ]);


        $_SESSION['redirect_after_oauth'] = $_SERVER['REQUEST_URI'];

        require_once __DIR__ . '/../views/layouts/header.php';
        require_once __DIR__ . '/../views/processos/form.php';
        require_once __DIR__ . '/../views/layouts/footer.php';
    }
    public function delete($id)
    {
        // Validação do ID
        if (!$id) {
            $_SESSION['error_message'] = "ID do processo não fornecido.";
            header('Location: dashboard.php');
            exit();
        }

        // A verificação de permissão já acontece no arquivo processos.php,
        // mas é uma boa prática garantir a segurança também no método.
        if (!in_array($_SESSION['user_perfil'], ['admin', 'gerencia'])) {
            $_SESSION['error_message'] = "Você não tem permissão para excluir processos.";
            header('Location: dashboard.php');
            exit();
        }

        // Tenta deletar o processo usando o Model
        if ($this->processoModel->deleteProcesso($id)) {
            $_SESSION['success_message'] = "Processo excluído com sucesso!";
        } else {
            $_SESSION['error_message'] = "Erro ao excluir o processo.";
        }
        
        // Redireciona para a lista de processos ou dashboard
        header('Location: dashboard.php');
        exit();
    }
    public function storeServicoRapido()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $_POST['status_processo'] = 'Em Andamento';
            // A função create agora é unificada e lida com ambos os formulários
            $processoId = $this->processoModel->create($_POST, $_FILES);

            if ($processoId) {
                try {
                    $destinatarios = $this->configModel->get('alert_emails');
                    if (!empty($destinatarios)) {
                        // A LÓGICA DE RELATÓRIO DETALHADO TAMBÉM É APLICADA AQUI
                        $dados_completos = $this->processoModel->getById($processoId);
                        $processo = $dados_completos['processo'];

                        $assunto = "Novo Serviço Rápido #{$processoId} - Cliente: {$processo['nome_cliente']}";
                        $corpo = "
                        <!DOCTYPE html>
                        <html>
                        <head>
                            <meta charset='UTF-8'>
                            <style>
                                body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; margin: 0; padding: 0; background-color: #f4f4f7; }
                                .container { width: 100%; max-width: 600px; margin: 20px auto; background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
                                .header { background-color: #28a745; color: #ffffff; padding: 20px; text-align: center; }
                                .header h1 { margin: 0; font-size: 24px; }
                                .content { padding: 30px; }
                                .content p { color: #555555; line-height: 1.6; }
                                .details-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                                .details-table td { padding: 12px 15px; border-bottom: 1px solid #eeeeee; }
                                .details-table tr:nth-child(even) { background-color: #f9f9f9; }
                                .details-table td:first-child { font-weight: bold; color: #333333; width: 180px; }
                                .button-container { text-align: center; margin-top: 30px; text-decoration: none; }
                                .button { display: inline-block; padding: 12px 25px; background-color: #28a745; color: #ffffff; text-decoration: none; border-radius: 5px; font-weight: bold; }
                                .footer { background-color: #f4f4f7; padding: 20px; text-align: center; color: #888888; font-size: 12px; }
                            </style>
                        </head>
                        <body>
                            <div class='container'>
                                <div class='header'>
                                    <h1>Novo Serviço Rápido</h1>
                                </div>
                                <div class='content'>
                                    <p>Um novo serviço foi cadastrado no sistema. Seguem os detalhes:</p>
                                    <table class='details-table'>
                                        <tr>
                                            <td>Orçamento Nº:</td>
                                            <td>" . htmlspecialchars($processo['orcamento_numero']) . "</td>
                                        </tr>
                                        <tr>
                                            <td>Cliente:</td>
                                            <td>" . htmlspecialchars($processo['nome_cliente']) . "</td>
                                        </tr>
                                        <tr>
                                            <td>Serviço(s):</td>
                                            <td>" . htmlspecialchars($processo['categorias_servico'] ?? 'Não informado') . "</td>
                                        </tr>
                                        <tr>
                                            <td>Tradutor:</td>
                                            <td>" . htmlspecialchars($processo['nome_tradutor'] ?? 'Não definido') . "</td>
                                        </tr>
                                        <tr>
                                            <td>Valor Total:</td>
                                            <td><strong>R$ " . number_format($processo['valor_total'], 2, ',', '.') . "</strong></td>
                                        </tr>
                                    </table>
                                    <div class='button-container'>
                                        <a href='https://" . $_SERVER['HTTP_HOST'] . "/processos.php?action=view&id={$processoId}' class='button'>
                                        Ver Detalhes no Sistema
                                        </a>
                                    </div>
                                </div>
                                <div class='footer'>
                                    Este é um e-mail automático.
                                </div>
                            </div>
                        </body>
                        </html>
                        ";
                        
                        $emailService = new EmailService($this->pdo);
                        $emailService->sendEmail($destinatarios, $assunto, $corpo);
                    }
                } catch (Exception $e) {
                    error_log("FALHA DE E-MAIL (Serviço Rápido #{$processoId}): " . $e->getMessage());
                }
                $_SESSION['success_message'] = "Serviço cadastrado com sucesso!";
            } else {
                $_SESSION['error_message'] = "Erro ao cadastrar o serviço.";
            }
            
            header('Location: dashboard.php');
            exit();
        }
    }
    // =======================================================================
    // AÇÕES AJAX E PARCIAIS
    // =======================================================================
    
    public function storeCommentAjax()
    {
        header('Content-Type: application/json');
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['comentario'])) {
            $data = [
                'processo_id' => $_POST['processo_id'],
                'user_id' => $_SESSION['user_id'],
                'comentario' => $_POST['comentario']
            ];

            if ($this->processoModel->addComentario($data)) {
                echo json_encode(['success' => true, 'comment' => [
                    'author' => htmlspecialchars($_SESSION['user_nome']),
                    'date' => date('d/m/Y H:i'),
                    'text' => nl2br(htmlspecialchars($_POST['comentario']))
                ]]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Erro ao guardar o comentário.']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'O comentário não pode estar vazio.']);
        }
        exit();
    }
    
    public function updateEtapas()
    {
        header('Content-Type: application/json');
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
            $id = $_POST['id'];

            if (!empty($_POST['data_envio_cartorio'])) {
                $_POST['status_processo'] = 'Finalizado';
            }

            if ($this->processoModel->updateEtapas($id, $_POST)) {
                $processoData = $this->processoModel->getById($id);
                $processo = $processoData['processo'];

                $updated_data = [
                    'nome_tradutor' => htmlspecialchars($processo['nome_tradutor'] ?? 'FATTO'),
                    'data_inicio_traducao' => isset($processo['data_inicio_traducao']) ? date('d/m/Y', strtotime($processo['data_inicio_traducao'])) : 'Pendente',
                    'traducao_modalidade' => htmlspecialchars($processo['traducao_modalidade'] ?? 'N/A'),
                    'traducao_prazo_data_formatted' => $this->getPrazoCountdown($processo['traducao_prazo_data']),
                    'assinatura_tipo' => htmlspecialchars($processo['assinatura_tipo'] ?? 'N/A'),
                    'data_envio_assinatura' => isset($processo['data_envio_assinatura']) ? date('d/m/Y', strtotime($processo['data_envio_assinatura'])) : 'Pendente',
                    'data_devolucao_assinatura' => isset($processo['data_devolucao_assinatura']) ? date('d/m/Y', strtotime($processo['data_devolucao_assinatura'])) : 'Pendente',
                    'finalizacao_tipo' => htmlspecialchars($processo['finalizacao_tipo'] ?? 'N/A'),
                    'data_envio_cartorio' => isset($processo['data_envio_cartorio']) ? date('d/m/Y', strtotime($processo['data_envio_cartorio'])) : 'Pendente',
                    'status_processo' => htmlspecialchars($processo['status_processo']),
                    'status_processo_classes' => $this->getStatusClasses($processo['status_processo']),
                    'os_numero_conta_azul' => htmlspecialchars($processo['os_numero_conta_azul'] ?? 'Não definida')
                ];

                echo json_encode(['success' => true, 'message' => 'Etapas atualizadas com sucesso!', 'updated_data' => $updated_data]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Erro ao atualizar as etapas.']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Requisição inválida.']);
        }
        exit();
    }

public function changeStatus()
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $id = $_POST['id'] ?? null;
        $status = $_POST['status_processo'] ?? null;

        if (!$id || !$status) {
            $_SESSION['error_message'] = "Dados insuficientes para atualizar o status.";
            header('Location: dashboard.php');
            exit();
        }

        // 1. Busca os dados do processo ANTES de qualquer ação
        $res = $this->processoModel->getById($id);
        if (!$res || !isset($res['processo'])) {
            $_SESSION['error_message'] = "Processo não encontrado.";
            header('Location: dashboard.php');
            exit();
        }
        $processo = $res['processo'];
        $statusAntigo = $processo['status_processo'];

        // --- INÍCIO DA VERIFICAÇÃO DE PERMISSÃO ---
        if ($_SESSION['user_perfil'] === 'vendedor') {
            $vendedor_user_id = $this->vendedorModel->getUserIdByVendedorId($processo['vendedor_id']);

            // A ÚNICA REGRA: Vendedor só pode alterar o status do próprio orçamento.
            if ($vendedor_user_id != $_SESSION['user_id']) {
                $_SESSION['error_message'] = "Você não tem permissão para executar esta ação.";
                header('Location: dashboard.php');
                exit();
            }
            // Adicionalmente, um vendedor só pode reenviar para análise.
            if ($status !== 'Orçamento Pendente') {
                $_SESSION['error_message'] = "Como vendedor, você só pode reenviar o orçamento para análise.";
                header('Location: processos.php?action=view&id=' . $id);
                exit();
            }
        } 
        // Garante que apenas admin e gerência possam fazer outras alterações
        elseif (!in_array($_SESSION['user_perfil'], ['admin', 'gerencia'])) {
            $_SESSION['error_message'] = "Você não tem permissão para acessar esta página.";
            header('Location: dashboard.php');
            exit();
        }
        // --- FIM DA VERIFICAÇÃO DE PERMISSÃO ---

        // 2. O restante da sua lógica original continua aqui
        $dataToUpdate = [
            'status_processo'        => $status,
            'os_numero_conta_azul'   => $_POST['os_numero_conta_azul'] ?? $processo['os_numero_conta_azul'],
            'data_inicio_traducao'   => $_POST['data_inicio_traducao'] ?? $processo['data_inicio_traducao'],
            'traducao_prazo_tipo'    => $_POST['traducao_prazo_tipo'] ?? $processo['traducao_prazo_tipo'],
            'traducao_prazo_dias'    => $_POST['traducao_prazo_dias'] ?? $processo['traducao_prazo_dias'],
            'traducao_prazo_data'    => $_POST['traducao_prazo_data'] ?? $processo['traducao_prazo_data'],
        ];

        // Validação de campos para gerência (seu código original mantido)
        if (in_array($_SESSION['user_perfil'], ['admin', 'gerencia']) && $statusAntigo === 'Orçamento' && in_array($status, ['Aprovado', 'Em Andamento'], true)) {
            $erros = [];
            if (empty($dataToUpdate['os_numero_conta_azul'])) $erros[] = 'Informe o número da O.S. (CA).';
            if (empty($dataToUpdate['data_inicio_traducao'])) $erros[] = 'Informe a Data de envio para o Tradutor.';
            // ... (resto da sua validação)
            if (!empty($erros)) {
                $_SESSION['error_message'] = implode('<br>', $erros);
                header('Location: processos.php?action=view&id=' . $id);
                exit();
            }
        }

        // 3. Atualiza o processo e envia as notificações
        if ($this->processoModel->updateEtapas($id, $dataToUpdate)) {
            $_SESSION['success_message'] = "Status do processo atualizado com sucesso!";
            
            $link = "/processos.php?action=view&id={$id}";
            $remetente_id = $_SESSION['user_id'];

            // Notifica gerentes quando o vendedor reenvia
            if ($status === 'Orçamento Pendente' && $statusAntigo === 'Recusado') {
                // LIMPA NOTIFICAÇÕES ANTERIORES DESTE PROCESSO (EX: A DE RECUSA)
                $this->notificacaoModel->deleteByLink($link);

                $mensagem = "Orçamento #{$processo['orcamento_numero']} foi ajustado e reenviado para análise.";
                $gerentes_ids = $this->userModel->getIdsByPerfil(['admin', 'gerencia']);
                foreach ($gerentes_ids as $gerente_id) {
                    $this->notificacaoModel->criar($gerente_id, $remetente_id, $mensagem, $link);
                }
            } 
            // Notifica vendedor quando a gerência aprova/recusa
            else {
                $vendedor_user_id = $this->vendedorModel->getUserIdByVendedorId($processo['vendedor_id']);
                $mensagem = null;
                if ($status === 'Aprovado') {
                    $mensagem = "Seu orçamento #{$processo['orcamento_numero']} foi APROVADO!";
                    
                    // LIMPA ALERTAS ANTIGOS DESTE PROCESSO
                    $this->notificacaoModel->deleteByLink($link);

                } elseif ($status === 'Recusado') {
                    $mensagem = "Seu orçamento #{$processo['orcamento_numero']} foi RECUSADO. Ajuste-o.";
                }
                if ($vendedor_user_id && $mensagem) {
                    $this->notificacaoModel->criar($vendedor_user_id, $remetente_id, $mensagem, $link);
                }
            }
        } else {
            $_SESSION['error_message'] = "Falha ao atualizar o processo.";
        }

        header('Location: processos.php?action=view&id=' . $id);
        exit();
    }
}
    // =======================================================================
    // MÉTODOS PRIVADOS DE APOIO (HELPERS) E INTEGRAÇÃO CONTA AZUL
    // =======================================================================

    private function getStatusClasses($status)
    {
        switch ($status) {
            case 'Orçamento': return 'bg-yellow-100 text-yellow-800';
            case 'Aprovado': return 'bg-blue-100 text-blue-800';
            case 'Em Andamento': return 'bg-cyan-100 text-cyan-800';
            case 'Finalizado': return 'bg-green-100 text-green-800';
            case 'Arquivado': return 'bg-gray-200 text-gray-600';
            case 'Cancelado': return 'bg-red-100 text-red-800';
            default: return 'bg-gray-100 text-gray-800';
        }
    }

    private function getPrazoCountdown($dateString)
    {
        if (empty($dateString)) {
            return '<span class="text-gray-500">Não definido</span>';
        }
        try {
            $today = new DateTime();
            $today->setTime(0, 0, 0);
            $prazoDate = new DateTime($dateString);
            $prazoDate->setTime(0, 0, 0);

            if ($prazoDate < $today) {
                $diff = $today->diff($prazoDate);
                return '<span class="font-bold text-red-500">Atrasado há ' . $diff->days . ' dia(s)</span>';
            } elseif ($prazoDate == $today) {
                return '<span class="font-bold text-blue-500">Entrega hoje</span>';
            } else {
                $diff = $today->diff($prazoDate);
                return '<span class="font-bold text-green-600">Faltam ' . ($diff->days) . ' dia(s)</span>';
            }
        } catch (Exception $e) {
            return '<span class="text-gray-500">Data inválida</span>';
        }
    }
    
        public function createContaAzulQuote()
    {
        $this->handleContaAzulCreation('quote');
    }

    /**
     * Action para criar uma Venda na Conta Azul.
     */
public function createContaAzulSale()
    {
        $processoId = $_GET['id'] ?? null;
        if (!$processoId) {
            $_SESSION['error_message'] = "ID do processo não fornecido.";
            header('Location: processos.php');
            exit();
        }

        try {
            // --- Bloco 1: Coleta de Dados Locais ---
            $data = $this->processoModel->getById($processoId);
            if (!$data) throw new Exception("Processo local não encontrado.");
            
            $processo = $data['processo'];
            $cliente = $this->clienteModel->getById($processo['cliente_id']);
            $documentos = $data['documentos'];

            if (!$cliente) throw new Exception("Cliente local não encontrado para este processo.");
            if (empty($documentos)) throw new Exception("O processo não possui itens para gerar a venda.");

            // --- Bloco 2: Validação do Cliente (Usa o UUID já vinculado) ---
            $contaAzulClienteId = $cliente['conta_azul_uuid'] ?? null;
            if (empty($contaAzulClienteId)) {
                throw new Exception("Operação cancelada. O cliente '{$cliente['nome_cliente']}' não possui um UUID da Conta Azul vinculado. Por favor, vá na tela de edição do cliente e clique no botão 'Buscar e Vincular UUID' primeiro.");
            }
            
            // --- Bloco 3: CRIAÇÃO de um novo serviço para cada item ---
            $itensPayload = [];
            $calculatedTotal = 0.0;

            foreach ($documentos as $doc) {
                // Monta o payload para o SERVIÇO com todos os campos corretos.
                $nomeDoServico = $doc['tipo_documento'] . ' - Proc ' . $processo['id'];
                $newServicePayload = [
                    "descricao"    => $nomeDoServico,
                    "preco"        => (float)($doc['valor_unitario'] ?? 0),
                    "status"       => "ATIVO",
                    "tipo_servico" => "TOMADO" // <-- Correção final aplicada aqui
                ];
                
                // Chama o método correto para criar o serviço
                $createdService = $this->contaAzulService->createService($newServicePayload);

                // A resposta da API para serviço criado pode ter 'id' ou 'id_servico'
                if (isset($createdService['id']) || isset($createdService['id_servico'])) {
                    $contaAzulServicoId = $createdService['id'] ?? $createdService['id_servico'];
                    
                    $quantidade = (float)($doc['quantidade'] ?? 1);
                    if ($quantidade <= 0) $quantidade = 1; 
                    $valorUnitario = (float)($doc['valor_unitario'] ?? 0);

                    $itensPayload[] = ["id" => $contaAzulServicoId, "quantidade" => $quantidade, "valor" => $valorUnitario, "descricao" => $doc['nome_documento']];
                    $calculatedTotal += $quantidade * $valorUnitario;
                } else {
                    throw new Exception("Falha ao criar o serviço '{$doc['tipo_documento']}' na Conta Azul. Resposta da API: " . json_encode($createdService));
                }
            }
            
            // --- Bloco 4: Montagem e Envio da Venda Final ---
            $salePayload = [
                "id_cliente" => $contaAzulClienteId,
                "numero"     => (int)$processo['id'],
                "situacao"   => "EM_ANDAMENTO",
                "data_venda" => date('Y-m-d'),
                "itens"      => $itensPayload,
                "condicao_pagamento" => [
                    "opcao_condicao_pagamento" => "À vista",
                    "tipo_pagamento"         => "DINHEIRO",
                    "parcelas"               => [["data_vencimento" => date('Y-m-d'), "valor" => $calculatedTotal]]
                ]
            ];

            $response = $this->contaAzulService->createSale($salePayload);

            if (isset($response['id']) && isset($response['numero'])) {
                $this->processoModel->updateContaAzulSaleDetails($processoId, $response['id'], $response['numero']);
                $_SESSION['success_message'] = "Venda criada com sucesso na Conta Azul! Número: " . $response['numero'];
            } else {
                $errorMessage = $response['message'] ?? json_encode($response);
                throw new Exception("A API da Conta Azul retornou um erro ao criar a venda: " . $errorMessage);
            }

        } catch (Exception $e) {
            $_SESSION['error_message'] = "Erro: " . $e->getMessage();
        }

        header('Location: processos.php?action=view&id=' . $processoId);
        exit();
    }

    /**
     * Exibe a página com a lista de orçamentos pendentes para aprovação.
     */
    public function listarPendentes()
    {
        $pageTitle = "Aprovação de Orçamentos";
        // Usamos o model para buscar apenas os processos com status 'Orçamento Pendente'
        $processos_pendentes = $this->processoModel->getByStatus('Orçamento Pendente');
        
        require_once __DIR__ . '/../views/layouts/header.php';
        require_once __DIR__ . '/../views/processos/lista_pendentes.php';
        require_once __DIR__ . '/../views/layouts/footer.php';
    }

    /**
     * Aprova um orçamento, mudando seu status e notificando o vendedor.
     */
public function aprovarOrcamento()
{
    $id = $_GET['id'] ?? null;
    if (!$id) { header('Location: dashboard.php'); exit; } // Segurança básica

    $data = ['status_processo' => 'Aprovado'];
    
    if ($this->processoModel->updateStatus($id, $data)) {
        // Lógica de Notificação para o Vendedor (mantida)
        $processo = $this->processoModel->getById($id)['processo'];
        if ($processo && !empty($processo['vendedor_id'])) {
            $vendedor_user_id = $this->vendedorModel->getUserIdByVendedorId($processo['vendedor_id']);
            if ($vendedor_user_id) {
                $mensagem = "Seu orçamento #{$processo['orcamento_numero']} foi APROVADO!";
                $link = "/processos.php?action=view&id={$id}";
                $this->notificacaoModel->criar($vendedor_user_id, $_SESSION['user_id'], $mensagem, $link);
                $this->notificacaoModel->deleteByLink($link); // Limpa alertas antigos
            }
        }
        $_SESSION['success_message'] = "Orçamento aprovado com sucesso!";
    } else {
        $_SESSION['error_message'] = "Erro ao aprovar o orçamento.";
    }
    
    // Redireciona de volta para a página de detalhes do processo
    header('Location: processos.php?action=view&id=' . $id);
    exit;
}

public function recusarOrcamento()
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: dashboard.php'); exit; }
    
    $id = $_POST['id'] ?? null;
    $motivo = $_POST['motivo_recusa'] ?? '';
    
    if (!$id || empty($motivo)) {
        $_SESSION['error_message'] = "O motivo da recusa é obrigatório.";
        // Redireciona de volta em caso de erro
        header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? 'dashboard.php'));
        exit;
    }

    $data = [
        'status_processo' => 'Recusado',
        'motivo_recusa'   => $motivo
    ];

    if ($this->processoModel->updateStatus($id, $data)) {
        // Lógica de Notificação para o Vendedor (mantida)
        $processo = $this->processoModel->getById($id)['processo'];
        if ($processo && !empty($processo['vendedor_id'])) {
            $vendedor_user_id = $this->vendedorModel->getUserIdByVendedorId($processo['vendedor_id']);
            if ($vendedor_user_id) {
                $mensagem = "Orçamento #{$processo['orcamento_numero']} recusado. Por favor, revise-o.";
                $link = "/processos.php?action=view&id={$id}";
                $this->notificacaoModel->deleteByLink($link); // Limpa notificações anteriores
                $this->notificacaoModel->criar($vendedor_user_id, $_SESSION['user_id'], $mensagem, $link);
            }
        }
        $_SESSION['success_message'] = "Orçamento recusado e vendedor notificado.";
    } else {
        $_SESSION['error_message'] = "Erro ao recusar o orçamento.";
    }
    
    // Redireciona de volta para a página de detalhes do processo
    header('Location: processos.php?action=view&id=' . $id);
    exit;
}
        public function createServicoRapido()
    {
        $this->auth_check();
        $pageTitle = "Cadastrar Serviço Rápido";
        // O formulário de serviço rápido precisa da lista de clientes e vendedores
        $clientes = $this->clienteModel->getAll();
        $vendedores = $this->vendedorModel->getAll();
        $tradutores = $this->tradutorModel->getAll();
        $processo = null; // Nenhum processo existente, pois é uma criação

        // Carrega as partes da página: cabeçalho, o formulário específico e o rodapé
        require_once __DIR__ . '/../views/layouts/header.php';
        require_once __DIR__ . '/../views/processos/form_servico_rapido.php';
        require_once __DIR__ . '/../views/layouts/footer.php';
    }

    public function detalhe($id) {
        $this->auth_check();
        $processo_info = $this->processoModel->getById($id);
        
        // CORREÇÃO: Busca os anexos do novo sistema local
        $anexos = $this->processoModel->getAnexos($id);

        $this->render('processos/detalhe', [
            'processo' => $processo_info['processo'],
            'documentos' => $processo_info['documentos'],
            'anexos' => $anexos, // Envia os anexos locais para a view
            'pageTitle' => 'Detalhes do Processo'
        ]);
    }
    public function excluir_anexo($id, $anexo_id) {
        $this->auth_check();
        if (!$id || !$anexo_id) {
            $_SESSION['error_message'] = 'Informações insuficientes para excluir o anexo.';
            header('Location: /dashboard.php');
            exit();
        }

        if ($this->processoModel->deleteAnexo($anexo_id)) {
            $_SESSION['success_message'] = 'Anexo excluído com sucesso.';
        } else {
            $_SESSION['error_message'] = 'Erro ao excluir o anexo.';
        }
        header('Location: processos.php?action=view&id=' . $id);
        exit();
    }


}