<?php
// ============================================================
// admin/warga.php — Kelola Data Warga (Ketua RT / Admin)
// ============================================================
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once 'includes/admin_layout.php';
requireLogin();
requireRole(['ketua_rt']);

$msg = ''; $msgType = 'success';

// HAPUS
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    // Cek apakah warga punya tagihan
    $cek = $pdo->prepare("SELECT COUNT(*) FROM tagihan WHERE warga_id=?");
    $cek->execute([$id]);
    if ($cek->fetchColumn() > 0) {
        $pdo->prepare("UPDATE warga SET aktif=0 WHERE id=?")->execute([$id]);
        $msg = '⚠️ Warga dinonaktifkan (memiliki riwayat tagihan).'; $msgType = 'warning';
    } else {
        $pdo->prepare("DELETE FROM warga WHERE id=?")->execute([$id]);
        $msg = '🗑️ Data warga berhasil dihapus.'; $msgType = 'warning';
    }
}

// TOGGLE AKTIF
if (isset($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];
    $pdo->prepare("UPDATE warga SET aktif = NOT aktif WHERE id=?")->execute([$id]);
    header('Location: ../warga/'); exit;
}

// SIMPAN
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    $nama = trim($_POST['nama'] ?? '');
    $nomorRumah = trim($_POST['nomor_rumah'] ?? '');
    $noWa = trim($_POST['no_wa'] ?? '');
    $aktif = isset($_POST['aktif']) ? 1 : 0;

    if (!$nama || !$nomorRumah) {
        $msg = 'Nama dan nomor rumah wajib diisi!'; $msgType = 'danger';
    } else {
        if ($id) {
            $pdo->prepare("UPDATE warga SET nama=?,nomor_rumah=?,no_wa=?,aktif=? WHERE id=?")
                ->execute([$nama,$nomorRumah,$noWa,$aktif,$id]);
            $msg = "✅ Data warga '$nama' berhasil diperbarui!";
        } else {
            $pdo->prepare("INSERT INTO warga (nama,nomor_rumah,no_wa,aktif) VALUES (?,?,?,1)")
                ->execute([$nama,$nomorRumah,$noWa]);
            $msg = "✅ Warga '$nama' berhasil ditambahkan!";
        }
    }
}

$editData = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM warga WHERE id=?");
    $stmt->execute([(int)$_GET['edit']]);
    $editData = $stmt->fetch();
}

$searchQ = $_GET['q'] ?? '';
if ($searchQ) {
    $stmt = $pdo->prepare("SELECT * FROM warga WHERE (nama LIKE ? OR nomor_rumah LIKE ?) ORDER BY nomor_rumah ASC");
    $stmt->execute(["%$searchQ%","%$searchQ%"]);
    $wargas = $stmt->fetchAll();
} else {
    $wargas = $pdo->query("SELECT * FROM warga ORDER BY CAST(SUBSTRING(nomor_rumah, 6) AS UNSIGNED) ASC")->fetchAll();
}

$totalAktif = $pdo->query("SELECT COUNT(*) FROM warga WHERE aktif=1")->fetchColumn();

adminHeader('Data Warga', 'warga.php');
?>

<div class="breadcrumb"><a href="../dashboard/">Dashboard</a><span>›</span><span>Data Warga</span></div>
<div class="admin-page-title">🏘️ Data Warga RT 005</div>
<div class="admin-page-sub"><?=$totalAktif?> KK aktif · Data dikelola pengurus agar valid</div>

<?php if ($msg): ?>
<div class="alert alert-<?=$msgType?>" style="margin-bottom:16px;"><i class="fa-solid fa-circle-info"></i><span><?=htmlspecialchars($msg)?></span></div>
<?php endif; ?>

