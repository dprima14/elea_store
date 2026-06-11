<?php
session_start();
require_once '../config/db.php';

$user         = $_SESSION['user'] ?? null;
$is_pelanggan = $user && $user['role'] === 'pelanggan';
if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];

// Auto-migrate tabel ulasan
try { $pdo->exec("CREATE TABLE IF NOT EXISTS ulasan_produk (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_produk INT NOT NULL,
    id_user INT NOT NULL,
    nama_user VARCHAR(255) NOT NULL,
    rating TINYINT NOT NULL DEFAULT 5,
    ulasan TEXT,
    gambar VARCHAR(255) DEFAULT NULL,
    dibuat DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
)"); } catch (PDOException $e) {}

// KIRIM ULASAN
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['kirim_ulasan']) && $is_pelanggan) {
    $pid_ul  = intval($_POST['pid_ulasan'] ?? 0);
    $rating  = max(1, min(5, intval($_POST['rating'] ?? 5)));
    $teks    = trim($_POST['ulasan_text'] ?? '');
    $gambar_ul = null;

    if ($pid_ul) {
        if (!empty($_FILES['gambar_ulasan']['name'])) {
            $up_dir = '../assets/images/ulasan/';
            if (!is_dir($up_dir)) mkdir($up_dir, 0755, true);
            $ext = strtolower(pathinfo($_FILES['gambar_ulasan']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg','jpeg','png','webp']) && $_FILES['gambar_ulasan']['size'] < 5242880) {
                $fname = 'ul_'.time().'_'.rand(1000,9999).'.'.$ext;
                if (move_uploaded_file($_FILES['gambar_ulasan']['tmp_name'], $up_dir.$fname)) {
                    $gambar_ul = $fname;
                }
            }
        }
        $pdo->prepare("INSERT INTO ulasan_produk (id_produk, id_user, nama_user, rating, ulasan, gambar) VALUES (?,?,?,?,?,?)")
            ->execute([$pid_ul, $user['id_user'], $user['nama'], $rating, $teks, $gambar_ul]);
    }
    header('Location: produk.php?id='.$pid_ul.'&ulasan_ok=1'); exit;
}

// TAMBAH KE KERANJANG (butuh login)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['beli'])) {
    if (!$is_pelanggan) { header('Location: ../login.php'); exit; }

    $pid    = intval($_POST['pid']);
    $nama   = strip_tags($_POST['pnama'] ?? '');
    $hrg    = intval($_POST['phrg']);
    $ukuran = strip_tags($_POST['ukuran_pilih'] ?? '');

    // Cek stok terbaru dari DB
    $chk = $pdo->prepare("SELECT stok, ukuran FROM produk WHERE id_produk=?");
    $chk->execute([$pid]);
    $chk_prod = $chk->fetch();

    if (!$chk_prod || $chk_prod['stok'] <= 0) {
        header('Location: produk.php?id='.$pid.'&err=habis'); exit;
    }

    // Untuk produk dengan ukuran tertentu, cek stok ukuran tersebut
    $max_stok = (int)$chk_prod['stok'];
    if ($ukuran && $ukuran !== 'All Size'
        && !empty($chk_prod['ukuran']) && $chk_prod['ukuran'] !== 'all_size') {
        $uk_arr = json_decode($chk_prod['ukuran'], true) ?: [];
        $uk_stok = 0;
        foreach ($uk_arr as $u) {
            if ($u['uk'] === $ukuran) { $uk_stok = (int)($u['stok'] ?? 0); break; }
        }
        if ($uk_stok <= 0) {
            header('Location: produk.php?id='.$pid.'&err=habis'); exit;
        }
        $max_stok = $uk_stok;
    }

    // Hitung qty item ini yang sudah ada di keranjang
    $cart_qty = 0;
    foreach ($_SESSION['cart'] as $ci) {
        if ($ci['id'] === $pid && ($ci['ukuran'] ?? '') === $ukuran) {
            $cart_qty = $ci['qty']; break;
        }
    }
    if ($cart_qty >= $max_stok) {
        header('Location: produk.php?id='.$pid.'&err=max'); exit;
    }

    $found = false;
    foreach ($_SESSION['cart'] as &$item) {
        if ($item['id'] === $pid && ($item['ukuran'] ?? '') === $ukuran) {
            $item['qty']++;
            $found = true;
            break;
        }
    }
    unset($item);
    if (!$found) {
        $_SESSION['cart'][] = ['id'=>$pid, 'nama'=>$nama, 'harga'=>$hrg, 'qty'=>1, 'ukuran'=>$ukuran];
    }

    if (($_POST['action'] ?? '') === 'beli_sekarang') {
        header('Location: checkout.php'); exit;
    }
    header('Location: produk.php?id='.$pid.'&added=1'); exit;
}

// AMBIL DATA PRODUK
$id = intval($_GET['id'] ?? 0);
if (!$id) { header('Location: ../katalog.php'); exit; }

