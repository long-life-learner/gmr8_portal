<?php
// ============================================================
// admin/struktur_admin.php — Kelola Struktur Organisasi
// (Ketua RT)
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
    $stmt = $pdo->prepare("SELECT foto FROM struktur_organisasi WHERE id=?");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if ($row && $row['foto'] && file_exists('../assets/uploads/foto/'.$row['foto'])) {
        unlink('../assets/uploads/foto/'.$row['foto']);
    }
    $pdo->prepare("DELETE FROM struktur_organisasi WHERE id=?")->execute([$id]);
    $msg = '🗑️ Pengurus berhasil dihapus.'; $msgType = 'warning';
}

// SIMPAN
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    $nama = trim($_POST['nama'] ?? '');
    $jabatan = trim($_POST['jabatan'] ?? '');
    $deskripsi = trim($_POST['deskripsi'] ?? '');
    $noWa = trim($_POST['no_wa'] ?? '');
    $urutan = (int)($_POST['urutan'] ?? 0);
    $foto = null;

    if (!$nama || !$jabatan) {
        $msg = 'Nama dan jabatan wajib diisi!'; $msgType = 'danger';
    } else {
        // Handle foto upload
        if (!empty($_FILES['foto']['tmp_name'])) {
            $file = $_FILES['foto'];
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $realType = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);

            if (in_array($realType, ['image/jpeg','image/png','image/webp'])) {
                $uploadDir = '../assets/uploads/foto/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                $ext  = pathinfo($file['name'], PATHINFO_EXTENSION) ?: 'jpg';
                $foto = 'pengurus_' . time() . '_' . rand(100,999) . '.' . strtolower($ext);
                move_uploaded_file($file['tmp_name'], $uploadDir . $foto);

                // Hapus foto lama
                if ($id) {
                    $oldStmt = $pdo->prepare("SELECT foto FROM struktur_organisasi WHERE id=?");
                    $oldStmt->execute([$id]);
                    $oldRow = $oldStmt->fetch();
                    if ($oldRow && $oldRow['foto'] && file_exists($uploadDir.$oldRow['foto'])) {
                        unlink($uploadDir.$oldRow['foto']);
                    }
                }
            } else {
                $msg = 'Format foto tidak didukung (gunakan JPG/PNG/WebP).'; $msgType = 'danger';
            }
        }

        if ($msgType === 'success') {
            if ($id) {
                $sets = "nama=?,jabatan=?,deskripsi=?,no_wa=?,urutan=?";
                $params = [$nama,$jabatan,$deskripsi,$noWa,$urutan];
                if ($foto) { $sets .= ",foto=?"; $params[] = $foto; }
                $params[] = $id;
                $pdo->prepare("UPDATE struktur_organisasi SET $sets WHERE id=?")->execute($params);
                $msg = "✅ Data pengurus '$nama' berhasil diperbarui!";
            } else {
                $pdo->prepare("INSERT INTO struktur_organisasi (nama,jabatan,deskripsi,no_wa,urutan,foto) VALUES (?,?,?,?,?,?)")
                    ->execute([$nama,$jabatan,$deskripsi,$noWa,$urutan,$foto]);
                $msg = "✅ Pengurus '$nama' berhasil ditambahkan!";
            }
        }
    }
}

$editData = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM struktur_organisasi WHERE id=?");
    $stmt->execute([(int)$_GET['edit']]);
    $editData = $stmt->fetch();
}

$struktOrgData = $pdo->query("SELECT * FROM struktur_organisasi ORDER BY urutan ASC")->fetchAll();

adminHeader('Struktur Organisasi', 'struktur_admin.php');
?>

<div class="breadcrumb"><a href="../dashboard/">Dashboard</a><span>›</span><span>Struktur Organisasi</span></div>
<div class="admin-page-title">👥 Kelola Struktur Organisasi</div>
<div class="admin-page-sub">Tambah dan edit pengurus yang tampil di halaman Kenalan Pengurus</div>

<?php if ($msg): ?>
<div class="alert alert-<?=$msgType?>" style="margin-bottom:16px;"><i class="fa-solid fa-circle-check"></i><span><?=htmlspecialchars($msg)?></span></div>
<?php endif; ?>

