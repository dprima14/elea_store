<?php
$activePage = 'preorder';
$pageTitle  = 'Pre Order';
include 'includes/header.php';
require_once 'config/db.php';

// Auto-migrate
foreach ([
    "ALTER TABLE preorder MODIFY id_produk INT(11) NULL",
    "ALTER TABLE preorder MODIFY id_user INT(11) NULL",
    "ALTER TABLE preorder MODIFY status ENUM('pending','diproses','selesai','dibatalkan','dikonfirmasi','ditolak') NOT NULL DEFAULT 'pending'",
    "ALTER TABLE preorder ADD COLUMN nama VARCHAR(255) NULL AFTER status",
    "ALTER TABLE preorder ADD COLUMN no_wa VARCHAR(20) NULL AFTER nama",
    "ALTER TABLE preorder ADD COLUMN jenis_preorder ENUM('katalog','custom') NOT NULL DEFAULT 'katalog' AFTER no_wa",
    "ALTER TABLE preorder ADD COLUMN jenis_produk VARCHAR(100) NULL AFTER jenis_preorder",
    "ALTER TABLE preorder ADD COLUMN jumlah INT(11) NOT NULL DEFAULT 1 AFTER jenis_produk",
    "ALTER TABLE preorder ADD COLUMN ukuran VARCHAR(100) NULL AFTER jumlah",
    "ALTER TABLE preorder ADD COLUMN permintaan_custom TEXT NULL AFTER ukuran",
    "ALTER TABLE preorder ADD COLUMN file_referensi VARCHAR(255) NULL AFTER permintaan_custom",
    "ALTER TABLE preorder ADD COLUMN tanggal_dibutuhkan DATE NULL AFTER file_referensi",
    "ALTER TABLE preorder ADD COLUMN catatan TEXT NULL AFTER tanggal_dibutuhkan",
] as $q) { try { $pdo->exec($q); } catch (PDOException $e) {} }

// Ambil produk untuk dropdown (semua, untuk JS filter)
$produk_list = $pdo->query(
    "SELECT id_produk, nama_produk, jenis_produk FROM produk ORDER BY jenis_produk, nama_produk"
)->fetchAll();

// Kumpulkan jenis produk unik dari katalog
$jenis_di_katalog = array_unique(array_filter(array_column($produk_list, 'jenis_produk')));
sort($jenis_di_katalog);

$msg_ok  = '';
$msg_err = '';

