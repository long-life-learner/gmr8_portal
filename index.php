<?php
// ============================================================
// index.php — Beranda & Jadwal Kegiatan
// Portal Warga RT 005 RW 012 GMR 8
// ============================================================
require_once 'includes/db.php';
if (session_status() === PHP_SESSION_NONE)
    session_start();

$pageTitle = 'Beranda';

// Ambil kegiatan mendatang
$stmtMendatang = $pdo->query("
    SELECT * FROM kegiatan
    WHERE tanggal >= CURDATE()
    ORDER BY tanggal ASC, waktu ASC
    LIMIT 10
");
$kegiatanMendatang = $stmtMendatang->fetchAll();

// Ambil kegiatan lalu
$stmtLalu = $pdo->query("
    SELECT * FROM kegiatan
    WHERE tanggal < CURDATE()
    ORDER BY tanggal DESC
    LIMIT 6
");
$kegiatanLalu = $stmtLalu->fetchAll();

// Stats publik
$totalWarga = $pdo->query("SELECT COUNT(*) FROM warga WHERE aktif=1")->fetchColumn();
$bulan = date('n');
$tahun = date('Y');
$totalTagihan = $pdo->prepare("SELECT COUNT(*) FROM tagihan WHERE bulan=? AND tahun=?");
$totalTagihan->execute([$bulan, $tahun]);
$jumlahTagihan = $totalTagihan->fetchColumn();

$totalLunas = $pdo->prepare("SELECT COUNT(*) FROM tagihan WHERE bulan=? AND tahun=? AND status='lunas'");
$totalLunas->execute([$bulan, $tahun]);
$jumlahLunas = $totalLunas->fetchColumn();

$persenLunas = $jumlahTagihan > 0 ? round($jumlahLunas / $jumlahTagihan * 100) : 0;
$saldo = getSaldoKas($pdo);

require_once 'includes/header.php';
?>

<!-- HERO -->
<section class="hero">
    <div class="hero-content">
        <div class="hero-badge">🌿 RT 005 · RW 012 · GMR 8</div>
        <h1>Halo, Warga GMR 8! 👋</h1>
        <p>Yuk, stay update bareng soal kegiatan dan info penting di lingkungan kita yang asri ini.</p>
        <div class="hero-stats">
            <div class="hero-stat">
                <strong><?= $totalWarga ?></strong>
                <span>KK Warga</span>
            </div>
            <div class="hero-stat">
                <strong><?= count($kegiatanMendatang) ?></strong>
                <span>Kegiatan Mendatang</span>
            </div>
            <div class="hero-stat">
                <strong><?= $persenLunas ?>%</strong>
                <span>Iuran Lunas</span>
            </div>
        </div>
    </div>
</section>

<div class="container">

    <!-- KEGIATAN MENDATANG -->
    <div class="section">
        <h2 class="section-title">📅 Yang Akan Datang</h2>
        <p class="section-sub">Catat di kalender ya, jangan sampai ketinggalan!</p>

        <?php if (empty($kegiatanMendatang)): ?>
            <div class="empty-state">
                <i class="fa-regular fa-calendar-xmark"></i>
                <p>Belum ada kegiatan terjadwal nih. Nantikan info selanjutnya! 😊</p>
            </div>
        <?php else: ?>
            <?php foreach ($kegiatanMendatang as $k): ?>
                <?php
                $tgl = new DateTime($k['tanggal']);
                $isToday = $tgl->format('Y-m-d') === date('Y-m-d');
                $isTomorrow = $tgl->format('Y-m-d') === date('Y-m-d', strtotime('+1 day'));
                $dayLabel = $isToday ? '🔴 HARI INI' : ($isTomorrow ? '⭕ BESOK' : $tgl->format('d M Y'));
                ?>
                <div class="card" style="<?= $isToday ? 'border-color:var(--green-500);border-width:2px;' : '' ?>">
                    <div class="card-header">
                        <div class="card-icon card-icon-green">
                            <i class="fa-solid fa-calendar-star">
                                <div style="font-size:16px;font-weight:800;color:var(--green-800);margin-bottom:5px;">
                                    <?= date('d', strtotime($k['tanggal'])) ?>
                                </div>
                                <div style="font-size:10px;color:var(--green-600);font-weight:700;">
                                    <?= strtoupper(substr(bulanNama(date('n', strtotime($k['tanggal']))), 0, 3)) ?>
                                </div>
                            </i>
                        </div>
                        <div style="flex:1">
                            <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:4px;">
                                <h3 style="font-size:15px;margin:0;"><?= htmlspecialchars($k['judul']) ?></h3>
                                <?php if ($isToday): ?><span class="badge badge-red">Hari Ini!</span><?php endif; ?>
                                <?php if ($isTomorrow): ?><span class="badge badge-yellow">Besok</span><?php endif; ?>
                            </div>
                            <div class="card-meta">
                                <i class="fa-solid fa-calendar"></i> <?= $dayLabel ?>
                                <?php if ($k['waktu']): ?>
                                    &bull; <i class="fa-solid fa-clock"></i> <?= date('H:i', strtotime($k['waktu'])) ?> WIB
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <?php if ($k['lokasi']): ?>
                        <div
                            style="display:flex;align-items:center;gap:6px;font-size:13px;color:var(--text-mid);margin-bottom:8px;">
                            <i class="fa-solid fa-location-dot" style="color:var(--green-500)"></i>
                            <?= htmlspecialchars($k['lokasi']) ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($k['agenda']): ?>
                        <div style="background:var(--green-50);border-radius:8px;padding:10px;margin-bottom:8px;">
                            <div style="font-size:11px;font-weight:700;color:var(--green-700);margin-bottom:4px;">📋 AGENDA</div>
                            <div style="font-size:13px;color:var(--text-mid);"><?= nl2br(htmlspecialchars($k['agenda'])) ?></div>
                        </div>
                    <?php endif; ?>

                    <?php if ($k['deskripsi']): ?>
                        <p style="font-size:13px;color:var(--text-light);line-height:1.6;"><?= htmlspecialchars($k['deskripsi']) ?>
                        </p>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- KEGIATAN LALU -->
    <?php if (!empty($kegiatanLalu)): ?>
        <div class="section">
            <h2 class="section-title">🕐 Kegiatan Sebelumnya</h2>
            <p class="section-sub">Yang sudah terlaksana dengan baik 🙏</p>
            <div class="timeline">
                <?php foreach ($kegiatanLalu as $k): ?>
                    <?php $tgl = new DateTime($k['tanggal']); ?>
                    <div class="timeline-item past">
                        <div class="timeline-dot"></div>
                        <div class="card" style="margin-left:0;">
                            <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:10px">
                                <div>
                                    <div style="font-size:14px;font-weight:700;color:var(--green-900);">
                                        <?= htmlspecialchars($k['judul']) ?>
                                    </div>
                                    <div style="font-size:12px;color:var(--text-light);margin-top:3px;">
                                        <i class="fa-solid fa-calendar"></i> <?= $tgl->format('d M Y') ?>
                                        <?php if ($k['lokasi']): ?>
                                            &bull; <i class="fa-solid fa-location-dot"></i> <?= htmlspecialchars($k['lokasi']) ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <span class="badge badge-gray" style="flex-shrink:0;">Selesai</span>
                            </div>
                            <?php if ($k['deskripsi']): ?>
                                <p style="font-size:12.5px;color:var(--text-light);margin-top:8px;line-height:1.6;">
                                    <?= htmlspecialchars($k['deskripsi']) ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- SHORTCUT SECTION -->
    <div class="section mb-3">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
            <a href="<?= SITE_URL ?>/iuran/" class="card" style="text-decoration:none;text-align:center;padding:20px 14px;">
                <i class="fa-solid fa-leaf"
                    style="font-size:28px;color:var(--green-600);margin-bottom:8px;display:block;"></i>
                <div style="font-weight:700;font-size:14px;">Bayar Iuran</div>
                <div style="font-size:12px;color:var(--text-light);">IPL & Kas RT</div>
            </a>
            <a href="<?= SITE_URL ?>/monitoring/" class="card" style="text-decoration:none;text-align:center;padding:20px 14px;">
                <i class="fa-solid fa-chart-line"
                    style="font-size:28px;color:var(--green-600);margin-bottom:8px;display:block;"></i>
                <div style="font-weight:700;font-size:14px;">Cek Kas</div>
                <div style="font-size:12px;color:var(--text-light);">Transparan untuk semua</div>
            </a>
        </div>
    </div>

</div>

<?php require_once 'includes/footer.php'; ?>