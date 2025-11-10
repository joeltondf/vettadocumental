# Prompt para Codex: Melhorar Importação de Leads CSV com DDI/DDD

## 🎯 Objetivo

Refatorar a importação de leads por CSV para processar telefones corretamente, separando DDI, DDD e número, usando as funções existentes em `PhoneUtils.php` e garantindo consistência com o resto do sistema.

---

## 📝 Contexto

**Arquivos Envolvidos:**
- `crm/clientes/importar_processar.php` - Processa CSV e detecta duplicatas
- `crm/clientes/importar_confirmar.php` - Insere leads no banco
- `crm/clientes/importar_revisao.php` - Tela de revisão de duplicatas
- `app/utils/PhoneUtils.php` - Funções de validação/formatação de telefone (JÁ EXISTENTE)
- `app/utils/DatabaseSchemaInspector.php` - Verifica colunas no banco (JÁ EXISTENTE)

**Estrutura do Banco:**
```sql
-- Campos legados
telefone VARCHAR(255)

-- Campos novos (podem não existir em bancos antigos)
telefone_ddi VARCHAR(4)
telefone_ddd VARCHAR(4)
telefone_numero VARCHAR(20)
```

**Problema Atual:**
O sistema salva telefone importado no campo legado sem separar DDI/DDD/número, causando inconsistência:
- Cadastro manual → salva em campos separados ✓
- Importação CSV → salva só em `telefone` ✗
- Integração Omie → espera campos separados

---

## 🔧 Tarefas

### **TAREFA 1: Processar telefones com PhoneUtils em `importar_processar.php`**

**Localização:** `crm/clientes/importar_processar.php`

**Modificações:**

1. **Adicionar imports no início do arquivo (após linha 3):**
```php
require_once __DIR__ . '/../../app/utils/PhoneUtils.php';
```

2. **Substituir processamento de telefone (linha 179):**

**CÓDIGO ATUAL:**
```php
$telefoneDigits = preg_replace('/\D+/', '', $telefoneRaw);
```

**CÓDIGO NOVO:**
```php
// Processar telefone usando PhoneUtils
$telefoneData = [
    'raw' => $telefoneRaw,
    'ddi' => '55',
    'ddd' => null,
    'numero' => null,
    'combinado' => '',
    'valido' => false,
    'erro' => null,
];

if ($telefoneRaw !== '') {
    try {
        $digits = stripNonDigits($telefoneRaw);

        // Detectar DDI internacional
        $ddiDetectado = '55';
        if (strlen($digits) > 11) {
            // Se começa com 55 e tem mais de 11 dígitos
            if (strncmp($digits, '55', 2) === 0 && strlen($digits) > 12) {
                $ddiDetectado = '55';
                $digits = substr($digits, 2);
            } elseif (strlen($digits) >= 13) {
                // Telefone internacional (ex: +1 202 555-0123 = 12025550123)
                // Tentar extrair 1-3 dígitos como DDI
                for ($ddiLen = 3; $ddiLen >= 1; $ddiLen--) {
                    $possibleDDI = substr($digits, 0, $ddiLen);
                    $remainingDigits = substr($digits, $ddiLen);

                    // Verificar se sobram 10-13 dígitos (DDD + número)
                    if (strlen($remainingDigits) >= 10 && strlen($remainingDigits) <= 13) {
                        $ddiDetectado = $possibleDDI;
                        $digits = $remainingDigits;
                        break;
                    }
                }
            }
        }

        // Extrair DDD e número
        $parts = extractPhoneParts($digits);

        $telefoneData['ddi'] = $ddiDetectado;
        $telefoneData['ddd'] = $parts['ddd'];
        $telefoneData['numero'] = $parts['phone'];
        $telefoneData['combinado'] = $ddiDetectado . $parts['ddd'] . $parts['phone'];
        $telefoneData['valido'] = true;

    } catch (InvalidArgumentException $e) {
        // Telefone inválido - manter dados brutos mas marcar erro
        $telefoneData['erro'] = $e->getMessage();
        $telefoneData['combinado'] = stripNonDigits($telefoneRaw);
    }
}

// Para detecção de duplicatas, usar telefone combinado ou apenas dígitos
$telefoneDigits = $telefoneData['combinado'] ?: stripNonDigits($telefoneRaw);
```

3. **Atualizar array `$rowData` (linha 189-202) para incluir dados de telefone:**

