# 🤖 Panduan Setup WhatsApp Bot GMR 8

## ⚡ QUICK START (Lokal — Sudah Running)

Bot sudah berjalan dengan `npm run dev`. Ikuti urutan ini:

### Tahap 1 — Scan QR di terminal
Lihat terminal `npm run dev` → QR muncul → scan dengan WhatsApp HP bot.

### Tahap 2 — Tambahkan bot ke grup RT
Di HP bot: buka kontak yang sudah scan → tambahkan ke grup WhatsApp RT.

### Tahap 3 — Dapatkan Group ID
Buka terminal **baru** di folder `wa-bot/`:
```bash
node list-groups.js
```
Akan muncul daftar grup beserta ID-nya.

### Tahap 4 — Update .env
Edit file `.env`:
```env
GROUP_ID=628xxxxxxxxxx-xxxxxxxxxx@g.us   ← ganti dengan ID nyata
```

### Tahap 5 — Restart bot
```bash
# Ctrl+C untuk stop, lalu:
npm run dev
```

### Tahap 6 — Test di grup
Kirim pesan di grup WhatsApp RT:
```
!bantuan
```
Bot harus membalas dengan daftar perintah ✅

---



## Arsitektur Sistem

```
Warga GMR 8 (WhatsApp Grup)
        │
        ▼ pesan/command
  ┌─────────────┐        HTTP API Call        ┌──────────────────┐
  │  WA Bot     │ ─────────────────────────── │  PHP API         │
  │  (Node.js)  │  X-Api-Key: [secret]        │  wa-webhook.php  │
  │  Baileys    │ ─────────────────────────── │                  │
  └─────────────┘                             └──────────────────┘
                                                       │
                                                       ▼
                                               ┌──────────────────┐
                                               │  MySQL Database  │
                                               │  gmr8_portal     │
                                               └──────────────────┘
```

---

## Langkah 1: Konfigurasi API Key PHP

Buka `api/wa-webhook.php`, cari baris ini:

```php
define('WA_API_KEY', 'GANTI-DENGAN-KEY-RAHASIA-PANJANG-ANDA');
```

Ganti dengan key acak panjang, misalnya pakai password generator:
```
GMR8-RT005-2025-super-secret-key-xyz789!
```

---

## Langkah 2: Setup di Hosting (DirectAdmin)

### A. Upload File
Upload seluruh folder `wa-bot/` ke hosting via FTP/FileManager.
Disarankan letakkan di:
```
/home/namauser/wa-bot/
```
(Di luar public_html agar lebih aman)

### B. Aktifkan Node.js di DirectAdmin
1. Login DirectAdmin
2. Menu **Extra Features** → **Node.js**
3. Klik **Setup Node.js App**
4. Isi:
   - **Node.js version**: 18 atau 20 (pilih yang tersedia)
   - **Application mode**: Production
   - **Application root**: `/home/namauser/wa-bot`
   - **Application URL**: (kosongkan atau isi subdomain)
   - **Application startup file**: `bot.js`

### C. Install Dependencies via SSH

```bash
# SSH ke hosting
ssh namauser@namahosting.com

# Masuk ke folder bot
cd ~/wa-bot

# Salin dan edit file .env
cp .env.example .env
nano .env
```

Isi `.env`:
```env
SITE_URL=https://namadomainanda.com/GMR8
WA_API_KEY=GMR8-RT005-2025-super-secret-key-xyz789!
GROUP_ID=              # ← Isi setelah langkah scan QR
BOT_NUMBER=628xxxx     # ← Nomor HP yang dipakai bot
BOT_NAME=Bot GMR 8
LOG_LEVEL=silent
```

Install dependencies:
```bash
npm install
```

---

## Langkah 3: Scan QR Code (Autentikasi Pertama)

> ⚠️ QR hanya bisa discan SEKALI. Siapkan HP dengan nomor yang akan jadi bot.

```bash
# Jalankan bot manual pertama kali (di SSH)
node bot.js
```

Bot akan tampilkan QR di terminal. **Scan dengan WhatsApp** di HP bot:
- Buka WhatsApp → ⋮ → Perangkat Tertaut → Tautkan Perangkat

Setelah scan berhasil, Anda akan lihat:
```
✅ [BOT] Bot GMR 8 berhasil terhubung ke WhatsApp!
```

---

## Langkah 4: Dapatkan Group ID

1. Tambahkan nomor bot ke grup WhatsApp RT
2. Kirim pesan apapun di grup
3. Di terminal/ log bot, akan muncul:
   ```
   [BOT] Pesan dari grup: 628xxxxxxxxxx-xxxxxxxxxx@g.us
   ```
4. Salin ID tersebut ke `.env`:
   ```env
   GROUP_ID=628xxxxxxxxxx-xxxxxxxxxx@g.us
   ```
5. Restart bot:
   ```bash
   # Ctrl+C untuk stop, lalu:
   node bot.js
   ```

---

## Langkah 5: Jalankan dengan PM2 (Background + Auto-restart)

```bash
# Install PM2 global
npm install -g pm2

# Jalankan dengan PM2
pm2 start ecosystem.config.cjs

# Lihat status
pm2 status

# Lihat log real-time
pm2 logs gmr8-wa-bot

# Start otomatis saat server reboot
pm2 save
pm2 startup
```

---

## Perintah Bot di WhatsApp Grup

| Perintah | Fungsi |
|---|---|
| `!bantuan` | Tampilkan daftar perintah |
| `!saldo` | Cek saldo kas RT saat ini |
| `!iuran` | Lihat jenis iuran & nominal |
| `!cek` | Siapa saja yang belum bayar (semua iuran) |
| `!cek IPL` | Siapa yang belum bayar IPL |
| `!cek Kas` | Siapa yang belum bayar Kas RT |
| `!bayar Ahmad` | Catat pembayaran atas nama Ahmad |
| `!bayar` | Mulai alur catat pembayaran interaktif |
| `!laporan` | Laporan ringkasan siap kirim ke grup |
| `!statistik` | Grafik kepatuhan iuran bulan ini |
| `!kegiatan` | Info kegiatan mendatang |

### Alur `!bayar`:
```
Warga: !bayar Budi Santoso
Bot:   ✅ Ditemukan: Budi Santoso (GMR8-12)
       IPL — Rp 30.000
       Balas ya untuk konfirmasi atau batal.
Warga: ya
Bot:   🎉 Dicatat! Status: Menunggu verifikasi bendahara
       📸 PENTING: Mohon upload bukti di: https://.../iuran.php
```

---

## Keamanan

- API key disimpan di `.env` (jangan di-commit ke Git)
- Folder `auth_info/` berisi sesi WhatsApp — JANGAN dishare
- Tambahkan ke `.gitignore`:
  ```
  auth_info/
  .env
  logs/
  node_modules/
  ```

---

## Troubleshooting

| Masalah | Solusi |
|---|---|
| Bot tidak merespon | Cek `pm2 logs gmr8-wa-bot` |
| QR tidak muncul | Hapus `auth_info/` dan restart |
| "Unauthorized" dari API | API key di `.env` dan `wa-webhook.php` harus sama persis |
| Bot tidak kenal grup | Cek `GROUP_ID` di `.env`, pastikan format benar |
| Pesan tidak terdeteksi | Pastikan bot ada di grup dan GROUP_ID sudah diset |
| Node.js tidak tersedia | Hubungi hosting untuk aktifkan Node.js 18+ |
