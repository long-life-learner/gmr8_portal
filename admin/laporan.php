<?php
// ============================================================
// admin/laporan.php — Laporan Keuangan + Export WA & Excel
// ============================================================
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../libs/SimpleXLSXGen.php';
require_once 'includes/admin_layout.php';
requireLogin();
requireRole(['bendahara']);

$bulanFilter = isset($_GET['bulan']) ? (int)$_GET['bulan'] : (int)date('n');
$tahunFilter = isset($_GET['tahun']) ? (int)$_GET['tahun'] : (int)date('Y');

// Mutasi kas periode terpilih
$mutasi = $pdo->prepare("
    SELECT k.*, pg.nama as op_nama
    FROM kas k
    LEFT JOIN pengurus pg ON pg.id = k.created_by
    WHERE MONTH(k.tanggal)=? AND YEAR(k.tanggal)=?
    ORDER BY k.tanggal ASC, k.created_at ASC
");
$mutasi->execute([$bulanFilter, $tahunFilter]);
$mutasiData = $mutasi->fetchAll();

// Summary
$summary = $pdo->prepare("
    SELECT
        SUM(CASE WHEN tipe='masuk' THEN jumlah ELSE 0 END) as masuk,
        SUM(CASE WHEN tipe='keluar' THEN jumlah ELSE 0 END) as keluar
    FROM kas WHERE MONTH(tanggal)=? AND YEAR(tanggal)=?
");
$summary->execute([$bulanFilter, $tahunFilter]);
$sum = $summary->fetch();
$totalMasuk = (float)($sum['masuk'] ?? 0);
$totalKeluar = (float)($sum['keluar'] ?? 0);

// Saldo awal bulan (mutasi sebelum bulan ini)
$saldoAwal = $pdo->prepare("
    SELECT
        SUM(CASE WHEN tipe='masuk' THEN jumlah ELSE -jumlah END)
    FROM kas
    WHERE tanggal < DATE(CONCAT(?,'-',LPAD(?,2,'0'),'-01'))
");
$saldoAwal->execute([$tahunFilter, $bulanFilter]);
$saldoAwalBulan = (float)($saldoAwal->fetchColumn() ?? 0);
$saldoAkhir = $saldoAwalBulan + $totalMasuk - $totalKeluar;

// Warga belum bayar IPL bulan ini
$belumIPL = $pdo->prepare("
    SELECT w.nama, w.nomor_rumah
    FROM tagihan t
    JOIN warga w ON w.id = t.warga_id
    JOIN jenis_iuran j ON j.id = t.jenis_iuran_id
    WHERE t.bulan=? AND t.tahun=? AND t.status='belum_bayar' AND j.nama LIKE '%IPL%'
    ORDER BY w.nomor_rumah ASC
");
$belumIPL->execute([$bulanFilter, $tahunFilter]);
$belumIplData = $belumIPL->fetchAll();

// ============================================================
// EXPORT EXCEL
// ============================================================
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    $xlsx = new SimpleXLSXGen();

    // Sheet 1: Summary
    $sheet1 = [
        ['Laporan Keuangan RT 005 RW 012 GMR 8'],
        ['Periode', bulanNama($bulanFilter) . ' ' . $tahunFilter],
        ['Tanggal Cetak', date('d F Y')],
        [],
        ['Saldo Awal Bulan', $saldoAwalBulan],
        ['Total Pemasukan', $totalMasuk],
        ['Total Pengeluaran', $totalKeluar],
        ['Saldo Akhir', $saldoAkhir],
    ];
    $xlsx->addSheet('Ringkasan', $sheet1);

    // Sheet 2: Mutasi Kas
    $sheet2 = [['Tanggal', 'Tipe', 'Jumlah', 'Keterangan', 'Operator']];
    foreach ($mutasiData as $m) {
        $sheet2[] = [
            date('d/m/Y', strtotime($m['tanggal'])),
            $m['tipe'] === 'masuk' ? 'Pemasukan' : 'Pengeluaran',
            (float)$m['jumlah'],
            $m['keterangan'] ?? '-',
            $m['op_nama'] ?? 'Sistem',
        ];
    }
    $xlsx->addSheet('Mutasi Kas', $sheet2);

    // Sheet 3: Tagihan bulan ini
    $tagihan = $pdo->prepare("
        SELECT w.nama, w.nomor_rumah, j.nama as jenis, t.nominal, t.status
        FROM tagihan t
        JOIN warga w ON w.id = t.warga_id
        JOIN jenis_iuran j ON j.id = t.jenis_iuran_id
        WHERE t.bulan=? AND t.tahun=?
        ORDER BY j.nama, w.nomor_rumah
    ");
    $tagihan->execute([$bulanFilter, $tahunFilter]);
    $sheet3 = [['No. Rumah', 'Nama Warga', 'Jenis Iuran', 'Nominal', 'Status']];
    foreach ($tagihan->fetchAll() as $t) {
        $statusLabel = ['belum_bayar'=>'Belum Bayar','menunggu_verifikasi'=>'Menunggu Verifikasi','lunas'=>'Lunas'][$t['status']] ?? $t['status'];
        $sheet3[] = [$t['nomor_rumah'], $t['nama'], $t['jenis'], (float)$t['nominal'], $statusLabel];
    }
    $xlsx->addSheet('Tagihan Warga', $sheet3);

    $filename = 'Laporan_Kas_GMR8_' . bulanNama($bulanFilter) . '_' . $tahunFilter . '.xlsx';
    $xlsx->saveAs($filename);
    exit;
}

// Build WA Text
$namaAktif = explode(' ', getUserName())[0];
$waText  = "📊 *LAPORAN KAS RT 005 · GMR 8*\n";
$waText .= "Periode: " . bulanNama($bulanFilter) . " $tahunFilter\n";
$waText .= "Tanggal: " . date('d F Y') . "\n";
$waText .= "━━━━━━━━━━━━━━━━━━━━\n\n";
$waText .= "💼 *POSISI KAS*\n";
$waText .= "Saldo Awal : " . formatRupiah($saldoAwalBulan) . "\n";
$waText .= "Pemasukan  : " . formatRupiah($totalMasuk) . "\n";
$waText .= "Pengeluaran: " . formatRupiah($totalKeluar) . "\n";
$waText .= "Saldo Akhir: *" . formatRupiah($saldoAkhir) . "*\n\n";
if (!empty($mutasiData)) {
    $waText .= "📝 *RINCIAN TRANSAKSI*\n";
    foreach ($mutasiData as $m) {
        $simbol = $m['tipe'] === 'masuk' ? '+' : '-';
        $waText .= "$simbol " . formatRupiah($m['jumlah']) . " — " . ($m['keterangan'] ?? '-') . "\n";
    }
    $waText .= "\n";
}
if (!empty($belumIplData)) {
    $waText .= "📌 *IPL BELUM BAYAR (" . count($belumIplData) . " warga):*\n";
    foreach ($belumIplData as $i => $b) {
        $waText .= ($i+1) . ". {$b['nama']} ({$b['nomor_rumah']})\n";
    }
    $waText .= "\n";
}
$waText .= "━━━━━━━━━━━━━━━━━━━━\n";
$waText .= "🙏 Laporan oleh Bendahara RT 005 GMR 8\n";
$waText .= "_Terima kasih atas kepercayaannya!_ 💚";

adminHeader('Laporan Keuangan', 'laporan.php');
?>

<div class="breadcrumb"><a href="dashboard.php">Dashboard</a><span>›</span><span>Laporan Keuangan</span></div>
<div class="admin-page-title">📑 Laporan Keuangan</div>
<div class="admin-page-sub">Rekap pemasukan, pengeluaran, dan export laporan</div>

<!-- Filter -->
<div class="form-card">
    <form method="GET" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;">
        <div class="form-group" style="flex:1;min-width:120px;margin-bottom:0;">
            <label class="form-label">Bulan</label>
            <select name="bulan" class="form-control" onchange="this.form.submit()">
                <?php for ($m=1;$m<=12;$m++): ?>
                <option value="<?=$m?>" <?=$m==$bulanFilter?'selected':''?>><?=bulanNama($m)?></option>
                <?php endfor; ?>
            </select>
        </div>
        <div class="form-group" style="flex:1;min-width:100px;margin-bottom:0;">
            <label class="form-label">Tahun</label>
            <select name="tahun" class="form-control" onchange="this.form.submit()">
                <?php for ($y=date('Y');$y>=date('Y')-3;$y--): ?>
                <option value="<?=$y?>" <?=$y==$tahunFilter?'selected':''?>><?=$y?></option>
                <?php endfor; ?>
            </select>
        </div>
        <div style="flex:none;">
            <a href="?bulan=<?=$bulanFilter?>&tahun=<?=$tahunFilter?>&export=excel" class="btn btn-outline" style="border-color:var(--green-500);">
                <i class="fa-solid fa-file-excel" style="color:#1d6f42;"></i> Export Excel
            </a>
        </div>
    </form>
</div>

<!-- Summary Cards -->
<div class="admin-stats" style="grid-template-columns:repeat(2,1fr);">
    <div class="admin-stat-card">
        <div style="font-size:22px;">🏦</div>
        <div class="stat-num" style="font-size:16px;"><?=formatRupiah($saldoAwalBulan)?></div>
        <div class="stat-lbl">Saldo Awal</div>
    </div>
    <div class="admin-stat-card" style="border-left-color:var(--green-400)">
        <div style="font-size:22px;">📈</div>
        <div class="stat-num" style="font-size:16px;color:var(--green-700);"><?=formatRupiah($totalMasuk)?></div>
        <div class="stat-lbl">Pemasukan</div>
    </div>
    <div class="admin-stat-card" style="border-left-color:#e53935">
        <div style="font-size:22px;">📉</div>
        <div class="stat-num" style="font-size:16px;color:#e53935;"><?=formatRupiah($totalKeluar)?></div>
        <div class="stat-lbl">Pengeluaran</div>
    </div>
    <div class="admin-stat-card" style="border-left-color:var(--green-800)">
        <div style="font-size:22px;">💰</div>
        <div class="stat-num" style="font-size:16px;"><?=formatRupiah($saldoAkhir)?></div>
        <div class="stat-lbl">Saldo Akhir</div>
    </div>
</div>

<!-- Tabel Mutasi -->
<?php if (!empty($mutasiData)): ?>
<div class="admin-table-wrap" style="margin-bottom:20px;">
    <div class="admin-table-header"><h3>📋 Mutasi Kas <?=bulanNama($bulanFilter)?> <?=$tahunFilter?></h3></div>
    <table>
        <thead><tr><th>Tanggal</th><th>Keterangan</th><th>Masuk</th><th>Keluar</th></tr></thead>
        <tbody>
            <?php foreach ($mutasiData as $m): ?>
            <tr>
                <td style="font-size:12px;white-space:nowrap;"><?=date('d M', strtotime($m['tanggal']))?></td>
                <td style="font-size:13px;"><?=htmlspecialchars($m['keterangan']??'-')?></td>
                <td style="color:var(--green-700);font-weight:700;">
                    <?=$m['tipe']==='masuk'?formatRupiah($m['jumlah']):'-'?>
                </td>
                <td style="color:#e53935;font-weight:700;">
                    <?=$m['tipe']==='keluar'?formatRupiah($m['jumlah']):'-'?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr style="background:var(--green-50);">
                <td colspan="2" style="font-weight:700;padding:12px 14px;">TOTAL</td>
                <td style="font-weight:800;color:var(--green-700);"><?=formatRupiah($totalMasuk)?></td>
                <td style="font-weight:800;color:#e53935;"><?=formatRupiah($totalKeluar)?></td>
            </tr>
        </tfoot>
    </table>
</div>
<?php else: ?>
<div class="alert alert-info" style="margin-bottom:20px;"><i class="fa-solid fa-circle-info"></i> Belum ada transaksi di <?=bulanNama($bulanFilter)?> <?=$tahunFilter?>.</div>
<?php endif; ?>

<!-- WhatsApp Export -->
<div class="form-card">
    <div class="form-card-title">
        <i class="fa-brands fa-whatsapp" style="color:#25D366"></i>
        Rekap Teks WhatsApp
    </div>
    <p style="font-size:13px;color:var(--text-light);margin-bottom:14px;">Salin teks ini dan paste ke grup WhatsApp RT! 💚</p>
    <div class="wa-box" id="wa-preview"><?=htmlspecialchars($waText)?></div>
    <button id="copy-wa-btn" class="btn btn-primary btn-block mt-2" style="background:linear-gradient(135deg,#25D366,#128C7E);">
        <i class="fa-brands fa-whatsapp"></i> Copy Teks WA
    </button>
</div>

<?php adminFooter(); ?>
