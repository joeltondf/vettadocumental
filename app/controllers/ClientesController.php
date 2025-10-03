<?php
/**
 * @file /app/controllers/ClientesController.php
 * @description Controller para gerir as requisições da entidade 'Cliente'.
 * Inclui envio de e-mail de boas-vindas na criação de login.
 */

// --- INÍCIO DA CORREÇÃO ---
// Todos os 'requires' devem estar no topo do arquivo para garantir que as classes estejam disponíveis.
require_once __DIR__ . '/../models/Cliente.php';
require_once __DIR__ . '/../services/EmailService.php';
require_once __DIR__ . '/../models/CategoriaFinanceira.php';
// --- FIM DA CORREÇÃO ---

class ClientesController
{
    private $clienteModel;
    private $pdo; // <-- CORREÇÃO: Propriedade adicionada para guardar a conexão

    public function __construct($pdo)
    {
        $this->clienteModel = new Cliente($pdo);
        $this->pdo = $pdo; // <-- CORREÇÃO: A conexão agora é armazenada no controller
    }

    // =======================================================================
    // AÇÕES CRUD PARA CLIENTES (ATUALIZADAS)
    // =======================================================================

    /**
     * Exibe a página com a lista de todos os clientes.
     */
    public function index()
    {
        $pageTitle = "Gestão de Clientes";
        $clientes = $this->clienteModel->getAppClients();
        require __DIR__ . '/../views/layouts/header.php';
        require __DIR__ . '/../views/clientes/lista.php';
        require __DIR__ . '/../views/layouts/footer.php';
    }

    /**
     * Exibe o formulário de criação de um novo cliente.
     */
    public function create()
    {
        $pageTitle = "Novo Cliente";
        $cliente = $_SESSION['form_data'] ?? [];
        unset($_SESSION['form_data']);
        $isEdit = false;
        $return_url = $_GET['return_to'] ?? 'clientes.php';

        // Agora o model pode ser criado sem erro, pois a classe foi incluída no topo e $this->pdo existe.
        $categoriaModel = new CategoriaFinanceira($this->pdo);
        $produtos_orcamento = $categoriaModel->getProdutosOrcamento();
        
        // Em modo de criação, não há serviços definidos, então inicializamos como um array vazio.
        $servicos_mensalista = [];

        require_once __DIR__ . '/../views/layouts/header.php';
        require_once __DIR__ . '/../views/clientes/form.php';
        require_once __DIR__ . '/../views/layouts/footer.php';
    }

    /**
     * Processa e armazena o novo cliente. Envia e-mail se um login for criado.
     */
    public function store()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = $_POST;
            if (isset($data['tipo_pessoa']) && $data['tipo_pessoa'] === 'Física') {
                $data['nome_responsavel'] = $data['nome_cliente'];
            }

            $result = $this->clienteModel->create($data);
            if (session_status() == PHP_SESSION_NONE) session_start();
            $return_to = $_POST['return_to'] ?? 'clientes.php';

