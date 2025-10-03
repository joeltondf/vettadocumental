<?php
/**
 * @file ProcessosController.php
 * @description Controlador principal para gerenciamento de Processos.
 * Inclui operações CRUD, fluxos especializados, ações AJAX e integração com serviços externos.
 */


ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../models/Processo.php';
require_once __DIR__ . '/../models/Documento.php';
require_once __DIR__ . '/../models/Cliente.php';
require_once __DIR__ . '/../models/Vendedor.php';
require_once __DIR__ . '/../models/Tradutor.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../services/OmieService.php';
require_once __DIR__ . '/../models/Configuracao.php';
require_once __DIR__ . '/../models/CategoriaFinanceira.php';
require_once __DIR__ . '/../models/LancamentoFinanceiro.php';
require_once __DIR__ . '/../services/EmailService.php';
require_once __DIR__ . '/../models/Notificacao.php';
class ProcessosController
{
    private const DEFAULT_OMIE_SERVICE_TAXATION_CODE = '01';
    private const ALLOWED_OMIE_SERVICE_TAXATION_CODES = ['01', '02', '03', '04', '05', '06'];
    private const DEFAULT_OMIE_BANK_ACCOUNT_CODE = 4394066898;
    private const SESSION_KEY_PROCESS_FORM = 'process_form_data';
    private const SESSION_KEY_SERVICO_FORM = 'servico_rapido_form_data';

    private $pdo;
    private $processoModel;
    private $clienteModel;
    private $vendedorModel;
    private $tradutorModel;
    private $userModel;
    private $documentoModel;
    private $configModel;
    private $omieService;
    private $notificacaoModel;
    private $emailService;

    /**
     * Construtor da classe. Inicializa modelos e serviços necessários.
     *
     * @param PDO $pdo Conexão PDO com o banco de dados.
     */
    public function __construct($pdo)
    {
        $this->pdo = $pdo;
        $this->processoModel = new Processo($pdo);
        $this->clienteModel = new Cliente($pdo);
        $this->vendedorModel = new Vendedor($pdo);
        $this->tradutorModel = new Tradutor($pdo);
        $this->userModel = new User($pdo);
        $this->documentoModel = new Documento($pdo);
        $this->configModel = new Configuracao($pdo);
        $this->omieService = new OmieService($this->configModel, $pdo);
        $this->notificacaoModel = new Notificacao($pdo);
        $this->emailService = new EmailService($pdo);

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    // -----------------------------------------------------------------------
    // Métodos de ação pública
    // -----------------------------------------------------------------------

    /**
     * Página principal de listagem de processos.
     */
    public function index()
    {
        $pageTitle = "Gestão de Processos";
        $processos = $this->processoModel->getAll();
        $this->render('lista', [
            'processos' => $processos,
            'pageTitle' => $pageTitle,
        ]);
    }

    /**
     * Exibe o detalhe de um processo específico.
     *
     * @param int $id ID do processo.
     */
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
        $this->render('detalhe', [
            'processo' => $processoData['processo'],
            'documentos' => $processoData['documentos'],
            'anexos' => $anexos,
            'comprovantes' => $comprovantes,
            'pageTitle' => $pageTitle,
        ]);
    }

    /**
     * Exibe uma versão simplificada do orçamento para impressão.
     */
    public function exibirOrcamento($id)
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

        $cliente = null;
        if (!empty($processoData['processo']['cliente_id'])) {
            $cliente = $this->clienteModel->getById((int)$processoData['processo']['cliente_id']);
        }

        $responsibleUser = null;
        if (!empty($processoData['processo']['colaborador_id'])) {
            $responsibleUser = $this->userModel->getById((int)$processoData['processo']['colaborador_id']);
        }

        if (!$responsibleUser && isset($_SESSION['user_id'])) {
            $responsibleUser = $this->userModel->getById((int)$_SESSION['user_id']);
        }

        // Busca o logo do sistema a partir das configurações
        $system_logo = $this->configModel->get('system_logo');

        $html = $this->buildBudgetHtml(
            $processoData['processo'],
            $processoData['documentos'] ?? [],
            $cliente,
            [
                'fullPage' => true,
                'showPrintButton' => true,
                'user' => $responsibleUser,
                'system_logo' => $system_logo, // Passa o logo para a função que monta o HTML
            ]
        );

