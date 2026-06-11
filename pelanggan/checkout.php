<?php
session_start();
require_once '../config/db.php';
require_once '../config/midtrans.php';

// Auto-migrate kolom payment gateway
try { $pdo->exec("ALTER TABLE penjualan ADD COLUMN midtrans_order_id VARCHAR(100) NULL"); } catch (PDOException $e) {}
try { $pdo->exec("ALTER TABLE penjualan ADD COLUMN payment_token VARCHAR(500) NULL"); } catch (PDOException $e) {}
try { $pdo->exec("ALTER TABLE penjualan ADD COLUMN payment_status VARCHAR(50) NULL DEFAULT NULL"); } catch (PDOException $e) {}
try { $pdo->exec("ALTER TABLE penjualan MODIFY COLUMN status ENUM('menunggu_pembayaran','pending','diproses','dikirim','selesai','dibatalkan') NOT NULL DEFAULT 'pending'"); } catch (PDOException $e) {}

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'pelanggan') {
    header('Location: ../login.php'); exit;
}
if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];
$user = $_SESSION['user'];

$success  = false;
$order_id = '';
$order_total = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pesan']) && !empty($_SESSION['cart'])) {
    $nama    = trim($_POST['nama']       ?? '');
    $telp    = trim($_POST['telp']       ?? '');
    $alamat  = trim($_POST['alamat']     ?? '');
    $kota    = trim($_POST['kota']       ?? '');
    $kodepos = trim($_POST['kodepos']    ?? '');
    $eksped  = trim($_POST['ekspedisi']  ?? '');
    $bayar   = trim($_POST['bayar']      ?? '');
    $catatan = trim($_POST['catatan']    ?? '');
    $map_lat = !empty($_POST['co_lat'])  ? floatval($_POST['co_lat']) : null;
    $map_lng = !empty($_POST['co_lng'])  ? floatval($_POST['co_lng']) : null;

    if ($nama && $telp && $alamat && $kota && $bayar) {
        $total = 0; $valid = true; $cart_verified = [];
        foreach ($_SESSION['cart'] as $item) {
            $stmt = $pdo->prepare("SELECT id_produk, harga, stok FROM produk WHERE id_produk = ?");
            $stmt->execute([$item['id']]);
            $prod = $stmt->fetch();
            if (!$prod || $prod['stok'] < $item['qty']) { $valid = false; break; }
            $subtotal = $prod['harga'] * $item['qty'];
            $total += $subtotal;
            $cart_verified[] = ['id_produk'=>$prod['id_produk'], 'qty'=>$item['qty'], 'subtotal'=>$subtotal];
        }

        if ($valid && !empty($cart_verified)) {
            $stmt = $pdo->prepare(
                "INSERT INTO penjualan
                 (tgl_penjualan, total_harga, id_user, status,
                  nama_penerima, no_telepon_kirim, alamat_pengiriman, kota, kode_pos,
                  ekspedisi, metode_bayar, catatan, lat, lng)
                 VALUES (NOW(),?,?,'pending',?,?,?,?,?,?,?,?,?,?)"
            );
            $stmt->execute([
                $total, $user['id_user'],
                $nama, $telp, $alamat, $kota, $kodepos,
                $eksped, $bayar, $catatan, $map_lat, $map_lng,
            ]);
            $id_penjualan = $pdo->lastInsertId();
            $order_id     = '#PO-'.str_pad($id_penjualan, 5, '0', STR_PAD_LEFT);
            $order_total  = $total;

            foreach ($cart_verified as $item) {
                $pdo->prepare("INSERT INTO detail_penjualan (id_penjualan, id_produk, jumlah, subtotal) VALUES (?,?,?,?)")
                    ->execute([$id_penjualan, $item['id_produk'], $item['qty'], $item['subtotal']]);
                $pdo->prepare("UPDATE produk SET stok = stok - ? WHERE id_produk = ?")
                    ->execute([$item['qty'], $item['id_produk']]);
            }

            $_SESSION['last_order'] = [
                'id'    => $order_id,
                'nama'  => $nama,
                'bayar' => $bayar,
                'total' => $total,
                'tgl'   => date('d M Y, H:i'),
            ];
            $_SESSION['cart'] = [];
            $success = true;
        }
    }
}

