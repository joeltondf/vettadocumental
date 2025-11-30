<?php
// Reaproveita o painel atual do fluxo de caixa dentro do hub de relatórios.
$formAction = APP_URL . '/relatorios.php?view=caixa';
?>
<div class="bg-white shadow rounded-lg p-4 mb-4">
    <h2 class="text-2xl font-bold text-gray-800">Fluxo de Caixa</h2>
    <p class="text-gray-600">Visão consolidada do caixa e serviços.</p>
</div>

<?php
// Ajusta ação dos filtros para permanecer no hub
$filters['form_action'] = $formAction;
require __DIR__ . '/../fluxo_caixa/painel.php';
?>