**CÓDIGO ATUAL:**
```php
$rowData = [
    'row_index' => $rowNumber,
    'company_name' => $nomeCliente,
    'contact_name' => $nomeResponsavel,
    'email' => $email,
    'phone' => $telefoneRaw,  // ❌ Apenas raw
    'channel' => $defaultChannel,
    'duplicate' => $existingLead ? [...] : null,
];
```

**CÓDIGO NOVO:**
```php
$rowData = [
    'row_index' => $rowNumber,
    'company_name' => $nomeCliente,
    'contact_name' => $nomeResponsavel,
    'email' => $email,
    'phone_raw' => $telefoneRaw,                    // ✓ Raw original
    'phone_ddi' => $telefoneData['ddi'],             // ✓ DDI separado
    'phone_ddd' => $telefoneData['ddd'],             // ✓ DDD separado
    'phone_numero' => $telefoneData['numero'],       // ✓ Número separado
    'phone_combinado' => $telefoneData['combinado'], // ✓ DDI+DDD+Número
    'phone_valido' => $telefoneData['valido'],       // ✓ Flag de validação
    'phone_erro' => $telefoneData['erro'],           // ✓ Mensagem de erro
    'channel' => $defaultChannel,
    'duplicate' => $existingLead ? [...] : null,
];
```

---

### **TAREFA 2: Salvar em campos separados em `importar_confirmar.php`**

**Localização:** `crm/clientes/importar_confirmar.php`

**Modificações:**

1. **Adicionar imports no início do arquivo (após linha 3):**
```php
require_once __DIR__ . '/../../app/utils/PhoneUtils.php';
require_once __DIR__ . '/../../app/utils/DatabaseSchemaInspector.php';
```

2. **Substituir função `insertImportedProspects` completa (linhas 5-57):**

**CÓDIGO NOVO:**
```php
function insertImportedProspects(PDO $pdo, array $rows, ?int $assignedOwnerId): int
{
    if (empty($rows)) {
        return 0;
    }

    // Verificar quais colunas de telefone existem no banco
    $hasPhoneDDI = DatabaseSchemaInspector::hasColumn($pdo, 'clientes', 'telefone_ddi');
    $hasPhoneDDD = DatabaseSchemaInspector::hasColumn($pdo, 'clientes', 'telefone_ddd');
    $hasPhoneNumero = DatabaseSchemaInspector::hasColumn($pdo, 'clientes', 'telefone_numero');

    // Montar SQL dinamicamente baseado nas colunas disponíveis
    $columns = [
        'nome_cliente',
        'nome_responsavel',
        'email',
        'telefone',      // Campo legado (sempre presente)
        'canal_origem',
        'categoria',
        'is_prospect',
        'crmOwnerId'
    ];

    $placeholders = [
        ':nome_cliente',
        ':nome_responsavel',
        ':email',
        ':telefone',
        ':canal_origem',
        ':categoria',
        '1',
        ':crm_owner_id'
    ];

    // Adicionar campos separados se existirem
    if ($hasPhoneDDI) {
        $columns[] = 'telefone_ddi';
        $placeholders[] = ':telefone_ddi';
    }
    if ($hasPhoneDDD) {
        $columns[] = 'telefone_ddd';
        $placeholders[] = ':telefone_ddd';
    }
    if ($hasPhoneNumero) {
        $columns[] = 'telefone_numero';
        $placeholders[] = ':telefone_numero';
    }

    $insertSql = sprintf(
        'INSERT INTO clientes (%s) VALUES (%s)',
        implode(', ', $columns),
        implode(', ', $placeholders)
    );

    $insertStmt = $pdo->prepare($insertSql);
    $created = 0;

    $pdo->beginTransaction();

    try {
        foreach ($rows as $row) {
            // Preparar telefone combinado para campo legado
            $telefoneLegacy = null;
            if (isset($row['phone_combinado']) && $row['phone_combinado'] !== '') {
                $telefoneLegacy = $row['phone_combinado'];
            } elseif (isset($row['phone_raw']) && $row['phone_raw'] !== '') {
                $telefoneLegacy = $row['phone_raw'];
            }

            // Parâmetros base
            $params = [
                ':nome_cliente' => $row['company_name'],
                ':nome_responsavel' => $row['contact_name'] !== '' ? $row['contact_name'] : null,
                ':email' => $row['email'] !== '' ? $row['email'] : null,
                ':telefone' => $telefoneLegacy,
                ':canal_origem' => $row['channel'],
                ':categoria' => 'Entrada',
                ':crm_owner_id' => $assignedOwnerId,
            ];

            // Adicionar campos separados se disponíveis
            if ($hasPhoneDDI) {
                $params[':telefone_ddi'] = $row['phone_ddi'] ?? null;
            }
            if ($hasPhoneDDD) {
                $params[':telefone_ddd'] = $row['phone_ddd'] ?? null;
            }
            if ($hasPhoneNumero) {
                $params[':telefone_numero'] = $row['phone_numero'] ?? null;
            }

            $insertStmt->execute($params);
            $created++;
        }

        $pdo->commit();
    } catch (Throwable $exception) {
        $pdo->rollBack();
        throw $exception;
    }

    return $created;
}
```

