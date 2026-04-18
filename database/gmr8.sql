-- ============================================================
-- DATABASE: Portal Warga RT 005 RW 012 GMR 8
-- Grand Madani Residence 2
-- ============================================================

CREATE DATABASE IF NOT EXISTS gmr8_portal CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE gmr8_portal;

-- ============================================================
-- TABLE: warga
-- ============================================================
CREATE TABLE IF NOT EXISTS warga (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama VARCHAR(100) NOT NULL,
    nomor_rumah VARCHAR(20) NOT NULL,
    no_wa VARCHAR(20),
    aktif TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================================
-- TABLE: pengurus (admin accounts)
-- ============================================================
CREATE TABLE IF NOT EXISTS pengurus (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama VARCHAR(100) NOT NULL,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin','ketua_rt','bendahara','sekretaris') NOT NULL DEFAULT 'sekretaris',
    aktif TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================================
-- TABLE: struktur_organisasi
-- ============================================================
CREATE TABLE IF NOT EXISTS struktur_organisasi (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama VARCHAR(100) NOT NULL,
    jabatan VARCHAR(100) NOT NULL,
    deskripsi TEXT,
    foto VARCHAR(255),
    no_wa VARCHAR(20),
    urutan INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================================
-- TABLE: kegiatan
-- ============================================================
CREATE TABLE IF NOT EXISTS kegiatan (
    id INT AUTO_INCREMENT PRIMARY KEY,
    judul VARCHAR(200) NOT NULL,
    agenda TEXT,
    deskripsi TEXT,
    lokasi VARCHAR(200),
    tanggal DATE NOT NULL,
    waktu TIME,
    status ENUM('mendatang','berlangsung','selesai') DEFAULT 'mendatang',
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES pengurus(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================================
-- TABLE: jenis_iuran
-- ============================================================
CREATE TABLE IF NOT EXISTS jenis_iuran (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama VARCHAR(100) NOT NULL,
    deskripsi TEXT,
    nominal DECIMAL(12,2) NOT NULL DEFAULT 0,
    periode ENUM('bulanan','tahunan','insidental') DEFAULT 'bulanan',
    aktif TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================================
-- TABLE: tagihan
-- ============================================================
CREATE TABLE IF NOT EXISTS tagihan (
    id INT AUTO_INCREMENT PRIMARY KEY,
    warga_id INT NOT NULL,
    jenis_iuran_id INT NOT NULL,
    bulan INT NOT NULL COMMENT '1-12',
    tahun INT NOT NULL,
    nominal DECIMAL(12,2) NOT NULL,
    status ENUM('belum_bayar','menunggu_verifikasi','lunas') DEFAULT 'belum_bayar',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (warga_id) REFERENCES warga(id) ON DELETE CASCADE,
    FOREIGN KEY (jenis_iuran_id) REFERENCES jenis_iuran(id) ON DELETE CASCADE,
    UNIQUE KEY unique_tagihan (warga_id, jenis_iuran_id, bulan, tahun)
) ENGINE=InnoDB;

-- ============================================================
-- TABLE: pembayaran
-- ============================================================
CREATE TABLE IF NOT EXISTS pembayaran (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tagihan_id INT NOT NULL,
    bukti_bayar VARCHAR(255),
    catatan TEXT,
    status ENUM('pending','verified','rejected') DEFAULT 'pending',
    verified_by INT,
    verified_at DATETIME,
    catatan_admin TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tagihan_id) REFERENCES tagihan(id) ON DELETE CASCADE,
    FOREIGN KEY (verified_by) REFERENCES pengurus(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================================
-- TABLE: kas (mutasi kas)
-- ============================================================
CREATE TABLE IF NOT EXISTS kas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tipe ENUM('masuk','keluar') NOT NULL,
    jumlah DECIMAL(12,2) NOT NULL,
    keterangan VARCHAR(255),
    pembayaran_id INT,
    tanggal DATE NOT NULL,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (pembayaran_id) REFERENCES pembayaran(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES pengurus(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================================
-- SEED DATA: Jenis Iuran
-- ============================================================
INSERT INTO jenis_iuran (nama, deskripsi, nominal, periode) VALUES
('IPL (Iuran Pengelolaan Lingkungan)', 'Iuran bulanan untuk pengelolaan dan perawatan lingkungan perumahan GMR 8, termasuk kebersihan, penerangan, dan fasilitas bersama.', 30000, 'bulanan'),
('Kas RT', 'Iuran bulanan kas RT untuk kegiatan sosial, hajatan warga, dan keperluan mendadak di lingkungan RT 005.', 20000, 'bulanan');

-- ============================================================
-- SEED DATA: Struktur Organisasi (placeholder)
-- ============================================================
INSERT INTO struktur_organisasi (nama, jabatan, deskripsi, no_wa, urutan) VALUES
('Budi Hartono', 'Ketua RT 005', 'Koordinator utama RT, bertanggung jawab atas keamanan, ketertiban, dan kesejahteraan seluruh warga GMR 8.', '081200000001', 1),
('Agus Setiawan', 'Wakil Ketua RT', 'Mendampingi Ketua RT dan membantu koordinasi kegiatan warga di lingkungan.', '081200000002', 2),
('Rina Marliani', 'Sekretaris', 'Pencatat rapi semua kegiatan RT, jadwal, dan surat-menyurat yang berkaitan dengan warga.', '081200000003', 3),
('Sari Dewi Rahayu', 'Bendahara', 'Pengelola keuangan RT yang transparan dan amanah, menjaga saldo kas tetap sehat untuk warga.', '081200000004', 4),
('Wahyu Nugraha', 'Seksi Keamanan', 'Koordinator keamanan lingkungan, jadwal ronda, dan tanggap darurat.', '081200000005', 5),
('Indah Permata', 'Seksi Kebersihan', 'Penggiat kebersihan lingkungan dan koordinator jadwal kerja bakti rutin.', '081200000006', 6),
('Teguh Priyono', 'Seksi Sosial', 'Koordinator kegiatan sosial, bantuan warga, dan acara kebersamaan RT.', '081200000007', 7);

-- ============================================================
-- SEED DATA: Kegiatan (sample)
-- ============================================================
INSERT INTO kegiatan (judul, agenda, deskripsi, lokasi, tanggal, waktu, status) VALUES
('Kerja Bakti Rutin April', 'Bersih-bersih selokan dan taman RT, pengecatan ulang tembok batas, perapihan tanaman.', 'Yuk bareng-bareng bersih-bersihin lingkungan GMR 8 kita! Makin asri, makin nyaman buat semua. Jangan lupa bawa peralatan ya — sapu, cangkul, atau apalah yang ada di rumah 😄', 'Area RT 005 GMR 8', '2026-04-13', '07:00:00', 'mendatang'),
('Rapat Warga Bulanan', 'Laporan kas RT, evaluasi kegiatan, serta usulan program lingkungan dari warga.', 'Saatnya kita ngobrol bareng soal lingkungan kita! Semua usulan dan masukan warga sangat diharapkan. Hadir yuuu~', 'Pos RT 005', '2026-04-20', '19:30:00', 'mendatang'),
('Senam Pagi Bersama', 'Senam aerobik, peregangan, dan olahraga ringan bersama warga.', 'Mulai bulan ini ada senam pagi bareng setiap Minggu! Gratis, seru, dan bikin badan segar. Ajak keluarga dan tetangga ya!', 'Lapangan GMR 8', '2026-04-06', '06:30:00', 'mendatang'),
('Pengajian RT', 'Tausiyah, doa bersama, dan silaturahmi antar warga.', 'Momen pengajian bareng warga GMR 8 yang selalu bikin hati adem. Yuk hadir dan bawa keluarga!', 'Masjid Al-Madani GMR 8', '2026-03-28', '19:00:00', 'selesai'),
('Kerja Bakti Maret', 'Pembersihan saluran air dan pengecatan tiang listrik lingkungan.', 'Alhamdulillah kerja bakti Maret berjalan lancar berkat semangat warga yang luar biasa! Makasih buat semua yang hadir 🙏', 'Seluruh Gang RT 005', '2026-03-16', '07:00:00', 'selesai'),
('Posyandu Balita', 'Penimbangan, imunisasi, dan konsultasi gizi balita.', 'Posyandu rutin untuk si kecil warga GMR 8. Jangan lupa bawa buku KIA ya Bun!', 'Posko Posyandu RT 005', '2026-03-10', '09:00:00', 'selesai');

-- ============================================================
-- SEED DATA: 54 Warga GMR 8
-- ============================================================
INSERT INTO warga (nama, nomor_rumah, no_wa, aktif) VALUES
('Ahmad Fauzi', 'GMR8-01', '081234560001', 1),
('Siti Rahayu', 'GMR8-02', '081234560002', 1),
('Budi Santoso', 'GMR8-03', '081234560003', 1),
('Dewi Lestari', 'GMR8-04', '081234560004', 1),
('Eko Prasetyo', 'GMR8-05', '081234560005', 1),
('Fitri Handayani', 'GMR8-06', '081234560006', 1),
('Gunawan Wibowo', 'GMR8-07', '081234560007', 1),
('Hani Marlena', 'GMR8-08', '081234560008', 1),
('Irwan Susanto', 'GMR8-09', '081234560009', 1),
('Juliana Putri', 'GMR8-10', '081234560010', 1),
('Kurniawan Hadi', 'GMR8-11', '081234560011', 1),
('Lina Marliani', 'GMR8-12', '081234560012', 1),
('Muhammad Rizki', 'GMR8-13', '081234560013', 1),
('Nani Suryani', 'GMR8-14', '081234560014', 1),
('Oki Firmansyah', 'GMR8-15', '081234560015', 1),
('Putri Rahmawati', 'GMR8-16', '081234560016', 1),
('Qori Ananda', 'GMR8-17', '081234560017', 1),
('Rudi Hermawan', 'GMR8-18', '081234560018', 1),
('Sari Dewi', 'GMR8-19', '081234560019', 1),
('Taufik Hidayat', 'GMR8-20', '081234560020', 1),
('Usman Hakim', 'GMR8-21', '081234560021', 1),
('Vina Oktaviani', 'GMR8-22', '081234560022', 1),
('Wahyu Nugraha', 'GMR8-23', '081234560023', 1),
('Yudi Pratama', 'GMR8-24', '081234560024', 1),
('Zahra Aulia', 'GMR8-25', '081234560025', 1),
('Abdul Latif', 'GMR8-26', '081234560026', 1),
('Bagas Setiawan', 'GMR8-27', '081234560027', 1),
('Cynthia Anggraini', 'GMR8-28', '081234560028', 1),
('Dimas Aryanto', 'GMR8-29', '081234560029', 1),
('Emi Susanti', 'GMR8-30', '081234560030', 1),
('Farid Naufal', 'GMR8-31', '081234560031', 1),
('Gilang Ramadhan', 'GMR8-32', '081234560032', 1),
('Heri Kusnadi', 'GMR8-33', '081234560033', 1),
('Indah Permata', 'GMR8-34', '081234560034', 1),
('Joko Supriyanto', 'GMR8-35', '081234560035', 1),
('Kiki Amalia', 'GMR8-36', '081234560036', 1),
('Lukman Hakim', 'GMR8-37', '081234560037', 1),
('Melani Kurniasih', 'GMR8-38', '081234560038', 1),
('Nanda Pratiwi', 'GMR8-39', '081234560039', 1),
('Oman Candra', 'GMR8-40', '081234560040', 1),
('Putri Cahyani', 'GMR8-41', '081234560041', 1),
('Rahmad Ardiansyah', 'GMR8-42', '081234560042', 1),
('Sinta Nuriyah', 'GMR8-43', '081234560043', 1),
('Teguh Priyono', 'GMR8-44', '081234560044', 1),
('Ulfah Hasanah', 'GMR8-45', '081234560045', 1),
('Vicky Ardiansyah', 'GMR8-46', '081234560046', 1),
('Wulan Sari', 'GMR8-47', '081234560047', 1),
('Hendra Saputra', 'GMR8-48', '081234560048', 1),
('Yoga Pratama', 'GMR8-49', '081234560049', 1),
('Zainal Abidin', 'GMR8-50', '081234560050', 1),
('Asep Sopian', 'GMR8-51', '081234560051', 1),
('Bella Oktavia', 'GMR8-52', '081234560052', 1),
('Cahyo Nugroho', 'GMR8-53', '081234560053', 1),
('Dian Puspita', 'GMR8-54', '081234560054', 1);

-- ============================================================
-- SEED DATA: Sample Kas (saldo awal & beberapa transaksi lama)
-- ============================================================
INSERT INTO kas (tipe, jumlah, keterangan, tanggal) VALUES
('masuk', 2700000, 'Saldo awal kas RT periode Januari 2026', '2026-01-01'),
('keluar', 150000, 'Pembelian alat kebersihan (sapu, kemoceng)', '2026-01-15'),
('keluar', 200000, 'Biaya perbaikan lampu jalan gang A', '2026-02-05'),
('keluar', 100000, 'Konsumsi rapat bulanan Februari', '2026-02-20'),
('keluar', 250000, 'Cat dan bahan kerja bakti Maret', '2026-03-16'),
('keluar', 75000, 'Konsumsi pengajian RT Maret', '2026-03-28');

-- ============================================================
-- TABLE: tutorial (Portal Blog / Edukasi Warga)
-- ============================================================
CREATE TABLE IF NOT EXISTS tutorial (
    id INT AUTO_INCREMENT PRIMARY KEY,
    judul VARCHAR(255) NOT NULL,
    konten TEXT NOT NULL,
    foto VARCHAR(255),
    youtube_url VARCHAR(255),
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES pengurus(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================================
-- NOTE: Akun admin dibuat via setup.php
-- Jalankan http://localhost/GMR8/setup.php setelah import SQL ini
-- ============================================================

-- Table structure for table `cctv`
CREATE TABLE IF NOT EXISTS `cctv` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `lokasi` varchar(100) NOT NULL,
  `url_m3u8` text NOT NULL,
  `tipe` enum('ATCS','Internal') DEFAULT 'ATCS',
  `status` enum('Aktif','Nonaktif') DEFAULT 'Aktif',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
