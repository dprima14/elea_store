<?php
$page_key   = 'kelola_produk';
$page_title = 'Kelola Produk';
session_start();
require_once '../config/db.php';
require_once 'inc/header.php';

function simpanGambarTambahan(int $id_produk, PDO $pdo): void {
    if (empty($_FILES['extra_images']['name'][0])) return;
    $dir = '../assets/images/products/';
    $cur = $pdo->prepare("SELECT COALESCE(MAX(urutan),0) FROM produk_gambar WHERE id_produk=?");
    $cur->execute([$id_produk]);
    $next = (int)$cur->fetchColumn() + 1;
    foreach ($_FILES['extra_images']['tmp_name'] as $i => $tmp) {
        if (empty($_FILES['extra_images']['name'][$i]) || !is_uploaded_file($tmp)) continue;
        $ext = strtolower(pathinfo($_FILES['extra_images']['name'][$i], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg','jpeg','png','webp'])) continue;
        if ($_FILES['extra_images']['size'][$i] > 2097152) continue;
        $fname = uniqid('prod_') . '.' . $ext;
        if (move_uploaded_file($tmp, $dir . $fname)) {
            $pdo->prepare("INSERT INTO produk_gambar (id_produk, gambar, urutan) VALUES (?,?,?)")
                ->execute([$id_produk, $fname, $next++]);
        }
    }
}

// Auto-migrate: tambah kolom baru jika belum ada
foreach ([
    "ALTER TABLE produk ADD COLUMN jenis_produk VARCHAR(50) NULL AFTER deskripsi_produk",
    "ALTER TABLE produk ADD COLUMN ukuran TEXT NULL AFTER jenis_produk",
] as $q) {
    try { $pdo->exec($q); } catch (PDOException $e) {}
}

$msg_ok  = '';
$msg_err = '';
$edit_data = null;

$jenis_options    = ['Gamis','Hijab','Tunik','Rok','Celana','Outer/Cardigan','Aksesoris'];
$jenis_ada_panjang = ['Gamis','Tunik','Rok','Celana'];
$ukuran_options   = ['S','M','L','XL','XXL','XXXL'];

// TAMBAH STOK BULK (semua ukuran sekaligus)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'tambah_stok_bulk') {
    $id_bulk = intval($_POST['id_produk_stok'] ?? 0);
    $inputs  = $_POST['stok_input'] ?? [];
    if ($id_bulk > 0 && is_array($inputs)) {
        $sb = $pdo->prepare("SELECT stok, ukuran FROM produk WHERE id_produk=?");
        $sb->execute([$id_bulk]);
        $prod_bulk = $sb->fetch();
        if ($prod_bulk && !empty($prod_bulk['ukuran']) && $prod_bulk['ukuran'] !== 'all_size') {
            $uk_arr = json_decode($prod_bulk['ukuran'], true) ?: [];
            $total_tambah = 0;
            foreach ($uk_arr as &$uk) {
                $tambah = intval($inputs[$uk['uk']] ?? 0);
                if ($tambah > 0) {
                    $uk['stok'] = ($uk['stok'] ?? 0) + $tambah;
                    $total_tambah += $tambah;
                }
            }
            unset($uk);
            if ($total_tambah > 0) {
                $total_stok = array_sum(array_column($uk_arr, 'stok'));
                $pdo->prepare("UPDATE produk SET ukuran=?, stok=? WHERE id_produk=?")
                    ->execute([json_encode($uk_arr, JSON_UNESCAPED_UNICODE), $total_stok, $id_bulk]);
                $msg_ok = "Stok berhasil diperbarui (+{$total_tambah} unit total).";
            } else {
                $msg_err = 'Masukkan minimal satu jumlah penambahan stok.';
            }
        }
    }
}

// TAMBAH STOK (support per ukuran)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'tambah_stok') {
    $id_stok       = intval($_POST['id_produk_stok'] ?? 0);
    $ukuran_target = trim($_POST['ukuran_target'] ?? '');
    $has_error     = false;

    // Ambil data ukuran saat ini
    $prod_now = null;
    if ($id_stok > 0) {
        $s = $pdo->prepare("SELECT stok, ukuran FROM produk WHERE id_produk = ?");
        $s->execute([$id_stok]);
        $prod_now = $s->fetch();
    }

    $is_per_ukuran = $prod_now && !empty($prod_now['ukuran'])
                     && $prod_now['ukuran'] !== 'all_size'
                     && !empty($ukuran_target);

    if ($is_per_ukuran) {
        // Tambah stok untuk ukuran tertentu
        $tambah = intval($_POST['tambah_stok'] ?? 0);
        if ($tambah <= 0) {
            $msg_err = 'Jumlah penambahan harus lebih dari 0.';
            $has_error = true;
        } else {
            $uk_arr = json_decode($prod_now['ukuran'], true) ?: [];
            $found  = false;
            foreach ($uk_arr as &$uk) {
                if ($uk['uk'] === $ukuran_target) {
                    $uk['stok'] = ($uk['stok'] ?? 0) + $tambah;
                    $found = true;
                    break;
                }
            }
            unset($uk);
            if (!$found) {
                $uk_arr[] = ['uk' => $ukuran_target, 'pjg' => '', 'stok' => $tambah];
            }
            // Total stok = jumlah semua stok per ukuran
            $total_stok = array_sum(array_column($uk_arr, 'stok'));
            $pdo->prepare("UPDATE produk SET ukuran=?, stok=? WHERE id_produk=?")
                ->execute([json_encode($uk_arr, JSON_UNESCAPED_UNICODE), $total_stok, $id_stok]);
            $msg_ok = "Stok ukuran {$ukuran_target} berhasil ditambah +{$tambah} unit.";
        }
    } else {
        // All size atau tidak ada pilihan ukuran: tambah ke total stok
        $tambah = intval($_POST['tambah_stok'] ?? 0);
        if ($tambah <= 0 || $id_stok <= 0) {
            $msg_err = 'Jumlah penambahan stok harus lebih dari 0.';
            $has_error = true;
        } else {
            $pdo->prepare("UPDATE produk SET stok = stok + ? WHERE id_produk = ?")
                ->execute([$tambah, $id_stok]);
            $msg_ok = "Stok berhasil ditambah +{$tambah} unit.";
        }
    }
}

