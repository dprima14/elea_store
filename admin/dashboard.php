<?php
// ============================================================
// DASHBOARD PANEL INTERNAL — Elea Store
// Role: owner (laporan saja), admin (semua), kasir (produk/stok/pesanan)
// ============================================================
session_start();

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] === 'pelanggan') {
    header('Location: ../login.php'); exit;
}
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: ../login.php'); exit;
}

$user = $_SESSION['user'];
$role = $user['role'];

// -------- KONFIGURASI PERAN --------
$role_cfg = [
    'owner' => ['icon'=>'<i class="fas fa-crown"></i>',   'label'=>'Owner', 'gradient'=>'linear-gradient(135deg,#7a2e22,#7a2e22)', 'color'=>'#7a2e22'],
    'admin' => ['icon'=>'<i class="fas fa-shield-alt"></i>', 'label'=>'Admin', 'gradient'=>'linear-gradient(135deg,#7a2e22,#9e5848)', 'color'=>'#7a2e22'],
    'kasir' => ['icon'=>'<i class="fas fa-receipt"></i>',    'label'=>'Kasir', 'gradient'=>'linear-gradient(135deg,#7a2e22,#9e5848)', 'color'=>'#7a2e22'],
];
$cfg = $role_cfg[$role];

// -------- MENU PER PERAN --------
// Owner   : Dashboard, Laporan Keuangan, Laporan Penjualan
// Admin   : Dashboard, Produk, Stok, Pesanan, Data Pelanggan, Pengiriman, Laporan, Pengaturan
// Kasir   : Dashboard, Pesanan Masuk, Pengiriman (hanya kelola pemesanan)
$all_menus = [
    ['key'=>'dashboard',    'label'=>'Dashboard',          'icon'=>'<i class="fas fa-chart-bar"></i>',     'roles'=>['owner','admin','kasir']],
    ['key'=>'produk',       'label'=>'Kelola Produk',       'icon'=>'<i class="fas fa-tshirt"></i>',        'roles'=>['admin']],
    ['key'=>'stok',         'label'=>'Kelola Stok',         'icon'=>'<i class="fas fa-box"></i>',           'roles'=>['admin']],
    ['key'=>'pesanan',      'label'=>'Pesanan Masuk',       'icon'=>'<i class="fas fa-shopping-cart"></i>', 'roles'=>['admin','kasir']],
    ['key'=>'pelanggan',    'label'=>'Data Pelanggan',      'icon'=>'<i class="fas fa-users"></i>',         'roles'=>['admin']],
    ['key'=>'pengiriman',   'label'=>'Pengiriman',          'icon'=>'<i class="fas fa-truck"></i>',         'roles'=>['admin','kasir']],
    ['key'=>'laporan',      'label'=>'Laporan Keuangan',    'icon'=>'<i class="fas fa-coins"></i>',         'roles'=>['owner','admin']],
    ['key'=>'laporan_jual', 'label'=>'Laporan Penjualan',   'icon'=>'<i class="fas fa-chart-line"></i>',   'roles'=>['owner','admin']],
    ['key'=>'pengaturan',   'label'=>'Pengaturan',          'icon'=>'<i class="fas fa-cog"></i>',           'roles'=>['admin']],
];
$visible_menus  = array_filter($all_menus, fn($m) => in_array($role, $m['roles']));
$allowed_pages  = array_column(array_values($visible_menus), 'key');
$active_page    = in_array($_GET['page'] ?? '', $allowed_pages) ? $_GET['page'] : ($allowed_pages[0] ?? 'dashboard');

