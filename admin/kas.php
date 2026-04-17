<?php
// ============================================================
// admin/kas.php — Catat Pengeluaran Kas (Bendahara)
// ============================================================
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once 'includes/admin_layout.php';
requireLogin();
requireRole(['bendahara']);

$msg = ''; $msgType = 'success';

// HAPUS
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $cek = $pdo->prepare("SELECT pembayaran_id FROM kas WHERE id=?");
    $cek->execute([$id]);
    $row = $cek->fetch();
    if ($row && $row['pembayaran_id']) {
        $msg = 'Tidak bisa hapus — mutasi ini terkait dengan verifikasi pembayaran warga.'; $msgType = 'danger';
    } else {
        $pdo->prepare("DELETE FROM kas WHERE id=?")->execute([$id]);
        $msg = '🗑️ Catatan kas berhasil dihapus.'; $msgType = 'warning';
    }
}

// SIMPAN
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tipe = $_POST['tipe'] ?? 'keluar';
    $jumlah = (int)str_replace(['.', ','], '', $_POST['jumlah'] ?? 0);
    $keterangan = trim($_POST['keterangan'] ?? '');
    $tanggal = $_POST['tanggal'] ?? date('Y-m-d');

    if (!$jumlah || !$keterangan) {
        $msg = 'Jumlah dan keterangan wajib diisi!'; $msgType = 'danger';
    } else {
        $pdo->prepare("INSERT INTO kas (tipe, jumlah, keterangan, tanggal, created_by) VALUES (?,?,?,?,?)")
            ->execute([$tipe, $jumlah, $keterangan, $tanggal, getUserId()]);
        $msg = "✅ Catatan kas berhasil ditambahkan!";
    }
}

$bulanFilter = isset($_GET['bulan']) ? (int)$_GET['bulan'] : (int)date('n');
$tahunFilter = isset($_GET['tahun']) ? (int)$_GET['tahun'] : (int)date('Y');

$mutasi = $pdo->prepare("
    SELECT k.*, pg.nama as op_nama
    FROM kas k
    LEFT JOIN pengurus pg ON pg.id = k.created_by
    WHERE MONTH(k.tanggal)=? AND YEAR(k.tanggal)=?
      AND k.pembayaran_id IS NULL
    ORDER BY k.tanggal DESC, k.created_at DESC
");
$mutasi->execute([$bulanFilter, $tahunFilter]);
$mutasiData = $mutasi->fetchAll();

$saldo = getSaldoKas($pdo);

adminHeader('Catat Pengeluaran', 'kas.php');
?>

<div class="breadcrumb"><a href="dashboard.php">Dashboard</a><span>›</span><span>Catat Pengeluaran</span></div>
<div class="admin-page-title">💸 Catat Pengeluaran Kas</div>
<div class="admin-page-sub">Saldo Kas Saat Ini: <strong style="color:var(--green-700);"><?=formatRupiah($saldo)?></strong></div>

<?php if ($msg): ?>
<div class="alert alert-<?=$msgType?>" style="margin-bottom:16px;"><i class="fa-solid fa-circle-info"></i><span><?=htmlspecialchars($msg)?></span></div>
<?php endif; ?>

<!-- Form Catat -->
<div class="form-card">
    <div class="form-card-title"><i class="fa-solid fa-pen-to-square" style="color:var(--green-600)"></i> Catat Transaksi Kas</div>
    <form method="POST">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
            <div class="form-group">
                <label class="form-label">Tipe <span class="required">*</span></label>
                <select name="tipe" class="form-control" id="tipe-sel">
                    <option value="keluar">📉 Pengeluaran</option>
                    <option value="masuk">📈 Pemasukan Lain</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Jumlah (Rp) <span class="required">*</span></label>
                <input type="number" name="jumlah" class="form-control" placeholder="50000" min="0" required>
            </div>
            <div class="form-group">
                <label class="form-label">Tanggal</label>
                <input type="date" name="tanggal" class="form-control" value="<?=date('Y-m-d')?>">
            </div>
            <div class="form-group" style="grid-column:1/-1;">
                <label class="form-label">Keterangan <span class="required">*</span></label>
                <input type="text" name="keterangan" class="form-control" placeholder="Contoh: Pembelian alat kebersihan..." required>
            </div>
        </div>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save"></i> Simpan Catatan</button>
    </form>
</div>

<!-- Filter + Daftar Mutasi (manual saja, tidak termasuk dari verifikasi bayar) -->
<div class="admin-table-wrap">
    <div class="admin-table-header">
        <h3>Catatan Manual <?=bulanNama($bulanFilter)?> <?=$tahunFilter?></h3>
        <form method="GET" style="display:flex;gap:6px;">
            <select name="bulan" class="form-control" style="height:34px;font-size:13px;" onchange="this.form.submit()">
                <?php for ($m=1;$m<=12;$m++): ?>
                <option value="<?=$m?>" <?=$m==$bulanFilter?'selected':''?>><?=bulanNama($m)?></option>
                <?php endfor; ?>
            </select>
            <select name="tahun" class="form-control" style="height:34px;font-size:13px;" onchange="this.form.submit()">
                <?php for ($y=date('Y');$y>=date('Y')-2;$y--): ?>
                <option value="<?=$y?>" <?=$y==$tahunFilter?'selected':''?>><?=$y?></option>
                <?php endfor; ?>
            </select>
        </form>
    </div>

    <?php if (empty($mutasiData)): ?>
    <div style="padding:30px;text-align:center;color:var(--text-light);font-size:14px;">Belum ada catatan manual untuk periode ini.</div>
    <?php else: ?>
    <table>
        <thead><tr><th>Tanggal</th><th>Tipe</th><th>Keterangan</th><th>Jumlah</th><th>Aksi</th></tr></thead>
        <tbody>
            <?php foreach ($mutasiData as $m): ?>
            <tr>
                <td style="font-size:12px;white-space:nowrap;"><?=date('d M Y',strtotime($m['tanggal']))?></td>
                <td><span class="badge <?=$m['tipe']==='masuk'?'badge-green':'badge-red'?>"><?=$m['tipe']==='masuk'?'📈 Masuk':'📉 Keluar'?></span></td>
                <td style="font-size:13px;"><?=htmlspecialchars($m['keterangan']??'-')?></td>
                <td style="font-weight:700;color:<?=$m['tipe']==='masuk'?'var(--green-700)':'#e53935'?>;">
                    <?=$m['tipe']==='masuk'?'+':'-'?><?=formatRupiah($m['jumlah'])?>
                </td>
                <td>
                    <a href="?delete=<?=$m['id']?>&bulan=<?=$bulanFilter?>&tahun=<?=$tahunFilter?>" class="btn btn-sm btn-danger"
                       onclick="return confirm('Hapus catatan ini?')">
                        <i class="fa-solid fa-trash"></i>
                    </a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<?php adminFooter(); ?>
