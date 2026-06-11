<?php
$page_key   = 'kelola_penjualan';
$page_title = 'Detail Penjualan';
session_start();
require_once '../config/db.php';
require_once 'inc/header.php';

$id = intval($_GET['id'] ?? 0);
if (!$id) { header('Location: kelola_penjualan.php'); exit; }

$msg_ok = '';

// UPDATE STATUS
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ubah_status'])) {
    $valid_status = ['pending','diproses','dikirim','selesai','dibatalkan'];
    $new_status   = in_array($_POST['status'], $valid_status) ? $_POST['status'] : null;
    if ($new_status) {
        $pdo->prepare("UPDATE penjualan SET status=? WHERE id_penjualan=?")->execute([$new_status, $id]);
        $msg_ok = 'Status pesanan diperbarui menjadi <strong>' . ucfirst($new_status) . '</strong>.';
    }
}

// DATA PENJUALAN
$stmt = $pdo->prepare(
    "SELECT p.*,
            COALESCE(u.nama, p.nama_pelanggan, 'Pelanggan Umum') AS nama_user,
            u.no_telepon AS telp_akun, u.username
     FROM penjualan p
     LEFT JOIN user u ON p.id_user = u.id_user
     WHERE p.id_penjualan = ?"
);
$stmt->execute([$id]);
$penjualan = $stmt->fetch();
if (!$penjualan) { header('Location: kelola_penjualan.php'); exit; }

// DETAIL PRODUK
$stmt2 = $pdo->prepare(
    "SELECT dp.*, pr.nama_produk, pr.gambar_produk, pr.harga AS harga_satuan
     FROM detail_penjualan dp
     JOIN produk pr ON dp.id_produk = pr.id_produk
     WHERE dp.id_penjualan = ?"
);
$stmt2->execute([$id]);
$detail = $stmt2->fetchAll();