$stmt = $pdo->prepare("SELECT * FROM produk WHERE id_produk = ?");
$stmt->execute([$id]);
$p = $stmt->fetch();
if (!$p) { header('Location: ../katalog.php'); exit; }

// Gambar tambahan
$eg_stmt = $pdo->prepare("SELECT gambar FROM produk_gambar WHERE id_produk=? ORDER BY urutan, id");
$eg_stmt->execute([$id]);
$extra_imgs = array_column($eg_stmt->fetchAll(), 'gambar');
$all_images = [];
if (!empty($p['gambar_produk'])) $all_images[] = $p['gambar_produk'];
$all_images = array_merge($all_images, $extra_imgs);
if (empty($all_images)) $all_images[] = null;

// Parse ukuran
$ukuran_type   = 'none';
$ukuran_parsed = [];
if (!empty($p['ukuran'])) {
    if ($p['ukuran'] === 'all_size') {
        $ukuran_type = 'all_size';
    } else {
        $arr = json_decode($p['ukuran'], true);
        if ($arr) { $ukuran_parsed = $arr; $ukuran_type = 'sizes'; }
    }
}
$need_size = in_array($ukuran_type, ['all_size', 'sizes']);

// Produk terkait
$rel = $pdo->prepare("SELECT id_produk, nama_produk, harga, gambar_produk, jenis_produk FROM produk WHERE id_produk != ? AND stok > 0 ORDER BY RAND() LIMIT 4");
$rel->execute([$id]);
$related_list = $rel->fetchAll();

// Ulasan produk
$stmt_ul = $pdo->prepare("SELECT * FROM ulasan_produk WHERE id_produk=? ORDER BY dibuat DESC");
$stmt_ul->execute([$id]);
$ulasan_list  = $stmt_ul->fetchAll();
$ulasan_count = count($ulasan_list);
$avg_rating   = $ulasan_count ? round(array_sum(array_column($ulasan_list,'rating'))/$ulasan_count,1) : 0;

$sudah_ulasan = false;
if ($is_pelanggan) {
    $cek = $pdo->prepare("SELECT id FROM ulasan_produk WHERE id_produk=? AND id_user=?");
    $cek->execute([$id, $user['id_user']]);
    $sudah_ulasan = (bool)$cek->fetch();
}

$cart_count = array_sum(array_column($_SESSION['cart'], 'qty'));

$siteRoot   = '../';
$activePage = 'katalog';
$pageTitle  = $p['nama_produk'];
require_once '../includes/header.php';
?>

