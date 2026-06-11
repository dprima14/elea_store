<?php
// ============================================================
// MIDTRANS PAYMENT NOTIFICATION HANDLER (WEBHOOK)
// ============================================================
// Atur URL ini di Midtrans Dashboard:
//   Settings → Configuration → Payment Notification URL
//   Isi: https://yourdomain.com/midtrans_notification.php
//
// Untuk testing lokal: gunakan ngrok (ngrok http 80)
//   Lalu isi: https://xxxx.ngrok.io/elea_store%20-%20logo/midtrans_notification.php
// ============================================================

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/midtrans.php';
require_once __DIR__ . '/config/midtrans_helper.php';

$raw   = file_get_contents('php://input');
$notif = json_decode($raw, true);

if (!$notif || !isset($notif['order_id'])) {
    http_response_code(400); echo 'Bad Request'; exit;
}

$order_id           = $notif['order_id']            ?? '';
$status_code        = $notif['status_code']          ?? '';
$gross_amount       = $notif['gross_amount']         ?? '';
$signature_key      = $notif['signature_key']        ?? '';
$transaction_status = $notif['transaction_status']   ?? '';
$payment_type       = $notif['payment_type']         ?? '';
$fraud_status       = $notif['fraud_status']         ?? '';

// Verifikasi tanda tangan (signature)
if (!midtrans_verify_signature($order_id, $status_code, $gross_amount, $signature_key)) {
    http_response_code(403); echo 'Forbidden: Invalid Signature'; exit;
}

// Cari order di DB
$stmt = $pdo->prepare("SELECT id_penjualan, status FROM penjualan WHERE midtrans_order_id=?");
$stmt->execute([$order_id]);
$order = $stmt->fetch();

if (!$order) { http_response_code(404); echo 'Order not found'; exit; }

$id_penjualan = $order['id_penjualan'];
$new_status   = null;

if ($transaction_status === 'capture') {
    $new_status = ($fraud_status === 'accept') ? 'pending' : 'menunggu_pembayaran';
} elseif ($transaction_status === 'settlement') {
    $new_status = 'pending';
} elseif ($transaction_status === 'pending') {
    $new_status = 'menunggu_pembayaran';
} elseif (in_array($transaction_status, ['deny', 'cancel', 'expire', 'failure'])) {
    $new_status = 'dibatalkan';
    // Kembalikan stok
    $items = $pdo->prepare("SELECT id_produk, jumlah FROM detail_penjualan WHERE id_penjualan=?");
    $items->execute([$id_penjualan]);
    foreach ($items->fetchAll() as $item) {
        $pdo->prepare("UPDATE produk SET stok = stok + ? WHERE id_produk = ?")
            ->execute([$item['jumlah'], $item['id_produk']]);
    }
}

$method_display = [
    'credit_card'  => 'Kartu Kredit',
    'bank_transfer'=> 'Transfer Bank',
    'echannel'     => 'Mandiri Bill',
    'bca_va'       => 'BCA Virtual Account',
    'bni_va'       => 'BNI Virtual Account',
    'bri_va'       => 'BRI Virtual Account',
    'permata_va'   => 'Permata Virtual Account',
    'other_va'     => 'Virtual Account',
    'gopay'        => 'GoPay',
    'shopeepay'    => 'ShopeePay',
    'qris'         => 'QRIS',
    'other_qris'   => 'QRIS',
    'indomaret'    => 'Indomaret',
    'alfamart'     => 'Alfamart',
    'akulaku'      => 'Akulaku',
    'kredivo'      => 'Kredivo',
];
$metode = $method_display[$payment_type] ?? ucfirst(str_replace('_', ' ', $payment_type));

if ($new_status) {
    $pdo->prepare("UPDATE penjualan SET status=?, payment_status=?, metode_bayar=? WHERE id_penjualan=?")
        ->execute([$new_status, $transaction_status, $metode, $id_penjualan]);
}

http_response_code(200);
echo json_encode(['ok' => true, 'id_penjualan' => $id_penjualan, 'new_status' => $new_status]);
