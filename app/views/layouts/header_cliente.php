<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">  <!-- LINHA ADICIONADA -->

    <title><?php echo isset($pageTitle) ? htmlspecialchars($pageTitle) . ' - ' . APP_NAME : APP_NAME; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="/assets/style.css">
</head>
<body class="bg-gray-100">
    <header class="bg-white shadow-md">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 flex justify-between items-center">
            <h1 class="text-xl font-bold text-gray-800"><?php echo APP_NAME; ?></h1>
            <div>
                <span>Olá, <?php echo htmlspecialchars($_SESSION['user_nome']); ?></span>
                <a href="login.php?action=logout" class="ml-4 text-sm font-medium text-red-600 hover:text-red-800">Sair</a>
            </div>
        </div>
    </header>
    <main class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
        </main>
    </div>
</div>

</body>
</html>