
<?php

/**
 * Migration Runner LMS
 *
 * Menjalankan file SQL di database/migrations
 * yang belum tercatat di tabel migrations.
 */

declare(strict_types=1);

// ========================================
// 1. KONFIGURASI DATABASE
// ========================================

$host = '127.0.0.1';
$dbname = 'lmsdb';
$username = 'root';
$password = '';

$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$dbname;charset=$charset";

$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

// ========================================
// 2. LOKASI FOLDER MIGRATIONS
// ========================================

// __DIR__ = backend/migrations
// ../../database/migrations = database/migrations
$migrationsPath = __DIR__ . '/../../database/migrations';

// ========================================
// 3. CEK FOLDER MIGRATIONS
// ========================================

if (!is_dir($migrationsPath)) {
    exit("Folder migrations tidak ditemukan.\n");
}

$migrationFiles = glob($migrationsPath . '/*.sql');

if ($migrationFiles === false || count($migrationFiles) === 0) {
    exit("Tidak ada file migration yang perlu dijalankan.\n");
}

// Urutkan berdasarkan nama file
sort($migrationFiles, SORT_STRING);

echo "Folder migrations ditemukan.\n";
echo "Jumlah file SQL: " . count($migrationFiles) . "\n\n";

// ========================================
// 4. HUBUNGKAN KE DATABASE MYSQL
// ========================================

try {
    $pdo = new PDO($dsn, $username, $password, $options);

    echo "Berhasil terhubung ke database: $dbname\n\n";

} catch (PDOException $e) {
    exit("Gagal terhubung ke database: " . $e->getMessage() . "\n");
}

// ========================================
// 5. BUAT TABEL MIGRATIONS JIKA BELUM ADA
// ========================================

$pdo->exec("
    CREATE TABLE IF NOT EXISTS migrations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        migration VARCHAR(255) NOT NULL UNIQUE,
        batch INT NOT NULL,
        executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )
");

// ========================================
// 6. AMBIL MIGRATION YANG SUDAH DIJALANKAN
// ========================================

$stmt = $pdo->query("
    SELECT migration
    FROM migrations
");

$executedMigrations = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Ubah menjadi array untuk pengecekan cepat
$executedMigrations = array_flip($executedMigrations);

// ========================================
// 7. TENTUKAN BATCH BERIKUTNYA
// ========================================

$stmt = $pdo->query("
    SELECT COALESCE(MAX(batch), 0) AS last_batch
    FROM migrations
");

$lastBatch = (int) $stmt->fetchColumn();

$nextBatch = $lastBatch + 1;

$pendingCount = 0;

// ========================================
// 8. JALANKAN MIGRATION YANG BELUM ADA
// ========================================

foreach ($migrationFiles as $file) {

    $filename = basename($file);

    // Cek apakah migration sudah pernah dijalankan
    if (isset($executedMigrations[$filename])) {

        echo "[SUDAH] $filename\n";

        continue;
    }

    echo "[UPDATE] Menjalankan $filename...\n";

    try {

        // Baca isi file SQL
        $sql = file_get_contents($file);

        if ($sql === false) {
            throw new RuntimeException(
                "Tidak dapat membaca file $filename"
            );
        }

        // Mulai transaksi
        $pdo->beginTransaction();

        // Jalankan SQL migration
        $pdo->exec($sql);

        // Catat migration yang berhasil
        $stmt = $pdo->prepare("
            INSERT INTO migrations (migration, batch)
            VALUES (:migration, :batch)
        ");

        $stmt->execute([
            ':migration' => $filename,
            ':batch' => $nextBatch
        ]);

        // Simpan perubahan
        $pdo->commit();

        echo "[BERHASIL] $filename\n";

        $pendingCount++;

    } catch (Throwable $e) {

        // Batalkan perubahan jika masih dalam transaksi
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        echo "[GAGAL] $filename\n";
        echo "Alasan: " . $e->getMessage() . "\n";

        exit("Migration dihentikan.\n");
    }
}

// ========================================
// 9. HASIL AKHIR
// ========================================

echo "\n========================================\n";

if ($pendingCount === 0) {
    echo "Database sudah menggunakan migration terbaru.\n";
} else {
    echo "Jumlah migration baru: $pendingCount\n";
    echo "Database berhasil diperbarui.\n";
}

echo "========================================\n";