// HAPUS PRODUK
if (isset($_GET['hapus'])) {
    $id = intval($_GET['hapus']);
    $s = $pdo->prepare("SELECT gambar_produk FROM produk WHERE id_produk = ?");
    $s->execute([$id]);
    $row = $s->fetch();
    if ($row && !empty($row['gambar_produk'])) {
        $path = '../assets/images/products/' . $row['gambar_produk'];
        if (file_exists($path)) unlink($path);
    }
    $eg_del = $pdo->prepare("SELECT gambar FROM produk_gambar WHERE id_produk=?");
    $eg_del->execute([$id]);
    foreach ($eg_del->fetchAll() as $eg) {
        $ep = '../assets/images/products/' . $eg['gambar'];
        if (file_exists($ep)) unlink($ep);
    }
    $pdo->prepare("DELETE FROM produk_gambar WHERE id_produk=?")->execute([$id]);
    $pdo->prepare("DELETE FROM produk WHERE id_produk = ?")->execute([$id]);
    $msg_ok = 'Produk berhasil dihapus.';
}

// HAPUS GAMBAR TAMBAHAN
if (isset($_GET['hapus_gambar'])) {
    $gid   = intval($_GET['hapus_gambar']);
    $gstmt = $pdo->prepare("SELECT gambar, id_produk FROM produk_gambar WHERE id=?");
    $gstmt->execute([$gid]);
    $grow  = $gstmt->fetch();
    if ($grow) {
        $gpath = '../assets/images/products/' . $grow['gambar'];
        if (file_exists($gpath)) unlink($gpath);
        $pdo->prepare("DELETE FROM produk_gambar WHERE id=?")->execute([$gid]);
        $msg_ok = 'Gambar berhasil dihapus.';
        header('Location: kelola_produk.php?edit=' . $grow['id_produk']);
        exit;
    }
}

// AMBIL DATA EDIT
$extra_images = [];
if (isset($_GET['edit'])) {
    $id = intval($_GET['edit']);
    $stmt = $pdo->prepare("SELECT * FROM produk WHERE id_produk = ?");
    $stmt->execute([$id]);
    $edit_data = $stmt->fetch();
    if ($edit_data) {
        $eg_s = $pdo->prepare("SELECT id, gambar FROM produk_gambar WHERE id_produk=? ORDER BY urutan, id");
        $eg_s->execute([$edit_data['id_produk']]);
        $extra_images = $eg_s->fetchAll();
    }
}

// SIMPAN (tambah atau edit)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi'])) {
    $nama  = trim($_POST['nama_produk'] ?? '');
    $harga = intval($_POST['harga'] ?? 0);
    $stok  = intval($_POST['stok'] ?? 0);
    $desk  = trim($_POST['deskripsi_produk'] ?? '');
    $jenis = trim($_POST['jenis_produk'] ?? '') ?: null;

    // Proses ukuran
    $ukuran_data = null;
    if ($jenis) {
        $tipe = $_POST['tipe_ukuran'] ?? 'all_size';
        if ($tipe === 'all_size') {
            $ukuran_data = 'all_size';
        } else {
            $checked  = $_POST['ukuran_checked'] ?? [];
            $pjg_map  = $_POST['panjang_map'] ?? [];
            $sizes = [];
            foreach ($ukuran_options as $uk) {
                if (in_array($uk, $checked)) {
                    $sizes[] = ['uk' => $uk, 'pjg' => trim($pjg_map[$uk] ?? '')];
                }
            }
            $ukuran_data = !empty($sizes) ? json_encode($sizes, JSON_UNESCAPED_UNICODE) : 'all_size';
        }
    }

    // Gambar: ambil gambar lama jika edit
    $gambar = '';
    if ($_POST['aksi'] === 'edit') {
        $s = $pdo->prepare("SELECT gambar_produk FROM produk WHERE id_produk = ?");
        $s->execute([intval($_POST['id_produk'])]);
        $gambar = $s->fetchColumn() ?: '';
    }

    if (!empty($_FILES['gambar_file']['name'])) {
        $file = $_FILES['gambar_file'];
        $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg','jpeg','png','webp'])) {
            $msg_err = 'Format gambar harus JPG, PNG, atau WEBP.';
        } elseif ($file['size'] > 2097152) {
            $msg_err = 'Ukuran gambar maksimal 2MB.';
        } else {
            $dir = '../assets/images/products/';
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            if ($gambar && file_exists($dir . $gambar)) unlink($dir . $gambar);
            $newname = uniqid('prod_') . '.' . $ext;
            move_uploaded_file($file['tmp_name'], $dir . $newname);
            $gambar = $newname;
        }
    }

    if (empty($msg_err)) {
        if (empty($nama) || $harga <= 0) {
            $msg_err = 'Nama produk dan harga wajib diisi.';
        } elseif ($_POST['aksi'] === 'tambah') {
            $pdo->prepare(
                "INSERT INTO produk (nama_produk,harga,stok,deskripsi_produk,gambar_produk,jenis_produk,ukuran)
                 VALUES (?,?,?,?,?,?,?)"
            )->execute([$nama,$harga,$stok,$desk,$gambar,$jenis,$ukuran_data]);
            simpanGambarTambahan((int)$pdo->lastInsertId(), $pdo);
            $msg_ok = 'Produk "'.htmlspecialchars($nama).'" berhasil ditambahkan!';
        } elseif ($_POST['aksi'] === 'edit') {
            $id = intval($_POST['id_produk']);
            $pdo->prepare(
                "UPDATE produk SET nama_produk=?,harga=?,stok=?,deskripsi_produk=?,gambar_produk=?,jenis_produk=?,ukuran=?
                 WHERE id_produk=?"
            )->execute([$nama,$harga,$stok,$desk,$gambar,$jenis,$ukuran_data,$id]);
            simpanGambarTambahan($id, $pdo);
            $msg_ok = 'Produk berhasil diperbarui!';
            $edit_data = null;
        }
    }
}

