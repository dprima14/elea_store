<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'pelanggan') {
    header('Location: ../login.php'); exit;
}
if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];
$user = $_SESSION['user'];

// Hapus item
if (isset($_GET['hapus'])) {
    $hid = intval($_GET['hapus']);
    $huk = $_GET['uk'] ?? '';
    $_SESSION['cart'] = array_values(array_filter(
        $_SESSION['cart'],
        fn($c) => !($c['id'] === $hid && ($c['ukuran'] ?? '') === $huk)
    ));
    header('Location: keranjang.php'); exit;
}

// Update qty
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_qty'])) {
    foreach (($_POST['qty'] ?? []) as $k => $v) {
        $q = max(1, intval($v));
        if (isset($_SESSION['cart'][$k])) $_SESSION['cart'][$k]['qty'] = $q;
    }
    header('Location: keranjang.php'); exit;
}

$cart      = $_SESSION['cart'];
$subtotal  = array_sum(array_map(fn($c) => $c['harga'] * $c['qty'], $cart));
$cart_count = array_sum(array_column($cart, 'qty'));

$siteRoot   = '../';
$activePage = '';
$pageTitle  = 'Keranjang Belanja';
require_once '../includes/header.php';
?>

<style>
.kw{max-width:960px;margin:0 auto;padding:2rem 1rem 3rem;}
.kw h1{font-size:1.25rem;font-weight:700;color:#7a2e1a;margin-bottom:1.5rem;}
.k-grid{display:grid;grid-template-columns:1fr 320px;gap:1.25rem;align-items:start;}
.k-card{background:white;border-radius:1rem;border:1px solid #fce9e3;padding:1.25rem;box-shadow:0 1px 4px rgba(0,0,0,.05);}
.cart-item{display:flex;align-items:center;gap:.875rem;padding:.875rem 0;border-bottom:1px solid #fce9e3;}
.cart-item:last-of-type{border-bottom:none;}
.ci-img{width:60px;height:60px;border-radius:.625rem;background:linear-gradient(135deg,#fce9e3,#f5d4cb);display:flex;align-items:center;justify-content:center;font-size:1.5rem;flex-shrink:0;overflow:hidden;}
.ci-img img{width:100%;height:100%;object-fit:cover;}
.ci-name{font-weight:600;font-size:.875rem;color:#1f2937;margin-bottom:.2rem;}
.ci-size{font-size:.7rem;color:#7a2e22;background:#fff8f6;padding:.1rem .4rem;border-radius:.375rem;display:inline-block;margin-bottom:.25rem;}
.ci-price{font-size:.8125rem;font-weight:700;color:#7a2e22;}
.ci-qty-ctrl{display:flex;align-items:center;gap:0;border:1.5px solid #f5d4cb;border-radius:.5rem;overflow:hidden;margin-top:.375rem;}
.ci-qty-ctrl button{width:28px;height:28px;border:none;background:white;color:#7a2e22;font-weight:700;cursor:pointer;font-size:1rem;display:flex;align-items:center;justify-content:center;}
.ci-qty-ctrl button:hover{background:#fff8f6;}
.ci-qty-ctrl span{width:36px;text-align:center;font-weight:700;font-size:.8125rem;}
.ci-del{background:#fef2f2;border:none;color:#dc2626;border-radius:.375rem;padding:.25rem .625rem;font-size:.7rem;font-weight:600;cursor:pointer;margin-left:auto;white-space:nowrap;text-decoration:none;align-self:flex-start;}
.ci-del:hover{background:#fee2e2;}
.sum-card{background:white;border-radius:1rem;border:1px solid #fce9e3;padding:1.25rem;box-shadow:0 1px 4px rgba(0,0,0,.05);position:sticky;top:80px;}
.sum-row{display:flex;justify-content:space-between;font-size:.8125rem;color:#6b7280;padding:.375rem 0;}
.sum-total{display:flex;justify-content:space-between;font-weight:700;font-size:1.0625rem;color:#1f2937;padding:.625rem 0;border-top:2px solid #fce9e3;margin-top:.5rem;}
.sum-total span:last-child{color:#7a2e22;}
.btn-checkout{display:block;width:100%;padding:.8rem;border:none;border-radius:.875rem;background:linear-gradient(135deg,#953b22,#9e5848);color:white;font-weight:700;font-size:.9375rem;cursor:pointer;text-align:center;text-decoration:none;margin-top:.875rem;transition:opacity .15s;}
.btn-checkout:hover{opacity:.9;}
.empty-cart{text-align:center;padding:4rem 1rem;color:#dfb0a2;}
@media(max-width:680px){.k-grid{grid-template-columns:1fr;}.sum-card{position:static;}}
</style>

<div class="kw">
    <h1><i class="fas fa-shopping-cart"></i> Keranjang Belanja</h1>

    <?php if (empty($cart)): ?>
    <div class="empty-cart">
        <div style="font-size:3rem;margin-bottom:1rem;color:#7a2e22;"><i class="fas fa-shopping-cart"></i></div>
        <div style="font-weight:600;font-size:1rem;color:#7a2e22;margin-bottom:.5rem;">Keranjang masih kosong</div>
        <p style="font-size:.875rem;margin-bottom:1.5rem;">Temukan produk pilihan Anda di katalog.</p>
        <a href="../katalog.php" style="display:inline-block;padding:.625rem 1.75rem;background:linear-gradient(135deg,#953b22,#9e5848);color:white;border-radius:9999px;font-weight:700;font-size:.875rem;text-decoration:none;">Lihat Katalog</a>
    </div>
    <?php else: ?>
    <div class="k-grid">
        <!-- Daftar Item -->
        <div class="k-card">
            <form method="POST" action="keranjang.php" id="qty-form">
                <input type="hidden" name="update_qty" value="1">
                <?php foreach ($cart as $i => $item): ?>
                <div class="cart-item">
                    <div class="ci-img">
                        <?php
                        $gambar = '';
                        $pr = $pdo->prepare("SELECT gambar_produk FROM produk WHERE id_produk=?");
                        $pr->execute([$item['id']]);
                        $row = $pr->fetch();
                        $gambar = $row['gambar_produk'] ?? '';
                        ?>
                        <?php if ($gambar): ?>
                        <img src="../assets/images/products/<?= htmlspecialchars($gambar) ?>" alt="">
                        <?php else: ?><i class="fas fa-tshirt" style="color:#dfb0a2;"></i><?php endif; ?>
                    </div>
                    <div style="flex:1;min-width:0;">
                        <div class="ci-name"><?= htmlspecialchars($item['nama']) ?></div>
                        <?php if (!empty($item['ukuran'])): ?>
                        <span class="ci-size">Ukuran: <?= htmlspecialchars($item['ukuran']) ?></span>
                        <?php endif; ?>
                        <div class="ci-price"><?= fmt_rp((int)$item['harga']) ?> / pcs</div>
                        <div class="ci-qty-ctrl">
                            <button type="button" onclick="adjQty(<?= $i ?>,-1,<?= (int)$item['harga'] ?>)">−</button>
                            <span id="qty-<?= $i ?>"><?= $item['qty'] ?></span>
                            <button type="button" onclick="adjQty(<?= $i ?>,1,<?= (int)$item['harga'] ?>)">+</button>
                        </div>
                        <input type="hidden" name="qty[<?= $i ?>]" id="qin-<?= $i ?>" value="<?= $item['qty'] ?>">
                    </div>
                    <div style="display:flex;flex-direction:column;align-items:flex-end;gap:.375rem;">
                        <span style="font-weight:700;font-size:.875rem;color:#1f2937;" id="sub-<?= $i ?>"><?= fmt_rp((int)($item['harga']*$item['qty'])) ?></span>
                        <a href="keranjang.php?hapus=<?= $item['id'] ?>&uk=<?= urlencode($item['ukuran']??'') ?>"
                           class="ci-del" onclick="return confirm('Hapus produk ini?')">Hapus</a>
                    </div>
                </div>
                <?php endforeach; ?>
            </form>
            <div style="margin-top:.875rem;display:flex;gap:.5rem;">
                <button type="submit" form="qty-form" style="padding:.45rem 1rem;border:1.5px solid #f5d4cb;border-radius:.625rem;background:white;color:#7a2e22;font-size:.8125rem;font-weight:600;cursor:pointer;">Perbarui Keranjang</button>
                <a href="../katalog.php" style="padding:.45rem 1rem;border:1.5px solid #f5d4cb;border-radius:.625rem;background:white;color:#6b7280;font-size:.8125rem;font-weight:600;text-decoration:none;">← Lanjut Belanja</a>
            </div>
        </div>

        <!-- Ringkasan -->
        <div class="sum-card">
            <div style="font-weight:700;font-size:.9375rem;color:#7a2e1a;margin-bottom:.875rem;">Ringkasan Pesanan</div>
            <?php foreach ($cart as $item): ?>
            <div class="sum-row">
                <span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:140px;"><?= htmlspecialchars(mb_substr($item['nama'],0,22)).(mb_strlen($item['nama'])>22?'…':'') ?><?= !empty($item['ukuran']) ? ' ('.$item['ukuran'].')' : '' ?></span>
                <span style="flex-shrink:0;font-weight:500;"><?= fmt_rp((int)($item['harga']*$item['qty'])) ?></span>
            </div>
            <?php endforeach; ?>
            <div class="sum-total"><span>Total</span><span id="grand-total"><?= fmt_rp((int)$subtotal) ?></span></div>
            <a href="checkout.php" class="btn-checkout"><i class="fas fa-check"></i> LANJUT CHECKOUT</a>
            <div style="margin-top:.75rem;text-align:center;font-size:.65rem;color:#9ca3af;"><i class="fas fa-lock"></i> Transaksi Aman · <i class="fas fa-shield-alt"></i> Data Terlindungi</div>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
var prices = <?= json_encode(array_column($cart, 'harga')) ?>;
var qtys   = <?= json_encode(array_column($cart, 'qty')) ?>;

function adjQty(i, d, price) {
    qtys[i] = Math.max(1, qtys[i] + d);
    document.getElementById('qty-' + i).textContent = qtys[i];
    document.getElementById('qin-' + i).value = qtys[i];
    var sub = price * qtys[i];
    document.getElementById('sub-' + i).textContent = 'Rp ' + sub.toLocaleString('id-ID');
    var total = prices.reduce((a, p, k) => a + p * qtys[k], 0);
    document.getElementById('grand-total').textContent = 'Rp ' + total.toLocaleString('id-ID');
}
</script>

<?php require_once '../includes/footer.php'; ?>
