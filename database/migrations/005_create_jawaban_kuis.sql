
CREATE TABLE jawaban_kuis (
    id_jawaban INT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_percobaan INT UNSIGNED NOT NULL,
    id_soal INT UNSIGNED NOT NULL,

    jawaban TEXT NULL,
    nilai_diperoleh DECIMAL(6,2) UNSIGNED NULL DEFAULT NULL,

    status_penilaian VARCHAR(20) NOT NULL DEFAULT 'menunggu',

    id_penilai INT UNSIGNED NULL DEFAULT NULL,
    waktu_dinilai DATETIME NULL DEFAULT NULL,

    PRIMARY KEY (id_jawaban),

    UNIQUE KEY uq_jawaban_per_soal (
        id_percobaan,
        id_soal
    ),

    INDEX idx_jawaban_penilai (id_penilai),

    CONSTRAINT fk_jawaban_percobaan
        FOREIGN KEY (id_percobaan)
        REFERENCES percobaan_kuis (id_percobaan)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    CONSTRAINT fk_jawaban_soal
        FOREIGN KEY (id_soal)
        REFERENCES soal_kuis (id_soal)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    CONSTRAINT fk_jawaban_penilai
        FOREIGN KEY (id_penilai)
        REFERENCES pengajar (id_pengajar)
        ON UPDATE CASCADE
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
