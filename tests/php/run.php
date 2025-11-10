<?php

declare(strict_types=1);

$autoloadPath = __DIR__ . '/../../vendor/autoload.php';
if (file_exists($autoloadPath)) {
    require_once $autoloadPath;
}
require_once __DIR__ . '/../../app/models/Cliente.php';
require_once __DIR__ . '/../../app/models/Notificacao.php';
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

function buildNotificationTestSchema(PDO $pdo): void
{
    $pdo->exec('CREATE TABLE notificacoes (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        usuario_id INTEGER NOT NULL,
        remetente_id INTEGER NULL,
        mensagem TEXT NOT NULL,
        link TEXT NULL,
        tipo_alerta TEXT NOT NULL,
        referencia_id INTEGER NULL,
        grupo_destino TEXT NOT NULL,
        lida INTEGER NOT NULL DEFAULT 0,
        resolvido INTEGER NOT NULL DEFAULT 0,
        prioridade TEXT NOT NULL DEFAULT "media",
        data_criacao TEXT NOT NULL,
        UNIQUE (usuario_id, tipo_alerta, referencia_id, grupo_destino)
    )');
    $pdo->exec('CREATE TABLE processos (
        id INTEGER PRIMARY KEY,
        status_processo TEXT,
        titulo TEXT,
        cliente_id INTEGER NULL,
        data_criacao TEXT NULL,
        data_entrada TEXT NULL,
        data_previsao_entrega TEXT NULL,
        data_finalizacao_real TEXT NULL
    )');
    $pdo->exec('CREATE TABLE clientes (
        id INTEGER PRIMARY KEY,
        nome_cliente TEXT
    )');
    $pdo->exec('CREATE TABLE users (
        id INTEGER PRIMARY KEY,
        nome_completo TEXT
    )');
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

runTest('normalizePhone accepts short phone numbers', function (): void {
    assertEquals('1234', normalizePhone('1234'));
}, $tests);

runTest('normalizeDDI removes non-digits and validates length', function (): void {
    assertEquals('351', normalizeDDI('+351'));
}, $tests);

runTest('extractPhoneParts returns digits without country code', function (): void {
    $parts = extractPhoneParts('+55 (21) 3456-7890');
    assertEquals('21', $parts['ddd']);
    assertEquals('34567890', $parts['phone']);
}, $tests);

runTest('extractPhoneParts handles short local numbers', function (): void {
    $parts = extractPhoneParts('(11) 1234');
    assertEquals('11', $parts['ddd']);
    assertEquals('1234', $parts['phone']);
}, $tests);

runTest('formatInternationalPhone builds human readable string', function (): void {
    assertEquals('+55 (11) 91234-5678', formatInternationalPhone('55', '11', '912345678'));
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

runTest('Cliente::create persiste DDI quando coluna existe', function (): void {
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $inspectorReflection = new ReflectionClass(DatabaseSchemaInspector::class);
    $cacheProperty = $inspectorReflection->getProperty('columnCache');
    $cacheProperty->setAccessible(true);
    $cacheProperty->setValue(null, []);

    $pdo->exec('CREATE TABLE clientes (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        nome_cliente TEXT NOT NULL,
        nome_responsavel TEXT NULL,
        cpf_cnpj TEXT NULL,
        email TEXT NULL,
        telefone TEXT NULL,
        telefone_ddi TEXT NULL,
        telefone_ddd TEXT NULL,
        telefone_numero TEXT NULL,
        endereco TEXT NULL,
        numero TEXT NULL,
        bairro TEXT NULL,
        cidade TEXT NULL,
        estado TEXT NULL,
        cep TEXT NULL,
        tipo_pessoa TEXT NULL,
        tipo_assessoria TEXT NULL,
        tipo_servico TEXT NULL,
        prazo_acordado_dias INTEGER NULL,
        user_id INTEGER NULL,
        is_prospect INTEGER NOT NULL DEFAULT 0
    )');

    $clienteModel = new Cliente($pdo);

    $data = [
        'nome_cliente' => 'Cliente DDI',
        'nome_responsavel' => 'Responsável',
        'cpf_cnpj' => '12345678000100',
        'email' => 'ddi@example.com',
        'telefone' => '5511987654321',
        'telefone_ddi' => '55',
        'telefone_ddd' => '11',
        'telefone_numero' => '987654321',
        'endereco' => 'Rua Teste',
        'numero' => '123',
        'bairro' => 'Centro',
        'cidade' => 'São Paulo',
        'estado' => 'SP',
        'cep' => '01001000',
        'tipo_pessoa' => 'Jurídica',
        'tipo_assessoria' => 'Mensalista',
        'prazo_acordado_dias' => 10,
    ];

    $newId = $clienteModel->create($data);
    $row = $pdo->query('SELECT telefone_ddi, telefone_ddd, telefone_numero FROM clientes WHERE id = ' . (int) $newId)->fetch(PDO::FETCH_ASSOC);

    assertEquals('55', $row['telefone_ddi']);
    assertEquals('11', $row['telefone_ddd']);
    assertEquals('987654321', $row['telefone_numero']);
}, $tests);

runTest('Notificacao::criar evita duplicidade para alertas ativos', function (): void {
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    buildNotificationTestSchema($pdo);

    $pdo->exec("INSERT INTO processos (id, status_processo, titulo) VALUES (100, 'Orçamento', 'Processo 100')");
    $pdo->exec("INSERT INTO users (id, nome_completo) VALUES (1, 'Gestor')");

    $notificacaoModel = new Notificacao($pdo);
    $notificacaoModel->criar(1, null, 'Mensagem inicial', '/link', 'processo_pendente_orcamento', 100, 'gerencia');
    $pdo->exec("UPDATE notificacoes SET data_criacao = '2023-01-01 00:00:00', lida = 1, resolvido = 1 WHERE id = 1");
    $notificacaoModel->criar(1, null, 'Mensagem atualizada', '/link2', 'processo_pendente_orcamento', 100, 'gerencia');

    $count = (int)$pdo->query('SELECT COUNT(*) FROM notificacoes')->fetchColumn();
    assertEquals(1, $count, 'Deve existir apenas uma notificação ativa.');

    $row = $pdo->query('SELECT mensagem, link, lida, resolvido FROM notificacoes')->fetch(PDO::FETCH_ASSOC);
    assertEquals('Mensagem atualizada', $row['mensagem']);
    assertEquals('/link2', $row['link']);
    assertEquals(0, (int)$row['lida']);
    assertEquals(0, (int)$row['resolvido']);
}, $tests);

runTest('Notificacao::criar mantém notificações distintas por grupo de destino', function (): void {
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    buildNotificationTestSchema($pdo);

    $pdo->exec("INSERT INTO processos (id, status_processo, titulo) VALUES (101, 'Orçamento', 'Processo 101')");
    $pdo->exec("INSERT INTO users (id, nome_completo) VALUES (1, 'Gestor'), (2, 'Analista')");

    $model = new Notificacao($pdo);
    $model->criar(1, null, 'Mensagem gerente', '/link', 'processo_pendente_orcamento', 101, 'gerencia');
    $model->criar(2, null, 'Mensagem vendedor', '/link', 'processo_pendente_orcamento', 101, 'vendedor');

    $count = (int)$pdo->query('SELECT COUNT(*) FROM notificacoes')->fetchColumn();
    assertEquals(2, $count, 'As notificações com grupos distintos devem coexistir.');
}, $tests);

runTest('marcarComoLida propaga leitura para notificações relacionadas', function (): void {
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    buildNotificationTestSchema($pdo);

    $pdo->exec("INSERT INTO processos (id, status_processo, titulo) VALUES (200, 'Orçamento', 'Processo 200')");
    $pdo->exec("INSERT INTO users (id, nome_completo) VALUES (1, 'Gestor'), (2, 'Analista')");

    $notificacaoModel = new Notificacao($pdo);
    $notificacaoModel->criar(1, null, 'Mensagem 1', '/link', 'processo_pendente_orcamento', 200, 'gerencia');
    $notificacaoModel->criar(2, null, 'Mensagem 2', '/link', 'processo_pendente_orcamento', 200, 'gerencia');

    $row = $pdo->query('SELECT id FROM notificacoes WHERE usuario_id = 1')->fetch(PDO::FETCH_ASSOC);
    $firstNotificationId = (int)$row['id'];

    $notificacaoModel->marcarComoLida($firstNotificationId, 1);

    $rows = $pdo->query('SELECT usuario_id, lida FROM notificacoes ORDER BY usuario_id')->fetchAll(PDO::FETCH_ASSOC);
    assertEquals(1, (int)$rows[0]['lida']);
    assertEquals(1, (int)$rows[1]['lida']);
}, $tests);

runTest('getAlertFeed aplica prioridades e filtros agrupados', function (): void {
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    buildNotificationTestSchema($pdo);

    $pdo->exec("INSERT INTO clientes (id, nome_cliente) VALUES (1, 'Cliente A')");
    $pdo->exec("INSERT INTO processos (id, status_processo, titulo, cliente_id) VALUES (300, 'Orçamento', 'Processo A', 1)");
    $pdo->exec("INSERT INTO processos (id, status_processo, titulo, cliente_id) VALUES (301, 'Concluído', 'Processo B', 1)");
    $pdo->exec("INSERT INTO users (id, nome_completo) VALUES (1, 'Gestor')");

    $model = new Notificacao($pdo);
    $model->criar(1, null, 'Orçamento atrasado', '/a', 'processo_pendente_orcamento', 300, 'gerencia');
    $model->criar(1, null, 'Processo concluído', '/b', 'processo_generico', 301, 'gerencia');

    $pdo->exec("UPDATE notificacoes SET data_criacao = '2020-01-01 00:00:00' WHERE referencia_id = 300");

    $result = $model->getAlertFeed(1, 'gerencia', 20, false, 'UTC', ['grouped' => true]);
    assertEquals('alta', $result['groups'][0]['prioridade']);

    $filtered = $model->getAlertFeed(1, 'gerencia', 20, false, 'UTC', [
        'grouped' => true,
        'filters' => ['prioridade' => ['alta']],
    ]);

    assertEquals(1, count($filtered['groups']));
    assertEquals('alta', $filtered['groups'][0]['prioridade']);
}, $tests);

runTest('cron_limpeza_notificacoes arquiva notificações antigas e concluídas', function (): void {
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    buildNotificationTestSchema($pdo);

    $pdo->exec("INSERT INTO processos (id, status_processo) VALUES (400, 'Concluído')");
    $pdo->exec("INSERT INTO processos (id, status_processo) VALUES (401, 'Orçamento Pendente')");
    $pdo->exec("INSERT INTO notificacoes (id, usuario_id, mensagem, link, tipo_alerta, referencia_id, grupo_destino, lida, resolvido, prioridade, data_criacao)
        VALUES (1, 1, 'Antiga', '/a', 'processo_generico', 400, 'gerencia', 0, 0, 'media', '2020-01-01 00:00:00')");
    $pdo->exec("INSERT INTO notificacoes (id, usuario_id, mensagem, link, tipo_alerta, referencia_id, grupo_destino, lida, resolvido, prioridade, data_criacao)
        VALUES (2, 1, 'Atual', '/b', 'processo_generico', 401, 'gerencia', 0, 0, 'media', '2099-01-01 00:00:00')");

    $GLOBALS['pdo'] = $pdo;
    $_ENV['NOTIFICATION_ARCHIVE_DAYS'] = 30;
    ob_start();
    require __DIR__ . '/../../cron_limpeza_notificacoes.php';
    ob_end_clean();
    unset($_ENV['NOTIFICATION_ARCHIVE_DAYS'], $GLOBALS['pdo']);

    $rowOld = $pdo->query('SELECT resolvido, lida FROM notificacoes WHERE id = 1')->fetch(PDO::FETCH_ASSOC);
    $rowNew = $pdo->query('SELECT resolvido FROM notificacoes WHERE id = 2')->fetch(PDO::FETCH_ASSOC);

    assertEquals(1, (int)$rowOld['resolvido']);
    assertEquals(1, (int)$rowOld['lida']);
    assertEquals(0, (int)$rowNew['resolvido']);
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

