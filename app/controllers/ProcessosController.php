<?php
/**
 * @file /app/controllers/ProcessosController.php
 * @description Controller principal para gerir todas as operações relacionadas a Processos.
 * Inclui CRUD, fluxos especializados, ações AJAX e integração com serviços externos.
 */

// Seus includes e a declaração da classe estão corretos.
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
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
    private $emailService;

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
    $this->emailService = new EmailService($pdo); 

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

    public function create($data = [], $files = [])
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

        // =========================================
        // CRIAÇÃO DE NOVO PROCESSO OU SERVIÇO
        // =========================================
        if (empty($id_existente)) {

            // >>> DETECÇÃO DO FLUXO DE SERVIÇO RÁPIDO
            // O formulário de serviço rápido possui o campo oculto 'status_proposto'.
            // Se esse campo estiver definido, trata-se de um serviço rápido e aplicamos
            // a verificação de pendência e notificações de gerência.
            if (isset($_POST['status_proposto'])) {

                $dadosParaSalvar = $_POST;
                $perfilUsuario   = $_SESSION['user_perfil'] ?? '';
                $clienteId       = $dadosParaSalvar['cliente_id'] ?? null;
                // Aceita tanto 'documentos' quanto 'docs' (para edições ou diferentes formatos)
                $documentos      = $dadosParaSalvar['documentos'] ?? ($dadosParaSalvar['docs'] ?? []);

                // Verifica se há pendência (somente para colaborador ou vendedor)
                $pendente = false;
                if ($clienteId && in_array($perfilUsuario, ['colaborador', 'vendedor'])) {
                    $pendente = $this->verificarAlteracaoValorMensalista($clienteId, $documentos);
                }

                // Define o status final conforme o perfil
                if (in_array($perfilUsuario, ['admin', 'gerencia'])) {
                    $dadosParaSalvar['status_processo'] = 'Em andamento';
                } else {
                    $dadosParaSalvar['status_processo'] = $pendente ? 'Pendente' : 'Em andamento';
                }

                // Persiste o serviço rápido
                $processoId = $this->processoModel->create($dadosParaSalvar, $_FILES);

                // Mensagens e notificações
                if ($processoId) {
                    if ($dadosParaSalvar['status_processo'] === 'Pendente') {
                        $_SESSION['message'] = "Serviço enviado para aprovação da gerência.";
                        $this->notificarGerenciaPendencia($processoId, $clienteId, $_SESSION['user_id']);
                    } else {
                        $_SESSION['success_message'] = "Serviço cadastrado com sucesso!";
                    }
                } else {
                    $_SESSION['error_message'] = $_SESSION['error_message'] ?? "Erro ao cadastrar o serviço.";
                }

                // Redireciona e encerra
                header('Location: dashboard.php');
                exit();

            } // <<< FIM DO FLUXO DE SERVIÇO RÁPIDO

            // ============ FLUXO DE ORÇAMENTO NORMAL ============
            // Aqui não definimos status_processo para que o Model use o valor padrão (por exemplo 'Orçamento')
            $novo_id = $this->processoModel->create($_POST, $_FILES);

            if ($novo_id) {
                $_SESSION['message'] = "Processo cadastrado com sucesso!";
            } else {
                if (!isset($_SESSION['error_message'])) {
                    $_SESSION['error_message'] = "Erro ao criar o processo no banco de dados.";
                }
            }
            header('Location: dashboard.php');
            exit();
        }

        // =========================================
        // ATUALIZAÇÃO DE PROCESSO EXISTENTE
        // (Aqui a lógica continua igual, apenas para edição)
        // =========================================
        $dadosParaAtualizar = $_POST;
        $perfilUsuario     = $_SESSION['user_perfil'] ?? '';
        $processoOriginal  = $this->processoModel->getById($id_existente)['processo'];

        $valorAlterado = false;
        if (in_array($perfilUsuario, ['colaborador', 'vendedor'])) {
            $valorAlterado = $this->verificarAlteracaoValorMensalista(
                $dadosParaAtualizar['cliente_id'],
                $dadosParaAtualizar['docs'] ?? []
            );
        }

        if ($processoOriginal['status_processo'] === 'Pendente' && in_array($perfilUsuario, ['admin', 'gerencia'])) {
            $dadosParaAtualizar['status_processo'] = 'Em andamento';
            $this->notificacaoModel->deleteByLink("/processos.php?action=view&id=" . $id_existente);
            $_SESSION['message'] = "Serviço aprovado e status atualizado!";
        } elseif ($valorAlterado) {
            $dadosParaAtualizar['status_processo'] = 'Pendente';
            $_SESSION['message'] = "Alteração salva. O serviço aguarda aprovação da gerência.";
            $cliente    = $this->clienteModel->getById($dadosParaAtualizar['cliente_id']);
            $link       = "/processos.php?action=view&id=" . $id_existente;
            $mensagem   = "O serviço para o cliente '{$cliente['nome']}' foi alterado e precisa de aprovação.";
            $gerentes_ids = $this->userModel->getIdsByPerfil(['admin', 'gerencia']);
            foreach ($gerentes_ids as $gerente_id) {
                $this->notificacaoModel->criar($gerente_id, $_SESSION['user_id'], $mensagem, $link);
            }
        }

        if ($this->processoModel->update($id_existente, $dadosParaAtualizar, $_FILES)) {
            if (!isset($_SESSION['message'])) {
                $_SESSION['success_message'] = "Processo atualizado com sucesso!";
            }
        } else {
            if (!isset($_SESSION['error_message'])) {
                $_SESSION['error_message'] = "Ocorreu um erro ao atualizar o processo.";
            }
        }
        header('Location: processos.php?action=view&id=' . $id_existente);
        exit();
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
        // Obtém todos os dados enviados via POST
        $dadosParaSalvar = $_POST;

        // Perfil do usuário logado
        $perfilUsuario = $_SESSION['user_perfil'] ?? '';

        // Identifica o cliente e os documentos
        $clienteId  = $dadosParaSalvar['cliente_id'] ?? null;
        // Aceita 'documentos' (criação) ou 'docs' (edição/atualização)
        $documentos = $dadosParaSalvar['documentos'] ?? ($dadosParaSalvar['docs'] ?? []);

        // Calcula se há pendência para colaboradores ou vendedores
        $pendente = false;
        if ($clienteId && in_array($perfilUsuario, ['colaborador', 'vendedor'])) {
            $pendente = $this->verificarAlteracaoValorMensalista($clienteId, $documentos);
        }

        // Define o status final conforme o perfil
        // Gerência e admin sempre ficam como 'Em andamento'
        if (in_array($perfilUsuario, ['gerencia', 'admin'])) {
            $dadosParaSalvar['status_processo'] = 'Em andamento';
        } else {
            $dadosParaSalvar['status_processo'] = $pendente ? 'Pendente' : 'Em andamento';
        }

        // Persiste o serviço no banco de dados
        $processoId = $this->processoModel->create($dadosParaSalvar, $_FILES);

        // Notificações e mensagens
        if ($processoId) {
            if ($dadosParaSalvar['status_processo'] === 'Pendente') {
                $_SESSION['message'] = "Serviço enviado para aprovação da gerência.";
                // Dispara e-mails e alertas para gerência e admins
                $this->notificarGerenciaPendencia($processoId, $clienteId, $_SESSION['user_id']);
            } else {
                $_SESSION['success_message'] = "Serviço cadastrado com sucesso!";
            }
        } else {
            $_SESSION['error_message'] = $_SESSION['error_message'] ?? "Erro ao cadastrar o serviço.";
        }

        // Redireciona
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
private function notificarGerenciaPendencia(int $processoId, int $clienteId, int $remetenteId): void
{
    // Obtém o nome do cliente para personalizar a mensagem
    $cliente = $this->clienteModel->getById($clienteId);
    $nomeCliente = $cliente['nome_cliente'] ?? 'Cliente';

    // Monta o link para o detalhe do processo
    $link = "/processos.php?action=view&id=" . $processoId;

    // Busca todos os usuários com perfil admin ou gerencia
    $gerentesIds = $this->userModel->getIdsByPerfil(['admin', 'gerencia']);

    foreach ($gerentesIds as $gerenteId) {
        // Cria notificação interna
        $mensagem = "Novo serviço pendente para o cliente '{$nomeCliente}'.";
        $this->notificacaoModel->criar($gerenteId, $remetenteId, $mensagem, $link);

        // Envia e-mail
        $gerente = $this->userModel->getById($gerenteId);
        if ($gerente && !empty($gerente['email'])) {
            $subject = "Serviço pendente de aprovação";
            $body = "Olá {$gerente['nome_completo']},<br><br>"
                  . "Foi criado um serviço para o cliente <strong>{$nomeCliente}</strong> que está pendente de aprovação.<br>"
                  . "Clique no link a seguir para visualizar e aprovar: <a href=\"{$link}\">Ver serviço</a>.<br><br>"
                  . "Obrigado.";
            try {
                $this->emailService->sendEmail($gerente['email'], $subject, $body);
            } catch (Exception $e) {
                // Registra erro de envio de e-mail, mas não interrompe a execução
                error_log("Erro ao enviar e-mail para {$gerente['email']}: " . $e->getMessage());
            }
        }
    }
}

