<?php
session_start();
require_once '../config/db.php';
require_once '../config/midtrans.php';
require_once '../config/midtrans_helper.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'pelanggan') {
    header('Location: ../login.php'); exit;
}
$user = $_SESSION['user'];
$tab  = in_array($_GET['tab'] ?? 'profil', ['profil','pesanan','preorder','alamat'])
      ? ($_GET['tab'] ?? 'profil') : 'profil';

// AJAX: Detail pesanan
if (($_GET['action'] ?? '') === 'order_detail' && !empty($_GET['id'])) {
    header('Content-Type: application/json');
    $id_ord = intval($_GET['id']);
    $ord_s = $pdo->prepare("SELECT * FROM penjualan WHERE id_penjualan=? AND id_user=?");
    $ord_s->execute([$id_ord, $user['id_user']]);
    $ord = $ord_s->fetch(PDO::FETCH_ASSOC);
    if (!$ord) { echo json_encode(['error' => 'Pesanan tidak ditemukan']); exit; }
    $items_s = $pdo->prepare(
        "SELECT dp.jumlah, dp.subtotal, pr.nama_produk, pr.harga, pr.gambar_produk
         FROM detail_penjualan dp
         JOIN produk pr ON dp.id_produk = pr.id_produk
         WHERE dp.id_penjualan = ?"
    );
    $items_s->execute([$id_ord]);
    echo json_encode(['order' => $ord, 'items' => $items_s->fetchAll(PDO::FETCH_ASSOC)]);
    exit;
}

// Auto-migrate tabel alamat
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS alamat_pelanggan (
        id_alamat     INT AUTO_INCREMENT PRIMARY KEY,
        id_user       INT NOT NULL,
        label         VARCHAR(50) NOT NULL DEFAULT 'Rumah',
        nama_penerima VARCHAR(255) NOT NULL,
        no_telepon    VARCHAR(20) NOT NULL,
        alamat        TEXT NOT NULL,
        kota          VARCHAR(100) NOT NULL,
        kode_pos      VARCHAR(10) DEFAULT NULL,
        is_utama      TINYINT(1) NOT NULL DEFAULT 0,
        lat           DECIMAL(11,8) DEFAULT NULL,
        lng           DECIMAL(11,8) DEFAULT NULL,
        dibuat        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    )");
} catch (PDOException $e) {}
// Tambah kolom lat/lng jika tabel sudah ada tapi kolom belum
foreach (["ALTER TABLE alamat_pelanggan ADD COLUMN lat DECIMAL(11,8) DEFAULT NULL",
          "ALTER TABLE alamat_pelanggan ADD COLUMN lng DECIMAL(11,8) DEFAULT NULL"] as $q) {
    try { $pdo->exec($q); } catch (PDOException $e) {}
}

