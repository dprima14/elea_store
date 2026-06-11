<?php
session_start();
require_once '../config/db.php';

// Auto-migrate
try { $pdo->exec("CREATE TABLE IF NOT EXISTS pesan_kontak (id INT AUTO_INCREMENT PRIMARY KEY, nama VARCHAR(255) NOT NULL, email VARCHAR(255) NOT NULL, pesan TEXT NOT NULL, sudah_dibaca TINYINT(1) NOT NULL DEFAULT 0, dibuat DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP)"); } catch (PDOException $e) {}

// Tandai sudah dibaca
if (isset($_GET['baca']) && is_numeric($_GET['baca'])) {
    $pdo->prepare("UPDATE pesan_kontak SET sudah_dibaca=1 WHERE id=?")->execute([(int)$_GET['baca']]);
    header('Location: pesan_kontak.php'); exit;
}

// Tandai semua sudah dibaca
if (isset($_GET['baca_semua'])) {
    $pdo->exec("UPDATE pesan_kontak SET sudah_dibaca=1");
    header('Location: pesan_kontak.php'); exit;
}

// Hapus pesan
if (isset($_GET['hapus']) && is_numeric($_GET['hapus'])) {
    $pdo->prepare("DELETE FROM pesan_kontak WHERE id=?")->execute([(int)$_GET['hapus']]);
    header('Location: pesan_kontak.php'); exit;
}

$page_key   = 'pesan_kontak';
$page_title = 'Pesan Kontak';
require_once 'inc/header.php';

$filter = $_GET['filter'] ?? 'semua';
$where  = $filter === 'belum' ? 'WHERE sudah_dibaca = 0' : ($filter === 'sudah' ? 'WHERE sudah_dibaca = 1' : '');
$pesan_list  = $pdo->query("SELECT * FROM pesan_kontak $where ORDER BY dibuat DESC")->fetchAll();
$total_belum = (int)$pdo->query("SELECT COUNT(*) FROM pesan_kontak WHERE sudah_dibaca=0")->fetchColumn();
?>

