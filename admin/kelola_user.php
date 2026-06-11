<?php
$page_key   = 'kelola_user';
$page_title = 'Kelola User';
session_start();
require_once '../config/db.php';
require_once 'inc/header.php';

$msg_ok  = '';
$msg_err = '';
$sub     = $_GET['sub'] ?? 'pelanggan';
$edit_data = null;

// HAPUS USER
if (isset($_GET['hapus'])) {
    $id = intval($_GET['hapus']);
    // Jangan hapus diri sendiri
    if ($id !== intval($user['id_user'] ?? 0)) {
        $stmt = $pdo->prepare("DELETE FROM user WHERE id_user = ?");
        $stmt->execute([$id]);
        $msg_ok = 'User berhasil dihapus.';
    }
}

// AMBIL DATA EDIT
if (isset($_GET['edit'])) {
    $id = intval($_GET['edit']);
    $stmt = $pdo->prepare("SELECT id_user, nama, username, no_telepon, role FROM user WHERE id_user = ?");
    $stmt->execute([$id]);
    $edit_data = $stmt->fetch();
    $sub = $edit_data['role'];
}

// SIMPAN USER BARU / EDIT
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi'])) {
    $nama     = trim($_POST['nama'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $no_telp  = trim($_POST['no_telepon'] ?? '');
    $role     = in_array($_POST['role']??'', ['kasir','admin','pelanggan','owner']) ? $_POST['role'] : 'pelanggan';

    if (empty($nama) || empty($username)) {
        $msg_err = 'Nama dan username wajib diisi.';
    } elseif ($_POST['aksi'] === 'tambah') {
        if (empty($password)) { $msg_err = 'Password wajib diisi untuk user baru.'; }
        else {
            // Cek duplikat username
            $chk = $pdo->prepare("SELECT COUNT(*) FROM user WHERE username = ?");
            $chk->execute([$username]);
            if ($chk->fetchColumn() > 0) {
                $msg_err = 'Username sudah digunakan.';
            } else {
                $stmt = $pdo->prepare(
                    "INSERT INTO user (nama, username, password, no_telepon, role) VALUES (?,?,?,?,?)"
                );
                $stmt->execute([$nama, $username, $password, $no_telp, $role]);
                $msg_ok = 'User "'.$nama.'" berhasil ditambahkan!';
            }
        }
    } elseif ($_POST['aksi'] === 'edit') {
        $id = intval($_POST['id_user']);
        if (!empty($password)) {
            $stmt = $pdo->prepare("UPDATE user SET nama=?,username=?,password=?,no_telepon=? WHERE id_user=?");
            $stmt->execute([$nama, $username, $password, $no_telp, $id]);
        } else {
            $stmt = $pdo->prepare("UPDATE user SET nama=?,username=?,no_telepon=? WHERE id_user=?");
            $stmt->execute([$nama, $username, $no_telp, $id]);
        }
        $msg_ok = 'User berhasil diperbarui!';
        $edit_data = null;
        $sub = $role;
    }
}

// DATA USER PER ROLE
$roles_label = ['pelanggan'=>'Pelanggan','kasir'=>'Kasir','admin'=>'Admin','owner'=>'Owner'];
$users = $pdo->prepare("SELECT id_user, nama, username, no_telepon, role FROM user WHERE role = ? ORDER BY nama");
$users->execute([$sub]);
$user_list = $users->fetchAll();
?>

<!-- Sub-nav -->
<div style="display:flex;gap:.5rem;margin-bottom:1rem;flex-wrap:wrap;">
    <?php foreach ($roles_label as $r => $lbl): ?>
    <a href="kelola_user.php?sub=<?= $r ?>"
       style="padding:.375rem 1rem;border-radius:.625rem;font-size:.8125rem;font-weight:600;text-decoration:none;
              <?= $sub===$r ? 'background:linear-gradient(135deg,#7a2e22,#9e5848);color:white;' : 'background:white;color:#6b7280;border:1px solid #e5e7eb;' ?>">
        <?= $lbl ?>
    </a>
    <?php endforeach; ?>
    <a href="kelola_user.php?sub=<?= $sub ?>&tambah=1"
       style="padding:.375rem 1rem;border-radius:.625rem;font-size:.8125rem;font-weight:600;text-decoration:none;margin-left:auto;background:#fff8f6;color:#7a2e22;border:1px solid #fce9e3;">
        + Tambah User
    </a>
</div>

<?php if ($msg_ok):  ?><div class="alert-ok"><i class="fas fa-check-circle"></i> <?= $msg_ok ?></div><?php endif; ?>
<?php if ($msg_err): ?><div class="alert-err"><i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($msg_err) ?></div><?php endif; ?>

<!-- FORM TAMBAH/EDIT (tampil jika ada ?tambah=1 atau sedang edit) -->
<?php if ($edit_data || isset($_GET['tambah'])): ?>
<div class="cc">
    <h2><?= $edit_data ? 'Edit User' : 'Tambah User Baru' ?></h2>
    <form method="POST" action="kelola_user.php?sub=<?= $sub ?>">
        <input type="hidden" name="aksi" value="<?= $edit_data ? 'edit' : 'tambah' ?>">
        <input type="hidden" name="role" value="<?= $sub ?>">
        <?php if ($edit_data): ?>
        <input type="hidden" name="id_user" value="<?= $edit_data['id_user'] ?>">
        <?php endif; ?>
        <div class="fgrid">
            <div class="fg">
                <label>Nama Lengkap *</label>
                <input type="text" name="nama" required value="<?= htmlspecialchars($edit_data['nama'] ?? '') ?>">
            </div>
            <div class="fg">
                <label>Username *</label>
                <input type="text" name="username" required value="<?= htmlspecialchars($edit_data['username'] ?? '') ?>">
            </div>
            <div class="fg">
                <label>Password <?= $edit_data ? '(kosongkan jika tidak diubah)' : '*' ?></label>
                <input type="text" name="password" <?= $edit_data ? '' : 'required' ?>>
            </div>
            <div class="fg">
                <label>No. Telepon</label>
                <input type="text" name="no_telepon" value="<?= htmlspecialchars($edit_data['no_telepon'] ?? '') ?>">
            </div>
        </div>
        <div style="display:flex;gap:.625rem;margin-top:.75rem;">
            <button type="submit" class="btn-sm" style="background:linear-gradient(135deg,#7a2e22,#9e5848);color:white;">
                <?= $edit_data ? 'Simpan Perubahan' : 'Tambah User' ?>
            </button>
            <a href="kelola_user.php?sub=<?= $sub ?>" class="btn-sm"
               style="background:#f3f4f6;color:#374151;text-decoration:none;">Batal</a>
        </div>
    </form>
</div>
<?php endif; ?>

<!-- DAFTAR USER -->
<div class="cc">
    <h2>Data <?= $roles_label[$sub] ?> (<?= count($user_list) ?>)</h2>
    <?php if (empty($user_list)): ?>
    <p style="text-align:center;color:#9ca3af;padding:2rem 0;">Belum ada data <?= $roles_label[$sub] ?>.</p>
    <?php else: ?>
    <table class="dtbl">
        <thead><tr><th>#</th><th>Nama</th><th>Username</th><th>No. Telepon</th><th>Aksi</th></tr></thead>
        <tbody>
        <?php foreach ($user_list as $i => $u): ?>
        <tr>
            <td class="t-sub"><?= $i + 1 ?></td>
            <td>
                <div style="display:flex;align-items:center;gap:.5rem;">
                    <div class="tav" style="background:linear-gradient(135deg,#7a2e22,#9e5848);"><?= mb_strtoupper(mb_substr($u['nama'],0,1)) ?></div>
                    <span class="t-name"><?= htmlspecialchars($u['nama']) ?></span>
                </div>
            </td>
            <td class="t-sub">@<?= htmlspecialchars($u['username']) ?></td>
            <td class="t-sub"><?= htmlspecialchars($u['no_telepon'] ?? '-') ?></td>
            <td>
                <a href="kelola_user.php?edit=<?= $u['id_user'] ?>"
                   class="btn-sm" style="background:#fff8f6;color:#7a2e22;padding:.25rem .625rem;font-size:.7rem;text-decoration:none;">Edit</a>
                <a href="kelola_user.php?hapus=<?= $u['id_user'] ?>&sub=<?= $sub ?>"
                   class="btn-sm" style="background:#fef2f2;color:#dc2626;padding:.25rem .625rem;font-size:.7rem;text-decoration:none;"
                   onclick="return confirm('Hapus user ini?')">Hapus</a>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<?php require_once 'inc/footer.php'; ?>
