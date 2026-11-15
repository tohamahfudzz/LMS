-- LOKASI FILE : database/migrations/011_create_password_change_tokens.sql
-- GUNA FILE   : Tabel token ganti password dari halaman profil (admin, pengajar,
--               siswa). Token dikaitkan ke role + id pengguna, jadi hanya berlaku
--               untuk akun yang memintanya. Yang disimpan adalah hash SHA-256 token,
--               bukan token aslinya.

CREATE TABLE IF NOT EXISTS password_change_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    role ENUM('admin', 'pengajar', 'siswa') NOT NULL,
    id_pengguna INT UNSIGNED NOT NULL,
    token CHAR(64) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_password_change_token (token),
    INDEX idx_password_change_pengguna (role, id_pengguna)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;