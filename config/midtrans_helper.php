<?php
if (!defined('MIDTRANS_SERVER_KEY')) require_once __DIR__ . '/midtrans.php';

function midtrans_create_token(array $body): array {
    if (!function_exists('curl_init')) {
        return ['error' => 'cURL belum aktif. Aktifkan extension=curl di php.ini XAMPP.'];
    }
    $ch = curl_init(MIDTRANS_SNAP_API_URL);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($body),
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: Basic ' . base64_encode(MIDTRANS_SERVER_KEY . ':'),
        ],
        // SSL verification dinonaktifkan untuk XAMPP lokal
        // Aktifkan (set true) saat production
        CURLOPT_SSL_VERIFYPEER => MIDTRANS_IS_PRODUCTION,
        CURLOPT_SSL_VERIFYHOST => MIDTRANS_IS_PRODUCTION ? 2 : 0,
        CURLOPT_TIMEOUT        => 30,
    ]);
    $result   = curl_exec($ch);
    $err      = curl_error($ch);
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($err) return ['error' => 'Koneksi gagal: ' . $err];
    $data = json_decode($result, true);
    if (!$data) return ['error' => 'Response tidak valid dari Midtrans'];
    if ($httpcode !== 201) {
        $msg = $data['error_messages'][0] ?? $data['message'] ?? "Midtrans error (HTTP $httpcode)";
        return ['error' => $msg];
    }
    return $data;
}

function midtrans_verify_signature(string $order_id, string $status_code, string $gross_amount, string $given_sig): bool {
    $expected = hash('sha512', $order_id . $status_code . $gross_amount . MIDTRANS_SERVER_KEY);
    return hash_equals($expected, strtolower($given_sig));
}

function midtrans_get_status(string $order_id): array {
    if (!function_exists('curl_init')) return ['error' => 'cURL not available'];
    $base = MIDTRANS_IS_PRODUCTION
        ? 'https://api.midtrans.com'
        : 'https://api.sandbox.midtrans.com';
    $ch = curl_init($base . '/v2/' . rawurlencode($order_id) . '/status');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => [
            'Accept: application/json',
            'Authorization: Basic ' . base64_encode(MIDTRANS_SERVER_KEY . ':'),
        ],
        CURLOPT_SSL_VERIFYPEER => MIDTRANS_IS_PRODUCTION,
        CURLOPT_SSL_VERIFYHOST => MIDTRANS_IS_PRODUCTION ? 2 : 0,
        CURLOPT_TIMEOUT        => 15,
    ]);
    $result = curl_exec($ch);
    $err    = curl_error($ch);
    curl_close($ch);
    if ($err) return ['error' => $err];
    $data = json_decode($result, true);
    return $data ?: ['error' => 'Invalid response'];
}
