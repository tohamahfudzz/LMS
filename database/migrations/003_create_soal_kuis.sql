
CREATE TABLE soal_kuis (
    id_soal INT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_kuis INT UNSIGNED NOT NULL,

    tipe_soal VARCHAR(20) NOT NULL,
    soal TEXT NOT NULL,

    pilihan_a TEXT NULL,
    pilihan_b TEXT NULL,
    pilihan_c TEXT NULL,
    pilihan_d TEXT NULL,

    kunci_jawaban CHAR(1) NULL,
    poin DECIMAL(6,2) UNSIGNED NOT NULL DEFAULT 1.00,
    urutan SMALLINT UNSIGNED NOT NULL,

    PRIMARY KEY (id_soal),

    UNIQUE KEY uq_soal_kuis_urutan (id_kuis, urutan),

    CONSTRAINT fk_soal_kuis
        FOREIGN KEY (id_kuis)
        REFERENCES kuis (id_kuis)
        ON UPDATE CASCADE
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
