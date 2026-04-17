<?php
// ============================================================
// monitoring.php — Monitoring Kas & Laporan Publik
// Portal Warga RT 005 RW 012 GMR 8
// ============================================================
require_once 'includes/db.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$pageTitle = 'Monitoring Kas Warga';

$bulanIni = (int)date('n');
$tahunIni = (int)date('Y');

// Saldo kas saat ini
$saldo = getSaldoKas($pdo);

// Rekap per bulan (6 bulan terakhir)
$rekapBulan = $pdo->query("
    SELECT 
        YEAR(tanggal) as tahun,
        MONTH(tanggal) as bulan,
        SUM(CASE WHEN tipe='masuk' THEN jumlah ELSE 0 END) as masuk,
        SUM(CASE WHEN tipe='keluar' THEN jumlah ELSE 0 END) as keluar
    FROM kas
    WHERE tanggal >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY YEAR(tanggal), MONTH(tanggal)
    ORDER BY tahun ASC, bulan ASC
")->fetchAll();

// Statistik bulan ini
$stmtStats = $pdo->prepare("
    SELECT
        SUM(CASE WHEN tipe='masuk' THEN jumlah ELSE 0 END) as masuk,
        SUM(CASE WHEN tipe='keluar' THEN jumlah ELSE 0 END) as keluar
    FROM kas
    WHERE MONTH(tanggal)=? AND YEAR(tanggal)=?
");
$stmtStats->execute([$bulanIni, $tahunIni]);
$statsBulanIni = $stmtStats->fetch();

// Kepatuhan IPL bulan ini
$stmtIpl = $pdo->prepare("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status='lunas' THEN 1 ELSE 0 END) as lunas,
        SUM(CASE WHEN status='menunggu_verifikasi' THEN 1 ELSE 0 END) as pending
    FROM tagihan t
    JOIN jenis_iuran j ON j.id = t.jenis_iuran_id
    WHERE t.bulan=? AND t.tahun=? AND j.nama LIKE '%IPL%'
");
$stmtIpl->execute([$bulanIni, $tahunIni]);
$statsIpl = $stmtIpl->fetch();

// Kepatuhan Kas bulan ini
$stmtKas = $pdo->prepare("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status='lunas' THEN 1 ELSE 0 END) as lunas,
        SUM(CASE WHEN status='menunggu_verifikasi' THEN 1 ELSE 0 END) as pending
    FROM tagihan t
    JOIN jenis_iuran j ON j.id = t.jenis_iuran_id
    WHERE t.bulan=? AND t.tahun=? AND j.nama LIKE '%Kas%'
");
$stmtKas->execute([$bulanIni, $tahunIni]);
$statsKas = $stmtKas->fetch();

// Warga belum bayar IPL bulan ini (untuk rekap WA)
$stmtBelum = $pdo->prepare("
    SELECT w.nama, w.nomor_rumah
    FROM tagihan t
    JOIN warga w ON w.id = t.warga_id
    JOIN jenis_iuran j ON j.id = t.jenis_iuran_id
    WHERE t.bulan=? AND t.tahun=? AND t.status='belum_bayar' AND j.nama LIKE '%IPL%'
    ORDER BY w.nomor_rumah ASC
");
$stmtBelum->execute([$bulanIni, $tahunIni]);
$belumBayarIpl = $stmtBelum->fetchAll();

// Mutasi kas terbaru
$mutasiTerbaru = $pdo->query("
    SELECT k.*, p.nama as verifikator
    FROM kas k
    LEFT JOIN pengurus p ON p.id = k.created_by
    ORDER BY k.tanggal DESC, k.created_at DESC
    LIMIT 20
")->fetchAll();

// Build chart data
$chartLabels = [];
$chartMasuk = [];
$chartKeluar = [];
foreach ($rekapBulan as $r) {
    $chartLabels[] = bulanNama($r['bulan']) . ' ' . $r['tahun'];
    $chartMasuk[] = (float)$r['masuk'];
    $chartKeluar[] = (float)$r['keluar'];
}

// Build WhatsApp text
$tglLaporan = date('d F Y');
$masukBulanIni = (float)($statsBulanIni['masuk'] ?? 0);
$keluarBulanIni = (float)($statsBulanIni['keluar'] ?? 0);
$iplLunas = (int)($statsIpl['lunas'] ?? 0);
$iplTotal = (int)($statsIpl['total'] ?? 0);
$iplPending = (int)($statsIpl['pending'] ?? 0);
$kasLunas = (int)($statsKas['lunas'] ?? 0);
$kasTotal = (int)($statsKas['total'] ?? 0);

$waText = "📊 *LAPORAN KAS RT 005 · GMR 8*\n";
$waText .= "Periode: " . bulanNama($bulanIni) . " $tahunIni\n";
$waText .= "Tanggal Laporan: $tglLaporan\n";
$waText .= "━━━━━━━━━━━━━━━━━━━━\n\n";
$waText .= "💰 *SALDO KAS*\n";
$waText .= "Saldo Saat Ini: " . formatRupiah($saldo) . "\n\n";
$waText .= "📈 *BULAN " . strtoupper(bulanNama($bulanIni)) . " $tahunIni*\n";
$waText .= "Pemasukan  : " . formatRupiah($masukBulanIni) . "\n";
$waText .= "Pengeluaran: " . formatRupiah($keluarBulanIni) . "\n";
$waText .= "Selisih    : " . formatRupiah($masukBulanIni - $keluarBulanIni) . "\n\n";

if ($iplTotal > 0) {
    $waText .= "🌿 *IURAN IPL*\n";
    $waText .= "Lunas  : $iplLunas dari $iplTotal warga\n";
    if ($iplPending > 0) $waText .= "Proses : $iplPending warga (cek rekening)\n";
    $waText .= "Belum  : " . ($iplTotal - $iplLunas - $iplPending) . " warga\n\n";
}

if ($kasTotal > 0) {
    $waText .= "🏠 *KAS RT*\n";
    $waText .= "Lunas  : $kasLunas dari $kasTotal warga\n";
    $waText .= "Belum  : " . ($kasTotal - $kasLunas) . " warga\n\n";
}

if (!empty($belumBayarIpl)) {
    $waText .= "📌 *IPL BELUM BAYAR (" . count($belumBayarIpl) . " warga):*\n";
    foreach ($belumBayarIpl as $idx => $b) {
        $waText .= ($idx + 1) . ". " . $b['nama'] . " - " . $b['nomor_rumah'] . "\n";
    }
    $waText .= "\n";
}

$waText .= "━━━━━━━━━━━━━━━━━━━━\n";
$waText .= "🙏 Terima kasih semua warga GMR 8!\n";
$waText .= "Mari jaga lingkungan kita bersama 🌿\n";
$waText .= "\n_Portal Warga RT 005 GMR 8_\n";
$waText .= "_" . SITE_URL . "_";

$extraHead = '<style>
.chart-container{background:#fff;border-radius:var(--radius-md);padding:18px;box-shadow:var(--shadow-sm);border:1px solid var(--border);margin-bottom:16px;}
</style>';

require_once 'includes/header.php';
?>

<!-- HERO -->
<section class="hero">
    <div class="hero-content">
        <div class="hero-badge">📊 Transparansi untuk Semua Warga</div>
        <h1>Kas Warga GMR 8</h1>
        <p>Semua warga berhak tahu kondisi keuangan RT kita. Di sini terbuka, dan bisa dicek siapapun kapanpun! 💚</p>
    </div>
</section>

<div class="container">

    <!-- SALDO KAS -->
    <div class="mt-2">
        <div class="saldo-display">
            <div class="saldo-label">💰 Saldo Kas RT saat ini</div>
            <div class="saldo-amount" data-count="<?= $saldo ?>"><?= formatRupiah($saldo) ?></div>
            <div class="saldo-sub">Per tanggal <?= date('d F Y') ?></div>
        </div>
    </div>

    <!-- STATS BULAN INI -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon">📈</div>
            <div class="stat-val" style="font-size:18px;color:var(--green-700);"><?= formatRupiah($masukBulanIni) ?></div>
            <div class="stat-label">Masuk <?= bulanNama($bulanIni) ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">📉</div>
            <div class="stat-val" style="font-size:18px;color:#e53935;"><?= formatRupiah($keluarBulanIni) ?></div>
            <div class="stat-label">Keluar <?= bulanNama($bulanIni) ?></div>
        </div>
    </div>

    <!-- KEPATUHAN IURAN -->
    <?php if ($iplTotal > 0 || $kasTotal > 0): ?>
    <div class="card" style="margin-bottom:16px;">
        <h3 style="font-size:15px;margin-bottom:14px;">🌿 Kepatuhan Iuran <?= bulanNama($bulanIni) ?></h3>

        <?php if ($iplTotal > 0):
            $iplPersen = round($iplLunas / $iplTotal * 100);
        ?>
        <div style="margin-bottom:14px;">
            <div class="flex-between mb-1">
                <span style="font-size:13px;font-weight:700;">IPL (Iuran Pengelolaan)</span>
                <span style="font-size:13px;font-weight:700;color:var(--green-700);"><?= $iplLunas ?>/<?= $iplTotal ?> · <?= $iplPersen ?>%</span>
            </div>
            <div class="progress-wrap">
                <div class="progress-bar" style="width:<?= $iplPersen ?>%"></div>
            </div>
            <div style="font-size:11px;color:var(--text-light);margin-top:4px;">
                <?= $iplLunas ?> lunas · <?= $iplPending ?> proses · <?= $iplTotal - $iplLunas - $iplPending ?> belum
            </div>
        </div>
        <?php endif; ?>

        <?php if ($kasTotal > 0):
            $kasPersen = round($kasLunas / $kasTotal * 100);
        ?>
        <div>
            <div class="flex-between mb-1">
                <span style="font-size:13px;font-weight:700;">Kas RT</span>
                <span style="font-size:13px;font-weight:700;color:var(--green-700);"><?= $kasLunas ?>/<?= $kasTotal ?> · <?= $kasPersen ?>%</span>
            </div>
            <div class="progress-wrap">
                <div class="progress-bar" style="width:<?= $kasPersen ?>%"></div>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- GRAFIK -->
    <?php if (!empty($chartLabels)): ?>
    <div class="chart-container">
        <h3 style="font-size:15px;margin-bottom:16px;">📊 Grafik 6 Bulan Terakhir</h3>
        <canvas id="kasChart" style="max-height:220px;"></canvas>
    </div>
    <?php endif; ?>

    <!-- REKAP TABEL -->
    <?php if (!empty($rekapBulan)): ?>
    <div class="section">
        <h2 class="section-title">📋 Rekap Bulanan</h2>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Bulan</th>
                        <th>Masuk</th>
                        <th>Keluar</th>
                        <th>Selisih</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (array_reverse($rekapBulan) as $r):
                        $selisih = $r['masuk'] - $r['keluar'];
                    ?>
                    <tr>
                        <td style="font-weight:600;"><?= bulanNama($r['bulan']) ?> <?= $r['tahun'] ?></td>
                        <td style="color:var(--green-700);font-weight:600;"><?= formatRupiah($r['masuk']) ?></td>
                        <td style="color:#e53935;font-weight:600;"><?= formatRupiah($r['keluar']) ?></td>
                        <td style="font-weight:700;color:<?= $selisih >= 0 ? 'var(--green-700)' : '#e53935' ?>;"><?= formatRupiah($selisih) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- MUTASI TERBARU -->
    <?php if (!empty($mutasiTerbaru)): ?>
    <div class="section">
        <h2 class="section-title">🔄 Mutasi Kas Terbaru</h2>
        <div style="display:flex;flex-direction:column;gap:8px;">
            <?php foreach ($mutasiTerbaru as $m): ?>
            <div style="display:flex;align-items:center;gap:12px;background:#fff;border:1px solid var(--border);border-radius:10px;padding:12px 14px;">
                <div style="width:36px;height:36px;border-radius:50%;display:flex;align-items:center;justify-content:center;background:<?= $m['tipe']==='masuk' ? 'var(--green-100)' : '#fce4ec' ?>;font-size:16px;flex-shrink:0;">
                    <?= $m['tipe']==='masuk' ? '📈' : '📉' ?>
                </div>
                <div style="flex:1;min-width:0;">
                    <div style="font-weight:700;font-size:13px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= htmlspecialchars($m['keterangan'] ?? '-') ?></div>
                    <div style="font-size:11px;color:var(--text-light);"><?= date('d M Y', strtotime($m['tanggal'])) ?></div>
                </div>
                <div style="font-weight:800;font-size:14px;color:<?= $m['tipe']==='masuk' ? 'var(--green-700)' : '#e53935' ?>;flex-shrink:0;">
                    <?= $m['tipe']==='masuk' ? '+' : '-' ?><?= formatRupiah($m['jumlah']) ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- EXPORT WHATSAPP -->
    <div class="section">
        <h2 class="section-title">📱 Rekap WhatsApp</h2>
        <p class="section-sub">Salin teks ini langsung ke grup WhatsApp RT ya! 👇</p>

        <div class="wa-box" id="wa-preview"><?= htmlspecialchars($waText) ?></div>

        <button id="copy-wa-btn" class="btn btn-primary btn-block mt-2" style="background:linear-gradient(135deg,#25D366,#128C7E);">
            <i class="fa-brands fa-whatsapp"></i>
            Copy Teks WA
        </button>
    </div>

</div>

<?php
$extraScript = '<script>
const labels = ' . json_encode($chartLabels) . ';
const masuk = ' . json_encode($chartMasuk) . ';
const keluar = ' . json_encode($chartKeluar) . ';
const ctx = document.getElementById("kasChart");
if(ctx){
    new Chart(ctx, {
        type: "bar",
        data: {
            labels,
            datasets:[
                {label:"Pemasukan",data:masuk,backgroundColor:"rgba(82,183,136,0.8)",borderRadius:6,borderSkipped:false},
                {label:"Pengeluaran",data:keluar,backgroundColor:"rgba(229,57,53,0.7)",borderRadius:6,borderSkipped:false}
            ]
        },
        options:{
            responsive:true,
            plugins:{
                legend:{position:"top",labels:{font:{family:"Nunito",weight:"700"},boxWidth:12}},
                tooltip:{callbacks:{label:ctx=>"Rp "+ctx.raw.toLocaleString("id-ID")}}
            },
            scales:{
                y:{ticks:{callback:v=>"Rp "+v.toLocaleString("id-ID"),font:{family:"Nunito",size:10}},grid:{color:"rgba(0,0,0,.05)"}},
                x:{ticks:{font:{family:"Nunito",size:10}},grid:{display:false}}
            }
        }
    });
}
</script>';
require_once 'includes/footer.php'; ?>
