<?php
$page_key   = 'monitoring_penjualan';
$page_title = 'Monitoring Penjualan';
session_start();
require_once '../config/db.php';
require_once 'inc/header.php';

$sub = $_GET['sub'] ?? 'laporan';

// Ringkasan keseluruhan
$grand_total  = $pdo->query("SELECT COALESCE(SUM(total_harga),0) FROM penjualan")->fetchColumn();
$grand_count  = $pdo->query("SELECT COUNT(*) FROM penjualan")->fetchColumn();

// Pendapatan per bulan (12 bulan terakhir)
$per_bulan = $pdo->query(
    "SELECT DATE_FORMAT(tgl_penjualan,'%Y-%m') AS bulan,
            COUNT(*) AS jumlah,
            SUM(total_harga) AS total
     FROM penjualan
     GROUP BY DATE_FORMAT(tgl_penjualan,'%Y-%m')
     ORDER BY bulan DESC LIMIT 12"
)->fetchAll();

// Produk terlaris (dari detail_penjualan)
$terlaris = $pdo->query(
    "SELECT p.nama_produk, SUM(dp.jumlah) AS total_terjual, SUM(dp.subtotal) AS total_pendapatan
     FROM detail_penjualan dp
     JOIN produk p ON dp.id_produk = p.id_produk
     GROUP BY dp.id_produk, p.nama_produk
     ORDER BY total_terjual DESC LIMIT 10"
)->fetchAll();

// Rekap semua transaksi (LEFT JOIN agar kasir offline juga muncul)
$semua_transaksi = $pdo->query(
    "SELECT p.id_penjualan, p.tgl_penjualan, p.total_harga,
            COALESCE(p.nama_pelanggan, u.nama, 'Pelanggan Umum') AS nama_user
     FROM penjualan p
     LEFT JOIN user u ON p.id_user = u.id_user
     ORDER BY p.tgl_penjualan DESC"
)->fetchAll();
?>

<!-- Sub-nav + tombol export -->
<div style="display:flex;gap:.5rem;margin-bottom:1rem;align-items:center;flex-wrap:wrap;">
    <div style="display:flex;gap:.5rem;flex:1;flex-wrap:wrap;">
        <?php foreach (['laporan'=>'Laporan Penjualan','rekap'=>'Rekap Transaksi'] as $k=>$lbl): ?>
        <a href="monitoring_penjualan.php?sub=<?= $k ?>"
           style="padding:.375rem 1rem;border-radius:.625rem;font-size:.8125rem;font-weight:600;text-decoration:none;
                  <?= $sub===$k ? 'background:linear-gradient(135deg,#7a2e22,#7a2e22);color:white;' : 'background:white;color:#6b7280;border:1px solid #e5e7eb;' ?>">
            <?= $lbl ?>
        </a>
        <?php endforeach; ?>
    </div>
    <button onclick="toggleExportCard()" class="btn-sm"
            style="background:#fff8f6;color:#7a2e22;border:1px solid #fce9e3;display:flex;align-items:center;gap:.375rem;padding:.375rem 1rem;font-size:.8125rem;">
        <i class="fas fa-file-excel"></i> Export Excel
    </button>
</div>

<!-- Card Export Excel -->
<div id="exportCard" class="cc" style="display:none;border-color:#fce9e3;margin-bottom:1rem;">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:.75rem;">
        <div>
            <h2 style="margin:0;color:#7a2e22;"><i class="fas fa-file-excel"></i> Download Laporan Excel</h2>
            <p style="font-size:.875rem;color:#6b7280;margin:.25rem 0 0;">
                File berisi 4 sheet: <strong>Ringkasan</strong>, <strong>Detail Transaksi</strong>, <strong>Per Produk</strong>, dan <strong>Per Bulan</strong>.
            </p>
        </div>
        <button onclick="toggleExportCard()" style="background:none;border:none;color:#9ca3af;cursor:pointer;font-size:1.125rem;line-height:1;padding:0;">
            <i class="fas fa-times"></i>
        </button>
    </div>
    <form action="export_excel.php" method="GET"
          style="display:flex;gap:.75rem;align-items:flex-end;flex-wrap:wrap;">
        <div>
            <label style="display:block;font-size:.875rem;font-weight:600;color:#6b7280;margin-bottom:.375rem;">Dari Tanggal</label>
            <input type="date" name="dari" value="<?= date('Y-01-01') ?>"
                   style="padding:.5rem .75rem;border:1px solid #e5e7eb;border-radius:.625rem;font-size:.9375rem;font-family:inherit;outline:none;background:#f9fafb;">
        </div>
        <div>
            <label style="display:block;font-size:.875rem;font-weight:600;color:#6b7280;margin-bottom:.375rem;">Sampai Tanggal</label>
            <input type="date" name="sampai" value="<?= date('Y-m-d') ?>"
                   style="padding:.5rem .75rem;border:1px solid #e5e7eb;border-radius:.625rem;font-size:.9375rem;font-family:inherit;outline:none;background:#f9fafb;">
        </div>
        <button type="submit" class="btn-sm"
                style="background:linear-gradient(135deg,#7a2e22,#7a2e22);color:white;display:flex;align-items:center;gap:.5rem;padding:.5rem 1.25rem;">
            <i class="fas fa-download"></i> Download Excel
        </button>
    </form>
