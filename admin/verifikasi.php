<?php
// ============================================================
// admin/verifikasi.php — Verifikasi Pembayaran (Bendahara)
// Portal Warga RT 005 RW 012 GMR 8
// ============================================================
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once 'includes/admin_layout.php';
requireLogin();
requireRole(['bendahara']);

$msg = '';
$msgType = 'success';

// ============================================================
// PROSES VERIFIKASI / TOLAK
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pembayaranId = (int)($_POST['pembayaran_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    $catatanAdmin = trim($_POST['catatan_admin'] ?? '');

    if ($pembayaranId && in_array($action, ['verify','reject'])) {
        $stmt = $pdo->prepare("
            SELECT p.*, t.nominal, t.bulan, t.tahun, t.warga_id, t.jenis_iuran_id,
                   w.nama, j.nama as jenis_nama
            FROM pembayaran p
            JOIN tagihan t ON t.id = p.tagihan_id
            JOIN warga w ON w.id = t.warga_id
            JOIN jenis_iuran j ON j.id = t.jenis_iuran_id
            WHERE p.id = ? AND p.status = 'pending'
        ");
        $stmt->execute([$pembayaranId]);
        $bayar = $stmt->fetch();

        if ($bayar) {
            try {
                $pdo->beginTransaction();

                if ($action === 'verify') {
                    // Update pembayaran
                    $pdo->prepare("UPDATE pembayaran SET status='verified', verified_by=?, verified_at=NOW(), catatan_admin=? WHERE id=?")
                        ->execute([getUserId(), $catatanAdmin, $pembayaranId]);
                    // Update tagihan
                    $pdo->prepare("UPDATE tagihan SET status='lunas' WHERE id=?")
                        ->execute([$bayar['tagihan_id']]);
                    // Tambah mutasi kas
                    $keterangan = "IPL/Kas {$bayar['jenis_nama']} - {$bayar['nama']} (" . bulanNama($bayar['bulan']) . " {$bayar['tahun']})";
                    $pdo->prepare("INSERT INTO kas (tipe, jumlah, keterangan, pembayaran_id, tanggal, created_by) VALUES ('masuk', ?, ?, ?, CURDATE(), ?)")
                        ->execute([$bayar['nominal'], $keterangan, $pembayaranId, getUserId()]);
                    $msg = "✅ Pembayaran {$bayar['nama']} berhasil diverifikasi! Saldo kas sudah diperbarui.";
                } else {
                    // Tolak
                    $pdo->prepare("UPDATE pembayaran SET status='rejected', verified_by=?, verified_at=NOW(), catatan_admin=? WHERE id=?")
                        ->execute([getUserId(), $catatanAdmin, $pembayaranId]);
                    $pdo->prepare("UPDATE tagihan SET status='belum_bayar' WHERE id=?")
                        ->execute([$bayar['tagihan_id']]);
                    $msg = "❌ Pembayaran {$bayar['nama']} ditolak. Warga perlu upload ulang bukti.";
                    $msgType = 'warning';
                }

                $pdo->commit();
            } catch (PDOException $e) {
                $pdo->rollBack();
                $msg = "Terjadi error: " . $e->getMessage();
                $msgType = 'danger';
            }
        }
    }
}

// Filter
$filterStatus = $_GET['status'] ?? 'pending';
$allowedStatus = ['pending', 'verified', 'rejected', 'all'];
if (!in_array($filterStatus, $allowedStatus)) $filterStatus = 'pending';

$where = $filterStatus === 'all' ? '' : "WHERE p.status = '$filterStatus'";

$pembayaran = $pdo->query("
    SELECT p.*, t.nominal, t.bulan, t.tahun,
           w.nama, w.nomor_rumah, j.nama as jenis_nama,
           pg.nama as verifikator_nama
    FROM pembayaran p
    JOIN tagihan t ON t.id = p.tagihan_id
    JOIN warga w ON w.id = t.warga_id
    JOIN jenis_iuran j ON j.id = t.jenis_iuran_id
    LEFT JOIN pengurus pg ON pg.id = p.verified_by
    $where
    ORDER BY p.created_at DESC
    LIMIT 100
")->fetchAll();

$pendingCount = $pdo->query("SELECT COUNT(*) FROM pembayaran WHERE status='pending'")->fetchColumn();

adminHeader('Verifikasi Pembayaran', 'verifikasi.php');
?>

<div class="breadcrumb">
    <a href="../dashboard/">Dashboard</a>
    <span>›</span>
    <span>Verifikasi Pembayaran</span>
</div>
<div class="admin-page-title">✅ Verifikasi Pembayaran</div>
<div class="admin-page-sub">Periksa bukti transfer dan konfirmasi pembayaran warga</div>

<?php if ($msg): ?>
<div class="alert alert-<?= $msgType ?>" style="margin-bottom:16px;">
    <i class="fa-solid fa-circle-check"></i>
    <span><?= htmlspecialchars($msg) ?></span>
</div>
<?php endif; ?>

<!-- Filter tabs -->
<div style="display:flex;gap:8px;margin-bottom:20px;flex-wrap:wrap;">
    <?php
    $tabs = ['pending'=>"⏳ Pending ($pendingCount)",'verified'=>'✅ Terverifikasi','rejected'=>'❌ Ditolak','all'=>'Semua'];
    foreach ($tabs as $val => $lbl): ?>
    <a href="?status=<?= $val ?>" class="btn btn-sm <?= $filterStatus===$val?'btn-primary':'btn-outline' ?>" style="text-decoration:none;">
        <?= $lbl ?>
    </a>
    <?php endforeach; ?>
</div>

<!-- Daftar Pembayaran -->
<?php if (empty($pembayaran)): ?>
<div style="text-align:center;padding:48px 20px;background:#fff;border-radius:var(--radius-md);border:1px solid var(--border);">
    <div style="font-size:48px;margin-bottom:12px;"><?= $filterStatus==='pending'?'🎉':'📋' ?></div>
    <h3 style="color:var(--green-700);"><?= $filterStatus==='pending'?'Tidak ada yang pending!':'Belum ada data' ?></h3>
    <p style="font-size:14px;color:var(--text-light);"><?= $filterStatus==='pending'?'Semua pembayaran sudah diproses. Kerja bagus! 💚':'-' ?></p>
</div>
<?php else: ?>
<div style="display:flex;flex-direction:column;gap:16px;">
    <?php foreach ($pembayaran as $p): ?>
    <div class="form-card" style="padding:0;overflow:hidden;border-radius:var(--radius-md);">
        <!-- Card Header -->
        <div style="background:<?= $p['status']==='pending'?'#fff8e1':($p['status']==='verified'?'var(--green-50)':'#fce4ec') ?>;padding:14px 18px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
            <div style="flex:1;">
                <div style="font-weight:800;font-size:15px;"><?= htmlspecialchars($p['nama']) ?></div>
                <div style="font-size:12px;color:var(--text-light);">
                    <?= htmlspecialchars($p['nomor_rumah']) ?> &bull;
                    <?= htmlspecialchars($p['jenis_nama']) ?> &bull;
                    <?= bulanNama($p['bulan']) ?> <?= $p['tahun'] ?>
                </div>
            </div>
            <div style="text-align:right;">
                <div style="font-weight:800;font-size:16px;color:var(--green-700);"><?= formatRupiah($p['nominal']) ?></div>
                <span class="badge <?= $p['status']==='pending'?'badge-yellow':($p['status']==='verified'?'badge-green':'badge-red') ?>">
                    <?= ['pending'=>'⏳ Pending','verified'=>'✅ Lunas','rejected'=>'❌ Ditolak'][$p['status']] ?>
                </span>
            </div>
        </div>

        <!-- Card Body -->
        <div style="padding:16px 18px;">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;align-items:start;">
                <!-- Bukti bayar -->
                <div>
                    <div style="font-size:12px;font-weight:700;color:var(--text-light);margin-bottom:8px;">📸 BUKTI TRANSFER</div>
                    <?php if ($p['bukti_bayar'] && file_exists('../assets/uploads/bukti/' . $p['bukti_bayar'])): ?>
                    <a href="<?= SITE_URL ?>/assets/uploads/bukti/<?= htmlspecialchars($p['bukti_bayar']) ?>" target="_blank">
                        <img src="<?= SITE_URL ?>/assets/uploads/bukti/<?= htmlspecialchars($p['bukti_bayar']) ?>"
                             alt="Bukti Bayar"
                             style="width:100%;max-height:180px;object-fit:cover;border-radius:10px;border:2px solid var(--border);cursor:zoom-in;">
                    </a>
                    <div style="font-size:11px;color:var(--text-light);margin-top:4px;text-align:center;">Tap untuk perbesar</div>
                    <?php else: ?>
                    <div style="background:var(--green-50);border-radius:10px;height:100px;display:flex;align-items:center;justify-content:center;color:var(--text-light);font-size:13px;">
                        Tidak ada bukti
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Info -->
                <div>
                    <div style="font-size:12px;font-weight:700;color:var(--text-light);margin-bottom:8px;">📋 DETAIL</div>
                    <div style="font-size:13px;color:var(--text-mid);line-height:1.8;">
                        <div>Upload: <?= date('d M Y H:i', strtotime($p['created_at'])) ?></div>
                        <?php if ($p['catatan']): ?>
                        <div style="margin-top:6px;background:var(--green-50);border-radius:8px;padding:8px;font-size:12px;">
                            💬 "<?= htmlspecialchars($p['catatan']) ?>"
                        </div>
                        <?php endif; ?>
                        <?php if ($p['verified_at']): ?>
                        <div style="margin-top:6px;color:var(--green-700);">
                            Diproses oleh <?= htmlspecialchars($p['verifikator_nama'] ?? '-') ?>
                        </div>
                        <?php endif; ?>
                        <?php if ($p['catatan_admin']): ?>
                        <div style="margin-top:6px;background:#fff8e1;border-radius:8px;padding:8px;font-size:12px;">
                            📌 Admin: "<?= htmlspecialchars($p['catatan_admin']) ?>"
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Action buttons -->
            <?php if ($p['status'] === 'pending'): ?>
            <div style="margin-top:16px;padding-top:14px;border-top:1px solid var(--border);">
                <div style="display:flex;gap:10px;flex-wrap:wrap;">
                    <form method="POST" style="flex:1;">
                        <input type="hidden" name="pembayaran_id" value="<?= $p['id'] ?>">
                        <input type="hidden" name="action" value="verify">
                        <input type="text" name="catatan_admin" class="form-control" style="margin-bottom:8px;font-size:13px;" placeholder="Catatan (opsional)...">
                        <button type="submit" class="btn btn-primary btn-block btn-sm" onclick="return confirm('Konfirmasi verifikasi pembayaran <?= htmlspecialchars(addslashes($p['nama'])) ?>?')">
                            <i class="fa-solid fa-circle-check"></i> Verifikasi & Catat Kas
                        </button>
                    </form>
                    <form method="POST" style="flex:1;">
                        <input type="hidden" name="pembayaran_id" value="<?= $p['id'] ?>">
                        <input type="hidden" name="action" value="reject">
                        <input type="text" name="catatan_admin" class="form-control" style="margin-bottom:8px;font-size:13px;" placeholder="Alasan penolakan...">
                        <button type="submit" class="btn btn-danger btn-block btn-sm" onclick="return confirm('Tolak pembayaran ini? Warga perlu upload ulang.')">
                            <i class="fa-solid fa-xmark"></i> Tolak
                        </button>
                    </form>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php adminFooter(); ?>
