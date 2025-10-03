<?php

declare(strict_types=1);

$autoloadPath = __DIR__ . '/../../vendor/autoload.php';
if (file_exists($autoloadPath)) {
    require_once $autoloadPath;
}
require_once __DIR__ . '/../../app/models/Cliente.php';
require_once __DIR__ . '/../../app/utils/OmiePayloadBuilder.php';

$tests = [];

function runTest(string $name, callable $callback, array &$tests): void
{
    try {
        $callback();
        $tests[] = ['name' => $name, 'status' => 'passed'];
    } catch (Throwable $exception) {
        $tests[] = [
            'name' => $name,
            'status' => 'failed',
            'message' => $exception->getMessage(),
        ];
    }
}

function assertEquals($expected, $actual, string $message = ''): void
{
    if ($expected !== $actual) {
        $formatted = $message !== '' ? $message : sprintf('Failed asserting that %s matches expected %s.', var_export($actual, true), var_export($expected, true));
        throw new RuntimeException($formatted);
    }
}

runTest('normalizeDDD strips non-digits and validates length', function (): void {
    assertEquals('21', normalizeDDD('(21)'));
}, $tests);

runTest('normalizePhone removes DDD and non-digit characters', function (): void {
    assertEquals('34567890', normalizePhone('(21) 3456-7890'));
}, $tests);

runTest('normalizePhone trims country code when present', function (): void {
    assertEquals('912345678', normalizePhone('+55 (11) 91234-5678'));
}, $tests);

runTest('extractPhoneParts returns digits without country code', function (): void {
    $parts = extractPhoneParts('+55 (21) 3456-7890');
    assertEquals('21', $parts['ddd']);
    assertEquals('34567890', $parts['phone']);
}, $tests);

runTest('updateIntegrationIdentifiers skips integration column when absent', function (): void {
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('CREATE TABLE clientes (id INTEGER PRIMARY KEY AUTOINCREMENT, nome_cliente TEXT, omie_id INTEGER)');
    $pdo->exec("INSERT INTO clientes (nome_cliente, omie_id) VALUES ('Cliente Teste', NULL)");

    $clienteModel = new Cliente($pdo);
    $result = $clienteModel->updateIntegrationIdentifiers(1, 'CLI-000001', 123456);
    assertEquals(true, $result, 'O método deve retornar true mesmo sem a coluna de integração.');

    $row = $pdo->query('SELECT omie_id FROM clientes WHERE id = 1')->fetch(PDO::FETCH_ASSOC);
    assertEquals(123456, (int) $row['omie_id'], 'O campo omie_id deve ser atualizado.');
}, $tests);

runTest('buildAlterarClientePayload privilegia codigo_cliente_omie', function (): void {
    $payload = OmiePayloadBuilder::buildAlterarClientePayload([
        'id' => 10,
        'nome_cliente' => 'Empresa Teste',
        'telefone' => '(11) 91234-5678',
        'cidade' => 'São Paulo',
        'estado' => 'sp',
        'codigo_cliente_integracao' => 'CLI-000010',
        'omie_id' => 998877,
    ]);

    assertEquals(998877, $payload['codigo_cliente_omie']);
    if (isset($payload['codigo_cliente_integracao'])) {
        throw new RuntimeException('codigo_cliente_integracao não deve estar presente quando codigo_cliente_omie está definido.');
    }
    assertEquals('11', $payload['telefone1_ddd']);
    assertEquals('912345678', $payload['telefone1_numero']);
}, $tests);

runTest('buildAlterarClientePayload utiliza telefone pré-normalizado', function (): void {
    $payload = OmiePayloadBuilder::buildAlterarClientePayload([
        'nome_cliente' => 'Empresa Rio',
        'telefone_ddd' => '21',
        'telefone_numero' => '34567890',
        'cidade' => 'Rio de Janeiro',
        'estado' => 'RJ',
        'codigo_cliente_integracao' => 'CLI-000020',
    ]);

    assertEquals('21', $payload['telefone1_ddd']);
    assertEquals('34567890', $payload['telefone1_numero']);
}, $tests);

foreach ($tests as $test) {
    $prefix = $test['status'] === 'passed' ? '[PASS]' : '[FAIL]';
    echo $prefix . ' ' . $test['name'] . PHP_EOL;
    if ($test['status'] === 'failed' && isset($test['message'])) {
        echo '  ' . $test['message'] . PHP_EOL;
    }
}

$failures = array_filter($tests, static fn (array $test) => $test['status'] !== 'passed');
if (!empty($failures)) {
    exit(1);
}

