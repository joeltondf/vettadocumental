<?php
// /app/views/partials/messages.php

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Exibe a mensagem de sucesso, se houver
if (isset($_SESSION['success_message'])): ?>
    <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4 rounded-md shadow-sm" role="alert">
        <p class="font-bold">Sucesso!</p>
        <p><?php echo htmlspecialchars($_SESSION['success_message']); ?></p>
    </div>
    <?php unset($_SESSION['success_message']); // Remove a mensagem para não exibir novamente ?>
<?php endif; ?>

<?php
// Exibe a mensagem de erro, se houver
if (isset($_SESSION['error_message'])): ?>
    <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4 rounded-md shadow-sm" role="alert">
        <p class="font-bold">Erro!</p>
        <p><?php echo htmlspecialchars($_SESSION['error_message']); ?></p>
    </div>
    <?php unset($_SESSION['error_message']); // Remove a mensagem para não exibir novamente ?>
<?php endif; ?>