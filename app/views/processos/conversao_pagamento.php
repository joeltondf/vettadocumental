<?php
$formData = $formData ?? [];
$processId = (int)($processo['id'] ?? 0);
$paymentMethod = $formData['forma_cobranca'] ?? 'À vista';
$totalValue = $formData['valor_total'] ?? ($processo['valor_total'] ?? '');
$displayTotal = ($totalValue !== '' && $totalValue !== null)
    ? 'R$ ' . htmlspecialchars((string)$totalValue)
    : 'Não informado';
?>
<div class="max-w-3xl mx-auto space-y-8">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Converter em Serviço &mdash; Pagamento</h1>
            <p class="text-sm text-gray-600">Informe os dados financeiros para finalizar a conversão do processo em serviço.</p>
        </div>
        <a href="processos.php?action=view&id=<?php echo $processId; ?>" class="text-sm text-blue-600 hover:underline">&larr; Voltar para o processo</a>
    </div>

    <form
        action="processos.php?action=convert_to_service_payment&id=<?php echo $processId; ?>"
        method="POST"
        enctype="multipart/form-data"
        class="bg-white shadow rounded-lg p-6 space-y-6"
        data-conversion-step="payment"
    >
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-semibold text-gray-700" for="valor_total">Valor total do serviço</label>
                <input type="text" id="valor_total" name="valor_total" value="<?php echo htmlspecialchars($totalValue); ?>" class="mt-1 block w-full rounded-md border border-gray-300 shadow-sm focus:ring-orange-500 focus:border-orange-500" data-total-value>
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700" for="forma_cobranca">Forma de cobrança</label>
                <select id="forma_cobranca" name="forma_cobranca" class="mt-1 block w-full rounded-md border border-gray-300 shadow-sm focus:ring-orange-500 focus:border-orange-500" data-payment-method>
                    <option value="À vista" <?php echo $paymentMethod === 'À vista' ? 'selected' : ''; ?>>À vista</option>
                    <option value="Parcelado" <?php echo $paymentMethod === 'Parcelado' ? 'selected' : ''; ?>>Parcelado</option>
                    <option value="Mensal" <?php echo $paymentMethod === 'Mensal' ? 'selected' : ''; ?>>Mensal</option>
                </select>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-semibold text-gray-700" for="valor_entrada">Valor pago / entrada</label>
                <input type="text" id="valor_entrada" name="valor_entrada" value="<?php echo htmlspecialchars($formData['valor_entrada'] ?? ''); ?>" class="mt-1 block w-full rounded-md border border-gray-300 shadow-sm focus:ring-orange-500 focus:border-orange-500" data-entry-value>
            </div>
            <div id="parcelas-wrapper" class="<?php echo $paymentMethod === 'Parcelado' ? '' : 'hidden'; ?>">
                <label class="block text-sm font-semibold text-gray-700" for="parcelas">Quantidade de parcelas</label>
                <input type="number" min="2" id="parcelas" name="parcelas" value="<?php echo htmlspecialchars($formData['parcelas'] ?? 2); ?>" class="mt-1 block w-full rounded-md border border-gray-300 shadow-sm focus:ring-orange-500 focus:border-orange-500">
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-semibold text-gray-700" for="data_pagamento_1">Data do pagamento / 1ª parcela</label>
                <input type="date" id="data_pagamento_1" name="data_pagamento_1" value="<?php echo htmlspecialchars($formData['data_pagamento_1'] ?? ''); ?>" class="mt-1 block w-full rounded-md border border-gray-300 shadow-sm focus:ring-orange-500 focus:border-orange-500">
            </div>
            <div id="segunda-parcela-wrapper" class="<?php echo $paymentMethod === 'Parcelado' ? '' : 'hidden'; ?>">
                <label class="block text-sm font-semibold text-gray-700" for="data_pagamento_2">Data da 2ª parcela</label>
                <input type="date" id="data_pagamento_2" name="data_pagamento_2" value="<?php echo htmlspecialchars($formData['data_pagamento_2'] ?? ''); ?>" class="mt-1 block w-full rounded-md border border-gray-300 shadow-sm focus:ring-orange-500 focus:border-orange-500">
            </div>
        </div>

        <div class="rounded-md bg-gray-50 p-4 space-y-2">
            <p class="text-sm text-gray-700"><strong>Valor total:</strong> <span data-total-display><?php echo $displayTotal; ?></span></p>
            <p class="text-sm text-gray-700"><strong>Saldo restante:</strong> <span data-balance-display>-</span></p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-semibold text-gray-700" for="payment_proof_entry">Comprovante de pagamento</label>
                <input type="file" id="payment_proof_entry" name="payment_proof_entry" accept=".pdf,.png,.jpg,.jpeg,.webp" class="mt-1 block w-full text-sm text-gray-600">
            </div>
            <div id="segunda-comprovante-wrapper" class="<?php echo $paymentMethod === 'Parcelado' ? '' : 'hidden'; ?>">
                <label class="block text-sm font-semibold text-gray-700" for="payment_proof_balance">Comprovante saldo</label>
                <input type="file" id="payment_proof_balance" name="payment_proof_balance" accept=".pdf,.png,.jpg,.jpeg,.webp" class="mt-1 block w-full text-sm text-gray-600">
            </div>
        </div>

        <div class="flex items-center justify-between pt-4 border-t border-gray-200">
            <a href="processos.php?action=convert_to_service_deadline&id=<?php echo $processId; ?>" class="px-4 py-2 rounded-md border border-gray-300 text-sm font-semibold text-gray-700 hover:bg-gray-50">Voltar</a>
            <div class="flex items-center space-x-3">
                <a href="processos.php?action=view&id=<?php echo $processId; ?>" class="px-4 py-2 rounded-md border border-gray-300 text-sm font-semibold text-gray-700 hover:bg-gray-50">Cancelar</a>
                <button type="submit" class="px-4 py-2 rounded-md bg-green-600 text-white text-sm font-semibold shadow-sm hover:bg-green-700 focus:outline-none">
                    Finalizar conversão
                </button>
            </div>
        </div>
    </form>
</div>

<script src="assets/js/service-conversion.js"></script>
