<?php
$page_key   = 'transaksi';
$page_title = 'Transaksi';
session_start();
require_once '../config/db.php';
require_once 'inc/header.php';

// Auto-migrasi: tambah kolom nama_pelanggan & buat id_user nullable
try { $pdo->exec("ALTER TABLE penjualan ADD COLUMN nama_pelanggan VARCHAR(150) NULL"); } catch (PDOException $e) {}
try { $pdo->exec("ALTER TABLE penjualan MODIFY id_user INT NULL"); } catch (PDOException $e) {}

$msg_ok  = '';
$msg_err = '';
$sub     = $_GET['sub'] ?? 'input';

// DATA TRANSAKSI (sub=proses & cetak) — pakai LEFT JOIN agar yang manual juga muncul
$penjualan_list = $pdo->query(
    "SELECT p.id_penjualan, p.tgl_penjualan, p.total_harga,
            COALESCE(p.nama_pelanggan, u.nama, 'Pelanggan Umum') AS nama_user
     FROM penjualan p
     LEFT JOIN user u ON p.id_user = u.id_user
     ORDER BY p.tgl_penjualan DESC"
)->fetchAll();

// PRODUK untuk form input
$produk_list = $pdo->query("SELECT id_produk, nama_produk, harga, stok FROM produk ORDER BY nama_produk")->fetchAll();

// INPUT TRANSAKSI
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi_transaksi'])) {
    $nama_pelanggan = trim($_POST['nama_pelanggan'] ?? '');
    $items          = $_POST['items'] ?? [];
    $quantities     = $_POST['quantities'] ?? [];

    if ($nama_pelanggan === '') {
        $msg_err = 'Nama pelanggan wajib diisi.';
    } elseif (empty($items)) {
        $msg_err = 'Pilih minimal satu produk.';
    } else {
        $total = 0;
        $cart  = [];
        $valid = true;

        foreach ($items as $idx => $id_produk) {
            $qty = intval($quantities[$idx] ?? 1);
            if ($qty < 1) continue;

            $cek = $pdo->prepare("SELECT harga, stok, nama_produk FROM produk WHERE id_produk = ?");
            $cek->execute([$id_produk]);
            $prod = $cek->fetch();

            if (!$prod) continue;
            if ($prod['stok'] < $qty) {
                $msg_err = 'Stok "'.htmlspecialchars($prod['nama_produk']).'" tidak mencukupi (tersisa '.$prod['stok'].' unit).';
                $valid   = false;
                break;
            }
            $subtotal = $prod['harga'] * $qty;
            $total   += $subtotal;
            $cart[]   = ['id_produk' => $id_produk, 'qty' => $qty, 'subtotal' => $subtotal];
        }

        if ($valid && !empty($cart)) {
            $stmt = $pdo->prepare(
                "INSERT INTO penjualan (tgl_penjualan, total_harga, id_user, nama_pelanggan)
                 VALUES (NOW(), ?, NULL, ?)"
            );
            $stmt->execute([$total, $nama_pelanggan]);
            $id_penjualan = $pdo->lastInsertId();

            foreach ($cart as $item) {
                $pdo->prepare("INSERT INTO detail_penjualan (id_penjualan, id_produk, jumlah, subtotal) VALUES (?,?,?,?)")
                    ->execute([$id_penjualan, $item['id_produk'], $item['qty'], $item['subtotal']]);
                $pdo->prepare("UPDATE produk SET stok = stok - ? WHERE id_produk = ?")
                    ->execute([$item['qty'], $item['id_produk']]);
            }

            $msg_ok = 'Transaksi #'.str_pad($id_penjualan,5,'0',STR_PAD_LEFT).' berhasil! Total: '.fmt_rp($total);
        }
    }
}
?>

