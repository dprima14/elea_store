<?php
$page_key   = 'kelola_penjualan';
$page_title = 'Kelola Penjualan';
session_start();
require_once '../config/db.php';
require_once 'inc/header.php';

$sub = $_GET['sub'] ?? 'data';

// DATA PENJUALAN
$penjualan_list = $pdo->query(
    "SELECT p.id_penjualan, p.tgl_penjualan, p.total_harga, p.status,
            p.nama_penerima, p.ekspedisi, p.metode_bayar,
            COALESCE(p.nama_pelanggan, u.nama, 'Pelanggan Umum') AS nama_user,
            COALESCE(u.no_telepon, p.no_telepon_kirim) AS no_telepon
     FROM penjualan p
     LEFT JOIN user u ON p.id_user = u.id_user
     ORDER BY p.tgl_penjualan DESC"
)->fetchAll();

// REKAP PER BULAN
$rekap = $pdo->query(
    "SELECT DATE_FORMAT(tgl_penjualan,'%Y-%m') AS bulan,
            COUNT(*) AS jumlah_transaksi,
            SUM(total_harga) AS total
     FROM penjualan
     GROUP BY DATE_FORMAT(tgl_penjualan,'%Y-%m')
     ORDER BY bulan DESC LIMIT 12"
)->fetchAll();

$grand_total = $pdo->query("SELECT COALESCE(SUM(total_harga),0) FROM penjualan")->fetchColumn();
$grand_count = $pdo->query("SELECT COUNT(*) FROM penjualan")->fetchColumn();

$status_filter = [
    'semua'               => 'Semua',
    'menunggu_pembayaran' => 'Menunggu Bayar',
    'pending'             => 'Dibayar',
    'diproses'            => 'Diproses',
    'dikirim'             => 'Dikirim',
    'selesai'             => 'Selesai',
    'dibatalkan'          => 'Dibatalkan',
];
$status_badge = [
    'menunggu_pembayaran' => 'background:#faf5ff;color:#7c3aed;',
    'pending'             => 'background:#fffbeb;color:#d97706;',
    'diproses'            => 'background:#eff6ff;color:#2563eb;',
    'dikirim'             => 'background:#f0f9ff;color:#0369a1;',
    'selesai'             => 'background:#f0fdf4;color:#15803d;',
    'dibatalkan'          => 'background:#fef2f2;color:#dc2626;',
];
$status_label = [
    'menunggu_pembayaran' => 'Menunggu Bayar',
    'pending'             => 'Dibayar',
    'diproses'            => 'Diproses',
    'dikirim'             => 'Dikirim',
    'selesai'             => 'Selesai',
    'dibatalkan'          => 'Dibatalkan',
];

$filter = $_GET['filter'] ?? 'semua';
$displayed = ($filter === 'semua')
    ? $penjualan_list
    : array_values(array_filter($penjualan_list, fn($p) => $p['status'] === $filter));

$count_status = [];
foreach ($penjualan_list as $p) {
    $count_status[$p['status']] = ($count_status[$p['status']] ?? 0) + 1;
}
?>

<!-- Sub-nav -->
<div style="display:flex;gap:.5rem;margin-bottom:1rem;align-items:center;flex-wrap:wrap;">
    <div style="display:flex;gap:.5rem;flex:1;flex-wrap:wrap;">
        <a href="kelola_penjualan.php?sub=data"
           style="padding:.375rem 1rem;border-radius:.625rem;font-size:.8125rem;font-weight:600;text-decoration:none;
                  <?= $sub==='data' ? 'background:linear-gradient(135deg,#5c3320,#854040);color:white;' : 'background:white;color:#6b7280;border:1px solid #e5e7eb;' ?>">
            <i class="fas fa-clipboard-list"></i> Data Penjualan
        </a>
        <a href="kelola_penjualan.php?sub=rekap"
           style="padding:.375rem 1rem;border-radius:.625rem;font-size:.8125rem;font-weight:600;text-decoration:none;
                  <?= $sub==='rekap' ? 'background:linear-gradient(135deg,#5c3320,#854040);color:white;' : 'background:white;color:#6b7280;border:1px solid #e5e7eb;' ?>">
            <i class="fas fa-chart-bar"></i> Rekap Penjualan
        </a>
    </div>
    <button onclick="toggleExportCard()" class="btn-sm"
            style="background:#fff8f6;color:#4b5563;border:1px solid #f2e8dc;display:flex;align-items:center;gap:.375rem;padding:.375rem 1rem;font-size:.8125rem;">
        <i class="fas fa-file-excel"></i> Export Excel
    </button>
</div>

<!-- Card Export -->
<div id="exportCard" class="cc" style="display:none;border-color:#f2e8dc;margin-bottom:1rem;">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:.75rem;">
        <div>
            <h2 style="margin:0;color:#4b5563;"><i class="fas fa-file-excel"></i> Download Laporan Excel</h2>
            <p style="font-size:.875rem;color:#6b7280;margin:.25rem 0 0;">Laporan penjualan dalam format Excel.</p>
        </div>
        <button onclick="toggleExportCard()" style="background:none;border:none;color:#9ca3af;cursor:pointer;font-size:1.125rem;line-height:1;padding:0;"><i class="fas fa-times"></i></button>
    </div>
    <form action="export_excel.php" method="GET" style="display:flex;gap:.75rem;align-items:flex-end;flex-wrap:wrap;">
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
                style="background:linear-gradient(135deg,#4b5563,#6b7280);color:white;display:flex;align-items:center;gap:.5rem;padding:.5rem 1.25rem;">
            <i class="fas fa-download"></i> Download Excel
        </button>
    </form>
