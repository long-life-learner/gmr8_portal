<?php
// ============================================================
// admin/includes/admin_layout.php — Admin Layout Helper
// ============================================================
function adminHeader($pageTitle = '', $activePage = '') {
    global $pdo;
    require_once dirname(__DIR__) . '/../includes/db.php';
    require_once dirname(__DIR__) . '/../includes/auth.php';
    requireLogin();

    $role = getUserRole();
    $nama = getUserName();
    $initials = implode('', array_map(fn($w)=>strtoupper(mb_substr($w,0,1)), array_slice(explode(' ',$nama),0,2)));

    // Pending payments count (for bendahara badge)
    $pendingCount = 0;
    if (hasRole(['bendahara'])) {
        $pendingCount = $pdo->query("SELECT COUNT(*) FROM pembayaran WHERE status='pending'")->fetchColumn();
    }

    $menu = getAdminMenu();
    $currentUri = rtrim($_SERVER['REQUEST_URI'], '/');
    $currentSlug = basename($currentUri);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $pageTitle ? htmlspecialchars($pageTitle) . ' — ' : '' ?>Admin GMR 8</title>
<link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800&display=swap" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<link href="<?= SITE_URL ?>/assets/css/style.css" rel="stylesheet">
<link href="<?= SITE_URL ?>/assets/css/admin.css" rel="stylesheet">
</head>
<body class="admin-body">

<!-- Topbar -->
<div class="admin-topbar">
    <button class="toggle-sidebar" id="sidebar-toggle" aria-label="Toggle menu">
        <i class="fa-solid fa-bars"></i>
    </button>
    <div class="topbar-title">🌿 Panel Pengurus GMR 8</div>
    <div class="topbar-right">
        <div style="display:flex;align-items:center;gap:8px;">
            <div class="topbar-user">
                <div class="avatar"><?= $initials ?></div>
                <div style="display:flex;flex-direction:column;line-height:1.2;">
                    <span style="font-size:13px;font-weight:700;"><?= htmlspecialchars($nama) ?></span>
                    <span style="font-size:10px;opacity:.7;"><?= getRoleLabel($role) ?></span>
                </div>
            </div>
        </div>
        <a href="<?= SITE_URL ?>/admin/logout/" class="topbar-logout"><i class="fa-solid fa-right-from-bracket"></i> Keluar</a>
    </div>
</div>

<!-- Sidebar overlay (mobile) -->
<div class="sidebar-overlay" id="sidebar-overlay"></div>

<div class="admin-layout">
    <!-- Sidebar -->
        <aside class="admin-sidebar" id="admin-sidebar">
        <div class="sidebar-section">Menu</div>
        <?php foreach ($menu as $item): ?>
        <?php 
            $itemSlug = rtrim($item['url'], '/');
            $isActive = ($currentSlug === $itemSlug);
        ?>
        <a href="<?= $item['url'] ?>" class="sidebar-link <?= $isActive ? 'active' : '' ?>">
            <i class="fa-solid <?= $item['icon'] ?>"></i>
            <span><?= htmlspecialchars($item['label']) ?></span>
            <?php if ($item['url'] === 'verifikasi/' && $pendingCount > 0): ?>
            <span style="margin-left:auto;background:#e53935;color:#fff;border-radius:20px;padding:1px 7px;font-size:11px;font-weight:700;"><?= $pendingCount ?></span>
            <?php endif; ?>
        </a>
        <?php endforeach; ?>

        <div class="sidebar-divider"></div>
        <div class="sidebar-section">Navigasi</div>
        <a href="<?= SITE_URL ?>/" class="sidebar-link" target="_blank">
            <i class="fa-solid fa-arrow-up-right-from-square"></i>
            <span>Lihat Website</span>
        </a>
        <a href="<?= SITE_URL ?>/admin/logout/" class="sidebar-link" style="color:#c62828;">
            <i class="fa-solid fa-right-from-bracket"></i>
            <span>Keluar</span>
        </a>
    </aside>

    <!-- Main Content -->
    <main class="admin-main">
<?php
}

function adminFooter() { ?>
    </main>
</div><!-- /.admin-layout -->

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script src="<?= SITE_URL ?>/assets/js/app.js"></script>
<script>
// Sidebar toggle
const sidebarToggle = document.getElementById('sidebar-toggle');
const adminSidebar  = document.getElementById('admin-sidebar');
const sidebarOverlay= document.getElementById('sidebar-overlay');

function openSidebar(){
    adminSidebar.classList.add('open');
    sidebarOverlay.classList.add('show');
}
function closeSidebar(){
    adminSidebar.classList.remove('open');
    sidebarOverlay.classList.remove('show');
}

sidebarToggle?.addEventListener('click',()=>{
    if(adminSidebar.classList.contains('open')) closeSidebar();
    else openSidebar();
});
sidebarOverlay?.addEventListener('click', closeSidebar);
</script>
<?= isset($extraAdminScript) ? $extraAdminScript : '' ?>
</body>
</html>
<?php
}