<style>
/* Print: sembunyikan semua kecuali area struk */
@media print {
    body * { visibility: hidden; }
    #struk-area, #struk-area * { visibility: visible; }
    #struk-area {
        position: fixed; top: 0; left: 0;
        width: 80mm; /* lebar struk standar kasir */
        font-family: 'Courier New', monospace;
        font-size: 11px;
        color: #000;
        padding: 4mm;
    }
    .no-print { display: none !important; }
}
.struk-wrap {
    max-width: 360px;
    margin: 0 auto;
    font-family: 'Courier New', Courier, monospace;
    font-size: .8125rem;
    background: white;
    border: 1px dashed #d1d5db;
    border-radius: .75rem;
    padding: 1.5rem;
}
.struk-logo {
    text-align: center;
    border-bottom: 1px dashed #d1d5db;
    padding-bottom: .875rem;
    margin-bottom: .875rem;
}
.struk-logo .s-nama { font-size: 1.125rem; font-weight: 700; letter-spacing: .05em; color: #7a2e22; }
.struk-logo .s-tagline { font-size: .7rem; color: #6b7280; margin-top: .125rem; }
.struk-logo .s-alamat { font-size: .65rem; color: #9ca3af; margin-top: .125rem; }
.struk-info { border-bottom: 1px dashed #d1d5db; padding-bottom: .75rem; margin-bottom: .75rem; }
.struk-row { display: flex; justify-content: space-between; font-size: .75rem; margin-bottom: .2rem; }
.struk-row .lbl { color: #6b7280; }
.struk-row .val { font-weight: 500; text-align: right; max-width: 60%; }
.struk-items { border-bottom: 1px dashed #d1d5db; padding-bottom: .75rem; margin-bottom: .75rem; }
.struk-item { margin-bottom: .5rem; font-size: .75rem; }
.struk-item .item-nama { font-weight: 600; color: #111827; margin-bottom: .1rem; }
.struk-item .item-detail { display: flex; justify-content: space-between; color: #6b7280; font-size: .7rem; }
.struk-total { display: flex; justify-content: space-between; font-size: 1rem; font-weight: 700; margin-bottom: .25rem; }
.struk-total .t-label { color: #111827; }
.struk-total .t-val { color: #7a2e22; }
.struk-footer { text-align: center; font-size: .65rem; color: #9ca3af; margin-top: .875rem; border-top: 1px dashed #d1d5db; padding-top: .75rem; line-height: 1.6; }
</style>

<!-- Sub-nav + tombol export -->
<div style="display:flex;gap:.5rem;margin-bottom:1rem;align-items:center;flex-wrap:wrap;" class="no-print">
    <div style="display:flex;gap:.5rem;flex:1;flex-wrap:wrap;">
        <?php foreach (['input'=>'Input Transaksi','proses'=>'Riwayat & Cetak'] as $k=>$lbl): ?>
        <a href="transaksi.php?sub=<?= $k ?>"
           style="padding:.375rem 1rem;border-radius:.625rem;font-size:.8125rem;font-weight:600;text-decoration:none;
                  <?= $sub===$k ? 'background:linear-gradient(135deg,#7a2e22,#9e5848);color:white;' : 'background:white;color:#6b7280;border:1px solid #e5e7eb;' ?>">
            <?= $lbl ?>
        </a>
        <?php endforeach; ?>
    </div>
    <button onclick="toggleExportCard()" class="btn-sm"
            style="background:#e0f2fe;color:#7a2e22;border:1px solid #bae6fd;display:flex;align-items:center;gap:.375rem;padding:.375rem 1rem;font-size:.8125rem;">
        <i class="fas fa-file-excel"></i> Export Excel
    </button>
</div>

<!-- Card Export Excel -->
<div id="exportCard" class="cc no-print" style="display:none;border-color:#bae6fd;margin-bottom:1rem;">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:.75rem;">
        <div>
            <h2 style="margin:0;color:#7a2e22;"><i class="fas fa-file-excel"></i> Download Laporan Excel</h2>
            <p style="font-size:.875rem;color:#6b7280;margin:.25rem 0 0;">
                File berisi 4 sheet: <strong>Ringkasan</strong>, <strong>Detail Transaksi</strong>, <strong>Per Produk</strong>, dan <strong>Per Bulan</strong>.
                Default periode adalah hari ini.
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
            <input type="date" name="dari" value="<?= date('Y-m-d') ?>"
                   style="padding:.5rem .75rem;border:1px solid #e5e7eb;border-radius:.625rem;font-size:.9375rem;font-family:inherit;outline:none;background:#f9fafb;">
        </div>
        <div>
            <label style="display:block;font-size:.875rem;font-weight:600;color:#6b7280;margin-bottom:.375rem;">Sampai Tanggal</label>
            <input type="date" name="sampai" value="<?= date('Y-m-d') ?>"
                   style="padding:.5rem .75rem;border:1px solid #e5e7eb;border-radius:.625rem;font-size:.9375rem;font-family:inherit;outline:none;background:#f9fafb;">
        </div>
        <button type="submit" class="btn-sm"
                style="background:linear-gradient(135deg,#7a2e22,#9e5848);color:white;display:flex;align-items:center;gap:.5rem;padding:.5rem 1.25rem;">
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

<?php if ($msg_ok):  ?><div class="alert-ok no-print"><i class="fas fa-check-circle"></i> <?= $msg_ok ?></div><?php endif; ?>
<?php if ($msg_err): ?><div class="alert-err no-print"><i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($msg_err) ?></div><?php endif; ?>

<?php if ($sub === 'input'): ?>
<!-- ===================== INPUT TRANSAKSI ===================== -->
<div class="cc">
    <h2>Input Transaksi Baru</h2>
    <form method="POST" action="transaksi.php?sub=input">
        <input type="hidden" name="aksi_transaksi" value="1">

        <!-- Nama Pelanggan (manual) -->
        <div class="fg" style="margin-bottom:1.25rem;">
            <label>Nama Pelanggan *</label>
            <input type="text" name="nama_pelanggan"
                   placeholder="Contoh: Ibu Siti / Pelanggan Umum"
                   value="<?= htmlspecialchars($_POST['nama_pelanggan'] ?? '') ?>"
                   required autocomplete="off"
                   style="max-width:360px;">
        </div>

        <h2 style="font-size:.875rem;margin-bottom:.75rem;color:#374151;">Pilih Produk</h2>
        <div style="overflow-x:auto;">
        <table class="dtbl" id="produk-table">
            <thead>
                <tr>
                    <th style="width:30px;">
                        <input type="checkbox" id="check-all" onclick="toggleAll(this)">
                    </th>
                    <th>Nama Produk</th>
                    <th>Harga Satuan</th>
                    <th>Stok</th>
                    <th>Qty</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($produk_list as $p): ?>
            <tr>
                <td>
                    <input type="checkbox" name="items[]" value="<?= $p['id_produk'] ?>"
                           class="prod-check" onchange="toggleQty(this)"
                           <?= $p['stok'] == 0 ? 'disabled' : '' ?>>
                </td>
                <td class="t-name"><?= htmlspecialchars($p['nama_produk']) ?></td>
                <td class="t-bold"><?= fmt_rp((int)$p['harga']) ?></td>
                <td>
                    <span class="tbadge <?= $p['stok']>=5?'stok-ok':($p['stok']>0?'stok-low':'stok-out') ?>">
                        <?= $p['stok'] > 0 ? $p['stok'].' unit' : 'Habis' ?>
                    </span>
                </td>
                <td>
                    <input type="number" name="quantities[]" min="1" max="<?= $p['stok'] ?>" value="1"
                           class="qty-inp"
                           style="width:65px;padding:.25rem .4rem;border:1px solid #e5e7eb;border-radius:.375rem;font-size:.75rem;"
                           disabled>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>

        <!-- Ringkasan harga real-time -->
        <div id="summary-bar" style="display:none;margin-top:.875rem;background:#fff8f6;border:1px solid #fce9e3;border-radius:.625rem;padding:.625rem 1rem;font-size:.8125rem;color:#5a1a10;">
            <strong>Dipilih:</strong> <span id="sum-count">0</span> produk &nbsp;|&nbsp;
            <strong>Estimasi Total:</strong> <span id="sum-total">Rp 0</span>
        </div>

        <button type="submit" class="btn-sm" style="background:linear-gradient(135deg,#7a2e22,#9e5848);color:white;margin-top:1rem;">
            <i class="fas fa-save"></i> Simpan Transaksi
        </button>
    </form>
</div>

<?php else: ?>
<!-- ===================== RIWAYAT & CETAK ===================== -->
<?php
$id_cetak = intval($_GET['id'] ?? 0);
$bukti    = null;
$detail   = [];

if ($id_cetak) {
    $stmt = $pdo->prepare(
        "SELECT p.*, COALESCE(p.nama_pelanggan, u.nama, 'Pelanggan Umum') AS nama_pelanggan_tampil
         FROM penjualan p
         LEFT JOIN user u ON p.id_user = u.id_user
         WHERE p.id_penjualan = ?"
    );
    $stmt->execute([$id_cetak]);
    $bukti = $stmt->fetch();

    if ($bukti) {
        $stmt2 = $pdo->prepare(
            "SELECT dp.jumlah, dp.subtotal, pr.nama_produk, pr.harga
             FROM detail_penjualan dp
             JOIN produk pr ON dp.id_produk = pr.id_produk
             WHERE dp.id_penjualan = ?"
        );
        $stmt2->execute([$id_cetak]);
        $detail = $stmt2->fetchAll();
    }
}
?>

<?php if ($id_cetak && $bukti): ?>
<!-- STRUK CETAK -->
<div id="struk-area">
<div class="struk-wrap" id="cetak-area">

    <!-- Header toko -->
    <div class="struk-logo">
        <div class="s-nama">ELEA STORE</div>
        <div class="s-tagline">Fashion for All</div>
        <div class="s-alamat">Jakarta, Indonesia &nbsp;|&nbsp; WA: 0812-xxxx-xxxx</div>
    </div>

    <!-- Info transaksi -->
    <div class="struk-info">
        <div class="struk-row">
            <span class="lbl">No. Struk</span>
            <span class="val">#<?= str_pad($bukti['id_penjualan'],5,'0',STR_PAD_LEFT) ?></span>
        </div>
        <div class="struk-row">
            <span class="lbl">Tanggal</span>
            <span class="val"><?= date('d M Y', strtotime($bukti['tgl_penjualan'])) ?></span>
        </div>
        <div class="struk-row">
            <span class="lbl">Waktu</span>
            <span class="val"><?= date('H:i', strtotime($bukti['tgl_penjualan'])) ?> WIB</span>
        </div>
        <div class="struk-row">
            <span class="lbl">Kasir</span>
            <span class="val"><?= htmlspecialchars($user['nama']) ?></span>
        </div>
        <div class="struk-row" style="margin-top:.375rem;padding-top:.375rem;border-top:1px solid #f3f4f6;">
            <span class="lbl">Nama Pelanggan</span>
            <span class="val" style="font-weight:700;color:#111827;"><?= htmlspecialchars($bukti['nama_pelanggan_tampil']) ?></span>
        </div>
    </div>

    <!-- Detail produk -->
    <div class="struk-items">
        <?php foreach ($detail as $d): ?>
        <div class="struk-item">
            <div class="item-nama"><?= htmlspecialchars($d['nama_produk']) ?></div>
            <div class="item-detail">
                <span><?= $d['jumlah'] ?> pcs &times; <?= fmt_rp((int)$d['harga']) ?></span>
                <span style="font-weight:600;color:#111827;"><?= fmt_rp((int)$d['subtotal']) ?></span>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Total -->
    <div class="struk-total">
        <span class="t-label">TOTAL BAYAR</span>
        <span class="t-val"><?= fmt_rp((int)$bukti['total_harga']) ?></span>
    </div>
    <div style="font-size:.7rem;color:#9ca3af;text-align:right;margin-top:.1rem;">
        <?= count($detail) ?> item
    </div>

    <!-- Footer -->
    <div class="struk-footer">
        Terima kasih sudah berbelanja di<br>
        <strong>Elea Store</strong><br>
        Simpan struk ini sebagai bukti pembelian.<br>
        Barang yang sudah dibeli tidak dapat ditukar<br>
        kecuali ada kerusakan produk.
    </div>
</div>
</div>

<div style="text-align:center;margin-top:1rem;" class="no-print">
    <button onclick="window.print()" class="btn-sm" style="background:linear-gradient(135deg,#7a2e22,#9e5848);color:white;padding:.5rem 1.5rem;">
        <i class="fas fa-print"></i> Cetak Struk
    </button>
    <a href="transaksi.php?sub=proses" class="btn-sm" style="background:#f3f4f6;color:#374151;text-decoration:none;margin-left:.5rem;">
        ← Kembali
    </a>
</div>

<?php else: ?>
<!-- DAFTAR RIWAYAT -->
<div class="cc">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;">
        <h2 style="margin:0;">Riwayat Transaksi</h2>
        <span style="font-size:.75rem;color:#9ca3af;"><?= count($penjualan_list) ?> transaksi</span>
    </div>
    <?php if (empty($penjualan_list)): ?>
    <p style="text-align:center;color:#9ca3af;padding:2rem 0;">Belum ada transaksi.</p>
    <?php else: ?>
    <table class="dtbl">
        <thead>
            <tr>
                <th>No. Struk</th>
                <th>Nama Pelanggan</th>
                <th>Tanggal</th>
                <th>Total</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($penjualan_list as $p): ?>
        <tr>
            <td class="t-sub">#<?= str_pad($p['id_penjualan'],5,'0',STR_PAD_LEFT) ?></td>
            <td class="t-name"><?= htmlspecialchars($p['nama_user']) ?></td>
            <td class="t-sub"><?= date('d M Y, H:i', strtotime($p['tgl_penjualan'])) ?></td>
            <td class="t-bold"><?= fmt_rp((int)$p['total_harga']) ?></td>
            <td>
                <a href="transaksi.php?sub=proses&id=<?= $p['id_penjualan'] ?>"
                   class="btn-sm" style="background:linear-gradient(135deg,#7a2e22,#9e5848);color:white;padding:.25rem .75rem;font-size:.7rem;text-decoration:none;">
                    <i class="fas fa-print"></i> Cetak
                </a>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>
<?php endif; ?>
<?php endif; ?>

<script>
/* Produk check/uncheck */
function toggleAll(cb) {
    document.querySelectorAll('.prod-check:not(:disabled)').forEach(c => {
        c.checked = cb.checked;
        c.closest('tr').querySelector('.qty-inp').disabled = !cb.checked;
    });
    updateSummary();
}
function toggleQty(cb) {
    cb.closest('tr').querySelector('.qty-inp').disabled = !cb.checked;
    updateSummary();
}

/* Real-time summary */
const hargaMap = {
    <?php foreach ($produk_list as $p): ?>
    <?= $p['id_produk'] ?>: <?= (int)$p['harga'] ?>,
    <?php endforeach; ?>
};

function updateSummary() {
    const checks = document.querySelectorAll('.prod-check:checked');
    let total = 0;
    checks.forEach(c => {
        const qty = parseInt(c.closest('tr').querySelector('.qty-inp').value) || 1;
        total += (hargaMap[c.value] || 0) * qty;
    });
    const bar = document.getElementById('summary-bar');
    if (checks.length > 0) {
        bar.style.display = 'block';
        document.getElementById('sum-count').textContent = checks.length;
        document.getElementById('sum-total').textContent = 'Rp ' + total.toLocaleString('id-ID');
    } else {
        bar.style.display = 'none';
    }
}

/* Update summary saat qty diubah */
document.addEventListener('input', e => {
    if (e.target.classList.contains('qty-inp')) updateSummary();
});
</script>

<?php require_once 'inc/footer.php'; ?>
