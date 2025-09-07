<?php require_once __DIR__ . '/../layouts/header.php'; ?>
<div class="mb-6">
    <h1 class="text-3xl font-bold text-gray-800">Configurações</h1>
    <p class="text-gray-600">Ajuste as configurações globais do aplicativo e de integrações.</p>
</div>

<div class="bg-white p-6 rounded-lg shadow-lg mb-6">
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-xl font-bold text-gray-800">Conexão com a Conta Azul</h2>
            <?php if ($isContaAzulConnected): ?>
                <p class="text-green-600 font-semibold mt-1">Status: Conectado</p>
            <?php else: ?>
                <p class="text-red-600 font-semibold mt-1">Status: Desconectado</p>
            <?php endif; ?>
        </div>
        <div>
            <?php if ($isContaAzulConnected): ?>
                <a href="admin.php?action=ca_disconnect" class="bg-red-600 text-white font-bold py-2 px-4 rounded-lg hover:bg-red-700">
                    Desconectar
                </a>
            <?php else: ?>
                <?php if (empty($settings['conta_azul_client_id']) || empty($settings['conta_azul_client_secret'])): ?>
                    <button class="bg-blue-300 text-white font-bold py-2 px-4 rounded-lg cursor-not-allowed" disabled title="Salve o Client ID e Secret primeiro">Conectar com a Conta Azul</button>
                <?php else: ?>
                    <a href="<?php echo htmlspecialchars($contaAzulAuthUrl); ?>" class="bg-blue-600 text-white font-bold py-2 px-4 rounded-lg hover:bg-blue-700">
                        Conectar com a Conta Azul
                    </a>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>
    
<div class="bg-white p-6 rounded-lg shadow-lg">
    <form action="admin.php?action=save_settings" method="POST">
        <div>
            <h2 class="text-xl font-bold text-gray-800 mb-1">Conta Azul</h2>
            <p class="text-sm text-gray-500 mb-4">Insira as credenciais fornecidas pela Conta Azul para ativar a integração.</p>
            
            <div class="space-y-4">
                <div>
                    <label for="conta_azul_client_id" class="block text-sm font-medium text-gray-700">Client ID</label>
                    <input type="text" name="conta_azul_client_id" id="conta_azul_client_id" 
                           class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" 
                           value="<?php echo htmlspecialchars($settings['conta_azul_client_id'] ?? ''); ?>"
                           placeholder="Cole o Client ID aqui">
                </div>
                
                <div>
                    <label for="conta_azul_client_secret" class="block text-sm font-medium text-gray-700">Client Secret</label>
                    <input type="password" name="conta_azul_client_secret" id="conta_azul_client_secret" 
                           class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" 
                           value="<?php echo htmlspecialchars($settings['conta_azul_client_secret'] ?? ''); ?>"
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