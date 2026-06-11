<?php
session_start();
require_once '../config/db.php';
require_once '../config/midtrans.php';
require_once '../config/midtrans_helper.php';

if (!isset($_SESSION['user'])) die('Silakan login dulu.');
$user = $_SESSION['user'];

$msg = '';

// RECONCILE MANUAL: user input midtrans_order_id dari dashboard Midtrans
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['manual_oid']) && !empty($_POST['id_penjualan'])) {
    $manual_oid   = trim($_POST['manual_oid']);
    $id_penjualan = intval($_POST['id_penjualan']);

    // Verifikasi ke Midtrans
    $check = midtrans_get_status($manual_oid);
    $trx   = $check['transaction_status'] ?? '';

    if (in_array($trx, ['settlement', 'capture'])) {
        // Update DB
        $pdo->prepare("UPDATE penjualan SET status='pending', payment_status=?, midtrans_order_id=? WHERE id_penjualan=? AND id_user=?")
            ->execute([$trx, $manual_oid, $id_penjualan, $user['id_user']]);
        $msg = "<p style='color:green;font-weight:bold;background:#f0fdf4;padding:12px;border-radius:8px;'>✓ Order #{$id_penjualan} berhasil diupdate ke <b>Pesanan Diterima</b>!</p>";
    } else {
        $msg = "<p style='color:red;background:#fef2f2;padding:12px;border-radius:8px;'>✗ Midtrans Order ID <b>{$manual_oid}</b> berstatus: <b>" . ($trx ?: 'tidak ditemukan') . "</b>. Pastikan salin Order ID yang benar dari Midtrans dashboard.</p>";
    }
}

// Ambil semua order pending
$stmt = $pdo->prepare(
    "SELECT id_penjualan, status, midtrans_order_id, tgl_penjualan, total_harga
     FROM penjualan WHERE id_user=? AND status='menunggu_pembayaran' ORDER BY id_penjualan DESC"
);
$stmt->execute([$user['id_user']]);
$orders = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Debug Pembayaran</title>
<style>
body{font-family:Arial,sans-serif;padding:20px;max-width:800px;margin:0 auto;background:#f9fafb;}
h2{color:#7a2e22;}
.card{background:white;border:1px solid #e5e7eb;border-radius:12px;padding:20px;margin-bottom:16px;box-shadow:0 1px 4px rgba(0,0,0,.05);}
pre{background:#f3f4f6;padding:12px;border-radius:8px;font-size:12px;overflow-x:auto;}
.badge-404{color:#dc2626;font-weight:bold;}
.badge-ok{color:#15803d;font-weight:bold;}
input[type=text]{width:100%;padding:8px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:14px;box-sizing:border-box;margin-top:4px;}
.btn{padding:10px 20px;background:#7a2e22;color:white;border:none;border-radius:8px;cursor:pointer;font-size:14px;font-weight:600;}
.btn:hover{opacity:.9;}
.hint{background:#fffbeb;border:1px solid #fde68a;border-radius:8px;padding:12px;font-size:13px;color:#92400e;margin-top:8px;}
</style>
</head>
<body>
<h2>Debug Pembayaran — User: <?= htmlspecialchars($user['username']) ?></h2>

<?= $msg ?>

<div class="hint">
    <b>Masalah yang ditemukan:</b> Order ID di database sudah tertimpa saat klik "Lanjutkan Pembayaran",
    sedangkan pembayaran asli tercatat di Order ID yang lama.<br><br>
    <b>Cara fix:</b>
    <ol>
        <li>Buka <a href="https://dashboard.sandbox.midtrans.com/transactions" target="_blank">Midtrans Dashboard → Transaksi</a></li>
        <li>Cari transaksi yang statusnya <b>Settlement</b></li>
        <li>Salin <b>Order ID</b>-nya (misal: <code>ELEA-44-1780895000</code>)</li>
        <li>Masukkan di form di bawah sesuai nomor pesanan</li>
    </ol>
</div>

<?php if (empty($orders)): ?>
    <div class="card"><p style='color:green'>Tidak ada order menunggu pembayaran. Semua sudah terupdate!</p></div>
<?php else: ?>
    <?php foreach ($orders as $o): ?>
    <div class="card">
        <h3 style="margin-top:0;">Order #<?= str_pad($o['id_penjualan'],5,'0',STR_PAD_LEFT) ?>
            <small style="color:#9ca3af;font-size:14px;font-weight:400;">— <?= $o['tgl_penjualan'] ?></small>
        </h3>
        <b>Total:</b> Rp <?= number_format($o['total_harga'],0,',','.') ?><br>
        <b>Order ID di DB:</b> <code><?= htmlspecialchars($o['midtrans_order_id'] ?: 'KOSONG') ?></code><br>

        <?php
        $mt     = !empty($o['midtrans_order_id']) ? midtrans_get_status($o['midtrans_order_id']) : [];
        $mt_trx = $mt['transaction_status'] ?? '';
        ?>

        <b>Status Midtrans:</b>
        <?php if ($mt_trx): ?>
            <span class="<?= in_array($mt_trx, ['settlement','capture']) ? 'badge-ok' : 'badge-404' ?>">
                <?= $mt_trx ?>
            </span>
        <?php else: ?>
            <span class="badge-404">Transaction doesn't exist (Order ID di DB sudah tertimpa)</span>
        <?php endif; ?>
        <br><br>

        <?php if (!in_array($mt_trx, ['settlement', 'capture'])): ?>
        <b>Masukkan Order ID yang benar dari Midtrans Dashboard:</b>
        <form method="POST" style="margin-top:8px;">
            <input type="hidden" name="id_penjualan" value="<?= $o['id_penjualan'] ?>">
            <input type="text" name="manual_oid" placeholder="Contoh: ELEA-<?= $o['id_penjualan'] ?>-1780895000" required>
            <button type="submit" class="btn" style="margin-top:8px;width:100%;">
                Cek & Update Status Pesanan Ini
            </button>
        </form>
        <?php else: ?>
            <p style='color:green;font-weight:bold;'>✓ Sudah settlement — klik tombol di bawah untuk update status.</p>
            <form method="POST">
                <input type="hidden" name="id_penjualan" value="<?= $o['id_penjualan'] ?>">
                <input type="hidden" name="manual_oid" value="<?= htmlspecialchars($o['midtrans_order_id']) ?>">
                <button type="submit" class="btn">Update Status ke Pesanan Diterima</button>
            </form>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
<?php endif; ?>

<br>
<a href="profil.php?tab=pesanan" style="color:#7a2e22;font-weight:600;">← Kembali ke Riwayat Pesanan</a>
</body>
</html>
