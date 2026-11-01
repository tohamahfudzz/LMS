-- UNIQUE INDEX untuk email siswa
ALTER TABLE siswa
ADD UNIQUE INDEX unique_email_siswa (email);

-- UNIQUE INDEX untuk email pengajar
ALTER TABLE pengajar
ADD UNIQUE INDEX unique_email_pengajar (email);

-- UNIQUE INDEX untuk email admin
ALTER TABLE admin
ADD UNIQUE INDEX unique_email_admin (email);