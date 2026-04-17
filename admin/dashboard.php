<?php
// ============================================================
// admin/dashboard.php — Dashboard Panel Admin
// Portal Warga RT 005 RW 012 GMR 8
// ============================================================
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once 'includes/admin_layout.php';
requireLogin();

$role = getUserRole();
$nama = getUserName();
$bulanIni = (int) date('n');
$tahunIni = (int) date('Y');

// Stats
$saldo = getSaldoKas($pdo);
$totalWarga = $pdo->query("SELECT COUNT(*) FROM warga WHERE aktif=1")->fetchColumn();
$pendingBayar = $pdo->query("SELECT COUNT(*) FROM pembayaran WHERE status='pending'")->fetchColumn();
$kegiatanMendatang = $pdo->query("SELECT COUNT(*) FROM kegiatan WHERE tanggal >= CURDATE()")->fetchColumn();

$tagihanBulanIni = $pdo->prepare("SELECT COUNT(*) FROM tagihan WHERE bulan=? AND tahun=?");
$tagihanBulanIni->execute([$bulanIni, $tahunIni]);
$jmlTagihan = $tagihanBulanIni->fetchColumn();

$lunasBulanIni = $pdo->prepare("SELECT COUNT(*) FROM tagihan WHERE bulan=? AND tahun=? AND status='lunas'");
$lunasBulanIni->execute([$bulanIni, $tahunIni]);
$jmlLunas = $lunasBulanIni->fetchColumn();