---

### **TAREFA 3: Melhorar detecção de duplicatas (OPCIONAL, mas recomendado)**

**Localização:** `crm/clientes/importar_processar.php` (linha 149-156)

**CÓDIGO ATUAL:**
```php
$duplicateSql = "SELECT id, nome_cliente, email, telefone
                 FROM clientes
                 WHERE is_prospect = 1 AND (
                     (:email <> '' AND email = :email) OR
                     (:telefone <> '' AND REPLACE(REPLACE(...) = :telefone) OR
                     (:nome_cliente <> '' AND LOWER(nome_cliente) = LOWER(:nome_cliente))
                 )
                 LIMIT 1";
```

**CÓDIGO NOVO (melhorado):**
```php
$duplicateSql = "SELECT id, nome_cliente, email, telefone,
                        telefone_ddi, telefone_ddd, telefone_numero
                 FROM clientes
                 WHERE is_prospect = 1 AND (
                     -- Email exato
                     (:email <> '' AND email = :email) OR

                     -- Telefone: verificar campos separados E campo legado
                     (:telefone_numero <> '' AND telefone_numero = :telefone_numero AND telefone_ddd = :telefone_ddd) OR
                     (:telefone <> '' AND REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(telefone, '(', ''), ')', ''), '-', ''), ' ', ''), '.', ''), '+', '') = :telefone) OR

                     -- Nome similar
                     (:nome_cliente <> '' AND LOWER(nome_cliente) = LOWER(:nome_cliente))
                 )
                 LIMIT 1";
```

**E atualizar execução (linha 181):**
```php
$duplicateStmt->execute([
    ':email' => $email,
    ':telefone' => $telefoneDigits,
    ':telefone_numero' => $telefoneData['numero'] ?? '',
    ':telefone_ddd' => $telefoneData['ddd'] ?? '',
    ':nome_cliente' => $nomeCliente,
]);
```

---

### **TAREFA 4: Melhorar UI de revisão (OPCIONAL)**

**Localização:** `crm/clientes/importar_revisao.php`

**Adicionar indicador visual de telefone válido/inválido:**

Procurar no HTML onde exibe o telefone e adicionar status:

```php
<?php if (!empty($row['phone_raw'])): ?>
    <div class="phone-info">
        <strong>Telefone:</strong> <?= htmlspecialchars($row['phone_raw']) ?>

        <?php if ($row['phone_valido']): ?>
            <span class="badge badge-success" title="Telefone validado">
                ✓ Válido
            </span>
            <small class="text-muted">
                DDI: <?= htmlspecialchars($row['phone_ddi']) ?>,
                DDD: <?= htmlspecialchars($row['phone_ddd']) ?>,
                Nº: <?= htmlspecialchars($row['phone_numero']) ?>
            </small>
        <?php elseif ($row['phone_erro']): ?>
            <span class="badge badge-warning" title="<?= htmlspecialchars($row['phone_erro']) ?>">
                ⚠ Inválido
            </span>
            <small class="text-danger"><?= htmlspecialchars($row['phone_erro']) ?></small>
        <?php endif; ?>
    </div>
<?php endif; ?>
```

---

## ✅ Critérios de Aceitação

Após implementação, o sistema deve:

1. ✅ **Processar telefones brasileiros corretamente**
   - `(11) 91234-5678` → DDI: 55, DDD: 11, Número: 912345678
   - `11912345678` → DDI: 55, DDD: 11, Número: 912345678
   - `+55 11 91234-5678` → DDI: 55, DDD: 11, Número: 912345678

2. ✅ **Salvar em campos separados quando disponíveis**
   - Inserir em `telefone_ddi`, `telefone_ddd`, `telefone_numero`
   - Continuar preenchendo campo legado `telefone`

3. ✅ **Validar telefones e reportar erros**
   - Telefones inválidos devem gerar warning mas não bloquear importação
   - Mostrar mensagem de erro clara