// Auto-migrate tabel alamat (kalau belum ada)
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS alamat_pelanggan (
        id_alamat INT AUTO_INCREMENT PRIMARY KEY,
        id_user INT NOT NULL,
        label VARCHAR(50) NOT NULL DEFAULT 'Rumah',
        nama_penerima VARCHAR(255) NOT NULL,
        no_telepon VARCHAR(20) NOT NULL,
        alamat TEXT NOT NULL,
        kota VARCHAR(100) NOT NULL,
        kode_pos VARCHAR(10) DEFAULT NULL,
        is_utama TINYINT(1) NOT NULL DEFAULT 0,
        dibuat DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    )");
} catch (PDOException $e) {}

// Ambil alamat tersimpan
$alamat_tersimpan = $pdo->prepare(
    "SELECT * FROM alamat_pelanggan WHERE id_user=? ORDER BY is_utama DESC, dibuat ASC"
);
$alamat_tersimpan->execute([$user['id_user']]);
$alamat_tersimpan = $alamat_tersimpan->fetchAll();

$cart      = $_SESSION['cart'];
$subtotal  = array_sum(array_map(fn($c) => $c['harga'] * $c['qty'], $cart));
$cart_count = 0;
$nama_val   = htmlspecialchars($_POST['nama']   ?? $user['nama']);
$telp_val   = htmlspecialchars($_POST['telp']   ?? '');
$alamat_val = htmlspecialchars($_POST['alamat'] ?? '');
$kota_val   = htmlspecialchars($_POST['kota']   ?? '');
$catatan    = htmlspecialchars($_POST['catatan'] ?? '');

$siteRoot   = '../';
$activePage = '';
$pageTitle  = 'Checkout';
require_once '../includes/header.php';
?>

