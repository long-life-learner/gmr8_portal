<?php
// ============================================================
// admin/tutorial.php — Kelola Tutorial Warga (Sekretaris)
// ============================================================
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once 'includes/admin_layout.php';
requireLogin();
requireRole(['sekretaris']);

$msg = ''; $msgType = 'success';
$uploadDir = __DIR__ . '/../assets/uploads/tutorial/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// HAPUS
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    
    // Cari foto untuk dihapus
    $stmt = $pdo->prepare("SELECT foto FROM tutorial WHERE id=?");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if ($row && $row['foto'] && file_exists($uploadDir . $row['foto'])) {
        unlink($uploadDir . $row['foto']);
    }

    $pdo->prepare("DELETE FROM tutorial WHERE id=?")->execute([$id]);
    $msg = '🗑️ Tutorial berhasil dihapus.'; $msgType = 'warning';
}

// SIMPAN
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    $judul = trim($_POST['judul'] ?? '');
    $konten = trim($_POST['konten'] ?? '');
    $youtube_url = trim($_POST['youtube_url'] ?? '');

    if (!$judul || !$konten) {
        $msg = 'Judul dan konten wajib diisi!'; $msgType = 'danger';
    } else {
        // Upload Foto
        $fotoName = '';
        if ($id) {
            $stmt = $pdo->prepare("SELECT foto FROM tutorial WHERE id=?");
            $stmt->execute([$id]);
            $fotoName = $stmt->fetchColumn();
        }

        if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
            $tmp = $_FILES['foto']['tmp_name'];
            $ext = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
                $newName = 'tut_' . time() . '_' . rand(100, 999) . '.' . $ext;
                if (move_uploaded_file($tmp, $uploadDir . $newName)) {
                    // Hapus foto lama bila ada
                    if ($fotoName && file_exists($uploadDir . $fotoName)) {
                        unlink($uploadDir . $fotoName);
                    }
                    $fotoName = $newName;
                }
            } else {
                $msg = "Format foto tidak didukung (harus JPG/PNG/WEBP)."; $msgType = 'danger';
            }
        }

        if ($msgType !== 'danger') {
            if ($id) {
                $pdo->prepare("UPDATE tutorial SET judul=?, konten=?, foto=?, youtube_url=? WHERE id=?")
                    ->execute([$judul, $konten, $fotoName, $youtube_url, $id]);
                $msg = "✅ Tutorial '$judul' berhasil diperbarui!";
            } else {
                $pdo->prepare("INSERT INTO tutorial (judul, konten, foto, youtube_url, created_by) VALUES (?, ?, ?, ?, ?)")
                    ->execute([$judul, $konten, $fotoName, $youtube_url, getUserId()]);
                $msg = "✅ Tutorial '$judul' berhasil ditambahkan!";
            }
        }
    }
}

$editData = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM tutorial WHERE id=?");
    $stmt->execute([(int)$_GET['edit']]);
    $editData = $stmt->fetch();
}

$tutorialList = $pdo->query("SELECT * FROM tutorial ORDER BY created_at DESC")->fetchAll();