// ---- AKSI ALAMAT ----
$alamat_ok = ''; $alamat_err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi = $_POST['aksi_alamat'] ?? '';

    // TAMBAH / EDIT ALAMAT
    if (in_array($aksi, ['tambah_alamat', 'edit_alamat'])) {
        $label    = trim($_POST['label'] ?? 'Rumah');
        $nama_p   = trim($_POST['nama_penerima'] ?? '');
        $no_telp  = trim($_POST['no_telepon'] ?? '');
        $al       = trim($_POST['alamat'] ?? '');
        $kota     = trim($_POST['kota'] ?? '');
        $kodepos  = trim($_POST['kode_pos'] ?? '');

        $lat = !empty($_POST['map_lat']) ? floatval($_POST['map_lat']) : null;
        $lng = !empty($_POST['map_lng']) ? floatval($_POST['map_lng']) : null;

        if (!$nama_p || !$no_telp || !$al || !$kota) {
            $alamat_err = 'Nama penerima, nomor telepon, alamat, dan kota wajib diisi.';
            $tab = 'alamat';
        } else {
            if ($aksi === 'tambah_alamat') {
                $jml = $pdo->prepare("SELECT COUNT(*) FROM alamat_pelanggan WHERE id_user=?");
                $jml->execute([$user['id_user']]);
                $is_utama = ($jml->fetchColumn() == 0) ? 1 : 0;
                $pdo->prepare("INSERT INTO alamat_pelanggan
                    (id_user,label,nama_penerima,no_telepon,alamat,kota,kode_pos,is_utama,lat,lng)
                    VALUES (?,?,?,?,?,?,?,?,?,?)")
                    ->execute([$user['id_user'],$label,$nama_p,$no_telp,$al,$kota,$kodepos,$is_utama,$lat,$lng]);
                $alamat_ok = 'Alamat berhasil ditambahkan.';
            } else {
                $id_al = intval($_POST['id_alamat'] ?? 0);
                $pdo->prepare("UPDATE alamat_pelanggan
                    SET label=?,nama_penerima=?,no_telepon=?,alamat=?,kota=?,kode_pos=?,lat=?,lng=?
                    WHERE id_alamat=? AND id_user=?")
                    ->execute([$label,$nama_p,$no_telp,$al,$kota,$kodepos,$lat,$lng,$id_al,$user['id_user']]);
                $alamat_ok = 'Alamat berhasil diperbarui.';
            }
            $tab = 'alamat';
        }
    }

    // SET UTAMA
    if ($aksi === 'set_utama') {
        $id_al = intval($_POST['id_alamat'] ?? 0);
        $pdo->prepare("UPDATE alamat_pelanggan SET is_utama=0 WHERE id_user=?")->execute([$user['id_user']]);
        $pdo->prepare("UPDATE alamat_pelanggan SET is_utama=1 WHERE id_alamat=? AND id_user=?")->execute([$id_al,$user['id_user']]);
        $alamat_ok = 'Alamat utama diperbarui.';
        $tab = 'alamat';
    }

    // HAPUS ALAMAT
    if ($aksi === 'hapus_alamat') {
        $id_al = intval($_POST['id_alamat'] ?? 0);
        $pdo->prepare("DELETE FROM alamat_pelanggan WHERE id_alamat=? AND id_user=?")->execute([$id_al,$user['id_user']]);
        // Jika hapus alamat utama, set utama ke yang pertama tersisa
        $sisa = $pdo->prepare("SELECT id_alamat FROM alamat_pelanggan WHERE id_user=? ORDER BY dibuat ASC LIMIT 1");
        $sisa->execute([$user['id_user']]);
        $sisa_id = $sisa->fetchColumn();
        if ($sisa_id) {
            $pdo->prepare("UPDATE alamat_pelanggan SET is_utama=1 WHERE id_alamat=?")->execute([$sisa_id]);
        }
        $alamat_ok = 'Alamat dihapus.';
        $tab = 'alamat';
    }

    // UBAH PASSWORD
    if (isset($_POST['ubah_pw'])) {
        $pw_lama = $_POST['password_lama'] ?? '';
        $pw_baru = trim($_POST['password_baru'] ?? '');
        $cur = $pdo->prepare("SELECT password FROM user WHERE id_user = ?");
        $cur->execute([$user['id_user']]);
        $cur_pw = $cur->fetchColumn();
        if ($cur_pw !== $pw_lama) {
            $alamat_err = 'Password lama tidak sesuai.';
        } elseif (strlen($pw_baru) < 4) {
            $alamat_err = 'Password baru minimal 4 karakter.';
        } else {
            $pdo->prepare("UPDATE user SET password=? WHERE id_user=?")->execute([$pw_baru, $user['id_user']]);
            $alamat_ok = 'Password berhasil diubah!';
        }
        $tab = 'profil';
    }

    // EDIT PROFIL
    if (isset($_POST['edit_profil'])) {
        $nama_baru  = trim($_POST['nama'] ?? '');
        $uname_baru = trim($_POST['username'] ?? '');
        $telp_baru  = trim($_POST['no_telepon'] ?? '');
        if (empty($nama_baru) || empty($uname_baru)) {
            $alamat_err = 'Nama dan username wajib diisi.';
        } else {
            $chk = $pdo->prepare("SELECT COUNT(*) FROM user WHERE username=? AND id_user!=?");
            $chk->execute([$uname_baru, $user['id_user']]);
            if ($chk->fetchColumn() > 0) {
                $alamat_err = 'Username sudah digunakan oleh akun lain.';
            } else {
                $pdo->prepare("UPDATE user SET nama=?, username=?, no_telepon=? WHERE id_user=?")
                    ->execute([$nama_baru, $uname_baru, $telp_baru, $user['id_user']]);
                $_SESSION['user']['nama']     = $nama_baru;
                $_SESSION['user']['username'] = $uname_baru;
                $alamat_ok = 'Profil berhasil diperbarui!';
            }
        }
        $tab = 'profil';
    }
}

// Data profil
$profil = $pdo->prepare("SELECT * FROM user WHERE id_user = ?");
$profil->execute([$user['id_user']]);
$profil = $profil->fetch();

// Daftar alamat
$alamat_list = $pdo->prepare("SELECT * FROM alamat_pelanggan WHERE id_user=? ORDER BY is_utama DESC, dibuat ASC");
$alamat_list->execute([$user['id_user']]);
$alamat_list = $alamat_list->fetchAll();

// Alamat yang sedang diedit (jika ada)
$edit_alamat = null;
if (isset($_GET['edit_alamat'])) {
    $ea = $pdo->prepare("SELECT * FROM alamat_pelanggan WHERE id_alamat=? AND id_user=?");
    $ea->execute([intval($_GET['edit_alamat']), $user['id_user']]);
    $edit_alamat = $ea->fetch();
    if ($edit_alamat) $tab = 'alamat';
}

// Riwayat pesanan
$riwayat = $pdo->prepare(
    "SELECT p.id_penjualan, p.tgl_penjualan, p.total_harga, p.status,
            p.midtrans_order_id, p.ekspedisi, p.alamat_pengiriman, p.kota,
            GROUP_CONCAT(pr.nama_produk ORDER BY pr.nama_produk SEPARATOR ', ') AS items
     FROM penjualan p
     LEFT JOIN detail_penjualan dp ON p.id_penjualan = dp.id_penjualan
     LEFT JOIN produk pr ON dp.id_produk = pr.id_produk
     WHERE p.id_user = ?
     GROUP BY p.id_penjualan ORDER BY p.tgl_penjualan DESC"
);
$riwayat->execute([$user['id_user']]);
$riwayat_list = $riwayat->fetchAll();

// Auto-check status Midtrans untuk order yang masih menunggu pembayaran
foreach ($riwayat_list as &$o) {
    if ($o['status'] === 'menunggu_pembayaran' && !empty($o['midtrans_order_id'])) {
        $mt      = midtrans_get_status($o['midtrans_order_id']);
        $mt_trx  = $mt['transaction_status'] ?? '';
        $mt_fr   = $mt['fraud_status'] ?? '';
        $new_st  = null;
        if (in_array($mt_trx, ['settlement', 'capture'])) {
            if (!($mt_trx === 'capture' && $mt_fr === 'challenge')) {
                $new_st = 'pending';
            }
        } elseif (in_array($mt_trx, ['deny', 'cancel', 'expire', 'failure'])) {
            $new_st = 'dibatalkan';
            $itms = $pdo->prepare("SELECT id_produk, jumlah FROM detail_penjualan WHERE id_penjualan=?");
            $itms->execute([$o['id_penjualan']]);
            foreach ($itms->fetchAll() as $it) {
                $pdo->prepare("UPDATE produk SET stok = stok + ? WHERE id_produk = ?")
                    ->execute([$it['jumlah'], $it['id_produk']]);
            }
        }
        if ($new_st) {
            $pdo->prepare("UPDATE penjualan SET status=?, payment_status=? WHERE id_penjualan=?")
                ->execute([$new_st, $mt_trx, $o['id_penjualan']]);
            $o['status'] = $new_st;
        }
    }
}
unset($o);

// Riwayat preorder
$po_stmt = $pdo->prepare(
    "SELECT po.id_preorder, po.tanggal, po.status,
            COALESCE(p.nama_produk, po.jenis_produk, 'Custom') AS nama_produk,
            COALESCE(p.harga, 0) AS harga
     FROM preorder po
     LEFT JOIN produk p ON po.id_produk = p.id_produk
     WHERE po.id_user = ? ORDER BY po.tanggal DESC"
);
$po_stmt->execute([$user['id_user']]);
$preorder_list = $po_stmt->fetchAll();

$status_color = [
    'menunggu_pembayaran' => ['#fffbeb','#d97706'],
    'pending'      => ['#fffbeb','#d97706'],
    'dikonfirmasi' => ['#f0fdf4','#15803d'],
    'diproses'     => ['#eff6ff','#2563eb'],
    'dikirim'      => ['#f0f9ff','#0369a1'],
    'selesai'      => ['#f0fdf4','#15803d'],
    'ditolak'      => ['#fef2f2','#dc2626'],
    'dibatalkan'   => ['#fef2f2','#dc2626'],
];
$status_label = [
    'menunggu_pembayaran' => 'Menunggu Pembayaran',
    'pending'    => 'Menunggu Diproses',
    'diproses'   => 'Diproses',
    'dikirim'    => 'Dikirim',
    'selesai'    => 'Selesai',
    'dibatalkan' => 'Dibatalkan',
];

$siteRoot   = '../';
$activePage = '';
$pageTitle  = 'Profil Saya';
require_once '../includes/header.php';
?>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<style>
.prof-wrap{max-width:760px;margin:0 auto;padding:2rem 1rem 3rem;}
.prof-tabs{display:flex;gap:.375rem;margin-bottom:1.5rem;background:white;padding:.375rem;border-radius:.875rem;border:1px solid #fce9e3;box-shadow:0 1px 4px rgba(0,0,0,.04);}
.ptab{flex:1;padding:.5rem;border:none;background:none;border-radius:.625rem;font-size:.8125rem;font-weight:600;color:#9ca3af;cursor:pointer;transition:all .15s;}
.ptab.active{background:linear-gradient(135deg,#953b22,#9e5848);color:white;}
.ptab:hover:not(.active){background:#fff8f6;color:#953b22;}
.pcard{background:white;border-radius:1rem;border:1px solid #fce9e3;padding:1.25rem 1.5rem;box-shadow:0 1px 4px rgba(0,0,0,.05);margin-bottom:1rem;}
.pcard h3{font-weight:700;color:#7a2e1a;font-size:.9375rem;margin:0 0 1rem;}
.prof-avatar-lg{width:72px;height:72px;border-radius:50%;background:linear-gradient(135deg,#953b22,#9e5848);display:flex;align-items:center;justify-content:center;color:white;font-size:1.75rem;font-weight:700;margin:0 auto .875rem;}
.info-row{display:flex;justify-content:space-between;align-items:center;padding:.5rem 0;border-bottom:1px solid #fce9e3;font-size:.8125rem;}
.info-row:last-child{border-bottom:none;}
.info-lbl{color:#9ca3af;}
.info-val{font-weight:600;color:#1f2937;}
.ord-item{border:1px solid #fce9e3;border-radius:.875rem;padding:.875rem 1rem;margin-bottom:.75rem;}
.ord-num{font-size:.7rem;color:#9ca3af;margin-bottom:.25rem;}
.ord-items{font-size:.8125rem;color:#374151;margin-bottom:.375rem;font-weight:500;}
.ord-footer{display:flex;justify-content:space-between;align-items:center;}
.ord-total{font-weight:700;color:#953b22;}
.ord-date{font-size:.7rem;color:#9ca3af;}
.badge-status{font-size:.65rem;font-weight:700;padding:.2rem .625rem;border-radius:9999px;}
.btn-lanjut-bayar{display:flex;align-items:center;justify-content:center;gap:.4rem;width:100%;margin-top:.625rem;padding:.5rem .875rem;border:none;border-radius:.625rem;background:linear-gradient(135deg,#953b22,#9e5848);color:white;font-size:.8rem;font-weight:700;cursor:pointer;font-family:inherit;transition:opacity .15s;}
.btn-lanjut-bayar:hover{opacity:.88;}
.btn-lanjut-bayar:disabled{opacity:.6;cursor:not-allowed;}
.btn-detail-ord{display:flex;align-items:center;justify-content:center;gap:.4rem;width:100%;margin-top:.5rem;padding:.4rem .875rem;border:1.5px solid #fce9e3;border-radius:.625rem;background:transparent;color:#953b22;font-size:.78rem;font-weight:600;cursor:pointer;font-family:inherit;transition:all .15s;}
.btn-detail-ord:hover{background:#fff8f6;}
.ord-modal-bg{display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:9999;align-items:center;justify-content:center;padding:1rem;}
.ord-modal-bg.active{display:flex;}
.ord-modal{background:white;border-radius:1.25rem;width:100%;max-width:480px;max-height:88vh;overflow-y:auto;box-shadow:0 8px 40px rgba(0,0,0,.18);}
.ord-modal-head{display:flex;justify-content:space-between;align-items:center;padding:1.125rem 1.5rem 1rem;border-bottom:1px solid #fce9e3;position:sticky;top:0;background:white;z-index:1;}
.ord-modal-head h3{margin:0;font-size:1rem;font-weight:700;color:#1f2937;}
.ord-modal-close{background:none;border:none;font-size:1.375rem;cursor:pointer;color:#9ca3af;line-height:1;padding:.25rem .5rem;}
.ord-modal-close:hover{color:#1f2937;}
.ord-modal-body{padding:1.25rem 1.5rem 1.5rem;}
.detail-section{margin-bottom:1.125rem;}
.detail-section-title{font-size:.65rem;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:.06em;margin-bottom:.5rem;}
.detail-item-row{display:flex;gap:.75rem;align-items:flex-start;padding:.5rem 0;border-bottom:1px solid #f9fafb;}
.detail-item-row:last-child{border-bottom:none;}
.detail-item-img{width:46px;height:46px;border-radius:.5rem;object-fit:cover;background:#f3f4f6;flex-shrink:0;}
.detail-item-info{flex:1;min-width:0;}
.detail-item-name{font-size:.8125rem;font-weight:600;color:#1f2937;line-height:1.35;}
.detail-item-sub{font-size:.72rem;color:#9ca3af;margin-top:.15rem;}
.detail-item-price{font-size:.8125rem;font-weight:700;color:#953b22;white-space:nowrap;padding-top:.1rem;}
.detail-info-row{display:flex;justify-content:space-between;gap:.5rem;padding:.3rem 0;font-size:.8125rem;border-bottom:1px solid #f9fafb;}
.detail-info-row:last-child{border-bottom:none;}
.detail-info-row .lbl{color:#9ca3af;flex-shrink:0;}
.detail-info-row .val{font-weight:600;color:#1f2937;text-align:right;max-width:62%;word-break:break-word;}
.detail-total-row{display:flex;justify-content:space-between;align-items:center;padding:.875rem 0 0;border-top:2px solid #fce9e3;font-weight:700;font-size:.9375rem;}
.detail-total-row .val{color:#953b22;}
.empty-state{text-align:center;padding:2.5rem 1rem;color:#dfb0a2;}
.fg-p{margin-bottom:.875rem;}
.fg-p label{display:block;font-size:.75rem;font-weight:600;color:#6b7280;margin-bottom:.375rem;}
.fg-p input,.fg-p select,.fg-p textarea{width:100%;padding:.625rem .875rem;border:1.5px solid #f5d4cb;border-radius:.625rem;font-size:.8125rem;font-family:inherit;outline:none;background:#fff8f6;transition:all .15s;box-sizing:border-box;}
.fg-p input:focus,.fg-p select:focus,.fg-p textarea:focus{border-color:#9e5848;box-shadow:0 0 0 3px rgba(149,59,34,.1);}
.alert-ok{background:#f0fdf4;border:1px solid #bbf7d0;border-radius:.75rem;padding:.625rem 1rem;font-size:.8125rem;color:#15803d;margin-bottom:1rem;}
.alert-err{background:#fef2f2;border:1px solid #fecaca;border-radius:.75rem;padding:.625rem 1rem;font-size:.8125rem;color:#dc2626;margin-bottom:1rem;}
.btn-pink{width:100%;padding:.7rem;border:none;border-radius:.75rem;background:linear-gradient(135deg,#953b22,#9e5848);color:white;font-weight:700;font-size:.875rem;cursor:pointer;transition:opacity .15s;}
.btn-pink:hover{opacity:.9;}
/* Alamat card */
.addr-card{border:1.5px solid #fce9e3;border-radius:.875rem;padding:1rem 1.125rem;margin-bottom:.75rem;position:relative;background:white;}
.addr-card.utama{border-color:#953b22;background:#fff8f6;}
.addr-label{display:inline-flex;align-items:center;gap:.3rem;font-size:.65rem;font-weight:700;padding:.2rem .625rem;border-radius:9999px;background:#fce9e3;color:#953b22;margin-bottom:.5rem;}
.addr-label.utama-badge{background:#953b22;color:white;}
.addr-nama{font-size:.875rem;font-weight:700;color:#1f2937;}
.addr-telp{font-size:.75rem;color:#9ca3af;margin:.1rem 0;}
.addr-text{font-size:.8125rem;color:#374151;line-height:1.5;margin-top:.3rem;}
.addr-actions{display:flex;gap:.375rem;margin-top:.75rem;flex-wrap:wrap;}
.addr-btn{padding:.3rem .875rem;border-radius:9999px;font-size:.72rem;font-weight:600;border:1.5px solid;cursor:pointer;font-family:inherit;background:white;transition:all .15s;}
.addr-btn-edit{border-color:#f5d4cb;color:#953b22;}
.addr-btn-edit:hover{background:#fff8f6;}
.addr-btn-utama{border-color:#86efac;color:#15803d;}
.addr-btn-utama:hover{background:#f0fdf4;}
.addr-btn-hapus{border-color:#fca5a5;color:#dc2626;}
.addr-btn-hapus:hover{background:#fef2f2;}
.fgrid2{display:grid;grid-template-columns:1fr 1fr;gap:.75rem;}
.btn-outline-pink{width:100%;padding:.625rem;border:1.5px solid #953b22;border-radius:.75rem;background:white;color:#953b22;font-weight:700;font-size:.875rem;cursor:pointer;transition:all .15s;margin-top:.5rem;}
.btn-outline-pink:hover{background:#fff8f6;}
/* MAP PICKER */
.map-modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:9999;align-items:center;justify-content:center;padding:1rem;}
.map-modal-overlay.open{display:flex;}
.map-modal{background:white;border-radius:1.25rem;width:100%;max-width:680px;max-height:90vh;display:flex;flex-direction:column;overflow:hidden;box-shadow:0 20px 60px rgba(0,0,0,.25);}
.map-modal-head{padding:.875rem 1.25rem;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid #fce9e3;flex-shrink:0;}
.map-modal-head h3{margin:0;font-size:.9375rem;font-weight:700;color:#1a0805;}
.map-modal-close{background:none;border:none;font-size:1.1rem;color:#9ca3af;cursor:pointer;}
.map-search-wrap{padding:.625rem 1rem;border-bottom:1px solid #fce9e3;flex-shrink:0;display:flex;gap:.5rem;}
.map-search-input{flex:1;padding:.5rem .875rem;border:1.5px solid #f5d4cb;border-radius:.625rem;font-size:.8125rem;font-family:inherit;outline:none;}
.map-search-input:focus{border-color:#9e5848;}
.map-search-btn{padding:.5rem 1rem;background:linear-gradient(135deg,#953b22,#9e5848);color:white;border:none;border-radius:.625rem;font-size:.8125rem;font-weight:600;cursor:pointer;}
.map-container{flex:1;min-height:320px;}
.map-footer{padding:.75rem 1.25rem;border-top:1px solid #fce9e3;flex-shrink:0;display:flex;align-items:center;gap:.75rem;flex-wrap:wrap;}
.map-result-text{flex:1;font-size:.75rem;color:#6b7280;line-height:1.4;}
.map-result-text strong{display:block;color:#1f2937;font-size:.8125rem;}
.btn-map-confirm{padding:.5rem 1.25rem;background:linear-gradient(135deg,#953b22,#9e5848);color:white;border:none;border-radius:.625rem;font-size:.8125rem;font-weight:700;cursor:pointer;}
.btn-map-myloc{padding:.5rem .875rem;background:white;color:#953b22;border:1.5px solid #f5d4cb;border-radius:.625rem;font-size:.8125rem;font-weight:600;cursor:pointer;}
.btn-peta{display:inline-flex;align-items:center;gap:.375rem;padding:.35rem .875rem;border:1.5px solid #f5d4cb;border-radius:9999px;background:#fff8f6;color:#953b22;font-size:.75rem;font-weight:600;cursor:pointer;font-family:inherit;transition:all .15s;}
.btn-peta:hover{border-color:#953b22;}
.addr-coords{font-size:.7rem;color:#9ca3af;margin-top:.2rem;display:flex;align-items:center;gap:.3rem;}
.addr-map-thumb{margin-top:.5rem;border-radius:.5rem;overflow:hidden;border:1px solid #fce9e3;height:100px;cursor:pointer;}
.map-search-results{position:absolute;top:100%;left:0;right:0;background:white;border:1px solid #fce9e3;border-radius:.625rem;margin-top:.25rem;box-shadow:0 4px 12px rgba(0,0,0,.1);z-index:10001;max-height:200px;overflow-y:auto;}
.map-search-result-item{padding:.625rem 1rem;font-size:.8rem;cursor:pointer;color:#374151;border-bottom:1px solid #fce9e3;}
.map-search-result-item:last-child{border-bottom:none;}
.map-search-result-item:hover{background:#fff8f6;color:#953b22;}
</style>

<div class="prof-wrap">

    <div class="pcard" style="text-align:center;padding:1.5rem;">
        <div class="prof-avatar-lg"><?= mb_strtoupper(mb_substr($profil['nama'],0,1)) ?></div>
        <div style="font-weight:700;font-size:1.125rem;color:#1f2937;"><?= htmlspecialchars($profil['nama']) ?></div>
        <div style="font-size:.8125rem;color:#9ca3af;margin-top:.2rem;">@<?= htmlspecialchars($profil['username']) ?></div>
    </div>

    <div class="prof-tabs">
        <button class="ptab <?= $tab==='profil'  ?'active':'' ?>" onclick="switchTab('profil')"><i class="fas fa-user"></i> Profil</button>
        <button class="ptab <?= $tab==='alamat'  ?'active':'' ?>" onclick="switchTab('alamat')"><i class="fas fa-map-marker-alt"></i> Alamat (<?= count($alamat_list) ?>)</button>
        <button class="ptab <?= $tab==='pesanan' ?'active':'' ?>" onclick="switchTab('pesanan')"><i class="fas fa-box"></i> Pesanan (<?= count($riwayat_list) ?>)</button>
        <button class="ptab <?= $tab==='preorder'?'active':'' ?>" onclick="switchTab('preorder')"><i class="fas fa-clipboard-list"></i> Pre Order (<?= count($preorder_list) ?>)</button>
    </div>

    <?php if ($alamat_ok): ?><div class="alert-ok"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($alamat_ok) ?></div><?php endif; ?>
    <?php if ($alamat_err): ?><div class="alert-err"><i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($alamat_err) ?></div><?php endif; ?>

    <!-- TAB: PROFIL -->
    <div id="tab-profil" class="tab-content" style="<?= $tab!=='profil'?'display:none':'' ?>">
        <div class="pcard">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;">
                <h3 style="margin:0;">Informasi Akun</h3>
                <button type="button" id="btnEditProfil" onclick="toggleEditProfil()"
                    style="display:inline-flex;align-items:center;gap:.375rem;padding:.35rem .875rem;border:1.5px solid #f5d4cb;border-radius:9999px;background:#fff8f6;color:#953b22;font-size:.75rem;font-weight:600;cursor:pointer;font-family:inherit;">
                    <i class="fas fa-pen"></i> Edit Profil
                </button>
            </div>

            <!-- INFO READ-ONLY -->
            <div id="infoAkun">
                <?php foreach ([
                    ['Nama Lengkap', $profil['nama']],
                    ['Username',     '@'.$profil['username']],
                    ['No. Telepon',  $profil['no_telepon'] ?: '-'],
                ] as [$l, $v]): ?>
                <div class="info-row">
                    <span class="info-lbl"><?= $l ?></span>
                    <span class="info-val"><?= htmlspecialchars($v) ?></span>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- FORM EDIT (tersembunyi) -->
            <div id="formEditProfil" style="display:none;margin-top:.75rem;border-top:1px solid #fce9e3;padding-top:1rem;">
                <form method="POST" action="profil.php?tab=profil">
                    <input type="hidden" name="edit_profil" value="1">
                    <div class="fg-p">
                        <label>Nama Lengkap <span style="color:#dc2626;">*</span></label>
                        <input type="text" name="nama" value="<?= htmlspecialchars($profil['nama']) ?>" required>
                    </div>
                    <div class="fg-p">
                        <label>Username <span style="color:#dc2626;">*</span></label>
                        <input type="text" name="username" value="<?= htmlspecialchars($profil['username']) ?>" required>
                    </div>
                    <div class="fg-p">
                        <label>No. Telepon</label>
                        <input type="text" name="no_telepon" value="<?= htmlspecialchars($profil['no_telepon'] ?? '') ?>" placeholder="08xx...">
                    </div>
                    <div style="display:flex;gap:.625rem;">
                        <button type="submit" class="btn-pink" style="flex:1;">Simpan Perubahan</button>
                        <button type="button" onclick="toggleEditProfil()"
                            style="flex:1;padding:.7rem;border:1.5px solid #e5e7eb;border-radius:.75rem;background:white;color:#6b7280;font-weight:700;font-size:.875rem;cursor:pointer;">
                            Batal
                        </button>
                    </div>
                </form>
            </div>
        </div>
        <div class="pcard">
            <h3>Ubah Password</h3>
            <form method="POST" action="profil.php?tab=profil">
                <input type="hidden" name="ubah_pw" value="1">
                <div class="fg-p"><label>Password Lama</label><input type="password" name="password_lama" placeholder="Masukkan password lama" required></div>
                <div class="fg-p"><label>Password Baru</label><input type="password" name="password_baru" placeholder="Minimal 4 karakter" required></div>
                <button type="submit" class="btn-pink">Simpan Password</button>
            </form>
        </div>
        <a href="logout.php" style="display:block;text-align:center;padding:.75rem;background:#fef2f2;color:#dc2626;border-radius:.875rem;font-weight:700;font-size:.875rem;text-decoration:none;border:1.5px solid #fecaca;"
           onclick="return confirm('Yakin ingin keluar?')">
            <i class="fas fa-sign-out-alt"></i> Keluar dari Akun
        </a>
    </div>

    <!-- TAB: ALAMAT -->
    <div id="tab-alamat" class="tab-content" style="<?= $tab!=='alamat'?'display:none':'' ?>">

        <!-- Daftar alamat -->
        <?php if (empty($alamat_list)): ?>
        <div class="pcard">
            <div class="empty-state">
                <div style="font-size:2.5rem;margin-bottom:.75rem;color:#953b22;"><i class="fas fa-map-marker-alt"></i></div>
                <div style="font-weight:600;color:#953b22;font-size:.9375rem;">Belum ada alamat</div>
                <div style="font-size:.8125rem;color:#9ca3af;margin-top:.25rem;">Tambahkan alamat pengiriman Anda di bawah ini.</div>
            </div>
        </div>
        <?php else: ?>
        <?php foreach ($alamat_list as $al): ?>
        <div class="addr-card <?= $al['is_utama'] ? 'utama' : '' ?>">
            <div style="display:flex;align-items:flex-start;gap:.5rem;flex-wrap:wrap;">
                <span class="addr-label <?= $al['is_utama'] ? 'utama-badge' : '' ?>">
                    <i class="fas fa-<?= $al['label']==='Kantor'?'briefcase':($al['label']==='Kos'?'home':'map-marker-alt') ?>"></i>
                    <?= htmlspecialchars($al['label']) ?>
                </span>
                <?php if ($al['is_utama']): ?>
                <span class="addr-label utama-badge"><i class="fas fa-star"></i> Utama</span>
                <?php endif; ?>
            </div>
            <div class="addr-nama"><?= htmlspecialchars($al['nama_penerima']) ?></div>
            <div class="addr-telp"><?= htmlspecialchars($al['no_telepon']) ?></div>
            <div class="addr-text">
                <?= htmlspecialchars($al['alamat']) ?>,
                <?= htmlspecialchars($al['kota']) ?>
                <?php if ($al['kode_pos']): ?><?= htmlspecialchars($al['kode_pos']) ?><?php endif; ?>
            </div>
            <?php if ($al['lat'] && $al['lng']): ?>
            <div class="addr-coords"><i class="fas fa-map-marker-alt" style="color:#953b22;"></i> <?= round($al['lat'],6) ?>, <?= round($al['lng'],6) ?>
                <a href="https://www.openstreetmap.org/?mlat=<?= $al['lat'] ?>&mlon=<?= $al['lng'] ?>#map=16/<?= $al['lat'] ?>/<?= $al['lng'] ?>" target="_blank" style="color:#953b22;font-weight:600;text-decoration:none;"><i class="fas fa-external-link-alt"></i> Lihat Peta</a>
            </div>
            <div class="addr-map-thumb" id="minimap-<?= $al['id_alamat'] ?>"
                 onclick="window.open('https://www.openstreetmap.org/?mlat=<?= $al['lat'] ?>&mlon=<?= $al['lng'] ?>#map=16/<?= $al['lat'] ?>/<?= $al['lng'] ?>','_blank')">
            </div>
            <?php endif; ?>
            <div class="addr-actions">
                <a href="profil.php?tab=alamat&edit_alamat=<?= $al['id_alamat'] ?>" class="addr-btn addr-btn-edit">
                    <i class="fas fa-pen"></i> Edit
                </a>
                <?php if (!$al['is_utama']): ?>
                <form method="POST" style="display:inline;">
                    <input type="hidden" name="aksi_alamat" value="set_utama">
                    <input type="hidden" name="id_alamat" value="<?= $al['id_alamat'] ?>">
                    <button type="submit" class="addr-btn addr-btn-utama"><i class="fas fa-star"></i> Jadikan Utama</button>
                </form>
                <form method="POST" style="display:inline;" onsubmit="return confirm('Hapus alamat ini?')">
                    <input type="hidden" name="aksi_alamat" value="hapus_alamat">
                    <input type="hidden" name="id_alamat" value="<?= $al['id_alamat'] ?>">
                    <button type="submit" class="addr-btn addr-btn-hapus"><i class="fas fa-trash"></i> Hapus</button>
                </form>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>

        <!-- Form Tambah / Edit Alamat -->
        <div class="pcard" id="formAlamat">
            <h3><?= $edit_alamat ? '<i class="fas fa-pen"></i> Edit Alamat' : '<i class="fas fa-plus"></i> Tambah Alamat Baru' ?></h3>
            <form method="POST" action="profil.php?tab=alamat<?= $edit_alamat ? '&edit_alamat='.$edit_alamat['id_alamat'] : '' ?>">
                <input type="hidden" name="aksi_alamat" value="<?= $edit_alamat ? 'edit_alamat' : 'tambah_alamat' ?>">
                <input type="hidden" name="map_lat" id="formLat" value="<?= htmlspecialchars($edit_alamat['lat'] ?? '') ?>">
                <input type="hidden" name="map_lng" id="formLng" value="<?= htmlspecialchars($edit_alamat['lng'] ?? '') ?>">
                <?php if ($edit_alamat): ?>
                <input type="hidden" name="id_alamat" value="<?= $edit_alamat['id_alamat'] ?>">
                <?php endif; ?>

                <!-- Tombol pilih di peta -->
                <div style="margin-bottom:1rem;">
                    <button type="button" class="btn-peta" onclick="bukaMapPicker()">
                        <i class="fas fa-map-marker-alt"></i> Pilih Titik di Peta
                    </button>
                    <span id="coordLabel" style="font-size:.72rem;color:#9ca3af;margin-left:.5rem;">
                        <?php if ($edit_alamat && $edit_alamat['lat']): ?>
                        <i class="fas fa-check-circle" style="color:#15803d;"></i> Titik dipilih: <?= round($edit_alamat['lat'],5) ?>, <?= round($edit_alamat['lng'],5) ?>
                        <?php else: ?>
                        Belum ada titik dipilih
                        <?php endif; ?>
                    </span>
                </div>

                <div class="fg-p">
                    <label>Label Alamat</label>
                    <select name="label">
                        <?php foreach (['Rumah','Kantor','Kos','Lainnya'] as $lbl): ?>
                        <option value="<?= $lbl ?>" <?= ($edit_alamat['label'] ?? 'Rumah') === $lbl ? 'selected' : '' ?>><?= $lbl ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="fgrid2">
                    <div class="fg-p">
                        <label>Nama Penerima <span style="color:#dc2626;">*</span></label>
                        <input type="text" name="nama_penerima" value="<?= htmlspecialchars($edit_alamat['nama_penerima'] ?? $profil['nama']) ?>" placeholder="Nama penerima paket" required>
                    </div>
                    <div class="fg-p">
                        <label>No. Telepon <span style="color:#dc2626;">*</span></label>
                        <input type="text" name="no_telepon" value="<?= htmlspecialchars($edit_alamat['no_telepon'] ?? $profil['no_telepon'] ?? '') ?>" placeholder="0812..." required>
                    </div>
                </div>
                <div class="fg-p">
                    <label>Alamat Lengkap <span style="color:#dc2626;">*</span></label>
                    <textarea name="alamat" rows="3" placeholder="Nama jalan, nomor rumah, RT/RW, kelurahan, kecamatan..." required><?= htmlspecialchars($edit_alamat['alamat'] ?? '') ?></textarea>
                </div>
                <div class="fgrid2">
                    <div class="fg-p">
                        <label>Kota / Kabupaten <span style="color:#dc2626;">*</span></label>
                        <input type="text" name="kota" value="<?= htmlspecialchars($edit_alamat['kota'] ?? '') ?>" placeholder="Nama kota" required>
                    </div>
                    <div class="fg-p">
                        <label>Kode Pos</label>
                        <input type="text" name="kode_pos" value="<?= htmlspecialchars($edit_alamat['kode_pos'] ?? '') ?>" placeholder="12345">
                    </div>
                </div>
                <button type="submit" class="btn-pink">
                    <i class="fas fa-<?= $edit_alamat ? 'save' : 'plus' ?>"></i>
                    <?= $edit_alamat ? 'Simpan Perubahan' : 'Tambah Alamat' ?>
                </button>
                <?php if ($edit_alamat): ?>
                <a href="profil.php?tab=alamat" class="btn-outline-pink" style="display:block;text-align:center;text-decoration:none;margin-top:.5rem;">Batal</a>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <!-- TAB: PESANAN -->
    <div id="tab-pesanan" class="tab-content" style="<?= $tab!=='pesanan'?'display:none':'' ?>">
        <?php if (empty($riwayat_list)): ?>
        <div class="empty-state">
            <div style="font-size:2.5rem;margin-bottom:.75rem;color:#953b22;"><i class="fas fa-box"></i></div>
            <div style="font-weight:600;color:#953b22;font-size:.9375rem;">Belum ada pesanan</div>
            <a href="../katalog.php" style="display:inline-block;margin-top:1rem;padding:.5rem 1.5rem;background:linear-gradient(135deg,#953b22,#9e5848);color:white;border-radius:9999px;font-weight:700;font-size:.875rem;text-decoration:none;">Mulai Belanja</a>
        </div>
        <?php else: ?>
        <?php foreach ($riwayat_list as $o):
            [$bg, $fg] = $status_color[$o['status']] ?? ['#f3f4f6','#6b7280'];
            $lbl = $status_label[$o['status']] ?? ucfirst($o['status']);
        ?>
        <div class="ord-item">
            <div class="ord-num">Pesanan #<?= str_pad($o['id_penjualan'],5,'0',STR_PAD_LEFT) ?> · <?= date('d M Y, H:i', strtotime($o['tgl_penjualan'])) ?></div>
            <div class="ord-items"><?= htmlspecialchars($o['items'] ?: '-') ?></div>
            <?php if ($o['ekspedisi'] || $o['kota']): ?>
            <div style="font-size:.72rem;color:#9ca3af;margin-bottom:.25rem;">
                <?php if ($o['ekspedisi']): ?><i class="fas fa-truck"></i> <?= htmlspecialchars($o['ekspedisi']) ?><?php endif; ?>
                <?php if ($o['kota']): ?> · <?= htmlspecialchars($o['kota']) ?><?php endif; ?>
            </div>
            <?php endif; ?>
            <div class="ord-footer">
                <span class="ord-total"><?= fmt_rp((int)$o['total_harga']) ?></span>
                <span class="badge-status" style="background:<?= $bg ?>;color:<?= $fg ?>;"><?= $lbl ?></span>
            </div>
            <button class="btn-detail-ord" onclick="lihatDetail(<?= $o['id_penjualan'] ?>)">
                <i class="fas fa-list-ul"></i> Lihat Detail Pesanan
            </button>
            <?php if ($o['status'] === 'menunggu_pembayaran'): ?>
            <button class="btn-lanjut-bayar" onclick="lanjutBayar(this, <?= $o['id_penjualan'] ?>)">
                <i class="fas fa-credit-card"></i> Lanjutkan Pembayaran
            </button>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- TAB: PRE ORDER -->
    <div id="tab-preorder" class="tab-content" style="<?= $tab!=='preorder'?'display:none':'' ?>">
        <?php if (empty($preorder_list)): ?>
        <div class="empty-state">
            <div style="font-size:2.5rem;margin-bottom:.75rem;color:#953b22;"><i class="fas fa-clipboard-list"></i></div>
            <div style="font-weight:600;color:#953b22;font-size:.9375rem;">Belum ada pre order</div>
            <a href="../preorder.php" style="display:inline-block;margin-top:1rem;padding:.5rem 1.5rem;background:linear-gradient(135deg,#953b22,#9e5848);color:white;border-radius:9999px;font-weight:700;font-size:.875rem;text-decoration:none;">Buat Pre Order</a>
        </div>
        <?php else: ?>
        <?php foreach ($preorder_list as $po):
            [$bg, $fg] = $status_color[$po['status']] ?? ['#f3f4f6','#6b7280'];
        ?>
        <div class="ord-item">
            <div class="ord-num">Pre Order #<?= $po['id_preorder'] ?> · <?= date('d M Y', strtotime($po['tanggal'])) ?></div>
            <div class="ord-items"><?= htmlspecialchars($po['nama_produk']) ?></div>
            <div class="ord-footer">
                <span class="ord-total"><?= fmt_rp((int)$po['harga']) ?></span>
                <span class="badge-status" style="background:<?= $bg ?>;color:<?= $fg ?>;"><?= ucfirst($po['status']) ?></span>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>

</div>

<!-- MAP PICKER MODAL -->
<div class="map-modal-overlay" id="mapModalOverlay">
    <div class="map-modal">
        <div class="map-modal-head">
            <h3><i class="fas fa-map-marker-alt" style="color:#953b22;"></i> Pilih Titik Pengiriman</h3>
            <button class="map-modal-close" onclick="tutupMapPicker()"><i class="fas fa-times"></i></button>
        </div>
        <div class="map-search-wrap" style="position:relative;">
            <input type="text" class="map-search-input" id="mapSearchInput" placeholder="Cari lokasi, jalan, kota...">
            <button class="map-search-btn" onclick="cariLokasi()"><i class="fas fa-search"></i> Cari</button>
            <div class="map-search-results" id="mapSearchResults" style="display:none;"></div>
        </div>
        <div class="map-container" id="mapContainer"></div>
        <div class="map-footer">
            <button class="btn-map-myloc" onclick="lokasiSaya()"><i class="fas fa-crosshairs"></i> Lokasi Saya</button>
            <div class="map-result-text" id="mapResultText">
                <strong id="mapResultAddr">Klik pada peta untuk memilih titik</strong>
                <span id="mapResultCoord"></span>
            </div>
            <button class="btn-map-confirm" id="btnKonfirmasiMap" onclick="konfirmasiLokasi()" disabled>
                <i class="fas fa-check"></i> Gunakan Lokasi Ini
            </button>
        </div>
    </div>
</div>

<script>
function toggleEditProfil() {
    var form = document.getElementById('formEditProfil');
    var info = document.getElementById('infoAkun');
    var btn  = document.getElementById('btnEditProfil');
    var open = form.style.display === 'none';
    form.style.display = open ? 'block' : 'none';
    info.style.display = open ? 'none'  : 'block';
    btn.innerHTML = open
        ? '<i class="fas fa-times"></i> Batal'
        : '<i class="fas fa-pen"></i> Edit Profil';
}

<?php if ($alamat_err && isset($_POST['edit_profil'])): ?>
// Buka form edit otomatis jika ada error validasi
window.addEventListener('DOMContentLoaded', function() { toggleEditProfil(); });
<?php endif; ?>

function switchTab(tab) {
    document.querySelectorAll('.tab-content').forEach(el => el.style.display = 'none');
    document.querySelectorAll('.ptab').forEach(b => b.classList.remove('active'));
    document.getElementById('tab-' + tab).style.display = 'block';
    var tabs = ['profil','alamat','pesanan','preorder'];
    document.querySelectorAll('.ptab')[tabs.indexOf(tab)].classList.add('active');
    history.replaceState(null,'','profil.php?tab='+tab);
}

// ============ MAP PICKER ============
var mapPicker = null, markerPicker = null;
var selectedLat = null, selectedLng = null, selectedAddr = '';

function bukaMapPicker() {
    document.getElementById('mapModalOverlay').classList.add('open');
    setTimeout(function() {
        if (!mapPicker) {
            // Inisialisasi peta pertama kali
            mapPicker = L.map('mapContainer').setView([-2.5, 118.0], 5);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
                maxZoom: 19
            }).addTo(mapPicker);

            // Jika sudah ada koordinat tersimpan
            var existLat = document.getElementById('formLat').value;
            var existLng = document.getElementById('formLng').value;
            if (existLat && existLng) {
                var ll = [parseFloat(existLat), parseFloat(existLng)];
                mapPicker.setView(ll, 16);
                markerPicker = L.marker(ll, {draggable: true}).addTo(mapPicker);
                markerPicker.on('dragend', function() {
                    var p = markerPicker.getLatLng();
                    reverseGeocode(p.lat, p.lng);
                });
                selectedLat = ll[0]; selectedLng = ll[1];
                document.getElementById('btnKonfirmasiMap').disabled = false;
            }

            mapPicker.on('click', function(e) {
                pasangMarker(e.latlng.lat, e.latlng.lng);
            });
        }
        mapPicker.invalidateSize();
    }, 150);
}

function tutupMapPicker() {
    document.getElementById('mapModalOverlay').classList.remove('open');
}

function pasangMarker(lat, lng) {
    if (markerPicker) {
        markerPicker.setLatLng([lat, lng]);
    } else {
        markerPicker = L.marker([lat, lng], {draggable: true}).addTo(mapPicker);
        markerPicker.on('dragend', function() {
            var p = markerPicker.getLatLng();
            reverseGeocode(p.lat, p.lng);
        });
    }
    reverseGeocode(lat, lng);
}

function reverseGeocode(lat, lng) {
    selectedLat = lat; selectedLng = lng;
    document.getElementById('mapResultAddr').textContent = 'Memuat alamat...';
    document.getElementById('mapResultCoord').textContent = lat.toFixed(6) + ', ' + lng.toFixed(6);
    document.getElementById('btnKonfirmasiMap').disabled = true;

    fetch('https://nominatim.openstreetmap.org/reverse?format=json&lat=' + lat + '&lon=' + lng + '&accept-language=id', {
        headers: { 'Accept-Language': 'id' }
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        var addr = data.address || {};
        var road   = addr.road || addr.pedestrian || addr.footway || '';
        var num    = addr.house_number || '';
        var sub    = addr.suburb || addr.neighbourhood || addr.quarter || '';
        var dist   = addr.city_district || addr.district || '';
        var kota   = addr.city || addr.town || addr.county || addr.regency || '';
        var pos    = addr.postcode || '';

        var baris1 = [road, num].filter(Boolean).join(' ');
        var baris2 = [sub, dist].filter(Boolean).join(', ');
        var full   = [baris1, baris2].filter(Boolean).join(', ');

        selectedAddr = { full: full, kota: kota, pos: pos };
        document.getElementById('mapResultAddr').textContent = full || data.display_name || 'Lokasi dipilih';
        document.getElementById('mapResultCoord').textContent = lat.toFixed(6) + ', ' + lng.toFixed(6);
        document.getElementById('btnKonfirmasiMap').disabled = false;
    })
    .catch(function() {
        selectedAddr = { full: '', kota: '', pos: '' };
        document.getElementById('mapResultAddr').textContent = 'Titik dipilih (alamat tidak dapat dimuat)';
        document.getElementById('btnKonfirmasiMap').disabled = false;
    });
}

function konfirmasiLokasi() {
    if (selectedLat === null) return;
    document.getElementById('formLat').value = selectedLat.toFixed(8);
    document.getElementById('formLng').value = selectedLng.toFixed(8);

    // Isi field alamat jika ada data dari nominatim
    if (selectedAddr && typeof selectedAddr === 'object') {
        if (selectedAddr.full) {
            var fAl = document.querySelector('textarea[name="alamat"]');
            if (fAl && !fAl.value) fAl.value = selectedAddr.full;
        }
        if (selectedAddr.kota) {
            var fKota = document.querySelector('input[name="kota"]');
            if (fKota && !fKota.value) fKota.value = selectedAddr.kota;
        }
        if (selectedAddr.pos) {
            var fPos = document.querySelector('input[name="kode_pos"]');
            if (fPos && !fPos.value) fPos.value = selectedAddr.pos;
        }
    }

    document.getElementById('coordLabel').innerHTML =
        '<i class="fas fa-check-circle" style="color:#15803d;"></i> Titik dipilih: ' +
        parseFloat(selectedLat).toFixed(5) + ', ' + parseFloat(selectedLng).toFixed(5);
    tutupMapPicker();
}

function lokasiSaya() {
    if (!navigator.geolocation) {
        alert('Browser Anda tidak mendukung geolocation.');
        return;
    }
    navigator.geolocation.getCurrentPosition(function(pos) {
        var lat = pos.coords.latitude;
        var lng = pos.coords.longitude;
        mapPicker.setView([lat, lng], 17);
        pasangMarker(lat, lng);
    }, function() {
        alert('Tidak dapat mengakses lokasi. Pastikan izin lokasi diaktifkan.');
    });
}

// PENCARIAN LOKASI
var searchTimer = null;
document.getElementById('mapSearchInput').addEventListener('keydown', function(e) {
    if (e.key === 'Enter') { e.preventDefault(); cariLokasi(); }
});
document.getElementById('mapSearchInput').addEventListener('input', function() {
    clearTimeout(searchTimer);
    var q = this.value.trim();
    if (q.length < 3) { document.getElementById('mapSearchResults').style.display = 'none'; return; }
    searchTimer = setTimeout(function() { cariLokasiSuggest(q); }, 400);
});

function cariLokasi() {
    var q = document.getElementById('mapSearchInput').value.trim();
    if (!q) return;
    fetch('https://nominatim.openstreetmap.org/search?format=json&q=' + encodeURIComponent(q) + '&limit=1&countrycodes=id', {
        headers: { 'Accept-Language': 'id' }
    })
    .then(function(r) { return r.json(); })
    .then(function(results) {
        if (!results.length) { alert('Lokasi tidak ditemukan.'); return; }
        var r = results[0];
        mapPicker.setView([r.lat, r.lon], 16);
        pasangMarker(parseFloat(r.lat), parseFloat(r.lon));
        document.getElementById('mapSearchResults').style.display = 'none';
    });
}

function cariLokasiSuggest(q) {
    fetch('https://nominatim.openstreetmap.org/search?format=json&q=' + encodeURIComponent(q) + '&limit=5&countrycodes=id', {
        headers: { 'Accept-Language': 'id' }
    })
    .then(function(r) { return r.json(); })
    .then(function(results) {
        var box = document.getElementById('mapSearchResults');
        if (!results.length) { box.style.display = 'none'; return; }
        box.innerHTML = '';
        results.forEach(function(r) {
            var div = document.createElement('div');
            div.className = 'map-search-result-item';
            div.textContent = r.display_name;
            div.onclick = function() {
                document.getElementById('mapSearchInput').value = r.display_name;
                box.style.display = 'none';
                mapPicker.setView([parseFloat(r.lat), parseFloat(r.lon)], 16);
                pasangMarker(parseFloat(r.lat), parseFloat(r.lon));
            };
            box.appendChild(div);
        });
        box.style.display = 'block';
    });
}

document.addEventListener('click', function(e) {
    if (!e.target.closest('.map-search-wrap')) {
        document.getElementById('mapSearchResults').style.display = 'none';
    }
});

// Tutup modal klik di luar
document.getElementById('mapModalOverlay').addEventListener('click', function(e) {
    if (e.target === this) tutupMapPicker();
});

// Mini map pada kartu alamat
<?php foreach ($alamat_list as $al): if ($al['lat'] && $al['lng']): ?>
(function() {
    var el = document.getElementById('minimap-<?= $al['id_alamat'] ?>');
    if (!el) return;
    var m = L.map(el, {zoomControl:false, dragging:false, scrollWheelZoom:false, doubleClickZoom:false, touchZoom:false, attributionControl:false});
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(m);
    m.setView([<?= $al['lat'] ?>, <?= $al['lng'] ?>], 15);
    L.circleMarker([<?= $al['lat'] ?>, <?= $al['lng'] ?>], {radius:8,color:'#953b22',fillColor:'#953b22',fillOpacity:0.8}).addTo(m);
})();
<?php endif; endforeach; ?>
</script>

<!-- Modal Detail Pesanan -->
<div class="ord-modal-bg" id="modalDetail" onclick="if(event.target===this)tutupModalDetail()">
    <div class="ord-modal">
        <div class="ord-modal-head">
            <h3><i class="fas fa-receipt" style="color:#953b22;margin-right:.4rem;"></i> Detail Pesanan</h3>
            <button class="ord-modal-close" onclick="tutupModalDetail()">×</button>
        </div>
        <div class="ord-modal-body" id="modalBody">
            <div style="text-align:center;padding:3rem;"><i class="fas fa-spinner fa-spin" style="font-size:1.5rem;color:#953b22;"></i></div>
        </div>
    </div>
</div>

<script src="<?= MIDTRANS_SNAP_JS_URL ?>" data-client-key="<?= MIDTRANS_CLIENT_KEY ?>"></script>
<script>
function lihatDetail(id) {
    var modal = document.getElementById('modalDetail');
    var body  = document.getElementById('modalBody');
    modal.classList.add('active');
    body.innerHTML = '<div style="text-align:center;padding:3rem;"><i class="fas fa-spinner fa-spin" style="font-size:1.5rem;color:#953b22;"></i></div>';

    fetch('profil.php?action=order_detail&id=' + id)
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.error) { body.innerHTML = '<p style="color:#dc2626;text-align:center;">' + data.error + '</p>'; return; }
        var o     = data.order;
        var items = data.items;
        var labelMap = {menunggu_pembayaran:'Menunggu Pembayaran',pending:'Menunggu Diproses',diproses:'Diproses',dikirim:'Dikirim',selesai:'Selesai',dibatalkan:'Dibatalkan'};
        var colorMap = {menunggu_pembayaran:'#d97706',pending:'#d97706',diproses:'#2563eb',dikirim:'#7c3aed',selesai:'#15803d',dibatalkan:'#dc2626'};
        var bgMap    = {menunggu_pembayaran:'#fffbeb',pending:'#fffbeb',diproses:'#eff6ff',dikirim:'#f5f3ff',selesai:'#f0fdf4',dibatalkan:'#fef2f2'};
        var stLbl = labelMap[o.status] || o.status;
        var stClr = colorMap[o.status] || '#6b7280';
        var stBg  = bgMap[o.status]    || '#f3f4f6';
        var html  = '';

        // Header ringkasan
        html += '<div style="background:#f9fafb;border-radius:.75rem;padding:.75rem 1rem;display:flex;justify-content:space-between;align-items:center;margin-bottom:1.25rem;">';
        html += '<div><div style="font-size:.72rem;font-weight:700;color:#953b22;">Pesanan #' + String(o.id_penjualan).padStart(5,'0') + '</div>';
        html += '<div style="font-size:.75rem;color:#9ca3af;margin-top:.1rem;">' + (o.tgl_penjualan ? o.tgl_penjualan.slice(0,16).replace('T',' ') : '-') + '</div></div>';
        html += '<span style="font-size:.68rem;font-weight:700;padding:.25rem .75rem;border-radius:9999px;background:' + stBg + ';color:' + stClr + ';">' + stLbl + '</span>';
        html += '</div>';

        // Produk
        html += '<div class="detail-section"><div class="detail-section-title"><i class="fas fa-box"></i> Produk</div>';
        items.forEach(function(item) {
            var imgSrc = item.gambar_produk ? '../assets/images/products/' + item.gambar_produk : '';
            html += '<div class="detail-item-row">';
            if (imgSrc) html += '<img src="' + imgSrc + '" class="detail-item-img" onerror="this.style.display=\'none\'">';
            else html += '<div class="detail-item-img" style="display:flex;align-items:center;justify-content:center;"><i class="fas fa-tshirt" style="color:#d1d5db;font-size:1.25rem;"></i></div>';
            html += '<div class="detail-item-info">';
            html += '<div class="detail-item-name">' + item.nama_produk + '</div>';
            html += '<div class="detail-item-sub">' + item.jumlah + ' pcs &times; Rp&nbsp;' + parseInt(item.harga).toLocaleString('id-ID') + '</div>';
            html += '</div>';
            html += '<div class="detail-item-price">Rp&nbsp;' + parseInt(item.subtotal).toLocaleString('id-ID') + '</div>';
            html += '</div>';
        });
        html += '</div>';

        // Pengiriman
        html += '<div class="detail-section"><div class="detail-section-title"><i class="fas fa-truck"></i> Pengiriman</div>';
        html += '<div class="detail-info-row"><span class="lbl">Penerima</span><span class="val">' + (o.nama_penerima||'-') + '</span></div>';
        html += '<div class="detail-info-row"><span class="lbl">No. WA</span><span class="val">' + (o.no_telepon_kirim||'-') + '</span></div>';
        html += '<div class="detail-info-row"><span class="lbl">Alamat</span><span class="val">' + (o.alamat_pengiriman||'-') + '</span></div>';
        html += '<div class="detail-info-row"><span class="lbl">Kota</span><span class="val">' + (o.kota||'-') + (o.kode_pos?' '+o.kode_pos:'') + '</span></div>';
        if (o.ekspedisi) html += '<div class="detail-info-row"><span class="lbl">Ekspedisi</span><span class="val">' + o.ekspedisi + '</span></div>';
        if (o.catatan)   html += '<div class="detail-info-row"><span class="lbl">Catatan</span><span class="val">' + o.catatan + '</span></div>';
        html += '</div>';

        // Pembayaran
        html += '<div class="detail-section"><div class="detail-section-title"><i class="fas fa-credit-card"></i> Pembayaran</div>';
        if (o.metode_bayar) html += '<div class="detail-info-row"><span class="lbl">Metode</span><span class="val">' + o.metode_bayar + '</span></div>';
        if (o.payment_status) html += '<div class="detail-info-row"><span class="lbl">Status Bayar</span><span class="val" style="text-transform:capitalize;">' + o.payment_status + '</span></div>';
        html += '</div>';

        // Total
        html += '<div class="detail-total-row"><span>Total</span><span class="val">Rp&nbsp;' + parseInt(o.total_harga).toLocaleString('id-ID') + '</span></div>';

        body.innerHTML = html;
    })
    .catch(function() {
        body.innerHTML = '<p style="color:#dc2626;text-align:center;padding:1.5rem;">Gagal memuat detail pesanan.</p>';
    });
}
function tutupModalDetail() {
    document.getElementById('modalDetail').classList.remove('active');
}
document.addEventListener('keydown', function(e) { if (e.key === 'Escape') tutupModalDetail(); });

function lanjutBayar(btn, idPenjualan) {
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Memuat...';

    fetch('payment_finish.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=retry_token&id_penjualan=' + idPenjualan,
    })
    .then(function(r) { return r.json(); })
    .then(function(res) {
        if (res.already_paid) {
            window.location.reload();
            return;
        }
        if (res.error) {
            alert(res.error);
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-credit-card"></i> Lanjutkan Pembayaran';
            return;
        }
        snap.pay(res.token, {
            onSuccess: function() {
                window.location.href = 'payment_finish.php?order_id=' + encodeURIComponent(res.midtrans_order_id) + '&transaction_status=settlement&status_code=200&gross_amount=0';
            },
            onPending: function() {
                window.location.href = 'payment_finish.php?order_id=' + encodeURIComponent(res.midtrans_order_id) + '&transaction_status=pending&status_code=201&gross_amount=0';
            },
            onError: function() {
                window.location.href = 'payment_finish.php?order_id=' + encodeURIComponent(res.midtrans_order_id) + '&transaction_status=deny&status_code=202&gross_amount=0';
            },
            onClose: function() {
                // Redirect ke payment_finish agar polling dapat cek apakah DANA/e-wallet sudah diproses
                window.location.href = 'payment_finish.php?order_id=' + encodeURIComponent(res.midtrans_order_id) + '&transaction_status=pending&status_code=201&gross_amount=0';
            },
        });
    })
    .catch(function() {
        alert('Terjadi kesalahan koneksi.');
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-credit-card"></i> Lanjutkan Pembayaran';
    });
}
</script>
<?php require_once '../includes/footer.php'; ?>