4. ✅ **Manter compatibilidade backward**
   - Funcionar com bancos antigos (sem colunas novas)
   - Usar `DatabaseSchemaInspector` para verificar colunas

5. ✅ **Melhorar detecção de duplicatas**
   - Comparar por campos separados também
   - Detectar leads antigos como duplicatas

---

## 🧪 Como Testar

**Criar arquivo de teste `test_import.csv`:**
```csv
Nome do Lead/Empresa,Nome do Lead Principal,E-mail,Telefone
Empresa Teste 1,João Silva,joao1@teste.com,(11) 91234-5678
Empresa Teste 2,Maria Santos,maria2@teste.com,21987654321
Empresa Teste 3,Pedro Oliveira,pedro3@teste.com,+55 11 98765-4321
Empresa Teste 4,Ana Costa,ana4@teste.com,(85) 3333-4444
Empresa Internacional,John Doe,john@intl.com,+1 202 555-0123
Empresa Inválida,José,jose@invalido.com,123
```

**Passos:**
1. Fazer login como vendedor ou admin
2. Ir em CRM → Leads → Importar
3. Fazer upload de `test_import.csv`
4. Verificar que telefones são processados:
   - Empresa Teste 1: ✓ Válido (DDI: 55, DDD: 11, Nº: 912345678)
   - Empresa Teste 2: ✓ Válido (DDI: 55, DDD: 21, Nº: 987654321)
   - Empresa Internacional: ✓ Válido (DDI: 1, DDD: 20, Nº: 25550123)
   - Empresa Inválida: ⚠ Inválido (telefone muito curto)
5. Confirmar importação
6. Verificar no banco que campos separados foram preenchidos:

```sql
SELECT nome_cliente, telefone, telefone_ddi, telefone_ddd, telefone_numero
FROM clientes
WHERE nome_cliente LIKE 'Empresa Teste%'
ORDER BY id DESC;
```

**Resultado esperado:**
```
| nome_cliente        | telefone         | telefone_ddi | telefone_ddd | telefone_numero |
|---------------------|------------------|--------------|--------------|-----------------|
| Empresa Teste 1     | 5511912345678    | 55           | 11           | 912345678       |
| Empresa Teste 2     | 5521987654321    | 55           | 21           | 987654321       |
| Empresa Teste 3     | 5511987654321    | 55           | 11           | 987654321       |
| Empresa Teste 4     | 55853334444      | 55           | 85           | 33334444        |
| Empresa Internacional | 12025550123    | 1            | 20           | 25550123        |
```

---

## 📌 Notas Importantes

1. **Não modificar PhoneUtils.php** - As funções já existem e funcionam bem
2. **Sempre usar DatabaseSchemaInspector** - Garante compatibilidade com bancos antigos
3. **Tratamento de exceções** - Telefones inválidos não devem quebrar importação
4. **Manter campo legado** - Código antigo depende do campo `telefone`
5. **Log de erros** - Usar `error_log()` para debugging se necessário

---

## 🎨 Código Limpo

- Usar constantes para DDI padrão (ex: `const DEFAULT_DDI_BRAZIL = '55'`)
- Comentar lógica de detecção de DDI internacional
- Manter indentação consistente (4 espaços)
- Nomear variáveis de forma clara (`$telefoneData` em vez de `$td`)

---

## 🚀 Prioridade de Implementação

**ALTA PRIORIDADE:**
- ✅ TAREFA 1: Processar telefones com PhoneUtils
- ✅ TAREFA 2: Salvar em campos separados

**MÉDIA PRIORIDADE:**
- ⚠️ TAREFA 3: Melhorar detecção de duplicatas

**BAIXA PRIORIDADE:**
- 💡 TAREFA 4: Melhorar UI de revisão

---

## 📚 Referências

- **PhoneUtils.php**: Funções prontas para usar
  - `stripNonDigits()` - Remove não-dígitos
  - `extractPhoneParts()` - Extrai DDD e número
  - `normalizeDDI()`, `normalizeDDD()`, `normalizePhone()` - Validações

- **DatabaseSchemaInspector.php**: Verificar colunas
  - `hasColumn($pdo, 'clientes', 'telefone_ddi')` → bool

- **Estrutura de dados esperada:**
```php
[
    'phone_raw' => '(11) 91234-5678',
    'phone_ddi' => '55',
    'phone_ddd' => '11',
    'phone_numero' => '912345678',
    'phone_combinado' => '5511912345678',
    'phone_valido' => true,
    'phone_erro' => null
]
```

---

**BOA SORTE! 🚀**
