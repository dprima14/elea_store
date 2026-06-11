<?php
// File testing sementara - hapus setelah selesai debug
require_once 'config/midtrans.php';

$body = [
    'transaction_details' => ['order_id' => 'TEST-' . time(), 'gross_amount' => 10000],
    'customer_details'    => ['first_name' => 'Test', 'phone' => '08123456789', 'email' => 'test@elea.store'],
    'item_details'        => [['id' => '1', 'price' => 10000, 'quantity' => 1, 'name' => 'Test Item']],
];

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
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => 0,
    CURLOPT_TIMEOUT        => 30,
]);
$result   = curl_exec($ch);
$err      = curl_error($ch);
$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo '<pre>';
echo "HTTP Code: $httpcode\n\n";
if ($err) echo "cURL Error: $err\n\n";
echo "Server Key (3 chars): " . substr(MIDTRANS_SERVER_KEY, 0, 20) . "...\n";
echo "Snap URL: " . MIDTRANS_SNAP_API_URL . "\n\n";
echo "Response:\n";
echo json_encode(json_decode($result), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
echo '</pre>';
?>