$status_info = [
    'pending'    => ['label'=>'Pending',    'color'=>'#d97706','bg'=>'#fffbeb','icon'=>'clock'],
    'diproses'   => ['label'=>'Diproses',   'color'=>'#2563eb','bg'=>'#eff6ff','icon'=>'cog'],
    'dikirim'    => ['label'=>'Dikirim',    'color'=>'#0369a1','bg'=>'#f0f9ff','icon'=>'truck'],
    'selesai'    => ['label'=>'Selesai',    'color'=>'#15803d','bg'=>'#f0fdf4','icon'=>'check-circle'],
    'dibatalkan' => ['label'=>'Dibatalkan', 'color'=>'#dc2626','bg'=>'#fef2f2','icon'=>'times-circle'],
];
$st = $status_info[$penjualan['status']] ?? $status_info['pending'];
$has_map = !empty($penjualan['lat']) && !empty($penjualan['lng']);
?>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<style>
.dp-wrap{display:grid;grid-template-columns:1fr 300px;gap:1.25rem;align-items:start;}
.dp-card{background:white;border-radius:1rem;border:1px solid #e5e7eb;padding:1.25rem 1.5rem;margin-bottom:1.25rem;box-shadow:0 1px 4px rgba(0,0,0,.05);}
.dp-card h3{font-size:.875rem;font-weight:700;color:#374151;margin:0 0 1rem;display:flex;align-items:center;gap:.5rem;}
.dp-grid{display:grid;grid-template-columns:1fr 1fr;gap:.75rem 1.5rem;}
.dp-item{display:flex;flex-direction:column;gap:.2rem;}
.dp-item.full{grid-column:1/-1;}
.dp-lbl{font-size:.65rem;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:.05em;}
.dp-val{font-size:.875rem;color:#1f2937;line-height:1.5;}
.dp-val.empty{color:#d1d5db;font-style:italic;}
.status-badge-lg{display:inline-flex;align-items:center;gap:.5rem;padding:.5rem 1.125rem;border-radius:9999px;font-size:.875rem;font-weight:700;}
.timeline{display:flex;flex-direction:column;gap:0;}
.tl-item{display:flex;gap:.875rem;padding-bottom:1.25rem;position:relative;}
.tl-item:last-child{padding-bottom:0;}
.tl-item:not(:last-child)::before{content:'';position:absolute;left:13px;top:28px;bottom:0;width:2px;background:#e5e7eb;}
.tl-dot{width:28px;height:28px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:.75rem;flex-shrink:0;border:2px solid;}
.tl-dot.done{background:#854040;border-color:#854040;color:white;}
.tl-dot.active{background:white;border-color:#854040;color:#854040;}
.tl-dot.todo{background:white;border-color:#e5e7eb;color:#d1d5db;}
.tl-content{flex:1;padding-top:.2rem;}
.tl-label{font-size:.8125rem;font-weight:600;color:#1f2937;}
.tl-sub{font-size:.7rem;color:#9ca3af;margin-top:.1rem;}
.status-btn{padding:.4rem 1rem;border-radius:.5rem;border:1.5px solid;font-size:.75rem;font-weight:600;cursor:pointer;font-family:inherit;transition:all .15s;width:100%;text-align:left;display:flex;align-items:center;gap:.5rem;}
.map-mini{height:160px;border-radius:.625rem;overflow:hidden;border:1px solid #e5e7eb;margin-top:.75rem;}
.prod-row{display:flex;gap:.75rem;align-items:center;padding:.625rem 0;border-bottom:1px solid #f3f4f6;}
.prod-row:last-child{border-bottom:none;}
.prod-img{width:44px;height:44px;border-radius:.5rem;background:linear-gradient(135deg,#fce9e3,#f5d4cb);display:flex;align-items:center;justify-content:center;flex-shrink:0;overflow:hidden;}
.prod-img img{width:100%;height:100%;object-fit:cover;}
</style>

<div style="margin-bottom:1rem;display:flex;align-items:center;gap:1rem;flex-wrap:wrap;">
    <a href="kelola_penjualan.php" style="font-size:.8125rem;color:#854040;font-weight:600;text-decoration:none;">← Kembali ke Kelola Penjualan</a>
    <span style="font-size:.8125rem;color:#9ca3af;">|</span>
    <span style="font-size:.8125rem;color:#374151;font-weight:600;">Pesanan #<?= str_pad($id,5,'0',STR_PAD_LEFT) ?></span>
    <span class="status-badge-lg" style="background:<?= $st['bg'] ?>;color:<?= $st['color'] ?>;">
        <i class="fas fa-<?= $st['icon'] ?>"></i> <?= $st['label'] ?>
    </span>
</div>

<?php if ($msg_ok): ?>
<div style="background:#f0fdf4;border:1px solid #86efac;border-radius:.75rem;padding:.625rem 1rem;font-size:.875rem;color:#15803d;margin-bottom:1rem;">
    <i class="fas fa-check-circle"></i> <?= $msg_ok ?>
</div>
<?php endif; ?>

<div class="dp-wrap">
<div>

    <!-- INFO PENGIRIMAN -->
    <div class="dp-card">
        <h3><i class="fas fa-map-marker-alt" style="color:#854040;"></i> Informasi Pengiriman</h3>
        <div class="dp-grid">
            <div class="dp-item">
                <span class="dp-lbl">Pelanggan</span>
                <span class="dp-val"><?= htmlspecialchars($penjualan['nama_user']) ?></span>
                <?php if ($penjualan['username']): ?>
                <span style="font-size:.72rem;color:#9ca3af;">@<?= htmlspecialchars($penjualan['username']) ?></span>
                <?php endif; ?>
            </div>
            <div class="dp-item">
                <span class="dp-lbl">Nama Penerima</span>
                <span class="dp-val"><?= htmlspecialchars($penjualan['nama_penerima'] ?: '-') ?></span>
            </div>
            <div class="dp-item">
                <span class="dp-lbl">No. Telepon Penerima</span>
                <span class="dp-val"><?= htmlspecialchars($penjualan['no_telepon_kirim'] ?: ($penjualan['telp_akun'] ?? '-')) ?></span>
            </div>
            <div class="dp-item">
                <span class="dp-lbl">Ekspedisi</span>
                <span class="dp-val"><?= htmlspecialchars($penjualan['ekspedisi'] ?: '-') ?></span>
            </div>
            <div class="dp-item full">
                <span class="dp-lbl">Alamat Pengiriman</span>
                <span class="dp-val <?= empty($penjualan['alamat_pengiriman']) ? 'empty' : '' ?>">
                    <?php if ($penjualan['alamat_pengiriman']): ?>
                    <?= htmlspecialchars($penjualan['alamat_pengiriman']) ?>,
                    <?= htmlspecialchars($penjualan['kota'] ?? '') ?>
                    <?php if ($penjualan['kode_pos']): ?><?= htmlspecialchars($penjualan['kode_pos']) ?><?php endif; ?>
                    <?php else: ?>Tidak ada data alamat<?php endif; ?>
                </span>
            </div>
            <?php if ($penjualan['catatan']): ?>
            <div class="dp-item full">
                <span class="dp-lbl">Catatan Pembeli</span>
                <span class="dp-val"><?= htmlspecialchars($penjualan['catatan']) ?></span>
            </div>
            <?php endif; ?>
            <?php if ($has_map): ?>
            <div class="dp-item full">
                <span class="dp-lbl">Titik Koordinat</span>
                <span class="dp-val" style="font-size:.75rem;color:#6b7280;">
                    <?= round($penjualan['lat'],6) ?>, <?= round($penjualan['lng'],6) ?>
                    <a href="https://www.openstreetmap.org/?mlat=<?= $penjualan['lat'] ?>&mlon=<?= $penjualan['lng'] ?>#map=16/<?= $penjualan['lat'] ?>/<?= $penjualan['lng'] ?>"
                       target="_blank" style="color:#854040;font-weight:600;text-decoration:none;margin-left:.5rem;">
                        <i class="fas fa-external-link-alt"></i> Buka Peta
                    </a>
                </span>
                <div class="map-mini" id="mapDetail"></div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- PRODUK -->
    <div class="dp-card">
        <h3><i class="fas fa-shopping-bag" style="color:#854040;"></i> Item Produk</h3>
        <?php foreach ($detail as $d): ?>
        <div class="prod-row">
            <div class="prod-img">
                <?php if ($d['gambar_produk']): ?>
                <img src="../assets/images/products/<?= htmlspecialchars($d['gambar_produk']) ?>" alt="">
                <?php else: ?><i class="fas fa-tshirt" style="color:#dfb0a2;"></i><?php endif; ?>
            </div>
            <div style="flex:1;">
                <div style="font-size:.8125rem;font-weight:600;color:#1f2937;"><?= htmlspecialchars($d['nama_produk']) ?></div>
                <div style="font-size:.72rem;color:#9ca3af;"><?= $d['jumlah'] ?> pcs × <?= fmt_rp((int)$d['harga_satuan']) ?></div>
            </div>
            <div style="font-weight:700;color:#854040;font-size:.875rem;"><?= fmt_rp((int)$d['subtotal']) ?></div>
        </div>
        <?php endforeach; ?>
        <div style="display:flex;justify-content:space-between;padding:.75rem 0 0;border-top:2px solid #f3f4f6;margin-top:.5rem;">
            <span style="font-weight:700;color:#374151;">Total Pembayaran</span>
            <span style="font-weight:700;color:#854040;font-size:1.0625rem;"><?= fmt_rp((int)$penjualan['total_harga']) ?></span>
        </div>
    </div>

</div>
<div>

    <!-- STATUS & UBAH STATUS -->
    <div class="dp-card">
        <h3><i class="fas fa-truck" style="color:#854040;"></i> Status Pengiriman</h3>

        <?php
        $steps = [
            'pending'  => ['Pesanan Masuk',    'Pesanan diterima sistem'],
            'diproses' => ['Sedang Diproses',   'Admin sedang memproses pesanan'],
            'dikirim'  => ['Dalam Pengiriman',  'Paket dalam perjalanan'],
            'selesai'  => ['Pesanan Selesai',   'Pesanan telah diterima pembeli'],
        ];
        $step_keys = array_keys($steps);
        $cur_idx   = array_search($penjualan['status'], $step_keys);
        $cancelled = $penjualan['status'] === 'dibatalkan';
        ?>

        <?php if ($cancelled): ?>
        <div style="background:#fef2f2;border:1px solid #fca5a5;border-radius:.625rem;padding:.75rem 1rem;font-size:.875rem;color:#dc2626;font-weight:600;">
            <i class="fas fa-times-circle"></i> Pesanan ini telah dibatalkan.
        </div>
        <?php else: ?>
        <div class="timeline">
            <?php foreach ($steps as $key => [$label, $sub]):
                $s_idx  = array_search($key, $step_keys);
                $done   = $cur_idx !== false && $s_idx <= $cur_idx;
                $active = $cur_idx !== false && $s_idx == $cur_idx;
                $cls    = $done ? 'done' : ($active ? 'active' : 'todo');
            ?>
            <div class="tl-item">
                <div class="tl-dot <?= $cls ?>">
                    <i class="fas fa-<?= ($done && !$active) ? 'check' : 'circle' ?>"></i>
                </div>
                <div class="tl-content">
                    <div class="tl-label" style="<?= $active ? 'color:#854040;font-weight:700;' : ($done ? '' : 'color:#9ca3af;') ?>"><?= $label ?></div>
                    <div class="tl-sub"><?= $sub ?></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Tombol ubah status -->
        <div style="margin-top:1.25rem;padding-top:1rem;border-top:1px solid #f3f4f6;">
            <div style="font-size:.75rem;font-weight:700;color:#6b7280;margin-bottom:.625rem;">UBAH STATUS</div>
            <form method="POST">
                <input type="hidden" name="ubah_status" value="1">
                <div style="display:flex;flex-direction:column;gap:.375rem;">
                    <?php
                    $btn_styles = [
                        'pending'    => 'border-color:#fde68a;color:#d97706;',
                        'diproses'   => 'border-color:#bfdbfe;color:#2563eb;',
                        'dikirim'    => 'border-color:#bae6fd;color:#0369a1;',
                        'selesai'    => 'border-color:#86efac;color:#15803d;',
                        'dibatalkan' => 'border-color:#fca5a5;color:#dc2626;',
                    ];
                    $btn_icons  = ['pending'=>'clock','diproses'=>'cog','dikirim'=>'truck','selesai'=>'check-circle','dibatalkan'=>'times-circle'];
                    $btn_labels = ['pending'=>'Pending','diproses'=>'Diproses','dikirim'=>'Dikirim','selesai'=>'Selesai','dibatalkan'=>'Batalkan Pesanan'];
                    foreach ($btn_styles as $k => $sty):
                        if ($k === $penjualan['status']) continue;
                    ?>
                    <button type="submit" name="status" value="<?= $k ?>"
                            class="status-btn" style="<?= $sty ?>background:white;"
                            onclick="return confirm('Ubah status menjadi <?= ucfirst($k) ?>?')">
                        <i class="fas fa-<?= $btn_icons[$k] ?>"></i> <?= $btn_labels[$k] ?>
                    </button>
                    <?php endforeach; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- INFO PEMBAYARAN -->
    <div class="dp-card">
        <h3><i class="fas fa-credit-card" style="color:#854040;"></i> Pembayaran</h3>
        <div class="dp-item" style="margin-bottom:.625rem;">
            <span class="dp-lbl">Metode Bayar</span>
            <span class="dp-val"><?= htmlspecialchars($penjualan['metode_bayar'] ?: '-') ?></span>
        </div>
        <div class="dp-item" style="margin-bottom:.625rem;">
            <span class="dp-lbl">Tanggal Pesanan</span>
            <span class="dp-val"><?= date('d F Y, H:i', strtotime($penjualan['tgl_penjualan'])) ?></span>
        </div>
        <div class="dp-item">
            <span class="dp-lbl">Total</span>
            <span style="font-size:1.125rem;font-weight:700;color:#854040;"><?= fmt_rp((int)$penjualan['total_harga']) ?></span>
        </div>
    </div>

</div>
</div>

<?php if ($has_map): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var map = L.map('mapDetail', {zoomControl:true, attributionControl:false});
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);
    var ll = [<?= $penjualan['lat'] ?>, <?= $penjualan['lng'] ?>];
    map.setView(ll, 16);
    L.marker(ll).addTo(map)
        .bindPopup('<?= htmlspecialchars(addslashes($penjualan['alamat_pengiriman'] ?? 'Titik pengiriman')) ?>')
        .openPopup();
});
</script>
<?php endif; ?>

<?php require_once 'inc/footer.php'; ?>
