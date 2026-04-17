<?php
// ============================================================
// api/wa-webhook.php — REST API untuk WhatsApp Bot GMR8
// Secured by API Key
// ============================================================

require_once '../includes/db.php';

// ============================================================
// KONFIGURASI API KEY
// Ganti dengan key rahasia Anda (simpan sama di .env bot)
// ============================================================
define('WA_API_KEY', 'GMR8-RT005-2025-super-secret-key-xyz789!');

// CORS & Headers
header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');

// Validasi API Key
$apiKey = $_SERVER['HTTP_X_API_KEY'] ?? ($_GET['api_key'] ?? '');
if ($apiKey !== WA_API_KEY) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// ===========================================================
// ROUTER
// ===========================================================
try {
    switch ($action) {

        // ---------------------------------------------------
        // GET: Daftar warga belum bayar
        // ?action=belum_bayar&jenis_id=1&bulan=4&tahun=2026
        // ---------------------------------------------------
        case 'belum_bayar':
            $jenisId = (int) ($_GET['jenis_id'] ?? 0);
            $bulan = (int) ($_GET['bulan'] ?? date('n'));
            $tahun = (int) ($_GET['tahun'] ?? date('Y'));

            if ($jenisId) {
                $stmt = $pdo->prepare("
                    SELECT t.id as tagihan_id, w.nama, w.nomor_rumah, t.nominal
                    FROM tagihan t
                    JOIN warga w ON w.id = t.warga_id
                    WHERE t.jenis_iuran_id=? AND t.bulan=? AND t.tahun=?
                      AND t.status='belum_bayar' AND w.aktif=1
                    ORDER BY w.nomor_rumah ASC
                ");
                $stmt->execute([$jenisId, $bulan, $tahun]);
                $data = $stmt->fetchAll();
            } else {
                // Semua jenis iuran
                $stmt = $pdo->prepare("
                    SELECT t.id as tagihan_id, w.nama, w.nomor_rumah, t.nominal,
                           j.nama as jenis_nama
                    FROM tagihan t
                    JOIN warga w ON w.id = t.warga_id
                    JOIN jenis_iuran j ON j.id = t.jenis_iuran_id
                    WHERE t.bulan=? AND t.tahun=? AND t.status='belum_bayar' AND w.aktif=1
                    ORDER BY j.nama, w.nomor_rumah ASC
                ");
                $stmt->execute([$bulan, $tahun]);
                $data = $stmt->fetchAll();
            }
            echo json_encode(['ok' => true, 'data' => $data, 'count' => count($data)]);
            break;

        // ---------------------------------------------------
        // GET: Saldo Kas
        // ?action=saldo
        // ---------------------------------------------------
        case 'saldo':
            $saldo = getSaldoKas($pdo);
            $bulan = (int) date('n');
            $tahun = (int) date('Y');

            $statsBulan = $pdo->prepare("
                SELECT SUM(CASE WHEN tipe='masuk' THEN jumlah ELSE 0 END) as masuk,
                       SUM(CASE WHEN tipe='keluar' THEN jumlah ELSE 0 END) as keluar
                FROM kas WHERE MONTH(tanggal)=? AND YEAR(tanggal)=?
            ");
            $statsBulan->execute([$bulan, $tahun]);
            $stats = $statsBulan->fetch();

            echo json_encode([
                'ok' => true,
                'saldo' => $saldo,
                'bulan' => bulanNama($bulan) . ' ' . $tahun,
                'masuk_bulan_ini' => (float) ($stats['masuk'] ?? 0),
                'keluar_bulan_ini' => (float) ($stats['keluar'] ?? 0),
            ]);
            break;

        // ---------------------------------------------------
        // GET: Jenis Iuran aktif
        // ?action=jenis_iuran
        // ---------------------------------------------------
        case 'jenis_iuran':
            $data = $pdo->query("SELECT id, nama, nominal, periode FROM jenis_iuran WHERE aktif=1 ORDER BY id ASC")->fetchAll();
            echo json_encode(['ok' => true, 'data' => $data]);
            break;

        // ---------------------------------------------------
        // GET: Kegiatan mendatang
        // ?action=kegiatan&limit=3
        // ---------------------------------------------------
        case 'kegiatan':
            $limit = min((int) ($_GET['limit'] ?? 3), 10);
            $stmt = $pdo->prepare("
                SELECT judul, agenda, lokasi, tanggal, waktu
                FROM kegiatan WHERE tanggal >= CURDATE()
                ORDER BY tanggal ASC LIMIT ?
            ");
            $stmt->execute([$limit]);
            echo json_encode(['ok' => true, 'data' => $stmt->fetchAll()]);
            break;

        // ---------------------------------------------------
        // GET: Cari warga berdasarkan nama (fuzzy)
        // ?action=cari_warga&nama=Ahmad
        // ---------------------------------------------------
        case 'cari_warga':
            $nama = trim($_GET['nama'] ?? '');
            if (!$nama) {
                echo json_encode(['ok' => false, 'error' => 'Nama wajib diisi']);
                break;
            }

            $bulan = (int) ($_GET['bulan'] ?? date('n'));
            $tahun = (int) ($_GET['tahun'] ?? date('Y'));
            $jenisId = (int) ($_GET['jenis_id'] ?? 0);

            $likeNama = '%' . $nama . '%';
            $whereJenis = $jenisId ? "AND j.id = $jenisId" : '';

            $stmt = $pdo->prepare("
                SELECT t.id as tagihan_id, w.nama, w.nomor_rumah, t.nominal,
                       t.status, j.nama as jenis_nama, j.id as jenis_id
                FROM tagihan t
                JOIN warga w ON w.id = t.warga_id
                JOIN jenis_iuran j ON j.id = t.jenis_iuran_id
                WHERE w.nama LIKE ? AND t.bulan=? AND t.tahun=?
                  AND w.aktif=1 $whereJenis
                ORDER BY w.nama ASC
            ");
            $stmt->execute([$likeNama, $bulan, $tahun]);
            $data = $stmt->fetchAll();
            echo json_encode(['ok' => true, 'data' => $data, 'count' => count($data)]);
            break;

        // ---------------------------------------------------
        // GET: Statistik kepatuhan bulan ini
        // ?action=statistik&bulan=4&tahun=2026
        // ---------------------------------------------------
        case 'statistik':
            $bulan = (int) ($_GET['bulan'] ?? date('n'));
            $tahun = (int) ($_GET['tahun'] ?? date('Y'));

            $stmt = $pdo->prepare("
                SELECT j.nama as jenis,
                       COUNT(t.id) as total,
                       SUM(t.status='lunas') as lunas,
                       SUM(t.status='menunggu_verifikasi') as pending,
                       SUM(t.status='belum_bayar') as belum
                FROM tagihan t
                JOIN jenis_iuran j ON j.id = t.jenis_iuran_id
                WHERE t.bulan=? AND t.tahun=?
                GROUP BY j.id, j.nama
            ");
            $stmt->execute([$bulan, $tahun]);
            $data = $stmt->fetchAll();

            echo json_encode([
                'ok' => true,
                'bulan' => bulanNama($bulan) . ' ' . $tahun,
                'data' => $data,
            ]);
            break;

        // ---------------------------------------------------
        // POST: Catat pembayaran via WhatsApp (pending)
        // Body JSON: { tagihan_id, catatan_wa, nama_pengirim }
        // ---------------------------------------------------
        case 'catat_bayar':
            if ($method !== 'POST') {
                http_response_code(405);
                echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
                break;
            }

            $body = json_decode(file_get_contents('php://input'), true);
            $tagihanId = (int) ($body['tagihan_id'] ?? 0);
            $catatanWa = trim($body['catatan_wa'] ?? '');
            $namaPengirim = trim($body['nama_pengirim'] ?? '');

            if (!$tagihanId) {
                echo json_encode(['ok' => false, 'error' => 'tagihan_id wajib diisi']);
                break;
            }

            // Cek tagihan
            $stmt = $pdo->prepare("
                SELECT t.*, w.nama, w.nomor_rumah, j.nama as jenis_nama
                FROM tagihan t
                JOIN warga w ON w.id = t.warga_id
                JOIN jenis_iuran j ON j.id = t.jenis_iuran_id
                WHERE t.id = ?
            ");
            $stmt->execute([$tagihanId]);
            $tagihan = $stmt->fetch();

            if (!$tagihan) {
                echo json_encode(['ok' => false, 'error' => 'Tagihan tidak ditemukan']);
                break;
            }

            if ($tagihan['status'] !== 'belum_bayar') {
                echo json_encode([
                    'ok' => false,
                    'error' => 'Tagihan sudah dalam status: ' . $tagihan['status'],
                    'status' => $tagihan['status']
                ]);
                break;
            }

            // Cek apakah sudah ada pembayaran pending untuk tagihan ini
            $cekPending = $pdo->prepare("SELECT id FROM pembayaran WHERE tagihan_id=? AND status='pending'");
            $cekPending->execute([$tagihanId]);
            if ($cekPending->fetchColumn()) {
                echo json_encode(['ok' => false, 'error' => 'Sudah ada pembayaran pending untuk tagihan ini']);
                break;
            }

            try {
                $pdo->beginTransaction();

                $catatan = "[Dicatat via WhatsApp]";
                if ($namaPengirim)
                    $catatan .= " Dilaporkan oleh: $namaPengirim";
                if ($catatanWa)
                    $catatan .= " | Pesan: $catatanWa";

                // Insert pembayaran tanpa bukti (perlu upload manual di web)
                $pdo->prepare("
                    INSERT INTO pembayaran (tagihan_id, catatan, status, bukti_bayar)
                    VALUES (?, ?, 'pending', NULL)
                ")->execute([$tagihanId, $catatan]);

                // Update status tagihan
                $pdo->prepare("UPDATE tagihan SET status='menunggu_verifikasi' WHERE id=?")
                    ->execute([$tagihanId]);

                $pdo->commit();

                echo json_encode([
                    'ok' => true,
                    'message' => 'Pembayaran berhasil dicatat, menunggu verifikasi bendahara',
                    'warga' => $tagihan['nama'],
                    'nomor_rumah' => $tagihan['nomor_rumah'],
                    'jenis' => $tagihan['jenis_nama'],
                    'nominal' => $tagihan['nominal'],
                ]);
            } catch (PDOException $e) {
                $pdo->rollBack();
                echo json_encode(['ok' => false, 'error' => 'DB error: ' . $e->getMessage()]);
            }
            break;

        // ---------------------------------------------------
        // GET: Laporan ringkasan untuk WA
        // ?action=laporan_wa&bulan=4&tahun=2026
        // ---------------------------------------------------
        case 'laporan_wa':
            $bulan = (int) ($_GET['bulan'] ?? date('n'));
            $tahun = (int) ($_GET['tahun'] ?? date('Y'));
            $saldo = getSaldoKas($pdo);

            $stats = $pdo->prepare("
                SELECT SUM(CASE WHEN tipe='masuk' THEN jumlah ELSE 0 END) as masuk,
                       SUM(CASE WHEN tipe='keluar' THEN jumlah ELSE 0 END) as keluar
                FROM kas WHERE MONTH(tanggal)=? AND YEAR(tanggal)=?
            ");
            $stats->execute([$bulan, $tahun]);
            $statsBulan = $stats->fetch();

            $iuran = $pdo->prepare("
                SELECT j.nama, COUNT(t.id) as total,
                       SUM(t.status='lunas') as lunas,
                       SUM(t.status='belum_bayar') as belum
                FROM tagihan t
                JOIN jenis_iuran j ON j.id = t.jenis_iuran_id
                WHERE t.bulan=? AND t.tahun=?
                GROUP BY j.id, j.nama
            ");
            $iuran->execute([$bulan, $tahun]);
            $dataIuran = $iuran->fetchAll();

            $belumIPL = $pdo->prepare("
                SELECT w.nama, w.nomor_rumah
                FROM tagihan t JOIN warga w ON w.id=t.warga_id
                JOIN jenis_iuran j ON j.id=t.jenis_iuran_id
                WHERE t.bulan=? AND t.tahun=? AND t.status='belum_bayar' AND j.nama LIKE '%IPL%'
                ORDER BY w.nomor_rumah ASC
            ");
            $belumIPL->execute([$bulan, $tahun]);
            $belumIplData = $belumIPL->fetchAll();

            // Build WA text
            $text = "📊 *LAPORAN KAS RT 005 · GMR 8*\n";
            $text .= "Periode: " . bulanNama($bulan) . " $tahun\n";
            $text .= "Tanggal: " . date('d F Y') . "\n";
            $text .= "━━━━━━━━━━━━━━━━━━━━\n\n";
            $text .= "💰 *SALDO KAS*\n";
            $text .= "Saldo Saat Ini: *" . formatRupiah($saldo) . "*\n";
            $text .= "Masuk: " . formatRupiah((float) ($statsBulan['masuk'] ?? 0)) . "\n";
            $text .= "Keluar: " . formatRupiah((float) ($statsBulan['keluar'] ?? 0)) . "\n\n";

            foreach ($dataIuran as $d) {
                $persen = $d['total'] > 0 ? round($d['lunas'] / $d['total'] * 100) : 0;
                $text .= "🌿 *{$d['nama']}*\n";
                $text .= "Lunas: {$d['lunas']}/{$d['total']} warga ($persen%)\n";
                $text .= "Belum: {$d['belum']} warga\n\n";
            }

            if (!empty($belumIplData)) {
                $text .= "📌 *IPL Belum Bayar:*\n";
                foreach ($belumIplData as $i => $b) {
                    $text .= ($i + 1) . ". {$b['nama']} ({$b['nomor_rumah']})\n";
                }
                $text .= "\n";
            }
            $text .= "━━━━━━━━━━━━━━━━━━━━\n";
            $text .= "🙏 Terima kasih warga GMR 8! 💚";

            echo json_encode(['ok' => true, 'text' => $text, 'saldo' => $saldo]);
            break;

        default:
            http_response_code(404);
            echo json_encode(['ok' => false, 'error' => "Action '$action' tidak dikenal"]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