adminHeader('Kelola Tutorial Warga', 'tutorial.php');
?>
<!-- Summernote CSS -->
<link href="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-lite.min.css" rel="stylesheet">
<style>
.note-editor .note-editing-area .note-editable { background: #fff; line-height: 1.6; }
</style>

<div class="breadcrumb"><a href="dashboard.php">Dashboard</a><span>›</span><span>Kelola Tutorial</span></div>
<div class="admin-page-title">📖 Kelola Tutorial Warga</div>
<div class="admin-page-sub">Tulis panduan atau artikel edukatif untuk membantu warga.</div>

<?php if ($msg): ?>
<div class="alert alert-<?=$msgType?>" style="margin-bottom:16px;">
    <i class="fa-solid fa-circle-info"></i><span><?=htmlspecialchars($msg)?></span>
</div>
<?php endif; ?>

<!-- Form Tambah/Edit -->
<div class="form-card">
    <div class="form-card-title">
        <i class="fa-solid fa-pen-to-square" style="color:var(--green-600)"></i>
        <?=$editData?'Edit Tutorial':'Tulis Tutorial Baru'?>
    </div>
    <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="id" value="<?=$editData['id']??0?>">

        <div class="form-group">
            <label class="form-label">Judul Tutorial <span class="required">*</span></label>
            <input type="text" name="judul" class="form-control" value="<?=htmlspecialchars($editData['judul']??'')?>" placeholder="Contoh: Tata Cara Pindah KTP ke RT 005" required>
        </div>

        <div class="form-group">
            <label class="form-label">Link YouTube (opsional)</label>
            <input type="url" name="youtube_url" class="form-control" value="<?=htmlspecialchars($editData['youtube_url']??'')?>" placeholder="Contoh: https://www.youtube.com/watch?v=xxxx">
        </div>

        <div class="form-group">
            <label class="form-label">Upload Foto Banner (opsional)</label>
            <?php if(isset($editData['foto']) && $editData['foto']): ?>
                <div style="margin-bottom:10px;">
                    <img src="../assets/uploads/tutorial/<?= htmlspecialchars($editData['foto']) ?>" style="max-height:80px;border-radius:6px;border:1px solid #ddd;">
                </div>
            <?php endif; ?>
            <input type="file" name="foto" class="form-control" accept=".jpg,.jpeg,.png,.webp">
            <div style="font-size:12px;color:#888;margin-top:4px;">Disarankan gambar landscape (maks 2MB). Kosongkan jika tidak ingin mengubah foto.</div>
        </div>

        <div class="form-group">
            <label class="form-label">Konten Artikel <span class="required">*</span></label>
            <textarea name="konten" id="summernote" required><?=htmlspecialchars($editData['konten']??'')?></textarea>
        </div>

        <div style="display:flex;gap:10px;margin-top:20px;">
            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save"></i> <?=$editData?'Update':'Publish'?></button>
            <?php if ($editData): ?><a href="tutorial.php" class="btn btn-outline">Batal</a><?php endif; ?>
        </div>
    </form>
</div>

<!-- Daftar Tutorial -->
<div class="admin-table-wrap">
    <div class="admin-table-header">
        <h3>Daftar Tutorial (<?=count($tutorialList)?>)</h3>
    </div>
    <table>
        <thead><tr><th>Judul</th><th>Media</th><th>Tanggal Publish</th><th>Aksi</th></tr></thead>
        <tbody>
            <?php foreach ($tutorialList as $t): ?>
            <tr>
                <td>
                    <div style="font-weight:700;font-size:14px;"><?=htmlspecialchars($t['judul'])?></div>
                </td>
                <td>
                    <div style="display:flex;gap:4px;">
                        <?php if($t['foto']): ?><span class="badge badge-blue"><i class="fa-solid fa-image"></i> Foto</span><?php endif; ?>
                        <?php if($t['youtube_url']): ?><span class="badge badge-red"><i class="fa-brands fa-youtube"></i> Video</span><?php endif; ?>
                    </div>
                </td>
                <td style="font-size:13px;"><?=date('d M Y, H:i',strtotime($t['created_at']))?></td>
                <td>
                    <div style="display:flex;gap:6px;">
                        <a href="?edit=<?=$t['id']?>" class="btn btn-sm btn-outline" title="Edit"><i class="fa-solid fa-pen"></i></a>
                        <a href="?delete=<?=$t['id']?>" class="btn btn-sm btn-danger" onclick="return confirm('Hapus tutorial ini secara permanen?')" title="Hapus"><i class="fa-solid fa-trash"></i></a>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Summernote JS -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-lite.min.css"></script>
<script src="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-lite.min.js"></script>
<script>
$(document).ready(function() {
    $('#summernote').summernote({
        placeholder: 'Tuliskan panduan atau langkah-langkah di sini...',
        tabsize: 2,
        height: 250,
        toolbar: [
          ['style', ['style']],
          ['font', ['bold', 'underline', 'clear']],
          ['color', ['color']],
          ['para', ['ul', 'ol', 'paragraph']],
          ['table', ['table']],
          ['insert', ['link']],
          ['view', ['fullscreen', 'codeview', 'help']]
        ]
    });
});
</script>

<?php adminFooter(); ?>