$produk_list = $pdo->query(
    "SELECT p.*, (SELECT COUNT(*) FROM produk_gambar pg WHERE pg.id_produk=p.id_produk) AS jml_gambar_tambahan
     FROM produk p ORDER BY p.id_produk DESC"
)->fetchAll();
?>

<?php if ($msg_ok):  ?><div class="alert-ok"><i class="fas fa-check-circle"></i> <?= $msg_ok ?></div><?php endif; ?>
<?php if ($msg_err): ?><div class="alert-err"><i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($msg_err) ?></div><?php endif; ?>

<style>
.ukuran-section{background:#fff8f6;border:1px solid #f2e8dc;border-radius:.75rem;padding:1rem;margin-top:.75rem;}
.ukuran-section h4{font-size:.75rem;font-weight:700;color:#7a2e22;margin:0 0 .75rem;}
.tipe-btn-group{display:flex;gap:.5rem;margin-bottom:.875rem;}
.tipe-btn{padding:.375rem .875rem;border-radius:.5rem;font-size:.75rem;font-weight:600;border:1.5px solid #7a2e22;cursor:pointer;transition:all .15s;background:white;color:#7a2e22;}
.tipe-btn.active{background:#7a2e22;color:white;}
.size-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:.5rem;}
.size-row{display:flex;flex-direction:column;gap:.25rem;background:white;border-radius:.5rem;padding:.5rem .625rem;border:1.5px solid #e5e7eb;transition:border-color .15s;}
.size-row.selected{border-color:#7a2e22;background:#fff8f6;}
.size-row label{display:flex;align-items:center;gap:.375rem;font-size:.75rem;font-weight:600;cursor:pointer;user-select:none;}
.pjg-inp{width:100%;padding:.3rem .5rem;font-size:.7rem;border:1px solid #d1d5db;border-radius:.375rem;outline:none;box-sizing:border-box;margin-top:.25rem;}
.pjg-inp:focus{border-color:#7a2e22;}
.pjg-lbl{font-size:.65rem;color:#9ca3af;margin-top:.25rem;}
.img-preview-box{margin-top:.375rem;}
.img-preview{width:80px;height:80px;object-fit:cover;border-radius:.5rem;border:1px solid #e5e7eb;}
.thumb-img{width:40px;height:40px;object-fit:cover;border-radius:.375rem;border:1px solid #e5e7eb;}
.no-thumb{width:40px;height:40px;border-radius:.375rem;background:#f3f4f6;display:flex;align-items:center;justify-content:center;font-size:1.1rem;}
/* Modal Tambah Stok */
.modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:999;display:flex;align-items:center;justify-content:center;}
.modal-box{background:white;border-radius:1rem;padding:1.5rem;width:100%;max-width:360px;box-shadow:0 10px 40px rgba(0,0,0,.2);}
.modal-box h3{font-size:.9375rem;font-weight:700;color:#1f2937;margin:0 0 .25rem;}
.modal-box .modal-sub{font-size:.75rem;color:#6b7280;margin-bottom:1.25rem;}
.modal-box label{display:block;font-size:.75rem;font-weight:600;color:#6b7280;margin-bottom:.375rem;}
.modal-box input[type=number]{width:100%;padding:.625rem .875rem;border:1px solid #e5e7eb;border-radius:.625rem;font-size:.8125rem;outline:none;background:#f9fafb;box-sizing:border-box;}
.modal-box input[type=number]:focus{box-shadow:0 0 0 3px rgba(2,132,199,.15);border-color:#7dd3fc;}
.modal-actions{display:flex;gap:.625rem;margin-top:1rem;}
</style>

<!-- FORM TAMBAH / EDIT -->
<div class="cc">
    <h2><?= $edit_data ? 'Edit Produk' : 'Tambah Produk Baru' ?></h2>
    <form method="POST" action="kelola_produk.php" enctype="multipart/form-data" id="formProduk">
        <input type="hidden" name="aksi" value="<?= $edit_data ? 'edit' : 'tambah' ?>">
        <?php if ($edit_data): ?>
        <input type="hidden" name="id_produk" value="<?= $edit_data['id_produk'] ?>">
        <?php endif; ?>

        <div class="fgrid">
            <div class="fg">
                <label>Nama Produk *</label>
                <input type="text" name="nama_produk" placeholder="Nama produk" required
                       value="<?= htmlspecialchars($edit_data['nama_produk'] ?? '') ?>">
            </div>
            <div class="fg">
                <label>Harga (Rp) *</label>
                <input type="number" name="harga" placeholder="150000" min="1" required
                       value="<?= $edit_data['harga'] ?? '' ?>">
            </div>
            <div class="fg">
                <label>Stok</label>
                <input type="number" name="stok" placeholder="0" min="0"
                       value="<?= $edit_data['stok'] ?? 0 ?>">
            </div>
            <div class="fg">
                <label>Jenis Produk <span style="color:#9ca3af;font-weight:400;">(opsional)</span></label>
                <select name="jenis_produk" id="selJenis" onchange="handleJenis()">
                    <option value="">-- Tidak Ada Jenis --</option>
                    <?php foreach ($jenis_options as $j): ?>
                    <option value="<?= $j ?>" <?= ($edit_data['jenis_produk'] ?? '') === $j ? 'selected' : '' ?>><?= $j ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="fg fg-full">
                <label>Gambar Utama <span style="color:#9ca3af;font-weight:400;">(JPG/PNG/WEBP, maks 2MB)</span></label>
                <input type="file" name="gambar_file" accept=".jpg,.jpeg,.png,.webp" onchange="previewImg(this)">
                <div class="img-preview-box">
                    <?php if (!empty($edit_data['gambar_produk'])): ?>
                    <img src="../assets/images/products/<?= htmlspecialchars($edit_data['gambar_produk']) ?>"
                         id="imgPreview" class="img-preview" alt="Preview">
                    <div style="font-size:.65rem;color:#9ca3af;margin-top:.2rem;">Gambar saat ini · Upload baru untuk ganti</div>
                    <?php else: ?>
                    <img id="imgPreview" class="img-preview" style="display:none;" alt="Preview">
                    <?php endif; ?>
                </div>
            </div>
            <div class="fg fg-full">
                <label>Gambar Tambahan <span style="color:#9ca3af;font-weight:400;">(opsional · bisa pilih lebih dari 1 · maks 2MB/gambar)</span></label>
                <?php if (!empty($extra_images)): ?>
                <div style="display:flex;flex-wrap:wrap;gap:.5rem;margin-bottom:.625rem;">
                    <?php foreach ($extra_images as $eg): ?>
                    <div style="position:relative;display:inline-block;">
                        <img src="../assets/images/products/<?= htmlspecialchars($eg['gambar']) ?>"
                             style="width:64px;height:64px;object-fit:cover;border-radius:.5rem;border:1.5px solid #e5e7eb;">
                        <a href="kelola_produk.php?hapus_gambar=<?= $eg['id'] ?>&edit=<?= $edit_data['id_produk'] ?>"
                           onclick="return confirm('Hapus gambar ini?')"
                           style="position:absolute;top:-6px;right:-6px;width:18px;height:18px;background:#dc2626;color:white;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:.7rem;font-weight:700;text-decoration:none;line-height:1;">×</a>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                <input type="file" name="extra_images[]" accept=".jpg,.jpeg,.png,.webp" multiple onchange="previewExtraImgs(this)">
                <div style="font-size:.68rem;color:#9ca3af;margin-top:.25rem;">Tahan Ctrl / Cmd untuk memilih beberapa file sekaligus</div>
                <div id="extraImgPreview" style="display:flex;flex-wrap:wrap;gap:.5rem;margin-top:.5rem;"></div>
            </div>
            <div class="fg fg-full">
                <label>Deskripsi Produk</label>
                <textarea name="deskripsi_produk" rows="3"
                          placeholder="Deskripsi singkat produk..."><?= htmlspecialchars($edit_data['deskripsi_produk'] ?? '') ?></textarea>
            </div>
        </div>

        <!-- SEKSI UKURAN (muncul saat jenis dipilih) -->
        <div id="ukuranSection" style="display:none;">
            <div class="ukuran-section">
                <h4><i class="fas fa-ruler-combined"></i> Ukuran Tersedia</h4>
                <input type="hidden" name="tipe_ukuran" id="tipeUkuranInput" value="all_size">
                <div class="tipe-btn-group">
                    <button type="button" class="tipe-btn active" id="btnAllSize" onclick="setTipe('all_size')">All Size</button>
                    <button type="button" class="tipe-btn" id="btnPilih" onclick="setTipe('pilih')">Pilih Ukuran</button>
                </div>
                <div id="pilihWrap" style="display:none;">
                    <div class="size-grid">
                        <?php foreach ($ukuran_options as $uk): ?>
                        <div class="size-row" id="row_<?= $uk ?>">
                            <label>
                                <input type="checkbox" name="ukuran_checked[]" value="<?= $uk ?>"
                                       onchange="toggleRow('<?= $uk ?>',this.checked)">
                                <?= $uk ?>
                            </label>
                            <div id="pjg_<?= $uk ?>" style="display:none;">
                                <div class="pjg-lbl">Panjang (cm) — opsional</div>
                                <input type="number" name="panjang_map[<?= $uk ?>]"
                                       class="pjg-inp" placeholder="cth. 130" min="1" max="500">
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <div id="pjgNote" style="font-size:.65rem;color:#6b7280;margin-top:.5rem;padding:.375rem .625rem;background:white;border-radius:.375rem;display:none;">
                        <i class="fas fa-info-circle"></i> Isi panjang (cm) untuk produk seperti gamis, tunik, atau rok agar pelanggan tahu ukuran detail.
                    </div>
                </div>
            </div>
        </div>

        <div style="display:flex;gap:.625rem;margin-top:.875rem;">
            <button type="submit" class="btn-sm" style="background:linear-gradient(135deg,#7a2e22,#9e5848);color:white;">
                <?= $edit_data ? 'Simpan Perubahan' : '+ Tambah Produk' ?>
            </button>
            <?php if ($edit_data): ?>
            <a href="kelola_produk.php" class="btn-sm" style="background:#f3f4f6;color:#374151;text-decoration:none;">Batal</a>
            <?php else: ?>
            <button type="reset" class="btn-sm" style="background:#f3f4f6;color:#374151;" onclick="onReset()">Reset</button>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- DAFTAR PRODUK -->
<div class="cc">
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.75rem;margin-bottom:1rem;">
        <h2 style="margin:0;">Data Produk (<span id="produkCount"><?= count($produk_list) ?></span> produk)</h2>
        <!-- Search -->
        <div style="position:relative;flex:1;min-width:200px;max-width:320px;">
            <i class="fas fa-search" style="position:absolute;left:.75rem;top:50%;transform:translateY(-50%);color:#9ca3af;font-size:.8rem;pointer-events:none;"></i>
            <input type="text" id="searchProduk" placeholder="Cari nama produk..."
                   oninput="filterProduk()"
                   style="width:100%;padding:.5rem .75rem .5rem 2.25rem;border:1.5px solid #e5e7eb;border-radius:.625rem;font-size:.8125rem;outline:none;background:#f9fafb;box-sizing:border-box;font-family:inherit;"
                   onfocus="this.style.borderColor='#7a2e22'" onblur="this.style.borderColor='#e5e7eb'">
        </div>
    </div>

    <!-- Filter jenis -->
    <?php
    $jenis_ada = array_unique(array_filter(array_column($produk_list, 'jenis_produk')));
    sort($jenis_ada);
    ?>
    <div style="display:flex;gap:.375rem;flex-wrap:wrap;margin-bottom:1rem;" id="filterTabs">
        <button class="filter-tab active" onclick="setFilter('',this)">Semua <span class="filter-count"><?= count($produk_list) ?></span></button>
        <?php foreach ($jenis_ada as $j): ?>
        <?php $cnt = count(array_filter($produk_list, fn($p) => $p['jenis_produk'] === $j)); ?>
        <button class="filter-tab" onclick="setFilter('<?= htmlspecialchars($j) ?>',this)"><?= htmlspecialchars($j) ?> <span class="filter-count"><?= $cnt ?></span></button>
        <?php endforeach; ?>
        <?php $no_jenis = count(array_filter($produk_list, fn($p) => empty($p['jenis_produk']))); ?>
        <?php if ($no_jenis > 0): ?>
        <button class="filter-tab" onclick="setFilter('__none__',this)">Tanpa Jenis <span class="filter-count"><?= $no_jenis ?></span></button>
        <?php endif; ?>
    </div>

    <style>
    .filter-tab{padding:.3rem .875rem;border-radius:9999px;border:1.5px solid #e5e7eb;background:white;font-size:.75rem;font-weight:600;color:#6b7280;cursor:pointer;transition:all .15s;font-family:inherit;display:inline-flex;align-items:center;gap:.375rem;}
    .filter-tab:hover{border-color:#9e5848;color:#7a2e22;}
    .filter-tab.active{background:#7a2e22;border-color:#7a2e22;color:white;}
    .filter-count{font-size:.65rem;padding:.05rem .35rem;border-radius:9999px;background:rgba(0,0,0,.1);}
    .filter-tab.active .filter-count{background:rgba(255,255,255,.25);}
    #emptyState{display:none;text-align:center;padding:2rem;color:#9ca3af;font-size:.875rem;}
    </style>

    <?php if (empty($produk_list)): ?>
    <p style="text-align:center;color:#9ca3af;padding:2rem 0;">Belum ada produk.</p>
    <?php else: ?>
    <div style="overflow-x:auto;">
    <table class="dtbl" id="tblProduk">
        <thead>
            <tr><th>#</th><th>Gambar</th><th>Nama Produk</th><th>Jenis</th><th>Ukuran</th><th>Harga</th><th>Stok</th><th>Aksi</th></tr>
        </thead>
        <tbody id="tbodyProduk">
        <?php foreach ($produk_list as $i => $p):
            $uk_arr  = [];
            $uk_info = '';
            $is_per_uk = false;
            if (!empty($p['ukuran'])) {
                if ($p['ukuran'] === 'all_size') {
                    $uk_info = 'All Size';
                } else {
                    $uk_arr    = json_decode($p['ukuran'], true) ?: [];
                    $is_per_uk = !empty($uk_arr);
                    $parts = [];
                    foreach ($uk_arr as $s) {
                        $parts[] = $s['uk'] . (!empty($s['pjg']) ? ' ('.$s['pjg'].'cm)' : '');
                    }
                    $uk_info = implode(', ', $parts);
                }
            }
        ?>
        <tr data-nama="<?= strtolower(htmlspecialchars($p['nama_produk'])) ?>"
            data-jenis="<?= htmlspecialchars($p['jenis_produk'] ?? '') ?>">
            <td class="t-sub"><?= $i + 1 ?></td>
            <td>
                <div style="position:relative;display:inline-block;">
                    <?php if (!empty($p['gambar_produk'])): ?>
                    <img src="../assets/images/products/<?= htmlspecialchars($p['gambar_produk']) ?>"
                         class="thumb-img" alt="">
                    <?php else: ?>
                    <div class="no-thumb"><i class="fas fa-tshirt" style="color:#e8a67a;"></i></div>
                    <?php endif; ?>
                    <?php if ($p['jml_gambar_tambahan'] > 0): ?>
                    <span style="position:absolute;bottom:-4px;right:-4px;background:#953b22;color:white;font-size:.55rem;font-weight:700;padding:.1rem .35rem;border-radius:.25rem;line-height:1.4;">+<?= $p['jml_gambar_tambahan'] ?></span>
                    <?php endif; ?>
                </div>
            </td>
            <td class="t-name"><?= htmlspecialchars($p['nama_produk']) ?></td>
            <td>
                <?php if ($p['jenis_produk']): ?>
                <span class="tbadge" style="background:#fce9e3;color:#4b5563;"><?= htmlspecialchars($p['jenis_produk']) ?></span>
                <?php else: ?><span style="color:#d1d5db;font-size:.7rem;">—</span><?php endif; ?>
            </td>
            <td style="font-size:.7rem;color:#6b7280;max-width:120px;">
                <?= $uk_info ?: '<span style="color:#d1d5db;">—</span>' ?>
            </td>
            <td class="t-bold"><?= fmt_rp((int)$p['harga']) ?></td>
            <td>
                <?php if ($is_per_uk): ?>
                <!-- Stok per ukuran -->
                <div style="display:flex;flex-direction:column;gap:.2rem;">
                    <?php foreach ($uk_arr as $uk): ?>
                    <span style="font-size:.7rem;display:flex;gap:.375rem;align-items:center;">
                        <span style="font-weight:600;color:#374151;min-width:24px;"><?= htmlspecialchars($uk['uk']) ?></span>
                        <span class="tbadge <?= ($uk['stok'] ?? 0) > 5 ? 'stok-ok' : (($uk['stok'] ?? 0) > 0 ? 'stok-low' : 'stok-out') ?>"
                              style="font-size:.65rem;padding:.1rem .4rem;">
                            <?= ($uk['stok'] ?? 0) > 0 ? ($uk['stok'] ?? 0).' unit' : 'Habis' ?>
                        </span>
                    </span>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <span class="tbadge <?= $p['stok'] > 10 ? 'stok-ok' : ($p['stok'] > 0 ? 'stok-low' : 'stok-out') ?>">
                    <?= $p['stok'] > 0 ? $p['stok'].' unit' : 'Habis' ?>
                </span>
                <?php endif; ?>
            </td>
            <td style="white-space:nowrap;vertical-align:top;">
                <a href="kelola_produk.php?edit=<?= $p['id_produk'] ?>"
                   class="btn-sm" style="background:#fff8f6;color:#7a2e22;padding:.25rem .625rem;font-size:.7rem;text-decoration:none;">Edit</a>
                <button type="button" class="btn-sm"
                        style="background:#eef2ff;color:#4338ca;padding:.25rem .625rem;font-size:.7rem;"
                        onclick="openStokModal(<?= $p['id_produk'] ?>, <?= htmlspecialchars(json_encode($p['nama_produk'])) ?>, <?= (int)$p['stok'] ?>, <?= htmlspecialchars(json_encode($p['ukuran'] ?? null)) ?>)">
                    <i class="fas fa-layer-group"></i> Detail Stok
                </button>
                <a href="kelola_produk.php?hapus=<?= $p['id_produk'] ?>"
                   class="btn-sm" style="background:#fef2f2;color:#dc2626;padding:.25rem .625rem;font-size:.7rem;text-decoration:none;"
                   onclick="return confirm('Hapus produk ini? Gambar juga akan dihapus.')">Hapus</a>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <div id="emptyState"><i class="fas fa-search" style="font-size:1.5rem;margin-bottom:.5rem;display:block;"></i> Tidak ada produk yang cocok.</div>
    </div>
    <?php endif; ?>
</div>

<!-- MODAL DETAIL & TAMBAH STOK -->
<style>
.stok-uk-card{border:1.5px solid #e5e7eb;border-radius:.625rem;padding:.625rem .875rem;display:flex;align-items:center;gap:.75rem;margin-bottom:.5rem;background:white;transition:border-color .15s;}
.stok-uk-card.selected{border-color:#7a2e22;background:#fff8f6;}
.stok-uk-name{font-weight:700;font-size:.875rem;color:#1f2937;min-width:32px;}
.stok-uk-count{font-size:.75rem;padding:.2rem .625rem;border-radius:9999px;font-weight:600;}
.stok-add-inp{width:70px;padding:.375rem .5rem;border:1px solid #e5e7eb;border-radius:.5rem;font-size:.8125rem;outline:none;text-align:center;font-family:inherit;}
.stok-add-inp:focus{border-color:#7a2e22;}
.stok-add-btn{padding:.375rem .875rem;background:linear-gradient(135deg,#7a2e22,#9e5848);color:white;border:none;border-radius:.5rem;font-size:.75rem;font-weight:600;cursor:pointer;white-space:nowrap;}
.modal-box-lg{background:white;border-radius:1rem;padding:1.5rem;width:100%;max-width:480px;box-shadow:0 10px 40px rgba(0,0,0,.2);max-height:88vh;overflow-y:auto;}
</style>

<div id="modalStok" class="modal-overlay" style="display:none;" onclick="if(event.target===this)closeStokModal()">
    <div class="modal-box-lg">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:.25rem;">
            <h3 style="margin:0;font-size:.9375rem;font-weight:700;color:#1f2937;">
                <i class="fas fa-layer-group" style="color:#4338ca;margin-right:.375rem;"></i> Detail & Tambah Stok
            </h3>
            <button onclick="closeStokModal()" style="background:none;border:none;color:#9ca3af;cursor:pointer;font-size:1.1rem;"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-sub" id="modalStokSub" style="font-size:.8rem;color:#6b7280;margin-bottom:1.25rem;"></div>

        <!-- Untuk produk ALL SIZE -->
        <div id="stokAllSize">
            <div style="background:#f9fafb;border-radius:.625rem;padding:.875rem;margin-bottom:1rem;display:flex;align-items:center;justify-content:space-between;">
                <div>
                    <div style="font-size:.65rem;color:#9ca3af;font-weight:700;text-transform:uppercase;letter-spacing:.05em;">Stok Saat Ini</div>
                    <div style="font-size:1.5rem;font-weight:700;color:#1f2937;" id="stokAllSizeVal">0</div>
                    <div style="font-size:.7rem;color:#9ca3af;">All Size</div>
                </div>
                <i class="fas fa-boxes" style="font-size:2rem;color:#e5e7eb;"></i>
            </div>
            <form method="POST" action="kelola_produk.php" id="formStokAll">
                <input type="hidden" name="aksi" value="tambah_stok">
                <input type="hidden" name="id_produk_stok" id="modalStokIdAll">
                <label style="font-size:.8rem;font-weight:600;color:#6b7280;display:block;margin-bottom:.375rem;">Jumlah Penambahan Stok</label>
                <input type="number" name="tambah_stok" id="modalStokJumlahAll" min="1" placeholder="cth. 10" required
                       style="width:100%;padding:.625rem .875rem;border:1px solid #e5e7eb;border-radius:.625rem;font-size:.9375rem;font-family:inherit;outline:none;background:#f9fafb;box-sizing:border-box;">
                <div class="modal-actions" style="margin-top:.875rem;">
                    <button type="submit" class="btn-sm" style="background:linear-gradient(135deg,#7a2e22,#9e5848);color:white;flex:1;">
                        <i class="fas fa-plus"></i> Tambah Stok
                    </button>
                    <button type="button" class="btn-sm" style="background:#f3f4f6;color:#374151;" onclick="closeStokModal()">Batal</button>
                </div>
            </form>
        </div>

        <!-- Untuk produk dengan ukuran spesifik: semua ukuran sekaligus -->
        <div id="stokPerUkuran" style="display:none;">
            <div style="font-size:.75rem;font-weight:600;color:#374151;margin-bottom:.75rem;">
                Masukkan jumlah penambahan untuk setiap ukuran (kosongkan jika tidak ditambah):
            </div>
            <form method="POST" action="kelola_produk.php" id="formStokBulk">
                <input type="hidden" name="aksi" value="tambah_stok_bulk">
                <input type="hidden" name="id_produk_stok" id="modalStokIdUk">
                <div id="stokUkuranList"></div>
                <div style="margin-top:.875rem;display:flex;gap:.5rem;">
                    <button type="submit" class="btn-sm" style="background:linear-gradient(135deg,#7a2e22,#9e5848);color:white;flex:1;">
                        <i class="fas fa-save"></i> Simpan Semua
                    </button>
                    <button type="button" class="btn-sm" style="background:#f3f4f6;color:#374151;" onclick="closeStokModal()">Batal</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
const jenisPanjang = <?= json_encode($jenis_ada_panjang) ?>;

function openStokModal(id, nama, stokSaat, ukuranJson) {
    document.getElementById('modalStokSub').textContent = nama;
    document.getElementById('modalStok').style.display = 'flex';

    var isPerUkuran = ukuranJson && ukuranJson !== 'all_size' && ukuranJson !== 'null';
    var ukArr = [];
    if (isPerUkuran) {
        try { ukArr = JSON.parse(ukuranJson); } catch(e) { isPerUkuran = false; }
    }

    if (isPerUkuran && ukArr.length > 0) {
        // Tampilkan semua ukuran dengan input sekaligus
        document.getElementById('stokAllSize').style.display   = 'none';
        document.getElementById('stokPerUkuran').style.display = 'block';
        document.getElementById('modalStokIdUk').value = id;

        var list = document.getElementById('stokUkuranList');
        list.innerHTML = '';
        ukArr.forEach(function(uk) {
            var stok = uk.stok || 0;
            var cls  = stok > 5 ? 'stok-ok' : (stok > 0 ? 'stok-low' : 'stok-out');
            var row  = document.createElement('div');
            row.className = 'stok-uk-card';
            row.style.cssText = 'display:grid;grid-template-columns:40px auto 1fr 80px;gap:.625rem;align-items:center;';
            row.innerHTML =
                '<span class="stok-uk-name">' + uk.uk + '</span>' +
                '<span class="stok-uk-count ' + cls + '">' + (stok > 0 ? stok + ' unit' : 'Habis') + '</span>' +
                (uk.pjg ? '<span style="font-size:.7rem;color:#9ca3af;">' + uk.pjg + ' cm</span>' : '<span></span>') +
                '<input type="number" name="stok_input[' + uk.uk + ']" min="0" placeholder="+tambah"' +
                    ' class="stok-add-inp" style="width:100%;text-align:center;">';
            list.appendChild(row);
        });
        // Fokus ke input pertama
        setTimeout(function() {
            var first = list.querySelector('input[type=number]');
            if (first) first.focus();
        }, 60);
    } else {
        // All size atau tanpa ukuran
        document.getElementById('stokAllSize').style.display    = 'block';
        document.getElementById('stokPerUkuran').style.display  = 'none';
        document.getElementById('modalStokIdAll').value  = id;
        document.getElementById('stokAllSizeVal').textContent = stokSaat;
        document.getElementById('modalStokJumlahAll').value = '';
        setTimeout(function(){ document.getElementById('modalStokJumlahAll').focus(); }, 50);
    }
}

function closeStokModal() {
    document.getElementById('modalStok').style.display = 'none';
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeStokModal();
});

function handleJenis() {
    var jenis = document.getElementById('selJenis').value;
    document.getElementById('ukuranSection').style.display = jenis ? 'block' : 'none';
    document.getElementById('pjgNote').style.display = (jenis && jenisPanjang.indexOf(jenis) !== -1) ? 'block' : 'none';
}

function setTipe(tipe) {
    var isAll = tipe === 'all_size';
    document.getElementById('tipeUkuranInput').value = tipe;
    document.getElementById('btnAllSize').classList.toggle('active', isAll);
    document.getElementById('btnPilih').classList.toggle('active', !isAll);
    document.getElementById('pilihWrap').style.display = isAll ? 'none' : 'block';
}

function toggleRow(uk, on) {
    document.getElementById('row_' + uk).classList.toggle('selected', on);
    document.getElementById('pjg_' + uk).style.display = on ? 'block' : 'none';
    if (!on) {
        var inp = document.querySelector('input[name="panjang_map[' + uk + ']"]');
        if (inp) inp.value = '';
    }
}

function previewImg(input) {
    if (input.files && input.files[0]) {
        var r = new FileReader();
        r.onload = function(e) {
            var img = document.getElementById('imgPreview');
            img.src = e.target.result;
            img.style.display = 'block';
        };
        r.readAsDataURL(input.files[0]);
    }
}

function previewExtraImgs(input) {
    var box = document.getElementById('extraImgPreview');
    box.innerHTML = '';
    if (!input.files) return;
    Array.from(input.files).forEach(function(file) {
        var r = new FileReader();
        r.onload = function(e) {
            var img = document.createElement('img');
            img.src = e.target.result;
            img.style.cssText = 'width:64px;height:64px;object-fit:cover;border-radius:.5rem;border:1.5px solid #e5e7eb;';
            box.appendChild(img);
        };
        r.readAsDataURL(file);
    });
}

function onReset() {
    setTimeout(function() {
        document.getElementById('ukuranSection').style.display = 'none';
        document.getElementById('imgPreview').style.display = 'none';
        setTipe('all_size');
        document.querySelectorAll('.size-row').forEach(function(r){ r.classList.remove('selected'); });
        document.querySelectorAll('[id^="pjg_"]').forEach(function(d){ d.style.display = 'none'; });
    }, 0);
}

// ============ SEARCH & FILTER ============
var activeFilter = '';

function setFilter(jenis, btn) {
    activeFilter = jenis;
    document.querySelectorAll('.filter-tab').forEach(function(b){ b.classList.remove('active'); });
    btn.classList.add('active');
    filterProduk();
}

function filterProduk() {
    var keyword = (document.getElementById('searchProduk').value || '').toLowerCase().trim();
    var rows = document.querySelectorAll('#tbodyProduk tr');
    var visible = 0;
    rows.forEach(function(row) {
        var nama  = row.getAttribute('data-nama') || '';
        var jenis = row.getAttribute('data-jenis') || '';
        var matchSearch = !keyword || nama.indexOf(keyword) !== -1;
        var matchFilter = true;
        if (activeFilter === '__none__') {
            matchFilter = jenis === '';
        } else if (activeFilter !== '') {
            matchFilter = jenis === activeFilter;
        }
        var show = matchSearch && matchFilter;
        row.style.display = show ? '' : 'none';
        if (show) visible++;
    });
    document.getElementById('produkCount').textContent = visible;
    var empty = document.getElementById('emptyState');
    if (empty) empty.style.display = visible === 0 ? 'block' : 'none';
}

// Inisialisasi saat mode edit
<?php if ($edit_data && !empty($edit_data['jenis_produk'])): ?>
(function() {
    handleJenis();
    var raw = <?= json_encode($edit_data['ukuran'] ?? null) ?>;
    if (raw && raw !== 'all_size') {
        setTipe('pilih');
        try {
            JSON.parse(raw).forEach(function(s) {
                var cb = document.querySelector('input[name="ukuran_checked[]"][value="' + s.uk + '"]');
                if (cb) { cb.checked = true; toggleRow(s.uk, true); }
                if (s.pjg) {
                    var inp = document.querySelector('input[name="panjang_map[' + s.uk + ']"]');
                    if (inp) inp.value = s.pjg;
                }
            });
        } catch(e) {}
    }
})();
<?php endif; ?>
</script>

<?php require_once 'inc/footer.php'; ?>
