ALTER TABLE percobaan_kuis
ADD UNIQUE KEY uq_kuis_siswa (id_kuis, id_siswa);