            if ($result && is_numeric($result)) {
                $newClientId = $result;
                $_SESSION['success_message'] = "Cliente cadastrado com sucesso!";

                // Salva os serviços do mensalista, se for o caso
                if ($_POST['tipo_assessoria'] === 'Mensalista') {
                    $this->clienteModel->salvarServicosMensalista($newClientId, $_POST['servicos_mensalistas'] ?? []);
                }

                if (!empty($_POST['criar_login']) && !empty($_POST['login_email'])) {
                    $this->sendWelcomeEmail($_POST['nome_cliente'], $_POST['login_email'], $_POST['login_senha']);
                }
                
                $separator = (parse_url($return_to, PHP_URL_QUERY) == NULL) ? '?' : '&';
                header('Location: ' . $return_to . $separator . 'new_client_id=' . $newClientId);
                exit();
            } else {
                $_SESSION['error_message'] = ($result === 'error_duplicate_cpf_cnpj') 
                    ? "O CPF/CNPJ informado já está em uso por outro cliente." 
                    : "Erro ao cadastrar cliente.";
                $_SESSION['form_data'] = $_POST;
                header('Location: clientes.php?action=create&return_to=' . urlencode($return_to));
                exit();
            }
        }
    }

    /**
     * Exibe o formulário de edição para um cliente existente.
     */
    public function edit($id)
    {
        $pageTitle = "Editar Cliente";
        $cliente = $this->clienteModel->getById($id);
        $isEdit = true;

        $categoriaModel = new CategoriaFinanceira($this->pdo);
        $produtos_orcamento = $categoriaModel->getProdutosOrcamento();
        $servicos_mensalista = $this->clienteModel->getServicosMensalista($id);

        require_once __DIR__ . '/../views/layouts/header.php';
        require_once __DIR__ . '/../views/clientes/form.php';
        require_once __DIR__ . '/../views/layouts/footer.php';
    }

    /**
     * Processa a atualização do cliente. Envia e-mail se um login for criado ou senha alterada.
     */
    public function update()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $id = $_POST['id'];
            $data = $_POST;
            if (isset($data['tipo_pessoa']) && $data['tipo_pessoa'] === 'Física') {
                $data['nome_responsavel'] = $data['nome_cliente'];
            }

            $result = $this->clienteModel->update($id, $data);
            if (session_status() == PHP_SESSION_NONE) session_start();
            
            if ($result === true) {
                $_SESSION['success_message'] = "Cliente atualizado com sucesso!";

                // Atualiza ou limpa os serviços do mensalista
                if ($_POST['tipo_assessoria'] === 'Mensalista') {
                    $this->clienteModel->salvarServicosMensalista($id, $_POST['servicos_mensalistas'] ?? []);
                } else {
                    $this->clienteModel->salvarServicosMensalista($id, []); // Limpa se mudou para "À vista"
                }

            // ========================================================================
            // INÍCIO DA LÓGICA DE REDIRECIONAMENTO PÓS-PROSPECÇÃO
            // ========================================================================
            // Verifica se os campos ocultos do fluxo de prospecção foram enviados.
            if (isset($_POST['continue_prospeccao_id'], $_POST['continue_nome_servico'], $_POST['continue_valor_inicial'])) {
                // Se viemos do fluxo de aprovação, montamos a URL e redirecionamos
                // para o formulário de criação de orçamento (processo), já preenchendo os campos.
                $queryParams = http_build_query([
                    'cliente_id' => $id,
                    'titulo' => $_POST['continue_nome_servico'],
                    'valor_total' => $_POST['continue_valor_inicial']
                ]);
                header('Location: ' . APP_URL . '/processos.php?action=create&' . $queryParams);
                exit();
            }
            // ========================================================================
            // FIM DA LÓGICA DE REDIRECIONAMENTO
            // ========================================================================

            // Redirecionamento padrão se não for o fluxo de prospecção
                header('Location: clientes.php');
                exit();
            } else {
                $_SESSION['error_message'] = ($result === 'error_duplicate_cpf_cnpj') 
                    ? "O CPF/CNPJ informado já está em uso por outro cliente." 
                    : "Erro ao atualizar cliente.";
                header('Location: clientes.php?action=edit&id=' . $id);
                exit();
            }
        }
    }

    /**
     * Deleta um cliente.
     */
    public function delete($id)
    {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }

        if ($this->clienteModel->delete($id)) {
            $_SESSION['success_message'] = "Cliente excluído com sucesso!";
        } else {
            $_SESSION['error_message'] = "Não é possível excluir o cliente, pois ele está associado a um ou mais processos.";
        }

        header('Location: clientes.php');
        exit();
    }


    // =======================================================================
    // MÉTODO PRIVADO PARA ENVIO DE E-MAIL
    // =======================================================================

    /**
     * Monta e envia um e-mail de boas-vindas para o cliente.
     * @param string $clientName Nome do cliente.
     * @param string $recipientEmail E-mail do cliente (que será o login).
     * @param string $password A senha em texto plano (enviada apenas na criação).
     */
    private function sendWelcomeEmail($clientName, $recipientEmail, $password)
    {
        try {
            $emailService = new EmailService($this->clienteModel->getPdo());

            $assunto = "Seu acesso ao Portal do Cliente foi criado!";
            
            // Define a URL de login do seu site
            $loginUrl = "https://teste.cliente.pro/login.php"; // <-- CONFIRME SE ESTA É A URL CORRETA

            $corpo = "
                <div style='font-family: Arial, sans-serif; line-height: 1.6;'>
                    <h2>Olá, " . htmlspecialchars($clientName) . "!</h2>
                    <p>Seu acesso ao nosso Portal do Cliente foi criado com sucesso. Agora você pode acompanhar seus processos online.</p>
                    <p>Utilize os dados abaixo para acessar:</p>
                    <div style='background-color: #f2f2f2; padding: 15px; border-radius: 5px; margin: 20px 0;'>
                        <p><strong>Site para Acesso:</strong> <a href='" . $loginUrl . "'>" . $loginUrl . "</a></p>
                        <p><strong>Login:</strong> " . htmlspecialchars($recipientEmail) . "</p>
                        <p><strong>Sua Senha:</strong> " . htmlspecialchars($password) . "</p>
                    </div>
                    <p>Atenciosamente,<br>Equipe FATTO</p>
                </div>
            ";
            
            $emailService->sendEmail($recipientEmail, $assunto, $corpo);

        } catch (Exception $e) {
            // Se o envio falhar, loga o erro mas não interrompe o fluxo do usuário
            error_log('Falha ao enviar e-mail de boas-vindas para ' . $recipientEmail . ': ' . $e->getMessage());
        }
    }
    
        /**
     * Sincroniza um cliente local com a Conta Azul.
     * Tenta encontrar pelo nome, se não achar, cria um novo.
     * @param int $id ID do cliente no banco de dados local.
     */
