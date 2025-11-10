# Análise: Importação de Leads por CSV - Melhorias DDI/DDD

## 📊 Situação Atual

### Arquitetura da Importação
```
importar.php (formulário upload)
    ↓
importar_processar.php (processa CSV + detecta duplicatas)
    ↓
importar_revisao.php (usuário revisa duplicatas)
    ↓
importar_confirmar.php (insere no banco)
```

### Formato CSV Atual
```csv
Nome do Lead/Empresa, Nome do Lead Principal, E-mail, Telefone
Empresa ABC, João Silva, joao@empresa.com, (11) 91234-5678
```

## ❌ Problemas Identificados

### 1. **Telefone NÃO é separado em DDI/DDD/Número**
**Local:** `importar_confirmar.php:11-29`

```php
$insertSql = 'INSERT INTO clientes (
    nome_cliente,
    nome_responsavel,
    email,
    telefone,  // ❌ Salva tudo junto no campo legado
    canal_origem,
    categoria,
    is_prospect,
    crmOwnerId
) VALUES (...)'
```

**Problema:** O telefone é salvo "como está" no CSV, sem separar DDI, DDD e número. Isso é **inconsistente** com:
- Formulário de cadastro manual que salva em `telefone_ddi`, `telefone_ddd`, `telefone_numero`
- Sistema Omie que espera campos separados
- Lógica de conversão prospect→cliente que valida campos separados

### 2. **PhoneUtils.php NÃO é utilizado**
**Local:** `importar_processar.php:179`

```php
// ❌ Validação atual: apenas remove caracteres
$telefoneDigits = preg_replace('/\D+/', '', $telefoneRaw);
```

**Problema:** O sistema já possui em `app/utils/PhoneUtils.php`:
- ✅ `extractPhoneParts()` - extrai DDD e número
- ✅ `normalizeDDI()` - valida DDI (1-4 dígitos)
- ✅ `normalizeDDD()` - valida DDD (exatos 2 dígitos)
- ✅ `normalizePhone()` - valida número (4-11 dígitos, 11 deve começar com 9)

**Mas nenhuma dessas funções é usada na importação!**

### 3. **Validação Insuficiente**

Telefones inválidos são aceitos:
```csv
Empresa XYZ, , , 123                     // ❌ Aceito (número muito curto)
Empresa ABC, , , (99) 81234-5678         // ❌ Aceito (DDD inválido: 99)
Empresa DEF, , , 11 81234-5678           // ❌ Aceito (fixo com 11 dígitos)
```

### 4. **Detecção de Duplicatas Inconsistente**
**Local:** `importar_processar.php:153`

```php
REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(telefone, '(', ''), ')', ''), '-', ''), ' ', ''), '.', ''), '+', '') = :telefone
```

**Problema:**
- Compara apenas campo legado `telefone`
- Ignora campos separados `telefone_ddi`, `telefone_ddd`, `telefone_numero`
- Leads antigos (com campos separados) não são detectados como duplicatas

### 5. **Falta Suporte a Telefones Internacionais**

```csv
// ❌ Como importar cliente internacional?
International Corp, John Doe, john@corp.com, +1 (202) 555-0123
```

DDI diferente de 55 é ignorado ou mal processado.

### 6. **DatabaseSchemaInspector NÃO é usado**
**Local:** `importar_confirmar.php:11-29`

Outros lugares do sistema usam:
```php
$phoneColumnAvailability = [
    'ddi' => DatabaseSchemaInspector::hasColumn($pdo, 'clientes', 'telefone_ddi'),
    'ddd' => DatabaseSchemaInspector::hasColumn($pdo, 'clientes', 'telefone_ddd'),
    'numero' => DatabaseSchemaInspector::hasColumn($pdo, 'clientes', 'telefone_numero'),
];
```

Mas a importação insere apenas no campo legado, mesmo se os campos novos existirem!

---

## ✅ Melhorias Propostas

### 1. **Usar PhoneUtils na Importação**

