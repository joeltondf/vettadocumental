<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
    <div class="bg-white shadow rounded-lg p-6 border border-gray-100">
        <div class="flex items-center justify-between mb-2">
            <h4 class="text-sm text-gray-500 uppercase">Receita total</h4>
            <i class="fas fa-arrow-trend-up text-green-600"></i>
        </div>
        <p class="text-3xl font-bold text-gray-900">R$ <?php echo number_format($receitaTotal ?? 0, 2, ',', '.'); ?></p>
    </div>
    <div class="bg-white shadow rounded-lg p-6 border border-gray-100">
        <div class="flex items-center justify-between mb-2">
            <h4 class="text-sm text-gray-500 uppercase">Comissões</h4>
            <i class="fas fa-hand-holding-usd text-indigo-600"></i>
        </div>
        <p class="text-3xl font-bold text-gray-900">R$ <?php echo number_format($totalComissoes ?? 0, 2, ',', '.'); ?></p>
    </div>
    <div class="bg-white shadow rounded-lg p-6 border border-gray-100">
        <div class="flex items-center justify-between mb-2">
            <h4 class="text-sm text-gray-500 uppercase">Alertas</h4>
            <i class="fas fa-bell text-amber-500"></i>
        </div>
        <p class="text-3xl font-bold text-gray-900"><?php echo is_array($alertas ?? null) ? count($alertas) : 0; ?></p>
    </div>
</div>

<div class="bg-white shadow rounded-lg p-6 border border-gray-100">
    <h3 class="text-lg font-semibold mb-4">Alertas Recentes</h3>
    <?php if (empty($alertas ?? [])): ?>
        <p class="text-gray-500">Nenhum alerta pendente.</p>
    <?php else: ?>
        <ul class="divide-y divide-gray-100">
            <?php foreach ($alertas as $alerta): ?>
                <li class="py-3 flex items-center justify-between">
                    <div>
                        <p class="font-semibold text-gray-800"><?php echo htmlspecialchars($alerta['titulo'] ?? ''); ?></p>
                        <p class="text-gray-500 text-sm"><?php echo htmlspecialchars($alerta['cliente'] ?? ''); ?></p>
                    </div>
                    <span class="px-3 py-1 rounded-full bg-amber-100 text-amber-700 text-xs font-semibold">Pendente</span>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>
