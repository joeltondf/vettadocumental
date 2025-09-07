<?php
// app/controllers/CategoriasController.php

require_once __DIR__ . '/../models/CategoriaFinanceira.php';

class CategoriasController {
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
        require_once __DIR__ . '/../views/layouts/footer.php';
        require_once __DIR__ . '/../views/' . $view . '.php';
    }

    public function index() {
        $this->auth_check();
        $show_inactive = isset($_GET['show_inactive']) && $_GET['show_inactive'] == '1';
        $categorias = $this->categoriaModel->getCategoriasFinanceiras($show_inactive);
        $grupos_principais = $this->categoriaModel->getGruposPrincipais();
        $pageTitle = "Gerenciar Grupos Financeiros";
        
        $this->render('categorias/index', [
            'categorias' => $categorias,
            'grupos_principais' => $grupos_principais,
            'pageTitle' => $pageTitle,
            'show_inactive' => $show_inactive
        ]);
    }

    public function store() {
        $this->auth_check();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if ($this->categoriaModel->create($_POST)) {
                 $_SESSION['success_message'] = 'Categoria criada com sucesso!';
            } else {
                $_SESSION['error_message'] = 'Erro ao criar a categoria.';
            }
            header('Location: categorias.php');
            exit();
        }
    }

    public function update($id) {
        $this->auth_check();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if ($this->categoriaModel->update($id, $_POST)) {
                $_SESSION['success_message'] = 'Categoria atualizada com sucesso!';
            } else {
                 $_SESSION['error_message'] = 'Erro ao atualizar a categoria.';
            }
            header('Location: categorias.php');
            exit();
        }
    }
    
    // Método para desativar (muda ativo para 0)
    public function deactivate($id) {
        $this->auth_check();
        if ($this->categoriaModel->setActiveStatus($id, 0)) {
            $_SESSION['success_message'] = 'Categoria desativada com sucesso!';
        } else {
            $_SESSION['error_message'] = 'Erro ao desativar a categoria.';
        }
        header('Location: categorias.php');
        exit();
    }

    // Método para reativar (muda ativo para 1)
    public function reactivate($id) {
        $this->auth_check();
        if ($this->categoriaModel->setActiveStatus($id, 1)) {
            $_SESSION['success_message'] = 'Categoria ativada com sucesso!';
        } else {
            $_SESSION['error_message'] = 'Erro ao ativar a categoria.';
        }
        header('Location: categorias.php');
        exit();
    }
    
    // Método para excluir permanentemente
    public function delete_permanente($id) {
        $this->auth_check();
        if ($this->categoriaModel->delete($id)) {
            $_SESSION['success_message'] = 'Categoria excluída permanentemente!';
        } else {
            $_SESSION['error_message'] = 'Erro ao excluir a categoria. Verifique se ela não está em uso.';
        }
        header('Location: categorias.php');
        exit();
    }
}