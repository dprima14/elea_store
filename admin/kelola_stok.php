<?php
$page_key   = 'kelola_stok';
$page_title = 'Kelola Stok';
session_start();
require_once '../config/db.php';
require_once 'inc/header.php';

$msg_ok  = '';
$msg_err = '';

// UPDATE STOK
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id_produk'])) {
    $id    = intval($_POST['id_produk']);
    $tambah = intval($_POST['tambah_stok'] ?? 0);
    if ($tambah > 0) {
        $stmt = $pdo->prepare("UPDATE produk SET stok = stok + ? WHERE id_produk = ?");
        $stmt->execute([$tambah, $id]);
        $msg_ok = "Stok berhasil diperbarui (+{$tambah} unit).";
    } else {
        $msg_err = 'Jumlah penambahan stok harus lebih dari 0.';
    }
}

$produk_list = $pdo->query("SELECT id_produk, nama_produk, stok FROM produk ORDER BY nama_produk")->fetchAll();
?>

<?php if ($msg_ok):  ?><div class="alert-ok"><i class="fas fa-check-circle"></i> <?= $msg_ok ?></div><?php endif; ?>
<?php if ($msg_err): ?><div class="alert-err"><i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($msg_err) ?></div><?php endif; ?>

<div class="cc">
    <h2>Update Stok Produk</h2>
    <?php if (empty($produk_list)): ?>
    <p style="text-align:center;color:#9ca3af;padding:2rem 0;">Belum ada data produk.</p>
    <?php else: ?>
    <table class="dtbl">
        <thead>
            <tr><th>Nama Produk</th><th>Stok Saat Ini</th><th>Tambah Stok</th><th>Aksi</th></tr>
        </thead>
        <tbody>
        <?php foreach ($produk_list as $p): ?>
        <tr>
            <td class="t-name"><?= htmlspecialchars($p['nama_produk']) ?></td>
            <td>
                <span class="tbadge <?= $p['stok'] > 10 ? 'stok-ok' : ($p['stok'] > 0 ? 'stok-low' : 'stok-out') ?>">
                    <?= $p['stok'] > 0 ? $p['stok'].' unit' : 'Habis' ?>
                </span>
            </td>
            <td>
                <form method="POST" action="kelola_stok.php" style="display:inline;">
                    <input type="hidden" name="id_produk" value="<?= $p['id_produk'] ?>">
                    <input type="number" name="tambah_stok" min="1" placeholder="Jumlah"
                           style="width:80px;padding:.25rem .5rem;border:1px solid #e5e7eb;border-radius:.375rem;font-size:.75rem;">
                    <button type="submit" class="btn-sm"
                            style="background:linear-gradient(135deg,#7a2e22,#9e5848);color:white;padding:.25rem .75rem;font-size:.75rem;margin-left:.25rem;">
                        Tambah
                    </button>
                </form>
            </td>
            <td>
                <form method="POST" action="kelola_stok.php" style="display:inline;">
                    <input type="hidden" name="id_produk" value="<?= $p['id_produk'] ?>">
                    <input type="hidden" name="tambah_stok" value="0">
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<?php require_once 'inc/footer.php'; ?>