public function changeStatus()
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $id     = $_POST['id']             ?? null;
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
        $processo     = $res['processo'];
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
        } elseif (!in_array($_SESSION['user_perfil'], ['admin', 'gerencia'])) {
            $_SESSION['error_message'] = "Você não tem permissão para acessar esta página.";
            header('Location: dashboard.php');
            exit();
        }
        // --- FIM DA VERIFICAÇÃO DE PERMISSÃO ---

        // 2. Prepara os campos a atualizar
        $dataToUpdate = [
            'status_processo'      => $status,
            'os_numero_conta_azul' => $_POST['os_numero_conta_azul'] ?? $processo['os_numero_conta_azul'],
            'data_inicio_traducao' => $_POST['data_inicio_traducao'] ?? $processo['data_inicio_traducao'],
            'traducao_prazo_tipo'  => $_POST['traducao_prazo_tipo'] ?? $processo['traducao_prazo_tipo'],
            'traducao_prazo_dias'  => $_POST['traducao_prazo_dias'] ?? $processo['traducao_prazo_dias'],
            'traducao_prazo_data'  => $_POST['traducao_prazo_data'] ?? $processo['traducao_prazo_data'],
        ];

        // 3. Validação extra para gerência/admin (por exemplo, exigir preenchimento de campos quando aprova)
        if (in_array($_SESSION['user_perfil'], ['admin', 'gerencia']) &&
            $statusAntigo === 'Orçamento' &&
            in_array($status, ['Aprovado', 'Em Andamento'], true)
        ) {
            $erros = [];
            if (empty($dataToUpdate['os_numero_conta_azul'])) $erros[] = 'Informe o número da O.S. (CA).';
            if (empty($dataToUpdate['data_inicio_traducao'])) $erros[] = 'Informe a Data de envio para o Tradutor.';
            if (!empty($erros)) {
                $_SESSION['error_message'] = implode(' ', $erros);
                header('Location: processos.php?action=view&id=' . $id);
                exit();
            }
        }

        // 4. Atualiza as etapas e envia notificações apropriadas
        if ($this->processoModel->updateEtapas($id, $dataToUpdate)) {
            $_SESSION['success_message'] = "Status do processo atualizado com sucesso!";

            $link         = "/processos.php?action=view&id={$id}";
            $remetente_id = $_SESSION['user_id'];

            // ================== NOVA LÓGICA DE NOTIFICAÇÃO ==================
            // Se o status for "Orçamento Pendente", notificar SEMPRE os gerentes/admins
            // e limpar notificações antigas referentes a este processo.
            if ($status === 'Orçamento Pendente') {
                // Remove notificações já existentes para este link (se houver)
                $this->notificacaoModel->deleteByLink($link);

                // Cria nova notificação para todos os usuários com perfil admin ou gerência
                $mensagem = "Orçamento #{$processo['orcamento_numero']} está pendente de análise.";
                $gerentes_ids = $this->userModel->getIdsByPerfil(['admin', 'gerencia']);
                foreach ($gerentes_ids as $gerente_id) {
                    $this->notificacaoModel->criar($gerente_id, $remetente_id, $mensagem, $link);
                }
            }
            // Caso contrário, se a gerência aprovar ou recusar, notificar apenas o vendedor
            else {
                $vendedor_user_id = $this->vendedorModel->getUserIdByVendedorId($processo['vendedor_id']);
                $mensagem = null;

                if ($status === 'Aprovado') {
                    $mensagem = "Seu orçamento #{$processo['orcamento_numero']} foi APROVADO!";
                    // Limpa alertas antigos deste processo (para evitar duplicidade)
                    $this->notificacaoModel->deleteByLink($link);

                } elseif ($status === 'Recusado') {
                    $mensagem = "Seu orçamento #{$processo['orcamento_numero']} foi RECUSADO. Ajuste-o.";
                }

                if ($vendedor_user_id && $mensagem) {
                    $this->notificacaoModel->criar($vendedor_user_id, $remetente_id, $mensagem, $link);
                }
            }
            // ================== FIM DA NOVA LÓGICA ==================

        } else {
            $_SESSION['error_message'] = "Falha ao atualizar o processo.";
        }

        // Redireciona para a tela de detalhes do processo
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
    public function painelNotificacoes()
    {
        $pageTitle = "Painel de Notificações";
        
        // A lógica de busca continua a mesma, pois inclui o status 'Pendente'
        $status_para_buscar = ['Orçamento Pendente', 'Pendente'];
        $processos_pendentes = $this->processoModel->getByMultipleStatus($status_para_buscar);
        
        require_once __DIR__ . '/../views/layouts/header.php';
        // O nome do arquivo da view será alterado no próximo passo
        require_once __DIR__ . '/../views/processos/painel_notificacoes.php';
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

private function verificarAlteracaoValorMensalista($cliente_id, $documentosPost)
{
    $cliente = $this->clienteModel->getById($cliente_id);

    // A verificação só se aplica se o cliente for encontrado e for do tipo 'Mensalista'.
    if (!$cliente || ($cliente['tipo_assessoria'] ?? '') !== 'Mensalista') {
        return false;
    }

    if (isset($documentosPost) && is_array($documentosPost)) {
        // O loop percorre as categorias ('tradução', 'crc', etc.)
        foreach ($documentosPost as $categoria => $documentosDaCategoria) {
            // O segundo loop percorre os serviços dentro de cada categoria
            foreach ($documentosDaCategoria as $doc) {
                if (!empty($doc['tipo_documento']) && isset($doc['valor_unitario'])) {
                    $nomeServico = $doc['tipo_documento'];

                    // Converte o valor enviado para um número float, limpando 'R$' e trocando vírgula por ponto.
                    $valorEnviado = floatval(str_replace(',', '.', preg_replace('/[^\d,]/', '', $doc['valor_unitario'])));

                    // Busca o valor padrão do serviço que o cliente tem cadastrado.
                    $servicoPadrao = $this->clienteModel->getServicoContratadoPorNome($cliente['id'], $nomeServico);
                    $valorPadrao = $servicoPadrao ? floatval($servicoPadrao['valor_padrao']) : null;

                    // Compara os dois valores.
                    // A regra foi ajustada: somente se o valor enviado for menor que o padrão é considerada alteração.
                    if ($valorPadrao !== null && $valorEnviado < $valorPadrao) {
                        return true; // Valor abaixo do contratado, necessita aprovação.
                    }
                }
            }
        }
    }

    return false; // Não encontrou nenhuma alteração.
}



}