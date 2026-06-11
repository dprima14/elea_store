<?php
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'kasir') {
    header('Location: ../../login.php'); exit;
}
$user = $_SESSION['user'];
$cfg  = ['icon'=>'<i class="fas fa-receipt"></i>','label'=>'Kasir','gradient'=>'linear-gradient(135deg,#5c3320,#854040)','color'=>'#854040'];
$menus = [
    ['key'=>'index',           'label'=>'Dashboard',        'icon'=>'<i class="fas fa-chart-bar"></i>',       'href'=>'index.php'],
    ['key'=>'transaksi',       'label'=>'Transaksi',        'icon'=>'<i class="fas fa-receipt"></i>',         'href'=>'transaksi.php'],
    ['key'=>'kelola_penjualan','label'=>'Kelola Penjualan', 'icon'=>'<i class="fas fa-clipboard-list"></i>',  'href'=>'kelola_penjualan.php'],
    ['key'=>'preorder',        'label'=>'Pre Order',        'icon'=>'<i class="fas fa-box-open"></i>',        'href'=>'preorder.php'],
    ['key'=>'pesan_kontak',    'label'=>'Pesan Kontak',     'icon'=>'<i class="fas fa-envelope"></i>',         'href'=>'pesan_kontak.php'],
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title><?= $page_title ?? 'Kasir' ?> | Elea Store</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<style>
*{box-sizing:border-box;}
html,body{margin:0;padding:0;font-family:'Inter','Segoe UI',system-ui,-apple-system,sans-serif;background:#f9fafb;}
.db-wrap{display:block;font-size:1rem;}
.db-sidebar{position:fixed;top:0;left:0;bottom:0;width:240px;display:flex;flex-direction:column;overflow-y:auto;z-index:50;}
.sb-brand{padding:1.25rem;border-bottom:1px solid rgba(255,255,255,.12);}
.sb-logo-row{display:flex;align-items:center;gap:.625rem;margin-bottom:.75rem;}
.sb-logo{width:36px;height:36px;border-radius:50%;background:rgba(255,255,255,.2);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:1rem;color:white;}
.sb-name{font-weight:700;font-size:1rem;color:white;line-height:1.2;}
.sb-sub{font-size:.75rem;color:rgba(255,255,255,.6);}
.sb-user{display:flex;align-items:center;gap:.625rem;background:rgba(255,255,255,.12);border-radius:.625rem;padding:.625rem .75rem;}
.sb-user .u-icon{font-size:1.125rem;}
.sb-user .u-name{font-size:.875rem;font-weight:600;color:white;}
.sb-user .u-role{font-size:.7rem;color:rgba(255,255,255,.6);}
.sb-nav{flex:1;padding:.75rem;overflow-y:auto;}
.sb-nav a{display:flex;align-items:center;gap:.75rem;padding:.625rem .75rem;border-radius:.625rem;color:rgba(255,255,255,.7);margin-bottom:3px;font-size:.9375rem;transition:all .15s;text-decoration:none;}
.sb-nav a:hover{background:rgba(255,255,255,.12);color:white;}
.sb-nav a.active{background:rgba(255,255,255,.22);color:white;font-weight:600;}
.sb-nav a .n-icon{font-size:1.0625rem;flex-shrink:0;}
.sb-footer{padding:.875rem;border-top:1px solid rgba(255,255,255,.1);}
.sb-footer a{display:flex;align-items:center;gap:.625rem;padding:.625rem .75rem;border-radius:.625rem;color:rgba(255,255,255,.65);font-size:.875rem;text-decoration:none;transition:all .15s;}
.sb-footer a:hover{background:rgba(255,255,255,.1);color:white;}
.db-main{margin-left:240px;display:flex;flex-direction:column;min-height:100vh;background:#f9fafb;}
.db-topbar{background:white;border-bottom:1px solid #e5e7eb;padding:1rem 1.75rem;display:flex;align-items:center;justify-content:space-between;box-shadow:0 1px 3px rgba(0,0,0,.05);flex-shrink:0;}
.db-topbar h1{font-weight:700;color:#1f2937;font-size:1.125rem;}
.db-topbar p{font-size:.8125rem;color:#9ca3af;margin-top:2px;}
.topbar-right{display:flex;align-items:center;gap:.75rem;}
.topbar-right a{font-size:.875rem;color:#9ca3af;border:1px solid #e5e7eb;padding:.3rem .875rem;border-radius:9999px;text-decoration:none;transition:color .15s;}
.topbar-right a:hover{color:#374151;}
.topbar-avatar{width:34px;height:34px;border-radius:50%;display:flex;align-items:center;justify-content:center;color:white;font-size:1rem;}
.db-content{flex:1;padding:1.75rem;overflow-y:auto;}
.sg{display:grid;grid-template-columns:repeat(4,1fr);gap:1rem;margin-bottom:1.375rem;}
.sc{background:white;border-radius:1rem;padding:1.375rem;border:1px solid #e5e7eb;box-shadow:0 1px 4px rgba(0,0,0,.05);}
.sc-row{display:flex;align-items:center;justify-content:space-between;margin-bottom:.75rem;}
.sc-icon{font-size:1.5rem;}
.sc-badge{font-size:.75rem;font-weight:700;padding:.2rem .5rem;border-radius:9999px;}
.sc-val{font-size:1.375rem;font-weight:700;color:#1f2937;}
.sc-lbl{font-size:.8125rem;color:#9ca3af;margin-top:3px;}
.cc{background:white;border-radius:1rem;border:1px solid #e5e7eb;padding:1.5rem;box-shadow:0 1px 4px rgba(0,0,0,.05);margin-bottom:1.25rem;}
.cc h2{font-weight:700;color:#1f2937;font-size:1.0625rem;margin-bottom:1.125rem;}
.dtbl{width:100%;border-collapse:collapse;}
.dtbl th{text-align:left;font-size:.8rem;font-weight:600;color:#9ca3af;padding:.625rem .875rem;border-bottom:1px solid #f3f4f6;text-transform:uppercase;letter-spacing:.04em;}
.dtbl td{padding:.75rem .875rem;border-bottom:1px solid #f9fafb;vertical-align:middle;font-size:.9375rem;}
.dtbl tr:last-child td{border-bottom:none;}
.dtbl tr:hover td{background:#f9fafb;}
.tav{width:32px;height:32px;border-radius:50%;display:flex;align-items:center;justify-content:center;color:white;font-size:.75rem;font-weight:700;flex-shrink:0;}
.t-name{font-weight:500;color:#374151;}
.t-sub{font-size:.8rem;color:#9ca3af;}
.t-bold{font-weight:700;color:#1f2937;}
.tbadge{font-size:.75rem;font-weight:600;padding:.25rem .625rem;border-radius:9999px;}
.status-pending{background:#fffbeb;color:#d97706;}
.status-dikonfirmasi{background:#fff8f6;color:#4b5563;}
.status-ditolak{background:#fef2f2;color:#dc2626;}
.stok-ok{color:#7a2e22;background:#fff8f6;}
.stok-low{color:#d97706;background:#fffbeb;}
.stok-out{color:#dc2626;background:#fef2f2;}
.fgrid{display:grid;grid-template-columns:1fr 1fr;gap:.875rem;}
.fg{margin-bottom:0;}
.fg label{display:block;font-size:.875rem;font-weight:600;margin-bottom:.375rem;color:#6b7280;}
.fg input,.fg select,.fg textarea{width:100%;padding:.625rem .875rem;border:1px solid #e5e7eb;border-radius:.625rem;font-size:.9375rem;font-family:inherit;outline:none;background:#f9fafb;transition:box-shadow .15s;box-sizing:border-box;}
.fg input:focus,.fg select:focus,.fg textarea:focus{box-shadow:0 0 0 3px rgba(122,46,34,.12);border-color:#f5d4cb;}
.fg-full{grid-column:1/-1;}
.btn-sm{padding:.5rem 1.25rem;border:none;border-radius:.625rem;font-size:.9375rem;font-weight:600;cursor:pointer;transition:opacity .2s;font-family:inherit;}
.btn-sm:hover{opacity:.85;}
.alert-ok{background:#fff8f6;border:1px solid #f2e8dc;border-radius:.625rem;padding:.625rem 1rem;font-size:.9375rem;color:#4b5563;margin-bottom:.875rem;}
.alert-err{background:#fef2f2;border:1px solid #fecaca;border-radius:.625rem;padding:.625rem 1rem;font-size:.9375rem;color:#dc2626;margin-bottom:.875rem;}
.sb-toggle{display:none;align-items:center;justify-content:center;width:36px;height:36px;background:none;border:none;cursor:pointer;padding:.25rem;border-radius:.5rem;color:#374151;flex-shrink:0;line-height:1;}
.sb-toggle:hover{background:#f3f4f6;}
.sb-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.42);z-index:49;}
.sb-overlay.active{display:block;}
@media(max-width:768px){
.sb-toggle{display:flex;}
.db-sidebar{transform:translateX(-100%);transition:transform .28s ease;}
.db-sidebar.sb-open{transform:translateX(0);}
.db-main{margin-left:0;}
.db-topbar{padding:.75rem 1rem;}
.db-content{padding:1rem;}
.sg{grid-template-columns:repeat(2,1fr);}
.fgrid{grid-template-columns:1fr;}
.cc{overflow-x:auto;}
.topbar-right a{padding:.2rem .5rem;font-size:.8rem;}
}
@media(max-width:480px){
.sg{grid-template-columns:1fr;}
.db-topbar h1{font-size:1rem;}
.db-topbar>div:first-child p{display:none;}
}
input::placeholder,textarea::placeholder{color:#b8bfc9;opacity:1;}
input::-webkit-input-placeholder,textarea::-webkit-input-placeholder{color:#b8bfc9;opacity:1;}
input::-moz-placeholder,textarea::-moz-placeholder{color:#b8bfc9;opacity:1;}
</style>
</head>
<body>
<div class="db-wrap">
<aside class="db-sidebar" style="background:<?= $cfg['gradient'] ?>;">
    <div class="sb-brand">
        <div class="sb-logo-row">
            <div class="sb-logo">E</div>
            <div><div class="sb-name">Elea Store</div><div class="sb-sub">Panel <?= $cfg['label'] ?></div></div>
        </div>
        <div class="sb-user">
            <span class="u-icon"><?= $cfg['icon'] ?></span>
            <div>
                <div class="u-name"><?= htmlspecialchars($user['nama']) ?></div>
                <div class="u-role"><?= ucfirst($user['role']) ?> · Aktif</div>
            </div>
        </div>
    </div>
    <nav class="sb-nav">
        <?php foreach ($menus as $m): ?>
        <a href="<?= $m['href'] ?>" class="<?= ($page_key??'') === $m['key'] ? 'active' : '' ?>">
            <span class="n-icon"><?= $m['icon'] ?></span><?= $m['label'] ?>
        </a>
        <?php endforeach; ?>
    </nav>
    <div class="sb-footer">
        <a href="../index.php">
            <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
            Website
        </a>
        <a href="logout.php">
            <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
            Keluar
        </a>
    </div>
</aside>
<div class="sb-overlay" id="sbOverlay" onclick="toggleSidebar()"></div>
<div class="db-main">
<div class="db-topbar">
    <div style="display:flex;align-items:center;flex:1;min-width:0;gap:.25rem;">
        <button class="sb-toggle" onclick="toggleSidebar()" aria-label="Toggle menu">
            <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 6h16M4 12h16M4 18h16"/></svg>
        </button>
        <div style="min-width:0;">
            <h1 style="margin:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= $page_title ?? 'Dashboard' ?></h1>
            <p style="margin:0;"><?= htmlspecialchars($user['nama']) ?> · Kasir</p>
        </div>
    </div>
    <div class="topbar-right">
        <a href="../index.php">← Website</a>
        <div class="topbar-avatar" style="background:<?= $cfg['gradient'] ?>;"><?= $cfg['icon'] ?></div>
    </div>
</div>
<div class="db-content">
<script>
function toggleSidebar(){
    var sb=document.querySelector('.db-sidebar');
    var ov=document.getElementById('sbOverlay');
    var open=sb.classList.toggle('sb-open');
    ov.classList.toggle('active',open);
    document.body.style.overflow=open?'hidden':'';
}
document.querySelectorAll('.sb-nav a,.sb-footer a').forEach(function(a){
    a.addEventListener('click',function(){
        if(window.innerWidth<=768){
            document.querySelector('.db-sidebar').classList.remove('sb-open');
            var ov=document.getElementById('sbOverlay');if(ov)ov.classList.remove('active');
            document.body.style.overflow='';
        }
    });
});
document.addEventListener('keydown',function(e){
    if(e.key==='Escape'){
        document.querySelector('.db-sidebar').classList.remove('sb-open');
        var ov=document.getElementById('sbOverlay');if(ov)ov.classList.remove('active');
        document.body.style.overflow='';
    }
});
</script>
