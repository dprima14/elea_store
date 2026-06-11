<?php
$activePage = 'tentang';
$pageTitle  = 'Tentang Kami';
include 'includes/header.php';
?>

<style>
.ab-hero{background:linear-gradient(135deg,#7a2e22 0%,#9e5848 50%,#c97d5e 100%);padding:4rem 1rem 3rem;text-align:center;color:white;}
.ab-hero h1{font-size:2.25rem;font-weight:800;margin-bottom:.75rem;line-height:1.2;}
.ab-hero p{font-size:1.0625rem;opacity:.9;max-width:600px;margin:0 auto;line-height:1.7;}
.ab-badge{display:inline-block;background:rgba(255,255,255,.18);border:1px solid rgba(255,255,255,.3);border-radius:9999px;padding:.35rem 1rem;font-size:.8rem;font-weight:700;letter-spacing:.04em;margin-bottom:1rem;}
.ab-stats{display:flex;justify-content:center;gap:2.5rem;flex-wrap:wrap;margin-top:2.5rem;padding-top:2rem;border-top:1px solid rgba(255,255,255,.2);}
.ab-stat{text-align:center;}
.ab-stat-val{font-size:2rem;font-weight:800;line-height:1;}
.ab-stat-lbl{font-size:.8rem;opacity:.8;margin-top:.25rem;}
.ab-section{max-width:900px;margin:0 auto;padding:3rem 1rem;}
.ab-promo-grid{display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;margin:2rem 0;}
.ab-promo-card{background:white;border-radius:1.25rem;border:1px solid #fce9e3;padding:1.5rem;box-shadow:0 2px 8px rgba(149,59,34,.07);position:relative;overflow:hidden;}
.ab-promo-card::before{content:'';position:absolute;top:0;left:0;width:4px;height:100%;background:linear-gradient(135deg,#953b22,#9e5848);}
.ab-promo-card h3{font-size:1rem;font-weight:700;color:#1f2937;margin-bottom:.5rem;}
.ab-promo-card p{font-size:.875rem;color:#6b7280;line-height:1.6;margin:0;}
.ab-promo-icon{font-size:2rem;margin-bottom:.75rem;}
.ab-highlight{background:linear-gradient(135deg,#fff8f6,#fce9e3);border-radius:1.5rem;padding:2.5rem;text-align:center;margin:2rem 0;border:1px solid #f5d4cb;}
.ab-highlight h2{font-size:1.5rem;font-weight:800;color:#7a2e22;margin-bottom:.75rem;}
.ab-highlight p{font-size:.9375rem;color:#4b5563;line-height:1.8;max-width:650px;margin:0 auto;}
.ab-why{display:grid;grid-template-columns:repeat(3,1fr);gap:1rem;margin:2rem 0;}
.ab-why-item{text-align:center;padding:1.25rem 1rem;background:white;border-radius:1rem;border:1px solid #fce9e3;box-shadow:0 1px 4px rgba(0,0,0,.05);}
.ab-why-icon{font-size:2rem;margin-bottom:.625rem;}
.ab-why-title{font-weight:700;font-size:.9rem;color:#1f2937;margin-bottom:.375rem;}
.ab-why-desc{font-size:.8rem;color:#9ca3af;line-height:1.5;}
.ab-cta{text-align:center;padding:2.5rem 1rem;background:white;border-radius:1.5rem;border:1px solid #fce9e3;margin-top:1.5rem;box-shadow:0 2px 8px rgba(149,59,34,.07);}
.ab-cta h2{font-size:1.375rem;font-weight:800;color:#7a2e22;margin-bottom:.625rem;}
.ab-cta p{font-size:.9rem;color:#6b7280;margin-bottom:1.5rem;}
.ab-btn{display:inline-block;padding:.875rem 2rem;background:linear-gradient(135deg,#953b22,#9e5848);color:white;border-radius:9999px;font-weight:700;font-size:.9375rem;text-decoration:none;transition:opacity .15s;margin:.25rem;}
.ab-btn:hover{opacity:.88;}
.ab-btn-out{background:white;color:#7a2e22;border:2px solid #7a2e22;}
.ab-btn-out:hover{background:#fff8f6;}
@media(max-width:640px){
  .ab-hero h1{font-size:1.625rem;}
  .ab-promo-grid{grid-template-columns:1fr;}
  .ab-why{grid-template-columns:1fr 1fr;}
  .ab-stats{gap:1.5rem;}
}
</style>

<!-- HERO -->
<div class="ab-hero">
    <div class="ab-badge">ELEA STORE — Fashion for All</div>
    <h1>Tampil Kece Tanpa Harus Mahal</h1>
    <p>Kami hadir untuk membuktikan bahwa fashion berkualitas tinggi bisa dinikmati semua kalangan. Harga terjangkau, bahan premium, desain kekinian — itulah Elea Store.</p>
    <div class="ab-stats">
        <div class="ab-stat"><div class="ab-stat-val">20 Jam</div><div class="ab-stat-lbl">Layanan per Hari</div></div>
    </div>
</div>

<div class="ab-section">

    <!-- HIGHLIGHT UTAMA -->
    <div class="ab-highlight">
        <h2>Kualitas Mewah, Harga Ramah di Kantong</h2>
        <p>
            Di Elea Store, kami percaya bahwa semua orang berhak tampil percaya diri tanpa harus mengorbankan budget. Setiap produk kami dirancang dengan bahan pilihan berkualitas tinggi — lembut, adem, tahan lama — namun kami jaga harganya tetap terjangkau agar bisa dinikmati semua kalangan.
        </p>
    </div>

    <!-- KEUNGGULAN -->
    <h2 style="font-size:1.25rem;font-weight:800;color:#7a2e22;text-align:center;margin-bottom:1.5rem;">Kenapa Pilih Elea Store?</h2>
    <div class="ab-promo-grid">
        <div class="ab-promo-card">
            <div class="ab-promo-icon"><i class="fas fa-tags" style="color:#953b22;"></i></div>
            <h3>Harga Paling Bersahabat</h3>
            <p>Nikmati fashion premium untuk semua dengan harga yang sangat terjangkau. Kami memangkas biaya distribusi agar penghematan langsung sampai ke tangan Anda.</p>
        </div>
        <div class="ab-promo-card">
            <div class="ab-promo-icon"><i class="fas fa-gem" style="color:#953b22;"></i></div>
            <h3>Bahan Premium, Kualitas Terjamin</h3>
            <p>Setiap koleksi menggunakan kain pilihan — sifon lembut, katun adem, dan bahan berkualitas tinggi yang nyaman dipakai seharian di iklim tropis Indonesia.</p>
        </div>
        <div class="ab-promo-card">
            <div class="ab-promo-icon"><i class="fas fa-leaf" style="color:#953b22;"></i></div>
            <h3>Desain Modern & Elegan</h3>
            <p>Koleksi kami hadir dengan desain kontemporer yang elegan, anggun, dan fleksibel — cocok untuk berbagai gaya, kebutuhan, dan aktivitas sehari-hari.</p>
        </div>
        <div class="ab-promo-card">
            <div class="ab-promo-icon"><i class="fas fa-flag" style="color:#953b22;"></i></div>
            <h3>Produk Lokal Berkualitas</h3>
            <p>Dibuat dengan bangga di Indonesia oleh tangan-tangan perajin lokal berpengalaman. Setiap jahitan dikerjakan dengan penuh ketelitian dan kontrol kualitas ketat.</p>
        </div>
    </div>

    <!-- KENAPA KAMI -->
    <div class="ab-why">
        <div class="ab-why-item">
            <div class="ab-why-icon"><i class="fas fa-shield-alt" style="color:#953b22;"></i></div>
            <div class="ab-why-title">Produk Original</div>
            <div class="ab-why-desc">Garansi 100% keaslian produk dari tangan kami langsung ke Anda</div>
        </div>
        <div class="ab-why-item">
            <div class="ab-why-icon"><i class="fas fa-sync-alt" style="color:#953b22;"></i></div>
            <div class="ab-why-title">7 Hari Retur</div>
            <div class="ab-why-desc">Tidak puas atau produk cacat? Kami siap mengganti tanpa ribet</div>
        </div>
        <div class="ab-why-item">
            <div class="ab-why-icon"><i class="fas fa-headset" style="color:#953b22;"></i></div>
            <div class="ab-why-title">CS 20 Jam Sehari</div>
            <div class="ab-why-desc">Tim kami siap melayani dari jam 05.00 pagi hingga 01.00 dini hari</div>
        </div>
        <div class="ab-why-item">
            <div class="ab-why-icon"><i class="fas fa-truck" style="color:#953b22;"></i></div>
            <div class="ab-why-title">Pengiriman Cepat</div>
            <div class="ab-why-desc">Pesanan diproses cepat dengan berbagai pilihan ekspedisi terpercaya</div>
        </div>
        <div class="ab-why-item">
            <div class="ab-why-icon"><i class="fas fa-credit-card" style="color:#953b22;"></i></div>
            <div class="ab-why-title">Pembayaran Aman</div>
            <div class="ab-why-desc">Transfer bank, e-wallet, QRIS, COD — semua tersedia dan aman</div>
        </div>
        <div class="ab-why-item">
            <div class="ab-why-icon"><i class="fas fa-star" style="color:#953b22;"></i></div>
            <div class="ab-why-title">Ribuan Ulasan Positif</div>
            <div class="ab-why-desc">Sudah dipercaya banyak pelanggan dari berbagai daerah di Indonesia</div>
        </div>
    </div>

    <!-- CTA -->
    <div class="ab-cta">
        <h2>Siap Tampil Cantik Hari Ini?</h2>
        <p>Jelajahi ratusan koleksi fashion kami dan temukan pilihan terbaik dengan harga yang tidak akan membuat dompet Anda menangis.</p>
        <a href="katalog.php" class="ab-btn"><i class="fas fa-tshirt"></i> Lihat Koleksi</a>
        <a href="kontak.php" class="ab-btn ab-btn-out"><i class="fab fa-whatsapp"></i> Hubungi Kami</a>
    </div>

</div>

<?php include 'includes/footer.php'; ?>
