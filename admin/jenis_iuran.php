<?php
// ============================================================
// admin/jenis_iuran.php — Kelola Jenis Iuran & Generate Tagihan
// (Bendahara)
// ============================================================
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once 'includes/admin_layout.php';
requireLogin();
requireRole(['bendahara']);

$msg = ''; $msgType = 'success';

// ============================================================
// SIMPAN JENIS IURAN
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_jenis') {
    $id = (int)($_POST['id'] ?? 0);
    $nama = trim($_POST['nama'] ?? '');
    $deskripsi = trim($_POST['deskripsi'] ?? '');
    $nominal = (int)str_replace(['.', ','], '', $_POST['nominal'] ?? 0);
    $periode = $_POST['periode'] ?? 'bulanan';
    $aktif  = isset($_POST['aktif']) ? 1 : 0;

    if (!$nama || $nominal <= 0) {
        $msg = 'Nama dan nominal wajib diisi ya!'; $msgType = 'danger';
    } else {
        if ($id) {
            $pdo->prepare("UPDATE jenis_iuran SET nama=?,deskripsi=?,nominal=?,periode=?,aktif=? WHERE id=?")
                ->execute([$nama,$deskripsi,$nominal,$periode,$aktif,$id]);
            $msg = "✅ Jenis iuran '$nama' berhasil diperbarui!";
        } else {
            $pdo->prepare("INSERT INTO jenis_iuran (nama,deskripsi,nominal,periode,aktif) VALUES (?,?,?,?,1)")
                ->execute([$nama,$deskripsi,$nominal,$periode]);
            $msg = "✅ Jenis iuran '$nama' berhasil ditambahkan!";
        }
    }
}

// ============================================================
// GENERATE TAGIHAN MASSAL
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'generate') {
    $jenisId = (int)($_POST['jenis_id'] ?? 0);
    $bulan   = (int)($_POST['gen_bulan'] ?? 0);
    $tahun   = (int)($_POST['gen_tahun'] ?? 0);

    if ($jenisId && $bulan && $tahun) {
        $jenis = $pdo->prepare("SELECT * FROM jenis_iuran WHERE id=?");
        $jenis->execute([$jenisId]);
        $jenisRow = $jenis->fetch();

        if ($jenisRow) {
            $wargas = $pdo->query("SELECT id FROM warga WHERE aktif=1")->fetchAll();
            $stmt = $pdo->prepare("
                INSERT IGNORE INTO tagihan (warga_id, jenis_iuran_id, bulan, tahun, nominal)
                VALUES (?, ?, ?, ?, ?)
            ");
            $count = 0;
            foreach ($wargas as $w) {
                $stmt->execute([$w['id'], $jenisId, $bulan, $tahun, $jenisRow['nominal']]);
                if ($stmt->rowCount()) $count++;
            }
            $msg = "✅ Berhasil generate $count tagihan {$jenisRow['nama']} untuk " . bulanNama($bulan) . " $tahun!";
        }
    } else {
        $msg = 'Pilih jenis iuran, bulan, dan tahun dulu ya!'; $msgType = 'danger';
    }
}

// TOGGLE AKTIF
if (isset($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];
    $pdo->prepare("UPDATE jenis_iuran SET aktif = NOT aktif WHERE id=?")->execute([$id]);
    header('Location: ../jenis_iuran/?msg=updated'); exit;
}

// Edit data
$editData = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM jenis_iuran WHERE id=?");
    $stmt->execute([(int)$_GET['edit']]);
    $editData = $stmt->fetch();
}

$jenisIuran = $pdo->query("SELECT * FROM jenis_iuran ORDER BY aktif DESC, id ASC")->fetchAll();

adminHeader('Jenis Iuran', 'jenis_iuran.php');
?>

<div class="breadcrumb"><a href="../dashboard/">Dashboard</a><span>›</span><span>Jenis Iuran</span></div>
<div class="admin-page-title">🏷️ Kelola Jenis Iuran</div>
<div class="admin-page-sub">Tambah, edit jenis iuran, dan generate tagihan untuk warga</div>

<?php if ($msg): ?>
<div class="alert alert-<?=$msgType?>" style="margin-bottom:16px;">
    <i class="fa-solid fa-circle-check"></i><span><?=htmlspecialchars($msg)?></span>
</div>
<?php endif; ?>

