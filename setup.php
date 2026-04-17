<?php
// ============================================================
// setup.php — One-time installer for Portal Warga GMR 8
// Jalankan sekali, lalu hapus file ini!
// ============================================================
require_once 'includes/db.php';

$done = false;
$errors = [];
$success = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Hapus pengurus lama jika ada
        $pdo->exec("DELETE FROM pengurus");

        $accounts = [
            ['Mariadi', 'ketuart', 'gmr854', 'ketua_rt'],
            ['M. Fathur', 'bendahara', 'gmr820', 'bendahara'],
            ['Sekretaris', 'sekretaris', 'gmr824', 'sekretaris'],
            ['Super Admin', 'admin', 'gmr8zz', 'admin'],
        ];

        $stmt = $pdo->prepare("INSERT INTO pengurus (nama, username, password, role) VALUES (?, ?, ?, ?)");
        foreach ($accounts as $acc) {
            $hashed = password_hash($acc[2], PASSWORD_DEFAULT);
            $stmt->execute([$acc[0], $acc[1], $hashed, $acc[3]]);
            $success[] = "✅ Akun <strong>{$acc[1]}</strong> ({$acc[3]}) berhasil dibuat";
        }
        $done = true;
    } catch (PDOException $e) {
        $errors[] = "Error: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Setup — Portal Warga GMR 8</title>
<link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;700;800&display=swap" rel="stylesheet">
<style>
body{font-family:'Nunito',sans-serif;background:#f0fbf4;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px;}
.box{background:#fff;border-radius:20px;padding:36px 28px;max-width:480px;width:100%;box-shadow:0 8px 40px rgba(45,106,79,.15);}
h1{color:#2D6A4F;font-size:24px;margin-bottom:6px;}
p{color:#6b8f7a;font-size:14px;margin-bottom:20px;}
.alert{padding:12px 16px;border-radius:10px;margin-bottom:10px;font-size:14px;}
.alert-success{background:#d8f3dc;color:#1B4332;}
.alert-error{background:#fce4ec;color:#c62828;}
.card{background:#f0fbf4;border-radius:12px;padding:16px;margin-bottom:20px;font-size:14px;color:#2D6A4F;}
.card table{width:100%;border-collapse:collapse;}
.card td{padding:5px 8px;border-bottom:1px solid #d8f3dc;}
.card td:first-child{font-weight:700;width:50%;}
.btn{width:100%;padding:14px;background:linear-gradient(135deg,#2D6A4F,#52B788);color:#fff;border:none;border-radius:10px;font-family:'Nunito',sans-serif;font-size:16px;font-weight:700;cursor:pointer;margin-top:10px;}
.btn:hover{opacity:.9;}
.warn{color:#e65100;font-size:13px;background:#fff8e1;border:1px solid #ffe0b2;border-radius:8px;padding:12px;margin-top:16px;}
</style>
</head>
<body>
<div class="box">
    <h1>🌿 Setup Portal Warga GMR 8</h1>
    <p>Script ini akan membuat akun admin untuk Panel Pengurus. Jalankan sekali saja, lalu hapus file ini.</p>

    <?php foreach ($errors as $e): ?>
        <div class="alert alert-error"><?= $e ?></div>
    <?php endforeach; ?>

    <?php if ($done): ?>
        <?php foreach ($success as $s): ?>
            <div class="alert alert-success"><?= $s ?></div>
        <?php endforeach; ?>
        <div class="warn">⚠️ <strong>Penting!</strong> Hapus file <code>setup.php</code> setelah ini untuk keamanan!</div>
        <div style="margin-top:20px;text-align:center;">
            <a href="admin/login.php" style="display:inline-block;padding:12px 28px;background:#2D6A4F;color:#fff;border-radius:10px;font-weight:700;text-decoration:none;">
                Ke Halaman Login Admin →
            </a>
        </div>
    <?php else: ?>
        <div class="card">
            <p style="font-weight:700;margin-bottom:8px;">Akun yang akan dibuat:</p>
            <table>
                <tr><td>Ketua RT</td><td>ketuart / ketuart123</td></tr>
                <tr><td>Bendahara</td><td>bendahara / bendahara123</td></tr>
                <tr><td>Sekretaris</td><td>sekretaris / sekretaris123</td></tr>
                <tr><td>Super Admin</td><td>admin / admin123</td></tr>
            </table>
        </div>
        <div class="warn">⚠️ Pastikan sudah import file <code>database/gmr8.sql</code> di phpMyAdmin sebelum menjalankan setup ini.</div>
        <form method="POST">
            <button type="submit" class="btn">🚀 Jalankan Setup & Buat Akun Admin</button>
        </form>
    <?php endif; ?>
</div>
</body>
</html>
