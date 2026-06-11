<?php
$page_key   = 'index';
$page_title = 'Dashboard Owner';
session_start();
require_once '../config/db.php';
require_once 'inc/header.php';

// Statistik ringkasan
$total_pendapatan = $pdo->query("SELECT COALESCE(SUM(total_harga),0) FROM penjualan")->fetchColumn();
$total_transaksi  = $pdo->query("SELECT COUNT(*) FROM penjualan")->fetchColumn();
$total_produk     = $pdo->query("SELECT COUNT(*) FROM produk")->fetchColumn();
$stok_total       = $pdo->query("SELECT COALESCE(SUM(stok),0) FROM produk")->fetchColumn();

// Pendapatan bulan ini
$pendapatan_bulan = $pdo->query(
    "SELECT COALESCE(SUM(total_harga),0) FROM penjualan
     WHERE MONTH(tgl_penjualan)=MONTH(NOW()) AND YEAR(tgl_penjualan)=YEAR(NOW())"
)->fetchColumn();
?>

<div style="background:linear-gradient(135deg,#7a2e22,#7a2e22);border-radius:1rem;padding:1.25rem;color:white;margin-bottom:1.25rem;display:flex;align-items:center;gap:1rem;">
    <div style="font-size:2rem;"><i class="fas fa-crown"></i></div>
    <div>
        <div style="font-weight:700;font-size:1rem;">Selamat datang, <?= htmlspecialchars(explode(' ',$user['nama'])[0]) ?>!</div>
        <div style="font-size:.8125rem;opacity:.85;margin-top:.25rem;">Pantau penjualan dan stok toko Anda dari dashboard ini.</div>
        <div style="margin-top:.75rem;display:flex;gap:.5rem;flex-wrap:wrap;">
            <a href="monitoring_penjualan.php" style="padding:.3rem .75rem;background:rgba(255,255,255,.2);border-radius:9999px;font-size:.75rem;color:white;font-weight:600;text-decoration:none;"><i class="fas fa-chart-line"></i> Monitoring Penjualan</a>
            <a href="monitoring_stok.php"      style="padding:.3rem .75rem;background:rgba(255,255,255,.2);border-radius:9999px;font-size:.75rem;color:white;font-weight:600;text-decoration:none;"><i class="fas fa-box"></i> Monitoring Stok</a>
        </div>
    </div>
</div>

<div class="sg">
    <div class="sc">
        <div class="sc-row"><span class="sc-icon"><i class="fas fa-coins"></i></span></div>
        <div class="sc-val"><?= fmt_rp((int)$total_pendapatan) ?></div>
        <div class="sc-lbl">Total Pendapatan</div>
    </div>
    <div class="sc">
        <div class="sc-row"><span class="sc-icon"><i class="fas fa-box"></i></span></div>
        <div class="sc-val"><?= $total_transaksi ?></div>
        <div class="sc-lbl">Total Transaksi</div>
    </div>
    <div class="sc">
        <div class="sc-row"><span class="sc-icon"><i class="fas fa-gem"></i></span></div>
        <div class="sc-val"><?= fmt_rp((int)$pendapatan_bulan) ?></div>
        <div class="sc-lbl">Pendapatan Bulan Ini</div>
    </div>
    <div class="sc">
        <div class="sc-row"><span class="sc-icon"><i class="fas fa-tshirt"></i></span></div>
        <div class="sc-val"><?= $total_produk ?> produk</div>
        <div class="sc-lbl">Total Stok: <?= number_format($stok_total) ?> unit</div>
    </div>
</div>

<?php require_once 'inc/footer.php'; ?>
