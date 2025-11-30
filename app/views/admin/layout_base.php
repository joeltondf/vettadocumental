<?php
$baseUrl = APP_URL . '/configuracoes.php';
require_once __DIR__ . '/../layouts/header.php';
?>

<style>
    .hub-admin { display: flex; min-height: calc(100vh - 64px); }
    .hub-admin .sidebar { width: 250px; background: #0f172a; color: #fff; padding: 24px 16px; }
    .hub-admin .sidebar a { display: flex; align-items: center; gap: 10px; padding: 10px 12px; border-radius: 10px; margin-bottom: 8px; color: #e5e7eb; text-decoration: none; }
    .hub-admin .sidebar a.active { background: rgba(255,255,255,0.1); color: #fff; font-weight: 700; }
    .hub-admin .sidebar a:hover { background: rgba(255,255,255,0.08); }
    .hub-admin .content { flex: 1; padding: 24px; background: #f8fafc; }
    .hub-admin iframe { width: 100%; border: none; min-height: 80vh; background: #fff; box-shadow: 0 10px 30px rgba(15,23,42,0.08); border-radius: 12px; }
</style>

<div class="hub-admin">
    <aside class="sidebar">
        <h3 class="text-lg font-bold mb-4">Administração</h3>
        <a href="<?php echo $baseUrl; ?>?view=produtos" class="<?php echo $view === 'produtos' ? 'active' : ''; ?>"><i class="fas fa-box"></i> Produtos de Orçamento</a>
        <a href="<?php echo $baseUrl; ?>?view=usuarios" class="<?php echo $view === 'usuarios' ? 'active' : ''; ?>"><i class="fas fa-users-cog"></i> Usuários &amp; Permissões</a>
        <a href="<?php echo $baseUrl; ?>?view=categorias" class="<?php echo $view === 'categorias' ? 'active' : ''; ?>"><i class="fas fa-layer-group"></i> Categorias Financeiras</a>
        <a href="<?php echo $baseUrl; ?>?view=email" class="<?php echo $view === 'email' ? 'active' : ''; ?>"><i class="fas fa-envelope"></i> E-mail / SMTP</a>
        <a href="<?php echo $baseUrl; ?>?view=tradutores" class="<?php echo $view === 'tradutores' ? 'active' : ''; ?>"><i class="fas fa-language"></i> Tradutores</a>
    </aside>
    <main class="content">
        <?php require $viewFile; ?>
    </main>
</div>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
