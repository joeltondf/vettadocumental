<?php
$formData = $formData ?? [];
$processId = (int)($processo['id'] ?? 0);
$deadlineDays = $formData['traducao_prazo_dias'] ?? $formData['prazo_dias'] ?? '';
$deadlinePreview = $formData['data_previsao_entrega'] ?? '';

$conversionSteps = [
    [
        'key' => 'client',
        'label' => 'Cliente',
        'description' => 'Revise ou cadastre os dados do cliente.',
    ],
    [
        'key' => 'deadline',
        'label' => 'Prazo do serviço',
        'description' => 'Defina data de início e prazo de entrega.',
    ],
    [
        'key' => 'payment',
        'label' => 'Pagamento',
        'description' => 'Informe as condições financeiras.',
    ],
];
$currentStep = 'deadline';
$completedSteps = ['client'];
include __DIR__ . '/partials/conversion_steps.php';
?>
<div class="max-w-3xl mx-auto space-y-10">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Converter em Serviço &mdash; Prazo do Serviço</h1>
            <p class="text-sm text-gray-600">Defina a data de início e o prazo acordado para a entrega.</p>
        </div>
        <a href="processos.php?action=view&id=<?php echo $processId; ?>" class="text-sm text-blue-600 hover:underline">&larr; Voltar para o processo</a>
    </div>

    <form
        action="processos.php?action=convert_to_service_deadline&id=<?php echo $processId; ?>"
        method="POST"
        class="bg-white shadow rounded-lg p-8 space-y-8"
        data-conversion-step="deadline"
    >
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label class="block text-sm font-semibold text-gray-700" for="data_inicio_traducao">Data de início</label>
                <input type="date" id="data_inicio_traducao" name="data_inicio_traducao" value="<?php echo htmlspecialchars($formData['data_inicio_traducao'] ?? date('Y-m-d')); ?>" class="mt-2 block w-full rounded-md border border-gray-300 shadow-sm focus:ring-orange-500 focus:border-orange-500">
                <p class="mt-2 text-xs text-gray-500">Obrigatória apenas quando houver prazo de tradução maior que zero.</p>
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700" for="traducao_prazo_dias">Dias para entrega</label>
                <input
                    type="number"
                    id="traducao_prazo_dias"
                    name="traducao_prazo_dias"
                    value="<?php echo htmlspecialchars($deadlineDays); ?>"
                    class="mt-2 block w-full rounded-md border border-gray-300 shadow-sm focus:ring-orange-500 focus:border-orange-500"
                    data-deadline-days
                >
                <p class="mt-2 text-xs text-gray-500">Informe um número inteiro de dias. Use zero ou deixe em branco para remover o prazo.</p>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <span class="block text-sm font-semibold text-gray-700">Previsão de entrega</span>
                <p class="mt-2 text-sm text-gray-600" data-deadline-display data-default-message="Informe a data de início e a quantidade de dias para calcular automaticamente a previsão de entrega.">
                    <?php if (!empty($deadlinePreview)) : ?>
                        Entrega prevista para <strong><?php echo date('d/m/Y', strtotime($deadlinePreview)); ?></strong>
                    <?php else : ?>
                        Informe a data de início e a quantidade de dias para calcular automaticamente a previsão de entrega.
                    <?php endif; ?>
                </p>
            </div>
        </div>

        <div class="flex items-center justify-between pt-6 border-t border-gray-200">
            <a href="processos.php?action=convert_to_service_client&id=<?php echo $processId; ?>" class="px-4 py-2 rounded-md border border-gray-300 text-sm font-semibold text-gray-700 hover:bg-gray-50">Voltar</a>
            <div class="flex items-center space-x-3">
                <a href="processos.php?action=view&id=<?php echo $processId; ?>" class="px-4 py-2 rounded-md border border-gray-300 text-sm font-semibold text-gray-700 hover:bg-gray-50">Cancelar</a>
                <button type="submit" class="px-4 py-2 rounded-md bg-orange-500 text-white text-sm font-semibold shadow-sm hover:bg-orange-600 focus:outline-none">
                    Salvar e continuar
                </button>
            </div>
        </div>
    </form>
</div>

<script src="assets/js/service-conversion.js"></script>