<style>
/* ===== IMAGE SLIDER ===== */
.prod-slider-wrap{position:relative;}
.prod-slider-main{border-radius:1.25rem;overflow:hidden;background:linear-gradient(145deg,#fce9e3,#f5d4cb);aspect-ratio:4/5;position:relative;cursor:pointer;border:1px solid #fce9e3;}
.prod-slider-track{display:flex;height:100%;transition:transform .35s cubic-bezier(.4,0,.2,1);will-change:transform;}
.prod-slide{min-width:100%;height:100%;display:flex;align-items:center;justify-content:center;overflow:hidden;flex-shrink:0;}
.prod-slide img{width:100%;height:100%;object-fit:cover;user-select:none;-webkit-user-drag:none;}
.sl-btn{position:absolute;top:50%;transform:translateY(-50%);background:rgba(255,255,255,.88);border:none;width:36px;height:36px;border-radius:50%;display:flex;align-items:center;justify-content:center;cursor:pointer;font-size:1.375rem;color:#374151;box-shadow:0 2px 8px rgba(0,0,0,.15);transition:all .15s;z-index:5;line-height:1;padding:0;}
.sl-btn:hover{background:white;box-shadow:0 4px 14px rgba(0,0,0,.2);}
.sl-prev{left:.625rem;}
.sl-next{right:.625rem;}
.sl-dots{position:absolute;bottom:.75rem;left:50%;transform:translateX(-50%);display:flex;gap:.4rem;z-index:5;}
.sl-dot{width:7px;height:7px;border-radius:50%;background:rgba(255,255,255,.55);cursor:pointer;transition:all .2s;border:none;padding:0;}
.sl-dot.active{background:white;transform:scale(1.3);}
.prod-sl-thumbs{display:flex;gap:.5rem;margin-top:.625rem;overflow-x:auto;padding-bottom:.25rem;scrollbar-width:none;}
.prod-sl-thumbs::-webkit-scrollbar{display:none;}
.prod-sl-thumb{width:60px;height:60px;border-radius:.625rem;overflow:hidden;cursor:pointer;border:2px solid transparent;transition:border-color .15s;flex-shrink:0;background:#f3f4f6;}
.prod-sl-thumb.active{border-color:#953b22;}
.prod-sl-thumb img{width:100%;height:100%;object-fit:cover;}
/* ======================== */
.prod-wrap{max-width:1080px;margin:0 auto;padding:1.5rem 1rem 3rem;}
.breadcrumb{font-size:.8rem;color:#9ca3af;margin-bottom:1.25rem;display:flex;align-items:center;gap:.375rem;flex-wrap:wrap;}
.breadcrumb a{color:#e69c7f;text-decoration:none;font-weight:500;}
.breadcrumb a:hover{color:#953b22;}
.prod-main{display:grid;grid-template-columns:1fr 1fr;gap:2.5rem;align-items:start;margin-bottom:2.5rem;}
.prod-img-area{border-radius:1.25rem;overflow:hidden;background:linear-gradient(145deg,#fce9e3,#f5d4cb);aspect-ratio:4/5;display:flex;align-items:center;justify-content:center;border:1px solid #fce9e3;position:relative;}
.prod-img-area img{width:100%;height:100%;object-fit:cover;}
.prod-badge-jenis{position:absolute;top:.75rem;left:.75rem;background:rgba(0,0,0,.45);color:white;font-size:.65rem;font-weight:700;padding:.25rem .625rem;border-radius:9999px;}
.prod-stok-badge{position:absolute;top:.75rem;right:.75rem;background:#dc2626;color:white;font-size:.65rem;font-weight:700;padding:.25rem .75rem;border-radius:9999px;}
.prod-title{font-size:1.625rem;font-weight:700;color:#1f2937;line-height:1.3;margin-bottom:.5rem;}
.prod-jenis{font-size:.75rem;font-weight:700;color:#9e5848;text-transform:uppercase;letter-spacing:.06em;margin-bottom:.625rem;}
.prod-price{font-size:2rem;font-weight:700;color:#953b22;margin:1rem 0;}
.divider-p{height:1px;background:#fce9e3;margin:.875rem 0;}
.spec-row{display:flex;gap:.625rem;margin-bottom:1rem;flex-wrap:wrap;}
.spec-chip{background:#fff8f6;border:1px solid #fce9e3;border-radius:.625rem;padding:.4rem .75rem;font-size:.75rem;color:#6b7280;}
.spec-chip strong{color:#1f2937;}
/* Ukuran */
.size-sec{margin-bottom:1.25rem;}
.size-sec-lbl{font-size:.8125rem;font-weight:700;color:#6b7280;margin-bottom:.625rem;}
.size-pills{display:flex;flex-wrap:wrap;gap:.5rem;}
.size-pill{padding:.5rem 1rem;border:2px solid #f5d4cb;border-radius:.625rem;font-size:.8125rem;font-weight:700;cursor:pointer;transition:all .15s;background:white;color:#374151;text-align:center;}
.size-pill:hover{border-color:#9e5848;color:#953b22;}
.size-pill.selected{border-color:#953b22;background:#fff8f6;color:#953b22;}
.size-pill-pjg{font-size:.6rem;font-weight:400;display:block;color:#9ca3af;margin-top:.1rem;}
.allsize-badge{display:inline-flex;align-items:center;gap:.375rem;background:#f0fdf4;border:1.5px solid #bbf7d0;color:#059669;border-radius:.625rem;padding:.5rem 1rem;font-size:.8125rem;font-weight:700;}
/* Buttons */
.btn-row{display:grid;grid-template-columns:1fr 1fr;gap:.625rem;margin-bottom:.875rem;}
.btn-cart{padding:.8rem;border:2px solid #7a2e22;border-radius:.875rem;background:white;color:#7a2e22;font-weight:700;font-size:.875rem;cursor:pointer;transition:all .15s;}
.btn-cart:hover{background:#fff8f6;}
.btn-buy{padding:.8rem;border:none;border-radius:.875rem;background:linear-gradient(135deg,#953b22,#9e5848);color:white;font-weight:700;font-size:.875rem;cursor:pointer;transition:opacity .15s;}
.btn-buy:hover{opacity:.9;}
.added-alert{background:#f0fdf4;border:1px solid #bbf7d0;border-radius:.75rem;padding:.625rem 1rem;font-size:.8rem;color:#15803d;margin-bottom:1rem;display:flex;align-items:center;gap:.5rem;}
/* Tabs */
.desc-card{background:white;border-radius:1.25rem;border:1px solid #fce9e3;padding:1.25rem 1.5rem;margin-bottom:2rem;box-shadow:0 1px 4px rgba(0,0,0,.05);}
.prod-tabs{display:flex;gap:0;border-bottom:2px solid #fce9e3;margin-bottom:1.25rem;}
.prod-tabs button{padding:.625rem 1.25rem;border:none;background:none;font-size:.8125rem;font-weight:600;color:#9ca3af;cursor:pointer;border-bottom:2px solid transparent;margin-bottom:-2px;transition:all .15s;}
.prod-tabs button.active{color:#953b22;border-bottom-color:#953b22;}
/* Related */
.rel-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:1rem;}
.rel-card{background:white;border-radius:1rem;border:1px solid #fce9e3;overflow:hidden;box-shadow:0 1px 4px rgba(0,0,0,.05);transition:all .2s;text-decoration:none;display:block;}
.rel-card:hover{box-shadow:0 6px 20px rgba(149,59,34,.1);transform:translateY(-3px);}
.rel-img{height:130px;background:linear-gradient(145deg,#fce9e3,#f5d4cb);display:flex;align-items:center;justify-content:center;overflow:hidden;}
.rel-img img{width:100%;height:100%;object-fit:cover;}
.rel-info{padding:.625rem .75rem;}
.rel-name{font-size:.75rem;font-weight:500;color:#374151;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;margin-bottom:.25rem;}
.rel-price{font-weight:700;font-size:.875rem;color:#953b22;}
.guarantees{display:flex;gap:.5rem;flex-wrap:wrap;margin-top:.75rem;}
.g-chip{display:flex;align-items:center;gap:.3rem;font-size:.7rem;color:#6b7280;background:white;padding:.35rem .625rem;border-radius:.5rem;border:1px solid #f3f4f6;}
@media(max-width:700px){.prod-main{grid-template-columns:1fr;gap:1.25rem;}.btn-row{grid-template-columns:1fr;}}
/* Ulasan */
.ul-summary{display:flex;align-items:center;gap:1.25rem;padding:.875rem 0 1.25rem;border-bottom:1px solid #fce9e3;margin-bottom:1.25rem;}
.ul-big-rating{font-size:2.5rem;font-weight:800;color:#953b22;line-height:1;}
.ul-stars-big{display:flex;gap:2px;font-size:1.125rem;}
.ul-total{font-size:.8rem;color:#9ca3af;margin-top:2px;}
.ul-card{border:1px solid #fce9e3;border-radius:.875rem;padding:1rem 1.125rem;margin-bottom:.75rem;background:white;}
.ul-card-head{display:flex;align-items:flex-start;justify-content:space-between;gap:.75rem;margin-bottom:.5rem;}
.ul-avatar{width:34px;height:34px;border-radius:50%;background:linear-gradient(135deg,#953b22,#9e5848);display:flex;align-items:center;justify-content:center;color:white;font-weight:700;font-size:.8125rem;flex-shrink:0;}
.ul-name{font-weight:700;font-size:.875rem;color:#1f2937;}
.ul-date{font-size:.7rem;color:#9ca3af;margin-top:1px;}
.ul-stars{display:flex;gap:1px;font-size:.875rem;}
.ul-teks{font-size:.875rem;color:#374151;line-height:1.65;}
.ul-img{margin-top:.75rem;border-radius:.625rem;max-width:200px;max-height:200px;object-fit:cover;cursor:pointer;border:1px solid #fce9e3;}
.ul-form{background:#fff8f6;border-radius:1rem;border:1px solid #fce9e3;padding:1.25rem;margin-top:1.25rem;}
.ul-form h4{font-weight:700;font-size:.9375rem;color:#1f2937;margin-bottom:1rem;}
.star-pick{display:flex;flex-direction:row-reverse;justify-content:flex-end;gap:.25rem;margin-bottom:.875rem;}
.star-pick input{display:none;}
.star-pick label{font-size:1.75rem;color:#e5e7eb;cursor:pointer;transition:color .1s;}
.star-pick input:checked ~ label,.star-pick label:hover,.star-pick label:hover ~ label{color:#f59e0b;}
.ul-fg label{display:block;font-size:.8rem;font-weight:600;color:#6b7280;margin-bottom:.375rem;}
.ul-fg textarea,.ul-fg input[type=file]{width:100%;padding:.625rem .875rem;border:1.5px solid #f5d4cb;border-radius:.625rem;font-size:.8125rem;font-family:inherit;background:#fff;box-sizing:border-box;outline:none;}
.ul-fg textarea:focus,.ul-fg input[type=file]:focus{border-color:#953b22;}
.ul-fg{margin-bottom:.875rem;}
.ul-submit{padding:.7rem 1.5rem;border:none;border-radius:.875rem;background:linear-gradient(135deg,#953b22,#9e5848);color:white;font-weight:700;font-size:.875rem;cursor:pointer;transition:opacity .15s;}
.ul-submit:hover{opacity:.9;}
.ul-img-modal{display:none;position:fixed;inset:0;background:rgba(0,0,0,.8);z-index:300;align-items:center;justify-content:center;padding:1rem;}
.ul-img-modal.open{display:flex;}
.ul-img-modal img{max-width:90vw;max-height:88vh;border-radius:1rem;object-fit:contain;}
</style>

<div class="prod-wrap">

    <div class="breadcrumb">
        <a href="../katalog.php">Katalog</a>
        <span>›</span>
        <?php if (!empty($p['jenis_produk'])): ?>
        <a href="../katalog.php?jenis=<?= urlencode($p['jenis_produk']) ?>"><?= htmlspecialchars($p['jenis_produk']) ?></a>
        <span>›</span>
        <?php endif; ?>
        <span style="color:#953b22;font-weight:500;"><?= htmlspecialchars(mb_substr($p['nama_produk'],0,50)) ?></span>
    </div>

    <?php if (!empty($_GET['added'])): ?>
    <div class="added-alert"><i class="fas fa-check-circle"></i> Produk ditambahkan ke keranjang! <a href="keranjang.php" style="color:#15803d;font-weight:700;margin-left:.25rem;">Lihat Keranjang →</a></div>
    <?php endif; ?>
    <?php if (!empty($_GET['ulasan_ok'])): ?>
    <div class="added-alert"><i class="fas fa-star"></i> Ulasan Anda berhasil dikirim. Terima kasih!</div>
    <?php endif; ?>
    <?php if (($_GET['err'] ?? '') === 'habis'): ?>
    <div style="background:#fef2f2;border:1px solid #fecaca;border-radius:.75rem;padding:.625rem 1rem;font-size:.8rem;color:#dc2626;margin-bottom:.75rem;"><i class="fas fa-exclamation-triangle"></i> Stok produk ini sudah habis.</div>
    <?php elseif (($_GET['err'] ?? '') === 'max'): ?>
    <div style="background:#fffbeb;border:1px solid #fde68a;border-radius:.75rem;padding:.625rem 1rem;font-size:.8rem;color:#92400e;margin-bottom:.75rem;"><i class="fas fa-exclamation-triangle"></i> Jumlah di keranjang sudah mencapai batas stok tersedia.</div>
    <?php endif; ?>

    <div class="prod-main">

        <!-- GAMBAR / SLIDER -->
        <div>
            <div class="prod-slider-wrap">
                <div class="prod-slider-main" id="slMain">
                    <div class="prod-slider-track" id="slTrack">
                        <?php foreach ($all_images as $img): ?>
                        <div class="prod-slide">
                            <?php if ($img): ?>
                            <img src="../assets/images/products/<?= htmlspecialchars($img) ?>"
                                 alt="<?= htmlspecialchars($p['nama_produk']) ?>">
                            <?php else: ?>
                            <svg viewBox="0 0 64 64" style="width:120px;height:120px;stroke:#dfb0a2;fill:none;opacity:.6;">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M32 6L6 18l4 8 4-2v26h36V24l4 2 4-8L32 6z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M24 24h16v24H24z"/>
                            </svg>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php if (count($all_images) > 1): ?>
                    <button class="sl-btn sl-prev" onclick="slMove(-1)" aria-label="Sebelumnya">&#8249;</button>
                    <button class="sl-btn sl-next" onclick="slMove(1)"  aria-label="Berikutnya">&#8250;</button>
                    <div class="sl-dots">
                        <?php foreach ($all_images as $si => $img): ?>
                        <button class="sl-dot <?= $si===0?'active':'' ?>" onclick="slGo(<?= $si ?>)"></button>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($p['jenis_produk'])): ?>
                    <span class="prod-badge-jenis"><?= htmlspecialchars($p['jenis_produk']) ?></span>
                    <?php endif; ?>
                    <?php if ($p['stok'] == 0): ?>
                    <span class="prod-stok-badge">STOK HABIS</span>
                    <?php endif; ?>
                </div>
                <?php if (count($all_images) > 1): ?>
                <div class="prod-sl-thumbs" id="slThumbs">
                    <?php foreach ($all_images as $si => $img): ?>
                    <div class="prod-sl-thumb <?= $si===0?'active':'' ?>" onclick="slGo(<?= $si ?>)">
                        <?php if ($img): ?>
                        <img src="../assets/images/products/<?= htmlspecialchars($img) ?>" alt="">
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- INFO -->
        <div>
            <?php if (!empty($p['jenis_produk'])): ?>
            <div class="prod-jenis"><?= htmlspecialchars($p['jenis_produk']) ?></div>
            <?php endif; ?>
            <h1 class="prod-title"><?= htmlspecialchars($p['nama_produk']) ?></h1>
            <div class="prod-price"><?= fmt_rp((int)$p['harga']) ?></div>
            <div class="divider-p"></div>

            <div class="spec-row">
                <div class="spec-chip"><i class="fas fa-box"></i> Stok: <strong style="color:<?= $p['stok']>0?'#059669':'#dc2626' ?>"><?= $p['stok'] > 0 ? $p['stok'].' unit' : 'Habis' ?></strong></div>
                <div class="spec-chip"><i class="fas fa-truck"></i> <strong>1-3 hari kerja</strong></div>
                <div class="spec-chip"><i class="fas fa-sync-alt"></i> <strong>7 hari retur</strong></div>
            </div>

            <!-- UKURAN -->
            <?php if ($ukuran_type === 'all_size'): ?>
            <div class="size-sec">
                <div class="size-sec-lbl">Ukuran:</div>
                <div class="allsize-badge"><i class="fas fa-check"></i> All Size (Muat semua ukuran)</div>
            </div>
            <?php elseif ($ukuran_type === 'sizes'): ?>
            <div class="size-sec">
                <div class="size-sec-lbl">Pilih Ukuran: <span id="lbl-ukuran" style="color:#953b22;font-weight:700;"></span></div>
                <div class="size-pills">
                    <?php foreach ($ukuran_parsed as $s): ?>
                    <button type="button" class="size-pill"
                            onclick="pilihUkuran('<?= htmlspecialchars($s['uk']) ?>', this)"
                            data-uk="<?= htmlspecialchars($s['uk']) ?>">
                        <?= htmlspecialchars($s['uk']) ?>
                        <?php if (!empty($s['pjg'])): ?>
                        <span class="size-pill-pjg"><?= $s['pjg'] ?> cm</span>
                        <?php endif; ?>
                    </button>
                    <?php endforeach; ?>
                </div>
                <div id="size-warn" style="display:none;font-size:.75rem;color:#dc2626;margin-top:.375rem;"><i class="fas fa-exclamation-triangle"></i> Pilih ukuran terlebih dahulu</div>
            </div>
            <?php endif; ?>

            <input type="hidden" id="ukuran-val"
                   value="<?= $ukuran_type === 'all_size' ? 'All Size' : '' ?>">

            <?php if ($p['stok'] > 0): ?>
                <form method="POST" action="produk.php?id=<?= $p['id_produk'] ?>" onsubmit="return checkUkuran()">
                    <input type="hidden" name="beli" value="1">
                    <input type="hidden" name="pid" value="<?= $p['id_produk'] ?>">
                    <input type="hidden" name="pnama" value="<?= htmlspecialchars($p['nama_produk']) ?>">
                    <input type="hidden" name="phrg" value="<?= $p['harga'] ?>">
                    <input type="hidden" name="ukuran_pilih" id="ukuran-input" value="<?= $ukuran_type==='all_size'?'All Size':'' ?>">
                    <div class="btn-row">
                        <button type="submit" name="action" value="keranjang" class="btn-cart"><i class="fas fa-shopping-cart"></i> + Keranjang</button>
                        <button type="submit" name="action" value="beli_sekarang" class="btn-buy"><i class="fas fa-bolt"></i> Beli Sekarang</button>
                    </div>
                </form>
            <?php else: ?>
            <div style="padding:.875rem;background:#fef2f2;border-radius:.875rem;text-align:center;color:#dc2626;font-weight:600;font-size:.875rem;border:1.5px solid #fecaca;">
                Stok Habis — Cek kembali nanti atau Pre Order
            </div>
            <?php endif; ?>

            <div class="guarantees">
                <div class="g-chip"><i class="fas fa-sync-alt"></i> 7 Hari Pengembalian</div>
                <div class="g-chip"><i class="fas fa-shield-alt"></i> Produk Original</div>
                <div class="g-chip"><i class="fas fa-headset"></i> CS 20 Jam</div>
            </div>
        </div>
    </div>

    <!-- DESKRIPSI & PENGIRIMAN -->
    <div class="desc-card">
        <div class="prod-tabs">
            <button class="active" onclick="switchTab(this,'tab-desc')"><i class="fas fa-clipboard-list"></i> Deskripsi</button>
            <button onclick="switchTab(this,'tab-kirim')"><i class="fas fa-truck"></i> Pengiriman</button>
            <button onclick="switchTab(this,'tab-ulasan')"><i class="fas fa-star"></i> Ulasan<?= $ulasan_count > 0 ? " ($ulasan_count)" : '' ?></button>
        </div>
        <div id="tab-desc">
            <?php if (!empty($p['deskripsi_produk'])): ?>
            <p style="color:#4b5563;font-size:.875rem;line-height:1.8;"><?= nl2br(htmlspecialchars($p['deskripsi_produk'])) ?></p>
            <?php else: ?>
            <p style="color:#9ca3af;font-size:.875rem;">Deskripsi produk belum tersedia.</p>
            <?php endif; ?>
        </div>
        <div id="tab-kirim" style="display:none;">
            <?php foreach ([
                ['<i class="fas fa-box"></i>',             'Estimasi Tiba','1-3 hari kerja (J&T, JNE, SiCepat tersedia).'],
                ['<i class="fas fa-sync-alt"></i>',        'Retur Mudah','7 hari retur jika produk cacat atau tidak sesuai pesanan.'],
                ['<i class="fas fa-map-marker-alt"></i>',  'Lacak Paket','Nomor resi otomatis dikirim ke WhatsApp setelah verifikasi.'],
                ['<i class="fas fa-headset"></i>',         'CS Siap Bantu','Layanan pelanggan aktif setiap hari 05.00 – 01.00 WIB.'],
            ] as [$ic,$jd,$kt]): ?>
            <div style="display:flex;gap:.875rem;padding:.75rem 0;border-bottom:1px solid #fce9e3;">
                <span style="font-size:1.5rem;flex-shrink:0;"><?= $ic ?></span>
                <div><strong style="font-size:.875rem;color:#1f2937;"><?= $jd ?></strong>
                <p style="font-size:.8125rem;color:#6b7280;margin-top:.2rem;"><?= $kt ?></p></div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- TAB ULASAN -->
        <div id="tab-ulasan" style="display:none;">

            <!-- Ringkasan rating -->
            <?php if ($ulasan_count > 0): ?>
            <div class="ul-summary">
                <div>
                    <div class="ul-big-rating"><?= $avg_rating ?></div>
                    <div class="ul-stars-big">
                        <?php for ($s=1;$s<=5;$s++): ?>
                        <i class="fas fa-star" style="color:<?= $s<=$avg_rating?'#f59e0b':'#e5e7eb'?>;"></i>
                        <?php endfor; ?>
                    </div>
                    <div class="ul-total"><?= $ulasan_count ?> ulasan</div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Daftar ulasan -->
            <?php if (empty($ulasan_list)): ?>
            <div style="text-align:center;padding:2rem 1rem;color:#9ca3af;">
                <i class="fas fa-star" style="font-size:2rem;margin-bottom:.5rem;display:block;"></i>
                Belum ada ulasan untuk produk ini. Jadilah yang pertama!
            </div>
            <?php else: ?>
            <?php foreach ($ulasan_list as $ul): ?>
            <div class="ul-card">
                <div class="ul-card-head">
                    <div style="display:flex;align-items:flex-start;gap:.625rem;">
                        <div class="ul-avatar"><?= mb_strtoupper(mb_substr($ul['nama_user'],0,1)) ?></div>
                        <div>
                            <div class="ul-name"><?= htmlspecialchars($ul['nama_user']) ?></div>
                            <div class="ul-date"><?= date('d M Y', strtotime($ul['dibuat'])) ?></div>
                        </div>
                    </div>
                    <div class="ul-stars">
                        <?php for ($s=1;$s<=5;$s++): ?>
                        <i class="fas fa-star" style="color:<?= $s<=(int)$ul['rating']?'#f59e0b':'#e5e7eb'?>;"></i>
                        <?php endfor; ?>
                    </div>
                </div>
                <?php if (!empty($ul['ulasan'])): ?>
                <div class="ul-teks"><?= nl2br(htmlspecialchars($ul['ulasan'])) ?></div>
                <?php endif; ?>
                <?php if (!empty($ul['gambar'])): ?>
                <img src="../assets/images/ulasan/<?= htmlspecialchars($ul['gambar']) ?>"
                     class="ul-img"
                     onclick="bukaGambarUlasan(this.src)"
                     alt="Foto ulasan">
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>

            <!-- Form tulis ulasan -->
            <?php if ($is_pelanggan && !$sudah_ulasan): ?>
            <div class="ul-form">
                <h4><i class="fas fa-pencil-alt" style="color:#953b22;margin-right:.375rem;"></i> Tulis Ulasan Anda</h4>
                <form method="POST" action="produk.php?id=<?= $p['id_produk'] ?>" enctype="multipart/form-data">
                    <input type="hidden" name="kirim_ulasan" value="1">
                    <input type="hidden" name="pid_ulasan" value="<?= $p['id_produk'] ?>">

                    <div class="ul-fg">
                        <label>Rating *</label>
                        <div class="star-pick" id="starPick">
                            <?php for ($s=5;$s>=1;$s--): ?>
                            <input type="radio" name="rating" id="star<?= $s ?>" value="<?= $s ?>" <?= $s===5?'checked':'' ?>>
                            <label for="star<?= $s ?>"><i class="fas fa-star"></i></label>
                            <?php endfor; ?>
                        </div>
                    </div>

                    <div class="ul-fg">
                        <label>Ulasan <span style="color:#9ca3af;font-weight:400;">(opsional jika ada foto)</span></label>
                        <textarea name="ulasan_text" rows="4" placeholder="Bagikan pengalaman Anda menggunakan produk ini..."></textarea>
                    </div>

                    <div class="ul-fg">
                        <label><i class="fas fa-image" style="margin-right:4px;color:#953b22;"></i> Foto Produk <span style="color:#9ca3af;font-weight:400;">(opsional, maks. 5 MB)</span></label>
                        <input type="file" name="gambar_ulasan" accept="image/jpeg,image/png,image/webp">
                        <div style="font-size:.7rem;color:#9ca3af;margin-top:.3rem;">Format: JPG, PNG, WebP</div>
                    </div>

                    <button type="submit" class="ul-submit"><i class="fas fa-paper-plane"></i> Kirim Ulasan</button>
                </form>
            </div>
            <?php elseif ($is_pelanggan && $sudah_ulasan): ?>
            <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:.875rem;padding:.875rem 1rem;font-size:.875rem;color:#15803d;margin-top:1rem;">
                <i class="fas fa-check-circle"></i> Anda sudah memberikan ulasan untuk produk ini.
            </div>
            <?php else: ?>
            <div style="background:#fff8f6;border:1px solid #fce9e3;border-radius:.875rem;padding:.875rem 1rem;font-size:.875rem;color:#953b22;margin-top:1rem;">
                <a href="../login.php" style="color:#953b22;font-weight:700;">Masuk</a> untuk memberikan ulasan pada produk ini.
            </div>
            <?php endif; ?>

        </div>
    </div>

    <!-- PRODUK TERKAIT -->
    <?php if (!empty($related_list)): ?>
    <div>
        <h2 style="font-weight:700;color:#7a2e1a;font-size:1rem;margin-bottom:1rem;"><i class="fas fa-star"></i> Produk Lainnya</h2>
        <div class="rel-grid">
            <?php foreach ($related_list as $r): ?>
            <a href="produk.php?id=<?= $r['id_produk'] ?>" class="rel-card">
                <div class="rel-img">
                    <?php if (!empty($r['gambar_produk'])): ?>
                    <img src="../assets/images/products/<?= htmlspecialchars($r['gambar_produk']) ?>" alt="">
                    <?php else: ?>
                    <svg viewBox="0 0 24 24" style="width:40px;height:40px;stroke:#dfb0a2;fill:none;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    <?php endif; ?>
                </div>
                <div class="rel-info">
                    <div class="rel-name"><?= htmlspecialchars($r['nama_produk']) ?></div>
                    <div class="rel-price"><?= fmt_rp((int)$r['harga']) ?></div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
// ===== SLIDER =====
var slIdx = 0, slTotal = <?= count($all_images) ?>;
var slTrack = document.getElementById('slTrack');
function slMove(dir) { slGo((slIdx + dir + slTotal) % slTotal); }
function slGo(idx) {
    slIdx = idx;
    if (slTrack) slTrack.style.transform = 'translateX(-' + (idx * 100) + '%)';
    document.querySelectorAll('.sl-dot').forEach(function(d,i){ d.classList.toggle('active', i===idx); });
    document.querySelectorAll('.prod-sl-thumb').forEach(function(t,i){
        t.classList.toggle('active', i===idx);
        if (i===idx) t.scrollIntoView({inline:'nearest', behavior:'smooth', block:'nearest'});
    });
}
if (slTotal > 1) {
    var slMainEl = document.getElementById('slMain');
    var slTouchX = null;
    slMainEl.addEventListener('touchstart', function(e){ slTouchX = e.touches[0].clientX; }, {passive:true});
    slMainEl.addEventListener('touchend', function(e){
        if (slTouchX === null) return;
        var dx = e.changedTouches[0].clientX - slTouchX;
        if (Math.abs(dx) > 40) slMove(dx < 0 ? 1 : -1);
        slTouchX = null;
    });
    document.addEventListener('keydown', function(e){
        if (e.key === 'ArrowLeft')  slMove(-1);
        if (e.key === 'ArrowRight') slMove(1);
    });
}
// ==================

var selectedSize = <?= $ukuran_type === 'all_size' ? "'All Size'" : "''" ?>;
var needSize     = <?= ($ukuran_type === 'sizes') ? 'true' : 'false' ?>;

function pilihUkuran(uk, btn) {
    selectedSize = uk;
    document.getElementById('ukuran-val').value   = uk;
    document.getElementById('ukuran-input').value = uk;
    document.querySelectorAll('.size-pill').forEach(b => b.classList.remove('selected'));
    btn.classList.add('selected');
    document.getElementById('lbl-ukuran').textContent = uk;
    document.getElementById('size-warn').style.display = 'none';
}

function checkUkuran() {
    if (needSize && !selectedSize) {
        document.getElementById('size-warn').style.display = 'block';
        document.querySelector('.size-sec').scrollIntoView({behavior:'smooth',block:'center'});
        return false;
    }
    return true;
}

function switchTab(btn, id) {
    document.querySelectorAll('.prod-tabs button').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    ['tab-desc','tab-kirim','tab-ulasan'].forEach(t => {
        document.getElementById(t).style.display = t === id ? 'block' : 'none';
    });
}

function bukaGambarUlasan(src) {
    var m = document.getElementById('ulImgModal');
    m.querySelector('img').src = src;
    m.classList.add('open');
}
document.addEventListener('click', function(e) {
    var m = document.getElementById('ulImgModal');
    if (m && e.target === m) m.classList.remove('open');
});
<?php if (!empty($_GET['ulasan_ok'])): ?>
document.addEventListener('DOMContentLoaded', function(){
    var btn = document.querySelector('.prod-tabs button:nth-child(3)');
    if (btn) btn.click();
});
<?php endif; ?>
</script>

<!-- Modal foto ulasan -->
<div class="ul-img-modal" id="ulImgModal">
    <img src="" alt="Foto ulasan">
</div>

<?php require_once '../includes/footer.php'; ?>
