ALTER TABLE absensi
    ADD COLUMN mode ENUM('siswa', 'manual') NOT NULL DEFAULT 'siswa' AFTER status;

ALTER TABLE kehadiran
    ADD COLUMN catatan_siswa VARCHAR(255) NULL AFTER status,
    ADD COLUMN catatan_pengajar VARCHAR(255) NULL AFTER catatan_siswa;