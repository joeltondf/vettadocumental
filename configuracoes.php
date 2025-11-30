<?php
session_start();

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/app/core/auth_check.php';
require_once __DIR__ . '/app/core/access_control.php';
require_permission(['admin', 'gerencia']);

$view = isset($_GET['view']) ? preg_replace('/[^a-z_]/', '', $_GET['view']) : 'produtos';

$availableViews = [
    'produtos' => ['title' => 'Produtos de Orçamento', 'url' => APP_URL . '/produtos_orcamento.php'],
    'usuarios' => ['title' => 'Usuários & Permissões', 'url' => APP_URL . '/users.php'],
    'categorias' => ['title' => 'Categorias Financeiras', 'url' => APP_URL . '/categorias.php'],
    'email' => ['title' => 'Configuração de E-mail/SMTP', 'url' => null],
    'tradutores' => ['title' => 'Tradutores', 'url' => APP_URL . '/tradutores.php'],
];

if (!array_key_exists($view, $availableViews)) {
    $view = 'produtos';
}

$viewFile = __DIR__ . '/app/views/admin/' . $view . '.php';

if (!file_exists($viewFile)) {
    http_response_code(404);
    echo 'View não encontrada';
    exit;
}

require __DIR__ . '/app/views/admin/layout_base.php';
