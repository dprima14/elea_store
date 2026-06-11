<?php
session_start();
require_once 'config/db.php';

if (isset($_SESSION['user'])) {
    $r = $_SESSION['user']['role'];
    if ($r === 'pelanggan')     header('Location: pelanggan/beranda.php');
    elseif ($r === 'kasir')     header('Location: kasir/index.php');
    elseif ($r === 'owner')     header('Location: owner/index.php');
    else                        header('Location: admin/index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = 'Username dan password wajib diisi.';
    } else {
        $stmt = $pdo->prepare("SELECT id_user, nama, username, password, role FROM user WHERE username = ?");
        $stmt->execute([$username]);
        $akun = $stmt->fetch();

        if ($akun && $akun['password'] === $password) {
            $_SESSION['user'] = [
                'id_user'  => $akun['id_user'],
                'username' => $akun['username'],
                'nama'     => $akun['nama'],
                'role'     => $akun['role'],
            ];
            $role = $akun['role'];
            if ($role === 'pelanggan')   header('Location: index.php');
            elseif ($role === 'kasir')   header('Location: kasir/index.php');
            elseif ($role === 'owner')   header('Location: owner/index.php');
            else                         header('Location: admin/index.php');
            exit;
        } else {
            $error = 'Username atau password salah.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | Elea Store</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        body{padding-top:0!important;}
        .login-page{min-height:100vh;background:linear-gradient(160deg,#ffffff 0%,#fff8f6 50%,#fce9e3 100%);display:flex;flex-direction:column;}
        .login-header{background:#7a3e2e;padding:1rem 1.5rem;display:flex;align-items:center;gap:.75rem;border-bottom:none;box-shadow:0 2px 12px rgba(0,0,0,.18);}
        .login-header .logo{width:32px;height:32px;border-radius:50%;background:rgba(255,255,255,.2);display:flex;align-items:center;justify-content:center;font-weight:700;color:white;font-size:1rem;}
        .login-header .brand{color:#ffffff;font-weight:700;font-size:1rem;font-family:'Playfair Display',Georgia,serif;}
        .login-header .sub{color:rgba(255,255,255,.7);font-size:.7rem;}
        .login-header a{margin-left:auto;color:rgba(255,255,255,.85);font-size:.75rem;text-decoration:none;}
        .login-body{flex:1;display:flex;align-items:center;justify-content:center;padding:2rem 1rem;}
        .login-wrap{width:100%;max-width:24rem;}
        .login-title{text-align:center;margin-bottom:1.5rem;}
        .login-title .store-icon{font-size:3rem;margin-bottom:.5rem;color:#7a3e2e;}
        .login-title h1{font-size:1.5rem;font-weight:700;color:#1f2937;}
        .login-title p{font-size:.875rem;color:#6b7280;margin-top:.25rem;}
        .login-card{background:white;border-radius:1.5rem;box-shadow:0 10px 30px rgba(0,0,0,.08);overflow:hidden;border:1px solid #fce9e3;padding:2rem;}
        .lbl{display:block;font-size:.75rem;font-weight:600;color:#7a3e2e;margin-bottom:.375rem;}
        .linput{width:100%;padding:.75rem 1rem;border:1px solid #f5d4cb;border-radius:.75rem;font-size:.875rem;font-family:inherit;outline:none;transition:box-shadow .2s,border-color .2s;margin-bottom:1.25rem;box-sizing:border-box;background:#fff8f6;}
        .linput:focus{box-shadow:0 0 0 3px rgba(122,62,46,.12);border-color:#9e5848;}
        .pass-wrap{position:relative;}
        .pass-wrap .linput{padding-right:2.75rem;margin-bottom:0;}
        .show-btn{position:absolute;right:.875rem;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;padding:0;color:#9ca3af;}
        .show-btn svg{width:16px;height:16px;stroke:currentColor;fill:none;}
        .submit-btn{width:100%;padding:.8rem;border:none;border-radius:.75rem;font-size:.9rem;font-weight:700;color:white;cursor:pointer;transition:opacity .2s;letter-spacing:.02em;background:linear-gradient(135deg,#7a3e2e,#9e5848);margin-top:1.25rem;}
        .submit-btn:hover{opacity:.9;}
        .error-box{background:#fef2f2;border:1px solid #fecaca;border-radius:.75rem;padding:.625rem 1rem;margin-bottom:1rem;font-size:.8125rem;color:#dc2626;}
        .back{text-align:center;margin-top:1rem;font-size:.75rem;color:#9ca3af;}
        .back a{color:#953b22;font-weight:600;}
        .back a:hover{text-decoration:underline;}
    </style>
</head>
<body>
<div class="login-page">
    <div class="login-header">
        <div class="logo">E</div>
        <div><div class="brand">Elea Store</div><div class="sub">Fashion for All</div></div>
        <a href="index.php">← Beranda</a>
    </div>
    <div class="login-body">
        <div class="login-wrap">
            <div class="login-title">
                <div class="store-icon"><i class="fas fa-shopping-bag"></i></div>
                <h1>Masuk ke Elea Store</h1>
                <p>Masukkan username dan password Anda</p>
            </div>

            <div class="login-card">
                <?php if ($error): ?>
                <div class="error-box"><i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <form method="POST" action="login.php">
                    <div>
                        <label class="lbl">Username</label>
                        <input type="text" name="username" class="linput"
                            placeholder="Masukkan username"
                            value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required>
                    </div>
                    <div>
                        <label class="lbl">Password</label>
                        <div class="pass-wrap">
                            <input type="password" id="pw" name="password" class="linput"
                                placeholder="Masukkan password" required>
                            <button type="button" class="show-btn" onclick="togglePw()">
                                <svg id="eye-icon" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0zM2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                            </button>
                        </div>
                    </div>
                    <button type="submit" class="submit-btn">MASUK</button>
                </form>
            </div>
            <div class="back">Kembali ke <a href="index.php">Halaman Utama</a></div>
            <div class="back" style="margin-top:.5rem;">Belum punya akun? <a href="register.php" style="color:#953b22;font-weight:600;">Daftar Sekarang</a></div>
        </div>
    </div>
</div>
<script>
function togglePw() {
    const pw = document.getElementById('pw');
    const icon = document.getElementById('eye-icon');
    const show = pw.type === 'password';
    pw.type = show ? 'text' : 'password';
    icon.innerHTML = show
        ? '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>'
        : '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0zM2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>';
}
</script>
</body>
</html>