<style>
.pk-toolbar{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.75rem;margin-bottom:1.25rem;}
.pk-filter{display:flex;gap:.375rem;}
.pk-filter a{padding:.375rem .875rem;border-radius:9999px;font-size:.8125rem;font-weight:600;text-decoration:none;border:1.5px solid #e5e7eb;color:#6b7280;transition:all .15s;}
.pk-filter a.active,.pk-filter a:hover{border-color:#854040;color:#854040;background:#fff8f6;}
.pk-card{background:white;border-radius:1rem;border:1px solid #e5e7eb;box-shadow:0 1px 4px rgba(0,0,0,.05);margin-bottom:.875rem;overflow:hidden;transition:box-shadow .15s;}
.pk-card:hover{box-shadow:0 3px 10px rgba(0,0,0,.08);}
.pk-card.unread{border-left:4px solid #854040;}
.pk-head{display:flex;align-items:flex-start;justify-content:space-between;gap:1rem;padding:1rem 1.25rem .75rem;}
.pk-avatar{width:38px;height:38px;border-radius:50%;background:linear-gradient(135deg,#5c3320,#854040);display:flex;align-items:center;justify-content:center;color:white;font-weight:700;font-size:.9375rem;flex-shrink:0;}
.pk-meta .pk-name{font-weight:700;font-size:.9375rem;color:#1f2937;}
.pk-meta .pk-email{font-size:.8rem;color:#9ca3af;}
.pk-meta .pk-date{font-size:.75rem;color:#9ca3af;margin-top:2px;}
.pk-badge{font-size:.7rem;font-weight:700;padding:.2rem .625rem;border-radius:9999px;white-space:nowrap;}
.pk-badge.unread{background:#fff8f6;color:#854040;border:1px solid #f5d4cb;}
.pk-badge.read{background:#f3f4f6;color:#9ca3af;border:1px solid #e5e7eb;}
.pk-body{padding:.25rem 1.25rem 1rem;font-size:.875rem;color:#374151;line-height:1.6;white-space:pre-wrap;}
.pk-actions{display:flex;gap:.5rem;padding:.625rem 1.25rem;border-top:1px solid #f3f4f6;background:#fafafa;}
.pk-btn{font-size:.75rem;font-weight:600;padding:.3rem .75rem;border-radius:.5rem;border:none;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;gap:.3rem;transition:opacity .15s;}
.pk-btn:hover{opacity:.8;}
.pk-btn-read{background:#fff8f6;color:#854040;border:1px solid #f5d4cb;}
.pk-btn-del{background:#fef2f2;color:#dc2626;border:1px solid #fecaca;}
.pk-empty{text-align:center;padding:4rem 1rem;color:#9ca3af;}
.pk-stats{display:flex;gap:.75rem;flex-wrap:wrap;margin-bottom:1.25rem;}
.pk-stat{background:white;border-radius:.875rem;border:1px solid #e5e7eb;padding:.75rem 1.25rem;flex:1;min-width:120px;}
.pk-stat-val{font-size:1.5rem;font-weight:700;color:#1f2937;}
.pk-stat-lbl{font-size:.75rem;color:#9ca3af;margin-top:2px;}
</style>

<div style="margin-bottom:1rem;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.5rem;">
    <div>
        <h2 style="font-size:1.125rem;font-weight:700;color:#1f2937;margin:0;">Pesan Kontak</h2>
        <p style="font-size:.8125rem;color:#9ca3af;margin:2px 0 0;">Pesan masuk dari halaman kontak website</p>
    </div>
    <?php if ($total_belum > 0): ?>
    <a href="pesan_kontak.php?baca_semua=1" class="pk-btn pk-btn-read" onclick="return confirm('Tandai semua pesan sebagai sudah dibaca?')">
        <i class="fas fa-check-double"></i> Tandai Semua Dibaca
    </a>
    <?php endif; ?>
</div>

<div class="pk-stats">
    <div class="pk-stat">
        <div class="pk-stat-val"><?= $pdo->query("SELECT COUNT(*) FROM pesan_kontak")->fetchColumn() ?></div>
        <div class="pk-stat-lbl">Total Pesan</div>
    </div>
    <div class="pk-stat">
        <div class="pk-stat-val" style="color:#854040;"><?= $total_belum ?></div>
        <div class="pk-stat-lbl">Belum Dibaca</div>
    </div>
    <div class="pk-stat">
        <div class="pk-stat-val" style="color:#9ca3af;"><?= $pdo->query("SELECT COUNT(*) FROM pesan_kontak WHERE sudah_dibaca=1")->fetchColumn() ?></div>
        <div class="pk-stat-lbl">Sudah Dibaca</div>
    </div>
</div>

<div class="pk-toolbar">
    <div class="pk-filter">
        <a href="pesan_kontak.php?filter=semua" class="<?= $filter==='semua'?'active':'' ?>">Semua</a>
        <a href="pesan_kontak.php?filter=belum" class="<?= $filter==='belum'?'active':'' ?>">Belum Dibaca<?= $total_belum>0?" ($total_belum)":'' ?></a>
        <a href="pesan_kontak.php?filter=sudah" class="<?= $filter==='sudah'?'active':'' ?>">Sudah Dibaca</a>
    </div>
    <span style="font-size:.8125rem;color:#9ca3af;"><?= count($pesan_list) ?> pesan ditampilkan</span>
</div>

<?php if (empty($pesan_list)): ?>
<div class="pk-empty cc">
    <div style="font-size:2.5rem;margin-bottom:.75rem;"><i class="fas fa-envelope-open-text"></i></div>
    <div style="font-weight:600;color:#6b7280;margin-bottom:.25rem;">Belum ada pesan</div>
    <div style="font-size:.8125rem;">Pesan dari halaman kontak akan muncul di sini</div>
</div>
<?php else: ?>
<?php foreach ($pesan_list as $p): ?>
<div class="pk-card <?= !$p['sudah_dibaca'] ? 'unread' : '' ?>">
    <div class="pk-head">
        <div style="display:flex;align-items:flex-start;gap:.75rem;">
            <div class="pk-avatar"><?= mb_strtoupper(mb_substr($p['nama'],0,1)) ?></div>
            <div class="pk-meta">
                <div class="pk-name"><?= htmlspecialchars($p['nama']) ?></div>
                <div class="pk-email"><i class="fas fa-envelope" style="font-size:.65rem;margin-right:2px;"></i><?= htmlspecialchars($p['email']) ?></div>
                <div class="pk-date"><i class="fas fa-clock" style="font-size:.65rem;margin-right:2px;"></i><?= date('d M Y, H:i', strtotime($p['dibuat'])) ?> WIB</div>
            </div>
        </div>
        <span class="pk-badge <?= $p['sudah_dibaca'] ? 'read' : 'unread' ?>">
            <?= $p['sudah_dibaca'] ? 'Sudah Dibaca' : 'Belum Dibaca' ?>
        </span>
    </div>
    <div class="pk-body"><?= htmlspecialchars($p['pesan']) ?></div>
    <div class="pk-actions">
        <?php if (!$p['sudah_dibaca']): ?>
        <a href="pesan_kontak.php?baca=<?= $p['id'] ?>&filter=<?= $filter ?>" class="pk-btn pk-btn-read">
            <i class="fas fa-check"></i> Tandai Dibaca
        </a>
        <?php endif; ?>
        <a href="mailto:<?= htmlspecialchars($p['email']) ?>?subject=Re: Pesan Kontak Elea Store" class="pk-btn" style="background:#eff6ff;color:#1d4ed8;border:1px solid #bfdbfe;">
            <i class="fas fa-reply"></i> Balas Email
        </a>
        <a href="pesan_kontak.php?hapus=<?= $p['id'] ?>&filter=<?= $filter ?>" class="pk-btn pk-btn-del" onclick="return confirm('Hapus pesan dari <?= htmlspecialchars(addslashes($p['nama'])) ?>?')">
            <i class="fas fa-trash"></i> Hapus
        </a>
    </div>
</div>
<?php endforeach; ?>
<?php endif; ?>

<?php require_once 'inc/footer.php'; ?>
