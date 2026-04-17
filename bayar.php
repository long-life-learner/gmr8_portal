<?php
// ============================================================
// bayar.php — Form Upload Bukti Bayar
// Portal Warga RT 005 RW 012 GMR 8
// ============================================================
require_once 'includes/db.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$pageTitle = 'Upload Bukti Bayar';

// Validasi tagihan_id
$tagihanId = isset($_GET['tagihan_id']) ? (int)$_GET['tagihan_id'] : 0;
if (!$tagihanId) {
    header('Location: ' . SITE_URL . '/iuran/');
    exit;
}

// Ambil data tagihan + warga + jenis iuran
$stmt = $pdo->prepare("
    SELECT t.*, w.nama, w.nomor_rumah, w.no_wa, j.nama as jenis_nama, j.deskripsi as jenis_desc
    FROM tagihan t
    JOIN warga w ON w.id = t.warga_id
    JOIN jenis_iuran j ON j.id = t.jenis_iuran_id
    WHERE t.id = ?
");
$stmt->execute([$tagihanId]);
$tagihan = $stmt->fetch();

if (!$tagihan) {
    header('Location: ' . SITE_URL . '/iuran/');
    exit;
}

// Jika sudah bayar / menunggu verifikasi
if ($tagihan['status'] === 'lunas') {
    header('Location: ' . SITE_URL . '/iuran/?msg=sudah_lunas');
    exit;
}

$error = '';
$sudahPending = $tagihan['status'] === 'menunggu_verifikasi';

// ============================================================
// PROSES SUBMIT PEMBAYARAN
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$sudahPending) {
    $catatan = trim($_POST['catatan'] ?? '');

    // Validasi file upload
    if (empty($_FILES['bukti_bayar']['tmp_name'])) {
        $error = 'Upload bukti transfer dulu ya! 📸';
    } else {
        $file = $_FILES['bukti_bayar'];
        $allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        $maxSize = 5 * 1024 * 1024; // 5MB

        // Deteksi MIME type real
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $realType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($realType, $allowedTypes)) {
            $error = 'File harus berupa gambar (JPG, PNG, atau WebP) ya!';
        } elseif ($file['size'] > $maxSize) {
            $error = 'Ukuran file terlalu besar. Maksimal 5MB ya!';
        } else {
            // Simpan file
            $uploadDir = UPLOAD_PATH . 'bukti/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

            $ext = pathinfo($file['name'], PATHINFO_EXTENSION) ?: 'jpg';
            $filename = 'bayar_' . $tagihanId . '_' . time() . '.' . strtolower($ext);
            $destPath = $uploadDir . $filename;

            if (!move_uploaded_file($file['tmp_name'], $destPath)) {
                $error = 'Gagal upload file. Coba lagi ya!';
            } else {
                try {
                    $pdo->beginTransaction();

                    // Simpan pembayaran
                    $stmtPay = $pdo->prepare("
                        INSERT INTO pembayaran (tagihan_id, bukti_bayar, catatan, status)
                        VALUES (?, ?, ?, 'pending')
                    ");
                    $stmtPay->execute([$tagihanId, $filename, $catatan]);

                    // Update status tagihan
                    $pdo->prepare("UPDATE tagihan SET status='menunggu_verifikasi' WHERE id=?")->execute([$tagihanId]);

                    $pdo->commit();
                    header('Location: ' . SITE_URL . '/konfirmasi/?tagihan_id=' . $tagihanId);
                    exit;
                } catch (PDOException $e) {
                    $pdo->rollBack();
                    if (file_exists($destPath)) unlink($destPath);
                    $error = 'Terjadi kesalahan. Coba lagi ya!';
                }
            }
        }
    }
}

require_once 'includes/header.php';
?>

<!-- Back Button -->
<div class="container" style="padding-top:16px;">
    <a href="<?= SITE_URL ?>/iuran/" style="display:inline-flex;align-items:center;gap:6px;font-size:14px;font-weight:700;color:var(--green-700);text-decoration:none;">
        <i class="fa-solid fa-arrow-left"></i> Kembali ke Daftar
    </a>
</div>