<style>
.co-wrap{max-width:900px;margin:0 auto;padding:1.5rem 1rem 3rem;}
.steps{display:flex;align-items:center;gap:0;margin-bottom:2rem;background:white;padding:.875rem 1.5rem;border-radius:1rem;border:1px solid #fce9e3;box-shadow:0 1px 4px rgba(0,0,0,.05);}
.step{display:flex;align-items:center;gap:.375rem;flex:1;}
.step-num{width:26px;height:26px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:.75rem;font-weight:700;flex-shrink:0;}
.step-lbl{font-size:.8125rem;font-weight:600;white-space:nowrap;}
.step-line{flex:1;height:2px;background:#fce9e3;margin:0 .5rem;}
.cgrid{display:grid;grid-template-columns:1fr 340px;gap:1.25rem;align-items:start;}
.fcard{background:white;border-radius:1.25rem;border:1px solid #fce9e3;padding:1.25rem 1.5rem;box-shadow:0 1px 4px rgba(0,0,0,.05);margin-bottom:1rem;}
.fcard h2{font-weight:700;color:#1f2937;font-size:.9375rem;margin-bottom:1rem;display:flex;align-items:center;gap:.5rem;}
.fg{margin-bottom:.875rem;}
.fg label{display:block;font-size:.75rem;font-weight:600;color:#6b7280;margin-bottom:.375rem;}
.fg input,.fg select,.fg textarea{width:100%;padding:.625rem .875rem;border:1.5px solid #f5d4cb;border-radius:.625rem;font-size:.8125rem;font-family:inherit;outline:none;background:#fff8f6;transition:all .15s;box-sizing:border-box;}
.fg input:focus,.fg select:focus,.fg textarea:focus{border-color:#9e5848;box-shadow:0 0 0 3px rgba(149,59,34,.1);}
.fgrid2{display:grid;grid-template-columns:1fr 1fr;gap:.75rem;}
.pay-opts{display:grid;grid-template-columns:1fr 1fr;gap:.5rem;}
.pay-opt{border:2px solid #fce9e3;border-radius:.875rem;padding:.625rem .875rem;cursor:pointer;display:flex;align-items:center;gap:.5rem;transition:all .15s;background:white;}
.pay-opt:hover{border-color:#9e5848;background:#fff8f6;}
.pay-opt.selected{border-color:#953b22;background:#fff8f6;}
.pay-icon{font-size:1.25rem;flex-shrink:0;}
.pay-name{font-size:.8rem;font-weight:600;color:#374151;}
.pay-sub{font-size:.65rem;color:#9ca3af;}
.osum{background:white;border-radius:1.25rem;border:1px solid #fce9e3;padding:1.25rem;box-shadow:0 1px 4px rgba(0,0,0,.05);position:sticky;top:80px;}
.os-item{display:flex;gap:.625rem;padding:.5rem 0;border-bottom:1px solid #fce9e3;}
.os-item:last-of-type{border-bottom:none;}
.os-img{width:40px;height:40px;border-radius:.5rem;background:linear-gradient(135deg,#fce9e3,#f5d4cb);display:flex;align-items:center;justify-content:center;font-size:1rem;flex-shrink:0;overflow:hidden;}
.os-img img{width:100%;height:100%;object-fit:cover;}
.os-name{font-size:.75rem;font-weight:500;color:#374151;}
.os-qty{font-size:.7rem;color:#9ca3af;}
.os-price{font-weight:700;font-size:.8125rem;color:#953b22;margin-left:auto;white-space:nowrap;}
.os-row{display:flex;justify-content:space-between;padding:.375rem 0;font-size:.8125rem;color:#6b7280;}
.os-total{display:flex;justify-content:space-between;padding:.625rem 0;border-top:2px solid #fce9e3;margin-top:.5rem;font-weight:700;font-size:1rem;color:#1f2937;}
.os-total span:last-child{color:#953b22;}
.btn-order{width:100%;padding:.875rem;border:none;border-radius:.875rem;background:linear-gradient(135deg,#953b22,#9e5848);color:white;font-weight:700;font-size:.9375rem;cursor:pointer;transition:opacity .15s;margin-top:.875rem;}
.btn-order:hover{opacity:.9;}
.success-overlay{position:fixed;inset:0;background:rgba(0,0,0,.55);display:flex;align-items:center;justify-content:center;z-index:200;padding:1rem;}
.success-box{background:white;border-radius:1.5rem;padding:2rem;max-width:380px;width:100%;text-align:center;box-shadow:0 20px 60px rgba(0,0,0,.2);}
.addr-sel-card{border:1.5px solid #fce9e3;border-radius:.75rem;padding:.75rem 1rem;cursor:pointer;transition:all .15s;display:block;}
.addr-sel-card:hover{border-color:#dfb0a2;background:#fff8f6;}
.addr-sel-card.addr-sel-active{border-color:#953b22;background:#fff8f6;}
/* Payment method baru */
.pay-mode{border:2px solid #fce9e3;border-radius:1rem;padding:.875rem 1rem;cursor:pointer;transition:all .15s;background:white;margin-bottom:.625rem;display:block;width:100%;text-align:left;box-sizing:border-box;}
.pay-mode:hover{border-color:#dfb0a2;background:#fff8f6;}
.pay-mode.selected{border-color:#953b22;background:#fff8f6;}
.pay-mode-top{display:flex;align-items:center;gap:.75rem;}
.pay-mode-icon{font-size:1.5rem;flex-shrink:0;}
.pay-mode-title{font-size:.9rem;font-weight:700;color:#1f2937;}
.pay-mode-sub{font-size:.75rem;color:#9ca3af;margin-top:1px;}
.pay-mode-check{margin-left:auto;font-size:1.125rem;color:#953b22;display:none;}
.pay-mode.selected .pay-mode-check{display:block;}
.pay-badge{font-size:.65rem;font-weight:700;padding:.175rem .5rem;border-radius:.375rem;background:#f3f4f6;color:#374151;display:inline-block;margin:.25rem .2rem 0 0;}
.pay-badge.green{background:#f0fdf4;color:#15803d;}
.pay-badge.blue{background:#eff6ff;color:#1d4ed8;}
.pay-badge.purple{background:#faf5ff;color:#7c3aed;}
@media(max-width:680px){.cgrid{grid-template-columns:1fr;}.pay-opts{grid-template-columns:1fr 1fr;}.fgrid2{grid-template-columns:1fr;}.osum{position:static;}}
</style>

<?php if ($success && isset($_SESSION['last_order'])): $o = $_SESSION['last_order']; ?>
<div class="success-overlay">
    <div class="success-box">
        <div style="font-size:3.5rem;margin-bottom:.875rem;color:#953b22;"><i class="fas fa-gift"></i></div>
        <h2 style="font-size:1.25rem;font-weight:700;color:#1f2937;margin-bottom:.5rem;">Pesanan Berhasil!</h2>
        <p style="font-size:.875rem;color:#6b7280;margin-bottom:.5rem;">Terima kasih <?= htmlspecialchars($o['nama']) ?>!<br>Pesanan Anda sedang kami proses.</p>
        <div style="background:#fff8f6;border-radius:.75rem;padding:.5rem 1rem;font-size:.875rem;font-weight:700;color:#953b22;margin:1rem 0;"><?= $o['id'] ?></div>
        <div style="background:#f9fafb;border-radius:.75rem;padding:.75rem;font-size:.8125rem;color:#374151;text-align:left;margin-bottom:1rem;">
            <div style="display:flex;justify-content:space-between;margin-bottom:.375rem;"><span style="color:#9ca3af;">Tanggal</span><strong><?= $o['tgl'] ?></strong></div>
            <div style="display:flex;justify-content:space-between;margin-bottom:.375rem;"><span style="color:#9ca3af;">Pembayaran</span><strong><?= htmlspecialchars($o['bayar']) ?></strong></div>
            <div style="display:flex;justify-content:space-between;"><span style="color:#9ca3af;">Total</span><strong style="color:#953b22;"><?= fmt_rp((int)$o['total']) ?></strong></div>
        </div>
        <a href="profil.php?tab=pesanan" style="display:block;padding:.75rem;background:linear-gradient(135deg,#953b22,#9e5848);color:white;border-radius:.875rem;font-weight:700;font-size:.875rem;text-decoration:none;margin-bottom:.5rem;">
            <i class="fas fa-box"></i> Lihat Riwayat Pesanan
        </a>
        <a href="../katalog.php" style="display:block;padding:.5rem;color:#953b22;font-size:.8125rem;font-weight:500;text-decoration:none;">Lanjut Belanja →</a>
    </div>
</div>
<?php endif; ?>

<?php if (!$success && empty($cart)): ?>
<div style="text-align:center;padding:4rem 1rem;">
    <div style="font-size:3rem;margin-bottom:1rem;color:#953b22;"><i class="fas fa-shopping-cart"></i></div>
    <div style="font-weight:600;color:#953b22;font-size:1rem;margin-bottom:.5rem;">Keranjang Kosong</div>
    <a href="../katalog.php" style="display:inline-block;padding:.625rem 1.5rem;background:linear-gradient(135deg,#953b22,#9e5848);color:white;border-radius:9999px;font-weight:700;font-size:.875rem;text-decoration:none;">Lihat Produk</a>
</div>
<?php else: ?>
<div class="co-wrap">

    <!-- STEPS -->
    <div class="steps">
        <div class="step"><div class="step-num" style="background:#953b22;color:white;">✓</div><div class="step-lbl" style="color:#953b22;">Keranjang</div></div>
        <div class="step-line" style="background:#953b22;"></div>
        <div class="step"><div class="step-num" style="background:#953b22;color:white;">2</div><div class="step-lbl" style="color:#953b22;">Checkout</div></div>
        <div class="step-line"></div>
        <div class="step"><div class="step-num" style="background:#e5e7eb;color:#9ca3af;">3</div><div class="step-lbl" style="color:#9ca3af;">Selesai</div></div>
    </div>

    <div class="cgrid">
        <div>
            <form method="POST" action="checkout.php" id="checkout-form">
                <input type="hidden" name="pesan" value="1">
                <input type="hidden" name="co_lat" id="coLat" value="">
                <input type="hidden" name="co_lng" id="coLng" value="">
                <div class="fcard">
                    <h2><i class="fas fa-map-marker-alt"></i> Alamat Pengiriman</h2>

                    <?php if (!empty($alamat_tersimpan)): ?>
                    <!-- Pilih dari alamat tersimpan -->
                    <div style="margin-bottom:1rem;">
                        <div style="font-size:.75rem;font-weight:600;color:#6b7280;margin-bottom:.5rem;">Pilih Alamat Tersimpan</div>
                        <div id="addrCards" style="display:flex;flex-direction:column;gap:.5rem;">
                        <?php foreach ($alamat_tersimpan as $i => $al): ?>
                        <label class="addr-sel-card <?= $al['is_utama'] ? 'addr-sel-active' : '' ?>"
                               id="addrCard<?= $i ?>"
                               onclick="pilihAlamat(<?= $i ?>, this)">
                            <input type="radio" name="_pilih_alamat" value="<?= $i ?>" style="display:none;" <?= $al['is_utama'] ? 'checked' : '' ?>>
                            <div style="display:flex;align-items:center;gap:.5rem;margin-bottom:.25rem;">
                                <span style="font-size:.65rem;font-weight:700;padding:.15rem .5rem;border-radius:9999px;background:<?= $al['is_utama'] ? '#953b22' : '#fce9e3' ?>;color:<?= $al['is_utama'] ? 'white' : '#953b22' ?>;">
                                    <?= htmlspecialchars($al['label']) ?>
                                </span>
                                <?php if ($al['is_utama']): ?><span style="font-size:.65rem;color:#953b22;font-weight:600;"><i class="fas fa-star"></i> Utama</span><?php endif; ?>
                            </div>
                            <div style="font-size:.8125rem;font-weight:700;color:#1f2937;"><?= htmlspecialchars($al['nama_penerima']) ?></div>
                            <div style="font-size:.75rem;color:#9ca3af;"><?= htmlspecialchars($al['no_telepon']) ?></div>
                            <div style="font-size:.75rem;color:#374151;margin-top:.2rem;line-height:1.4;">
                                <?= htmlspecialchars($al['alamat']) ?>, <?= htmlspecialchars($al['kota']) ?>
                                <?php if ($al['kode_pos']): ?> <?= htmlspecialchars($al['kode_pos']) ?><?php endif; ?>
                            </div>
                        </label>
                        <?php endforeach; ?>
                        </div>
                        <a href="profil.php?tab=alamat" style="display:inline-flex;align-items:center;gap:.375rem;margin-top:.625rem;font-size:.75rem;font-weight:600;color:#953b22;text-decoration:none;">
                            <i class="fas fa-plus"></i> Tambah / Kelola Alamat
                        </a>
                    </div>
                    <div style="height:1px;background:#fce9e3;margin:.75rem 0;"></div>
                    <div style="font-size:.75rem;font-weight:600;color:#6b7280;margin-bottom:.5rem;">Detail Pengiriman</div>
                    <?php endif; ?>

                    <div class="fg"><label>Nama Penerima *</label><input type="text" name="nama" id="fNama" value="<?= $nama_val ?>" required></div>
                    <div class="fg"><label>Nomor WhatsApp *</label><input type="tel" name="telp" id="fTelp" value="<?= $telp_val ?>" required></div>
                    <div class="fg"><label>Alamat Lengkap *</label><textarea name="alamat" id="fAlamat" rows="3" required><?= $alamat_val ?></textarea></div>
                    <div class="fgrid2">
                        <div class="fg"><label>Kota / Kabupaten *</label><input type="text" name="kota" id="fKota" value="<?= $kota_val ?>" required></div>
                        <div class="fg"><label>Kode Pos</label><input type="text" name="kodepos" id="fKodepos"></div>
                    </div>

                    <?php if (!empty($alamat_tersimpan)): ?>
                    <button type="button" onclick="gunakanAlamatManual()" id="btnManual"
                        style="margin-top:.25rem;background:none;border:none;color:#9ca3af;font-size:.75rem;cursor:pointer;padding:0;text-decoration:underline;">
                        Masukkan alamat lain secara manual
                    </button>
                    <?php endif; ?>
                </div>
                <div class="fcard">
                    <h2><i class="fas fa-truck"></i> Pilih Ekspedisi</h2>
                    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:.5rem;">
                        <?php foreach ([['JNE','Regular','1-3 hari'],['J&T','Express','1-2 hari'],['SiCepat','HALU','2-4 hari']] as [$e,$t,$d]): ?>
                        <label style="border:2px solid #fce9e3;border-radius:.875rem;padding:.625rem;cursor:pointer;display:block;transition:all .15s;" onclick="selectExp(this)">
                            <input type="radio" name="ekspedisi" value="<?= $e ?>" style="display:none;">
                            <div style="font-weight:700;font-size:.875rem;color:#1f2937;"><?= $e ?></div>
                            <div style="font-size:.7rem;color:#9e5848;font-weight:600;"><?= $t ?></div>
                            <div style="font-size:.65rem;color:#9ca3af;"><?= $d ?></div>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="fcard">
                    <h2><i class="fas fa-wallet"></i> Metode Pembayaran</h2>

                    <!-- Bayar Online via Midtrans -->
                    <button type="button" class="pay-mode selected" id="payModeOnline" onclick="selectPayMode('online',this)">
                        <input type="radio" name="bayar" value="online" checked style="display:none;">
                        <div class="pay-mode-top">
                            <div class="pay-mode-icon-wrap" style="width:2.25rem;height:2.25rem;border-radius:.625rem;background:linear-gradient(135deg,#eff6ff,#dbeafe);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                <i class="fas fa-credit-card" style="color:#1d4ed8;font-size:1rem;"></i>
                            </div>
                            <div>
                                <div class="pay-mode-title">Bayar Online</div>
                                <div class="pay-mode-sub">Aman &amp; terkonfirmasi otomatis via Midtrans</div>
                            </div>
                            <i class="fas fa-check-circle pay-mode-check"></i>
                        </div>
                        <div style="margin-top:.625rem;padding-left:2.75rem;display:flex;flex-wrap:wrap;gap:.25rem;">
                            <span class="pay-badge green"><i class="fas fa-circle" style="font-size:.45rem;vertical-align:middle;margin-right:2px;"></i>GoPay</span>
                            <span class="pay-badge green"><i class="fas fa-circle" style="font-size:.45rem;vertical-align:middle;margin-right:2px;"></i>ShopeePay</span>
                            <span class="pay-badge blue">QRIS</span>
                            <span class="pay-badge blue">Transfer Bank</span>
                            <span class="pay-badge blue">Virtual Account</span>
                            <span class="pay-badge purple">Kartu Kredit</span>
                            <span class="pay-badge">Indomaret</span>
                            <span class="pay-badge">Alfamart</span>
                        </div>
                    </button>

                    <!-- COD -->
                    <button type="button" class="pay-mode" id="payModeCod" onclick="selectPayMode('cod',this)">
                        <input type="radio" name="bayar" value="COD (Tunai)" style="display:none;">
                        <div class="pay-mode-top">
                            <div class="pay-mode-icon-wrap" style="width:2.25rem;height:2.25rem;border-radius:.625rem;background:linear-gradient(135deg,#f0fdf4,#dcfce7);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                <i class="fas fa-hand-holding-usd" style="color:#15803d;font-size:1rem;"></i>
                            </div>
                            <div>
                                <div class="pay-mode-title">COD — Bayar di Tempat</div>
                                <div class="pay-mode-sub">Bayar tunai saat paket tiba · Hanya area tertentu</div>
                            </div>
                            <i class="fas fa-check-circle pay-mode-check"></i>
                        </div>
                        <div style="margin-top:.5rem;padding-left:2.75rem;">
                            <span class="pay-badge" style="background:#f0fdf4;color:#15803d;"><i class="fas fa-truck" style="margin-right:3px;"></i>Bayar saat kurir tiba</span>
                        </div>
                    </button>
                </div>
                <div class="fcard">
                    <h2><i class="fas fa-edit"></i> Catatan Pesanan</h2>
                    <div class="fg"><textarea name="catatan" rows="2" placeholder="Catatan opsional..."><?= $catatan ?></textarea></div>
                </div>
            </form>
        </div>

        <div>
            <div class="osum">
                <h2 style="font-weight:700;font-size:.9375rem;color:#1f2937;margin-bottom:.875rem;"><i class="fas fa-shopping-cart"></i> Ringkasan Pesanan</h2>
                <?php foreach ($cart as $item):
                    $pr = $pdo->prepare("SELECT gambar_produk FROM produk WHERE id_produk=?");
                    $pr->execute([$item['id']]); $row = $pr->fetch();
                    $gambar = $row['gambar_produk'] ?? '';
                ?>
                <div class="os-item">
                    <div class="os-img">
                        <?php if ($gambar): ?><img src="../assets/images/products/<?= htmlspecialchars($gambar) ?>" alt="">
                        <?php else: ?><i class="fas fa-tshirt" style="color:#dfb0a2;"></i><?php endif; ?>
                    </div>
                    <div style="flex:1;min-width:0;">
                        <div class="os-name"><?= htmlspecialchars(mb_substr($item['nama'],0,28)) ?><?= mb_strlen($item['nama'])>28?'…':'' ?></div>
                        <?php if (!empty($item['ukuran'])): ?><div class="os-qty">Ukuran: <?= htmlspecialchars($item['ukuran']) ?></div><?php endif; ?>
                        <div class="os-qty">Qty: <?= $item['qty'] ?></div>
                    </div>
                    <div class="os-price"><?= fmt_rp((int)($item['harga']*$item['qty'])) ?></div>
                </div>
                <?php endforeach; ?>
                <div class="os-total" style="margin-top:.625rem;"><span>Total Pembayaran</span><span><?= fmt_rp((int)$subtotal) ?></span></div>
                <button type="button" id="btnOrder" class="btn-order" onclick="submitOrder()"><i class="fas fa-lock"></i> BUAT PESANAN & BAYAR ONLINE</button>
                <div style="margin-top:.5rem;text-align:center;font-size:.65rem;color:#9ca3af;"><i class="fas fa-lock"></i> Transaksi Aman · <i class="fas fa-shield-alt"></i> Data Terlindungi</div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
// Data alamat tersimpan dari PHP
var savedAddresses = <?= json_encode(array_values($alamat_tersimpan ?? [])) ?>;

function pilihAlamat(idx, el) {
    document.querySelectorAll('.addr-sel-card').forEach(c => c.classList.remove('addr-sel-active'));
    el.classList.add('addr-sel-active');
    el.querySelector('input[type=radio]').checked = true;
    var a = savedAddresses[idx];
    if (!a) return;
    document.getElementById('fNama').value    = a.nama_penerima;
    document.getElementById('fTelp').value    = a.no_telepon;
    document.getElementById('fAlamat').value  = a.alamat;
    document.getElementById('fKota').value    = a.kota;
    document.getElementById('fKodepos').value = a.kode_pos || '';
    document.getElementById('coLat').value    = a.lat || '';
    document.getElementById('coLng').value    = a.lng || '';
    // Nonaktifkan edit field (readonly) karena sudah dari alamat tersimpan
    ['fNama','fTelp','fAlamat','fKota','fKodepos'].forEach(id => {
        var el2 = document.getElementById(id);
        if (el2) { el2.style.background='#f9fafb'; el2.readOnly = false; }
    });
}

function gunakanAlamatManual() {
    document.querySelectorAll('.addr-sel-card').forEach(c => c.classList.remove('addr-sel-active'));
    ['fNama','fTelp','fAlamat','fKota','fKodepos'].forEach(id => {
        var el = document.getElementById(id);
        if (el) { el.value=''; el.style.background='#fff8f6'; el.readOnly = false; }
    });
    document.getElementById('fNama').focus();
}

var _payMode = 'online';

function selectPayMode(mode, el) {
    _payMode = mode;
    document.querySelectorAll('.pay-mode').forEach(function(e) { e.classList.remove('selected'); });
    el.classList.add('selected');
    el.querySelector('input[type=radio]').checked = true;
    var btn = document.getElementById('btnOrder');
    if (mode === 'cod') {
        btn.innerHTML = '<i class="fas fa-hand-holding-usd"></i> PESAN DENGAN COD';
    } else {
        btn.innerHTML = '<i class="fas fa-lock"></i> BUAT PESANAN & BAYAR ONLINE';
    }
}

function submitOrder() {
    var nama   = (document.getElementById('fNama')  ?.value || '').trim();
    var telp   = (document.getElementById('fTelp')  ?.value || '').trim();
    var alamat = (document.getElementById('fAlamat')?.value || '').trim();
    var kota   = (document.getElementById('fKota')  ?.value || '').trim();

    if (!nama)   { alert('Nama penerima wajib diisi.');      document.getElementById('fNama').focus();   return; }
    if (!telp)   { alert('Nomor WhatsApp wajib diisi.');     document.getElementById('fTelp').focus();   return; }
    if (!alamat) { alert('Alamat lengkap wajib diisi.');     document.getElementById('fAlamat').focus(); return; }
    if (!kota)   { alert('Kota / Kabupaten wajib diisi.');   document.getElementById('fKota').focus();   return; }

    if (_payMode === 'cod') {
        document.getElementById('checkout-form').submit();
        return;
    }

    // Online payment via Midtrans
    var btn = document.getElementById('btnOrder');
    var origHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Memproses...';

    var payload = {
        nama:      nama,
        telp:      telp,
        alamat:    alamat,
        kota:      kota,
        kodepos:   (document.getElementById('fKodepos') ?.value || ''),
        ekspedisi: (document.querySelector('input[name=ekspedisi]:checked')?.value || ''),
        catatan:   (document.querySelector('textarea[name=catatan]')       ?.value || ''),
        co_lat:    (document.getElementById('coLat')?.value || ''),
        co_lng:    (document.getElementById('coLng')?.value || ''),
    };

    fetch('create_midtrans_order.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
    })
    .then(function(r) { return r.json(); })
    .then(function(res) {
        if (res.error) {
            alert('Gagal membuat pesanan:\n' + res.error);
            btn.disabled = false; btn.innerHTML = origHtml; return;
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
        alert('Terjadi kesalahan koneksi. Silakan coba lagi.');
        btn.disabled = false; btn.innerHTML = origHtml;
    });
}

function selectExp(lbl) {
    document.querySelectorAll('[onclick="selectExp(this)"]').forEach(l => {
        l.style.borderColor = '#fce9e3'; l.style.background = 'white';
    });
    lbl.style.borderColor = '#953b22'; lbl.style.background = '#fff8f6';
    lbl.querySelector('input[type=radio]').checked = true;
}
document.addEventListener('DOMContentLoaded', () => {
    // Set default online payment selected
    var online = document.getElementById('payModeOnline');
    if (online) online.classList.add('selected');
    const fe = document.querySelector('[onclick="selectExp(this)"]');
    if (fe) selectExp(fe);
    // Auto-pilih alamat utama jika ada
    var utama = document.querySelector('.addr-sel-card.addr-sel-active');
    if (utama) {
        var idx = Array.from(document.querySelectorAll('.addr-sel-card')).indexOf(utama);
        if (idx >= 0 && savedAddresses[idx]) pilihAlamat(idx, utama);
    }
});
</script>

<script src="<?= MIDTRANS_SNAP_JS_URL ?>" data-client-key="<?= MIDTRANS_CLIENT_KEY ?>"></script>
<?php require_once '../includes/footer.php'; ?>
