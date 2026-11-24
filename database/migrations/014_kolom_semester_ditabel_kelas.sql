ALTER TABLE kelas
    ADD COLUMN semester ENUM('ganjil', 'genap') NULL AFTER id_tahun_ajaran;