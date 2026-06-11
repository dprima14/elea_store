<?php
$page_key   = 'index';
$page_title = 'Dashboard Admin';
session_start();
require_once '../config/db.php';
require_once 'inc/header.php';

// Statistik dari database
$total_produk   = $pdo->query("SELECT COUNT(*) FROM produk")->fetchColumn();
$total_penjualan= $pdo->query("SELECT COUNT(*) FROM penjualan")->fetchColumn();
$total_pendapatan= $pdo->query("SELECT COALESCE(SUM(total_harga),0) FROM penjualan")->fetchColumn();
$total_user_pelanggan = $pdo->query("SELECT COUNT(*) FROM user WHERE role='pelanggan'")->fetchColumn();

// 5 penjualan terbaru
$penjualan_terbaru = $pdo->query(
    "SELECT p.id_penjualan, p.tgl_penjualan, p.total_harga, u.nama AS nama_user
     FROM penjualan p
     JOIN user u ON p.id_user = u.id_user
     ORDER BY p.tgl_penjualan DESC
     LIMIT 5"
)->fetchAll();
?>

<div class="sg">
    <div class="sc">
        <div class="sc-row"><span class="sc-icon"><i class="fas fa-tshirt"></i></span><span class="sc-badge" style="background:#fff8f6;color:#7a2e22;">Aktif</span></div>
        <div class="sc-val"><?= $total_produk ?></div>
        <div class="sc-lbl">Total Produk</div>
    </div>
    <div class="sc">
        <div class="sc-row"><span class="sc-icon"><i class="fas fa-box"></i></span></div>
        <div class="sc-val"><?= $total_penjualan ?></div>
        <div class="sc-lbl">Total Penjualan</div>
    </div>
    <div class="sc">
        <div class="sc-row"><span class="sc-icon"><i class="fas fa-coins"></i></span></div>
        <div class="sc-val"><?= fmt_rp((int)$total_pendapatan) ?></div>
        <div class="sc-lbl">Total Pendapatan</div>
    </div>
    <div class="sc">
        <div class="sc-row"><span class="sc-icon"><i class="fas fa-users"></i></span></div>
        <div class="sc-val"><?= $total_user_pelanggan ?></div>
        <div class="sc-lbl">Pelanggan Terdaftar</div>
    </div>
</div>

<div class="cc">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;">
        <h2 style="margin:0;">Penjualan Terbaru</h2>
        <a href="kelola_penjualan.php" style="font-size:.75rem;color:#7a2e22;font-weight:600;">Lihat Semua →</a>
    </div>
    <?php if (empty($penjualan_terbaru)): ?>
    <p style="text-align:center;color:#9ca3af;padding:2rem 0;">Belum ada data penjualan.</p>
    <?php else: ?>
    <table class="dtbl">
        <thead><tr><th>ID</th><th>Pelanggan</th><th>Tanggal</th><th>Total</th></tr></thead>
        <tbody>
        <?php foreach ($penjualan_terbaru as $p): ?>
        <tr>
            <td class="t-sub">#<?= $p['id_penjualan'] ?></td>
            <td class="t-name"><?= htmlspecialchars($p['nama_user']) ?></td>
            <td class="t-sub"><?= date('d M Y', strtotime($p['tgl_penjualan'])) ?></td>
            <td class="t-bold"><?= fmt_rp((int)$p['total_harga']) ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<?php require_once 'inc/footer.php'; ?>
