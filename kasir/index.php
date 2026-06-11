<?php
$page_key   = 'index';
$page_title = 'Dashboard Kasir';
session_start();
require_once '../config/db.php';
require_once 'inc/header.php';

// Statistik
$total_penjualan_hari = $pdo->query(
    "SELECT COUNT(*) FROM penjualan WHERE DATE(tgl_penjualan) = CURDATE()"
)->fetchColumn();

$total_penjualan_bulan = $pdo->query(
    "SELECT COUNT(*) FROM penjualan WHERE MONTH(tgl_penjualan)=MONTH(NOW()) AND YEAR(tgl_penjualan)=YEAR(NOW())"
)->fetchColumn();

$preorder_pending = $pdo->query(
    "SELECT COUNT(*) FROM preorder WHERE status='pending'"
)->fetchColumn();

$stok_habis = $pdo->query(
    "SELECT COUNT(*) FROM produk WHERE stok = 0"
)->fetchColumn();

// 5 penjualan terbaru
$penjualan_terbaru = $pdo->query(
    "SELECT p.id_penjualan, p.tgl_penjualan, p.total_harga, u.nama AS nama_user
     FROM penjualan p JOIN user u ON p.id_user = u.id_user
     ORDER BY p.tgl_penjualan DESC LIMIT 5"
)->fetchAll();
?>

<div class="sg">
    <div class="sc">
        <div class="sc-row"><span class="sc-icon"><i class="fas fa-receipt"></i></span></div>
        <div class="sc-val"><?= $total_penjualan_hari ?></div>
        <div class="sc-lbl">Transaksi Hari Ini</div>
    </div>
    <div class="sc">
        <div class="sc-row"><span class="sc-icon"><i class="fas fa-box"></i></span></div>
        <div class="sc-val"><?= $total_penjualan_bulan ?></div>
        <div class="sc-lbl">Transaksi Bulan Ini</div>
    </div>
    <div class="sc">
        <div class="sc-row"><span class="sc-icon"><i class="fas fa-clipboard-list"></i></span>
            <?php if ($preorder_pending > 0): ?>
            <span class="sc-badge" style="background:#fffbeb;color:#d97706;"><?= $preorder_pending ?></span>
            <?php endif; ?>
        </div>
        <div class="sc-val"><?= $preorder_pending ?></div>
        <div class="sc-lbl">Pre Order Pending</div>
    </div>
    <div class="sc">
        <div class="sc-row"><span class="sc-icon"><i class="fas fa-exclamation-triangle"></i></span></div>
        <div class="sc-val"><?= $stok_habis ?></div>
        <div class="sc-lbl">Produk Stok Habis</div>
    </div>
</div>

<div class="cc">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;">
        <h2 style="margin:0;">Transaksi Terbaru</h2>
        <a href="transaksi.php" style="font-size:.75rem;color:#7a2e22;font-weight:600;">Lihat Semua →</a>
    </div>
    <?php if (empty($penjualan_terbaru)): ?>
    <p style="text-align:center;color:#9ca3af;padding:2rem 0;">Belum ada transaksi.</p>
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
