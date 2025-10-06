<?php

declare(strict_types=1);

class OmieProduto
{
    private const TABLE_NAME = 'omie_produtos';

    private PDO $pdo;
    private array $columnExistsCache = [];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function upsert(array $data): void
    {
        $descricao = $this->normalizeString($data['descricao'] ?? '');
        if ($descricao === null) {
            throw new InvalidArgumentException('A descrição do produto é obrigatória para o cadastro na tabela omie_produtos.');
        }

        $hasLocalProductId = array_key_exists('local_produto_id', $data);
        $hasValorUnitario = array_key_exists('valor_unitario', $data);
        $hasAtivo = array_key_exists('ativo', $data);

        $localProductId = $hasLocalProductId
            ? $this->normalizeLocalProductId($data['local_produto_id'])
            : null;
        $codigo = $this->normalizeProductCode($data['codigo'] ?? null);
        $codigoProduto = $this->normalizeString($data['codigo_produto'] ?? null);
        $codigoIntegracao = $this->normalizeString($data['codigo_integracao'] ?? null);
        $cfop = $this->normalizeString($data['cfop'] ?? null);
        $codigoServicoMunicipal = $this->normalizeMunicipalCode($data['codigo_servico_municipal'] ?? null);
        $ncm = $this->normalizeNcm($data['ncm'] ?? null);
        $unidade = $this->normalizeString($data['unidade'] ?? null);
        $valorUnitario = $hasValorUnitario
            ? $this->normalizeDecimal($data['valor_unitario'])
            : null;
        $ativo = $hasAtivo ? (int)$data['ativo'] : null;

        $existing = $this->resolveExistingProduct($localProductId, $codigoProduto, $codigoIntegracao, $codigo);

        if ($existing) {
            $localProductId = $localProductId ?? $this->normalizeLocalProductId($existing['local_produto_id'] ?? null);
            $codigo = $codigo ?? $this->normalizeProductCode($existing['codigo'] ?? null);
            $codigoProduto = $codigoProduto ?? $this->normalizeString($existing['codigo_produto'] ?? null);
            $codigoIntegracao = $codigoIntegracao ?? $this->normalizeString($existing['codigo_integracao'] ?? null);
            $cfop = $cfop ?? $this->normalizeString($existing['cfop'] ?? null);
            $codigoServicoMunicipal = $codigoServicoMunicipal
                ?? $this->normalizeMunicipalCode($existing['codigo_servico_municipal'] ?? null);
            $ncm = $ncm ?? $this->normalizeNcm($existing['ncm'] ?? null);
            $unidade = $unidade ?? $this->normalizeString($existing['unidade'] ?? null);
            $valorUnitario = $valorUnitario ?? $this->normalizeDecimal($existing['valor_unitario'] ?? null);
            $ativo = $hasAtivo ? ($ativo ?? 0) : (int)($existing['ativo'] ?? 1);
            $columnsToUpdate = $this->filterAvailableColumns([
                'descricao' => $descricao,
                'codigo' => $codigo,
                'codigo_produto' => $codigoProduto,
                'codigo_integracao' => $codigoIntegracao,
                'cfop' => $cfop,
                'codigo_servico_municipal' => $codigoServicoMunicipal,
                'ncm' => $ncm,
                'unidade' => $unidade,
                'valor_unitario' => $valorUnitario,
                'local_produto_id' => $localProductId,
                'ativo' => $ativo ?? 1,
            ]);

            $setClauses = [];
            $params = [];

            foreach ($columnsToUpdate as $column => $value) {
                $placeholder = ':' . $column;
                $setClauses[] = $column . ' = ' . $placeholder;
                $params[$placeholder] = $value;
            }

            if (!$setClauses) {
                return;
            }

            $params[':id'] = (int)$existing['id'];

            $updateSql = 'UPDATE ' . self::TABLE_NAME . ' SET ' . implode(', ', $setClauses) . ' WHERE id = :id';

            $stmt = $this->pdo->prepare($updateSql);
            $stmt->execute($params);

            return;
        }

        $columnsToInsert = $this->filterAvailableColumns([
            'descricao' => $descricao,
            'codigo' => $codigo,
            'codigo_produto' => $codigoProduto,
            'codigo_integracao' => $codigoIntegracao,
            'cfop' => $cfop,
            'codigo_servico_municipal' => $codigoServicoMunicipal,
            'ncm' => $ncm,
            'unidade' => $unidade,
            'valor_unitario' => $valorUnitario,
            'local_produto_id' => $localProductId,
            'ativo' => $hasAtivo ? ($ativo ?? 0) : 1,
        ]);

        $columns = array_keys($columnsToInsert);
        if (!$columns) {
            throw new RuntimeException('Nenhuma coluna disponível para inserir em omie_produtos.');
        }
        $placeholders = array_map(static fn(string $column): string => ':' . $column, $columns);

        $insertSql = 'INSERT INTO ' . self::TABLE_NAME
            . ' (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $placeholders) . ')';

        $params = [];
        foreach ($columns as $index => $column) {
            $params[$placeholders[$index]] = $columnsToInsert[$column];
        }