<!-- Form Tambah/Edit -->
<div class="form-card">
    <div class="form-card-title">
        <i class="fa-solid fa-user-tie" style="color:var(--green-600)"></i>
        <?=$editData?'Edit Pengurus':'Tambah Pengurus Baru'?>
    </div>
    <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="id" value="<?=$editData['id']??0?>">

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
            <div class="form-group" style="grid-column:1/-1;">
                <label class="form-label">Nama Lengkap <span class="required">*</span></label>
                <input type="text" name="nama" class="form-control" value="<?=htmlspecialchars($editData['nama']??'')?>" placeholder="Nama pengurus..." required>
            </div>
            <div class="form-group">
                <label class="form-label">Jabatan <span class="required">*</span></label>
                <input type="text" name="jabatan" class="form-control" value="<?=htmlspecialchars($editData['jabatan']??'')?>" placeholder="Ketua RT, Bendahara, ..." required>
            </div>
            <div class="form-group">
                <label class="form-label">Urutan Tampil</label>
                <input type="number" name="urutan" class="form-control" value="<?=$editData['urutan']??0?>" min="0" placeholder="1 = paling atas">
            </div>
            <div class="form-group">
                <label class="form-label">No. WhatsApp</label>
                <input type="text" name="no_wa" class="form-control" value="<?=htmlspecialchars($editData['no_wa']??'')?>" placeholder="08xxxxxxxxxx">
            </div>
            <div class="form-group">
                <label class="form-label">Foto (opsional)</label>
                <input type="file" name="foto" class="form-control" accept="image/jpeg,image/png,image/webp" style="padding:10px;">
                <?php if ($editData && $editData['foto'] && file_exists('../assets/uploads/foto/'.$editData['foto'])): ?>
                <div style="margin-top:8px;">
                    <img src="<?=SITE_URL?>/assets/uploads/foto/<?=htmlspecialchars($editData['foto'])?>"
                         style="width:60px;height:60px;border-radius:50%;object-fit:cover;border:2px solid var(--border);">
                    <span style="font-size:12px;color:var(--text-light);margin-left:8px;">Foto saat ini</span>
                </div>
                <?php endif; ?>
                <div class="form-hint">Kosongkan jika tidak ingin mengganti foto. Max 5MB.</div>
            </div>
            <div class="form-group" style="grid-column:1/-1;">
                <label class="form-label">Deskripsi Peran <span style="font-weight:400;color:var(--text-light);">(bahasa santai)</span></label>
                <textarea name="deskripsi" class="form-control" rows="3" placeholder="Koordinator utama RT, bertanggung jawab atas..."><?=htmlspecialchars($editData['deskripsi']??'')?></textarea>
            </div>
        </div>
        <div style="display:flex;gap:10px;">
            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save"></i> <?=$editData?'Update':'Simpan'?></button>
            <?php if ($editData): ?><a href="../struktur_admin/" class="btn btn-outline">Batal</a><?php endif; ?>
        </div>
    </form>
</div>

<!-- Preview & Daftar -->
<div class="admin-table-wrap">
    <div class="admin-table-header">
        <h3>Tim Pengurus (<?=count($struktOrgData)?>)</h3>
        <a href="<?=SITE_URL?>/struktur/" target="_blank" class="btn btn-sm btn-outline">
            <i class="fa-solid fa-eye"></i> Lihat Halaman
        </a>
    </div>
    <table>
        <thead><tr><th>Urutan</th><th>Foto</th><th>Nama</th><th>Jabatan</th><th>No. WA</th><th>Aksi</th></tr></thead>
        <tbody>
            <?php foreach ($struktOrgData as $s): ?>
            <tr>
                <td style="text-align:center;font-weight:700;color:var(--text-light);"><?=$s['urutan']?></td>
                <td>
                    <?php if ($s['foto'] && file_exists('../assets/uploads/foto/'.$s['foto'])): ?>
                    <img src="<?=SITE_URL?>/assets/uploads/foto/<?=htmlspecialchars($s['foto'])?>"
                         style="width:40px;height:40px;border-radius:50%;object-fit:cover;">
                    <?php else: ?>
                    <div style="width:40px;height:40px;border-radius:50%;background:linear-gradient(135deg,var(--green-400),var(--green-700));display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:13px;">
                        <?=implode('',array_map(fn($w)=>strtoupper(mb_substr($w,0,1)),array_slice(explode(' ',$s['nama']),0,2)))?>
                    </div>
                    <?php endif; ?>
                </td>
                <td style="font-weight:700;"><?=htmlspecialchars($s['nama'])?></td>
                <td style="font-size:13px;color:var(--green-700);"><?=htmlspecialchars($s['jabatan'])?></td>
                <td style="font-size:12px;">
                    <?php if ($s['no_wa']): ?>
                    <a href="https://wa.me/62<?=ltrim($s['no_wa'],'0')?>" target="_blank" style="color:var(--green-700);">
                        <i class="fa-brands fa-whatsapp"></i> <?=htmlspecialchars($s['no_wa'])?>
                    </a>
                    <?php else: ?>-<?php endif; ?>
                </td>
                <td>
                    <div style="display:flex;gap:6px;">
                        <a href="?edit=<?=$s['id']?>" class="btn btn-sm btn-outline"><i class="fa-solid fa-pen"></i></a>
                        <a href="?delete=<?=$s['id']?>" class="btn btn-sm btn-danger"
                           onclick="return confirm('Hapus pengurus <?=htmlspecialchars(addslashes($s['nama']))?>?')">
                            <i class="fa-solid fa-trash"></i>
                        </a>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php adminFooter(); ?>
