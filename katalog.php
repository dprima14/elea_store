<?php
$activePage = 'katalog';
$pageTitle  = 'Katalog Produk';
require_once 'config/db.php';

$search = trim($_GET['search'] ?? '');
$sort   = $_GET['sort'] ?? 'terbaru';
$jenis  = trim($_GET['jenis'] ?? '');

// Ambil daftar jenis yang ada di database
$jenis_list = $pdo->query(
    "SELECT DISTINCT jenis_produk FROM produk WHERE jenis_produk IS NOT NULL AND jenis_produk != '' ORDER BY jenis_produk"
)->fetchAll(PDO::FETCH_COLUMN);

// Query produk
$sql    = "SELECT id_produk, nama_produk, harga, stok, gambar_produk, jenis_produk FROM produk WHERE 1=1";
$params = [];

if ($search !== '') {
    $sql .= " AND nama_produk LIKE ?";
    $params[] = '%' . $search . '%';
}
if ($jenis !== '') {
    $sql .= " AND jenis_produk = ?";
    $params[] = $jenis;
}
switch ($sort) {
    case 'harga-asc':  $sql .= " ORDER BY harga ASC"; break;
    case 'harga-desc': $sql .= " ORDER BY harga DESC"; break;
    default:           $sql .= " ORDER BY id_produk DESC"; break;
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

include 'includes/header.php';
?>

<div class="page-header">
    <h1>Katalog Produk</h1>
    <p>Jelajahi seluruh koleksi fashion for all pilihan kami.</p>
    <p>Temukan gaya elegan Anda berikutnya.</p>
    <div class="divider"></div>
</div>

<section class="section-py">
    <div class="container">

        <!-- FILTER JENIS -->
        <?php if (!empty($jenis_list)): ?>
        <div class="jenis-filter">
            <?php
            $baseUrl = 'katalog.php?' . ($search ? 'search='.urlencode($search).'&' : '') . ($sort !== 'terbaru' ? 'sort='.urlencode($sort).'&' : '');
            ?>
            <a href="<?= rtrim($baseUrl,'&?') ?: 'katalog.php' ?>"
               class="jenis-pill <?= $jenis === '' ? 'active' : '' ?>">Semua</a>
            <?php foreach ($jenis_list as $j): ?>
            <a href="<?= $baseUrl ?>jenis=<?= urlencode($j) ?>"
               class="jenis-pill <?= $jenis === $j ? 'active' : '' ?>"><?= htmlspecialchars($j) ?></a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- SEARCH & SORT -->
        <form method="GET" action="katalog.php" class="search-bar">
            <?php if ($jenis !== ''): ?>
            <input type="hidden" name="jenis" value="<?= htmlspecialchars($jenis) ?>">
            <?php endif; ?>
            <div class="search-row">
                <div class="search-input">
                    <svg viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    <input type="text" name="search" placeholder="Cari produk..." value="<?= htmlspecialchars($search) ?>">
                </div>
                <select name="sort" class="search-select" onchange="this.form.submit()">
                    <option value="terbaru"    <?= $sort==='terbaru'    ?'selected':'' ?>>Terbaru</option>
                    <option value="harga-asc"  <?= $sort==='harga-asc'  ?'selected':'' ?>>Harga Terendah</option>
                    <option value="harga-desc" <?= $sort==='harga-desc' ?'selected':'' ?>>Harga Tertinggi</option>
                </select>
                <button type="submit" class="btn btn-primary" style="padding:.625rem 1.25rem;border-radius:.75rem;font-size:.8125rem;">Cari</button>
            </div>
        </form>

        <div class="results-count">
            Menampilkan <strong><?= count($products) ?></strong> produk
            <?php if ($jenis !== ''): ?>
            · <span style="color:var(--pink-600);font-weight:600;"><?= htmlspecialchars($jenis) ?></span>
            <a href="katalog.php<?= $search ? '?search='.urlencode($search) : '' ?>" style="font-size:.75rem;color:#9ca3af;margin-left:.375rem;">× Hapus filter</a>
            <?php endif; ?>
        </div>

        <div class="products-grid">
            <?php foreach ($products as $p): ?>
            <div class="product-card">
                <div class="product-image">
                    <?php if (!empty($p['gambar_produk'])): ?>
                    <img src="assets/images/products/<?= htmlspecialchars($p['gambar_produk']) ?>"
                         alt="<?= htmlspecialchars($p['nama_produk']) ?>"
                         style="width:100%;height:100%;object-fit:cover;">
                    <?php else: ?>
                    <svg viewBox="0 0 24 24" style="width:64px;height:64px;stroke:#dfb0a2;fill:none;">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                    <?php endif; ?>
                    <?php if ($p['stok'] == 0): ?>
                    <span class="product-badge" style="background:#dc2626;">HABIS</span>
                    <?php elseif (!empty($p['jenis_produk'])): ?>
                    <span class="product-badge" style="background:rgba(0,0,0,.45);font-size:.6rem;"><?= htmlspecialchars($p['jenis_produk']) ?></span>
                    <?php endif; ?>
                </div>
                <div class="product-info">
                    <div class="product-name"><?= htmlspecialchars($p['nama_produk']) ?></div>
                    <div class="product-meta">
                        <span style="font-size:.7rem;color:#9ca3af;">Stok: <?= $p['stok'] ?></span>
                    </div>
                    <div class="product-price"><?= fmt_rp((int)$p['harga']) ?></div>
                    <a href="pelanggan/produk.php?id=<?= $p['id_produk'] ?>"
                       class="product-btn" style="display:block;text-align:center;text-decoration:none;">DETAIL PRODUK</a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <?php if (empty($products)): ?>
        <div class="text-center" style="padding:3rem 0;color:var(--pink-400);">
            <div style="font-size:2.5rem;margin-bottom:1rem;color:#9e5848;"><i class="fas fa-search"></i></div>
            <div style="font-weight:600;font-size:1.125rem;">Produk tidak ditemukan</div>
            <div style="font-size:.875rem;margin-top:.5rem;">Coba ubah kata kunci atau filter.</div>
            <a href="katalog.php" class="btn btn-primary" style="margin-top:1.5rem;display:inline-block;">Lihat Semua Produk</a>
        </div>
        <?php endif; ?>
    </div>
</section>

<style>
.jenis-filter{display:flex;flex-wrap:wrap;gap:.5rem;margin-bottom:1.25rem;}
.jenis-pill{padding:.4rem 1rem;border-radius:9999px;font-size:.8125rem;font-weight:600;border:1.5px solid #f5d4cb;color:#7a2e22;background:white;text-decoration:none;transition:all .15s;}
.jenis-pill:hover{background:#fce9e3;}
.jenis-pill.active{background:linear-gradient(135deg,#953b22,#9e5848);color:white;border-color:transparent;}
.results-count{font-size:.8125rem;color:#6b7280;margin-bottom:1rem;}
</style>

<?php include 'includes/footer.php'; ?>