**Em `importar_processar.php`:**
```php
require_once __DIR__ . '/../../app/utils/PhoneUtils.php';

// Processar telefone
$telefoneRaw = trim($data[3] ?? '');
$telefoneData = [
    'raw' => $telefoneRaw,
    'ddi' => '55',  // padrão Brasil
    'ddd' => null,
    'numero' => null,
    'valido' => false,
];

if ($telefoneRaw !== '') {
    try {
        $digits = stripNonDigits($telefoneRaw);

        // Se começa com 55 e tem mais de 11 dígitos, extrair DDI
        if (strlen($digits) > 11 && strncmp($digits, '55', 2) === 0) {
            $telefoneData['ddi'] = '55';
            $digits = substr($digits, 2);
        } elseif (strlen($digits) > 13) {
            // Telefone internacional (ex: +1 202 555-0123)
            // Extrair primeiros 1-4 dígitos como DDI
            $possibleDDI = substr($digits, 0, min(4, strlen($digits) - 9));
            $telefoneData['ddi'] = $possibleDDI;
            $digits = substr($digits, strlen($possibleDDI));
        }

        // Extrair DDD + Número
        $parts = extractPhoneParts($digits);
        $telefoneData['ddd'] = $parts['ddd'];
        $telefoneData['numero'] = $parts['phone'];
        $telefoneData['valido'] = true;

    } catch (InvalidArgumentException $e) {
        // Telefone inválido - marcar para revisão
        $telefoneData['erro'] = $e->getMessage();
    }
}
```

### 2. **Salvar em Campos Separados**

**Em `importar_confirmar.php`:**
```php
require_once __DIR__ . '/../../app/utils/PhoneUtils.php';
require_once __DIR__ . '/../../app/utils/DatabaseSchemaInspector.php';

function insertImportedProspects(PDO $pdo, array $rows, ?int $assignedOwnerId): int
{
    // Verificar colunas disponíveis
    $hasPhoneDDI = DatabaseSchemaInspector::hasColumn($pdo, 'clientes', 'telefone_ddi');
    $hasPhoneDDD = DatabaseSchemaInspector::hasColumn($pdo, 'clientes', 'telefone_ddd');
    $hasPhoneNumero = DatabaseSchemaInspector::hasColumn($pdo, 'clientes', 'telefone_numero');

    // Montar SQL dinamicamente
    $columns = ['nome_cliente', 'nome_responsavel', 'email', 'telefone', 'canal_origem', 'categoria', 'is_prospect', 'crmOwnerId'];
    $placeholders = [':nome_cliente', ':nome_responsavel', ':email', ':telefone', ':canal_origem', ':categoria', '1', ':crm_owner_id'];

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

    foreach ($rows as $row) {
        $params = [
            ':nome_cliente' => $row['company_name'],
            ':nome_responsavel' => $row['contact_name'] !== '' ? $row['contact_name'] : null,
            ':email' => $row['email'] !== '' ? $row['email'] : null,
            ':telefone' => $row['phone_raw'] ?? null,  // Campo legado
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
    }
}
```

### 3. **Melhorar Detecção de Duplicatas**

```php
// Considerar campos separados também
$duplicateSql = "SELECT id, nome_cliente, email, telefone, telefone_ddi, telefone_ddd, telefone_numero
                 FROM clientes
                 WHERE is_prospect = 1 AND (
                     (:email <> '' AND email = :email) OR
                     (:telefone_numero <> '' AND telefone_numero = :telefone_numero AND telefone_ddd = :telefone_ddd) OR
                     (:telefone <> '' AND REPLACE(REPLACE(REPLACE(telefone, '(', ''), ')', ''), '-', '') = :telefone) OR
                     (:nome_cliente <> '' AND LOWER(nome_cliente) = LOWER(:nome_cliente))
                 )
                 LIMIT 1";
```

### 4. **Adicionar Coluna de Status de Validação**

Mostrar ao usuário quais telefones foram validados:

```
✓ Empresa ABC - (11) 91234-5678 → +55 (11) 91234-5678
✗ Empresa XYZ - 123 → Inválido: telefone muito curto
⚠ Internacional Corp - +1 202 555-0123 → DDI: 1, DDD: 20, Número: 25550123
```

### 5. **Suportar Formato Estendido de CSV (Opcional)**

```csv
Nome,Responsável,Email,Telefone,DDI,DDD
Empresa ABC,João,joao@abc.com,91234-5678,55,11
International,John,john@corp.com,555-0123,1,202
```

---

## 🎯 Benefícios das Melhorias

