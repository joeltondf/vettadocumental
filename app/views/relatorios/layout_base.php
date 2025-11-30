<?php
$baseUrl = APP_URL . '/relatorios.php';
require_once __DIR__ . '/../layouts/header.php';
?>

<style>
    .hub-relatorios { display: flex; min-height: calc(100vh - 64px); }
    .hub-relatorios .sidebar { width: 240px; background: #0b1727; color: #fff; padding: 24px 16px; }
    .hub-relatorios .sidebar a { display: flex; align-items: center; gap: 10px; padding: 10px 12px; border-radius: 10px; margin-bottom: 8px; color: #e5e7eb; text-decoration: none; }
    .hub-relatorios .sidebar a.active { background: rgba(255,255,255,0.1); color: #fff; font-weight: 700; }
    .hub-relatorios .sidebar a:hover { background: rgba(255,255,255,0.08); }
    .hub-relatorios .content { flex: 1; padding: 24px; background: #f3f4f6; }
</style>

<div class="hub-relatorios">
    <aside class="sidebar">
        <h3 class="text-lg font-bold mb-4">Relatórios &amp; BI</h3>
        <a href="<?php echo $baseUrl; ?>" class="<?php echo $view === 'dashboard' ? 'active' : ''; ?>"><i class="fas fa-chart-pie"></i> Dashboard</a>
        <a href="<?php echo $baseUrl; ?>?view=caixa" class="<?php echo $view === 'caixa' ? 'active' : ''; ?>"><i class="fas fa-cash-register"></i> Fluxo de Caixa</a>
        <a href="<?php echo $baseUrl; ?>?view=vendas" class="<?php echo $view === 'vendas' ? 'active' : ''; ?>"><i class="fas fa-user-tie"></i> Vendas</a>
    </aside>
    <main class="content">
        <?php require $viewFile; ?>
    </main>
</div>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
