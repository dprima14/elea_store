<?php
session_start();
require_once 'config/db.php';

// Sudah login → redirect
if (isset($_SESSION['user'])) {
    $r = $_SESSION['user']['role'];
    if ($r === 'pelanggan') header('Location: index.php');
    elseif ($r === 'kasir')  header('Location: kasir/index.php');
    elseif ($r === 'owner')  header('Location: owner/index.php');
    else                     header('Location: admin/index.php');
    exit;
}

$error  = '';
$sukses = false;
$val    = ['nama'=>'','username'=>'','telp'=>''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama     = trim($_POST['nama']     ?? '');
    $username = trim($_POST['username'] ?? '');
    $telp     = trim($_POST['telp']     ?? '');
    $password = $_POST['password']      ?? '';
    $konfirm  = $_POST['konfirm']       ?? '';

    $val = compact('nama','username','telp');

    if (empty($nama) || empty($username) || empty($password)) {
        $error = 'Nama, username, dan password wajib diisi.';
    } elseif (strlen($username) < 4) {
        $error = 'Username minimal 4 karakter.';
    } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        $error = 'Username hanya boleh huruf, angka, dan underscore.';
    } elseif (strlen($password) < 6) {
        $error = 'Password minimal 6 karakter.';
    } elseif ($password !== $konfirm) {
        $error = 'Konfirmasi password tidak sesuai.';
    } else {
        // Cek username sudah dipakai
        $cek = $pdo->prepare("SELECT id_user FROM user WHERE username = ?");
        $cek->execute([$username]);
        if ($cek->fetch()) {
            $error = 'Username "'.htmlspecialchars($username).'" sudah digunakan. Coba yang lain.';
        } else {
            $pdo->prepare(
                "INSERT INTO user (nama, username, no_telepon, password, role)
                 VALUES (?, ?, ?, ?, 'pelanggan')"
            )->execute([$nama, $username, $telp ?: null, $password]);

            $sukses = true;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Akun | Elea Store</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        body{padding-top:0!important;}
        .reg-page{min-height:100vh;background:linear-gradient(160deg,#ffffff 0%,#fff8f6 50%,#fce9e3 100%);display:flex;flex-direction:column;}
        .reg-header{background:#7a3e2e;padding:1rem 1.5rem;display:flex;align-items:center;gap:.75rem;border-bottom:none;box-shadow:0 2px 12px rgba(0,0,0,.18);}
        .reg-header .logo{height:36px;width:auto;display:block;}
        .reg-header .brand{color:#ffffff;font-weight:700;font-size:1rem;font-family:'Playfair Display',Georgia,serif;}
        .reg-header .sub{color:rgba(255,255,255,.7);font-size:.7rem;}
        .reg-header a{margin-left:auto;color:rgba(255,255,255,.85);font-size:.75rem;text-decoration:none;}
        .reg-body{flex:1;display:flex;align-items:center;justify-content:center;padding:2rem 1rem;}
        .reg-wrap{width:100%;max-width:26rem;}
        .reg-title{text-align:center;margin-bottom:1.25rem;}
        .reg-title .icon{font-size:2.75rem;margin-bottom:.5rem;color:#7a3e2e;}
        .reg-title h1{font-size:1.375rem;font-weight:700;color:#1f2937;}
        .reg-title p{font-size:.875rem;color:#6b7280;margin-top:.25rem;}
        .reg-card{background:white;border-radius:1.5rem;box-shadow:0 10px 30px rgba(0,0,0,.08);border:1px solid #fce9e3;padding:2rem;}
        .lbl{display:block;font-size:.75rem;font-weight:600;color:#7a3e2e;margin-bottom:.375rem;}
        .lbl span{color:#9ca3af;font-weight:400;}
        .linput{width:100%;padding:.75rem 1rem;border:1px solid #f5d4cb;border-radius:.75rem;font-size:.875rem;font-family:inherit;outline:none;transition:box-shadow .2s,border-color .2s;margin-bottom:1rem;box-sizing:border-box;background:#fff8f6;}
        .linput:focus{box-shadow:0 0 0 3px rgba(122,62,46,.12);border-color:#9e5848;}
        .linput.err{border-color:#f87171;background:#fff5f5;}
        .pass-wrap{position:relative;}
        .pass-wrap .linput{padding-right:2.75rem;margin-bottom:0;}
        .show-btn{position:absolute;right:.875rem;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;padding:0;color:#9ca3af;}
        .show-btn svg{width:16px;height:16px;stroke:currentColor;fill:none;}
        .pw-hint{font-size:.7rem;color:#9ca3af;margin:.3rem 0 1rem;}
        .submit-btn{width:100%;padding:.8rem;border:none;border-radius:.75rem;font-size:.9rem;font-weight:700;color:white;cursor:pointer;transition:opacity .2s;background:linear-gradient(135deg,#7a3e2e,#9e5848);margin-top:.25rem;}
        .submit-btn:hover{opacity:.9;}
        .error-box{background:#fef2f2;border:1px solid #fecaca;border-radius:.75rem;padding:.625rem 1rem;margin-bottom:1rem;font-size:.8125rem;color:#dc2626;}
        .success-box{background:#f0fdf4;border:1px solid #bbf7d0;border-radius:.75rem;padding:1.25rem;text-align:center;margin-bottom:1rem;}
        .divider{display:flex;align-items:center;gap:.75rem;margin:1.25rem 0;}
        .divider-line{flex:1;height:1px;background:#f5d4cb;}
        .divider span{font-size:.75rem;color:#9ca3af;}
        .back{text-align:center;margin-top:1rem;font-size:.75rem;color:#9ca3af;}
        .back a{color:#953b22;font-weight:600;}
        .back a:hover{text-decoration:underline;}
        .req{color:#dc2626;}
    </style>
</head>
<body>
<div class="reg-page">
    <div class="reg-header">
        <img src="assets/images/ELEA STORE_20260604_100238_0000.png" alt="Elea Store" class="logo">
        <div><div class="brand">Elea Store</div><div class="sub">Fashion for All</div></div>
        <a href="index.php">← Beranda</a>
    </div>
    <div class="reg-body">
        <div class="reg-wrap">
            <div class="reg-title">
                <img src="assets/images/ELEA STORE_20260604_100238_0000.png" alt="Elea Store" style="height:80px;width:auto;margin:0 auto .5rem;display:block;">
                <h1>Buat Akun Baru</h1>
                <p>Daftar gratis dan mulai belanja di Elea Store</p>
            </div>

            <div class="reg-card">
                <?php if ($sukses): ?>
                <div class="success-box">
                    <div style="font-size:2.5rem;margin-bottom:.5rem;color:#15803d;"><i class="fas fa-check-circle"></i></div>
                    <div style="font-weight:700;font-size:1rem;color:#15803d;margin-bottom:.375rem;">Akun Berhasil Dibuat!</div>
                    <p style="font-size:.8125rem;color:#6b7280;margin-bottom:1rem;">Selamat datang di Elea Store! Silakan masuk dengan akun Anda.</p>
                    <a href="login.php"
                       style="display:inline-block;padding:.625rem 1.5rem;background:linear-gradient(135deg,#7a2e22,#9e5848);color:white;border-radius:.75rem;font-weight:700;font-size:.875rem;text-decoration:none;">
                        Masuk Sekarang →
                    </a>
                </div>
                <?php else: ?>

                <?php if ($error): ?>
                <div class="error-box"><i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <form method="POST" action="register.php" id="regForm">
                    <div>
                        <label class="lbl">Nama Lengkap <span class="req">*</span></label>
                        <input type="text" name="nama" class="linput"
                               placeholder="Nama lengkap Anda"
                               value="<?= htmlspecialchars($val['nama']) ?>" required>
                    </div>
                    <div>
                        <label class="lbl">Username <span class="req">*</span></label>
                        <input type="text" name="username" class="linput" id="inp-username"
                               placeholder="Huruf, angka, underscore (min 4)"
                               value="<?= htmlspecialchars($val['username']) ?>"
                               pattern="[a-zA-Z0-9_]{4,}" required>
                    </div>
                    <div>
                        <label class="lbl">No. WhatsApp / Telepon <span style="color:#9ca3af;font-weight:400;">(opsional)</span></label>
                        <input type="tel" name="telp" class="linput"
                               placeholder="Contoh: 08123456789"
                               value="<?= htmlspecialchars($val['telp']) ?>">
                    </div>
                    <div>
                        <label class="lbl">Password <span class="req">*</span></label>
                        <div class="pass-wrap">
                            <input type="password" id="pw1" name="password" class="linput"
                                   placeholder="Minimal 6 karakter" required>
                            <button type="button" class="show-btn" onclick="togglePw('pw1','eye1')">
                                <svg id="eye1" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0zM2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                            </button>
                        </div>
                        <div class="pw-hint">Minimal 6 karakter, kombinasi huruf dan angka</div>
                    </div>
                    <div>
                        <label class="lbl">Konfirmasi Password <span class="req">*</span></label>
                        <div class="pass-wrap">
                            <input type="password" id="pw2" name="konfirm" class="linput"
                                   placeholder="Ulangi password" required>
                            <button type="button" class="show-btn" onclick="togglePw('pw2','eye2')">
                                <svg id="eye2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0zM2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                            </button>
                        </div>
                        <div id="konfirm-warn" style="font-size:.7rem;color:#dc2626;margin-top:.25rem;display:none;">Password tidak sama</div>
                    </div>
                    <button type="submit" class="submit-btn">BUAT AKUN SEKARANG</button>
                </form>

                <div class="divider"><div class="divider-line"></div><span>atau</span><div class="divider-line"></div></div>
                <div style="text-align:center;font-size:.8125rem;color:#6b7280;">Sudah punya akun? <a href="login.php" style="color:#7c3aed;font-weight:600;">Masuk di sini</a></div>

                <?php endif; ?>
            </div>

            <div class="back">Kembali ke <a href="index.php">Halaman Utama</a></div>
        </div>
    </div>
</div>

<script>
const eyePath = {
    close: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>',
    open:  '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0zM2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>',
};
function togglePw(id, iconId) {
    var inp = document.getElementById(id);
    var ico = document.getElementById(iconId);
    var show = inp.type === 'password';
    inp.type = show ? 'text' : 'password';
    ico.innerHTML = show ? eyePath.close : eyePath.open;
}

// Live cek konfirmasi password
document.getElementById('pw2').addEventListener('input', function() {
    var match = this.value === document.getElementById('pw1').value;
    document.getElementById('konfirm-warn').style.display = this.value && !match ? 'block' : 'none';
});

// Cegah submit kalau password tidak sama
document.getElementById('regForm').addEventListener('submit', function(e) {
    var p1 = document.getElementById('pw1').value;
    var p2 = document.getElementById('pw2').value;
    if (p1 !== p2) {
        e.preventDefault();
        document.getElementById('konfirm-warn').style.display = 'block';
        document.getElementById('pw2').scrollIntoView({behavior:'smooth', block:'center'});
    }
});
</script>
</body>
</html>