<div class="container">
    <div class="section" style="padding-top:12px;">

        <!-- Konfirmasi Data -->
        <div class="card" style="background:linear-gradient(135deg,var(--green-50),#fff);border-color:var(--green-300);margin-bottom:20px;">
            <h2 style="font-size:17px;margin-bottom:14px;color:var(--green-900);">
                <i class="fa-solid fa-receipt" style="color:var(--green-600)"></i> Konfirmasi Data
            </h2>
            <div style="display:flex;flex-direction:column;gap:10px;">
                <div class="flex-between">
                    <span style="font-size:13px;color:var(--text-light);">Nama Warga</span>
                    <span style="font-weight:700;font-size:14px;"><?= htmlspecialchars($tagihan['nama']) ?></span>
                </div>
                <div class="divider" style="margin:2px 0;"></div>
                <div class="flex-between">
                    <span style="font-size:13px;color:var(--text-light);">Nomor Rumah</span>
                    <span style="font-weight:700;font-size:14px;"><?= htmlspecialchars($tagihan['nomor_rumah']) ?></span>
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
                    <span style="font-weight:800;font-size:18px;color:var(--green-700);"><?= formatRupiah($tagihan['nominal']) ?></span>
                </div>
            </div>
        </div>

        <?php if ($sudahPending): ?>
        <!-- Sudah upload, menunggu verifikasi -->
        <div class="alert alert-warning" style="margin-bottom:20px;">
            <i class="fa-solid fa-clock-rotate-left"></i>
            <div>
                <strong>Pembayaran sedang diverifikasi</strong><br>
                <span style="font-size:12px;">Bukti sudah diterima bendahara. Tunggu konfirmasi ya, biasanya 1x24 jam! 🙏</span>
            </div>
        </div>
        <a href="<?= SITE_URL ?>/iuran/" class="btn btn-outline btn-block">
            <i class="fa-solid fa-arrow-left"></i> Kembali ke Daftar Iuran
        </a>

        <?php else: ?>
        <!-- Info Transfer -->
        <div class="card" style="border-left:4px solid var(--green-600);padding:16px;">
            <h3 style="font-size:15px;margin-bottom:12px;">🏦 Transfer ke Rekening Ini</h3>
            <div style="display:flex;flex-direction:column;gap:8px;">
                <div class="flex-between">
                    <span style="font-size:13px;color:var(--text-light);">Bank</span>
                    <span style="font-weight:700;font-size:15px;"><?= BANK_NAMA ?></span>
                </div>
                <div class="flex-between" onclick="navigator.clipboard.writeText('<?= BANK_NOMOR ?>').then(()=>showToast('✅ Nomor rekening disalin!'))" style="cursor:pointer;padding:10px;background:var(--green-50);border-radius:8px;border:1.5px dashed var(--green-300);">
                    <span style="font-size:13px;color:var(--text-light);">Nomor Rekening</span>
                    <span style="font-weight:800;font-size:16px;color:var(--green-800);letter-spacing:1px;"><?= BANK_NOMOR ?> <i class="fa-regular fa-copy" style="font-size:13px;"></i></span>
                </div>
                <div class="flex-between">
                    <span style="font-size:13px;color:var(--text-light);">A/N</span>
                    <span style="font-weight:700;font-size:14px;"><?= BANK_ATAS_NAMA ?></span>
                </div>
                <div class="flex-between">
                    <span style="font-size:13px;color:var(--text-light);">Nominal Transfer</span>
                    <span style="font-weight:800;font-size:15px;color:var(--green-700);"><?= formatRupiah($tagihan['nominal']) ?></span>
                </div>
            </div>
            <div class="alert alert-info mt-2" style="margin-bottom:0;">
                <i class="fa-solid fa-lightbulb"></i>
                <span style="font-size:12px;">Klik nomor rekening di atas untuk salin otomatis. Setelah transfer, screenshot buktinya lalu upload di bawah ya!</span>
            </div>
        </div>

        <!-- Form Upload -->
        <?php if ($error): ?>
        <div class="alert alert-danger mt-2">
            <i class="fa-solid fa-circle-exclamation"></i>
            <span><?= htmlspecialchars($error) ?></span>
        </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data" id="form-bayar">
            <div class="form-group mt-2">
                <label class="form-label">📸 Screenshot Bukti Transfer <span class="required">*</span></label>
                <div class="upload-zone" id="upload-zone">
                    <input type="file" name="bukti_bayar" id="bukti_bayar" accept="image/*" capture="environment" required>
                    <i class="fa-solid fa-cloud-arrow-up"></i>
                    <p>Tap di sini untuk upload / foto bukti transfer</p>
                    <span>JPG, PNG, WebP • Maksimal 5 MB</span>
                    <div class="upload-preview">
                        <img src="" alt="Preview">
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="catatan">📝 Catatan (opsional)</label>
                <textarea id="catatan" name="catatan" class="form-control" rows="2" placeholder="Misal: sudah transfer jam 10 pagi via mobile banking..."></textarea>
            </div>

            <button type="submit" class="btn btn-primary btn-block" id="btn-submit" style="font-size:16px;padding:16px;">
                <i class="fa-solid fa-paper-plane"></i>
                Kirim Bukti Bayar
            </button>
        </form>

        <p style="text-align:center;font-size:12px;color:var(--text-light);margin-top:12px;">
            Bendahara akan verifikasi dalam 1x24 jam. Terima kasih! 🌿
        </p>
        <?php endif; ?>

    </div>
</div>

<?php
$extraScript = "<script>
document.getElementById('form-bayar')?.addEventListener('submit', function(e){
    const btn = document.getElementById('btn-submit');
    btn.innerHTML = '<span class=\"loader\"></span> Mengirim...';
    btn.disabled = true;
});
</script>";
require_once 'includes/footer.php'; ?>
