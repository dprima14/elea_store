<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($activePage)) $activePage = '';
if (!isset($pageTitle))  $pageTitle  = 'Elea Store';
$siteRoot = $siteRoot ?? '';

$is_pelanggan = isset($_SESSION['user']) && $_SESSION['user']['role'] === 'pelanggan';
if ($is_pelanggan && !isset($_SESSION['cart'])) $_SESSION['cart'] = [];
$cart_count = $is_pelanggan ? array_sum(array_column($_SESSION['cart'] ?? [], 'qty')) : 0;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> | Elea Store - Fashion for All</title>
    <meta name="description" content="Elea Store - Butik fashion for all pilihan untuk semua kalangan Indonesia. Harga terjangkau, kualitas premium, sesuai selera Anda.">
    <link rel="stylesheet" href="<?= $siteRoot ?>assets/css/style.css?v=<?= filemtime(__DIR__.'/../assets/css/preorder.css') ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
    .nav-profile{position:relative;}
    .btn-nav-profile{display:flex;align-items:center;gap:.375rem;padding:.4rem .875rem;border-radius:9999px;border:1.5px solid #f5d4cb;background:transparent;cursor:pointer;font-size:.8125rem;font-weight:600;color:#7a2e22;transition:all .15s;font-family:'Lato',inherit;line-height:1;}
    .btn-nav-profile:hover{background:rgba(122,46,34,0.07);color:#953b22;border-color:#dfb0a2;}
    .prof-av-sm{width:22px;height:22px;border-radius:50%;background:linear-gradient(135deg,#7a2e22,#9e5848);display:flex;align-items:center;justify-content:center;color:white;font-size:.625rem;font-weight:700;flex-shrink:0;}
    .prof-dropdown{position:absolute;right:0;top:calc(100% + .5rem);background:white;border-radius:.875rem;box-shadow:0 8px 28px rgba(0,0,0,.1);border:1px solid #fce9e3;min-width:190px;z-index:200;overflow:hidden;opacity:0;pointer-events:none;transform:translateY(-6px);transition:opacity .15s,transform .15s;}
    .prof-dropdown.open{opacity:1;pointer-events:auto;transform:translateY(0);}
    .prof-dropdown a{display:flex;align-items:center;gap:.5rem;padding:.625rem 1rem;font-size:.8125rem;color:#374151;text-decoration:none;transition:background .1s;}
    .prof-dropdown a:hover{background:#fff8f6;color:#7a2e22;}
    .prof-dropdown .pd-sep{border:none;border-top:1px solid #fce9e3;margin:.2rem 0;}
    .cart-nav-btn{position:relative;display:flex;align-items:center;justify-content:center;width:36px;height:36px;border-radius:.625rem;color:#7a2e22;text-decoration:none;font-size:1.1rem;transition:background .15s;}
    .cart-nav-btn:hover{background:rgba(122,46,34,0.07);}
    .cart-badge-nav{position:absolute;top:1px;right:1px;background:#953b22;color:white;min-width:15px;height:15px;border-radius:9999px;display:flex;align-items:center;justify-content:center;font-size:.55rem;font-weight:700;padding:0 3px;}
    </style>
</head>
<body><!-- NAVBAR -->
<nav class="navbar">
    <div class="navbar-inner">
        <a href="<?= $siteRoot ?>index.php" class="navbar-brand">
            <img src="<?= $siteRoot ?>assets/images/ELEA STORE_20260604_100238_0000.png" alt="Elea Store" class="navbar-logo-img">
        </a>

        <div class="navbar-links">
            <a href="<?= $siteRoot ?>index.php"    class="<?= $activePage==='beranda' ?'active':'' ?>">Beranda</a>
            <a href="<?= $siteRoot ?>katalog.php"  class="<?= $activePage==='katalog' ?'active':'' ?>">Katalog Produk</a>
            <a href="<?= $siteRoot ?>preorder.php" class="<?= $activePage==='preorder'?'active':'' ?>">Pre Order</a>
            <a href="<?= $siteRoot ?>tentang.php"  class="<?= $activePage==='tentang' ?'active':'' ?>">Tentang Kami</a>
            <a href="<?= $siteRoot ?>kontak.php"   class="<?= $activePage==='kontak'  ?'active':'' ?>">Kontak</a>
        </div>

        <div class="navbar-actions">
            <?php if ($is_pelanggan): ?>
            <a href="<?= $siteRoot ?>pelanggan/keranjang.php" class="cart-nav-btn" title="Keranjang">
                <i class="fas fa-shopping-cart"></i><?php if ($cart_count > 0): ?><span class="cart-badge-nav"><?= $cart_count ?></span><?php endif; ?>
            </a>
            <div class="nav-profile">
                <button class="btn-nav-profile" id="profBtn" onclick="toggleProfMenu(event)">
                    <span class="prof-av-sm"><?= mb_strtoupper(mb_substr($_SESSION['user']['nama'],0,1)) ?></span>
                    <?= htmlspecialchars(explode(' ',$_SESSION['user']['nama'])[0]) ?>
                    <svg width="10" height="10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/></svg>
                </button>
                <div class="prof-dropdown" id="profDropdown">
                    <a href="<?= $siteRoot ?>pelanggan/profil.php"><i class="fas fa-user"></i> Profil Saya</a>
                    <a href="<?= $siteRoot ?>pelanggan/profil.php?tab=pesanan"><i class="fas fa-box"></i> Riwayat Pesanan</a>
                    <a href="<?= $siteRoot ?>pelanggan/keranjang.php"><i class="fas fa-shopping-cart"></i> Keranjang<?= $cart_count > 0 ? " ($cart_count)" : '' ?></a>
                    <hr class="pd-sep">
                    <a href="<?= $siteRoot ?>pelanggan/logout.php"><i class="fas fa-sign-out-alt"></i> Keluar</a>
                </div>
            </div>
            <?php else: ?>
            <a href="<?= $siteRoot ?>login.php" class="btn-nav-login">Masuk</a>
            <a href="<?= $siteRoot ?>register.php" class="btn-nav-admin">Daftar</a>
            <?php endif; ?>
        </div>

        <button class="navbar-toggle" onclick="toggleMobileNav()" aria-label="Menu">
            <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path id="menu-icon" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
            </svg>
        </button>
    </div>

    <div class="navbar-mobile" id="mobile-nav">
        <a href="<?= $siteRoot ?>index.php">Beranda</a>
        <a href="<?= $siteRoot ?>katalog.php">Katalog Produk</a>
        <a href="<?= $siteRoot ?>preorder.php">Pre Order</a>
        <a href="<?= $siteRoot ?>tentang.php">Tentang Kami</a>
        <a href="<?= $siteRoot ?>kontak.php">Kontak</a>
        <?php if ($is_pelanggan): ?>
        <hr style="border:none;border-top:1px solid #fce9e3;margin:.25rem 0;">
        <a href="<?= $siteRoot ?>pelanggan/profil.php"><i class="fas fa-user"></i> Profil Saya</a>
        <a href="<?= $siteRoot ?>pelanggan/profil.php?tab=pesanan"><i class="fas fa-box"></i> Riwayat Pesanan</a>
        <a href="<?= $siteRoot ?>pelanggan/keranjang.php"><i class="fas fa-shopping-cart"></i> Keranjang<?= $cart_count > 0 ? " ($cart_count)" : '' ?></a>
        <a href="<?= $siteRoot ?>pelanggan/logout.php" style="color:#dc2626;"><i class="fas fa-sign-out-alt"></i> Keluar</a>
        <?php else: ?>
        <div class="navbar-mobile-actions">
            <a href="<?= $siteRoot ?>login.php" class="mobile-btn-login">Masuk</a>
            <a href="<?= $siteRoot ?>register.php" class="mobile-btn-admin">Daftar</a>
        </div>
        <?php endif; ?>
    </div>
</nav>

<main>

<script>
function toggleProfMenu(e) {
    e.stopPropagation();
    document.getElementById('profDropdown').classList.toggle('open');
}
document.addEventListener('click', function() {
    var d = document.getElementById('profDropdown');
    if (d) d.classList.remove('open');
});
function toggleMobileNav() {
    var n = document.getElementById('mobile-nav');
    n.style.display = n.style.display === 'block' ? 'none' : 'block';
}
</script>