</div>

<script>function toggleExportCard(){var c=document.getElementById('exportCard');c.style.display=c.style.display==='none'?'block':'none';}</script>

<?php if ($sub === 'data'): ?>

<!-- Filter status -->
<div style="display:flex;gap:.375rem;margin-bottom:1rem;flex-wrap:wrap;align-items:center;">
    <?php foreach ($status_filter as $k => $lbl): ?>
    <?php $cnt = ($k === 'semua') ? count($penjualan_list) : ($count_status[$k] ?? 0); ?>
    <a href="kelola_penjualan.php?sub=data&filter=<?= $k ?>"
       style="padding:.25rem .875rem;border-radius:9999px;font-size:.75rem;font-weight:600;text-decoration:none;display:inline-flex;align-items:center;gap:.375rem;
              <?= $filter===$k ? 'background:#854040;color:white;' : 'background:white;color:#6b7280;border:1px solid #e5e7eb;' ?>">
        <?= $lbl ?>
        <?php if ($cnt > 0): ?>
        <span style="<?= $filter===$k ? 'background:rgba(255,255,255,.25);' : 'background:#f3f4f6;' ?>font-size:.65rem;padding:.05rem .375rem;border-radius:9999px;"><?= $cnt ?></span>
        <?php endif; ?>
    </a>
    <?php endforeach; ?>
</div>

<div class="cc">
    <h2>Data Penjualan (<?= count($displayed) ?> transaksi)</h2>
    <?php if (empty($displayed)): ?>
    <p style="text-align:center;color:#9ca3af;padding:2rem 0;">Tidak ada data penjualan.</p>
    <?php else: ?>
    <div style="overflow-x:auto;">
    <table class="dtbl">
        <thead>
            <tr><th>ID</th><th>Pelanggan</th><th>Penerima</th><th>Ekspedisi</th><th>Tanggal</th><th>Total</th><th>Status</th><th>Aksi</th></tr>
        </thead>
        <tbody>
        <?php foreach ($displayed as $p): ?>
        <tr>
            <td class="t-sub">#<?= str_pad($p['id_penjualan'],5,'0',STR_PAD_LEFT) ?></td>
            <td>
                <div class="t-name"><?= htmlspecialchars($p['nama_user']) ?></div>
                <div class="t-sub"><?= htmlspecialchars($p['no_telepon'] ?? '-') ?></div>
            </td>
            <td class="t-sub"><?= htmlspecialchars($p['nama_penerima'] ?? '-') ?></td>
            <td class="t-sub"><?= htmlspecialchars($p['ekspedisi'] ?: '-') ?></td>
            <td class="t-sub"><?= date('d M Y', strtotime($p['tgl_penjualan'])) ?></td>
            <td class="t-bold"><?= fmt_rp((int)$p['total_harga']) ?></td>
            <td>
                <span class="tbadge" style="<?= $status_badge[$p['status']] ?? 'background:#f3f4f6;color:#6b7280;' ?>">
                    <?= $status_label[$p['status']] ?? ucfirst($p['status']) ?>
                </span>
            </td>
            <td>
                <a href="detail_penjualan.php?id=<?= $p['id_penjualan'] ?>"
                   class="btn-sm" style="background:#fff8f6;color:#854040;border:1px solid #fce9e3;padding:.25rem .625rem;font-size:.7rem;text-decoration:none;border-radius:.5rem;">
                    <i class="fas fa-eye"></i> Detail
                </a>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>
</div>

<?php else: ?>

<div class="sg" style="grid-template-columns:repeat(2,1fr);">
    <div class="sc">
        <div class="sc-row"><span class="sc-icon"><i class="fas fa-box"></i></span></div>
        <div class="sc-val"><?= $grand_count ?></div>
        <div class="sc-lbl">Total Transaksi</div>
    </div>
    <div class="sc">
        <div class="sc-row"><span class="sc-icon"><i class="fas fa-coins"></i></span></div>
        <div class="sc-val"><?= fmt_rp((int)$grand_total) ?></div>
        <div class="sc-lbl">Total Pendapatan</div>
    </div>
</div>

<div class="cc">
    <h2>Rekap Penjualan per Bulan</h2>
    <?php if (empty($rekap)): ?>
    <p style="text-align:center;color:#9ca3af;padding:2rem 0;">Belum ada data.</p>
    <?php else: ?>
    <table class="dtbl">
        <thead><tr><th>Bulan</th><th>Jumlah Transaksi</th><th>Total Pendapatan</th></tr></thead>
        <tbody>
        <?php foreach ($rekap as $r): ?>
        <tr>
            <td class="t-name"><?= date('F Y', strtotime($r['bulan'].'-01')) ?></td>
            <td><span class="tbadge" style="background:#fff8f6;color:#854040;"><?= $r['jumlah_transaksi'] ?> transaksi</span></td>
            <td class="t-bold"><?= fmt_rp((int)$r['total']) ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<?php endif; ?>

<?php require_once 'inc/footer.php'; ?>
