<?php
session_start();
require_once '../config/db.php';
require_once '../config/midtrans.php';
require_once '../config/midtrans_helper.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'pelanggan') {
    header('Location: ../login.php'); exit;
}

// AJAX: Cek status order ke Midtrans API
if (($_GET['check'] ?? '') === 'status') {
    header('Content-Type: application/json');
    $id_cek  = intval($_GET['id'] ?? 0);
    $user_id = $_SESSION['user']['id_user'];
    $row = $pdo->prepare("SELECT id_penjualan, status, midtrans_order_id FROM penjualan WHERE id_penjualan=? AND id_user=?");
    $row->execute([$id_cek, $user_id]);
    $cek = $row->fetch();
    if (!$cek) { echo json_encode(['status' => 'not_found']); exit; }
    if ($cek['status'] !== 'menunggu_pembayaran') {
        echo json_encode(['status' => $cek['status']]); exit;
    }
    if (!empty($cek['midtrans_order_id'])) {
        $mt = midtrans_get_status($cek['midtrans_order_id']);
        $mt_trx = $mt['transaction_status'] ?? '';
        if (in_array($mt_trx, ['settlement', 'capture'])) {
            $pdo->prepare("UPDATE penjualan SET status='pending', payment_status=? WHERE id_penjualan=?")
                ->execute([$mt_trx, $id_cek]);
            echo json_encode(['status' => 'pending', 'updated' => true]); exit;
        }
        if (in_array($mt_trx, ['deny', 'cancel', 'expire', 'failure'])) {
            $itms = $pdo->prepare("SELECT id_produk, jumlah FROM detail_penjualan WHERE id_penjualan=?");
            $itms->execute([$id_cek]);
            foreach ($itms->fetchAll() as $it) {
                $pdo->prepare("UPDATE produk SET stok = stok + ? WHERE id_produk = ?")->execute([$it['jumlah'], $it['id_produk']]);
            }
            $pdo->prepare("UPDATE penjualan SET status='dibatalkan', payment_status=? WHERE id_penjualan=?")->execute([$mt_trx, $id_cek]);
            echo json_encode(['status' => 'dibatalkan', 'updated' => true]); exit;
        }
        echo json_encode(['status' => 'menunggu_pembayaran', 'mt_status' => $mt_trx]); exit;
    }
    echo json_encode(['status' => 'menunggu_pembayaran']); exit;
}

