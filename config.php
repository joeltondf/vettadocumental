<?php
/**
 * @file /config.php
 * @description Bootstrap de configuração global da aplicação.
 */

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

date_default_timezone_set('America/Sao_Paulo');

define('BASE_PATH', __DIR__ . DIRECTORY_SEPARATOR);

$autoloadPath = BASE_PATH . 'vendor/autoload.php';
if (file_exists($autoloadPath)) {
    require_once $autoloadPath;
}

// Recomenda-se usar o pacote vlucas/phpdotenv para carregar variáveis do arquivo .env.
if (class_exists('Dotenv\\Dotenv')) {
    Dotenv\Dotenv::createImmutable(BASE_PATH)->safeLoad();
}

require_once BASE_PATH . 'src/Database.php';

// 1. Configurações de Conexão com a Base de Dados
$config = require BASE_PATH . 'src/config.php';
$dbConfig = $config['db'] ?? [];

define('DB_HOST', $dbConfig['host'] ?? 'localhost');
define('DB_NAME', $dbConfig['dbname'] ?? '');
define('DB_USER', $dbConfig['user'] ?? '');
define('DB_PASS', $dbConfig['pass'] ?? '');
define('DB_CHARSET', $dbConfig['charset'] ?? 'utf8mb4');

// 2. Configurações Gerais da Aplicação
$defaultAppUrl = getenv('APP_URL') ?: 'https://app.vettadocumental.com';
$defaultAppName = getenv('APP_NAME') ?: 'Vetta Documental';

define('APP_URL', $defaultAppUrl);
define('APP_NAME', $defaultAppName);

// 3. Iniciar a Sessão
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 4. Conexão PDO com a Base de Dados
try {
    $database = new Database($dbConfig);
    $pdo = $database->getConnection();
} catch (PDOException $e) {
    // Em caso de erro, encerra a execução com uma mensagem clara.
    die('Erro na conexão com a base de dados: ' . $e->getMessage());
}

// 5. Configurações de E-mail
$defaultEmailHost = getenv('EMAIL_HOST') ?: 'smtp.example.com';
$defaultEmailUser = getenv('EMAIL_USERNAME') ?: 'nao-responda@exemplo.com';
$defaultEmailPass = getenv('EMAIL_PASSWORD') ?: '';
$defaultEmailPort = getenv('EMAIL_PORT') ?: 465;
$defaultEmailFromAddress = getenv('EMAIL_FROM_ADDRESS') ?: 'nao-responda@exemplo.com';
$defaultEmailFromName = getenv('EMAIL_FROM_NAME') ?: 'Vetta Documental';

define('EMAIL_HOST', $defaultEmailHost);
define('EMAIL_USERNAME', $defaultEmailUser);
define('EMAIL_PASSWORD', $defaultEmailPass);
define('EMAIL_PORT', (int) $defaultEmailPort);
define('EMAIL_FROM_ADDRESS', $defaultEmailFromAddress);
define('EMAIL_FROM_NAME', $defaultEmailFromName);