        $stmt = $this->pdo->prepare($insertSql);
        $stmt->execute($params);
    }

    public function findByLocalProductId(int $localProductId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM omie_produtos WHERE local_produto_id = :id LIMIT 1');
        $stmt->execute([':id' => $localProductId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    public function deactivateByLocalProductId(int $localProductId): bool
    {
        $stmt = $this->pdo->prepare('UPDATE omie_produtos SET ativo = 0 WHERE local_produto_id = :id');
        $stmt->execute([':id' => $localProductId]);

        return $stmt->rowCount() > 0;
    }

    public function deleteByLocalProductId(int $localProductId): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM omie_produtos WHERE local_produto_id = :id');
        $stmt->execute([':id' => $localProductId]);

        return $stmt->rowCount() > 0;
    }

    public function findByCodigoProduto(string $codigoProduto): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM omie_produtos WHERE codigo_produto = :codigo LIMIT 1');
        $stmt->execute([':codigo' => $codigoProduto]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    public function findByCodigoIntegracao(string $codigoIntegracao): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM omie_produtos WHERE codigo_integracao = :codigo LIMIT 1');
        $stmt->execute([':codigo' => $codigoIntegracao]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    public function findByCodigo(string $codigo): ?array
    {
        if (!$this->hasColumn('codigo')) {
            return null;
        }

        $stmt = $this->pdo->prepare('SELECT * FROM omie_produtos WHERE codigo = :codigo LIMIT 1');
        $stmt->execute([':codigo' => $codigo]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ?: null;
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM omie_produtos WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    public function getAll(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM omie_produtos ORDER BY descricao');
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function updateById(int $id, array $data): bool
    {
        $columnsToUpdate = $this->filterAvailableColumns([
            'descricao' => $this->normalizeString($data['descricao'] ?? ''),
            'codigo' => $this->normalizeProductCode($data['codigo'] ?? null),
            'codigo_produto' => $this->normalizeString($data['codigo_produto'] ?? null),
            'codigo_integracao' => $this->normalizeString($data['codigo_integracao'] ?? null),
            'cfop' => $this->normalizeString($data['cfop'] ?? null),
            'codigo_servico_municipal' => $this->normalizeMunicipalCode($data['codigo_servico_municipal'] ?? null),
            'ncm' => $this->normalizeNcm($data['ncm'] ?? null),
            'unidade' => $this->normalizeString($data['unidade'] ?? null),
            'valor_unitario' => $this->normalizeDecimal($data['valor_unitario'] ?? null),
            'ativo' => isset($data['ativo']) ? (int)$data['ativo'] : 0,
        ]);

        $setClauses = [];
        $params = [];

        foreach ($columnsToUpdate as $column => $value) {
            $placeholder = ':' . $column;
            $setClauses[] = $column . ' = ' . $placeholder;
            $params[$placeholder] = $value;
        }

        if (!$setClauses) {
            return false;
        }

        $params[':id'] = $id;

        $sql = 'UPDATE ' . self::TABLE_NAME . ' SET ' . implode(', ', $setClauses) . ' WHERE id = :id';

        $stmt = $this->pdo->prepare($sql);

        return $stmt->execute($params);
    }

    private function resolveExistingProduct(
        ?int $localProductId,
        ?string $codigoProduto,
        ?string $codigoIntegracao,
        ?string $codigo
    ): ?array {
        if ($localProductId !== null) {
            $record = $this->findByLocalProductId($localProductId);
            if ($record) {
                return $record;
            }
        }

        if ($codigoProduto !== null) {
            $record = $this->findByCodigoProduto($codigoProduto);
            if ($record) {
                return $record;
            }
        }

        if ($codigoIntegracao !== null) {
            $record = $this->findByCodigoIntegracao($codigoIntegracao);
            if ($record) {
                return $record;
            }
        }

        if ($codigo !== null) {
            return $this->findByCodigo($codigo);
        }

        return null;
    }

    private function normalizeMunicipalCode($value): ?string
    {
        $normalized = $this->normalizeString($value);
        if ($normalized === null) {
            return null;
        }

        return mb_substr($normalized, 0, 10);
    }

    private function normalizeNcm($value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_int($value)) {
            $value = (string)$value;
        } elseif (is_float($value)) {
            $value = (string)(int)$value;
        } elseif (!is_string($value)) {
            return null;
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $trimmed);
        if ($digits === '') {
            return null;
        }

        return substr($digits, 0, 8);
    }

    private function normalizeProductCode($value): ?string
    {
        $normalized = $this->normalizeString($value);
        if ($normalized === null) {
            return null;
        }

        return mb_substr($normalized, 0, 50);
    }

    private function normalizeString($value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim((string)$value);
        return $normalized === '' ? null : $normalized;
    }

    private function normalizeDecimal($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return number_format((float)$value, 4, '.', '');
        }

        $normalized = str_replace(['R$', ' '], '', (string)$value);
        if (strpos($normalized, ',') !== false && strpos($normalized, '.') !== false) {
            $normalized = str_replace('.', '', $normalized);
            $normalized = str_replace(',', '.', $normalized);
        } elseif (strpos($normalized, ',') !== false) {
            $normalized = str_replace(',', '.', $normalized);
        }

        return is_numeric($normalized) ? number_format((float)$normalized, 4, '.', '') : null;
    }

    private function normalizeLocalProductId($value): ?int
    {
        if ($value === null) {
            return null;
        }

        if (is_int($value)) {
            $id = $value;
        } elseif (is_numeric($value)) {
            $id = (int)$value;
        } else {
            return null;
        }

        return $id > 0 ? $id : null;
    }

    private function hasColumn(string $column): bool
    {
        if (array_key_exists($column, $this->columnExistsCache)) {
            return $this->columnExistsCache[$column];
        }

        $safeTable = str_replace('`', '``', self::TABLE_NAME);
        $stmt = $this->pdo->prepare("SHOW COLUMNS FROM `{$safeTable}` LIKE :column");
        $stmt->execute(['column' => $column]);

        $this->columnExistsCache[$column] = (bool)$stmt->fetch(PDO::FETCH_ASSOC);

        return $this->columnExistsCache[$column];
    }

    private function filterAvailableColumns(array $values): array
    {
        $available = [];

        foreach ($values as $column => $value) {
            if ($this->hasColumn($column)) {
                $available[$column] = $value;
            }
        }

        return $available;
    }
}