        header('Content-Type: text/html; charset=utf-8');
        echo $html;
        exit();
    }

    /**
     * Exibe o formulário de criação de novo processo.
     *
     * @param array $data Dados de formulário, se houver.
     * @param array $files Arquivos enviados via upload.
     */
    public function create($data = [], $files = [])
    {
        $pageTitle = "Cadastrar Novo Processo";
        $clientes = $this->getClientesForOrcamentoForm();
        $vendedores = $this->vendedorModel->getAll();
        $tradutores = $this->tradutorModel->getAll();
        $formData = $this->consumeFormInput(self::SESSION_KEY_PROCESS_FORM);

        $categoriaModel = new CategoriaFinanceira($this->pdo);
        $tipos_traducao = $categoriaModel->getReceitasPorServico('Tradução');
        $tipos_crc = $categoriaModel->getReceitasPorServico('CRC');
        $this->render('form', [
            'clientes' => $clientes,
            'vendedores' => $vendedores,
            'tradutores' => $tradutores,
            'tipos_traducao' => $tipos_traducao,
            'tipos_crc' => $tipos_crc,
            'pageTitle' => $pageTitle,
            'formData' => $formData,
        ]);
    }

    /**
     * Persiste os dados de um processo (criação ou atualização).
     */
    public function store()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: dashboard.php');
            exit();
        }

        $id_existente = $_POST['id'] ?? null;

        // ------------------------------------------------------------------
        // Criação de novo processo
        // ------------------------------------------------------------------
        if (empty($id_existente)) {
            // Fluxo de serviço rápido
            if (isset($_POST['status_proposto'])) {
                $dadosParaSalvar = $_POST;
                $perfilUsuario = $_SESSION['user_perfil'] ?? '';
                $clienteId = $dadosParaSalvar['cliente_id'] ?? null;
                $documentos = $dadosParaSalvar['documentos'] ?? ($dadosParaSalvar['docs'] ?? []);

                // Verifica pendência para colaboradores/vendedores
                $pendente = false;
                if ($clienteId && in_array($perfilUsuario, ['colaborador', 'vendedor'])) {
                    $pendente = $this->verificarAlteracaoValorMinimo($clienteId, $documentos);
                }

                // Define status final conforme perfil do usuário
                if (in_array($perfilUsuario, ['admin', 'gerencia', 'supervisor'])) {
                    $dadosParaSalvar['status_processo'] = 'Em andamento';
                } else {
                    $dadosParaSalvar['status_processo'] = $pendente ? 'Pendente' : 'Em andamento';
                }

                // Cria o serviço rápido
                $dadosParaSalvar = $this->prepareOmieSelectionData($dadosParaSalvar);
                $processoId = $this->processoModel->create($dadosParaSalvar, $_FILES);
                $redirectUrl = 'dashboard.php';

                if ($processoId) {
                    if ($dadosParaSalvar['status_processo'] === 'Pendente') {
                        $_SESSION['message'] = "Serviço enviado para aprovação da gerência/supervisão.";
                        $this->notificarGerenciaPendencia($processoId, $clienteId, $_SESSION['user_id'], 'serviço');
                    } else {
                        $_SESSION['success_message'] = "Serviço cadastrado com sucesso!";
                        if ($this->shouldGenerateOmieOs($dadosParaSalvar['status_processo'])) {
                            $osNumero = $this->gerarOsOmie($processoId);
                            if ($osNumero) {
                                $_SESSION['success_message'] .= " Ordem de Serviço #{$osNumero} gerada na Omie.";
                            }
                        }
                    }

                    $this->convertProspectIfNeeded((int)($dadosParaSalvar['cliente_id'] ?? 0), $dadosParaSalvar['status_processo'] ?? null);
                    if (in_array($dadosParaSalvar['status_processo'], ['Em andamento', 'Aprovado'], true)) {
                        $this->gerarOsOmie($processoId);
                    }
                    $this->clearFormInput(self::SESSION_KEY_SERVICO_FORM);
                } else {
                    $_SESSION['error_message'] = $_SESSION['error_message'] ?? "Erro ao cadastrar o serviço.";
                    $this->rememberFormInput(self::SESSION_KEY_SERVICO_FORM, $dadosParaSalvar);
                    $redirectUrl = 'servico-rapido.php?action=create';
                }

                header('Location: ' . $redirectUrl);
                exit();
            }

            // Fluxo de orçamento normal
            $dadosProcesso = $this->prepareOmieSelectionData($_POST);
            $novo_id = $this->processoModel->create($dadosProcesso, $_FILES);
            $redirectUrl = 'dashboard.php';
            if ($novo_id) {
                $status_inicial = $dadosProcesso['status_processo'] ?? 'Orçamento';
                $this->convertProspectIfNeeded((int)($dadosProcesso['cliente_id'] ?? 0), $status_inicial);
                $mensagemSucesso = "Processo cadastrado com sucesso!";

                if ($status_inicial === 'Orçamento') {
                    $this->sendBudgetEmails($novo_id, $_SESSION['user_id'] ?? null);
                    $mensagemSucesso = "Orçamento cadastrado e enviado para o cliente.";
                }

                if ($this->shouldGenerateOmieOs($status_inicial)) {
                    $osNumero = $this->gerarOsOmie($novo_id);
                    if ($osNumero) {
                        $mensagemSucesso .= " Ordem de Serviço #{$osNumero} gerada na Omie.";
                    }
                }

                $_SESSION['message'] = $mensagemSucesso;
                $_SESSION['success_message'] = $mensagemSucesso;
                $this->clearFormInput(self::SESSION_KEY_PROCESS_FORM);
            } else {
                $_SESSION['error_message'] = $_SESSION['error_message'] ?? "Erro ao criar o processo no banco de dados.";
                $this->rememberFormInput(self::SESSION_KEY_PROCESS_FORM, $dadosProcesso);
                $redirectUrl = $this->buildProcessCreateRedirectUrl($dadosProcesso['return_to'] ?? null);
            }
            header('Location: ' . $redirectUrl);
            exit();
        }

        // ------------------------------------------------------------------
        // Atualização de processo existente
        // ------------------------------------------------------------------
        $dadosParaAtualizar = $_POST;
        $dadosParaAtualizar = $this->prepareOmieSelectionData($dadosParaAtualizar);
        $perfilUsuario = $_SESSION['user_perfil'] ?? '';
        $processoOriginal = $this->processoModel->getById($id_existente)['processo'];

        $valorAlterado = false;
        if (in_array($perfilUsuario, ['colaborador', 'vendedor'])) {
            $valorAlterado = $this->verificarAlteracaoValorMinimo(
                $dadosParaAtualizar['cliente_id'],
                $dadosParaAtualizar['docs'] ?? []
            );
        }

        if ($processoOriginal['status_processo'] === 'Pendente' && in_array($perfilUsuario, ['admin', 'gerencia', 'supervisor'])) {
            $dadosParaAtualizar['status_processo'] = 'Em andamento';
            $this->notificacaoModel->deleteByLink("/processos.php?action=view&id=" . $id_existente);
            $_SESSION['message'] = "Serviço aprovado e status atualizado!";
        } elseif ($valorAlterado) {
            if ($this->isBudgetStatus($processoOriginal['status_processo'])) {
                $dadosParaAtualizar['status_processo'] = 'Orçamento';
                $_SESSION['message'] = 'Orçamento atualizado e enviado para o cliente.';
                $_SESSION['success_message'] = 'Orçamento atualizado e enviado para o cliente.';
            } else {
                $dadosParaAtualizar['status_processo'] = 'Pendente';
                $_SESSION['message'] = "Alteração salva. O serviço aguarda aprovação da gerência/supervisão.";
                $_SESSION['success_message'] = $_SESSION['message'];
                $cliente = $this->clienteModel->getById($dadosParaAtualizar['cliente_id']);
                $link = "/processos.php?action=view&id=" . $id_existente;
                $mensagem = "O serviço para o cliente '{$cliente['nome']}' foi alterado e precisa de aprovação.";
                $gerentes_ids = $this->userModel->getIdsByPerfil(['admin', 'gerencia', 'supervisor']);
                foreach ($gerentes_ids as $gerente_id) {
                    $this->notificacaoModel->criar($gerente_id, $_SESSION['user_id'], $mensagem, $link);
                }
            }
        }

        if ($this->processoModel->update($id_existente, $dadosParaAtualizar, $_FILES)) {
            if (!isset($_SESSION['message'])) {
                $_SESSION['success_message'] = "Processo atualizado com sucesso!";
            }
            $novoStatus = $dadosParaAtualizar['status_processo'] ?? $processoOriginal['status_processo'];
            if ($this->shouldGenerateOmieOs($novoStatus)) {
                $osNumero = $this->gerarOsOmie($id_existente);
                if ($osNumero) {
                    $mensagemBase = $_SESSION['success_message'] ?? 'Processo atualizado com sucesso!';
                    $_SESSION['success_message'] = $mensagemBase . " Ordem de Serviço #{$osNumero} gerada na Omie.";
                }
            }
            $clienteParaConverter = (int)($dadosParaAtualizar['cliente_id'] ?? $processoOriginal['cliente_id']);
            $this->convertProspectIfNeeded($clienteParaConverter, $novoStatus);
            if ($this->isBudgetStatus($novoStatus)) {
                $this->sendBudgetEmails($id_existente, $_SESSION['user_id'] ?? null);
            }
        } else {
            $_SESSION['error_message'] = $_SESSION['error_message'] ?? "Ocorreu um erro ao atualizar o processo.";
        }
        header('Location: processos.php?action=view&id=' . $id_existente);
        exit();
    }

    /**
     * Exibe o formulário de edição de um processo existente.
     *
     * @param int $id ID do processo a ser editado.
     */
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

        $anexos = $this->processoModel->getAnexosPorCategoria($id, 'anexo');
        $comprovantes = $this->processoModel->getAnexosPorCategoria($id, 'comprovante');
        $processo = $processoData['processo'];
        $formData = $this->consumeFormInput(self::SESSION_KEY_PROCESS_FORM);

        // Verificação de permissão de edição
        if ($_SESSION['user_perfil'] === 'vendedor') {
            $vendedor_user_id = $this->vendedorModel->getUserIdByVendedorId($processo['vendedor_id']);
            if ($vendedor_user_id != $_SESSION['user_id']) {
                $_SESSION['error_message'] = "Você não tem permissão para editar este orçamento.";
                header('Location: dashboard.php');
                exit();
            }
        } elseif (!in_array($_SESSION['user_perfil'], ['admin', 'gerencia', 'supervisor'])) {
            $_SESSION['error_message'] = "Você não tem permissão para acessar esta página.";
            header('Location: dashboard.php');
            exit();
        }

        $pageTitle = "Editar Processo: " . htmlspecialchars($processo['titulo']);
        $categoriaModel = new CategoriaFinanceira($this->pdo);
        $this->render('form', [
            'processo' => !empty($formData) ? array_merge($processo, $formData) : $processo,
            'documentos' => $processoData['documentos'],
            'anexos' => $anexos,
            'comprovantes' => $comprovantes,
            'clientes' => $this->getClientesForOrcamentoForm($processo),
            'vendedores' => $this->vendedorModel->getAll(),
            'tradutores' => $this->tradutorModel->getAll(),
            'tipos_traducao' => $categoriaModel->getReceitasPorServico('Tradução'),
            'tipos_crc' => $categoriaModel->getReceitasPorServico('CRC'),
            'pageTitle' => $pageTitle,
            'formData' => $formData,
        ]);

        $_SESSION['redirect_after_oauth'] = $_SERVER['REQUEST_URI'];
    }

    /**
     * Remove um processo do sistema.
     *
     * @param int $id ID do processo a ser excluído.
     */
    public function delete($id)
    {
        if (!$id) {
            $_SESSION['error_message'] = "ID do processo não fornecido.";
            header('Location: dashboard.php');
            exit();
        }

        if (!in_array($_SESSION['user_perfil'], ['admin', 'gerencia', 'supervisor'])) {
            $_SESSION['error_message'] = "Você não tem permissão para excluir processos.";
            header('Location: dashboard.php');
            exit();
        }

        if ($this->processoModel->deleteProcesso($id)) {
            $_SESSION['success_message'] = "Processo excluído com sucesso!";
        } else {
            $_SESSION['error_message'] = "Erro ao excluir o processo.";
        }

        header('Location: dashboard.php');
        exit();
    }

    /**
     * Criação de serviço rápido (Ação específica).
     */
    public function createServicoRapido()
    {
        $this->auth_check();
        $pageTitle = "Cadastrar Serviço Rápido";
        $clientes = $this->clienteModel->getAll();
        $vendedores = $this->vendedorModel->getAll();
        $tradutores = $this->tradutorModel->getAll();
        $categoriaModel = new CategoriaFinanceira($this->pdo);
        $financeiroServicos = [
            'Tradução' => $categoriaModel->getReceitasPorServico('Tradução'),
            'CRC' => $categoriaModel->getReceitasPorServico('CRC'),
            'Apostilamento' => $categoriaModel->getReceitasPorServico('Apostilamento'),
            'Postagem' => $categoriaModel->getReceitasPorServico('Postagem'),
        ];
        $formData = $this->consumeFormInput(self::SESSION_KEY_SERVICO_FORM);

        $this->render('form_servico_rapido', [
            'clientes' => $clientes,
            'vendedores' => $vendedores,
            'tradutores' => $tradutores,
            'financeiroServicos' => $financeiroServicos,
            'pageTitle' => $pageTitle,
            'formData' => $formData,
        ]);
    }

    /**
     * Persiste dados de serviço rápido via form específico.
     */
    public function storeServicoRapido()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: dashboard.php');
            exit();
        }

        $dadosParaSalvar = $_POST;
        $perfilUsuario = $_SESSION['user_perfil'] ?? '';
        $clienteId = $dadosParaSalvar['cliente_id'] ?? null;
        $documentos = $dadosParaSalvar['documentos'] ?? ($dadosParaSalvar['docs'] ?? []);

        // Calcula pendência para colaboradores ou vendedores
        $pendente = false;
        if ($clienteId && in_array($perfilUsuario, ['colaborador', 'vendedor'])) {
            $pendente = $this->verificarAlteracaoValorMinimo($clienteId, $documentos);
        }

        // Define o status final
        if (in_array($perfilUsuario, ['admin', 'gerencia', 'supervisor'])) {
            $dadosParaSalvar['status_processo'] = 'Em andamento';
        } else {
            $dadosParaSalvar['status_processo'] = $pendente ? 'Pendente' : 'Em andamento';
        }

        $processoId = $this->processoModel->create($dadosParaSalvar, $_FILES);
        if ($processoId) {
            if ($dadosParaSalvar['status_processo'] === 'Pendente') {
                $_SESSION['message'] = "Serviço enviado para aprovação da gerência/supervisão.";
                $this->notificarGerenciaPendencia($processoId, $clienteId, $_SESSION['user_id'], 'serviço');
            } else {
                $_SESSION['success_message'] = "Serviço cadastrado com sucesso!";
                if ($this->shouldGenerateOmieOs($dadosParaSalvar['status_processo'])) {
                    $osNumero = $this->gerarOsOmie($processoId);
                    if ($osNumero) {
                        $_SESSION['success_message'] .= " Ordem de Serviço #{$osNumero} gerada na Omie.";
                    }
                }
            }
        } else {
            $_SESSION['error_message'] = $_SESSION['error_message'] ?? "Erro ao cadastrar o serviço.";
        }

        header('Location: dashboard.php');
        exit();
    }

    /**
     * Salva um comentário via AJAX.
     */
    public function storeCommentAjax()
    {
        header('Content-Type: application/json');
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['comentario'])) {
            $data = [
                'processo_id' => $_POST['processo_id'],
                'user_id' => $_SESSION['user_id'],
                'comentario' => $_POST['comentario'],
            ];

            if ($this->processoModel->addComentario($data)) {
                echo json_encode([
                    'success' => true,
                    'comment' => [
                        'author' => htmlspecialchars($_SESSION['user_nome']),
                        'date' => date('d/m/Y H:i'),
                        'text' => nl2br(htmlspecialchars($_POST['comentario'])),
                    ],
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Erro ao guardar o comentário.']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'O comentário não pode estar vazio.']);
        }
        exit();
    }

    /**
     * Atualiza as etapas de um processo via AJAX.
     */
    public function updateEtapas()
    {
        header('Content-Type: application/json');
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
            $id = $_POST['id'];

            if (!empty($_POST['data_envio_cartorio'])) {
                $_POST['status_processo'] = 'Finalizado';
            }

            // Limpa o campo de prazo que não foi selecionado
            if (isset($_POST['traducao_prazo_tipo'])) {
                if ($_POST['traducao_prazo_tipo'] === 'dias') {
                    $_POST['traducao_prazo_data'] = null;
                } else {
                    $_POST['traducao_prazo_dias'] = null;
                }
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

    /**
     * Altera o status de um processo, incluindo verificações de permissão e notificações.
     */
    public function changeStatus()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: dashboard.php');
            exit();
        }
        $id = $_POST['id'] ?? null;
        $status = $_POST['status_processo'] ?? null;
        if (!$id || !$status) {
            $_SESSION['error_message'] = "Dados insuficientes para atualizar o status.";
            header('Location: dashboard.php');
            exit();
        }

        // Busca os dados do processo antes de qualquer ação
        $res = $this->processoModel->getById($id);
        if (!$res || !isset($res['processo'])) {
            $_SESSION['error_message'] = "Processo não encontrado.";
            header('Location: dashboard.php');
            exit();
        }
        $processo = $res['processo'];
        $statusAntigo = $processo['status_processo'];
        $clienteId = (int)($processo['cliente_id'] ?? 0);

        // Verificação de permissão
        if ($_SESSION['user_perfil'] === 'vendedor') {
            $vendedor_user_id = $this->vendedorModel->getUserIdByVendedorId($processo['vendedor_id']);
            if ($vendedor_user_id != $_SESSION['user_id']) {
                $_SESSION['error_message'] = "Você não tem permissão para executar esta ação.";
                header('Location: dashboard.php');
                exit();
            }
            $allowedStatuses = ['Orçamento'];
            if (!in_array($status, $allowedStatuses, true)) {
                $_SESSION['error_message'] = "Como vendedor, você só pode reenviar o orçamento para o cliente.";
                header('Location: processos.php?action=view&id=' . $id);
                exit();
            }
        } elseif (!in_array($_SESSION['user_perfil'], ['admin', 'gerencia', 'supervisor'])) {
            $_SESSION['error_message'] = "Você não tem permissão para acessar esta página.";
            header('Location: dashboard.php');
            exit();
        }

        // Prepara os campos a atualizar
        $dataToUpdate = [
            'status_processo' => $status,
            'data_inicio_traducao' => $_POST['data_inicio_traducao'] ?? $processo['data_inicio_traducao'],
            'traducao_prazo_tipo' => $_POST['traducao_prazo_tipo'] ?? $processo['traducao_prazo_tipo'],
            'traducao_prazo_dias' => $_POST['traducao_prazo_dias'] ?? $processo['traducao_prazo_dias'],
            'traducao_prazo_data' => $_POST['traducao_prazo_data'] ?? $processo['traducao_prazo_data'],
        ];

        // Validação extra para perfis de gestão (admin, gerência ou supervisão)
        if (in_array($_SESSION['user_perfil'], ['admin', 'gerencia', 'supervisor']) &&
            $statusAntigo === 'Orçamento' && in_array($status, ['Aprovado', 'Em Andamento'], true)) {
            $erros = [];
            if (empty($dataToUpdate['data_inicio_traducao'])) $erros[] = 'Informe a Data de envio para o Tradutor.';
            if (!empty($erros)) {
                $_SESSION['error_message'] = implode(' ', $erros);
                header('Location: processos.php?action=view&id=' . $id);
                exit();
            }
        }

        // Atualiza as etapas
        if ($this->processoModel->updateEtapas($id, $dataToUpdate)) {
            $link = "/processos.php?action=view&id={$id}";
            $remetenteId = $_SESSION['user_id'];

            $statusSaiuDePendencia = $statusAntigo === 'Pendente' && $status !== 'Pendente';

            $this->convertProspectIfNeeded($clienteId, $status);

            if ($statusSaiuDePendencia) {
                $this->notificacaoModel->deleteByLink($link);
            }

            $successMessage = 'Status do processo atualizado com sucesso!';
            if ($status === 'Em Andamento' && $statusAntigo === 'Pendente') {
                $successMessage = 'Serviço aprovado e status atualizado para Em Andamento.';
            } elseif ($status === 'Orçamento') {
                $successMessage = 'Orçamento enviado para o cliente.';
            } elseif ($status === 'Aprovado' && $statusAntigo === 'Orçamento') {
                $successMessage = 'Orçamento aprovado com sucesso!';
            }
            $_SESSION['success_message'] = $successMessage;

            if ($this->shouldGenerateOmieOs($status)) {
                $osNumero = $this->gerarOsOmie($id);
                if ($osNumero) {
                    $_SESSION['success_message'] .= " Ordem de Serviço #{$osNumero} gerada na Omie.";
                }
            }

            if ($status === 'Cancelado') {
                $this->cancelarOsOmie($id);
            }

            $vendedorUserId = $this->vendedorModel->getUserIdByVendedorId($processo['vendedor_id']);

            switch ($status) {
                case 'Orçamento':
                    $this->sendBudgetEmails($id, $remetenteId);
                    if ($vendedorUserId && $remetenteId !== $vendedorUserId) {
                        $mensagem = "Seu orçamento #{$processo['orcamento_numero']} foi enviado ao cliente.";
                        $this->notificacaoModel->criar($vendedorUserId, $remetenteId, $mensagem, $link);
                    }
                    break;
                case 'Aprovado':
                    if ($vendedorUserId) {
                        $mensagem = "Seu orçamento #{$processo['orcamento_numero']} foi APROVADO!";
                        $this->notificacaoModel->criar($vendedorUserId, $remetenteId, $mensagem, $link);
                    }
                    break;
                case 'Recusado':
                    if ($vendedorUserId) {
                        $mensagem = "Seu orçamento #{$processo['orcamento_numero']} foi RECUSADO. Ajuste-o.";
                        $this->notificacaoModel->criar($vendedorUserId, $remetenteId, $mensagem, $link);
                    }
                    break;
                default:
                    // Para os demais status não é necessário gerar notificações extras.
                    break;
            }
        } else {
            $_SESSION['error_message'] = "Falha ao atualizar o processo.";
        }

        header('Location: processos.php?action=view&id=' . $id);
        exit();
    }

    /**
     * Painel de notificações, lista processos pendentes de aprovação.
     */
    public function painelNotificacoes()
    {
        $pageTitle = "Painel de Notificações";
        $status_para_buscar = ['Pendente'];
        $processos_pendentes = $this->processoModel->getByMultipleStatus($status_para_buscar);
        $this->render('painel_notificacoes', [
            'processos_pendentes' => $processos_pendentes,
            'pageTitle' => $pageTitle,
        ]);
    }

    /**
     * Aprova um orçamento.
     */
    public function aprovarOrcamento()
    {
        $id = $_GET['id'] ?? null;
        if (!$id) {
            header('Location: dashboard.php');
            exit();
        }
        $data = ['status_processo' => 'Orçamento'];
        if ($this->processoModel->updateStatus($id, $data)) {
            $processo = $this->processoModel->getById($id)['processo'];
            $link = "/processos.php?action=view&id={$id}";
            $this->notificacaoModel->deleteByLink($link);
            $this->sendBudgetEmails($id, $_SESSION['user_id'] ?? null);

            if ($processo && !empty($processo['vendedor_id'])) {
                $vendedor_user_id = $this->vendedorModel->getUserIdByVendedorId($processo['vendedor_id']);
                if ($vendedor_user_id) {
                    $mensagem = "Seu orçamento #{$processo['orcamento_numero']} foi liberado pela gerência.";
                    $this->notificacaoModel->criar($vendedor_user_id, $_SESSION['user_id'], $mensagem, $link);
                }
            }
            $_SESSION['success_message'] = "Orçamento aprovado pela gerência e enviado ao cliente.";
        } else {
            $_SESSION['error_message'] = "Erro ao aprovar o orçamento.";
        }
        header('Location: processos.php?action=view&id=' . $id);
        exit();
    }

    /**
     * Recusa um orçamento.
     */
    public function recusarOrcamento()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: dashboard.php');
            exit();
        }
        $id = $_POST['id'] ?? null;
        $motivo = $_POST['motivo_recusa'] ?? '';
        if (!$id || empty($motivo)) {
            $_SESSION['error_message'] = "O motivo da recusa é obrigatório.";
            header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? 'dashboard.php'));
            exit();
        }
        $data = ['status_processo' => 'Recusado', 'motivo_recusa' => $motivo];
        if ($this->processoModel->updateStatus($id, $data)) {
            $processo = $this->processoModel->getById($id)['processo'];
            if ($processo && !empty($processo['vendedor_id'])) {
                $vendedor_user_id = $this->vendedorModel->getUserIdByVendedorId($processo['vendedor_id']);
                if ($vendedor_user_id) {
                    $mensagem = "Orçamento #{$processo['orcamento_numero']} recusado. Por favor, revise-o.";
                    $link = "/processos.php?action=view&id={$id}";
                    $this->notificacaoModel->deleteByLink($link);
                    $this->notificacaoModel->criar($vendedor_user_id, $_SESSION['user_id'], $mensagem, $link);
                }
            }
            $_SESSION['success_message'] = "Orçamento recusado e vendedor notificado.";
        } else {
            $_SESSION['error_message'] = "Erro ao recusar o orçamento.";
        }
        header('Location: processos.php?action=view&id=' . $id);
        exit();
    }

    /**
     * Exibe os detalhes de um processo.
     */
    public function detalhe($id)
    {
        $this->auth_check();
        $processo_info = $this->processoModel->getById($id);
        $anexos = $this->processoModel->getAnexos($id);
        $this->render('processos/detalhe', [
            'processo' => $processo_info['processo'],
            'documentos' => $processo_info['documentos'],
            'anexos' => $anexos,
            'pageTitle' => 'Detalhes do Processo',
        ]);
    }

    /**
     * Exclui um anexo específico.
     */
    public function excluir_anexo($id, $anexo_id)
    {
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

    /**
     * Ação para disparar manualmente a geração de Ordem de Serviço na Omie.
     */
    public function gerarOsOmieManual($id)
    {
        if (!$id) {
            $_SESSION['error_message'] = "ID do processo não fornecido.";
            header('Location: dashboard.php');
            exit();
        }
        if (!in_array($_SESSION['user_perfil'], ['admin', 'gerencia', 'supervisor'])) {
            $_SESSION['error_message'] = "Você não tem permissão para executar esta ação.";
            header('Location: processos.php?action=view&id=' . $id);
            exit();
        }
        $processoAtual = $this->processoModel->getById($id);
        $osAnterior = $processoAtual['processo']['os_numero_omie'] ?? null;
        $osNumero = $this->gerarOsOmie($id);
        if ($osNumero) {
            if ($osAnterior) {
                $_SESSION['success_message'] = "A Ordem de Serviço #{$osNumero} já estava vinculada na Omie.";
            } else {
                $_SESSION['success_message'] = "Ordem de Serviço #{$osNumero} gerada na Omie com sucesso!";
            }
        }
        header('Location: processos.php?action=view&id=' . $id);
        exit();
    }

    // -----------------------------------------------------------------------
    // Métodos utilitários privados
    // -----------------------------------------------------------------------

    /**
     * Verifica se o usuário está autenticado. Redireciona para login se não estiver.
     */
    private function auth_check()
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: /login.php');
            exit();
        }
    }


    private function rememberFormInput(string $sessionKey, array $data): void
    {
        $_SESSION[$sessionKey] = $data;
    }

    private function consumeFormInput(string $sessionKey): array
    {
        if (!isset($_SESSION[$sessionKey]) || !is_array($_SESSION[$sessionKey])) {
            return [];
        }

        $data = $_SESSION[$sessionKey];
        unset($_SESSION[$sessionKey]);

        return $data;
    }

    private function clearFormInput(string $sessionKey): void
    {
        unset($_SESSION[$sessionKey]);
    }

    private function buildProcessCreateRedirectUrl(?string $returnTo): string
    {
        $base = 'processos.php?action=create';
        if ($returnTo === null || $returnTo === '') {
            return $base;
        }

        return $base . '&return_to=' . urlencode($returnTo);
    }


    private function prepareOmieSelectionData(array $data): array
    {
        $data['etapa_faturamento_codigo'] = $this->sanitizeOmieString($data['etapa_faturamento_codigo'] ?? null);
        $data['codigo_categoria'] = $this->sanitizeOmieString($data['codigo_categoria'] ?? null);
        $data['codigo_conta_corrente'] = $this->sanitizeOmieDigits($data['codigo_conta_corrente'] ?? null);
        $data['codigo_cenario_fiscal'] = $this->sanitizeOmieDigits($data['codigo_cenario_fiscal'] ?? null);

        return $data;
    }

    private function sanitizeOmieString($value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim((string)$value);
        return $normalized === '' ? null : $normalized;
    }

    private function sanitizeOmieDigits($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return (string)$value;
        }

        $digits = preg_replace('/\D+/', '', (string)$value);
        return $digits === '' ? null : $digits;
    }

    /**
     * Envia o orçamento recém-criado para o cliente e para o usuário responsável.
     */
    private function sendBudgetEmails(int $processId, ?int $userId): void
    {
        $processData = $this->processoModel->getById($processId);
        if (!$processData) {
            return;
        }

        $process = $processData['processo'];
        $documents = $processData['documentos'] ?? [];

        $client = null;
        if (!empty($process['cliente_id'])) {
            $client = $this->clienteModel->getById((int)$process['cliente_id']);
        }
        $clientEmail = null;
        if ($client) {
            $clientEmail = $client['email'] ?? ($client['user_email'] ?? null);
        }

        $user = null;
        if ($userId) {
            $user = $this->userModel->getById((int)$userId);
        }

        $subject = 'Orçamento #' . ($process['orcamento_numero'] ?? $processId);
        if (!empty($process['titulo'])) {
            $subject .= ' - ' . $process['titulo'];
        }

        $systemLogo = $this->configModel->get('system_logo');

        $body = $this->buildBudgetHtml($process, $documents, $client, [
            'fullPage' => true,
            'isEmail' => true,
            'showPrintButton' => false,
            'user' => $user,
            'system_logo' => $systemLogo,
        ]);

        if (!empty($clientEmail)) {
            try {
                $this->emailService->sendEmail($clientEmail, $subject, $body);
            } catch (\Exception $e) {
                error_log('Erro ao enviar orçamento para o cliente: ' . $e->getMessage());
            }
        }

        if (!empty($user['email'])) {
            try {
                $this->emailService->sendEmail($user['email'], $subject, $body);
            } catch (\Exception $e) {
                error_log('Erro ao enviar orçamento para o usuário: ' . $e->getMessage());
            }
        }
    }

    /**
     * Monta o HTML do orçamento considerando o contexto (e-mail ou impressão).
     */
    private function buildBudgetHtml(array $process, array $documents, ?array $client, array $options = []): string
    {
        $config = array_merge([
            'fullPage' => false,
            'isEmail' => false,
            'showPrintButton' => false,
            'user' => null,
            'system_logo' => null, // Adiciona um valor padrão para o novo parâmetro
        ], $options);

        $fullPage = $config['fullPage'];
        $isEmail = $config['isEmail'];
        $showPrintButton = $config['showPrintButton'];
        $user = $config['user'];
        $system_logo = $config['system_logo']; // Extrai o logo para usar na view

        ob_start();
        $viewPath = $isEmail
            ? __DIR__ . '/../views/processos/orcamento_email.php'
            : __DIR__ . '/../views/processos/orcamento_visualizar.php';
        require $viewPath;
        return ob_get_clean();
    }

    /**
     * Renderiza uma view com header e footer.
     *
     * @param string $view Nome da view
     * @param array $data Dados a serem passados para a view
     */
    protected function render($view, $data = [])
    {
        extract($data);
        require_once __DIR__ . '/../views/layouts/header.php';
        require_once __DIR__ . '/../views/processos/' . $view . '.php';
        require_once __DIR__ . '/../views/layouts/footer.php';
    }

    /**
     * Notifica perfis de gestão quando há serviço pendente.
     */
    private function notificarGerenciaPendencia(int $processoId, int $clienteId, int $remetenteId, string $tipoPendencia = 'serviço'): void
    {
        $cliente = $this->clienteModel->getById($clienteId);
        $nomeCliente = $cliente['nome_cliente'] ?? 'Cliente';
        $link = APP_URL . "/processos.php?action=view&id=" . $processoId;
        $tipoMinusculo = mb_strtolower($tipoPendencia, 'UTF-8');
        $tipoCapitalizado = mb_convert_case($tipoMinusculo, MB_CASE_TITLE, 'UTF-8');
        $gerentesIds = $this->userModel->getIdsByPerfil(['admin', 'gerencia', 'supervisor']);
        foreach ($gerentesIds as $gerenteId) {
            $mensagem = "Novo {$tipoMinusculo} pendente para o cliente '{$nomeCliente}'.";
            $this->notificacaoModel->criar($gerenteId, $remetenteId, $mensagem, $link);
            $gerente = $this->userModel->getById($gerenteId);
            if ($gerente && !empty($gerente['email'])) {
                $subject = "{$tipoCapitalizado} pendente de aprovação";
                $body = "Olá {$gerente['nome_completo']},<br><br>"
                    . "Foi criado um {$tipoMinusculo} para o cliente <strong>{$nomeCliente}</strong> que está pendente de aprovação.<br>"
                    . "Clique no link a seguir para visualizar e aprovar: <a href=\"{$link}\">Ver {$tipoMinusculo}</a>.<br><br>"
                    . "Obrigado.";
                try {
                    $this->emailService->sendEmail($gerente['email'], $subject, $body);
                } catch (Exception $e) {
                    error_log("Erro ao enviar e-mail para {$gerente['email']}: " . $e->getMessage());
                }
            }
        }
    }

    /**
     * Verifica se algum documento utiliza um valor abaixo do mínimo permitido.
     * Considera serviços personalizados de clientes mensalistas e produtos do financeiro.
     */
    private function verificarAlteracaoValorMinimo($clienteId, $documentosPost)
    {
        $cliente = $this->clienteModel->getById($clienteId);
        if (!$cliente || !is_array($documentosPost)) {
            return false;
        }

        $documentos = $this->normalizarDocumentosParaValidacao($documentosPost);
        if (empty($documentos)) {
            return false;
        }

        $isMensalista = ($cliente['tipo_assessoria'] ?? '') === 'Mensalista';
        $categoriaModel = new CategoriaFinanceira($this->pdo);

        foreach ($documentos as $doc) {
            $nomeServico = trim((string)($doc['tipo_documento'] ?? ''));
            if ($nomeServico === '') {
                continue;
            }

            $valorEnviado = $this->parseCurrencyValue($doc['valor_unitario'] ?? null);
            if ($valorEnviado === null) {
                continue;
            }

            if ($isMensalista) {
                $servicoPadrao = $this->clienteModel->getServicoContratadoPorNome($clienteId, $nomeServico);
                if ($servicoPadrao && $valorEnviado < (float)$servicoPadrao['valor_padrao']) {
                    return true;
                }
                continue;
            }

            $categoriaFinanceira = $categoriaModel->findReceitaByNome($nomeServico);
            if (!$categoriaFinanceira) {
                continue;
            }

            $valorPadrao = isset($categoriaFinanceira['valor_padrao'])
                ? (float)$categoriaFinanceira['valor_padrao']
                : null;
            $bloquear = !empty($categoriaFinanceira['bloquear_valor_minimo']);

            if ($bloquear && $valorPadrao !== null && $valorPadrao > 0 && $valorEnviado < $valorPadrao) {
                return true;
            }
        }

        return false;
    }

    /**
     * Normaliza a estrutura dos documentos enviados, aceitando arrays simples ou agrupados por categoria.
     */
    private function normalizarDocumentosParaValidacao($documentosPost): array
    {
        if (!is_array($documentosPost)) {
            return [];
        }

        $documentos = [];
        $chavesSequenciais = array_keys($documentosPost) === range(0, count($documentosPost) - 1);

        if ($chavesSequenciais) {
            foreach ($documentosPost as $doc) {
                if (is_array($doc)) {
                    $documentos[] = $doc;
                }
            }
            return $documentos;
        }

        foreach ($documentosPost as $lista) {
            if (!is_array($lista)) {
                continue;
            }
            foreach ($lista as $doc) {
                if (is_array($doc)) {
                    $documentos[] = $doc;
                }
            }
        }

        return $documentos;
    }

    /**
     * Converte valores monetários em float independente do formato recebido.
     */
    private function parseCurrencyValue($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return (float)$value;
        }

        if (!is_string($value)) {
            return null;
        }

        $clean = preg_replace('/[^0-9,.-]/', '', $value);
        if ($clean === '' || $clean === null) {
            return null;
        }

        $clean = str_replace('.', '', $clean);
        $clean = str_replace(',', '.', $clean);

        return is_numeric($clean) ? (float)$clean : null;
    }

    /**
     * Determina se um status pertence ao fluxo de orçamentos.
     */
    private function isBudgetStatus(?string $status): bool
    {
        if ($status === null) {
            return false;
        }

        $normalized = mb_strtolower(trim($status));
        return mb_stripos($normalized, 'orçamento') !== false;
    }

    /**
     * Retorna classes CSS de acordo com status do processo.
     */
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

    /**
     * Calcula contagem regressiva para um prazo e retorna string formatada.
     */
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

    /**
     * Verifica se o status informado exige a geração de uma OS na Omie.
     */
    private function shouldGenerateOmieOs(?string $status): bool
    {
        if ($status === null) {
            return false;
        }
        $normalizedStatus = strtolower(trim($status));
        return in_array($normalizedStatus, ['em andamento', 'aprovado'], true);
    }

    private function shouldConvertProspectToClient(?string $status): bool
    {
        if ($status === null) {
            return false;
        }

        $normalized = mb_strtolower(trim($status));
        $serviceStatuses = ['aprovado', 'em andamento', 'pendente', 'finalizado'];

        return in_array($normalized, $serviceStatuses, true);
    }

    private function convertProspectIfNeeded(?int $clienteId, ?string $status): void
    {
        if (($clienteId ?? 0) <= 0) {
            return;
        }

        if (!$this->shouldConvertProspectToClient($status)) {
            return;
        }

        $this->clienteModel->promoteProspectToClient($clienteId);
    }

    private function getClientesForOrcamentoForm(?array $processo = null): array
    {
        $clientes = [];
        if ($processo !== null && !$this->isBudgetStatus($processo['status_processo'] ?? null)) {
            $clientes = $this->clienteModel->getAll();
        } else {
            $clientes = $this->clienteModel->getProspects();
        }

        if ($processo === null) {
            return $clientes;
        }

        $clienteId = (int)($processo['cliente_id'] ?? 0);
        if ($clienteId <= 0) {
            return $clientes;
        }

        foreach ($clientes as $cliente) {
            if ((int)($cliente['id'] ?? 0) === $clienteId) {
                return $clientes;
            }
        }

        $clienteSelecionado = $this->clienteModel->getById($clienteId);
        if ($clienteSelecionado) {
            $clientes[] = $clienteSelecionado;
            usort($clientes, static function (array $a, array $b): int {
                return strcasecmp($a['nome_cliente'] ?? '', $b['nome_cliente'] ?? '');
            });
        }

        return $clientes;
    }

    private function buildOmieServiceDescription(array $documento, array $processo): string
    {
        $tipoDocumento = trim((string)($documento['tipo_documento'] ?? 'Serviço'));
        $nomeDocumento = trim((string)($documento['nome_documento'] ?? ''));

        if ($nomeDocumento === '') {
            $orcamentoNumero = trim((string)($processo['orcamento_numero'] ?? ''));
            $nomeDocumento = $orcamentoNumero === ''
                ? 'Ref. Orçamento'
                : 'Ref. Orçamento ' . $orcamentoNumero;
        }

        $descricaoPartes = array_filter([$tipoDocumento, $nomeDocumento]);
        return implode(' - ', $descricaoPartes);
    }

    private function buildServiceItems(
        array $processo,
        array $documentos,
        string $serviceCode,
        string $serviceTaxationCode,
        string $categoryItemCode
    ): array {
        if (empty($documentos)) {
            $documentos = [$this->buildFallbackServiceDocument($processo)];
        }

        $items = [];
        foreach ($documentos as $documento) {
            $items[] = [
                'cCodCategItem' => $categoryItemCode,
                'cCodServLC116' => $serviceCode,
                'cCodServMun' => '4.12',
                'cDescServ' => $this->buildOmieServiceDescription($documento, $processo),
                'cTribServ' => $serviceTaxationCode,
                'cRetemISS' => 'N',
                'nQtde' => 1,
                'nValUnit' => $this->resolveServiceAmount($documento, $processo),
            ];
        }

        return $items;
    }

    private function buildFallbackServiceDocument(array $processo): array
    {
        $titulo = trim((string)($processo['titulo'] ?? ''));
        $descricao = $titulo === '' ? 'Serviço' : $titulo;
        $orcamentoNumero = trim((string)($processo['orcamento_numero'] ?? ''));

        return [
            'tipo_documento' => $descricao,
            'nome_documento' => $orcamentoNumero === '' ? null : 'Ref. Orçamento ' . $orcamentoNumero,
            'valor_unitario' => $processo['valor_total'] ?? 0,
            'quantidade' => 1,
        ];
    }

    private function resolveServiceAmount(array $documento, array $processo): float
    {
        $quantity = $this->normalizeDecimalValue($documento['quantidade'] ?? 1);
        if ($quantity <= 0) {
            $quantity = 1.0;
        }

        $unitValue = $this->normalizeDecimalValue($documento['valor_unitario'] ?? 0);
        $calculatedTotal = $unitValue * $quantity;

        if ($calculatedTotal > 0) {
            return round($calculatedTotal, 2);
        }

        $processValue = $this->normalizeDecimalValue($processo['valor_total'] ?? 0);
        if ($processValue > 0) {
            return round($processValue, 2);
        }

        return 0.0;
    }

    private function normalizeDecimalValue($value): float
    {
        if ($value === null) {
            return 0.0;
        }

        if (is_numeric($value)) {
            return (float)$value;
        }

        $normalized = str_replace(['R$', ' '], '', (string)$value);

        if (strpos($normalized, ',') !== false && strpos($normalized, '.') !== false) {
            $normalized = str_replace('.', '', $normalized);
            $normalized = str_replace(',', '.', $normalized);
        } elseif (strpos($normalized, ',') !== false) {
            $normalized = str_replace(',', '.', $normalized);
        }

        return is_numeric($normalized) ? (float)$normalized : 0.0;
    }

    private function resolveOmieServiceTaxationCode(): string
    {
        $rawConfiguredCode = $this->configModel->getSetting('omie_os_taxation_code') ?? null;
        $normalizedCode = $this->normalizeOmieServiceTaxationCode($rawConfiguredCode);

        if ($normalizedCode !== null && in_array($normalizedCode, self::ALLOWED_OMIE_SERVICE_TAXATION_CODES, true)) {
            return $normalizedCode;
        }

        if ($rawConfiguredCode !== null && trim((string)$rawConfiguredCode) !== '') {
            error_log(sprintf(
                'Configuração Omie: o código de tributação de serviços "%s" é inválido. Aplicando "%s".',
                trim((string)$rawConfiguredCode),
                self::DEFAULT_OMIE_SERVICE_TAXATION_CODE
            ));
        }

        return self::DEFAULT_OMIE_SERVICE_TAXATION_CODE;
    }

    private function normalizeOmieServiceTaxationCode($code): ?string
    {
        if ($code === null) {
            return null;
        }

        $digitsOnly = preg_replace('/\D+/', '', (string)$code);
        if ($digitsOnly === '') {
            return null;
        }

        if (strlen($digitsOnly) > 2) {
            return null;
        }

        return str_pad($digitsOnly, 2, '0', STR_PAD_LEFT);
    }

    private function buildServiceOrderHeader(array $processo, array $cliente): array
    {
        return [
            'cCodIntOS' => $this->generateOmieInternalOrderCode($processo),
            'cCodParc' => '000',
            'cEtapa' => '10',
            'dDtPrevisao' => $this->calculateServiceForecastDate($processo),
            'nCodCli' => (int)$cliente['omie_id'],
            'nQtdeParc' => 1,
        ];
    }

    private function generateOmieInternalOrderCode(array $processo): string
    {
        $shortKey = $processo['os_numero_conta_azul'] ?? null;
        if (is_string($shortKey) && $shortKey !== '') {
            $digitsOnly = preg_replace('/\D+/', '', $shortKey);
            if ($digitsOnly !== '') {
                return str_pad($digitsOnly, 6, '0', STR_PAD_LEFT);
            }
        }

        $processId = isset($processo['id']) ? (int)$processo['id'] : 0;
        if ($processId > 0) {
            $sequenceValue = $processId + 9;
            return str_pad((string)$sequenceValue, 6, '0', STR_PAD_LEFT);
        }

        try {
            return (string)random_int(100000, 999999);
        } catch (Exception $exception) {
            return (string)mt_rand(100000, 999999);
        }
    }

    private function calculateServiceForecastDate(array $processo): string
    {
        $explicitDates = [
            $processo['traducao_prazo_data'] ?? null,
            $processo['data_previsao_entrega'] ?? null,
        ];

        foreach ($explicitDates as $explicitDate) {
            if (!empty($explicitDate)) {
                return $this->formatOmieDate($explicitDate);
            }
        }

        $days = $processo['traducao_prazo_dias'] ?? null;
        if (is_numeric($days) && (int)$days > 0) {
            $referenceDate = $processo['data_inicio_traducao'] ?? $processo['data_entrada'] ?? date('Y-m-d');

            try {
                $baseDate = new DateTime($referenceDate);
            } catch (Exception $exception) {
                $baseDate = new DateTime();
            }

            $baseDate->modify('+' . (int)$days . ' days');
            return $baseDate->format('d/m/Y');
        }

        return date('d/m/Y');
    }

    private function buildServiceOrderEmail(array $cliente): array
    {
        return [
            'cEnviarPara' => $this->resolveClientEmail($cliente),
        ];
    }

    private function resolveClientEmail(array $cliente): string
    {
        $candidates = [
            $cliente['email'] ?? null,
            $cliente['user_email'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            if ($candidate === null) {
                continue;
            }

            $email = trim((string)$candidate);
            if ($email !== '') {
                return $email;
            }
        }

        return '';
    }

    private function buildServiceOrderAdditionalInfo(
        array $cliente,
        string $categoryCode,
        int $bankAccountCode
    ): array {
        $info = [
            'cCodCateg' => $categoryCode,
            'nCodCC' => $bankAccountCode,
        ];

        $cityDisplayName = $this->buildCityDisplayName($cliente);
        if ($cityDisplayName !== '') {
            $info['cCidPrestServ'] = $cityDisplayName;
        }

        return $info;
    }

    private function buildCityDisplayName(array $cliente): string
    {
        $city = isset($cliente['cidade']) ? trim((string)$cliente['cidade']) : '';
        $state = isset($cliente['estado']) ? trim((string)$cliente['estado']) : '';

        if ($city === '' && $state === '') {
            return '';
        }

        $uppercase = static function (string $value): string {
            return function_exists('mb_strtoupper') ? mb_strtoupper($value, 'UTF-8') : strtoupper($value);
        };

        $cityFormatted = $city === '' ? '' : $uppercase($city);
        $stateFormatted = $state === '' ? '' : $uppercase($state);

        if ($cityFormatted !== '' && $stateFormatted !== '') {
            return sprintf('%s (%s)', $cityFormatted, $stateFormatted);
        }

        return $cityFormatted !== '' ? $cityFormatted : $stateFormatted;
    }

    private function resolveOmieCategoryItemCode(string $defaultCategoryCode): string
    {
        $configuredCode = trim((string)($this->configModel->getSetting('omie_os_category_item_code') ?? ''));

        if ($configuredCode !== '') {
            return $configuredCode;
        }

        return $defaultCategoryCode;
    }

    private function resolveOmieBankAccountCode(): int
    {
        $configuredAccountCode = trim((string)($this->configModel->getSetting('omie_os_bank_account_code') ?? ''));
        $accountCode = $configuredAccountCode === ''
            ? (string)self::DEFAULT_OMIE_BANK_ACCOUNT_CODE
            : $configuredAccountCode;

        $accountCodeValue = filter_var(
            $accountCode,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );

        if ($accountCodeValue === false) {
            throw new Exception('Configuração Omie: informe um código de conta corrente numérico válido (nCodCC).');
        }

        return (int)$accountCodeValue;
    }

    private function formatOmieDate(?string $date): string
    {
        if (empty($date)) {
            return date('d/m/Y');
        }

        $knownFormats = ['Y-m-d', 'd/m/Y'];
        foreach ($knownFormats as $format) {
            $dateTime = DateTime::createFromFormat($format, $date);
            if ($dateTime instanceof DateTime) {
                return $dateTime->format('d/m/Y');
            }
        }

        try {
            $dateTime = new DateTime($date);
            return $dateTime->format('d/m/Y');
        } catch (Exception $exception) {
            return date('d/m/Y');
        }
    }

    /**
     * Cancela uma Ordem de Serviço na Omie e limpa o número salvo localmente.
     */
    private function cancelarOsOmie(int $processoId)
    {
        try {
            $processoData = $this->processoModel->getById($processoId);
            if (!$processoData) return;
            $processo = $processoData['processo'];
            if (empty($processo['os_numero_omie'])) {
                return;
            }
            $this->omieService->cancelServiceOrder($processo['os_numero_omie']);
            $this->processoModel->limparNumeroOsOmie($processoId);
            $_SESSION['info_message'] = "Ordem de Serviço #" . $processo['os_numero_omie'] . " cancelada na Omie e desvinculada do processo.";
        } catch (Exception $e) {
            error_log("Falha ao cancelar OS na Omie para o processo ID {$processoId}: " . $e->getMessage());
            $_SESSION['warning_message'] = "O processo foi cancelado, mas falhou ao cancelar a OS na Omie: " . $e->getMessage();
        }
    }

    /**
     * Gera uma Ordem de Serviço na Omie a partir de um processo local.
     */
    private function gerarOsOmie(int $processoId): ?string
    {
        try {
            $processoData = $this->processoModel->getById($processoId);
            if (!$processoData) {
                throw new Exception("Processo não encontrado.");
            }
            $processo = $processoData['processo'];
            $documentos = $processoData['documentos'];
            $cliente = $this->clienteModel->getById($processo['cliente_id']);
            if (empty($cliente['omie_id'])) {
                throw new Exception("Cliente não possui ID da Omie. Sincronize o cliente primeiro.");
            }
            if (!empty($processo['os_numero_omie'])) {
                return $processo['os_numero_omie'];
            }

            // Carrega as configurações da Omie do banco de dados
            $omieServiceCode = $this->configModel->getSetting('omie_os_service_code') ?: '1.07';
            $omieCategoryCode = $this->configModel->getSetting('omie_os_category_code') ?: '1.01.02';
            $omieCategoryItemCode = $this->resolveOmieCategoryItemCode($omieCategoryCode);
            $omieBankAccountCode = $this->resolveOmieBankAccountCode();
            $omieServiceTaxationCode = $this->resolveOmieServiceTaxationCode();

            $servicosPrestados = $this->buildServiceItems(
                $processo,
                $documentos,
                $omieServiceCode,
                $omieServiceTaxationCode,
                $omieCategoryItemCode
            );

            if (empty($servicosPrestados)) {
                throw new Exception("O processo não possui serviços para gerar a OS.");
            }

            $payload = [
                'Cabecalho' => $this->buildServiceOrderHeader($processo, $cliente),
                'Email' => $this->buildServiceOrderEmail($cliente),
                'InformacoesAdicionais' => $this->buildServiceOrderAdditionalInfo(
                    $cliente,
                    $omieCategoryCode,
                    $omieBankAccountCode
                ),
                'ServicosPrestados' => $servicosPrestados,
            ];
            $response = $this->omieService->createServiceOrder($payload);
            if (!isset($response['cNumOS'])) {
                throw new Exception('A Omie não retornou o número da OS.');
            }
            $this->processoModel->salvarNumeroOsOmie($processoId, $response['cNumOS']);
            return $response['cNumOS'];
        } catch (Exception $e) {
            error_log("Falha ao gerar OS na Omie para o processo ID {$processoId}: " . $e->getMessage());

            // Mensagem amigável e cumulativa na sessão
            $mensagem = "A operação foi concluída, mas não foi possível gerar a OS na Omie: " . $e->getMessage();
            if (!empty($_SESSION['warning_message'])) {
                $_SESSION['warning_message'] .= ' ' . $mensagem;
            } else {
                $_SESSION['warning_message'] = $mensagem;
            }

            return null;
        }
    }
}
