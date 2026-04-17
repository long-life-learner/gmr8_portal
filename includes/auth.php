<?php
// ============================================================
// Auth & Role Management - Portal Warga RT 005 GMR 8
// ============================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function isLoggedIn() {
    return isset($_SESSION['admin_id']) && !empty($_SESSION['admin_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ' . (strpos($_SERVER['REQUEST_URI'], '/admin/') !== false ? '' : 'admin/') . 'login/?redirect=' . urlencode($_SERVER['REQUEST_URI']));
        exit;
    }
}

function getUserRole() {
    return $_SESSION['admin_role'] ?? null;
}

function getUserName() {
    return $_SESSION['admin_nama'] ?? 'Admin';
}

function getUserId() {
    return $_SESSION['admin_id'] ?? null;
}

function hasRole($roles) {
    if (!is_array($roles)) $roles = [$roles];
    $userRole = getUserRole();
    return in_array($userRole, $roles) || $userRole === 'admin';
}

function requireRole($roles) {
    requireLogin();
    if (!hasRole($roles)) {
        header('Location: ../dashboard/?error=akses_ditolak');
        exit;
    }
}

// Role labels
function getRoleLabel($role) {
    $labels = [
        'admin'      => 'Super Admin',
        'ketua_rt'   => 'Ketua RT',
        'bendahara'  => 'Bendahara',
        'sekretaris' => 'Sekretaris',
    ];
    return $labels[$role] ?? $role;
}

// Menu items berdasarkan role
function getAdminMenu() {
    $role = getUserRole();
    $menu = [];

    // Dashboard selalu ada
    $menu[] = ['url' => 'dashboard/', 'icon' => 'fa-gauge', 'label' => 'Dashboard'];

    if (hasRole(['bendahara'])) {
        $menu[] = ['url' => 'verifikasi/', 'icon' => 'fa-circle-check', 'label' => 'Verifikasi Bayar'];
        $menu[] = ['url' => 'jenis_iuran/', 'icon' => 'fa-tags', 'label' => 'Jenis Iuran'];
        $menu[] = ['url' => 'laporan/', 'icon' => 'fa-chart-bar', 'label' => 'Laporan Keuangan'];
        $menu[] = ['url' => 'kas/', 'icon' => 'fa-wallet', 'label' => 'Catat Pengeluaran'];
    }

    if (hasRole(['sekretaris'])) {
        $menu[] = ['url' => 'kegiatan/', 'icon' => 'fa-calendar-days', 'label' => 'Jadwal Kegiatan'];
        $menu[] = ['url' => 'tutorial/', 'icon' => 'fa-book-open', 'label' => 'Kelola Tutorial'];
    }

    if (hasRole(['ketua_rt'])) {
        $menu[] = ['url' => 'warga/', 'icon' => 'fa-house-user', 'label' => 'Data Warga'];
        $menu[] = ['url' => 'struktur_admin/', 'icon' => 'fa-people-group', 'label' => 'Struktur Organisasi'];
    }

    return $menu;
}
