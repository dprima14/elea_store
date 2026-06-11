<?php
require_once 'config/db.php';

$activePage = 'kontak';
$pageTitle = 'Kontak';

// Auto-migrate tabel pesan_kontak
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS pesan_kontak (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nama VARCHAR(255) NOT NULL,
        email VARCHAR(255) NOT NULL,
        pesan TEXT NOT NULL,
        sudah_dibaca TINYINT(1) NOT NULL DEFAULT 0,
        dibuat DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    )");
} catch (PDOException $e) {}

$sent = false;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama  = trim($_POST['nama'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $pesan = trim($_POST['pesan'] ?? '');

    if (empty($nama))  $errors[] = 'Nama wajib diisi.';
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Email tidak valid.';
    if (empty($pesan)) $errors[] = 'Pesan wajib diisi.';

    if (empty($errors)) {
        $pdo->prepare("INSERT INTO pesan_kontak (nama, email, pesan) VALUES (?, ?, ?)")
            ->execute([$nama, $email, $pesan]);
        $sent = true;
    }
}

$contacts = [
    ['icon'=>'<i class="fas fa-mobile-alt"></i>',     'label'=>'WhatsApp','value'=>'+62 882-2035-7699','sub'=>'Aktif setiap hari, 05.00–01.00 WIB'],
    ['icon'=>'<i class="fas fa-envelope"></i>',        'label'=>'Email','value'=>'sintanurlaelaa@gmail.com','sub'=>'Balasan dalam 1x24 jam'],
    ['icon'=>'<i class="fas fa-map-marker-alt"></i>',  'label'=>'Alamat','value'=>'Blok Karang Mekar Gang Mekar Indah 4 RT.50 RW.21, Kel. Cigadung, Kec. Subang, Kab. Subang (41213)','sub'=>'Kunjungi toko kami di sini'],
    ['icon'=>'<i class="fas fa-clock"></i>',           'label'=>'Jam Operasional','value'=>'Setiap Hari','sub'=>'05.00 – 01.00 WIB'],
];

include 'includes/header.php';
?>

<div class="page-header">
    <h1>Hubungi Kami</h1>
    <p>Ada pertanyaan tentang produk, ukuran, atau kemitraan?</p>
    <p>Jangan ragu untuk mengirimkan pesan kepada kami.</p>
    <div class="divider"></div>
</div>

<section class="section-py">
    <div class="container">
        <div class="contact-grid">

            <!-- KIRI: INFO KONTAK -->
            <div>
                <h2 style="font-size:1.25rem;font-weight:700;color:var(--pink-900);margin-bottom:1.25rem;">Informasi Kontak</h2>
                <div class="contact-info-list">
                    <?php foreach ($contacts as $c): ?>
                    <div class="contact-info-item">
                        <div class="contact-icon"><?= $c['icon'] ?></div>
                        <div>
                            <div class="label"><?= $c['label'] ?></div>
                            <div class="value"><?= htmlspecialchars($c['value']) ?></div>
                            <div class="sub"><?= $c['sub'] ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <a href="https://wa.me/6288220357699" target="_blank" class="btn btn-green btn-block" style="border-radius:0.75rem;display:flex;align-items:center;justify-content:center;gap:0.625rem;">
                    <svg width="20" height="20" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/>
                    </svg>
                    CHAT VIA WHATSAPP
                </a>
            </div>

            <!-- KANAN: FORM -->
            <div class="contact-form-card">
                <h2>Kirim Pesan</h2>

                <?php if (!empty($errors)): ?>
                <div style="background:#fef2f2;border:1px solid #fecaca;border-radius:0.75rem;padding:0.75rem 1rem;margin-bottom:1rem;">
                    <?php foreach ($errors as $err): ?>
                    <div style="font-size:0.8125rem;color:#dc2626;"><i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($err) ?></div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <?php if ($sent): ?>
                <div class="form-success">
                    <div class="success-icon"><i class="fas fa-check-circle"></i></div>
                    <h3>Pesan Terkirim!</h3>
                    <p>Tim kami akan menghubungi Anda segera.</p>
                    <a href="kontak.php" class="btn btn-primary mt-6" style="margin-top:1.5rem;">Kirim Pesan Lagi</a>
                </div>
                <?php else: ?>
                <form method="POST" action="kontak.php">
                    <div class="form-group">
                        <label for="nama">Nama Lengkap</label>
                        <input type="text" id="nama" name="nama" placeholder="Nama lengkap Anda" value="<?= htmlspecialchars($_POST['nama'] ?? '') ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" placeholder="nama@email.com" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="pesan">Pesan</label>
                        <textarea id="pesan" name="pesan" rows="5" placeholder="Tulis pesan Anda di sini..." required><?= htmlspecialchars($_POST['pesan'] ?? '') ?></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary btn-block" style="border-radius:0.75rem;">KIRIM PESAN</button>
                </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<?php include 'includes/footer.php'; ?>
