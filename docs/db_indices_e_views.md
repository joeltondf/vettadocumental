# Orientações de otimização de banco (sem execução automática)

As instruções abaixo servem apenas como referência para execução manual pelo time de DBA. Nenhuma delas é aplicada automaticamente pela aplicação.

## Índices sugeridos

```sql
CREATE INDEX idx_processos_data_pagamento_1 ON processos (data_pagamento_1);
CREATE INDEX idx_processos_data_pagamento_2 ON processos (data_pagamento_2);
CREATE INDEX idx_lancamentos_financeiros_data ON lancamentos_financeiros (data_lancamento);
```

## View sugerida para consolidação

```sql
CREATE OR REPLACE VIEW v_movimentacoes AS
SELECT 'Entrada' AS tipo,
       p.titulo AS descricao,
       p.orcamento_valor_entrada AS valor,
       p.data_pagamento_1 AS data,
       'Processo' AS origem,
       p.vendedor_id,
       p.sdr_id
FROM processos p
UNION ALL
SELECT 'Entrada' AS tipo,
       p.titulo AS descricao,
       p.orcamento_valor_restante AS valor,
       p.data_pagamento_2 AS data,
       'Processo' AS origem,
       p.vendedor_id,
       p.sdr_id
FROM processos p
UNION ALL
SELECT CASE WHEN l.tipo_lancamento = 'RECEITA' THEN 'Entrada' ELSE 'Saída' END AS tipo,
       l.descricao,
       l.valor,
       l.data_lancamento AS data,
       'Lançamento manual' AS origem,
       p.vendedor_id,
       p.sdr_id
FROM lancamentos_financeiros l
LEFT JOIN processos p ON p.id = l.processo_id;
```

> Execute somente após validação, garantindo que não haja impactos em dados existentes.