// AJAX: Buat ulang Snap token untuk retry pembayaran
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'retry_token') {
    header('Content-Type: application/json');
    $id_penjualan = intval($_POST['id_penjualan'] ?? 0);
    $user_id      = $_SESSION['user']['id_user'];

    $stmt = $pdo->prepare(
        "SELECT p.*, GROUP_CONCAT(dp.id_produk,':',dp.jumlah,':',dp.subtotal ORDER BY dp.id_produk SEPARATOR '|') AS items
         FROM penjualan p
         LEFT JOIN detail_penjualan dp ON p.id_penjualan = dp.id_penjualan
         WHERE p.id_penjualan=? AND p.id_user=? AND p.status='menunggu_pembayaran'
         GROUP BY p.id_penjualan"
    );
    $stmt->execute([$id_penjualan, $user_id]);
    $order = $stmt->fetch();

    if (!$order) { echo json_encode(['error' => 'Pesanan tidak ditemukan atau sudah dibayar.']); exit; }

    // Cek dulu apakah order_id saat ini sudah dibayar di Midtrans sebelum membuat yang baru
    if (!empty($order['midtrans_order_id'])) {
        $existing = midtrans_get_status($order['midtrans_order_id']);
        $ex_trx   = $existing['transaction_status'] ?? '';
        if (in_array($ex_trx, ['settlement', 'capture'])) {
            $pdo->prepare("UPDATE penjualan SET status='pending', payment_status=? WHERE id_penjualan=?")
                ->execute([$ex_trx, $id_penjualan]);
            echo json_encode(['already_paid' => true, 'message' => 'Pembayaran sudah dikonfirmasi!']);
            exit;
        }
    }

    // Buat midtrans_order_id baru (token lama sudah expired)
    $new_oid = 'ELEA-' . $id_penjualan . '-' . time();
    $pdo->prepare("UPDATE penjualan SET midtrans_order_id=? WHERE id_penjualan=?")->execute([$new_oid, $id_penjualan]);

    // Ambil item details
    $item_details = [];
    if ($order['items']) {
        foreach (explode('|', $order['items']) as $row) {
            [$pid, $qty, $sub] = explode(':', $row);
            $pr = $pdo->prepare("SELECT nama_produk, harga FROM produk WHERE id_produk=?");
            $pr->execute([$pid]);
            $prod = $pr->fetch();
            if ($prod) {
                $item_details[] = [
                    'id'       => (string)$pid,
                    'price'    => (int)$prod['harga'],
                    'quantity' => (int)$qty,
                    'name'     => mb_substr($prod['nama_produk'], 0, 50),
                ];
            }
        }
    }
    if (empty($item_details)) {
        $item_details[] = ['id' => '0', 'price' => (int)$order['total_harga'], 'quantity' => 1, 'name' => 'Pesanan #' . $id_penjualan];
    }

    $uname = $_SESSION['user']['username'] ?? 'pelanggan';
    $email = filter_var($uname, FILTER_VALIDATE_EMAIL) ? $uname : ($uname . '@elea.store');

    $proto_r    = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $finish_url_r = $proto_r . '://' . $_SERVER['HTTP_HOST']
                  . str_replace(' ', '%20', dirname($_SERVER['SCRIPT_NAME']))
                  . '/payment_finish.php';

    $snap_body = [
        'transaction_details' => ['order_id' => $new_oid, 'gross_amount' => (int)$order['total_harga']],
        'customer_details'    => ['first_name' => $order['nama_penerima'], 'phone' => $order['no_telepon_kirim'], 'email' => $email],
        'item_details'        => $item_details,
        'callbacks'           => ['finish' => $finish_url_r],
        'enabled_payments'    => ['credit_card','bca_va','bni_va','bri_va','permata_va','other_va','gopay','shopeepay','qris','other_qris','indomaret','alfamart'],
    ];
    $snap_res = midtrans_create_token($snap_body);

    if (isset($snap_res['token'])) {
        $pdo->prepare("UPDATE penjualan SET payment_token=? WHERE id_penjualan=?")->execute([$snap_res['token'], $id_penjualan]);
        echo json_encode(['success' => true, 'token' => $snap_res['token'], 'midtrans_order_id' => $new_oid]);
    } else {
        echo json_encode(['error' => $snap_res['error'] ?? 'Gagal membuat token pembayaran.']);
    }
    exit;
}

// Ambil parameter dari redirect Midtrans
$midtrans_oid = $_GET['order_id']           ?? '';
$trans_status = $_GET['transaction_status'] ?? '';
$status_code  = $_GET['status_code']        ?? '';
$gross_get    = $_GET['gross_amount']        ?? '';
$sig_get      = $_GET['signature_key']       ?? '';

$order = null;
if ($midtrans_oid) {
    $stmt = $pdo->prepare("SELECT * FROM penjualan WHERE midtrans_order_id=?");
    $stmt->execute([$midtrans_oid]);
    $order = $stmt->fetch();
}
if (!$order && isset($_SESSION['pending_midtrans'])) {
    $pm = $_SESSION['pending_midtrans'];
    $stmt = $pdo->prepare("SELECT * FROM penjualan WHERE id_penjualan=?");
    $stmt->execute([$pm['id_penjualan']]);
    $order = $stmt->fetch();
    if (!$trans_status && $order) $trans_status = $order['payment_status'] ?? '';
}

if (!$order) { header('Location: profil.php?tab=pesanan'); exit; }

