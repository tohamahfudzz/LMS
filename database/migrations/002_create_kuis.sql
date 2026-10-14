
CREATE TABLE kuis (
    id_kuis INT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_kelas INT UNSIGNED NOT NULL,
    id_pengajar INT UNSIGNED NOT NULL,

    judul VARCHAR(200) NOT NULL,
    deskripsi TEXT NULL,

    waktu_mulai DATETIME NULL DEFAULT NULL,
    waktu_selesai DATETIME NULL DEFAULT NULL,
    durasi_menit SMALLINT UNSIGNED NOT NULL,

    status VARCHAR(20) NOT NULL DEFAULT 'draft',
    waktu_dibuat DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id_kuis),

    INDEX idx_kuis_kelas (id_kelas),
    INDEX idx_kuis_pengajar (id_pengajar),

    CONSTRAINT fk_kuis_kelas
        FOREIGN KEY (id_kelas)
        REFERENCES kelas (id_kelas)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    CONSTRAINT fk_kuis_pengajar
        FOREIGN KEY (id_pengajar)
        REFERENCES pengajar (id_pengajar)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
