<?php
$page_key   = 'preorder';
$page_title = 'Pre Order';
session_start();
require_once '../config/db.php';
require_once 'inc/header.php';

$msg_ok  = '';
$msg_err = '';
$sub     = $_GET['sub'] ?? 'data';

// UBAH STATUS PRE ORDER
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi_status'])) {
    $id     = intval($_POST['id_preorder']);
    $valid  = ['pending','dikonfirmasi','ditolak','diproses','selesai','dibatalkan'];
    $status = in_array($_POST['status'], $valid) ? $_POST['status'] : 'pending';
    $pdo->prepare("UPDATE preorder SET status = ? WHERE id_preorder = ?")
        ->execute([$status, $id]);
    $msg_ok = 'Status pre order #' . $id . ' diperbarui menjadi ' . ucfirst($status) . '.';
}

// DATA PRE ORDER
$preorder_list = $pdo->query(
    "SELECT po.id_preorder, po.tanggal, po.status,
            COALESCE(u.nama, po.nama)           AS nama_user,
            COALESCE(u.no_telepon, po.no_wa)    AS no_telepon,
            COALESCE(p.nama_produk, '—')        AS nama_produk,
            COALESCE(p.harga, 0)                AS harga,
            po.jenis_preorder, po.jenis_produk,
            po.jumlah, po.ukuran,
            po.permintaan_custom, po.catatan,
            po.tanggal_dibutuhkan, po.file_referensi,
            po.no_wa AS wa_pelanggan
     FROM preorder po
     LEFT JOIN user u ON po.id_user = u.id_user
     LEFT JOIN produk p ON po.id_produk = p.id_produk
     ORDER BY po.tanggal DESC"
)->fetchAll();

$status_filter = [
    'semua'        => 'Semua',
    'pending'      => 'Pending',
    'dikonfirmasi' => 'Dikonfirmasi',
    'diproses'     => 'Diproses',
    'selesai'      => 'Selesai',
    'ditolak'      => 'Ditolak',
    'dibatalkan'   => 'Dibatalkan',
];
$filter = $_GET['filter'] ?? 'semua';
$displayed_list = ($filter === 'semua')
    ? $preorder_list
    : array_values(array_filter($preorder_list, fn($po) => $po['status'] === $filter));

$status_class = [
    'pending'      => 'status-baru',
    'dikonfirmasi' => 'status-selesai',
    'diproses'     => 'status-diproses',
    'selesai'      => 'status-selesai',
    'ditolak'      => 'status-ditolak-po',
    'dibatalkan'   => 'status-ditolak-po',
];
$status_label = [
    'pending'      => 'Pending',
    'dikonfirmasi' => 'Dikonfirmasi',
    'diproses'     => 'Diproses',
    'selesai'      => 'Selesai',
    'ditolak'      => 'Ditolak',
    'dibatalkan'   => 'Dibatalkan',
];
?>