// Pembayaran pending terbaru
$pembayaranPending = $pdo->query("
    SELECT p.*, t.nominal, t.bulan, t.tahun,
           w.nama, w.nomor_rumah, j.nama as jenis_nama
    FROM pembayaran p
    JOIN tagihan t ON t.id = p.tagihan_id
    JOIN warga w ON w.id = t.warga_id
    JOIN jenis_iuran j ON j.id = t.jenis_iuran_id
    WHERE p.status = 'pending'
    ORDER BY p.created_at DESC
    LIMIT 5
")->fetchAll();

// Kegiatan mendatang
$kegiatan = $pdo->query("
    SELECT * FROM kegiatan WHERE tanggal >= CURDATE() ORDER BY tanggal ASC LIMIT 3
")->fetchAll();

adminHeader('Dashboard', 'dashboard.php');
?>

<div class="breadcrumb">
    <i class="fa-solid fa-gauge"></i>
    <span>Dashboard</span>
</div>

<div class="admin-page-title">
    Hai, <?= htmlspecialchars(explode(' ', $nama)[0]) ?>! 👋
</div>
<div class="admin-page-sub">
    <?= getRoleLabel($role) ?> · <?= bulanNama($bulanIni) ?> <?= $tahunIni ?> · Selamat bertugas 🌿
</div>

<?php if ($pendingBayar > 0 && hasRole(['bendahara'])): ?>
    <div class="alert alert-warning" style="margin-bottom:20px;">
        <i class="fa-solid fa-bell"></i>
        <div>
            <strong><?= $pendingBayar ?> pembayaran menunggu verifikasi!</strong><br>
            <a href="verifikasi.php" style="font-size:13px;color:inherit;">Klik untuk verifikasi sekarang →</a>
        </div>
    </div>
<?php endif; ?>

<!-- Stats -->
<div class="admin-stats">
    <div class="admin-stat-card">
        <div style="font-size:28px;margin-bottom:6px;">💰</div>
        <div class="stat-num" style="font-size:18px;"><?= formatRupiah($saldo) ?></div>
        <div class="stat-lbl">Saldo Kas RT</div>
    </div>
    <div class="admin-stat-card">
        <div style="font-size:28px;margin-bottom:6px;">👥</div>
        <div class="stat-num"><?= $totalWarga ?></div>
        <div class="stat-lbl">Total KK Aktif</div>
    </div>
    <?php if (hasRole(['bendahara'])): ?>
        <div class="admin-stat-card" style="cursor:pointer;" onclick="location='verifikasi.php'">
            <div style="font-size:28px;margin-bottom:6px;">⏳</div>
            <div class="stat-num"><?= $pendingBayar ?></div>
            <div class="stat-lbl">Menunggu Verifikasi</div>
        </div>
        <div class="admin-stat-card">
            <div style="font-size:28px;margin-bottom:6px;">✅</div>
            <div class="stat-num"><?= $jmlLunas ?>/<?= $jmlTagihan ?></div>
            <div class="stat-lbl">Lunas Bulan Ini</div>
        </div>
    <?php endif; ?>
    <div class="admin-stat-card">
        <div style="font-size:28px;margin-bottom:6px;">📅</div>
        <div class="stat-num"><?= $kegiatanMendatang ?></div>
        <div class="stat-lbl">Kegiatan Mendatang</div>
    </div>
</div>

<!-- Quick Actions sesuai role -->
<div class="form-card">
    <div class="form-card-title"><i class="fa-solid fa-bolt" style="color:var(--green-600)"></i> Aksi Cepat</div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
        <?php if (hasRole(['sekretaris'])): ?>
            <a href="kegiatan.php" class="btn btn-outline btn-sm">
                <i class="fa-solid fa-calendar-plus"></i> Tambah Kegiatan
            </a>
        <?php endif; ?>
        <?php if (hasRole(['bendahara'])): ?>
            <a href="verifikasi.php" class="btn btn-outline btn-sm">
                <i class="fa-solid fa-circle-check"></i> Verifikasi Bayar
            </a>
            <a href="jenis_iuran.php" class="btn btn-outline btn-sm">
                <i class="fa-solid fa-tags"></i> Buat Tagihan
            </a>
            <a href="laporan.php" class="btn btn-outline btn-sm">
                <i class="fa-solid fa-chart-bar"></i> Laporan Keuangan
            </a>
            <a href="kas.php" class="btn btn-outline btn-sm">
                <i class="fa-solid fa-minus-circle"></i> Catat Pengeluaran
            </a>
        <?php endif; ?>
        <?php if (hasRole(['ketua_rt'])): ?>
            <a href="warga.php" class="btn btn-outline btn-sm">
                <i class="fa-solid fa-house-user"></i> Data Warga
            </a>
            <a href="struktur_admin.php" class="btn btn-outline btn-sm">
                <i class="fa-solid fa-people-group"></i> Struktur Org
            </a>
        <?php endif; ?>
        <a href="<?= SITE_URL ?>/monitoring.php" target="_blank" class="btn btn-sm"
            style="background:var(--green-100);color:var(--green-800);">
            <i class="fa-solid fa-eye"></i> Lihat Monitoring
        </a>
    </div>
</div>

<!-- Pending Payments -->
<?php if (!empty($pembayaranPending) && hasRole(['bendahara'])): ?>
    <div class="admin-table-wrap" style="margin-bottom:20px;">
        <div class="admin-table-header">
            <h3><span class="pending-dot"></span> Pembayaran Pending</h3>
            <a href="verifikasi.php" class="btn btn-sm btn-primary">Lihat Semua</a>
        </div>
        <table>
            <thead>
                <tr>
                    <th>Warga</th>
                    <th>Iuran</th>
                    <th>Nominal</th>
                    <th>Waktu</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pembayaranPending as $p): ?>
                    <tr>
                        <td>
                            <div style="font-weight:700;"><?= htmlspecialchars($p['nama']) ?></div>
                            <div style="font-size:11px;color:var(--text-light);"><?= htmlspecialchars($p['nomor_rumah']) ?>
                            </div>
                        </td>
                        <td>
                            <div style="font-size:13px;"><?= htmlspecialchars($p['jenis_nama']) ?></div>
                            <div style="font-size:11px;color:var(--text-light);"><?= bulanNama($p['bulan']) ?>
                                <?= $p['tahun'] ?></div>
                        </td>
                        <td style="font-weight:700;color:var(--green-700);"><?= formatRupiah($p['nominal']) ?></td>
                        <td style="font-size:12px;color:var(--text-light);"><?= date('d/m H:i', strtotime($p['created_at'])) ?>
                        </td>
                        <td>
                            <a href="verifikasi.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-primary">
                                <i class="fa-solid fa-check"></i>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<!-- Kegiatan Mendatang -->
<?php if (!empty($kegiatan)): ?>
    <div class="form-card">
        <div class="form-card-title"><i class="fa-solid fa-calendar-days" style="color:var(--green-600)"></i> Kegiatan
            Mendatang</div>
        <?php foreach ($kegiatan as $k): ?>
            <div style="display:flex;align-items:flex-start;gap:14px;padding:10px 0;border-bottom:1px solid var(--border);">
                <div style="background:var(--green-100);border-radius:10px;padding:8px 12px;text-align:center;flex-shrink:0;">
                    <div style="font-size:16px;font-weight:800;color:var(--green-800);">
                        <?= date('d', strtotime($k['tanggal'])) ?></div>
                    <div style="font-size:10px;color:var(--green-600);font-weight:700;">
                        <?= strtoupper(substr(bulanNama(date('n', strtotime($k['tanggal']))), 0, 3)) ?></div>
                </div>
                <div style="flex:1;">
                    <div style="font-weight:700;font-size:14px;"><?= htmlspecialchars($k['judul']) ?></div>
                    <div style="font-size:12px;color:var(--text-light);"><?= htmlspecialchars($k['lokasi'] ?? '-') ?></div>
                </div>
                <?php if (hasRole(['sekretaris'])): ?>
                    <a href="kegiatan.php?edit=<?= $k['id'] ?>" class="btn btn-sm btn-outline"><i class="fa-solid fa-pen"></i></a>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php adminFooter(); ?>