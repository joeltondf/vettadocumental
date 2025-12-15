<?php
/**
 * @file /app/models/Processo.php
 * @description Model responsável por toda a interação com a base de dados
 * para a entidade 'Processo' e seus dados relacionados.
 */

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/Configuracao.php';

class Processo
{
    public const TV_PANEL_EXCLUDED_STATUSES = ['Concluído', 'Finalizado', 'Cancelado', 'Recusado'];
    private $pdo;
    private array $processColumns = [];
    private ?int $defaultVendorId = null;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
        $this->loadProcessColumns();
    }

    private function getDefaultVendorId(): ?int
    {
        if ($this->defaultVendorId !== null) {
            return $this->defaultVendorId;
        }

        $configuracao = new Configuracao($this->pdo);
        $value = $configuracao->get('default_vendedor_id');
        $this->defaultVendorId = $value !== null && $value !== '' ? (int)$value : null;

        return $this->defaultVendorId;
    }

    /**
     * Garante que processos sem seleção de vendedor usem o vendedor padrão definido em configurações.
     */
    private function resolveVendorId(array $data): ?int
    {
        $vendorId = $data['vendedor_id'] ?? $data['id_vendedor'] ?? null;
        if (empty($vendorId)) {
            return $this->getDefaultVendorId();
        }

        return (int)$vendorId;
    }
	
    public function parseCurrency($value) {
        if ($value === null || $value === '') {
            return null;
        }

        // normaliza e remove símbolos
        $value = trim(str_replace(['R$', ' '], '', (string)$value));

        // mantém apenas dígitos, vírgula e ponto
        $value = preg_replace('/[^0-9,.\-]/', '', $value);

        $hasComma = strpos($value, ',') !== false;
        $hasDot   = strpos($value, '.') !== false;

        if ($hasComma && $hasDot) {
            // Formato BR (milhar com ponto e decimal com vírgula): 1.234,56
            $value = str_replace('.', '', $value);    // remove milhar
            $value = str_replace(',', '.', $value);   // vírgula -> ponto decimal
        } elseif ($hasComma) {
            // Só vírgula: 123,45
            $value = str_replace(',', '.', $value);
        } else {
            // Só ponto ou só dígitos: 123.45 ou 12345
            // (nada a fazer)
        }

        // Retorna string com ponto decimal (melhor para DECIMAL do MySQL que usar float)
        if ($value === '' || $value === '-' || $value === '.') {
            return null;
        }

        // garante 2 casas sem ruído binário de float
        return number_format((float)$value, 2, '.', '');
    }

    private function loadProcessColumns(): void
    {
        if (!empty($this->processColumns)) {
            return;
        }

        try {
            $stmt = $this->pdo->query('SHOW COLUMNS FROM processos');
            $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $this->processColumns = array_map(static fn($column) => $column['Field'] ?? '', $columns);
        } catch (PDOException $exception) {
            $this->processColumns = [];
            error_log('Erro ao carregar colunas da tabela processos: ' . $exception->getMessage());
        }
    }

    private function hasProcessColumn(string $column): bool
    {
        return in_array($column, $this->processColumns, true);
    }

    private function getValorRecebidoExpression(): string
    {
        if ($this->hasProcessColumn('valor_recebido')) {
            return 'COALESCE(p.valor_recebido, 0)';
        }

        if ($this->hasProcessColumn('orcamento_valor_entrada')) {
            return 'COALESCE(p.orcamento_valor_entrada, 0)';
        }

        return '0';
    }

    private function getValorRestanteExpression(): string
    {
        if ($this->hasProcessColumn('valor_restante')) {
            return 'COALESCE(p.valor_restante, 0)';
        }

        if ($this->hasProcessColumn('orcamento_valor_restante')) {
            return 'COALESCE(p.orcamento_valor_restante, 0)';
        }

        $valorRecebido = $this->getValorRecebidoExpression();

        return "GREATEST(COALESCE(p.valor_total, 0) - {$valorRecebido}, 0)";
    }

    private function getSdrIdSelectExpression(): string
    {
        $this->loadProcessColumns();

        $hasSdrId = $this->hasProcessColumn('sdr_id');
        $hasSdrIdCamel = $this->hasProcessColumn('sdrId');

        if ($hasSdrId) {
            // Mantemos apenas o valor explícito de SDR; removemos o fallback para colaborador
            // para evitar atribuir SDR incorretamente e permitir o uso de "Sem SDR" nos relatórios.
            return 'p.sdr_id';
        }

        if ($hasSdrIdCamel) {
            // Mantemos apenas o valor explícito de SDR; removemos o fallback para colaborador
            // para evitar atribuir SDR incorretamente e permitir o uso de "Sem SDR" nos relatórios.
            return 'p.sdrId';
        }

        return 'NULL';
    }

    private function getStatusFinanceiroSelectExpression(): string
    {
        if ($this->hasProcessColumn('status_financeiro')) {
            return 'LOWER(p.status_financeiro) AS status_financeiro';
        }

        $valorRecebido = $this->getValorRecebidoExpression();

        return "LOWER(CASE\n                WHEN COALESCE(p.valor_total, 0) <= 0 THEN 'pendente'\n                WHEN {$valorRecebido} >= COALESCE(p.valor_total, 0) - 0.01 THEN 'pago'\n                WHEN {$valorRecebido} > 0 THEN 'parcial'\n                ELSE 'pendente'\n            END) AS status_financeiro";
    }

    private function normalizePrazoDias($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            $normalized = (int)$value;
            // Zero ou negativos removem o prazo e são tratados como null.
            return $normalized > 0 ? $normalized : null;
        }

        if (is_string($value)) {
            $filtered = preg_replace('/[^0-9-]/', '', $value);
            if ($filtered === null || $filtered === '' || $filtered === '-') {
                return null;
            }

            if (is_numeric($filtered)) {
                $normalized = (int)$filtered;
                // Zero ou negativos removem o prazo e são tratados como null.
                return $normalized > 0 ? $normalized : null;
            }
        }

        return null;
    }

    private function calculateDeadlineFromCreation(?string $creationDate, ?int $prazoDias): ?string
    {
        if ($prazoDias === null) {
            return null;
        }

        $baseDate = $creationDate ?: date('Y-m-d');

        try {
            $date = new DateTimeImmutable($baseDate);
        } catch (Exception $exception) {
            return null;
        }

        return $date->modify('+' . $prazoDias . ' days')->format('Y-m-d');
    }

    private function normalizeStatus(string $status): string
    {
        return mb_strtolower(trim($status), 'UTF-8');
    }

    private function getProcessStatusDataConversao(int $id): ?array
    {
        try {
            $stmt = $this->pdo->prepare('SELECT status_processo, data_conversao, os_numero_omie FROM processos WHERE id = :id');
            $stmt->execute([':id' => $id]);

            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (PDOException $exception) {
            error_log('Erro ao buscar status e data de conversão do processo ' . $id . ': ' . $exception->getMessage());

            return null;
        }
    }



public function create($data, $files)
{
    $this->pdo->beginTransaction();
    try {
        // --- Lógica para gerar número de orçamento (mantida) ---
        $ano = date('y');
        $stmt = $this->pdo->prepare("SELECT orcamento_numero FROM processos WHERE orcamento_numero LIKE ? ORDER BY id DESC LIMIT 1");
        $stmt->execute([$ano . '-%']);
        $ultimoOrcamento = $stmt->fetchColumn();

        $novoSequencial = 1;
        if ($ultimoOrcamento) {
            $ultimoSequencial = (int) substr($ultimoOrcamento, strpos($ultimoOrcamento, '-') + 1);
            $novoSequencial = $ultimoSequencial + 1;
        }
        $orcamento_numero = $ano . '-' . str_pad($novoSequencial, 4, '0', STR_PAD_LEFT);
        
        $prazo_calculado = new DateTime();
        $prazo_calculado->modify('+3 days');
        $prazo_formatado = $prazo_calculado->format('Y-m-d');
        $omieKeyPreview = $this->generateNextOmieKey();

        // ===== INÍCIO DA CORREÇÃO =====
        // Query SQL CORRIGIDA: A coluna 'orcamento_comprovantes' foi removida.
        $hasApostilamentoCategoria = $this->hasProcessColumn('apostilamento_categoria_id');
        $hasPostagemCategoria = $this->hasProcessColumn('postagem_categoria_id');

        $columns = [
            'cliente_id', 'colaborador_id', 'vendedor_id', 'prospeccao_id', 'titulo', 'status_processo',
            'orcamento_numero', 'orcamento_origem', 'orcamento_prazo_calculado',
            'data_previsao_entrega', 'categorias_servico', 'idioma',
            'valor_total', 'orcamento_forma_pagamento', 'orcamento_parcelas', 'orcamento_valor_entrada',
            'data_pagamento_1', 'data_pagamento_2',
            'apostilamento_quantidade', 'apostilamento_valor_unitario',
            'postagem_quantidade', 'postagem_valor_unitario', 'observacoes',
            'data_entrada', 'data_inicio_traducao', 'traducao_modalidade',
            'prazo_dias', 'traducao_prazo_dias',
            'assinatura_tipo', 'tradutor_id',
            'etapa_faturamento_codigo', 'codigo_categoria', 'codigo_conta_corrente', 'codigo_cenario_fiscal', 'os_numero_conta_azul'
        ];

        $placeholders = array_map(static fn($column) => ':' . $column, $columns);

        if ($hasApostilamentoCategoria) {
            $columns[] = 'apostilamento_categoria_id';
            $placeholders[] = ':apostilamento_categoria_id';
        }

        if ($hasPostagemCategoria) {
            $columns[] = 'postagem_categoria_id';
            $placeholders[] = ':postagem_categoria_id';
        }

        $sqlProcesso = sprintf(
            'INSERT INTO processos (%s) VALUES (%s)',
            implode(', ', $columns),
            implode(', ', $placeholders)
        );
        $stmtProcesso = $this->pdo->prepare($sqlProcesso);

        // A chamada para a função antiga 'uploadComprovante' foi removida, pois 'salvarArquivos' já faz o trabalho.
        // $comprovantePath = $this->uploadComprovante($files['comprovante'] ?? null);

        // Parâmetros CORRIGIDOS: A chave 'orcamento_comprovantes' foi removida.
        $dataEntrada = $data['data_solicitacao'] ?? $data['data_entrada'] ?? date('Y-m-d');
        $traducaoPrazoDias = $this->normalizePrazoDias($data['traducao_prazo_dias'] ?? null);
        $prazoDias = $this->normalizePrazoDias($data['prazo_dias'] ?? null);
        if ($prazoDias === null && $traducaoPrazoDias !== null) {
            // Ao cadastrar serviços rápidos, zero ou vazio removem o prazo geral; reaproveitamos o prazo de tradução quando informado.
            $prazoDias = $traducaoPrazoDias;
        }
        $dataPrevisaoEntrega = $this->calculateDeadlineFromCreation($dataEntrada, $prazoDias);

        $assinaturaSelecionada = $data['assinatura_tipo'] ?? $data['modalidade_assinatura'] ?? null;
        $assinaturaTipo = ($assinaturaSelecionada === 'Física' || $assinaturaSelecionada === 'Assinatura Física') ? 'Física' : 'Digital';

        $params = [
            'cliente_id' => $data['id_cliente'] ?? $data['cliente_id'] ?? null,
            'colaborador_id' => $_SESSION['user_id'],
            'vendedor_id' => $this->resolveVendorId($data),
            'titulo' => $data['titulo'] ?? 'Orçamento #' . $orcamento_numero,
            'prospeccao_id' => $this->sanitizeNullableInt($data['prospeccao_id'] ?? null),
            'status_processo' => $data['status_processo'] ?? $data['status'] ?? 'Orçamento',
            'orcamento_numero' => $orcamento_numero,
            'orcamento_origem' => $data['orcamento_origem'] ?? null,
            'orcamento_prazo_calculado' => $prazo_formatado,
            'data_previsao_entrega' => $dataPrevisaoEntrega,
            'categorias_servico' => isset($data['categorias_servico']) ? implode(',', $data['categorias_servico']) : ($data['tipo_servico'] ?? null),
            'idioma' => $data['idioma'] ?? null,
            'valor_total' => $this->parseCurrency($data['valor_total'] ?? $data['valor_total_hidden'] ?? 0),
            'orcamento_forma_pagamento' => $data['orcamento_forma_pagamento'] ?? null,
            'orcamento_parcelas' => in_array(mb_strtolower($data['orcamento_forma_pagamento'] ?? ''), ['à vista', 'pagamento único', 'pagamento unico'], true)
                ? ($data['orcamento_parcelas'] ?? null)
                : null,
            'orcamento_valor_entrada' => $this->parseCurrency($data['orcamento_valor_entrada'] ?? null),
            'data_pagamento_1' => empty($data['data_pagamento_1']) ? null : $data['data_pagamento_1'],
            'data_pagamento_2' => empty($data['data_pagamento_2']) ? null : $data['data_pagamento_2'],
            'apostilamento_quantidade' => empty($data['apostilamento_quantidade']) ? null : (int)$data['apostilamento_quantidade'],
            'apostilamento_valor_unitario' => $this->parseCurrency($data['apostilamento_valor_unitario'] ?? null),
            'postagem_quantidade' => empty($data['postagem_quantidade']) ? null : (int)$data['postagem_quantidade'],
            'postagem_valor_unitario' => $this->parseCurrency($data['postagem_valor_unitario'] ?? null),
            'observacoes' => $data['observacoes'] ?? '',
            'data_entrada' => $dataEntrada,
            'data_inicio_traducao' => $data['data_inicio_traducao'] ?? null,
            'traducao_modalidade' => $data['traducao_modalidade'] ?? 'Normal',
            'prazo_dias' => $prazoDias,
            'traducao_prazo_dias' => $traducaoPrazoDias,
            'assinatura_tipo' => $assinaturaTipo,
            'tradutor_id' => $data['id_tradutor'] ?? $data['tradutor_id'] ?? null,
            'etapa_faturamento_codigo' => $this->sanitizeNullableString($data['etapa_faturamento_codigo'] ?? null),
            'codigo_categoria' => $this->sanitizeNullableString($data['codigo_categoria'] ?? null),
            'codigo_conta_corrente' => $this->sanitizeNullableInt($data['codigo_conta_corrente'] ?? null),
            'codigo_cenario_fiscal' => $this->sanitizeNullableInt($data['codigo_cenario_fiscal'] ?? null),
            'os_numero_conta_azul' => $omieKeyPreview
        ];

        if ($hasApostilamentoCategoria) {
            $params['apostilamento_categoria_id'] = $this->sanitizeNullableInt($data['apostilamento_categoria_id'] ?? null);
        }

        if ($hasPostagemCategoria) {
            $params['postagem_categoria_id'] = $this->sanitizeNullableInt($data['postagem_categoria_id'] ?? null);
        }
        // ===== FIM DA CORREÇÃO =====

        $stmtProcesso->execute($params);
        $processoId = (int)$this->pdo->lastInsertId();
        $this->updateOmieKeyForProcessId($processoId);

        // Esta lógica de salvar arquivos e documentos já estava correta e foi mantida.
        // O formulário de serviço rápido envia arquivos no campo 'anexos', que serão salvos aqui.
        $storageContext = $this->determineStorageContextKey(
            $processoId,
            $params['status_processo'] ?? null,
            $params['orcamento_numero'] ?? null
        );

        $this->salvarArquivos($processoId, $files['translationFiles'] ?? null, 'traducao', $storageContext);

        $crcFiles = $files['crcFiles'] ?? null;
        $shouldReuseCrc = !empty($data['reuseTraducaoForCrc']);
        if ($shouldReuseCrc) {
            $this->replicarAnexosDeCategoria($processoId, 'traducao', 'crc', $storageContext);
        } else {
            $this->salvarArquivos($processoId, $crcFiles, 'crc', $storageContext);
        }

        $this->salvarArquivos($processoId, $files['paymentProofFiles'] ?? null, 'comprovante', $storageContext);
        
        $documents = $this->normalizeDocumentsForInsert($data);
        if (!empty($documents)) {
            $this->insertProcessDocuments($processoId, $documents);
        }
        
        $this->pdo->commit();
        return $processoId;
    } catch (PDOException $e) {
        $this->pdo->rollBack();
        
        // Mantemos o debug por enquanto para ter certeza.
        if (!isset($_SESSION['error_message'])) {
            $_SESSION['error_message'] = "Erro de Banco de Dados: " . $e->getMessage();
        }

        error_log('Erro ao criar processo: ' . $e->getMessage());
        return false;
    }
}


    /**
     * Busca um processo completo pelo seu ID, incluindo dados relacionados.
     * ATUALIZADO: Corrige o JOIN para buscar o nome do vendedor da tabela 'users'.
     *
     * @param int $id O ID do processo a ser buscado.
     * @return array|false Retorna um array com os dados do processo e seus documentos,
     * ou 'false' se não for encontrado.
     */
    public function getById($id)
    {
        // A correção está na consulta SQL abaixo, que agora inclui um JOIN com a tabela 'users'.
        $sql = "SELECT
                    p.*,
                    c.nome_cliente,
                    pr.id_texto AS prospeccao_codigo,
                    pr.nome_prospecto AS prospeccao_nome,
                    COALESCE(u_vendedor.nome_completo, 'Sistema') as nome_vendedor, -- Pega o nome da tabela 'users'
                    t.nome_tradutor
                FROM processos p
                LEFT JOIN clientes c ON p.cliente_id = c.id
                LEFT JOIN vendedores v ON p.vendedor_id = v.id
                LEFT JOIN users u_vendedor ON v.user_id = u_vendedor.id -- FAZ O JOIN ADICIONAL AQUI
                LEFT JOIN prospeccoes pr ON p.prospeccao_id = pr.id
                LEFT JOIN tradutores t ON p.tradutor_id = t.id
                WHERE p.id = ?";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$id]);
        $processo = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$processo) {
            return false;
        }

        // Busca os documentos associados (esta parte permanece a mesma)
        $docStmt = $this->pdo->prepare("SELECT * FROM documentos WHERE processo_id = ?");
        $docStmt->execute([$id]);
        $documentos = $docStmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'processo' => $processo,
            'documentos' => $documentos
        ];
    }


    
    /**
     * Atualiza um processo existente e seus documentos.
     * @param int $id ID do processo.
     * @param array $data Dados do formulário.
     * @param array $files Arquivos (não implementado na query de update principal).
     * @return bool
     */
    public function update($id, $data, $files)
    {
        // Busca o status do processo ANTES de qualquer alteração
        $stmtOldStatus = $this->pdo->prepare("SELECT status_processo FROM processos WHERE id = ?");
        $stmtOldStatus->execute([$id]);
        $oldStatus = $stmtOldStatus->fetchColumn();

        $this->pdo->beginTransaction();
        try {
            $hasApostilamentoCategoria = $this->hasProcessColumn('apostilamento_categoria_id');
            $hasPostagemCategoria = $this->hasProcessColumn('postagem_categoria_id');

            $setParts = [
                'cliente_id = :cliente_id',
                'vendedor_id = :vendedor_id',
                'titulo = :titulo',
                'status_processo = :status_processo',
                'orcamento_origem = :orcamento_origem',
                'categorias_servico = :categorias_servico',
                'idioma = :idioma',
                'assinatura_tipo = :assinatura_tipo',
                'valor_total = :valor_total',
                'orcamento_forma_pagamento = :orcamento_forma_pagamento',
                'orcamento_parcelas = :orcamento_parcelas',
                'orcamento_valor_entrada = :orcamento_valor_entrada',
                'data_pagamento_1 = :data_pagamento_1',
                'data_pagamento_2 = :data_pagamento_2',
                'apostilamento_quantidade = :apostilamento_quantidade',
                'apostilamento_valor_unitario = :apostilamento_valor_unitario',
                'postagem_quantidade = :postagem_quantidade',
                'postagem_valor_unitario = :postagem_valor_unitario',
                'observacoes = :observacoes',
                'etapa_faturamento_codigo = :etapa_faturamento_codigo',
                'codigo_categoria = :codigo_categoria',
                'codigo_conta_corrente = :codigo_conta_corrente',
                'codigo_cenario_fiscal = :codigo_cenario_fiscal'
            ];

            if ($hasApostilamentoCategoria) {
                $setParts[] = 'apostilamento_categoria_id = :apostilamento_categoria_id';
            }

            if ($hasPostagemCategoria) {
                $setParts[] = 'postagem_categoria_id = :postagem_categoria_id';
            }

            $sqlProcesso = 'UPDATE processos SET ' . implode(', ', $setParts) . ' WHERE id = :id';
            $stmtProcesso = $this->pdo->prepare($sqlProcesso);

            $assinaturaSelecionada = $data['assinatura_tipo'] ?? $data['modalidade_assinatura'] ?? 'Digital';
            $assinaturaTipo = in_array($assinaturaSelecionada, ['Física', 'Assinatura Física'], true) ? 'Física' : 'Digital';

            $params = [
                'id' => $id,
                'cliente_id' => $data['cliente_id'],
                'vendedor_id' => $this->resolveVendorId($data),
                'titulo' => $data['titulo'],
                'status_processo' => $data['status_processo'] ?? 'Orçamento',
                'orcamento_origem' => $data['orcamento_origem'] ?? null,
                'categorias_servico' => isset($data['categorias_servico']) ? implode(',', $data['categorias_servico']) : null,
                'idioma' => $data['idioma'] ?? null,
                'assinatura_tipo' => $assinaturaTipo,
                'valor_total' => $this->parseCurrency($data['valor_total_hidden'] ?? null),
                'orcamento_forma_pagamento' => $data['orcamento_forma_pagamento'] ?? null,
                'orcamento_parcelas' => in_array(mb_strtolower($data['orcamento_forma_pagamento'] ?? ''), ['à vista', 'pagamento único', 'pagamento unico'], true)
                    ? ($data['orcamento_parcelas'] ?? null)
                    : null,
                'orcamento_valor_entrada' => $this->parseCurrency($data['orcamento_valor_entrada'] ?? null),
                'data_pagamento_1' => empty($data['data_pagamento_1']) ? null : $data['data_pagamento_1'],
                'data_pagamento_2' => empty($data['data_pagamento_2']) ? null : $data['data_pagamento_2'],
                'apostilamento_quantidade' => empty($data['apostilamento_quantidade']) ? null : (int)$data['apostilamento_quantidade'],
                'apostilamento_valor_unitario' => $this->parseCurrency($data['apostilamento_valor_unitario'] ?? null),
                'postagem_quantidade' => empty($data['postagem_quantidade']) ? null : (int)$data['postagem_quantidade'],
                'postagem_valor_unitario' => $this->parseCurrency($data['postagem_valor_unitario'] ?? null),
                'observacoes' => $data['observacoes'] ?? '',
                'etapa_faturamento_codigo' => $this->sanitizeNullableString($data['etapa_faturamento_codigo'] ?? null),
                'codigo_categoria' => $this->sanitizeNullableString($data['codigo_categoria'] ?? null),
                'codigo_conta_corrente' => $this->sanitizeNullableInt($data['codigo_conta_corrente'] ?? null),
                'codigo_cenario_fiscal' => $this->sanitizeNullableInt($data['codigo_cenario_fiscal'] ?? null)
            ];

            if ($hasApostilamentoCategoria) {
                $params['apostilamento_categoria_id'] = $this->sanitizeNullableInt($data['apostilamento_categoria_id'] ?? null);
            }

            if ($hasPostagemCategoria) {
                $params['postagem_categoria_id'] = $this->sanitizeNullableInt($data['postagem_categoria_id'] ?? null);
            }
            $stmtProcesso->execute($params);

            // A lógica para atualizar os documentos também permanece a mesma
            $this->pdo->prepare("DELETE FROM documentos WHERE processo_id = ?")->execute([$id]);
            $documents = $this->normalizeDocumentsForInsert($data);
            if (!empty($documents)) {
                $this->insertProcessDocuments($id, $documents);
            }
            
            // --- INÍCIO DA NOVA LÓGICA DE LANÇAMENTO FINANCEIRO ---
            $newStatus = $data['status_processo'];
            $statusTrigger = ['Serviço Pendente', 'Serviço em Andamento', 'Serviço pendente', 'Serviço em andamento', 'Pendente de Pagamento', 'Pendente de Documentos', 'Pago - A Enviar']; // Status que disparam a criação da receita

            // Dispara a lógica apenas se o status MUDOU para um dos status do gatilho
            if ($oldStatus != $newStatus && in_array($newStatus, $statusTrigger)) {
                // Instancia os models necessários
                require_once __DIR__ . '/LancamentoFinanceiro.php';
                require_once __DIR__ . '/CategoriaFinanceira.php';
                $lancamentoModel = new LancamentoFinanceiro($this->pdo);
                $categoriaModel = new CategoriaFinanceira($this->pdo);

                // Pega todos os documentos do processo que acabamos de atualizar
                $documentosDoProcesso = $this->getDocumentosByProcessoId($id);
                $processoCompleto = $this->getById($id)['processo'];
                
                // Prepara os arrays para agregar os valores
                $produtosAgregados = ['Tradução' => 0, 'CRC' => 0, 'Outros' => 0];
                $documentosAgregados = ['Tradução' => [], 'CRC' => [], 'Outros' => []];

                foreach ($documentosDoProcesso as $doc) {
                    $categoriaFinanceira = $categoriaModel->findReceitaByNome($doc['tipo_documento']);

                    // Verifica se é um 'Produto de Orçamento' para ser agregado
                    if ($categoriaFinanceira && $categoriaFinanceira['eh_produto_orcamento'] == 1) {
                        if ($categoriaFinanceira['servico_tipo'] === 'Tradução') {
                            $produtosAgregados['Tradução'] += $doc['valor_unitario'];
                            // Guarda o documento para referência futura, se necessário
                            $documentosAgregados['Tradução'][] = $doc;
                        } elseif ($categoriaFinanceira['servico_tipo'] === 'CRC') {
                            $produtosAgregados['CRC'] += $doc['valor_unitario'];
                            $documentosAgregados['CRC'][] = $doc;
                        } elseif ($categoriaFinanceira['servico_tipo'] === 'Outros') {
                            $produtosAgregados['Outros'] += $doc['valor_unitario'];
                            $documentosAgregados['Outros'][] = $doc;
                        }

                        $produtosAgregados[$serviceType] += (float)$doc['valor_unitario'];
                        $documentosAgregados[$serviceType][] = $doc;
                    } else {
                        // Comportamento antigo: cria lançamento individual para itens que não são produtos de orçamento
                        if ($categoriaFinanceira) {
                            $dadosLancamento = [
                                'descricao' => 'Receita do Orçamento #' . $processoCompleto['orcamento_numero'] . ' - ' . $doc['nome_documento'],
                                'valor' => $doc['valor_unitario'],
                                'data_vencimento' => date('Y-m-d'),
                                'tipo' => 'RECEITA',
                                'categoria_id' => $categoriaFinanceira['id'],
                                'cliente_id' => $processoCompleto['cliente_id'],
                                'processo_id' => $id,
                                'status' => 'Pendente',
                                'finalizado' => 0,
                                'user_id' => $_SESSION['user_id'] ?? null,
                            ];
                            $lancamentoModel->create($dadosLancamento);
                        }
                    }
                }

                // Cria os lançamentos agregados por tipo de serviço quando houver valor acumulado
                foreach ($produtosAgregados as $tipoServico => $valorTotal) {
                    if ($valorTotal <= 0) {
                        continue;
                    }

                    $categoriaAgregada = $categoriaModel->findByServiceType($tipoServico);
                    $idsDocumentos = array_column($documentosAgregados[$tipoServico], 'id');

                    $dadosLancamentoAgregado = [
                        'descricao' => $tipoServico . ' — Orçamento #' . $processoCompleto['orcamento_numero'],
                        'valor' => number_format((float)$valorTotal, 2, '.', ''),
                        'data_vencimento' => date('Y-m-d'),
                        'tipo' => 'RECEITA',
                        'categoria_id' => $categoriaAgregada ? $categoriaAgregada['id'] : null,
                        'cliente_id' => $processoCompleto['cliente_id'],
                        'processo_id' => $id,
                        'status' => 'Pendente',
                        'eh_agregado' => 1,
                        'itens_agregados_ids' => json_encode($idsDocumentos),
                        'finalizado' => 0,
                        'user_id' => $_SESSION['user_id'] ?? null,
                    ];

                    $lancamentoModel->create($dadosLancamentoAgregado);
                }
            }
            // --- FIM DA NOVA LÓGICA ---
            $storageContext = $this->determineStorageContextKey($id);

            $this->salvarArquivos($id, $files['translationFiles'] ?? null, 'traducao', $storageContext);

            $crcFiles = $files['crcFiles'] ?? null;
            $shouldReuseCrc = !empty($data['reuseTraducaoForCrc']);
            if ($shouldReuseCrc) {
                $this->replicarAnexosDeCategoria($id, 'traducao', 'crc', $storageContext);
            } else {
                $this->salvarArquivos($id, $crcFiles, 'crc', $storageContext);
            }

            $this->salvarArquivos($id, $files['paymentProofFiles'] ?? null, 'comprovante', $storageContext);
            $this->pdo->commit();
            return true;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log("Erro ao atualizar processo: " . $e->getMessage());
            return false;
        }
    }

    public function updateFromLeadConversion(int $processoId, array $data, array $paymentProofFiles = []): bool
    {
        $allowedFields = [
            'status_processo',
            'data_inicio_traducao',
            'prazo_dias',
            'traducao_prazo_dias',
            'data_previsao_entrega',
            'prazo_pausado_em',
            'prazo_dias_restantes',
            'valor_total',
            'orcamento_forma_pagamento',
            'orcamento_parcelas',
            'orcamento_valor_entrada',
            'orcamento_valor_restante',
            'data_pagamento_1',
            'data_pagamento_2',
            'comprovante_pagamento_1',
            'comprovante_pagamento_2',
            'data_conversao',
            'cliente_id',
        ];

        $setParts = [];
        $params = [':id' => $processoId];

        $shouldUpdateDeadline = false;
        $translationDeadlineProvided = false;

        foreach ($allowedFields as $field) {
            if (!array_key_exists($field, $data)) {
                continue;
            }

            $value = $data[$field];

            if ($value === '') {
                $value = null;
            }

            if (in_array($field, ['valor_total', 'orcamento_valor_entrada', 'orcamento_valor_restante'], true)) {
                $value = $this->parseCurrency($value);
            } elseif (in_array($field, ['prazo_dias', 'traducao_prazo_dias'], true)) {
                // Zero ou valores vazios removem o prazo e devem ser tratados como NULL.
                $value = $this->normalizePrazoDias($value);
            } elseif (in_array($field, ['orcamento_parcelas', 'cliente_id', 'prazo_dias_restantes'], true) && $value !== null) {
                $value = (int)$value;
            }

            $params[":" . $field] = $value;
            $setParts[] = "`{$field}` = :{$field}";

            if (in_array($field, ['prazo_dias', 'traducao_prazo_dias'], true)) {
                $shouldUpdateDeadline = true;

                if ($field === 'traducao_prazo_dias') {
                    $translationDeadlineProvided = true;
                }
            }
        }

        if ($translationDeadlineProvided) {
            $translationValue = $params[':traducao_prazo_dias'] ?? null;

            if (!in_array('`prazo_dias` = :prazo_dias', $setParts, true)) {
                $setParts[] = '`prazo_dias` = :prazo_dias';
            }

            $params[':prazo_dias'] = $translationValue;
        }

        if ($shouldUpdateDeadline && !array_key_exists('data_previsao_entrega', $data)) {
            if (!array_key_exists(':data_inicio_traducao', $params)) {
                $params[':data_inicio_traducao'] = null;
            }

            $setParts[] = "data_previsao_entrega = CASE\n                WHEN :prazo_dias IS NULL THEN NULL\n                WHEN COALESCE(:data_inicio_traducao, data_inicio_traducao) IS NOT NULL THEN DATE_ADD(COALESCE(:data_inicio_traducao, data_inicio_traducao), INTERVAL :prazo_dias DAY)\n                ELSE DATE_ADD(data_criacao, INTERVAL :prazo_dias DAY)\n            END";
        }

        if (!empty($paymentProofFiles)) {
            $proofPaths = array_column($paymentProofFiles, 'caminho_arquivo');
            $legacyMap = [
                'comprovante_pagamento_1' => $proofPaths[0] ?? null,
                'comprovante_pagamento_2' => $proofPaths[1] ?? null,
            ];

            foreach ($legacyMap as $column => $path) {
                if ($path === null || $path === '') {
                    continue;
                }

                $placeholder = ':' . $column;
                $assignment = "`{$column}` = {$placeholder}";
                $params[$placeholder] = $path;

                if (!in_array($assignment, $setParts, true)) {
                    $setParts[] = $assignment;
                }
            }
        }

        if (empty($setParts)) {
            return false;
        }

        $sql = 'UPDATE processos SET ' . implode(', ', $setParts) . ' WHERE id = :id';
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }
    /**
     * Deleta um processo do banco de dados.
     * @param int $id ID do processo a ser deletado.
     * @return bool
     */
    public function deleteProcesso($id)
    {
        // A transação garante que todas as operações funcionem, ou nenhuma será executada.
        $this->pdo->beginTransaction();
        try {
            // --- INÍCIO DA CORREÇÃO ---
            // 1. Exclui as notificações relacionadas ao processo
            $link = "/processos.php?action=view&id=" . $id;
            $stmtNotificacoes = $this->pdo->prepare("DELETE FROM notificacoes WHERE link = ?");
            $stmtNotificacoes->execute([$link]);

            // 2. Exclui os anexos físicos e seus registros no banco de dados
            $todosAnexos = array_merge(
                $this->getAnexosPorCategoria($id, ['traducao']),
                $this->getAnexosPorCategoria($id, ['crc']),
                $this->getAnexosPorCategoria($id, ['comprovante']),
                $this->getAnexosPorCategoria($id, ['anexo'])
            );
            foreach ($todosAnexos as $anexo) {
                $this->deleteAnexo($anexo['id']); // Reutiliza a função que já apaga o arquivo e o registro
            }
            // --- FIM DA CORREÇÃO ---

            // 3. Exclui outros dados associados (lógica que você já tinha)
            $stmtComentarios = $this->pdo->prepare("DELETE FROM comentarios WHERE processo_id = ?");
            $stmtComentarios->execute([$id]);

            $stmtDocumentos = $this->pdo->prepare("DELETE FROM documentos WHERE processo_id = ?");
            $stmtDocumentos->execute([$id]);
            
            // 4. Finalmente, exclui o processo principal
            $stmtProcesso = $this->pdo->prepare("DELETE FROM processos WHERE id = ?");
            $success = $stmtProcesso->execute([$id]);

            $this->pdo->commit();
            return $success;

        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log("Erro ao deletar processo: " . $e->getMessage());
            return false;
        }
    }



    // =======================================================================
    // MÉTODOS DE BUSCA E LISTAGEM
    // =======================================================================

    /**
     * Busca todos os processos com informações básicas para listagem geral.
     * @return array
     */
    public function getAll()
    {
        $sql = "SELECT
                    p.orcamento_numero, p.id, p.titulo, p.status_processo, p.data_entrada,
                    p.data_previsao_entrega, p.valor_total, p.prospeccao_id,
                    c.nome_cliente,
                    pr.id_texto AS prospeccao_codigo,
                    pr.nome_prospecto AS prospeccao_nome,
                    u_colab.nome_completo as nome_colaborador,
                    COALESCE(u_vend.nome_completo, 'Sistema') as nome_vendedor
                FROM processos p
                JOIN clientes c ON p.cliente_id = c.id
                JOIN users u_colab ON p.colaborador_id = u_colab.id
                LEFT JOIN vendedores v ON p.vendedor_id = v.id
                LEFT JOIN users u_vend ON v.user_id = u_vend.id
                LEFT JOIN prospeccoes pr ON p.prospeccao_id = pr.id
                WHERE p.status_processo NOT IN ('Cancelado', 'Recusado')
                ORDER BY p.id DESC";
        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Retorna os processos com base em filtros, com paginação.
     * @param array $filters Filtros aplicados.
     * @param int $limit Limite de resultados por página.
     * @param int $offset Offset para paginação.
     * @return array
     */
    public function getFilteredProcesses(array $filters = [], int $limit = 50, int $offset = 0): array
    {
        $select_part = "SELECT
                    p.id, p.titulo, p.status_processo, p.data_criacao, p.data_previsao_entrega,
                    p.valor_total, p.prospeccao_id,
                    pr.id_texto AS prospeccao_codigo,
                    pr.nome_prospecto AS prospeccao_nome,
                    p.categorias_servico, c.nome_cliente, c.tipo_servico AS tipo_servico, t.nome_tradutor, p.os_numero_omie, p.os_numero_conta_azul,
                    p.data_inicio_traducao, p.data_previsao_entrega AS prazo_data, p.prazo_dias, p.traducao_modalidade, p.assinatura_tipo,
                    p.data_envio_assinatura, p.data_devolucao_assinatura, p.data_envio_cartorio,
                    COALESCE(v.nome_completo, 'Sistema') as nome_vendedor,
                    (SELECT COUNT(*) FROM documentos d WHERE d.processo_id = p.id) as total_documentos_contagem,
                    (SELECT COALESCE(SUM(d.quantidade), 0) FROM documentos d WHERE d.processo_id = p.id) as total_documentos_soma";

    $from_part = " FROM processos AS p
                    JOIN clientes AS c ON p.cliente_id = c.id
                    LEFT JOIN tradutores AS t ON p.tradutor_id = t.id
                    LEFT JOIN vendedores AS vend ON p.vendedor_id = vend.id
                    LEFT JOIN users AS v ON vend.user_id = v.id
                    LEFT JOIN prospeccoes AS pr ON p.prospeccao_id = pr.id";

    $where_clauses = [];
    $params = [];
    // Se nenhum filtro de status for aplicado e nenhum card estiver ativo, exclui os orçamentos por padrão.
    if (empty($filters['status']) && empty($filters['filtro_card'])) {
        $where_clauses[] = "p.status_processo NOT IN ('Orçamento', 'Orçamento Pendente', 'Cancelado', 'Recusado', 'Serviço Pendente', 'Serviço pendente')";

    }

    // ==========================================================
    //  NOVO BLOCO PARA TRATAR OS FILTROS VINDOS DOS CARDS
    // ==========================================================
    if (!empty($filters['filtro_card'])) {
        $deadlineDateExpression = "COALESCE(\n            p.data_previsao_entrega,\n            CASE\n                WHEN p.traducao_prazo_dias IS NOT NULL AND p.data_inicio_traducao IS NOT NULL\n                    THEN DATE_ADD(p.data_inicio_traducao, INTERVAL p.traducao_prazo_dias DAY)\n                WHEN p.prazo_dias IS NOT NULL AND p.data_inicio_traducao IS NOT NULL\n                    THEN DATE_ADD(p.data_inicio_traducao, INTERVAL p.prazo_dias DAY)\n                ELSE NULL\n            END\n        )";

        $deadlineDiffExpression = "CASE\n            WHEN LOWER(p.status_processo) IN ('pendente de pagamento', 'pendente de documentos', 'aguardando pagamento', 'aguardando pagamentos', 'aguardando documento', 'aguardando documentos', 'aguardando documentacao', 'aguardando documentação') AND p.prazo_dias_restantes IS NOT NULL THEN p.prazo_dias_restantes\n            WHEN {$deadlineDateExpression} IS NOT NULL THEN DATEDIFF({$deadlineDateExpression}, CURDATE())\n            ELSE NULL\n        END";

        switch ($filters['filtro_card']) {
            case 'ativos':
                $where_clauses[] = "LOWER(p.status_processo) IN ('serviço em andamento', 'servico em andamento', 'pago - a enviar', 'pendente de pagamento', 'pendente de documentos', 'aguardando pagamento', 'aguardando pagamentos', 'aguardando documento', 'aguardando documentos', 'aguardando documentacao', 'aguardando documentação')";
                break;
            case 'pendentes':
                $where_clauses[] = "p.status_processo IN ('Serviço Pendente', 'Serviço pendente')";
                break;
            case 'orcamentos':
                $where_clauses[] = "LOWER(p.status_processo) IN ('orçamento pendente', 'orcamento pendente')";
                break;
            case 'finalizados_mes':
                $where_clauses[] = "p.status_processo IN ('Concluído', 'Finalizado') AND MONTH(p.data_finalizacao_real) = MONTH(CURDATE()) AND YEAR(p.data_finalizacao_real) = YEAR(CURDATE())";
                break;
            case 'atrasados':
                $where_clauses[] = "p.data_previsao_entrega < CURDATE() AND LOWER(p.status_processo) NOT IN ('concluído', 'concluido', 'finalizado', 'finalizada', 'arquivado', 'arquivada', 'cancelado', 'recusado', 'recusada', 'pendente de pagamento', 'pendente de documentos', 'aguardando pagamento', 'aguardando pagamentos', 'aguardando documento', 'aguardando documentos', 'aguardando documentacao', 'aguardando documentação')";
                break;
        }
    }


    // Lógica de Filtros
    $ignoreDateAndVendorFilters = ($filters['filtro_card'] ?? '') === 'orcamentos';

    if (!$ignoreDateAndVendorFilters && !empty($filters['vendedor_id'])) {
        $where_clauses[] = "p.vendedor_id = :vendedor_id";
        $params[':vendedor_id'] = $filters['vendedor_id'];
    }
        if (!empty($filters['status']) && $filters['status'] !== '__all__') {
            $statusValue = mb_strtolower($filters['status']);
            if (in_array($statusValue, ['serviço em andamento', 'servico em andamento'], true)) {
                $where_clauses[] = "LOWER(p.status_processo) IN ('serviço em andamento', 'servico em andamento', 'pago - a enviar', 'pendente de pagamento', 'pendente de documentos', 'aguardando pagamento', 'aguardando pagamentos', 'aguardando documento', 'aguardando documentos', 'aguardando documentacao', 'aguardando documentação')";
            } else {
                $where_clauses[] = "p.status_processo = :status";
                $params[':status'] = $filters['status'];
        }
    }
    if (!empty($filters['titulo'])) {
        $where_clauses[] = "p.titulo LIKE :titulo";
        $params[':titulo'] = '%' . $filters['titulo'] . '%';
    }
    if (!empty($filters['cliente_id'])) {
        $where_clauses[] = "p.cliente_id = :cliente_id";
        $params[':cliente_id'] = $filters['cliente_id'];
    }
    if (!empty($filters['os_numero'])) {
        $where_clauses[] = "p.os_numero_omie LIKE :os_numero";
        $params[':os_numero'] = '%' . $filters['os_numero'] . '%';
    }
    if (!empty($filters['tipo_servico'])) {
        $where_clauses[] = "FIND_IN_SET(:tipo_servico, p.categorias_servico)";
        $params[':tipo_servico'] = $filters['tipo_servico'];
    }
    if (!empty($filters['tradutor_id'])) {
        $where_clauses[] = "p.tradutor_id = :tradutor_id";
        $params[':tradutor_id'] = $filters['tradutor_id'];
    }
    if (!$ignoreDateAndVendorFilters && !empty($filters['data_inicio'])) {
        $where_clauses[] = "p.data_criacao >= :data_inicio";
        $params[':data_inicio'] = $filters['data_inicio'];
    }
    if (!$ignoreDateAndVendorFilters && !empty($filters['data_fim'])) {
        $where_clauses[] = "p.data_criacao <= :data_fim";
        $params[':data_fim'] = $filters['data_fim'];
    }
    if (!empty($filters['tipo_prazo'])) {
        switch ($filters['tipo_prazo']) {
            case 'falta_3': $where_clauses[] = "DATEDIFF(p.data_previsao_entrega, $today) = 3"; break;
            case 'falta_2': $where_clauses[] = "DATEDIFF(p.data_previsao_entrega, $today) = 2"; break;
            case 'falta_1': $where_clauses[] = "DATEDIFF(p.data_previsao_entrega, $today) = 1"; break;
            case 'vence_hoje': $where_clauses[] = "DATEDIFF(p.data_previsao_entrega, $today) = 0"; break;
            case 'venceu_1': $where_clauses[] = "DATEDIFF(p.data_previsao_entrega, $today) = -1"; break;
            case 'venceu_2': $where_clauses[] = "DATEDIFF(p.data_previsao_entrega, $today) = -2"; break;
            case 'venceu_3_mais': $where_clauses[] = "DATEDIFF(p.data_previsao_entrega, $today) <= -3"; break;
        }
    }

    $where_part = !empty($where_clauses) ? ' WHERE ' . implode(' AND ', $where_clauses) : '';
    $allowedSorts = [
        'titulo' => 'p.titulo',
        'cliente' => 'c.nome_cliente',
        'omie' => 'p.os_numero_omie',
        'dataEntrada' => 'p.data_criacao',
        'dataEnvio' => 'p.data_inicio_traducao',
    ];
    $sortKey = $filters['sort'] ?? null;
    $sortDirection = strtoupper($filters['direction'] ?? 'ASC');
    $sortDirection = $sortDirection === 'DESC' ? 'DESC' : 'ASC';

    if ($sortKey && isset($allowedSorts[$sortKey])) {
        $order_part = ' ORDER BY ' . $allowedSorts[$sortKey] . ' ' . $sortDirection . ', p.id DESC';
    } else {
        $order_part = " ORDER BY
        (CASE WHEN p.status_processo IN ('Orçamento', 'Orçamento Pendente') THEN 1 ELSE 0 END),
        p.data_criacao DESC";
    }
    $limit_offset_part = " LIMIT :limit OFFSET :offset";
    $sql = $select_part . $from_part . $where_part . $order_part . $limit_offset_part;

    try {
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', (int) $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', (int) $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erro ao buscar processos filtrados: " . $e->getMessage());
        return [];
    }
}

    /**
     * Retorna a contagem total de processos com base nos mesmos filtros da listagem.
     * @param array $filters Filtros aplicados.
     * @return int
     */
    public function getTotalFilteredProcessesCount(array $filters = []): int
    {
        $sql = "SELECT COUNT(*) FROM processos p
            JOIN clientes c ON p.cliente_id = c.id";
        $params = [];
        $where_clauses = [];


        // Garante que a contagem também exclua os orçamentos por padrão.
        if (empty($filters['status']) && empty($filters['filtro_card'])) {
            $where_clauses[] = "p.status_processo NOT IN ('Orçamento', 'Orçamento Pendente', 'Cancelado', 'Recusado', 'Serviço Pendente', 'Serviço pendente')";

        }

        $deadlineDateExpression = "COALESCE(\n            p.data_previsao_entrega,\n            CASE\n                WHEN p.traducao_prazo_dias IS NOT NULL AND p.data_inicio_traducao IS NOT NULL\n                    THEN DATE_ADD(p.data_inicio_traducao, INTERVAL p.traducao_prazo_dias DAY)\n                WHEN p.prazo_dias IS NOT NULL AND p.data_inicio_traducao IS NOT NULL\n                    THEN DATE_ADD(p.data_inicio_traducao, INTERVAL p.prazo_dias DAY)\n                ELSE NULL\n            END\n        )";

        $deadlineDiffExpression = "CASE\n            WHEN LOWER(p.status_processo) IN ('pendente de pagamento', 'pendente de documentos', 'aguardando pagamento', 'aguardando pagamentos', 'aguardando documento', 'aguardando documentos', 'aguardando documentacao', 'aguardando documentação') AND p.prazo_dias_restantes IS NOT NULL THEN p.prazo_dias_restantes\n            WHEN {$deadlineDateExpression} IS NOT NULL THEN DATEDIFF({$deadlineDateExpression}, CURDATE())\n            ELSE NULL\n        END";

        if (!empty($filters['filtro_card'])) {
            switch ($filters['filtro_card']) {
                case 'ativos':
                    $where_clauses[] = "LOWER(p.status_processo) IN ('serviço em andamento', 'servico em andamento', 'pendente de pagamento', 'pendente de documentos', 'aguardando pagamento', 'aguardando pagamentos', 'aguardando documento', 'aguardando documentos', 'aguardando documentacao', 'aguardando documentação')";
                    break;
                case 'pendentes':
                    $where_clauses[] = "p.status_processo IN ('Serviço Pendente', 'Serviço pendente')";
                    break;
                case 'orcamentos':
                    $where_clauses[] = "LOWER(p.status_processo) IN ('orçamento pendente', 'orcamento pendente')";
                    break;
                case 'finalizados_mes':
                    $where_clauses[] = "p.status_processo IN ('Concluído', 'Finalizado') AND MONTH(p.data_finalizacao_real) = MONTH(CURDATE()) AND YEAR(p.data_finalizacao_real) = YEAR(CURDATE())";
                    break;
                case 'atrasados':
                    $where_clauses[] = "p.data_previsao_entrega < CURDATE() AND LOWER(p.status_processo) NOT IN ('concluído', 'concluido', 'finalizado', 'finalizada', 'arquivado', 'arquivada', 'cancelado', 'recusado', 'recusada', 'pendente de pagamento', 'pendente de documentos', 'aguardando pagamento', 'aguardando pagamentos', 'aguardando documento', 'aguardando documentos', 'aguardando documentacao', 'aguardando documentação')";
                    break;
            }
        }

    // Lógica de Filtros (deve ser idêntica à de getFilteredProcesses)
    $ignoreDateAndVendorFilters = ($filters['filtro_card'] ?? '') === 'orcamentos';

    if (!$ignoreDateAndVendorFilters && !empty($filters['vendedor_id'])) {
        $where_clauses[] = "p.vendedor_id = :vendedor_id";
        $params[':vendedor_id'] = $filters['vendedor_id'];
    }
    if (!empty($filters['status']) && $filters['status'] !== '__all__') {
        $statusValue = mb_strtolower($filters['status']);
        if (in_array($statusValue, ['serviço em andamento', 'servico em andamento'], true)) {
            $where_clauses[] = "LOWER(p.status_processo) IN ('serviço em andamento', 'servico em andamento', 'pendente de pagamento', 'pendente de documentos', 'aguardando pagamento', 'aguardando pagamentos', 'aguardando documento', 'aguardando documentos', 'aguardando documentacao', 'aguardando documentação')";
        } else {
            $where_clauses[] = "p.status_processo = :status";
            $params[':status'] = $filters['status'];
        }
    }
    if (!empty($filters['titulo'])) {
        $where_clauses[] = "p.titulo LIKE :titulo";
        $params[':titulo'] = '%' . $filters['titulo'] . '%';
    }
    if (!empty($filters['cliente_id'])) {
        $where_clauses[] = "p.cliente_id = :cliente_id";
        $params[':cliente_id'] = $filters['cliente_id'];
    }
    if (!empty($filters['os_numero'])) {
        $where_clauses[] = "p.os_numero_omie LIKE :os_numero";
        $params[':os_numero'] = '%' . $filters['os_numero'] . '%';
    }
    if (!empty($filters['tipo_servico'])) {
        $where_clauses[] = "FIND_IN_SET(:tipo_servico, p.categorias_servico)";
        $params[':tipo_servico'] = $filters['tipo_servico'];
    }
    if (!empty($filters['tradutor_id'])) {
        $where_clauses[] = "p.tradutor_id = :tradutor_id";
        $params[':tradutor_id'] = $filters['tradutor_id'];
    }
    if (!$ignoreDateAndVendorFilters && !empty($filters['data_inicio'])) {
        $where_clauses[] = "p.data_criacao >= :data_inicio";
        $params[':data_inicio'] = $filters['data_inicio'];
    }
    if (!$ignoreDateAndVendorFilters && !empty($filters['data_fim'])) {
        $where_clauses[] = "p.data_criacao <= :data_fim";
        $params[':data_fim'] = $filters['data_fim'];
    }
    if (!empty($filters['tipo_prazo'])) {
        switch ($filters['tipo_prazo']) {
            case 'falta_3': $where_clauses[] = "DATEDIFF(p.data_previsao_entrega, $today) = 3"; break;
            case 'falta_2': $where_clauses[] = "DATEDIFF(p.data_previsao_entrega, $today) = 2"; break;
            case 'falta_1': $where_clauses[] = "DATEDIFF(p.data_previsao_entrega, $today) = 1"; break;
            case 'vence_hoje': $where_clauses[] = "DATEDIFF(p.data_previsao_entrega, $today) = 0"; break;
            case 'venceu_1': $where_clauses[] = "DATEDIFF(p.data_previsao_entrega, $today) = -1"; break;
            case 'venceu_2': $where_clauses[] = "DATEDIFF(p.data_previsao_entrega, $today) = -2"; break;
            case 'venceu_3_mais': $where_clauses[] = "DATEDIFF(p.data_previsao_entrega, $today) <= -3"; break;
        }
    }

    $where_part = !empty($where_clauses) ? ' WHERE ' . implode(' AND ', $where_clauses) : '';
    $sql .= $where_part;

    try {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log("Erro ao contar processos filtrados: " . $e->getMessage());
        return 0;
    }
}

    public function getProcessesForTvPanel(array $filters = []): array
    {
        $baseSql = "SELECT
                    p.id, p.titulo, p.status_processo, p.data_criacao, p.data_previsao_entrega,
                    p.categorias_servico, c.nome_cliente, t.nome_tradutor, p.os_numero_omie,
                    p.data_inicio_traducao, p.data_previsao_entrega, p.prazo_dias,
                    p.traducao_modalidade, p.finalizacao_tipo, p.data_envio_cartorio,
                    p.data_envio_assinatura, p.data_devolucao_assinatura,
                    (SELECT COALESCE(SUM(d.quantidade), 0) FROM documentos d WHERE d.processo_id = p.id) AS total_documentos_soma,
                    COALESCE(
                        p.data_previsao_entrega,
                        CASE
                            WHEN p.prazo_dias IS NOT NULL
                                THEN DATE_ADD(p.data_criacao, INTERVAL p.prazo_dias DAY)
                            ELSE p.data_previsao_entrega
                        END
                    ) AS prazo_estimado
                FROM processos AS p
                JOIN clientes AS c ON p.cliente_id = c.id
                LEFT JOIN tradutores AS t ON p.tradutor_id = t.id
                LEFT JOIN vendedores AS vend ON p.vendedor_id = vend.id
                LEFT JOIN users AS u ON vend.user_id = u.id";

        $params = [];
        $excludedPlaceholders = [];
        foreach (self::TV_PANEL_EXCLUDED_STATUSES as $index => $status) {
            $placeholder = ":excluded_status_{$index}";
            $excludedPlaceholders[] = $placeholder;
            $params[$placeholder] = $status;
        }

        $whereParts = [];
        if (!empty($excludedPlaceholders)) {
            $whereParts[] = 'p.status_processo NOT IN (' . implode(', ', $excludedPlaceholders) . ')';
        }

        if (!empty($filters['statuses']) && is_array($filters['statuses'])) {
            $statuses = array_values(array_filter($filters['statuses'], static function ($status) {
                return is_string($status) && $status !== '';
            }));

            if (!empty($statuses)) {
                $placeholders = [];
                foreach ($statuses as $index => $status) {
                    $placeholder = ":status_tv_{$index}";
                    $placeholders[] = $placeholder;
                    $params[$placeholder] = $status;
                }

                $whereParts[] = 'p.status_processo IN (' . implode(', ', $placeholders) . ')';
            }
        }

        $sql = $baseSql;
        if (!empty($whereParts)) {
            $sql .= ' WHERE ' . implode(' AND ', $whereParts);
        }

        $sql .= " ORDER BY
                    CASE WHEN prazo_estimado IS NULL THEN 1 ELSE 0 END,
                    prazo_estimado ASC";

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $exception) {
            error_log('Erro ao buscar processos para o painel de TV: ' . $exception->getMessage());
            return [];
        }
    }

    public function getAvailableStatuses(): array
    {
        $sql = "SELECT DISTINCT status_processo FROM processos WHERE status_processo IS NOT NULL AND status_processo <> '' ORDER BY status_processo";

        try {
            $stmt = $this->pdo->query($sql);
            $statuses = $stmt->fetchAll(PDO::FETCH_COLUMN);
            if (!is_array($statuses) || empty($statuses)) {
                return $this->getDefaultStatuses();
            }

            return array_values(array_unique(array_map('strval', $statuses)));
        } catch (PDOException $exception) {
            error_log('Erro ao buscar status disponíveis dos processos: ' . $exception->getMessage());
            return $this->getDefaultStatuses();
        }
    }

    private function getDefaultStatuses(): array
    {
        return [
            'Orçamento',
            'Orçamento Pendente',
            'Serviço Pendente',
            'Serviço em Andamento',
            'Pago - A Enviar',
            'Pendente de Pagamento',
            'Pendente de Documentos',
            'Serviço',
            'Serviço Pendente com Serviço',
            'Serviço em andamento',
            'Concluído',
            'Finalizado',
            'Cancelado',
            'Recusado',
        ];
    }


    // =======================================================================
    // MÉTODOS DE RELATÓRIO FINANCEIRO
    // =======================================================================

    /**
     * Busca os dados para a tabela de processos financeiros individuais.
     * @param array $filters Filtros de data, vendedor e forma de pagamento.
     * @return array
     */
    public function getFinancialData(array $filters = []): array
    {
        $this->loadProcessColumns();

        $hasPaymentDate1 = $this->hasProcessColumn('data_pagamento_1');
        $hasPaymentDate2 = $this->hasProcessColumn('data_pagamento_2');
        $conversionColumn = $this->hasProcessColumn('data_conversao') ? 'p.data_conversao' : ($hasPaymentDate1 ? 'p.data_pagamento_1' : 'p.data_criacao');
        $valorEntradaExpr = $this->hasProcessColumn('orcamento_valor_entrada') ? 'COALESCE(p.orcamento_valor_entrada, 0)' : '0';
        $valorParcela2Expr = $this->hasProcessColumn('orcamento_valor_restante') ? 'COALESCE(p.orcamento_valor_restante, 0)' : 'GREATEST(COALESCE(p.valor_total, 0) - COALESCE(p.orcamento_valor_entrada, 0), 0)';
        $valorRecebidoExpr = 'COALESCE(' . ($hasPaymentDate1 ? "CASE WHEN p.data_pagamento_1 IS NOT NULL THEN {$valorEntradaExpr} ELSE 0 END" : '0') . ', 0)'
            . ' + COALESCE(' . ($hasPaymentDate2 ? "CASE WHEN p.data_pagamento_2 IS NOT NULL THEN {$valorParcela2Expr} ELSE 0 END" : '0') . ', 0)';

        $selectParts = [
            'p.id',
            'p.titulo',
            'p.valor_total',
            'p.data_criacao',
            'p.categorias_servico',
            'p.os_numero_omie',
            'p.os_numero_conta_azul',
            'p.orcamento_numero',
            'p.data_finalizacao_real',
            'c.nome_cliente AS cliente_nome',
            "COALESCE(u.nome_completo, 'Sistema') AS nome_vendedor",
            "{$valorRecebidoExpr} AS valor_recebido",
            '(SELECT COALESCE(SUM(d.quantidade), 0) FROM documentos d WHERE d.processo_id = p.id) AS total_documentos',
        ];

        if ($this->hasProcessColumn('orcamento_parcelas')) {
            $selectParts[] = 'COALESCE(p.orcamento_parcelas, 1) AS orcamento_parcelas';
        } else {
            $selectParts[] = '1 AS orcamento_parcelas';
        }

        $selectParts[] = $hasPaymentDate1
            ? 'p.data_pagamento_1'
            : 'NULL AS data_pagamento_1';

        $selectParts[] = $hasPaymentDate2
            ? 'p.data_pagamento_2'
            : 'NULL AS data_pagamento_2';

        $selectParts[] = $this->hasProcessColumn('data_conversao')
            ? 'p.data_conversao'
            : ($hasPaymentDate1 ? 'p.data_pagamento_1 AS data_conversao' : 'p.data_criacao AS data_conversao');

        $selectParts[] = $hasPaymentDate1
            ? 'COALESCE(p.data_pagamento_1, p.data_lancamento_receita) AS data_pagamento'
            : 'COALESCE(p.data_lancamento_receita, p.data_criacao) AS data_pagamento';

        $selectParts[] = $this->hasProcessColumn('desconto')
            ? 'COALESCE(p.desconto, 0) AS desconto'
            : '0 AS desconto';

        if ($this->hasProcessColumn('valor_restante')) {
            $selectParts[] = 'COALESCE(p.valor_restante, 0) AS valor_restante';
        } elseif ($this->hasProcessColumn('orcamento_valor_restante')) {
            $selectParts[] = 'COALESCE(p.orcamento_valor_restante, 0) AS valor_restante';
        } else {
            $selectParts[] = 'GREATEST(COALESCE(p.valor_total, 0) - COALESCE(p.orcamento_valor_entrada, 0), 0) AS valor_restante';
        }

        $selectParts[] = $this->getStatusFinanceiroSelectExpression();
        $selectParts[] = 'COALESCE(comm.total_comissao_vendedor, 0) AS total_comissao_vendedor';
        $selectParts[] = 'COALESCE(comm.total_comissao_sdr, 0) AS total_comissao_sdr';

        $sql = 'SELECT ' . implode(",\n               ", $selectParts)
            . "\nFROM processos AS p
                JOIN clientes AS c ON p.cliente_id = c.id
                LEFT JOIN vendedores AS v ON p.vendedor_id = v.id
                LEFT JOIN users AS u ON v.user_id = u.id
                LEFT JOIN (
                    SELECT venda_id,
                           SUM(CASE WHEN tipo_comissao = 'vendedor' THEN valor_comissao ELSE 0 END) AS total_comissao_vendedor,
                           SUM(CASE WHEN tipo_comissao = 'sdr' THEN valor_comissao ELSE 0 END) AS total_comissao_sdr
                    FROM comissoes
                    GROUP BY venda_id
                ) comm ON comm.venda_id = p.id";

        $where = ["p.status_processo NOT IN ('Orçamento', 'Orçamento Pendente', 'Cancelado', 'Recusado')",
            "{$conversionColumn} BETWEEN :data_inicio AND :data_fim",
        ];
        $params = [
            ':data_inicio' => $filters['data_inicio'] ?? (date('Y-m-01') . ' 00:00:00'),
            ':data_fim' => $filters['data_fim'] ?? (date('Y-m-t') . ' 23:59:59'),
        ];

        if (!empty($filters['vendedor_id'])) {
            $where[] = 'p.vendedor_id = :vendedor_id';
            $params[':vendedor_id'] = $filters['vendedor_id'];
        }

        if (!empty($filters['cliente_id'])) {
            $where[] = 'p.cliente_id = :cliente_id';
            $params[':cliente_id'] = $filters['cliente_id'];
        }

        if (!empty($filters['sdr_id'])) {
            $sdrExpression = $this->getSdrIdSelectExpression();
            $where[] = "{$sdrExpression} = :sdr_id";
            $params[':sdr_id'] = $filters['sdr_id'];
        }

        $sql .= ' WHERE ' . implode(' AND ', $where);
        $sql .= " ORDER BY {$conversionColumn} DESC, p.id DESC";

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Erro ao buscar dados financeiros individuais: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Retorna os dados para os cards de resumo financeiro.
     * @param string $start_date Data de início (YYYY-MM-DD).
     * @param string $end_date Data de fim (YYYY-MM-DD).
     * @return array
     */
    public function getOverallFinancialSummary($start_date, $end_date, array $filters = []): array
    {
        $hasPaymentDate1 = $this->hasProcessColumn('data_pagamento_1');
        $hasPaymentDate2 = $this->hasProcessColumn('data_pagamento_2');
        $valorRestanteExpr = $this->getValorRestanteExpression();
        $conversionColumn = $this->hasProcessColumn('data_conversao') ? 'p.data_conversao' : ($hasPaymentDate1 ? 'p.data_pagamento_1' : 'p.data_criacao');
        $valorEntradaExpr = $this->hasProcessColumn('orcamento_valor_entrada') ? 'COALESCE(p.orcamento_valor_entrada, 0)' : '0';
        $valorParcela2Expr = $this->hasProcessColumn('orcamento_valor_restante') ? 'COALESCE(p.orcamento_valor_restante, 0)' : 'GREATEST(COALESCE(p.valor_total, 0) - COALESCE(p.orcamento_valor_entrada, 0), 0)';
        $valorRecebidoExpr = 'COALESCE(' . ($hasPaymentDate1 ? "CASE WHEN p.data_pagamento_1 IS NOT NULL THEN {$valorEntradaExpr} ELSE 0 END" : '0') . ', 0)'
            . ' + COALESCE(' . ($hasPaymentDate2 ? "CASE WHEN p.data_pagamento_2 IS NOT NULL THEN {$valorParcela2Expr} ELSE 0 END" : '0') . ', 0)';

        $startDateTime = (!empty($start_date) && preg_match('/\d{2}:\d{2}:\d{2}$/', (string) $start_date))
            ? $start_date
            : (($start_date ?: date('Y-m-01')) . ' 00:00:00');
        $endDateTime = (!empty($end_date) && preg_match('/\d{2}:\d{2}:\d{2}$/', (string) $end_date))
            ? $end_date
            : (($end_date ?: date('Y-m-t')) . ' 23:59:59');

        $commissionsSubquery = "(
                SELECT venda_id,
                       SUM(CASE WHEN tipo_comissao = 'vendedor' THEN valor_comissao ELSE 0 END) AS total_comissao_vendedor,
                       SUM(CASE WHEN tipo_comissao = 'sdr' THEN valor_comissao ELSE 0 END) AS total_comissao_sdr
                FROM comissoes
                GROUP BY venda_id
            ) comm";

        $docsSubquery = "(
                SELECT processo_id, SUM(COALESCE(quantidade, 0)) AS total_documentos
                FROM documentos
                GROUP BY processo_id
            ) docs";

        $sql_totals = "SELECT
                        SUM(COALESCE(p.valor_total, 0)) AS total_valor_total,
                        SUM({$valorRecebidoExpr}) AS total_valor_entrada,
                        SUM({$valorRestanteExpr}) AS total_valor_restante,
                        SUM(COALESCE(comm.total_comissao_vendedor, 0)) AS total_comissao_vendedor,
                        SUM(COALESCE(comm.total_comissao_sdr, 0)) AS total_comissao_sdr,
                        SUM(COALESCE(docs.total_documentos, 0)) AS total_documentos
                FROM processos p
                LEFT JOIN {$commissionsSubquery} ON comm.venda_id = p.id
                LEFT JOIN {$docsSubquery} ON docs.processo_id = p.id
                WHERE p.status_processo NOT IN ('Orçamento', 'Orçamento Pendente', 'Cancelado', 'Recusado')
                  AND {$conversionColumn} BETWEEN :start_date AND :end_date";

        $params = [
            ':start_date' => $startDateTime,
            ':end_date' => $endDateTime,
        ];

        if (!empty($filters['vendedor_id'])) {
            $sql_totals .= " AND p.vendedor_id = :vendedor_id";
            $params[':vendedor_id'] = $filters['vendedor_id'];
        }

        if (!empty($filters['cliente_id'])) {
            $sql_totals .= " AND p.cliente_id = :cliente_id";
            $params[':cliente_id'] = $filters['cliente_id'];
        }

        if (!empty($filters['sdr_id'])) {
            $sdrExpression = $this->getSdrIdSelectExpression();
            $sql_totals .= " AND {$sdrExpression} = :sdr_id";
            $params[':sdr_id'] = $filters['sdr_id'];
        }

        try {
            $stmt_totals = $this->pdo->prepare($sql_totals);
            $stmt_totals->execute($params);
            $summary = $stmt_totals->fetch(PDO::FETCH_ASSOC);

            $total_documentos_soma = $summary['total_documentos'] ?? 0;

            $media_valor_documento = 0;
            if (!empty($total_documentos_soma) && $total_documentos_soma > 0) {
                $media_valor_documento = ($summary['total_valor_total'] ?? 0) / $total_documentos_soma;
            }

            return [
                'total_valor_total'      => $summary['total_valor_total'] ?? 0,
                'total_valor_entrada'    => $summary['total_valor_entrada'] ?? 0,
                'total_valor_restante'   => $summary['total_valor_restante'] ?? 0,
                'total_comissao_vendedor' => $summary['total_comissao_vendedor'] ?? 0,
                'total_comissao_sdr'     => $summary['total_comissao_sdr'] ?? 0,
                'media_valor_documento'  => $media_valor_documento,
            ];
        } catch (PDOException $e) {
            error_log("Erro ao buscar resumo financeiro geral: " . $e->getMessage());
            return [
                'total_valor_total' => 0,
                'total_valor_entrada' => 0,
                'total_valor_restante' => 0,
                'total_comissao_vendedor' => 0,
                'total_comissao_sdr' => 0,
                'media_valor_documento' => 0,
            ];
        }
    }

    public function getBudgetPipelineSummary(?string $startDate = null, ?string $endDate = null): array
    {
        $params = [];

        $budgetDateCondition = '';
        if (!empty($startDate)) {
            $budgetDateCondition .= ' AND p.data_criacao >= :budgetStartDate';
            $params[':budgetStartDate'] = $startDate . ' 00:00:00';
        }
        if (!empty($endDate)) {
            $budgetDateCondition .= ' AND p.data_criacao <= :budgetEndDate';
            $params[':budgetEndDate'] = $endDate . ' 23:59:59';
        }

        $serviceDateCondition = '';
        if (!empty($startDate)) {
            $serviceDateCondition .= ' AND p.data_conversao >= :serviceStartDate';
            $params[':serviceStartDate'] = $startDate . ' 00:00:00';
        }
        if (!empty($endDate)) {
            $serviceDateCondition .= ' AND p.data_conversao <= :serviceEndDate';
            $params[':serviceEndDate'] = $endDate . ' 23:59:59';
        }

        $sql = "SELECT
                    SUM(CASE WHEN p.status_processo IN ('Orçamento', 'Orçamento Pendente'){$budgetDateCondition} THEN 1 ELSE 0 END) AS budgetCount,
                    SUM(CASE WHEN p.status_processo IN ('Orçamento', 'Orçamento Pendente'){$budgetDateCondition} THEN COALESCE(p.valor_total, 0) ELSE 0 END) AS budgetValue,
                    SUM(CASE WHEN p.status_processo IN ('Serviço Pendente', 'Serviço pendente', 'Serviço em Andamento', 'Serviço em andamento', 'Pago - A Enviar', 'Pendente de Pagamento', 'Pendente de Documentos') AND p.data_conversao IS NOT NULL{$serviceDateCondition} THEN 1 ELSE 0 END) AS pipelineCount,
                    SUM(CASE WHEN p.status_processo IN ('Serviço Pendente', 'Serviço pendente', 'Serviço em Andamento', 'Serviço em andamento', 'Pago - A Enviar', 'Pendente de Pagamento', 'Pendente de Documentos') AND p.data_conversao IS NOT NULL{$serviceDateCondition} THEN COALESCE(p.valor_total, 0) ELSE 0 END) AS pipelineValue,
                    SUM(CASE WHEN p.status_processo IN ('Concluído', 'Finalizado') AND p.data_conversao IS NOT NULL{$serviceDateCondition} THEN 1 ELSE 0 END) AS closedCount,
                    SUM(CASE WHEN p.status_processo IN ('Concluído', 'Finalizado') AND p.data_conversao IS NOT NULL{$serviceDateCondition} THEN COALESCE(p.valor_total, 0) ELSE 0 END) AS closedValue
                FROM processos p";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        $data = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $budgetValue = (float) ($data['budgetValue'] ?? 0);
        $budgetCount = (int) ($data['budgetCount'] ?? 0);
        $averageBudgetValue = $budgetCount > 0 ? $budgetValue / $budgetCount : 0.0;

        return [
            'budgetCount' => $budgetCount,
            'budgetValue' => $budgetValue,
            'pipelineCount' => (int) ($data['pipelineCount'] ?? 0),
            'pipelineValue' => (float) ($data['pipelineValue'] ?? 0),
            'closedCount' => (int) ($data['closedCount'] ?? 0),
            'closedValue' => (float) ($data['closedValue'] ?? 0),
            'averageBudgetValue' => $averageBudgetValue,
        ];
    }

    public function getVendorCommissionSummary(?string $startDate = null, ?string $endDate = null): array
    {
        $conditions = ["p.status_processo NOT IN ('Orçamento', 'Orçamento Pendente', 'Cancelado', 'Recusado')", 'p.data_conversao IS NOT NULL'];
        $params = [];

        if (!empty($startDate)) {
            $conditions[] = 'p.data_conversao >= :startDate';
            $params[':startDate'] = $startDate . ' 00:00:00';
        }

        if (!empty($endDate)) {
            $conditions[] = 'p.data_conversao <= :endDate';
            $params[':endDate'] = $endDate . ' 23:59:59';
        }

        $processFilters = $conditions ? ' AND ' . implode(' AND ', $conditions) : '';

        $sql = "SELECT
                    v.id AS vendorId,
                    u.id AS userId,
                    u.nome_completo AS vendorName,
                    COALESCE(v.percentual_comissao, 0) AS commissionPercent,
                    COUNT(DISTINCT p.id) AS dealsCount,
                    SUM(COALESCE(p.valor_total, 0)) AS totalSales,
                    SUM(COALESCE(comm.total_comissao_vendedor, 0)) AS totalCommission
                FROM vendedores v
                JOIN users u ON u.id = v.user_id
                LEFT JOIN processos p ON p.vendedor_id = v.id$processFilters
                LEFT JOIN (
                    SELECT venda_id,
                           SUM(CASE WHEN tipo_comissao = 'vendedor' THEN valor_comissao ELSE 0 END) AS total_comissao_vendedor
                    FROM comissoes
                    GROUP BY venda_id
                ) comm ON comm.venda_id = p.id
                GROUP BY v.id, u.id, u.nome_completo, v.percentual_comissao
                ORDER BY totalCommission DESC, vendorName ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as &$row) {
            $dealsCount = (int) ($row['dealsCount'] ?? 0);
            $totalSales = (float) ($row['totalSales'] ?? 0);
            $row['averageTicket'] = $dealsCount > 0 ? $totalSales / $dealsCount : 0.0;
        }

        unset($row);

        return $rows;
    }

    public function calculateVendorCommission(array $processo, float $percentualComissao): float
    {
        $status = $processo['status_processo'] ?? '';
        $serviceStatuses = [
            'Serviço Pendente',
            'Serviço pendente',
            'Serviço em Andamento',
            'Serviço em andamento',
            'Pago - A Enviar',
            'Pendente de Pagamento',
            'Pendente de Documentos',
            'Concluído',
            'Finalizado'
        ];

        if (!in_array($status, $serviceStatuses, true)) {
            return 0.0;
        }

        $valorTotal = isset($processo['valor_total']) ? (float) $processo['valor_total'] : 0.0;

        return $valorTotal > 0 ? ($valorTotal * $percentualComissao) / 100 : 0.0;
    }

    public function getVendorBudgetsByMonth(int $vendorId, string $monthStart, string $monthEnd): array
    {
        $sdrExpression = $this->getSdrIdSelectExpression();

        $sql = "SELECT
                    p.id,
                    p.orcamento_numero,
                    p.titulo,
                    p.os_numero_omie,
                    p.categorias_servico,
                    p.status_processo,
                    p.data_criacao,
                    p.valor_total,
                    {$sdrExpression} AS sdr_id,
                    c.nome_cliente
                FROM processos p
                INNER JOIN clientes c ON c.id = p.cliente_id
                WHERE p.vendedor_id = :vendorId
                  AND p.status_processo IN ('Orçamento', 'Orçamento Pendente')
                  AND p.data_criacao BETWEEN :monthStart AND :monthEnd
                ORDER BY p.data_criacao DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':vendorId', $vendorId, PDO::PARAM_INT);
        $stmt->bindValue(':monthStart', $monthStart);
        $stmt->bindValue(':monthEnd', $monthEnd);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getVendorBudgets(int $vendorId, ?string $startDate = null, ?string $endDate = null, array $filters = []): array
    {
        $sdrExpression = $this->getSdrIdSelectExpression();

        $sql = "SELECT
                    p.id,
                    p.orcamento_numero,
                    p.titulo,
                    p.categorias_servico,
                    p.status_processo,
                    p.data_criacao,
                    p.valor_total,
                    {$sdrExpression} AS sdr_id,
                    c.nome_cliente
                FROM processos p
                INNER JOIN clientes c ON c.id = p.cliente_id
                WHERE p.vendedor_id = :vendorId
                  AND p.status_processo IN ('Orçamento', 'Orçamento Pendente')";

        $params = [
            ':vendorId' => $vendorId,
        ];

        if (!empty($startDate)) {
            $sql .= " AND p.data_criacao >= :startDate";
            $params[':startDate'] = $startDate;
        }

        if (!empty($endDate)) {
            $sql .= " AND p.data_criacao <= :endDate";
            $params[':endDate'] = $endDate;
        }

        if (!empty($filters['cliente_id'])) {
            $sql .= " AND p.cliente_id = :clienteId";
            $params[':clienteId'] = (int) $filters['cliente_id'];
        }

        if (!empty($filters['status'])) {
            $sql .= " AND p.status_processo = :status";
            $params[':status'] = $filters['status'];
        }

        if (!empty($filters['titulo'])) {
            $sql .= " AND p.titulo LIKE :titulo";
            $params[':titulo'] = '%' . $filters['titulo'] . '%';
        }

        $sql .= " ORDER BY p.data_criacao DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getVendorServicesByMonth(int $vendorId, string $monthStart, string $monthEnd): array
    {
        $sdrExpression = $this->getSdrIdSelectExpression();

        $sql = "SELECT
                    p.id,
                    p.orcamento_numero,
                    p.titulo,
                    p.categorias_servico,
                    p.status_processo,
                    p.data_criacao,
                    p.data_conversao,
                    p.valor_total,
                    {$sdrExpression} AS sdr_id,
                    c.nome_cliente
                FROM processos p
                INNER JOIN clientes c ON c.id = p.cliente_id
                WHERE p.vendedor_id = :vendorId
                  AND p.status_processo NOT IN ('Orçamento', 'Orçamento Pendente', 'Cancelado', 'Recusado', 'Concluído', 'Finalizado')
                  AND p.data_conversao IS NOT NULL
                  AND p.data_conversao BETWEEN :monthStart AND :monthEnd
                ORDER BY p.data_conversao DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':vendorId', $vendorId, PDO::PARAM_INT);
        $stmt->bindValue(':monthStart', $monthStart);
        $stmt->bindValue(':monthEnd', $monthEnd);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getVendorServices(int $vendorId, ?string $startDate = null, ?string $endDate = null, array $filters = []): array
    {
        $sdrExpression = $this->getSdrIdSelectExpression();

        $sql = "SELECT
                    p.id,
                    p.orcamento_numero,
                    p.titulo,
                    p.categorias_servico,
                    p.status_processo,
                    p.data_criacao,
                    p.valor_total,
                    {$sdrExpression} AS sdr_id,
                    c.nome_cliente
                FROM processos p
                INNER JOIN clientes c ON c.id = p.cliente_id
                WHERE p.vendedor_id = :vendorId
                  AND p.status_processo NOT IN ('Orçamento', 'Orçamento Pendente', 'Recusado', 'Finalizado')";

        $params = [
            ':vendorId' => $vendorId,
        ];

        if (!empty($startDate)) {
            $sql .= " AND p.data_criacao >= :startDate";
            $params[':startDate'] = $startDate;
        }

        if (!empty($endDate)) {
            $sql .= " AND p.data_criacao <= :endDate";
            $params[':endDate'] = $endDate;
        }

        if (!empty($filters['cliente_id'])) {
            $sql .= " AND p.cliente_id = :clienteId";
            $params[':clienteId'] = (int) $filters['cliente_id'];
        }

        if (!empty($filters['status'])) {
            $sql .= " AND p.status_processo = :status";
            $params[':status'] = $filters['status'];
        }

        if (!empty($filters['titulo'])) {
            $sql .= " AND p.titulo LIKE :titulo";
            $params[':titulo'] = '%' . $filters['titulo'] . '%';
        }

        $sql .= " ORDER BY p.data_criacao DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getVendorActiveServicesFromLastMonth(int $vendorId, string $lastMonthStart, string $lastMonthEnd): array
    {
        $sdrExpression = $this->getSdrIdSelectExpression();

        $activeStatuses = [
            'Serviço Pendente',
            'Serviço pendente',
            'Serviço em Andamento',
            'Serviço em andamento',
            'Pago - A Enviar',
            'Pendente de Pagamento',
            'Pendente de pagamento',
            'Pendente de Documentos',
            'Pendente de documentos'
        ];

        $placeholders = implode(',', array_fill(0, count($activeStatuses), '?'));

        $sql = "SELECT
                    p.id,
                    p.orcamento_numero,
                    p.titulo,
                    p.categorias_servico,
                    p.status_processo,
                    p.data_criacao,
                    p.data_conversao,
                    p.valor_total,
                    {$sdrExpression} AS sdr_id,
                    c.nome_cliente
                FROM processos p
                INNER JOIN clientes c ON c.id = p.cliente_id
                WHERE p.vendedor_id = ?
                  AND p.status_processo IN ({$placeholders})
                  AND p.data_conversao IS NOT NULL
                  AND p.data_conversao BETWEEN ? AND ?
                ORDER BY p.data_conversao DESC";

        $stmt = $this->pdo->prepare($sql);

        $bindIndex = 1;
        $stmt->bindValue($bindIndex++, $vendorId, PDO::PARAM_INT);
        foreach ($activeStatuses as $status) {
            $stmt->bindValue($bindIndex++, $status);
        }
        $stmt->bindValue($bindIndex++, $lastMonthStart);
        $stmt->bindValue($bindIndex, $lastMonthEnd);

        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getAggregatedFinancialTotals(string $startDate, string $endDate, array $filters = [], string $groupBy = 'month'): array
    {
        $this->loadProcessColumns();

        $groupBy = in_array($groupBy, ['day', 'month', 'year'], true) ? $groupBy : 'month';

        $hasPaymentDate1 = $this->hasProcessColumn('data_pagamento_1');
        $hasPaymentDate2 = $this->hasProcessColumn('data_pagamento_2');
        $conversionColumn = $this->hasProcessColumn('data_conversao') ? 'p.data_conversao' : ($hasPaymentDate1 ? 'p.data_pagamento_1' : 'p.data_criacao');
        $valorEntradaExpr = $this->hasProcessColumn('orcamento_valor_entrada') ? 'COALESCE(p.orcamento_valor_entrada, 0)' : '0';
        $valorParcela2Expr = $this->hasProcessColumn('orcamento_valor_restante') ? 'COALESCE(p.orcamento_valor_restante, 0)' : 'GREATEST(COALESCE(p.valor_total, 0) - COALESCE(p.orcamento_valor_entrada, 0), 0)';
        $valorRecebidoExpr = 'COALESCE(' . ($hasPaymentDate1 ? "CASE WHEN p.data_pagamento_1 IS NOT NULL THEN {$valorEntradaExpr} ELSE 0 END" : '0') . ', 0)'
            . ' + COALESCE(' . ($hasPaymentDate2 ? "CASE WHEN p.data_pagamento_2 IS NOT NULL THEN {$valorParcela2Expr} ELSE 0 END" : '0') . ', 0)';
        $valorRestanteExpr = $this->getValorRestanteExpression();

        switch ($groupBy) {
            case 'day':
                $periodExpression = "DATE_FORMAT({$conversionColumn}, '%Y-%m-%d')";
                $orderBy = 'period ASC';
                break;
            case 'year':
                $periodExpression = "DATE_FORMAT({$conversionColumn}, '%Y')";
                $orderBy = 'period DESC';
                break;
            default:
                $periodExpression = "DATE_FORMAT({$conversionColumn}, '%Y-%m')";
                $orderBy = 'period DESC';
        }

        $startDateTime = (!empty($startDate) && preg_match('/\d{2}:\d{2}:\d{2}$/', (string) $startDate)) ? $startDate : ($startDate . ' 00:00:00');
        $endDateTime = (!empty($endDate) && preg_match('/\d{2}:\d{2}:\d{2}$/', (string) $endDate)) ? $endDate : ($endDate . ' 23:59:59');

        $sql = "SELECT
                    {$periodExpression} AS period,
                    COUNT(*) AS process_count,
                    SUM(COALESCE(p.valor_total, 0)) AS total_valor_total,
                    SUM({$valorRecebidoExpr}) AS total_valor_recebido,
                    SUM({$valorRestanteExpr}) AS total_valor_restante,
                    SUM(COALESCE(comm.total_comissao_vendedor, 0)) AS total_comissao_vendedor,
                    SUM(COALESCE(comm.total_comissao_sdr, 0)) AS total_comissao_sdr
                FROM processos p
                LEFT JOIN (
                    SELECT venda_id,
                           SUM(CASE WHEN tipo_comissao = 'vendedor' THEN valor_comissao ELSE 0 END) AS total_comissao_vendedor,
                           SUM(CASE WHEN tipo_comissao = 'sdr' THEN valor_comissao ELSE 0 END) AS total_comissao_sdr
                    FROM comissoes
                    GROUP BY venda_id
                ) comm ON comm.venda_id = p.id
                WHERE p.status_processo NOT IN ('Orçamento', 'Orçamento Pendente', 'Cancelado', 'Recusado')
                  AND {$conversionColumn} BETWEEN :start_date AND :end_date";

        $params = [
            ':start_date' => $startDateTime,
            ':end_date' => $endDateTime,
        ];

        if (!empty($filters['vendedor_id'])) {
            $sql .= ' AND p.vendedor_id = :vendedor_id';
            $params[':vendedor_id'] = $filters['vendedor_id'];
        }

        if (!empty($filters['cliente_id'])) {
            $sql .= ' AND p.cliente_id = :cliente_id';
            $params[':cliente_id'] = $filters['cliente_id'];
        }

        if (!empty($filters['sdr_id'])) {
            $sdrExpression = $this->getSdrIdSelectExpression();
            $sql .= " AND {$sdrExpression} = :sdr_id";
            $params[':sdr_id'] = $filters['sdr_id'];
        }

        $sql .= ' GROUP BY period ORDER BY ' . $orderBy;

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $exception) {
            error_log('Erro ao buscar totais financeiros agregados: ' . $exception->getMessage());
            return [];
        }
    }

    public function getStatusFinanceiroOptions(): array
    {
        $defaults = [
            'pendente' => 'Pendente',
            'parcial' => 'Parcial',
            'pago' => 'Pago',
        ];

        if (!$this->hasProcessColumn('status_financeiro')) {
            return $defaults;
        }

        try {
            $stmt = $this->pdo->query("SELECT DISTINCT LOWER(status_financeiro) AS status_financeiro FROM processos WHERE status_financeiro IS NOT NULL AND status_financeiro <> '' ORDER BY status_financeiro");
            $statuses = $stmt->fetchAll(PDO::FETCH_COLUMN);

            if (empty($statuses)) {
                return $defaults;
            }

            $options = [];
            foreach ($statuses as $status) {
                $status = trim((string) $status);
                if ($status === '') {
                    continue;
                }

                $options[$status] = mb_convert_case($status, MB_CASE_TITLE, 'UTF-8');
            }

            return !empty($options) ? $options : $defaults;
        } catch (PDOException $exception) {
            error_log('Erro ao buscar opções de status financeiro: ' . $exception->getMessage());
            return $defaults;
        }
    }

    
    /**
     * Atualiza um campo financeiro específico de um processo (para edição inline).
     * @param int $processId ID do processo.
     * @param string $field Nome da coluna a ser atualizada.
     * @param mixed $value Novo valor.
     * @return bool
     */
    public function updateProcessFinancialField(int $processId, string $field, $value): bool
    {
        $this->loadProcessColumns();

        $columnMap = [
            'valor_total' => 'valor_total',
            'desconto' => $this->hasProcessColumn('desconto') ? 'desconto' : null,
            'valor_recebido' => $this->hasProcessColumn('valor_recebido')
                ? 'valor_recebido'
                : ($this->hasProcessColumn('orcamento_valor_entrada') ? 'orcamento_valor_entrada' : null),
            'valor_restante' => $this->hasProcessColumn('valor_restante')
                ? 'valor_restante'
                : ($this->hasProcessColumn('orcamento_valor_restante') ? 'orcamento_valor_restante' : null),
            'data_pagamento' => $this->hasProcessColumn('data_pagamento')
                ? 'data_pagamento'
                : ($this->hasProcessColumn('data_pagamento_1') ? 'data_pagamento_1' : null),
            'forma_pagamento_id' => 'forma_pagamento_id',
            'status_financeiro' => $this->hasProcessColumn('status_financeiro') ? 'status_financeiro' : null,
        ];

        if (!isset($columnMap[$field]) || $columnMap[$field] === null) {
            error_log('Tentativa de atualizar campo financeiro não suportado: ' . $field);
            return false;
        }

        $column = $columnMap[$field];

        $sql = "UPDATE processos SET {$column} = :value, data_atualizacao = NOW() WHERE id = :id";

        try {
            $stmt = $this->pdo->prepare($sql);

            if ($value === null || $value === '') {
                $stmt->bindValue(':value', null, PDO::PARAM_NULL);
            } elseif ($field === 'forma_pagamento_id') {
                $stmt->bindValue(':value', (int) $value, PDO::PARAM_INT);
            } else {
                $stmt->bindValue(':value', $value);
            }

            $stmt->bindValue(':id', $processId, PDO::PARAM_INT);

            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Erro ao atualizar campo financeiro {$field} para processo {$processId}: " . $e->getMessage());
            return false;
        }
    }


    // =======================================================================
    // MÉTODOS DO DASHBOARD
    // =======================================================================

    /**
     * Busca estatísticas principais para os cards do dashboard.
     * @return array
     */
    public function getDashboardStats(?string $filtroDataInicio = null, ?string $filtroDataFim = null, ?int $vendedorId = null)
    {
        $deadlineExpression = "COALESCE(\n            p.data_previsao_entrega,\n            CASE\n                WHEN p.traducao_prazo_dias IS NOT NULL AND p.data_inicio_traducao IS NOT NULL\n                    THEN DATE_ADD(p.data_inicio_traducao, INTERVAL p.traducao_prazo_dias DAY)\n                WHEN p.prazo_dias IS NOT NULL AND p.data_inicio_traducao IS NOT NULL\n                    THEN DATE_ADD(p.data_inicio_traducao, INTERVAL p.prazo_dias DAY)\n                ELSE NULL\n            END\n        )";

        $whereClauses = [];
        $params = [];

        if (!empty($filtroDataInicio)) {
            $whereClauses[] = 'p.data_criacao >= :data_inicio';
            $params[':data_inicio'] = $filtroDataInicio;
        }

        if (!empty($filtroDataFim)) {
            $whereClauses[] = 'p.data_criacao <= :data_fim';
            $params[':data_fim'] = $filtroDataFim;
        }

        if (!empty($vendedorId)) {
            $whereClauses[] = 'p.vendedor_id = :vendedor_id';
            $params[':vendedor_id'] = $vendedorId;
        }

        $wherePart = !empty($whereClauses) ? ' WHERE ' . implode(' AND ', $whereClauses) : '';

        $sql = "SELECT
            COUNT(CASE WHEN LOWER(p.status_processo) IN ('serviço em andamento', 'servico em andamento', 'pendente de pagamento', 'pendente de documentos', 'aguardando pagamento', 'aguardando pagamentos', 'aguardando documento', 'aguardando documentos', 'aguardando documentacao', 'aguardando documentação') THEN 1 END) as processos_ativos,
            COUNT(CASE WHEN LOWER(p.status_processo) IN ('serviço pendente', 'servico pendente') THEN 1 END) as servicos_pendentes,
            COUNT(CASE WHEN LOWER(p.status_processo) IN ('orçamento pendente', 'orcamento pendente') THEN 1 END) as orcamentos_pendentes,
            COUNT(CASE WHEN LOWER(p.status_processo) IN ('concluído', 'concluido', 'finalizado', 'finalizada') AND MONTH(p.data_finalizacao_real) = MONTH(CURDATE()) AND YEAR(p.data_finalizacao_real) = YEAR(CURDATE()) THEN 1 END) as finalizados_mes,
            COUNT(CASE WHEN p.data_previsao_entrega < CURDATE() AND LOWER(p.status_processo) NOT IN ('concluído', 'concluido', 'finalizado', 'finalizada', 'arquivado', 'arquivada', 'cancelado', 'recusado', 'recusada', 'pendente de pagamento', 'pendente de documentos', 'aguardando pagamento', 'aguardando pagamentos', 'aguardando documento', 'aguardando documentos', 'aguardando documentacao', 'aguardando documentação') THEN 1 END) as processos_atrasados
        FROM processos p" . $wherePart;
        try {
            $stmt = $this->pdo->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erro ao buscar estatísticas do dashboard: " . $e->getMessage());
            return [
                'processos_ativos' => 0,
                'servicos_pendentes' => 0,
                'orcamentos_pendentes' => 0,
                'finalizados_mes' => 0,
                'processos_atrasados' => 0,
            ];
        }
    }

    /**
     * Busca os orçamentos mais recentes para exibir no dashboard.
     * @param int $limit Número de orçamentos a retornar.
     * @return array
     */
    public function getRecentesOrcamentos($limit = 5)
    {
        // Para a seção "Últimos Orçamentos", ignoramos os serviços de clientes mensalistas
        $sql = "SELECT p.id, p.orcamento_numero, p.titulo, p.data_criacao, c.nome_cliente
                FROM processos p
                JOIN clientes c ON p.cliente_id = c.id
                WHERE p.status_processo = 'Orçamento'
                ORDER BY p.data_criacao DESC
                LIMIT :limit";

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erro ao buscar orçamentos recentes: " . $e->getMessage());
            return [];
        }
    }

    
    // =======================================================================
    // GESTÃO DE ETAPAS E STATUS
    // =======================================================================

    /**
     * Atualiza os campos relacionados às etapas de um processo.
     * @param int $id ID do processo.
     * @param array $data Dados a serem atualizados.
     * @return bool
     */
    public function updateEtapas($id, $data)
    {
        // Lista de todas as colunas que esta função tem permissão para atualizar.
        $allowed_fields = [
            'status_processo', 'tradutor_id', 'data_inicio_traducao', 'traducao_modalidade',
            'prazo_dias', 'traducao_prazo_dias', 'data_previsao_entrega',
            'assinatura_tipo', 'data_envio_assinatura', 'data_devolucao_assinatura',
            'finalizacao_tipo', 'data_envio_cartorio', 'os_numero_conta_azul', 'os_numero_omie',
            'prazo_pausado_em', 'prazo_dias_restantes'
        ];

        if (isset($data['status_processo'])) {
            // A data_conversao marca quando um orçamento vira serviço ou quando o serviço é criado diretamente.
            // Para clientes mensalistas a criação pode ocorrer sem pagamento de entrada, mantendo data_pagamento nula.
            $currentProcess = $this->getProcessStatusDataConversao((int) $id);
            $currentStatus = $this->normalizeStatus($currentProcess['status_processo'] ?? '');
            $newStatus = $this->normalizeStatus((string) $data['status_processo']);
            $hasConversionDate = !empty($currentProcess['data_conversao']);
            $hasOmieServiceOrder = !empty($currentProcess['os_numero_omie']);

            $budgetStatuses = [
                'orçamento',
                'orçamento pendente',
                'orçamento em negociação',
                'orçamento aguardando cliente',
            ];
            $conversionStatuses = [
                'serviço pendente',
                'serviço em andamento',
                'pago - a enviar',
                'pendente de pagamento',
                'pendente de documentos',
                'concluído',
                'finalizado',
            ];

            $shouldSetConversionDate = in_array($newStatus, $conversionStatuses, true)
                && !$hasConversionDate
                && !$hasOmieServiceOrder
                && (in_array($currentStatus, $budgetStatuses, true) || $currentStatus === '');

            if ($shouldSetConversionDate) {
                $data['data_conversao'] = date('Y-m-d');
                $allowed_fields[] = 'data_conversao';
            }
        }

        // Adiciona a data de finalização apenas se o status for 'Concluído'
        if (isset($data['status_processo']) && in_array($data['status_processo'], ['Concluído', 'Finalizado'], true)) {
            $data['data_finalizacao_real'] = date('Y-m-d H:i:s');
            $allowed_fields[] = 'data_finalizacao_real'; // Adiciona à lista de permissões
        }

        $fieldsToUpdate = [];
        $params = ['id' => $id];

        // Monta a query dinamicamente, usando apenas os campos que foram enviados pelo controller.
        $shouldUpdateDeadline = false;
        $translationDeadlineProvided = false;

        foreach ($allowed_fields as $field) {
            if (array_key_exists($field, $data)) {
                $value = ($data[$field] === '') ? null : $data[$field];

                if (in_array($field, ['prazo_dias', 'traducao_prazo_dias'], true)) {
                    // Prazo igual a zero remove a deadline e deve ser persistido como NULL.
                    $value = $this->normalizePrazoDias($value);
                } elseif ($field === 'prazo_dias_restantes' && $value !== null) {
                    $value = (int) $value;
                }

                $fieldsToUpdate[] = "`{$field}` = :{$field}";
                $params[$field] = $value;

                if (in_array($field, ['prazo_dias', 'traducao_prazo_dias'], true)) {
                    $shouldUpdateDeadline = true;

                    if ($field === 'traducao_prazo_dias') {
                        $translationDeadlineProvided = true;
                    }
                }
            }
        }
        if ($translationDeadlineProvided) {
            $translationValue = $params['traducao_prazo_dias'] ?? null;

            if (!in_array('`prazo_dias` = :prazo_dias', $fieldsToUpdate, true)) {
                $fieldsToUpdate[] = "`prazo_dias` = :prazo_dias";
            }

            $params['prazo_dias'] = $translationValue;
        }

        if ($shouldUpdateDeadline && !array_key_exists('data_previsao_entrega', $data)) {
            if (!array_key_exists('data_inicio_traducao', $params)) {
                $params['data_inicio_traducao'] = null;
            }

            $fieldsToUpdate[] = "data_previsao_entrega = CASE\n                WHEN :prazo_dias IS NULL THEN NULL\n                WHEN COALESCE(:data_inicio_traducao, data_inicio_traducao) IS NOT NULL THEN DATE_ADD(COALESCE(:data_inicio_traducao, data_inicio_traducao), INTERVAL :prazo_dias DAY)\n                ELSE DATE_ADD(data_criacao, INTERVAL :prazo_dias DAY)\n            END";
        }

        // Se, por algum motivo, nenhum campo válido foi enviado, interrompe a execução.
        if (empty($fieldsToUpdate)) {
            return false;
        }

        $sql = "UPDATE processos SET " . implode(', ', $fieldsToUpdate) . " WHERE id = :id";
        
        try {
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute($params);
        } catch (PDOException $e) {
            error_log("Erro ao atualizar etapas do processo {$id}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Atualiza colunas específicas de um processo (usado para status, motivo, etc).
     * Combina a lógica de atualização genérica com regras específicas de status.
     * @param int $id O ID do processo.
     * @param array $data Um array associativo com as colunas e valores.
     * @return bool
     */
    public function updateStatus(int $id, array $data): bool
    {
        // LÓGICA DA VERSÃO ANTIGA: Adiciona a data de finalização automaticamente.
        if (isset($data['status_processo']) && in_array($data['status_processo'], ['Concluído', 'Finalizado'], true)) {
            $data['data_finalizacao_real'] = date('Y-m-d H:i:s');
        }

        if (isset($data['status_processo'])) {
            $currentProcess = $this->getProcessStatusDataConversao($id);
            $currentStatus = $this->normalizeStatus($currentProcess['status_processo'] ?? '');
            $newStatus = $this->normalizeStatus((string) $data['status_processo']);
            $hasConversionDate = !empty($currentProcess['data_conversao']);
            $hasOmieServiceOrder = !empty($currentProcess['os_numero_omie']);

            $budgetStatuses = ['orçamento', 'orçamento pendente'];
            $conversionStatuses = ['serviço em andamento', 'concluído', 'finalizado'];

            if (in_array($newStatus, $conversionStatuses, true)
                && !$hasConversionDate
                && !$hasOmieServiceOrder
                && (in_array($currentStatus, $budgetStatuses, true) || $currentStatus === '')) {
                $data['data_conversao'] = date('Y-m-d');
            }
        }

        // LÓGICA DA NOVA VERSÃO: Constrói a query de forma flexível.
        $set_parts = [];
        foreach (array_keys($data) as $key) {
            $set_parts[] = "`{$key}` = ?";
        }
        $set_clause = implode(', ', $set_parts);

        if (empty($set_clause)) {
            return false; // Nada para atualizar
        }

        $sql = "UPDATE processos SET {$set_clause} WHERE id = ?";
        
        $values = array_values($data);
        $values[] = $id;

        try {
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute($values);
        } catch (PDOException $e) {
            error_log("Erro ao atualizar status do processo #{$id}: " . $e->getMessage());
            return false;
        }
    }

    // =======================================================================
    // GESTÃO DE DADOS RELACIONADOS (COMENTÁRIOS, DOCUMENTOS)
    // =======================================================================
    
    /**
     * Busca todos os documentos de um processo específico.
     * @param int $processo_id ID do processo.
     * @return array
     */
    public function getDocumentosByProcessoId($processo_id)
    {
        $stmt = $this->pdo->prepare("SELECT * FROM documentos WHERE processo_id = ?");
        $stmt->execute([$processo_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Busca todos os comentários de um processo, com o nome do autor.
     * @param int $processo_id ID do processo.
     * @return array
     */
    public function getComentariosByProcessoId($processo_id)
    {
        $sql = "SELECT c.*, u.nome_completo
                FROM comentarios c
                JOIN users u ON c.user_id = u.id
                WHERE c.processo_id = ?
                ORDER BY c.data_comentario DESC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$processo_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Adiciona um novo comentário a um processo.
     * @param array $data Dados do comentário (processo_id, user_id, comentario).
     * @return bool
     */
    public function addComentario($data)
    {
        $sql = "INSERT INTO comentarios (processo_id, user_id, comentario) VALUES (:processo_id, :user_id, :comentario)";
        try {
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute([
                'processo_id' => $data['processo_id'],
                'user_id'     => $data['user_id'],
                'comentario'  => $data['comentario']
            ]);
        } catch (PDOException $e) {
            error_log("Erro ao adicionar comentário: " . $e->getMessage());
            return false;
        }
    }

    
    // =======================================================================
    // MÉTODOS DE APOIO / HELPERS
    // =======================================================================

    /**
     * Retorna todos os vendedores ativos para uso em selects.
     * @return array
     */
    public function getAllVendedores(): array
    {
        $sql = "SELECT v.id, COALESCE(u.nome_completo, 'Sistema') AS nome_vendedor
                FROM vendedores v
                JOIN users u ON v.user_id = u.id
                WHERE u.ativo = 1
                ORDER BY u.nome_completo";
        try {
            $stmt = $this->pdo->query($sql);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erro ao buscar todos os vendedores: " . $e->getMessage());
            return [];
        }
    }

    public function getAllSdrs(): array
    {
        $sql = "SELECT id, nome_completo FROM users WHERE perfil = 'sdr' AND ativo = 1 ORDER BY nome_completo";

        try {
            $stmt = $this->pdo->query($sql);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Erro ao buscar SDRs: ' . $e->getMessage());
            return [];
        }
    }

    public function getVendedorIdByUserId(int $userId): ?int
    {
        try {
            $stmt = $this->pdo->prepare('SELECT id FROM vendedores WHERE user_id = :user_id LIMIT 1');
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->execute();

            $result = $stmt->fetchColumn();
            return $result !== false ? (int) $result : null;
        } catch (PDOException $e) {
            error_log('Erro ao localizar vendedor pelo usuário: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Retorna todas as formas de pagamento para uso em selects.
     * @return array
     */
    public function getAllFormasPagamento(): array
    {
        try {
            $stmt = $this->pdo->query("SELECT id, nome FROM formas_pagamento ORDER BY nome ASC");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erro ao buscar formas de pagamento: " . $e->getMessage());
            return [];
        }
    }
    
    
        
    /**
     * Busca todos os processos de um cliente específico.
     *
     * @param int $clienteId O ID do cliente.
     * @return array A lista de processos pertencentes ao cliente.
     */
    public function getProcessosByClienteId($clienteId) {
        $sql = "SELECT id, titulo, status_processo, data_criacao, data_previsao_entrega 
                FROM processos 
                WHERE cliente_id = ? 
                ORDER BY data_criacao DESC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$clienteId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Retorna todos os clientes para uso em selects.
     * @return array
     */
    public function getAllClientes(): array
    {
        // Presume-se que a tabela de clientes se chama 'clientes' e as colunas são 'id' e 'nome_cliente'
        $sql = "SELECT id, nome_cliente AS nome FROM clientes WHERE is_prospect = 0 ORDER BY nome_cliente ASC";
        try {
            $stmt = $this->pdo->query($sql);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erro ao buscar todos os clientes: " . $e->getMessage());
            return [];
        }
    }
    
    // =======================================================================
    // MÉTODOS PRIVADOS
    // =======================================================================

    /**
     * Normaliza os dados de documentos enviados via formulário para inserção no banco.
     * Aceita tanto o formato antigo (agrupado por categoria) quanto o formato atual (lista plana).
     *
     * @param array $data Dados do formulário.
     * @return array Lista de documentos prontos para inserção.
     */
    private function normalizeDocumentsForInsert(array $data): array
    {
        $documents = [];

        if (isset($data['documentos']) && is_array($data['documentos'])) {
            foreach ($data['documentos'] as $category => $docs) {
                if (!is_array($docs)) {
                    continue;
                }

                foreach ($docs as $doc) {
                    if (!is_array($doc)) {
                        continue;
                    }

                    $documents[] = [
                        'categoria' => $doc['categoria'] ?? $category,
                        'tipo_documento' => $doc['tipo_documento'] ?? null,
                        'nome_documento' => $doc['nome_documento'] ?? null,
                        'quantidade' => $doc['quantidade'] ?? 1,
                        'valor_unitario' => $doc['valor_unitario'] ?? null,
                    ];
                }
            }
        }

        if (isset($data['docs']) && is_array($data['docs'])) {
            foreach ($data['docs'] as $doc) {
                if (!is_array($doc)) {
                    continue;
                }

                $documents[] = [
                    'categoria' => $doc['categoria'] ?? 'Outros',
                    'tipo_documento' => $doc['tipo_documento'] ?? null,
                    'nome_documento' => $doc['nome_documento'] ?? null,
                    'quantidade' => $doc['quantidade'] ?? 1,
                    'valor_unitario' => $doc['valor_unitario'] ?? null,
                ];
            }
        }

        $normalized = [];
        foreach ($documents as $doc) {
            $type = trim((string)($doc['tipo_documento'] ?? ''));
            if ($type === '') {
                continue;
            }

            $rawValue = $doc['valor_unitario'] ?? null;
            if (empty($rawValue)) {
                continue;
            }

            $parsedValue = $this->parseCurrency($rawValue);
            if ($parsedValue === null) {
                continue;
            }

            $quantity = (int)($doc['quantidade'] ?? 1);
            if ($quantity <= 0) {
                $quantity = 1;
            }

            $name = isset($doc['nome_documento']) ? trim((string)$doc['nome_documento']) : null;
            $name = $name === '' ? null : $name;

            $category = isset($doc['categoria']) ? trim((string)$doc['categoria']) : '';
            if ($category === '') {
                $category = 'Outros';
            }

            if (strcasecmp($category, 'Outros') === 0) {
                if ($name === null) {
                    continue;
                }

                if ((float)$parsedValue <= 0) {
                    continue;
                }
            }

            $normalized[] = [
                'categoria' => $category,
                'tipo_documento' => $type,
                'nome_documento' => $name,
                'quantidade' => $quantity,
                'valor_unitario' => $parsedValue,
            ];
        }

        return $normalized;
    }

    /**
     * Insere uma lista de documentos na tabela vinculada ao processo informado.
     *
     * @param int $processoId ID do processo.
     * @param array $documents Documentos normalizados.
     * @return void
     */
    private function insertProcessDocuments(int $processoId, array $documents): void
    {
        if (empty($documents)) {
            return;
        }

        $sql = "INSERT INTO documentos (processo_id, categoria, tipo_documento, nome_documento, quantidade, valor_unitario) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $this->pdo->prepare($sql);

        foreach ($documents as $doc) {
            $stmt->execute([
                $processoId,
                $doc['categoria'],
                $doc['tipo_documento'],
                $doc['nome_documento'],
                $doc['quantidade'],
                $doc['valor_unitario'],
            ]);
        }
    }

    /**
     * Realiza o upload de um arquivo de comprovante.
     * @param array|null $file O arquivo de $_FILES.
     * @return string|null O caminho do arquivo salvo ou null em caso de falha.
     */
    private function uploadComprovante($file)
    {
        if (isset($file) && $file['error'] === UPLOAD_ERR_OK) {
            $uploadDir = __DIR__ . '/../../uploads/comprovantes/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            $fileName = time() . '_' . uniqid() . '_' . basename($file['name']);
            $targetPath = $uploadDir . $fileName;
            if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                return 'uploads/comprovantes/' . $fileName;
            }
        }
        return null;
    }
    
    
    
    /**
     * Busca os processos mais recentes associados a um vendedor específico pelo seu user_id.
     * @param int $userId O ID do usuário (do vendedor).
     * @param int $limit O número de processos a serem retornados.
     * @return array Lista de processos do vendedor.
     */
    public function getProcessosRecentesPorVendedor(int $userId, int $limit): array
    {
        // A consulta SQL une as tabelas de processos e vendedores
        // para filtrar pelo user_id do vendedor.
        $sql = "SELECT
                    p.id,
                    p.titulo,
                    p.status_processo,
                    c.nome_cliente,
                    p.data_criacao
                FROM
                    processos AS p
                JOIN
                    clientes AS c ON p.cliente_id = c.id
                JOIN
                    vendedores AS v ON p.vendedor_id = v.id
                WHERE
                    v.user_id = ?
                ORDER BY
                    p.data_criacao DESC
                LIMIT ?";
    
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            // Tratar erro na preparação da consulta
            return [];
        }
    
        $stmt->bind_param("ii", $userId, $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_all(MYSQLI_ASSOC);
    }
    /**
     * Calcula a soma do valor total dos processos de um vendedor no mês corrente.
     * Considera apenas processos que não estão nos status 'Orçamento' ou 'Cancelado'.
     *
     * @param int $vendedorId O ID do vendedor (da tabela 'vendedores').
     * @return float O valor total das vendas do mês.
     */
    public function getVendasTotalMesByVendedor(int $vendedorId): float
    {
        $sql = "SELECT SUM(p.valor_total) as total_vendas_mes
                FROM processos p
                WHERE p.vendedor_id = :vendedor_id
                  AND p.status_processo NOT IN ('Orçamento', 'Orçamento Pendente', 'Cancelado', 'Recusado')
                  AND p.data_conversao IS NOT NULL
                  AND MONTH(p.data_conversao) = MONTH(CURDATE())
                  AND YEAR(p.data_conversao) = YEAR(CURDATE())";

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':vendedor_id' => $vendedorId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            // Retorna o valor somado, ou 0.0 se for nulo.
            return (float) ($result['total_vendas_mes'] ?? 0.0);

        } catch (PDOException $e) {
            error_log("Erro ao buscar total de vendas do mês para o vendedor {$vendedorId}: " . $e->getMessage());
            return 0.0;
        }
    }

    public function getSalesByFilter($filters)
    {
        $statusFinanceiroSelect = $this->getStatusFinanceiroSelectExpression();
        $dateField = 'p.data_criacao';
        $serviceStatuses = [
            'Serviço Pendente',
            'Serviço pendente',
            'Serviço em Andamento',
            'Serviço em andamento',
            'Pago - A Enviar',
            'Pendente de Pagamento',
            'Pendente de pagamento',
            'Pendente de Documentos',
            'Pendente de documentos',
            'Concluído',
            'Finalizado',
        ];

        $filters = array_merge([
            'data_inicio' => null,
            'data_fim' => null,
            'vendedor_id' => null,
            'sdr_id' => null,
        ], $filters);

        $sql = "SELECT
                    p.id,
                    p.titulo,
                    p.data_criacao,
                    {$dateField} AS data_filtro,
                    {$dateField} AS data_conversao,
                    {$dateField} AS data_pagamento,
                    p.valor_total,
                    p.status_processo,
                    {$statusFinanceiroSelect},
                    COALESCE(u.nome_completo, 'Sistema') AS nome_vendedor,
                    u.id AS vendedor_user_id,
                    {$this->getSdrIdSelectExpression()} AS sdr_id,
                    c.nome_cliente,
                    (SELECT COALESCE(SUM(d.quantidade), 0) FROM documentos d WHERE d.processo_id = p.id) AS total_documentos
                FROM processos p
                LEFT JOIN vendedores v ON p.vendedor_id = v.id
                LEFT JOIN users u ON v.user_id = u.id
                JOIN clientes c ON p.cliente_id = c.id
                WHERE p.valor_total > 0
                  AND p.status_processo IN ('" . implode("','", $serviceStatuses) . "')";

        $params = [];

        if (!empty($filters['vendedor_id'])) {
            $sql .= " AND p.vendedor_id = :vendedor_id";
            $params[':vendedor_id'] = $filters['vendedor_id'];
        }

        if (!empty($filters['sdr_id'])) {
            $sql .= " AND {$this->getSdrIdSelectExpression()} = :sdr_id";
            $params[':sdr_id'] = $filters['sdr_id'];
        }

        if (!empty($filters['data_inicio'])) {
            $sql .= " AND DATE({$dateField}) >= :data_inicio";
            $params[':data_inicio'] = $filters['data_inicio'];
        }
        if (!empty($filters['data_fim'])) {
            $sql .= " AND DATE({$dateField}) <= :data_fim";
            $params[':data_fim'] = $filters['data_fim'];
        }

        $sql .= " ORDER BY {$dateField} DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getCommissionsByFilter(array $filters): array
    {
        $serviceStatuses = [
            'Serviço Pendente',
            'Serviço pendente',
            'Serviço em Andamento',
            'Serviço em andamento',
            'Pago - A Enviar',
            'Pendente de Pagamento',
            'Pendente de pagamento',
            'Pendente de Documentos',
            'Pendente de documentos',
            'Concluído',
            'Finalizado'
        ];

        $sdrExpression = $this->getSdrIdSelectExpression();
        $statusFinanceiroSelect = $this->getStatusFinanceiroSelectExpression();
        $dateFieldConversao = 'p.data_conversao';
        $dateFieldFiltro = $dateFieldConversao;
        $dateFieldPagamento = 'COALESCE(p.data_pagamento_1, p.data_pagamento_2, p.data_conversao)';

        $sql = "SELECT
                    p.id,
                    p.orcamento_numero,
                    p.titulo,
                    p.os_numero_omie,
                    p.categorias_servico,
                    p.data_criacao,
                    p.valor_total,
                    p.status_processo,
                    p.data_criacao AS data_entrada,
                    {$dateFieldFiltro} AS data_filtro,
                    {$dateFieldConversao} AS data_conversao,
                    {$dateFieldPagamento} AS data_pagamento,
                    {$statusFinanceiroSelect},
                    COALESCE(u.nome_completo, 'Sistema') AS nome_vendedor,
                    u.id AS vendedor_user_id,
                    COALESCE(v.percentual_comissao, 0) AS percentual_comissao_vendedor,
                    {$sdrExpression} AS sdr_id,
                    c.nome_cliente,
                    c.tipo_assessoria,
                    COALESCE(comm_vendedor.total_comissao_vendedor, 0) AS valor_comissao_vendedor,
                    COALESCE(comm_sdr.total_comissao_sdr, 0) AS valor_comissao_sdr,
                    (SELECT COALESCE(SUM(d.quantidade), 0) FROM documentos d WHERE d.processo_id = p.id) AS total_documentos
                FROM processos p
                JOIN clientes c ON p.cliente_id = c.id
                LEFT JOIN vendedores v ON p.vendedor_id = v.id
                LEFT JOIN users u ON v.user_id = u.id
                LEFT JOIN (
                    SELECT venda_id, SUM(valor_comissao) AS total_comissao_vendedor
                    FROM comissoes
                    WHERE tipo_comissao = 'vendedor'
                    GROUP BY venda_id
                ) comm_vendedor ON comm_vendedor.venda_id = p.id
                LEFT JOIN (
                    SELECT venda_id, SUM(valor_comissao) AS total_comissao_sdr
                    FROM comissoes
                    WHERE tipo_comissao = 'sdr'
                    GROUP BY venda_id
                ) comm_sdr ON comm_sdr.venda_id = p.id
                WHERE p.valor_total > 0
                  AND p.status_processo IN ('" . implode("','", $serviceStatuses) . "')";

        $params = [];

        if (!empty($filters['vendedor_id'])) {
            $sql .= ' AND p.vendedor_id = :vendedor_id';
            $params[':vendedor_id'] = $filters['vendedor_id'];
        }

        if (!empty($filters['status'])) {
            $sql .= ' AND p.status_processo = :status';
            $params[':status'] = $filters['status'];
        }

        if (!empty($filters['cliente_id'])) {
            $sql .= ' AND p.cliente_id = :cliente_id';
            $params[':cliente_id'] = $filters['cliente_id'];
        }

        if (!empty($filters['sdr_id'])) {
            $sql .= " AND {$sdrExpression} = :sdr_id";
            $params[':sdr_id'] = $filters['sdr_id'];
        }

        if (!empty($filters['data_conversao_inicio'])) {
            $sql .= " AND DATE({$dateFieldFiltro}) >= :data_conversao_inicio";
            $params[':data_conversao_inicio'] = $filters['data_conversao_inicio'];
        }

        if (!empty($filters['data_conversao_fim'])) {
            $sql .= " AND DATE({$dateFieldFiltro}) <= :data_conversao_fim";
            $params[':data_conversao_fim'] = $filters['data_conversao_fim'];
        }

        $sql .= " ORDER BY
            CASE
                WHEN p.os_numero_omie IS NULL OR p.os_numero_omie = '' THEN 1
                ELSE 0
            END,
            CAST(p.os_numero_omie AS UNSIGNED) ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $processos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $sdrPercent = $this->getSdrCommissionPercent();

        $totals = [
            'valor_total' => 0.0,
            'comissao_vendedor' => 0.0,
            'comissao_sdr' => 0.0,
        ];

        foreach ($processos as &$processo) {
            $valorTotal = (float) ($processo['valor_total'] ?? 0);
            $vendorPercent = (float) ($processo['percentual_comissao_vendedor'] ?? 0);
            $hasSdr = !empty($processo['sdr_id']) && $sdrPercent > 0;

            if ($hasSdr && isset($processo['vendedor_user_id'])) {
                $hasSdr = (int) $processo['sdr_id'] !== (int) $processo['vendedor_user_id'];
            }

            if ($hasSdr) {
                $adjustedVendorPercent = max(0, $vendorPercent - $sdrPercent);
                $valorComissaoVendedor = round($valorTotal * ($adjustedVendorPercent / 100), 2);
                $valorComissaoSdr = round($valorTotal * ($sdrPercent / 100), 2);

                $processo['percentual_comissao_vendedor'] = $adjustedVendorPercent;
                $processo['percentual_comissao_sdr'] = $sdrPercent;
            } else {
                $vendorFinalPercent = $vendorPercent > 0 ? $vendorPercent : $sdrPercent;
                $valorComissaoVendedor = round($valorTotal * ($vendorFinalPercent / 100), 2);
                $valorComissaoSdr = 0.0;

                $processo['percentual_comissao_vendedor'] = $vendorFinalPercent;
                $processo['percentual_comissao_sdr'] = 0.0;
            }

            $processo['valor_comissao_vendedor'] = $valorComissaoVendedor;
            $processo['valor_comissao_sdr'] = $valorComissaoSdr;

            $totals['valor_total'] += $valorTotal;
            $totals['comissao_vendedor'] += $valorComissaoVendedor;
            $totals['comissao_sdr'] += $valorComissaoSdr;
        }

        unset($processo);

        return [
            'processos' => $processos,
            'totais' => $totals,
        ];
    }

    private function getSdrCommissionPercent(): float
    {
        try {
            $stmt = $this->pdo->prepare("SELECT valor FROM configuracoes_comissao WHERE tipo_regra = 'percentual_sdr' AND ativo = 1 ORDER BY id DESC LIMIT 1");
            $stmt->execute();
            $value = $stmt->fetchColumn();

            return $value !== false && $value !== null ? (float) $value : 0.0;
        } catch (PDOException $exception) {
            error_log('Erro ao buscar percentual de comissão SDR: ' . $exception->getMessage());
            return 0.0;
        }
    }

    private function isSdrUser(int $userId): bool
    {
        try {
            $stmt = $this->pdo->prepare('SELECT perfil FROM users WHERE id = :id LIMIT 1');
            $stmt->execute([':id' => $userId]);
            $perfil = $stmt->fetchColumn();

            if ($perfil === false || $perfil === null) {
                return false;
            }

            return in_array(strtolower((string) $perfil), ['sdr', 'colaborador'], true);
        } catch (PDOException $exception) {
            error_log('Erro ao verificar perfil de SDR para o usuário ' . $userId . ': ' . $exception->getMessage());
            return false;
        }
    }
        
/**
     * Cria um novo processo a partir dos dados de uma prospecção ganha.
     * @param array $data Dados da prospecção (cliente_id, titulo, valor_proposto, vendedor_id).
     * @return int|false Retorna o ID do novo processo ou false em caso de erro.
     */
    public function createFromProspeccao(array $data)
    {
        $manageTransaction = !$this->pdo->inTransaction();

        if ($manageTransaction) {
            $this->pdo->beginTransaction();
        }

        try {
            // Gera o número do orçamento
            $ano = date('y');
            $stmt = $this->pdo->query("SELECT COUNT(*) FROM processos WHERE YEAR(data_criacao) = YEAR(CURDATE())");
            $count = $stmt->fetchColumn() + 1;
            $orcamento_numero = $ano . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);

            $omieKeyPreview = $this->generateNextOmieKey();

            $sqlProcesso = "INSERT INTO processos (
                cliente_id, colaborador_id, vendedor_id, prospeccao_id, titulo, valor_total,
                status_processo, orcamento_numero, data_entrada, os_numero_conta_azul
            ) VALUES (
                :cliente_id, :colaborador_id, :vendedor_id, :prospeccao_id, :titulo, :valor_total,
                :status_processo, :orcamento_numero, :data_entrada, :os_numero_conta_azul
            )";
            $stmtProcesso = $this->pdo->prepare($sqlProcesso);

            $params = [
                'cliente_id'      => $data['cliente_id'],
                'colaborador_id'  => $data['colaborador_id'] ?? $data['vendedor_id'], // O vendedor/SDR que fechou se torna o colaborador inicial
                'vendedor_id'     => $data['vendedor_id'],
                'titulo'          => $data['titulo'] ?? ('Orçamento #' . $orcamento_numero),
                'prospeccao_id'   => $this->sanitizeNullableInt($data['prospeccao_id'] ?? null),
                'valor_total'     => $data['valor_proposto'] ?? 0.00,
                'status_processo' => $data['status_processo'] ?? 'Orçamento',
                'orcamento_numero'=> $orcamento_numero,
                'data_entrada'    => date('Y-m-d'),
                'os_numero_conta_azul' => $omieKeyPreview
            ];

            $stmtProcesso->execute($params);
            $processoId = (int)$this->pdo->lastInsertId();
            $this->updateOmieKeyForProcessId($processoId);

            if ($manageTransaction) {
                $this->pdo->commit();
            }

            return $processoId;

        } catch (Exception $e) {
            if ($manageTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log("Erro ao criar processo a partir da prospecção: " . $e->getMessage());
            if (!$manageTransaction) {
                throw $e;
            }
            return false;
        }
    }

        /**
     * Busca os dados de um anexo do Google Drive pelo seu ID da tabela.
     * @param int $anexoId O ID do anexo na tabela processo_anexos_gdrive.
     * @return mixed Retorna os dados do anexo ou false se não encontrar.
     */
    public function getAnexoGdriveById($anexoId)
    {
        $stmt = $this->pdo->prepare("SELECT * FROM processo_anexos_gdrive WHERE id = ?");
        $stmt->execute([$anexoId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Busca os dados do processo associado a um anexo, incluindo o user_id do cliente.
     * @param int $anexoId O ID do anexo.
     * @return mixed Retorna os dados do processo ou false se não encontrar.
     */
    public function getProcessoDoAnexo($anexoId)
    {
        $sql = "SELECT p.*, c.user_id as cliente_user_id
                FROM processos p
                JOIN clientes c ON p.cliente_id = c.id
                JOIN processo_anexos_gdrive pa ON p.id = pa.processo_id
                WHERE pa.id = ?";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$anexoId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
        public function getServicosVencidos()
    {
        $sql = "SELECT 
                    p.id, p.orcamento_numero, p.data_previsao_entrega, p.status_processo,
                    c.nome_cliente
                FROM processos p
                JOIN clientes c ON p.cliente_id = c.id
                WHERE 
                    p.data_previsao_entrega IS NOT NULL
                    AND p.data_previsao_entrega < CURDATE()
                    AND p.status_processo NOT IN ('Concluído', 'Finalizado', 'Cancelado', 'Arquivado', 'Recusado', 'Pendente de Pagamento', 'Pendente de pagamento', 'Pendente de Documentos', 'Pendente de documentos')
                ORDER BY p.data_previsao_entrega ASC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
        /**
     * Busca todos os processos que correspondem a um status específico.
     * @param string $status O status a ser filtrado.
     * @return array
     */
    public function getByStatus($status)
    {
        // A query foi melhorada para juntar com as tabelas de clientes e usuários
        // e assim já trazer o nome do cliente e do colaborador que criou/editou.
        $sql = "
            SELECT 
                p.*,
                c.nome AS nome_cliente,
                u.nome_completo AS nome_usuario_criador
            FROM 
                processos p
            LEFT JOIN 
                clientes c ON p.cliente_id = c.id
            LEFT JOIN 
                users u ON p.user_id = u.id
            WHERE 
                p.status_processo = :status
            ORDER BY 
                p.data_criacao DESC
        ";

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindParam(':status', $status, PDO::PARAM_STR);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            // Em um ambiente de produção, logar este erro em vez de exibi-lo.
            error_log("Erro ao buscar processos por status: " . $e->getMessage());
            return [];
        }
    }

    public function getRelatorioServicosPorPeriodo($data_inicio, $data_fim) {
        // CORREÇÃO: A consulta agora junta 'processos' com 'documentos' e 'categorias_financeiras'
        // e filtra apenas por status que representam um serviço ativo.
        $serviceTypes = ['Tradução', 'CRC', 'Outros'];
        $placeholders = implode(', ', array_fill(0, count($serviceTypes), '?'));

        $sql = "SELECT
                    cf.servico_tipo,
                    COUNT(doc.id) AS quantidade,
                    SUM(doc.valor_unitario) AS valor_total
                FROM processos p
                JOIN documentos doc ON p.id = doc.processo_id
                JOIN categorias_financeiras cf ON doc.tipo_documento = cf.nome_categoria
                WHERE
                    p.data_criacao BETWEEN ? AND ?
                    AND p.status_processo IN ('Serviço Pendente', 'Serviço pendente', 'Serviço em Andamento', 'Serviço em andamento', 'Pago - A Enviar', 'Pendente de Pagamento', 'Pendente de pagamento', 'Pendente de Documentos', 'Pendente de documentos', 'Concluído', 'Finalizado')
                    AND cf.servico_tipo IN ($placeholders)
                GROUP BY
                    cf.servico_tipo";

        $stmt = $this->pdo->prepare($sql);
        $params = array_merge([$data_inicio, $data_fim], $serviceTypes);
        $stmt->execute($params);

        $resultado = [];
        foreach ($serviceTypes as $serviceType) {
            $resultado[$serviceType] = ['quantidade' => 0, 'valor_total' => 0];
        }
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $resultado[$row['servico_tipo']] = [
                'quantidade' => (int)$row['quantidade'],
                'valor_total' => (float)$row['valor_total']
            ];
        }
        return $resultado;
    }

    /**
     * Salva arquivos enviados para um processo em diretórios estruturados por data.
     */
    public function salvarArquivos(int $processoId, ?array $files, string $categoria, string $storageContext, ?string $month = null, ?string $day = null): array
    {
        $uploadedFiles = $this->normalizeUploadedFiles($files);
        if (empty($uploadedFiles)) {
            return [];
        }

        $month = $month ?? date('m');
        $day = $day ?? date('d');

        $baseDirectory = $this->resolveUploadDirectory($month, $day, $storageContext, $categoria);
        if (!is_dir($baseDirectory) && !mkdir($baseDirectory, 0775, true) && !is_dir($baseDirectory)) {
            throw new RuntimeException('Não foi possível criar o diretório de uploads.');
        }

        $sql = "INSERT INTO processo_anexos (processo_id, categoria, nome_arquivo_sistema, nome_arquivo_original, caminho_arquivo) VALUES (?, ?, ?, ?, ?)";
        $stmt = $this->pdo->prepare($sql);

        $stored = [];
        foreach ($uploadedFiles as $file) {
            if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                continue;
            }

            if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
                continue;
            }

            $originalName = basename($file['name'] ?? 'arquivo');
            $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
            $systemName = uniqid($categoria . '_', true) . ($extension ? '.' . $extension : '');
            $destination = $baseDirectory . $systemName;

            if (!move_uploaded_file($file['tmp_name'], $destination)) {
                continue;
            }

            $relativePath = $this->buildRelativePath($month, $day, $storageContext, $categoria, $systemName);
            $stmt->execute([$processoId, $categoria, $systemName, $originalName, $relativePath]);

            $stored[] = [
                'processo_id' => $processoId,
                'categoria' => $categoria,
                'nome_arquivo_sistema' => $systemName,
                'nome_arquivo_original' => $originalName,
                'caminho_arquivo' => $relativePath,
            ];
        }

        return $stored;
    }

    private function normalizeUploadedFiles(?array $files): array
    {
        if (empty($files) || !isset($files['name'])) {
            return [];
        }

        $normalized = [];

        if (is_array($files['name'])) {
            foreach ($files['name'] as $index => $name) {
                $normalized[] = [
                    'name' => $name,
                    'type' => $files['type'][$index] ?? null,
                    'tmp_name' => $files['tmp_name'][$index] ?? null,
                    'error' => $files['error'][$index] ?? UPLOAD_ERR_NO_FILE,
                    'size' => $files['size'][$index] ?? 0,
                ];
            }
            return $normalized;
        }

        return [$files];
    }

    private function resolveUploadDirectory(string $month, string $day, string $storageContext, string $categoria): string
    {
        $normalizedCategoria = trim($categoria, '/');
        $normalizedContext = trim($storageContext, '/');

        $segments = [
            __DIR__ . '/../../uploads/',
            sprintf('%02d', (int)$month) . '/',
            sprintf('%02d', (int)$day) . '/',
            $normalizedCategoria . '/',
            $normalizedContext !== '' ? $normalizedContext . '/' : '',
        ];

        return implode('', $segments);
    }

    private function buildRelativePath(string $month, string $day, string $storageContext, string $categoria, string $filename): string
    {
        $normalizedCategoria = trim($categoria, '/');
        $normalizedContext = trim($storageContext, '/');

        $contextSegment = $normalizedContext !== '' ? $normalizedContext . '/' : '';

        return sprintf(
            'uploads/%02d/%02d/%s/%s%s',
            (int)$month,
            (int)$day,
            $normalizedCategoria,
            $contextSegment,
            $filename
        );
    }

    public function determineStorageContextKey(int $processoId, ?string $status = null, ?string $orcamentoNumero = null): string
    {
        if ($status === null || $orcamentoNumero === null) {
            $stmt = $this->pdo->prepare('SELECT status_processo, orcamento_numero FROM processos WHERE id = ?');
            $stmt->execute([$processoId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $status = $status ?? $row['status_processo'];
                $orcamentoNumero = $orcamentoNumero ?? $row['orcamento_numero'];
            }
        }

        $status = is_string($status) ? mb_strtolower($status) : '';
        if ($status === 'orçamento' || $status === 'orcamento') {
            $safeNumber = preg_replace('/[^A-Za-z0-9_-]/', '', (string)$orcamentoNumero);
            return $safeNumber ? 'orcamento-' . $safeNumber : 'orcamento';
        }

        return 'processo-' . $processoId;
    }

    private function replicarAnexosDeCategoria(int $processoId, string $categoriaOrigem, string $categoriaDestino, string $storageContext): void
    {
        $anexosOrigem = $this->getAnexosPorCategoria($processoId, $categoriaOrigem);
        if (empty($anexosOrigem)) {
            return;
        }

        $this->deleteAnexosByCategoria($processoId, $categoriaDestino);

        $sql = "INSERT INTO processo_anexos (processo_id, categoria, nome_arquivo_sistema, nome_arquivo_original, caminho_arquivo) VALUES (?, ?, ?, ?, ?)";
        $stmt = $this->pdo->prepare($sql);

        foreach ($anexosOrigem as $anexo) {
            $sourcePath = __DIR__ . '/../../' . ltrim($anexo['caminho_arquivo'], '/');
            if (!is_file($sourcePath)) {
                continue;
            }

            [$month, $day] = $this->extractDateSegmentsFromPath($anexo['caminho_arquivo']);
            $month = $month ?? date('m');
            $day = $day ?? date('d');

            $destinationDir = $this->resolveUploadDirectory($month, $day, $storageContext, $categoriaDestino);
            if (!is_dir($destinationDir) && !mkdir($destinationDir, 0775, true) && !is_dir($destinationDir)) {
                continue;
            }

            $extension = strtolower(pathinfo($anexo['nome_arquivo_sistema'], PATHINFO_EXTENSION));
            $systemName = uniqid($categoriaDestino . '_', true) . ($extension ? '.' . $extension : '');
            $destination = $destinationDir . $systemName;

            if (!copy($sourcePath, $destination)) {
                continue;
            }

            $relativePath = $this->buildRelativePath($month, $day, $storageContext, $categoriaDestino, $systemName);
            $stmt->execute([
                $processoId,
                $categoriaDestino,
                $systemName,
                $anexo['nome_arquivo_original'],
                $relativePath,
            ]);
        }
    }

    private function deleteAnexosByCategoria(int $processoId, string $categoria): void
    {
        $stmt = $this->pdo->prepare('SELECT id, caminho_arquivo FROM processo_anexos WHERE processo_id = ? AND categoria = ?');
        $stmt->execute([$processoId, $categoria]);
        $anexos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($anexos)) {
            return;
        }

        $deleteStmt = $this->pdo->prepare('DELETE FROM processo_anexos WHERE id = ?');
        foreach ($anexos as $anexo) {
            $filePath = __DIR__ . '/../../' . ltrim($anexo['caminho_arquivo'], '/');
            if (is_file($filePath)) {
                @unlink($filePath);
            }
            $deleteStmt->execute([$anexo['id']]);
        }
    }

    private function extractDateSegmentsFromPath(string $relativePath): array
    {
        $pattern = '#uploads/(\d{2})/(\d{2})/#';
        if (preg_match($pattern, $relativePath, $matches)) {
            return [$matches[1], $matches[2]];
        }

        return [null, null];
    }

    /** Busca os anexos de um processo por categoria.  */
    public function getAnexosPorCategoria($processoId, $categoria = 'anexo') {
        $categorias = is_array($categoria) ? $categoria : [$categoria];

        $placeholders = implode(',', array_fill(0, count($categorias), '?'));
        $params = array_merge([$processoId], $categorias);

        $sql = "SELECT * FROM processo_anexos WHERE processo_id = ? AND categoria IN ($placeholders) ORDER BY nome_arquivo_original";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $anexos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($anexos) && in_array('traducao', $categorias, true) && !in_array('anexo', $categorias, true)) {
            return $this->getAnexosPorCategoria($processoId, ['anexo']);
        }

        return $anexos;
    }

    /** Exclui um anexo (agora funciona para qualquer categoria).*/
    public function deleteAnexo($anexoId) {
        $stmt = $this->pdo->prepare("SELECT * FROM processo_anexos WHERE id = ?");
        $stmt->execute([$anexoId]);
        $anexo = $stmt->fetch();

        if ($anexo) {
            $filePath = __DIR__ . '/../../' . $anexo['caminho_arquivo'];
            if (file_exists($filePath)) {
                unlink($filePath);
            }
            $deleteStmt = $this->pdo->prepare("DELETE FROM processo_anexos WHERE id = ?");
            return $deleteStmt->execute([$anexoId]);
        }
        return false;
    }

/**
     * Busca processos que correspondem a uma lista de status.
     *
     * @param array $statuList A lista de status para filtrar.
     * @return array
     */
    public function getByMultipleStatus(array $statusList)
        {
            // Se a lista de status estiver vazia, não há nada a fazer.
            if (empty($statusList)) {
                return [];
            }

            // Cria os placeholders para a cláusula IN (?, ?, ?)
            $placeholders = implode(',', array_fill(0, count($statusList), '?'));

            $sql = "
                SELECT 
                    p.*,
                    p.categorias_servico AS tipo_servico, -- ALIAS ADICIONADO AQUI
                    p.status_processo AS status,         -- ALIAS ADICIONADO AQUI
                    c.nome_cliente,
                    u_criador.nome_completo AS nome_usuario_criador,
                    COALESCE(u_vendedor.nome_completo, 'Sistema') AS nome_vendedor
                FROM 
                    processos p
                LEFT JOIN 
                    clientes c ON p.cliente_id = c.id
                LEFT JOIN 
                    users u_criador ON p.colaborador_id = u_criador.id
                LEFT JOIN
                    vendedores v ON p.vendedor_id = v.id
                LEFT JOIN 
                    users u_vendedor ON v.user_id = u_vendedor.id
                WHERE 
                    p.status_processo IN ({$placeholders})
                ORDER BY 
                    p.data_criacao DESC
            ";

            try {
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute($statusList);
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (PDOException $e) {
                error_log("Erro ao buscar processos por múltiplos status: " . $e->getMessage());
                return [];
            }
        }

    /**
     * Persiste o código de integração do pedido da Omie vinculado ao processo local.
     *
     * @param int $processoId Identificador do processo local.
     * @param string $codigo Código de integração gerado para o pedido.
     * @return bool
     */
    public function salvarCodigoPedidoIntegracao(int $processoId, string $codigo): bool
    {
        $sql = "UPDATE processos SET codigo_pedido_integracao = ? WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$codigo, $processoId]);
    }

    /**
     * Salva o número da Ordem de Serviço retornado pela Omie.
     * @param int $processoId O ID do processo local.
     * @param string $osNumero O número da OS da Omie.
     * @return bool
     */
    public function salvarNumeroOsOmie(int $processoId, string $osNumero, ?int $codigoOsOmie = null): bool
    {
        $this->loadProcessColumns();
        $hasCodigoOsColumn = $this->hasProcessColumn('codigo_os_omie');
        $dataConversao = date('Y-m-d');
        $setParts = [
            'os_numero_omie = ?',
            'data_conversao = COALESCE(data_conversao, ?)',
        ];

        $params = [$osNumero, $dataConversao];

        if ($hasCodigoOsColumn) {
            $setParts[] = 'codigo_os_omie = ?';
            $params[] = $codigoOsOmie;
        }

        $params[] = $processoId;

        $sql = 'UPDATE processos SET ' . implode(', ', $setParts) . ' WHERE id = ?';
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }

    public function salvarCodigoOsOmie(int $processoId, ?int $codigoOsOmie): bool
    {
        $this->loadProcessColumns();

        if (!$this->hasProcessColumn('codigo_os_omie')) {
            return false;
        }

        $stmt = $this->pdo->prepare('UPDATE processos SET codigo_os_omie = ? WHERE id = ?');
        return $stmt->execute([$codigoOsOmie, $processoId]);
    }

    public function getServicesSummary(?string $startDate = null, ?string $endDate = null): array
    {
        $this->loadProcessColumns();

        $dateColumn = $this->hasProcessColumn('data_criacao') ? 'p.data_criacao' : 'p.data_entrada';
        $conditions = [];
        $params = [];

        if (!empty($startDate)) {
            $conditions[] = "{$dateColumn} >= :startDate";
            $params[':startDate'] = $startDate . ' 00:00:00';
        }

        if (!empty($endDate)) {
            $conditions[] = "{$dateColumn} <= :endDate";
            $params[':endDate'] = $endDate . ' 23:59:59';
        }

        $whereSql = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
        $hasTypeColumn = $this->hasProcessColumn('tipo_servico');

        $sql = "SELECT
                    " . ($hasTypeColumn ? 'COALESCE(p.tipo_servico, \"Sem Tipo\") AS tipo_servico,' : "'Geral' AS tipo_servico,") . "
                    COUNT(*) AS totalIniciados,
                    SUM(CASE WHEN p.status_processo IN ('Concluído', 'Finalizado') THEN 1 ELSE 0 END) AS totalFinalizados,
                    SUM(CASE WHEN p.status_processo NOT IN ('Concluído', 'Finalizado', 'Cancelado', 'Recusado') THEN 1 ELSE 0 END) AS totalPendentes,
                    SUM(CASE WHEN p.status_processo IN ('Concluído', 'Finalizado') THEN COALESCE(p.valor_total, 0) ELSE 0 END) AS valorFinalizado
                FROM processos p
                {$whereSql}
                GROUP BY " . ($hasTypeColumn ? 'COALESCE(p.tipo_servico, \"Sem Tipo\")' : "'Geral'") . "
                ORDER BY tipo_servico ASC";

        $stmt = $this->pdo->prepare($sql);

        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, PDO::PARAM_STR);
        }

        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $totals = [
            'totalIniciados' => 0,
            'totalFinalizados' => 0,
            'totalPendentes' => 0,
            'valorFinalizado' => 0.0,
            'detalhes' => [],
        ];

        foreach ($rows as $row) {
            $totals['totalIniciados'] += (int)$row['totalIniciados'];
            $totals['totalFinalizados'] += (int)$row['totalFinalizados'];
            $totals['totalPendentes'] += (int)$row['totalPendentes'];
            $totals['valorFinalizado'] += (float)$row['valorFinalizado'];

            $totals['detalhes'][] = [
                'tipo' => $row['tipo_servico'],
                'totalIniciados' => (int)$row['totalIniciados'],
                'totalFinalizados' => (int)$row['totalFinalizados'],
                'totalPendentes' => (int)$row['totalPendentes'],
                'valorFinalizado' => (float)$row['valorFinalizado'],
            ];
        }

        return $totals;
    }

    /**
     * Limpa o número da Ordem de Serviço da Omie, geralmente após um cancelamento.
     * @param int $processoId O ID do processo local.
     * @return bool
     */
    public function limparNumeroOsOmie(int $processoId): bool
    {
        $this->loadProcessColumns();
        $setParts = ['os_numero_omie = NULL'];
        if ($this->hasProcessColumn('codigo_os_omie')) {
            $setParts[] = 'codigo_os_omie = NULL';
        }

        $sql = 'UPDATE processos SET ' . implode(', ', $setParts) . ' WHERE id = ?';
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$processoId]);
    }

    /**
     * Calcula a próxima chave curta utilizada na integração com a Omie.
     * A sequência acompanha o ID do processo e inicia em 000010.
     */
    public function generateNextOmieKey(): string
    {
        $nextProcessId = $this->fetchNextProcessId();

        return $this->formatShortOmieKey(
            $this->calculateOmieSequenceFromProcessId($nextProcessId)
        );
    }

    /**
     * Persiste a chave curta definitiva da Omie utilizando o ID real do processo.
     */
    private function updateOmieKeyForProcessId(int $processoId): string
    {
        $sequenceValue = $this->calculateOmieSequenceFromProcessId($processoId);
        $key = $this->formatShortOmieKey($sequenceValue);

        $stmt = $this->pdo->prepare("UPDATE processos SET os_numero_conta_azul = ? WHERE id = ?");
        $stmt->execute([$key, $processoId]);

        return $key;
    }

    /**
     * Converte o ID do processo para a sequência desejada pela Omie.
     */
    private function calculateOmieSequenceFromProcessId(int $processoId): int
    {
        $normalizedId = max(1, $processoId);
        return $normalizedId + 9;
    }

    private function fetchNextProcessId(): int
    {
        try {
            $stmt = $this->pdo->query("SHOW TABLE STATUS LIKE 'processos'");
            $tableStatus = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($tableStatus && isset($tableStatus['Auto_increment'])) {
                $nextId = (int)$tableStatus['Auto_increment'];
                if ($nextId > 0) {
                    return $nextId;
                }
            }
        } catch (PDOException $exception) {
            error_log('Erro ao consultar próximo ID de processo (SHOW TABLE STATUS): ' . $exception->getMessage());
        }

        try {
            $stmt = $this->pdo->query('SELECT MAX(id) FROM processos');
            $maxId = (int)$stmt->fetchColumn();
            $nextId = $maxId + 1;
            return $nextId > 0 ? $nextId : 1;
        } catch (PDOException $exception) {
            error_log('Erro ao consultar próximo ID de processo (MAX(id)): ' . $exception->getMessage());
        }

        return 1;
    }

    private function formatShortOmieKey(int $value): string
    {
        return str_pad((string)$value, 6, '0', STR_PAD_LEFT);
    }

    private function sanitizeNullableString($value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string)$value);
        return $trimmed === '' ? null : $trimmed;
    }

    private function sanitizeNullableInt($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return (int)$value;
        }

        $digits = preg_replace('/\D+/', '', (string)$value);
        return $digits === '' ? null : (int)$digits;
    }

    public function getVendorPerformanceRanking(?string $startDate = null, ?string $endDate = null): array
    {
        $conditions = ["p.status_processo IN ('Concluído', 'Serviço em andamento', 'Serviço em Andamento', 'Pago - A Enviar', 'Pendente de Pagamento', 'Pendente de pagamento')"];
        $params = [];

        if (!empty($startDate)) {
            $conditions[] = 'p.data_criacao >= :startDate';
            $params[':startDate'] = $startDate . ' 00:00:00';
        }

        if (!empty($endDate)) {
            $conditions[] = 'p.data_criacao <= :endDate';
            $params[':endDate'] = $endDate . ' 23:59:59';
        }

        $whereSql = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

        $sql = "SELECT
                    u.nome_completo AS vendedor,
                    COUNT(p.id) AS qtd_vendas,
                    SUM(p.valor_total) AS total_vendido,
                    AVG(p.valor_total) AS ticket_medio
                FROM processos p
                JOIN users u ON p.vendedor_id = u.id
                $whereSql
                GROUP BY p.vendedor_id, u.nome_completo
                ORDER BY total_vendido DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
