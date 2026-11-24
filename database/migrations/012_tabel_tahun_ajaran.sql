CREATE TABLE tahun_ajaran (
    id_tahun_ajaran INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nama_tahun_ajaran VARCHAR(20) NOT NULL UNIQUE,
    tanggal_mulai DATE NOT NULL,
    tanggal_selesai DATE NOT NULL,
    status ENUM('aktif', 'nonaktif')
        NOT NULL DEFAULT 'nonaktif',
    dibuat_pada DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;