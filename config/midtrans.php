<?php
// Kunci Midtrans dibaca dari .env di root proyek.
// Salin .env.example → .env lalu isi dengan kunci Anda sendiri.

if (!function_exists('get_env_vars')) require_once __DIR__ . '/env.php';
$_env_mt = get_env_vars();

define('MIDTRANS_SERVER_KEY',    $_env_mt['MIDTRANS_SERVER_KEY']    ?? '');
define('MIDTRANS_CLIENT_KEY',    $_env_mt['MIDTRANS_CLIENT_KEY']    ?? '');
define('MIDTRANS_IS_PRODUCTION', ($_env_mt['MIDTRANS_IS_PRODUCTION'] ?? 'false') === 'true');

define('MIDTRANS_SNAP_API_URL', MIDTRANS_IS_PRODUCTION
    ? 'https://app.midtrans.com/snap/v1/transactions'
    : 'https://app.sandbox.midtrans.com/snap/v1/transactions');

define('MIDTRANS_SNAP_JS_URL', MIDTRANS_IS_PRODUCTION
    ? 'https://app.midtrans.com/snap/snap.js'
    : 'https://app.sandbox.midtrans.com/snap/snap.js');
