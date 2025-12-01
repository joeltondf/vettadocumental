# Changelog

## [Unreleased]
- Ajuste do FinanceiroService para ler movimentações via view `v_movimentacoes`, com filtros opcionais por vendedor, SDR e status financeiro em caixa e competência.
- Atualização dos controladores e menu para direcionar ao novo Painel Unificado, garantindo acesso apenas a perfis financeiro/gestão e removendo links antigos.
- Limpeza dos relatórios obsoletos (`relatorio_caixa_real.php` e `relatorio_vendedor.php`) e seus componentes associados para evitar uso duplicado.
