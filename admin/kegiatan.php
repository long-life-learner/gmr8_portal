<?php
// ============================================================
// admin/kegiatan.php — Kelola Jadwal Kegiatan (Sekretaris)
// ============================================================
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once 'includes/admin_layout.php';
requireLogin();
requireRole(['sekretaris']);

$msg = ''; $msgType = 'success';

// HAPUS
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $pdo->prepare("DELETE FROM kegiatan WHERE id=?")->execute([$id]);
    $msg = '🗑️ Kegiatan berhasil dihapus.'; $msgType = 'warning';
}

// SIMPAN
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    $judul = trim($_POST['judul'] ?? '');
    $agenda = trim($_POST['agenda'] ?? '');
    $deskripsi = trim($_POST['deskripsi'] ?? '');
    $lokasi = trim($_POST['lokasi'] ?? '');
    $tanggal = $_POST['tanggal'] ?? '';
    $waktu = $_POST['waktu'] ?? null;
    $status = $_POST['status'] ?? 'mendatang';

    if (!$judul || !$tanggal) {
        $msg = 'Judul dan tanggal wajib diisi!'; $msgType = 'danger';
    } else {
        if (!$waktu) $waktu = null;
        if ($id) {
            $pdo->prepare("UPDATE kegiatan SET judul=?,agenda=?,deskripsi=?,lokasi=?,tanggal=?,waktu=?,status=? WHERE id=?")
                ->execute([$judul,$agenda,$deskripsi,$lokasi,$tanggal,$waktu,$status,$id]);
            $msg = "✅ Kegiatan '$judul' berhasil diperbarui!";
        } else {
            $pdo->prepare("INSERT INTO kegiatan (judul,agenda,deskripsi,lokasi,tanggal,waktu,status,created_by) VALUES (?,?,?,?,?,?,?,?)")
                ->execute([$judul,$agenda,$deskripsi,$lokasi,$tanggal,$waktu,$status,getUserId()]);
            $msg = "✅ Kegiatan '$judul' berhasil ditambahkan!";
        }
    }
}

$editData = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM kegiatan WHERE id=?");
    $stmt->execute([(int)$_GET['edit']]);
    $editData = $stmt->fetch();
}

// Auto-update status based on date
$pdo->query("UPDATE kegiatan SET status='selesai' WHERE tanggal < CURDATE() AND status='mendatang'");

$kegiatan = $pdo->query("SELECT * FROM kegiatan ORDER BY tanggal DESC LIMIT 50")->fetchAll();

adminHeader('Jadwal Kegiatan', 'kegiatan.php');
?>

<div class="breadcrumb"><a href="../dashboard/">Dashboard</a><span>›</span><span>Jadwal Kegiatan</span></div>
<div class="admin-page-title">📅 Kelola Jadwal Kegiatan</div>
<div class="admin-page-sub">Tambah dan kelola agenda kegiatan RT 005 GMR 8</div>

<?php if ($msg): ?>
<div class="alert alert-<?=$msgType?>" style="margin-bottom:16px;">
    <i class="fa-solid fa-circle-check"></i><span><?=htmlspecialchars($msg)?></span>
</div>
<?php endif; ?>