| Benefício | Impacto |
|-----------|---------|
| **Consistência de dados** | Leads importados terão mesma estrutura que cadastro manual |
| **Integração Omie** | Campos DDI/DDD/Número prontos para sincronização |
| **Validação robusta** | Detectar telefones inválidos antes de importar |
| **Melhor detecção de duplicatas** | Comparar por campos separados também |
| **Suporte internacional** | Aceitar clientes de outros países |
| **Backward compatibility** | Continua preenchendo campo legado `telefone` |
| **Rastreabilidade** | Log de erros de validação por linha |

---

## 📋 Checklist de Implementação

- [ ] Importar `PhoneUtils.php` em `importar_processar.php`
- [ ] Importar `DatabaseSchemaInspector.php` em `importar_confirmar.php`
- [ ] Processar telefone com `extractPhoneParts()` durante parsing
- [ ] Armazenar DDI, DDD, Número separados no array `$rowData`
- [ ] Modificar `insertImportedProspects()` para inserir campos separados
- [ ] Atualizar detecção de duplicatas para considerar campos separados
- [ ] Adicionar coluna de status de validação na tela de revisão
- [ ] Adicionar tratamento de exceções para telefones inválidos
- [ ] Testar com telefones brasileiros (fixo 8 dígitos, celular 9 dígitos)
- [ ] Testar com telefones internacionais
- [ ] Testar com telefones inválidos
- [ ] Garantir compatibilidade com banco sem colunas novas (usar `DatabaseSchemaInspector`)
- [ ] Adicionar logs de importação (quantos telefones válidos/inválidos)

---

## 🔍 Arquivos a Modificar

1. **`crm/clientes/importar_processar.php`**
   - Adicionar processamento com PhoneUtils
   - Incluir DDI/DDD/Número no array `$rowData`
   - Melhorar detecção de duplicatas

2. **`crm/clientes/importar_confirmar.php`**
   - Usar `DatabaseSchemaInspector` para detectar colunas
   - Inserir em campos separados quando disponíveis
   - Manter campo legado preenchido

3. **`crm/clientes/importar_revisao.php`** (opcional)
   - Mostrar status de validação de telefones
   - Exibir telefone formatado

---

## 🧪 Casos de Teste

### Telefones Válidos Brasileiros
```csv
Empresa A,João,joao@a.com,(11) 91234-5678       → ✓ DDI: 55, DDD: 11, Nº: 912345678
Empresa B,Maria,maria@b.com,11912345678         → ✓ DDI: 55, DDD: 11, Nº: 912345678
Empresa C,Pedro,pedro@c.com,+55 11 91234-5678   → ✓ DDI: 55, DDD: 11, Nº: 912345678
Empresa D,Ana,ana@d.com,(21) 3333-4444          → ✓ DDI: 55, DDD: 21, Nº: 33334444
```

### Telefones Internacionais
```csv
US Corp,John,john@us.com,+1 (202) 555-0123      → ✓ DDI: 1, DDD: 20, Nº: 25550123
UK Ltd,Jane,jane@uk.com,+44 20 7946 0958        → ✓ DDI: 44, DDD: 20, Nº: 79460958
```

### Telefones Inválidos
```csv
Empresa E,José,jose@e.com,123                   → ✗ Telefone muito curto
Empresa F,Clara,clara@f.com,(99) 91234-5678     → ⚠ DDD inválido: 99 (aceitar mas alertar)
Empresa G,Lucas,lucas@g.com,11 81234-56789      → ✗ Fixo não pode ter 11 dígitos
```

---

## 💡 Dicas de Implementação

1. **Tratamento de erros gracioso**: Telefones inválidos devem gerar aviso, mas não bloquear importação
2. **Log detalhado**: Registrar quantos telefones foram validados com sucesso
3. **Preview antes de confirmar**: Mostrar telefone formatado na tela de revisão
4. **Compatibilidade**: Sempre preencher campo legado `telefone` para não quebrar código antigo
5. **Performance**: Processar telefones em lote, não um por vez

---

## 📞 Referências

- **PhoneUtils.php**: `/home/user/sbx2/app/utils/PhoneUtils.php`
- **DatabaseSchemaInspector**: `/home/user/sbx2/app/utils/DatabaseSchemaInspector.php`
- **Modelo Cliente**: `/home/user/sbx2/app/models/Cliente.php`
- **Migrations**: `/home/user/sbx2/database/migrations/20240920120000_prepare_omie_integration.php`
