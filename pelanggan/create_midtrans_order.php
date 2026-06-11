<?php
// Endpoint AJAX: Buat order baru + ambil Midtrans Snap token
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'pelanggan') {
    echo json_encode(['error' => 'Sesi tidak valid. Silakan login ulang.']); exit;
}
if (empty($_SESSION['cart'])) {
    echo json_encode(['error' => 'Keranjang belanja kosong.']); exit;
}

require_once '../config/db.php';
require_once '../config/midtrans.php';
require_once '../config/midtrans_helper.php';

$user = $_SESSION['user'];
$post = json_decode(file_get_contents('php://input'), true) ?? [];
if (empty($post)) $post = $_POST;

$nama    = trim($post['nama']      ?? '');
$telp    = trim($post['telp']      ?? '');
$alamat  = trim($post['alamat']    ?? '');
$kota    = trim($post['kota']      ?? '');
$kodepos = trim($post['kodepos']   ?? '');
$eksped  = trim($post['ekspedisi'] ?? '');
$catatan = trim($post['catatan']   ?? '');
$map_lat = !empty($post['co_lat']) ? floatval($post['co_lat']) : null;
$map_lng = !empty($post['co_lng']) ? floatval($post['co_lng']) : null;

if (!$nama || !$telp || !$alamat || !$kota) {
    echo json_encode(['error' => 'Mohon lengkapi nama penerima, nomor WA, alamat, dan kota.']); exit;
}

// Verifikasi cart & hitung total
$total = 0; $valid = true; $cart_verified = []; $item_details = [];
foreach ($_SESSION['cart'] as $item) {
    $stmt = $pdo->prepare("SELECT id_produk, nama_produk, harga, stok FROM produk WHERE id_produk=?");
    $stmt->execute([$item['id']]);
    $prod = $stmt->fetch();
    if (!$prod || $prod['stok'] < $item['qty']) { $valid = false; break; }
    $harga_int = (int)$prod['harga'];
    $qty_int   = (int)$item['qty'];
    $subtotal  = $harga_int * $qty_int;
    $total    += $subtotal;
    $cart_verified[] = ['id_produk' => $prod['id_produk'], 'qty' => $qty_int, 'subtotal' => $subtotal];
    $item_details[]  = [
        'id'       => (string)$prod['id_produk'],
        'price'    => $harga_int,
        'quantity' => $qty_int,
        'name'     => mb_substr($prod['nama_produk'], 0, 50),
    ];
}

if (!$valid || empty($cart_verified)) {
    echo json_encode(['error' => 'Stok produk tidak mencukupi atau produk tidak valid.']); exit;
}

// Auto-migrate kolom payment gateway
try { $pdo->exec("ALTER TABLE penjualan ADD COLUMN midtrans_order_id VARCHAR(100) NULL"); } catch (PDOException $e) {}
try { $pdo->exec("ALTER TABLE penjualan ADD COLUMN payment_token VARCHAR(500) NULL"); } catch (PDOException $e) {}
try { $pdo->exec("ALTER TABLE penjualan ADD COLUMN payment_status VARCHAR(50) NULL DEFAULT NULL"); } catch (PDOException $e) {}
try {
    $pdo->exec("ALTER TABLE penjualan MODIFY COLUMN status ENUM('menunggu_pembayaran','pending','diproses','dikirim','selesai','dibatalkan') NOT NULL DEFAULT 'pending'");
} catch (PDOException $e) {}

// Buat order di DB dengan status menunggu_pembayaran
$stmt = $pdo->prepare(
    "INSERT INTO penjualan
     (tgl_penjualan, total_harga, id_user, status,
      nama_penerima, no_telepon_kirim, alamat_pengiriman, kota, kode_pos,
      ekspedisi, metode_bayar, catatan, lat, lng)
     VALUES (NOW(),?,?,'menunggu_pembayaran',?,?,?,?,?,?,'Online (Midtrans)',?,?,?)"
);
$stmt->execute([$total, $user['id_user'], $nama, $telp, $alamat, $kota, $kodepos, $eksped, $catatan, $map_lat, $map_lng]);
$id_penjualan = (int)$pdo->lastInsertId();
$midtrans_oid = 'ELEA-' . $id_penjualan . '-' . time();

// Simpan detail & kurangi stok
foreach ($cart_verified as $item) {
    $pdo->prepare("INSERT INTO detail_penjualan (id_penjualan, id_produk, jumlah, subtotal) VALUES (?,?,?,?)")
        ->execute([$id_penjualan, $item['id_produk'], $item['qty'], $item['subtotal']]);
    $pdo->prepare("UPDATE produk SET stok = stok - ? WHERE id_produk = ?")
        ->execute([$item['qty'], $item['id_produk']]);
}

// Simpan midtrans_order_id
$pdo->prepare("UPDATE penjualan SET midtrans_order_id=? WHERE id_penjualan=?")
    ->execute([$midtrans_oid, $id_penjualan]);

// Siapkan email pelanggan
$uname = $user['username'] ?? 'pelanggan';
$email = filter_var($uname, FILTER_VALIDATE_EMAIL) ? $uname : ($uname . '@elea.store');

// Build Midtrans Snap request
$proto      = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$finish_url = $proto . '://' . $_SERVER['HTTP_HOST']
            . str_replace(' ', '%20', dirname($_SERVER['SCRIPT_NAME']))
            . '/payment_finish.php';

$snap_body = [
    'transaction_details' => [
        'order_id'     => $midtrans_oid,
        'gross_amount' => $total,
    ],
    'customer_details' => [
        'first_name' => $nama,
        'phone'      => $telp,
        'email'      => $email,
    ],
    'item_details' => $item_details,
    'callbacks'    => ['finish' => $finish_url],
];

$snap_res = midtrans_create_token($snap_body);

if (!isset($snap_res['token'])) {
    // Rollback: kembalikan stok & hapus order
    foreach ($cart_verified as $item) {
        $pdo->prepare("UPDATE produk SET stok = stok + ? WHERE id_produk = ?")
            ->execute([$item['qty'], $item['id_produk']]);
    }
    $pdo->prepare("DELETE FROM detail_penjualan WHERE id_penjualan=?")->execute([$id_penjualan]);
    $pdo->prepare("DELETE FROM penjualan WHERE id_penjualan=?")->execute([$id_penjualan]);
    echo json_encode(['error' => $snap_res['error'] ?? 'Gagal menghubungi payment gateway. Pastikan API key sudah benar.']);
    exit;
}

// Simpan token ke DB
$pdo->prepare("UPDATE penjualan SET payment_token=? WHERE id_penjualan=?")
    ->execute([$snap_res['token'], $id_penjualan]);

// Kosongkan cart
$_SESSION['cart'] = [];

// Simpan info order untuk halaman finish
$_SESSION['pending_midtrans'] = [
    'id_penjualan' => $id_penjualan,
    'midtrans_oid' => $midtrans_oid,
    'nama'         => $nama,
    'total'        => $total,
];

echo json_encode([
    'success'           => true,
    'token'             => $snap_res['token'],
    'id_penjualan'      => $id_penjualan,
    'midtrans_order_id' => $midtrans_oid,
]);