<!-- Form Tambah/Edit -->
<div class="form-card">
    <div class="form-card-title"><i class="fa-solid fa-user-plus" style="color:var(--green-600)"></i> <?=$editData?'Edit Data Warga':'Tambah Warga Baru'?></div>
    <form method="POST">
        <input type="hidden" name="id" value="<?=$editData['id']??0?>">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
            <div class="form-group" style="grid-column:1/-1;">
                <label class="form-label">Nama Kepala Keluarga <span class="required">*</span></label>
                <input type="text" name="nama" class="form-control" value="<?=htmlspecialchars($editData['nama']??'')?>" placeholder="Nama lengkap warga..." required>
            </div>
            <div class="form-group">
                <label class="form-label">Nomor Rumah <span class="required">*</span></label>
                <input type="text" name="nomor_rumah" class="form-control" value="<?=htmlspecialchars($editData['nomor_rumah']??'')?>" placeholder="GMR8-01" required>
            </div>
            <div class="form-group">
                <label class="form-label">No. WhatsApp</label>
                <input type="text" name="no_wa" class="form-control" value="<?=htmlspecialchars($editData['no_wa']??'')?>" placeholder="08xxxxxxxxxx">
            </div>
            <?php if ($editData): ?>
            <div class="form-group" style="grid-column:1/-1;display:flex;align-items:center;gap:10px;">
                <input type="checkbox" id="aktif" name="aktif" value="1" <?=$editData['aktif']?'checked':''?>>
                <label for="aktif" style="font-weight:600;cursor:pointer;">Warga Aktif (centang = aktif)</label>
            </div>
            <?php endif; ?>
        </div>
        <div style="display:flex;gap:10px;">
            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save"></i> <?=$editData?'Update':'Simpan'?></button>
            <?php if ($editData): ?><a href="../warga/" class="btn btn-outline">Batal</a><?php endif; ?>
        </div>
    </form>
</div>

<!-- Search & Tabel -->
<div class="admin-table-wrap">
    <div class="admin-table-header">
        <h3>Daftar Warga (<?=count($wargas)?>)</h3>
        <form method="GET" style="display:flex;gap:8px;">
            <input type="text" name="q" class="form-control" style="max-width:200px;height:36px;font-size:13px;" placeholder="Cari nama / nomor..." value="<?=htmlspecialchars($searchQ)?>">
            <button type="submit" class="btn btn-sm btn-outline"><i class="fa-solid fa-search"></i></button>
        </form>
    </div>
    <table>
        <thead><tr><th>No. Rumah</th><th>Nama</th><th>No. WA</th><th>Status</th><th>Aksi</th></tr></thead>
        <tbody>
            <?php foreach ($wargas as $w): ?>
            <tr style="<?=$w['aktif']?'':'opacity:.5;'?>">
                <td style="font-weight:800;color:var(--green-700);"><?=htmlspecialchars($w['nomor_rumah'])?></td>
                <td style="font-weight:600;"><?=htmlspecialchars($w['nama'])?></td>
                <td style="font-size:12px;">
                    <?php if ($w['no_wa']): ?>
                    <a href="https://wa.me/62<?=ltrim($w['no_wa'],'0')?>" target="_blank" style="color:var(--green-700);">
                        <i class="fa-brands fa-whatsapp"></i> <?=htmlspecialchars($w['no_wa'])?>
                    </a>
                    <?php else: ?><span style="color:var(--text-light);">-</span><?php endif; ?>
                </td>
                <td>
                    <a href="?toggle=<?=$w['id']?>" class="badge <?=$w['aktif']?'badge-green':'badge-gray'?>" style="text-decoration:none;">
                        <?=$w['aktif']?'✅ Aktif':'⭕ Nonaktif'?>
                    </a>
                </td>
                <td>
                    <div style="display:flex;gap:6px;">
                        <a href="?edit=<?=$w['id']?>" class="btn btn-sm btn-outline"><i class="fa-solid fa-pen"></i></a>
                        <a href="?delete=<?=$w['id']?>" class="btn btn-sm btn-danger" onclick="return confirm('Hapus/nonaktifkan warga <?=htmlspecialchars(addslashes($w['nama']))?>?')"><i class="fa-solid fa-trash"></i></a>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php adminFooter(); ?>
