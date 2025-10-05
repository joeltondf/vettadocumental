<?php
$showActions = $showActions ?? true;
$showProgress = $showProgress ?? false;
$highlightAnimations = $highlightAnimations ?? false;
$deadlineColors = $deadlineColors ?? [];
?>
<table class="min-w-full divide-y divide-gray-200 table-auto">
    <thead class="bg-gray-50 sticky top-0 z-10">
        <tr>
            <th scope="col" class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Família</th>
            <th scope="col" class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Assessoria</th>
            <th scope="col" class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Doc.</th>
            <th scope="col" class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">OS Omie</th>
            <th scope="col" class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Serviços</th>
            <th scope="col" class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Entrada</th>
            <th scope="col" class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Envio</th>
            <th scope="col" class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Prazo</th>
            <?php if ($showActions): ?>
                <th scope="col" class="relative px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Ações</th>
            <?php endif; ?>
        </tr>
    </thead>
    <tbody class="bg-white divide-y divide-gray-200" id="processes-table-body">
        <?php
            $processes = $processes ?? [];
            $renderOnlyBody = false;
            require __DIR__ . '/process_table_rows.php';
        ?>
    </tbody>
</table>
