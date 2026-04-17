<?php
// ============================================================
// konfirmasi.php — Halaman Konfirmasi Setelah Bayar
// Portal Warga RT 005 RW 012 GMR 8
// ============================================================
require_once 'includes/db.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$pageTitle = 'Pembayaran Terkirim!';

$tagihanId = isset($_GET['tagihan_id']) ? (int)$_GET['tagihan_id'] : 0;
$tagihan = null;

if ($tagihanId) {
    $stmt = $pdo->prepare("
        SELECT t.*, w.nama, w.nomor_rumah, j.nama as jenis_nama
        FROM tagihan t
        JOIN warga w ON w.id = t.warga_id
        JOIN jenis_iuran j ON j.id = t.jenis_iuran_id
        WHERE t.id = ?
    ");
    $stmt->execute([$tagihanId]);
    $tagihan = $stmt->fetch();
}

require_once 'includes/header.php';
?>

<div class="container">
    <div class="success-page mt-3">
        <div class="success-icon">
            <i class="fa-solid fa-check"></i>
        </div>

        <h1 style="font-size:22px;margin-bottom:8px;color:var(--green-800);">
            Terima kasih, ya! 🌿
        </h1>
        <p style="font-size:15px;color:var(--text-mid);line-height:1.7;margin-bottom:24px;">
            Bukti pembayaran udah kita terima. Bendahara akan segera memverifikasi dalam <strong>1×24 jam</strong>.
            Kontribusimu sangat berarti buat lingkungan GMR 8 kita! 🙏
        </p>

        <?php if ($tagihan): ?>
        <div class="card" style="text-align:left;margin-bottom:24px;">
            <h3 style="font-size:15px;margin-bottom:12px;color:var(--green-800);">📋 Ringkasan Pembayaran</h3>
            <div style="display:flex;flex-direction:column;gap:8px;">
                <div class="flex-between">
                    <span style="font-size:13px;color:var(--text-light);">Nama</span>
                    <span style="font-weight:700;font-size:14px;"><?= htmlspecialchars($tagihan['nama']) ?></span>
                </div>
                <div class="divider" style="margin:2px 0;"></div>
                <div class="flex-between">
                    <span style="font-size:13px;color:var(--text-light);">Jenis Iuran</span>
                    <span style="font-weight:700;font-size:14px;"><?= htmlspecialchars($tagihan['jenis_nama']) ?></span>
                </div>
                <div class="divider" style="margin:2px 0;"></div>
                <div class="flex-between">
                    <span style="font-size:13px;color:var(--text-light);">Periode</span>
                    <span style="font-weight:700;font-size:14px;"><?= bulanNama($tagihan['bulan']) ?> <?= $tagihan['tahun'] ?></span>
                </div>
                <div class="divider" style="margin:2px 0;"></div>
                <div class="flex-between">
                    <span style="font-size:13px;color:var(--text-light);">Nominal</span>
                    <span style="font-weight:800;font-size:16px;color:var(--green-700);"><?= formatRupiah($tagihan['nominal']) ?></span>
                </div>
                <div class="divider" style="margin:2px 0;"></div>
                <div class="flex-between">
                    <span style="font-size:13px;color:var(--text-light);">Status</span>
                    <span class="badge badge-yellow">⏳ Menunggu Verifikasi</span>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="alert alert-info" style="text-align:left;margin-bottom:24px;">
            <i class="fa-solid fa-circle-info"></i>
            <div>
                <strong>Apa selanjutnya?</strong><br>
                <span style="font-size:13px;">Bendahara akan mengecek bukti dan rekening bank. Setelah terverifikasi, status iuranmu akan berubah jadi ✅ Lunas. Kamu bisa cek di halaman monitoring 😊</span>
            </div>
        </div>

        <div style="display:flex;flex-direction:column;gap:12px;">
            <a href="<?= SITE_URL ?>/monitoring/" class="btn btn-primary">
                <i class="fa-solid fa-chart-line"></i>
                Cek Monitoring Kas
            </a>
            <a href="<?= SITE_URL ?>/iuran/" class="btn btn-outline">
                <i class="fa-solid fa-leaf"></i>
                Kembali ke Halaman Iuran
            </a>
            <a href="<?= SITE_URL ?>/" class="btn" style="background:transparent;color:var(--text-light);font-size:14px;">
                <i class="fa-solid fa-house"></i>
                Ke Beranda
            </a>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
