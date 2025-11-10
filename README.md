# Sistema de Notificações

O painel de notificações foi modernizado para oferecer visão consolidada e ações em lote sobre os alertas do CRM.

## Recursos principais

- **Agrupamento por processo**: notificações relacionadas ao mesmo `referencia_id` são exibidas juntas, com informações do cliente e status do processo.
- **Ordenação por prioridade**: alertas são ordenados por criticidade (`alta`, `média`, `baixa`) e depois pela data de criação.
- **Filtros avançados**: é possível combinar filtros por tipo de alerta, prioridade, status da notificação, período, cliente, status do processo e destinatário (para perfis de gestão).
- **Seleção múltipla**: utilize as caixas de seleção para marcar vários alertas de uma vez e aplicar as ações de “Marcar como lidas” ou “Marcar como resolvidas”.
- **Destaque visual**: cores de borda e badges indicam rapidamente o nível de prioridade de cada grupo.

## Ações em lote via endpoint

Envie uma requisição `POST` para `notificacoes.php?action=batchUpdate` com:

```http
ids[]=12&ids[]=42&batch_action=mark_read
```

A propriedade `batch_action` aceita `mark_read` (marcar como lida) e `mark_resolved` (marcar como resolvida). O endpoint aceita `application/x-www-form-urlencoded` ou JSON.

## Prioridades

As prioridades são calculadas no modelo `Notificacao` conforme o tipo de alerta, idade do registro e status do processo:

- Orçamentos pendentes há mais de 7 dias → prioridade **alta**.
- Alertas de serviço pendente há mais de 5 dias → prioridade **alta**.
- Processos concluídos ou arquivados → prioridade automaticamente reduzida para **baixa**.

Os critérios podem ser personalizados editando o método `determinePriority` em `app/models/Notificacao.php`.

## Arquivamento automático

O script `cron_limpeza_notificacoes.php` arquiva (marca como lidas e resolvidas) notificações que:

1. Ultrapassam o limite configurado (padrão de 15 dias, alterável via variável de ambiente `NOTIFICATION_ARCHIVE_DAYS`).
2. Referenciam processos cujo status não está mais em fase pendente.

O script respeita bancos MySQL e SQLite, permitindo execução em ambiente de testes.

## Testes

Execute `php tests/php/run.php` para validar:

- Regras de duplicidade na criação de notificações.
- Propagação da leitura entre alertas do mesmo processo.
- Ordenação e filtragem por prioridade.
- Rotina de arquivamento automático.
