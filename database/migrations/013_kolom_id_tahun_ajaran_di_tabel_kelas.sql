ALTER TABLE kelas
ADD COLUMN id_tahun_ajaran INT UNSIGNED NULL,
ADD CONSTRAINT fk_kelas_tahun_ajaran
    FOREIGN KEY (id_tahun_ajaran)
    REFERENCES tahun_ajaran(id_tahun_ajaran)
    ON UPDATE CASCADE
    ON DELETE RESTRICT;