/**
     * Busca um cliente na Conta Azul pelo nome e vincula o UUID retornado.
     * Não tenta mais criar o cliente.
     * @param int $id ID do cliente no banco de dados local.
     */
    public function syncContaAzul($id)
    {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }

        try {
            // Inclui os serviços e models necessários
            require_once __DIR__ . '/../models/Configuracao.php';
            require_once __DIR__ . '/../services/ContaAzulService.php';
            
            $configModel = new Configuracao($this->clienteModel->getPdo());
            $contaAzulService = new ContaAzulService($configModel);

            // Pega os dados do cliente do nosso banco
            $cliente = $this->clienteModel->getById($id);
            if (!$cliente) {
                throw new Exception("Cliente local com ID {$id} não encontrado.");
            }

            // Se já está vinculado, informa o usuário e não faz nada.
            if (!empty($cliente['conta_azul_uuid'])) {
                $_SESSION['success_message'] = "Este cliente já está vinculado.";
                header('Location: clientes.php?action=edit&id=' . $id);
                exit();
            }

            // Passo 1: Busca o cliente na Conta Azul pelo nome
            $clienteCa = $contaAzulService->findCustomerByName($cliente['nome_cliente']);

            // Passo 2: Verifica o resultado da busca
            if ($clienteCa && isset($clienteCa['uuid'])) {
                // ENCONTROU! Vincula o UUID ao cliente local.
                $contaAzulUuid = $clienteCa['uuid'];
                $this->clienteModel->linkContaAzulUuid($id, $contaAzulUuid);
                $_SESSION['success_message'] = "Cliente encontrado e vinculado com sucesso! UUID: " . $contaAzulUuid;
            } else {
                // NÃO ENCONTROU! Informa o usuário que ele precisa criar na plataforma.
                throw new Exception("Nenhum cliente com o nome '{$cliente['nome_cliente']}' foi encontrado na sua conta da Conta Azul. Por favor, crie o cliente na plataforma da Conta Azul primeiro.");
            }

        } catch (Exception $e) {
            $_SESSION['error_message'] = "Erro na busca: " . $e->getMessage();
        }

        // Redireciona de volta para a página de edição
        header('Location: clientes.php?action=edit&id=' . $id);
        exit();
    }
    
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

            // --- Bloco 2: Validação dos Pré-requisitos ---
            // Pega o UUID do cliente que JÁ DEVE ESTAR VINCULADO
            $clienteIdContaAzul = $cliente['conta_azul_uuid'] ?? null;
            if (empty($clienteIdContaAzul)) {
                throw new Exception("Operação cancelada. O cliente '{$cliente['nome_cliente']}' não possui um UUID da Conta Azul vinculado. Por favor, vá na tela de edição do cliente e clique no botão 'Buscar e Vincular UUID' primeiro.");
            }

            if (empty($documentos)) {
                throw new Exception("Não há itens (documentos) neste processo para criar a venda.");
            }

            // --- Bloco 3: CRIAÇÃO de um novo serviço para cada item ---
            $itensPayload = [];
            $calculatedTotal = 0.0;

            foreach ($documentos as $doc) {
                // Prepara os dados para criar um novo serviço na Conta Azul
                $newProductPayload = [
                    "nome"        => $doc['tipo_documento'] . ' - Proc. ' . $processo['id'], // Nome único para o serviço
                    "valor_venda" => (float)($doc['valor_unitario'] ?? 0),
                    "tipo"        => "SERVICO" // Cria como SERVIÇO para não ter controle de estoque
                ];
                
                // Chama a API para criar o serviço
                $createdProduct = $this->contaAzulService->createProduct($newProductPayload);

                if (isset($createdProduct['id'])) {
                    $contaAzulServicoId = $createdProduct['id'];
                    
                    // IMPORTANTE: Usando quantidade 1 para criar uma venda válida.
                    $quantidade = 1;
                    $valorUnitario = (float)($doc['valor_unitario'] ?? 0);

                    // Adiciona o item recém-criado ao payload da venda
                    $itensPayload[] = ["id" => $contaAzulServicoId, "quantidade" => $quantidade, "valor" => $valorUnitario];
                    $calculatedTotal += $quantidade * $valorUnitario;

                } else {
                    throw new Exception("Falha ao criar o serviço '{$doc['tipo_documento']}' na Conta Azul. Resposta da API: " . json_encode($createdProduct));
                }
            }
            
            // --- Bloco 4: Montagem e Envio da Venda Final ---
            $salePayload = [
                "id_cliente" => $clienteIdContaAzul,
                "numero"     => (int)$processo['id'], // Usando o ID do processo como número único e obrigatório
                "situacao"   => "APROVADO",
                "data_venda" => date('Y-m-d'),
                "itens"      => $itensPayload,
                "condicao_pagamento" => [
                    "opcao_condicao_pagamento" => "À vista",
                    "tipo_pagamento"         => "DINHEIRO",
                    "parcelas"               => [ ["data_vencimento" => date('Y-m-d'), "valor" => $calculatedTotal] ]
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
    
  


}