<?php
// ============================================================
// admin/cctv.php — Kelola Link CCTV Monitoring
// ============================================================
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once 'includes/admin_layout.php';
requireLogin();
// Sesuai permintaan, fitur CRUD ini bisa dikelola oleh sekretaris atau admin
requireRole(['sekretaris']);

$msg = ''; $msgType = 'success';

// HAPUS
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $pdo->prepare("DELETE FROM cctv WHERE id=?")->execute([$id]);
    $msg = '🗑️ CCTV berhasil dihapus.'; $msgType = 'warning';
}

// SIMPAN
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    $lokasi = trim($_POST['lokasi'] ?? '');
    $url_m3u8 = trim($_POST['url_m3u8'] ?? '');
    $tipe = $_POST['tipe'] ?? 'ATCS';
    $status = $_POST['status'] ?? 'Aktif';

    if (!$lokasi || !$url_m3u8) {
        $msg = 'Lokasi dan URL m3u8 wajib diisi!'; $msgType = 'danger';
    } else {
        if ($id) {
            $pdo->prepare("UPDATE cctv SET lokasi=?, url_m3u8=?, tipe=?, status=? WHERE id=?")
                ->execute([$lokasi, $url_m3u8, $tipe, $status, $id]);
            $msg = "✅ CCTV di '$lokasi' berhasil diperbarui!";
        } else {
            $pdo->prepare("INSERT INTO cctv (lokasi, url_m3u8, tipe, status) VALUES (?, ?, ?, ?)")
                ->execute([$lokasi, $url_m3u8, $tipe, $status]);
            $msg = "✅ CCTV di '$lokasi' berhasil ditambahkan!";
        }
    }
}

$editData = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM cctv WHERE id=?");
    $stmt->execute([(int)$_GET['edit']]);
    $editData = $stmt->fetch();
}

$cctvList = $pdo->query("SELECT * FROM cctv ORDER BY created_at DESC")->fetchAll();

adminHeader('Kelola CCTV', 'cctv.php');
?>

<div class="breadcrumb"><a href="dashboard.php">Dashboard</a><span>›</span><span>Kelola CCTV</span></div>
<div class="admin-page-title">📹 Kelola Monitoring CCTV</div>
<div class="admin-page-sub">Tambahkan link streaming .m3u8 untuk dipantau oleh warga.</div>

<?php if ($msg): ?>
<div class="alert alert-<?=$msgType?>" style="margin-bottom:16px;">
    <i class="fa-solid fa-circle-info"></i><span><?=htmlspecialchars($msg)?></span>
</div>
<?php endif; ?>

<!-- Form Tambah/Edit -->
<div class="form-card">
    <div class="form-card-title">
        <i class="fa-solid fa-video" style="color:var(--green-600)"></i>
        <?=$editData?'Edit CCTV':'Tambah CCTV Baru'?>
    </div>
    <form method="POST">
        <input type="hidden" name="id" value="<?=$editData['id']??0?>">

        <div class="form-group">
            <label class="form-label">Nama Lokasi <span class="required">*</span></label>
            <input type="text" name="lokasi" class="form-control" value="<?=htmlspecialchars($editData['lokasi']??'')?>" placeholder="Contoh: Perempatan Jalan Utama" required>
        </div>

        <div class="form-group">
            <label class="form-label">URL Streaming .m3u8 <span class="required">*</span></label>
            <input type="url" name="url_m3u8" class="form-control" value="<?=htmlspecialchars($editData['url_m3u8']??'')?>" placeholder="https://example.com/live/stream.m3u8" required>
            <div style="font-size:12px;color:#888;margin-top:4px;">Pastikan link berakhiran .m3u8 (HLS Streaming).</div>
        </div>

        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:16px;">
            <div class="form-group">
                <label class="form-label">Tipe CCTV</label>
                <select name="tipe" class="form-control">
                    <option value="ATCS" <?=($editData['tipe']??'')==='ATCS'?'selected':''?>>ATCS (Dishub)</option>
                    <option value="Internal" <?=($editData['tipe']??'')==='Internal'?'selected':''?>>Internal (Perumahan)</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Status</label>
                <select name="status" class="form-control">
                    <option value="Aktif" <?=($editData['status']??'')==='Aktif'?'selected':''?>>Aktif</option>
                    <option value="Nonaktif" <?=($editData['status']??'')==='Nonaktif'?'selected':''?>>Nonaktif (Offline)</option>
                </select>
            </div>
        </div>

        <div style="display:flex;gap:10px;margin-top:20px;">
            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save"></i> <?=$editData?'Update':'Tambah CCTV'?></button>
            <?php if ($editData): ?><a href="cctv.php" class="btn btn-outline">Batal</a><?php endif; ?>
        </div>
    </form>
</div>

<!-- Daftar CCTV -->
<div class="admin-table-wrap">
    <div class="admin-table-header">
        <h3>Daftar CCTV (<?=count($cctvList)?>)</h3>
    </div>
    <table>
        <thead><tr><th>Lokasi</th><th>Tipe</th><th>Status</th><th>Aksi</th></tr></thead>
        <tbody>
            <?php if (empty($cctvList)): ?>
                <tr><td colspan="4" style="text-align:center;padding:20px;color:#888;">Belum ada data CCTV.</td></tr>
            <?php endif; ?>
            <?php foreach ($cctvList as $c): ?>
            <tr>
                <td>
                    <div style="font-weight:700;font-size:14px;"><?=htmlspecialchars($c['lokasi'])?></div>
                    <div style="font-size:11px;color:#888;word-break:break-all;"><?=htmlspecialchars($c['url_m3u8'])?></div>
                </td>
                <td><span class="badge <?= $c['tipe']==='ATCS'?'badge-blue':'badge-green' ?>"><?= $c['tipe'] ?></span></td>
                <td><span class="badge <?= $c['status']==='Aktif'?'badge-green':'badge-red' ?>"><?= $c['status'] ?></span></td>
                <td>
                    <div style="display:flex;gap:6px;">
                        <a href="?edit=<?=$c['id']?>" class="btn btn-sm btn-outline" title="Edit"><i class="fa-solid fa-pen"></i></a>
                        <a href="?delete=<?=$c['id']?>" class="btn btn-sm btn-danger" onclick="return confirm('Hapus CCTV ini?')" title="Hapus"><i class="fa-solid fa-trash"></i></a>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php adminFooter(); ?>
