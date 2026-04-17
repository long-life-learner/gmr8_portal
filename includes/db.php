<?php
// ============================================================
// Load Environment Variables
// ============================================================
function loadEnv($path)
{
    if (!file_exists($path)) {
        return false;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) {
            continue;
        }

        if (strpos($line, '=') !== false) {
            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);

            // Strip quotes if present
            $value = trim($value, " \t\n\r\0\x0B\"'");

            if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
                putenv(sprintf('%s=%s', $name, $value));
                $_ENV[$name] = $value;
                $_SERVER[$name] = $value;
            }
        }
    }
    return true;
}

// Load .env from root directory
loadEnv(__DIR__ . '/../.env');

define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('DB_NAME') ?: 'gmr8_portal');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_CHARSET', getenv('DB_CHARSET') ?: 'utf8mb4');

define('SITE_NAME', getenv('SITE_NAME') ?: 'Portal Warga GMR 8');
define('SITE_URL', getenv('SITE_URL') ?: 'http://gmr8.test');
define('UPLOAD_PATH', __DIR__ . '/../assets/uploads/');
define('UPLOAD_URL', SITE_URL . '/assets/uploads/');

// Rekening bank untuk pembayaran
define('BANK_NAMA', getenv('BANK_NAMA') ?: 'BCA');
define('BANK_NOMOR', getenv('BANK_NOMOR') ?: '1234567890');
define('BANK_ATAS_NAMA', getenv('BANK_ATAS_NAMA') ?: 'Sari Dewi Rahayu');

$dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

try {
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (PDOException $e) {
    die('<div style="font-family:sans-serif;padding:30px;background:#fff0f0;border:2px solid #f00;border-radius:8px;max-width:500px;margin:50px auto;">
        <h3 style="color:#c00;">⚠️ Koneksi Database Gagal</h3>
        <p>Pastikan XAMPP sudah berjalan dan database <strong>gmr8_portal</strong> sudah diimport.</p>
        <p style="color:#888;font-size:13px;">Error: ' . htmlspecialchars($e->getMessage()) . '</p>
        <a href="' . SITE_URL . '/setup.php" style="display:inline-block;padding:10px 20px;background:#2D6A4F;color:#fff;border-radius:6px;text-decoration:none;">Jalankan Setup</a>
    </div>');
}

// Helper function
function formatRupiah($angka)
{
    return 'Rp ' . number_format($angka, 0, ',', '.');
}

function bulanNama($bulan)
{
    $nama = [
        '',
        'Januari',
        'Februari',
        'Maret',
        'April',
        'Mei',
        'Juni',
        'Juli',
        'Agustus',
        'September',
        'Oktober',
        'November',
        'Desember'
    ];
    return $nama[(int) $bulan] ?? '';
}

function getSaldoKas($pdo)
{
    $stmt = $pdo->query("SELECT 
        SUM(CASE WHEN tipe='masuk' THEN jumlah ELSE 0 END) as total_masuk,
        SUM(CASE WHEN tipe='keluar' THEN jumlah ELSE 0 END) as total_keluar
        FROM kas");
    $row = $stmt->fetch();
    return ($row['total_masuk'] ?? 0) - ($row['total_keluar'] ?? 0);
}
