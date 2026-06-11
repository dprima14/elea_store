<?php
$activePage = 'beranda';
$pageTitle  = 'Beranda';
include 'includes/header.php';
?>

<!-- HERO -->
<section class="hero">
    <div class="hero-grid">
        <div>
            <h1 class="hero-title">
                Tampil Modis,<br>
                <span>Tampil Elegan.</span>
            </h1>
            <p class="hero-desc">
                Temukan keanggunan dalam setiap pilihan bersama koleksi fashion Elea Store — dirancang untuk semua yang dinamis dan percaya diri.
            </p>
            <div class="hero-buttons">
                <a href="katalog.php" class="btn btn-primary">LIHAT KATALOG</a>
                <a href="preorder.php" class="btn btn-outline">PRE ORDER</a>
            </div>
        </div>
        <div class="hero-visual">
            <div class="hero-card">
                <div class="hero-card-content">
                    <div class="hero-card-icon">
                        <img src="assets/images/kerudung2.png" alt="Koleksi Premium">
                    </div>
                    <div class="title">Koleksi Premium</div>
                    <div class="sub">Fashion for All</div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- INFO SINGKAT -->
<section class="section-py">
    <div class="container">
        <div class="info-singkat-grid">
            <div style="background:white;border-radius:1rem;padding:1.5rem;border:1px solid #fce9e3;box-shadow:0 1px 4px rgba(0,0,0,.05);">
                <div style="font-size:2rem;margin-bottom:.5rem;color:#7a2e22;"><i class="fas fa-leaf"></i></div>
                <h3 style="font-weight:700;color:#7a2e1a;margin-bottom:.375rem;">Desain Elegan</h3>
                <p style="font-size:.875rem;color:#6b7280;">Desain yang fleksibel, elegan, dan modern — sesuai selera dan kebutuhan Anda.</p>
            </div>
            <div style="background:white;border-radius:1rem;padding:1.5rem;border:1px solid #fce9e3;box-shadow:0 1px 4px rgba(0,0,0,.05);">
                <div style="font-size:2rem;margin-bottom:.5rem;color:#7a2e22;"><i class="fas fa-gem"></i></div>
                <h3 style="font-weight:700;color:#7a2e1a;margin-bottom:.375rem;">Bahan Premium</h3>
                <p style="font-size:.875rem;color:#6b7280;">Material berkualitas tinggi, nyaman dipakai sepanjang hari.</p>
            </div>
            <div style="background:white;border-radius:1rem;padding:1.5rem;border:1px solid #fce9e3;box-shadow:0 1px 4px rgba(0,0,0,.05);">
                <div style="font-size:2rem;margin-bottom:.5rem;color:#7a2e22;"><i class="fas fa-truck"></i></div>
                <h3 style="font-weight:700;color:#7a2e1a;margin-bottom:.375rem;">Pengiriman Cepat</h3>
                <p style="font-size:.875rem;color:#6b7280;">Dikirim ke seluruh Indonesia dengan ekspedisi terpercaya pilihan Anda.</p>
            </div>
        </div>
        <div class="text-center mt-8">
            <a href="katalog.php" class="btn btn-primary">BELANJA SEKARANG</a>
        </div>
    </div>
</section>

<?php include 'includes/footer.php'; ?>
