<?php
// ============================================================
// struktur.php — Halaman Struktur Organisasi
// Portal Warga RT 005 RW 012 GMR 8
// ============================================================
require_once 'includes/db.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$pageTitle = 'Kenalan Sama Pengurus Kita';

$pengurus = $pdo->query("SELECT * FROM struktur_organisasi ORDER BY urutan ASC")->fetchAll();

function getInitials($nama) {
    $words = explode(' ', trim($nama));
    $init = '';
    foreach (array_slice($words, 0, 2) as $w) {
        $init .= strtoupper(mb_substr($w, 0, 1));
    }
    return $init;
}

// Warna avatar berdasarkan urutan
$avatarColors = [
    ['#1B4332','#52B788'], ['#2D6A4F','#74C69D'], ['#40916C','#95D5B2'],
    ['#1565C0','#42A5F5'], ['#6A1B9A','#AB47BC'], ['#BF360C','#FF7043'],
    ['#F57F17','#FFD54F'],
];

require_once 'includes/header.php';
?>

<!-- HERO -->
<section class="hero">
    <div class="hero-content">
        <div class="hero-badge">👋 RT 005 RW 012 GMR 8</div>
        <h1>Kenalan yuk sama pengurus RT kita! 😊</h1>
        <p>Mereka adalah warga GMR 8 juga — teman sebelah yang siap bantu & gotong royong bareng.</p>
    </div>
</section>

<div class="container">

    <div class="section">
        <h2 class="section-title">🌿 Tim Pengurus RT 005</h2>
        <p class="section-sub">Periode 2025 – 2028 &bull; Dipilih dari dan untuk warga</p>

        <div class="org-grid">
            <?php foreach ($pengurus as $i => $p):
                $colors = $avatarColors[$i % count($avatarColors)];
                $bg = "linear-gradient(135deg, {$colors[0]}, {$colors[1]})";
                $initials = getInitials($p['nama']);
                $isKetua = $p['urutan'] == 1;
            ?>
            <div class="org-card <?= $isKetua ? 'ketua' : '' ?>">
                <div class="org-avatar" style="<?= $isKetua ? 'width:88px;height:88px;font-size:32px;' : '' ?>background:<?= $bg ?>;">
                    <?php if ($p['foto'] && file_exists('assets/uploads/foto/' . $p['foto'])): ?>
                        <img src="<?= SITE_URL ?>/assets/uploads/foto/<?= htmlspecialchars($p['foto']) ?>" alt="<?= htmlspecialchars($p['nama']) ?>">
                    <?php else: ?>
                        <?= $initials ?>
                    <?php endif; ?>
                </div>

                <?php if ($isKetua): ?>
                    <div style="display:inline-block;background:var(--green-100);border-radius:20px;padding:3px 12px;font-size:11px;font-weight:700;color:var(--green-800);margin-bottom:6px;">⭐ Ketua RT</div>
                <?php endif; ?>

                <div class="org-name"><?= htmlspecialchars($p['nama']) ?></div>
                <div class="org-jabatan"><?= htmlspecialchars($p['jabatan']) ?></div>

                <?php if ($p['deskripsi']): ?>
                <div class="org-desc"><?= htmlspecialchars($p['deskripsi']) ?></div>
                <?php endif; ?>

                <?php if ($p['no_wa']): ?>
                <a href="https://wa.me/62<?= ltrim($p['no_wa'], '0') ?>" target="_blank" class="org-wa" rel="noopener">
                    <i class="fa-brands fa-whatsapp"></i>
                    Chat di WA
                </a>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>

        <?php if (empty($pengurus)): ?>
        <div class="empty-state">
            <i class="fa-solid fa-users"></i>
            <p>Data pengurus belum ditambahkan. Silakan login sebagai Ketua RT untuk mengaturnya.</p>
        </div>
        <?php endif; ?>
    </div>

    <!-- INFO HUBUNGI -->
    <div class="card" style="background:linear-gradient(135deg,var(--green-50),#fff);border-color:var(--green-200);">
        <div style="font-size:18px;margin-bottom:8px;">💬 Ada yang perlu disampaikan?</div>
        <p style="font-size:14px;color:var(--text-mid);line-height:1.7;">
            Jangan sungkan menghubungi pengurus RT kita ya! Semua pertanyaan, masukan, dan laporan warga
            sangat-sangat diterima. Karena GMR 8 yang nyaman adalah hasil dari komunikasi yang baik
            antara pengurus dan seluruh warga. 🌿
        </p>
        <div style="margin-top:14px;font-size:13px;color:var(--text-light);">
            <i class="fa-solid fa-clock"></i> Pengurus biasanya aktif merespons pagi &amp; malam hari.
        </div>
    </div>

</div>

<?php require_once 'includes/footer.php'; ?>