<!-- Form Tambah/Edit -->
<div class="form-card">
    <div class="form-card-title">
        <i class="fa-solid fa-tags" style="color:var(--green-600)"></i>
        <?= $editData ? 'Edit Jenis Iuran' : 'Tambah Jenis Iuran Baru' ?>
    </div>
    <form method="POST">
        <input type="hidden" name="action" value="save_jenis">
        <input type="hidden" name="id" value="<?=$editData['id']??0?>">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
            <div class="form-group" style="grid-column:1/-1;">
                <label class="form-label">Nama Iuran <span class="required">*</span></label>
                <input type="text" name="nama" class="form-control" value="<?=htmlspecialchars($editData['nama']??'')?>" placeholder="Contoh: IPL (Iuran Pengelolaan Lingkungan)" required>
            </div>
            <div class="form-group">
                <label class="form-label">Nominal (Rp) <span class="required">*</span></label>
                <input type="number" name="nominal" class="form-control" value="<?=$editData['nominal']??''?>" placeholder="30000" min="0" required>
            </div>
            <div class="form-group">
                <label class="form-label">Periode</label>
                <select name="periode" class="form-control">
                    <option value="bulanan" <?=($editData['periode']??'bulanan')==='bulanan'?'selected':''?>>Bulanan</option>
                    <option value="tahunan" <?=($editData['periode']??'')==='tahunan'?'selected':''?>>Tahunan</option>
                    <option value="insidental" <?=($editData['periode']??'')==='insidental'?'selected':''?>>Insidental</option>
                </select>
            </div>
            <div class="form-group" style="grid-column:1/-1;">
                <label class="form-label">Deskripsi</label>
                <textarea name="deskripsi" class="form-control" rows="2" placeholder="Keterangan jenis iuran..."><?=htmlspecialchars($editData['deskripsi']??'')?></textarea>
            </div>
        </div>
        <div style="display:flex;gap:10px;flex-wrap:wrap;">
            <button type="submit" class="btn btn-primary">
                <i class="fa-solid fa-save"></i> <?=$editData?'Update':'Simpan'?>
            </button>
            <?php if ($editData): ?>
            <a href="../jenis_iuran/" class="btn btn-outline">Batal</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Generate Tagihan -->
<div class="form-card" style="border-left:4px solid var(--green-600);">
    <div class="form-card-title">
        <i class="fa-solid fa-bolt" style="color:var(--green-600)"></i>
        Generate Tagihan Massal
    </div>
    <p style="font-size:13px;color:var(--text-light);margin-bottom:14px;">
        Klik tombol ini setiap awal bulan untuk generate tagihan ke semua warga aktif. Duplikat otomatis diabaikan.
    </p>
    <form method="POST">
        <input type="hidden" name="action" value="generate">
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px;align-items:flex-end;">
            <div class="form-group" style="margin-bottom:0;">
                <label class="form-label">Jenis Iuran</label>
                <select name="jenis_id" class="form-control">
                    <?php foreach ($jenisIuran as $j): ?>
                    <?php if ($j['aktif']): ?>
                    <option value="<?=$j['id']?>"><?=htmlspecialchars($j['nama'])?></option>
                    <?php endif; ?>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="margin-bottom:0;">
                <label class="form-label">Bulan</label>
                <select name="gen_bulan" class="form-control">
                    <?php for ($m=1;$m<=12;$m++): ?>
                    <option value="<?=$m?>" <?=$m==(int)date('n')?'selected':''?>><?=bulanNama($m)?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="form-group" style="margin-bottom:0;">
                <label class="form-label">Tahun</label>
                <select name="gen_tahun" class="form-control">
                    <?php for ($y=date('Y');$y>=date('Y')-1;$y--): ?>
                    <option value="<?=$y?>"><?=$y?></option>
                    <?php endfor; ?>
                </select>
            </div>
        </div>
        <button type="submit" class="btn btn-primary mt-2" onclick="return confirm('Generate tagihan untuk semua 54 KK warga aktif? (Duplikat akan diabaikan)')">
            <i class="fa-solid fa-bolt"></i> Generate Tagihan Sekarang
        </button>
    </form>
</div>

<!-- Daftar Jenis Iuran -->
<div class="admin-table-wrap">
    <div class="admin-table-header"><h3>Daftar Jenis Iuran</h3></div>
    <table>
        <thead><tr><th>Nama</th><th>Nominal</th><th>Periode</th><th>Status</th><th>Aksi</th></tr></thead>
        <tbody>
            <?php foreach ($jenisIuran as $j): ?>
            <tr>
                <td>
                    <div style="font-weight:700;"><?=htmlspecialchars($j['nama'])?></div>
                    <?php if ($j['deskripsi']): ?>
                    <div style="font-size:11px;color:var(--text-light);"><?=htmlspecialchars(substr($j['deskripsi'],0,60))?>...</div>
                    <?php endif; ?>
                </td>
                <td style="font-weight:700;color:var(--green-700);"><?=formatRupiah($j['nominal'])?></td>
                <td><span class="badge badge-blue"><?=ucfirst($j['periode'])?></span></td>
                <td>
                    <a href="?toggle=<?=$j['id']?>" class="badge <?=$j['aktif']?'badge-green':'badge-gray'?>">
                        <?=$j['aktif']?'✅ Aktif':'⭕ Nonaktif'?>
                    </a>
                </td>
                <td><a href="?edit=<?=$j['id']?>" class="btn btn-sm btn-outline"><i class="fa-solid fa-pen"></i></a></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php adminFooter(); ?>
