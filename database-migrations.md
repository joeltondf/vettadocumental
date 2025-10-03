# Database Migrations — Lead Conversion Wizard

As instruções abaixo atualizam o banco de dados para suportar o fluxo de conversão de leads em serviços pendentes.

## 1. Ajustes na tabela `clientes`

```sql
ALTER TABLE `clientes`
  ADD COLUMN `prazo_acordado_dias` INT UNSIGNED NULL DEFAULT NULL AFTER `tipo_assessoria`,
  ADD COLUMN `complemento` VARCHAR(60) NULL DEFAULT NULL AFTER `numero`,
  ADD COLUMN `cidade_validation_source` ENUM('api', 'database') NULL DEFAULT 'api' AFTER `estado`,
  ADD COLUMN `data_conversao` DATETIME NULL DEFAULT NULL AFTER `cidade_validation_source`,
  ADD COLUMN `usuario_conversao_id` INT UNSIGNED NULL DEFAULT NULL AFTER `data_conversao`,
  ADD COLUMN `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER `data_cadastro`,
  ADD COLUMN `deleted_at` DATETIME NULL DEFAULT NULL AFTER `updated_at`;

CREATE INDEX `idx_clientes_tipo_pessoa` ON `clientes` (`tipo_pessoa`);
CREATE INDEX `idx_clientes_tipo_assessoria` ON `clientes` (`tipo_assessoria`);
CREATE INDEX `idx_clientes_is_prospect` ON `clientes` (`is_prospect`);
CREATE INDEX `idx_clientes_data_conversao` ON `clientes` (`data_conversao`);
```

## 2. Ajustes na tabela `cliente_servicos_mensalistas`

```sql
ALTER TABLE `cliente_servicos_mensalistas`
  ADD COLUMN `servico_tipo` VARCHAR(50) NULL DEFAULT NULL AFTER `valor_padrao`,
  ADD COLUMN `ativo` TINYINT(1) NOT NULL DEFAULT 1 AFTER `servico_tipo`,
  ADD COLUMN `data_inicio` DATE NULL DEFAULT NULL AFTER `ativo`,
  ADD COLUMN `data_fim` DATE NULL DEFAULT NULL AFTER `data_inicio`,
  ADD COLUMN `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER `data_fim`,
  ADD COLUMN `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER `created_at`;

CREATE INDEX `idx_csm_ativo` ON `cliente_servicos_mensalistas` (`ativo`);
```

## 3. Ajustes na tabela `processos`

```sql
ALTER TABLE `processos`
  ADD COLUMN `comprovante_pagamento_1` VARCHAR(255) NULL DEFAULT NULL AFTER `data_pagamento_2`,
  ADD COLUMN `comprovante_pagamento_2` VARCHAR(255) NULL DEFAULT NULL AFTER `comprovante_pagamento_1`,
  MODIFY COLUMN `orcamento_forma_pagamento` ENUM('À vista', 'Parcelado', 'Mensal') NULL DEFAULT NULL;

CREATE INDEX `idx_processos_status` ON `processos` (`status_processo`);
CREATE INDEX `idx_processos_data_inicio_traducao` ON `processos` (`data_inicio_traducao`);
```

## 4. Inclusão do status "Serviço Pendente"

```sql
ALTER TABLE `processos`
  MODIFY COLUMN `status_processo`
    ENUM('Orçamento','Aprovado','Em andamento','Concluído','Cancelado','Pendente','Orçamento Pendente','Serviço Pendente')
    DEFAULT 'Orçamento';
```

## Exemplos de uso

A tabela abaixo demonstra comandos básicos utilizando os novos campos.

```sql
-- CREATE TABLE: exemplo para testes isolados
CREATE TABLE IF NOT EXISTS `clientes_test` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nome_cliente` VARCHAR(255) NOT NULL,
  `prazo_acordado_dias` INT UNSIGNED NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB;

-- INSERT: novo cliente convertido
INSERT INTO `clientes` (
  `nome_cliente`, `tipo_pessoa`, `tipo_assessoria`, `prazo_acordado_dias`,
  `cpf_cnpj`, `email`, `telefone`, `cidade`, `estado`,
  `cidade_validation_source`, `is_prospect`, `data_conversao`
) VALUES (
  'Cliente Exemplo Ltda', 'Jurídica', 'Mensalista', 10,
  '12345678000100', 'contato@exemplo.com', '(11) 99999-9999', 'São Paulo', 'SP',
  'api', 0, NOW()
);

-- UPDATE: registro de comprovante no processo
UPDATE `processos`
   SET `orcamento_forma_pagamento` = 'Parcelado',
       `orcamento_valor_entrada` = 1500.00,
       `comprovante_pagamento_1` = 'uploads/comprovantes/comprovante_1.pdf'
 WHERE `id` = 42;

-- DELETE: desativação lógica de serviço mensalista
UPDATE `cliente_servicos_mensalistas`
   SET `ativo` = 0, `data_fim` = CURDATE()
 WHERE `cliente_id` = 42 AND `produto_orcamento_id` = 7;

-- ALTER TABLE: remoção da tabela auxiliar de testes
ALTER TABLE `clientes_test`
  ADD COLUMN `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP;

-- DELETE definitivo (apenas para ambiente de testes)
DELETE FROM `clientes_test` WHERE `id` = 1;
```

> **Observação:** execute os scripts em ordem para preservar a integridade referencial. Sempre realize backup antes de aplicar as alterações em produção.
