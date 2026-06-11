<?php
// Kunci Midtrans dibaca dari .env di root proyek.
// Salin .env.example → .env lalu isi dengan kunci Anda sendiri.

$_envFile = __DIR__ . '/../.env';
if (file_exists($_envFile)) {
    foreach (file($_envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $_line) {
        if ($_line[0] === '#' || strpos($_line, '=') === false) continue;
        [$_k, $_v] = explode('=', $_line, 2);
        putenv(trim($_k) . '=' . trim($_v));
    }
}

define('MIDTRANS_SERVER_KEY',    getenv('MIDTRANS_SERVER_KEY')    ?: '');
define('MIDTRANS_CLIENT_KEY',    getenv('MIDTRANS_CLIENT_KEY')    ?: '');
define('MIDTRANS_IS_PRODUCTION', getenv('MIDTRANS_IS_PRODUCTION') === 'true');

define('MIDTRANS_SNAP_API_URL', MIDTRANS_IS_PRODUCTION
    ? 'https://app.midtrans.com/snap/v1/transactions'
    : 'https://app.sandbox.midtrans.com/snap/v1/transactions');

define('MIDTRANS_SNAP_JS_URL', MIDTRANS_IS_PRODUCTION
    ? 'https://app.midtrans.com/snap/snap.js'
    : 'https://app.sandbox.midtrans.com/snap/snap.js');
