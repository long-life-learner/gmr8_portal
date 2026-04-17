<?php
// ============================================================
// admin/login.php — Login Panel Admin
// Portal Warga RT 005 RW 012 GMR 8
// ============================================================
require_once '../includes/db.php';
require_once '../includes/auth.php';

if (isLoggedIn()) {
    header('Location: ../dashboard/');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!$username || !$password) {
        $error = 'Username dan password wajib diisi ya!';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM pengurus WHERE username = ? AND aktif = 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['admin_id']   = $user['id'];
            $_SESSION['admin_nama'] = $user['nama'];
            $_SESSION['admin_role'] = $user['role'];

            $redirect = $_GET['redirect'] ?? 'dashboard.php';
            header('Location: ' . (strpos($redirect, 'admin/') !== false ? SITE_URL . '/admin/' . str_replace('.php', '/', basename($redirect)) : SITE_URL . '/admin/dashboard/'));
            exit;
        } else {
            $error = 'Username atau password salah. Coba lagi ya!';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login Admin — Portal Warga GMR 8</title>
<link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800&display=swap" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<link href="<?= SITE_URL ?>/assets/css/style.css" rel="stylesheet">
<style>
body{padding-bottom:0;min-height:100vh;display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,var(--green-900) 0%,var(--green-700) 50%,var(--green-500) 100%);}
.login-wrap{width:100%;max-width:380px;padding:20px;}
.login-card{background:#fff;border-radius:24px;padding:36px 28px;box-shadow:0 20px 60px rgba(0,0,0,.25);}
.login-logo{text-align:center;margin-bottom:28px;}
.login-logo .icon{width:72px;height:72px;background:linear-gradient(135deg,var(--green-800),var(--green-500));border-radius:20px;display:flex;align-items:center;justify-content:center;margin:0 auto 12px;font-size:30px;box-shadow:0 8px 24px rgba(45,106,79,.35);}
.login-logo h1{font-size:20px;color:var(--green-900);}
.login-logo p{font-size:13px;color:var(--text-light);}
.input-group{position:relative;margin-bottom:16px;}
.input-group i{position:absolute;left:14px;top:50%;transform:translateY(-50%);color:var(--text-light);font-size:16px;}
.input-group input{padding-left:42px;}
.toggle-pw{position:absolute;right:14px;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--text-light);cursor:pointer;font-size:16px;padding:4px;}
.back-link{display:block;text-align:center;margin-top:16px;font-size:13px;color:rgba(255,255,255,.8);text-decoration:none;}
.back-link:hover{color:#fff;}
</style>
</head>
<body>
<div class="login-wrap">
    <div class="login-card">
        <div class="login-logo">
            <div class="icon">🌿</div>
            <h1>Panel Pengurus</h1>
            <p>RT 005 RW 012 GMR 8</p>
        </div>

        <?php if ($error): ?>
        <div class="alert alert-danger" style="margin-bottom:16px;">
            <i class="fa-solid fa-circle-exclamation"></i>
            <span><?= htmlspecialchars($error) ?></span>
        </div>
        <?php endif; ?>

        <form method="POST" id="login-form">
            <div class="form-group">
                <label class="form-label" for="username">Username</label>
                <div class="input-group">
                    <i class="fa-solid fa-user"></i>
                    <input type="text" id="username" name="username" class="form-control"
                           placeholder="Masukkan username..." value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                           autocomplete="username" required>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="password">Password</label>
                <div class="input-group">
                    <i class="fa-solid fa-lock"></i>
                    <input type="password" id="password" name="password" class="form-control"
                           placeholder="Masukkan password..." autocomplete="current-password" required>
                    <button type="button" class="toggle-pw" onclick="togglePw()" id="pw-toggle">
                        <i class="fa-regular fa-eye" id="pw-icon"></i>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn btn-primary btn-block" id="btn-login" style="font-size:16px;padding:15px;">
                <i class="fa-solid fa-right-to-bracket"></i>
                Masuk ke Panel
            </button>
        </form>

        <p style="text-align:center;font-size:12px;color:var(--text-light);margin-top:16px;">
            Lupa password? Hubungi Super Admin RT 😊
        </p>
    </div>

    <a href="../../" class="back-link">
        <i class="fa-solid fa-arrow-left"></i> Kembali ke Halaman Warga
    </a>
</div>

<script>
function togglePw(){
    const inp = document.getElementById('password');
    const icon = document.getElementById('pw-icon');
    if(inp.type==='password'){
        inp.type='text';
        icon.className='fa-regular fa-eye-slash';
    }else{
        inp.type='password';
        icon.className='fa-regular fa-eye';
    }
}
document.getElementById('login-form').addEventListener('submit',function(){
    const btn = document.getElementById('btn-login');
    btn.disabled = true;
    btn.innerHTML='<span style="border:3px solid rgba(255,255,255,.3);border-top-color:#fff;width:18px;height:18px;border-radius:50%;display:inline-block;animation:spin .7s linear infinite;vertical-align:middle;"></span> Masuk...';
});
</script>
</body>
</html>