// -------- DATA DEMO --------
$orders = [
    ['id'=>'#PO-001','nama'=>'Fatimah Azzahra', 'produk'=>'Gamis Burkat Tile',    'jumlah'=>'Rp 195.000','status'=>'Diproses','sc'=>'status-diproses'],
    ['id'=>'#PO-002','nama'=>'Siti Rahmawati',  'produk'=>'Mukena Mewah Arumi',   'jumlah'=>'Rp 255.000','status'=>'Dikirim', 'sc'=>'status-dikirim'],
    ['id'=>'#PO-003','nama'=>'Nur Hidayah',     'produk'=>'Bergo Hamidah Label',  'jumlah'=>'Rp 85.000', 'status'=>'Selesai', 'sc'=>'status-selesai'],
    ['id'=>'#PO-004','nama'=>'Aisyah Putri',    'produk'=>'Gamis Abaya Aesthetic','jumlah'=>'Rp 100.000','status'=>'Baru',    'sc'=>'status-baru'],
    ['id'=>'#PO-005','nama'=>'Zahra Amalia',    'produk'=>'Kaftan Bordir',        'jumlah'=>'Rp 320.000','status'=>'Selesai', 'sc'=>'status-selesai'],
];
$products_data = [
    ['nama'=>'Gamis Burkat Tile Mutiara',    'cat'=>'GAMIS',  'harga'=>'Rp 195.000','stok'=>34,'terjual'=>234],
    ['nama'=>'Bergo Hamidah Label Akrilik',  'cat'=>'HIJAB',  'harga'=>'Rp 85.000', 'stok'=>12,'terjual'=>89],
    ['nama'=>'Mukena Mewah Arumi Katun',     'cat'=>'MUKENA', 'harga'=>'Rp 255.000','stok'=>8, 'terjual'=>412],
    ['nama'=>'Gamis Abaya Aesthetic Elegan', 'cat'=>'GAMIS',  'harga'=>'Rp 100.000','stok'=>0, 'terjual'=>67],
    ['nama'=>'Kaftan Syar\'i Bordir',        'cat'=>'GAMIS',  'harga'=>'Rp 320.000','stok'=>5, 'terjual'=>78],
    ['nama'=>'Outer Cardigan Muslimah',      'cat'=>'OUTER',  'harga'=>'Rp 145.000','stok'=>20,'terjual'=>43],
];
$customers = [
    ['nama'=>'Fatimah Azzahra','email'=>'fatimah@email.com','telp'=>'0812-1111-xxxx','pesanan'=>5,'total'=>'Rp 875.000'],
    ['nama'=>'Siti Rahmawati', 'email'=>'siti@email.com',   'telp'=>'0813-2222-xxxx','pesanan'=>3,'total'=>'Rp 540.000'],
    ['nama'=>'Nur Hidayah',    'email'=>'nur@email.com',    'telp'=>'0814-3333-xxxx','pesanan'=>8,'total'=>'Rp 1.200.000'],
    ['nama'=>'Aisyah Putri',   'email'=>'aisyah@email.com', 'telp'=>'0815-4444-xxxx','pesanan'=>2,'total'=>'Rp 320.000'],
];

