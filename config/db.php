<?php
// Konfigurasi koneksi database
define('DB_HOST', 'localhost');
define('DB_NAME', 'elea_store');
define('DB_USER', 'root');
define('DB_PASS', '');

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8",
        DB_USER,
        DB_PASS
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Koneksi database gagal: " . $e->getMessage());
}

// Auto-migrate: tabel gambar tambahan per produk
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS produk_gambar (
        id        INT AUTO_INCREMENT PRIMARY KEY,
        id_produk INT NOT NULL,
        gambar    VARCHAR(255) NOT NULL,
        urutan    INT NOT NULL DEFAULT 0,
        dibuat    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_pg_produk (id_produk)
    )");
} catch (PDOException $e) {}

function fmt_rp(int $n): string {
    return 'Rp ' . number_format($n, 0, ',', '.');
}