<!-- Form Tambah/Edit -->
<div class="form-card">
    <div class="form-card-title">
        <i class="fa-solid fa-calendar-plus" style="color:var(--green-600)"></i>
        <?=$editData?'Edit Kegiatan':'Tambah Kegiatan Baru'?>
    </div>
    <form method="POST">
        <input type="hidden" name="id" value="<?=$editData['id']??0?>">

        <div class="form-group">
            <label class="form-label">Judul Kegiatan <span class="required">*</span></label>
            <input type="text" name="judul" class="form-control" value="<?=htmlspecialchars($editData['judul']??'')?>" placeholder="Contoh: Kerja Bakti Rutin Mei" required>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
            <div class="form-group">
                <label class="form-label">Tanggal <span class="required">*</span></label>
                <input type="date" name="tanggal" class="form-control" value="<?=$editData['tanggal']??date('Y-m-d')?>" required>
            </div>
            <div class="form-group">
                <label class="form-label">Waktu</label>
                <input type="time" name="waktu" class="form-control" value="<?=substr($editData['waktu']??'',0,5)?>">
            </div>
        </div>

        <div class="form-group">
            <label class="form-label">Lokasi</label>
            <input type="text" name="lokasi" class="form-control" value="<?=htmlspecialchars($editData['lokasi']??'')?>" placeholder="Contoh: Pos RT 005 GMR 8">
        </div>

        <div class="form-group">
            <label class="form-label">Agenda <span style="font-weight:400;color:var(--text-light);">(poin-poin)</span></label>
            <textarea name="agenda" class="form-control" rows="3" placeholder="1. Bersih-bersih selokan&#10;2. Pengecatan pagar"><?=htmlspecialchars($editData['agenda']??'')?></textarea>
        </div>

        <div class="form-group">
            <label class="form-label">Deskripsi <span style="font-weight:400;color:var(--text-light);">(bahasa hangat untuk warga)</span></label>
            <textarea name="deskripsi" class="form-control" rows="3" placeholder="Yuk bareng-bareng kita..."><?=htmlspecialchars($editData['deskripsi']??'')?></textarea>
        </div>

        <div class="form-group">
            <label class="form-label">Status</label>
            <select name="status" class="form-control">
                <option value="mendatang" <?=($editData['status']??'mendatang')==='mendatang'?'selected':''?>>📅 Mendatang</option>
                <option value="berlangsung" <?=($editData['status']??'')==='berlangsung'?'selected':''?>>🔴 Berlangsung</option>
                <option value="selesai" <?=($editData['status']??'')==='selesai'?'selected':''?>>✅ Selesai</option>
            </select>
        </div>

        <div style="display:flex;gap:10px;">
            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save"></i> <?=$editData?'Update':'Simpan'?></button>
            <?php if ($editData): ?><a href="../kegiatan/" class="btn btn-outline">Batal</a><?php endif; ?>
        </div>
    </form>
</div>

<!-- Daftar Kegiatan -->
<div class="admin-table-wrap">
    <div class="admin-table-header">
        <h3>Daftar Kegiatan (<?=count($kegiatan)?>)</h3>
    </div>
    <table>
        <thead><tr><th>Kegiatan</th><th>Tanggal</th><th>Lokasi</th><th>Status</th><th>Aksi</th></tr></thead>
        <tbody>
            <?php foreach ($kegiatan as $k): ?>
            <tr>
                <td>
                    <div style="font-weight:700;font-size:14px;"><?=htmlspecialchars($k['judul'])?></div>
                    <?php if ($k['waktu']): ?>
                    <div style="font-size:11px;color:var(--text-light);">⏰ <?=date('H:i',strtotime($k['waktu']))?> WIB</div>
                    <?php endif; ?>
                </td>
                <td style="font-size:13px;white-space:nowrap;"><?=date('d M Y',strtotime($k['tanggal']))?></td>
                <td style="font-size:12px;color:var(--text-light);"><?=htmlspecialchars($k['lokasi']??'-')?></td>
                <td>
                    <span class="badge <?=['mendatang'=>'badge-blue','berlangsung'=>'badge-red','selesai'=>'badge-green'][$k['status']]??'badge-gray'?>">
                        <?=['mendatang'=>'📅 Mendatang','berlangsung'=>'🔴 Berlangsung','selesai'=>'✅ Selesai'][$k['status']]??$k['status']?>
                    </span>
                </td>
                <td>
                    <div style="display:flex;gap:6px;">
                        <a href="?edit=<?=$k['id']?>" class="btn btn-sm btn-outline"><i class="fa-solid fa-pen"></i></a>
                        <a href="?delete=<?=$k['id']?>" class="btn btn-sm btn-danger" onclick="return confirm('Hapus kegiatan ini?')"><i class="fa-solid fa-trash"></i></a>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php adminFooter(); ?>
