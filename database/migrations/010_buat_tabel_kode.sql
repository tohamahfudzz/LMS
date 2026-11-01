CREATE TABLE kodetb (
    id INT AUTO_INCREMENT PRIMARY KEY,
    role ENUM('admin', 'pengajar', 'siswa') NOT NULL,
    kode VARCHAR(50) NOT NULL UNIQUE
);