<style>
.status-ditolak-po { background:#fef2f2; color:#dc2626; }
/* Modal */
.po-modal-overlay {
    display:none; position:fixed; inset:0; background:rgba(0,0,0,.45);
    z-index:999; align-items:center; justify-content:center;
}
.po-modal-overlay.open { display:flex; }
.po-modal {
    background:white; border-radius:1rem; width:90%; max-width:640px;
    max-height:88vh; overflow-y:auto; box-shadow:0 16px 48px rgba(0,0,0,.2);
    padding:1.75rem 2rem;
}
.po-modal-header {
    display:flex; align-items:center; justify-content:space-between;
    margin-bottom:1.25rem; border-bottom:1px solid #fce9e3; padding-bottom:.875rem;
}
.po-modal-header h3 { font-size:1.05rem; font-weight:700; color:#1a0805; margin:0; }
.po-modal-close {
    background:none; border:none; font-size:1.25rem; color:#9ca3af;
    cursor:pointer; line-height:1; padding:.25rem;
}
.po-modal-close:hover { color:#374151; }
.po-detail-grid { display:grid; grid-template-columns:1fr 1fr; gap:.75rem 1.5rem; }
.po-detail-item { display:flex; flex-direction:column; gap:.2rem; }
.po-detail-item.full { grid-column:1/-1; }
.po-detail-label { font-size:.7rem; font-weight:700; color:#9ca3af; text-transform:uppercase; letter-spacing:.05em; }
.po-detail-value { font-size:.875rem; color:#1f2937; line-height:1.5; }
.po-detail-value.empty { color:#d1d5db; font-style:italic; }
.po-ref-img-wrap {
    margin-top:.5rem; background:#f9fafb; border:1px solid #fce9e3;
    border-radius:.625rem; display:flex; align-items:center;
    justify-content:center; padding:.5rem; overflow:hidden; max-height:260px;
}
.po-ref-img { max-width:100%; max-height:240px; width:auto; height:auto; object-fit:contain; border-radius:.375rem; display:block; }
.po-modal-actions { display:flex; gap:.5rem; margin-top:1.25rem; padding-top:1rem; border-top:1px solid #fce9e3; flex-wrap:wrap; }
.po-modal-actions form { display:inline; }
</style>

<!-- Sub-nav -->
<div style="display:flex;gap:.5rem;margin-bottom:1rem;flex-wrap:wrap;">
    <?php foreach (['data'=>'Data Pre Order','konfirmasi'=>'Konfirmasi Pre Order'] as $k=>$lbl): ?>
    <a href="preorder.php?sub=<?= $k ?>"
       style="padding:.375rem 1rem;border-radius:.625rem;font-size:.8125rem;font-weight:600;text-decoration:none;
              <?= $sub===$k ? 'background:linear-gradient(135deg,#7a2e22,#9e5848);color:white;' : 'background:white;color:#6b7280;border:1px solid #e5e7eb;' ?>">
        <?= $lbl ?>
    </a>
    <?php endforeach; ?>
</div>

<?php if ($msg_ok): ?><div class="alert-ok"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($msg_ok) ?></div><?php endif; ?>

<?php if ($sub === 'data'): ?>

<!-- Filter status -->
<div style="display:flex;gap:.375rem;margin-bottom:1rem;flex-wrap:wrap;">
    <?php foreach ($status_filter as $k => $lbl): ?>
    <a href="preorder.php?sub=data&filter=<?= $k ?>"
       style="padding:.25rem .75rem;border-radius:9999px;font-size:.75rem;font-weight:600;text-decoration:none;
              <?= $filter===$k ? 'background:#7a2e22;color:white;' : 'background:white;color:#6b7280;border:1px solid #e5e7eb;' ?>">
        <?= $lbl ?>
    </a>
    <?php endforeach; ?>
</div>

<div class="cc">
    <h2>Data Pre Order (<?= count($displayed_list) ?>)</h2>
    <?php if (empty($displayed_list)): ?>
    <p style="text-align:center;color:#9ca3af;padding:2rem 0;">Tidak ada data pre order.</p>
    <?php else: ?>
    <table class="dtbl">
        <thead>
            <tr>
                <th>ID</th>
                <th>Pelanggan</th>
                <th>Produk / Jenis</th>
                <th>Jml</th>
                <th>Ukuran</th>
                <th>Tanggal</th>
                <th>Status</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($displayed_list as $po): ?>
        <tr>
            <td class="t-sub">#<?= $po['id_preorder'] ?></td>
            <td>
                <div class="t-name"><?= htmlspecialchars($po['nama_user'] ?? '—') ?></div>
                <div class="t-sub"><?= htmlspecialchars($po['no_telepon'] ?? $po['wa_pelanggan'] ?? '') ?></div>
            </td>
            <td>
                <div class="t-name"><?= htmlspecialchars($po['nama_produk']) ?></div>
                <div class="t-sub"><?= htmlspecialchars($po['jenis_preorder'] === 'custom' ? 'Custom' : 'Katalog') ?>
                    <?php if ($po['jenis_produk']): ?> · <?= htmlspecialchars($po['jenis_produk']) ?><?php endif; ?>
                </div>
            </td>
            <td class="t-sub"><?= (int)$po['jumlah'] ?></td>
            <td class="t-sub"><?= htmlspecialchars($po['ukuran'] ?: '—') ?></td>
            <td class="t-sub"><?= date('d M Y', strtotime($po['tanggal'])) ?></td>
            <td>
                <span class="tbadge <?= $status_class[$po['status']] ?? 'status-baru' ?>">
                    <?= $status_label[$po['status']] ?? ucfirst($po['status']) ?>
                </span>
            </td>
            <td>
                <button type="button" class="btn-sm"
                    onclick="openDetail(<?= htmlspecialchars(json_encode($po), ENT_QUOTES) ?>)"
                    style="background:#fff8f6;color:#7a2e22;border:1px solid #fce9e3;padding:.25rem .625rem;font-size:.7rem;border-radius:.5rem;cursor:pointer;">
                    <i class="fas fa-eye"></i> Detail
                </button>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<?php else: ?>

<!-- KONFIRMASI PRE ORDER -->
<div class="cc">
    <h2>Konfirmasi Pre Order</h2>
    <?php
    $pending_list = array_values(array_filter($preorder_list, fn($po) => $po['status'] === 'pending'));
    if (empty($pending_list)):
    ?>
    <p style="text-align:center;color:#9ca3af;padding:2rem 0;">Tidak ada pre order yang menunggu konfirmasi.</p>
    <?php else: ?>
    <table class="dtbl">
        <thead>
            <tr><th>ID</th><th>Pelanggan</th><th>Produk / Jenis</th><th>Jml</th><th>Tanggal</th><th>Detail</th><th>Aksi</th></tr>
        </thead>
        <tbody>
        <?php foreach ($pending_list as $po): ?>
        <tr>
            <td class="t-sub">#<?= $po['id_preorder'] ?></td>
            <td>
                <div class="t-name"><?= htmlspecialchars($po['nama_user'] ?? '—') ?></div>
                <div class="t-sub"><?= htmlspecialchars($po['no_telepon'] ?? $po['wa_pelanggan'] ?? '') ?></div>
            </td>
            <td>
                <div class="t-name"><?= htmlspecialchars($po['nama_produk']) ?></div>
                <div class="t-sub"><?= $po['jenis_preorder'] === 'custom' ? 'Custom' : 'Katalog' ?>
                    <?php if ($po['jenis_produk']): ?> · <?= htmlspecialchars($po['jenis_produk']) ?><?php endif; ?>
                </div>
            </td>
            <td class="t-sub"><?= (int)$po['jumlah'] ?></td>
            <td class="t-sub"><?= date('d M Y', strtotime($po['tanggal'])) ?></td>
            <td>
                <button type="button" class="btn-sm"
                    onclick="openDetail(<?= htmlspecialchars(json_encode($po), ENT_QUOTES) ?>)"
                    style="background:#fff8f6;color:#7a2e22;border:1px solid #fce9e3;padding:.25rem .625rem;font-size:.7rem;border-radius:.5rem;cursor:pointer;">
                    <i class="fas fa-eye"></i> Lihat
                </button>
            </td>
            <td style="display:flex;gap:.25rem;align-items:center;">
                <form method="POST" action="preorder.php?sub=konfirmasi">
                    <input type="hidden" name="id_preorder" value="<?= $po['id_preorder'] ?>">
                    <input type="hidden" name="aksi_status" value="1">
                    <input type="hidden" name="status" value="dikonfirmasi">
                    <button type="submit" class="btn-sm" style="background:#f0fdf4;color:#16a34a;border:1px solid #86efac;padding:.25rem .5rem;font-size:.7rem;border-radius:.5rem;cursor:pointer;">
                        <i class="fas fa-check"></i> Konfirmasi
                    </button>
                </form>
                <form method="POST" action="preorder.php?sub=konfirmasi">
                    <input type="hidden" name="id_preorder" value="<?= $po['id_preorder'] ?>">
                    <input type="hidden" name="aksi_status" value="1">
                    <input type="hidden" name="status" value="ditolak">
                    <button type="submit" class="btn-sm" style="background:#fef2f2;color:#dc2626;border:1px solid #fca5a5;padding:.25rem .5rem;font-size:.7rem;border-radius:.5rem;cursor:pointer;">
                        <i class="fas fa-times"></i> Tolak
                    </button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<?php endif; ?>

<!-- MODAL DETAIL -->
<div class="po-modal-overlay" id="poModalOverlay" onclick="closeDetailOutside(event)">
    <div class="po-modal" id="poModal">
        <div class="po-modal-header">
            <h3 id="modalTitle">Detail Pre Order</h3>
            <button class="po-modal-close" onclick="closeDetail()"><i class="fas fa-times"></i></button>
        </div>
        <div class="po-detail-grid" id="modalBody"></div>
        <div class="po-modal-actions" id="modalActions"></div>
    </div>
</div>

<script>
function openDetail(po) {
    var body = document.getElementById('modalBody');
    var actions = document.getElementById('modalActions');
    document.getElementById('modalTitle').textContent = 'Detail Pre Order #' + po.id_preorder;

    var statusLabel = {
        pending:'Pending', dikonfirmasi:'Dikonfirmasi', diproses:'Diproses',
        selesai:'Selesai', ditolak:'Ditolak', dibatalkan:'Dibatalkan'
    };

    function row(label, value, full) {
        var val = (value && value !== '—' && value !== '') ? escHtml(value) : '<span class="empty">—</span>';
        return '<div class="po-detail-item' + (full ? ' full' : '') + '">'
             + '<span class="po-detail-label">' + label + '</span>'
             + '<span class="po-detail-value">' + val + '</span></div>';
    }
    function escHtml(s) {
        return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    var tglPesan = po.tanggal ? po.tanggal.substring(0,10) : '—';
    var tglButuh = po.tanggal_dibutuhkan || '—';

    var html = ''
        + row('Pelanggan', po.nama_user)
        + row('No. WhatsApp', po.no_telepon || po.wa_pelanggan)
        + row('Jenis Pre Order', po.jenis_preorder === 'custom' ? 'Custom / Desain Baru' : 'Produk dari Katalog')
        + row('Produk', po.nama_produk)
        + row('Jenis Produk', po.jenis_produk)
        + row('Jumlah', po.jumlah + ' pcs')
        + row('Ukuran', po.ukuran)
        + row('Tanggal Pesan', tglPesan)
        + row('Tanggal Dibutuhkan', tglButuh)
        + row('Status', statusLabel[po.status] || po.status)
        + row('Permintaan Custom', po.permintaan_custom, true)
        + row('Catatan Tambahan', po.catatan, true);

    if (po.file_referensi) {
        html += '<div class="po-detail-item full">'
              + '<span class="po-detail-label">Gambar Referensi</span>'
              + '<div class="po-ref-img-wrap">'
              + '<img src="../assets/uploads/preorder/' + escHtml(po.file_referensi) + '" class="po-ref-img" alt="Referensi">'
              + '</div></div>';
    } else {
        html += row('Gambar Referensi', '', true);
    }

    body.innerHTML = html;

    // Tombol ubah status
    var validStatus = {dikonfirmasi:'Konfirmasi',diproses:'Diproses',selesai:'Selesai',ditolak:'Tolak',dibatalkan:'Batalkan'};
    var styles = {
        dikonfirmasi:'background:#f0fdf4;color:#16a34a;border:1px solid #86efac;',
        diproses:'background:#fffbeb;color:#d97706;border:1px solid #fde68a;',
        selesai:'background:#eff6ff;color:#2563eb;border:1px solid #bfdbfe;',
        ditolak:'background:#fef2f2;color:#dc2626;border:1px solid #fca5a5;',
        dibatalkan:'background:#f3f4f6;color:#6b7280;border:1px solid #e5e7eb;',
    };
    var actHtml = '<strong style="font-size:.8rem;color:#374151;align-self:center;">Ubah Status:</strong>';
    for (var s in validStatus) {
        if (s === po.status) continue;
        actHtml += '<form method="POST" action="preorder.php?sub=' + (document.querySelector('a[style*="gradient"]') ? 'data' : 'data') + '">'
                 + '<input type="hidden" name="id_preorder" value="' + po.id_preorder + '">'
                 + '<input type="hidden" name="aksi_status" value="1">'
                 + '<input type="hidden" name="status" value="' + s + '">'
                 + '<button type="submit" class="btn-sm" style="' + styles[s] + 'padding:.3rem .75rem;font-size:.75rem;border-radius:.5rem;cursor:pointer;">'
                 + validStatus[s] + '</button></form>';
    }
    actions.innerHTML = actHtml;

    document.getElementById('poModalOverlay').classList.add('open');
}

function closeDetail() {
    document.getElementById('poModalOverlay').classList.remove('open');
}
function closeDetailOutside(e) {
    if (e.target === document.getElementById('poModalOverlay')) closeDetail();
}
</script>

<?php require_once 'inc/footer.php'; ?>
