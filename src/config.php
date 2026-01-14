<?php
/**
 * @file /src/config.php
 * @description Configurações principais da aplicação.
 */

return [
    'db' => [
        // Use variáveis de ambiente quando possível.
        'host' => getenv('DB_HOST') ?: 'localhost',
        'dbname' => getenv('DB_NAME') ?: 'u371107598_financeiro',
        'user' => getenv('DB_USER') ?: 'u371107598_userfinanca',
        'pass' => getenv('DB_PASS') ?: 'SENHA_SECRET',
        'charset' => 'utf8mb4',
    ],
];
