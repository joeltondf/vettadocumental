<?php
/**
 * @file /src/Database.php
 * @description Classe responsável por instanciar a conexão PDO.
 */

declare(strict_types=1);

class Database
{
    private PDO $pdo;

    /**
     * @param array $dbConfig Configurações de conexão do banco de dados.
     */
    public function __construct(array $dbConfig)
    {
        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=%s',
            $dbConfig['host'],
            $dbConfig['dbname'],
            $dbConfig['charset']
        );

        $this->pdo = new PDO($dsn, $dbConfig['user'], $dbConfig['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }

    /**
     * @return PDO Retorna a conexão PDO já pronta para uso.
     */
    public function getConnection(): PDO
    {
        return $this->pdo;
    }
}
