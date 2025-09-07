<?php
// app/controllers/ProdutosOrcamentoController.php

require_once __DIR__ . '/../models/CategoriaFinanceira.php';

class ProdutosOrcamentoController {
    private $pdo;
    private $categoriaModel;

    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->categoriaModel = new CategoriaFinanceira($pdo);
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
        require_once __DIR__ . '/../views/' . $view . '.php';
        require_once __DIR__ . '/../views/layouts/footer.php';
    }

    public function index() {
        $this->auth_check();
        $produtos = $this->categoriaModel->getProdutosOrcamento();
        $this->render('produtos_orcamento/index', ['produtos' => $produtos]);
    }

    public function store() {
        $this->auth_check();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Valores fixos para produtos de orçamento
            $_POST['tipo_lancamento'] = 'RECEITA';
            $_POST['eh_produto_orcamento'] = 1;
            $_POST['grupo_principal'] = 'Produtos e Serviços'; // Grupo padrão

            if ($this->categoriaModel->createProdutoOrcamento($_POST)) {
                $_SESSION['success_message'] = 'Produto de Orçamento criado com sucesso!';
            } else {
                $_SESSION['error_message'] = 'Erro ao criar o produto.';
            }
            header('Location: produtos_orcamento.php');
            exit();
        }
    }

    public function update($id) {
        $this->auth_check();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if ($this->categoriaModel->updateProdutoOrcamento($id, $_POST)) {
                $_SESSION['success_message'] = 'Produto de Orçamento atualizado com sucesso!';
            } else {
                $_SESSION['error_message'] = 'Erro ao atualizar o produto.';
            }
            header('Location: produtos_orcamento.php');
            exit();
        }
    }

    public function delete($id) {
        $this->auth_check();
        if ($this->categoriaModel->delete($id)) {
            $_SESSION['success_message'] = 'Produto de Orçamento excluído com sucesso!';
        } else {
            $_SESSION['error_message'] = 'Erro ao excluir o produto.';
        }
        header('Location: produtos_orcamento.php');
        exit();
    }
}