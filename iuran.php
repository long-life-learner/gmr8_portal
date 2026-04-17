<?php
// ============================================================
// iuran.php — Halaman IPL & Pilih Warga Bayar
// Portal Warga RT 005 RW 012 GMR 8
// ============================================================
require_once 'includes/db.php';
if (session_status() === PHP_SESSION_NONE)
    session_start();

$pageTitle = 'Bayar Iuran';

$bulanIni = (int) date('n');
$tahunIni = (int) date('Y');

$bulan = isset($_GET['bulan']) ? (int) $_GET['bulan'] : $bulanIni;
$tahun = isset($_GET['tahun']) ? (int) $_GET['tahun'] : $tahunIni;
$jenisId = isset($_GET['jenis']) ? (int) $_GET['jenis'] : 0;

// Ambil semua jenis iuran aktif
$jenisIuran = $pdo->query("SELECT * FROM jenis_iuran WHERE aktif=1 ORDER BY id ASC")->fetchAll();

// Default jenis pertama jika belum dipilih
if (!$jenisId && !empty($jenisIuran))
    $jenisId = $jenisIuran[0]['id'];

// Ambil info jenis iuran terpilih
$jenisInfo = null;
foreach ($jenisIuran as $j) {
    if ($j['id'] == $jenisId) {
        $jenisInfo = $j;
        break;
    }
}

// Tagihan belum bayar untuk bulan/tahun/jenis terpilih
$belumBayar = [];
$sudahBayar = [];