</div>

<script>
function toggleExportCard() {
    var c = document.getElementById('exportCard');
    c.style.display = c.style.display === 'none' ? 'block' : 'none';
}
</script>

<?php if ($sub === 'laporan'): ?>

<div class="sg" style="grid-template-columns:repeat(2,1fr);">
    <div class="sc">
        <div class="sc-row"><span class="sc-icon"><i class="fas fa-coins"></i></span></div>
        <div class="sc-val"><?= fmt_rp((int)$grand_total) ?></div>
        <div class="sc-lbl">Total Pendapatan</div>
    </div>
    <div class="sc">
        <div class="sc-row"><span class="sc-icon"><i class="fas fa-box"></i></span></div>
        <div class="sc-val"><?= $grand_count ?></div>
        <div class="sc-lbl">Total Transaksi</div>
    </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
    <div class="cc" style="margin-bottom:0;">
        <h2>Pendapatan per Bulan</h2>
        <?php if (empty($per_bulan)): ?>
        <p style="color:#9ca3af;text-align:center;padding:1rem 0;">Belum ada data.</p>
        <?php else: ?>
        <?php foreach ($per_bulan as $b): ?>
        <div class="lap-row">
            <span class="lap-lbl"><?= date('F Y', strtotime($b['bulan'].'-01')) ?></span>
            <div style="text-align:right;">
                <div class="lap-val"><?= fmt_rp((int)$b['total']) ?></div>
                <div style="font-size:.65rem;color:#9ca3af;"><?= $b['jumlah'] ?> transaksi</div>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div class="cc" style="margin-bottom:0;">
        <h2>Produk Terlaris</h2>
        <?php if (empty($terlaris)): ?>
        <p style="color:#9ca3af;text-align:center;padding:1rem 0;">Belum ada data.</p>
        <?php else: ?>
        <?php foreach ($terlaris as $i => $t): ?>
        <div class="lap-row">
            <div>
                <span class="tbadge" style="<?= $i===0?'background:#fef9c3;color:#854d0e;':($i===1?'background:#f1f5f9;color:#475569;':($i===2?'background:#fdf4ff;color:#7a2e22;':'background:#f3f4f6;color:#374151;')) ?>">#<?= $i+1 ?></span>
                <span style="font-size:.8125rem;color:#374151;margin-left:.375rem;"><?= htmlspecialchars($t['nama_produk']) ?></span>
            </div>
            <div style="text-align:right;">
                <div style="font-size:.75rem;font-weight:700;color:#7a2e22;"><?= $t['total_terjual'] ?> terjual</div>
                <div style="font-size:.65rem;color:#9ca3af;"><?= fmt_rp((int)$t['total_pendapatan']) ?></div>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php else: ?>

<!-- REKAP TRANSAKSI -->
<div class="cc">
    <h2>Rekap Semua Transaksi (<?= count($semua_transaksi) ?>)</h2>
    <?php if (empty($semua_transaksi)): ?>
    <p style="text-align:center;color:#9ca3af;padding:2rem 0;">Belum ada data transaksi.</p>
    <?php else: ?>
    <table class="dtbl">
        <thead><tr><th>ID</th><th>Pelanggan</th><th>Tanggal</th><th>Total</th></tr></thead>
        <tbody>
        <?php foreach ($semua_transaksi as $t): ?>
        <tr>
            <td class="t-sub">#<?= $t['id_penjualan'] ?></td>
            <td class="t-name"><?= htmlspecialchars($t['nama_user']) ?></td>
            <td class="t-sub"><?= date('d M Y', strtotime($t['tgl_penjualan'])) ?></td>
            <td class="t-bold"><?= fmt_rp((int)$t['total_harga']) ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<?php endif; ?>

<?php require_once 'inc/footer.php'; ?>