// Hanya pelanggan login yang bisa submit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_preorder'])) {
    if (!$is_pelanggan) {
        header('Location: login.php?redirect=preorder.php');
        exit;
    }

    $id_user        = intval($_SESSION['user']['id_user']);
    $no_wa_input    = trim($_POST['no_wa'] ?? '');
    $jenis_po       = ($_POST['jenis_preorder'] ?? '') === 'custom' ? 'custom' : 'katalog';
    $id_produk      = $jenis_po === 'katalog' ? intval($_POST['id_produk'] ?? 0) : null;
    if ($id_produk === 0) $id_produk = null;
    $jenis_produk   = trim($_POST['jenis_produk'] ?? '');
    $jumlah         = max(1, intval($_POST['jumlah'] ?? 1));
    $ukuran         = trim($_POST['ukuran'] ?? '');
    $perm_custom    = trim($_POST['permintaan_custom'] ?? '');
    $tgl_dibutuhkan = !empty($_POST['tanggal_dibutuhkan']) ? $_POST['tanggal_dibutuhkan'] : null;
    $catatan        = trim($_POST['catatan'] ?? '');
    $nama_simpan    = $_SESSION['user']['nama'];

    $file_referensi = null;
    if (!empty($_FILES['file_referensi']['name']) && $_FILES['file_referensi']['error'] === UPLOAD_ERR_OK) {
        $ext     = strtolower(pathinfo($_FILES['file_referensi']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg','jpeg','png','gif','webp'];
        if (!in_array($ext, $allowed)) {
            $msg_err = 'File referensi harus berupa gambar (jpg, png, gif, webp).';
        } elseif ($_FILES['file_referensi']['size'] > 5 * 1024 * 1024) {
            $msg_err = 'Ukuran file referensi maksimal 5MB.';
        } else {
            $upload_dir = __DIR__ . '/assets/uploads/preorder/';
            $file_referensi = 'ref_' . time() . '_' . uniqid() . '.' . $ext;
            move_uploaded_file($_FILES['file_referensi']['tmp_name'], $upload_dir . $file_referensi);
        }
    }

    if (empty($msg_err)) {
        $stmt = $pdo->prepare(
            "INSERT INTO preorder
             (id_user, id_produk, nama, no_wa, jenis_preorder, jenis_produk,
              jumlah, ukuran, permintaan_custom, file_referensi, tanggal_dibutuhkan, catatan, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')"
        );
        $stmt->execute([
            $id_user, $id_produk, $nama_simpan, $no_wa_input,
            $jenis_po, $jenis_produk, $jumlah, $ukuran,
            $perm_custom, $file_referensi, $tgl_dibutuhkan, $catatan,
        ]);
        $msg_ok = 'Pre order Anda berhasil dikirim! Tim kami akan segera menghubungi Anda via WhatsApp.';
    }
}
?>

<div class="page-header">
    <h1>Pre Order Elea Store</h1>
    <p>Pesan koleksi eksklusif sebelum kehabisan slot.</p>
    <div class="divider"></div>
</div>

<section class="section-py">
    <div class="container">

        <div class="section-title">
            <h2>Cara Pre Order</h2>
        </div>
        <div class="steps-grid">
            <div class="step-card" style="background:#f8d9bc;border-color:#e8a67a;">
                <div class="step-header">
                    <div class="step-num">01</div>
                    <div class="step-icon"><i class="fas fa-mobile-alt"></i></div>
                </div>
                <h3>Pilih Metode</h3>
                <p>Hubungi via WhatsApp untuk bantuan personal, atau isi form pre order langsung di bawah ini.</p>
            </div>
            <div class="step-card" style="background:#fbecde;border-color:#d07a55;">
                <div class="step-header">
                    <div class="step-num">02</div>
                    <div class="step-icon"><i class="fas fa-credit-card"></i></div>
                </div>
                <h3>Bayar DP 50%</h3>
                <p>Bayar uang muka minimal 50% untuk mengamankan pesanan. Pelunasan saat produk siap kirim.</p>
            </div>
            <div class="step-card" style="background:#fef5ee;border-color:#f2c49a;">
                <div class="step-header">
                    <div class="step-num">03</div>
                    <div class="step-icon"><i class="fas fa-box"></i></div>
                </div>
                <h3>Produk Dikirim</h3>
                <p>Setelah produksi selesai (estimasi 14–28 hari), pesanan langsung dikirim ke alamat Anda.</p>
            </div>
        </div>

        <div class="section-title" style="margin-top:2.5rem;">
            <h2>Pilih Metode Pre Order</h2>
            <p>Pilih cara yang paling nyaman bagi Anda untuk melakukan pemesanan.</p>
        </div>

        <?php if ($msg_ok): ?>
        <div class="po-alert-ok"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($msg_ok) ?></div>
        <?php endif; ?>
        <?php if ($msg_err): ?>
        <div class="po-alert-err"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($msg_err) ?></div>
        <?php endif; ?>

        <div class="po-methods-grid">

            <!-- KIRI: WHATSAPP -->
            <div class="po-method-card po-wa-card">
                <div class="po-method-badge"><i class="fas fa-mobile-alt"></i> Quick Access</div>
                <h3>Personal Shopping Via WhatsApp</h3>
                <p>Nikmati bantuan personal dari admin kami. Kami akan membantu Anda memilih ukuran, warna, hingga memberikan saran gaya yang tepat sesuai kebutuhan Anda.</p>
                <ul class="po-wa-benefits">
                    <li><i class="fas fa-check"></i> Respon cepat dari admin</li>
                    <li><i class="fas fa-check"></i> Konsultasi pilihan produk</li>
                    <li><i class="fas fa-check"></i> Panduan ukuran personal</li>
                    <li><i class="fas fa-check"></i> Info ketersediaan real-time</li>
                </ul>
                <a href="https://wa.me/628120000000" target="_blank" class="btn-po-wa">
                    <i class="fab fa-whatsapp"></i> HUBUNGI WHATSAPP
                </a>
            </div>

            <!-- KANAN: FORM PRE ORDER -->
            <div class="po-method-card po-form-card">
                <div class="po-method-badge po-badge-form"><i class="fas fa-file-alt"></i> Official Form</div>
                <h3>Pre-Order Form</h3>

                <?php if (!$is_pelanggan): ?>
                <!-- Belum login -->
                <div class="po-login-required">
                    <div class="po-login-icon"><i class="fas fa-lock"></i></div>
                    <h4>Login Diperlukan</h4>
                    <p>Anda harus masuk ke akun pelanggan terlebih dahulu untuk mengisi form pre order.</p>
                    <div class="po-login-btns">
                        <a href="login.php?redirect=preorder.php" class="btn-po-submit" style="text-decoration:none;display:inline-flex;">
                            <i class="fas fa-sign-in-alt"></i> Masuk Sekarang
                        </a>
                        <a href="register.php" class="btn-po-register">Belum punya akun? Daftar</a>
                    </div>
                </div>

                <?php else: ?>
                <!-- Sudah login: tampilkan form -->
                <form method="POST" enctype="multipart/form-data" class="po-form" id="poForm">

                    <div class="po-user-info">
                        <i class="fas fa-user-circle"></i>
                        <span>Memesan sebagai <strong><?= htmlspecialchars($_SESSION['user']['nama']) ?></strong></span>
                    </div>

                    <!-- NOMOR WHATSAPP -->
                    <div class="po-form-group">
                        <label>Nomor WhatsApp <span class="req">*</span></label>
                        <input type="text" name="no_wa" placeholder="Contoh: 08123456789" required>
                        <small>Nomor ini akan kami gunakan untuk konfirmasi pesanan.</small>
                    </div>

                    <!-- JENIS PRE ORDER -->
                    <div class="po-form-group">
                        <label>Jenis Pre Order <span class="req">*</span></label>
                        <div class="po-radio-group">
                            <label class="po-radio-opt">
                                <input type="radio" name="jenis_preorder" value="katalog" checked onchange="toggleJenisPO('katalog')">
                                <span><i class="fas fa-tag"></i> Produk dari Katalog</span>
                            </label>
                            <label class="po-radio-opt">
                                <input type="radio" name="jenis_preorder" value="custom" onchange="toggleJenisPO('custom')">
                                <span><i class="fas fa-paint-brush"></i> Custom / Desain Baru</span>
                            </label>
                        </div>
                    </div>

                    <!-- SECTION KATALOG -->
                    <div id="sectionKatalog">
                        <!-- FILTER JENIS PRODUK -->
                        <div class="po-form-group">
                            <label>Filter Jenis Produk</label>
                            <div class="po-filter-tabs" id="filterTabs">
                                <button type="button" class="po-filter-tab active" onclick="filterProduk('')">Semua</button>
                                <?php foreach ($jenis_di_katalog as $j): ?>
                                <button type="button" class="po-filter-tab" onclick="filterProduk('<?= htmlspecialchars($j) ?>')"><?= htmlspecialchars($j) ?></button>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <!-- PILIH PRODUK -->
                        <div class="po-form-group">
                            <label>Pilih Produk dari Katalog</label>
                            <select name="id_produk" id="selectProduk">
                                <option value="">-- Pilih produk --</option>
                                <?php foreach ($produk_list as $p): ?>
                                <option value="<?= $p['id_produk'] ?>"
                                        data-jenis="<?= htmlspecialchars($p['jenis_produk'] ?? '') ?>">
                                    <?= htmlspecialchars($p['nama_produk']) ?>
                                    <?php if ($p['jenis_produk']): ?>(<?= htmlspecialchars($p['jenis_produk']) ?>)<?php endif; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <small>Pilih produk dari katalog yang ingin Anda custom (warna, ukuran, dll).</small>
                        </div>
                    </div>

                    <!-- JENIS PRODUK (untuk custom) -->
                    <div class="po-form-group" id="fieldJenisProdukCustom" style="display:none;">
                        <label>Jenis Produk <span class="req">*</span></label>
                        <select name="jenis_produk">
                            <option value="">-- Pilih jenis --</option>
                            <?php foreach (['Gamis','Hijab','Tunik','Rok','Celana','Outer/Cardigan','Aksesoris','Lainnya'] as $j): ?>
                            <option value="<?= $j ?>"><?= $j ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="po-form-row">
                        <div class="po-form-group">
                            <label>Jumlah Pesanan <span class="req">*</span></label>
                            <input type="number" name="jumlah" value="1" min="1" max="999" required>
                        </div>
                        <div class="po-form-group">
                            <label>Ukuran</label>
                            <select name="ukuran">
                                <option value="">-- Pilih ukuran --</option>
                                <?php foreach (['S','M','L','XL','XXL','XXXL','Free Size','All Size'] as $u): ?>
                                <option value="<?= $u ?>"><?= $u ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="po-form-group">
                        <label>Permintaan Custom</label>
                        <textarea name="permintaan_custom" rows="3" placeholder="Contoh: warna hitam, lengan panjang, motif bunga kecil..."></textarea>
                    </div>

                    <div class="po-form-group">
                        <label>Upload Referensi <span class="po-optional">(opsional)</span></label>
                        <div class="po-file-wrap">
                            <input type="file" name="file_referensi" id="fileRef" accept="image/*" onchange="previewFile(this)">
                            <label for="fileRef" class="po-file-label">
                                <i class="fas fa-cloud-upload-alt"></i>
                                <span id="fileRefName">Pilih gambar referensi (maks. 5MB)</span>
                            </label>
                        </div>
                        <div id="filePreview" style="display:none;margin-top:.5rem;">
                            <img id="filePreviewImg" src="" alt="Preview" style="max-height:120px;border-radius:.5rem;border:1px solid #fce9e3;">
                        </div>
                    </div>

                    <div class="po-form-group">
                        <label>Tanggal Dibutuhkan</label>
                        <input type="date" name="tanggal_dibutuhkan" min="<?= date('Y-m-d', strtotime('+14 days')) ?>">
                        <small>Estimasi pengerjaan 14–28 hari kerja.</small>
                    </div>

                    <div class="po-form-group">
                        <label>Catatan Tambahan</label>
                        <textarea name="catatan" rows="2" placeholder="Catatan lain untuk tim kami..."></textarea>
                    </div>

                    <button type="submit" name="submit_preorder" class="btn-po-submit">
                        <i class="fas fa-paper-plane"></i> KIRIM PRE ORDER
                    </button>
                </form>
                <?php endif; ?>

            </div><!-- /.po-form-card -->
        </div><!-- /.po-methods-grid -->

    </div>
</section>

<script>
// Data produk dari PHP untuk filter JS
var produkData = <?= json_encode($produk_list) ?>;

function toggleJenisPO(val) {
    var sKatalog = document.getElementById('sectionKatalog');
    var fCustom  = document.getElementById('fieldJenisProdukCustom');
    if (!sKatalog) return;
    if (val === 'katalog') {
        sKatalog.style.display = 'block';
        if (fCustom) fCustom.style.display = 'none';
    } else {
        sKatalog.style.display = 'none';
        if (fCustom) fCustom.style.display = 'block';
    }
}

function filterProduk(jenis) {
    // Tandai tab aktif
    document.querySelectorAll('.po-filter-tab').forEach(function(btn) {
        btn.classList.remove('active');
        if (btn.getAttribute('onclick') === "filterProduk('" + jenis + "')") {
            btn.classList.add('active');
        }
    });
    if (jenis === '') {
        // Semua tab
        document.querySelector('.po-filter-tab').classList.add('active');
    }

    var select = document.getElementById('selectProduk');
    if (!select) return;
    var current = select.value;
    // Hapus semua option kecuali yang pertama
    while (select.options.length > 1) select.remove(1);
    // Isi ulang sesuai filter
    produkData.forEach(function(p) {
        if (jenis === '' || p.jenis_produk === jenis) {
            var opt = document.createElement('option');
            opt.value = p.id_produk;
            opt.setAttribute('data-jenis', p.jenis_produk || '');
            opt.textContent = p.nama_produk + (p.jenis_produk ? ' (' + p.jenis_produk + ')' : '');
            if (p.id_produk == current) opt.selected = true;
            select.appendChild(opt);
        }
    });
}

function previewFile(input) {
    var label   = document.getElementById('fileRefName');
    var preview = document.getElementById('filePreview');
    var img     = document.getElementById('filePreviewImg');
    if (input.files && input.files[0]) {
        label.textContent = input.files[0].name;
        var reader = new FileReader();
        reader.onload = function(e) { img.src = e.target.result; preview.style.display = 'block'; };
        reader.readAsDataURL(input.files[0]);
    }
}
</script>

<?php include 'includes/footer.php'; ?>
