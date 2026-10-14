
CREATE TABLE percobaan_kuis (
    id_percobaan INT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_kuis INT UNSIGNED NOT NULL,
    id_siswa INT UNSIGNED NOT NULL,

    nomor_percobaan SMALLINT UNSIGNED NOT NULL DEFAULT 1,

    waktu_mulai DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    waktu_selesai DATETIME NULL DEFAULT NULL,

    status VARCHAR(20) NOT NULL DEFAULT 'berlangsung',

    nilai_otomatis DECIMAL(8,2) UNSIGNED NOT NULL DEFAULT 0.00,
    nilai_manual DECIMAL(8,2) UNSIGNED NOT NULL DEFAULT 0.00,
    nilai_total DECIMAL(8,2) UNSIGNED NULL DEFAULT NULL,

    PRIMARY KEY (id_percobaan),

    UNIQUE KEY uq_percobaan_siswa (
        id_kuis,
        id_siswa,
        nomor_percobaan
    ),

    INDEX idx_percobaan_siswa (id_siswa),

    CONSTRAINT fk_percobaan_kuis
        FOREIGN KEY (id_kuis)
        REFERENCES kuis (id_kuis)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    CONSTRAINT fk_percobaan_siswa
        FOREIGN KEY (id_siswa)
        REFERENCES siswa (id_siswa)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