if ($jenisId) {
    $stmtBelum = $pdo->prepare("
        SELECT t.id as tagihan_id, w.nama, w.nomor_rumah, t.nominal, t.status
        FROM tagihan t
        JOIN warga w ON w.id = t.warga_id
        WHERE t.jenis_iuran_id = ? AND t.bulan = ? AND t.tahun = ?
          AND t.status IN ('belum_bayar')
          AND w.aktif = 1
        ORDER BY w.nomor_rumah ASC
    ");
    $stmtBelum->execute([$jenisId, $bulan, $tahun]);
    $belumBayar = $stmtBelum->fetchAll();

    $stmtSudah = $pdo->prepare("
        SELECT w.nama, w.nomor_rumah, t.nominal, t.status
        FROM tagihan t
        JOIN warga w ON w.id = t.warga_id
        WHERE t.jenis_iuran_id = ? AND t.bulan = ? AND t.tahun = ?
          AND t.status IN ('menunggu_verifikasi','lunas')
          AND w.aktif = 1
        ORDER BY w.nomor_rumah ASC
    ");
    $stmtSudah->execute([$jenisId, $bulan, $tahun]);
    $sudahBayar = $stmtSudah->fetchAll();
}

$totalWarga = $pdo->query("SELECT COUNT(*) FROM warga WHERE aktif=1")->fetchColumn();
$jmlBelum = count($belumBayar);
$jmlSudah = count($sudahBayar);

function getInitials($nama)
{
    $words = explode(' ', trim($nama));
    $init = '';
    foreach (array_slice($words, 0, 2) as $w)
        $init .= strtoupper(mb_substr($w, 0, 1));
    return $init;
}

require_once 'includes/header.php';
?>

<!-- HERO -->
<section class="hero" style="padding-bottom:24px;">
    <div class="hero-content">
        <div class="hero-badge">💚 Iuran = Gerakan Bersama</div>
        <h1>Bayar Iuran GMR 8</h1>
        <p>Iuran bukan cuma kewajiban — ini cara kita bareng-bareng menjaga lingkungan kita tetap nyaman, bersih, dan
            rukun. Makasih ya udah peduli! 🙏</p>
    </div>
</section>

<div class="container">

    <!-- Info Box -->
    <div class="alert alert-info mt-2">
        <i class="fa-solid fa-circle-info"></i>
        <div>
            <strong>Cara bayar iuran:</strong> Pilih jenis iuran → cari nama kamu → klik nama → ikuti panduan transfer →
            upload bukti. Gampang banget! 😊
        </div>
    </div>

    <!-- Filter Bulan & Tahun -->
    <form method="GET" style="display:flex;gap:10px;margin-bottom:16px;align-items:flex-end;">
        <input type="hidden" name="jenis" value="<?= $jenisId ?>">
        <div class="form-group" style="flex:1;margin-bottom:0;">
            <label class="form-label" for="sel-bulan">Bulan</label>
            <select name="bulan" id="sel-bulan" class="form-control" onchange="this.form.submit()">
                <?php for ($m = 1; $m <= 12; $m++): ?>
                    <option value="<?= $m ?>" <?= $m == $bulan ? 'selected' : '' ?>><?= bulanNama($m) ?></option>
                <?php endfor; ?>
            </select>
        </div>
        <div class="form-group" style="flex:1;margin-bottom:0;">
            <label class="form-label" for="sel-tahun">Tahun</label>
            <select name="tahun" id="sel-tahun" class="form-control" onchange="this.form.submit()">
                <?php for ($y = date('Y'); $y >= date('Y') - 2; $y--): ?>
                    <option value="<?= $y ?>" <?= $y == $tahun ? 'selected' : '' ?>><?= $y ?></option>
                <?php endfor; ?>
            </select>
        </div>
    </form>

    <!-- Tabs Jenis Iuran -->
    <?php if (!empty($jenisIuran)): ?>
        <div class="tabs-container">
            <div class="tabs">
                <?php foreach ($jenisIuran as $j): ?>
                    <a href="?jenis=<?= $j['id'] ?>&bulan=<?= $bulan ?>&tahun=<?= $tahun ?>"
                        class="tab-btn <?= $j['id'] == $jenisId ? 'active' : '' ?>" style="text-decoration:none;">
                        <?= htmlspecialchars($j['nama']) ?>
                        <small style="font-weight:400;"> (<?= formatRupiah($j['nominal']) ?>)</small>
                    </a>
                <?php endforeach; ?>
            </div>

            <?php if ($jenisInfo): ?>
                <!-- Progress -->
                <div style="margin-bottom:16px;">
                    <div class="flex-between mb-1">
                        <span style="font-size:13px;color:var(--text-mid);font-weight:600;"><?= $jmlSudah ?> dari
                            <?= ($jmlSudah + $jmlBelum) ?> warga sudah bayar</span>
                        <span
                            style="font-size:13px;font-weight:700;color:var(--green-700);"><?= ($jmlSudah + $jmlBelum) > 0 ? round($jmlSudah / ($jmlSudah + $jmlBelum) * 100) : 0 ?>%</span>
                    </div>
                    <div class="progress-wrap">
                        <div class="progress-bar"
                            style="width:<?= ($jmlSudah + $jmlBelum) > 0 ? round($jmlSudah / ($jmlSudah + $jmlBelum) * 100) : 0 ?>%">
                        </div>
                    </div>
                </div>

                <?php if ($jmlBelum + $jmlSudah === 0): ?>
                    <!-- Tagihan belum digenerate -->
                    <div class="alert alert-warning">
                        <i class="fa-solid fa-triangle-exclamation"></i>
                        <div>
                            <strong>Tagihan bulan <?= bulanNama($bulan) ?>             <?= $tahun ?> belum tersedia.</strong><br>
                            <span style="font-size:12px;">Mohon tunggu bendahara membuat tagihan bulan ini ya 🙏</span>
                        </div>
                    </div>

                <?php elseif ($jmlBelum === 0): ?>
                    <!-- Semua sudah bayar -->
                    <div style="text-align:center;padding:30px 20px;">
                        <div style="font-size:48px;margin-bottom:12px;">🎉</div>
                        <h3 style="color:var(--green-700);">Semua sudah bayar!</h3>
                        <p style="font-size:14px;color:var(--text-light);margin-top:6px;">
                            Warga GMR 8 kompak banget! <?= bulanNama($bulan) ?>             <?= $tahun ?> sudah lunas semua 🙏
                        </p>
                    </div>

                <?php else: ?>
                    <!-- DAFTAR BELUM BAYAR -->
                    <div class="section" style="padding-top:0;">
                        <h3 class="section-title" style="font-size:16px;">👇 Pilih nama kamu</h3>
                        <p class="section-sub">Yang namanya ada di sini, yuk segera bayar ya! Tinggal klik nama, lalu ikuti langkah
                            selanjutnya.</p>

                        <!-- Search -->
                        <div class="search-bar">
                            <i class="fa-solid fa-magnifying-glass"></i>
                            <input type="text" id="warga-search" class="form-control" placeholder="Cari nama atau nomor rumah..."
                                autocomplete="off">
                        </div>

                        <div class="warga-list" id="warga-list-belum">
                            <?php foreach ($belumBayar as $w): ?>
                                <a href="bayar.php?tagihan_id=<?= $w['tagihan_id'] ?>" class="warga-item" style="text-decoration:none;">
                                    <div class="warga-avatar"><?= getInitials($w['nama']) ?></div>
                                    <div class="warga-info">
                                        <div class="warga-nama"><?= htmlspecialchars($w['nama']) ?></div>
                                        <div class="warga-rumah"><i class="fa-solid fa-house"></i>
                                            <?= htmlspecialchars($w['nomor_rumah']) ?> &bull; <?= formatRupiah($w['nominal']) ?></div>
                                    </div>
                                    <i class="fa-solid fa-chevron-right arrow"></i>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- DAFTAR SUDAH BAYAR / PENDING -->
                <?php if (!empty($sudahBayar)): ?>
                    <div class="section">
                        <h3 class="section-title" style="font-size:16px;">✅ Sudah Bayar / Verifikasi</h3>
                        <p class="section-sub">Terima kasih sudah berpartisipasi! 🌿</p>
                        <div style="display:flex;flex-direction:column;gap:8px;">
                            <?php foreach ($sudahBayar as $w): ?>
                                <div
                                    style="display:flex;align-items:center;gap:12px;background:#fff;border:1px solid var(--border);border-radius:10px;padding:10px 14px;">
                                    <div class="warga-avatar"
                                        style="background:linear-gradient(135deg,var(--green-300),var(--green-500));width:36px;height:36px;font-size:13px;">
                                        <?= getInitials($w['nama']) ?></div>
                                    <div style="flex:1;">
                                        <div style="font-weight:700;font-size:14px;"><?= htmlspecialchars($w['nama']) ?></div>
                                        <div style="font-size:12px;color:var(--text-light);"><?= htmlspecialchars($w['nomor_rumah']) ?>
                                        </div>
                                    </div>
                                    <span class="badge <?= $w['status'] === 'lunas' ? 'badge-green' : 'badge-yellow' ?>">
                                        <?= $w['status'] === 'lunas' ? '✅ Lunas' : '⏳ Verifikasi' ?>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="empty-state">
            <i class="fa-solid fa-tags"></i>
            <p>Belum ada jenis iuran yang diatur. Admin sedang menyiapkannya! 😊</p>
        </div>
    <?php endif; ?>

</div>

<?php require_once 'includes/footer.php'; ?>