// -------- FORM AKSI --------
$form_success = '';
$form_error   = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi'])) {
    if ($_POST['aksi'] === 'tambah_produk' && in_array($role, ['admin','kasir'])) {
        $np  = trim($_POST['nama_produk'] ?? '');
        $cat = trim($_POST['kategori']    ?? '');
        $hrg = trim($_POST['harga']       ?? '');
        if (empty($np) || empty($cat) || empty($hrg)) {
            $form_error = 'Nama produk, kategori, dan harga wajib diisi.';
        } else {
            $form_success = 'Produk "'.htmlspecialchars($np).'" berhasil ditambahkan!';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel <?= $cfg['label'] ?> | Elea Store</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        html,body{height:100%;margin:0;}
        /* LAYOUT */
        .db-wrap{display:flex;min-height:100vh;font-size:.875rem;}
        /* SIDEBAR */
        .db-sidebar{width:210px;flex-shrink:0;display:flex;flex-direction:column;}
        .sb-brand{padding:1rem;border-bottom:1px solid rgba(255,255,255,.12);}
        .sb-logo-row{display:flex;align-items:center;gap:.5rem;margin-bottom:.625rem;}
        .sb-logo{width:30px;height:30px;border-radius:50%;background:rgba(255,255,255,.2);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:.875rem;color:white;}
        .sb-name{font-weight:700;font-size:.875rem;color:white;line-height:1.2;}
        .sb-sub{font-size:.65rem;color:rgba(255,255,255,.6);}
        .sb-user{display:flex;align-items:center;gap:.5rem;background:rgba(255,255,255,.12);border-radius:.625rem;padding:.5rem .625rem;}
        .sb-user .u-icon{font-size:1rem;}
        .sb-user .u-name{font-size:.75rem;font-weight:600;color:white;}
        .sb-user .u-role{font-size:.6rem;color:rgba(255,255,255,.6);}
        .sb-nav{flex:1;padding:.625rem;overflow-y:auto;}
        .sb-nav a{display:flex;align-items:center;gap:.625rem;padding:.5rem .625rem;border-radius:.625rem;color:rgba(255,255,255,.7);margin-bottom:2px;font-size:.8125rem;transition:all .15s;text-decoration:none;}
        .sb-nav a:hover{background:rgba(255,255,255,.12);color:white;}
        .sb-nav a.active{background:rgba(255,255,255,.22);color:white;font-weight:600;}
        .sb-nav a .n-icon{font-size:.9375rem;flex-shrink:0;}
        .sb-footer{padding:.75rem;border-top:1px solid rgba(255,255,255,.1);}
        .sb-footer a{display:flex;align-items:center;gap:.5rem;padding:.5rem .625rem;border-radius:.625rem;color:rgba(255,255,255,.65);font-size:.8125rem;text-decoration:none;transition:all .15s;}
        .sb-footer a:hover{background:rgba(255,255,255,.1);color:white;}
        /* MAIN */
        .db-main{flex:1;display:flex;flex-direction:column;background:#f9fafb;min-width:0;}
        .db-topbar{background:white;border-bottom:1px solid #e5e7eb;padding:.875rem 1.5rem;display:flex;align-items:center;justify-content:space-between;box-shadow:0 1px 3px rgba(0,0,0,.05);flex-shrink:0;}
        .db-topbar h1{font-weight:700;color:#1f2937;font-size:.9375rem;}
        .db-topbar p{font-size:.7rem;color:#9ca3af;margin-top:2px;}
        .topbar-right{display:flex;align-items:center;gap:.625rem;}
        .topbar-right a{font-size:.75rem;color:#9ca3af;border:1px solid #e5e7eb;padding:.25rem .75rem;border-radius:9999px;text-decoration:none;transition:color .15s;}
        .topbar-right a:hover{color:#374151;}
        .topbar-avatar{width:30px;height:30px;border-radius:50%;display:flex;align-items:center;justify-content:center;color:white;font-size:.875rem;}
        .db-content{flex:1;padding:1.5rem;overflow-y:auto;}
        /* STAT CARDS */
        .sg{display:grid;grid-template-columns:repeat(4,1fr);gap:.875rem;margin-bottom:1.25rem;}
        .sc{background:white;border-radius:1rem;padding:1.125rem;border:1px solid #e5e7eb;box-shadow:0 1px 4px rgba(0,0,0,.05);}
        .sc-row{display:flex;align-items:center;justify-content:space-between;margin-bottom:.625rem;}
        .sc-icon{font-size:1.25rem;}
        .sc-badge{font-size:.65rem;font-weight:700;padding:.15rem .4rem;border-radius:9999px;}
        .sc-val{font-size:1.125rem;font-weight:700;color:#1f2937;}
        .sc-lbl{font-size:.7rem;color:#9ca3af;margin-top:2px;}
        /* CONTENT CARDS */
        .cc{background:white;border-radius:1rem;border:1px solid #e5e7eb;padding:1.25rem;box-shadow:0 1px 4px rgba(0,0,0,.05);margin-bottom:1rem;}
        .cc h2{font-weight:700;color:#1f2937;font-size:.9375rem;margin-bottom:1rem;}
        /* TABLE */
        .dtbl{width:100%;border-collapse:collapse;}
        .dtbl th{text-align:left;font-size:.7rem;font-weight:600;color:#9ca3af;padding:.5rem .75rem;border-bottom:1px solid #f3f4f6;text-transform:uppercase;letter-spacing:.04em;}
        .dtbl td{padding:.625rem .75rem;border-bottom:1px solid #f9fafb;vertical-align:middle;font-size:.8125rem;}
        .dtbl tr:last-child td{border-bottom:none;}
        .dtbl tr:hover td{background:#f9fafb;}
        .tav{width:28px;height:28px;border-radius:50%;display:flex;align-items:center;justify-content:center;color:white;font-size:.7rem;font-weight:700;flex-shrink:0;}
        .t-name{font-weight:500;color:#374151;}
        .t-sub{font-size:.7rem;color:#9ca3af;}
        .t-bold{font-weight:700;color:#1f2937;}
        .tbadge{font-size:.65rem;font-weight:600;padding:.2rem .5rem;border-radius:9999px;}
        /* STATUS */
        .status-baru    {background:#fff8f6;color:#7a2e22;}
        .status-diproses{background:#fffbeb;color:#d97706;}
        .status-dikirim {background:#fff8f6;color:#7a2e22;}
        .status-selesai {background:#fff8f6;color:#4b5563;}
        .stok-ok {color:#7a2e22;background:#fff8f6;}
        .stok-low{color:#d97706;background:#fffbeb;}
        .stok-out{color:#dc2626;background:#fef2f2;}
        /* FORM */
        .fgrid{display:grid;grid-template-columns:1fr 1fr;gap:.875rem;}
        .fg{margin-bottom:0;}
        .fg label{display:block;font-size:.75rem;font-weight:600;margin-bottom:.375rem;color:#6b7280;}
        .fg input,.fg select,.fg textarea{width:100%;padding:.625rem .875rem;border:1px solid #e5e7eb;border-radius:.625rem;font-size:.8125rem;font-family:inherit;outline:none;background:#f9fafb;transition:box-shadow .15s;}
        .fg input:focus,.fg select:focus,.fg textarea:focus{box-shadow:0 0 0 3px rgba(122,46,34,.12);border-color:#f5d4cb;}
        .fg-full{grid-column:1/-1;}
        .btn-sm{padding:.5rem 1.25rem;border:none;border-radius:.625rem;font-size:.8125rem;font-weight:600;cursor:pointer;transition:opacity .2s;}
        .btn-sm:hover{opacity:.85;}
        /* LAPORAN */
        .lap-row{display:flex;justify-content:space-between;align-items:center;padding:.625rem 0;border-bottom:1px solid #f3f4f6;font-size:.8125rem;}
        .lap-row:last-child{border-bottom:none;}
        .lap-lbl{color:#6b7280;}
        .lap-val{font-weight:700;color:#1f2937;}
        .lap-up{color:#7a2e22;font-size:.7rem;font-weight:600;}
        /* ALERT */
        .alert-ok {background:#fff8f6;border:1px solid #f2e8dc;border-radius:.625rem;padding:.625rem 1rem;font-size:.8125rem;color:#4b5563;margin-bottom:.875rem;}
        .alert-err{background:#fef2f2;border:1px solid #fecaca;border-radius:.625rem;padding:.625rem 1rem;font-size:.8125rem;color:#dc2626;margin-bottom:.875rem;}

        @media(max-width:768px){.sg{grid-template-columns:repeat(2,1fr);}.fgrid{grid-template-columns:1fr;}.db-sidebar{display:none;}}
    </style>
</head>
<body>
<div class="db-wrap">

    <!-- SIDEBAR -->
    <aside class="db-sidebar" style="background:<?= $cfg['gradient'] ?>;">
        <div class="sb-brand">
            <div class="sb-logo-row">
                <div class="sb-logo">E</div>
                <div>
                    <div class="sb-name">Elea Store</div>
                    <div class="sb-sub">Panel <?= $cfg['label'] ?></div>
                </div>
            </div>
            <div class="sb-user">
                <span class="u-icon"><?= $cfg['icon'] ?></span>
                <div>
                    <div class="u-name"><?= htmlspecialchars($user['nama']) ?></div>
                    <div class="u-role"><?= ucfirst($role) ?> · Aktif</div>
                </div>
            </div>
        </div>
        <nav class="sb-nav">
            <?php foreach ($visible_menus as $m): ?>
            <a href="dashboard.php?page=<?= $m['key'] ?>" class="<?= $active_page === $m['key'] ? 'active' : '' ?>">
                <span class="n-icon"><?= $m['icon'] ?></span><?= $m['label'] ?>
            </a>
            <?php endforeach; ?>
        </nav>
        <div class="sb-footer">
            <a href="../index.php">
                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                Website
            </a>
            <a href="dashboard.php?logout=1">
                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                Keluar
            </a>
        </div>
    </aside>

    <!-- MAIN -->
    <div class="db-main">
        <div class="db-topbar">
            <div>
                <h1>
                    <?php
                    $found = array_filter(array_values($visible_menus), fn($m) => $m['key'] === $active_page);
                    echo $found ? array_values($found)[0]['label'] : 'Dashboard';
                    ?>
                </h1>
                <p><?= htmlspecialchars($user['nama']) ?> · <?= ucfirst($role) ?></p>
            </div>
            <div class="topbar-right">
                <a href="../index.php">← Website</a>
                <div class="topbar-avatar" style="background:<?= $cfg['gradient'] ?>;"><?= $cfg['icon'] ?></div>
            </div>
        </div>

        <div class="db-content">

            <?php if ($form_success): ?><div class="alert-ok"><i class="fas fa-check-circle"></i> <?= $form_success ?></div><?php endif; ?>
            <?php if ($form_error): ?><div class="alert-err"><i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($form_error) ?></div><?php endif; ?>

            <?php
            // ===================================================
            // DASHBOARD
            // ===================================================
            if ($active_page === 'dashboard'):
            ?>
            <div class="sg">
                <?php
                if ($role === 'owner') {
                    $stats = [
                        ['label'=>'Pendapatan Bulan Ini','value'=>'Rp 18.4jt','change'=>'+8%', 'icon'=>'<i class="fas fa-coins"></i>',         'pos'=>true],
                        ['label'=>'Laba Bersih',         'value'=>'Rp 12.2jt','change'=>'+14%','icon'=>'<i class="fas fa-chart-bar"></i>',    'pos'=>true],
                        ['label'=>'Total Penjualan',     'value'=>'247',       'change'=>'+12%','icon'=>'<i class="fas fa-box"></i>',           'pos'=>true],
                        ['label'=>'Pengeluaran',         'value'=>'Rp 6.2jt', 'change'=>'-3%', 'icon'=>'<i class="fas fa-money-bill-wave"></i>','pos'=>false],
                    ];
                } elseif ($role === 'kasir') {
                    $stats = [
                        ['label'=>'Pesanan Hari Ini',  'value'=>'12',       'change'=>'+3', 'icon'=>'<i class="fas fa-box"></i>',                  'pos'=>true],
                        ['label'=>'Pesanan Diproses',  'value'=>'4',        'change'=>'',   'icon'=>'<i class="fas fa-hourglass-half"></i>',        'pos'=>true],
                        ['label'=>'Produk Stok Habis', 'value'=>'1 produk', 'change'=>'!',  'icon'=>'<i class="fas fa-exclamation-triangle"></i>',  'pos'=>false],
                        ['label'=>'Dikirim Hari Ini',  'value'=>'8',        'change'=>'+2', 'icon'=>'<i class="fas fa-truck"></i>',                 'pos'=>true],
                    ];
                } else {
                    $stats = [
                        ['label'=>'Total Pesanan',        'value'=>'247',      'change'=>'+12%','icon'=>'<i class="fas fa-box"></i>',           'pos'=>true],
                        ['label'=>'Pendapatan Bulan Ini', 'value'=>'Rp 18.4jt','change'=>'+8%', 'icon'=>'<i class="fas fa-coins"></i>',         'pos'=>true],
                        ['label'=>'Produk Aktif',         'value'=>'84',       'change'=>'+3',  'icon'=>'<i class="fas fa-tshirt"></i>',         'pos'=>true],
                        ['label'=>'Pesanan Pending',      'value'=>'23',       'change'=>'-5%', 'icon'=>'<i class="fas fa-hourglass-half"></i>', 'pos'=>false],
                    ];
                }
                foreach ($stats as $s):
                ?>
                <div class="sc">
                    <div class="sc-row">
                        <span class="sc-icon"><?= $s['icon'] ?></span>
                        <?php if ($s['change']): ?>
                        <span class="sc-badge" style="<?= $s['pos'] ? 'background:#fff8f6;color:#4b5563;' : 'background:#fef2f2;color:#dc2626;' ?>"><?= $s['change'] ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="sc-val"><?= $s['value'] ?></div>
                    <div class="sc-lbl"><?= $s['label'] ?></div>
                </div>
                <?php endforeach; ?>
            </div>

            <?php if ($role === 'owner'): ?>
            <div style="background:linear-gradient(135deg,#7a2e22,#7a2e22);border-radius:1rem;padding:1.25rem;color:white;margin-bottom:1rem;display:flex;align-items:center;gap:1rem;">
                <div style="font-size:2rem;"><i class="fas fa-crown"></i></div>
                <div>
                    <div style="font-weight:700;font-size:1rem;">Selamat datang, Pemilik Toko!</div>
                    <div style="font-size:.8125rem;opacity:.85;margin-top:.25rem;">Gunakan menu di kiri untuk melihat laporan keuangan dan penjualan toko.</div>
                    <div style="margin-top:.75rem;display:flex;gap:.5rem;">
                        <a href="dashboard.php?page=laporan"      style="padding:.3rem .75rem;background:rgba(255,255,255,.2);border-radius:9999px;font-size:.75rem;color:white;font-weight:600;text-decoration:none;"><i class="fas fa-coins"></i> Laporan Keuangan</a>
                        <a href="dashboard.php?page=laporan_jual" style="padding:.3rem .75rem;background:rgba(255,255,255,.2);border-radius:9999px;font-size:.75rem;color:white;font-weight:600;text-decoration:none;"><i class="fas fa-chart-line"></i> Laporan Penjualan</a>
                    </div>
                </div>
            </div>
            <?php else: ?>
            <div class="cc">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;">
                    <h2 style="margin:0;">Pesanan Terbaru</h2>
                    <a href="dashboard.php?page=pesanan" style="font-size:.75rem;color:<?= $cfg['color'] ?>;font-weight:600;">Lihat Semua →</a>
                </div>
                <table class="dtbl">
                    <thead><tr><th>ID</th><th>Pelanggan</th><th>Produk</th><th>Total</th><th>Status</th><?php if (in_array($role,['admin','kasir'])): ?><th>Aksi</th><?php endif; ?></tr></thead>
                    <tbody>
                    <?php foreach (array_slice($orders,0,5) as $o): ?>
                    <tr>
                        <td class="t-sub"><?= $o['id'] ?></td>
                        <td>
                            <div style="display:flex;align-items:center;gap:.5rem;">
                                <div class="tav" style="background:<?= $cfg['gradient'] ?>;"><?= mb_substr($o['nama'],0,1) ?></div>
                                <span class="t-name"><?= htmlspecialchars($o['nama']) ?></span>
                            </div>
                        </td>
                        <td class="t-sub"><?= htmlspecialchars($o['produk']) ?></td>
                        <td class="t-bold"><?= $o['jumlah'] ?></td>
                        <td><span class="tbadge <?= $o['sc'] ?>"><?= $o['status'] ?></span></td>
                        <?php if (in_array($role,['admin','kasir'])): ?>
                        <td>
                            <select style="font-size:.7rem;padding:.25rem .375rem;border:1px solid #e5e7eb;border-radius:.375rem;background:white;cursor:pointer;">
                                <option>Ubah Status</option>
                                <option>Diproses</option>
                                <option>Dikirim</option>
                                <option>Selesai</option>
                            </select>
                        </td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>

            <?php
            // ===================================================
            // KELOLA PRODUK
            // ===================================================
            elseif ($active_page === 'produk' && in_array($role,['admin','kasir'])):
            ?>
            <div class="cc">
                <h2>Tambah Produk Baru</h2>
                <form method="POST" action="dashboard.php?page=produk">
                    <input type="hidden" name="aksi" value="tambah_produk">
                    <div class="fgrid">
                        <div class="fg"><label>Nama Produk *</label><input type="text" name="nama_produk" placeholder="Nama produk" required></div>
                        <div class="fg"><label>Kategori *</label>
                            <select name="kategori" required>
                                <option value="">-- Pilih Kategori --</option>
                                <?php foreach (['GAMIS','HIJAB','MUKENA','OUTER'] as $k): ?>
                                <option><?= $k ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="fg"><label>Harga (Rp) *</label><input type="text" name="harga" placeholder="Contoh: 150000" required></div>
                        <div class="fg"><label>Stok Awal</label><input type="number" name="stok" placeholder="0" min="0" value="0"></div>
                        <div class="fg fg-full"><label>Deskripsi</label><textarea name="deskripsi" rows="3" placeholder="Deskripsi singkat produk..."></textarea></div>
                    </div>
                    <div style="display:flex;gap:.625rem;margin-top:.75rem;">
                        <button type="submit" class="btn-sm" style="background:<?= $cfg['gradient'] ?>;color:white;">Simpan Produk</button>
                        <button type="reset"  class="btn-sm" style="background:#f3f4f6;color:#374151;">Reset</button>
                    </div>
                </form>
            </div>
            <div class="cc">
                <h2>Daftar Produk</h2>
                <table class="dtbl">
                    <thead><tr><th>#</th><th>Nama Produk</th><th>Kategori</th><th>Harga</th><th>Stok</th><th>Terjual</th><th>Aksi</th></tr></thead>
                    <tbody>
                    <?php foreach ($products_data as $i => $p): ?>
                    <tr>
                        <td class="t-sub"><?= $i+1 ?></td>
                        <td class="t-name"><?= htmlspecialchars($p['nama']) ?></td>
                        <td><span class="tbadge" style="background:#f3f4f6;color:#374151;"><?= $p['cat'] ?></span></td>
                        <td class="t-bold"><?= $p['harga'] ?></td>
                        <td><span class="tbadge <?= $p['stok']>10?'stok-ok':($p['stok']>0?'stok-low':'stok-out') ?>"><?= $p['stok']>0?$p['stok'].' unit':'Habis' ?></span></td>
                        <td class="t-sub"><?= $p['terjual'] ?> terjual</td>
                        <td>
                            <button class="btn-sm" style="background:#fff8f6;color:#7a2e22;padding:.25rem .625rem;font-size:.7rem;" onclick="alert('Edit produk — fitur database diisi saat implementasi')">Edit</button>
                            <button class="btn-sm" style="background:#fef2f2;color:#dc2626;padding:.25rem .625rem;font-size:.7rem;" onclick="confirm('Hapus produk ini?')">Hapus</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php
            // ===================================================
            // KELOLA STOK (kasir)
            // ===================================================
            elseif ($active_page === 'stok' && $role === 'kasir'):
            ?>
            <div class="cc">
                <h2>Update Stok Produk</h2>
                <table class="dtbl">
                    <thead><tr><th>Nama Produk</th><th>Kategori</th><th>Stok Saat Ini</th><th>Tambah Stok</th><th>Aksi</th></tr></thead>
                    <tbody>
                    <?php foreach ($products_data as $p): ?>
                    <tr>
                        <td class="t-name"><?= htmlspecialchars($p['nama']) ?></td>
                        <td><span class="tbadge" style="background:#f3f4f6;color:#374151;"><?= $p['cat'] ?></span></td>
                        <td><span class="tbadge <?= $p['stok']>10?'stok-ok':($p['stok']>0?'stok-low':'stok-out') ?>"><?= $p['stok']>0?$p['stok'].' unit':'Habis' ?></span></td>
                        <td><input type="number" min="1" placeholder="Jumlah" style="width:80px;padding:.25rem .5rem;border:1px solid #e5e7eb;border-radius:.375rem;font-size:.75rem;"></td>
                        <td><button class="btn-sm" style="background:<?= $cfg['gradient'] ?>;color:white;padding:.25rem .75rem;font-size:.75rem;" onclick="alert('Stok diperbarui — simpan ke DB saat implementasi')">Tambah</button></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php
            // ===================================================
            // PESANAN MASUK
            // ===================================================
            elseif ($active_page === 'pesanan' && in_array($role,['admin','kasir'])):
            ?>
            <div class="cc">
                <h2>Daftar Pesanan Masuk</h2>
                <table class="dtbl">
                    <thead><tr><th>ID Pesanan</th><th>Pelanggan</th><th>Produk</th><th>Total</th><th>Status</th><th>Ubah Status</th></tr></thead>
                    <tbody>
                    <?php foreach ($orders as $o): ?>
                    <tr>
                        <td class="t-sub"><?= $o['id'] ?></td>
                        <td>
                            <div style="display:flex;align-items:center;gap:.5rem;">
                                <div class="tav" style="background:<?= $cfg['gradient'] ?>;"><?= mb_substr($o['nama'],0,1) ?></div>
                                <span class="t-name"><?= htmlspecialchars($o['nama']) ?></span>
                            </div>
                        </td>
                        <td class="t-sub"><?= htmlspecialchars($o['produk']) ?></td>
                        <td class="t-bold"><?= $o['jumlah'] ?></td>
                        <td><span class="tbadge <?= $o['sc'] ?>"><?= $o['status'] ?></span></td>
                        <td>
                            <select style="font-size:.7rem;padding:.25rem .375rem;border:1px solid #e5e7eb;border-radius:.375rem;background:white;cursor:pointer;" onchange="alert('Status diperbarui: '+this.value)">
                                <option value="">Pilih Status</option>
                                <option>Diproses</option>
                                <option>Dikirim</option>
                                <option>Selesai</option>
                            </select>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php
            // ===================================================
            // DATA PELANGGAN (admin)
            // ===================================================
            elseif ($active_page === 'pelanggan' && $role === 'admin'):
            ?>
            <div class="cc">
                <h2>Data Pelanggan Terdaftar</h2>
                <table class="dtbl">
                    <thead><tr><th>Nama</th><th>Email</th><th>Telepon</th><th>Total Pesanan</th><th>Total Belanja</th></tr></thead>
                    <tbody>
                    <?php foreach ($customers as $c): ?>
                    <tr>
                        <td>
                            <div style="display:flex;align-items:center;gap:.5rem;">
                                <div class="tav" style="background:linear-gradient(135deg,#953b22,#c0663a);"><?= mb_substr($c['nama'],0,1) ?></div>
                                <span class="t-name"><?= htmlspecialchars($c['nama']) ?></span>
                            </div>
                        </td>
                        <td class="t-sub"><?= $c['email'] ?></td>
                        <td class="t-sub"><?= $c['telp'] ?></td>
                        <td><span class="tbadge" style="background:#fff8f6;color:#7a2e22;"><?= $c['pesanan'] ?> pesanan</span></td>
                        <td class="t-bold"><?= $c['total'] ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php
            // ===================================================
            // PENGIRIMAN
            // ===================================================
            elseif ($active_page === 'pengiriman' && in_array($role,['admin','kasir'])):
            ?>
            <div class="cc">
                <h2>Status Pengiriman</h2>
                <table class="dtbl">
                    <thead><tr><th>ID Pesanan</th><th>Penerima</th><th>Produk</th><th>Status</th><th>No. Resi</th><th>Aksi</th></tr></thead>
                    <tbody>
                    <?php foreach ($orders as $o): if ($o['status']==='Baru') continue; ?>
                    <tr>
                        <td class="t-sub"><?= $o['id'] ?></td>
                        <td class="t-name"><?= htmlspecialchars($o['nama']) ?></td>
                        <td class="t-sub"><?= htmlspecialchars($o['produk']) ?></td>
                        <td><span class="tbadge <?= $o['sc'] ?>"><?= $o['status'] ?></span></td>
                        <td><input type="text" placeholder="Isi no. resi" style="width:130px;padding:.25rem .5rem;border:1px solid #e5e7eb;border-radius:.375rem;font-size:.7rem;"></td>
                        <td><button class="btn-sm" style="background:<?= $cfg['gradient'] ?>;color:white;padding:.25rem .75rem;font-size:.7rem;" onclick="alert('Resi disimpan!')">Simpan</button></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php
            // ===================================================
            // LAPORAN KEUANGAN (owner + admin)
            // ===================================================
            elseif ($active_page === 'laporan' && in_array($role,['owner','admin'])):
            ?>
            <div class="sg" style="grid-template-columns:repeat(3,1fr);">
                <?php foreach ([['<i class="fas fa-coins"></i>','Pendapatan Bulan Ini','Rp 18.400.000','+8%',true],['<i class="fas fa-money-bill-wave"></i>','Pengeluaran','Rp 6.200.000','-3%',false],['<i class="fas fa-chart-bar"></i>','Laba Bersih','Rp 12.200.000','+14%',true]] as [$ic,$lb,$vl,$ch,$ps]): ?>
                <div class="sc"><div class="sc-row"><span class="sc-icon"><?= $ic ?></span><span class="sc-badge" style="<?= $ps?'background:#fff8f6;color:#4b5563;':'background:#fef2f2;color:#dc2626;' ?>"><?= $ch ?></span></div><div class="sc-val"><?= $vl ?></div><div class="sc-lbl"><?= $lb ?></div></div>
                <?php endforeach; ?>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
                <div class="cc" style="margin-bottom:0;">
                    <h2>Pendapatan per Bulan</h2>
                    <?php foreach ([['Januari 2026','Rp 15.200.000','+5%'],['Februari 2026','Rp 16.800.000','+10%'],['Maret 2026','Rp 17.500.000','+4%'],['April 2026','Rp 18.400.000','+5%']] as [$bln,$vl,$ch]): ?>
                    <div class="lap-row"><span class="lap-lbl"><?= $bln ?></span><div style="text-align:right;"><div class="lap-val"><?= $vl ?></div><div class="lap-up"><?= $ch ?></div></div></div>
                    <?php endforeach; ?>
                </div>
                <div class="cc" style="margin-bottom:0;">
                    <h2>Pengeluaran per Kategori</h2>
                    <?php foreach ([['Bahan Baku','Rp 3.500.000'],['Ongkos Jahit','Rp 1.200.000'],['Pengiriman','Rp 850.000'],['Marketing','Rp 650.000']] as [$kat,$vl]): ?>
                    <div class="lap-row"><span class="lap-lbl"><?= $kat ?></span><span class="lap-val"><?= $vl ?></span></div>
                    <?php endforeach; ?>
                </div>
            </div>

            <?php
            // ===================================================
            // LAPORAN PENJUALAN (owner + admin)
            // ===================================================
            elseif ($active_page === 'laporan_jual' && in_array($role,['owner','admin'])):
            ?>
            <div class="sg">
                <?php foreach ([['<i class="fas fa-box"></i>','Total Pesanan','247','+12%',true],['<i class="fas fa-check-circle"></i>','Pesanan Selesai','198','+9%',true],['<i class="fas fa-times-circle"></i>','Dibatalkan','12','-3%',false],['<i class="fas fa-star"></i>','Rating Rata-rata','4.8','+0.2',true]] as [$ic,$lb,$vl,$ch,$ps]): ?>
                <div class="sc"><div class="sc-row"><span class="sc-icon"><?= $ic ?></span><span class="sc-badge" style="<?= $ps?'background:#fff8f6;color:#4b5563;':'background:#fef2f2;color:#dc2626;' ?>"><?= $ch ?></span></div><div class="sc-val"><?= $vl ?></div><div class="sc-lbl"><?= $lb ?></div></div>
                <?php endforeach; ?>
            </div>
            <div class="cc">
                <h2>Produk Terlaris</h2>
                <table class="dtbl">
                    <thead><tr><th>Peringkat</th><th>Nama Produk</th><th>Kategori</th><th>Terjual</th><th>Est. Pendapatan</th></tr></thead>
                    <tbody>
                    <?php $sorted = $products_data; usort($sorted,fn($a,$b)=>$b['terjual']<=>$a['terjual']); foreach ($sorted as $i=>$p): ?>
                    <tr>
                        <td><span class="tbadge" style="<?= $i===0?'background:#fef9c3;color:#854d0e;':($i===1?'background:#f1f5f9;color:#475569;':($i===2?'background:#fdf4ff;color:#7a2e22;':'background:#f3f4f6;color:#374151;')) ?>">#<?= $i+1 ?></span></td>
                        <td class="t-name"><?= htmlspecialchars($p['nama']) ?></td>
                        <td><span class="tbadge" style="background:#f3f4f6;color:#374151;"><?= $p['cat'] ?></span></td>
                        <td class="t-bold"><?= $p['terjual'] ?> pcs</td>
                        <td class="t-bold" style="color:#7a2e22;"><?= $p['harga'] ?> × <?= $p['terjual'] ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php
            // ===================================================
            // PENGATURAN (admin)
            // ===================================================
            elseif ($active_page === 'pengaturan' && $role === 'admin'):
            ?>
            <div class="cc">
                <h2>Pengaturan Toko</h2>
                <div class="fgrid">
                    <div class="fg"><label>Nama Toko</label><input type="text" value="Elea Store"></div>
                    <div class="fg"><label>Tagline</label><input type="text" value="Fashion for All"></div>
                    <div class="fg"><label>Email</label><input type="email" value="eleastorefashion@gmail.com"></div>
                    <div class="fg"><label>No. WhatsApp</label><input type="text" value="0812-xxxx-xxxx"></div>
                    <div class="fg fg-full"><label>Alamat</label><textarea rows="2">Jakarta, Indonesia</textarea></div>
                </div>
                <div style="margin-top:.75rem;">
                    <button class="btn-sm" style="background:<?= $cfg['gradient'] ?>;color:white;" onclick="alert('Pengaturan disimpan!')">Simpan</button>
                </div>
            </div>

            <?php else: ?>
            <div style="text-align:center;padding:4rem 1rem;">
                <div style="font-size:2.5rem;margin-bottom:1rem;color:#9ca3af;"><i class="fas fa-lock"></i></div>
                <div style="font-weight:700;color:#374151;font-size:1rem;margin-bottom:.5rem;">Akses Ditolak</div>
                <div style="color:#9ca3af;font-size:.875rem;">Anda tidak memiliki izin untuk halaman ini.</div>
                <a href="dashboard.php" style="display:inline-block;margin-top:1rem;padding:.5rem 1.25rem;background:<?= $cfg['gradient'] ?>;color:white;border-radius:.625rem;font-size:.8125rem;font-weight:600;text-decoration:none;">← Kembali</a>
            </div>
            <?php endif; ?>

        </div>
    </div>
</div>
</body>
</html>