// Auto-check status dari Midtrans API jika order masih menunggu pembayaran
if ($order['status'] === 'menunggu_pembayaran' && !empty($order['midtrans_order_id'])) {
    $mt = midtrans_get_status($order['midtrans_order_id']);
    $mt_trans = $mt['transaction_status'] ?? '';
    $mt_fraud = $mt['fraud_status'] ?? '';
    $auto_new = null;
    if (in_array($mt_trans, ['settlement', 'capture'])) {
        if ($mt_trans === 'capture' && $mt_fraud === 'challenge') {
            // masih perlu review manual, biarkan menunggu
        } else {
            $auto_new = 'pending';
        }
    } elseif (in_array($mt_trans, ['deny', 'cancel', 'expire', 'failure'])) {
        $auto_new = 'dibatalkan';
        $items_s = $pdo->prepare("SELECT id_produk, jumlah FROM detail_penjualan WHERE id_penjualan=?");
        $items_s->execute([$order['id_penjualan']]);
        foreach ($items_s->fetchAll() as $it) {
            $pdo->prepare("UPDATE produk SET stok = stok + ? WHERE id_produk = ?")->execute([$it['jumlah'], $it['id_produk']]);
        }
    }
    if ($auto_new) {
        $pdo->prepare("UPDATE penjualan SET status=?, payment_status=? WHERE id_penjualan=?")
            ->execute([$auto_new, $mt_trans, $order['id_penjualan']]);
        $order['status'] = $auto_new;
        if (!$trans_status) $trans_status = $mt_trans;
    }
}

// Update status berdasarkan redirect parameter
if ($trans_status && $order) {
    $sig_ok = !$sig_get || midtrans_verify_signature($midtrans_oid ?: ($order['midtrans_order_id'] ?? ''), $status_code, $gross_get, $sig_get);
    if ($sig_ok && $order['status'] === 'menunggu_pembayaran') {
        $new_st = null;
        if (in_array($trans_status, ['settlement', 'capture'])) $new_st = 'pending';
        elseif ($trans_status === 'pending')                     $new_st = 'menunggu_pembayaran';
        elseif (in_array($trans_status, ['deny','cancel','expire','failure'])) {
            $new_st = 'dibatalkan';
            // Kembalikan stok jika dibatalkan dari browser
            $items = $pdo->prepare("SELECT id_produk, jumlah FROM detail_penjualan WHERE id_penjualan=?");
            $items->execute([$order['id_penjualan']]);
            foreach ($items->fetchAll() as $it) {
                $pdo->prepare("UPDATE produk SET stok = stok + ? WHERE id_produk = ?")
                    ->execute([$it['jumlah'], $it['id_produk']]);
            }
        }
        if ($new_st) {
            $pdo->prepare("UPDATE penjualan SET status=?, payment_status=? WHERE id_penjualan=?")
                ->execute([$new_st, $trans_status, $order['id_penjualan']]);
            $order['status'] = $new_st;
        }
    }
}

unset($_SESSION['pending_midtrans']);

$is_success = in_array($trans_status, ['settlement','capture']) || $order['status'] === 'pending';
$is_waiting = $trans_status === 'pending' || $order['status'] === 'menunggu_pembayaran';
$is_failed  = in_array($trans_status, ['deny','cancel','expire','failure']) || $order['status'] === 'dibatalkan';
$display_id = '#ELEA-' . str_pad($order['id_penjualan'], 5, '0', STR_PAD_LEFT);

$siteRoot   = '../';
$activePage = '';
$pageTitle  = 'Status Pembayaran';
require_once '../includes/header.php';
?>

