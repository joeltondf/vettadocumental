<?php require_once __DIR__ . '/../layouts/header.php'; ?>
<div class="mb-6">
    <h1 class="text-3xl font-bold text-gray-800">Configurações</h1>
    <p class="text-gray-600">Ajuste as configurações globais do aplicativo e de integrações.</p>
</div>

<div class="bg-white p-6 rounded-lg shadow-lg">
    <form action="admin.php?action=save_settings" method="POST">

        <div class="mt-6">
            <h2 class="text-xl font-bold text-gray-800 mb-1">Omie</h2>
            <p class="text-sm text-gray-500 mb-4">Insira as credenciais da sua aplicação Omie.</p>

            <div class="space-y-4">
                <div>
                    <label for="omie_app_key" class="block text-sm font-medium text-gray-700">App Key</label>
                    <input type="text" name="omie_app_key" id="omie_app_key" 
                           class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" 
                           value="<?php echo htmlspecialchars($settings['omie_app_key'] ?? ''); ?>"
                           placeholder="Cole a App Key aqui">
                </div>
                
                <div>
                    <label for="omie_app_secret" class="block text-sm font-medium text-gray-700">App Secret</label>
                    <input type="password" name="omie_app_secret" id="omie_app_secret" 
                           class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" 
                           value="<?php echo htmlspecialchars($settings['omie_app_secret'] ?? ''); ?>"
                           placeholder="••••••••••••••••••••">
                </div>
            </div>
        </div>

        <div class="mt-8 pt-5 border-t border-gray-200">
            <div class="flex justify-end">
                <button type="submit" class="bg-indigo-600 text-white font-bold py-2 px-4 rounded-lg hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    Salvar Configurações
                </button>
            </div>
        </div>

    </form>
</div>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
