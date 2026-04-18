<?php
// ============================================================
// Header — Portal Warga RT 005 GMR 8
// ============================================================
$currentPage = rtrim($_SERVER['REQUEST_URI'], '/');
$currentPage = basename($currentPage);
if (empty($currentPage)) $currentPage = 'index';
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
    <meta name="description"
        content="Portal Warga RT 005 RW 012 Blok GMR 8, Grand Madani Residence 2 — Informasi kegiatan, iuran, dan kas warga.">
    <title><?= isset($pageTitle) ? htmlspecialchars($pageTitle) . ' | ' : '' ?>Portal Warga GMR 8</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <link href="<?= SITE_URL ?>/assets/css/style.css" rel="stylesheet">
    <?= isset($extraHead) ? $extraHead : '' ?>
</head>

<body>

    <!-- Top Header -->
    <header class="site-header">
        <a href="<?= SITE_URL ?>/" class="logo">
            <div class="logo-icon">🌿</div>
            <div class="logo-text">
                <strong>Warga GMR 8</strong>
                <span>RT 005 · RW 012 · Grand Madani Residence 2</span>
            </div>
        </a>

        <!-- Desktop Navigation -->
        <nav class="desktop-nav" aria-label="Navigasi utama">
            <a href="<?= SITE_URL ?>/" class="<?= $currentPage === 'index' ? 'active' : '' ?>">
                <i class="fa-solid fa-house"></i> Beranda
            </a>
            <a href="<?= SITE_URL ?>/iuran/"
                class="<?= $currentPage === 'iuran' || $currentPage === 'bayar' ? 'active' : '' ?>">
                <i class="fa-solid fa-leaf"></i> Iuran
            </a>
            <a href="<?= SITE_URL ?>/monitoring/" class="<?= $currentPage === 'monitoring' ? 'active' : '' ?>">
                <i class="fa-solid fa-chart-line"></i> Kas Warga
            </a>
            <a href="<?= SITE_URL ?>/struktur/" class="<?= $currentPage === 'struktur' ? 'active' : '' ?>">
                <i class="fa-solid fa-people-group"></i> Pengurus
            </a>
            <a href="<?= SITE_URL ?>/tutorial/" class="<?= $currentPage === 'tutorial' || strpos($_SERVER['REQUEST_URI'], '/tutorial_detail/') !== false ? 'active' : '' ?>">
                <i class="fa-solid fa-book-open"></i> Tutorial
            </a>
            <a href="<?= SITE_URL ?>/cctv/" class="<?= $currentPage === 'cctv.php' ? 'active' : '' ?>">
                <i class="fa-solid fa-video"></i> CCTV
            </a>
        </nav>

        <a href="<?= SITE_URL ?>/admin/login/" class="header-admin-btn" aria-label="Login admin">
            <i class="fa-solid fa-lock"></i>
            <span class="hidden" style="display:none">Admin</span>
        </a>
    </header>

    <!-- Flash Messages -->
    <?php if (isset($_SESSION['flash'])): ?>
        <div class="container mt-2">
            <div class="alert alert-<?= $_SESSION['flash']['type'] === 'success' ? 'success' : 'danger' ?> flash-msg">
                <i
                    class="fa-solid fa-<?= $_SESSION['flash']['type'] === 'success' ? 'circle-check' : 'circle-exclamation' ?>"></i>
                <span><?= htmlspecialchars($_SESSION['flash']['msg']) ?></span>
            </div>
        </div>
        <?php unset($_SESSION['flash']); ?>
    <?php endif; ?>