<style>
.pf-wrap{max-width:540px;margin:2rem auto;padding:1.5rem 1rem 4rem;}
.pf-card{background:white;border-radius:1.5rem;border:1px solid #fce9e3;padding:2.5rem 2rem;text-align:center;box-shadow:0 4px 24px rgba(0,0,0,.08);}
.pf-icon{font-size:4rem;margin-bottom:1rem;line-height:1;}
.pf-title{font-size:1.375rem;font-weight:700;color:#1f2937;margin-bottom:.5rem;}
.pf-sub{font-size:.9375rem;color:#6b7280;margin-bottom:1.5rem;line-height:1.6;}
.pf-info{background:#f9fafb;border-radius:.875rem;padding:1rem 1.25rem;text-align:left;margin-bottom:1.5rem;border:1px solid #f3f4f6;}
.pf-row{display:flex;justify-content:space-between;align-items:flex-start;padding:.375rem 0;font-size:.875rem;}
.pf-row:not(:last-child){border-bottom:1px solid #f3f4f6;}
.pf-row .lbl{color:#9ca3af;flex-shrink:0;}
.pf-row .val{font-weight:600;color:#1f2937;text-align:right;max-width:65%;}
.pf-btn{display:block;padding:.875rem;border-radius:.875rem;font-weight:700;font-size:.9375rem;text-decoration:none;text-align:center;margin-bottom:.5rem;cursor:pointer;border:none;font-family:inherit;width:100%;}
.pf-btn-primary{background:linear-gradient(135deg,#953b22,#9e5848);color:white;}
.pf-btn-primary:hover{opacity:.9;}
.pf-btn-outline{background:transparent;border:1.5px solid #fce9e3;color:#953b22;}
.pf-btn-outline:hover{background:#fff8f6;}
.pf-steps{display:flex;gap:.5rem;justify-content:center;margin-bottom:1.5rem;flex-wrap:wrap;}
.pf-step{padding:.4rem .875rem;border-radius:9999px;font-size:.75rem;font-weight:600;}
.pf-step.done{background:#fff8f6;color:#953b22;}
.pf-step.active{background:#fffbeb;color:#d97706;}
.pf-step.todo{background:#f3f4f6;color:#9ca3af;}
.pf-alert{background:#fffbeb;border:1px solid #fde68a;border-radius:.75rem;padding:.875rem 1rem;font-size:.875rem;color:#92400e;margin-bottom:1rem;text-align:left;line-height:1.5;}
</style>

<div class="pf-wrap">
    <div class="pf-card">

        <?php if ($is_success && !$is_waiting): ?>
        <div class="pf-icon">✅</div>
        <div class="pf-title" style="color:#16a34a;">Pembayaran Berhasil!</div>
        <div class="pf-sub">Terima kasih! Pesanan Anda telah dikonfirmasi dan sedang kami persiapkan.</div>

        <?php elseif ($is_waiting && !$is_success): ?>
        <div class="pf-icon">⏳</div>
        <div class="pf-title" style="color:#d97706;">Menunggu Pembayaran</div>
        <div class="pf-sub">Pesanan dibuat. Selesaikan pembayaran sesuai instruksi yang Anda terima.</div>
        <div class="pf-steps">
            <div class="pf-step done"><i class="fas fa-check"></i> Pesanan Dibuat</div>
            <div class="pf-step active"><i class="fas fa-clock"></i> Bayar Sekarang</div>
            <div class="pf-step todo"><i class="fas fa-box"></i> Diproses</div>
        </div>

        <div class="pf-alert">
            <i class="fas fa-info-circle"></i> Jika Anda sudah membayar namun status belum berubah, tunggu beberapa menit atau hubungi kami via WhatsApp.
        </div>
        <button class="pf-btn pf-btn-outline" id="btnRetry" onclick="retryPayment(<?= $order['id_penjualan'] ?>)" style="margin-bottom:.75rem;">
            <i class="fas fa-redo"></i> Lanjutkan / Ulangi Pembayaran
        </button>

        <?php elseif ($is_failed): ?>
        <div class="pf-icon">❌</div>
        <div class="pf-title" style="color:#dc2626;">Pembayaran Tidak Berhasil</div>
        <div class="pf-sub">Pembayaran gagal, ditolak, atau kedaluwarsa. Pesanan telah dibatalkan dan stok dikembalikan.</div>

        <?php else: ?>
        <div class="pf-icon">📋</div>
        <div class="pf-title">Status Pesanan</div>
        <div class="pf-sub">Silakan cek riwayat pesanan Anda untuk detail lebih lanjut.</div>
        <?php endif; ?>

        <div class="pf-info">
            <div class="pf-row"><span class="lbl">No. Pesanan</span><span class="val" style="color:#953b22;"><?= $display_id ?></span></div>
            <div class="pf-row"><span class="lbl">Total</span><span class="val"><?= fmt_rp((int)$order['total_harga']) ?></span></div>
            <div class="pf-row"><span class="lbl">Penerima</span><span class="val"><?= htmlspecialchars($order['nama_penerima'] ?? '-') ?></span></div>
            <div class="pf-row"><span class="lbl">Alamat</span><span class="val"><?= htmlspecialchars(mb_substr($order['alamat_pengiriman'] ?? '-', 0, 60)) ?></span></div>
            <div class="pf-row"><span class="lbl">Status</span>
                <span class="val">
                    <?php
                    $st = $order['status'];
                    $stl = ['menunggu_pembayaran'=>'Menunggu Pembayaran','pending'=>'Menunggu Diproses','diproses'=>'Diproses','dikirim'=>'Dikirim','selesai'=>'Selesai','dibatalkan'=>'Dibatalkan'];
                    echo $stl[$st] ?? ucfirst($st);
                    ?>
                </span>
            </div>
        </div>

        <a href="profil.php?tab=pesanan" class="pf-btn pf-btn-primary"><i class="fas fa-box"></i> Lihat Riwayat Pesanan</a>
        <a href="../katalog.php" class="pf-btn pf-btn-outline" style="display:block;padding:.625rem;color:#953b22;font-size:.875rem;text-decoration:none;text-align:center;">Lanjut Belanja →</a>
    </div>
</div>

<script src="<?= MIDTRANS_SNAP_JS_URL ?>" data-client-key="<?= MIDTRANS_CLIENT_KEY ?>"></script>
<script>
function retryPayment(idPenjualan) {
    var btn = document.getElementById('btnRetry');
    if (!btn) return;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Memuat...';

    fetch('payment_finish.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=retry_token&id_penjualan=' + idPenjualan,
    })
    .then(function(r) { return r.json(); })
    .then(function(res) {
        if (res.error) {
            alert(res.error);
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-redo"></i> Lanjutkan / Ulangi Pembayaran';
            return;
        }
        snap.pay(res.token, {
            onSuccess: function() { window.location.href = 'payment_finish.php?order_id=' + encodeURIComponent(res.midtrans_order_id) + '&transaction_status=settlement&status_code=200&gross_amount=0'; },
            onPending: function() { window.location.href = 'payment_finish.php?order_id=' + encodeURIComponent(res.midtrans_order_id) + '&transaction_status=pending&status_code=201&gross_amount=0'; },
            onError:   function() { window.location.href = 'payment_finish.php?order_id=' + encodeURIComponent(res.midtrans_order_id) + '&transaction_status=deny&status_code=202&gross_amount=0'; },
            onClose:   function() {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-redo"></i> Lanjutkan / Ulangi Pembayaran';
            },
        });
    })
    .catch(function() {
        alert('Terjadi kesalahan koneksi.');
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-redo"></i> Lanjutkan / Ulangi Pembayaran';
    });
}
</script>

<?php if ($is_waiting && $order): ?>
<script>
// Auto-cek status pembayaran setiap 4 detik
var orderId = <?= (int)$order['id_penjualan'] ?>;
var pollCount = 0;
var maxPoll = 45; // cek maksimal 3 menit

var pollTimer = setInterval(function() {
    pollCount++;
    if (pollCount > maxPoll) { clearInterval(pollTimer); return; }

    fetch('payment_finish.php?check=status&id=' + orderId)
    .then(function(r) { return r.json(); })
    .then(function(d) {
        if (d.status === 'pending') {
            clearInterval(pollTimer);
            // Tampil notif sukses lalu reload
            document.querySelector('.pf-icon').textContent = '✅';
            document.querySelector('.pf-title').textContent = 'Pembayaran Berhasil!';
            document.querySelector('.pf-title').style.color = '#16a34a';
            document.querySelector('.pf-sub').textContent = 'Terima kasih! Pesanan Anda dikonfirmasi.';
            setTimeout(function() { window.location.reload(); }, 1500);
        } else if (d.status === 'dibatalkan') {
            clearInterval(pollTimer);
            window.location.reload();
        }
    })
    .catch(function() {});
}, 4000);
</script>
<?php endif; ?>

<?php require_once '../includes/footer.php'; ?>
