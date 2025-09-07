<?php
// /conta_azul_callback.php (Versão Corrigida e Simplificada)

/**
 * Este arquivo é o ponto de retorno do fluxo de autorização da Conta Azul.
 */

// 1. Carrega TODAS as configurações e a conexão com o banco ($pdo)
require_once __DIR__ . '/config.php';

// 2. Carrega as classes que o AdminCaController vai precisar
require_once __DIR__ . '/app/models/Configuracao.php';
require_once __DIR__ . '/app/services/ContaAzulService.php';

// 3. Carrega o controlador que vamos usar
require_once __DIR__ . '/app/controllers/AdminCaController.php';

// --- EXECUÇÃO ---
// Como o config.php já foi incluído, a variável $pdo já existe e está pronta para ser usada.
// Não precisamos mais do bloco try/catch para a conexão aqui.

// Instancia o controlador, passando a conexão $pdo que veio do config.php
$controller = new AdminCaController($pdo);

// Chama a ação de callback
$controller->callback();