<?php
$page_key   = 'monitoring_stok';
$page_title = 'Monitoring Stok';
session_start();
require_once '../config/db.php';
require_once 'inc/header.php';

$produk_list = $pdo->query(
    "SELECT id_produk, nama_produk, harga, stok, deskripsi_produk FROM produk ORDER BY stok ASC"
)->fetchAll();

$total_produk = count($produk_list);
$stok_habis   = count(array_filter($produk_list, fn($p) => $p['stok'] == 0));
$stok_menipis = count(array_filter($produk_list, fn($p) => $p['stok'] > 0 && $p['stok'] < 5));
$stok_aman    = $total_produk - $stok_habis - $stok_menipis;
?>

<div class="sg">
    <div class="sc">
        <div class="sc-row"><span class="sc-icon"><i class="fas fa-tshirt"></i></span></div>
        <div class="sc-val"><?= $total_produk ?></div>
        <div class="sc-lbl">Total Produk</div>
    </div>
    <div class="sc">
        <div class="sc-row"><span class="sc-icon"><i class="fas fa-check-circle"></i></span></div>
        <div class="sc-val"><?= $stok_aman ?></div>
        <div class="sc-lbl">Stok Aman</div>
    </div>
    <div class="sc">
        <div class="sc-row"><span class="sc-icon"><i class="fas fa-exclamation-triangle"></i></span></div>
        <div class="sc-val"><?= $stok_menipis ?></div>
        <div class="sc-lbl">Stok Menipis</div>
    </div>
    <div class="sc">
        <div class="sc-row"><span class="sc-icon"><i class="fas fa-times-circle"></i></span></div>
        <div class="sc-val"><?= $stok_habis ?></div>
        <div class="sc-lbl">Stok Habis</div>
    </div>
</div>

<div class="cc">
    <h2>Data Stok Produk</h2>
    <?php if (empty($produk_list)): ?>
    <p style="text-align:center;color:#9ca3af;padding:2rem 0;">Belum ada data produk.</p>
    <?php else: ?>
    <table class="dtbl">
        <thead>
            <tr><th>#</th><th>Nama Produk</th><th>Harga</th><th>Stok</th><th>Keterangan</th></tr>
        </thead>
        <tbody>
        <?php foreach ($produk_list as $i => $p): ?>
        <tr>
            <td class="t-sub"><?= $i + 1 ?></td>
            <td class="t-name"><?= htmlspecialchars($p['nama_produk']) ?></td>
            <td class="t-bold"><?= fmt_rp((int)$p['harga']) ?></td>
            <td>
                <span class="tbadge <?= $p['stok'] >= 5 ? 'stok-ok' : ($p['stok'] > 0 ? 'stok-low' : 'stok-out') ?>">
                    <?= $p['stok'] > 0 ? $p['stok'].' unit' : 'Habis' ?>
                </span>
            </td>
            <td class="t-sub">
                <?php if ($p['stok'] == 0): ?>
                <span style="color:#dc2626;font-weight:600;">Perlu restock segera!</span>
                <?php elseif ($p['stok'] < 5): ?>
                <span style="color:#d97706;">Stok menipis</span>
                <?php else: ?>
                <span style="color:#7a2e22;">Aman</span>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<?php require_once 'inc/footer.php'; ?>
